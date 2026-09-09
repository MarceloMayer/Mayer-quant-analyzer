<?php

namespace App\Jobs;

use App\Services\Portfolio\PortfolioCombinationAnalyzerService;
use App\Services\Portfolio\PortfolioCombinationGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs the (potentially very large) portfolio-combination analysis off the web request.
 *
 * Progress and the final result are written to the cache under keys derived from the
 * user id + Livewire component id, so PortfolioCombinationAnalyzer can poll for progress
 * and pick up the ranking once it is ready. A cancel flag in the cache lets the page
 * abort a running analysis between chunks.
 */
class AnalyzePortfolioCombinationsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    /**
     * @param  int[]  $selectedStrategyIds
     * @param  array{initial_balance?: float, start_date?: string|null, end_date?: string|null}  $filters
     */
    public function __construct(
        public int $userId,
        public string $componentId,
        public string $analysisId,
        public array $selectedStrategyIds,
        public int $strategiesPerPortfolio,
        public array $filters = [],
    ) {}

    public static function resultsCacheKey(int $userId, string $componentId): string
    {
        return "portfolio_combo_results:{$userId}:{$componentId}";
    }

    public static function progressCacheKey(int $userId, string $componentId): string
    {
        return "portfolio_combo_progress:{$userId}:{$componentId}";
    }

    public static function cancelCacheKey(int $userId, string $componentId): string
    {
        return "portfolio_combo_cancel:{$userId}:{$componentId}";
    }

    public function handle(
        PortfolioCombinationGeneratorService $generator,
        PortfolioCombinationAnalyzerService $analyzer,
    ): void {
        $progressKey = self::progressCacheKey($this->userId, $this->componentId);
        $cancelKey = self::cancelCacheKey($this->userId, $this->componentId);

        // The page clears any stale cancel flag before dispatching (see analyze()), so a
        // flag present here means the user cancelled while this job was still queued.
        if (Cache::get($cancelKey) === true) {
            Cache::forget($cancelKey);
            Cache::put($progressKey, [
                'status' => 'cancelled',
                'done' => 0,
                'total' => 0,
                'analysis_id' => $this->analysisId,
            ], now()->addHour());

            return;
        }

        $combinations = $generator->generate($this->selectedStrategyIds, $this->strategiesPerPortfolio);
        $total = count($combinations);

        Cache::put($progressKey, [
            'status' => 'running',
            'done' => 0,
            'total' => $total,
            'analysis_id' => $this->analysisId,
        ], now()->addHour());

        $cancelled = false;

        $results = $analyzer->analyze(
            $combinations,
            $this->filters,
            function (int $done, int $total) use ($progressKey, $cancelKey, &$cancelled): bool {
                if (Cache::get($cancelKey) === true) {
                    $cancelled = true;

                    return false;
                }

                Cache::put($progressKey, [
                    'status' => 'running',
                    'done' => $done,
                    'total' => $total,
                    'analysis_id' => $this->analysisId,
                ], now()->addHour());

                return true;
            },
        );

        if ($cancelled) {
            Cache::forget(self::resultsCacheKey($this->userId, $this->componentId));
            Cache::forget($cancelKey);
            Cache::put($progressKey, [
                'status' => 'cancelled',
                'done' => 0,
                'total' => $total,
                'analysis_id' => $this->analysisId,
            ], now()->addHour());

            return;
        }

        Cache::put(self::resultsCacheKey($this->userId, $this->componentId), $results, now()->addHour());
        Cache::put($progressKey, [
            'status' => 'done',
            'done' => $total,
            'total' => $total,
            'count' => count($results),
            'analysis_id' => $this->analysisId,
        ], now()->addHour());
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put(self::progressCacheKey($this->userId, $this->componentId), [
            'status' => 'failed',
            'message' => $exception?->getMessage() ?? 'Falha ao processar a análise.',
            'analysis_id' => $this->analysisId,
        ], now()->addHour());
    }
}
