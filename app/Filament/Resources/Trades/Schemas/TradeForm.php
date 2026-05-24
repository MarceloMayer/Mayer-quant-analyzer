<?php

namespace App\Filament\Resources\Trades\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('strategy_id')
                    ->label('Estratégia')
                    ->relationship('strategy', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('asset')
                    ->label('Ativo')
                    ->required(),
                TextInput::make('direction')
                    ->label('Direção')
                    ->default(null),
                TextInput::make('volume')
                    ->label('Volume')
                    ->numeric()
                    ->default(null),
                DateTimePicker::make('entry_time')
                    ->label('Entrada'),
                DateTimePicker::make('exit_time')
                    ->label('Saída'),
                TextInput::make('entry_price')
                    ->label('Preço entrada')
                    ->numeric()
                    ->default(null),
                TextInput::make('exit_price')
                    ->label('Preço saída')
                    ->numeric()
                    ->default(null),
                TextInput::make('net_profit')
                    ->label('Resultado líquido')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
