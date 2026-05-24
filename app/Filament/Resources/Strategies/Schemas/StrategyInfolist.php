<?php

namespace App\Filament\Resources\Strategies\Schemas;

use App\Models\Strategy;
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
                            ->formatStateUsing(fn (?string $state): ?string => Strategy::assetOptions()[$state] ?? $state)
                            ->badge(),
                    ])
                    ->columns(2),
            ]);
    }
}
