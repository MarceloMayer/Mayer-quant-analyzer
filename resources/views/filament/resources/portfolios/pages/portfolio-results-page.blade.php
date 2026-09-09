@php
    $formatMoney = fn (mixed $value): string => 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $formatSignedMoney = fn (mixed $value): string => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . $formatMoney($value);
    $formatPercent = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
    $formatWeight = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . 'x';
    $formatDate = function (mixed $value): string {
        if (blank($value)) {
            return '-';
        }

        try {
            return \Carbon\CarbonImmutable::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return '-';
        }
    };
    $assetLabels = \App\Models\Strategy::assetOptions();
    $consolidatedTrades = $metrics['consolidated_trades'] ?? [];
    $strategySummaries = $metrics['strategy_summaries'] ?? [];
    $dailyPerformance = $metrics['daily_performance'] ?? [];
    $dailyTable = $dailyTable ?? ['rows' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => 15];
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

        .mqa-daily-body {
            padding: 1rem;
        }

        .mqa-daily-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 768px) {
            .mqa-daily-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .mqa-daily-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }

        .mqa-daily-card {
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            background: rgb(249 250 251);
            padding: 0.875rem;
        }

        .dark .mqa-daily-card {
            border-color: rgb(55 65 81);
            background: rgb(15 23 42);
        }

        .mqa-daily-label {
            color: rgb(107 114 128);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            line-height: 1rem;
            text-transform: uppercase;
        }

        .dark .mqa-daily-label {
            color: rgb(156 163 175);
        }

        .mqa-daily-value {
            margin-top: 0.35rem;
            color: rgb(17 24 39);
            font-size: 1.25rem;
            font-variant-numeric: tabular-nums;
            font-weight: 750;
            line-height: 1.75rem;
        }

        .dark .mqa-daily-value {
            color: rgb(249 250 251);
        }

        .mqa-daily-description {
            margin-top: 0.25rem;
            color: rgb(107 114 128);
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .dark .mqa-daily-description {
            color: rgb(156 163 175);
        }

        .mqa-daily-positive {
            color: rgb(4 120 87);
        }

        .mqa-daily-negative {
            color: rgb(190 18 60);
        }

        .mqa-daily-neutral {
            color: rgb(107 114 128);
        }

        .dark .mqa-daily-positive {
            color: rgb(110 231 183);
        }

        .dark .mqa-daily-negative {
            color: rgb(253 164 175);
        }

        .dark .mqa-daily-neutral {
            color: rgb(156 163 175);
        }

        .mqa-daily-bar {
            display: flex;
            overflow: hidden;
            height: 0.85rem;
            border-radius: 999px;
            background: rgb(229 231 235);
            margin-bottom: 1rem;
        }

        .dark .mqa-daily-bar {
            background: rgb(31 41 55);
        }

        .mqa-daily-bar-positive {
            background: rgb(16 185 129);
        }

        .mqa-daily-bar-negative {
            background: rgb(244 63 94);
        }

        .mqa-daily-bar-neutral {
            background: rgb(156 163 175);
        }

        .mqa-daily-table {
            width: 100%;
            min-width: 560px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.8125rem;
            line-height: 1.25rem;
        }

        .mqa-daily-table th,
        .mqa-daily-table td {
            border: 1px solid rgb(229 231 235);
            padding: 0.625rem 0.75rem;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .dark .mqa-daily-table th,
        .dark .mqa-daily-table td {
            border-color: rgb(55 65 81);
        }

        .mqa-daily-table thead th {
            background: rgb(243 244 246);
            color: rgb(55 65 81);
            font-weight: 700;
        }

        .dark .mqa-daily-table thead th {
            background: rgb(31 41 55);
            color: rgb(229 231 235);
        }

        .mqa-daily-table tbody tr:nth-child(even) td {
            background: rgb(249 250 251);
        }

        .dark .mqa-daily-table tbody tr:nth-child(even) td {
            background: rgb(15 23 42);
        }

        .mqa-daily-badge {
            display: inline-flex;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 650;
            line-height: 1rem;
        }

        .mqa-daily-badge-positive {
            background: rgb(236 253 245);
            color: rgb(4 120 87);
        }

        .mqa-daily-badge-negative {
            background: rgb(255 241 242);
            color: rgb(190 18 60);
        }

        .mqa-daily-badge-neutral {
            background: rgb(243 244 246);
            color: rgb(75 85 99);
        }

        .dark .mqa-daily-badge-positive {
            background: rgb(6 78 59 / 0.32);
            color: rgb(110 231 183);
        }

        .dark .mqa-daily-badge-negative {
            background: rgb(127 29 29 / 0.35);
            color: rgb(253 164 175);
        }

        .dark .mqa-daily-badge-neutral {
            background: rgb(55 65 81);
            color: rgb(209 213 219);
        }

        .mqa-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .mqa-pagination-info {
            color: rgb(107 114 128);
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .dark .mqa-pagination-info {
            color: rgb(156 163 175);
        }

        .mqa-pagination-controls {
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        .mqa-pagination-page {
            color: rgb(75 85 99);
            font-size: 0.75rem;
            font-weight: 650;
            line-height: 1rem;
            white-space: nowrap;
        }

        .dark .mqa-pagination-page {
            color: rgb(209 213 219);
        }

        .mqa-pagination-button {
            border: 1px solid rgb(209 213 219);
            border-radius: 0.5rem;
            background: rgb(255 255 255);
            color: rgb(17 24 39);
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 650;
            line-height: 1rem;
            padding: 0.4rem 0.75rem;
        }

        .mqa-pagination-button:hover:not(:disabled) {
            background: rgb(243 244 246);
        }

        .mqa-pagination-button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .dark .mqa-pagination-button {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: rgb(249 250 251);
        }

        .dark .mqa-pagination-button:hover:not(:disabled) {
            background: rgb(55 65 81);
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

        @media (max-width: 640px) {
            .mqa-correlation-summary {
                gap: 1rem;
            }
        }
    </style>
@endonce

<div class="space-y-6">
    @livewire(\App\Filament\Widgets\PortfolioMetricsOverview::class, [
        'metrics' => $metrics,
    ])

    @if (($weightSuggestion['ok'] ?? false) === true)
        @php
            $wsMetricRows = [
                ['key' => 'ulcer_index', 'label' => 'Ulcer Index', 'fmt' => 'ratio', 'lower_better' => true],
                ['key' => 'positive_months_percent', 'label' => 'Meses positivos', 'fmt' => 'percent', 'lower_better' => false],
                ['key' => 'max_drawdown', 'label' => 'Drawdown máximo', 'fmt' => 'money_neg', 'lower_better' => true],
                ['key' => 'net_profit', 'label' => 'Resultado líquido', 'fmt' => 'money', 'lower_better' => false],
                ['key' => 'net_profit_to_drawdown', 'label' => 'Lucro / Drawdown', 'fmt' => 'ratio', 'lower_better' => false],
                ['key' => 'equity_r2', 'label' => 'R² da curva', 'fmt' => 'ratio4', 'lower_better' => false],
            ];
            $wsFmt = function (string $fmt, mixed $value) use ($formatMoney, $formatSignedMoney, $formatPercent): string {
                return match ($fmt) {
                    'money' => $formatSignedMoney($value),
                    'money_neg' => (float) $value > 0 ? '-' . $formatMoney($value) : $formatMoney(0),
                    'percent' => $formatPercent($value),
                    'ratio4' => number_format((float) $value, 4, ',', '.'),
                    default => number_format((float) $value, 2, ',', '.'),
                };
            };
            $wsCurrent = $weightSuggestion['current_metrics'] ?? [];
            $wsSuggested = $weightSuggestion['suggested_metrics'] ?? [];
        @endphp

        <section class="mqa-strategy-card mqa-wopt">
            <div class="mqa-strategy-header mqa-wopt-header">
                <div>
                    <h3 class="mqa-strategy-title">Sugestão de pesos</h3>
                    <p class="mqa-strategy-description">
                        Objetivo: <strong>{{ $weightSuggestion['objective_label'] ?? '-' }}</strong>
                        &middot; {{ number_format((int) ($weightSuggestion['evaluations'] ?? 0), 0, ',', '.') }} combinações avaliadas
                    </p>
                </div>
                <div class="mqa-wopt-actions">
                    <button type="button" class="mqa-wopt-btn mqa-wopt-btn-ghost" wire:click="discardWeightSuggestion">Descartar</button>
                    <button
                        type="button"
                        class="mqa-wopt-btn mqa-wopt-btn-primary"
                        wire:click="applyWeights"
                        wire:loading.attr="disabled"
                        wire:target="applyWeights"
                        @if (($weightSuggestion['changed'] ?? false) !== true) disabled @endif
                    >
                        <span wire:loading.remove wire:target="applyWeights">Aplicar pesos</span>
                        <span wire:loading wire:target="applyWeights">Aplicando...</span>
                    </button>
                </div>
            </div>

            <div class="mqa-wopt-body">
                @if (($weightSuggestion['changed'] ?? false) !== true)
                    <p class="mqa-wopt-note">Os pesos atuais já são os melhores encontrados para esse objetivo — nada a aplicar.</p>
                @endif

                <div class="mqa-wopt-grid">
                    <div class="mqa-wopt-panel">
                        <table class="mqa-wopt-table">
                            <thead>
                                <tr>
                                    <th class="mqa-left">Estratégia</th>
                                    <th class="mqa-right">Peso atual</th>
                                    <th class="mqa-right">Peso sugerido</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($weightSuggestion['strategies'] ?? [] as $ws)
                                    @php $wsChanged = abs((float) $ws['current_weight'] - (float) $ws['suggested_weight']) >= 0.01; @endphp
                                    <tr>
                                        <td class="mqa-left">{{ $ws['name'] }}</td>
                                        <td class="mqa-right">{{ $formatWeight($ws['current_weight']) }}</td>
                                        <td class="mqa-right {{ $wsChanged ? 'mqa-wopt-changed' : '' }}">{{ $formatWeight($ws['suggested_weight']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mqa-wopt-panel">
                        <table class="mqa-wopt-table">
                            <thead>
                                <tr>
                                    <th class="mqa-left">Métrica</th>
                                    <th class="mqa-right">Atual</th>
                                    <th class="mqa-right">Sugerido</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($wsMetricRows as $row)
                                    @php
                                        $cur = (float) ($wsCurrent[$row['key']] ?? 0);
                                        $sug = (float) ($wsSuggested[$row['key']] ?? 0);
                                        $delta = round($sug - $cur, 4);
                                        $moved = abs($delta) < 1e-9 ? 0 : ($delta > 0 ? 1 : -1);
                                        $better = $moved === 0 ? null : ($row['lower_better'] ? $delta < 0 : $delta > 0);
                                    @endphp
                                    <tr>
                                        <td class="mqa-left">{{ $row['label'] }}</td>
                                        <td class="mqa-right">{{ $wsFmt($row['fmt'], $cur) }}</td>
                                        <td class="mqa-right {{ $better === true ? 'mqa-wopt-up' : ($better === false ? 'mqa-wopt-down' : '') }}">
                                            {{ $wsFmt($row['fmt'], $sug) }}
                                            @if ($moved === 1) &uarr; @elseif ($moved === -1) &darr; @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        @once
            <style>
                .mqa-wopt-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
                .mqa-wopt-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
                .mqa-wopt-btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.9rem; font-size: 0.8125rem; font-weight: 600; border-radius: 0.5rem; border: none; cursor: pointer; transition: background .15s, opacity .15s; }
                .mqa-wopt-btn:disabled { opacity: .5; cursor: not-allowed; }
                .mqa-wopt-btn-primary { background: rgb(59 130 246); color: #fff; }
                .mqa-wopt-btn-primary:hover:not(:disabled) { background: rgb(37 99 235); }
                .mqa-wopt-btn-ghost { background: transparent; color: rgb(107 114 128); border: 1px solid rgb(229 231 235); }
                .mqa-wopt-btn-ghost:hover { background: rgb(243 244 246); }
                .dark .mqa-wopt-btn-ghost { border-color: rgb(55 65 81); color: rgb(156 163 175); }
                .dark .mqa-wopt-btn-ghost:hover { background: rgb(31 41 55); }
                .mqa-wopt-body { padding: 1rem; }
                .mqa-wopt-note { margin: 0 0 0.75rem; font-size: 0.8125rem; color: rgb(133 77 14); background: rgb(254 249 195); border: 1px solid rgb(253 224 71); border-radius: 0.5rem; padding: 0.5rem 0.75rem; }
                .dark .mqa-wopt-note { color: rgb(253 224 71); background: rgb(113 63 18 / .3); border-color: rgb(113 63 18); }
                .mqa-wopt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
                @media (max-width: 820px) { .mqa-wopt-grid { grid-template-columns: 1fr; } }
                .mqa-wopt-panel { border: 1px solid rgb(229 231 235); border-radius: 0.5rem; overflow: hidden; }
                .dark .mqa-wopt-panel { border-color: rgb(55 65 81); }
                .mqa-wopt-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; font-variant-numeric: tabular-nums; }
                .mqa-wopt-table th { background: rgb(249 250 251); color: rgb(107 114 128); font-weight: 600; padding: 0.5rem 0.75rem; }
                .dark .mqa-wopt-table th { background: rgb(31 41 55); color: rgb(156 163 175); }
                .mqa-wopt-table td { padding: 0.5rem 0.75rem; border-top: 1px solid rgb(243 244 246); color: rgb(55 65 81); }
                .dark .mqa-wopt-table td { border-color: rgb(31 41 55); color: rgb(209 213 219); }
                .mqa-wopt-table .mqa-left { text-align: left; }
                .mqa-wopt-table .mqa-right { text-align: right; }
                .mqa-wopt-changed { color: rgb(37 99 235); font-weight: 700; }
                .dark .mqa-wopt-changed { color: rgb(96 165 250); }
                .mqa-wopt-up { color: rgb(22 163 74); font-weight: 700; }
                .mqa-wopt-down { color: rgb(220 38 38); font-weight: 700; }
                .dark .mqa-wopt-up { color: rgb(74 222 128); }
                .dark .mqa-wopt-down { color: rgb(248 113 113); }
            </style>
        @endonce
    @endif

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
            <h3 class="mqa-strategy-title">Dias positivos vs negativos</h3>
            <p class="mqa-strategy-description">Comparação dos dias operacionais do portfólio, consolidando o resultado líquido ponderado dos trades fechados em cada data.</p>
        </div>

        <div class="mqa-daily-body">
            @if (! ($dailyPerformance['has_data'] ?? false))
                <div class="mqa-correlation-empty-state">
                    Nenhum dia operacional encontrado para calcular a comparação.
                </div>
            @else
                <div class="mqa-daily-grid">
                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Dias positivos</div>
                        <div class="mqa-daily-value mqa-daily-positive">{{ number_format((int) ($dailyPerformance['positive_days'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-daily-description">{{ $formatPercent($dailyPerformance['positive_day_rate'] ?? 0) }} dos dias operacionais</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Dias negativos</div>
                        <div class="mqa-daily-value mqa-daily-negative">{{ number_format((int) ($dailyPerformance['negative_days'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-daily-description">{{ $formatPercent($dailyPerformance['negative_day_rate'] ?? 0) }} dos dias operacionais</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Dias neutros</div>
                        <div class="mqa-daily-value mqa-daily-neutral">{{ number_format((int) ($dailyPerformance['neutral_days'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-daily-description">{{ $formatPercent($dailyPerformance['neutral_day_rate'] ?? 0) }} dos dias operacionais</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Média dia positivo</div>
                        <div class="mqa-daily-value mqa-daily-positive">{{ $formatSignedMoney($dailyPerformance['average_positive_day'] ?? 0) }}</div>
                        <div class="mqa-daily-description">Média apenas dos dias acima de zero</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Média dia negativo</div>
                        <div class="mqa-daily-value mqa-daily-negative">{{ $formatSignedMoney($dailyPerformance['average_negative_day'] ?? 0) }}</div>
                        <div class="mqa-daily-description">Média apenas dos dias abaixo de zero</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Relação positivo/negativo</div>
                        <div class="mqa-daily-value">
                            {{ ($dailyPerformance['positive_negative_ratio'] ?? null) === null ? 'Sem perdas' : number_format((float) $dailyPerformance['positive_negative_ratio'], 2, ',', '.') . 'x' }}
                        </div>
                        <div class="mqa-daily-description">Quantidade de dias positivos por dia negativo</div>
                    </div>
                </div>

                <div class="mqa-daily-bar" title="Distribuição dos dias operacionais">
                    <span class="mqa-daily-bar-positive" style="width: {{ (float) ($dailyPerformance['positive_day_rate'] ?? 0) }}%;"></span>
                    <span class="mqa-daily-bar-negative" style="width: {{ (float) ($dailyPerformance['negative_day_rate'] ?? 0) }}%;"></span>
                    <span class="mqa-daily-bar-neutral" style="width: {{ (float) ($dailyPerformance['neutral_day_rate'] ?? 0) }}%;"></span>
                </div>

                <div class="mqa-daily-grid">
                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Melhor dia</div>
                        <div class="mqa-daily-value mqa-daily-positive">{{ $formatSignedMoney(data_get($dailyPerformance, 'best_day.net_profit', 0)) }}</div>
                        <div class="mqa-daily-description">{{ $formatDate(data_get($dailyPerformance, 'best_day.date')) }}</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Pior dia</div>
                        <div class="mqa-daily-value mqa-daily-negative">{{ $formatSignedMoney(data_get($dailyPerformance, 'worst_day.net_profit', 0)) }}</div>
                        <div class="mqa-daily-description">{{ $formatDate(data_get($dailyPerformance, 'worst_day.date')) }}</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Resultado dos dias positivos</div>
                        <div class="mqa-daily-value mqa-daily-positive">{{ $formatSignedMoney($dailyPerformance['positive_days_net_profit'] ?? 0) }}</div>
                        <div class="mqa-daily-description">Soma dos dias positivos</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Resultado dos dias negativos</div>
                        <div class="mqa-daily-value mqa-daily-negative">{{ $formatSignedMoney($dailyPerformance['negative_days_net_profit'] ?? 0) }}</div>
                        <div class="mqa-daily-description">Soma dos dias negativos</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Total de dias operacionais</div>
                        <div class="mqa-daily-value">{{ number_format((int) ($dailyPerformance['total_days'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-daily-description">Dias com pelo menos um trade fechado</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Sequência atual</div>
                        @php
                            $currentStreakType = $dailyPerformance['current_streak_type'] ?? null;
                            $currentStreakCount = (int) ($dailyPerformance['current_streak_count'] ?? 0);
                            $currentStreakClass = match ($currentStreakType) {
                                'positive' => 'mqa-daily-positive',
                                'negative' => 'mqa-daily-negative',
                                default => 'mqa-daily-neutral',
                            };
                        @endphp
                        <div class="mqa-daily-value {{ $currentStreakClass }}">{{ $currentStreakCount > 0 ? $currentStreakCount : '-' }}</div>
                        <div class="mqa-daily-description">
                            {{ match ($currentStreakType) {
                                'positive' => 'dias positivos seguidos até o último dia',
                                'negative' => 'dias negativos seguidos até o último dia',
                                default => 'Sem sequência em andamento',
                            } }}
                        </div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Maior sequência positiva</div>
                        <div class="mqa-daily-value mqa-daily-positive">{{ number_format((int) ($dailyPerformance['max_positive_streak'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-daily-description">Dias positivos consecutivos, no máximo</div>
                    </div>

                    <div class="mqa-daily-card">
                        <div class="mqa-daily-label">Maior sequência negativa</div>
                        <div class="mqa-daily-value mqa-daily-negative">{{ number_format((int) ($dailyPerformance['max_negative_streak'] ?? 0), 0, ',', '.') }}</div>
                        <div class="mqa-daily-description">Dias negativos consecutivos, no máximo</div>
                    </div>
                </div>

                <div class="mqa-correlation-toolbar">
                    <div class="mqa-correlation-filter-group">
                        <label class="mqa-correlation-field">
                            <span class="mqa-correlation-label">Classificação</span>
                            <select wire:model.live="dailyFilter" class="mqa-correlation-select">
                                <option value="all">Todos os dias</option>
                                <option value="positive">Somente positivos</option>
                                <option value="negative">Somente negativos</option>
                                <option value="neutral">Somente neutros</option>
                            </select>
                        </label>

                        <label class="mqa-correlation-field">
                            <span class="mqa-correlation-label">Dias por página</span>
                            <select wire:model.live="dailyPerPage" class="mqa-correlation-select">
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="60">60</option>
                                <option value="9999">Todos</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="mqa-strategy-scroll" tabindex="0" role="region" aria-label="Tabela de resultado diário do portfólio">
                    <table class="mqa-daily-table">
                        <colgroup>
                            <col style="width: 130px;">
                            <col style="width: 140px;">
                            <col style="width: 120px;">
                            <col style="width: 160px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="mqa-left">Data</th>
                                <th class="mqa-right">Resultado</th>
                                <th class="mqa-right">Trades</th>
                                <th class="mqa-left">Classificação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dailyTable['rows'] as $day)
                                @php
                                    $classification = $day['classification'] ?? 'neutral';
                                    $classificationLabel = match ($classification) {
                                        'positive' => 'Positivo',
                                        'negative' => 'Negativo',
                                        default => 'Neutro',
                                    };
                                    $profitClass = match (true) {
                                        (float) ($day['net_profit'] ?? 0) > 0 => 'mqa-profit-positive',
                                        (float) ($day['net_profit'] ?? 0) < 0 => 'mqa-profit-negative',
                                        default => 'mqa-profit-neutral',
                                    };
                                @endphp

                                <tr>
                                    <td class="mqa-left">{{ $formatDate($day['date'] ?? null) }}</td>
                                    <td class="mqa-right {{ $profitClass }}">{{ $formatSignedMoney($day['net_profit'] ?? 0) }}</td>
                                    <td class="mqa-right">{{ number_format((int) ($day['trades'] ?? 0), 0, ',', '.') }}</td>
                                    <td class="mqa-left">
                                        <span class="mqa-daily-badge mqa-daily-badge-{{ $classification }}">{{ $classificationLabel }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="mqa-left" colspan="4">Nenhum dia encontrado para o filtro selecionado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mqa-pagination">
                    <span class="mqa-pagination-info">
                        @if ($dailyTable['total'] > 0)
                            Mostrando {{ number_format((($dailyTable['current_page'] - 1) * $dailyTable['per_page']) + 1, 0, ',', '.') }}–{{ number_format(min($dailyTable['current_page'] * $dailyTable['per_page'], $dailyTable['total']), 0, ',', '.') }} de {{ number_format($dailyTable['total'], 0, ',', '.') }} dias
                        @else
                            Nenhum dia para exibir
                        @endif
                    </span>
                    <div class="mqa-pagination-controls">
                        <button
                            type="button"
                            wire:click="previousDailyPage"
                            @disabled($dailyTable['current_page'] <= 1)
                            class="mqa-pagination-button"
                        >Anterior</button>
                        <span class="mqa-pagination-page">Página {{ $dailyTable['current_page'] }} de {{ $dailyTable['last_page'] }}</span>
                        <button
                            type="button"
                            wire:click="nextDailyPage"
                            @disabled($dailyTable['current_page'] >= $dailyTable['last_page'])
                            class="mqa-pagination-button"
                        >Próxima</button>
                    </div>
                </div>
            @endif
        </div>
    </section>

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
                    <p class="mqa-scroll-hint">Deslize a tabela para comparar todas as estratégias.</p>
                    <div class="mqa-correlation-scroll" tabindex="0" role="region" aria-label="Matriz de correlação entre estratégias">
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

        <p class="mqa-scroll-hint">Deslize a tabela para ver todos os indicadores.</p>
        <div class="mqa-strategy-scroll" tabindex="0" role="region" aria-label="Tabela de estratégias do portfólio">
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
