<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'backtest_id',
    'strategy_id',
    'original_filename',
    'file_hash',
    'report_start_date',
    'report_end_date',
    'initial_deposit',
    'reported_net_profit',
    'imported_trades_count',
    'skipped_duplicates_count',
    'warnings',
    'status',
    'imported_at',
])]
class Mt5ReportFile extends Model
{
    public const STATUS_IMPORTED = 'imported';

    public const STATUS_SKIPPED_DUPLICATE = 'skipped_duplicate';

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_start_date' => 'date',
            'report_end_date' => 'date',
            'initial_deposit' => 'decimal:8',
            'reported_net_profit' => 'decimal:8',
            'warnings' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
