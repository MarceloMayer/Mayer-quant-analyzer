<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'strategy_id',
    'file_name',
    'total_rows',
    'imported_rows',
    'ignored_rows',
    'imported_at',
])]
class StrategyImport extends Model
{
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
            'imported_at' => 'datetime',
        ];
    }
}
