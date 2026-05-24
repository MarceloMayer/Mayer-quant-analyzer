<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'strategy_id',
    'backtest_id',
    'order_id',
    'entry_deal_id',
    'entry_order_id',
    'exit_deal_id',
    'exit_order_id',
    'asset',
    'symbol',
    'direction',
    'volume',
    'entry_time',
    'exit_time',
    'entry_price',
    'exit_price',
    'gross_profit',
    'commission',
    'swap',
    'net_profit',
    'balance_after_trade',
    'comment',
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
            'volume' => 'decimal:8',
            'entry_time' => 'datetime',
            'exit_time' => 'datetime',
            'entry_price' => 'decimal:8',
            'exit_price' => 'decimal:8',
            'gross_profit' => 'decimal:8',
            'commission' => 'decimal:8',
            'swap' => 'decimal:8',
            'net_profit' => 'decimal:8',
            'balance_after_trade' => 'decimal:8',
        ];
    }
}
