<?php

namespace App\Filament\Resources\Portfolios\Schemas;

use App\Models\Strategy;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PortfolioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Portfólio')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Repeater::make('portfolioStrategies')
                    ->label('Estratégias')
                    ->relationship()
                    ->schema([
                        Select::make('strategy_id')
                            ->label('Estratégia')
                            ->relationship('strategy', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Strategy $record): string => self::strategyOptionLabel($record))
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(6),
                        Toggle::make('enabled')
                            ->label('Ativa')
                            ->default(true)
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('weight')
                            ->label('Peso')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->minValue(0)
                            ->columnSpan(4),
                    ])
                    ->columns(12)
                    ->defaultItems(2)
                    ->minItems(2)
                    ->addActionLabel('Adicionar estratégia')
                    ->reorderable(false)
                    ->columnSpanFull(),
            ]);
    }

    private static function strategyOptionLabel(Strategy $strategy): string
    {
        $asset = Strategy::assetOptions()[$strategy->asset] ?? $strategy->asset;

        return "{$strategy->name} ({$asset})";
    }
}
