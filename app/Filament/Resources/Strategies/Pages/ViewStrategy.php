<?php

namespace App\Filament\Resources\Strategies\Pages;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Filament\Resources\Strategies\StrategyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewStrategy extends ViewRecord
{
    protected static string $resource = StrategyResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getInfolistContentComponent(),
                View::make('filament.resources.strategies.metrics')
                    ->model($this->record),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportMt5CsvAction::makeForRecord($this->record),
            EditAction::make(),
        ];
    }
}
