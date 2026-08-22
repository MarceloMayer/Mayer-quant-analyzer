<?php

namespace App\Services\Metrics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PortfolioDailyPerformanceService
{
    /**
     * @param  Collection<int, mixed>  $trades
     * @return array<string, mixed>
     */
    public function calculate(Collection $trades): array
    {
        $dailyResults = [];

        foreach ($trades as $trade) {
            $exitTime = $this->exitTime($trade);

            if ($exitTime === null) {
                continue;
            }

            $date = $exitTime->toDateString();

            $dailyResults[$date] ??= [
                'date' => $date,
                'net_profit' => 0.0,
                'trades' => 0,
            ];

            $dailyResults[$date]['net_profit'] += (float) (data_get($trade, 'net_profit') ?? 0);
            $dailyResults[$date]['trades']++;
        }

        $rows = collect($dailyResults)
            ->sortKeys()
            ->map(function (array $row): array {
                $netProfit = round((float) $row['net_profit'], 2);

                return [
                    'date' => $row['date'],
                    'net_profit' => $netProfit,
                    'trades' => (int) $row['trades'],
                    'classification' => match (true) {
                        $netProfit > 0 => 'positive',
                        $netProfit < 0 => 'negative',
                        default => 'neutral',
                    },
                ];
            })
            ->values();

        $positiveDays = $rows->filter(fn (array $row): bool => (float) $row['net_profit'] > 0);
        $negativeDays = $rows->filter(fn (array $row): bool => (float) $row['net_profit'] < 0);
        $neutralDays = $rows->filter(fn (array $row): bool => (float) $row['net_profit'] === 0.0);
        $totalDays = $rows->count();

        return [
            'has_data' => $totalDays > 0,
            'total_days' => $totalDays,
            'positive_days' => $positiveDays->count(),
            'negative_days' => $negativeDays->count(),
            'neutral_days' => $neutralDays->count(),
            'positive_day_rate' => $this->percent($positiveDays->count(), $totalDays),
            'negative_day_rate' => $this->percent($negativeDays->count(), $totalDays),
            'neutral_day_rate' => $this->percent($neutralDays->count(), $totalDays),
            'positive_negative_ratio' => $negativeDays->count() > 0
                ? round($positiveDays->count() / $negativeDays->count(), 2)
                : null,
            'average_positive_day' => $positiveDays->isNotEmpty()
                ? round((float) $positiveDays->avg('net_profit'), 2)
                : 0.0,
            'average_negative_day' => $negativeDays->isNotEmpty()
                ? round((float) $negativeDays->avg('net_profit'), 2)
                : 0.0,
            'positive_days_net_profit' => round((float) $positiveDays->sum('net_profit'), 2),
            'negative_days_net_profit' => round((float) $negativeDays->sum('net_profit'), 2),
            'best_day' => $rows->sortByDesc('net_profit')->first(),
            'worst_day' => $rows->sortBy('net_profit')->first(),
            'rows' => $rows->sortByDesc('date')->values()->all(),
        ];
    }

    private function percent(int $value, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
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
