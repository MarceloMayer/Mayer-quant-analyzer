<?php

namespace App\Services\Portfolio;

use App\Models\Portfolio;
use App\Models\PortfolioStrategy;
use App\Models\Trade;
use App\Services\Charts\DatedLineChartBuilder;
use App\Services\Metrics\PortfolioAnalyzerService;
use App\Services\Metrics\PortfolioCorrelationService;
use Illuminate\Support\Facades\DB;

/**
 * Searches for per-strategy weights that improve a chosen objective for a portfolio,
 * keeping every enabled strategy in the mix (weights stay within [min, max]).
 *
 * Four objectives are supported, matching the metrics traders asked to steer by:
 *   - ulcer_index             → minimize (depth + duration of drawdowns)
 *   - positive_months_percent → maximize (share of months in the green)
 *   - net_profit_to_drawdown  → maximize (Recovery Factor: net profit / max drawdown)
 *   - max_drawdown_percent    → minimize (deepest peak-to-valley drop, as % of the peak)
 *
 * max_drawdown_percent (not the raw money drawdown) is what gets optimized here on
 * purpose: weights can only go down to 1 contract, never to 0, so once every strategy
 * is already at the floor, absolute-money drawdown can't be reduced further by the grid
 * search — scaling every weight down together always shrinks it, and there is nowhere
 * left to scale down to. The percentage figure is scale-invariant, so the search is free
 * to explore the RATIO between strategies (not just the overall exposure) and can surface
 * real diversification gains: two strategies whose drawdowns fall in different periods can
 * offset each other in the consolidated curve, which shows up as a lower percentage even
 * when nobody's weight is reduced.
 *
 * Weights are whole numbers only: a weight is a contract multiplier and BM&F mini
 * contracts (mini-índice / mini-dólar) trade in whole units, so 0.5 or 1.5 make no
 * physical sense. The grid steps by 1, and the suggested weights are reduced by their
 * greatest common divisor so the smallest ratio is returned (e.g. 2:4 → 1:2).
 *
 * The search runs a full grid for small portfolios and random-restart hill climbing
 * for larger ones, capped by a fixed evaluation budget. Reconstructing the weighted
 * equity curve is done in a single pass per evaluation (no array allocation) for speed;
 * the before/after metrics shown to the user are then produced by PortfolioAnalyzerService
 * (with the suggested weights as an override), so they are exactly what the results page
 * shows once the weights are applied.
 */
class PortfolioWeightOptimizerService
{
    public const OBJECTIVE_ULCER = 'ulcer_index';

    public const OBJECTIVE_POSITIVE_MONTHS = 'positive_months_percent';

    public const OBJECTIVE_RECOVERY_FACTOR = 'net_profit_to_drawdown';

    public const OBJECTIVE_MAX_DRAWDOWN = 'max_drawdown_percent';

    private const GRID_STEP = 1;

    private const GRID_MIN = 1;

    private const GRID_MAX = 6;

    private const MAX_EVALUATIONS = 2500;

    private const HILL_CLIMB_RESTARTS = 30;

    private const FULL_GRID_MAX_STRATEGIES = 4;

    private const FULL_GRID_MAX_POINTS = 4096;

    // Large enough to dominate any objective's scale, so a correlation cap violation always
    // loses to a feasible candidate but still gives the search a slope towards feasibility.
    private const CORRELATION_PENALTY = 1_000_000.0;

    private const CHART_CURRENT_COLOR = '#94a3b8';

    private const CHART_SUGGESTED_COLOR = '#2563eb';

    public function __construct(
        private readonly PortfolioAnalyzerService $analyzer,
        private readonly PortfolioCorrelationService $correlationService,
        private readonly PortfolioMetricsCache $metricsCache,
        private readonly DatedLineChartBuilder $chartBuilder,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function objectiveOptions(): array
    {
        return [
            self::OBJECTIVE_ULCER => 'Ulcer Index (menor é melhor)',
            self::OBJECTIVE_POSITIVE_MONTHS => '% de meses positivos (maior é melhor)',
            self::OBJECTIVE_RECOVERY_FACTOR => 'Fator de Recuperação (maior é melhor)',
            self::OBJECTIVE_MAX_DRAWDOWN => 'Drawdown Máximo % (menor é melhor)',
        ];
    }

    /**
     * @param  array{min?: int|float, max?: int|float}  $bounds
     * @param  array{max_weighted_correlation?: int|float|string|null, correlation_period?: string}  $constraints
     * @return array<string, mixed>
     */
    public function optimize(Portfolio $portfolio, string $objective, array $bounds = [], array $constraints = []): array
    {
        $objective = array_key_exists($objective, self::objectiveOptions()) ? $objective : self::OBJECTIVE_ULCER;
        $min = max(1, (int) round((float) ($bounds['min'] ?? self::GRID_MIN)));
        $max = max($min + self::GRID_STEP, (int) round((float) ($bounds['max'] ?? self::GRID_MAX)));

        $enabled = $portfolio->portfolioStrategies()
            ->where('enabled', true)
            ->with('strategy:id,name')
            ->orderBy('id')
            ->get()
            ->filter(fn (PortfolioStrategy $row): bool => $row->strategy !== null)
            ->values();

        if ($enabled->count() < 2) {
            return [
                'ok' => false,
                'message' => 'O portfólio precisa de pelo menos 2 estratégias ativas para otimizar os pesos.',
            ];
        }

        $strategyIds = $enabled->map(fn (PortfolioStrategy $row): int => (int) $row->strategy_id)->all();
        $trades = $this->orderedTrades($strategyIds);

        if ($trades === []) {
            return [
                'ok' => false,
                'message' => 'Nenhum trade fechado encontrado para as estratégias ativas deste portfólio.',
            ];
        }

        $baseline = (float) ($portfolio->initial_balance ?? 0);
        $grid = $this->gridValues($min, $max);

        // Actual stored weights drive the "current" metrics/display; a grid-snapped copy
        // is only the starting point for the search.
        $currentWeights = $enabled
            ->mapWithKeys(fn (PortfolioStrategy $row): array => [
                (int) $row->strategy_id => (float) ($row->weight ?? 1),
            ])
            ->all();
        $searchStart = array_map(fn (float $weight): float => $this->snapToGrid($weight, $grid), $currentWeights);

        // Pearson correlation doesn't depend on the weights, so the matrix is built once
        // here instead of inside the evaluation loop.
        $correlations = $this->correlationLookup(
            $portfolio,
            (string) ($constraints['correlation_period'] ?? PortfolioCorrelationService::PERIOD_DAILY),
        );
        $maxCorrelation = $this->normalizeMaxCorrelation($constraints['max_weighted_correlation'] ?? null);
        $constraintApplied = $maxCorrelation !== null && $correlations !== [];

        $evaluations = 0;
        $evaluate = function (array $weights) use ($trades, $baseline, $objective, $correlations, $maxCorrelation, $constraintApplied, &$evaluations): float {
            $evaluations++;

            $score = $this->objectiveValue($this->fastMetrics($trades, $weights, $baseline), $objective);

            if ($constraintApplied) {
                $excess = $this->weightedCorrelation($weights, $correlations) - $maxCorrelation;
                $score -= self::CORRELATION_PENALTY * max(0.0, $excess);
            }

            return $score;
        };

        $best = $this->search($strategyIds, $grid, $searchStart, $evaluate, $evaluations);

        $suggestedWeights = $this->normalize($best);

        $currentMetrics = $this->displayMetrics($this->analyzer->calculate($portfolio));
        $suggestedMetrics = $this->displayMetrics($this->analyzer->calculate($portfolio, $suggestedWeights));

        if ($correlations !== []) {
            $currentMetrics['weighted_correlation'] = round($this->weightedCorrelation($currentWeights, $correlations), 4);
            $suggestedMetrics['weighted_correlation'] = round($this->weightedCorrelation($suggestedWeights, $correlations), 4);
        }

        $strategies = $enabled->map(fn (PortfolioStrategy $row): array => [
            'strategy_id' => (int) $row->strategy_id,
            'name' => $row->strategy->name,
            'current_weight' => round((float) ($row->weight ?? 1), 2),
            'suggested_weight' => $suggestedWeights[(int) $row->strategy_id],
        ])->all();

        return [
            'ok' => true,
            'objective' => $objective,
            'objective_label' => self::objectiveOptions()[$objective],
            'bounds' => ['min' => $min, 'max' => $max],
            'evaluations' => $evaluations,
            'strategies' => $strategies,
            'current_metrics' => $currentMetrics,
            'suggested_metrics' => $suggestedMetrics,
            'improvement' => $this->improvement($objective, $currentMetrics, $suggestedMetrics),
            'changed' => $this->weightsChanged($strategies),
            'has_correlation' => $correlations !== [],
            'chart' => $this->chartBuilder->build([
                ['label' => 'Pesos atuais', 'color' => self::CHART_CURRENT_COLOR, 'points' => $this->equityPoints($trades, $currentWeights, $baseline)],
                ['label' => 'Pesos sugeridos', 'color' => self::CHART_SUGGESTED_COLOR, 'points' => $this->equityPoints($trades, $suggestedWeights, $baseline)],
            ]),
            'constraint' => [
                'max_weighted_correlation' => $maxCorrelation,
                'applied' => $constraintApplied,
                'satisfied' => ! $constraintApplied
                    || ($suggestedMetrics['weighted_correlation'] ?? 0.0) <= $maxCorrelation + 1e-9,
            ],
        ];
    }

    /**
     * Writes suggested weights onto the portfolio's strategies. Goes through the model (not a bulk
     * update) so the PortfolioStrategy hooks keep enforcing ownership and busting the metrics cache.
     * Strategies that are no longer part of the portfolio are skipped.
     *
     * @param  array<int, array{strategy_id: int|string, suggested_weight: int|float|string}>  $strategies
     * @return int Number of portfolio strategies updated.
     */
    public function applyWeights(Portfolio $portfolio, array $strategies): int
    {
        $updated = 0;

        DB::transaction(function () use ($portfolio, $strategies, &$updated): void {
            foreach ($strategies as $strategy) {
                PortfolioStrategy::query()
                    ->where('portfolio_id', $portfolio->getKey())
                    ->where('strategy_id', (int) $strategy['strategy_id'])
                    ->each(function (PortfolioStrategy $portfolioStrategy) use ($strategy, &$updated): void {
                        $portfolioStrategy->weight = (float) $strategy['suggested_weight'];
                        $portfolioStrategy->save();
                        $updated++;
                    });
            }
        });

        // The per-row model hook already forgets the cache, but inside the transaction: a concurrent
        // request could re-cache the old weights before commit. Forget again once they are durable.
        $this->metricsCache->forgetPortfolio((int) $portfolio->getKey());

        return $updated;
    }

    /**
     * Builds [rowStrategyId][columnStrategyId] => correlation from the service's positional
     * matrix. Empty when the portfolio doesn't have enough strategies to correlate.
     *
     * @return array<int, array<int, float>>
     */
    private function correlationLookup(Portfolio $portfolio, string $period): array
    {
        $data = $this->correlationService->calculate(
            $portfolio,
            $period,
            PortfolioCorrelationService::METRIC_PROFIT_LOSS,
        );

        if (($data['has_enough_strategies'] ?? false) !== true) {
            return [];
        }

        $columnIds = array_map(fn (array $strategy): int => (int) $strategy['id'], $data['strategies'] ?? []);
        $lookup = [];

        foreach ($data['matrix'] ?? [] as $row) {
            $rowId = (int) ($row['strategy']['id'] ?? 0);

            foreach ($row['cells'] ?? [] as $index => $cell) {
                $columnId = $columnIds[$index] ?? null;

                if ($columnId === null || $columnId === $rowId) {
                    continue;
                }

                $lookup[$rowId][$columnId] = (float) ($cell['value'] ?? 0.0);
            }
        }

        return $lookup;
    }

    private function normalizeMaxCorrelation(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0.0, min(1.0, (float) $value));
    }

    /**
     * Weight-averaged pairwise correlation: sum(wi*wj*rho_ij) / sum(wi*wj) over pairs i<j.
     * Signed on purpose — negatively correlated pairs pull the figure down (good diversification).
     *
     * @param  array<int, float>  $weights
     * @param  array<int, array<int, float>>  $correlations
     */
    private function weightedCorrelation(array $weights, array $correlations): float
    {
        $ids = array_keys($weights);
        $count = count($ids);
        $numerator = 0.0;
        $denominator = 0.0;

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pairWeight = $weights[$ids[$i]] * $weights[$ids[$j]];
                $numerator += $pairWeight * ($correlations[$ids[$i]][$ids[$j]] ?? 0.0);
                $denominator += $pairWeight;
            }
        }

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    /**
     * @param  int[]  $strategyIds
     * @return array<int, array{strategy_id: int, net_profit: float, month_key: string, day_key: string}>
     */
    private function orderedTrades(array $strategyIds): array
    {
        return Trade::query()
            ->whereIn('strategy_id', $strategyIds)
            ->whereNotNull('exit_time')
            ->orderBy('exit_time')
            ->orderBy('id')
            ->get(['id', 'strategy_id', 'exit_time', 'net_profit'])
            ->map(fn (Trade $trade): array => [
                'strategy_id' => (int) $trade->strategy_id,
                'net_profit' => (float) $trade->net_profit,
                'month_key' => $trade->exit_time?->format('Y-m') ?? '',
                'day_key' => $trade->exit_time?->format('Y-m-d') ?? '',
            ])
            ->all();
    }

    /**
     * End-of-day equity (baseline + cumulative weighted P&L) for the comparison chart. One point
     * per day keeps the payload small: the suggestion lives in a public Livewire property.
     *
     * @param  array<int, array{strategy_id: int, net_profit: float, month_key: string, day_key: string}>  $trades
     * @param  array<int, float>  $weights
     * @return array<int, array{date: string, equity: float}>
     */
    private function equityPoints(array $trades, array $weights, float $baseline): array
    {
        $cum = 0.0;
        $byDay = [];

        foreach ($trades as $trade) {
            $cum += $trade['net_profit'] * ($weights[$trade['strategy_id']] ?? 0.0);

            if ($trade['day_key'] !== '') {
                $byDay[$trade['day_key']] = round($baseline + $cum, 2);
            }
        }

        $points = [];

        foreach ($byDay as $day => $equity) {
            $points[] = ['date' => (string) $day, 'equity' => $equity];
        }

        return $points;
    }

    /**
     * Single-pass weighted equity reconstruction. Returns just what the objective needs.
     *
     * @param  array<int, array{strategy_id: int, net_profit: float, month_key: string, day_key: string}>  $trades
     * @param  array<int, float>  $weights
     * @return array{ulcer_index: float, positive_months_percent: float, net_profit: float, max_drawdown: float, max_drawdown_percent: float}
     */
    private function fastMetrics(array $trades, array $weights, float $baseline): array
    {
        $cum = 0.0;
        $peak = $baseline;
        $sumDrawdownSquared = 0.0;
        $maxDrawdown = 0.0;
        $maxDrawdownPercent = 0.0;
        $points = 0;
        $months = [];

        foreach ($trades as $trade) {
            $weighted = $trade['net_profit'] * ($weights[$trade['strategy_id']] ?? 0.0);
            $cum += $weighted;

            if ($trade['month_key'] !== '') {
                $months[$trade['month_key']] = ($months[$trade['month_key']] ?? 0.0) + $weighted;
            }

            $equity = $baseline + $cum;
            $peak = max($peak, $equity);
            $drawdown = $peak - $equity;
            $maxDrawdown = max($maxDrawdown, $drawdown);
            $drawdownPercent = $peak > 0 ? ($drawdown / $peak) * 100 : 0.0;
            $maxDrawdownPercent = max($maxDrawdownPercent, $drawdownPercent);
            $sumDrawdownSquared += $drawdownPercent ** 2;
            $points++;
        }

        $monthsWithTrades = count($months);
        $positiveMonths = count(array_filter($months, fn (float $value): bool => $value > 0));

        return [
            'ulcer_index' => $points > 0 ? sqrt($sumDrawdownSquared / $points) : 0.0,
            'positive_months_percent' => $monthsWithTrades > 0 ? ($positiveMonths / $monthsWithTrades) * 100 : 0.0,
            'net_profit' => $cum,
            'max_drawdown' => $maxDrawdown,
            'max_drawdown_percent' => $maxDrawdownPercent,
        ];
    }

    /**
     * Maps the portfolio analyzer's output (the same numbers the results page shows) onto the keys the
     * suggestion panel renders, so "Sugerido" is exactly what the page shows after the weights are applied.
     * profit_factor stays null when there are no losing trades, as on the page.
     *
     * @param  array<string, mixed>  $analysis
     * @return array<string, float|null>
     */
    private function displayMetrics(array $analysis): array
    {
        $daily = $analysis['daily_performance'] ?? [];

        return [
            'net_profit' => (float) $analysis['net_profit'],
            'max_drawdown' => (float) $analysis['max_drawdown'],
            'max_drawdown_percent' => (float) $analysis['max_drawdown_percent'],
            'ulcer_index' => (float) $analysis['ulcer_index'],
            'equity_r2' => (float) $analysis['equity_r2'],
            'net_profit_to_drawdown' => (float) $analysis['net_profit_to_drawdown'],
            'positive_months_percent' => (float) $analysis['positive_months_percent'],
            'profit_factor' => $analysis['profit_factor'] === null ? null : (float) $analysis['profit_factor'],
            'positive_days' => (float) ($daily['positive_days'] ?? 0),
            'negative_days' => (float) ($daily['negative_days'] ?? 0),
        ];
    }

    /**
     * @param  array{ulcer_index: float, positive_months_percent: float, net_profit: float, max_drawdown: float, max_drawdown_percent: float}  $metrics
     */
    private function objectiveValue(array $metrics, string $objective): float
    {
        // Higher is always better here. A tiny net-profit term breaks ties without
        // overriding the primary objective.
        $tieBreak = $metrics['net_profit'] * 1e-9;

        return match ($objective) {
            self::OBJECTIVE_POSITIVE_MONTHS => $metrics['positive_months_percent'] + $tieBreak,
            self::OBJECTIVE_RECOVERY_FACTOR => ($metrics['max_drawdown'] > 0
                ? $metrics['net_profit'] / $metrics['max_drawdown']
                : ($metrics['net_profit'] > 0 ? 100.0 : 0.0)) + $tieBreak,
            // Percentage, not money: money drawdown can only shrink by scaling every
            // weight down together, and weights are already floored at 1 contract, so
            // that path is closed off. The percentage is scale-invariant, which lets the
            // search reward a better RATIO between strategies — i.e. real diversification.
            self::OBJECTIVE_MAX_DRAWDOWN => -$metrics['max_drawdown_percent'] + $tieBreak,
            default => -$metrics['ulcer_index'] + $tieBreak,
        };
    }

    /**
     * @param  int[]  $strategyIds
     * @param  float[]  $grid
     * @param  array<int, float>  $currentWeights
     * @return array<int, float>
     */
    private function search(array $strategyIds, array $grid, array $currentWeights, callable $evaluate, int &$evaluations): array
    {
        $gridPoints = count($grid) ** count($strategyIds);

        if (count($strategyIds) <= self::FULL_GRID_MAX_STRATEGIES && $gridPoints <= self::FULL_GRID_MAX_POINTS) {
            return $this->fullGridSearch($strategyIds, $grid, $currentWeights, $evaluate);
        }

        return $this->hillClimb($strategyIds, $grid, $currentWeights, $evaluate, $evaluations);
    }

    /**
     * @param  int[]  $strategyIds
     * @param  float[]  $grid
     * @param  array<int, float>  $currentWeights
     * @return array<int, float>
     */
    private function fullGridSearch(array $strategyIds, array $grid, array $currentWeights, callable $evaluate): array
    {
        $bestWeights = $currentWeights;
        $bestScore = $evaluate($currentWeights);

        $combinations = [[]];

        foreach ($strategyIds as $strategyId) {
            $next = [];

            foreach ($combinations as $prefix) {
                foreach ($grid as $value) {
                    $next[] = $prefix + [$strategyId => $value];
                }
            }

            $combinations = $next;
        }

        foreach ($combinations as $weights) {
            $score = $evaluate($weights);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestWeights = $weights;
            }
        }

        return $bestWeights;
    }

    /**
     * @param  int[]  $strategyIds
     * @param  float[]  $grid
     * @param  array<int, float>  $currentWeights
     * @return array<int, float>
     */
    private function hillClimb(array $strategyIds, array $grid, array $currentWeights, callable $evaluate, int &$evaluations): array
    {
        $bestWeights = $currentWeights;
        $bestScore = $evaluate($currentWeights);

        for ($restart = 0; $restart <= self::HILL_CLIMB_RESTARTS; $restart++) {
            $weights = $restart === 0
                ? $currentWeights
                : $this->randomWeights($strategyIds, $grid);
            $score = $evaluate($weights);

            $improved = true;

            while ($improved && $evaluations < self::MAX_EVALUATIONS) {
                $improved = false;

                foreach ($strategyIds as $strategyId) {
                    foreach ($grid as $value) {
                        if ($value === $weights[$strategyId]) {
                            continue;
                        }

                        $candidate = $weights;
                        $candidate[$strategyId] = $value;
                        $candidateScore = $evaluate($candidate);

                        if ($candidateScore > $score) {
                            $weights = $candidate;
                            $score = $candidateScore;
                            $improved = true;
                        }

                        if ($evaluations >= self::MAX_EVALUATIONS) {
                            break 2;
                        }
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestWeights = $weights;
            }

            if ($evaluations >= self::MAX_EVALUATIONS) {
                break;
            }
        }

        return $bestWeights;
    }

    /**
     * @param  int[]  $strategyIds
     * @param  float[]  $grid
     * @return array<int, float>
     */
    private function randomWeights(array $strategyIds, array $grid): array
    {
        $weights = [];

        foreach ($strategyIds as $strategyId) {
            $weights[$strategyId] = $grid[array_rand($grid)];
        }

        return $weights;
    }

    /**
     * Whole-number weights only (contract multipliers), from $min to $max inclusive.
     *
     * @return float[]
     */
    private function gridValues(int $min, int $max): array
    {
        $values = [];

        for ($value = $min; $value <= $max; $value++) {
            $values[] = (float) $value;
        }

        return $values === [] ? [1.0] : $values;
    }

    /**
     * @param  float[]  $grid
     */
    private function snapToGrid(float $weight, array $grid): float
    {
        $closest = $grid[0];

        foreach ($grid as $value) {
            if (abs($value - $weight) < abs($closest - $weight)) {
                $closest = $value;
            }
        }

        return $closest;
    }

    /**
     * Reduces whole-number weights by their greatest common divisor, so the smallest
     * equivalent integer ratio is returned (e.g. [2, 4, 6] → [1, 2, 3]).
     *
     * @param  array<int, float>  $weights
     * @return array<int, float>
     */
    private function normalize(array $weights): array
    {
        $ints = array_map(static fn (float $value): int => max(1, (int) round($value)), $weights);
        $divisor = array_reduce($ints, fn (int $carry, int $value): int => $this->gcd($carry, $value), 0);

        if ($divisor <= 1) {
            return array_map(static fn (int $value): float => (float) $value, $ints);
        }

        return array_map(static fn (int $value): float => (float) intdiv($value, $divisor), $ints);
    }

    private function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);

        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }

    /**
     * @param  array<string, float>  $current
     * @param  array<string, float>  $suggested
     * @return array{metric: string, current: float, suggested: float, delta: float, better: bool}
     */
    private function improvement(string $objective, array $current, array $suggested): array
    {
        if ($objective === self::OBJECTIVE_POSITIVE_MONTHS) {
            $delta = round($suggested['positive_months_percent'] - $current['positive_months_percent'], 2);

            return [
                'metric' => 'positive_months_percent',
                'current' => $current['positive_months_percent'],
                'suggested' => $suggested['positive_months_percent'],
                'delta' => $delta,
                'better' => $delta > 0,
            ];
        }

        if ($objective === self::OBJECTIVE_RECOVERY_FACTOR) {
            $delta = round($suggested['net_profit_to_drawdown'] - $current['net_profit_to_drawdown'], 2);

            return [
                'metric' => 'net_profit_to_drawdown',
                'current' => $current['net_profit_to_drawdown'],
                'suggested' => $suggested['net_profit_to_drawdown'],
                'delta' => $delta,
                'better' => $delta > 0,
            ];
        }

        if ($objective === self::OBJECTIVE_MAX_DRAWDOWN) {
            $delta = round($suggested['max_drawdown_percent'] - $current['max_drawdown_percent'], 2);

            return [
                'metric' => 'max_drawdown_percent',
                'current' => $current['max_drawdown_percent'],
                'suggested' => $suggested['max_drawdown_percent'],
                'delta' => $delta,
                'better' => $delta < 0,
            ];
        }

        $delta = round($suggested['ulcer_index'] - $current['ulcer_index'], 4);

        return [
            'metric' => 'ulcer_index',
            'current' => $current['ulcer_index'],
            'suggested' => $suggested['ulcer_index'],
            'delta' => $delta,
            'better' => $delta < 0,
        ];
    }

    /**
     * @param  array<int, array{current_weight: float, suggested_weight: float}>  $strategies
     */
    private function weightsChanged(array $strategies): bool
    {
        foreach ($strategies as $strategy) {
            if (abs($strategy['current_weight'] - $strategy['suggested_weight']) >= 0.01) {
                return true;
            }
        }

        return false;
    }
}
