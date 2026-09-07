<?php

namespace App\Services\Metrics;

use App\Models\Mt5ReportFile;
use App\Models\Strategy;
use App\Models\StrategyBacktestExecution;

class InitialCapitalResolver
{
    /**
     * Resolves the starting balance to use as the drawdown/Ulcer index baseline.
     *
     * Without it, the equity curve (pure accumulated P&L, starting at zero) is
     * used as its own peak reference, which makes percentage-based risk metrics
     * blow up whenever the running peak is small relative to a later drawdown.
     *
     * When $backtestId is blank, trades from every execution are being combined
     * (e.g. "Todas as execuções"), so the earliest execution's capital is used
     * as a best-effort baseline.
     */
    public function resolve(Strategy $strategy, ?string $backtestId = null): float
    {
        $execution = StrategyBacktestExecution::query()
            ->where('strategy_id', $strategy->id)
            ->when(filled($backtestId), fn ($query) => $query->where('backtest_id', $backtestId))
            ->orderBy('started_at')
            ->orderBy('id')
            ->first();

        if ($execution === null) {
            return 0.0;
        }

        if ($execution->initial_capital !== null) {
            return (float) $execution->initial_capital;
        }

        $reportInitialDeposit = Mt5ReportFile::query()
            ->where('strategy_id', $strategy->id)
            ->where('backtest_id', $execution->backtest_id)
            ->whereNotNull('initial_deposit')
            ->orderBy('report_start_date')
            ->value('initial_deposit');

        return $reportInitialDeposit === null ? 0.0 : (float) $reportInitialDeposit;
    }
}
