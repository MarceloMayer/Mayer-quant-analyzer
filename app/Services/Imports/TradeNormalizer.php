<?php

namespace App\Services\Imports;

use App\Models\Strategy;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TradeNormalizer
{
    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>|null
     */
    public function normalize(Strategy $strategy, array $row): ?array
    {
        $orderId = $this->value($row, [
            'order_id', 'order', 'ordem',
            'ticket',
            'position', 'posicao',
            'deal', 'oferta',
        ]);

        $entryTime = $this->dateTime($this->value($row, [
            'entry_time', 'open_time', 'opening_time',
            'entrada', 'horario_entrada', 'data_entrada',
            'time_in',
        ]));

        $exitTime = $this->dateTime($this->value($row, [
            'exit_time', 'close_time', 'closing_time',
            'closed_at', 'saida', 'horario_saida', 'data_saida',
            'time_out', 'time',
        ]));

        $grossProfit = $this->decimal($this->value($row, [
            'gross_profit', 'profit', 'lucro', 'resultado', 'p_l', 'pl',
        ]));
        $commission = $this->decimal($this->value($row, ['commission', 'comissao', 'comissoes']));
        $swap = $this->decimal($this->value($row, ['swap']));
        $netProfit = $this->decimal($this->value($row, [
            'net_profit', 'netprofit', 'net', 'lucro_liquido', 'resultado_liquido',
        ]));

        if ($netProfit === null) {
            $netProfit = ($grossProfit ?? 0.0) + ($commission ?? 0.0) + ($swap ?? 0.0);
        }

        $asset = $this->value($row, ['asset', 'ativo', 'symbol', 'simbolo']) ?? $strategy->asset;
        $volume = $this->decimal($this->value($row, ['volume', 'vol']));
        $entryPrice = $this->decimal($this->value($row, [
            'entry_price', 'open_price', 'preco_entrada', 'price_in',
        ]));
        $exitPrice = $this->decimal($this->value($row, [
            'exit_price', 'close_price', 'preco_saida', 'price_out', 'price', 'preco',
        ]));
        $direction = $this->direction($this->value($row, [
            'direction', 'direcao', 'type', 'tipo',
        ]));

        if (
            $orderId === null
            && $entryTime === null
            && $exitTime === null
            && $volume === null
            && $entryPrice === null
            && $exitPrice === null
            && $grossProfit === null
            && $commission === null
            && $swap === null
            && $netProfit === 0.0
        ) {
            return null;
        }

        return [
            'strategy_id' => $strategy->id,
            'order_id' => $orderId,
            'asset' => $asset,
            'direction' => $direction,
            'volume' => $volume,
            'entry_time' => $entryTime,
            'exit_time' => $exitTime,
            'entry_price' => $entryPrice,
            'exit_price' => $exitPrice,
            'gross_profit' => $grossProfit,
            'commission' => $commission,
            'swap' => $swap,
            'net_profit' => $netProfit,
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

    private function direction(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)->ascii()->lower()->trim()->toString();

        return match (true) {
            str_contains($normalized, 'buy'), str_contains($normalized, 'compra') => 'buy',
            str_contains($normalized, 'sell'), str_contains($normalized, 'venda') => 'sell',
            $normalized === 'in' => 'in',
            $normalized === 'out' => 'out',
            default => $value,
        };
    }

    private function decimal(?string $value): ?float
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

    private function dateTime(?string $value): ?string
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
}
