<?php

namespace App\Filament\Resources\Strategies\Tables;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Models\Strategy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                TextColumn::make('asset')
                    ->label('Ativo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('trades_count')
                    ->label('Trades')
                    ->counts('trades')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('asset')
                    ->label('Ativo')
                    ->options(Strategy::assetOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ImportMt5CsvAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
