<?php

namespace App\Filament\Resources\Portfolios\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use App\Models\PortfolioStrategy;
use App\Services\Metrics\PortfolioAnalyzerService;
use App\Services\Metrics\PortfolioCorrelationService;
use App\Services\Portfolio\PortfolioWeightOptimizerService;
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
use Illuminate\Support\Facades\DB;

class PortfolioResultsPage extends ViewRecord
{
    protected static string $resource = PortfolioResource::class;

    protected static ?string $title = 'Resultados do Portfólio';

    public string $correlationPeriod = PortfolioCorrelationService::PERIOD_MONTHLY;

    public string $correlationMetric = PortfolioCorrelationService::METRIC_PROFIT_LOSS;

    public string $dailyFilter = 'all';

    public int $dailyPerPage = 15;

    public int $dailyPage = 1;

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

                        return [
                            'portfolio' => $this->portfolio(),
                            'metrics' => $metrics,
                            'dailyTable' => $this->dailyTable($metrics['daily_performance'] ?? []),
                            'correlation' => $this->correlation(),
                            'weightSuggestion' => $this->weightSuggestion,
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
                ])
                ->action(function (array $data): void {
                    $this->runWeightOptimization($data);
                }),
            EditAction::make(),
        ];
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
        );

        if (($result['ok'] ?? false) !== true) {
            $this->weightSuggestion = [];

            Notification::make()
                ->title($result['message'] ?? 'Não foi possível otimizar os pesos.')
                ->warning()
                ->send();

            return;
        }

        $this->weightSuggestion = $result;

        Notification::make()
            ->title($result['changed']
                ? 'Sugestão de pesos pronta. Revise abaixo antes de aplicar.'
                : 'Os pesos atuais já são os melhores para esse objetivo.')
            ->success()
            ->send();
    }

    public function applyWeights(): void
    {
        $strategies = $this->weightSuggestion['strategies'] ?? [];

        if ($strategies === []) {
            return;
        }

        DB::transaction(function () use ($strategies): void {
            foreach ($strategies as $strategy) {
                PortfolioStrategy::query()
                    ->where('portfolio_id', $this->portfolio()->getKey())
                    ->where('strategy_id', (int) $strategy['strategy_id'])
                    ->each(function (PortfolioStrategy $portfolioStrategy) use ($strategy): void {
                        $portfolioStrategy->weight = (float) $strategy['suggested_weight'];
                        $portfolioStrategy->save();
                    });
            }
        });

        $this->weightSuggestion = [];

        Notification::make()
            ->title('Pesos atualizados. As métricas foram recalculadas.')
            ->success()
            ->send();
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
