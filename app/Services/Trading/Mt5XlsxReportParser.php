<?php

namespace App\Services\Trading;

use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class Mt5XlsxReportParser
{
    private const CONFIG_SECTION = 'configuracao';

    private const RESULTS_SECTION = 'resultados';

    private const ORDERS_SECTION = 'ordens';

    private const TRANSACTIONS_SECTION = 'transacoes';

    /**
     * @return array{
     *     backtest_id: string|null,
     *     sections: array<string, bool>,
     *     metadata: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     orders: array<int, array<string, mixed>>,
     *     transactions: array<int, array<string, mixed>>,
     *     trades: array<int, array<string, mixed>>,
     *     warnings: array<int, string>
     * }
     */
    public function parse(string $filePath, ?string $backtestId = null): array
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException('O arquivo XLSX não pôde ser lido.');
        }

        $backtestId ??= $this->buildBacktestId($filePath);
        $warnings = [];
        $rows = $this->readFirstSheet($filePath);
        $sections = $this->findSections($rows);

        foreach ($this->expectedSections() as $section) {
            if (($sections[$section] ?? null) === null) {
                $warnings[] = "Seção não encontrada: {$this->sectionLabel($section)}.";
            }
        }

        $metadata = $this->parseMetadata(
            $this->sectionRows($rows, $sections, self::CONFIG_SECTION),
        );

        $summary = $this->parseSummary(
            $this->sectionRows($rows, $sections, self::RESULTS_SECTION),
        );

        $orders = $this->parseOrders(
            $rows,
            $sections,
            $warnings,
        );

        $transactions = $this->parseTransactions(
            $rows,
            $sections,
            $warnings,
        );

        $trades = $this->buildClosedTrades(
            $transactions,
            $backtestId,
            $warnings,
        );

        $this->validateNetProfit($summary, $trades, $warnings);

        return [
            'backtest_id' => $backtestId,
            'sections' => [
                self::CONFIG_SECTION => ($sections[self::CONFIG_SECTION] ?? null) !== null,
                self::RESULTS_SECTION => ($sections[self::RESULTS_SECTION] ?? null) !== null,
                self::ORDERS_SECTION => ($sections[self::ORDERS_SECTION] ?? null) !== null,
                self::TRANSACTIONS_SECTION => ($sections[self::TRANSACTIONS_SECTION] ?? null) !== null,
            ],
            'metadata' => $metadata,
            'summary' => $summary,
            'orders' => $orders,
            'transactions' => $transactions,
            'trades' => $trades,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function readFirstSheet(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);

        set_error_handler(function (int $severity, string $message, string $file): bool {
            if ($severity === E_WARNING && str_contains($file, 'PhpSpreadsheet')) {
                return true;
            }

            return false;
        });

        try {
            $spreadsheet = $reader->load($filePath);
        } finally {
            restore_error_handler();
        }

        $sheet = $spreadsheet->getSheet(0);

        try {
            return $this->readRows($sheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $rows = [];

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $row = [];

            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowNumber;
                $cell = $sheet->getCell($coordinate);
                $value = $cell->getCalculatedValue();

                if (ExcelDate::isDateTime($cell) && is_numeric($value)) {
                    $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
                }

                $value = $this->cleanValue($value);

                if ($this->isBlank($value)) {
                    continue;
                }

                $row[$columnIndex] = $value;
            }

            if ($row !== []) {
                ksort($row);
                $rows[$rowNumber] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, int|null>
     */
    private function findSections(array $rows): array
    {
        $sections = array_fill_keys($this->expectedSections(), null);

        foreach ($rows as $rowNumber => $row) {
            $firstColumn = $this->normalizeKey($row[1] ?? null);

            if ($firstColumn === self::CONFIG_SECTION) {
                $sections[self::CONFIG_SECTION] = $rowNumber;
            }

            if ($firstColumn === self::RESULTS_SECTION) {
                $sections[self::RESULTS_SECTION] = $rowNumber;
            }

            if ($firstColumn === self::ORDERS_SECTION) {
                $sections[self::ORDERS_SECTION] = $rowNumber;
            }

            if ($firstColumn === self::TRANSACTIONS_SECTION) {
                $sections[self::TRANSACTIONS_SECTION] = $rowNumber;
            }
        }

        return $sections;
    }

    /**
     * @return array<int, string>
     */
    private function expectedSections(): array
    {
        return [
            self::CONFIG_SECTION,
            self::RESULTS_SECTION,
            self::ORDERS_SECTION,
            self::TRANSACTIONS_SECTION,
        ];
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            self::CONFIG_SECTION => 'Configuração',
            self::RESULTS_SECTION => 'Resultados',
            self::ORDERS_SECTION => 'Ordens',
            self::TRANSACTIONS_SECTION => 'Transações',
            default => $section,
        };
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int|null>  $sections
     * @return array<int, array<int, mixed>>
     */
    private function sectionRows(array $rows, array $sections, string $section): array
    {
        $startRow = $sections[$section] ?? null;

        if ($startRow === null) {
            return [];
        }

        $nextStartRow = $this->nextSectionStartRow($sections, $startRow);
        $sectionRows = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= $startRow) {
                continue;
            }

            if ($nextStartRow !== null && $rowNumber >= $nextStartRow) {
                continue;
            }

            $sectionRows[$rowNumber] = $row;
        }

        return $sectionRows;
    }

    /**
     * @param  array<string, int|null>  $sections
     */
    private function nextSectionStartRow(array $sections, int $currentStartRow): ?int
    {
        $nextRows = array_filter(
            $sections,
            fn (?int $rowNumber): bool => $rowNumber !== null && $rowNumber > $currentStartRow,
        );

        if ($nextRows === []) {
            return null;
        }

        return min($nextRows);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function parseMetadata(array $rows): array
    {
        $metadata = [
            'expert_advisor' => null,
            'symbol' => null,
            'timeframe' => null,
            'start_date' => null,
            'end_date' => null,
            'company' => null,
            'currency' => null,
            'initial_deposit' => null,
            'leverage' => null,
        ];

        foreach ($this->extractLabelPairs($rows) as $pair) {
            $key = $this->metadataKey($pair['label']);

            if ($key === null) {
                continue;
            }

            if ($key === 'period') {
                $period = $this->parsePeriod((string) $pair['value']);
                $metadata['timeframe'] = $period['timeframe'];
                $metadata['start_date'] = $period['start_date'];
                $metadata['end_date'] = $period['end_date'];

                continue;
            }

            $metadata[$key] = $key === 'initial_deposit'
                ? $this->decimal($pair['value'])
                : $this->stringOrNull($pair['value']);
        }

        return $metadata;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function parseSummary(array $rows): array
    {
        $summary = [];

        foreach ($this->extractLabelPairs($rows) as $pair) {
            $key = $this->summaryKey($pair['label']);

            if ($key === null) {
                continue;
            }

            $summary[$key] = $this->summaryValue($key, $pair['value']);
        }

        return $summary;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array{label: string, value: mixed, row: int}>
     */
    private function extractLabelPairs(array $rows): array
    {
        $pairs = [];

        foreach ($rows as $rowNumber => $row) {
            foreach ($row as $columnIndex => $value) {
                if (! is_string($value) || ! str_contains($value, ':')) {
                    continue;
                }

                [$label, $inlineValue] = array_pad(explode(':', $value, 2), 2, null);
                $label = trim($label);
                $value = trim((string) $inlineValue) !== ''
                    ? trim((string) $inlineValue)
                    : $this->firstValueToRight($row, $columnIndex);

                if ($label === '' || $value === null) {
                    continue;
                }

                $pairs[] = [
                    'label' => $label,
                    'value' => $value,
                    'row' => $rowNumber,
                ];
            }
        }

        return $pairs;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function firstValueToRight(array $row, int $columnIndex): mixed
    {
        foreach ($row as $candidateColumn => $candidateValue) {
            if ($candidateColumn <= $columnIndex || $this->isBlank($candidateValue)) {
                continue;
            }

            return $candidateValue;
        }

        return null;
    }

    private function metadataKey(string $label): ?string
    {
        $key = $this->normalizeKey($label);

        return match (true) {
            str_contains($key, 'expert_advisor') || str_contains($key, 'robo') => 'expert_advisor',
            $key === 'ativo' || $key === 'symbol' || $key === 'simbolo' => 'symbol',
            $key === 'periodo' || $key === 'period' => 'period',
            $key === 'empresa' || $key === 'company' => 'company',
            $key === 'moeda' || $key === 'currency' => 'currency',
            $key === 'deposito_inicial' || $key === 'initial_deposit' => 'initial_deposit',
            $key === 'alavancagem' || $key === 'leverage' => 'leverage',
            default => null,
        };
    }

    /**
     * @return array{timeframe: string|null, start_date: string|null, end_date: string|null}
     */
    private function parsePeriod(string $period): array
    {
        $period = trim($period);

        if (preg_match('/^\s*([^\s(]+)\s*\(\s*(\d{4})[.\/-](\d{2})[.\/-](\d{2})\s*-\s*(\d{4})[.\/-](\d{2})[.\/-](\d{2})\s*\)/', $period, $matches) === 1) {
            return [
                'timeframe' => $matches[1],
                'start_date' => "{$matches[2]}-{$matches[3]}-{$matches[4]}",
                'end_date' => "{$matches[5]}-{$matches[6]}-{$matches[7]}",
            ];
        }

        return [
            'timeframe' => $period !== '' ? $period : null,
            'start_date' => null,
            'end_date' => null,
        ];
    }

    private function summaryKey(string $label): ?string
    {
        $key = $this->normalizeKey($label);

        return match (true) {
            str_contains($key, 'qualidade_do_historico') => 'history_quality',
            $key === 'barras' || $key === 'bars' => 'bars',
            $key === 'ticks' => 'ticks',
            $key === 'ativos' || $key === 'symbols' => 'symbols',
            str_contains($key, 'lucro_liquido_total') => 'total_net_profit',
            $key === 'lucro_bruto' || $key === 'gross_profit' => 'gross_profit',
            $key === 'perda_bruta' || $key === 'gross_loss' => 'gross_loss',
            str_contains($key, 'fator_de_lucro') => 'profit_factor',
            str_contains($key, 'retorno_esperado') || str_contains($key, 'payoff') => 'expected_payoff',
            str_contains($key, 'fator_de_recuperacao') => 'recovery_factor',
            str_contains($key, 'indice_de_sharpe') => 'sharpe_ratio',
            str_contains($key, 'total_de_negociacoes') => 'total_trades',
            str_contains($key, 'ofertas_total') => 'total_deals',
            str_contains($key, 'negociacoes_com_lucro') => 'winning_trades',
            str_contains($key, 'negociacoes_com_perda') => 'losing_trades',
            str_contains($key, 'maior_lucro_da_negociacao') => 'largest_profit_trade',
            str_contains($key, 'maior_perda_na_negociacao') => 'largest_loss_trade',
            str_contains($key, 'media_lucro_da_negociacao') => 'average_profit_trade',
            str_contains($key, 'media_perda_na_negociacao') => 'average_loss_trade',
            str_contains($key, 'maximo_ganhos_consecutivos') => 'max_consecutive_wins',
            str_contains($key, 'maximo_perdas_consecutivas') => 'max_consecutive_losses',
            str_contains($key, 'tempo_minimo_de_duracao_da_posicao') => 'min_position_duration',
            str_contains($key, 'tempo_maximo_de_duracao_da_posicao') => 'max_position_duration',
            str_contains($key, 'tempo_medio_de_duracao_da_posicao') => 'average_position_duration',
            default => null,
        };
    }

    private function summaryValue(string $key, mixed $value): mixed
    {
        if (in_array($key, [
            'min_position_duration',
            'max_position_duration',
            'average_position_duration',
        ], true)) {
            return $this->stringOrNull($value);
        }

        return $this->decimal($value);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int|null>  $sections
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function parseOrders(array $rows, array $sections, array &$warnings): array
    {
        $sectionStart = $sections[self::ORDERS_SECTION] ?? null;

        if ($sectionStart === null) {
            return [];
        }

        $requiredHeaders = [
            'horario_da_abertura',
            'ordem',
            'ativo',
            'tipo',
            'volume',
            'preco',
            's_l',
            't_p',
            'horario',
            'estado',
            'comentario',
        ];

        $records = $this->parseTableSection(
            $rows,
            $sections,
            self::ORDERS_SECTION,
            $requiredHeaders,
            $warnings,
            false,
        );

        $orders = [];

        foreach ($records as $record) {
            if ($this->isBlank($record['ordem'] ?? null)) {
                continue;
            }

            $orders[] = [
                'row_number' => $record['_row_number'],
                'open_time' => $this->dateTime($record['horario_da_abertura'] ?? null),
                'order_id' => $this->stringOrNull($record['ordem'] ?? null),
                'symbol' => $this->stringOrNull($record['ativo'] ?? null),
                'type' => $this->tradeType($record['tipo'] ?? null),
                'volume' => $this->decimal($record['volume'] ?? null),
                'price' => $this->decimal($record['preco'] ?? null),
                'stop_loss' => $this->decimal($record['s_l'] ?? null),
                'take_profit' => $this->decimal($record['t_p'] ?? null),
                'time' => $this->dateTime($record['horario'] ?? null),
                'state' => $this->stringOrNull($record['estado'] ?? null),
                'comment' => $this->stringOrNull($record['comentario'] ?? null),
            ];
        }

        return $orders;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int|null>  $sections
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function parseTransactions(array $rows, array $sections, array &$warnings): array
    {
        if (($sections[self::TRANSACTIONS_SECTION] ?? null) === null) {
            return [];
        }

        $requiredHeaders = [
            'horario',
            'oferta',
            'ativo',
            'tipo',
            'direcao',
            'volume',
            'preco',
            'ordem',
            'comissao',
            'swap',
            'lucro',
            'saldo',
            'comentario',
        ];

        $records = $this->parseTableSection(
            $rows,
            $sections,
            self::TRANSACTIONS_SECTION,
            $requiredHeaders,
            $warnings,
            true,
        );

        $transactions = [];

        foreach ($records as $record) {
            $time = $this->dateTime($record['horario'] ?? null);
            $dealId = $this->stringOrNull($record['oferta'] ?? null);
            $type = $this->tradeType($record['tipo'] ?? null);
            $direction = $this->transactionDirection($record['direcao'] ?? null);

            if (
                $time === null
                && $dealId === null
                && $type === null
                && $direction === null
            ) {
                continue;
            }

            if ($time === null || $type === null) {
                $warnings[] = "Transação ignorada na linha {$record['_row_number']}: campos obrigatórios ausentes.";

                continue;
            }

            $transactions[] = [
                'row_number' => $record['_row_number'],
                'time' => $time,
                'deal_id' => $dealId,
                'symbol' => $this->stringOrNull($record['ativo'] ?? null),
                'type' => $type,
                'direction' => $direction,
                'volume' => $this->decimal($record['volume'] ?? null),
                'price' => $this->decimal($record['preco'] ?? null),
                'order_id' => $this->stringOrNull($record['ordem'] ?? null),
                'commission' => $this->decimal($record['comissao'] ?? null) ?? 0.0,
                'swap' => $this->decimal($record['swap'] ?? null) ?? 0.0,
                'profit' => $this->decimal($record['lucro'] ?? null) ?? 0.0,
                'balance' => $this->decimal($record['saldo'] ?? null),
                'comment' => $this->stringOrNull($record['comentario'] ?? null),
            ];
        }

        return $transactions;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int|null>  $sections
     * @param  array<int, string>  $requiredHeaders
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function parseTableSection(
        array $rows,
        array $sections,
        string $section,
        array $requiredHeaders,
        array &$warnings,
        bool $requireAllHeaders,
    ): array {
        $startRow = $sections[$section] ?? null;

        if ($startRow === null) {
            return [];
        }

        $endRow = $this->nextSectionStartRow($sections, $startRow);
        $headerRowNumber = $this->findHeaderRow($rows, $startRow + 1, $endRow, $requiredHeaders, $requireAllHeaders);

        if ($headerRowNumber === null) {
            $warnings[] = "Cabeçalho da seção {$this->sectionLabel($section)} não encontrado.";

            return [];
        }

        $headerMap = $this->buildHeaderMap($rows[$headerRowNumber] ?? []);
        $missingHeaders = array_values(array_diff($requiredHeaders, array_keys($headerMap)));

        if ($missingHeaders !== []) {
            $warnings[] = "Cabeçalho da seção {$this->sectionLabel($section)} incompleto: ".implode(', ', $missingHeaders).'.';

            if ($requireAllHeaders) {
                return [];
            }
        }

        $records = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= $headerRowNumber) {
                continue;
            }

            if ($endRow !== null && $rowNumber >= $endRow) {
                continue;
            }

            $record = ['_row_number' => $rowNumber];
            $hasData = false;

            foreach ($headerMap as $header => $columnIndex) {
                $value = $row[$columnIndex] ?? null;
                $record[$header] = $value;

                if (! $this->isBlank($value)) {
                    $hasData = true;
                }
            }

            if (! $hasData) {
                continue;
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $requiredHeaders
     */
    private function findHeaderRow(array $rows, int $startRow, ?int $endRow, array $requiredHeaders, bool $requireAllHeaders): ?int
    {
        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber < $startRow) {
                continue;
            }

            if ($endRow !== null && $rowNumber >= $endRow) {
                continue;
            }

            $headerMap = $this->buildHeaderMap($row);
            $matches = count(array_intersect($requiredHeaders, array_keys($headerMap)));

            if ($requireAllHeaders && $matches === count($requiredHeaders)) {
                return $rowNumber;
            }

            if (! $requireAllHeaders && $matches >= 5) {
                return $rowNumber;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, int>
     */
    private function buildHeaderMap(array $row): array
    {
        $map = [];

        foreach ($row as $columnIndex => $header) {
            $key = $this->normalizeKey($header);

            if ($key === '') {
                continue;
            }

            $map[$key] = $columnIndex;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $transactions
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function buildClosedTrades(array $transactions, ?string $backtestId, array &$warnings): array
    {
        $orderedTransactions = array_values(array_filter(
            $transactions,
            fn (array $transaction): bool => ($transaction['type'] ?? null) !== 'balance',
        ));

        usort($orderedTransactions, function (array $left, array $right): int {
            return [$left['time'] ?? '', $left['row_number'] ?? 0] <=> [$right['time'] ?? '', $right['row_number'] ?? 0];
        });

        $openPositions = [];
        $trades = [];

        foreach ($orderedTransactions as $transaction) {
            $direction = $transaction['direction'] ?? null;

            if ($direction === 'in') {
                if (! in_array($transaction['type'] ?? null, ['buy', 'sell'], true)) {
                    $warnings[] = "Entrada ignorada na linha {$transaction['row_number']}: tipo inválido.";

                    continue;
                }

                $openPositions[] = $transaction;

                continue;
            }

            if ($direction !== 'out') {
                $warnings[] = "Transação ignorada na linha {$transaction['row_number']}: direção inválida.";

                continue;
            }

            $entryIndex = $this->findMatchingEntry($openPositions, $transaction);

            if ($entryIndex === null) {
                $warnings[] = "Saída sem entrada correspondente na linha {$transaction['row_number']}.";

                continue;
            }

            $entry = $openPositions[$entryIndex];
            array_splice($openPositions, $entryIndex, 1);

            $trades[] = $this->buildTrade($entry, $transaction, $backtestId);
        }

        foreach ($openPositions as $position) {
            $warnings[] = "Operação sem fechamento ignorada: linha {$position['row_number']}, oferta {$position['deal_id']}.";
        }

        return $trades;
    }

    /**
     * @param  array<int, array<string, mixed>>  $openPositions
     */
    private function findMatchingEntry(array $openPositions, array $exit): ?int
    {
        foreach ($openPositions as $index => $entry) {
            if (! $this->sameSymbol($entry['symbol'] ?? null, $exit['symbol'] ?? null)) {
                continue;
            }

            if (! $this->sameVolume($entry['volume'] ?? null, $exit['volume'] ?? null)) {
                continue;
            }

            if (! $this->isOppositeTradeType($entry['type'] ?? null, $exit['type'] ?? null)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function buildTrade(array $entry, array $exit, ?string $backtestId): array
    {
        $grossProfit = (float) ($exit['profit'] ?? 0.0);
        $commission = (float) ($exit['commission'] ?? 0.0);
        $swap = (float) ($exit['swap'] ?? 0.0);
        $netProfit = round($grossProfit + $commission + $swap, 8);
        $symbol = $entry['symbol'] ?? $exit['symbol'] ?? null;

        return [
            'backtest_id' => $backtestId,
            'order_id' => $exit['order_id'] ?? null,
            'entry_deal_id' => $entry['deal_id'] ?? null,
            'entry_order_id' => $entry['order_id'] ?? null,
            'exit_deal_id' => $exit['deal_id'] ?? null,
            'exit_order_id' => $exit['order_id'] ?? null,
            'asset' => $symbol,
            'symbol' => $symbol,
            'direction' => $entry['type'] ?? null,
            'volume' => $entry['volume'] ?? null,
            'entry_time' => $entry['time'] ?? null,
            'exit_time' => $exit['time'] ?? null,
            'entry_price' => $entry['price'] ?? null,
            'exit_price' => $exit['price'] ?? null,
            'gross_profit' => $grossProfit,
            'commission' => $commission,
            'swap' => $swap,
            'net_profit' => $netProfit,
            'balance_after_trade' => $exit['balance'] ?? null,
            'comment' => $exit['comment'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $trades
     * @param  array<int, string>  $warnings
     */
    private function validateNetProfit(array $summary, array $trades, array &$warnings): void
    {
        $reportedNetProfit = $summary['total_net_profit'] ?? null;

        if ($reportedNetProfit === null) {
            $warnings[] = 'Lucro Líquido Total não encontrado na seção Resultados.';

            return;
        }

        $calculatedNetProfit = round(array_sum(array_map(
            fn (array $trade): float => (float) ($trade['net_profit'] ?? 0.0),
            $trades,
        )), 8);

        $difference = round(((float) $reportedNetProfit) - $calculatedNetProfit, 8);

        if (abs($difference) > 0.05) {
            $warnings[] = 'Divergência entre Lucro Líquido Total do relatório e soma dos trades: '
                .'relatório='.number_format((float) $reportedNetProfit, 2, '.', '')
                .', calculado='.number_format($calculatedNetProfit, 2, '.', '')
                .', diferença='.number_format($difference, 2, '.', '').'.';
        }
    }

    private function sameSymbol(mixed $left, mixed $right): bool
    {
        $left = Str::of((string) $left)->ascii()->lower()->trim()->toString();
        $right = Str::of((string) $right)->ascii()->lower()->trim()->toString();

        return $left !== '' && $left === $right;
    }

    private function sameVolume(mixed $left, mixed $right): bool
    {
        $left = $this->decimal($left);
        $right = $this->decimal($right);

        if ($left === null || $right === null) {
            return false;
        }

        return abs($left - $right) < 0.00000001;
    }

    private function isOppositeTradeType(mixed $entryType, mixed $exitType): bool
    {
        return ($entryType === 'buy' && $exitType === 'sell')
            || ($entryType === 'sell' && $exitType === 'buy');
    }

    private function tradeType(mixed $value): ?string
    {
        $value = $this->normalizeKey($value);

        return match (true) {
            $value === 'buy' || $value === 'compra' => 'buy',
            $value === 'sell' || $value === 'venda' => 'sell',
            $value === 'balance' || $value === 'saldo' => 'balance',
            default => $value !== '' ? $value : null,
        };
    }

    private function transactionDirection(mixed $value): ?string
    {
        $value = $this->normalizeKey($value);

        return match ($value) {
            'in', 'entrada' => 'in',
            'out', 'saida' => 'out',
            default => $value !== '' ? $value : null,
        };
    }

    private function dateTime(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\.\d{3,6}$/', '', $value) ?? $value);

        foreach ($this->dateFormats() as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }

            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function dateFormats(): array
    {
        return [
            'Y.m.d H:i:s',
            'Y.m.d H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y/m/d H:i:s',
            'Y/m/d H:i',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
        ];
    }

    private function decimal(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        $negativeByParentheses = str_contains($value, '(') && str_contains($value, ')') && ! str_contains($value, '-');

        if (preg_match('/[-+]?\d[\d\s.,]*/', $value, $matches) !== 1) {
            return null;
        }

        $clean = str_replace(["\xc2\xa0", ' '], '', $matches[0]);
        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $clean = str_replace($thousandSeparator, '', $clean);
            $clean = str_replace($decimalSeparator, '.', $clean);
        } elseif ($lastComma !== false) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            $parts = explode('.', $clean);

            if (count($parts) > 2) {
                $decimal = array_pop($parts);
                $clean = implode('', $parts).'.'.$decimal;
            }
        }

        if ($clean === '' || $clean === '-' || $clean === '+') {
            return null;
        }

        $number = (float) $clean;

        return $negativeByParentheses ? abs($number) * -1 : $number;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = $this->cleanValue($value);

        if ($this->isBlank($value)) {
            return null;
        }

        return (string) $value;
    }

    private function cleanValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim(str_replace("\xc2\xa0", ' ', $value));
        }

        return $value;
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    private function normalizeKey(mixed $value): string
    {
        return Str::of((string) $this->cleanValue($value))
            ->replace("\xEF\xBB\xBF", '')
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function buildBacktestId(string $filePath): ?string
    {
        $hash = sha1_file($filePath);

        return $hash !== false ? $hash : null;
    }
}
