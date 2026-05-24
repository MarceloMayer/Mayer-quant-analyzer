<?php

namespace App\Filament\Resources\Portfolios\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPortfolio extends EditRecord
{
    protected static string $resource = PortfolioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewResults')
                ->label('Ver resultados')
                ->icon(Heroicon::OutlinedChartBarSquare)
                ->url(fn (Portfolio $record): string => PortfolioResource::getUrl('results', ['record' => $record])),
            DeleteAction::make(),
        ];
    }
}
