<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Strategy;
use App\Services\Metrics\StrategyComparisonService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class StrategyComparison extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Comparar Estratégias';

    protected static ?string $title = 'Comparação de Estratégias';

    protected static ?int $navigationSort = 5;

    protected ?string $subheading = 'Selecione duas estratégias para comparar resultado, risco, consistência e o efeito de usá-las juntas.';

    // --- Selection state ---
    #[Url(as: 'a')]
    public string $firstStrategyId = '';

    #[Url(as: 'b')]
    public string $secondStrategyId = '';

    public string $firstBacktestId = StrategyComparisonService::ALL_EXECUTIONS;

    public string $secondBacktestId = StrategyComparisonService::ALL_EXECUTIONS;

    public string $correlationPeriod = StrategyComparisonService::PERIOD_MONTHLY;

    public string $assetFilter = '';

    // --- Comparison state ---
    /** @var array<string, mixed> */
    public array $comparison = [];

    public bool $isCompared = false;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        if ($this->firstStrategyId !== '' && $this->secondStrategyId !== '') {
            $this->compare();
        }
    }

    // --- Computed ---

    /**
     * @return Collection<int, Strategy>
     */
    #[Computed]
    public function availableStrategies(): Collection
    {
        return Strategy::query()
            ->where('user_id', auth()->id())
            ->when($this->assetFilter !== '', fn ($query) => $query->where('asset', $this->assetFilter))
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->get(['id', 'name', 'asset', 'is_favorite']);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function firstExecutionOptions(): array
    {
        return $this->executionOptions($this->firstStrategyId);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function secondExecutionOptions(): array
    {
        return $this->executionOptions($this->secondStrategyId);
    }

    // --- Page content ---

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.strategy-comparison')
                ->viewData(fn (): array => [
                    'availableStrategies' => $this->availableStrategies,
                    'firstStrategyId' => $this->firstStrategyId,
                    'secondStrategyId' => $this->secondStrategyId,
                    'firstExecutionOptions' => $this->firstExecutionOptions,
                    'secondExecutionOptions' => $this->secondExecutionOptions,
                    'assetOptions' => Strategy::assetOptions(),
                    'periodOptions' => app(StrategyComparisonService::class)->periodOptions(),
                    'comparison' => $this->comparison,
                    'isCompared' => $this->isCompared,
                    'errorMessage' => $this->errorMessage,
                    'strategiesUrl' => StrategyResource::getUrl('index'),
                ]),
        ]);
    }

    // --- Livewire lifecycle ---

    public function updatedFirstStrategyId(): void
    {
        $this->firstBacktestId = StrategyComparisonService::ALL_EXECUTIONS;
        $this->resetComparison();
    }

    public function updatedSecondStrategyId(): void
    {
        $this->secondBacktestId = StrategyComparisonService::ALL_EXECUTIONS;
        $this->resetComparison();
    }

    public function updatedFirstBacktestId(): void
    {
        $this->resetComparison();
    }

    public function updatedSecondBacktestId(): void
    {
        $this->resetComparison();
    }

    public function updatedCorrelationPeriod(): void
    {
        if ($this->isCompared) {
            $this->compare();
        }
    }

    // --- Actions ---

    public function compare(): void
    {
        $this->errorMessage = null;
        $this->comparison = [];
        $this->isCompared = false;

        $first = $this->ownedStrategy($this->firstStrategyId);
        $second = $this->ownedStrategy($this->secondStrategyId);

        if ($first === null || $second === null) {
            $this->errorMessage = 'Selecione duas estratégias para comparar.';

            return;
        }

        if ($first->id === $second->id) {
            $this->errorMessage = 'Selecione duas estratégias diferentes.';

            return;
        }

        $this->comparison = app(StrategyComparisonService::class)->compare($first, $second, [
            'first_backtest_id' => $this->firstBacktestId,
            'second_backtest_id' => $this->secondBacktestId,
            'correlation_period' => $this->correlationPeriod,
        ]);

        $this->isCompared = true;
    }

    public function swapStrategies(): void
    {
        [$this->firstStrategyId, $this->secondStrategyId] = [$this->secondStrategyId, $this->firstStrategyId];
        [$this->firstBacktestId, $this->secondBacktestId] = [$this->secondBacktestId, $this->firstBacktestId];

        unset($this->firstExecutionOptions, $this->secondExecutionOptions);

        if ($this->isCompared) {
            $this->compare();
        }
    }

    public function clearComparison(): void
    {
        $this->firstStrategyId = '';
        $this->secondStrategyId = '';
        $this->firstBacktestId = StrategyComparisonService::ALL_EXECUTIONS;
        $this->secondBacktestId = StrategyComparisonService::ALL_EXECUTIONS;

        $this->resetComparison();
    }

    // --- Private helpers ---

    /**
     * @return array<string, string>
     */
    private function executionOptions(string $strategyId): array
    {
        $strategy = $this->ownedStrategy($strategyId);

        if ($strategy === null) {
            return [StrategyComparisonService::ALL_EXECUTIONS => 'Todas as execuções'];
        }

        return app(StrategyComparisonService::class)->executionOptions($strategy);
    }

    private function ownedStrategy(string $strategyId): ?Strategy
    {
        if ((int) $strategyId <= 0) {
            return null;
        }

        return Strategy::query()
            ->where('user_id', auth()->id())
            ->find((int) $strategyId);
    }

    private function resetComparison(): void
    {
        $this->comparison = [];
        $this->isCompared = false;
        $this->errorMessage = null;
    }
}
