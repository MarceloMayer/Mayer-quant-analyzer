<?php

namespace App\Services\Metrics;

use App\Models\Portfolio;
use App\Models\Trade;
use App\Services\Portfolio\LinearRegressionService;
use App\Services\Portfolio\UlcerIndexCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Side-by-side comparison of two saved portfolios: metric-by-metric verdict, overlaid
 * equity curves, year-by-year result, correlation between the two consolidated P&L
 * series and the effect of running both portfolios together.
 *
 * Mirrors StrategyComparisonService, but each side's numbers come straight from
 * PortfolioAnalyzerService so they match the portfolio results page.
 */
class PortfolioComparisonService
{
    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    public const FIRST_COLOR = '#2563eb';

    public const SECOND_COLOR = '#f97316';

    private const CHART_WIDTH = 900;

    private const CHART_HEIGHT = 260;

    private const CHART_TOP = 18;

    private const CHART_BOTTOM = 212;

    private const CHART_LEFT = 62;

    private const CHART_RIGHT = 882;

    private const MAX_CHART_POINTS = 320;

    public function __construct(
        private readonly PortfolioAnalyzerService $portfolioAnalyzer,
        private readonly DrawdownCalculator $drawdownCalculator,
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
     * @param  array{correlation_period?: string|null}  $options
     * @return array<string, mixed>
     */
    public function compare(Portfolio $first, Portfolio $second, array $options = []): array
    {
        $period = $this->normalizePeriod((string) ($options['correlation_period'] ?? self::PERIOD_MONTHLY));

        $firstMetrics = $this->portfolioAnalyzer->calculate($first);
        $secondMetrics = $this->portfolioAnalyzer->calculate($second);

        $rows = $this->rows($firstMetrics, $secondMetrics);
        $score = $this->score($rows);

        $sides = [
            $this->side($first, $firstMetrics, self::FIRST_COLOR, $score[0]),
            $this->side($second, $secondMetrics, self::SECOND_COLOR, $score[1]),
        ];

        $firstSeries = $this->periodSeriesFromTrades($firstMetrics['consolidated_trades'] ?? [], $period);
        $secondSeries = $this->periodSeriesFromTrades($secondMetrics['consolidated_trades'] ?? [], $period);

        return [
            'generated_at' => now()->format('d/m/Y H:i'),
            'filters' => [
                'period' => $period,
                'period_label' => $this->periodOptions()[$period],
                'period_options' => $this->periodOptions(),
            ],
            'portfolios' => $sides,
            'has_data' => ($firstMetrics['total_trades'] ?? 0) > 0 || ($secondMetrics['total_trades'] ?? 0) > 0,
            'rows' => $rows,
            'verdict' => $this->verdict($sides, $rows),
            'chart' => $this->chart($firstMetrics, $secondMetrics, $sides),
            'annual' => $this->annualComparison($firstMetrics, $secondMetrics),
            'head_to_head' => $this->headToHead($firstSeries, $secondSeries),
            'correlation' => $this->correlation($firstSeries, $secondSeries, $sides, $period),
            'combined' => $this->combined($first, $second, $firstMetrics, $secondMetrics),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function side(Portfolio $portfolio, array $metrics, string $color, int $wins): array
    {
        return [
            'id' => $portfolio->id,
            'name' => $portfolio->name,
            'color' => $color,
            'wins' => $wins,
            'strategies_count' => $metrics['active_strategies_count'] ?? 0,
            'has_data' => ($metrics['total_trades'] ?? 0) > 0,
            'metrics' => [
                'net_profit' => (float) ($metrics['net_profit'] ?? 0),
                'max_drawdown' => (float) ($metrics['max_drawdown'] ?? 0),
                'profit_factor' => $metrics['profit_factor'] ?? null,
                'win_rate' => (float) ($metrics['win_rate'] ?? 0),
                'total_trades' => (int) ($metrics['total_trades'] ?? 0),
                'ulcer_index' => (float) ($metrics['ulcer_index'] ?? 0),
                'equity_r2' => (float) ($metrics['equity_r2'] ?? 0),
                'positive_months_percent' => (float) ($metrics['positive_months_percent'] ?? 0),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $first, array $second): array
    {
        $comparable = ($first['total_trades'] ?? 0) > 0 && ($second['total_trades'] ?? 0) > 0;

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
            ['key' => 'net_profit', 'label' => 'Resultado líquido', 'hint' => 'Soma do resultado líquido ponderado das estratégias ativas.', 'group' => 'Retorno', 'format' => 'money', 'direction' => 'higher', 'scored' => true],
            ['key' => 'average_trade', 'label' => 'Resultado médio por trade', 'hint' => 'Expectativa matemática por operação.', 'group' => 'Retorno', 'format' => 'money', 'direction' => 'higher', 'scored' => true],
            ['key' => 'profit_factor', 'label' => 'Profit factor', 'hint' => 'Lucro bruto dividido pelo prejuízo bruto.', 'group' => 'Retorno', 'format' => 'ratio', 'direction' => 'higher', 'scored' => true],
            ['key' => 'average_payoff', 'label' => 'Payoff médio', 'hint' => 'Ganho médio dividido pela perda média.', 'group' => 'Retorno', 'format' => 'ratio', 'direction' => 'higher', 'scored' => true],

            ['key' => 'max_drawdown', 'label' => 'Drawdown máximo', 'hint' => 'Maior queda financeira a partir de um topo da curva consolidada.', 'group' => 'Risco', 'format' => 'money_negative', 'direction' => 'lower', 'scored' => true],
            ['key' => 'max_drawdown_percent', 'label' => 'Drawdown máximo (%)', 'hint' => 'Maior queda percentual a partir de um topo.', 'group' => 'Risco', 'format' => 'percent', 'direction' => 'lower', 'scored' => true],
            ['key' => 'net_profit_to_drawdown', 'label' => 'Lucro / drawdown', 'hint' => 'Quantas vezes o resultado cobre o pior drawdown.', 'group' => 'Risco', 'format' => 'ratio', 'direction' => 'higher', 'scored' => true],
            ['key' => 'ulcer_index', 'label' => 'Ulcer index', 'hint' => 'Profundidade e duração dos drawdowns. Quanto menor, melhor.', 'group' => 'Risco', 'format' => 'ratio', 'direction' => 'lower', 'scored' => true],
            ['key' => 'max_losing_streak', 'label' => 'Maior sequência de perdas', 'hint' => 'Maior número de trades perdedores consecutivos.', 'group' => 'Risco', 'format' => 'integer', 'direction' => 'lower', 'scored' => true],
            ['key' => 'max_days_without_new_high', 'label' => 'Dias sem romper topo', 'hint' => 'Maior intervalo sem nova máxima da curva consolidada.', 'group' => 'Risco', 'format' => 'integer', 'direction' => 'lower', 'scored' => true],

            ['key' => 'win_rate', 'label' => 'Taxa de acerto', 'hint' => 'Percentual de trades com resultado positivo.', 'group' => 'Consistência', 'format' => 'percent', 'direction' => 'higher', 'scored' => true],
            ['key' => 'positive_months_percent', 'label' => 'Meses positivos', 'hint' => 'Percentual de meses com resultado positivo.', 'group' => 'Consistência', 'format' => 'percent', 'direction' => 'higher', 'scored' => true],
            ['key' => 'equity_r2', 'label' => 'R² da curva', 'hint' => 'Aderência da curva consolidada a uma reta. Quanto maior, mais suave.', 'group' => 'Consistência', 'format' => 'ratio_4', 'direction' => 'higher', 'scored' => true],

            ['key' => 'total_trades', 'label' => 'Total de trades', 'hint' => 'Operações fechadas das estratégias ativas.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
            ['key' => 'winning_trades', 'label' => 'Trades vencedores', 'hint' => 'Operações com resultado positivo.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
            ['key' => 'losing_trades', 'label' => 'Trades perdedores', 'hint' => 'Operações com resultado negativo.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
            ['key' => 'months_with_trades', 'label' => 'Meses com operações', 'hint' => 'Meses em que o portfólio operou.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
            ['key' => 'active_strategies_count', 'label' => 'Estratégias ativas', 'hint' => 'Estratégias habilitadas na composição.', 'group' => 'Amostra', 'format' => 'integer', 'direction' => null],
        ];
    }

    private function winner(mixed $firstValue, mixed $secondValue, string $direction): ?int
    {
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
        $total = collect($rows)->filter(fn (array $row): bool => ($row['scored'] ?? false) === true)->count();
        $firstWins = (int) $sides[0]['wins'];
        $secondWins = (int) $sides[1]['wins'];

        if (! $sides[0]['has_data'] || ! $sides[1]['has_data']) {
            return [
                'leader' => null,
                'type' => 'neutral',
                'message' => 'Um dos portfólios não possui trades fechados, então a comparação está incompleta.',
                'first_wins' => $firstWins,
                'second_wins' => $secondWins,
                'total_metrics' => $total,
            ];
        }

        if ($firstWins === $secondWins) {
            return [
                'leader' => null,
                'type' => 'neutral',
                'message' => "Empate técnico: cada portfólio lidera {$firstWins} das {$total} métricas avaliadas.",
                'first_wins' => $firstWins,
                'second_wins' => $secondWins,
                'total_metrics' => $total,
            ];
        }

        $leader = $firstWins > $secondWins ? 0 : 1;
        $leaderWins = max($firstWins, $secondWins);

        return [
            'leader' => $leader,
            'type' => 'success',
            'message' => "{$sides[$leader]['name']} lidera em {$leaderWins} das {$total} métricas avaliadas.",
            'first_wins' => $firstWins,
            'second_wins' => $secondWins,
            'total_metrics' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @param  array<int, array<string, mixed>>  $sides
     * @return array<string, mixed>
     */
    private function chart(array $first, array $second, array $sides): array
    {
        $series = [
            ['label' => $sides[0]['name'], 'color' => $sides[0]['color'], 'points' => $this->datedEquity($first['equity_curve'] ?? [])],
            ['label' => $sides[1]['name'], 'color' => $sides[1]['color'], 'points' => $this->datedEquity($second['equity_curve'] ?? [])],
        ];

        $allPoints = array_merge(...array_column($series, 'points'));

        if ($allPoints === []) {
            return ['has_data' => false, 'series' => [], 'y_ticks' => [], 'x_ticks' => [], 'zero_y' => null];
        }

        $timestamps = array_column($allPoints, 'timestamp');
        $values = array_column($allPoints, 'equity');
        $minTimestamp = min($timestamps);
        $maxTimestamp = max($timestamps);
        $minValue = min(min($values), 0.0);
        $maxValue = max(max($values), 0.0);

        if ($minValue === $maxValue) {
            $minValue -= 1;
            $maxValue += 1;
        }

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
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @return array<int, array<string, mixed>>
     */
    private function annualComparison(array $first, array $second): array
    {
        $firstYears = collect($first['monthly_performance'] ?? [])->keyBy('year');
        $secondYears = collect($second['monthly_performance'] ?? [])->keyBy('year');

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
     * Bucket a portfolio's consolidated (weighted) trades into periodic P&L totals.
     *
     * @param  array<int, array<string, mixed>>  $trades
     * @return array<string, float>
     */
    private function periodSeriesFromTrades(array $trades, string $period): array
    {
        $series = [];

        foreach ($trades as $trade) {
            $key = $this->periodKey($trade['exit_time'] ?? null, $period);

            if ($key === null) {
                continue;
            }

            $series[$key] = ($series[$key] ?? 0.0) + (float) ($trade['net_profit'] ?? 0);
        }

        ksort($series);

        return array_map(fn (float $value): float => round($value, 2), $series);
    }

    /**
     * @param  array<string, float>  $firstSeries
     * @param  array<string, float>  $secondSeries
     * @return array<string, mixed>
     */
    private function headToHead(array $firstSeries, array $secondSeries): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($firstSeries), array_keys($secondSeries))));
        sort($keys);

        $firstWins = 0;
        $secondWins = 0;
        $ties = 0;

        foreach ($keys as $key) {
            $difference = round(($firstSeries[$key] ?? 0.0) - ($secondSeries[$key] ?? 0.0), 2);

            if ($difference > 0) {
                $firstWins++;
            } elseif ($difference < 0) {
                $secondWins++;
            } else {
                $ties++;
            }
        }

        $count = count($keys);

        return [
            'period_count' => $count,
            'first_wins' => $firstWins,
            'second_wins' => $secondWins,
            'ties' => $ties,
            'first_win_rate' => $count > 0 ? round(($firstWins / $count) * 100, 2) : 0.0,
            'second_win_rate' => $count > 0 ? round(($secondWins / $count) * 100, 2) : 0.0,
        ];
    }

    /**
     * @param  array<string, float>  $firstSeries
     * @param  array<string, float>  $secondSeries
     * @param  array<int, array<string, mixed>>  $sides
     * @return array<string, mixed>
     */
    private function correlation(array $firstSeries, array $secondSeries, array $sides, string $period): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($firstSeries), array_keys($secondSeries))));
        sort($keys);

        $first = [];
        $second = [];

        foreach ($keys as $key) {
            $first[] = $firstSeries[$key] ?? 0.0;
            $second[] = $secondSeries[$key] ?? 0.0;
        }

        $value = $this->pearson($first, $second);

        return [
            'value' => $value,
            'display' => $value === null ? '-' : number_format($value, 2, ',', '.'),
            'period_count' => count($keys),
            'has_enough_data' => count($keys) >= 2 && $value !== null,
            'class' => $value === null ? 'mqa-correlation-empty' : $this->correlationClass($value),
            'description' => $value === null ? 'Dados insuficientes para calcular a correlação.' : $this->correlationDescription($value),
            'message' => $value === null
                ? "Não há períodos suficientes em comum entre {$sides[0]['name']} e {$sides[1]['name']}."
                : $this->correlationMessage($value, $sides),
        ];
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
            abs($correlation) <= 0.20 => "{$names} têm comportamentos praticamente independentes — combiná-los tende a suavizar a curva.",
            $correlation < -0.20 => "{$names} tendem a se mover em direções opostas, o que reduz o drawdown quando somados.",
            abs($correlation) <= 0.40 => "{$names} têm alguma sobreposição de comportamento. Vale acompanhar de perto.",
            abs($correlation) <= 0.70 => "{$names} têm correlação alta e tendem a ganhar e perder juntos.",
            default => "{$names} são muito redundantes entre si. Manter os dois entrega pouca diversificação extra.",
        };
    }

    /**
     * Effect of running the enabled strategies of both portfolios together.
     *
     * @param  array<string, mixed>  $firstMetrics
     * @param  array<string, mixed>  $secondMetrics
     * @return array<string, mixed>
     */
    private function combined(Portfolio $first, Portfolio $second, array $firstMetrics, array $secondMetrics): array
    {
        $weights = $this->mergedWeights($first, $second);
        $baseline = (float) ($first->initial_balance ?? 0) + (float) ($second->initial_balance ?? 0);
        $bundle = $this->weightedBundleMetrics($weights, $baseline);

        $drawdownSum = round((float) ($firstMetrics['max_drawdown'] ?? 0) + (float) ($secondMetrics['max_drawdown'] ?? 0), 2);
        $drawdownReduction = round($drawdownSum - (float) $bundle['max_drawdown'], 2);
        $reductionPercent = $drawdownSum > 0 ? round(($drawdownReduction / $drawdownSum) * 100, 2) : 0.0;

        return array_merge($bundle, [
            'drawdown_sum' => $drawdownSum,
            'drawdown_reduction' => $drawdownReduction,
            'drawdown_reduction_percent' => $reductionPercent,
            'best_individual_drawdown' => round(min((float) ($firstMetrics['max_drawdown'] ?? 0), (float) ($secondMetrics['max_drawdown'] ?? 0)), 2),
            'best_individual_net_profit' => round(max((float) ($firstMetrics['net_profit'] ?? 0), (float) ($secondMetrics['net_profit'] ?? 0)), 2),
        ]);
    }

    /**
     * Union of the enabled strategies of both portfolios; a strategy present on both
     * sides gets the mean of its two weights.
     *
     * @return array<int, float>
     */
    private function mergedWeights(Portfolio $first, Portfolio $second): array
    {
        $collect = static function (Portfolio $portfolio): array {
            return $portfolio->portfolioStrategies()
                ->where('enabled', true)
                ->get(['strategy_id', 'weight'])
                ->mapWithKeys(fn ($row): array => [(int) $row->strategy_id => (float) ($row->weight ?? 1)])
                ->all();
        };

        $firstWeights = $collect($first);
        $secondWeights = $collect($second);
        $merged = [];

        foreach (array_unique(array_merge(array_keys($firstWeights), array_keys($secondWeights))) as $strategyId) {
            $values = array_values(array_filter([
                $firstWeights[$strategyId] ?? null,
                $secondWeights[$strategyId] ?? null,
            ], fn (mixed $value): bool => $value !== null));

            $merged[(int) $strategyId] = count($values) > 0 ? array_sum($values) / count($values) : 1.0;
        }

        return $merged;
    }

    /**
     * Single-pass weighted equity reconstruction for the combined portfolio.
     *
     * @param  array<int, float>  $weights
     * @return array<string, mixed>
     */
    private function weightedBundleMetrics(array $weights, float $baseline): array
    {
        $trades = Trade::query()
            ->whereIn('strategy_id', array_keys($weights))
            ->whereNotNull('exit_time')
            ->orderBy('exit_time')
            ->orderBy('id')
            ->get(['id', 'strategy_id', 'exit_time', 'net_profit']);

        if ($trades->isEmpty()) {
            return [
                'has_data' => false,
                'net_profit' => 0.0,
                'max_drawdown' => 0.0,
                'max_drawdown_percent' => 0.0,
                'profit_factor' => null,
                'net_profit_to_drawdown' => null,
                'win_rate' => 0.0,
                'total_trades' => 0,
                'positive_months_percent' => 0.0,
                'equity_r2' => 0.0,
                'ulcer_index' => 0.0,
            ];
        }

        $cum = 0.0;
        $curve = [];
        $months = [];
        $grossProfit = 0.0;
        $grossLoss = 0.0;
        $wins = 0;
        $count = 0;

        foreach ($trades as $trade) {
            $weighted = (float) $trade->net_profit * ($weights[(int) $trade->strategy_id] ?? 0.0);
            $cum += $weighted;
            $curve[] = ['equity' => round($cum, 2)];
            $count++;

            if ($weighted > 0) {
                $grossProfit += $weighted;
                $wins++;
            } elseif ($weighted < 0) {
                $grossLoss += abs($weighted);
            }

            $monthKey = $trade->exit_time?->format('Y-m');

            if ($monthKey !== null) {
                $months[$monthKey] = ($months[$monthKey] ?? 0.0) + $weighted;
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
            'has_data' => true,
            'net_profit' => $netProfit,
            'max_drawdown' => (float) $drawdown['max_drawdown'],
            'max_drawdown_percent' => (float) $drawdown['max_drawdown_percent'],
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'net_profit_to_drawdown' => $absDrawdown > 0 ? round($netProfit / $absDrawdown, 2) : null,
            'win_rate' => $count > 0 ? round(($wins / $count) * 100, 2) : 0.0,
            'total_trades' => $count,
            'positive_months_percent' => $monthsWithTrades > 0 ? round(($positiveMonths / $monthsWithTrades) * 100, 2) : 0.0,
            'equity_r2' => $equityR2,
            'ulcer_index' => $ulcer,
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
