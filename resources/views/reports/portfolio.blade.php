@php
    $money = fn (mixed $value): string => 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $signedMoney = fn (mixed $value): string => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $percent = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
    $number = fn (mixed $value, int $decimals = 2): string => number_format((float) $value, $decimals, ',', '.');
    $integer = fn (mixed $value): string => number_format((int) $value, 0, ',', '.');
    $ratio = fn (mixed $value): string => $value === null ? 'Sem perdas' : number_format((float) $value, 2, ',', '.');
    $date = function (mixed $value): string {
        if (blank($value)) {
            return '-';
        }

        try {
            return \Carbon\CarbonImmutable::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return '-';
        }
    };
    $tone = fn (mixed $value): string => (float) $value > 0 ? '#047857' : ((float) $value < 0 ? '#be123c' : '#64748b');

    // Whole-number money for the dense monthly grids.
    $compact = fn (mixed $value): string => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . number_format(abs((float) $value), 0, ',', '.');

    // Mixes white toward the given rgb by $intensity (0-1) to shade a heat-map cell.
    $shade = function (float $intensity, array $rgb): string {
        $intensity = max(0.0, min(1.0, $intensity));

        return sprintf(
            '#%02x%02x%02x',
            (int) round(255 - (255 - $rgb[0]) * $intensity),
            (int) round(255 - (255 - $rgb[1]) * $intensity),
            (int) round(255 - (255 - $rgb[2]) * $intensity),
        );
    };
    $heat = function (mixed $value, float $max) use ($shade): string {
        $value = (float) $value;

        if ($value === 0.0 || $max <= 0) {
            return '#ffffff';
        }

        $intensity = 0.14 + 0.5 * min(abs($value) / $max, 1.0);

        return $value > 0 ? $shade($intensity, [16, 185, 129]) : $shade($intensity, [244, 63, 94]);
    };

    $correlationStyles = [
        'mqa-correlation-good' => ['#dcfce7', '#166534'],
        'mqa-correlation-warning' => ['#fef9c3', '#854d0e'],
        'mqa-correlation-high' => ['#ffedd5', '#9a3412'],
        'mqa-correlation-critical' => ['#ffe4e6', '#be123c'],
        'mqa-correlation-inverse' => ['#dbeafe', '#1d4ed8'],
        'mqa-correlation-diagonal' => ['#e2e8f0', '#0f172a'],
        'mqa-correlation-empty' => ['#f8fafc', '#64748b'],
    ];
    $interpretationStyles = [
        'success' => ['#ecfdf5', '#047857'],
        'warning' => ['#fefce8', '#854d0e'],
        'danger' => ['#fff1f2', '#be123c'],
        'neutral' => ['#f1f5f9', '#475569'],
    ];

    $hasTrades = ($metrics['total_trades'] ?? 0) > 0;
    $daily = $metrics['daily_performance'] ?? [];
    $summaries = $metrics['strategy_summaries'] ?? [];
    $monthlyPerformance = collect($metrics['consolidated_monthly_performance'] ?? [])->sortBy('year')->values()->all();
    $cumulativePerformance = collect($metrics['consolidated_monthly_cumulative_performance'] ?? [])->sortBy('year')->values()->all();
    $monthNames = array_values(\App\Services\Metrics\MonthlyPerformanceService::MONTHS);

    $netProfit = (float) ($metrics['consolidated_net_profit'] ?? 0);
    $monthsWithTrades = (int) ($metrics['months_with_trades'] ?? 0);
    $maxDrawdown = (float) ($metrics['consolidated_max_drawdown'] ?? 0);
    $maxDrawdownPercent = (float) ($metrics['consolidated_max_drawdown_percent'] ?? 0);
    $activeCount = (int) ($metrics['active_strategies_count'] ?? 0);
    $linkedCount = count($summaries);
    $totalAbsProfit = max((float) collect($summaries)->where('enabled', true)->sum(fn ($s) => abs((float) ($s['net_profit'] ?? 0))), 0.0001);
    $description = \Illuminate\Support\Str::limit((string) $portfolio->description, 260);

    $kpis = [
        [
            'label' => 'Lucro total',
            'value' => $signedMoney($netProfit),
            'color' => $tone($netProfit),
            'sub' => 'Média mensal: ' . ($monthsWithTrades > 0 ? $signedMoney($netProfit / $monthsWithTrades) : '-'),
        ],
        [
            'label' => 'Fator de lucro',
            'value' => $ratio($metrics['consolidated_profit_factor'] ?? null),
            'color' => ($metrics['consolidated_profit_factor'] ?? 1) === null || (float) ($metrics['consolidated_profit_factor'] ?? 1) >= 1 ? '#047857' : '#be123c',
            'sub' => 'Taxa de acerto: ' . $percent($metrics['consolidated_win_rate'] ?? 0),
        ],
        [
            'label' => 'Payoff',
            'value' => ($metrics['consolidated_payoff'] ?? null) === null ? '-' : $number($metrics['consolidated_payoff']),
            'color' => (float) ($metrics['consolidated_payoff'] ?? 0) >= 1 ? '#047857' : '#be123c',
            'sub' => 'Ganho médio ' . $money($metrics['average_win'] ?? 0) . ' / perda média ' . $money($metrics['average_loss'] ?? 0),
        ],
        [
            'label' => 'Drawdown máximo',
            'value' => ($maxDrawdown > 0 ? '-' : '') . $money($maxDrawdown),
            'color' => $maxDrawdown > 0 ? '#be123c' : '#64748b',
            'sub' => ($maxDrawdownPercent > 0 ? '-' : '') . $percent($maxDrawdownPercent) . ' do topo da curva',
        ],
        [
            'label' => 'Fator de recuperação',
            'value' => ($metrics['net_profit_to_drawdown'] ?? null) === null ? '-' : $number($metrics['net_profit_to_drawdown']),
            'color' => '#0f172a',
            'sub' => 'Lucro líquido ÷ drawdown máximo',
        ],
        [
            'label' => 'Ulcer Index',
            'value' => ($metrics['ulcer_index'] ?? null) === null ? '-' : $number($metrics['ulcer_index']),
            'color' => '#0f172a',
            'sub' => 'R² da curva de capital: ' . (($metrics['equity_r2'] ?? null) === null ? '-' : $number($metrics['equity_r2'], 4)),
        ],
    ];

    $tradeStats = [
        ['Total de trades', $integer($metrics['total_trades'] ?? 0), '#0f172a'],
        ['Trades vencedores', $integer($metrics['winning_trades'] ?? 0), '#047857'],
        ['Trades perdedores', $integer($metrics['losing_trades'] ?? 0), '#be123c'],
        ['Trades zerados', $integer($metrics['breakeven_trades'] ?? 0), '#64748b'],
        ['Média por trade', $signedMoney($metrics['average_trade'] ?? 0), $tone($metrics['average_trade'] ?? 0)],
        ['Melhor trade', $signedMoney($metrics['best_trade'] ?? 0), $tone($metrics['best_trade'] ?? 0)],
        ['Pior trade', $signedMoney($metrics['worst_trade'] ?? 0), $tone($metrics['worst_trade'] ?? 0)],
    ];
    $riskStats = [
        ['Lucro bruto', $money($metrics['gross_profit'] ?? 0), '#047857'],
        ['Perda bruta', '-' . $money($metrics['gross_loss'] ?? 0), '#be123c'],
        ['Maior sequência de ganhos', $integer($metrics['max_winning_streak'] ?? 0) . ' trades', '#0f172a'],
        ['Maior sequência de perdas', $integer($metrics['max_losing_streak'] ?? 0) . ' trades', '#be123c'],
        ['Dias sem romper o topo', $integer($metrics['consolidated_max_days_without_new_high'] ?? 0) . ' dias', '#0f172a'],
        ['Meses positivos', $integer($metrics['positive_months'] ?? 0) . ' de ' . $integer($monthsWithTrades), '#047857'],
        ['Meses negativos', $integer($metrics['negative_months'] ?? 0) . ' de ' . $integer($monthsWithTrades), '#be123c'],
    ];

    $monthlyMax = (float) collect($monthlyPerformance)->flatMap(fn ($row) => collect($row['months'] ?? [])->filter(fn ($v) => $v !== null)->map(fn ($v) => abs((float) $v)))->max();
    $cumulativeMax = (float) collect($cumulativePerformance)->flatMap(fn ($row) => collect($row['months'] ?? [])->filter(fn ($v) => $v !== null)->map(fn ($v) => abs((float) $v)))->max();

    $correlationSummary = $correlation['summary'] ?? [];
    $strategyNumbers = collect($correlation['strategies'] ?? [])->values();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório do portfólio - {{ $portfolio->name }}</title>
    <style>
        @page { margin: 34pt 32pt 50pt 32pt; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #1e293b;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            line-height: 1.35;
        }

        table { border-collapse: collapse; }
        .full { width: 100%; }
        .right { text-align: right; }
        .center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .bold { font-weight: bold; }
        .muted { color: #64748b; }

        /* ---------- Cover header ---------- */
        .hero { width: 100%; background: #0f172a; border-radius: 8pt; }
        .hero td { padding: 15pt 20pt; vertical-align: top; }
        .hero .eyebrow { color: #34d399; font-size: 7pt; font-weight: bold; letter-spacing: 1.6pt; text-transform: uppercase; }
        .hero .title { margin-top: 5pt; color: #ffffff; font-size: 19pt; font-weight: bold; line-height: 1.2; }
        .hero .desc { margin-top: 6pt; color: #94a3b8; font-size: 7.5pt; line-height: 1.4; }
        .hero .meta-label { color: #64748b; font-size: 6.5pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; }
        .hero .meta-value { margin: 1pt 0 8pt 0; color: #e2e8f0; font-size: 9pt; font-weight: bold; }

        /* ---------- Info strip ---------- */
        .strip { width: 100%; margin-top: 10pt; border: 1px solid #e2e8f0; border-radius: 6pt; background: #f8fafc; }
        .strip td { padding: 8pt 12pt; border-right: 1px solid #e2e8f0; }
        .strip td:last-child { border-right: none; }
        .strip .label { color: #64748b; font-size: 6.5pt; font-weight: bold; letter-spacing: 0.8pt; text-transform: uppercase; }
        .strip .value { margin-top: 1pt; color: #0f172a; font-size: 10pt; font-weight: bold; }

        /* ---------- Sections ---------- */
        .section { margin-top: 14pt; }
        .section-head { margin-bottom: 8pt; padding-bottom: 5pt; border-bottom: 1.5pt solid #0f172a; page-break-after: avoid; }
        .section-title { color: #0f172a; font-size: 12pt; font-weight: bold; }
        .section-desc { margin-top: 2pt; color: #64748b; font-size: 7.5pt; }
        .subtitle { margin: 10pt 0 4pt 0; color: #334155; font-size: 8.5pt; font-weight: bold; page-break-after: avoid; }
        .break { page-break-before: always; }
        .keep { page-break-inside: avoid; }

        /* ---------- Cards ---------- */
        .cards { width: 100%; border-collapse: collapse; }
        .cards td { width: 33.33%; padding: 0 5.33pt; vertical-align: top; }
        .cards td:first-child { padding-left: 0; padding-right: 10.66pt; }
        .cards td:last-child { padding-left: 10.66pt; padding-right: 0; }
        .card { padding: 9pt 11pt; border: 1px solid #e2e8f0; border-left: 3pt solid #0f172a; background: #ffffff; }
        .card .label { color: #64748b; font-size: 6.5pt; font-weight: bold; letter-spacing: 0.8pt; text-transform: uppercase; }
        .card .value { margin-top: 3pt; font-size: 15pt; font-weight: bold; line-height: 1.15; }
        .card .sub { margin-top: 3pt; color: #64748b; font-size: 7pt; line-height: 1.3; }

        .chart { display: block; width: 100%; border: 1px solid #e2e8f0; border-radius: 5pt; }
        .caption { margin-top: 4pt; color: #64748b; font-size: 7pt; }

        /* ---------- Key/value lists ---------- */
        .kv { width: 100%; }
        .kv td { padding: 2.4pt 6pt; border-bottom: 1px solid #eef2f6; font-size: 7.8pt; }
        .kv tr:last-child td { border-bottom: none; }
        .kv td:first-child { color: #475569; }
        .kv td:last-child { text-align: right; font-weight: bold; }
        .two { width: 100%; border-collapse: separate; border-spacing: 0; }
        .two > tbody > tr > td, .two > tr > td { width: 50%; vertical-align: top; }
        .panel { border: 1px solid #e2e8f0; border-radius: 5pt; background: #ffffff; }

        /* ---------- Data tables ---------- */
        .grid { width: 100%; }
        .grid th { padding: 4.5pt 4pt; border: 1px solid #cbd5e1; background: #f1f5f9; color: #334155; font-size: 6.8pt; font-weight: bold; text-transform: uppercase; }
        .grid td { padding: 4pt 4pt; border: 1px solid #e2e8f0; font-size: 7.6pt; }
        .grid tr { page-break-inside: avoid; }
        .months th, .months td { padding: 4.2pt 1.5pt; font-size: 6.6pt; text-align: center; }
        .months td.year { background: #f1f5f9; color: #0f172a; font-weight: bold; }
        .months td.total { font-weight: bold; background: #f8fafc; }

        .pill { display: inline-block; padding: 1.5pt 5pt; border-radius: 8pt; font-size: 6.6pt; font-weight: bold; }
        .pill-on { background: #d1fae5; color: #047857; }
        .pill-off { background: #e2e8f0; color: #475569; }

        .bar-track { width: 100%; height: 5pt; border-radius: 3pt; background: #e2e8f0; }
        .bar-fill { height: 5pt; border-radius: 3pt; }

        .dist { width: 100%; margin: 8pt 0 10pt 0; border-collapse: collapse; }
        .dist td { height: 9pt; padding: 0; }

        .notice { margin-top: 8pt; padding: 7pt 10pt; border-radius: 5pt; font-size: 8pt; font-weight: bold; }
        .chip { display: inline-block; margin: 0 4pt 4pt 0; padding: 2.5pt 7pt; border: 1px solid #fdba74; border-radius: 9pt; background: #fff7ed; color: #9a3412; font-size: 7pt; font-weight: bold; }
        .legend-swatch { display: inline-block; width: 9pt; height: 9pt; margin-right: 3pt; border: 1px solid #cbd5e1; border-radius: 2pt; vertical-align: middle; }

        .disclaimer { margin-top: 22pt; padding: 9pt 12pt; border: 1px solid #e2e8f0; border-radius: 5pt; background: #f8fafc; color: #64748b; font-size: 7pt; line-height: 1.45; }

        /* ---------- Footer ---------- */
        .footer { position: fixed; right: 0; bottom: -32pt; left: 0; height: 22pt; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 7pt; }
        .footer table { width: 100%; margin-top: 5pt; }
        .footer .page:before { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        <table>
            <tr>
                <td>Mayer Quant Analyzer &middot; {{ \Illuminate\Support\Str::limit($portfolio->name, 70) }}</td>
                <td class="right">Gerado em {{ $generatedAt->format('d/m/Y H:i') }} &middot; Página <span class="page"></span></td>
            </tr>
        </table>
    </div>

    {{-- ===================== Cover ===================== --}}
    <table class="hero">
        <tr>
            <td style="width: 66%;">
                <div class="eyebrow">Relatório de análise de portfólio</div>
                <div class="title">{{ $portfolio->name }}</div>
                @if (filled($description))
                    <div class="desc">{{ $description }}</div>
                @endif
            </td>
            <td style="width: 34%; padding-left: 8pt;">
                <div class="meta-label">Período analisado</div>
                <div class="meta-value">{!! filled($period['start']) ? e($period['start']).' &ndash; '.e($period['end']) : '-' !!}</div>
                <div class="meta-label">Duração</div>
                <div class="meta-value">{{ $integer($period['days']) }} dias</div>
                <div class="meta-label">Emitido em</div>
                <div class="meta-value" style="margin-bottom: 0;">{{ $generatedAt->format('d/m/Y \à\s H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="strip">
        <tr>
            <td>
                <div class="label">Saldo inicial</div>
                <div class="value">{{ $money($portfolio->initial_balance ?? 0) }}</div>
            </td>
            <td>
                <div class="label">Estratégias ativas</div>
                <div class="value">{{ $integer($activeCount) }} <span class="muted" style="font-size: 7.5pt; font-weight: normal;">de {{ $integer($linkedCount) }} vinculadas</span></div>
            </td>
            <td>
                <div class="label">Total de trades</div>
                <div class="value">{{ $integer($metrics['total_trades'] ?? 0) }}</div>
            </td>
            <td>
                <div class="label">Dias operacionais</div>
                <div class="value">{{ $integer($daily['total_days'] ?? 0) }}</div>
            </td>
        </tr>
    </table>

    @if (! $hasTrades)
        <div class="notice" style="margin-top: 16pt; background: #f1f5f9; color: #475569;">
            Nenhum trade com data de saída foi encontrado para as estratégias ativas deste portfólio.
        </div>
    @else
        {{-- ===================== Headline metrics ===================== --}}
        <div class="section" style="margin-top: 14pt;">
            <table class="cards">
                @foreach (array_chunk($kpis, 3) as $row)
                    <tr>
                        @foreach ($row as $kpi)
                            <td>
                                <div class="card" style="border-left-color: {{ $kpi['color'] }};">
                                    <div class="label">{{ $kpi['label'] }}</div>
                                    <div class="value" style="color: {{ $kpi['color'] }};">{{ $kpi['value'] }}</div>
                                    <div class="sub">{{ $kpi['sub'] }}</div>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                    @if (! $loop->last)
                        <tr><td colspan="3" style="height: 8pt;"></td></tr>
                    @endif
                @endforeach
            </table>
        </div>

        {{-- ===================== Equity curve ===================== --}}
        <div class="section keep">
            <div class="section-head">
                <div class="section-title">Curva de capital</div>
                <div class="section-desc">Resultado financeiro acumulado do portfólio, trade a trade, ordenado pela data de saída.</div>
            </div>
            @if ($charts['equity'])
                <img class="chart" src="{{ $charts['equity'] }}" alt="Curva de capital">
            @endif
            <div class="caption">
                {{ $period['start'] }} &ndash; {{ $period['end'] }} &middot; resultado acumulado final:
                <span class="bold" style="color: {{ $tone($netProfit) }};">{{ $signedMoney($netProfit) }}</span>
            </div>
        </div>

        {{-- ===================== Indicators ===================== --}}
        <div class="section keep">
            <div class="section-head">
                <div class="section-title">Indicadores de desempenho</div>
                <div class="section-desc">Estatísticas consolidadas dos trades fechados das estratégias ativas, já ponderadas pelos pesos.</div>
            </div>
            <table class="two">
                <tr>
                    <td style="padding-right: 6pt;">
                        <div class="panel">
                            <table class="kv">
                                @foreach ($tradeStats as [$label, $value, $color])
                                    <tr><td>{{ $label }}</td><td style="color: {{ $color }};">{{ $value }}</td></tr>
                                @endforeach
                            </table>
                        </div>
                    </td>
                    <td style="padding-left: 6pt;">
                        <div class="panel">
                            <table class="kv">
                                @foreach ($riskStats as [$label, $value, $color])
                                    <tr><td>{{ $label }}</td><td style="color: {{ $color }};">{{ $value }}</td></tr>
                                @endforeach
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ===================== Drawdown ===================== --}}
        <div class="section keep">
            <div class="section-head">
                <div class="section-title">Drawdown</div>
                <div class="section-desc">Distância da curva de capital até o seu topo anterior ao longo do tempo.</div>
            </div>
            @if ($charts['drawdown'])
                <img class="chart" src="{{ $charts['drawdown'] }}" alt="Drawdown">
            @endif
            <table class="cards" style="margin-top: 8pt;">
                <tr>
                    <td>
                        <div class="card" style="border-left-color: #be123c;">
                            <div class="label">Drawdown máximo</div>
                            <div class="value" style="color: #be123c; font-size: 12pt;">{{ ($maxDrawdown > 0 ? '-' : '') . $money($maxDrawdown) }}</div>
                            <div class="sub">{{ ($maxDrawdownPercent > 0 ? '-' : '') . $percent($maxDrawdownPercent) }} do topo</div>
                        </div>
                    </td>
                    <td>
                        <div class="card">
                            <div class="label">Topo da curva</div>
                            <div class="value" style="font-size: 12pt;">{{ $money($metrics['drawdown_peak'] ?? 0) }}</div>
                            <div class="sub">Saldo antes da queda máxima</div>
                        </div>
                    </td>
                    <td>
                        <div class="card">
                            <div class="label">Fundo da queda</div>
                            <div class="value" style="font-size: 12pt;">{{ $money($metrics['drawdown_valley'] ?? 0) }}</div>
                            <div class="sub">Menor saldo dessa queda</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- ===================== Monthly ===================== --}}
        <div class="section">
            <div class="keep">
                <div class="section-head">
                    <div class="section-title">Resultado mensal e anual</div>
                    <div class="section-desc">Resultado financeiro consolidado de cada mês. Meses sem operações não entram como lucro ou prejuízo.</div>
                </div>

                @if ($charts['monthly'])
                    <div class="subtitle" style="margin-top: 0;">Resultado por mês</div>
                    <img class="chart" src="{{ $charts['monthly'] }}" alt="Resultado por mês">
                @endif
            </div>

            @if ($charts['yearly'])
                <div class="keep">
                    <div class="subtitle">Resultado por ano</div>
                    <img class="chart" src="{{ $charts['yearly'] }}" alt="Resultado por ano">
                </div>
            @endif
        </div>

        <div class="section keep">
            <div class="section-head">
                <div class="section-title">Resultado mês a mês</div>
                <div class="section-desc">Valores em R$. Quanto mais intensa a cor, maior o resultado em relação aos demais meses.</div>
            </div>
            <table class="grid months">
                <thead>
                    <tr>
                        <th style="width: 8%;">Ano</th>
                        @foreach ($monthNames as $month)
                            <th>{{ $month }}</th>
                        @endforeach
                        <th style="width: 11%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($monthlyPerformance as $row)
                        <tr>
                            <td class="year">{{ $row['year'] }}</td>
                            @foreach ($monthNames as $month)
                                @php $value = $row['months'][$month] ?? null; @endphp
                                @if ($value === null)
                                    <td style="color: #cbd5e1;">-</td>
                                @else
                                    <td style="background: {{ $heat($value, $monthlyMax) }}; color: {{ $tone($value) }};">{{ (float) $value === 0.0 ? '-' : $compact($value) }}</td>
                                @endif
                            @endforeach
                            <td class="total" style="color: {{ $tone($row['ytd'] ?? 0) }};">{{ $compact($row['ytd'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="subtitle" style="margin-top: 16pt;">Resultado acumulado por mês</div>
            <div class="section-desc" style="margin: 0 0 5pt 0;">Saldo acumulado ao final de cada mês, reconstruído pela curva consolidada.</div>
            <table class="grid months">
                <thead>
                    <tr>
                        <th style="width: 8%;">Ano</th>
                        @foreach ($monthNames as $month)
                            <th>{{ $month }}</th>
                        @endforeach
                        <th style="width: 11%;">Final</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cumulativePerformance as $row)
                        <tr>
                            <td class="year">{{ $row['year'] }}</td>
                            @foreach ($monthNames as $month)
                                @php $value = $row['months'][$month] ?? null; @endphp
                                @if ($value === null)
                                    <td style="color: #cbd5e1;">-</td>
                                @else
                                    <td style="background: {{ $heat($value, $cumulativeMax) }}; color: {{ $tone($value) }};">{{ $compact($value) }}</td>
                                @endif
                            @endforeach
                            <td class="total" style="color: {{ $tone($row['ytd'] ?? 0) }};">{{ $compact($row['ytd'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- ===================== Days ===================== --}}
        @if ($daily['has_data'] ?? false)
            <div class="section keep">
                <div class="section-head">
                    <div class="section-title">Dias positivos vs negativos</div>
                    <div class="section-desc">Comparação dos dias operacionais, consolidando o resultado líquido ponderado dos trades fechados em cada data.</div>
                </div>

                @php
                    $dayCards = [
                        ['Dias positivos', $integer($daily['positive_days'] ?? 0), '#047857', $percent($daily['positive_day_rate'] ?? 0) . ' dos dias operacionais'],
                        ['Dias negativos', $integer($daily['negative_days'] ?? 0), '#be123c', $percent($daily['negative_day_rate'] ?? 0) . ' dos dias operacionais'],
                        ['Dias neutros', $integer($daily['neutral_days'] ?? 0), '#64748b', $percent($daily['neutral_day_rate'] ?? 0) . ' dos dias operacionais'],
                        ['Média dia positivo', $signedMoney($daily['average_positive_day'] ?? 0), '#047857', 'Média apenas dos dias acima de zero'],
                        ['Média dia negativo', $signedMoney($daily['average_negative_day'] ?? 0), '#be123c', 'Média apenas dos dias abaixo de zero'],
                        ['Relação positivo/negativo', ($daily['positive_negative_ratio'] ?? null) === null ? 'Sem perdas' : $number($daily['positive_negative_ratio']) . 'x', '#0f172a', 'Dias positivos por dia negativo'],
                    ];
                    $extremeCards = [
                        ['Melhor dia', $signedMoney(data_get($daily, 'best_day.net_profit', 0)), '#047857', $date(data_get($daily, 'best_day.date'))],
                        ['Pior dia', $signedMoney(data_get($daily, 'worst_day.net_profit', 0)), '#be123c', $date(data_get($daily, 'worst_day.date'))],
                        ['Maior sequência positiva', $integer($daily['max_positive_streak'] ?? 0) . ' dias', '#047857', 'Dias positivos consecutivos'],
                        ['Maior sequência negativa', $integer($daily['max_negative_streak'] ?? 0) . ' dias', '#be123c', 'Dias negativos consecutivos'],
                        ['Sequência atual', $integer($daily['current_streak_count'] ?? 0) . ' dias', ($daily['current_streak_type'] ?? null) === 'negative' ? '#be123c' : '#047857', ($daily['current_streak_type'] ?? null) === 'negative' ? 'Dias negativos seguidos' : 'Dias positivos seguidos'],
                        ['Total de dias operacionais', $integer($daily['total_days'] ?? 0), '#0f172a', 'Com pelo menos um trade fechado'],
                    ];
                @endphp

                <table class="cards">
                    @foreach (array_chunk($dayCards, 3) as $row)
                        <tr>
                            @foreach ($row as [$label, $value, $color, $sub])
                                <td>
                                    <div class="card" style="border-left-color: {{ $color }};">
                                        <div class="label">{{ $label }}</div>
                                        <div class="value" style="color: {{ $color }}; font-size: 12pt;">{{ $value }}</div>
                                        <div class="sub">{{ $sub }}</div>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                        <tr><td colspan="3" style="height: 8pt;"></td></tr>
                    @endforeach
                </table>

                <table class="dist">
                    <tr>
                        @if (($daily['positive_day_rate'] ?? 0) > 0)
                            <td style="width: {{ (float) $daily['positive_day_rate'] }}%; background: #10b981;"></td>
                        @endif
                        @if (($daily['negative_day_rate'] ?? 0) > 0)
                            <td style="width: {{ (float) $daily['negative_day_rate'] }}%; background: #f43f5e;"></td>
                        @endif
                        @if (($daily['neutral_day_rate'] ?? 0) > 0)
                            <td style="width: {{ (float) $daily['neutral_day_rate'] }}%; background: #94a3b8;"></td>
                        @endif
                    </tr>
                </table>

                <table class="cards">
                    @foreach (array_chunk($extremeCards, 3) as $row)
                        <tr>
                            @foreach ($row as [$label, $value, $color, $sub])
                                <td>
                                    <div class="card" style="border-left-color: {{ $color }};">
                                        <div class="label">{{ $label }}</div>
                                        <div class="value" style="color: {{ $color }}; font-size: 12pt;">{{ $value }}</div>
                                        <div class="sub">{{ $sub }}</div>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                        @if (! $loop->last)
                            <tr><td colspan="3" style="height: 8pt;"></td></tr>
                        @endif
                    @endforeach
                </table>
            </div>
        @endif
    @endif

    {{-- ===================== Strategies ===================== --}}
    <div class="section keep">
        <div class="section-head">
            <div class="section-title">Estratégias do portfólio</div>
            <div class="section-desc">Resultado e risco individual das estratégias vinculadas. Nas ativas os valores já refletem o peso; nas inativas aparecem sem peso.</div>
        </div>

        <table class="grid">
            <thead>
                <tr>
                    <th class="center" style="width: 4%;">#</th>
                    <th style="text-align: left;">Estratégia</th>
                    <th style="text-align: left; width: 11%;">Ativo</th>
                    <th style="width: 8%;">Status</th>
                    <th class="right" style="width: 7%;">Trades</th>
                    <th class="right" style="width: 13%;">Resultado</th>
                    <th style="width: 11%;">Contrib.</th>
                    <th class="right" style="width: 13%;">Drawdown</th>
                    <th class="right" style="width: 7%;">Peso</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaries as $index => $strategy)
                    @php
                        $profit = (float) ($strategy['net_profit'] ?? 0);
                        $enabled = (bool) ($strategy['enabled'] ?? false);
                        $share = $enabled ? min(abs($profit) / $totalAbsProfit * 100, 100) : 0;
                    @endphp
                    <tr>
                        <td class="center muted">{{ $index + 1 }}</td>
                        <td class="bold" style="color: #0f172a; font-size: 7.2pt;">{{ $strategy['name'] ?? '-' }}</td>
                        <td>{{ $assetLabels[$strategy['asset'] ?? null] ?? ($strategy['asset'] ?? '-') }}</td>
                        <td class="center"><span class="pill {{ $enabled ? 'pill-on' : 'pill-off' }}">{{ $enabled ? 'Ativa' : 'Inativa' }}</span></td>
                        <td class="right">{{ $integer($strategy['total_trades'] ?? 0) }}</td>
                        <td class="right bold" style="color: {{ $tone($profit) }};">{{ $signedMoney($profit) }}</td>
                        <td>
                            @if ($enabled)
                                <div class="bar-track"><div class="bar-fill" style="width: {{ round($share) }}%; background: {{ $profit >= 0 ? '#10b981' : '#f43f5e' }};"></div></div>
                                <div class="muted" style="margin-top: 1pt; font-size: 6.5pt;">{{ number_format($share, 1, ',', '.') }}%</div>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td class="right nowrap" style="color: #be123c;">
                            {{ (float) ($strategy['max_drawdown'] ?? 0) > 0 ? '-' : '' }}{{ $money($strategy['max_drawdown'] ?? 0) }}
                            <div style="font-size: 6.5pt;">{{ (float) ($strategy['max_drawdown_percent'] ?? 0) > 0 ? '-' : '' }}{{ $percent($strategy['max_drawdown_percent'] ?? 0) }}</div>
                        </td>
                        <td class="right bold">{{ $number($strategy['weight'] ?? 1) }}x</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="center muted" style="padding: 12pt;">Nenhuma estratégia vinculada.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="caption">Contribuição = participação do resultado absoluto da estratégia entre as estratégias ativas.</div>
    </div>

    {{-- ===================== Correlation ===================== --}}
    <div class="section">
        <div class="section-head">
            <div class="section-title">Correlação entre estratégias</div>
            <div class="section-desc">Coeficiente de Pearson sobre o {{ \Illuminate\Support\Str::lower($correlation['metric_label'] ?? 'Profit/Loss') }} líquido agregado por período ({{ \Illuminate\Support\Str::lower($correlation['period_label'] ?? 'diário') }}). Valores próximos de zero indicam melhor diversificação.</div>
        </div>

        @if (! ($correlation['has_enough_strategies'] ?? false))
            <div class="notice" style="background: #f1f5f9; color: #475569;">Adicione pelo menos duas estratégias ativas ao portfólio para calcular a correlação.</div>
        @elseif (! ($correlation['has_periods'] ?? false))
            <div class="notice" style="background: #f1f5f9; color: #475569;">Não há períodos suficientes com trades fechados para calcular correlação.</div>
        @else
            @php
                $correlationCards = [
                    ['Média absoluta', $correlationSummary['average_absolute_correlation_display'] ?? '-', 'Correlação média absoluta'],
                    ['Maior correlação', $correlationSummary['highest_correlation_display'] ?? '-', data_get($correlationSummary, 'highest_pair.label', 'Sem pares suficientes')],
                    ['Menor correlação', $correlationSummary['lowest_correlation_display'] ?? '-', data_get($correlationSummary, 'lowest_pair.label', 'Sem pares suficientes')],
                    ['Pares acima de 0,20', $integer($correlationSummary['pairs_above_020'] ?? 0), 'Acima da faixa ideal'],
                    ['Pares acima de 0,40', $integer($correlationSummary['pairs_above_040'] ?? 0), 'Correlação alta'],
                    ['Pares acima de 0,70', $integer($correlationSummary['pairs_above_070'] ?? 0), 'Forte redundância'],
                ];
                $interpretation = $interpretationStyles[data_get($correlationSummary, 'interpretation.type', 'neutral')] ?? $interpretationStyles['neutral'];
                $count = $strategyNumbers->count();
                $cellWidth = $count > 0 ? round(64 / $count, 2) : 10;
                $cellFont = $count > 14 ? 5.6 : ($count > 9 ? 6.4 : 7.4);
            @endphp

            <table class="cards">
                @foreach (array_chunk($correlationCards, 3) as $row)
                    <tr>
                        @foreach ($row as [$label, $value, $sub])
                            <td>
                                <div class="card">
                                    <div class="label">{{ $label }}</div>
                                    <div class="value" style="font-size: 12pt;">{{ $value }}</div>
                                    <div class="sub">{{ $sub }}</div>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                    @if (! $loop->last)
                        <tr><td colspan="3" style="height: 8pt;"></td></tr>
                    @endif
                @endforeach
            </table>

            @if (filled(data_get($correlationSummary, 'interpretation.message')))
                <div class="notice" style="background: {{ $interpretation[0] }}; color: {{ $interpretation[1] }};">
                    {{ data_get($correlationSummary, 'interpretation.message') }}
                </div>
            @endif

            @if (filled($correlationSummary['highlighted_pairs'] ?? []))
                <div style="margin-top: 8pt;">
                    @foreach ($correlationSummary['highlighted_pairs'] as $pair)
                        <span class="chip">{{ $pair['label'] ?? '-' }}: {{ $pair['display'] ?? '-' }}</span>
                    @endforeach
                </div>
            @endif

            <div class="keep">
                <div class="subtitle">Matriz de correlação</div>
                <table class="grid" style="table-layout: fixed;">
                    <thead>
                        <tr>
                            <th style="width: 36%; text-align: left;">Estratégia</th>
                            @foreach ($strategyNumbers as $number_)
                                <th style="width: {{ $cellWidth }}%; padding: 4pt 0;">{{ $loop->iteration }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($correlation['matrix'] ?? []) as $row)
                            <tr>
                                <td class="bold" style="background: #f1f5f9; color: #0f172a; font-size: 6.8pt;">{{ $loop->iteration }}. {{ data_get($row, 'strategy.name', '-') }}</td>
                                @foreach (($row['cells'] ?? []) as $cell)
                                    @php [$cellBg, $cellFg] = $correlationStyles[$cell['class'] ?? 'mqa-correlation-empty'] ?? $correlationStyles['mqa-correlation-empty']; @endphp
                                    <td class="center bold" style="padding: 4pt 0; background: {{ $cellBg }}; color: {{ $cellFg }}; font-size: {{ $cellFont }}pt;">{{ $cell['display'] ?? '-' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div style="margin-top: 7pt; font-size: 7pt; color: #475569;">
                    @foreach (($correlation['legend'] ?? []) as $legend)
                        @php [$legendBg] = $correlationStyles[$legend['class'] ?? 'mqa-correlation-empty'] ?? $correlationStyles['mqa-correlation-empty']; @endphp
                        <span style="margin-right: 8pt; white-space: nowrap;"><span class="legend-swatch" style="background: {{ $legendBg }};"></span><span class="bold">{{ $legend['label'] ?? '' }}:</span> {{ $legend['description'] ?? '' }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="disclaimer">
        <span class="bold">Como ler este relatório.</span> Todos os resultados são calculados a partir dos trades fechados das estratégias ativas do portfólio,
        multiplicados pelo peso de cada estratégia, e ordenados pela data de saída. Desempenho passado não garante resultados futuros.
        Este documento é informativo e não constitui recomendação de investimento.
    </div>
</body>
</html>
