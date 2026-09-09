<?php

namespace App\Filament\Resources\Portfolios\Tables;

use App\Filament\Pages\PortfolioComparison;
use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Component;

class PortfoliosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('strategies_count')
                    ->label('Quantidade de estratégias')
                    ->counts('strategies')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Data de criação')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('viewResults')
                    ->label('Ver resultados')
                    ->icon(Heroicon::OutlinedChartBarSquare)
                    ->url(fn (Portfolio $record): string => PortfolioResource::getUrl('results', ['record' => $record])),
                Action::make('compare')
                    ->label('Comparar com...')
                    ->icon(Heroicon::OutlinedScale)
                    ->url(fn (Portfolio $record): string => PortfolioComparison::getUrl(['a' => $record->getKey()])),
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
}
