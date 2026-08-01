<?php

namespace App\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StrategyMetricsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = null;

    /**
     * @var array<string, mixed>
     */
    public array $metrics = [];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $netProfit = (float) ($this->metrics['net_profit'] ?? 0);
        $drawdown = (float) ($this->metrics['max_drawdown'] ?? 0);
        $winRate = (float) ($this->metrics['win_rate'] ?? 0);
        $profitFactor = $this->metrics['profit_factor'] ?? null;
        $payoff = $this->metrics['average_payoff'] ?? null;
        $totalTrades = (int) ($this->metrics['total_trades'] ?? 0);
        $maxLosingStreak = (int) ($this->metrics['max_losing_streak'] ?? 0);
        $daysWithoutNewHigh = (int) ($this->metrics['max_days_without_new_high'] ?? 0);

        return [
            Stat::make('Resultado líquido', $this->formatSignedMoney($netProfit))
                ->color($this->moneyColor($netProfit))
                ->icon($netProfit >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
                ->chart($this->equityChart())
                ->chartColor($this->moneyColor($netProfit)),

            Stat::make('Drawdown máximo', $this->formatNegativeMoney($drawdown))
                ->color($drawdown > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedArrowTrendingDown),

            Stat::make('Taxa de acerto', $this->formatPercent($winRate))
                ->color($winRate > 0 ? 'success' : 'gray')
                ->icon(Heroicon::OutlinedShieldCheck),

            Stat::make('Profit factor', $profitFactor === null ? 'Sem perdas' : $this->formatNumber($profitFactor))
                ->color($this->ratioColor($profitFactor))
                ->icon(Heroicon::OutlinedCalculator),

            Stat::make('Relação ganho/perda', $payoff === null ? '-' : $this->formatNumber($payoff))
                ->color($this->ratioColor($payoff))
                ->icon(Heroicon::OutlinedScale),

            Stat::make('Total de trades', number_format($totalTrades, 0, ',', '.'))
                ->color($totalTrades > 0 ? 'primary' : 'gray')
                ->icon(Heroicon::OutlinedHashtag),

            Stat::make('Maior sequência de perdas', number_format($maxLosingStreak, 0, ',', '.'))
                ->color($maxLosingStreak > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedExclamationTriangle),

            Stat::make('Dias sem romper topo', number_format($daysWithoutNewHigh, 0, ',', '.'))
                ->description('Maior intervalo sem nova máxima acumulada')
                ->color($daysWithoutNewHigh > 0 ? 'gray' : 'success')
                ->icon(Heroicon::OutlinedClock),
        ];
    }

    private function formatSignedMoney(float $value): string
    {
        return ($value > 0 ? '+' : '').$this->formatMoney($value);
    }

    private function formatNegativeMoney(float $value): string
    {
        return $value > 0 ? '-'.$this->formatMoney($value) : $this->formatMoney(0);
    }

    private function formatMoney(float $value): string
    {
        return 'R$ '.number_format(abs($value), 2, ',', '.');
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 2, ',', '.').'%';
    }

    private function formatNumber(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function moneyColor(float $value): string
    {
        return match (true) {
            $value > 0 => 'success',
            $value < 0 => 'danger',
            default => 'gray',
        };
    }

    private function ratioColor(mixed $value): string
    {
        if ($value === null) {
            return 'gray';
        }

        return (float) $value >= 1 ? 'success' : 'danger';
    }

    /**
     * @return array<float>
     */
    private function equityChart(): array
    {
        return collect($this->metrics['equity_curve'] ?? [])
            ->pluck('equity')
            ->map(fn (mixed $value): float => (float) $value)
            ->take(-12)
            ->values()
            ->all();
    }
}
