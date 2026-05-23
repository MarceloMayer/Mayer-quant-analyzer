<?php

namespace App\Filament\Resources\Strategies\Pages;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Filament\Resources\Strategies\StrategyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStrategy extends EditRecord
{
    protected static string $resource = StrategyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportMt5CsvAction::makeForRecord($this->record),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
