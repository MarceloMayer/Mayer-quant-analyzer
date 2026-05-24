<?php

namespace App\Services\Trading;

use Illuminate\Support\Collection;

class PerformanceMetricsService
{
    /**
     * @param  Collection<int, object>  $trades
     * @return array<string, mixed>
     */
    public function calculate(Collection $trades): array
    {
        $orderedTrades = $trades
            ->sortBy([
                fn (object $trade): int => $trade->exit_time?->timestamp ?? 0,
                fn (object $trade): int => $trade->id ?? 0,
            ])
            ->values();

        $equity = 0.0;
        $peak = 0.0;
        $maxDrawdown = 0.0;
        $maxDrawdownPercent = 0.0;
        $equityCurve = [];
        $drawdownCurve = [];
        $monthly = [];

        foreach ($orderedTrades as $index => $trade) {
            $profit = (float) $trade->net_profit;
            $equity += $profit;
            $peak = max($peak, $equity);

            $drawdown = $equity - $peak;
            $drawdownPercent = $peak > 0 ? ($drawdown / $peak) * 100 : 0.0;

            $maxDrawdown = min($maxDrawdown, $drawdown);
            $maxDrawdownPercent = min($maxDrawdownPercent, $drawdownPercent);

            $date = $trade->exit_time;
            $label = $date?->format('d/m/Y H:i') ?? (string) ($index + 1);

            $equityCurve[] = [
                'label' => $label,
                'value' => round($equity, 2),
            ];

            $drawdownCurve[] = [
                'label' => $label,
                'value' => round($drawdown, 2),
                'percent' => round($drawdownPercent, 2),
            ];

            if ($date !== null) {
                $year = (int) $date->format('Y');
                $month = (int) $date->format('n');

                $monthly[$year][$month] ??= [
                    'profit' => 0.0,
                    'trades' => 0,
                ];

                $monthly[$year][$month]['profit'] += $profit;
                $monthly[$year][$month]['trades']++;
            }
        }

        ksort($monthly);

        $monthlyTable = [];

        foreach ($monthly as $year => $months) {
            $row = [
                'year' => $year,
                'months' => [],
                'total' => 0.0,
                'trades' => 0,
            ];

            for ($month = 1; $month <= 12; $month++) {
                $profit = round($months[$month]['profit'] ?? 0.0, 2);
                $tradeCount = $months[$month]['trades'] ?? 0;

                $row['months'][$month] = [
                    'profit' => $profit,
                    'trades' => $tradeCount,
                ];

                $row['total'] += $profit;
                $row['trades'] += $tradeCount;
            }

            $row['total'] = round($row['total'], 2);
            $monthlyTable[] = $row;
        }

        $profits = $orderedTrades->map(fn (object $trade): float => (float) $trade->net_profit);
        $winningTrades = $profits->filter(fn (float $profit): bool => $profit > 0);
        $losingTrades = $profits->filter(fn (float $profit): bool => $profit < 0);
        $grossProfit = (float) $winningTrades->sum();
        $grossLoss = abs((float) $losingTrades->sum());
        $totalTrades = $orderedTrades->count();
        $netProfit = (float) $profits->sum();

        return [
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades->count(),
            'losing_trades' => $losingTrades->count(),
            'breakeven_trades' => $profits->filter(fn (float $profit): bool => $profit === 0.0)->count(),
            'win_rate' => $totalTrades > 0 ? ($winningTrades->count() / $totalTrades) * 100 : 0.0,
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'net_profit' => round($netProfit, 2),
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'average_trade' => $totalTrades > 0 ? round($netProfit / $totalTrades, 2) : 0.0,
            'average_win' => $winningTrades->isNotEmpty() ? round((float) $winningTrades->avg(), 2) : 0.0,
            'average_loss' => $losingTrades->isNotEmpty() ? round((float) $losingTrades->avg(), 2) : 0.0,
            'best_trade' => $profits->isNotEmpty() ? round((float) $profits->max(), 2) : 0.0,
            'worst_trade' => $profits->isNotEmpty() ? round((float) $profits->min(), 2) : 0.0,
            'max_drawdown' => round(abs($maxDrawdown), 2),
            'max_drawdown_percent' => round(abs($maxDrawdownPercent), 2),
            'equity_curve' => $equityCurve,
            'drawdown_curve' => $drawdownCurve,
            'monthly_table' => $monthlyTable,
        ];
    }
}
