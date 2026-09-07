<?php

namespace App\Filament\Widgets;

use App\Models\Strategy;
use Carbon\CarbonImmutable;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class PortfolioConsolidatedTradesTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * The trades are cached server-side (see tradesCacheKey()) instead of held in a public
     * property: with portfolios that accumulate thousands of closed trades, embedding them
     * directly here would be re-serialized into the Livewire payload on every sort/page
     * interaction, making the page heavy to load and slow to paginate.
     *
     * @param  array<int, array<string, mixed>>  $trades
     */
    public function mount(array $trades = []): void
    {
        Cache::put($this->tradesCacheKey(), $trades, now()->addHour());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Trades consolidados')
            ->description('Trades fechados das estratégias ativas, já ponderados pelo peso do portfólio.')
            ->records(
                fn (?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage): LengthAwarePaginator => $this->records($sortColumn, $sortDirection, $page, $recordsPerPage)
            )
            ->columns([
                TextColumn::make('exit_time')
                    ->label('Saída')
                    ->formatStateUsing(fn (mixed $state): string => $this->formatDateTime($state))
                    ->sortable(),

                TextColumn::make('strategy_name')
                    ->label('Estratégia')
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

                TextColumn::make('weight')
                    ->label('Peso')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => $this->formatWeight($state))
                    ->sortable(),

                TextColumn::make('original_net_profit')
                    ->label('Resultado original')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => $this->formatSignedMoney($state))
                    ->color(fn (mixed $state): string => $this->moneyColor($state))
                    ->sortable(),

                TextColumn::make('cumulative_net_profit')
                    ->label('Valor acumulado')
                    ->alignment(Alignment::End)
                    ->formatStateUsing(fn (mixed $state): string => $this->formatSignedMoney($state))
                    ->color(fn (mixed $state): string => $this->moneyColor($state))
                    ->sortable(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    private function records(?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        $records = collect(Cache::get($this->tradesCacheKey(), []))->values();

        if (filled($sortColumn)) {
            $records = $records->sortBy(
                fn (array $record): mixed => $record[$sortColumn] ?? null,
                SORT_REGULAR,
                $sortDirection === 'desc',
            );
        }

        $records = $records->values();

        $items = $records
            ->forPage($page, $recordsPerPage)
            ->mapWithKeys(fn (array $trade, int $index): array => [
                (string) ($trade['id'] ?? $index) => $trade,
            ]);

        return new LengthAwarePaginator(
            $items,
            $records->count(),
            $recordsPerPage,
            $page,
        );
    }

    /**
     * Scoped to the current user and this specific widget instance, so trades from one
     * portfolio page don't leak into another tab/session while it's open.
     */
    private function tradesCacheKey(): string
    {
        return 'portfolio_consolidated_trades:'.auth()->id().':'.$this->getId();
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

    private function formatWeight(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
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
