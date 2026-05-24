<?php

namespace App\Filament\Resources\Portfolios\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use App\Services\Metrics\PortfolioAnalyzerService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PortfolioResultsPage extends ViewRecord
{
    protected static string $resource = PortfolioResource::class;

    protected static ?string $title = 'Resultados do Portfólio';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.portfolios.pages.portfolio-results-page')
                    ->viewData(fn (): array => [
                        'portfolio' => $this->portfolio(),
                        'metrics' => app(PortfolioAnalyzerService::class)->calculate($this->portfolio()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    private function portfolio(): Portfolio
    {
        /** @var Portfolio $portfolio */
        $portfolio = $this->record;

        return $portfolio;
    }
}
