<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description'])]
class Portfolio extends Model
{
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
