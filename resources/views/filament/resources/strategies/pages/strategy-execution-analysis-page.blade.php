@php
    use App\Models\Strategy;
    use App\Models\StrategyBacktestExecution;
    use Carbon\CarbonImmutable;
    use Carbon\CarbonInterface;

    $formatMoney = fn (mixed $value): string => $value === null ? 'Indisponível' : 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $formatSignedMoney = fn (mixed $value): string => $value === null ? 'Indisponível' : (((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . $formatMoney($value));
    $formatPercent = fn (mixed $value): string => $value === null ? 'Indisponível' : number_format((float) $value, 2, ',', '.') . '%';
    $formatRatio = fn (mixed $value): string => $value === null ? 'Indisponível' : number_format((float) $value, 2, ',', '.');
    $formatNumber = fn (mixed $value, int $decimals = 2): string => $value === null ? 'Indisponível' : number_format((float) $value, $decimals, ',', '.');
    $formatDate = function (mixed $value, bool $withTime = false): string {
        if ($value instanceof CarbonInterface) {
            return $value->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
        }

        if (blank($value)) {
            return '-';
        }

        try {
            return CarbonImmutable::parse((string) $value)->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (Throwable) {
            return '-';
        }
    };
    $moneyClass = fn (mixed $value): string => match (true) {
        $value === null => 'mqa-muted',
        (float) $value > 0 => 'mqa-positive',
        (float) $value < 0 => 'mqa-negative',
        default => 'mqa-muted',
    };
    $statusClass = fn (?string $status): string => match ($status) {
        StrategyBacktestExecution::STATUS_APPROVED_NEXT_STEP, StrategyBacktestExecution::STATUS_SENT_TO_SIMULATOR => 'mqa-status-success',
        StrategyBacktestExecution::STATUS_WATCHING, StrategyBacktestExecution::STATUS_INCONCLUSIVE => 'mqa-status-warning',
        StrategyBacktestExecution::STATUS_REJECTED, StrategyBacktestExecution::STATUS_ARCHIVED => 'mqa-status-danger',
        default => 'mqa-status-neutral',
    };
    $alertClass = fn (string $level): string => match ($level) {
        'danger' => 'mqa-alert-danger',
        'warning' => 'mqa-alert-warning',
        'info' => 'mqa-alert-info',
        default => 'mqa-alert-info',
    };
    $classification = $metrics['classification'] ?? [];
    $executionInfo = $metrics['execution'] ?? [];
    $parameters = $executionInfo['parameters'] ?? [];
    $costNotes = data_get($executionInfo, 'costs.observacoes');
    $periodStart = data_get($metrics, 'period.execution_start') ?: data_get($metrics, 'period.first_trade_date_label');
    $periodEnd = data_get($metrics, 'period.execution_end') ?: data_get($metrics, 'period.last_trade_date_label');
    $cards = [
        ['label' => 'Lucro líquido', 'value' => $formatSignedMoney($metrics['net_profit'] ?? null), 'class' => $moneyClass($metrics['net_profit'] ?? null), 'note' => $formatPercent($metrics['return_percent'] ?? null) . ' sobre o capital inicial'],
        ['label' => 'Drawdown máximo', 'value' => $formatMoney($metrics['max_drawdown'] ?? null), 'class' => 'mqa-negative', 'note' => $formatPercent($metrics['max_drawdown_percent'] ?? null) . ' de saldo'],
        ['label' => 'Profit factor', 'value' => ($metrics['profit_factor'] ?? null) === null ? ($metrics['profit_factor_label'] ?? 'Indisponível') : $formatRatio($metrics['profit_factor']), 'class' => (($metrics['profit_factor'] ?? 0) >= 1 ? 'mqa-positive' : 'mqa-muted'), 'note' => 'Lucro bruto / prejuízo bruto'],
        ['label' => 'Resultado médio', 'value' => $formatSignedMoney($metrics['average_trade'] ?? null), 'class' => $moneyClass($metrics['average_trade'] ?? null), 'note' => 'Por operação fechada'],
        ['label' => 'Relação ganho/perda', 'value' => $formatRatio($metrics['gain_loss_ratio'] ?? null), 'class' => (($metrics['gain_loss_ratio'] ?? 0) >= 1 ? 'mqa-positive' : 'mqa-muted'), 'note' => 'Média ganhadora / média perdedora'],
        ['label' => 'Operações', 'value' => number_format((int) ($metrics['total_trades'] ?? 0), 0, ',', '.'), 'class' => 'mqa-neutral', 'note' => 'Taxa de acerto: ' . $formatPercent($metrics['win_rate'] ?? null)],
        ['label' => 'Maior sequência de perdas', 'value' => number_format((int) data_get($metrics, 'streaks.max_losing.count', 0), 0, ',', '.'), 'class' => data_get($metrics, 'streaks.max_losing.count', 0) > 0 ? 'mqa-negative' : 'mqa-muted', 'note' => 'Prejuízo: ' . $formatMoney(data_get($metrics, 'streaks.max_losing.amount'))],
        ['label' => 'Lucro / drawdown', 'value' => $formatRatio($metrics['profit_drawdown_ratio'] ?? null), 'class' => (($metrics['profit_drawdown_ratio'] ?? 0) >= 1 ? 'mqa-positive' : 'mqa-muted'), 'note' => 'Versão absoluta'],
        ['label' => 'Custos informados', 'value' => $formatSignedMoney($metrics['costs_total'] ?? null), 'class' => $moneyClass($metrics['costs_total'] ?? null), 'note' => ($metrics['costs_available'] ?? false) ? 'Comissão + swap importados' : 'Sem custos nos dados'],
        ['label' => 'Meses positivos', 'value' => $formatPercent(data_get($metrics, 'monthly_summary.positive_month_rate')), 'class' => ((float) data_get($metrics, 'monthly_summary.positive_month_rate', 0) >= 50 ? 'mqa-positive' : 'mqa-muted'), 'note' => data_get($metrics, 'monthly_summary.positive_months', 0) . ' positivos / ' . data_get($metrics, 'monthly_summary.active_months', 0) . ' com operações'],
    ];
@endphp

@once
    <style>
        .mqa-page {
            display: grid;
            gap: 1rem;
            min-width: 0;
        }

        .mqa-page,
        .mqa-page * {
            box-sizing: border-box;
        }

        .mqa-page > * {
            max-width: 100%;
            min-width: 0;
            width: 100%;
        }

        .mqa-card {
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            background: rgb(255 255 255);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            overflow: hidden;
            inline-size: 100%;
            justify-self: stretch;
            max-inline-size: 100%;
            min-width: 0;
            width: 100%;
        }

        .dark .mqa-card {
            border-color: rgb(31 41 55);
            background: rgb(17 24 39);
        }

        .mqa-card-body {
            padding: 1rem;
        }

        .mqa-card-header {
            border-bottom: 1px solid rgb(229 231 235);
            padding: 0.875rem 1rem;
        }

        .dark .mqa-card-header {
            border-color: rgb(31 41 55);
        }

        .mqa-title {
            color: rgb(17 24 39);
            font-size: 0.875rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .dark .mqa-title {
            color: rgb(255 255 255);
        }

        .mqa-description,
        .mqa-muted {
            color: rgb(107 114 128);
        }

        .dark .mqa-description,
        .dark .mqa-muted {
            color: rgb(156 163 175);
        }

        .mqa-meta-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 0.75rem;
            min-width: 0;
        }

        .mqa-meta-grid > div {
            min-width: 0;
        }

        @media (min-width: 768px) {
            .mqa-meta-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .mqa-meta-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1536px) {
            .mqa-meta-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (min-width: 1920px) {
            .mqa-meta-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .mqa-meta-label,
        .mqa-card-label {
            color: rgb(75 85 99);
            font-size: 0.75rem;
            font-weight: 650;
            line-height: 1rem;
        }

        .dark .mqa-meta-label,
        .dark .mqa-card-label {
            color: rgb(209 213 219);
        }

        .mqa-meta-value {
            margin-top: 0.25rem;
            color: rgb(17 24 39);
            font-size: 0.875rem;
            font-weight: 650;
            line-height: 1.25rem;
            overflow-wrap: anywhere;
        }

        .dark .mqa-meta-value {
            color: rgb(249 250 251);
        }

        .mqa-summary-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 0.75rem;
            min-width: 0;
        }

        @media (min-width: 640px) {
            .mqa-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .mqa-summary-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1536px) {
            .mqa-summary-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (min-width: 1920px) {
            .mqa-summary-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .mqa-metric {
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            background: rgb(249 250 251);
            padding: 0.875rem;
            min-width: 0;
        }

        .dark .mqa-metric {
            border-color: rgb(55 65 81);
            background: rgb(15 23 42);
        }

        .mqa-card-value {
            margin-top: 0.35rem;
            font-size: 1.25rem;
            font-weight: 750;
            line-height: 1.6rem;
            overflow-wrap: anywhere;
        }

        .mqa-card-note {
            margin-top: 0.25rem;
            color: rgb(107 114 128);
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .dark .mqa-card-note {
            color: rgb(156 163 175);
        }

        .mqa-positive {
            color: rgb(4 120 87);
        }

        .mqa-negative {
            color: rgb(190 18 60);
        }

        .mqa-neutral {
            color: rgb(37 99 235);
        }

        .dark .mqa-positive {
            color: rgb(110 231 183);
        }

        .dark .mqa-negative {
            color: rgb(253 164 175);
        }

        .dark .mqa-neutral {
            color: rgb(147 197 253);
        }

        .mqa-status {
            display: inline-flex;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1rem;
        }

        .mqa-status-success {
            background: rgb(236 253 245);
            color: rgb(4 120 87);
        }

        .mqa-status-warning {
            background: rgb(254 249 195);
            color: rgb(133 77 14);
        }

        .mqa-status-danger {
            background: rgb(255 241 242);
            color: rgb(190 18 60);
        }

        .mqa-status-neutral,
        .mqa-alert-info {
            background: rgb(243 244 246);
            color: rgb(75 85 99);
        }

        .dark .mqa-status-success {
            background: rgb(6 78 59 / 0.32);
            color: rgb(110 231 183);
        }

        .dark .mqa-status-warning {
            background: rgb(113 63 18 / 0.35);
            color: rgb(253 224 71);
        }

        .dark .mqa-status-danger {
            background: rgb(127 29 29 / 0.35);
            color: rgb(253 164 175);
        }

        .dark .mqa-status-neutral,
        .dark .mqa-alert-info {
            background: rgb(31 41 55);
            color: rgb(209 213 219);
        }

        .mqa-alerts {
            display: grid;
            gap: 0.5rem;
        }

        .mqa-alert {
            border-radius: 0.5rem;
            padding: 0.65rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            line-height: 1.2rem;
        }

        .mqa-alert-warning {
            background: rgb(254 249 195);
            color: rgb(133 77 14);
        }

        .mqa-alert-danger {
            background: rgb(255 241 242);
            color: rgb(190 18 60);
        }

        .dark .mqa-alert-warning {
            background: rgb(113 63 18 / 0.35);
            color: rgb(253 224 71);
        }

        .dark .mqa-alert-danger {
            background: rgb(127 29 29 / 0.35);
            color: rgb(253 164 175);
        }

        .mqa-table-scroll {
            overflow-x: auto;
            padding: 1rem;
        }

        .mqa-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.8125rem;
            line-height: 1.25rem;
        }

        .mqa-table th,
        .mqa-table td {
            border: 1px solid rgb(229 231 235);
            padding: 0.625rem 0.75rem;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .dark .mqa-table th,
        .dark .mqa-table td {
            border-color: rgb(55 65 81);
        }

        .mqa-table thead th {
            background: rgb(243 244 246);
            color: rgb(55 65 81);
            font-weight: 700;
        }

        .dark .mqa-table thead th {
            background: rgb(31 41 55);
            color: rgb(229 231 235);
        }

        .mqa-table tbody tr:nth-child(even) td {
            background: rgb(249 250 251);
        }

        .dark .mqa-table tbody tr:nth-child(even) td {
            background: rgb(15 23 42);
        }

        .mqa-left {
            text-align: left;
        }

        .mqa-right {
            text-align: right;
        }

        .mqa-two-columns {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1rem;
            min-width: 0;
        }

        @media (min-width: 1024px) {
            .mqa-two-columns {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .mqa-params {
            max-height: 12rem;
            overflow: auto;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        @media (max-width: 640px) {
            .mqa-page {
                gap: 1.5rem;
            }

            .mqa-meta-grid,
            .mqa-summary-grid,
            .mqa-two-columns {
                gap: 1rem;
            }
        }
    </style>
@endonce

<div class="mqa-page">
    <div class="mqa-card">
        <div class="mqa-card-header">
            <div class="mqa-title">Resumo da execução</div>
            <div class="mqa-description">Resultado líquido baseado no valor líquido importado; custos já incluídos não são subtraídos novamente.</div>
        </div>
        <div class="mqa-card-body">
            <div class="mqa-meta-grid">
                <div>
                    <div class="mqa-meta-label">Estratégia</div>
                    <div class="mqa-meta-value">{{ $strategy->name }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Versão</div>
                    <div class="mqa-meta-value">{{ $executionInfo['strategy_version'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Execução</div>
                    <div class="mqa-meta-value">{{ $executionInfo['name'] ?? $metrics['backtest_id'] }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Tipo</div>
                    <div class="mqa-meta-value">{{ $executionInfo['execution_type_label'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Status</div>
                    <div class="mqa-meta-value">
                        <span class="mqa-status {{ $statusClass($classification['final_status'] ?? null) }}">{{ $classification['final_label'] ?? 'Não analisada' }}</span>
                    </div>
                </div>
                <div>
                    <div class="mqa-meta-label">Ativo</div>
                    <div class="mqa-meta-value">{{ Strategy::assetOptions()[$executionInfo['asset'] ?? $strategy->asset] ?? ($executionInfo['asset'] ?? '-') }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Símbolo</div>
                    <div class="mqa-meta-value">{{ $executionInfo['symbol'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Timeframe</div>
                    <div class="mqa-meta-value">{{ $executionInfo['timeframe'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Período analisado</div>
                    <div class="mqa-meta-value">{{ $formatDate($periodStart) }} a {{ $formatDate($periodEnd) }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Capital inicial</div>
                    <div class="mqa-meta-value">{{ $formatMoney($metrics['initial_capital'] ?? null) }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Contratos iniciais</div>
                    <div class="mqa-meta-value">{{ $formatNumber($executionInfo['initial_contracts'] ?? null, 2) }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Spread / slippage</div>
                    <div class="mqa-meta-value">{{ $formatNumber($executionInfo['spread'] ?? null, 2) }} / {{ $formatNumber($executionInfo['slippage'] ?? null, 2) }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Origem</div>
                    <div class="mqa-meta-value">{{ $executionInfo['data_source'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Arquivos MT5</div>
                    <div class="mqa-meta-value">{{ number_format((int) ($executionInfo['report_files_count'] ?? 0), 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="mqa-meta-label">Operações</div>
                    <div class="mqa-meta-value">{{ number_format((int) ($metrics['total_trades'] ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>

            <div class="mqa-two-columns" style="margin-top: 1rem;">
                <div class="mqa-metric">
                    <div class="mqa-card-label">Origem do lucro líquido</div>
                    <div class="mqa-card-note">{{ $metrics['net_profit_origin'] ?? '-' }}</div>
                </div>
                <div class="mqa-metric">
                    <div class="mqa-card-label">Limitação de equity</div>
                    <div class="mqa-card-note">{{ data_get($metrics, 'equity_drawdown.message') }}</div>
                </div>
            </div>

            @if (! empty($parameters) || filled($costNotes) || filled($executionInfo['notes'] ?? null))
                <div class="mqa-two-columns" style="margin-top: 1rem;">
                    @if (! empty($parameters))
                        <div class="mqa-metric">
                            <div class="mqa-card-label">Parâmetros</div>
                            <pre class="mqa-card-note mqa-params">{{ json_encode($parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif

                    @if (filled($costNotes) || filled($executionInfo['notes'] ?? null))
                        <div class="mqa-metric">
                            <div class="mqa-card-label">Observações</div>
                            <div class="mqa-card-note">{{ $costNotes ?: '' }}</div>
                            @if (filled($executionInfo['notes'] ?? null))
                                <div class="mqa-card-note">{{ $executionInfo['notes'] }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="mqa-summary-grid">
        @foreach ($cards as $card)
            <div class="mqa-metric">
                <div class="mqa-card-label">{{ $card['label'] }}</div>
                <div class="mqa-card-value {{ $card['class'] }}">{{ $card['value'] }}</div>
                <div class="mqa-card-note">{{ $card['note'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="mqa-two-columns">
        <div class="mqa-card">
            <div class="mqa-card-header">
                <div class="mqa-title">Classificação sugerida</div>
                <div class="mqa-description">
                    <span class="mqa-status {{ $statusClass($classification['suggested_status'] ?? null) }}">{{ $classification['suggested_label'] ?? 'Não analisada' }}</span>
                    @if (filled($classification['manual_label'] ?? null))
                        <span class="mqa-status {{ $statusClass($classification['manual_status'] ?? null) }}" style="margin-left: 0.5rem;">Manual: {{ $classification['manual_label'] }}</span>
                    @endif
                </div>
            </div>
            <div class="mqa-card-body">
                <div class="mqa-alerts">
                    @foreach (($classification['reasons'] ?? []) as $reason)
                        <div class="mqa-alert mqa-alert-info">{{ $reason }}</div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mqa-card">
            <div class="mqa-card-header">
                <div class="mqa-title">Alertas de qualidade</div>
                <div class="mqa-description">Pontos de atenção não bloqueiam a análise.</div>
            </div>
            <div class="mqa-card-body">
                <div class="mqa-alerts">
                    @forelse (($classification['alerts'] ?? []) as $alert)
                        <div class="mqa-alert {{ $alertClass($alert['level'] ?? 'info') }}">{{ $alert['message'] }}</div>
                    @empty
                        <div class="mqa-alert mqa-alert-info">Nenhum alerta gerado pelos critérios atuais.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @livewire(\App\Filament\Widgets\EquityCurveChart::class, [
        'heading' => 'Curva de capital',
        'description' => 'Saldo líquido reconstruído em ordem cronológica pela data de saída.',
        'curve' => $metrics['capital_curve'] ?? [],
    ], key('capital-'.$metrics['backtest_id']))

    @livewire(\App\Filament\Widgets\EquityCurveChart::class, [
        'heading' => 'Drawdown de saldo',
        'description' => 'Diferença entre o saldo corrente e o pico acumulado anterior.',
        'curve' => $metrics['drawdown_curve'] ?? [],
        'strokeColor' => '#fb7185',
        'fillColor' => '#fecdd3',
        'pointColor' => '#e11d48',
    ], key('drawdown-'.$metrics['backtest_id']))

    <div class="mqa-two-columns">
        @livewire(\App\Filament\Widgets\PeriodResultBarChart::class, [
            'heading' => 'Resultado mensal',
            'description' => 'Meses sem operações são omitidos, não tratados como lucro ou prejuízo.',
            'rows' => $metrics['monthly_results'] ?? [],
        ], key('monthly-chart-'.$metrics['backtest_id']))

        @livewire(\App\Filament\Widgets\PeriodResultBarChart::class, [
            'heading' => 'Resultado anual',
            'description' => 'Participação anual calculada sobre o lucro líquido total.',
            'rows' => $metrics['annual_results'] ?? [],
        ], key('annual-chart-'.$metrics['backtest_id']))
    </div>

    <div class="mqa-card">
        <div class="mqa-card-header">
            <div class="mqa-title">Resultados mensais</div>
            <div class="mqa-description">
                {{ data_get($metrics, 'monthly_summary.positive_months', 0) }} positivos,
                {{ data_get($metrics, 'monthly_summary.negative_months', 0) }} negativos,
                {{ data_get($metrics, 'monthly_summary.zero_months', 0) }} zerados.
                Meses sem operações: {{ data_get($metrics, 'monthly_summary.no_trade_months', 0) }}.
            </div>
        </div>
        <div class="mqa-table-scroll">
            <table class="mqa-table">
                <thead>
                    <tr>
                        <th class="mqa-left">Mês</th>
                        <th class="mqa-right">Ops.</th>
                        <th class="mqa-right">Lucro bruto</th>
                        <th class="mqa-right">Prejuízo bruto</th>
                        <th class="mqa-right">Custos</th>
                        <th class="mqa-right">Lucro líquido</th>
                        <th class="mqa-right">Retorno</th>
                        <th class="mqa-right">PF</th>
                        <th class="mqa-right">Acerto</th>
                        <th class="mqa-right">Ganho médio</th>
                        <th class="mqa-right">Perda média</th>
                        <th class="mqa-right">DD período</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (array_reverse($metrics['monthly_results'] ?? []) as $row)
                        <tr>
                            <td class="mqa-left">{{ $row['label'] }}</td>
                            <td class="mqa-right">{{ number_format((int) $row['trade_count'], 0, ',', '.') }}</td>
                            <td class="mqa-right mqa-positive">{{ $formatMoney($row['gross_profit']) }}</td>
                            <td class="mqa-right mqa-negative">{{ $formatMoney($row['gross_loss']) }}</td>
                            <td class="mqa-right {{ $moneyClass($row['costs']) }}">{{ $formatSignedMoney($row['costs']) }}</td>
                            <td class="mqa-right {{ $moneyClass($row['net_profit']) }}">{{ $formatSignedMoney($row['net_profit']) }}</td>
                            <td class="mqa-right">{{ $formatPercent($row['return_percent']) }}</td>
                            <td class="mqa-right">{{ $formatRatio($row['profit_factor']) }}</td>
                            <td class="mqa-right">{{ $formatPercent($row['win_rate']) }}</td>
                            <td class="mqa-right mqa-positive">{{ $formatMoney($row['average_win']) }}</td>
                            <td class="mqa-right mqa-negative">{{ $formatMoney($row['average_loss']) }}</td>
                            <td class="mqa-right mqa-negative">{{ $formatMoney($row['drawdown']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="mqa-left mqa-muted">Nenhum resultado mensal disponível.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mqa-card">
        <div class="mqa-card-header">
            <div class="mqa-title">Resultados anuais</div>
            <div class="mqa-description">
                Melhor ano: {{ data_get($metrics, 'annual_summary.best_year.label', '-') }}.
                Concentração no melhor ano: {{ $formatPercent(data_get($metrics, 'annual_summary.best_year_concentration_percent')) }}.
            </div>
        </div>
        <div class="mqa-table-scroll">
            <table class="mqa-table">
                <thead>
                    <tr>
                        <th class="mqa-left">Ano</th>
                        <th class="mqa-right">Ops.</th>
                        <th class="mqa-right">Lucro bruto</th>
                        <th class="mqa-right">Prejuízo bruto</th>
                        <th class="mqa-right">Custos</th>
                        <th class="mqa-right">Lucro líquido</th>
                        <th class="mqa-right">Retorno</th>
                        <th class="mqa-right">DD</th>
                        <th class="mqa-right">PF</th>
                        <th class="mqa-right">Acerto</th>
                        <th class="mqa-right">Lucro/DD</th>
                        <th class="mqa-right">Participação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (array_reverse($metrics['annual_results'] ?? []) as $row)
                        <tr>
                            <td class="mqa-left">{{ $row['label'] }}</td>
                            <td class="mqa-right">{{ number_format((int) $row['trade_count'], 0, ',', '.') }}</td>
                            <td class="mqa-right mqa-positive">{{ $formatMoney($row['gross_profit']) }}</td>
                            <td class="mqa-right mqa-negative">{{ $formatMoney($row['gross_loss']) }}</td>
                            <td class="mqa-right {{ $moneyClass($row['costs']) }}">{{ $formatSignedMoney($row['costs']) }}</td>
                            <td class="mqa-right {{ $moneyClass($row['net_profit']) }}">{{ $formatSignedMoney($row['net_profit']) }}</td>
                            <td class="mqa-right">{{ $formatPercent($row['return_percent']) }}</td>
                            <td class="mqa-right mqa-negative">{{ $formatMoney($row['drawdown']) }}</td>
                            <td class="mqa-right">{{ $formatRatio($row['profit_factor']) }}</td>
                            <td class="mqa-right">{{ $formatPercent($row['win_rate']) }}</td>
                            <td class="mqa-right">{{ $formatRatio($row['profit_drawdown_ratio']) }}</td>
                            <td class="mqa-right">{{ $formatPercent($row['profit_share_percent'] ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="mqa-left mqa-muted">Nenhum resultado anual disponível.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mqa-two-columns">
        <div class="mqa-card">
            <div class="mqa-card-header">
                <div class="mqa-title">Compras versus vendas</div>
            </div>
            <div class="mqa-table-scroll">
                <table class="mqa-table" style="min-width: 760px;">
                    <thead>
                        <tr>
                            <th class="mqa-left">Direção</th>
                            <th class="mqa-right">Ops.</th>
                            <th class="mqa-right">Vencedoras</th>
                            <th class="mqa-right">Perdedoras</th>
                            <th class="mqa-right">Zeradas</th>
                            <th class="mqa-right">Acerto</th>
                            <th class="mqa-right">Resultado</th>
                            <th class="mqa-right">PF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($metrics['direction_summary'] ?? []) as $row)
                            <tr>
                                <td class="mqa-left">{{ $row['label'] }}</td>
                                <td class="mqa-right">{{ number_format((int) $row['total_trades'], 0, ',', '.') }}</td>
                                <td class="mqa-right">{{ number_format((int) $row['winning_trades'], 0, ',', '.') }}</td>
                                <td class="mqa-right">{{ number_format((int) $row['losing_trades'], 0, ',', '.') }}</td>
                                <td class="mqa-right">{{ number_format((int) $row['breakeven_trades'], 0, ',', '.') }}</td>
                                <td class="mqa-right">{{ $formatPercent($row['win_rate']) }}</td>
                                <td class="mqa-right {{ $moneyClass($row['net_profit']) }}">{{ $formatSignedMoney($row['net_profit']) }}</td>
                                <td class="mqa-right">{{ $formatRatio($row['profit_factor']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mqa-card">
            <div class="mqa-card-header">
                <div class="mqa-title">Sequências</div>
                <div class="mqa-description">Operações zeradas encerram a sequência e não contam como ganho ou perda.</div>
            </div>
            <div class="mqa-table-scroll">
                <table class="mqa-table" style="min-width: 760px;">
                    <thead>
                        <tr>
                            <th class="mqa-left">Métrica</th>
                            <th class="mqa-right">Valor</th>
                            <th class="mqa-right">Resultado</th>
                            <th class="mqa-right">Início</th>
                            <th class="mqa-right">Fim</th>
                            <th class="mqa-right">Duração</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="mqa-left">Maior sequência de perdas</td>
                            <td class="mqa-right">{{ number_format((int) data_get($metrics, 'streaks.max_losing.count', 0), 0, ',', '.') }}</td>
                            <td class="mqa-right mqa-negative">{{ $formatMoney(data_get($metrics, 'streaks.max_losing.amount')) }}</td>
                            <td class="mqa-right">{{ $formatDate(data_get($metrics, 'streaks.max_losing.start'), true) }}</td>
                            <td class="mqa-right">{{ $formatDate(data_get($metrics, 'streaks.max_losing.end'), true) }}</td>
                            <td class="mqa-right">{{ data_get($metrics, 'streaks.max_losing.duration_days') ?? '-' }} dia(s)</td>
                        </tr>
                        <tr>
                            <td class="mqa-left">Maior sequência de ganhos</td>
                            <td class="mqa-right">{{ number_format((int) data_get($metrics, 'streaks.max_winning.count', 0), 0, ',', '.') }}</td>
                            <td class="mqa-right mqa-positive">{{ $formatMoney(data_get($metrics, 'streaks.max_winning.amount')) }}</td>
                            <td class="mqa-right">{{ $formatDate(data_get($metrics, 'streaks.max_winning.start'), true) }}</td>
                            <td class="mqa-right">{{ $formatDate(data_get($metrics, 'streaks.max_winning.end'), true) }}</td>
                            <td class="mqa-right">-</td>
                        </tr>
                        <tr>
                            <td class="mqa-left">Média de perdas por sequência</td>
                            <td class="mqa-right">{{ $formatNumber(data_get($metrics, 'streaks.average_losses_per_sequence'), 2) }}</td>
                            <td class="mqa-right">-</td>
                            <td class="mqa-right">-</td>
                            <td class="mqa-right">-</td>
                            <td class="mqa-right">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mqa-card">
        <div class="mqa-card-header">
            <div class="mqa-title">Critérios de curadoria</div>
        </div>
        <div class="mqa-table-scroll">
            <table class="mqa-table">
                <thead>
                    <tr>
                        <th class="mqa-left">Critério</th>
                        <th class="mqa-right">Valor</th>
                        <th class="mqa-right">Limite</th>
                        <th class="mqa-left">Regra</th>
                        <th class="mqa-left">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (($classification['criteria'] ?? []) as $criterion)
                        <tr>
                            <td class="mqa-left">{{ $criterion['label'] }}</td>
                            <td class="mqa-right">{{ $formatNumber($criterion['actual']) }}</td>
                            <td class="mqa-right">{{ $formatNumber($criterion['threshold']) }}</td>
                            <td class="mqa-left">{{ $criterion['direction'] }}</td>
                            <td class="mqa-left">
                                @if ($criterion['passed'] === true)
                                    <span class="mqa-status mqa-status-success">Aprovado</span>
                                @elseif ($criterion['passed'] === false)
                                    <span class="mqa-status mqa-status-danger">Reprovado</span>
                                @else
                                    <span class="mqa-status mqa-status-neutral">Indisponível</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @livewire(\App\Filament\Widgets\StrategyTradesTable::class, [
        'tableHeading' => 'Operações da execução',
        'tableDescription' => 'Operações fechadas filtradas pelo identificador de backtest atual.',
        'trades' => $metrics['strategy_trades'] ?? [],
    ], key('trades-'.$metrics['backtest_id']))
</div>
