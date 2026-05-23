<?php

namespace App\Filament\Resources\Portfolios\Schemas;

use App\Models\Strategy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PortfolioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('strategies')
                    ->label('Estratégias')
                    ->relationship(titleAttribute: 'name')
                    ->getOptionLabelFromRecordUsing(fn (Strategy $record): string => "{$record->name} ({$record->asset})")
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required(),
            ]);
    }
}
