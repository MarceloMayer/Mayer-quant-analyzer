<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'portfolio_id',
    'objective',
    'min_weight',
    'max_weight',
    'max_correlation',
    'changed',
    'is_favorite',
    'applied_at',
    'result',
])]
class PortfolioWeightOptimization extends Model
{
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_correlation' => 'decimal:3',
            'changed' => 'boolean',
            'is_favorite' => 'boolean',
            'applied_at' => 'datetime',
            'result' => 'array',
        ];
    }
}
