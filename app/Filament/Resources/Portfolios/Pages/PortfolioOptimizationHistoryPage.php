<?php

namespace App\Filament\Resources\Portfolios\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use App\Models\PortfolioWeightOptimization;
use App\Services\Portfolio\PortfolioWeightOptimizerService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

class PortfolioOptimizationHistoryPage extends ViewRecord
{
    protected static string $resource = PortfolioResource::class;

    protected static ?string $title = 'Histórico de otimizações';

    public bool $onlyFavorites = false;

    public ?int $expandedId = null;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.portfolios.pages.portfolio-optimization-history-page')
                    ->viewData(fn (): array => [
                        'optimizations' => $this->optimizations(),
                        'onlyFavorites' => $this->onlyFavorites,
                        'expandedId' => $this->expandedId,
                    ]),
            ]);
    }

    public function getSubheading(): ?string
    {
        return $this->portfolio()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Resultados')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(fn (): string => PortfolioResource::getUrl('results', ['record' => $this->portfolio()]))
                ->color('gray'),
        ];
    }

    /**
     * Favorites first, then newest.
     *
     * @return Collection<int, PortfolioWeightOptimization>
     */
    private function optimizations(): Collection
    {
        return $this->portfolio()->weightOptimizations()
            ->when($this->onlyFavorites, fn ($query) => $query->where('is_favorite', true))
            ->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function toggleExpanded(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function toggleFavorite(int $id): void
    {
        $optimization = $this->findOptimization($id);

        $optimization?->update(['is_favorite' => ! $optimization->is_favorite]);
    }

    public function applyOptimization(int $id): void
    {
        $optimization = $this->findOptimization($id);

        if ($optimization === null) {
            return;
        }

        $strategies = $optimization->result['strategies'] ?? [];
        $updated = app(PortfolioWeightOptimizerService::class)->applyWeights($this->portfolio(), $strategies);
        $skipped = count($strategies) - $updated;

        $optimization->update(['applied_at' => now()]);

        Notification::make()
            ->title('Pesos aplicados. As métricas foram recalculadas.')
            ->body($skipped > 0
                ? $skipped.' estratégia(s) desta sugestão não fazem mais parte do portfólio e foram ignoradas.'
                : null)
            ->success()
            ->actions([
                Action::make('viewResults')
                    ->label('Ver resultados')
                    ->url(PortfolioResource::getUrl('results', ['record' => $this->portfolio()])),
            ])
            ->send();
    }

    public function deleteOptimization(int $id): void
    {
        $this->findOptimization($id)?->delete();

        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }
    }

    private function findOptimization(int $id): ?PortfolioWeightOptimization
    {
        return $this->portfolio()->weightOptimizations()->find($id);
    }

    private function portfolio(): Portfolio
    {
        /** @var Portfolio $portfolio */
        $portfolio = $this->record;

        return $portfolio;
    }
}
