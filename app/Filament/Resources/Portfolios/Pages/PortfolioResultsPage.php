<?php

namespace App\Filament\Resources\Portfolios\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use App\Models\PortfolioWeightOptimization;
use App\Services\Metrics\MonthlyPerformanceService;
use App\Services\Metrics\PortfolioAnalyzerService;
use App\Services\Metrics\PortfolioCorrelationService;
use App\Services\Portfolio\PortfolioWeightOptimizerService;
use App\Services\Reports\PortfolioReportService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioResultsPage extends ViewRecord
{
    private const MAX_KEPT_OPTIMIZATIONS = 30;

    protected static string $resource = PortfolioResource::class;

    protected static ?string $title = 'Resultados do Portfólio';

    public string $correlationPeriod = PortfolioCorrelationService::PERIOD_DAILY;

    public string $correlationMetric = PortfolioCorrelationService::METRIC_PROFIT_LOSS;

    public string $selectedMonthlyYear = 'all';

    /**
     * Result of the last weight-optimizer run, rendered as an in-page suggestion panel
     * until the user applies or discards it.
     *
     * @var array<string, mixed>
     */
    public array $weightSuggestion = [];

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.portfolios.pages.portfolio-results-page')
                    ->viewData(function (): array {
                        $metrics = $this->metrics();
                        $monthlyPerformance = $metrics['consolidated_monthly_performance'] ?? [];
                        $monthlyCumulativePerformance = $metrics['consolidated_monthly_cumulative_performance'] ?? [];

                        return [
                            'portfolio' => $this->portfolio(),
                            'metrics' => $metrics,
                            'correlation' => $this->correlation(),
                            'weightSuggestion' => $this->weightSuggestion,
                            'weightsKey' => $this->weightsSignature(),
                            'selectedMonthlyYear' => $this->selectedMonthlyYear,
                            'monthlyYearOptions' => $this->monthlyYearOptions($monthlyPerformance),
                            'filteredMonthlyPerformance' => $this->filterByYear($monthlyPerformance),
                            'filteredMonthlyCumulativePerformance' => $this->filterByYear($monthlyCumulativePerformance),
                            'selectedYearBars' => $this->selectedYearBars($metrics['monthly_table'] ?? []),
                        ];
                    }),
            ]);
    }

    /**
     * Child widgets get their data as mount-time props and Livewire keeps an existing child (and
     * its stale props) across parent re-renders. Folding the current weights into their keys makes
     * them remount with fresh numbers as soon as the weights or the active set change.
     */
    private function weightsSignature(): string
    {
        return md5($this->portfolio()->portfolioStrategies()
            ->orderBy('id')
            ->get(['strategy_id', 'weight', 'enabled'])
            ->map(fn ($row): string => $row->strategy_id.':'.(float) $row->weight.':'.(int) $row->enabled)
            ->implode('|'));
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

    /**
     * @param  array<int, array<string, mixed>>  $monthlyPerformance
     * @return array<int, int>
     */
    private function monthlyYearOptions(array $monthlyPerformance): array
    {
        return collect($monthlyPerformance)
            ->pluck('year')
            ->map(fn (mixed $year): int => (int) $year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $performance
     * @return array<int, array<string, mixed>>
     */
    private function filterByYear(array $performance): array
    {
        if ($this->selectedMonthlyYear === 'all') {
            return $performance;
        }

        $year = (int) $this->selectedMonthlyYear;

        return collect($performance)
            ->filter(fn (array $row): bool => (int) ($row['year'] ?? 0) === $year)
            ->values()
            ->all();
    }

    /**
     * Builds the {label, net_profit, trade_count} rows PeriodResultBarChart expects for the
     * currently selected year, omitting months without trades instead of showing them as zero.
     *
     * @param  array<int, array<string, mixed>>  $monthlyTable
     * @return array<int, array{label: string, net_profit: float, trade_count: int}>
     */
    private function selectedYearBars(array $monthlyTable): array
    {
        if ($this->selectedMonthlyYear === 'all') {
            return [];
        }

        $year = (int) $this->selectedMonthlyYear;
        $row = collect($monthlyTable)->first(fn (array $row): bool => (int) ($row['year'] ?? 0) === $year);

        if ($row === null) {
            return [];
        }

        $monthLabels = array_values(MonthlyPerformanceService::MONTHS);

        return collect($row['months'] ?? [])
            ->values()
            ->map(fn (array $month, int $index): array => [
                'label' => $monthLabels[$index] ?? '',
                'net_profit' => (float) ($month['profit'] ?? 0),
                'trade_count' => (int) ($month['trades'] ?? 0),
            ])
            ->filter(fn (array $month): bool => $month['trade_count'] > 0)
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Portfólios')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(PortfolioResource::getUrl('index'))
                ->color('gray'),
            Action::make('optimizeWeights')
                ->label('Otimizar pesos')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading('Otimizar pesos das estratégias')
                ->modalDescription('O sistema busca pesos que melhoram a métrica escolhida, mantendo todas as estratégias ativas na composição. Nada é alterado até você aplicar.')
                ->modalSubmitActionLabel('Gerar sugestão')
                ->schema([
                    Select::make('objective')
                        ->label('Objetivo')
                        ->options(PortfolioWeightOptimizerService::objectiveOptions())
                        ->default(PortfolioWeightOptimizerService::OBJECTIVE_ULCER)
                        ->native(false)
                        ->required(),
                    TextInput::make('min_weight')
                        ->label('Peso mínimo por estratégia')
                        ->helperText('Número inteiro de contratos (mini-índice e mini-dólar operam em múltiplos de 1).')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(1),
                    TextInput::make('max_weight')
                        ->label('Peso máximo por estratégia')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(6),
                    TextInput::make('max_correlation')
                        ->label('Correlação média máxima (opcional)')
                        ->helperText('Limita a correlação média entre as estratégias, ponderada pelos pesos (0 a 1, usando o período da matriz de correlação). Deixe vazio para ignorar.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.05),
                ])
                ->action(function (array $data): void {
                    $this->runWeightOptimization($data);
                }),
            Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(fn () => $this->downloadPdfReport()),
            Action::make('optimizationHistory')
                ->label('Histórico de otimizações')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->url(fn (): string => PortfolioResource::getUrl('optimizations', ['record' => $this->portfolio()])),
            EditAction::make(),
        ];
    }

    private function downloadPdfReport(): StreamedResponse
    {
        $report = app(PortfolioReportService::class);
        $pdf = $report->pdf($this->portfolio());

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $report->fileName($this->portfolio()),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function runWeightOptimization(array $data): void
    {
        $result = app(PortfolioWeightOptimizerService::class)->optimize(
            $this->portfolio(),
            (string) ($data['objective'] ?? PortfolioWeightOptimizerService::OBJECTIVE_ULCER),
            [
                'min' => (int) ($data['min_weight'] ?? 1),
                'max' => (int) ($data['max_weight'] ?? 6),
            ],
            [
                'max_weighted_correlation' => $data['max_correlation'] ?? null,
                'correlation_period' => $this->correlationPeriod,
            ],
        );

        if (($result['ok'] ?? false) !== true) {
            $this->weightSuggestion = [];

            Notification::make()
                ->title($result['message'] ?? 'Não foi possível otimizar os pesos.')
                ->warning()
                ->send();

            return;
        }

        $optimization = $this->portfolio()->weightOptimizations()->create([
            'objective' => $result['objective'],
            'min_weight' => $result['bounds']['min'],
            'max_weight' => $result['bounds']['max'],
            'max_correlation' => $result['constraint']['max_weighted_correlation'],
            'changed' => $result['changed'],
            'result' => $result,
        ]);

        $this->pruneWeightOptimizations();

        $this->weightSuggestion = $result + [
            'optimization_id' => $optimization->getKey(),
            'is_favorite' => false,
        ];

        Notification::make()
            ->title($result['changed']
                ? 'Sugestão de pesos pronta. Revise abaixo antes de aplicar.'
                : 'Os pesos atuais já são os melhores para esse objetivo.')
            ->success()
            ->send();
    }

    /**
     * Keeps the most recent runs; favorites and applied runs are never discarded.
     */
    private function pruneWeightOptimizations(): void
    {
        $keepIds = $this->portfolio()->weightOptimizations()
            ->latest('id')
            ->limit(self::MAX_KEPT_OPTIMIZATIONS)
            ->pluck('id');

        $this->portfolio()->weightOptimizations()
            ->where('is_favorite', false)
            ->whereNull('applied_at')
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function currentOptimization(): ?PortfolioWeightOptimization
    {
        $id = (int) ($this->weightSuggestion['optimization_id'] ?? 0);

        return $id > 0 ? $this->portfolio()->weightOptimizations()->find($id) : null;
    }

    public function applyWeights(): void
    {
        $strategies = $this->weightSuggestion['strategies'] ?? [];

        if ($strategies === []) {
            return;
        }

        app(PortfolioWeightOptimizerService::class)->applyWeights($this->portfolio(), $strategies);

        $this->currentOptimization()?->update(['applied_at' => now()]);

        $this->weightSuggestion = [];

        Notification::make()
            ->title('Pesos atualizados. As métricas foram recalculadas.')
            ->success()
            ->send();
    }

    public function toggleFavoriteSuggestion(): void
    {
        $optimization = $this->currentOptimization();

        if ($optimization === null) {
            return;
        }

        $optimization->update(['is_favorite' => ! $optimization->is_favorite]);

        $this->weightSuggestion['is_favorite'] = $optimization->is_favorite;
    }

    public function discardWeightSuggestion(): void
    {
        $this->weightSuggestion = [];
    }

    private function portfolio(): Portfolio
    {
        /** @var Portfolio $portfolio */
        $portfolio = $this->record;

        return $portfolio;
    }
}
