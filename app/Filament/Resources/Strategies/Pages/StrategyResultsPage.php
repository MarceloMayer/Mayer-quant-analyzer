<?php

namespace App\Filament\Resources\Strategies\Pages;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Strategy;
use App\Models\Trade;
use App\Services\Metrics\StrategyMetricsService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;

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
                        'trades' => $this->trades(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
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

    /**
     * @return Collection<int, Trade>
     */
    private function trades(): Collection
    {
        return $this->strategy()
            ->trades()
            ->whereNotNull('exit_time')
            ->orderByDesc('exit_time')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }
}
