@php
    $formatMoney = fn (mixed $value): string => 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $formatSignedMoney = fn (mixed $value): string => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . $formatMoney($value);
    $formatPercent = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
    $formatWeight = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . 'x';
    $assetLabels = \App\Models\Strategy::assetOptions();
    $consolidatedTrades = $metrics['consolidated_trades'] ?? [];
    $strategySummaries = $metrics['strategy_summaries'] ?? [];
    $hasTrades = ($metrics['total_trades'] ?? 0) > 0;
@endphp

@once
    <style>
        .mqa-strategy-card {
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            background: rgb(255 255 255);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .dark .mqa-strategy-card {
            border-color: rgb(31 41 55);
            background: rgb(17 24 39);
        }

        .mqa-strategy-header {
            border-bottom: 1px solid rgb(229 231 235);
            padding: 0.875rem 1rem;
        }

        .dark .mqa-strategy-header {
            border-color: rgb(31 41 55);
        }

        .mqa-strategy-title {
            color: rgb(17 24 39);
            font-size: 0.875rem;
            font-weight: 650;
            line-height: 1.25rem;
        }

        .dark .mqa-strategy-title {
            color: rgb(255 255 255);
        }

        .mqa-strategy-description {
            margin-top: 0.25rem;
            color: rgb(107 114 128);
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .dark .mqa-strategy-description {
            color: rgb(156 163 175);
        }

        .mqa-strategy-scroll {
            overflow-x: auto;
            padding: 1rem;
        }

        .mqa-strategy-table {
            width: 100%;
            min-width: 1120px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.8125rem;
            line-height: 1.25rem;
        }

        .mqa-strategy-table th,
        .mqa-strategy-table td {
            border: 1px solid rgb(229 231 235);
            padding: 0.625rem 0.75rem;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .dark .mqa-strategy-table th,
        .dark .mqa-strategy-table td {
            border-color: rgb(55 65 81);
        }

        .mqa-strategy-table thead th {
            background: rgb(243 244 246);
            color: rgb(55 65 81);
            font-weight: 650;
        }

        .dark .mqa-strategy-table thead th {
            background: rgb(31 41 55);
            color: rgb(229 231 235);
        }

        .mqa-strategy-table tbody tr:nth-child(even) td {
            background: rgb(249 250 251);
        }

        .mqa-strategy-table tbody tr:hover td {
            background: rgb(243 244 246);
        }

        .dark .mqa-strategy-table tbody tr:nth-child(even) td {
            background: rgb(15 23 42);
        }

        .dark .mqa-strategy-table tbody tr:hover td {
            background: rgb(30 41 59);
        }

        .mqa-left {
            text-align: left;
        }

        .mqa-right {
            text-align: right;
        }

        .mqa-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 650;
            line-height: 1rem;
        }

        .mqa-badge-active {
            background: rgb(236 253 245);
            color: rgb(4 120 87);
        }

        .mqa-badge-inactive {
            background: rgb(243 244 246);
            color: rgb(75 85 99);
        }

        .dark .mqa-badge-active {
            background: rgb(6 78 59 / 0.32);
            color: rgb(110 231 183);
        }

        .dark .mqa-badge-inactive {
            background: rgb(55 65 81);
            color: rgb(209 213 219);
        }

        .mqa-profit-positive {
            color: rgb(4 120 87);
            font-weight: 650;
        }

        .mqa-profit-negative,
        .mqa-drawdown {
            color: rgb(190 18 60);
            font-weight: 650;
        }

        .mqa-profit-neutral {
            color: rgb(107 114 128);
            font-weight: 600;
        }

        .dark .mqa-profit-positive {
            color: rgb(110 231 183);
        }

        .dark .mqa-profit-negative,
        .dark .mqa-drawdown {
            color: rgb(253 164 175);
        }

        .dark .mqa-profit-neutral {
            color: rgb(156 163 175);
        }
    </style>
@endonce

<div class="space-y-6">
    @livewire(\App\Filament\Widgets\PortfolioMetricsOverview::class, [
        'metrics' => $metrics,
    ])

    @if (! $hasTrades)
        <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            Nenhum trade com data de saída encontrado para as estratégias ativas deste portfólio.
        </div>
    @else
        <div class="space-y-5">
            @livewire(\App\Filament\Widgets\EquityCurveChart::class, [
                'heading' => 'Resultado financeiro acumulado',
                'curve' => $metrics['consolidated_equity_curve'] ?? [],
            ])

            @livewire(\App\Filament\Widgets\MonthlyPerformanceTable::class, [
                'heading' => 'Resultado mês a mês',
                'description' => 'Resultado financeiro consolidado de cada mês, calculado pelos trades fechados das estratégias ativas.',
                'performance' => $metrics['consolidated_monthly_performance'] ?? [],
            ])

            @livewire(\App\Filament\Widgets\MonthlyPerformanceTable::class, [
                'heading' => 'Resultado acumulado por mês',
                'description' => 'Saldo acumulado ao final de cada mês, reconstruído pela curva consolidada ordenada por data de saída.',
                'performance' => $metrics['consolidated_monthly_cumulative_performance'] ?? [],
            ])
        </div>
    @endif

    <section class="mqa-strategy-card">
        <div class="mqa-strategy-header">
            <h3 class="mqa-strategy-title">Estratégias do portfólio</h3>
            <p class="mqa-strategy-description">Resultado e risco individual das estratégias vinculadas.</p>
        </div>

        <div class="mqa-strategy-scroll">
            <table class="mqa-strategy-table">
                <colgroup>
                    <col style="width: 260px;">
                    <col style="width: 120px;">
                    <col style="width: 110px;">
                    <col style="width: 90px;">
                    <col style="width: 170px;">
                    <col style="width: 150px;">
                    <col style="width: 110px;">
                    <col style="width: 100px;">
                </colgroup>
                <thead>
                    <tr>
                        <th class="mqa-left">Estratégia</th>
                        <th class="mqa-left">Ativo</th>
                        <th class="mqa-left">Status</th>
                        <th class="mqa-right">Trades</th>
                        <th class="mqa-right">Resultado líquido</th>
                        <th class="mqa-right">Drawdown</th>
                        <th class="mqa-right">DD %</th>
                        <th class="mqa-right">Peso</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($strategySummaries as $strategy)
                        @php
                            $profitClass = match (true) {
                                (float) ($strategy['net_profit'] ?? 0) > 0 => 'mqa-profit-positive',
                                (float) ($strategy['net_profit'] ?? 0) < 0 => 'mqa-profit-negative',
                                default => 'mqa-profit-neutral',
                            };
                        @endphp

                        <tr>
                            <td class="mqa-left" style="font-weight: 650;">{{ $strategy['name'] ?? '-' }}</td>
                            <td class="mqa-left">{{ $assetLabels[$strategy['asset'] ?? null] ?? $strategy['asset'] ?? '-' }}</td>
                            <td class="mqa-left">
                                <span class="mqa-badge {{ ($strategy['enabled'] ?? false) ? 'mqa-badge-active' : 'mqa-badge-inactive' }}">
                                    {{ ($strategy['enabled'] ?? false) ? 'Ativa' : 'Inativa' }}
                                </span>
                            </td>
                            <td class="mqa-right">{{ number_format((int) ($strategy['total_trades'] ?? 0), 0, ',', '.') }}</td>
                            <td class="mqa-right {{ $profitClass }}">{{ $formatSignedMoney($strategy['net_profit'] ?? 0) }}</td>
                            <td class="mqa-right mqa-drawdown">{{ (float) ($strategy['max_drawdown'] ?? 0) > 0 ? '-' : '' }}{{ $formatMoney($strategy['max_drawdown'] ?? 0) }}</td>
                            <td class="mqa-right mqa-drawdown">{{ (float) ($strategy['max_drawdown_percent'] ?? 0) > 0 ? '-' : '' }}{{ $formatPercent($strategy['max_drawdown_percent'] ?? 0) }}</td>
                            <td class="mqa-right" style="font-weight: 650;">{{ $formatWeight($strategy['weight'] ?? 1) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding: 1rem; text-align: center; color: rgb(107 114 128);">Nenhuma estratégia vinculada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @livewire(\App\Filament\Widgets\PortfolioConsolidatedTradesTable::class, [
        'trades' => $consolidatedTrades,
    ])
</div>
