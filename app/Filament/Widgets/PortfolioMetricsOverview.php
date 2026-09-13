<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class PortfolioMetricsOverview extends Widget
{
    protected string $view = 'filament.widgets.portfolio-metrics-overview';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<string, mixed>
     */
    public array $metrics = [];

    /**
     * Three compound "hero" cards, mirroring a portfolio-analytics dashboard reference:
     * each pairs a headline metric with the secondary figures that explain it, instead
     * of scattering every stat across same-weight single-value tiles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function heroCards(): array
    {
        $daily = $this->metrics['daily_performance'] ?? [];

        $netProfit = (float) ($this->metrics['consolidated_net_profit'] ?? 0);
        $monthsWithTrades = (int) ($this->metrics['months_with_trades'] ?? 0);
        $averageMonth = $monthsWithTrades > 0 ? $netProfit / $monthsWithTrades : null;

        $totalDays = (int) ($daily['total_days'] ?? 0);
        $positiveDays = (int) ($daily['positive_days'] ?? 0);
        $negativeDays = (int) ($daily['negative_days'] ?? 0);
        $neutralDays = (int) ($daily['neutral_days'] ?? 0);

        $profitFactor = $this->metrics['consolidated_profit_factor'] ?? null;
        $winRate = (float) ($this->metrics['consolidated_win_rate'] ?? 0);
        $recoveryFactor = $this->metrics['net_profit_to_drawdown'] ?? null;

        $payoff = $this->metrics['consolidated_payoff'] ?? null;
        $averagePositiveDay = (float) ($daily['average_positive_day'] ?? 0);
        $averageNegativeDay = (float) ($daily['average_negative_day'] ?? 0);

        return [
            [
                'label' => 'Lucro Total',
                'value' => $this->formatSignedMoney($netProfit),
                'valueClass' => $this->moneyClasses($netProfit),
                'aside_label' => 'Média Mensal',
                'aside_value' => $averageMonth === null ? '-' : $this->formatSignedMoney($averageMonth),
                'total_days' => $totalDays,
                'positive_days' => $positiveDays,
                'negative_days' => $negativeDays,
                'neutral_days' => $neutralDays,
                'positive_day_rate' => (float) ($daily['positive_day_rate'] ?? 0),
                'negative_day_rate' => (float) ($daily['negative_day_rate'] ?? 0),
                'neutral_day_rate' => (float) ($daily['neutral_day_rate'] ?? 0),
            ],
            [
                'label' => 'Fator de Lucro',
                'value' => $profitFactor === null ? 'Sem perdas' : $this->formatNumber($profitFactor),
                'valueClass' => $this->ratioClasses($profitFactor),
                'sub' => [
                    ['label' => 'Taxa de Acerto', 'value' => $this->formatPercent($winRate)],
                    ['label' => 'Fator de Recuperação', 'value' => $recoveryFactor === null ? '-' : $this->formatNumber($recoveryFactor)],
                ],
            ],
            [
                'label' => 'Payoff',
                'value' => $payoff === null ? '-' : $this->formatNumber($payoff),
                'valueClass' => $this->ratioClasses($payoff),
                'sub' => [
                    ['label' => 'Média Dias Positivos', 'value' => $this->formatSignedMoney($averagePositiveDay), 'valueClass' => $this->moneyClasses($averagePositiveDay)],
                    ['label' => 'Média Dias Negativos', 'value' => $this->formatSignedMoney($averageNegativeDay), 'valueClass' => $this->moneyClasses($averageNegativeDay)],
                ],
            ],
        ];
    }

    /**
     * Secondary indicators that don't need hero treatment but were previously shown
     * as standalone stat tiles.
     *
     * @return array<int, array{label: string, value: string, valueClass: string}>
     */
    public function secondaryStats(): array
    {
        $totalTrades = (int) ($this->metrics['total_trades'] ?? 0);
        $maxLosingStreak = (int) ($this->metrics['max_losing_streak'] ?? 0);
        $daysWithoutNewHigh = (int) ($this->metrics['consolidated_max_days_without_new_high'] ?? 0);

        return [
            [
                'label' => 'Total de Trades',
                'value' => number_format($totalTrades, 0, ',', '.'),
                'valueClass' => 'text-gray-950 dark:text-white',
            ],
            [
                'label' => 'Maior Sequência de Perdas',
                'value' => number_format($maxLosingStreak, 0, ',', '.'),
                'valueClass' => $maxLosingStreak > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-950 dark:text-white',
            ],
            [
                'label' => 'Dias sem Romper Topo',
                'value' => number_format($daysWithoutNewHigh, 0, ',', '.'),
                'valueClass' => $daysWithoutNewHigh > 0 ? 'text-gray-950 dark:text-white' : 'text-emerald-600 dark:text-emerald-400',
            ],
        ];
    }

    private function formatSignedMoney(float $value): string
    {
        return ($value > 0 ? '+' : ($value < 0 ? '-' : '')).'R$ '.number_format(abs($value), 2, ',', '.');
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 2, ',', '.').'%';
    }

    private function formatNumber(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function moneyClasses(float $value): string
    {
        return match (true) {
            $value > 0 => 'text-emerald-600 dark:text-emerald-400',
            $value < 0 => 'text-rose-600 dark:text-rose-400',
            default => 'text-gray-600 dark:text-gray-300',
        };
    }

    private function ratioClasses(mixed $value): string
    {
        if ($value === null) {
            return 'text-gray-600 dark:text-gray-300';
        }

        return (float) $value >= 1
            ? 'text-emerald-600 dark:text-emerald-400'
            : 'text-rose-600 dark:text-rose-400';
    }
}
