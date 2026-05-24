<?php

namespace App\Filament\Resources\Trades\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TradeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('strategy.name')
                    ->label('Estratégia'),
                TextEntry::make('asset')
                    ->label('Ativo'),
                TextEntry::make('direction')
                    ->label('Direção')
                    ->placeholder('-'),
                TextEntry::make('volume')
                    ->label('Volume')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextEntry::make('entry_time')
                    ->label('Entrada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextEntry::make('exit_time')
                    ->label('Saída')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextEntry::make('entry_price')
                    ->label('Preço entrada')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextEntry::make('exit_price')
                    ->label('Preço saída')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextEntry::make('net_profit')
                    ->label('Resultado líquido')
                    ->numeric(decimalPlaces: 2),
            ]);
    }
}
