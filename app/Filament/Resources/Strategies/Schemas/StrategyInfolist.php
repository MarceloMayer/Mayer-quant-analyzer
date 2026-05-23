<?php

namespace App\Filament\Resources\Strategies\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StrategyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estratégia')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nome'),
                        TextEntry::make('asset')
                            ->label('Ativo')
                            ->badge(),
                        TextEntry::make('trades_count')
                            ->label('Trades')
                            ->state(fn ($record): int => $record->trades()->count()),
                    ])
                    ->columns(3),
            ]);
    }
}
