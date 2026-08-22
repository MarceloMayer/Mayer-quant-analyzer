<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'description', 'combination_hash', 'initial_balance'])]
class Portfolio extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function strategies(): BelongsToMany
    {
        return $this->belongsToMany(Strategy::class)
            ->withPivot(['enabled', 'weight'])
            ->withTimestamps();
    }

    public function portfolioStrategies(): HasMany
    {
        return $this->hasMany(PortfolioStrategy::class);
    }
}
