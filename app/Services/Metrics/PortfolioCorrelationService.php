<?php

namespace App\Services\Metrics;

use App\Models\Portfolio;
use App\Models\PortfolioStrategy;
use App\Models\Trade;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PortfolioCorrelationService
{
    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    public const METRIC_PROFIT_LOSS = 'profit_loss';

    /**
     * @return array<string, mixed>
     */
    public function calculate(
        Portfolio $portfolio,
        string $period = self::PERIOD_MONTHLY,
        string $metric = self::METRIC_PROFIT_LOSS,
    ): array {
        $period = $this->normalizePeriod($period);
        $metric = $this->normalizeMetric($metric);
        $strategies = $this->activeStrategies($portfolio);
        $trades = $this->closedTrades($strategies->pluck('id'));
        $periods = $this->periods($trades, $period);
        $series = $this->series($strategies, $trades, $periods, $period, $metric);
        $pairs = [];

        $matrix = $strategies
            ->map(function (array $rowStrategy) use ($strategies, $series, &$pairs): array {
                $cells = $strategies
                    ->map(function (array $columnStrategy) use ($rowStrategy, $series, &$pairs): array {
                        $isDiagonal = $rowStrategy['id'] === $columnStrategy['id'];
                        $correlation = $isDiagonal
                            ? 1.0
                            : $this->pearson(
                                $series[$rowStrategy['id']] ?? [],
                                $series[$columnStrategy['id']] ?? [],
                            );

                        $cell = $this->cell($correlation, $rowStrategy['name'], $columnStrategy['name'], $isDiagonal);

                        if (! $isDiagonal && $correlation !== null && $rowStrategy['id'] < $columnStrategy['id']) {
                            $pairs[] = [
                                'first_strategy_id' => $rowStrategy['id'],
                                'second_strategy_id' => $columnStrategy['id'],
                                'first_strategy_name' => $rowStrategy['name'],
                                'second_strategy_name' => $columnStrategy['name'],
                                'label' => "{$rowStrategy['name']} x {$columnStrategy['name']}",
                                'value' => round($correlation, 4),
                                'display' => $this->formatCorrelation($correlation),
                                'absolute_value' => round(abs($correlation), 4),
                                'description' => $this->correlationDescription($correlation),
                            ];
                        }

                        return $cell;
                    })
                    ->values()
                    ->all();

                return [
                    'strategy' => $rowStrategy,
                    'cells' => $cells,
                ];
            })
            ->values()
            ->all();

        return [
            'period' => $period,
            'period_label' => $this->periodOptions()[$period],
            'period_options' => $this->periodOptions(),
            'metric' => $metric,
            'metric_label' => $this->metricOptions()[$metric],
            'metric_options' => $this->metricOptions(),
            'strategies' => $strategies->values()->all(),
            'periods' => array_values($periods),
            'series' => $series,
            'matrix' => $matrix,
            'summary' => $this->summary($pairs),
            'legend' => $this->legend(),
            'has_enough_strategies' => $strategies->count() >= 2,
            'has_periods' => count($periods) >= 2,
        ];
    }

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
     * @return array<string, string>
     */
    public function metricOptions(): array
    {
        return [
            self::METRIC_PROFIT_LOSS => 'Profit/Loss',
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, asset: string|null}>
     */
    private function activeStrategies(Portfolio $portfolio): Collection
    {
        return $portfolio->portfolioStrategies()
            ->with('strategy:id,name,asset')
            ->where('enabled', true)
            ->orderBy('id')
            ->get()
            ->map(fn (PortfolioStrategy $portfolioStrategy): ?array => $portfolioStrategy->strategy === null
                ? null
                : [
                    'id' => $portfolioStrategy->strategy->id,
                    'name' => $portfolioStrategy->strategy->name,
                    'asset' => $portfolioStrategy->strategy->asset,
                ])
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, int>  $strategyIds
     * @return Collection<int, Trade>
     */
    private function closedTrades(Collection $strategyIds): Collection
    {
        if ($strategyIds->isEmpty()) {
            return collect();
        }

        return Trade::query()
            ->whereIn('strategy_id', $strategyIds)
            ->whereNotNull('exit_time')
            ->orderBy('exit_time')
            ->orderBy('id')
            ->get(['id', 'strategy_id', 'exit_time', 'net_profit']);
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<int, string>
     */
    private function periods(Collection $trades, string $period): array
    {
        return $trades
            ->map(fn (Trade $trade): ?string => $this->periodKey($trade->exit_time, $period))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{id: int, name: string, asset: string|null}>  $strategies
     * @param  Collection<int, Trade>  $trades
     * @param  array<int, string>  $periods
     * @return array<int, array<int, float>>
     */
    private function series(Collection $strategies, Collection $trades, array $periods, string $period, string $metric): array
    {
        $series = [];

        foreach ($strategies as $strategy) {
            $series[$strategy['id']] = array_fill(0, count($periods), 0.0);
        }

        $periodIndexes = array_flip($periods);

        foreach ($trades as $trade) {
            $periodKey = $this->periodKey($trade->exit_time, $period);

            if ($periodKey === null || ! array_key_exists($periodKey, $periodIndexes)) {
                continue;
            }

            $strategyId = (int) $trade->strategy_id;

            if (! array_key_exists($strategyId, $series)) {
                continue;
            }

            $series[$strategyId][$periodIndexes[$periodKey]] += $this->metricValue($trade, $metric);
        }

        return collect($series)
            ->map(fn (array $values): array => collect($values)
                ->map(fn (float $value): float => round($value, 2))
                ->all())
            ->all();
    }

    private function metricValue(Trade $trade, string $metric): float
    {
        return match ($metric) {
            self::METRIC_PROFIT_LOSS => (float) $trade->net_profit,
            default => (float) $trade->net_profit,
        };
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

        $firstSeries = array_slice($firstSeries, 0, $count);
        $secondSeries = array_slice($secondSeries, 0, $count);
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

    /**
     * @return array<string, mixed>
     */
    private function cell(?float $correlation, string $rowStrategyName, string $columnStrategyName, bool $isDiagonal): array
    {
        if ($correlation === null) {
            return [
                'value' => null,
                'display' => '-',
                'class' => 'mqa-correlation-empty',
                'tooltip' => "Dados insuficientes para calcular correlação entre {$rowStrategyName} e {$columnStrategyName}.",
                'is_diagonal' => $isDiagonal,
            ];
        }

        return [
            'value' => round($correlation, 4),
            'display' => $this->formatCorrelation($correlation),
            'class' => $isDiagonal ? 'mqa-correlation-diagonal' : $this->correlationClass($correlation),
            'tooltip' => $this->tooltip($correlation, $rowStrategyName, $columnStrategyName, $isDiagonal),
            'is_diagonal' => $isDiagonal,
        ];
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

    private function tooltip(float $correlation, string $rowStrategyName, string $columnStrategyName, bool $isDiagonal): string
    {
        if ($isDiagonal) {
            return "{$rowStrategyName} comparada com ela mesma. Correlação perfeita de 1,00.";
        }

        $relation = $correlation < 0 ? 'relação inversa' : 'relação direta';

        return "{$rowStrategyName} x {$columnStrategyName}: {$this->formatCorrelation($correlation)}. {$this->correlationDescription($correlation)} com {$relation}.";
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
     * @param  array<int, array<string, mixed>>  $pairs
     * @return array<string, mixed>
     */
    private function summary(array $pairs): array
    {
        $validPairs = collect($pairs);
        $averageAbsoluteCorrelation = $validPairs->isEmpty()
            ? null
            : round((float) $validPairs->avg('absolute_value'), 4);
        $highestPair = $validPairs
            ->sortByDesc('absolute_value')
            ->first();
        $lowestPair = $validPairs
            ->sortBy('value')
            ->first();
        $pairsAbove020 = $validPairs->filter(fn (array $pair): bool => (float) $pair['absolute_value'] > 0.20)->count();
        $pairsAbove040 = $validPairs->filter(fn (array $pair): bool => (float) $pair['absolute_value'] > 0.40)->count();
        $pairsAbove070 = $validPairs->filter(fn (array $pair): bool => (float) $pair['absolute_value'] > 0.70)->count();
        $highlightedPairs = $validPairs
            ->filter(fn (array $pair): bool => (float) $pair['absolute_value'] > 0.40)
            ->sortByDesc('absolute_value')
            ->take(5)
            ->values()
            ->all();

        return [
            'average_absolute_correlation' => $averageAbsoluteCorrelation,
            'average_absolute_correlation_display' => $this->formatNullableCorrelation($averageAbsoluteCorrelation),
            'highest_pair' => $highestPair,
            'highest_correlation_display' => $highestPair === null ? '-' : $highestPair['display'],
            'lowest_pair' => $lowestPair,
            'lowest_correlation_display' => $lowestPair === null ? '-' : $lowestPair['display'],
            'pairs_above_020' => $pairsAbove020,
            'pairs_above_040' => $pairsAbove040,
            'pairs_above_070' => $pairsAbove070,
            'highlighted_pairs' => $highlightedPairs,
            'interpretation' => $this->interpretation($averageAbsoluteCorrelation, $highestPair, $pairsAbove040),
        ];
    }

    /**
     * @return array<int, array{label: string, description: string, class: string}>
     */
    private function legend(): array
    {
        return [
            [
                'label' => 'Próximo de 0',
                'description' => 'melhor diversificação',
                'class' => 'mqa-correlation-good',
            ],
            [
                'label' => 'Acima de 0.20',
                'description' => 'atenção',
                'class' => 'mqa-correlation-warning',
            ],
            [
                'label' => 'Acima de 0.40',
                'description' => 'correlação alta',
                'class' => 'mqa-correlation-high',
            ],
            [
                'label' => 'Acima de 0.70',
                'description' => 'forte redundância',
                'class' => 'mqa-correlation-critical',
            ],
            [
                'label' => 'Valores negativos',
                'description' => 'comportamento oposto entre estratégias',
                'class' => 'mqa-correlation-inverse',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $highestPair
     * @return array{message: string, type: string}
     */
    private function interpretation(?float $averageAbsoluteCorrelation, ?array $highestPair, int $pairsAbove040): array
    {
        if ($highestPair !== null && (float) $highestPair['absolute_value'] > 0.70) {
            return [
                'message' => 'Existem estratégias muito correlacionadas neste portfólio.',
                'type' => 'danger',
            ];
        }

        if ($averageAbsoluteCorrelation !== null && $averageAbsoluteCorrelation <= 0.20) {
            return [
                'message' => 'O portfólio apresenta boa diversificação entre estratégias.',
                'type' => 'success',
            ];
        }

        if ($pairsAbove040 > 0) {
            return [
                'message' => 'Existem pares com correlação alta que merecem atenção.',
                'type' => 'warning',
            ];
        }

        return [
            'message' => 'As correlações do portfólio estão em faixa moderada.',
            'type' => 'neutral',
        ];
    }

    private function normalizePeriod(string $period): string
    {
        return array_key_exists($period, $this->periodOptions()) ? $period : self::PERIOD_MONTHLY;
    }

    private function normalizeMetric(string $metric): string
    {
        return array_key_exists($metric, $this->metricOptions()) ? $metric : self::METRIC_PROFIT_LOSS;
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
            self::PERIOD_MONTHLY => $date->format('Y-m'),
            default => $date->format('Y-m'),
        };
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
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

    private function formatCorrelation(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function formatNullableCorrelation(?float $value): string
    {
        return $value === null ? '-' : $this->formatCorrelation($value);
    }
}
