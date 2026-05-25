<?php

namespace App\Services\Imports;

use App\Models\Mt5ReportFile;
use App\Models\Strategy;
use App\Models\StrategyImport;
use App\Models\Trade;
use App\Services\Trading\Mt5TradeFingerprintService;
use App\Services\Trading\Mt5XlsxReportParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Mt5MultipleReportImportService
{
    public function __construct(
        private readonly Mt5XlsxReportParser $parser,
        private readonly Mt5TradeFingerprintService $fingerprints,
    ) {}

    /**
     * @param  array<int, array{path: string, name: string}|string>  $files
     * @return array<string, mixed>
     */
    public function import(Strategy $strategy, string $backtestId, array $files): array
    {
        $backtestId = trim($backtestId);

        if ($backtestId === '') {
            throw new RuntimeException('Identificador do backtest não informado.');
        }

        $this->ensureExistingTradeFingerprints($strategy, $backtestId);

        $summary = [
            'files_received' => count($files),
            'files_imported' => 0,
            'files_skipped_duplicate_hash' => 0,
            'total_trades_found' => 0,
            'total_trades_imported' => 0,
            'total_trades_skipped_duplicates' => 0,
            'warnings' => [],
            'file_results' => [],
        ];

        $processedPeriods = [];

        foreach ($files as $file) {
            $filePath = is_array($file) ? $file['path'] : $file;
            $fileName = is_array($file) ? $file['name'] : basename($file);
            $fileWarnings = [];

            if (! is_readable($filePath)) {
                $fileWarnings[] = "Arquivo {$fileName} não pôde ser lido.";
                $summary['warnings'][] = $fileWarnings[0];
                $summary['file_results'][] = $this->fileResult($fileName, null, null, 0, 0, 0, 'error', $fileWarnings);

                continue;
            }

            $fileHash = hash_file('sha256', $filePath);

            if ($fileHash === false) {
                $fileWarnings[] = "Não foi possível calcular o hash do arquivo {$fileName}.";
                $summary['warnings'][] = $fileWarnings[0];
                $summary['file_results'][] = $this->fileResult($fileName, null, null, 0, 0, 0, 'error', $fileWarnings);

                continue;
            }

            if ($this->alreadyImported($backtestId, $fileHash)) {
                $fileWarnings[] = "Arquivo {$fileName} já foi importado anteriormente para o backtest {$backtestId}.";
                $summary['files_skipped_duplicate_hash']++;
                $summary['warnings'][] = $fileWarnings[0];
                $summary['file_results'][] = $this->fileResult($fileName, null, null, 0, 0, 0, Mt5ReportFile::STATUS_SKIPPED_DUPLICATE, $fileWarnings);

                continue;
            }

            $report = $this->parser->parse($filePath, $backtestId);
            $metadata = $report['metadata'] ?? [];
            $reportStartDate = $metadata['start_date'] ?? null;
            $reportEndDate = $metadata['end_date'] ?? null;
            $trades = $report['trades'] ?? [];
            $fileWarnings = array_values($report['warnings'] ?? []);

            if ($this->hasPeriodOverlap($backtestId, $reportStartDate, $reportEndDate, $processedPeriods)) {
                $fileWarnings[] = "Sobreposição de período detectada no arquivo {$fileName}. Trades duplicados serão ignorados por fingerprint.";
            }

            $importedTrades = 0;
            $skippedDuplicates = 0;
            $tradeFingerprints = $this->buildTradeFingerprints($strategy, $backtestId, $trades);
            $existingFingerprints = $this->existingFingerprints($backtestId, array_values($tradeFingerprints));
            $seenInFile = [];

            $reportFile = DB::transaction(function () use (
                $strategy,
                $backtestId,
                $fileName,
                $fileHash,
                $metadata,
                $report,
                $reportStartDate,
                $reportEndDate,
                $trades,
                $tradeFingerprints,
                $existingFingerprints,
                &$seenInFile,
                &$importedTrades,
                &$skippedDuplicates,
                &$fileWarnings,
            ): Mt5ReportFile {
                $reportFile = Mt5ReportFile::query()->create([
                    'backtest_id' => $backtestId,
                    'strategy_id' => $strategy->id,
                    'original_filename' => $fileName,
                    'file_hash' => $fileHash,
                    'report_start_date' => $reportStartDate,
                    'report_end_date' => $reportEndDate,
                    'initial_deposit' => $metadata['initial_deposit'] ?? null,
                    'reported_net_profit' => data_get($report, 'summary.total_net_profit'),
                    'imported_trades_count' => 0,
                    'skipped_duplicates_count' => 0,
                    'warnings' => [],
                    'status' => Mt5ReportFile::STATUS_IMPORTED,
                    'imported_at' => now(),
                ]);

                foreach ($trades as $index => $trade) {
                    $fingerprint = $tradeFingerprints[$index] ?? null;

                    if ($fingerprint === null || isset($existingFingerprints[$fingerprint]) || isset($seenInFile[$fingerprint])) {
                        $skippedDuplicates++;

                        continue;
                    }

                    $seenInFile[$fingerprint] = true;
                    $trade['strategy_id'] = $strategy->id;
                    $trade['backtest_id'] = $backtestId;
                    $trade['mt5_report_file_id'] = $reportFile->id;
                    $trade['trade_fingerprint'] = $fingerprint;
                    $trade['asset'] = $trade['asset'] ?? $trade['symbol'] ?? $strategy->asset;

                    Trade::query()->create($trade);
                    $importedTrades++;
                }

                $reportFile->update([
                    'imported_trades_count' => $importedTrades,
                    'skipped_duplicates_count' => $skippedDuplicates,
                    'warnings' => $fileWarnings,
                ]);

                return $reportFile;
            });

            foreach ($fileWarnings as $warning) {
                Log::warning('Aviso na importação múltipla de relatório XLSX do MT5.', [
                    'strategy_id' => $strategy->id,
                    'backtest_id' => $backtestId,
                    'file_name' => $fileName,
                    'warning' => $warning,
                ]);
            }

            $summary['files_imported']++;
            $summary['total_trades_found'] += count($trades);
            $summary['total_trades_imported'] += $importedTrades;
            $summary['total_trades_skipped_duplicates'] += $skippedDuplicates;
            $summary['warnings'] = array_merge($summary['warnings'], $fileWarnings);
            $summary['file_results'][] = $this->fileResult(
                $fileName,
                $reportStartDate,
                $reportEndDate,
                count($trades),
                $importedTrades,
                $skippedDuplicates,
                $reportFile->status,
                $fileWarnings,
            );

            if ($reportStartDate !== null && $reportEndDate !== null) {
                $processedPeriods[] = [
                    'start' => $reportStartDate,
                    'end' => $reportEndDate,
                    'file_name' => $fileName,
                ];
            }
        }

        StrategyImport::query()->create([
            'strategy_id' => $strategy->id,
            'file_name' => $summary['files_received'].' arquivo(s) XLSX MT5',
            'total_rows' => $summary['total_trades_found'],
            'imported_rows' => $summary['total_trades_imported'],
            'ignored_rows' => $summary['total_trades_skipped_duplicates'] + $summary['files_skipped_duplicate_hash'],
            'imported_at' => now(),
        ]);

        $summary['warnings'] = array_values(array_unique($summary['warnings']));

        return $summary;
    }

    private function alreadyImported(string $backtestId, string $fileHash): bool
    {
        return Mt5ReportFile::query()
            ->where('backtest_id', $backtestId)
            ->where('file_hash', $fileHash)
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $trades
     * @return array<int, string>
     */
    private function buildTradeFingerprints(Strategy $strategy, string $backtestId, array $trades): array
    {
        $fingerprints = [];

        foreach ($trades as $index => $trade) {
            $fingerprints[$index] = $this->fingerprints->fingerprint($strategy->id, $backtestId, $trade);
        }

        return $fingerprints;
    }

    /**
     * @param  array<int, string>  $fingerprints
     * @return array<string, true>
     */
    private function existingFingerprints(string $backtestId, array $fingerprints): array
    {
        if ($fingerprints === []) {
            return [];
        }

        return Trade::query()
            ->where('backtest_id', $backtestId)
            ->whereIn('trade_fingerprint', array_unique($fingerprints))
            ->pluck('trade_fingerprint')
            ->filter()
            ->mapWithKeys(fn (string $fingerprint): array => [$fingerprint => true])
            ->all();
    }

    private function ensureExistingTradeFingerprints(Strategy $strategy, string $backtestId): void
    {
        Trade::query()
            ->where('strategy_id', $strategy->id)
            ->where('backtest_id', $backtestId)
            ->whereNull('trade_fingerprint')
            ->orderBy('id')
            ->chunkById(200, function (Collection $trades) use ($strategy, $backtestId): void {
                foreach ($trades as $trade) {
                    /** @var Trade $trade */
                    $fingerprint = $this->fingerprints->fingerprint($strategy->id, $backtestId, $trade->toArray());

                    if (Trade::query()
                        ->where('backtest_id', $backtestId)
                        ->where('trade_fingerprint', $fingerprint)
                        ->whereKeyNot($trade->id)
                        ->exists()) {
                        continue;
                    }

                    $trade->trade_fingerprint = $fingerprint;
                    $trade->save();
                }
            });
    }

    /**
     * @param  array<int, array{start: string, end: string, file_name: string}>  $processedPeriods
     */
    private function hasPeriodOverlap(string $backtestId, mixed $startDate, mixed $endDate, array $processedPeriods): bool
    {
        $start = $this->date($startDate);
        $end = $this->date($endDate);

        if ($start === null || $end === null) {
            return false;
        }

        foreach ($processedPeriods as $period) {
            if ($this->periodsOverlap($start, $end, $this->date($period['start']), $this->date($period['end']))) {
                return true;
            }
        }

        return Mt5ReportFile::query()
            ->where('backtest_id', $backtestId)
            ->whereNotNull('report_start_date')
            ->whereNotNull('report_end_date')
            ->whereDate('report_start_date', '<=', $end->toDateString())
            ->whereDate('report_end_date', '>=', $start->toDateString())
            ->exists();
    }

    private function periodsOverlap(CarbonImmutable $leftStart, CarbonImmutable $leftEnd, ?CarbonImmutable $rightStart, ?CarbonImmutable $rightEnd): bool
    {
        if ($rightStart === null || $rightEnd === null) {
            return false;
        }

        return $leftStart->lte($rightEnd) && $rightStart->lte($leftEnd);
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

    /**
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function fileResult(
        string $fileName,
        mixed $startDate,
        mixed $endDate,
        int $tradesFound,
        int $tradesImported,
        int $duplicates,
        string $status,
        array $warnings,
    ): array {
        return [
            'file_name' => $fileName,
            'report_start_date' => $startDate,
            'report_end_date' => $endDate,
            'trades_found' => $tradesFound,
            'trades_imported' => $tradesImported,
            'duplicates' => $duplicates,
            'status' => $status,
            'warnings' => $warnings,
        ];
    }
}
