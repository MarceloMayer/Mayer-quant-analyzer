<?php

namespace App\Services\Portfolio;

use App\Models\Strategy;
use App\Models\Trade;
use App\Services\Metrics\DrawdownCalculator;
use Illuminate\Support\Collection;

class PortfolioCombinationAnalyzerService
{
    public function __construct(
        private readonly DrawdownCalculator $drawdownCalculator,
        private readonly EquityConsistencyService $equityConsistencyService,
        private readonly PortfolioCombinationGeneratorService $generatorService,
    ) {}

    /**
     * Analyzes all combinations and returns a ranked result set.
     *
     * @param  array<int, int[]>  $combinations
     * @param  array{initial_balance?: float, start_date?: string|null, end_date?: string|null}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function analyze(array $combinations, array $filters = []): array
    {
        if (empty($combinations)) {
            return [];
        }

        $initialBalance = (float) ($filters['initial_balance'] ?? 10000);
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        $allStrategyIds = collect($combinations)->flatten()->unique()->values()->all();

        $strategies = Strategy::query()
            ->whereIn('id', $allStrategyIds)
            ->get(['id', 'name', 'asset'])
            ->keyBy('id');

        $query = Trade::query()
            ->whereIn('strategy_id', $allStrategyIds)
            ->whereNotNull('exit_time')
            ->orderBy('exit_time')
            ->orderBy('id');

        if ($startDate !== null && $startDate !== '') {
            $query->whereDate('exit_time', '>=', $startDate);
        }

        if ($endDate !== null && $endDate !== '') {
            $query->whereDate('exit_time', '<=', $endDate);
        }

        // Trade models cast `exit_time` to Carbon on every access (Eloquent does not
        // cache casted date attributes), and this data gets re-sorted once per
        // combination. Pre-computing the timestamp/formatted values here means each
        // trade's date is parsed exactly once, no matter how many combinations reuse it.
        // Kept as plain arrays (not Collections) beyond this point: calculateCombinationMetrics()
        // runs once per combination (up to thousands per analysis), and Collection method-call
        // overhead on the merge+sort in that hot path measurably slows down large analyses.
        $tradesByStrategy = $query
            ->get(['id', 'strategy_id', 'exit_time', 'net_profit'])
            ->map(function (Trade $trade): array {
                $exitTime = $trade->exit_time;

                return [
                    'id' => $trade->id,
                    'strategy_id' => $trade->strategy_id,
                    'timestamp' => $exitTime?->getTimestamp() ?? 0,
                    'date' => $exitTime?->format('Y-m-d H:i:s'),
                    'month_key' => $exitTime?->format('Y-m'),
                    'net_profit' => (float) $trade->net_profit,
                ];
            })
            ->groupBy('strategy_id')
            ->map(fn (Collection $trades): array => $trades->values()->all())
            ->all();

        $rawResults = [];

        foreach ($combinations as $combination) {
            $rawResults[] = $this->calculateCombinationMetrics(
                $combination,
                $tradesByStrategy,
                $strategies,
                $initialBalance,
            );
        }

        return $this->equityConsistencyService->applyConsistencyScores($rawResults);
    }

    /**
     * @param  int[]  $combination
     * @param  array<int, array<int, array<string, mixed>>>  $tradesByStrategy
     * @param  Collection<int, Strategy>  $strategies
     * @return array<string, mixed>
     */
    private function calculateCombinationMetrics(
        array $combination,
        array $tradesByStrategy,
        Collection $strategies,
        float $initialBalance,
    ): array {
        $hash = $this->generatorService->combinationHash($combination);

        $strategyNames = array_map(
            fn (int $id): string => $strategies->get($id)?->name ?? "Estratégia #{$id}",
            $combination,
        );

        // Merge all trades for the combination strategies (plain arrays: see note above)
        $sortedTrades = [];
        foreach ($combination as $strategyId) {
            foreach ($tradesByStrategy[$strategyId] ?? [] as $trade) {
                $sortedTrades[] = $trade;
            }
        }

        $totalTrades = count($sortedTrades);

        if ($totalTrades === 0) {
            return $this->emptyResult($hash, $combination, $strategyNames, $initialBalance);
        }

        // Sort combined trades by exit_time then id
        usort(
            $sortedTrades,
            fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp'] ?: $a['id'] <=> $b['id'],
        );

        // Build equity curve starting from initialBalance
        $equity = $initialBalance;
        $equityCurve = [
            ['date' => null, 'equity' => $initialBalance, 'net_profit' => 0.0, 'value' => $initialBalance],
        ];

        $profits = [];
        $monthlyProfitsMap = [];

        foreach ($sortedTrades as $trade) {
            $netProfit = $trade['net_profit'];
            $equity += $netProfit;
            $profits[] = $netProfit;

            $equityCurve[] = [
                'date' => $trade['date'],
                'equity' => round($equity, 2),
                'net_profit' => round($netProfit, 2),
                'value' => round($equity, 2),
            ];

            $key = $trade['month_key'];
            if ($key !== null) {
                $monthlyProfitsMap[$key] = ($monthlyProfitsMap[$key] ?? 0.0) + $netProfit;
            }
        }

        $monthlyProfits = array_values($monthlyProfitsMap);
        $totalNetProfit = (float) array_sum($profits);
        $winningProfits = array_filter($profits, fn (float $p): bool => $p > 0);
        $losingProfits = array_filter($profits, fn (float $p): bool => $p < 0);

        $grossProfit = (float) array_sum($winningProfits);
        $grossLoss = abs((float) array_sum($losingProfits));
        $avgWin = count($winningProfits) > 0 ? $grossProfit / count($winningProfits) : 0.0;
        $avgLoss = count($losingProfits) > 0 ? abs(array_sum($losingProfits) / count($losingProfits)) : 0.0;

        $winRate = round((count($winningProfits) / $totalTrades) * 100, 2);
        $profitFactor = $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null;
        $payoff = ($avgWin > 0 && $avgLoss > 0) ? round($avgWin / $avgLoss, 2) : null;

        $drawdown = $this->drawdownCalculator->calculate($equityCurve);

        $consistency = $this->equityConsistencyService->calculate(
            $equityCurve,
            $monthlyProfits,
            $totalNetProfit,
            $drawdown['max_drawdown'],
        );

        return [
            'combination_hash' => $hash,
            'strategy_ids' => $combination,
            'strategy_names' => $strategyNames,
            'strategies_count' => count($combination),
            'total_net_profit' => round($totalNetProfit, 2),
            'total_trades' => $totalTrades,
            'win_rate' => $winRate,
            'profit_factor' => $profitFactor,
            'payoff' => $payoff,
            'max_drawdown' => $drawdown['max_drawdown'],
            'max_drawdown_percent' => $drawdown['max_drawdown_percent'],
            'positive_months' => $consistency['positive_months'],
            'negative_months' => $consistency['negative_months'],
            'positive_months_percent' => $consistency['positive_months_percent'],
            'ulcer_index' => $consistency['ulcer_index'],
            'equity_r2' => $consistency['equity_r2'],
            'net_profit_to_drawdown' => $consistency['net_profit_to_drawdown'],
            'consistency_score' => 0.0, // set by applyConsistencyScores()
            'initial_balance' => $initialBalance,
        ];
    }

    /**
     * @param  int[]  $combination
     * @param  string[]  $strategyNames
     * @return array<string, mixed>
     */
    private function emptyResult(
        string $hash,
        array $combination,
        array $strategyNames,
        float $initialBalance,
    ): array {
        return [
            'combination_hash' => $hash,
            'strategy_ids' => $combination,
            'strategy_names' => $strategyNames,
            'strategies_count' => count($combination),
            'total_net_profit' => 0.0,
            'total_trades' => 0,
            'win_rate' => 0.0,
            'profit_factor' => null,
            'payoff' => null,
            'max_drawdown' => 0.0,
            'max_drawdown_percent' => 0.0,
            'positive_months' => 0,
            'negative_months' => 0,
            'positive_months_percent' => 0.0,
            'ulcer_index' => 0.0,
            'equity_r2' => 0.0,
            'net_profit_to_drawdown' => 0.0,
            'consistency_score' => 0.0,
            'initial_balance' => $initialBalance,
        ];
    }
}
