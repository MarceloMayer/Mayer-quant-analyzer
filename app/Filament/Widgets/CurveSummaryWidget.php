<?php

namespace App\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CurveSummaryWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Resumo da curva';

    protected ?string $description = 'Indicadores calculados sobre a curva consolidada do portfólio.';

    /**
     * @var array<string, mixed>
     */
    public array $metrics = [];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $maxDrawdown = (float) ($this->metrics['consolidated_max_drawdown'] ?? 0);
        $maxDrawdownPercent = (float) ($this->metrics['consolidated_max_drawdown_percent'] ?? 0);
        $peak = (float) ($this->metrics['drawdown_peak'] ?? 0);
        $valley = (float) ($this->metrics['drawdown_valley'] ?? 0);
        $finalResult = (float) ($this->metrics['consolidated_net_profit'] ?? 0);
        $daysWithoutNewHigh = (int) ($this->metrics['consolidated_max_days_without_new_high'] ?? 0);

        return [
            Stat::make('Drawdown máximo', $this->formatNegativeMoney($maxDrawdown))
                ->color($maxDrawdown > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedArrowTrendingDown),

            Stat::make('Drawdown percentual', $this->formatNegativePercent($maxDrawdownPercent))
                ->color($maxDrawdownPercent > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedChartBar),

            Stat::make('Topo da curva', $this->formatMoney($peak))
                ->color($peak > 0 ? 'success' : 'gray')
                ->icon(Heroicon::OutlinedArrowTrendingUp),

            Stat::make('Fundo da curva', $this->formatMoney($valley))
                ->color($valley < 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedArrowTrendingDown),

            Stat::make('Resultado acumulado final', $this->formatSignedMoney($finalResult))
                ->color($this->moneyColor($finalResult))
                ->icon($finalResult >= 0 ? Heroicon::OutlinedBanknotes : Heroicon::OutlinedExclamationTriangle),

            Stat::make('Dias sem romper topo', number_format($daysWithoutNewHigh, 0, ',', '.'))
                ->description('Maior intervalo sem nova máxima acumulada')
                ->color($daysWithoutNewHigh > 0 ? 'gray' : 'success')
                ->icon(Heroicon::OutlinedClock),
        ];
    }

    private function formatSignedMoney(float $value): string
    {
        return ($value > 0 ? '+' : ($value < 0 ? '-' : '')).$this->formatMoney(abs($value));
    }

    private function formatNegativeMoney(float $value): string
    {
        return $value > 0 ? '-'.$this->formatMoney($value) : $this->formatMoney(0);
    }

    private function formatNegativePercent(float $value): string
    {
        return $value > 0 ? '-'.$this->formatPercent($value) : $this->formatPercent(0);
    }

    private function formatMoney(float $value): string
    {
        return 'R$ '.number_format(abs($value), 2, ',', '.');
    }

    private function formatPercent(float $value): string
    {
        return number_format(abs($value), 2, ',', '.').'%';
    }

    private function moneyColor(float $value): string
    {
        return match (true) {
            $value > 0 => 'success',
            $value < 0 => 'danger',
            default => 'gray',
        };
    }
}
