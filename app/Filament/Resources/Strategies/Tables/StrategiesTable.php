<?php

namespace App\Filament\Resources\Strategies\Tables;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Filament\Pages\StrategyComparison;
use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Strategy;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Component;

class StrategiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('magic_number')
                    ->label('Magic Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('asset')
                    ->label('Ativo')
                    ->formatStateUsing(fn (?string $state): ?string => Strategy::assetOptions()[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Data de criação')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('asset')
                    ->label('Ativo')
                    ->options(Strategy::assetOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ImportMt5CsvAction::make(),
                Action::make('viewResults')
                    ->label('Ver resultados')
                    ->icon(Heroicon::OutlinedChartBarSquare)
                    ->url(fn (Strategy $record): string => StrategyResource::getUrl('results', ['record' => $record])),
                Action::make('compare')
                    ->label('Comparar com...')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->url(fn (Strategy $record): string => StrategyComparison::getUrl(['a' => $record->getKey()])),
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
}
