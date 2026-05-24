<?php

namespace App\Services\Metrics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EquityCurveService
{
    /**
     * @param  Collection<int, mixed>  $trades
     * @return array<int, array{date: string, net_profit: float, equity: float, value: float}>
     */
    public function calculate(Collection $trades): array
    {
        $equity = 0.0;

        return $this->orderedClosedTrades($trades)
            ->map(function (mixed $trade) use (&$equity): array {
                $netProfit = $this->netProfit($trade);
                $equity += $netProfit;

                return [
                    'date' => $this->exitTime($trade)?->format('Y-m-d H:i:s'),
                    'net_profit' => round($netProfit, 2),
                    'equity' => round($equity, 2),
                    'value' => round($equity, 2),
                ];
            })
            ->all();
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

    private function netProfit(mixed $trade): float
    {
        return (float) (data_get($trade, 'net_profit') ?? 0);
    }
}
