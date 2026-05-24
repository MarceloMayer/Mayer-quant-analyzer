<?php

namespace App\Services\Metrics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class StreakCalculator
{
    /**
     * @param  Collection<int, mixed>  $trades
     * @return array{max_winning_streak: int, max_losing_streak: int}
     */
    public function calculate(Collection $trades): array
    {
        $currentWinningStreak = 0;
        $currentLosingStreak = 0;
        $maxWinningStreak = 0;
        $maxLosingStreak = 0;

        foreach ($this->orderedClosedTrades($trades) as $trade) {
            $netProfit = (float) (data_get($trade, 'net_profit') ?? 0);

            if ($netProfit > 0) {
                $currentWinningStreak++;
                $currentLosingStreak = 0;
                $maxWinningStreak = max($maxWinningStreak, $currentWinningStreak);

                continue;
            }

            if ($netProfit < 0) {
                $currentLosingStreak++;
                $currentWinningStreak = 0;
                $maxLosingStreak = max($maxLosingStreak, $currentLosingStreak);
            }
        }

        return [
            'max_winning_streak' => $maxWinningStreak,
            'max_losing_streak' => $maxLosingStreak,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $trades
     * @return Collection<int, mixed>
     */
    private function orderedClosedTrades(Collection $trades): Collection
    {
        return $trades
            ->filter(fn (mixed $trade): bool => $this->exitTime($trade) !== null)
            ->sort(fn (mixed $first, mixed $second): int => [
                $this->exitTime($first)?->getTimestamp() ?? 0,
                (int) (data_get($first, 'id') ?? 0),
            ] <=> [
                $this->exitTime($second)?->getTimestamp() ?? 0,
                (int) (data_get($second, 'id') ?? 0),
            ])
            ->values();
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
