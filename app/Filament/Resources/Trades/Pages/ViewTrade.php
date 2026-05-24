<?php

namespace App\Filament\Resources\Trades\Pages;

use App\Filament\Resources\Trades\TradeResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTrade extends ViewRecord
{
    protected static string $resource = TradeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
