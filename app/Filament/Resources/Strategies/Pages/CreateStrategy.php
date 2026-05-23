<?php

namespace App\Filament\Resources\Strategies\Pages;

use App\Filament\Resources\Strategies\StrategyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStrategy extends CreateRecord
{
    protected static string $resource = StrategyResource::class;
}
