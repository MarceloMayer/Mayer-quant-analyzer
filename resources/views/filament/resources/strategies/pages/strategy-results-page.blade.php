@php
    $hasTrades = ($metrics['total_trades'] ?? 0) > 0;
@endphp

<div class="space-y-6">
    @livewire(\App\Filament\Widgets\StrategyMetricsOverview::class, [
        'metrics' => $metrics,
    ])

    @if (! $hasTrades)
        <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            Nenhum trade com data de saída encontrado para esta estratégia.
        </div>
    @else
        <div class="space-y-5">
            @livewire(\App\Filament\Widgets\EquityCurveChart::class, [
                'heading' => 'Resultado financeiro acumulado',
                'curve' => $metrics['equity_curve'] ?? [],
            ])

            @livewire(\App\Filament\Widgets\MonthlyPerformanceTable::class, [
                'heading' => 'Resultado mês a mês',
                'description' => 'Resultado financeiro de cada mês, calculado pelos trades fechados da estratégia.',
                'performance' => $metrics['monthly_performance'] ?? [],
            ])

            @livewire(\App\Filament\Widgets\MonthlyPerformanceTable::class, [
                'heading' => 'Resultado acumulado por mês',
                'description' => 'Saldo acumulado ao final de cada mês, reconstruído pela curva da estratégia ordenada por data de saída.',
                'performance' => $metrics['monthly_cumulative_performance'] ?? [],
            ])
        </div>
    @endif

    @livewire(\App\Filament\Widgets\StrategyTradesTable::class, [
        'trades' => $metrics['strategy_trades'] ?? [],
    ])
</div>
