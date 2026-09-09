<?php

namespace App\Services\Portfolio;

use App\Models\Portfolio;
use App\Models\PortfolioStrategy;
use App\Models\Trade;
use App\Services\Metrics\DrawdownCalculator;

/**
 * Searches for per-strategy weights that improve a chosen objective for a portfolio,
 * keeping every enabled strategy in the mix (weights stay within [min, max]).
 *
 * Two objectives are supported, matching the metrics traders asked to steer by:
 *   - ulcer_index            → minimize (depth + duration of drawdowns)
 *   - positive_months_percent → maximize (share of months in the green)
 *
 * The search runs a full grid for small portfolios and random-restart hill climbing
 * for larger ones, capped by a fixed evaluation budget. Reconstructing the weighted
 * equity curve is done in a single pass per evaluation (no array allocation) for speed;
 * the before/after metrics shown to the user are then recomputed once with the real
 * calculators so they line up with the rest of the app.
 */
class PortfolioWeightOptimizerService
{
    public const OBJECTIVE_ULCER = 'ulcer_index';

    public const OBJECTIVE_POSITIVE_MONTHS = 'positive_months_percent';

    private const GRID_STEP = 0.5;

    private const GRID_MIN = 0.5;

    private const GRID_MAX = 3.0;

    private const MAX_EVALUATIONS = 2500;

    private const HILL_CLIMB_RESTARTS = 30;

    private const FULL_GRID_MAX_STRATEGIES = 4;

    public function __construct(
        private readonly DrawdownCalculator $drawdownCalculator,
        private readonly UlcerIndexCalculator $ulcerIndexCalculator,
        private readonly LinearRegressionService $linearRegressionService,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function objectiveOptions(): array
    {
        return [
            self::OBJECTIVE_ULCER => 'Ulcer Index (menor é melhor)',
            self::OBJECTIVE_POSITIVE_MONTHS => '% de meses positivos (maior é melhor)',
        ];
    }

    /**
     * @param  array{min?: float, max?: float}  $bounds
     * @return array<string, mixed>
     */
    public function optimize(Portfolio $portfolio, string $objective, array $bounds = []): array
    {
        $objective = array_key_exists($objective, self::objectiveOptions()) ? $objective : self::OBJECTIVE_ULCER;
        $min = max(0.0, (float) ($bounds['min'] ?? self::GRID_MIN));
        $max = max($min + self::GRID_STEP, (float) ($bounds['max'] ?? self::GRID_MAX));

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
        $currentWeights = $enabled
            ->mapWithKeys(fn (PortfolioStrategy $row): array => [
                (int) $row->strategy_id => $this->snapToGrid((float) ($row->weight ?? 1), $grid),
            ])
            ->all();

        $evaluations = 0;
        $evaluate = function (array $weights) use ($trades, $baseline, $objective, &$evaluations): float {
            $evaluations++;

            return $this->objectiveValue($this->fastMetrics($trades, $weights, $baseline), $objective);
        };

        $best = $this->search($strategyIds, $grid, $currentWeights, $evaluate, $evaluations);

        $suggestedWeights = $this->normalize($best);

        $currentMetrics = $this->fullMetrics($trades, $currentWeights, $baseline);
        $suggestedMetrics = $this->fullMetrics($trades, $suggestedWeights, $baseline);

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
            'evaluations' => $evaluations,
            'strategies' => $strategies,
            'current_metrics' => $currentMetrics,
            'suggested_metrics' => $suggestedMetrics,
            'improvement' => $this->improvement($objective, $currentMetrics, $suggestedMetrics),
            'changed' => $this->weightsChanged($strategies),
        ];
    }

    /**
     * @param  int[]  $strategyIds
     * @return array<int, array{strategy_id: int, net_profit: float, month_key: string}>
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
            ])
            ->all();
    }

    /**
     * Single-pass weighted equity reconstruction. Returns just what the objective needs.
     *
     * @param  array<int, array{strategy_id: int, net_profit: float, month_key: string}>  $trades
     * @param  array<int, float>  $weights
     * @return array{ulcer_index: float, positive_months_percent: float, net_profit: float}
     */
    private function fastMetrics(array $trades, array $weights, float $baseline): array
    {
        $cum = 0.0;
        $peak = $baseline;
        $sumDrawdownSquared = 0.0;
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
            $drawdownPercent = $peak > 0 ? (($peak - $equity) / $peak) * 100 : 0.0;
            $sumDrawdownSquared += $drawdownPercent ** 2;
            $points++;
        }

        $monthsWithTrades = count($months);
        $positiveMonths = count(array_filter($months, fn (float $value): bool => $value > 0));

        return [
            'ulcer_index' => $points > 0 ? sqrt($sumDrawdownSquared / $points) : 0.0,
            'positive_months_percent' => $monthsWithTrades > 0 ? ($positiveMonths / $monthsWithTrades) * 100 : 0.0,
            'net_profit' => $cum,
        ];
    }

    /**
     * Recomputes the metrics shown to the user with the same calculators the rest of the app uses.
     *
     * @param  array<int, array{strategy_id: int, net_profit: float, month_key: string}>  $trades
     * @param  array<int, float>  $weights
     * @return array<string, float>
     */
    private function fullMetrics(array $trades, array $weights, float $baseline): array
    {
        $cum = 0.0;
        $curve = [];
        $months = [];

        foreach ($trades as $trade) {
            $weighted = $trade['net_profit'] * ($weights[$trade['strategy_id']] ?? 0.0);
            $cum += $weighted;
            $curve[] = ['equity' => round($cum, 2)];

            if ($trade['month_key'] !== '') {
                $months[$trade['month_key']] = ($months[$trade['month_key']] ?? 0.0) + $weighted;
            }
        }

        $drawdown = $this->drawdownCalculator->calculate($curve, $baseline);
        $ulcer = $this->ulcerIndexCalculator->calculate($curve, $baseline);
        $equityR2 = $this->linearRegressionService->calculateR2(array_column($curve, 'equity'));
        $netProfit = round($cum, 2);
        $absDrawdown = abs((float) $drawdown['max_drawdown']);
        $monthsWithTrades = count($months);
        $positiveMonths = count(array_filter($months, fn (float $value): bool => $value > 0));

        return [
            'net_profit' => $netProfit,
            'max_drawdown' => (float) $drawdown['max_drawdown'],
            'max_drawdown_percent' => (float) $drawdown['max_drawdown_percent'],
            'ulcer_index' => $ulcer,
            'equity_r2' => $equityR2,
            'net_profit_to_drawdown' => $absDrawdown > 0
                ? round($netProfit / $absDrawdown, 2)
                : ($netProfit > 0 ? 100.0 : 0.0),
            'positive_months_percent' => $monthsWithTrades > 0
                ? round(($positiveMonths / $monthsWithTrades) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * @param  array{ulcer_index: float, positive_months_percent: float, net_profit: float}  $metrics
     */
    private function objectiveValue(array $metrics, string $objective): float
    {
        // Higher is always better here. A tiny net-profit term breaks ties without
        // overriding the primary objective.
        $tieBreak = $metrics['net_profit'] * 1e-9;

        return match ($objective) {
            self::OBJECTIVE_POSITIVE_MONTHS => $metrics['positive_months_percent'] + $tieBreak,
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
        if (count($strategyIds) <= self::FULL_GRID_MAX_STRATEGIES) {
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
     * @return float[]
     */
    private function gridValues(float $min, float $max): array
    {
        $values = [];

        for ($value = $min; $value <= $max + 1e-9; $value += self::GRID_STEP) {
            $values[] = round($value, 2);
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
     * Scales weights so the smallest one is 1, keeping them easy to read.
     *
     * @param  array<int, float>  $weights
     * @return array<int, float>
     */
    private function normalize(array $weights): array
    {
        $min = min($weights);

        if ($min <= 0) {
            return array_map(fn (float $value): float => round($value, 2), $weights);
        }

        return array_map(fn (float $value): float => round($value / $min, 2), $weights);
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
