<?php

namespace App\Filament\Resources\Strategies\Schemas;

use App\Models\Strategy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

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
                TextInput::make('magic_number')
                    ->label('Magic Number')
                    ->helperText('Identificador numérico exclusivo da estratégia.')
                    ->integer()
                    ->minValue(1)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('user_id', auth()->id()),
                    )
                    ->required(),
                Select::make('asset')
                    ->label('Ativo')
                    ->options(Strategy::assetOptions())
                    ->native(false)
                    ->required(),
            ]);
    }
}
