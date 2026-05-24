<?php

namespace App\Filament\Resources\Portfolios\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PortfolioInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Portfólio')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nome'),
                        TextEntry::make('description')
                            ->label('Descrição')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('strategies_count')
                            ->label('Quantidade de estratégias')
                            ->state(fn ($record): int => $record->strategies()->count())
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
