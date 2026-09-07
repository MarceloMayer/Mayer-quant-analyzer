<?php

namespace App\Services\Metrics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DaysWithoutNewHighCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $equityCurve
     * @return array{max_days_without_new_high: int}
     */
    public function calculate(array $equityCurve): array
    {
        $peak = 0.0;
        $lastHighDate = null;
        $maxDaysWithoutNewHigh = 0;

        foreach ($this->datedPoints($equityCurve) as $point) {
            $date = $point['date'];
            $equity = $point['equity'];

            $lastHighDate ??= $date;

            if ($equity > $peak) {
                $peak = $equity;
                $lastHighDate = $date;

                continue;
            }

            $days = (int) $lastHighDate->startOfDay()->diffInDays($date->startOfDay());
            $maxDaysWithoutNewHigh = max($maxDaysWithoutNewHigh, $days);
        }

        return [
            'max_days_without_new_high' => $maxDaysWithoutNewHigh,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $equityCurve
     * @return array<int, array{date: CarbonInterface, equity: float}>
     */
    private function datedPoints(array $equityCurve): array
    {
        return collect($equityCurve)
            ->map(fn (array $point): array => [
                'date' => $this->date($point['date'] ?? null),
                'equity' => (float) ($point['equity'] ?? 0),
            ])
            ->filter(fn (array $point): bool => $point['date'] instanceof CarbonInterface)
            ->sortBy(fn (array $point): int => $point['date']->getTimestamp())
            ->values()
            ->all();
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
}
