<?php

namespace App\Services\Trading;

use App\Models\Portfolio;
use App\Models\Trade;

class PortfolioMetricsService
{
    public function __construct(
        private readonly PerformanceMetricsService $performanceMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(Portfolio $portfolio): array
    {
        $strategyIds = $portfolio->strategies()->pluck('strategies.id');

        $metrics = $this->performanceMetrics->calculate(
            Trade::query()
                ->whereIn('strategy_id', $strategyIds)
                ->with('strategy')
                ->orderBy('closed_at')
                ->orderBy('id')
                ->get(),
        );

        $metrics['strategies_count'] = $strategyIds->count();

        return $metrics;
    }
}
