@php
    $formatMoney = fn (mixed $value): string => number_format((float) $value, 2, ',', '.');
    $formatPercent = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
    $formatNumber = fn (mixed $value): string => number_format((float) $value, 2, ',', '.');
    $formatSignedMoney = fn (mixed $value): string => (($value ?? 0) > 0 ? '+' : '') . $formatMoney($value);
    $moneyTone = fn (mixed $value): string => match (true) {
        (float) $value > 0 => 'text-emerald-600 dark:text-emerald-400',
        (float) $value < 0 => 'text-rose-600 dark:text-rose-400',
        default => 'text-gray-600 dark:text-gray-300',
    };
    $lossTone = fn (mixed $value): string => (float) $value > 0
        ? 'text-rose-600 dark:text-rose-400'
        : 'text-gray-600 dark:text-gray-300';
    $assetLabels = \App\Models\Strategy::assetOptions();
    $directionLabels = ['buy' => 'Compra', 'sell' => 'Venda'];
    $hasTrades = ($metrics['total_trades'] ?? 0) > 0;

    $cards = [
        ['label' => 'Resultado líquido', 'value' => $formatMoney($metrics['net_profit'] ?? 0), 'tone' => $moneyTone($metrics['net_profit'] ?? 0)],
        ['label' => 'Drawdown máximo', 'value' => $formatMoney($metrics['max_drawdown'] ?? 0), 'tone' => $lossTone($metrics['max_drawdown'] ?? 0)],
        ['label' => 'Dias sem nova máxima', 'value' => (string) ($metrics['max_days_without_new_high'] ?? 0), 'tone' => 'text-gray-950 dark:text-white'],
        ['label' => 'Profit Factor', 'value' => ($metrics['profit_factor'] ?? null) === null ? 'Sem perdas' : $formatNumber($metrics['profit_factor']), 'tone' => 'text-gray-950 dark:text-white'],
        ['label' => 'Taxa de acerto', 'value' => $formatPercent($metrics['win_rate'] ?? 0), 'tone' => 'text-gray-950 dark:text-white'],
        ['label' => 'Payoff médio', 'value' => ($metrics['average_payoff'] ?? null) === null ? '-' : $formatNumber($metrics['average_payoff']), 'tone' => 'text-gray-950 dark:text-white'],
        ['label' => 'Total de trades', 'value' => (string) ($metrics['total_trades'] ?? 0), 'tone' => 'text-gray-950 dark:text-white'],
        ['label' => 'Maior sequência de perdas', 'value' => (string) ($metrics['max_losing_streak'] ?? 0), 'tone' => $lossTone($metrics['max_losing_streak'] ?? 0)],
    ];
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $strategy->name }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $assetLabels[$strategy->asset] ?? $strategy->asset }}</p>
        </div>
    </div>

    @if (! $hasTrades)
        <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            Nenhum trade com data de saída encontrado para esta estratégia.
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                    <div class="mt-2 text-xl font-semibold {{ $card['tone'] }}">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>

        @livewire(\App\Filament\Widgets\DrawdownInfoWidget::class, [
            'max_drawdown' => $metrics['max_drawdown'] ?? 0,
            'max_drawdown_percent' => $metrics['max_drawdown_percent'] ?? 0,
            'peak' => $metrics['drawdown_peak'] ?? 0,
            'valley' => $metrics['drawdown_valley'] ?? 0,
        ])

        @livewire(\App\Filament\Widgets\EquityCurveChart::class, [
            'curve' => $metrics['equity_curve'] ?? [],
        ])

        @livewire(\App\Filament\Widgets\MonthlyPerformanceTable::class, [
            'performance' => $metrics['monthly_performance'] ?? [],
        ])

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-950 dark:border-gray-800 dark:text-white">Trades da estratégia</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Saída</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Ativo</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Direção</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Volume</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Resultado líquido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($trades as $trade)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $trade->exit_time?->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $assetLabels[$trade->asset] ?? $trade->asset }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-300">{{ $directionLabels[$trade->direction] ?? $trade->direction ?? '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $trade->volume === null ? '-' : $formatNumber($trade->volume) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-medium {{ $moneyTone($trade->net_profit) }}">{{ $formatSignedMoney($trade->net_profit) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
