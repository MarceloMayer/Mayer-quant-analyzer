<?php

namespace App\Filament\Resources\Portfolios\Tables;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Models\Portfolio;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ]);
    }
}
