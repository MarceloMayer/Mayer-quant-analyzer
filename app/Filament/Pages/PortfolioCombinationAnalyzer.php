<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use App\Models\PortfolioStrategy;
use App\Models\Strategy;
use App\Services\Portfolio\PortfolioCombinationAnalyzerService;
use App\Services\Portfolio\PortfolioCombinationGeneratorService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class PortfolioCombinationAnalyzer extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Combinações de Portfólios';

    protected static ?string $title = 'Analisador de Combinações de Portfólios';

    protected static ?int $navigationSort = 4;

    // --- Form state ---
    /** @var int[] */
    public array $selectedStrategyIds = [];

    public int $maxStrategies = 3;

    public float $initialBalance = 10000.0;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public string $assetFilter = '';

    public string $nameSearch = '';

    // --- Analysis state ---
    /** @var array<int, array<string, mixed>> */
    public array $analysisResults = [];

    public bool $isAnalyzed = false;

    /** @var string[] */
    public array $selectedToSave = [];

    public ?string $errorMessage = null;

    public string $sortColumn = 'consistency_score';

    public string $sortDirection = 'desc';

    // --- Computed ---

    #[Computed]
    public function availableStrategies(): Collection
    {
        return Strategy::query()
            ->when($this->assetFilter !== '', fn ($q) => $q->where('asset', $this->assetFilter))
            ->when($this->nameSearch !== '', fn ($q) => $q->where('name', 'like', "%{$this->nameSearch}%"))
            ->orderBy('name')
            ->get(['id', 'name', 'asset']);
    }

    #[Computed]
    public function combinationsCount(): int
    {
        return app(PortfolioCombinationGeneratorService::class)->countCombinations(
            count($this->selectedStrategyIds),
            $this->maxStrategies,
        );
    }

    #[Computed]
    public function maxCombinations(): int
    {
        return (int) config('portfolio.max_combinations', 2000);
    }

    #[Computed]
    public function exceedsLimit(): bool
    {
        return $this->combinationsCount > $this->maxCombinations;
    }

    #[Computed]
    public function sortedResults(): array
    {
        if (empty($this->analysisResults)) {
            return [];
        }

        $results = $this->analysisResults;

        usort($results, function (array $a, array $b): int {
            $valA = (float) ($a[$this->sortColumn] ?? 0);
            $valB = (float) ($b[$this->sortColumn] ?? 0);

            // Ascending columns (lower is better)
            $ascending = in_array($this->sortColumn, ['max_drawdown', 'max_drawdown_percent', 'ulcer_index'], true);

            return $ascending
                ? ($this->sortDirection === 'asc' ? $valA <=> $valB : $valB <=> $valA)
                : ($this->sortDirection === 'desc' ? $valB <=> $valA : $valA <=> $valB);
        });

        return $results;
    }

    // --- Page content ---

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.portfolio-combination-analyzer')
                ->viewData(fn (): array => $this->viewData()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array
    {
        return [
            'availableStrategies' => $this->availableStrategies,
            'selectedStrategyIds' => $this->selectedStrategyIds,
            'maxStrategies' => $this->maxStrategies,
            'initialBalance' => $this->initialBalance,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'assetFilter' => $this->assetFilter,
            'nameSearch' => $this->nameSearch,
            'combinationsCount' => $this->combinationsCount,
            'maxCombinations' => $this->maxCombinations,
            'exceedsLimit' => $this->exceedsLimit,
            'isAnalyzed' => $this->isAnalyzed,
            'sortedResults' => $this->sortedResults,
            'selectedToSave' => $this->selectedToSave,
            'errorMessage' => $this->errorMessage,
            'sortColumn' => $this->sortColumn,
            'sortDirection' => $this->sortDirection,
            'assetOptions' => Strategy::assetOptions(),
        ];
    }

    // --- Livewire lifecycle ---

    public function updatedSelectedStrategyIds(): void
    {
        $this->resetAnalysis();
    }

    public function updatedMaxStrategies(): void
    {
        $this->resetAnalysis();
    }

    public function updatedAssetFilter(): void
    {
        // Keep selection even when filter changes; user may have selected before filtering
    }

    // --- Actions ---

    public function selectAllStrategies(): void
    {
        $this->selectedStrategyIds = $this->availableStrategies->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->resetAnalysis();
    }

    public function deselectAllStrategies(): void
    {
        $this->selectedStrategyIds = [];
        $this->resetAnalysis();
    }

    public function selectAllResults(): void
    {
        $this->selectedToSave = array_column($this->analysisResults, 'combination_hash');
    }

    public function deselectAllResults(): void
    {
        $this->selectedToSave = [];
    }

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'desc';
        }
    }

    public function analyze(): void
    {
        $this->errorMessage = null;
        $this->analysisResults = [];
        $this->isAnalyzed = false;
        $this->selectedToSave = [];

        if (count($this->selectedStrategyIds) < 2) {
            $this->errorMessage = 'Selecione ao menos 2 estratégias para gerar combinações.';

            return;
        }

        if ($this->maxStrategies < 2) {
            $this->errorMessage = 'O máximo de estratégias por portfólio deve ser ao menos 2.';

            return;
        }

        $count = $this->combinationsCount;

        if ($count === 0) {
            $this->errorMessage = 'Nenhuma combinação válida encontrada com a seleção atual.';

            return;
        }

        $limit = $this->maxCombinations;

        if ($count > $limit) {
            $this->errorMessage = "Essa seleção geraria {$count} combinações, acima do limite permitido de {$limit}. Reduza o número de estratégias selecionadas ou diminua o máximo de estratégias por portfólio.";

            return;
        }

        $generator = app(PortfolioCombinationGeneratorService::class);
        $analyzer = app(PortfolioCombinationAnalyzerService::class);

        $combinations = $generator->generate($this->selectedStrategyIds, $this->maxStrategies);

        $this->analysisResults = $analyzer->analyze($combinations, [
            'initial_balance' => $this->initialBalance,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ]);

        $this->isAnalyzed = true;
    }

    public function saveSingle(string $hash): void
    {
        $result = collect($this->analysisResults)->firstWhere('combination_hash', $hash);

        if ($result === null) {
            return;
        }

        $saved = $this->persistPortfolio($result);

        if ($saved) {
            Notification::make()
                ->title('Portfólio salvo com sucesso!')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Este portfólio já foi salvo anteriormente.')
                ->warning()
                ->send();
        }
    }

    public function viewResults(string $hash): void
    {
        $result = collect($this->analysisResults)->firstWhere('combination_hash', $hash);

        if ($result === null) {
            return;
        }

        $portfolio = Portfolio::query()->where('combination_hash', $hash)->first();

        if ($portfolio === null) {
            $this->persistPortfolio($result);
            $portfolio = Portfolio::query()->where('combination_hash', $hash)->first();
        }

        if ($portfolio === null) {
            return;
        }

        $this->redirect(PortfolioResource::getUrl('results', ['record' => $portfolio->id]));
    }

    public function saveSelected(): void
    {
        if (empty($this->selectedToSave)) {
            return;
        }

        $savedCount = 0;
        $skippedCount = 0;

        foreach ($this->selectedToSave as $hash) {
            $result = collect($this->analysisResults)->firstWhere('combination_hash', $hash);

            if ($result === null) {
                continue;
            }

            if ($this->persistPortfolio($result)) {
                $savedCount++;
            } else {
                $skippedCount++;
            }
        }

        $msg = "Portfólios salvos: {$savedCount}.";
        if ($skippedCount > 0) {
            $msg .= " Já existentes (ignorados): {$skippedCount}.";
        }

        Notification::make()
            ->title($msg)
            ->success()
            ->send();

        $this->selectedToSave = [];
    }

    // --- Private helpers ---

    /**
     * @param  array<string, mixed>  $result
     */
    private function persistPortfolio(array $result): bool
    {
        $hash = (string) $result['combination_hash'];

        if (Portfolio::query()->where('combination_hash', $hash)->exists()) {
            return false;
        }

        $strategyIds = (array) $result['strategy_ids'];
        $strategyNames = (array) $result['strategy_names'];
        $count = (int) $result['strategies_count'];
        $score = (float) $result['consistency_score'];

        $number = str_pad(
            (string) (Portfolio::query()->where('name', 'like', 'Portfolio Combo #%')->count() + 1),
            3,
            '0',
            STR_PAD_LEFT,
        );

        if ($count <= 3) {
            $namePart = implode(' + ', $strategyNames);
            $name = "Portfolio Combo #{$number} - {$namePart}";
        } else {
            $name = "Portfolio Combo #{$number} - {$count} estratégias";
        }

        if (mb_strlen($name) > 255) {
            $name = "Portfolio Combo #{$number} - {$count} estratégias";
        }

        $description = 'Gerado automaticamente pelo Analisador de Combinações. '
            . 'Estratégias: ' . implode(', ', $strategyNames) . '. '
            . "Score de consistência no momento da geração: {$score}.";

        DB::transaction(function () use ($name, $description, $hash, $result, $strategyIds): void {
            $portfolio = Portfolio::create([
                'name' => $name,
                'description' => $description,
                'combination_hash' => $hash,
                'initial_balance' => $result['initial_balance'] ?? null,
            ]);

            foreach ($strategyIds as $strategyId) {
                PortfolioStrategy::create([
                    'portfolio_id' => $portfolio->id,
                    'strategy_id' => $strategyId,
                    'enabled' => true,
                    'weight' => 1,
                ]);
            }
        });

        return true;
    }

    private function resetAnalysis(): void
    {
        $this->analysisResults = [];
        $this->isAnalyzed = false;
        $this->selectedToSave = [];
        $this->errorMessage = null;
    }
}
