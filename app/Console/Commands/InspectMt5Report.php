<?php

namespace App\Console\Commands;

use App\Services\Trading\Mt5XlsxReportParser;
use Illuminate\Console\Command;

class InspectMt5Report extends Command
{
    protected $signature = 'mt5:inspect-report {path : Caminho do arquivo XLSX exportado pelo MT5}';

    protected $description = 'Inspeciona um relatório XLSX do MetaTrader 5 Strategy Tester.';

    public function handle(Mt5XlsxReportParser $parser): int
    {
        $path = $this->argument('path');

        if (! is_string($path) || ! is_readable($path)) {
            $this->error('Arquivo não encontrado ou sem permissão de leitura.');

            return self::FAILURE;
        }

        $report = $parser->parse($path);
        $summary = $report['summary'] ?? [];
        $metadata = $report['metadata'] ?? [];
        $sections = $report['sections'] ?? [];
        $trades = $report['trades'] ?? [];
        $warnings = $report['warnings'] ?? [];

        $reportedNetProfit = $summary['total_net_profit'] ?? null;
        $calculatedNetProfit = round(array_sum(array_map(
            fn (array $trade): float => (float) ($trade['net_profit'] ?? 0.0),
            $trades,
        )), 8);
        $difference = $reportedNetProfit === null
            ? null
            : round(((float) $reportedNetProfit) - $calculatedNetProfit, 8);

        $this->line('Configuração encontrada: '.$this->yesNo($sections['configuracao'] ?? false));
        $this->line('Resultados encontrados: '.$this->yesNo($sections['resultados'] ?? false));
        $this->line('Ordens encontradas: '.$this->yesNo($sections['ordens'] ?? false));
        $this->line('Transações encontradas: '.$this->yesNo($sections['transacoes'] ?? false));
        $this->newLine();
        $this->line('Quantidade de ordens: '.count($report['orders'] ?? []));
        $this->line('Quantidade de transações: '.count($report['transactions'] ?? []));
        $this->line('Quantidade de trades fechados: '.count($trades));
        $this->newLine();
        $this->line('Depósito inicial: '.$this->formatNumber($metadata['initial_deposit'] ?? null));
        $this->line('Lucro líquido total informado: '.$this->formatNumber($reportedNetProfit));
        $this->line('Soma dos net_profit dos trades: '.$this->formatNumber($calculatedNetProfit));
        $this->line('Diferença: '.$this->formatNumber($difference));
        $this->newLine();

        if ($warnings === []) {
            $this->line('Warnings: nenhum.');

            return self::SUCCESS;
        }

        $this->line('Warnings:');

        foreach ($warnings as $warning) {
            $this->line('- '.$warning);
        }

        return self::SUCCESS;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'sim' : 'não';
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        return number_format((float) $value, 2, ',', '.');
    }
}
