<?php

namespace App\Filament\Resources\Strategies\Pages;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Filament\Resources\Strategies\StrategyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditStrategy extends EditRecord
{
    protected static string $resource = StrategyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportMt5CsvAction::makeForRecord($this->record),
            Action::make('viewResults')
                ->label('Ver resultados')
                ->icon(Heroicon::OutlinedChartBarSquare)
                ->url(StrategyResource::getUrl('results', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
