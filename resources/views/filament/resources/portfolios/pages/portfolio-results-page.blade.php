@php
    $formatMoney = fn (mixed $value): string => 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $formatSignedMoney = fn (mixed $value): string => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . $formatMoney($value);
    $formatPercent = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
    $formatWeight = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . 'x';
    $assetLabels = \App\Models\Strategy::assetOptions();
    $consolidatedTrades = $metrics['consolidated_trades'] ?? [];
    $strategySummaries = $metrics['strategy_summaries'] ?? [];
    $hasTrades = ($metrics['total_trades'] ?? 0) > 0;
    $correlationSummary = $correlation['summary'] ?? [];
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

        .mqa-correlation-body {
            padding: 1rem;
        }

        .mqa-correlation-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .mqa-correlation-filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .mqa-correlation-field {
            display: grid;
            gap: 0.35rem;
            min-width: 180px;
        }

        .mqa-correlation-label {
            color: rgb(75 85 99);
            font-size: 0.75rem;
            font-weight: 650;
            line-height: 1rem;
        }

        .dark .mqa-correlation-label {
            color: rgb(209 213 219);
        }

        .mqa-correlation-select {
            width: 100%;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.5rem;
            background: rgb(255 255 255);
            color: rgb(17 24 39);
            font-size: 0.875rem;
            line-height: 1.25rem;
            padding: 0.5rem 0.75rem;
        }

        .dark .mqa-correlation-select {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: rgb(249 250 251);
        }

        .mqa-correlation-summary {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 768px) {
            .mqa-correlation-summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .mqa-correlation-summary {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }

        .mqa-correlation-summary-card {
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            background: rgb(249 250 251);
            padding: 0.875rem;
        }

        .dark .mqa-correlation-summary-card {
            border-color: rgb(55 65 81);
            background: rgb(15 23 42);
        }

        .mqa-correlation-summary-label {
            color: rgb(107 114 128);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            line-height: 1rem;
            text-transform: uppercase;
        }

        .dark .mqa-correlation-summary-label {
            color: rgb(156 163 175);
        }

        .mqa-correlation-summary-value {
            margin-top: 0.35rem;
            color: rgb(17 24 39);
            font-size: 1.25rem;
            font-variant-numeric: tabular-nums;
            font-weight: 750;
            line-height: 1.75rem;
        }

        .dark .mqa-correlation-summary-value {
            color: rgb(249 250 251);
        }

        .mqa-correlation-summary-description {
            overflow: hidden;
            margin-top: 0.25rem;
            color: rgb(107 114 128);
            font-size: 0.75rem;
            line-height: 1rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .mqa-correlation-summary-description {
            color: rgb(156 163 175);
        }

        .mqa-correlation-interpretation {
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.75rem 0.875rem;
            font-size: 0.875rem;
            font-weight: 650;
            line-height: 1.25rem;
        }

        .mqa-correlation-interpretation-success {
            background: rgb(236 253 245);
            color: rgb(4 120 87);
        }

        .mqa-correlation-interpretation-warning {
            background: rgb(254 249 195);
            color: rgb(133 77 14);
        }

        .mqa-correlation-interpretation-danger {
            background: rgb(255 241 242);
            color: rgb(190 18 60);
        }

        .mqa-correlation-interpretation-neutral {
            background: rgb(243 244 246);
            color: rgb(75 85 99);
        }

        .dark .mqa-correlation-interpretation-success {
            background: rgb(6 78 59 / 0.32);
            color: rgb(110 231 183);
        }

        .dark .mqa-correlation-interpretation-warning {
            background: rgb(113 63 18 / 0.35);
            color: rgb(253 224 71);
        }

        .dark .mqa-correlation-interpretation-danger {
            background: rgb(127 29 29 / 0.35);
            color: rgb(253 164 175);
        }

        .dark .mqa-correlation-interpretation-neutral {
            background: rgb(31 41 55);
            color: rgb(209 213 219);
        }

        .mqa-correlation-highlighted {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .mqa-correlation-pair {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 1px solid rgb(253 186 116);
            border-radius: 999px;
            background: rgb(255 247 237);
            color: rgb(154 52 18);
            font-size: 0.75rem;
            font-weight: 650;
            line-height: 1rem;
            padding: 0.35rem 0.625rem;
        }

        .dark .mqa-correlation-pair {
            border-color: rgb(154 52 18);
            background: rgb(124 45 18 / 0.34);
            color: rgb(254 215 170);
        }

        .mqa-correlation-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .mqa-correlation-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: rgb(75 85 99);
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .dark .mqa-correlation-legend-item {
            color: rgb(209 213 219);
        }

        .mqa-correlation-legend-swatch {
            display: inline-flex;
            width: 1.2rem;
            height: 1.2rem;
            border-radius: 0.35rem;
            border: 1px solid rgb(229 231 235);
        }

        .dark .mqa-correlation-legend-swatch {
            border-color: rgb(55 65 81);
        }

        .mqa-correlation-scroll {
            overflow-x: auto;
        }

        .mqa-correlation-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.8125rem;
            line-height: 1.25rem;
        }

        .mqa-correlation-table th,
        .mqa-correlation-table td {
            border: 1px solid rgb(229 231 235);
            padding: 0.625rem 0.75rem;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .dark .mqa-correlation-table th,
        .dark .mqa-correlation-table td {
            border-color: rgb(55 65 81);
        }

        .mqa-correlation-table thead th,
        .mqa-correlation-row-heading {
            background: rgb(243 244 246);
            color: rgb(55 65 81);
            font-weight: 700;
        }

        .dark .mqa-correlation-table thead th,
        .dark .mqa-correlation-row-heading {
            background: rgb(31 41 55);
            color: rgb(229 231 235);
        }

        .mqa-correlation-cell {
            text-align: center;
            font-weight: 750;
        }

        .mqa-correlation-good {
            background: rgb(220 252 231);
            color: rgb(22 101 52);
        }

        .mqa-correlation-warning {
            background: rgb(254 249 195);
            color: rgb(133 77 14);
        }

        .mqa-correlation-high {
            background: rgb(255 237 213);
            color: rgb(154 52 18);
        }

        .mqa-correlation-critical {
            background: rgb(255 228 230);
            color: rgb(190 18 60);
        }

        .mqa-correlation-inverse {
            background: rgb(239 246 255);
            color: rgb(29 78 216);
        }

        .mqa-correlation-diagonal {
            background: rgb(229 231 235);
            color: rgb(17 24 39);
        }

        .mqa-correlation-empty {
            background: rgb(249 250 251);
            color: rgb(107 114 128);
            font-weight: 600;
        }

        .dark .mqa-correlation-good {
            background: rgb(6 78 59 / 0.42);
            color: rgb(134 239 172);
        }

        .dark .mqa-correlation-warning {
            background: rgb(113 63 18 / 0.42);
            color: rgb(253 224 71);
        }

        .dark .mqa-correlation-high {
            background: rgb(124 45 18 / 0.48);
            color: rgb(253 186 116);
        }

        .dark .mqa-correlation-critical {
            background: rgb(127 29 29 / 0.48);
            color: rgb(253 164 175);
        }

        .dark .mqa-correlation-inverse {
            background: rgb(30 58 138 / 0.38);
            color: rgb(147 197 253);
        }

        .dark .mqa-correlation-diagonal {
            background: rgb(55 65 81);
            color: rgb(249 250 251);
        }

        .dark .mqa-correlation-empty {
            background: rgb(31 41 55 / 0.72);
            color: rgb(156 163 175);
        }

        .mqa-correlation-empty-state {
            border: 1px dashed rgb(209 213 219);
            border-radius: 0.5rem;
            color: rgb(107 114 128);
            font-size: 0.875rem;
            line-height: 1.25rem;
            padding: 1rem;
            text-align: center;
        }

        .dark .mqa-correlation-empty-state {
            border-color: rgb(75 85 99);
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
            <h3 class="mqa-strategy-title">Correlação entre Estratégias</h3>
            <p class="mqa-strategy-description">Comparação por coeficiente de Pearson usando séries agregadas de Profit/Loss líquido.</p>
        </div>

        <div class="mqa-correlation-body">
            <div class="mqa-correlation-toolbar">
                <div class="mqa-correlation-filter-group">
                    <label class="mqa-correlation-field">
                        <span class="mqa-correlation-label">Período de agrupamento</span>
                        <select wire:model.live="correlationPeriod" class="mqa-correlation-select">
                            @foreach (($correlation['period_options'] ?? []) as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="mqa-correlation-field">
                        <span class="mqa-correlation-label">Métrica analisada</span>
                        <select wire:model.live="correlationMetric" class="mqa-correlation-select">
                            @foreach (($correlation['metric_options'] ?? []) as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>

            @if (! ($correlation['has_enough_strategies'] ?? false))
                <div class="mqa-correlation-empty-state">
                    Adicione pelo menos duas estratégias ativas ao portfólio para calcular a correlação.
                </div>
            @else
                <div class="mqa-correlation-summary">
                    <div class="mqa-correlation-summary-card">
                        <div class="mqa-correlation-summary-label">Média abs.</div>
                        <div class="mqa-correlation-summary-value">{{ $correlationSummary['average_absolute_correlation_display'] ?? '-' }}</div>
                        <div class="mqa-correlation-summary-description">Correlação média absoluta</div>
                    </div>

                    <div class="mqa-correlation-summary-card">
                        <div class="mqa-correlation-summary-label">Maior correlação</div>
                        <div class="mqa-correlation-summary-value">{{ $correlationSummary['highest_correlation_display'] ?? '-' }}</div>
                        <div class="mqa-correlation-summary-description">{{ data_get($correlationSummary, 'highest_pair.label', 'Sem pares suficientes') }}</div>
                    </div>

                    <div class="mqa-correlation-summary-card">
                        <div class="mqa-correlation-summary-label">Menor correlação</div>
                        <div class="mqa-correlation-summary-value">{{ $correlationSummary['lowest_correlation_display'] ?? '-' }}</div>
                        <div class="mqa-correlation-summary-description">{{ data_get($correlationSummary, 'lowest_pair.label', 'Sem pares suficientes') }}</div>
                    </div>

                    <div class="mqa-correlation-summary-card">
                        <div class="mqa-correlation-summary-label">Pares &gt; 0.20</div>
                        <div class="mqa-correlation-summary-value">{{ number_format((int) ($correlationSummary['pairs_above_020'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-correlation-summary-description">Acima da faixa ideal</div>
                    </div>

                    <div class="mqa-correlation-summary-card">
                        <div class="mqa-correlation-summary-label">Pares &gt; 0.40</div>
                        <div class="mqa-correlation-summary-value">{{ number_format((int) ($correlationSummary['pairs_above_040'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-correlation-summary-description">Correlação alta</div>
                    </div>

                    <div class="mqa-correlation-summary-card">
                        <div class="mqa-correlation-summary-label">Pares &gt; 0.70</div>
                        <div class="mqa-correlation-summary-value">{{ number_format((int) ($correlationSummary['pairs_above_070'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-correlation-summary-description">Forte redundância</div>
                    </div>
                </div>

                @if (filled(data_get($correlationSummary, 'interpretation.message')))
                    <div class="mqa-correlation-interpretation mqa-correlation-interpretation-{{ data_get($correlationSummary, 'interpretation.type', 'neutral') }}">
                        {{ data_get($correlationSummary, 'interpretation.message') }}
                    </div>
                @endif

                @if (filled($correlationSummary['highlighted_pairs'] ?? []))
                    <div class="mqa-correlation-highlighted">
                        @foreach ($correlationSummary['highlighted_pairs'] as $pair)
                            <span class="mqa-correlation-pair">
                                {{ $pair['label'] ?? '-' }}: {{ $pair['display'] ?? '-' }}
                            </span>
                        @endforeach
                    </div>
                @endif

                <div class="mqa-correlation-legend">
                    @foreach (($correlation['legend'] ?? []) as $legend)
                        <span class="mqa-correlation-legend-item">
                            <span class="mqa-correlation-legend-swatch {{ $legend['class'] ?? '' }}"></span>
                            <span><strong>{{ $legend['label'] ?? '' }}:</strong> {{ $legend['description'] ?? '' }}</span>
                        </span>
                    @endforeach
                </div>

                @if (! ($correlation['has_periods'] ?? false))
                    <div class="mqa-correlation-empty-state">
                        Não há períodos suficientes com trades fechados para calcular correlação.
                    </div>
                @else
                    <div class="mqa-correlation-scroll">
                        <table class="mqa-correlation-table">
                            <colgroup>
                                <col style="width: 240px;">
                                @foreach (($correlation['strategies'] ?? []) as $strategy)
                                    <col style="width: 132px;">
                                @endforeach
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="mqa-left">Estratégia</th>
                                    @foreach (($correlation['strategies'] ?? []) as $strategy)
                                        <th class="mqa-right" title="{{ $strategy['name'] ?? '-' }}">{{ $strategy['name'] ?? '-' }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (($correlation['matrix'] ?? []) as $row)
                                    <tr>
                                        <th class="mqa-left mqa-correlation-row-heading" title="{{ data_get($row, 'strategy.name', '-') }}">
                                            {{ data_get($row, 'strategy.name', '-') }}
                                        </th>

                                        @foreach (($row['cells'] ?? []) as $cell)
                                            <td class="mqa-correlation-cell {{ $cell['class'] ?? 'mqa-correlation-empty' }}" title="{{ $cell['tooltip'] ?? '' }}">
                                                {{ $cell['display'] ?? '-' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </section>

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
