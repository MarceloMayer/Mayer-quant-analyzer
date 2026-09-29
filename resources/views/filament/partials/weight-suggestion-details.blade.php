{{--
    Body of a weight-optimizer result: notes, weights table, metric comparison and the
    equity-curve chart. Shared by the results page (live suggestion) and the history page
    (saved runs). Expects $suggestion (the optimizer result array) and the money/percent
    formatting closures.
--}}
@php
    $wsMetricRows = [
        ['key' => 'ulcer_index', 'label' => 'Ulcer Index', 'fmt' => 'ratio', 'lower_better' => true],
        ['key' => 'positive_months_percent', 'label' => 'Meses positivos', 'fmt' => 'percent', 'lower_better' => false],
        ['key' => 'max_drawdown', 'label' => 'Drawdown máximo', 'fmt' => 'money_neg', 'lower_better' => true],
        ['key' => 'max_drawdown_percent', 'label' => 'Drawdown máximo %', 'fmt' => 'percent', 'lower_better' => true],
        ['key' => 'net_profit', 'label' => 'Resultado líquido', 'fmt' => 'money', 'lower_better' => false],
        ['key' => 'net_profit_to_drawdown', 'label' => 'Fator de Recuperação', 'fmt' => 'ratio', 'lower_better' => false],
        ['key' => 'profit_factor', 'label' => 'Fator de lucro', 'fmt' => 'ratio', 'lower_better' => false],
        ['key' => 'equity_r2', 'label' => 'R² da curva', 'fmt' => 'ratio4', 'lower_better' => false],
        ['key' => 'positive_days', 'label' => 'Dias positivos', 'fmt' => 'integer', 'lower_better' => false],
        ['key' => 'negative_days', 'label' => 'Dias negativos', 'fmt' => 'integer', 'lower_better' => true],
    ];

    if (($suggestion['has_correlation'] ?? false) === true) {
        $wsMetricRows[] = ['key' => 'weighted_correlation', 'label' => 'Correlação média ponderada', 'fmt' => 'ratio', 'lower_better' => true];
    }

    $wsFmt = function (string $fmt, mixed $value) use ($formatMoney, $formatSignedMoney, $formatPercent): string {
        return match ($fmt) {
            'money' => $formatSignedMoney($value),
            'money_neg' => (float) $value > 0 ? '-' . $formatMoney($value) : $formatMoney(0),
            'percent' => $formatPercent($value),
            'ratio4' => number_format((float) $value, 4, ',', '.'),
            'integer' => number_format((float) $value, 0, ',', '.'),
            default => number_format((float) $value, 2, ',', '.'),
        };
    };
    $wsCurrent = $suggestion['current_metrics'] ?? [];
    $wsSuggested = $suggestion['suggested_metrics'] ?? [];
    $wsWeight = fn (mixed $value): string => (float) $value == (int) $value
        ? number_format((float) $value, 0, ',', '.') . 'x'
        : number_format((float) $value, 2, ',', '.') . 'x';
    $wsEquity = fn (mixed $value): string => ((float) $value < 0 ? '-' : '') . $formatMoney($value);
    $wsChart = $suggestion['chart'] ?? [];
@endphp

@if (($suggestion['changed'] ?? false) !== true)
    <p class="mqa-wopt-note">Os pesos atuais já são os melhores encontrados para esse objetivo — nada a aplicar.</p>
@endif

@if (($suggestion['constraint']['applied'] ?? false) === true && ($suggestion['constraint']['satisfied'] ?? true) !== true)
    <p class="mqa-wopt-note">Nenhuma combinação respeita a correlação média máxima de {{ number_format((float) $suggestion['constraint']['max_weighted_correlation'], 2, ',', '.') }}; a sugestão abaixo é a mais próxima do limite.</p>
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
                @foreach ($suggestion['strategies'] ?? [] as $ws)
                    @php $wsChanged = abs((float) $ws['current_weight'] - (float) $ws['suggested_weight']) >= 0.01; @endphp
                    <tr>
                        <td class="mqa-left">{{ $ws['name'] }}</td>
                        <td class="mqa-right">{{ $wsWeight($ws['current_weight']) }}</td>
                        <td class="mqa-right {{ $wsChanged ? 'mqa-wopt-changed' : '' }}">{{ $wsWeight($ws['suggested_weight']) }}</td>
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
                        // A null value means "no losing trades" (fator de lucro): shown as such and ranked above any number.
                        $curNull = array_key_exists($row['key'], $wsCurrent) && $wsCurrent[$row['key']] === null;
                        $sugNull = array_key_exists($row['key'], $wsSuggested) && $wsSuggested[$row['key']] === null;
                        $cur = $curNull ? PHP_FLOAT_MAX : (float) ($wsCurrent[$row['key']] ?? 0);
                        $sug = $sugNull ? PHP_FLOAT_MAX : (float) ($wsSuggested[$row['key']] ?? 0);
                        $delta = $curNull && $sugNull ? 0.0 : round($sug - $cur, 4);
                        $moved = abs($delta) < 1e-9 ? 0 : ($delta > 0 ? 1 : -1);
                        $better = $moved === 0 ? null : ($row['lower_better'] ? $delta < 0 : $delta > 0);
                    @endphp
                    <tr>
                        <td class="mqa-left">{{ $row['label'] }}</td>
                        <td class="mqa-right">{{ $curNull ? 'Sem perdas' : $wsFmt($row['fmt'], $cur) }}</td>
                        <td class="mqa-right {{ $better === true ? 'mqa-wopt-up' : ($better === false ? 'mqa-wopt-down' : '') }}">
                            {{ $sugNull ? 'Sem perdas' : $wsFmt($row['fmt'], $sug) }}
                            @if ($moved === 1) &uarr; @elseif ($moved === -1) &darr; @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if (($wsChart['has_data'] ?? false) === true)
    <div class="mqa-wopt-panel mqa-wopt-chart">
        <div class="mqa-wopt-chart-title">Curva de capital: pesos atuais x sugeridos</div>
        <div class="mqa-wopt-chart-plot">
            @include('filament.partials.dual-line-chart', [
                'chart' => $wsChart,
                'ariaLabel' => 'Curva de capital com os pesos atuais e os pesos sugeridos',
                'formatMoney' => $wsEquity,
            ])
        </div>
        <div class="mqa-wopt-legend">
            @foreach ($wsChart['series'] as $series)
                <span class="mqa-wopt-legend-item">
                    <span class="mqa-wopt-dot" style="background: {{ $series['color'] }}"></span>
                    {{ $series['label'] }} — <strong>{{ $wsEquity($series['final_equity']) }}</strong>
                </span>
            @endforeach
        </div>
    </div>
@endif
