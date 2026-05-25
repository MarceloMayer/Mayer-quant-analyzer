<?php

namespace App\Console\Commands;

use App\Services\Trading\Mt5TradeFingerprintService;
use App\Services\Trading\Mt5XlsxReportParser;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class InspectMultipleMt5Reports extends Command
{
    protected $signature = 'mt5:inspect-multiple-reports {paths* : Caminhos dos arquivos XLSX exportados pelo MT5}';

    protected $description = 'Inspeciona múltiplos relatórios XLSX do MetaTrader 5 e aponta sobreposições e duplicidades.';

    public function handle(Mt5XlsxReportParser $parser, Mt5TradeFingerprintService $fingerprints): int
    {
        $paths = $this->argument('paths');
        $reports = [];
        $fingerprintFiles = [];

        foreach ($paths as $path) {
            if (! is_string($path) || ! is_readable($path)) {
                $this->error("Arquivo não encontrado ou sem permissão de leitura: {$path}");

                return self::FAILURE;
            }

            $report = $parser->parse($path, 'diagnostic');
            $metadata = $report['metadata'] ?? [];
            $trades = $report['trades'] ?? [];
            $fileName = basename($path);
            $duplicateTrades = 0;

            foreach ($trades as $trade) {
                $fingerprint = $fingerprints->fingerprint('diagnostic', 'diagnostic', $trade);

                if (isset($fingerprintFiles[$fingerprint])) {
                    $duplicateTrades++;
                }

                $fingerprintFiles[$fingerprint][] = $fileName;
            }

            $reports[] = [
                'file' => $fileName,
                'start' => $metadata['start_date'] ?? null,
                'end' => $metadata['end_date'] ?? null,
                'transactions' => count($report['transactions'] ?? []),
                'trades' => count($trades),
                'duplicate_trades' => $duplicateTrades,
                'warnings' => $report['warnings'] ?? [],
            ];
        }

        $this->table(
            ['Arquivo', 'Início', 'Fim', 'Transações', 'Trades fechados', 'Duplicados em arquivos anteriores'],
            collect($reports)->map(fn (array $report): array => [
                $report['file'],
                $report['start'] ?? '-',
                $report['end'] ?? '-',
                $report['transactions'],
                $report['trades'],
                $report['duplicate_trades'],
            ])->all(),
        );

        $overlaps = $this->periodOverlaps($reports);

        if ($overlaps === []) {
            $this->line('Sobreposição de períodos: nenhuma.');
        } else {
            $this->line('Sobreposição de períodos:');

            foreach ($overlaps as $overlap) {
                $this->line("- {$overlap}");
            }
        }

        $duplicateFingerprints = collect($fingerprintFiles)
            ->filter(fn (array $files): bool => count($files) > 1)
            ->count();

        $this->newLine();
        $this->line('Trades fechados totais: '.array_sum(array_column($reports, 'trades')));
        $this->line('Possíveis trades duplicados entre arquivos: '.$duplicateFingerprints);
        $this->line('Total consolidado sem duplicidades: '.(count($fingerprintFiles)));

        $warnings = collect($reports)
            ->flatMap(fn (array $report): array => array_map(
                fn (string $warning): string => "{$report['file']}: {$warning}",
                $report['warnings'],
            ))
            ->values()
            ->all();

        if ($warnings !== []) {
            $this->newLine();
            $this->line('Warnings:');

            foreach ($warnings as $warning) {
                $this->line("- {$warning}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<int, string>
     */
    private function periodOverlaps(array $reports): array
    {
        $overlaps = [];

        foreach ($reports as $leftIndex => $left) {
            $leftStart = $this->date($left['start'] ?? null);
            $leftEnd = $this->date($left['end'] ?? null);

            if ($leftStart === null || $leftEnd === null) {
                continue;
            }

            foreach (array_slice($reports, $leftIndex + 1) as $right) {
                $rightStart = $this->date($right['start'] ?? null);
                $rightEnd = $this->date($right['end'] ?? null);

                if ($rightStart === null || $rightEnd === null) {
                    continue;
                }

                if ($leftStart->lte($rightEnd) && $rightStart->lte($leftEnd)) {
                    $overlaps[] = "{$left['file']} ({$left['start']} - {$left['end']}) sobrepõe {$right['file']} ({$right['start']} - {$right['end']})";
                }
            }
        }

        return $overlaps;
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
