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
                        TextEntry::make('strategies_count')
                            ->label('Estratégias')
                            ->state(fn ($record): int => $record->strategies()->count()),
                    ])
                    ->columns(2),
            ]);
    }
}
