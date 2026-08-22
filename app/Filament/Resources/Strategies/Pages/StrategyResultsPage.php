<?php

namespace App\Filament\Resources\Strategies\Pages;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Filament\Pages\StrategyComparison;
use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Strategy;
use App\Services\Metrics\StrategyExecutionComparisonService;
use App\Services\Metrics\StrategyMetricsService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StrategyResultsPage extends ViewRecord
{
    protected static string $resource = StrategyResource::class;

    protected static ?string $title = 'Resultados da Estratégia';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.strategies.pages.strategy-results-page')
                    ->viewData(fn (): array => [
                        'strategy' => $this->strategy(),
                        'metrics' => app(StrategyMetricsService::class)->calculate($this->strategy()),
                        'executions' => app(StrategyExecutionComparisonService::class)->compare($this->strategy()),
                    ]),
            ]);
    }

    public function getSubheading(): ?string
    {
        $strategy = $this->strategy();
        $asset = Strategy::assetOptions()[$strategy->asset] ?? $strategy->asset;

        return "{$strategy->name} - {$asset}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compare')
                ->label('Comparar com outra estratégia')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->url(fn (): string => StrategyComparison::getUrl(['a' => $this->strategy()->getKey()])),
            ImportMt5CsvAction::makeForRecord($this->strategy()),
            EditAction::make(),
        ];
    }

    private function strategy(): Strategy
    {
        /** @var Strategy $strategy */
        $strategy = $this->record;

        return $strategy;
    }
}
