<?php

namespace App\Filament\Resources\Portfolios\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use App\Services\Metrics\PortfolioAnalyzerService;
use App\Services\Metrics\PortfolioCorrelationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PortfolioResultsPage extends ViewRecord
{
    protected static string $resource = PortfolioResource::class;

    protected static ?string $title = 'Resultados do Portfólio';

    public string $correlationPeriod = PortfolioCorrelationService::PERIOD_MONTHLY;

    public string $correlationMetric = PortfolioCorrelationService::METRIC_PROFIT_LOSS;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.portfolios.pages.portfolio-results-page')
                    ->viewData(fn (): array => [
                        'portfolio' => $this->portfolio(),
                        'metrics' => app(PortfolioAnalyzerService::class)->calculate($this->portfolio()),
                        'correlation' => app(PortfolioCorrelationService::class)->calculate(
                            $this->portfolio(),
                            $this->correlationPeriod,
                            $this->correlationMetric,
                        ),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Portfólios')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(PortfolioResource::getUrl('index'))
                ->color('gray'),
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
