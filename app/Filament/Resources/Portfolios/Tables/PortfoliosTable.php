<?php

namespace App\Filament\Resources\Portfolios\Tables;

use App\Filament\Pages\PortfolioComparison;
use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use App\Services\Metrics\PortfolioAnalyzerService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class PortfoliosTable
{
    /**
     * Memoizes metrics within a single table render so the three metric columns share one
     * calculation per row instead of hitting the cache store three times per portfolio.
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
                    ->tooltip(fn (Portfolio $record): string => $record->is_favorite
                        ? 'Remover dos favoritos'
                        : 'Marcar como favorito')
                    ->action(function (Portfolio $record): void {
                        $record->update(['is_favorite' => ! $record->is_favorite]);
                    }),
                TextColumn::make('name')
                    ->label('Nome')
                    ->limit(40)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_profit')
                    ->label('Lucro Total')
                    ->state(fn (Portfolio $record): float => (float) (self::metricsFor($record)['net_profit'] ?? 0))
                    ->formatStateUsing(fn (float $state): string => self::formatSignedMoney($state))
                    ->color(fn (float $state): string => self::moneyColor($state))
                    ->alignEnd(),
                TextColumn::make('recovery_factor')
                    ->label('Fator de Recuperação')
                    ->state(fn (Portfolio $record): ?float => self::metricsFor($record)['net_profit_to_drawdown'] ?? null)
                    ->formatStateUsing(fn (?float $state): string => $state === null ? 'Sem drawdown' : number_format($state, 2, ',', '.'))
                    ->color(fn (?float $state): string => self::ratioColor($state))
                    ->alignEnd(),
                TextColumn::make('max_drawdown')
                    ->label('Drawdown Máximo')
                    ->state(fn (Portfolio $record): float => (float) (self::metricsFor($record)['max_drawdown'] ?? 0))
                    ->formatStateUsing(fn (float $state): string => self::formatNegativeMoney($state))
                    ->color(fn (float $state): string => $state > 0 ? 'danger' : 'gray')
                    ->alignEnd(),
                TextColumn::make('win_rate')
                    ->label('Taxa de Acerto')
                    ->state(fn (Portfolio $record): float => (float) (self::metricsFor($record)['win_rate'] ?? 0))
                    ->formatStateUsing(fn (float $state): string => number_format($state, 2, ',', '.').'%')
                    ->color(fn (float $state): string => $state > 0 ? 'success' : 'gray')
                    ->alignEnd(),
            ])
            ->filters([
                Filter::make('is_favorite')
                    ->label('Somente favoritos')
                    ->query(fn (Builder $query): Builder => $query->where('is_favorite', true))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('viewResults')
                    ->label('Ver resultados')
                    ->icon(Heroicon::OutlinedChartBarSquare)
                    ->iconButton()
                    ->url(fn (Portfolio $record): string => PortfolioResource::getUrl('results', ['record' => $record])),
                Action::make('compare')
                    ->label('Comparar com...')
                    ->icon(Heroicon::OutlinedScale)
                    ->iconButton()
                    ->url(fn (Portfolio $record): string => PortfolioComparison::getUrl(['a' => $record->getKey()])),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkAction::make('comparePortfolios')
                    ->label('Comparar 2 portfólios')
                    ->icon(Heroicon::OutlinedScale)
                    ->color('primary')
                    ->accessSelectedRecords()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, Component $livewire): void {
                        if ($records->count() !== 2) {
                            Notification::make()
                                ->title('Selecione exatamente 2 portfólios para comparar.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $ids = $records->pluck('id')->values();

                        $livewire->redirect(
                            PortfolioComparison::getUrl(['a' => $ids[0], 'b' => $ids[1]]),
                            navigate: false,
                        );
                    }),
            ]);
    }

    /**
     * Reuses the same cache entry as the portfolio results page, so the listing doesn't force
     * a fresh full recalculation (equity curve, drawdown, monthly performance, ...) per row.
     *
     * @return array<string, mixed>
     */
    private static function metricsFor(Portfolio $portfolio): array
    {
        return self::$metricsCache[$portfolio->getKey()] ??= Cache::remember(
            'portfolio_metrics:'.$portfolio->getKey(),
            now()->addMinutes(10),
            fn (): array => app(PortfolioAnalyzerService::class)->calculate($portfolio),
        );
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
