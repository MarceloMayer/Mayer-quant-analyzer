<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'strategy_id',
    'ticket',
    'symbol',
    'type',
    'volume',
    'price',
    'commission',
    'swap',
    'profit',
    'balance',
    'closed_at',
])]
class Trade extends Model
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
            'closed_at' => 'datetime',
            'volume' => 'decimal:4',
            'price' => 'decimal:5',
            'commission' => 'decimal:2',
            'swap' => 'decimal:2',
            'profit' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }
}
