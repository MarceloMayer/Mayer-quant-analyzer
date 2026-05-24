<?php

namespace App\Filament\Resources\Strategies;

use App\Filament\Resources\Strategies\Pages\CreateStrategy;
use App\Filament\Resources\Strategies\Pages\EditStrategy;
use App\Filament\Resources\Strategies\Pages\ListStrategies;
use App\Filament\Resources\Strategies\Pages\StrategyResultsPage;
use App\Filament\Resources\Strategies\Schemas\StrategyForm;
use App\Filament\Resources\Strategies\Schemas\StrategyInfolist;
use App\Filament\Resources\Strategies\Tables\StrategiesTable;
use App\Models\Strategy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StrategyResource extends Resource
{
    protected static ?string $model = Strategy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $modelLabel = 'estratégia';

    protected static ?string $pluralModelLabel = 'estratégias';

    protected static ?string $navigationLabel = 'Estratégias';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return StrategyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StrategyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StrategiesTable::configure($table);
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
            'index' => ListStrategies::route('/'),
            'create' => CreateStrategy::route('/create'),
            'results' => StrategyResultsPage::route('/{record}/results'),
            'edit' => EditStrategy::route('/{record}/edit'),
        ];
    }
}
