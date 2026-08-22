@php
    use App\Filament\Resources\Strategies\StrategyResource;
    use App\Models\StrategyBacktestExecution;

    $hasTrades = ($metrics['total_trades'] ?? 0) > 0;
    $formatMoney = fn (mixed $value): string => $value === null ? '-' : (((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . 'R$ ' . number_format(abs((float) $value), 2, ',', '.'));
    $formatPercent = fn (mixed $value): string => $value === null ? '-' : number_format((float) $value, 2, ',', '.') . '%';
    $formatRatio = fn (mixed $value): string => $value === null ? '-' : number_format((float) $value, 2, ',', '.');
    $moneyClass = fn (mixed $value): string => match (true) {
        $value === null => 'mqa-result-muted',
        (float) $value > 0 => 'mqa-result-positive',
        (float) $value < 0 => 'mqa-result-negative',
        default => 'mqa-result-muted',
    };
    $statusClass = fn (?string $status): string => match ($status) {
        StrategyBacktestExecution::STATUS_APPROVED_NEXT_STEP, StrategyBacktestExecution::STATUS_SENT_TO_SIMULATOR => 'mqa-execution-status-success',
        StrategyBacktestExecution::STATUS_WATCHING, StrategyBacktestExecution::STATUS_INCONCLUSIVE => 'mqa-execution-status-warning',
        StrategyBacktestExecution::STATUS_REJECTED, StrategyBacktestExecution::STATUS_ARCHIVED => 'mqa-execution-status-danger',
        default => 'mqa-execution-status-neutral',
    };
@endphp

@once
    <style>
        .mqa-execution-card {
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            background: rgb(255 255 255);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .dark .mqa-execution-card {
            border-color: rgb(31 41 55);
            background: rgb(17 24 39);
        }

        .mqa-execution-header {
            border-bottom: 1px solid rgb(229 231 235);
            padding: 0.875rem 1rem;
        }

        .dark .mqa-execution-header {
            border-color: rgb(31 41 55);
        }

        .mqa-execution-title {
            color: rgb(17 24 39);
            font-size: 0.875rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .dark .mqa-execution-title {
            color: rgb(255 255 255);
        }

        .mqa-execution-description {
            margin-top: 0.25rem;
            color: rgb(107 114 128);
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .dark .mqa-execution-description {
            color: rgb(156 163 175);
        }

        .mqa-execution-scroll {
            overflow-x: auto;
            padding: 1rem;
        }

        .mqa-execution-table {
            width: 100%;
            min-width: 1660px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.8125rem;
            line-height: 1.25rem;
        }

        .mqa-execution-table th,
        .mqa-execution-table td {
            border: 1px solid rgb(229 231 235);
            padding: 0.625rem 0.75rem;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .dark .mqa-execution-table th,
        .dark .mqa-execution-table td {
            border-color: rgb(55 65 81);
        }

        .mqa-execution-table thead th {
            background: rgb(243 244 246);
            color: rgb(55 65 81);
            font-weight: 700;
        }

        .dark .mqa-execution-table thead th {
            background: rgb(31 41 55);
            color: rgb(229 231 235);
        }

        .mqa-execution-table tbody tr:nth-child(even) td {
            background: rgb(249 250 251);
        }

        .dark .mqa-execution-table tbody tr:nth-child(even) td {
            background: rgb(15 23 42);
        }

        .mqa-result-positive {
            color: rgb(4 120 87);
            font-weight: 650;
        }

        .mqa-result-negative {
            color: rgb(190 18 60);
            font-weight: 650;
        }

        .mqa-result-muted {
            color: rgb(107 114 128);
        }

        .dark .mqa-result-positive {
            color: rgb(110 231 183);
        }

        .dark .mqa-result-negative {
            color: rgb(253 164 175);
        }

        .dark .mqa-result-muted {
            color: rgb(156 163 175);
        }

        .mqa-execution-status {
            display: inline-flex;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1rem;
        }

        .mqa-execution-status-success {
            background: rgb(236 253 245);
            color: rgb(4 120 87);
        }

        .mqa-execution-status-warning {
            background: rgb(254 249 195);
            color: rgb(133 77 14);
        }

        .mqa-execution-status-danger {
            background: rgb(255 241 242);
            color: rgb(190 18 60);
        }

        .mqa-execution-status-neutral {
            background: rgb(243 244 246);
            color: rgb(75 85 99);
        }

        .dark .mqa-execution-status-success {
            background: rgb(6 78 59 / 0.32);
            color: rgb(110 231 183);
        }

        .dark .mqa-execution-status-warning {
            background: rgb(113 63 18 / 0.35);
            color: rgb(253 224 71);
        }

        .dark .mqa-execution-status-danger {
            background: rgb(127 29 29 / 0.35);
            color: rgb(253 164 175);
        }

        .dark .mqa-execution-status-neutral {
            background: rgb(31 41 55);
            color: rgb(209 213 219);
        }

        .mqa-left {
            text-align: left;
        }

        .mqa-right {
            text-align: right;
        }

        .mqa-wrap {
            overflow-wrap: anywhere;
            white-space: normal !important;
        }
    </style>
@endonce

<div class="space-y-6">
    <div class="mqa-execution-card">
        <div class="mqa-execution-header">
            <div class="mqa-execution-title">Execuções da estratégia</div>
            <div class="mqa-execution-description">Comparação inicial por identificador de backtest, preservando a análise agregada abaixo.</div>
        </div>
        <p class="mqa-scroll-hint">Deslize a tabela para ver todas as métricas.</p>
        <div class="mqa-execution-scroll" tabindex="0" role="region" aria-label="Tabela de execuções da estratégia">
            <table class="mqa-execution-table">
                <colgroup>
                    <col style="width: 280px;">
                    <col style="width: 170px;">
                    <col style="width: 130px;">
                    <col style="width: 230px;">
                    <col style="width: 130px;">
                    <col style="width: 120px;">
                    <col style="width: 110px;">
                    <col style="width: 120px;">
                    <col style="width: 90px;">
                    <col style="width: 110px;">
                    <col style="width: 110px;">
                    <col style="width: 180px;">
                    <col style="width: 80px;">
                </colgroup>
                <thead>
                    <tr>
                        <th class="mqa-left">Execução</th>
                        <th class="mqa-left">Tipo</th>
                        <th class="mqa-left">Versão</th>
                        <th class="mqa-left">Período</th>
                        <th class="mqa-right">Lucro líquido</th>
                        <th class="mqa-right">Drawdown</th>
                        <th class="mqa-right">PF</th>
                        <th class="mqa-right">Lucro/DD</th>
                        <th class="mqa-right">Ops.</th>
                        <th class="mqa-right">Meses +</th>
                        <th class="mqa-right">Seq. perdas</th>
                        <th class="mqa-left">Status</th>
                        <th class="mqa-right">Alertas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($executions as $executionRow)
                        <tr>
                            <td class="mqa-left mqa-wrap">
                                <a href="{{ StrategyResource::getUrl('execution', ['record' => $strategy, 'backtestId' => rawurlencode($executionRow['backtest_id'])]) }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $executionRow['name'] }}
                                </a>
                            </td>
                            <td class="mqa-left">{{ $executionRow['execution_type_label'] }}</td>
                            <td class="mqa-left">{{ $executionRow['strategy_version'] ?? '-' }}</td>
                            <td class="mqa-left mqa-wrap">{{ $executionRow['period'] }}</td>
                            <td class="mqa-right {{ $moneyClass($executionRow['net_profit']) }}">{{ $formatMoney($executionRow['net_profit']) }}</td>
                            <td class="mqa-right mqa-result-negative">{{ $formatMoney($executionRow['max_drawdown']) }}</td>
                            <td class="mqa-right">{{ $executionRow['profit_factor'] === null ? ($executionRow['profit_factor_label'] ?? '-') : $formatRatio($executionRow['profit_factor']) }}</td>
                            <td class="mqa-right">{{ $formatRatio($executionRow['profit_drawdown_ratio']) }}</td>
                            <td class="mqa-right">{{ number_format((int) $executionRow['total_trades'], 0, ',', '.') }}</td>
                            <td class="mqa-right">{{ $formatPercent($executionRow['positive_month_rate']) }}</td>
                            <td class="mqa-right">{{ number_format((int) $executionRow['max_losing_streak'], 0, ',', '.') }}</td>
                            <td class="mqa-left"><span class="mqa-execution-status {{ $statusClass($executionRow['status']) }}">{{ $executionRow['status_label'] }}</span></td>
                            <td class="mqa-right">{{ number_format((int) $executionRow['alerts_count'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="mqa-left mqa-result-muted">Nenhuma execução encontrada. Importe um CSV ou XLSX com identificador de backtest.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

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
