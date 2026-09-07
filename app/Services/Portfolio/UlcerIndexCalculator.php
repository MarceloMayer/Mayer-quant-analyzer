<?php

namespace App\Services\Portfolio;

class UlcerIndexCalculator
{
    /**
     * Calculates the Ulcer Index from an equity curve.
     * Measures both depth and duration of drawdowns below the running peak.
     *
     * @param  array<int, array{equity: float}>  $equityCurve
     * @param  float  $initialBalance  Starting balance the running peak is measured against.
     *                                 Defaults to 0 (pure P&L curve), matching the original
     *                                 behavior for callers that don't track capital.
     */
    public function calculate(array $equityCurve, float $initialBalance = 0.0): float
    {
        $count = count($equityCurve);

        if ($count < 2) {
            return 0.0;
        }

        $peak = $initialBalance;
        $squaredDrawdowns = [];

        foreach ($equityCurve as $point) {
            $equity = $initialBalance + (float) ($point['equity'] ?? 0);
            $peak = max($peak, $equity);

            $drawdownPercent = $peak > 0 ? (($peak - $equity) / $peak) * 100 : 0.0;
            $squaredDrawdowns[] = $drawdownPercent ** 2;
        }

        $mean = array_sum($squaredDrawdowns) / count($squaredDrawdowns);

        return round(sqrt($mean), 4);
    }
}
