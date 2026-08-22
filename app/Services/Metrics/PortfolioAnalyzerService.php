<?php

namespace App\Services\Metrics;

use App\Models\Portfolio;
use App\Models\PortfolioStrategy;
use App\Models\Trade;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PortfolioAnalyzerService
{
    public function __construct(
        private readonly EquityCurveService $equityCurveService,
        private readonly DrawdownCalculator $drawdownCalculator,
        private readonly DaysWithoutNewHighCalculator $daysWithoutNewHighCalculator,
        private readonly MonthlyPerformanceService $monthlyPerformanceService,
        private readonly StreakCalculator $streakCalculator,
        private readonly PortfolioDailyPerformanceService $dailyPerformanceService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(Portfolio $portfolio): array
    {
        return $this->calculate($portfolio);
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(Portfolio $portfolio): array
    {
        $strategyWeights = $this->enabledStrategyWeights($portfolio);
        $activeStrategiesCount = $strategyWeights->keys()->unique()->count();

        $trades = $this->weightedClosedTrades(
            $this->trades($strategyWeights->keys()),
            $strategyWeights,
        );

        $equityCurve = $this->equityCurveService->calculate($trades);
        $drawdown = $this->drawdownCalculator->calculate($equityCurve);
        $streaks = $this->streakCalculator->calculate($trades);
        $monthlyPerformance = $this->monthlyPerformanceService->calculate($trades);
        $monthlyCumulativePerformance = $this->monthlyPerformanceService->calculateCumulative($trades);
        $dailyPerformance = $this->dailyPerformanceService->calculate($trades);
        $daysWithoutNewHigh = $this->daysWithoutNewHighCalculator->calculate($equityCurve);

        $profits = $trades->map(fn (object $trade): float => (float) data_get($trade, 'net_profit', 0));
        $winningTrades = $profits->filter(fn (float $profit): bool => $profit > 0);
        $losingTrades = $profits->filter(fn (float $profit): bool => $profit < 0);
        $grossProfit = (float) $winningTrades->sum();
        $grossLoss = abs((float) $losingTrades->sum());
        $netProfit = (float) $profits->sum();
        $totalTrades = $profits->count();
        $averageWin = $winningTrades->isNotEmpty() ? (float) $winningTrades->avg() : 0.0;
        $averageLoss = $losingTrades->isNotEmpty() ? (float) $losingTrades->avg() : 0.0;
        $payoff = $averageWin > 0 && $averageLoss < 0 ? $averageWin / abs($averageLoss) : null;

        return [
            'consolidated_net_profit' => round($netProfit, 2),
            'net_profit' => round($netProfit, 2),
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades->count(),
            'losing_trades' => $losingTrades->count(),
            'breakeven_trades' => $profits->filter(fn (float $profit): bool => $profit === 0.0)->count(),
            'consolidated_win_rate' => $totalTrades > 0 ? round(($winningTrades->count() / $totalTrades) * 100, 2) : 0.0,
            'win_rate' => $totalTrades > 0 ? round(($winningTrades->count() / $totalTrades) * 100, 2) : 0.0,
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'consolidated_profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'consolidated_payoff' => $payoff !== null ? round($payoff, 2) : null,
            'average_payoff' => $payoff !== null ? round($payoff, 2) : null,
            'payoff' => $payoff !== null ? round($payoff, 2) : null,
            'average_trade' => $totalTrades > 0 ? round($netProfit / $totalTrades, 2) : 0.0,
            'average_win' => round($averageWin, 2),
            'average_loss' => round($averageLoss, 2),
            'best_trade' => $profits->isNotEmpty() ? round((float) $profits->max(), 2) : 0.0,
            'worst_trade' => $profits->isNotEmpty() ? round((float) $profits->min(), 2) : 0.0,
            'max_winning_streak' => $streaks['max_winning_streak'],
            'max_losing_streak' => $streaks['max_losing_streak'],
            'consolidated_max_drawdown' => $drawdown['max_drawdown'],
            'max_drawdown' => $drawdown['max_drawdown'],
            'consolidated_max_drawdown_percent' => $drawdown['max_drawdown_percent'],
            'max_drawdown_percent' => $drawdown['max_drawdown_percent'],
            'drawdown_peak' => $drawdown['peak'],
            'drawdown_valley' => $drawdown['valley'],
            'consolidated_equity_curve' => $equityCurve,
            'equity_curve' => $equityCurve,
            'drawdown_curve' => $this->drawdownCurve($equityCurve),
            'consolidated_monthly_performance' => $monthlyPerformance,
            'monthly_performance' => $monthlyPerformance,
            'consolidated_monthly_cumulative_performance' => $monthlyCumulativePerformance,
            'monthly_cumulative_performance' => $monthlyCumulativePerformance,
            'daily_performance' => $dailyPerformance,
            'monthly_table' => $this->monthlyTable($monthlyPerformance, $trades),
            'consolidated_max_days_without_new_high' => $daysWithoutNewHigh['max_days_without_new_high'],
            'max_days_without_new_high' => $daysWithoutNewHigh['max_days_without_new_high'],
            'active_strategies_count' => $activeStrategiesCount,
            'strategies_count' => $activeStrategiesCount,
            'strategy_summaries' => $this->strategySummaries($portfolio),
            'consolidated_trades' => $this->summarizedTrades($trades),
        ];
    }

    /**
     * @return Collection<int, float>
     */
    private function enabledStrategyWeights(Portfolio $portfolio): Collection
    {
        return $portfolio->portfolioStrategies()
            ->where('enabled', true)
            ->get(['strategy_id', 'weight'])
            ->mapWithKeys(fn (PortfolioStrategy $portfolioStrategy): array => [
                $portfolioStrategy->strategy_id => (float) ($portfolioStrategy->weight ?? 1),
            ]);
    }

    /**
     * @param  Collection<int, int>  $strategyIds
     * @return Collection<int, Trade>
     */
    private function trades(Collection $strategyIds): Collection
    {
        if ($strategyIds->isEmpty()) {
            return collect();
        }

        return Trade::query()
            ->with('strategy')
            ->whereIn('strategy_id', $strategyIds)
            ->orderBy('exit_time')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @param  Collection<int, float>  $strategyWeights
     * @return Collection<int, object>
     */
    private function weightedClosedTrades(Collection $trades, Collection $strategyWeights): Collection
    {
        return $trades
            ->filter(fn (Trade $trade): bool => $trade->exit_time !== null && $strategyWeights->has($trade->strategy_id))
            ->map(function (Trade $trade) use ($strategyWeights): object {
                $weight = (float) $strategyWeights->get($trade->strategy_id, 1);

                return (object) [
                    'id' => $trade->id,
                    'strategy_id' => $trade->strategy_id,
                    'strategy_name' => $trade->strategy?->name,
                    'order_id' => $trade->order_id,
                    'asset' => $trade->asset,
                    'direction' => $trade->direction,
                    'volume' => $trade->volume,
                    'entry_time' => $trade->entry_time,
                    'exit_time' => $trade->exit_time,
                    'entry_price' => $trade->entry_price,
                    'exit_price' => $trade->exit_price,
                    'original_net_profit' => (float) $trade->net_profit,
                    'weight' => $weight,
                    'net_profit' => (float) $trade->net_profit * $weight,
                ];
            })
            ->sort(fn (object $first, object $second): int => [
                $this->exitTime($first)?->getTimestamp() ?? 0,
                (int) data_get($first, 'id', 0),
            ] <=> [
                $this->exitTime($second)?->getTimestamp() ?? 0,
                (int) data_get($second, 'id', 0),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, object>  $trades
     * @return array<int, array<string, mixed>>
     */
    private function summarizedTrades(Collection $trades): array
    {
        $cumulativeNetProfit = 0.0;

        return $trades
            ->sort(fn (object $first, object $second): int => [
                $this->exitTime($first)?->getTimestamp() ?? 0,
                (int) data_get($first, 'id', 0),
            ] <=> [
                $this->exitTime($second)?->getTimestamp() ?? 0,
                (int) data_get($second, 'id', 0),
            ])
            ->map(function (object $trade) use (&$cumulativeNetProfit): array {
                $netProfit = (float) data_get($trade, 'net_profit', 0);
                $cumulativeNetProfit += $netProfit;

                return [
                    'id' => data_get($trade, 'id'),
                    'strategy_id' => data_get($trade, 'strategy_id'),
                    'strategy_name' => data_get($trade, 'strategy_name'),
                    'asset' => data_get($trade, 'asset'),
                    'direction' => data_get($trade, 'direction'),
                    'volume' => data_get($trade, 'volume'),
                    'exit_time' => $this->exitTime($trade)?->format('Y-m-d H:i:s'),
                    'original_net_profit' => round((float) data_get($trade, 'original_net_profit', 0), 2),
                    'weight' => round((float) data_get($trade, 'weight', 1), 8),
                    'net_profit' => round($netProfit, 2),
                    'cumulative_net_profit' => round($cumulativeNetProfit, 2),
                ];
            })
            ->sort(fn (array $first, array $second): int => [
                (string) ($second['exit_time'] ?? ''),
                (int) ($second['id'] ?? 0),
            ] <=> [
                (string) ($first['exit_time'] ?? ''),
                (int) ($first['id'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function strategySummaries(Portfolio $portfolio): array
    {
        $portfolioStrategies = $portfolio->portfolioStrategies()
            ->with('strategy')
            ->orderByDesc('enabled')
            ->orderBy('id')
            ->get();

        $strategyIds = $portfolioStrategies
            ->pluck('strategy_id')
            ->filter()
            ->unique()
            ->values();

        $tradesByStrategy = $strategyIds->isEmpty()
            ? collect()
            : Trade::query()
                ->whereIn('strategy_id', $strategyIds)
                ->whereNotNull('exit_time')
                ->orderBy('exit_time')
                ->orderBy('id')
                ->get()
                ->groupBy('strategy_id');

        return $portfolioStrategies
            ->map(function (PortfolioStrategy $portfolioStrategy) use ($tradesByStrategy): array {
                $strategy = $portfolioStrategy->strategy;
                $trades = $strategy === null
                    ? collect()
                    : $tradesByStrategy->get($strategy->id, collect());
                $equityCurve = $this->equityCurveService->calculate($trades);
                $drawdown = $this->drawdownCalculator->calculate($equityCurve);
                $netProfit = (float) $trades->sum(fn (Trade $trade): float => (float) $trade->net_profit);

                return [
                    'strategy_id' => $strategy?->id,
                    'name' => $strategy?->name ?? '-',
                    'asset' => $strategy?->asset,
                    'enabled' => (bool) $portfolioStrategy->enabled,
                    'weight' => (float) ($portfolioStrategy->weight ?? 1),
                    'total_trades' => $trades->count(),
                    'net_profit' => round($netProfit, 2),
                    'max_drawdown' => $drawdown['max_drawdown'],
                    'max_drawdown_percent' => $drawdown['max_drawdown_percent'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $equityCurve
     * @return array<int, array{date: string|null, label: string, value: float, percent: float}>
     */
    private function drawdownCurve(array $equityCurve): array
    {
        $peak = 0.0;

        return collect($equityCurve)
            ->map(function (array $point) use (&$peak): array {
                $equity = (float) ($point['equity'] ?? 0);
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
     * @param  array<int, array{year: int, months: array<string, float>, ytd: float}>  $monthlyPerformance
     * @param  Collection<int, object>  $trades
     * @return array<int, array{year: int, months: array<int, array{profit: float, trades: int}>, total: float, trades: int}>
     */
    private function monthlyTable(array $monthlyPerformance, Collection $trades): array
    {
        $tradeCounts = [];

        foreach ($trades as $trade) {
            $exitTime = $this->exitTime($trade);

            if ($exitTime === null) {
                continue;
            }

            $year = (int) $exitTime->format('Y');
            $month = MonthlyPerformanceService::MONTHS[(int) $exitTime->format('n')];

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
