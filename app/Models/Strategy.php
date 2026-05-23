<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'asset'])]
class Strategy extends Model
{
    public const ASSET_MINI_INDICE = 'Mini-Índice';

    public const ASSET_MINI_DOLAR = 'Mini-Dólar';

    /**
     * @return array<string, string>
     */
    public static function assetOptions(): array
    {
        return [
            self::ASSET_MINI_INDICE => self::ASSET_MINI_INDICE,
            self::ASSET_MINI_DOLAR => self::ASSET_MINI_DOLAR,
        ];
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function portfolios(): BelongsToMany
    {
        return $this->belongsToMany(Portfolio::class)->withTimestamps();
    }
}
