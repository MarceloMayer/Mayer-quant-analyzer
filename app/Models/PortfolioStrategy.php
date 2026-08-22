<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
