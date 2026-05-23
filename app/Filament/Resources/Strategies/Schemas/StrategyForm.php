<?php

namespace App\Filament\Resources\Strategies\Schemas;

use App\Models\Strategy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StrategyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Select::make('asset')
                    ->label('Ativo')
                    ->options(Strategy::assetOptions())
                    ->native(false)
                    ->required(),
            ]);
    }
}
