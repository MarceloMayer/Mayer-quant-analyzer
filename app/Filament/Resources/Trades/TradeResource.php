<?php

namespace App\Filament\Resources\Trades;

use App\Filament\Resources\Trades\Pages\ListTrades;
use App\Filament\Resources\Trades\Pages\ViewTrade;
use App\Filament\Resources\Trades\Schemas\TradeForm;
use App\Filament\Resources\Trades\Schemas\TradeInfolist;
use App\Filament\Resources\Trades\Tables\TradesTable;
use App\Models\Trade;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TradeResource extends Resource
{
    protected static bool $isDiscovered = false;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Trade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $modelLabel = 'trade';

    protected static ?string $pluralModelLabel = 'trades';

    protected static ?string $navigationLabel = 'Trades';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return TradeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TradeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TradesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrades::route('/'),
            'view' => ViewTrade::route('/{record}'),
        ];
    }
}
