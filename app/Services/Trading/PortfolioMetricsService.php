<?php

namespace App\Services\Trading;

use App\Models\Portfolio;
use App\Services\Metrics\PortfolioAnalyzerService;

class PortfolioMetricsService
{
    public function __construct(
        private readonly PortfolioAnalyzerService $portfolioAnalyzer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(Portfolio $portfolio): array
    {
        return $this->portfolioAnalyzer->calculate($portfolio);
    }
}
