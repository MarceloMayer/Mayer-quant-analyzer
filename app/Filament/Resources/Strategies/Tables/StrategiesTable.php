<?php

namespace App\Filament\Resources\Strategies\Tables;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Filament\Pages\StrategyComparison;
use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Strategy;
use App\Services\Metrics\StrategyMetricsService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class StrategiesTable
{
    /**
     * Memoizes metrics within a single table render so the three metric columns share one
     * calculation per row instead of recomputing the equity curve/drawdown three times.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $metricsCache = [];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('is_favorite')->orderBy('name'))
            ->columns([
                IconColumn::make('is_favorite')
                    ->label('Favorito')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedStar)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->sortable()
                    ->tooltip(fn (Strategy $record): string => $record->is_favorite
                        ? 'Remover dos favoritos'
                        : 'Marcar como favorita')
                    ->action(function (Strategy $record): void {
                        $record->update(['is_favorite' => ! $record->is_favorite]);
                    }),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('asset')
                    ->label('Ativo')
                    ->formatStateUsing(fn (?string $state): ?string => Strategy::assetOptions()[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('net_profit')
                    ->label('Resultado líquido')
                    ->state(fn (Strategy $record): float => (float) (self::metricsFor($record)['net_profit'] ?? 0))
                    ->formatStateUsing(fn (float $state): string => self::formatSignedMoney($state))
                    ->color(fn (float $state): string => self::moneyColor($state))
                    ->alignEnd(),
                TextColumn::make('recovery_factor')
                    ->label('Fator de recuperação')
                    ->state(fn (Strategy $record): ?float => self::metricsFor($record)['profit_drawdown_ratio'] ?? null)
                    ->formatStateUsing(fn (?float $state): string => $state === null ? 'Sem drawdown' : number_format($state, 2, ',', '.'))
                    ->color(fn (?float $state): string => self::ratioColor($state))
                    ->alignEnd(),
                TextColumn::make('max_drawdown')
                    ->label('Drawdown máximo')
                    ->state(fn (Strategy $record): float => (float) (self::metricsFor($record)['max_drawdown'] ?? 0))
                    ->formatStateUsing(fn (float $state): string => self::formatNegativeMoney($state))
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'gray')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('asset')
                    ->label('Ativo')
                    ->options(Strategy::assetOptions()),
                Filter::make('is_favorite')
                    ->label('Somente favoritas')
                    ->query(fn (Builder $query): Builder => $query->where('is_favorite', true))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('viewResults')
                    ->label('Ver resultados')
                    ->icon(Heroicon::OutlinedChartBarSquare)
                    ->iconButton()
                    ->url(fn (Strategy $record): string => StrategyResource::getUrl('results', ['record' => $record])),
                Action::make('compare')
                    ->label('Comparar com...')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->iconButton()
                    ->url(fn (Strategy $record): string => StrategyComparison::getUrl(['a' => $record->getKey()])),
                ImportMt5CsvAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkAction::make('compareSelected')
                    ->label('Comparar estratégias')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('primary')
                    ->accessSelectedRecords()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, Component $livewire): void {
                        if ($records->count() !== 2) {
                            Notification::make()
                                ->title('Selecione exatamente 2 estratégias para comparar.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $ids = $records->pluck('id')->values();

                        $livewire->redirect(
                            StrategyComparison::getUrl(['a' => $ids[0], 'b' => $ids[1]]),
                            navigate: false,
                        );
                    }),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function metricsFor(Strategy $strategy): array
    {
        return self::$metricsCache[$strategy->getKey()] ??= app(StrategyMetricsService::class)->calculate($strategy);
    }

    private static function formatSignedMoney(float $value): string
    {
        return ($value > 0 ? '+' : ($value < 0 ? '-' : '')).'R$ '.number_format(abs($value), 2, ',', '.');
    }

    private static function formatNegativeMoney(float $value): string
    {
        return $value > 0
            ? '-R$ '.number_format($value, 2, ',', '.')
            : 'R$ '.number_format(0, 2, ',', '.');
    }

    private static function moneyColor(float $value): string
    {
        return match (true) {
            $value > 0 => 'success',
            $value < 0 => 'danger',
            default => 'gray',
        };
    }

    private static function ratioColor(?float $value): string
    {
        if ($value === null) {
            return 'gray';
        }

        return $value >= 1 ? 'success' : 'danger';
    }
}
