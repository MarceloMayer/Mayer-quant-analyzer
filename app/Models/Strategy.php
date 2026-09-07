<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'magic_number', 'asset', 'is_favorite'])]
class Strategy extends Model
{
    public const ASSET_MINI_INDICE = 'mini_indice';

    public const ASSET_MINI_DOLAR = 'mini_dolar';

    /**
     * @return array<string, string>
     */
    public static function assetOptions(): array
    {
        return [
            self::ASSET_MINI_INDICE => 'Mini-Índice',
            self::ASSET_MINI_DOLAR => 'Mini-Dólar',
        ];
    }

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function portfolios(): BelongsToMany
    {
        return $this->belongsToMany(Portfolio::class)
            ->withPivot(['enabled', 'weight'])
            ->withTimestamps();
    }

    public function strategyImports(): HasMany
    {
        return $this->hasMany(StrategyImport::class);
    }

    public function backtestExecutions(): HasMany
    {
        return $this->hasMany(StrategyBacktestExecution::class);
    }

    public function mt5ReportFiles(): HasMany
    {
        return $this->hasMany(Mt5ReportFile::class);
    }
}
