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
use Illuminate\Support\Facades\Cache;

class PortfolioResultsPage extends ViewRecord
{
    protected static string $resource = PortfolioResource::class;

    protected static ?string $title = 'Resultados do Portfólio';

    public string $correlationPeriod = PortfolioCorrelationService::PERIOD_MONTHLY;

    public string $correlationMetric = PortfolioCorrelationService::METRIC_PROFIT_LOSS;

    public string $dailyFilter = 'all';

    public int $dailyPerPage = 15;

    public int $dailyPage = 1;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.portfolios.pages.portfolio-results-page')
                    ->viewData(function (): array {
                        $metrics = $this->metrics();

                        return [
                            'portfolio' => $this->portfolio(),
                            'metrics' => $metrics,
                            'dailyTable' => $this->dailyTable($metrics['daily_performance'] ?? []),
                            'correlation' => $this->correlation(),
                        ];
                    }),
            ]);
    }

    /**
     * Cached so that UI-only interactions that don't change the underlying dataset — paginating
     * or filtering the daily table — don't trigger a full portfolio recalculation (equity curve,
     * drawdown, streaks, monthly/daily performance) on every request.
     *
     * @return array<string, mixed>
     */
    private function metrics(): array
    {
        return Cache::remember(
            'portfolio_metrics:'.$this->portfolio()->getKey(),
            now()->addMinutes(10),
            fn (): array => app(PortfolioAnalyzerService::class)->calculate($this->portfolio()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function correlation(): array
    {
        return Cache::remember(
            'portfolio_correlation:'.$this->portfolio()->getKey().':'.$this->correlationPeriod.':'.$this->correlationMetric,
            now()->addMinutes(10),
            fn (): array => app(PortfolioCorrelationService::class)->calculate(
                $this->portfolio(),
                $this->correlationPeriod,
                $this->correlationMetric,
            ),
        );
    }

    public function updatedDailyFilter(): void
    {
        $this->dailyPage = 1;
    }

    public function updatedDailyPerPage(): void
    {
        $this->dailyPage = 1;
    }

    public function previousDailyPage(): void
    {
        $this->dailyPage = max(1, $this->dailyPage - 1);
    }

    public function nextDailyPage(): void
    {
        $this->dailyPage++;
    }

    /**
     * @param  array<string, mixed>  $dailyPerformance
     * @return array{rows: array<int, array<string, mixed>>, total: int, current_page: int, last_page: int, per_page: int}
     */
    private function dailyTable(array $dailyPerformance): array
    {
        $rows = collect($dailyPerformance['rows'] ?? []);

        if ($this->dailyFilter !== 'all') {
            $rows = $rows->where('classification', $this->dailyFilter);
        }

        $perPage = max(1, $this->dailyPerPage);
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $this->dailyPage = min(max(1, $this->dailyPage), $lastPage);

        return [
            'rows' => $rows->slice(($this->dailyPage - 1) * $perPage, $perPage)->values()->all(),
            'total' => $total,
            'current_page' => $this->dailyPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
        ];
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
