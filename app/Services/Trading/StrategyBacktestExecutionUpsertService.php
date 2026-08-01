<?php

namespace App\Services\Trading;

use App\Models\Strategy;
use App\Models\StrategyBacktestExecution;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

class StrategyBacktestExecutionUpsertService
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $executionData
     */
    public function upsertFromImport(
        Strategy $strategy,
        string $backtestId,
        array $metadata = [],
        array $executionData = [],
    ): StrategyBacktestExecution {
        $backtestId = trim($backtestId);
        $execution = StrategyBacktestExecution::query()->firstOrNew([
            'strategy_id' => $strategy->id,
            'backtest_id' => $backtestId,
        ]);

        $startedAt = $this->date($metadata['start_date'] ?? $executionData['started_at'] ?? null);
        $endedAt = $this->date($metadata['end_date'] ?? $executionData['ended_at'] ?? null);

        $execution->fill([
            'name' => $this->firstFilled(
                $executionData['execution_name'] ?? null,
                $executionData['name'] ?? null,
                $execution->name,
                $backtestId,
            ),
            'execution_type' => $this->validExecutionType(
                $this->firstFilled($executionData['execution_type'] ?? null, $execution->execution_type),
            ),
            'strategy_version' => $this->firstFilled(
                $executionData['strategy_version'] ?? null,
                $execution->strategy_version,
            ),
            'asset' => $this->firstFilled(
                $executionData['asset'] ?? null,
                $execution->asset,
                $strategy->asset,
            ),
            'symbol' => $this->firstFilled(
                $metadata['symbol'] ?? null,
                $executionData['symbol'] ?? null,
                $execution->symbol,
            ),
            'timeframe' => $this->firstFilled(
                $executionData['timeframe'] ?? null,
                $metadata['timeframe'] ?? null,
                $execution->timeframe,
            ),
            'started_at' => $this->earliestDate($execution->started_at, $startedAt),
            'ended_at' => $this->latestDate($execution->ended_at, $endedAt),
            'initial_capital' => $this->firstNumeric(
                $executionData['initial_capital'] ?? null,
                $metadata['initial_deposit'] ?? null,
                $execution->initial_capital,
            ),
            'initial_contracts' => $this->firstNumeric(
                $executionData['initial_contracts'] ?? null,
                $execution->initial_contracts,
            ),
            'parameters' => $this->parameters($executionData['parameters'] ?? null, $execution->parameters),
            'costs' => $this->costs($executionData, $execution->costs),
            'slippage' => $this->firstNumeric($executionData['slippage'] ?? null, $execution->slippage),
            'spread' => $this->firstNumeric($executionData['spread'] ?? null, $execution->spread),
            'data_source' => $this->firstFilled(
                $executionData['data_source'] ?? null,
                $execution->data_source,
                'MetaTrader 5',
            ),
            'notes' => $this->firstFilled($executionData['notes'] ?? null, $execution->notes),
            'imported_at' => now(),
        ]);

        if (blank($execution->auto_classification_status)) {
            $execution->auto_classification_status = StrategyBacktestExecution::STATUS_NOT_ANALYZED;
        }

        $execution->save();

        return $execution;
    }

    private function validExecutionType(?string $type): string
    {
        $type = trim((string) $type);

        return array_key_exists($type, StrategyBacktestExecution::executionTypeOptions())
            ? $type
            : StrategyBacktestExecution::TYPE_MAIN_BACKTEST;
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstNumeric(mixed ...$values): ?float
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function earliestDate(mixed $current, ?CarbonImmutable $candidate): mixed
    {
        $currentDate = $this->date($current);

        if ($currentDate === null) {
            return $candidate;
        }

        if ($candidate === null) {
            return $currentDate;
        }

        return $candidate->lt($currentDate) ? $candidate : $currentDate;
    }

    private function latestDate(mixed $current, ?CarbonImmutable $candidate): mixed
    {
        $currentDate = $this->date($current);

        if ($currentDate === null) {
            return $candidate;
        }

        if ($candidate === null) {
            return $currentDate;
        }

        return $candidate->gt($currentDate) ? $candidate : $currentDate;
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>|null
     */
    private function parameters(mixed $value, ?array $current): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return $current;
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [
                'texto' => trim($value),
            ];
        }

        return is_array($decoded) ? $decoded : $current;
    }

    /**
     * @param  array<string, mixed>  $executionData
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>|null
     */
    private function costs(array $executionData, ?array $current): ?array
    {
        $costs = $current ?? [];

        foreach (['commission', 'swap', 'spread', 'slippage'] as $key) {
            if (! Arr::has($executionData, $key) || $executionData[$key] === null || $executionData[$key] === '') {
                continue;
            }

            $costs[$key] = is_numeric($executionData[$key]) ? (float) $executionData[$key] : $executionData[$key];
        }

        if (filled($executionData['costs_description'] ?? null)) {
            $costs['observacoes'] = trim((string) $executionData['costs_description']);
        }

        return $costs === [] ? null : $costs;
    }
}
