<?php

namespace App\Filament\Resources\Strategies\Tables;

use App\Filament\Actions\ImportMt5CsvAction;
use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Strategy;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
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
            ]);
    }
}
