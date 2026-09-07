<?php

namespace App\Services\Metrics;

use App\Models\Mt5ReportFile;
use App\Models\Strategy;
use App\Models\StrategyBacktestExecution;
use App\Models\Trade;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class StrategyExecutionMetricsService
{
    public function __construct(
        private readonly StrategyExecutionClassificationService $classificationService,
    ) {}

    /**
     * @param  Collection<int, Trade>|null  $preloadedTrades
     * @return array<string, mixed>
     */
    public function calculate(
        Strategy $strategy,
        string $backtestId,
        ?StrategyBacktestExecution $execution = null,
        ?Collection $preloadedTrades = null,
    ): array {
        $backtestId = $this->decodeBacktestId($backtestId);
        $execution ??= $this->execution($strategy, $backtestId);
        $trades = $this->orderedClosedTrades($preloadedTrades ?? $this->trades($strategy, $backtestId));
        $reports = $this->reports($strategy, $backtestId);
        $initialCapital = $this->initialCapital($execution, $reports);
        $capitalCurve = $this->capitalCurve($trades, $initialCapital, $this->executionStartDate($execution, $trades));
        $drawdown = $this->balanceDrawdown($capitalCurve);
        $stats = $this->resultStats($trades);
        $costs = $this->costStats($trades);
        $period = $this->period($execution, $trades, $reports);
        $monthlyRows = $this->periodRows($trades, $initialCapital, 'monthly');
        $annualRows = $this->periodRows($trades, $initialCapital, 'annual');
        $annualRows = $this->withProfitShare($annualRows, $stats['net_profit']);
        $periodStartForSummary = $this->date($period['execution_start'] ?? null) ?: $period['first_trade_date'];
        $periodEndForSummary = $this->date($period['execution_end'] ?? null) ?: $period['last_trade_date'];
        $monthlySummary = $this->periodSummary($monthlyRows, $stats['net_profit'], $periodStartForSummary, $periodEndForSummary, 'monthly');
        $annualSummary = $this->annualSummary($annualRows, $stats['net_profit']);
        $streaks = $this->streaks($trades);
        $reportedNetProfit = $reports->pluck('reported_net_profit')->filter(fn (mixed $value): bool => $value !== null)->sum(fn (mixed $value): float => (float) $value);
        $hasReportedNetProfit = $reports->contains(fn (Mt5ReportFile $report): bool => $report->reported_net_profit !== null);
        $reportedDifference = $hasReportedNetProfit ? $reportedNetProfit - $stats['net_profit'] : null;

        $metrics = [
            ...$stats,
            ...$costs,
            'strategy' => [
                'id' => $strategy->id,
                'name' => $strategy->name,
                'asset' => $strategy->asset,
            ],
            'execution' => $this->executionInfo($strategy, $backtestId, $execution, $reports),
            'backtest_id' => $backtestId,
            'initial_capital' => $initialCapital,
            'return_percent' => $this->percent($stats['net_profit'], $initialCapital),
            'average_monthly_profit' => $monthlySummary['active_months'] > 0 ? $stats['net_profit'] / $monthlySummary['active_months'] : null,
            'average_annual_profit' => $annualSummary['active_years'] > 0 ? $stats['net_profit'] / $annualSummary['active_years'] : null,
            'profit_drawdown_ratio' => $drawdown['max_drawdown'] > 0 ? $stats['net_profit'] / $drawdown['max_drawdown'] : null,
            'return_drawdown_ratio' => $drawdown['max_drawdown_percent'] !== null && $drawdown['max_drawdown_percent'] > 0 && $initialCapital !== null
                ? $this->percent($stats['net_profit'], $initialCapital) / $drawdown['max_drawdown_percent']
                : null,
            'capital_curve' => $capitalCurve,
            'equity_curve' => $capitalCurve,
            'drawdown_curve' => $drawdown['curve'],
            'max_drawdown' => $drawdown['max_drawdown'],
            'max_drawdown_percent' => $drawdown['max_drawdown_percent'],
            'drawdown_peak' => $drawdown['peak'],
            'drawdown_valley' => $drawdown['valley'],
            'drawdown_peak_date' => $drawdown['peak_date'],
            'drawdown_valley_date' => $drawdown['valley_date'],
            'drawdown_recovery_date' => $drawdown['recovery_date'],
            'drawdown_duration_days' => $drawdown['duration_days'],
            'drawdown_trades_count' => $drawdown['trades_count'],
            'current_drawdown' => $drawdown['current_drawdown'],
            'current_drawdown_percent' => $drawdown['current_drawdown_percent'],
            'is_currently_in_drawdown' => $drawdown['is_currently_in_drawdown'],
            'equity_drawdown' => [
                'available' => false,
                'message' => 'O relatório importado não fornece série intratrade de equity; a análise exibe apenas drawdown de saldo.',
            ],
            'max_days_without_new_high' => $this->daysWithoutNewHigh($capitalCurve),
            'monthly_results' => $monthlyRows,
            'monthly_summary' => $monthlySummary,
            'annual_results' => $annualRows,
            'annual_summary' => $annualSummary,
            'direction_summary' => $this->directionSummary($trades),
            'streaks' => $streaks,
            'strategy_trades' => $this->summarizedTrades($trades, $initialCapital),
            'reported_net_profit' => $hasReportedNetProfit ? $reportedNetProfit : null,
            'reported_net_profit_difference' => $reportedDifference,
            'net_profit_origin' => $this->netProfitOrigin($trades),
            'period' => $period,
        ];

        $metrics['classification'] = $this->classificationService->classify($metrics, $execution);

        if ($execution?->exists && $execution->auto_classification_status !== $metrics['classification']['suggested_status']) {
            $execution->forceFill([
                'auto_classification_status' => $metrics['classification']['suggested_status'],
            ])->save();
        }

        return $metrics;
    }

    private function decodeBacktestId(string $backtestId): string
    {
        return rawurldecode($backtestId);
    }

    private function execution(Strategy $strategy, string $backtestId): ?StrategyBacktestExecution
    {
        if ($backtestId === StrategyBacktestExecution::LEGACY_BACKTEST_ID) {
            return StrategyBacktestExecution::query()
                ->where('strategy_id', $strategy->id)
                ->where('backtest_id', $backtestId)
                ->first();
        }

        return StrategyBacktestExecution::query()
            ->where('strategy_id', $strategy->id)
            ->where('backtest_id', $backtestId)
            ->first();
    }

    /**
     * @return Collection<int, Trade>
     */
    private function trades(Strategy $strategy, string $backtestId): Collection
    {
        return Trade::query()
            ->where('strategy_id', $strategy->id)
            ->when(
                $backtestId === StrategyBacktestExecution::LEGACY_BACKTEST_ID,
                fn ($query) => $query->whereNull('backtest_id'),
                fn ($query) => $query->where('backtest_id', $backtestId),
            )
            ->whereNotNull('exit_time')
            ->orderBy('exit_time')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Mt5ReportFile>
     */
    private function reports(Strategy $strategy, string $backtestId): Collection
    {
        if ($backtestId === StrategyBacktestExecution::LEGACY_BACKTEST_ID) {
            return collect();
        }

        return Mt5ReportFile::query()
            ->where('strategy_id', $strategy->id)
            ->where('backtest_id', $backtestId)
            ->orderBy('report_start_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return Collection<int, Trade>
     */
    private function orderedClosedTrades(Collection $trades): Collection
    {
        return $trades
            ->filter(fn (Trade $trade): bool => $trade->exit_time !== null)
            ->sort(fn (Trade $first, Trade $second): int => [
                $first->exit_time?->getTimestamp() ?? 0,
                $first->id,
            ] <=> [
                $second->exit_time?->getTimestamp() ?? 0,
                $second->id,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Mt5ReportFile>  $reports
     */
    private function initialCapital(?StrategyBacktestExecution $execution, Collection $reports): ?float
    {
        if ($execution?->initial_capital !== null) {
            return (float) $execution->initial_capital;
        }

        $reportInitialCapital = $reports
            ->pluck('initial_deposit')
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->first();

        return $reportInitialCapital === null ? null : (float) $reportInitialCapital;
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<string, mixed>
     */
    private function resultStats(Collection $trades): array
    {
        $profits = $trades->map(fn (Trade $trade): float => (float) $trade->net_profit)->values();
        $winning = $profits->filter(fn (float $profit): bool => $profit > 0)->values();
        $losing = $profits->filter(fn (float $profit): bool => $profit < 0)->values();
        $zero = $profits->filter(fn (float $profit): bool => $profit === 0.0)->values();
        $grossProfit = (float) $winning->sum();
        $grossLoss = abs((float) $losing->sum());
        $netProfit = (float) $profits->sum();
        $totalTrades = $profits->count();
        $averageWin = $winning->isNotEmpty() ? (float) $winning->avg() : null;
        $averageLoss = $losing->isNotEmpty() ? (float) $losing->avg() : null;
        $profitFactor = $grossLoss > 0 ? $grossProfit / $grossLoss : null;

        return [
            'net_profit' => $netProfit,
            'gross_profit' => $grossProfit,
            'gross_loss' => $grossLoss,
            'total_trades' => $totalTrades,
            'winning_trades' => $winning->count(),
            'losing_trades' => $losing->count(),
            'breakeven_trades' => $zero->count(),
            'win_rate' => $totalTrades > 0 ? ($winning->count() / $totalTrades) * 100 : null,
            'profit_factor' => $profitFactor,
            'profit_factor_label' => $this->profitFactorLabel($totalTrades, $grossProfit, $grossLoss),
            'average_trade' => $totalTrades > 0 ? $netProfit / $totalTrades : null,
            'average_win' => $averageWin,
            'average_loss' => $averageLoss,
            'gain_loss_ratio' => $averageWin !== null && $averageLoss !== null && $averageWin > 0 && $averageLoss < 0
                ? $averageWin / abs($averageLoss)
                : null,
            'average_payoff' => $averageWin !== null && $averageLoss !== null && $averageWin > 0 && $averageLoss < 0
                ? $averageWin / abs($averageLoss)
                : null,
            'best_trade' => $profits->isNotEmpty() ? (float) $profits->max() : null,
            'worst_trade' => $profits->isNotEmpty() ? (float) $profits->min() : null,
            'median_trade' => $this->median($profits->all()),
            'best_trade_concentration_percent' => $netProfit > 0 && $profits->max() > 0 ? ((float) $profits->max() / $netProfit) * 100 : null,
        ];
    }

    private function profitFactorLabel(int $totalTrades, float $grossProfit, float $grossLoss): string
    {
        return match (true) {
            $totalTrades === 0 => 'Sem operações.',
            $grossLoss === 0.0 && $grossProfit > 0 => 'Sem operações perdedoras.',
            $grossLoss === 0.0 => 'Não calculável: prejuízo bruto igual a zero.',
            default => 'Calculado por lucro bruto dividido pelo prejuízo bruto absoluto.',
        };
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<string, mixed>
     */
    private function costStats(Collection $trades): array
    {
        $hasCosts = $trades->contains(fn (Trade $trade): bool => $trade->commission !== null || $trade->swap !== null);
        $commission = (float) $trades->sum(fn (Trade $trade): float => (float) ($trade->commission ?? 0));
        $swap = (float) $trades->sum(fn (Trade $trade): float => (float) ($trade->swap ?? 0));

        return [
            'costs_available' => $hasCosts,
            'commission_total' => $commission,
            'swap_total' => $swap,
            'costs_total' => $commission + $swap,
            'costs_total_abs' => abs($commission + $swap),
        ];
    }

    /**
     * A curva de saldo parte do capital inicial quando disponível. Sem capital
     * inicial, a base é zero e percentuais de drawdown podem ficar indisponíveis.
     *
     * @param  Collection<int, Trade>  $trades
     * @return array<int, array<string, mixed>>
     */
    private function capitalCurve(Collection $trades, ?float $initialCapital, ?CarbonInterface $startDate): array
    {
        if ($trades->isEmpty() && $initialCapital === null) {
            return [];
        }

        $balance = $initialCapital ?? 0.0;
        $curve = [[
            'date' => $startDate?->format('Y-m-d H:i:s'),
            'label' => 'Capital inicial',
            'trade_id' => null,
            'trade_position' => 0,
            'net_profit' => 0.0,
            'balance' => $balance,
            'equity' => $balance,
            'value' => $balance,
            'imported_balance_after_trade' => null,
            'direction' => null,
        ]];

        foreach ($trades as $index => $trade) {
            $netProfit = (float) $trade->net_profit;
            $balance += $netProfit;

            $curve[] = [
                'date' => $trade->exit_time?->format('Y-m-d H:i:s'),
                'label' => $trade->exit_time?->format('d/m/Y H:i') ?? (string) ($index + 1),
                'trade_id' => $trade->id,
                'trade_position' => $index + 1,
                'net_profit' => $netProfit,
                'balance' => $balance,
                'equity' => $balance,
                'value' => $balance,
                'imported_balance_after_trade' => $trade->balance_after_trade === null ? null : (float) $trade->balance_after_trade,
                'direction' => $trade->direction,
            ];
        }

        return $curve;
    }

    /**
     * @param  array<int, array<string, mixed>>  $capitalCurve
     * @return array<string, mixed>
     */
    private function balanceDrawdown(array $capitalCurve): array
    {
        if ($capitalCurve === []) {
            return [
                'max_drawdown' => 0.0,
                'max_drawdown_percent' => null,
                'peak' => null,
                'valley' => null,
                'peak_date' => null,
                'valley_date' => null,
                'recovery_date' => null,
                'duration_days' => null,
                'trades_count' => 0,
                'current_drawdown' => 0.0,
                'current_drawdown_percent' => null,
                'is_currently_in_drawdown' => false,
                'curve' => [],
            ];
        }

        $peakValue = (float) ($capitalCurve[0]['balance'] ?? 0);
        $peakIndex = 0;
        $max = [
            'drawdown' => 0.0,
            'percent' => null,
            'peak' => $peakValue,
            'valley' => $peakValue,
            'peak_index' => 0,
            'valley_index' => 0,
        ];
        $curve = [];

        foreach ($capitalCurve as $index => $point) {
            $balance = (float) ($point['balance'] ?? 0);

            if ($balance > $peakValue) {
                $peakValue = $balance;
                $peakIndex = $index;
            }

            $drawdown = $peakValue - $balance;
            $percent = $peakValue > 0 ? ($drawdown / $peakValue) * 100 : null;
            $curve[] = [
                'date' => $point['date'] ?? null,
                'label' => $point['label'] ?? '',
                'value' => -1 * $drawdown,
                'equity' => -1 * $drawdown,
                'drawdown' => $drawdown,
                'percent' => $percent,
            ];

            if ($drawdown > $max['drawdown']) {
                $max = [
                    'drawdown' => $drawdown,
                    'percent' => $percent,
                    'peak' => $peakValue,
                    'valley' => $balance,
                    'peak_index' => $peakIndex,
                    'valley_index' => $index,
                ];
            }
        }

        $peakPoint = $capitalCurve[$max['peak_index']] ?? null;
        $valleyPoint = $capitalCurve[$max['valley_index']] ?? null;
        $recoveryPoint = $this->recoveryPoint($capitalCurve, (int) $max['valley_index'], (float) $max['peak']);
        $lastPoint = $capitalCurve[array_key_last($capitalCurve)] ?? null;
        $currentPeak = collect($capitalCurve)->max(fn (array $point): float => (float) ($point['balance'] ?? 0));
        $currentDrawdown = max(0.0, (float) $currentPeak - (float) ($lastPoint['balance'] ?? 0));

        return [
            'max_drawdown' => $max['drawdown'],
            'max_drawdown_percent' => $max['percent'],
            'peak' => $max['peak'],
            'valley' => $max['valley'],
            'peak_date' => $peakPoint['date'] ?? null,
            'valley_date' => $valleyPoint['date'] ?? null,
            'recovery_date' => $recoveryPoint['date'] ?? null,
            'duration_days' => $this->dateDiffDays($peakPoint['date'] ?? null, $valleyPoint['date'] ?? null),
            'trades_count' => max(0, (int) ($valleyPoint['trade_position'] ?? 0) - (int) ($peakPoint['trade_position'] ?? 0)),
            'current_drawdown' => $currentDrawdown,
            'current_drawdown_percent' => $currentPeak > 0 ? ($currentDrawdown / (float) $currentPeak) * 100 : null,
            'is_currently_in_drawdown' => $currentDrawdown > 0,
            'curve' => $curve,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $capitalCurve
     * @return array<string, mixed>|null
     */
    private function recoveryPoint(array $capitalCurve, int $valleyIndex, float $peak): ?array
    {
        foreach (array_slice($capitalCurve, $valleyIndex + 1) as $point) {
            if ((float) ($point['balance'] ?? 0) >= $peak) {
                return $point;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<int, array<string, mixed>>
     */
    private function periodRows(Collection $trades, ?float $initialCapital, string $period): array
    {
        $format = $period === 'annual' ? 'Y' : 'Y-m';
        $groups = $trades
            ->groupBy(fn (Trade $trade): string => $trade->exit_time?->format($format) ?? '')
            ->filter(fn (Collection $group, string $key): bool => $key !== '')
            ->sortKeys();
        $runningBalance = $initialCapital ?? 0.0;
        $rows = [];

        foreach ($groups as $key => $periodTrades) {
            $stats = $this->resultStats($periodTrades);
            $costs = $this->costStats($periodTrades);
            $drawdown = $this->balanceDrawdown($this->capitalCurve($periodTrades, $runningBalance, $periodTrades->first()?->exit_time));
            $runningBalance += $stats['net_profit'];

            $rows[] = [
                'key' => $key,
                'label' => $period === 'annual' ? $key : CarbonImmutable::createFromFormat('Y-m', $key)->format('m/Y'),
                'year' => (int) substr($key, 0, 4),
                'month' => $period === 'annual' ? null : (int) substr($key, 5, 2),
                'trade_count' => $stats['total_trades'],
                'gross_profit' => $stats['gross_profit'],
                'gross_loss' => $stats['gross_loss'],
                'costs' => $costs['costs_total'],
                'net_profit' => $stats['net_profit'],
                'return_percent' => $this->percent($stats['net_profit'], $initialCapital),
                'profit_factor' => $stats['profit_factor'],
                'profit_factor_label' => $stats['profit_factor_label'],
                'win_rate' => $stats['win_rate'],
                'average_win' => $stats['average_win'],
                'average_loss' => $stats['average_loss'],
                'drawdown' => $drawdown['max_drawdown'],
                'drawdown_percent' => $drawdown['max_drawdown_percent'],
                'profit_drawdown_ratio' => $drawdown['max_drawdown'] > 0 ? $stats['net_profit'] / $drawdown['max_drawdown'] : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function periodSummary(array $rows, float $totalNetProfit, ?CarbonInterface $firstDate, ?CarbonInterface $lastDate, string $period): array
    {
        $results = array_map(fn (array $row): float => (float) $row['net_profit'], $rows);
        $positive = count(array_filter($results, fn (float $value): bool => $value > 0));
        $negative = count(array_filter($results, fn (float $value): bool => $value < 0));
        $zero = count(array_filter($results, fn (float $value): bool => $value === 0.0));
        $active = count($rows);
        $best = $rows === [] ? null : collect($rows)->sortByDesc('net_profit')->first();
        $worst = $rows === [] ? null : collect($rows)->sortBy('net_profit')->first();
        $totalPeriods = $period === 'monthly' ? $this->monthsBetweenInclusive($firstDate, $lastDate) : $active;
        $noTradePeriods = max(0, $totalPeriods - $active);

        return [
            'active_months' => $period === 'monthly' ? $active : null,
            'total_months_in_period' => $period === 'monthly' ? $totalPeriods : null,
            'no_trade_months' => $period === 'monthly' ? $noTradePeriods : null,
            'no_trade_months_percent' => $period === 'monthly' && $totalPeriods > 0 ? ($noTradePeriods / $totalPeriods) * 100 : null,
            'positive_months' => $period === 'monthly' ? $positive : null,
            'negative_months' => $period === 'monthly' ? $negative : null,
            'zero_months' => $period === 'monthly' ? $zero : null,
            'positive_month_rate' => $active > 0 ? ($positive / $active) * 100 : null,
            'best_month' => $period === 'monthly' ? $best : null,
            'worst_month' => $period === 'monthly' ? $worst : null,
            'average_monthly_profit' => $period === 'monthly' && $active > 0 ? array_sum($results) / $active : null,
            'median_monthly_profit' => $period === 'monthly' ? $this->median($results) : null,
            'monthly_stddev' => $period === 'monthly' ? $this->standardDeviation($results) : null,
            'max_negative_month_streak' => $period === 'monthly' ? $this->negativePeriodStreak($rows) : null,
            'best_month_concentration_percent' => $period === 'monthly' && $totalNetProfit > 0 && (($best['net_profit'] ?? 0) > 0)
                ? ((float) $best['net_profit'] / $totalNetProfit) * 100
                : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function annualSummary(array $rows, float $totalNetProfit): array
    {
        $results = array_map(fn (array $row): float => (float) $row['net_profit'], $rows);
        $best = $rows === [] ? null : collect($rows)->sortByDesc('net_profit')->first();
        $worst = $rows === [] ? null : collect($rows)->sortBy('net_profit')->first();

        return [
            'active_years' => count($rows),
            'positive_years' => count(array_filter($results, fn (float $value): bool => $value > 0)),
            'negative_years' => count(array_filter($results, fn (float $value): bool => $value < 0)),
            'best_year' => $best,
            'worst_year' => $worst,
            'best_year_concentration_percent' => $totalNetProfit > 0 && (($best['net_profit'] ?? 0) > 0)
                ? ((float) $best['net_profit'] / $totalNetProfit) * 100
                : null,
            'year_profit_shares' => $rows,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withProfitShare(array $rows, float $totalNetProfit): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index]['profit_share_percent'] = $totalNetProfit !== 0.0
                ? ((float) $row['net_profit'] / $totalNetProfit) * 100
                : null;
        }

        return $rows;
    }

    /**
     * Operações zeradas encerram a sequência atual e não contam como ganho ou
     * perda. Isso evita contaminar sequências de risco com trades de resultado zero.
     *
     * @param  Collection<int, Trade>  $trades
     * @return array<string, mixed>
     */
    private function streaks(Collection $trades): array
    {
        $sequences = [];
        $current = null;

        foreach ($trades as $trade) {
            $profit = (float) $trade->net_profit;
            $type = $profit > 0 ? 'win' : ($profit < 0 ? 'loss' : null);

            if ($type === null) {
                if ($current !== null) {
                    $sequences[] = $current;
                    $current = null;
                }

                continue;
            }

            if ($current === null || $current['type'] !== $type) {
                if ($current !== null) {
                    $sequences[] = $current;
                }

                $current = [
                    'type' => $type,
                    'count' => 0,
                    'sum' => 0.0,
                    'start' => $trade->exit_time?->format('Y-m-d H:i:s'),
                    'end' => $trade->exit_time?->format('Y-m-d H:i:s'),
                ];
            }

            $current['count']++;
            $current['sum'] += $profit;
            $current['end'] = $trade->exit_time?->format('Y-m-d H:i:s');
        }

        if ($current !== null) {
            $sequences[] = $current;
        }

        $losses = collect($sequences)->where('type', 'loss')->values();
        $wins = collect($sequences)->where('type', 'win')->values();
        $maxLoss = $losses->sortByDesc('count')->sortBy('sum')->first();
        $maxWin = $wins->sortByDesc('count')->sortByDesc('sum')->first();

        return [
            'max_losing' => [
                'count' => (int) ($maxLoss['count'] ?? 0),
                'amount' => abs((float) ($maxLoss['sum'] ?? 0)),
                'start' => $maxLoss['start'] ?? null,
                'end' => $maxLoss['end'] ?? null,
                'duration_days' => $this->dateDiffDays($maxLoss['start'] ?? null, $maxLoss['end'] ?? null),
            ],
            'average_losses_per_sequence' => $losses->isNotEmpty() ? (float) $losses->avg('count') : null,
            'max_winning' => [
                'count' => (int) ($maxWin['count'] ?? 0),
                'amount' => (float) ($maxWin['sum'] ?? 0),
                'start' => $maxWin['start'] ?? null,
                'end' => $maxWin['end'] ?? null,
            ],
            'sequences' => $sequences,
        ];
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<string, array<string, mixed>>
     */
    private function directionSummary(Collection $trades): array
    {
        return collect(['buy' => 'Compra', 'sell' => 'Venda', 'other' => 'Outras'])
            ->mapWithKeys(function (string $label, string $key) use ($trades): array {
                $filtered = $trades->filter(function (Trade $trade) use ($key): bool {
                    if ($key === 'other') {
                        return ! in_array($trade->direction, ['buy', 'sell'], true);
                    }

                    return $trade->direction === $key;
                });
                $stats = $this->resultStats($filtered);

                return [$key => [
                    'label' => $label,
                    'total_trades' => $stats['total_trades'],
                    'winning_trades' => $stats['winning_trades'],
                    'losing_trades' => $stats['losing_trades'],
                    'breakeven_trades' => $stats['breakeven_trades'],
                    'gross_profit' => $stats['gross_profit'],
                    'gross_loss' => $stats['gross_loss'],
                    'net_profit' => $stats['net_profit'],
                    'win_rate' => $stats['win_rate'],
                    'profit_factor' => $stats['profit_factor'],
                ]];
            })
            ->all();
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @return array<int, array<string, mixed>>
     */
    private function summarizedTrades(Collection $trades, ?float $initialCapital): array
    {
        $balance = $initialCapital ?? 0.0;

        return $trades
            ->map(function (Trade $trade) use (&$balance): array {
                $netProfit = (float) $trade->net_profit;
                $balance += $netProfit;

                return [
                    'id' => $trade->id,
                    'backtest_id' => $trade->backtest_id,
                    'asset' => $trade->asset,
                    'symbol' => $trade->symbol,
                    'direction' => $trade->direction,
                    'volume' => $trade->volume === null ? null : (float) $trade->volume,
                    'entry_time' => $trade->entry_time?->format('Y-m-d H:i:s'),
                    'exit_time' => $trade->exit_time?->format('Y-m-d H:i:s'),
                    'entry_price' => $trade->entry_price === null ? null : (float) $trade->entry_price,
                    'exit_price' => $trade->exit_price === null ? null : (float) $trade->exit_price,
                    'gross_profit' => $trade->gross_profit === null ? null : (float) $trade->gross_profit,
                    'commission' => $trade->commission === null ? null : (float) $trade->commission,
                    'swap' => $trade->swap === null ? null : (float) $trade->swap,
                    'net_profit' => $netProfit,
                    'balance_after_trade' => $trade->balance_after_trade === null ? null : (float) $trade->balance_after_trade,
                    'calculated_balance' => $balance,
                    'order_id' => $trade->order_id,
                    'exit_deal_id' => $trade->exit_deal_id,
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
     * @param  Collection<int, Mt5ReportFile>  $reports
     * @return array<string, mixed>
     */
    private function executionInfo(Strategy $strategy, string $backtestId, ?StrategyBacktestExecution $execution, Collection $reports): array
    {
        return [
            'id' => $execution?->id,
            'backtest_id' => $backtestId,
            'name' => $execution?->name ?: ($backtestId === StrategyBacktestExecution::LEGACY_BACKTEST_ID ? 'Sem identificador de backtest' : $backtestId),
            'execution_type' => $execution?->execution_type,
            'execution_type_label' => StrategyBacktestExecution::executionTypeLabel($execution?->execution_type),
            'strategy_version' => $execution?->strategy_version,
            'asset' => $execution?->asset ?: $strategy->asset,
            'symbol' => $execution?->symbol,
            'timeframe' => $execution?->timeframe,
            'started_at' => $execution?->started_at?->toDateString(),
            'ended_at' => $execution?->ended_at?->toDateString(),
            'initial_contracts' => $execution?->initial_contracts === null ? null : (float) $execution->initial_contracts,
            'parameters' => $execution?->parameters ?? [],
            'costs' => $execution?->costs ?? [],
            'slippage' => $execution?->slippage === null ? null : (float) $execution->slippage,
            'spread' => $execution?->spread === null ? null : (float) $execution->spread,
            'data_source' => $execution?->data_source,
            'notes' => $execution?->notes,
            'imported_at' => $execution?->imported_at?->format('Y-m-d H:i:s') ?: $reports->max('imported_at'),
            'report_files_count' => $reports->count(),
        ];
    }

    /**
     * @param  Collection<int, Trade>  $trades
     * @param  Collection<int, Mt5ReportFile>  $reports
     * @return array<string, mixed>
     */
    private function period(?StrategyBacktestExecution $execution, Collection $trades, Collection $reports): array
    {
        $firstTradeDate = $trades->first()?->exit_time;
        $lastTradeDate = $trades->last()?->exit_time;

        return [
            'execution_start' => $execution?->started_at?->toDateString() ?: $reports->pluck('report_start_date')->filter()->min(),
            'execution_end' => $execution?->ended_at?->toDateString() ?: $reports->pluck('report_end_date')->filter()->max(),
            'first_trade_date' => $firstTradeDate,
            'last_trade_date' => $lastTradeDate,
            'first_trade_date_label' => $firstTradeDate?->format('Y-m-d H:i:s'),
            'last_trade_date_label' => $lastTradeDate?->format('Y-m-d H:i:s'),
            'operational_days' => $trades->pluck('exit_time')->filter()->map(fn (CarbonInterface $date): string => $date->toDateString())->unique()->count(),
            'average_trades_per_operational_day' => $trades->count() > 0
                ? $trades->count() / max(1, $trades->pluck('exit_time')->filter()->map(fn (CarbonInterface $date): string => $date->toDateString())->unique()->count())
                : null,
        ];
    }

    private function executionStartDate(?StrategyBacktestExecution $execution, Collection $trades): ?CarbonInterface
    {
        return $execution?->started_at ?: $trades->first()?->exit_time;
    }

    /**
     * @param  Collection<int, Trade>  $trades
     */
    private function netProfitOrigin(Collection $trades): string
    {
        if ($trades->isEmpty()) {
            return 'Sem operações para identificar a origem do resultado líquido.';
        }

        $allHaveGrossAndCosts = $trades->every(fn (Trade $trade): bool => $trade->gross_profit !== null && ($trade->commission !== null || $trade->swap !== null));

        return $allHaveGrossAndCosts
            ? 'Resultado líquido calculado pelo sistema a partir de lucro bruto, comissão e swap importados.'
            : 'Resultado líquido fornecido ou já armazenado na importação; custos ausentes não foram subtraídos novamente.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $capitalCurve
     */
    private function daysWithoutNewHigh(array $capitalCurve): int
    {
        $peak = null;
        $lastHighDate = null;
        $maxDays = 0;

        foreach ($capitalCurve as $point) {
            $date = $this->date($point['date'] ?? null);

            if ($date === null) {
                continue;
            }

            $balance = (float) ($point['balance'] ?? 0);

            if ($peak === null || $balance > $peak) {
                $peak = $balance;
                $lastHighDate = $date;

                continue;
            }

            if ($lastHighDate !== null) {
                $maxDays = max($maxDays, (int) $lastHighDate->startOfDay()->diffInDays($date->startOfDay()));
            }
        }

        return $maxDays;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function median(array $values): ?float
    {
        $values = array_values(array_filter($values, fn (mixed $value): bool => is_numeric($value)));
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $values[$middle]
            : (((float) $values[$middle - 1] + (float) $values[$middle]) / 2);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function standardDeviation(array $values): ?float
    {
        $values = array_values(array_filter($values, fn (mixed $value): bool => is_numeric($value)));
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        $average = array_sum($values) / $count;
        $variance = array_sum(array_map(fn (float|int $value): float => ((float) $value - $average) ** 2, $values)) / $count;

        return sqrt($variance);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function negativePeriodStreak(array $rows): int
    {
        $current = 0;
        $max = 0;

        foreach ($rows as $row) {
            if ((float) ($row['net_profit'] ?? 0) < 0) {
                $current++;
                $max = max($max, $current);

                continue;
            }

            $current = 0;
        }

        return $max;
    }

    private function monthsBetweenInclusive(?CarbonInterface $firstDate, ?CarbonInterface $lastDate): int
    {
        if ($firstDate === null || $lastDate === null) {
            return 0;
        }

        return (((int) $lastDate->format('Y') - (int) $firstDate->format('Y')) * 12)
            + ((int) $lastDate->format('n') - (int) $firstDate->format('n'))
            + 1;
    }

    private function percent(float $value, ?float $base): ?float
    {
        return $base !== null && $base != 0.0 ? ($value / $base) * 100 : null;
    }

    private function dateDiffDays(mixed $start, mixed $end): ?int
    {
        $startDate = $this->date($start);
        $endDate = $this->date($end);

        return $startDate !== null && $endDate !== null ? (int) $startDate->diffInDays($endDate) : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
