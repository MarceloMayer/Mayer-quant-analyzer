<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Jobs\AnalyzePortfolioCombinationsJob;
use App\Models\Portfolio;
use App\Models\PortfolioStrategy;
use App\Models\Strategy;
use App\Services\Portfolio\PortfolioCombinationAnalyzerService;
use App\Services\Portfolio\PortfolioCombinationGeneratorService;
use BackedEnum;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public int $strategiesPerPortfolio = 3;

    public float $initialBalance = 10000.0;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public string $assetFilter = '';

    public string $nameSearch = '';

    // --- Analysis state ---
    // Full results are cached server-side (see analysisResultsCacheKey()) instead of held
    // in a public property: with up to maxCombinations() rows, embedding them directly here
    // would be re-serialized into the Livewire payload on every interaction (sorting, paging,
    // selecting a checkbox) and quickly blow past Livewire's request payload size limit.
    public int $resultsCount = 0;

    public bool $isAnalyzed = false;

    /** @var string[] */
    public array $selectedToSave = [];

    public ?string $errorMessage = null;

    public string $sortColumn = 'consistency_score';

    public string $sortDirection = 'desc';

    public int $resultsPerPage = 25;

    public int $currentPage = 1;

    // --- Queued analysis / progress state ---
    public bool $isRunning = false;

    public int $progressDone = 0;

    public int $progressTotal = 0;

    public ?string $analysisId = null;

    // --- Inline curve preview (no persistence) ---
    /** @var array<string, mixed> */
    public array $preview = [];

    public ?string $previewHash = null;

    // --- Computed ---

    #[Computed]
    public function availableStrategies(): Collection
    {
        return Strategy::query()
            ->where('user_id', auth()->id())
            ->when($this->assetFilter !== '', fn ($q) => $q->where('asset', $this->assetFilter))
            ->when($this->nameSearch !== '', fn ($q) => $q->where('name', 'like', "%{$this->nameSearch}%"))
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->get(['id', 'name', 'asset', 'is_favorite']);
    }

    #[Computed]
    public function combinationsCount(): int
    {
        return app(PortfolioCombinationGeneratorService::class)->countCombinations(
            count($this->selectedStrategyIds),
            $this->strategiesPerPortfolio,
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
        $results = $this->getAnalysisResults();

        if (empty($results)) {
            return [];
        }

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

    #[Computed]
    public function totalPages(): int
    {
        return (int) max(1, ceil(count($this->sortedResults) / $this->resultsPerPage));
    }

    #[Computed]
    public function paginatedResults(): array
    {
        $offset = ($this->currentPage - 1) * $this->resultsPerPage;

        return array_slice($this->sortedResults, $offset, $this->resultsPerPage);
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
            'strategiesPerPortfolio' => $this->strategiesPerPortfolio,
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
            'paginatedResults' => $this->paginatedResults,
            'currentPage' => $this->currentPage,
            'totalPages' => $this->totalPages,
            'resultsPerPage' => $this->resultsPerPage,
            'selectedToSave' => $this->selectedToSave,
            'errorMessage' => $this->errorMessage,
            'sortColumn' => $this->sortColumn,
            'sortDirection' => $this->sortDirection,
            'assetOptions' => Strategy::assetOptions(),
            'isRunning' => $this->isRunning,
            'progressDone' => $this->progressDone,
            'progressTotal' => $this->progressTotal,
            'progressPercent' => $this->progressTotal > 0
                ? min(100, (int) round(($this->progressDone / $this->progressTotal) * 100))
                : 0,
            'preview' => $this->preview,
            'previewHash' => $this->previewHash,
        ];
    }

    // --- Livewire lifecycle ---

    public function updatedSelectedStrategyIds(): void
    {
        $this->resetAnalysis();
    }

    public function updatedStrategiesPerPortfolio(): void
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
        $this->selectedToSave = array_column($this->getAnalysisResults(), 'combination_hash');
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

        $this->currentPage = 1;
    }

    public function goToPage(int $page): void
    {
        $this->currentPage = max(1, min($page, $this->totalPages));
    }

    public function nextPage(): void
    {
        $this->goToPage($this->currentPage + 1);
    }

    public function previousPage(): void
    {
        $this->goToPage($this->currentPage - 1);
    }

    public function analyze(): void
    {
        $this->errorMessage = null;
        $this->setAnalysisResults([]);
        $this->isAnalyzed = false;
        $this->selectedToSave = [];
        $this->currentPage = 1;
        $this->closePreview();

        $this->selectedStrategyIds = collect($this->selectedStrategyIds)
            ->map(fn (mixed $strategyId): int => (int) $strategyId)
            ->filter(fn (int $strategyId): bool => $strategyId > 0)
            ->unique()
            ->values()
            ->all();

        if (count($this->selectedStrategyIds) < 2) {
            $this->errorMessage = 'Selecione ao menos 2 estratégias para gerar combinações.';

            return;
        }

        $ownedStrategiesCount = Strategy::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->selectedStrategyIds)
            ->count();

        if ($ownedStrategiesCount !== count($this->selectedStrategyIds)) {
            $this->errorMessage = 'A seleção contém estratégias às quais você não tem acesso.';

            return;
        }

        if ($this->strategiesPerPortfolio < 2) {
            $this->errorMessage = 'O número de estratégias por portfólio deve ser ao menos 2.';

            return;
        }

        if ($this->strategiesPerPortfolio > count($this->selectedStrategyIds)) {
            $this->errorMessage = 'O número de estratégias por portfólio não pode ser maior que a quantidade de estratégias selecionadas.';

            return;
        }

        $count = $this->combinationsCount;

        if ($count === 0) {
            $this->errorMessage = 'Nenhuma combinação válida encontrada com a seleção atual.';

            return;
        }

        $limit = $this->maxCombinations;

        if ($count > $limit) {
            $this->errorMessage = "Essa seleção geraria {$count} combinações, acima do limite permitido de {$limit}. Reduza o número de estratégias selecionadas ou ajuste o número de estratégias por portfólio.";

            return;
        }

        // The scoring runs off the web request as a queued job; the page polls
        // pollAnalysis() for progress and picks up the ranking once it is ready.
        $this->analysisId = (string) Str::uuid();
        $this->isRunning = true;
        $this->progressDone = 0;
        $this->progressTotal = $count;

        Cache::forget($this->progressCacheKey());
        Cache::forget($this->cancelCacheKey());

        AnalyzePortfolioCombinationsJob::dispatch(
            (int) auth()->id(),
            $this->getId(),
            $this->analysisId,
            $this->selectedStrategyIds,
            $this->strategiesPerPortfolio,
            [
                'initial_balance' => $this->initialBalance,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
            ],
        );

        // Reconcile immediately so a sync queue (or a very fast job) doesn't leave the
        // page stuck on "0%".
        $this->pollAnalysis();
    }

    public function pollAnalysis(): void
    {
        if (! $this->isRunning) {
            return;
        }

        $progress = Cache::get($this->progressCacheKey());

        if (! is_array($progress) || ($progress['analysis_id'] ?? null) !== $this->analysisId) {
            return;
        }

        $this->progressTotal = (int) ($progress['total'] ?? $this->progressTotal);
        $this->progressDone = (int) ($progress['done'] ?? $this->progressDone);

        $status = $progress['status'] ?? 'running';

        if ($status === 'done') {
            $this->isRunning = false;
            $this->isAnalyzed = true;
            $this->resultsCount = (int) ($progress['count'] ?? count($this->getAnalysisResults()));
            $this->currentPage = 1;
            Cache::forget($this->progressCacheKey());

            return;
        }

        if ($status === 'failed') {
            $this->isRunning = false;
            $this->errorMessage = $progress['message'] ?? 'Falha ao processar a análise.';
            Cache::forget($this->progressCacheKey());

            return;
        }

        if ($status === 'cancelled') {
            $this->isRunning = false;
            Cache::forget($this->progressCacheKey());
        }
    }

    public function cancelAnalysis(): void
    {
        Cache::put($this->cancelCacheKey(), true, now()->addHour());
        $this->isRunning = false;
        $this->progressDone = 0;
        $this->progressTotal = 0;

        Notification::make()
            ->title('Análise cancelada.')
            ->warning()
            ->send();
    }

    public function saveSingle(string $hash): void
    {
        $result = collect($this->getAnalysisResults())->firstWhere('combination_hash', $hash);

        if ($result === null) {
            return;
        }

        $saved = $this->persistPortfolio($result);

        if ($saved) {
            $portfolio = Portfolio::query()
                ->where('user_id', auth()->id())
                ->where('combination_hash', $hash)
                ->first();

            $notification = Notification::make()
                ->title('Portfólio salvo com sucesso!')
                ->success();

            if ($portfolio !== null) {
                $notification->actions([
                    NotificationAction::make('ver')
                        ->label('Abrir resultados')
                        ->url(PortfolioResource::getUrl('results', ['record' => $portfolio->id]), shouldOpenInNewTab: true),
                ]);
            }

            $notification->send();
        } else {
            Notification::make()
                ->title('Este portfólio já foi salvo anteriormente.')
                ->warning()
                ->send();
        }
    }

    public function previewCombination(string $hash): void
    {
        $result = collect($this->getAnalysisResults())->firstWhere('combination_hash', $hash);

        if ($result === null) {
            return;
        }

        $this->previewHash = $hash;
        $this->preview = app(PortfolioCombinationAnalyzerService::class)->buildCurve(
            (array) ($result['strategy_ids'] ?? []),
            [
                'initial_balance' => $this->initialBalance,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
            ],
        );
    }

    public function closePreview(): void
    {
        $this->preview = [];
        $this->previewHash = null;
    }

    public function saveSelected(): void
    {
        if (empty($this->selectedToSave)) {
            return;
        }

        $savedCount = 0;
        $skippedCount = 0;
        $resultsByHash = collect($this->getAnalysisResults())->keyBy('combination_hash');

        foreach ($this->selectedToSave as $hash) {
            $result = $resultsByHash->get($hash);

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
        $userId = auth()->id();
        $strategyIds = collect((array) ($result['strategy_ids'] ?? []))
            ->map(fn (mixed $strategyId): int => (int) $strategyId)
            ->filter(fn (int $strategyId): bool => $strategyId > 0)
            ->unique()
            ->values()
            ->all();

        $strategies = Strategy::query()
            ->where('user_id', $userId)
            ->whereKey($strategyIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        if (count($strategyIds) < 2 || $strategies->count() !== count($strategyIds)) {
            return false;
        }

        if (Portfolio::query()
            ->where('user_id', $userId)
            ->where('combination_hash', $hash)
            ->exists()) {
            return false;
        }

        $strategyNames = array_map(
            fn (int $strategyId): string => (string) $strategies->get($strategyId)->name,
            $strategyIds,
        );
        $count = count($strategyIds);
        $score = (float) $result['consistency_score'];

        $number = str_pad(
            (string) (Portfolio::query()
                ->where('user_id', $userId)
                ->where('name', 'like', 'Portfolio Combo #%')
                ->count() + 1),
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
            .'Estratégias: '.implode(', ', $strategyNames).'. '
            ."Score de consistência no momento da geração: {$score}.";

        DB::transaction(function () use ($name, $description, $hash, $result, $strategyIds, $userId): void {
            $portfolio = Portfolio::create([
                'user_id' => $userId,
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
        $this->setAnalysisResults([]);
        $this->isAnalyzed = false;
        $this->selectedToSave = [];
        $this->errorMessage = null;
        $this->currentPage = 1;
        $this->isRunning = false;
        $this->progressDone = 0;
        $this->progressTotal = 0;
        $this->analysisId = null;
        Cache::forget($this->progressCacheKey());
        $this->closePreview();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAnalysisResults(): array
    {
        return Cache::get($this->analysisResultsCacheKey(), []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function setAnalysisResults(array $results): void
    {
        $this->resultsCount = count($results);

        if (empty($results)) {
            Cache::forget($this->analysisResultsCacheKey());

            return;
        }

        Cache::put($this->analysisResultsCacheKey(), $results, now()->addHour());
    }

    /**
     * Scoped to the current user and this specific component instance, so results from one
     * analysis don't leak into another tab/session while this page is open. Shared with
     * AnalyzePortfolioCombinationsJob, which writes the ranking here.
     */
    private function analysisResultsCacheKey(): string
    {
        return AnalyzePortfolioCombinationsJob::resultsCacheKey((int) auth()->id(), $this->getId());
    }

    private function progressCacheKey(): string
    {
        return AnalyzePortfolioCombinationsJob::progressCacheKey((int) auth()->id(), $this->getId());
    }

    private function cancelCacheKey(): string
    {
        return AnalyzePortfolioCombinationsJob::cancelCacheKey((int) auth()->id(), $this->getId());
    }
}
