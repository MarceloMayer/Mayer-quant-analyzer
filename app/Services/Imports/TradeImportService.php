<?php

namespace App\Services\Imports;

use App\Models\Strategy;
use App\Models\StrategyImport;
use App\Models\Trade;
use App\Services\Trading\Mt5XlsxReportParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TradeImportService
{
    public function __construct(
        private readonly CsvTradeParser $parser,
        private readonly TradeNormalizer $normalizer,
        private readonly Mt5XlsxReportParser $xlsxReportParser,
    ) {}

    /**
     * @return array{total_rows: int, imported_rows: int, ignored_rows: int, warnings: array<int, string>}
     */
    public function import(Strategy $strategy, UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $fileName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);

        if ($this->isXlsx((string) $fileName, (string) $filePath)) {
            return $this->importXlsx($strategy, (string) $filePath, (string) $fileName);
        }

        return $this->importCsv($strategy, (string) $filePath, (string) $fileName);
    }

    /**
     * @return array{total_rows: int, imported_rows: int, ignored_rows: int, warnings: array<int, string>}
     */
    private function importCsv(Strategy $strategy, string $filePath, string $fileName): array
    {
        $rows = $this->parser->parse((string) $filePath);
        $totalRows = count($rows);
        $importedRows = 0;
        $ignoredRows = 0;

        DB::transaction(function () use ($strategy, $rows, $fileName, &$importedRows, &$ignoredRows): void {
            foreach ($rows as $row) {
                $trade = $this->normalizer->normalize($strategy, $row);

                if ($trade === null) {
                    $ignoredRows++;

                    continue;
                }

                if ($this->isDuplicate($strategy, $trade)) {
                    $ignoredRows++;

                    continue;
                }

                Trade::query()->create($trade);
                $importedRows++;
            }

            StrategyImport::query()->create([
                'strategy_id' => $strategy->id,
                'file_name' => $fileName,
                'total_rows' => count($rows),
                'imported_rows' => $importedRows,
                'ignored_rows' => $ignoredRows,
                'imported_at' => now(),
            ]);
        });

        return [
            'total_rows' => $totalRows,
            'imported_rows' => $importedRows,
            'ignored_rows' => $ignoredRows,
            'warnings' => [],
        ];
    }

    /**
     * @return array{total_rows: int, imported_rows: int, ignored_rows: int, warnings: array<int, string>}
     */
    private function importXlsx(Strategy $strategy, string $filePath, string $fileName): array
    {
        $report = $this->xlsxReportParser->parse($filePath);
        $trades = $report['trades'] ?? [];
        $warnings = $report['warnings'] ?? [];
        $totalRows = count($report['transactions'] ?? []);
        $importedRows = 0;
        $ignoredRows = max(0, $totalRows - (count($trades) * 2));

        foreach ($warnings as $warning) {
            Log::warning('Aviso na importação de relatório XLSX do MT5.', [
                'strategy_id' => $strategy->id,
                'file_name' => $fileName,
                'warning' => $warning,
            ]);
        }

        DB::transaction(function () use ($strategy, $trades, $fileName, $totalRows, &$importedRows, &$ignoredRows): void {
            foreach ($trades as $trade) {
                $trade['strategy_id'] = $strategy->id;
                $trade['asset'] = $trade['asset'] ?? $trade['symbol'] ?? $strategy->asset;

                if ($this->isDuplicate($strategy, $trade)) {
                    $ignoredRows++;

                    continue;
                }

                Trade::query()->create($trade);
                $importedRows++;
            }

            StrategyImport::query()->create([
                'strategy_id' => $strategy->id,
                'file_name' => $fileName,
                'total_rows' => $totalRows,
                'imported_rows' => $importedRows,
                'ignored_rows' => $ignoredRows,
                'imported_at' => now(),
            ]);
        });

        return [
            'total_rows' => $totalRows,
            'imported_rows' => $importedRows,
            'ignored_rows' => $ignoredRows,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $trade
     */
    private function isDuplicate(Strategy $strategy, array $trade): bool
    {
        if (! blank($trade['backtest_id'] ?? null) && ! blank($trade['exit_deal_id'] ?? null)) {
            return Trade::query()
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
}
