<?php

namespace App\Services\Metrics;

use App\Models\Mt5ReportFile;
use App\Models\Strategy;
use App\Models\StrategyBacktestExecution;
use App\Models\Trade;

class StrategyExecutionComparisonService
{
    public function __construct(
        private readonly StrategyExecutionMetricsService $metricsService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function compare(Strategy $strategy): array
    {
        $executions = StrategyBacktestExecution::query()
            ->where('strategy_id', $strategy->id)
            ->get()
            ->keyBy('backtest_id');
        $trades = Trade::query()
            ->where('strategy_id', $strategy->id)
            ->whereNotNull('exit_time')
            ->orderBy('exit_time')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Trade $trade): string => $trade->backtest_id ?: StrategyBacktestExecution::LEGACY_BACKTEST_ID);
        $reportKeys = Mt5ReportFile::query()
            ->where('strategy_id', $strategy->id)
            ->pluck('backtest_id')
            ->filter()
            ->values();
        $keys = $executions->keys()
            ->merge($trades->keys())
            ->merge($reportKeys)
            ->filter()
            ->unique()
            ->values();

        return $keys
            ->map(function (string $backtestId) use ($strategy, $executions, $trades): array {
                $execution = $executions->get($backtestId);
                $metrics = $this->metricsService->calculate(
                    $strategy,
                    $backtestId,
                    $execution,
                    $trades->get($backtestId, collect()),
                );

                return [
                    'backtest_id' => $backtestId,
                    'name' => data_get($metrics, 'execution.name'),
                    'execution_type' => data_get($metrics, 'execution.execution_type'),
                    'execution_type_label' => data_get($metrics, 'execution.execution_type_label'),
                    'strategy_version' => data_get($metrics, 'execution.strategy_version'),
                    'parameters' => data_get($metrics, 'execution.parameters', []),
                    'period' => $this->periodLabel($metrics),
                    'net_profit' => $metrics['net_profit'],
                    'max_drawdown' => $metrics['max_drawdown'],
                    'profit_factor' => $metrics['profit_factor'],
                    'profit_factor_label' => $metrics['profit_factor_label'],
                    'profit_drawdown_ratio' => $metrics['profit_drawdown_ratio'],
                    'total_trades' => $metrics['total_trades'],
                    'positive_month_rate' => data_get($metrics, 'monthly_summary.positive_month_rate'),
                    'max_losing_streak' => data_get($metrics, 'streaks.max_losing.count'),
                    'status' => data_get($metrics, 'classification.final_status'),
                    'status_label' => data_get($metrics, 'classification.final_label'),
                    'suggested_status' => data_get($metrics, 'classification.suggested_status'),
                    'alerts_count' => count(data_get($metrics, 'classification.alerts', [])),
                    'imported_at' => data_get($metrics, 'execution.imported_at'),
                ];
            })
            ->sortByDesc(fn (array $row): string => (string) ($row['imported_at'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function periodLabel(array $metrics): string
    {
        $start = data_get($metrics, 'period.execution_start') ?: data_get($metrics, 'period.first_trade_date_label');
        $end = data_get($metrics, 'period.execution_end') ?: data_get($metrics, 'period.last_trade_date_label');

        if ($start === null && $end === null) {
            return '-';
        }

        return trim(($start ?: '-').' a '.($end ?: '-'));
    }
}
