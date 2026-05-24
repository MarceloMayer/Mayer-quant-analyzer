<?php

namespace App\Services\Trading;

use App\Models\Strategy;
use App\Services\Metrics\StrategyMetricsService as MetricsStrategyMetricsService;

class StrategyMetricsService
{
    public function __construct(
        private readonly MetricsStrategyMetricsService $strategyMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(Strategy $strategy): array
    {
        return $this->strategyMetrics->calculate($strategy);
    }
}
