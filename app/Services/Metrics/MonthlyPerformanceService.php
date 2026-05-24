<?php

namespace App\Services\Metrics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MonthlyPerformanceService
{
    public const MONTHS = [
        1 => 'Jan',
        2 => 'Fev',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'Mai',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Set',
        10 => 'Out',
        11 => 'Nov',
        12 => 'Dez',
    ];

    /**
     * @param  Collection<int, mixed>  $trades
     * @return array<int, array{year: int, months: array<string, float>, ytd: float}>
     */
    public function calculate(Collection $trades): array
    {
        $rows = [];

        foreach ($trades as $trade) {
            $exitTime = $this->exitTime($trade);

            if ($exitTime === null) {
                continue;
            }

            $year = (int) $exitTime->format('Y');
            $month = self::MONTHS[(int) $exitTime->format('n')];
            $netProfit = (float) (data_get($trade, 'net_profit') ?? 0);

            $rows[$year] ??= [
                'year' => $year,
                'months' => array_fill_keys(array_values(self::MONTHS), 0.0),
                'ytd' => 0.0,
            ];

            $rows[$year]['months'][$month] += $netProfit;
            $rows[$year]['ytd'] += $netProfit;
        }

        krsort($rows);

        return collect($rows)
            ->map(function (array $row): array {
                foreach ($row['months'] as $month => $value) {
                    $row['months'][$month] = round((float) $value, 2);
                }

                $row['ytd'] = round((float) $row['ytd'], 2);

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $trades
     * @return array<int, array{year: int, months: array<string, float|null>, ytd: float|null}>
     */
    public function calculateCumulative(Collection $trades): array
    {
        $orderedTrades = $trades
            ->filter(fn (mixed $trade): bool => $this->exitTime($trade) !== null)
            ->sort(fn (mixed $first, mixed $second): int => [
                $this->exitTime($first)?->getTimestamp() ?? 0,
                (int) (data_get($first, 'id') ?? 0),
            ] <=> [
                $this->exitTime($second)?->getTimestamp() ?? 0,
                (int) (data_get($second, 'id') ?? 0),
            ])
            ->values();

        if ($orderedTrades->isEmpty()) {
            return [];
        }

        $monthlyTotals = [];

        foreach ($orderedTrades as $trade) {
            $exitTime = $this->exitTime($trade);

            if ($exitTime === null) {
                continue;
            }

            $year = (int) $exitTime->format('Y');
            $month = (int) $exitTime->format('n');

            $monthlyTotals[$year][$month] ??= 0.0;
            $monthlyTotals[$year][$month] += (float) (data_get($trade, 'net_profit') ?? 0);
        }

        $firstExitTime = $this->exitTime($orderedTrades->first());
        $lastExitTime = $this->exitTime($orderedTrades->last());

        if ($firstExitTime === null || $lastExitTime === null) {
            return [];
        }

        $firstMonthIndex = ((int) $firstExitTime->format('Y') * 12) + (int) $firstExitTime->format('n');
        $lastMonthIndex = ((int) $lastExitTime->format('Y') * 12) + (int) $lastExitTime->format('n');
        $equity = 0.0;
        $rows = [];

        for ($year = (int) $firstExitTime->format('Y'); $year <= (int) $lastExitTime->format('Y'); $year++) {
            $months = [];
            $lastValueInYear = null;

            foreach (self::MONTHS as $monthNumber => $monthLabel) {
                $monthIndex = ($year * 12) + $monthNumber;

                if ($monthIndex < $firstMonthIndex || $monthIndex > $lastMonthIndex) {
                    $months[$monthLabel] = null;

                    continue;
                }

                $equity += (float) ($monthlyTotals[$year][$monthNumber] ?? 0);
                $lastValueInYear = round($equity, 2);
                $months[$monthLabel] = $lastValueInYear;
            }

            $rows[] = [
                'year' => $year,
                'months' => $months,
                'ytd' => $lastValueInYear,
            ];
        }

        return collect($rows)
            ->sortByDesc('year')
            ->values()
            ->all();
    }

    private function exitTime(mixed $trade): ?CarbonInterface
    {
        $value = data_get($trade, 'exit_time');

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
}
