<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Portfolio extends Model
{
    public function strategies(): BelongsToMany
    {
        return $this->belongsToMany(Strategy::class)->withTimestamps();
    }
}
