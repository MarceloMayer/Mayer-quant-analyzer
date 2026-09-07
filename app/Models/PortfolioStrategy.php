<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use App\Services\Metrics\PortfolioCorrelationService;

#[Fillable([
    'portfolio_id',
    'strategy_id',
    'enabled',
    'weight',
])]
class PortfolioStrategy extends Model
{
    protected $table = 'portfolio_strategy';

    protected static function booted(): void
    {
        static::saving(function (self $portfolioStrategy): void {
            $portfolioUserId = $portfolioStrategy->portfolio()->value('user_id');
            $strategyUserId = $portfolioStrategy->strategy()->value('user_id');

            if ($portfolioUserId === null || $portfolioUserId !== $strategyUserId) {
                throw new AuthorizationException('A estratégia selecionada não pertence a este portfólio.');
            }
        });

        static::saved(fn (self $portfolioStrategy) => self::bustPortfolioMetricsCache($portfolioStrategy->portfolio_id));
        static::deleted(fn (self $portfolioStrategy) => self::bustPortfolioMetricsCache($portfolioStrategy->portfolio_id));
    }

    /**
     * PortfolioResultsPage caches the (expensive) portfolio metrics and correlation matrix
     * keyed by portfolio id / period / metric. Weight or enabled-flag changes made through the
     * portfolio edit form must invalidate that cache immediately, otherwise the results page
     * would keep showing stale numbers until the TTL expires.
     */
    private static function bustPortfolioMetricsCache(int $portfolioId): void
    {
        Cache::forget('portfolio_metrics:'.$portfolioId);

        $correlationService = app(PortfolioCorrelationService::class);

        foreach (array_keys($correlationService->periodOptions()) as $period) {
            foreach (array_keys($correlationService->metricOptions()) as $metric) {
                Cache::forget("portfolio_correlation:{$portfolioId}:{$period}:{$metric}");
            }
        }
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'weight' => 'decimal:8',
        ];
    }
}
