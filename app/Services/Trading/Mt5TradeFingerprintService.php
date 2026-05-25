<?php

namespace App\Services\Trading;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class Mt5TradeFingerprintService
{
    /**
     * @param  array<string, mixed>  $trade
     */
    public function fingerprint(int|string $strategyId, string $backtestId, array $trade): string
    {
        return hash('sha256', json_encode([
            'strategy_id' => (string) $strategyId,
            'backtest_id' => $this->string($backtestId),
            'symbol' => $this->symbol($trade['symbol'] ?? $trade['asset'] ?? null),
            'direction' => $this->direction($trade['direction'] ?? null),
            'volume' => $this->decimal($trade['volume'] ?? null, 8),
            'entry_time' => $this->dateTime($trade['entry_time'] ?? null),
            'exit_time' => $this->dateTime($trade['exit_time'] ?? null),
            'entry_price' => $this->decimal($trade['entry_price'] ?? null, 8),
            'exit_price' => $this->decimal($trade['exit_price'] ?? null, 8),
            'net_profit' => $this->decimal($trade['net_profit'] ?? null, 8),
            'entry_deal_id' => $this->string($trade['entry_deal_id'] ?? null),
            'exit_deal_id' => $this->string($trade['exit_deal_id'] ?? null),
            'entry_order_id' => $this->string($trade['entry_order_id'] ?? null),
            'exit_order_id' => $this->string($trade['exit_order_id'] ?? null),
        ], JSON_THROW_ON_ERROR));
    }

    private function symbol(mixed $value): ?string
    {
        $value = $this->string($value);

        return $value === null ? null : Str::of($value)->upper()->toString();
    }

    private function direction(mixed $value): ?string
    {
        $value = $this->string($value);

        if ($value === null) {
            return null;
        }

        return match (Str::of($value)->ascii()->lower()->trim()->toString()) {
            'buy', 'compra' => 'buy',
            'sell', 'venda' => 'sell',
            default => $value,
        };
    }

    private function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimal(mixed $value, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $scale, '.', '');
    }

    private function dateTime(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return trim((string) $value);
        }
    }
}
