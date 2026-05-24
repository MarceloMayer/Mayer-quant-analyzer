<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Portfolio;
use App\Models\Strategy;
use App\Models\Trade;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 0;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.dashboard')
                    ->viewData(fn (): array => [
                        'metrics' => $this->metrics(),
                        'createStrategyUrl' => StrategyResource::getUrl('create'),
                        'createPortfolioUrl' => PortfolioResource::getUrl('create'),
                    ]),
            ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(): array
    {
        return [
            'strategies_count' => Strategy::query()->count(),
            'trades_count' => Trade::query()->count(),
            'portfolios_count' => Portfolio::query()->count(),
            'net_profit' => (float) Trade::query()->sum('net_profit'),
        ];
    }
}
