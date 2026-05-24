<?php

namespace App\Services\Imports;

use Illuminate\Support\Str;
use RuntimeException;

class CsvTradeParser
{
    private const HEADER_KEYS = [
        'order', 'ticket', 'position', 'order_id',
        'time', 'open_time', 'close_time', 'entry_time', 'exit_time',
        'type', 'direction',
        'volume',
        'price', 'open_price', 'close_price', 'entry_price', 'exit_price',
        'profit', 'net_profit',
        'commission',
        'swap',
    ];

    /**
     * @return array<int, array<string, string|null>>
     */
    public function parse(string $filePath): array
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException('O arquivo CSV não pôde ser lido.');
        }

        $delimiter = $this->detectDelimiter($filePath);
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('O arquivo CSV não pôde ser aberto.');
        }

        $headers = null;
        $rows = [];

        try {
            while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($this->isBlankRow($line)) {
                    continue;
                }

                $normalizedLine = $this->normalizeHeaders($line);

                if ($headers === null) {
                    if (! $this->looksLikeHeader($normalizedLine)) {
                        continue;
                    }

                    $headers = $normalizedLine;

                    continue;
                }

                $rows[] = $this->combineRow($headers, $line);
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function detectDelimiter(string $filePath): string
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $sample = implode("\n", array_slice($lines ?: [], 0, 5));

        $commaCount = substr_count($sample, ',');
        $semicolonCount = substr_count($sample, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        $used = [];

        foreach ($headers as $index => $header) {
            $key = Str::of((string) $header)
                ->replace("\xEF\xBB\xBF", '')
                ->ascii()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->toString();

            $key = $key !== '' ? $key : "column_{$index}";
            $used[$key] = ($used[$key] ?? 0) + 1;

            if ($used[$key] > 1) {
                $key .= '_'.$used[$key];
            }

            $normalized[] = $key;
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function looksLikeHeader(array $headers): bool
    {
        return count(array_intersect($headers, self::HEADER_KEYS)) >= 2;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $line
     * @return array<string, string|null>
     */
    private function combineRow(array $headers, array $line): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = $line[$index] ?? null;
        }

        return $row;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
