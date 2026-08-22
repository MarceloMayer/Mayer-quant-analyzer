<?php

namespace App\Services\Metrics;

use App\Models\Strategy;
use App\Models\StrategyBacktestExecution;
use App\Models\Trade;
use App\Services\Portfolio\LinearRegressionService;
use App\Services\Portfolio\UlcerIndexCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class StrategyComparisonService
{
    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    public const ALL_EXECUTIONS = '';

    public const FIRST_COLOR = '#2563eb';

    public const SECOND_COLOR = '#f97316';

    public const COMBINED_COLOR = '#16a34a';

    private const CHART_WIDTH = 900;

    private const CHART_HEIGHT = 260;

    private const CHART_TOP = 18;

    private const CHART_BOTTOM = 212;

    private const CHART_LEFT = 62;

    private const CHART_RIGHT = 882;

    private const MAX_CHART_POINTS = 320;

    public function __construct(
        private readonly EquityCurveService $equityCurveService,
        private readonly DrawdownCalculator $drawdownCalculator,
        private readonly DaysWithoutNewHighCalculator $daysWithoutNewHighCalculator,
        private readonly MonthlyPerformanceService $monthlyPerformanceService,
        private readonly StreakCalculator $streakCalculator,
        private readonly UlcerIndexCalculator $ulcerIndexCalculator,
        private readonly LinearRegressionService $linearRegressionService,
    ) {}

    /**
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return [
            self::PERIOD_DAILY => 'Diário',
            self::PERIOD_WEEKLY => 'Semanal',
            self::PERIOD_MONTHLY => 'Mensal',
        ];
    }

    /**
     * Compares two strategies side by side.
     *
     * @param  array{first_backtest_id?: string|null, second_backtest_id?: string|null, start_date?: string|null, end_date?: string|null, correlation_period?: string|null}  $options
     * @return array<string, mixed>
     */
    public function compare(Strategy $first, Strategy $second, array $options = []): array
    {
        $period = $this->normalizePeriod((string) ($options['correlation_period'] ?? self::PERIOD_MONTHLY));
        $startDate = $this->date($options['start_date'] ?? null)?->startOfDay();
        $endDate = $this->date($options['end_date'] ?? null)?->endOfDay();

        $firstTrades = $this->trades($first, $options['first_backtest_id'] ?? null, $startDate, $endDate);
        $secondTrades = $this->trades($second, $options['second_backtest_id'] ?? null, $startDate, $endDate);

        $firstMetrics = $this->metrics($firstTrades);
        $secondMetrics = $this->metrics($secondTrades);
        $combinedMetrics = $this->metrics($firstTrades->merge($secondTrades));

        $rows = $this->rows($firstMetrics, $secondMetrics);
        $score = $this->score($rows);

        $sides = [
            $this->side($first, $options['first_backtest_id'] ?? null, $firstMetrics, self::FIRST_COLOR, $score[0]),
            $this->side($second, $options['second_backtest_id'] ?? null, $secondMetrics, self::SECOND_COLOR, $score[1]),
        ];

        return [
            'generated_at' => now()->format('d/m/Y H:i'),
            'filters' => [
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
                'period' => $period,
                'period_label' => $this->periodOptions()[$period],
                'period_options' => $this->periodOptions(),
            ],
            'strategies' => $sides,
            'has_data' => $firstMetrics['total_trades'] > 0 || $secondMetrics['total_trades'] > 0,
            'rows' => $rows,
            'verdict' => $this->verdict($sides, $rows),
            'chart' => $this->chart($firstMetrics, $secondMetrics, $sides),
            'annual' => $this->annualComparison($firstMetrics, $secondMetrics),
            'head_to_head' => $this->headToHead($firstTrades, $secondTrades, $period),
            'correlation' => $this->correlation($firstTrades, $secondTrades, $period, $sides),
            'combined' => $this->combined($firstMetrics, $secondMetrics, $combinedMetrics),
        ];
    }

    /**
     * Backtest executions available for a strategy, ready for a select input.
     *
     * @return array<string, string>
     */
    public function executionOptions(Strategy $strategy): array
    {
        $options = [self::ALL_EXECUTIONS => 'Todas as execuções'];

        $executions = StrategyBacktestExecution::query()
            ->where('strategy_id', $strategy->id)
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->get(['backtest_id', 'name', 'execution_type']);

        foreach ($executions as $execution) {
            $backtestId = (string) $execution->backtest_id;

            if ($backtestId === '') {
                continue;
            }

            $label = (string) ($execution->name ?: $backtestId);
            $typeLabel = StrategyBacktestExecution::executionTypeLabel($execution->execution_type);

            $options[$backtestId] = "{$label} ({$typeLabel})";
        }

        return $options;
    }

    /**
     * @return Collection<int, Trade>
     */
    private function trades(
        Strategy $strategy,
        ?string $backtestId,
        ?CarbonInterface $startDate,
        ?CarbonInterface $endDate,
    ): Collection {
        return Trade::query()
            ->where('strategy_id', $strategy->id)
            ->whereNotNull('exit_time')
            ->when(filled($backtestId), fn ($query) => $query->where('backtest_id', $backtestId))
            ->when($startDate !== null, fn ($query) => $query->where('exit_time', '>=', $startDate))
            ->when($endDate !== null, fn ($query) => $query->where('exit_time', '<=', $endDate))
            ->orderBy('exit_time')
            ->orderBy('id')
            ->get(['id', 'strategy_id', 'backtest_id', 'exit_time', 'net_profit']);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function side(Strategy $strategy, ?string $backtestId, array $metrics, string $color, int $wins): array
    {
        return [
            'id' => $strategy->id,
            'name' => $strategy->name,
            'asset' => $strategy->asset,
            'asset_label' => Strategy::assetOptions()[$strategy->asset] ?? ($strategy->asset ?: '-'),
            'magic_number' => $strategy->magic_number,
            'backtest_id' => filled($backtestId) ? $backtestId : null,
            'execution_label' => filled($backtestId)
                ? ($this->executionOptions($strategy)[$backtestId] ?? $backtestId)
                : 'Todas as execuções',
            'color' => $color,
            'wins' => $wins,
            // The equity curve is already rendered by the chart, so it is dropped here
            // to keep the Livewire payload of the comparison page small.
            'metrics' => Arr::except($metrics, ['equity_curve', 'monthly_performance']),
        ];
    }

    /**
     * Calculates every metric used by the comparison from a set of closed trades.
     *
     * @param  Collection<int, Trade>  $trades
     * @return array<string, mixed>
     */
    private function metrics(Collection $trades): array
    {
        $trades = $trades
            ->sort(fn (Trade $first, Trade $second): int => [
                $first->exit_time?->getTimestamp() ?? 0,
                $first->id,
            ] <=> [
                $second->exit_time?->getTimestamp() ?? 0,
                $second->id,
            ])
            ->values();

        $equityCurve = $this->equityCurveService->calculate($trades);
        $drawdown = $this->drawdownCalculator->calculate($equityCurve);
        $streaks = $this->streakCalculator->calculate($trades);
        $monthlyPerformance = $this->monthlyPerformanceService->calculate($trades);
        $daysWithoutNewHigh = $this->daysWithoutNewHighCalculator->calculate($equityCurve);

        $profits = $trades->map(fn (Trade $trade): float => (float) $trade->net_profit);
        $winningTrades = $profits->filter(fn (float $profit): bool => $profit > 0);
        $losingTrades = $profits->filter(fn (float $profit): bool => $profit < 0);
        $grossProfit = (float) $winningTrades->sum();
        $grossLoss = abs((float) $losingTrades->sum());
        $netProfit = (float) $profits->sum();
        $totalTrades = $profits->count();
        $averageWin = $winningTrades->isNotEmpty() ? (float) $winningTrades->avg() : 0.0;
        $averageLoss = $losingTrades->isNotEmpty() ? (float) $losingTrades->avg() : 0.0;
        $payoff = $averageWin > 0 && $averageLoss < 0 ? $averageWin / abs($averageLoss) : null;
        $monthlyProfits = $this->monthlyProfits($trades);
        $positiveMonths = count(array_filter($monthlyProfits, fn (float $profit): bool => $profit > 0));
        $monthsWithTrades = count($monthlyProfits);

        return [
            'has_data' => $totalTrades > 0,
            'net_profit' => round($netProfit, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades->count(),
            'losing_trades' => $losingTrades->count(),
            'win_rate' => $totalTrades > 0 ? round(($winningTrades->count() / $totalTrades) * 100, 2) : 0.0,
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'payoff' => $payoff !== null ? round($payoff, 2) : null,
            'average_trade' => $totalTrades > 0 ? round($netProfit / $totalTrades, 2) : 0.0,
            'average_win' => round($averageWin, 2),
            'average_loss' => round($averageLoss, 2),
            'best_trade' => $profits->isNotEmpty() ? round((float) $profits->max(), 2) : 0.0,
            'worst_trade' => $profits->isNotEmpty() ? round((float) $profits->min(), 2) : 0.0,
            'max_drawdown' => $drawdown['max_drawdown'],
            'max_drawdown_percent' => $drawdown['max_drawdown_percent'],
            'net_profit_to_drawdown' => $drawdown['max_drawdown'] > 0
                ? round($netProfit / $drawdown['max_drawdown'], 2)
                : null,
            'max_winning_streak' => $streaks['max_winning_streak'],
            'max_losing_streak' => $streaks['max_losing_streak'],
            'max_days_without_new_high' => $daysWithoutNewHigh['max_days_without_new_high'],
            'months_with_trades' => $monthsWithTrades,
            'positive_months' => $positiveMonths,
            'negative_months' => count(array_filter($monthlyProfits, fn (float $profit): bool => $profit < 0)),
            'positive_months_percent' => $monthsWithTrades > 0
                ? round(($positiveMonths / $monthsWithTrades) * 100, 2)
                : 0.0,
            'average_monthly_profit' => $monthsWithTrades > 0
                ? round($netProfit / $monthsWithTrades, 2)
                : 0.0,
            'ulcer_index' => $this->ulcerIndexCalculator->calculate($equityCurve),
            'equity_r2' => $this->linearRegressionService->calculateR2(
                array_map(fn (array $point): float => (float) $point['equity'], $equityCurve),
            ),
            'first_trade_date' => $trades->first()?->exit_time?->format('Y-m-d'),
            'last_trade_date' => $trades->last()?->exit_time?->format('Y-m-d'),
            'monthly_performance' => $monthlyPerformance,
            'equity_curve' => $equityCurve,
        ];
    }

    /**
     * Monthly net profit keyed by `Y-m`, only for months that had trades.
     *
     * @param  Collection<int, Trade>  $trades
     * @return array<string, float>
     */
    private function monthlyProfits(Collection $trades): array
    {
        $months = [];

        foreach ($trades as $trade) {
            if ($trade->exit_time === null) {
                continue;
            }

            $key = $trade->exit_time->format('Y-m');
            $months[$key] ??= 0.0;
            $months[$key] += (float) $trade->net_profit;
        }

        ksort($months);

        return array_map(fn (float $profit): float => round($profit, 2), $months);
    }

    /**
     * Metric-by-metric comparison rows.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $first, array $second): array
    {
        // Without trades on both sides every metric would be compared against zeroes,
        // so the comparison is shown but nothing is declared a winner.
        $comparable = $first['has_data'] && $second['has_data'];

        return collect($this->definitions())
            ->map(function (array $definition) use ($first, $second, $comparable): array {
                $key = $definition['key'];
                $firstValue = $first[$key] ?? null;
                $secondValue = $second[$key] ?? null;
                $winner = ($definition['direction'] === null || ! $comparable)
                    ? null
                    : $this->winner($firstValue, $secondValue, $definition['direction']);

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'hint' => $definition['hint'],
                    'group' => $definition['group'],
                    'format' => $definition['format'],
                    'direction' => $definition['direction'],
                    'scored' => $definition['scored'] ?? false,
                    'first_value' => $firstValue,
                    'second_value' => $secondValue,
                    'difference' => $this->difference($firstValue, $secondValue),
                    'winner' => $winner,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            ['key' => 'net_profit', 'label' => 'Resultado líquido', 'hint' => 'Soma do lucro líquido de todos os trades fechados.', 'group' => 'Retorno', 'format' => 'money', 'direction' => 'higher', 'scored' => true],
            ['key' => 'average_monthly_profit', 'label' => 'Resultado médio mensal', 'hint' => 'Resultado líquido dividido pelos meses com operações.', 'group' => 'Retorno', 'format' => 'money', 'direction' => 'higher', 'scored' => true],
            ['key' => 'average_trade', 'label' => 'Resultado médio por trade', 'hint' => 'Expectativa matemática por operação.', 'group' => 'Retorno', 'format' => 'money', 'direction' => 'higher', 'scored' => true],
            ['key' => 'profit_factor', 'label' => 'Profit factor', 'hint' => 'Lucro bruto dividido pelo prejuízo bruto.', 'group' => 'Retorno', 'format' => 'ratio', 'direction' => 'higher', 'scored' => true],
            ['key' => 'payoff', 'label' => 'Relação ganho/perda', 'hint' => 'Ganho médio dividido pela perda média.', 'group' => 'Retorno', 'format' => 'ratio', 'direction' => 'higher', 'scored' => true],

            ['key' => 'max_drawdown', 'label' => 'Drawdown máximo', 'hint' => 'Maior queda financeira a partir de um topo da curva.', 'group' => 'Risco', 'format' => 'money_negative', 'direction' => 'lower', 'scored' => true],
            ['key' => 'max_drawdown_percent', 'label' => 'Drawdown máximo (%)', 'hint' => 'Maior queda percentual a partir de um topo da curva.', 'group' => 'Risco', 'format' => 'percent', 'direction' => 'lower', 'scored' => true],
            ['key' => 'net_profit_to_drawdown', 'label' => 'Lucro / drawdown', 'hint' => 'Quantas vezes o resultado cobre o pior drawdown.', 'group' => 'Risco', 'format' => 'ratio', 'direction' => 'higher', 'scored' => true],
            ['key' => 'ulcer_index', 'label' => 'Ulcer index', 'hint' => 'Mede profundidade e duração dos drawdowns. Quanto menor, melhor.', 'group' => 'Risco', 'format' => 'ratio', 'direction' => 'lower', 'scored' => true],
            ['key' => 'max_losing_streak', 'label' => 'Maior sequência de perdas', 'hint' => 'Maior número de trades perdedores consecutivos.', 'group' => 'Risco', 'format' => 'integer', 'direction' => 'lower', 'scored' => true],
            ['key' => 'max_days_without_new_high', 'label' => 'Dias sem romper topo', 'hint' => 'Maior intervalo sem nova máxima da curva de capital.', 'group' => 'Risco', 'format' => 'integer', 'direction' => 'lower', 'scored' => true],

            ['key' => 'win_rate', 'label' => 'Taxa de acerto', 'hint' => 'Percentual de trades com resultado positivo.', 'group' => 'Consistência', 'format' => 'percent', 'direction' => 'higher', 'scored' => true],
            ['key' => 'positive_months_percent', 'label' => 'Meses positivos', 'hint' => 'Percentual de meses com resultado positivo.', 'group' => 'Consistência', 'format' => 'percent', 'direction' => 'higher', 'scored' => true],
            ['key' => 'equity_r2', 'label' => 'R² da curva', 'hint' => 'Aderência da curva de capital a uma reta. Quanto maior, mais suave.', 'group' => 'Consistência', 'format' => 'ratio_4', 'direction' => 'higher', 'scored' => true],
            ['key' => 'max_winning_streak', 'label' => 'Maior sequência de ganhos', 'hint' => 'Maior número de trades vencedores consecutivos.', 'group' => 'Consistência', 'format' => 'integer', 'direction' => 'higher', 'scored' => false],

            ['key' => 'total_trades', 'label' => 'Total de trades', 'hint' => 'Quantidade de operações fechadas no período.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
            ['key' => 'winning_trades', 'label' => 'Trades vencedores', 'hint' => 'Operações com resultado positivo.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
            ['key' => 'losing_trades', 'label' => 'Trades perdedores', 'hint' => 'Operações com resultado negativo.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
            ['key' => 'months_with_trades', 'label' => 'Meses com operações', 'hint' => 'Meses em que a estratégia operou.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
            ['key' => 'average_win', 'label' => 'Ganho médio', 'hint' => 'Resultado médio dos trades vencedores.', 'group' => 'Amostra', 'format' => 'money', 'direction' => 'higher', 'scored' => false],
            ['key' => 'average_loss', 'label' => 'Perda média', 'hint' => 'Resultado médio dos trades perdedores.', 'group' => 'Amostra', 'format' => 'money', 'direction' => 'higher', 'scored' => false],
            ['key' => 'best_trade', 'label' => 'Melhor trade', 'hint' => 'Maior lucro em uma única operação.', 'group' => 'Amostra', 'format' => 'money', 'direction' => 'higher', 'scored' => false],
            ['key' => 'worst_trade', 'label' => 'Pior trade', 'hint' => 'Maior prejuízo em uma única operação.', 'group' => 'Amostra', 'format' => 'money', 'direction' => 'higher', 'scored' => false],
        ];
    }

    private function winner(mixed $firstValue, mixed $secondValue, string $direction): ?int
    {
        // A null means the metric does not apply (no losses, no drawdown, no month),
        // which cannot be ranked against a number.
        if ($firstValue === null || $secondValue === null) {
            return null;
        }

        $firstValue = (float) $firstValue;
        $secondValue = (float) $secondValue;

        if (abs($firstValue - $secondValue) < 0.0000001) {
            return null;
        }

        if ($direction === 'lower') {
            return $firstValue < $secondValue ? 0 : 1;
        }

        return $firstValue > $secondValue ? 0 : 1;
    }

    private function difference(mixed $firstValue, mixed $secondValue): ?float
    {
        if ($firstValue === null || $secondValue === null) {
            return null;
        }

        return round((float) $firstValue - (float) $secondValue, 4);
    }

    /**
     * Number of scored metrics each strategy wins.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: int, 1: int}
     */
    private function score(array $rows): array
    {
        $score = [0, 0];

        foreach ($rows as $row) {
            if (($row['scored'] ?? false) !== true || $row['winner'] === null) {
                continue;
            }

            $score[$row['winner']]++;
        }

        return $score;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sides
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function verdict(array $sides, array $rows): array
    {
        $scoredRows = collect($rows)->filter(fn (array $row): bool => ($row['scored'] ?? false) === true);
        $total = $scoredRows->count();
        $firstWins = (int) $sides[0]['wins'];
        $secondWins = (int) $sides[1]['wins'];

        if (! $sides[0]['metrics']['has_data'] || ! $sides[1]['metrics']['has_data']) {
            return [
                'leader' => null,
                'type' => 'neutral',
                'message' => 'Uma das estratégias não possui trades fechados no período selecionado, então a comparação está incompleta.',
                'first_wins' => $firstWins,
                'second_wins' => $secondWins,
                'total_metrics' => $total,
            ];
        }

        if ($firstWins === $secondWins) {
            return [
                'leader' => null,
                'type' => 'neutral',
                'message' => "Empate técnico: cada estratégia lidera {$firstWins} das {$total} métricas avaliadas.",
                'first_wins' => $firstWins,
                'second_wins' => $secondWins,
                'total_metrics' => $total,
            ];
        }

        $leader = $firstWins > $secondWins ? 0 : 1;
        $leaderWins = max($firstWins, $secondWins);
        $name = $sides[$leader]['name'];

        return [
            'leader' => $leader,
            'type' => 'success',
            'message' => "{$name} lidera em {$leaderWins} das {$total} métricas avaliadas.",
            'first_wins' => $firstWins,
            'second_wins' => $secondWins,
            'total_metrics' => $total,
        ];
    }

    /**
     * Overlaid equity curves drawn on a shared time axis.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @param  array<int, array<string, mixed>>  $sides
     * @return array<string, mixed>
     */
    private function chart(array $first, array $second, array $sides): array
    {
        $series = [
            ['label' => $sides[0]['name'], 'color' => $sides[0]['color'], 'points' => $this->datedEquity($first['equity_curve'])],
            ['label' => $sides[1]['name'], 'color' => $sides[1]['color'], 'points' => $this->datedEquity($second['equity_curve'])],
        ];

        $allPoints = array_merge(...array_column($series, 'points'));

        if ($allPoints === []) {
            return ['has_data' => false, 'series' => [], 'y_ticks' => [], 'x_ticks' => [], 'zero_y' => null];
        }

        $timestamps = array_column($allPoints, 'timestamp');
        $values = array_column($allPoints, 'equity');
        $minTimestamp = min($timestamps);
        $maxTimestamp = max($timestamps);
        $minValue = min($values);
        $maxValue = max($values);
        $minValue = min($minValue, 0.0);
        $maxValue = max($maxValue, 0.0);

        if ($minValue === $maxValue) {
            $minValue -= 1;
            $maxValue += 1;
        }

        // Small headroom so the curves never touch the top and bottom borders.
        $headroom = ($maxValue - $minValue) * 0.04;
        $minValue -= $headroom;
        $maxValue += $headroom;

        if ($minTimestamp === $maxTimestamp) {
            $maxTimestamp = $minTimestamp + 1;
        }

        $plotted = collect($series)
            ->map(function (array $item) use ($minTimestamp, $maxTimestamp, $minValue, $maxValue): array {
                $points = $this->downsample($item['points']);
                $coordinates = collect($points)
                    ->map(fn (array $point): string => $this->x($point['timestamp'], $minTimestamp, $maxTimestamp)
                        .','
                        .$this->y((float) $point['equity'], $minValue, $maxValue))
                    ->implode(' ');
                $lastPoint = $points === [] ? null : $points[array_key_last($points)];

                return [
                    'label' => $item['label'],
                    'color' => $item['color'],
                    'line' => $coordinates,
                    'has_points' => $points !== [],
                    'final_equity' => $lastPoint === null ? 0.0 : round((float) $lastPoint['equity'], 2),
                    'final_x' => $lastPoint === null ? null : $this->x($lastPoint['timestamp'], $minTimestamp, $maxTimestamp),
                    'final_y' => $lastPoint === null ? null : $this->y((float) $lastPoint['equity'], $minValue, $maxValue),
                ];
            })
            ->all();

        return [
            'has_data' => true,
            'width' => self::CHART_WIDTH,
            'height' => self::CHART_HEIGHT,
            'left' => self::CHART_LEFT,
            'right' => self::CHART_RIGHT,
            'top' => self::CHART_TOP,
            'bottom' => self::CHART_BOTTOM,
            'series' => $plotted,
            'y_ticks' => $this->yTicks($minValue, $maxValue),
            'x_ticks' => $this->xTicks($minTimestamp, $maxTimestamp),
            'zero_y' => $minValue <= 0 && $maxValue >= 0 ? $this->y(0.0, $minValue, $maxValue) : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $equityCurve
     * @return array<int, array{timestamp: int, equity: float, date: string}>
     */
    private function datedEquity(array $equityCurve): array
    {
        return collect($equityCurve)
            ->map(function (array $point): ?array {
                $date = $this->date($point['date'] ?? null);

                if ($date === null) {
                    return null;
                }

                return [
                    'timestamp' => $date->getTimestamp(),
                    'equity' => (float) ($point['equity'] ?? 0),
                    'date' => $date->format('Y-m-d'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Keeps the curve shape while limiting the number of rendered points.
     *
     * @param  array<int, array{timestamp: int, equity: float, date: string}>  $points
     * @return array<int, array{timestamp: int, equity: float, date: string}>
     */
    private function downsample(array $points): array
    {
        $count = count($points);

        if ($count <= self::MAX_CHART_POINTS) {
            return $points;
        }

        $step = $count / self::MAX_CHART_POINTS;
        $sampled = [];

        for ($index = 0; $index < self::MAX_CHART_POINTS; $index++) {
            $sampled[] = $points[(int) floor($index * $step)];
        }

        $sampled[] = $points[$count - 1];

        return $sampled;
    }

    private function x(int $timestamp, int $minTimestamp, int $maxTimestamp): float
    {
        $ratio = ($timestamp - $minTimestamp) / max($maxTimestamp - $minTimestamp, 1);

        return round(self::CHART_LEFT + ($ratio * (self::CHART_RIGHT - self::CHART_LEFT)), 2);
    }

    private function y(float $value, float $minValue, float $maxValue): float
    {
        $ratio = ($value - $minValue) / max($maxValue - $minValue, 0.0000001);

        return round(self::CHART_BOTTOM - ($ratio * (self::CHART_BOTTOM - self::CHART_TOP)), 2);
    }

    /**
     * @return array<int, array{label: string, y: float}>
     */
    private function yTicks(float $minValue, float $maxValue): array
    {
        $middle = $minValue + (($maxValue - $minValue) / 2);

        return collect([$maxValue, $middle, $minValue])
            ->map(fn (float $value): array => [
                'label' => $this->compactMoney($value),
                'y' => $this->y($value, $minValue, $maxValue),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, x: float, anchor: string}>
     */
    private function xTicks(int $minTimestamp, int $maxTimestamp): array
    {
        $middleTimestamp = (int) (($minTimestamp + $maxTimestamp) / 2);

        return [
            ['label' => $this->formatTimestamp($minTimestamp), 'x' => $this->x($minTimestamp, $minTimestamp, $maxTimestamp), 'anchor' => 'start'],
            ['label' => $this->formatTimestamp($middleTimestamp), 'x' => $this->x($middleTimestamp, $minTimestamp, $maxTimestamp), 'anchor' => 'middle'],
            ['label' => $this->formatTimestamp($maxTimestamp), 'x' => $this->x($maxTimestamp, $minTimestamp, $maxTimestamp), 'anchor' => 'end'],
        ];
    }

    private function formatTimestamp(int $timestamp): string
    {
        return CarbonImmutable::createFromTimestamp($timestamp)->format('d/m/Y');
    }

    private function compactMoney(float $value): string
    {
        $absolute = abs($value);
        $prefix = $value < 0 ? '-' : '';

        if ($absolute >= 1000000) {
            return $prefix.number_format($absolute / 1000000, 1, ',', '.').' mi';
        }

        if ($absolute >= 1000) {
            return $prefix.number_format($absolute / 1000, 1, ',', '.').' mil';
        }

        return $prefix.number_format($absolute, 0, ',', '.');
    }

    /**
     * Year by year result of both strategies.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @return array<int, array<string, mixed>>
     */
    private function annualComparison(array $first, array $second): array
    {
        $firstYears = collect($first['monthly_performance'])->keyBy('year');
        $secondYears = collect($second['monthly_performance'])->keyBy('year');

        return $firstYears->keys()
            ->merge($secondYears->keys())
            ->unique()
            ->sortDesc()
            ->map(function (int $year) use ($firstYears, $secondYears): array {
                $firstValue = $firstYears->has($year) ? (float) $firstYears->get($year)['ytd'] : null;
                $secondValue = $secondYears->has($year) ? (float) $secondYears->get($year)['ytd'] : null;

                return [
                    'year' => $year,
                    'first_value' => $firstValue,
                    'second_value' => $secondValue,
                    'difference' => $this->difference($firstValue, $secondValue),
                    'winner' => $this->winner($firstValue, $secondValue, 'higher'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * How many periods each strategy came out ahead.
     *
     * @param  Collection<int, Trade>  $firstTrades
     * @param  Collection<int, Trade>  $secondTrades
     * @return array<string, mixed>
     */
    private function headToHead(Collection $firstTrades, Collection $secondTrades, string $period): array
    {
        $keys = $this->periodKeys($firstTrades, $secondTrades, $period);
        $firstSeries = $this->series($firstTrades, $keys, $period);
        $secondSeries = $this->series($secondTrades, $keys, $period);
        $firstWins = 0;
        $secondWins = 0;
        $ties = 0;

        foreach (array_keys($keys) as $index) {
            $difference = round($firstSeries[$index] - $secondSeries[$index], 2);

            if ($difference > 0) {
                $firstWins++;

                continue;
            }

            if ($difference < 0) {
                $secondWins++;

                continue;
            }

            $ties++;
        }

        return [
            'period_count' => count($keys),
            'first_wins' => $firstWins,
            'second_wins' => $secondWins,
            'ties' => $ties,
            'first_win_rate' => count($keys) > 0 ? round(($firstWins / count($keys)) * 100, 2) : 0.0,
            'second_win_rate' => count($keys) > 0 ? round(($secondWins / count($keys)) * 100, 2) : 0.0,
        ];
    }

    /**
     * Correlation between both strategies over the selected period.
     *
     * @param  Collection<int, Trade>  $firstTrades
     * @param  Collection<int, Trade>  $secondTrades
     * @param  array<int, array<string, mixed>>  $sides
     * @return array<string, mixed>
     */
    private function correlation(Collection $firstTrades, Collection $secondTrades, string $period, array $sides): array
    {
        $keys = $this->periodKeys($firstTrades, $secondTrades, $period);
        $firstSeries = $this->series($firstTrades, $keys, $period);
        $secondSeries = $this->series($secondTrades, $keys, $period);
        $value = $this->pearson($firstSeries, $secondSeries);

        return [
            'value' => $value,
            'display' => $value === null ? '-' : number_format($value, 2, ',', '.'),
            'period_count' => count($keys),
            'has_enough_data' => count($keys) >= 2 && $value !== null,
            'class' => $value === null ? 'mqa-correlation-empty' : $this->correlationClass($value),
            'description' => $value === null
                ? 'Dados insuficientes para calcular a correlação.'
                : $this->correlationDescription($value),
            'message' => $value === null
                ? "Não há períodos suficientes em comum entre {$sides[0]['name']} e {$sides[1]['name']}."
                : $this->correlationMessage($value, $sides),
        ];
    }

    /**
     * @param  Collection<int, Trade>  $firstTrades
     * @param  Collection<int, Trade>  $secondTrades
     * @return array<int, string>
     */
    private function periodKeys(Collection $firstTrades, Collection $secondTrades, string $period): array
    {
        return $firstTrades->merge($secondTrades)
            ->map(fn (Trade $trade): ?string => $this->periodKey($trade->exit_time, $period))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @param  array<int, string>  $keys
     * @return array<int, float>
     */
    private function series(Collection $trades, array $keys, string $period): array
    {
        $series = array_fill(0, max(count($keys), 1), 0.0);
        $indexes = array_flip($keys);

        foreach ($trades as $trade) {
            $key = $this->periodKey($trade->exit_time, $period);

            if ($key === null || ! array_key_exists($key, $indexes)) {
                continue;
            }

            $series[$indexes[$key]] += (float) $trade->net_profit;
        }

        return array_map(fn (float $value): float => round($value, 2), array_slice($series, 0, count($keys)));
    }

    /**
     * @param  array<int, float>  $firstSeries
     * @param  array<int, float>  $secondSeries
     */
    private function pearson(array $firstSeries, array $secondSeries): ?float
    {
        $count = min(count($firstSeries), count($secondSeries));

        if ($count < 2) {
            return null;
        }

        $firstAverage = array_sum($firstSeries) / $count;
        $secondAverage = array_sum($secondSeries) / $count;
        $numerator = 0.0;
        $firstVariance = 0.0;
        $secondVariance = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $firstDelta = $firstSeries[$index] - $firstAverage;
            $secondDelta = $secondSeries[$index] - $secondAverage;

            $numerator += $firstDelta * $secondDelta;
            $firstVariance += $firstDelta ** 2;
            $secondVariance += $secondDelta ** 2;
        }

        $denominator = sqrt($firstVariance * $secondVariance);

        if ($denominator <= 0.00000001) {
            return null;
        }

        return round(max(-1, min(1, $numerator / $denominator)), 4);
    }

    private function correlationClass(float $correlation): string
    {
        $absolute = abs($correlation);

        return match (true) {
            $absolute <= 0.20 => 'mqa-correlation-good',
            $absolute <= 0.40 => 'mqa-correlation-warning',
            $absolute <= 0.70 => 'mqa-correlation-high',
            default => 'mqa-correlation-critical',
        };
    }

    private function correlationDescription(float $correlation): string
    {
        $absolute = abs($correlation);

        return match (true) {
            $absolute <= 0.20 => 'Boa diversificação',
            $absolute <= 0.40 => 'Atenção',
            $absolute <= 0.70 => 'Correlação alta',
            default => 'Correlação muito alta',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $sides
     */
    private function correlationMessage(float $correlation, array $sides): string
    {
        $names = "{$sides[0]['name']} e {$sides[1]['name']}";

        return match (true) {
            abs($correlation) <= 0.20 => "{$names} têm comportamentos praticamente independentes, o que favorece a combinação em um portfólio.",
            $correlation < -0.20 => "{$names} tendem a se mover em direções opostas, o que suaviza a curva quando combinadas.",
            abs($correlation) <= 0.40 => "{$names} apresentam alguma sobreposição de comportamento. Vale acompanhar a combinação.",
            abs($correlation) <= 0.70 => "{$names} têm correlação alta e tendem a ganhar e perder juntas.",
            default => "{$names} são muito redundantes entre si. Manter as duas no mesmo portfólio agrega pouca diversificação.",
        };
    }

    /**
     * Result of running both strategies together.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @param  array<string, mixed>  $combined
     * @return array<string, mixed>
     */
    private function combined(array $first, array $second, array $combined): array
    {
        $drawdownSum = round((float) $first['max_drawdown'] + (float) $second['max_drawdown'], 2);
        $drawdownReduction = round($drawdownSum - (float) $combined['max_drawdown'], 2);
        $reductionPercent = $drawdownSum > 0 ? round(($drawdownReduction / $drawdownSum) * 100, 2) : 0.0;

        return [
            'has_data' => $combined['has_data'],
            'net_profit' => $combined['net_profit'],
            'max_drawdown' => $combined['max_drawdown'],
            'max_drawdown_percent' => $combined['max_drawdown_percent'],
            'profit_factor' => $combined['profit_factor'],
            'net_profit_to_drawdown' => $combined['net_profit_to_drawdown'],
            'win_rate' => $combined['win_rate'],
            'total_trades' => $combined['total_trades'],
            'positive_months_percent' => $combined['positive_months_percent'],
            'equity_r2' => $combined['equity_r2'],
            'ulcer_index' => $combined['ulcer_index'],
            'drawdown_sum' => $drawdownSum,
            'drawdown_reduction' => $drawdownReduction,
            'drawdown_reduction_percent' => $reductionPercent,
            'best_individual_drawdown' => round(min((float) $first['max_drawdown'], (float) $second['max_drawdown']), 2),
            'best_individual_net_profit' => round(max((float) $first['net_profit'], (float) $second['net_profit']), 2),
        ];
    }

    private function normalizePeriod(string $period): string
    {
        return array_key_exists($period, $this->periodOptions()) ? $period : self::PERIOD_MONTHLY;
    }

    private function periodKey(mixed $value, string $period): ?string
    {
        $date = $this->date($value);

        if ($date === null) {
            return null;
        }

        return match ($period) {
            self::PERIOD_DAILY => $date->format('Y-m-d'),
            self::PERIOD_WEEKLY => $date->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d'),
            default => $date->format('Y-m'),
        };
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::parse($value);
        }

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
