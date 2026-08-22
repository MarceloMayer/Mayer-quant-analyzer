<?php

namespace App\Filament\Resources\Trades\Tables;

use App\Models\Strategy;
use App\Models\Trade;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('strategy.name')
                    ->label('Estratégia')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('asset')
                    ->label('Ativo')
                    ->formatStateUsing(fn (?string $state): ?string => Strategy::assetOptions()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('direction')
                    ->label('Direção')
                    ->formatStateUsing(fn (?string $state): ?string => self::formatDirection($state))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('volume')
                    ->label('Volume')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('entry_time')
                    ->label('Entrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('exit_time')
                    ->label('Saída')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('entry_price')
                    ->label('Preço entrada')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('exit_price')
                    ->label('Preço saída')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('net_profit')
                    ->label('Resultado líquido')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn (string|float|int|null $state): ?string => match (true) {
                        (float) $state > 0 => 'success',
                        (float) $state < 0 => 'danger',
                        default => null,
                    })
                    ->sortable(),
                TextColumn::make('exit_date')
                    ->label('Data de saída')
                    ->state(fn (Trade $record): mixed => $record->exit_time)
                    ->date('d/m/Y'),
            ])
            ->filters([
                SelectFilter::make('strategy_id')
                    ->label('Estratégia')
                    ->relationship(
                        'strategy',
                        'name',
                        fn (Builder $query): Builder => $query->where('user_id', auth()->id()),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('asset')
                    ->label('Ativo')
                    ->options(fn (): array => self::assetFilterOptions())
                    ->searchable(),
                SelectFilter::make('direction')
                    ->label('Direção')
                    ->options(fn (): array => self::directionFilterOptions())
                    ->searchable(),
                Filter::make('exit_time_period')
                    ->label('Período por data de saída')
                    ->schema([
                        DatePicker::make('from')
                            ->label('De'),
                        DatePicker::make('until')
                            ->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('exit_time', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('exit_time', '<=', $date),
                        )),
                Filter::make('positive_result')
                    ->label('Resultado positivo')
                    ->query(fn (Builder $query): Builder => $query->where('net_profit', '>', 0)),
                Filter::make('negative_result')
                    ->label('Resultado negativo')
                    ->query(fn (Builder $query): Builder => $query->where('net_profit', '<', 0)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function assetFilterOptions(): array
    {
        return self::ownedTradesQuery()
            ->whereNotNull('asset')
            ->distinct()
            ->orderBy('asset')
            ->pluck('asset', 'asset')
            ->map(fn (string $label, string $value): string => Strategy::assetOptions()[$value] ?? $label)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function directionFilterOptions(): array
    {
        return self::ownedTradesQuery()
            ->whereNotNull('direction')
            ->distinct()
            ->orderBy('direction')
            ->pluck('direction', 'direction')
            ->map(fn (string $label): string => self::formatDirection($label) ?? $label)
            ->all();
    }

    private static function ownedTradesQuery(): Builder
    {
        return Trade::query()
            ->whereHas('strategy', fn (Builder $query): Builder => $query->where('user_id', auth()->id()));
    }

    private static function formatDirection(?string $direction): ?string
    {
        return match ($direction) {
            'buy' => 'Compra',
            'sell' => 'Venda',
            default => $direction,
        };
    }
}
