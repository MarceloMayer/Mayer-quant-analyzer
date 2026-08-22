<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Portfolios\PortfolioResource;
use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Mt5ReportFile;
use App\Models\Portfolio;
use App\Models\Strategy;
use App\Models\Trade;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 0;

    protected ?string $subheading = 'Visão geral das estratégias, trades importados e portfólios analisados.';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.dashboard')
                    ->viewData(fn (): array => [
                        'metrics' => $this->metrics(),
                        'operational' => $this->operationalSummary(),
                        'createStrategyUrl' => StrategyResource::getUrl('create'),
                        'createPortfolioUrl' => PortfolioResource::getUrl('create'),
                        'strategiesUrl' => StrategyResource::getUrl('index'),
                        'portfoliosUrl' => PortfolioResource::getUrl('index'),
                    ]),
            ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(): array
    {
        $trades = $this->ownedTradesQuery();

        return [
            'strategies_count' => Strategy::query()->where('user_id', auth()->id())->count(),
            'trades_count' => (clone $trades)->count(),
            'portfolios_count' => Portfolio::query()->where('user_id', auth()->id())->count(),
            'net_profit' => (float) $trades->sum('net_profit'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalSummary(): array
    {
        $latestStrategy = Strategy::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->first(['id', 'name', 'asset', 'created_at']);

        $latestPortfolio = Portfolio::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->first(['id', 'name', 'created_at']);

        $latestReportFile = Mt5ReportFile::query()
            ->with('strategy:id,name')
            ->whereHas('strategy', fn (Builder $query): Builder => $query->where('user_id', auth()->id()))
            ->latest('imported_at')
            ->latest()
            ->first();

        return [
            'latest_strategy' => $latestStrategy,
            'latest_portfolio' => $latestPortfolio,
            'latest_report_file' => $latestReportFile,
            'mt5_report_files_count' => Mt5ReportFile::query()
                ->whereHas('strategy', fn (Builder $query): Builder => $query->where('user_id', auth()->id()))
                ->count(),
        ];
    }

    private function ownedTradesQuery(): Builder
    {
        return Trade::query()
            ->whereHas('strategy', fn (Builder $query): Builder => $query->where('user_id', auth()->id()));
    }
}
