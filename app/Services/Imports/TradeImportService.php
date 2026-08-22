<?php

namespace App\Services\Imports;

use App\Models\Strategy;
use App\Models\StrategyImport;
use App\Models\Trade;
use App\Services\Trading\StrategyBacktestExecutionUpsertService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TradeImportService
{
    public function __construct(
        private readonly CsvTradeParser $parser,
        private readonly TradeNormalizer $normalizer,
        private readonly Mt5MultipleReportImportService $multipleReportImportService,
        private readonly StrategyBacktestExecutionUpsertService $executionUpsert,
    ) {}

    /**
     * @return array{total_rows: int, imported_rows: int, ignored_rows: int, warnings: array<int, string>}
     */
    public function import(Strategy $strategy, UploadedFile|string $file, ?string $backtestId = null, array $executionData = []): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $fileName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);

        if ($this->isXlsx((string) $fileName, (string) $filePath)) {
            return $this->importXlsx($strategy, (string) $filePath, (string) $fileName, $backtestId, $executionData);
        }

        return $this->importCsv($strategy, (string) $filePath, (string) $fileName, $backtestId, $executionData);
    }

    /**
     * @param  array<string, mixed>  $executionData
     * @return array{total_rows: int, imported_rows: int, ignored_rows: int, warnings: array<int, string>}
     */
    private function importCsv(Strategy $strategy, string $filePath, string $fileName, ?string $backtestId, array $executionData): array
    {
        $rows = $this->parser->parse((string) $filePath);
        $totalRows = count($rows);
        $importedRows = 0;
        $ignoredRows = 0;
        $backtestId = $backtestId === null || trim($backtestId) === ''
            ? $this->defaultBacktestId($strategy)
            : trim($backtestId);
        $periodStart = null;
        $periodEnd = null;

        DB::transaction(function () use ($strategy, $rows, $fileName, $backtestId, $executionData, &$importedRows, &$ignoredRows, &$periodStart, &$periodEnd): void {
            foreach ($rows as $row) {
                $trade = $this->normalizer->normalize($strategy, $row);

                if ($trade === null) {
                    $ignoredRows++;

                    continue;
                }

                $trade['backtest_id'] = $backtestId;

                if ($this->isDuplicate($strategy, $trade)) {
                    $ignoredRows++;

                    continue;
                }

                Trade::query()->create($trade);
                $importedRows++;
                $tradeExitDate = $this->date($trade['exit_time'] ?? null);

                if ($tradeExitDate !== null) {
                    $periodStart = $periodStart === null || $tradeExitDate->lt($periodStart) ? $tradeExitDate : $periodStart;
                    $periodEnd = $periodEnd === null || $tradeExitDate->gt($periodEnd) ? $tradeExitDate : $periodEnd;
                }
            }

            StrategyImport::query()->create([
                'strategy_id' => $strategy->id,
                'file_name' => $fileName,
                'total_rows' => count($rows),
                'imported_rows' => $importedRows,
                'ignored_rows' => $ignoredRows,
                'imported_at' => now(),
            ]);

            $this->executionUpsert->upsertFromImport($strategy, $backtestId, [
                'start_date' => $periodStart?->toDateString(),
                'end_date' => $periodEnd?->toDateString(),
            ], $executionData);
        });

        return [
            'total_rows' => $totalRows,
            'imported_rows' => $importedRows,
            'ignored_rows' => $ignoredRows,
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $executionData
     * @return array{total_rows: int, imported_rows: int, ignored_rows: int, warnings: array<int, string>}
     */
    private function importXlsx(Strategy $strategy, string $filePath, string $fileName, ?string $backtestId, array $executionData): array
    {
        $result = $this->multipleReportImportService->import($strategy, $backtestId ?? $this->defaultBacktestId($strategy), [
            [
                'path' => $filePath,
                'name' => $fileName,
            ],
        ], $executionData);

        return [
            'total_rows' => $result['total_trades_found'],
            'imported_rows' => $result['total_trades_imported'],
            'ignored_rows' => $result['total_trades_skipped_duplicates'] + $result['files_skipped_duplicate_hash'],
            'warnings' => $result['warnings'],
        ];
    }

    /**
     * @param  array<string, mixed>  $trade
     */
    private function isDuplicate(Strategy $strategy, array $trade): bool
    {
        if (! blank($trade['backtest_id'] ?? null) && ! blank($trade['exit_deal_id'] ?? null)) {
            return Trade::query()
                ->where('strategy_id', $strategy->id)
                ->where('backtest_id', $trade['backtest_id'])
                ->where('exit_deal_id', $trade['exit_deal_id'])
                ->exists();
        }

        if (blank($trade['order_id'] ?? null)) {
            return false;
        }

        return Trade::query()
            ->where('strategy_id', $strategy->id)
            ->where('order_id', $trade['order_id'])
            ->exists();
    }

    private function isXlsx(string $fileName, string $filePath): bool
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: pathinfo($filePath, PATHINFO_EXTENSION));

        return $extension === 'xlsx';
    }

    private function defaultBacktestId(Strategy $strategy): string
    {
        return 'strategy-'.$strategy->id;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
