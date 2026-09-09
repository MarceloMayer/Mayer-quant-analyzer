<?php

namespace App\Services\Portfolio;

use App\Services\Metrics\PortfolioCorrelationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Central place to invalidate the (expensive) cached portfolio metrics and correlation
 * matrices. Both PortfolioResultsPage caches — keyed by portfolio id / period / metric —
 * must be dropped whenever the underlying dataset changes: weight/enabled edits through
 * the portfolio form, and (crucially) new trades imported for a strategy that belongs
 * to a portfolio.
 */
class PortfolioMetricsCache
{
    /**
     * Forget every cache entry tied to a single portfolio.
     */
    public function forgetPortfolio(int $portfolioId): void
    {
        Cache::forget('portfolio_metrics:'.$portfolioId);

        $correlationService = app(PortfolioCorrelationService::class);

        foreach (array_keys($correlationService->periodOptions()) as $period) {
            foreach (array_keys($correlationService->metricOptions()) as $metric) {
                Cache::forget("portfolio_correlation:{$portfolioId}:{$period}:{$metric}");
            }
        }
    }

    /**
     * Forget cache entries for every portfolio that contains the given strategy.
     */
    public function forgetForStrategy(int $strategyId): void
    {
        $portfolioIds = DB::table('portfolio_strategy')
            ->where('strategy_id', $strategyId)
            ->distinct()
            ->pluck('portfolio_id');

        foreach ($portfolioIds as $portfolioId) {
            $this->forgetPortfolio((int) $portfolioId);
        }
    }
}
