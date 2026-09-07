<?php

namespace App\Services\Metrics;

use App\Models\Strategy;
use App\Models\Trade;
use Illuminate\Support\Collection;

class StrategyMetricsService
{
    public function __construct(
        private readonly EquityCurveService $equityCurveService,
        private readonly DrawdownCalculator $drawdownCalculator,
        private readonly DaysWithoutNewHighCalculator $daysWithoutNewHighCalculator,
        private readonly MonthlyPerformanceService $monthlyPerformanceService,
        private readonly StreakCalculator $streakCalculator,
        private readonly InitialCapitalResolver $initialCapitalResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(Strategy $strategy): array
    {
        $trades = $strategy->trades()
            ->whereNotNull('exit_time')
            ->orderBy('exit_time')
            ->orderBy('id')
            ->get();

        $initialCapital = $this->initialCapitalResolver->resolve($strategy);
        $equityCurve = $this->equityCurveService->calculate($trades);
        $drawdown = $this->drawdownCalculator->calculate($equityCurve, $initialCapital);
        $streaks = $this->streakCalculator->calculate($trades);
        $monthlyPerformance = $this->monthlyPerformanceService->calculate($trades);
        $monthlyCumulativePerformance = $this->monthlyPerformanceService->calculateCumulative($trades);
        $daysWithoutNewHigh = $this->daysWithoutNewHighCalculator->calculate($equityCurve);

        $profits = $trades->map(fn (Trade $trade): float => (float) $trade->net_profit);
        $winningTrades = $profits->filter(fn (float $profit): bool => $profit > 0);
        $losingTrades = $profits->filter(fn (float $profit): bool => $profit < 0);
        $grossProfit = (float) $winningTrades->sum();
        $grossLoss = abs((float) $losingTrades->sum());
        $netProfit = (float) $profits->sum();
        $totalTrades = $profits->count();
        $averageWin = $winningTrades->isNotEmpty() ? (float) $winningTrades->avg() : 0.0;
        $averageLoss = $losingTrades->isNotEmpty() ? (float) $losingTrades->avg() : 0.0;
        $averagePayoff = $averageWin > 0 && $averageLoss < 0 ? $averageWin / abs($averageLoss) : null;

        return [
            'net_profit' => round($netProfit, 2),
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades->count(),
            'losing_trades' => $losingTrades->count(),
            'breakeven_trades' => $profits->filter(fn (float $profit): bool => $profit === 0.0)->count(),
            'win_rate' => $totalTrades > 0 ? round(($winningTrades->count() / $totalTrades) * 100, 2) : 0.0,
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'average_payoff' => $averagePayoff !== null ? round($averagePayoff, 2) : null,
            'payoff' => $averagePayoff !== null ? round($averagePayoff, 2) : null,
            'average_trade' => $totalTrades > 0 ? round($netProfit / $totalTrades, 2) : 0.0,
            'average_win' => round($averageWin, 2),
            'average_loss' => round($averageLoss, 2),
            'best_trade' => $profits->isNotEmpty() ? round((float) $profits->max(), 2) : 0.0,
            'worst_trade' => $profits->isNotEmpty() ? round((float) $profits->min(), 2) : 0.0,
            'max_winning_streak' => $streaks['max_winning_streak'],
            'max_losing_streak' => $streaks['max_losing_streak'],
            'max_drawdown' => $drawdown['max_drawdown'],
            'max_drawdown_percent' => $drawdown['max_drawdown_percent'],
            'drawdown_peak' => $drawdown['peak'],
            'drawdown_valley' => $drawdown['valley'],
            'equity_curve' => $equityCurve,
            'drawdown_curve' => $this->drawdownCurve($equityCurve, $initialCapital),
            'monthly_performance' => $monthlyPerformance,
            'monthly_cumulative_performance' => $monthlyCumulativePerformance,
            'monthly_table' => $this->monthlyTable($monthlyPerformance, $trades),
            'strategy_trades' => $this->summarizedTrades($trades),
            'max_days_without_new_high' => $daysWithoutNewHigh['max_days_without_new_high'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $equityCurve
     * @return array<int, array{date: string|null, label: string, value: float, percent: float}>
     */
    private function drawdownCurve(array $equityCurve, float $initialBalance = 0.0): array
    {
        $peak = $initialBalance;

        return collect($equityCurve)
            ->map(function (array $point) use (&$peak, $initialBalance): array {
                $equity = $initialBalance + (float) ($point['equity'] ?? 0);
                $peak = max($peak, $equity);
                $drawdown = $equity - $peak;
                $drawdownPercent = $peak > 0 ? ($drawdown / $peak) * 100 : 0.0;

                return [
                    'date' => $point['date'] ?? null,
                    'label' => (string) ($point['date'] ?? ''),
                    'value' => round($drawdown, 2),
                    'percent' => round($drawdownPercent, 2),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<int, array<string, mixed>>
     */
    private function summarizedTrades(Collection $trades): array
    {
        return $trades
            ->sort(fn (Trade $first, Trade $second): int => [
                $second->exit_time?->getTimestamp() ?? 0,
                $second->id,
            ] <=> [
                $first->exit_time?->getTimestamp() ?? 0,
                $first->id,
            ])
            ->map(fn (Trade $trade): array => [
                'id' => $trade->id,
                'asset' => $trade->asset,
                'direction' => $trade->direction,
                'volume' => $trade->volume === null ? null : (float) $trade->volume,
                'entry_time' => $trade->entry_time?->format('Y-m-d H:i:s'),
                'exit_time' => $trade->exit_time?->format('Y-m-d H:i:s'),
                'entry_price' => $trade->entry_price === null ? null : (float) $trade->entry_price,
                'exit_price' => $trade->exit_price === null ? null : (float) $trade->exit_price,
                'net_profit' => round((float) $trade->net_profit, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{year: int, months: array<string, float>, ytd: float}>  $monthlyPerformance
     * @param  Collection<int, Trade>  $trades
     * @return array<int, array{year: int, months: array<int, array{profit: float, trades: int}>, total: float, trades: int}>
     */
    private function monthlyTable(array $monthlyPerformance, Collection $trades): array
    {
        $tradeCounts = [];

        foreach ($trades as $trade) {
            if ($trade->exit_time === null) {
                continue;
            }

            $year = (int) $trade->exit_time->format('Y');
            $month = MonthlyPerformanceService::MONTHS[(int) $trade->exit_time->format('n')];

            $tradeCounts[$year][$month] ??= 0;
            $tradeCounts[$year][$month]++;
        }

        return collect($monthlyPerformance)
            ->map(function (array $row) use ($tradeCounts): array {
                $months = [];
                $totalTrades = 0;

                foreach ($row['months'] as $month => $profit) {
                    $trades = $tradeCounts[$row['year']][$month] ?? 0;
                    $totalTrades += $trades;

                    $months[] = [
                        'profit' => round((float) $profit, 2),
                        'trades' => $trades,
                    ];
                }

                return [
                    'year' => $row['year'],
                    'months' => $months,
                    'total' => round((float) $row['ytd'], 2),
                    'trades' => $totalTrades,
                ];
            })
            ->all();
    }
}
