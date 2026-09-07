<?php

namespace App\Services\Metrics;

class DrawdownCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $equityCurve
     * @param  float  $initialBalance  Starting balance the curve's peak/percent are measured
     *                                 against. Defaults to 0 (pure P&L curve), matching the
     *                                 original behavior for callers that don't track capital.
     * @return array{max_drawdown: float, max_drawdown_percent: float, peak: float, valley: float}
     */
    public function calculate(array $equityCurve, float $initialBalance = 0.0): array
    {
        $currentPeak = $initialBalance;
        $maxDrawdown = 0.0;
        $maxDrawdownPercent = 0.0;
        $peak = 0.0;
        $valley = 0.0;

        foreach ($equityCurve as $point) {
            $equity = $initialBalance + (float) ($point['equity'] ?? 0);

            if ($equity > $currentPeak) {
                $currentPeak = $equity;
            }

            $drawdown = $currentPeak - $equity;
            $drawdownPercent = $currentPeak > 0 ? ($drawdown / $currentPeak) * 100 : 0.0;
            $maxDrawdownPercent = max($maxDrawdownPercent, $drawdownPercent);

            if ($drawdown > $maxDrawdown) {
                $maxDrawdown = $drawdown;
                $peak = $currentPeak;
                $valley = $equity;
            }
        }

        return [
            'max_drawdown' => round($maxDrawdown, 2),
            'max_drawdown_percent' => round($maxDrawdownPercent, 2),
            'peak' => round($peak, 2),
            'valley' => round($valley, 2),
        ];
    }
}
