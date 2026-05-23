<?php

namespace App\Services\Trading;

use App\Models\Strategy;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class Mt5CsvImportService
{
    /**
     * @return array{imported: int, skipped: int}
     */
    public function import(Strategy $strategy, string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('O arquivo CSV não pôde ser lido.');
        }

        $delimiter = $this->detectDelimiter($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('O arquivo CSV não pôde ser aberto.');
        }

        $headers = null;
        $imported = 0;
        $skipped = 0;
        $rows = [];

        DB::transaction(function () use ($strategy, $handle, $delimiter, &$headers, &$imported, &$skipped, &$rows): void {
            $strategy->trades()->delete();

            while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($this->isBlankRow($line)) {
                    continue;
                }

                if ($headers === null) {
                    $candidateHeaders = $this->normalizeHeaders($line);

                    if (! $this->looksLikeHeader($candidateHeaders)) {
                        continue;
                    }

                    $headers = $candidateHeaders;

                    continue;
                }

                $record = $this->combineRow($headers, $line);
                $trade = $this->mapTrade($strategy, $record);

                if ($trade === null) {
                    $skipped++;

                    continue;
                }

                $rows[] = $trade;

                if (count($rows) >= 500) {
                    Trade::insert($rows);
                    $imported += count($rows);
                    $rows = [];
                }
            }

            if ($headers === null) {
                throw new RuntimeException('O cabeçalho do CSV do MT5 não foi identificado.');
            }

            if ($rows !== []) {
                Trade::insert($rows);
                $imported += count($rows);
            }
        });

        fclose($handle);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    private function detectDelimiter(string $path): string
    {
        $sample = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $firstLine = $sample[0] ?? '';
        $candidates = [',' => 0, ';' => 0, "\t" => 0];

        foreach ($candidates as $delimiter => $count) {
            $candidates[$delimiter] = substr_count($firstLine, $delimiter);
        }

        arsort($candidates);

        return array_key_first($candidates) ?: ',';
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
        return $this->hasAny($headers, ['profit', 'lucro', 'resultado', 'p_l', 'pl'])
            && $this->hasAny($headers, ['time', 'time_2', 'date', 'data', 'hora', 'close_time', 'closed_at']);
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, string>  $needles
     */
    private function hasAny(array $values, array $needles): bool
    {
        return array_intersect($needles, $values) !== [];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $line
     * @return array<string, string|null>
     */
    private function combineRow(array $headers, array $line): array
    {
        $record = [];

        foreach ($headers as $index => $header) {
            $record[$header] = $line[$index] ?? null;
        }

        return $record;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>|null
     */
    private function mapTrade(Strategy $strategy, array $row): ?array
    {
        $type = $this->value($row, ['type', 'tipo', 'direction', 'direcao']);

        if ($this->isBalanceOperation($type)) {
            return null;
        }

        $profit = $this->parseDecimal($this->value($row, ['profit', 'lucro', 'resultado', 'p_l', 'pl', 'profit_loss', 'net_profit']));
        $closedAt = $this->parseDate($this->value($row, ['close_time', 'closing_time', 'closed_at', 'time_2', 'date_2', 'data_2', 'hora_2', 'time', 'date', 'data', 'hora']));

        if ($profit === null || $closedAt === null) {
            return null;
        }

        $now = now();

        return [
            'strategy_id' => $strategy->id,
            'ticket' => $this->value($row, ['deal', 'ticket', 'order', 'ordem', 'negocio', 'position', 'posicao']),
            'symbol' => $this->value($row, ['symbol', 'simbolo', 'ativo']),
            'type' => $type,
            'volume' => $this->parseDecimal($this->value($row, ['volume', 'vol'])) ?? 0,
            'price' => $this->parseDecimal($this->value($row, ['price_2', 'preco_2', 'price', 'preco'])),
            'commission' => $this->parseDecimal($this->value($row, ['commission', 'comissao', 'comissoes'])) ?? 0,
            'swap' => $this->parseDecimal($this->value($row, ['swap'])) ?? 0,
            'profit' => $profit,
            'balance' => $this->parseDecimal($this->value($row, ['balance', 'saldo'])),
            'closed_at' => $closedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<int, string>  $keys
     */
    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $negative = str_contains($value, '(') && str_contains($value, ')');
        $clean = preg_replace('/[^\d,\.\-+]/', '', $value);

        if ($clean === null || $clean === '' || $clean === '-' || $clean === '+') {
            return null;
        }

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

        $number = (float) $clean;

        return $negative ? abs($number) * -1 : $number;
    }

    private function parseDate(?string $value): ?string
    {
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
            'm/d/Y H:i:s',
            'm/d/Y H:i',
        ];
    }

    private function isBalanceOperation(?string $type): bool
    {
        if ($type === null) {
            return false;
        }

        $normalized = Str::of($type)->ascii()->lower()->trim()->toString();

        return in_array($normalized, [
            'balance',
            'balanco',
            'saldo',
            'deposit',
            'deposito',
            'withdrawal',
            'saque',
            'credit',
            'credito',
        ], true);
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
