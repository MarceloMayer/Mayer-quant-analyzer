<?php

namespace App\Filament\Widgets;

use App\Models\Strategy;
use Carbon\CarbonImmutable;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

class StrategyTradesTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $trades = [];

    public string $tableHeading = 'Trades da estratégia';

    public string $tableDescription = 'Operações fechadas importadas para esta estratégia.';

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->tableHeading)
            ->description($this->tableDescription)
            ->records(fn (?string $sortColumn, ?string $sortDirection): Collection => $this->records($sortColumn, $sortDirection))
            ->columns([
                TextColumn::make('exit_time')
                    ->label('Saída')
                    ->formatStateUsing(fn (mixed $state): string => $this->formatDateTime($state))
                    ->sortable(),

                TextColumn::make('asset')
                    ->label('Ativo')
                    ->formatStateUsing(fn (?string $state): string => Strategy::assetOptions()[$state] ?? $state ?? '-')
                    ->sortable(),

                TextColumn::make('direction')
                    ->label('Direção')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'buy' => 'Compra',
                        'sell' => 'Venda',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'buy' => 'success',
                        'sell' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('volume')
                    ->label('Volume')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => $this->formatNumber($state))
                    ->sortable(),

                TextColumn::make('entry_time')
                    ->label('Entrada')
                    ->formatStateUsing(fn (mixed $state): string => $this->formatDateTime($state))
                    ->sortable(),

                TextColumn::make('entry_price')
                    ->label('Preço entrada')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => $this->formatNumber($state))
                    ->sortable(),

                TextColumn::make('exit_price')
                    ->label('Preço saída')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => $this->formatNumber($state))
                    ->sortable(),

                TextColumn::make('net_profit')
                    ->label('Resultado líquido')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => $this->formatSignedMoney($state))
                    ->color(fn (mixed $state): string => $this->moneyColor($state))
                    ->sortable(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    private function records(?string $sortColumn, ?string $sortDirection): Collection
    {
        $records = collect($this->trades)
            ->values()
            ->mapWithKeys(fn (array $trade, int $index): array => [
                (string) ($trade['id'] ?? $index) => $trade,
            ]);

        if (filled($sortColumn)) {
            $records = $records->sortBy(
                fn (array $record): mixed => $record[$sortColumn] ?? null,
                SORT_REGULAR,
                $sortDirection === 'desc',
            );
        }

        return $records;
    }

    private function formatDateTime(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        try {
            return CarbonImmutable::parse((string) $value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return '-';
        }
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    private function formatSignedMoney(mixed $value): string
    {
        $value = (float) $value;

        return ($value > 0 ? '+' : ($value < 0 ? '-' : '')).'R$ '.number_format(abs($value), 2, ',', '.');
    }

    private function moneyColor(mixed $value): string
    {
        return match (true) {
            (float) $value > 0 => 'success',
            (float) $value < 0 => 'danger',
            default => 'gray',
        };
    }
}
