<?php

namespace App\Services\Trading;

use App\Models\Strategy;

class StrategyMetricsService
{
    public function __construct(
        private readonly PerformanceMetricsService $performanceMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(Strategy $strategy): array
    {
        return $this->performanceMetrics->calculate(
            $strategy->trades()
                ->orderBy('closed_at')
                ->orderBy('id')
                ->get(),
        );
    }
}
