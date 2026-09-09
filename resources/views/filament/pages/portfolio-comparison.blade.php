@php
    use App\Filament\Resources\Portfolios\PortfolioResource;

    $money = fn (mixed $value): string => 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $signedMoney = fn (mixed $value): string =>
        ((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $percent = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
    $integer = fn (mixed $value): string => number_format((float) $value, 0, ',', '.');

    $formatValue = function (mixed $value, string $format) use ($money, $signedMoney, $percent, $integer): string {
        if ($value === null) {
            return '—';
        }

        return match ($format) {
            'money' => $signedMoney($value),
            'money_negative' => (float) $value > 0 ? '-' . $money($value) : $money(0),
            'percent' => $percent($value),
            'ratio' => number_format((float) $value, 2, ',', '.'),
            'ratio_4' => number_format((float) $value, 3, ',', '.'),
            'integer' => $integer($value),
            default => (string) $value,
        };
    };

    $formatDifference = function (?float $value, string $format) use ($signedMoney, $integer): string {
        if ($value === null) {
            return '—';
        }

        if (abs($value) < 0.0000001) {
            return 'igual';
        }

        $signal = $value > 0 ? '+' : '-';

        return match ($format) {
            'money', 'money_negative' => $signedMoney($value),
            'percent' => $signal . number_format(abs($value), 2, ',', '.') . ' p.p.',
            'ratio' => $signal . number_format(abs($value), 2, ',', '.'),
            'ratio_4' => $signal . number_format(abs($value), 3, ',', '.'),
            'integer' => $signal . $integer(abs($value)),
            default => $signal . $value,
        };
    };

    $emptyLabel = fn (string $key, bool $hasData): string => $hasData
        ? match ($key) {
            'profit_factor' => 'Sem perdas',
            'average_payoff' => 'Sem perdas',
            'net_profit_to_drawdown' => 'Sem drawdown',
            default => '—',
        }
        : '—';

    $valueTone = function (mixed $value, string $format): string {
        if ($value === null) {
            return 'sc-muted';
        }

        if ($format === 'money_negative') {
            return (float) $value > 0 ? 'sc-negative' : 'sc-muted';
        }

        if ($format !== 'money') {
            return '';
        }

        return match (true) {
            (float) $value > 0 => 'sc-positive',
            (float) $value < 0 => 'sc-negative',
            default => 'sc-muted',
        };
    };

    $rowGroups = collect($comparison['rows'] ?? [])->groupBy('group');
    $first = $comparison['portfolios'][0] ?? null;
    $second = $comparison['portfolios'][1] ?? null;
@endphp

@once
<style>
.sc-wrap { padding: 1.5rem 0; display: flex; flex-direction: column; gap: 1.5rem; }
.sc-card {
    background: rgb(255 255 255);
    border: 1px solid rgb(229 231 235);
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / .07);
    overflow: hidden;
}
.dark .sc-card { background: rgb(17 24 39); border-color: rgb(31 41 55); }
.sc-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgb(229 231 235);
}
.dark .sc-card-header { border-color: rgb(31 41 55); }
.sc-card-title { font-size: 0.9375rem; font-weight: 650; color: rgb(17 24 39); }
.dark .sc-card-title { color: rgb(249 250 251); }
.sc-card-subtitle { font-size: 0.75rem; color: rgb(107 114 128); margin-top: 0.125rem; }
.dark .sc-card-subtitle { color: rgb(156 163 175); }
.sc-card-body { padding: 1.25rem; }

.sc-select-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: end; }
@media (max-width: 900px) { .sc-select-grid { grid-template-columns: 1fr; } }
.sc-side-stack { display: flex; flex-direction: column; gap: .75rem; min-width: 0; }
.sc-params { display: grid; grid-template-columns: minmax(0, 260px); gap: .875rem; margin-top: 1rem; }
@media (max-width: 700px) { .sc-params { grid-template-columns: 1fr; } }

.sc-label { display: block; font-size: 0.8125rem; font-weight: 600; color: rgb(55 65 81); margin-bottom: 0.3rem; }
.dark .sc-label { color: rgb(209 213 219); }
.sc-input {
    width: 100%;
    min-width: 0;
    padding: 0.4375rem 0.75rem;
    font-size: 0.875rem;
    border: 1px solid rgb(209 213 219);
    border-radius: 0.5rem;
    background: rgb(255 255 255);
    color: rgb(17 24 39);
    outline: none;
    transition: border-color .15s;
}
.sc-input:focus { border-color: rgb(96 165 250); box-shadow: 0 0 0 3px rgb(96 165 250 / .2); }
.dark .sc-input { background: rgb(31 41 55); border-color: rgb(75 85 99); color: rgb(249 250 251); }

.sc-swap { display: flex; align-items: center; justify-content: center; padding-bottom: .25rem; }
.sc-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: 0.5rem;
    border: none;
    cursor: pointer;
    transition: background .15s, opacity .15s;
}
.sc-btn:disabled { opacity: .5; cursor: not-allowed; }
.sc-btn-primary { background: rgb(59 130 246); color: #fff; }
.sc-btn-primary:hover:not(:disabled) { background: rgb(37 99 235); }
.sc-btn-ghost {
    background: transparent;
    color: rgb(107 114 128);
    border: 1px solid rgb(229 231 235);
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    font-weight: 500;
    border-radius: 0.5rem;
    cursor: pointer;
}
.sc-btn-ghost:hover { background: rgb(243 244 246); }
.dark .sc-btn-ghost { border-color: rgb(55 65 81); color: rgb(156 163 175); }
.dark .sc-btn-ghost:hover { background: rgb(31 41 55); }
.sc-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }

.sc-alert { padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; }
.sc-alert-danger { background: rgb(254 226 226); color: rgb(127 29 29); border: 1px solid rgb(252 165 165); }
.sc-alert-info { background: rgb(239 246 255); color: rgb(29 78 216); border: 1px solid rgb(147 197 253); }
.sc-alert-success { background: rgb(220 252 231); color: rgb(21 128 61); border: 1px solid rgb(134 239 172); }
.sc-alert-warning { background: rgb(254 243 199); color: rgb(120 53 15); border: 1px solid rgb(252 211 77); }
.dark .sc-alert-danger { background: rgb(127 29 29 / .2); color: rgb(252 165 165); border-color: rgb(127 29 29); }
.dark .sc-alert-info { background: rgb(29 78 216 / .2); color: rgb(147 197 253); border-color: rgb(30 58 138); }
.dark .sc-alert-success { background: rgb(21 128 61 / .2); color: rgb(134 239 172); border-color: rgb(21 128 61); }
.dark .sc-alert-warning { background: rgb(120 53 15 / .2); color: rgb(252 211 77); border-color: rgb(120 53 15); }

.sc-verdict { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; justify-content: space-between; }
.sc-verdict-score { display: flex; align-items: center; gap: .5rem; font-size: 0.8125rem; font-weight: 600; }
.sc-score-pill {
    display: inline-flex;
    align-items: baseline;
    gap: .3rem;
    padding: .25rem .625rem;
    border-radius: 9999px;
    background: rgb(243 244 246);
    color: rgb(31 41 55);
}
.dark .sc-score-pill { background: rgb(31 41 55); color: rgb(229 231 235); }

.sc-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 800px) { .sc-grid-2 { grid-template-columns: 1fr; } }

.sc-strategy-card { border-radius: .75rem; border: 1px solid rgb(229 231 235); background: rgb(255 255 255); overflow: hidden; }
.dark .sc-strategy-card { border-color: rgb(31 41 55); background: rgb(17 24 39); }
.sc-strategy-top { padding: .875rem 1rem; border-bottom: 1px solid rgb(229 231 235); border-left: 4px solid var(--sc-color); }
.dark .sc-strategy-top { border-bottom-color: rgb(31 41 55); }
.sc-strategy-name { font-size: 0.9375rem; font-weight: 700; color: rgb(17 24 39); word-break: break-word; }
.dark .sc-strategy-name { color: rgb(249 250 251); }
.sc-strategy-meta { margin-top: .25rem; font-size: .75rem; color: rgb(107 114 128); display: flex; flex-wrap: wrap; gap: .375rem 0.75rem; }
.dark .sc-strategy-meta { color: rgb(156 163 175); }
.sc-mini-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; padding: 1rem; }
.sc-mini { background: rgb(249 250 251); border: 1px solid rgb(229 231 235); border-radius: .5rem; padding: .625rem .75rem; }
.dark .sc-mini { background: rgb(31 41 55); border-color: rgb(55 65 81); }
.sc-mini-label { font-size: .6875rem; color: rgb(107 114 128); }
.dark .sc-mini-label { color: rgb(156 163 175); }
.sc-mini-value { font-size: 1.0625rem; font-weight: 700; line-height: 1.35; font-variant-numeric: tabular-nums; }
.sc-badge {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    background: rgb(219 234 254);
    color: rgb(29 78 216);
}
.dark .sc-badge { background: rgb(30 58 138); color: rgb(147 197 253); }
.sc-badge-leader { background: rgb(220 252 231); color: rgb(21 128 61); }
.dark .sc-badge-leader { background: rgb(21 128 61 / .3); color: rgb(134 239 172); }

.sc-positive { color: rgb(22 163 74); font-weight: 650; }
.sc-negative { color: rgb(220 38 38); font-weight: 650; }
.sc-muted { color: rgb(107 114 128); }
.dark .sc-positive { color: rgb(110 231 183); }
.dark .sc-negative { color: rgb(253 164 175); }
.dark .sc-muted { color: rgb(156 163 175); }

.sc-table-wrap { overflow-x: auto; }
.sc-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; min-width: 640px; }
.sc-table th {
    padding: 0.5rem 0.75rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(107 114 128);
    background: rgb(249 250 251);
    border-bottom: 1px solid rgb(229 231 235);
    white-space: nowrap;
}
.dark .sc-table th { background: rgb(31 41 55); color: rgb(156 163 175); border-color: rgb(55 65 81); }
.sc-table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid rgb(243 244 246);
    color: rgb(55 65 81);
    vertical-align: middle;
    font-variant-numeric: tabular-nums;
}
.dark .sc-table td { color: rgb(209 213 219); border-color: rgb(31 41 55); }
.sc-table tr:last-child td { border-bottom: none; }
.sc-table tbody tr:hover td { background: rgb(249 250 251); }
.dark .sc-table tbody tr:hover td { background: rgb(31 41 55 / .5); }
.sc-group-row td {
    background: rgb(243 244 246);
    font-weight: 700;
    font-size: .75rem;
    color: rgb(55 65 81);
    text-transform: uppercase;
    letter-spacing: .03em;
}
.dark .sc-group-row td { background: rgb(31 41 55); color: rgb(209 213 219); }
.sc-num { text-align: right; white-space: nowrap; }
.sc-win { background: rgb(220 252 231 / .7); font-weight: 700; }
.dark .sc-win { background: rgb(21 128 61 / .22); }
.sc-metric-label { font-weight: 600; color: rgb(31 41 55); }
.dark .sc-metric-label { color: rgb(229 231 235); }
.sc-metric-hint { display: block; font-weight: 400; font-size: .6875rem; color: rgb(107 114 128); margin-top: .125rem; }
.dark .sc-metric-hint { color: rgb(156 163 175); }
.sc-dot { display: inline-block; width: .625rem; height: .625rem; border-radius: 9999px; flex-shrink: 0; }
.sc-legend { display: flex; flex-wrap: wrap; gap: 1rem; padding: 0 1.25rem 1.25rem; font-size: .8125rem; }
.sc-legend-item { display: flex; align-items: center; gap: .4rem; color: rgb(55 65 81); }
.dark .sc-legend-item { color: rgb(209 213 219); }

.sc-correlation-value { font-size: 2rem; font-weight: 700; line-height: 1.1; }
.mqa-correlation-good { color: rgb(22 163 74); }
.mqa-correlation-warning { color: rgb(202 138 4); }
.mqa-correlation-high { color: rgb(234 88 12); }
.mqa-correlation-critical { color: rgb(220 38 38); }
.mqa-correlation-empty { color: rgb(107 114 128); }
.dark .mqa-correlation-good { color: rgb(134 239 172); }
.dark .mqa-correlation-warning { color: rgb(253 224 71); }
.dark .mqa-correlation-high { color: rgb(253 186 116); }
.dark .mqa-correlation-critical { color: rgb(252 165 165); }

.sc-stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .875rem; }
@media (max-width: 900px) { .sc-stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .sc-stat-grid { grid-template-columns: 1fr; } }

.sc-empty { padding: 2rem 1.25rem; text-align: center; color: rgb(107 114 128); font-size: .875rem; }
.dark .sc-empty { color: rgb(156 163 175); }

@media (max-width: 640px) {
    .sc-wrap { padding: 1rem 0; gap: 1.25rem; }
    .sc-card-header, .sc-card-body { padding: .875rem 1rem; }
    .sc-input, .sc-btn, .sc-btn-ghost { min-height: 2.75rem; font-size: 1rem; }
    .sc-table-wrap { -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; }
}
</style>
@endonce

<div class="sc-wrap">

    {{-- ====================== SELEÇÃO ====================== --}}
    <div class="sc-card">
        <div class="sc-card-header">
            <div>
                <div class="sc-card-title">Portfólios comparados</div>
                <div class="sc-card-subtitle">Escolha dois portfólios salvos. As métricas usam as estratégias ativas e os pesos atuais de cada um.</div>
            </div>
            <a href="{{ $portfoliosUrl }}" class="sc-btn-ghost">Ver portfólios</a>
        </div>
        <div class="sc-card-body">
            <div class="sc-select-grid">
                <div class="sc-side-stack">
                    <div>
                        <label class="sc-label" for="pc-first">Portfólio A</label>
                        <select id="pc-first" class="sc-input" wire:model.live="firstPortfolioId">
                            <option value="">Selecione um portfólio...</option>
                            @foreach ($availablePortfolios as $portfolio)
                                <option value="{{ $portfolio->id }}">{{ $portfolio->name }} ({{ $portfolio->strategies_count }} estratégias)</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="sc-swap">
                    <button type="button" class="sc-btn-ghost" wire:click="swapPortfolios" title="Inverter A e B">⇄ Inverter</button>
                </div>

                <div class="sc-side-stack">
                    <div>
                        <label class="sc-label" for="pc-second">Portfólio B</label>
                        <select id="pc-second" class="sc-input" wire:model.live="secondPortfolioId">
                            <option value="">Selecione um portfólio...</option>
                            @foreach ($availablePortfolios as $portfolio)
                                <option value="{{ $portfolio->id }}">{{ $portfolio->name }} ({{ $portfolio->strategies_count }} estratégias)</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="sc-params">
                <div>
                    <label class="sc-label" for="pc-period">Período da correlação</label>
                    <select id="pc-period" class="sc-input" wire:model.live="correlationPeriod">
                        @foreach ($periodOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="sc-actions">
                <button type="button" class="sc-btn sc-btn-primary" wire:click="compare" wire:loading.attr="disabled" wire:target="compare">
                    <span wire:loading.remove wire:target="compare">Comparar portfólios</span>
                    <span wire:loading wire:target="compare">Calculando...</span>
                </button>
                <button type="button" class="sc-btn-ghost" wire:click="clearComparison">Limpar</button>
            </div>

            @if ($errorMessage)
                <div class="sc-alert sc-alert-danger" style="margin-top: 1rem;">{{ $errorMessage }}</div>
            @endif
        </div>
    </div>

    @if (! $isCompared)
        <div class="sc-card">
            <div class="sc-empty">Selecione dois portfólios e clique em <strong>Comparar portfólios</strong> para ver o resultado lado a lado.</div>
        </div>
    @else
        {{-- ====================== VEREDITO ====================== --}}
        <div class="sc-alert sc-alert-{{ $comparison['verdict']['type'] === 'success' ? 'success' : 'info' }}">
            <div class="sc-verdict">
                <span>{{ $comparison['verdict']['message'] }}</span>
                <span class="sc-verdict-score">
                    <span class="sc-score-pill">
                        <span class="sc-dot" style="background: {{ $first['color'] }}"></span>
                        {{ $comparison['verdict']['first_wins'] }}
                    </span>
                    <span class="sc-muted">x</span>
                    <span class="sc-score-pill">
                        <span class="sc-dot" style="background: {{ $second['color'] }}"></span>
                        {{ $comparison['verdict']['second_wins'] }}
                    </span>
                </span>
            </div>
        </div>

        {{-- ====================== RESUMO POR PORTFÓLIO ====================== --}}
        <div class="sc-grid-2">
            @foreach ([$first, $second] as $index => $side)
                @php $m = $side['metrics']; @endphp
                <div class="sc-strategy-card">
                    <div class="sc-strategy-top" style="--sc-color: {{ $side['color'] }}">
                        <div style="display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;">
                            <span class="sc-dot" style="background: {{ $side['color'] }}"></span>
                            <a class="sc-strategy-name" style="text-decoration: none;" href="{{ PortfolioResource::getUrl('results', ['record' => $side['id']]) }}">
                                {{ $index === 0 ? 'A' : 'B' }}. {{ $side['name'] }}
                            </a>
                            @if ($comparison['verdict']['leader'] === $index)
                                <span class="sc-badge sc-badge-leader">Melhor no geral</span>
                            @endif
                        </div>
                        <div class="sc-strategy-meta">
                            <span class="sc-badge">{{ $integer($side['strategies_count']) }} estratégias ativas</span>
                            <span>{{ $integer($m['total_trades']) }} trades</span>
                        </div>
                    </div>
                    <div class="sc-mini-grid">
                        <div class="sc-mini">
                            <div class="sc-mini-label">Resultado líquido</div>
                            <div class="sc-mini-value {{ $valueTone($m['net_profit'], 'money') }}">{{ $signedMoney($m['net_profit']) }}</div>
                        </div>
                        <div class="sc-mini">
                            <div class="sc-mini-label">Drawdown máximo</div>
                            <div class="sc-mini-value {{ (float) $m['max_drawdown'] > 0 ? 'sc-negative' : 'sc-muted' }}">
                                {{ (float) $m['max_drawdown'] > 0 ? '-' . $money($m['max_drawdown']) : $money(0) }}
                            </div>
                        </div>
                        <div class="sc-mini">
                            <div class="sc-mini-label">Profit factor</div>
                            <div class="sc-mini-value">{{ $m['profit_factor'] === null ? ($side['has_data'] ? 'Sem perdas' : '—') : number_format((float) $m['profit_factor'], 2, ',', '.') }}</div>
                        </div>
                        <div class="sc-mini">
                            <div class="sc-mini-label">Meses positivos / R²</div>
                            <div class="sc-mini-value">{{ $percent($m['positive_months_percent']) }} <span class="sc-muted" style="font-size: .8125rem; font-weight: 500;">/ {{ number_format((float) $m['equity_r2'], 3, ',', '.') }}</span></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ====================== CURVAS SOBREPOSTAS ====================== --}}
        <div class="sc-card">
            <div class="sc-card-header">
                <div>
                    <div class="sc-card-title">Curvas de capital sobrepostas</div>
                    <div class="sc-card-subtitle">Resultado consolidado acumulado dos dois portfólios no mesmo eixo de tempo.</div>
                </div>
            </div>

            @if (! ($comparison['chart']['has_data'] ?? false))
                <div class="sc-empty">Nenhum trade fechado encontrado para desenhar as curvas.</div>
            @else
                @php $chart = $comparison['chart']; @endphp
                <div style="padding: 1rem 1.25rem 0;">
                    <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="h-80 w-full" style="height: 20rem; width: 100%;" preserveAspectRatio="none" role="img" aria-label="Curvas de capital comparadas">
                        @foreach ($chart['y_ticks'] as $tick)
                            <line x1="{{ $chart['left'] }}" y1="{{ $tick['y'] }}" x2="{{ $chart['right'] }}" y2="{{ $tick['y'] }}" stroke="#9ca3af" stroke-opacity="0.25" stroke-width="1" />
                            <text x="{{ $chart['left'] - 8 }}" y="{{ $tick['y'] + 4 }}" text-anchor="end" fill="#9ca3af" font-size="11">{{ $tick['label'] }}</text>
                        @endforeach

                        @if ($chart['zero_y'] !== null)
                            <line x1="{{ $chart['left'] }}" y1="{{ $chart['zero_y'] }}" x2="{{ $chart['right'] }}" y2="{{ $chart['zero_y'] }}" stroke="#6b7280" stroke-width="1" stroke-dasharray="4 4" />
                        @endif

                        <line x1="{{ $chart['left'] }}" y1="{{ $chart['bottom'] }}" x2="{{ $chart['right'] }}" y2="{{ $chart['bottom'] }}" stroke="#6b7280" stroke-width="1" />
                        <line x1="{{ $chart['left'] }}" y1="{{ $chart['top'] }}" x2="{{ $chart['left'] }}" y2="{{ $chart['bottom'] }}" stroke="#6b7280" stroke-width="1" />

                        @foreach ($chart['series'] as $series)
                            @if ($series['has_points'])
                                <polyline points="{{ $series['line'] }}" fill="none" stroke="{{ $series['color'] }}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                                @if ($series['final_x'] !== null)
                                    <circle cx="{{ $series['final_x'] }}" cy="{{ $series['final_y'] }}" r="4" fill="{{ $series['color'] }}">
                                        <title>{{ $series['label'] }} | {{ $signedMoney($series['final_equity']) }}</title>
                                    </circle>
                                @endif
                            @endif
                        @endforeach

                        @foreach ($chart['x_ticks'] as $tick)
                            <line x1="{{ $tick['x'] }}" y1="{{ $chart['bottom'] }}" x2="{{ $tick['x'] }}" y2="{{ $chart['bottom'] + 5 }}" stroke="#6b7280" stroke-width="1" />
                            <text x="{{ $tick['x'] }}" y="{{ $chart['bottom'] + 22 }}" text-anchor="{{ $tick['anchor'] }}" fill="#9ca3af" font-size="11">{{ $tick['label'] }}</text>
                        @endforeach
                    </svg>
                </div>
                <div class="sc-legend">
                    @foreach ($chart['series'] as $series)
                        <span class="sc-legend-item">
                            <span class="sc-dot" style="background: {{ $series['color'] }}"></span>
                            {{ $series['label'] }} — <strong>{{ $signedMoney($series['final_equity']) }}</strong>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ====================== TABELA DE MÉTRICAS ====================== --}}
        <div class="sc-card">
            <div class="sc-card-header">
                <div>
                    <div class="sc-card-title">Métricas lado a lado</div>
                    <div class="sc-card-subtitle">A célula destacada indica o portfólio com melhor número em cada métrica.</div>
                </div>
            </div>
            <div class="sc-table-wrap">
                <table class="sc-table">
                    <thead>
                        <tr>
                            <th style="min-width: 240px;">Métrica</th>
                            <th class="sc-num">
                                <span class="sc-dot" style="background: {{ $first['color'] }}"></span>
                                A. {{ $first['name'] }}
                            </th>
                            <th class="sc-num">
                                <span class="sc-dot" style="background: {{ $second['color'] }}"></span>
                                B. {{ $second['name'] }}
                            </th>
                            <th class="sc-num">Diferença (A - B)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rowGroups as $group => $groupRows)
                            <tr class="sc-group-row">
                                <td colspan="4">{{ $group }}</td>
                            </tr>
                            @foreach ($groupRows as $row)
                                <tr>
                                    <td>
                                        <span class="sc-metric-label">{{ $row['label'] }}</span>
                                        <span class="sc-metric-hint">{{ $row['hint'] }}</span>
                                    </td>
                                    <td class="sc-num {{ $row['winner'] === 0 ? 'sc-win' : '' }}">
                                        <span class="{{ $valueTone($row['first_value'], $row['format']) }}">
                                            {{ $row['first_value'] === null ? $emptyLabel($row['key'], (bool) $first['has_data']) : $formatValue($row['first_value'], $row['format']) }}
                                        </span>
                                    </td>
                                    <td class="sc-num {{ $row['winner'] === 1 ? 'sc-win' : '' }}">
                                        <span class="{{ $valueTone($row['second_value'], $row['format']) }}">
                                            {{ $row['second_value'] === null ? $emptyLabel($row['key'], (bool) $second['has_data']) : $formatValue($row['second_value'], $row['format']) }}
                                        </span>
                                    </td>
                                    <td class="sc-num sc-muted">{{ $formatDifference($row['difference'], $row['format']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ====================== ANO A ANO ====================== --}}
        <div class="sc-card">
            <div class="sc-card-header">
                <div>
                    <div class="sc-card-title">Resultado ano a ano</div>
                    <div class="sc-card-subtitle">Resultado líquido consolidado de cada portfólio em cada ano com operações fechadas.</div>
                </div>
            </div>
            @if (empty($comparison['annual']))
                <div class="sc-empty">Nenhum ano com operações fechadas.</div>
            @else
                <div class="sc-table-wrap">
                    <table class="sc-table">
                        <thead>
                            <tr>
                                <th style="min-width: 90px;">Ano</th>
                                <th class="sc-num">A. {{ $first['name'] }}</th>
                                <th class="sc-num">B. {{ $second['name'] }}</th>
                                <th class="sc-num">Diferença (A - B)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comparison['annual'] as $year)
                                <tr>
                                    <td class="sc-metric-label">{{ $year['year'] }}</td>
                                    <td class="sc-num {{ $year['winner'] === 0 ? 'sc-win' : '' }}">
                                        <span class="{{ $valueTone($year['first_value'], 'money') }}">{{ $year['first_value'] === null ? '—' : $signedMoney($year['first_value']) }}</span>
                                    </td>
                                    <td class="sc-num {{ $year['winner'] === 1 ? 'sc-win' : '' }}">
                                        <span class="{{ $valueTone($year['second_value'], 'money') }}">{{ $year['second_value'] === null ? '—' : $signedMoney($year['second_value']) }}</span>
                                    </td>
                                    <td class="sc-num sc-muted">{{ $formatDifference($year['difference'], 'money') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ====================== CORRELAÇÃO E CONFRONTO ====================== --}}
        <div class="sc-grid-2">
            <div class="sc-card">
                <div class="sc-card-header">
                    <div>
                        <div class="sc-card-title">Correlação</div>
                        <div class="sc-card-subtitle">Resultado {{ mb_strtolower($comparison['filters']['period_label']) }} de um portfólio comparado ao do outro.</div>
                    </div>
                </div>
                <div class="sc-card-body">
                    <div class="sc-correlation-value {{ $comparison['correlation']['class'] }}">{{ $comparison['correlation']['display'] }}</div>
                    <div style="margin-top: .375rem; font-weight: 600;" class="{{ $comparison['correlation']['class'] }}">{{ $comparison['correlation']['description'] }}</div>
                    <p style="margin-top: .625rem; font-size: .8125rem;" class="sc-muted">{{ $comparison['correlation']['message'] }}</p>
                    <p style="margin-top: .375rem; font-size: .75rem;" class="sc-muted">
                        Baseada em {{ $integer($comparison['correlation']['period_count']) }} períodos ({{ mb_strtolower($comparison['filters']['period_label']) }}).
                    </p>
                </div>
            </div>

            <div class="sc-card">
                <div class="sc-card-header">
                    <div>
                        <div class="sc-card-title">Confronto direto</div>
                        <div class="sc-card-subtitle">Em quantos períodos cada portfólio entregou o melhor resultado.</div>
                    </div>
                </div>
                <div class="sc-card-body">
                    @php $head = $comparison['head_to_head']; @endphp
                    <div class="sc-grid-2">
                        <div class="sc-mini">
                            <div class="sc-mini-label">A. {{ $first['name'] }}</div>
                            <div class="sc-mini-value" style="color: {{ $first['color'] }}">{{ $integer($head['first_wins']) }}</div>
                            <div class="sc-mini-label">{{ $percent($head['first_win_rate']) }} dos períodos</div>
                        </div>
                        <div class="sc-mini">
                            <div class="sc-mini-label">B. {{ $second['name'] }}</div>
                            <div class="sc-mini-value" style="color: {{ $second['color'] }}">{{ $integer($head['second_wins']) }}</div>
                            <div class="sc-mini-label">{{ $percent($head['second_win_rate']) }} dos períodos</div>
                        </div>
                    </div>
                    <p style="margin-top: .75rem; font-size: .8125rem;" class="sc-muted">
                        {{ $integer($head['period_count']) }} períodos analisados, {{ $integer($head['ties']) }} com empate.
                    </p>
                </div>
            </div>
        </div>

        {{-- ====================== COMBINAÇÃO ====================== --}}
        @php $combined = $comparison['combined']; @endphp
        <div class="sc-card">
            <div class="sc-card-header">
                <div>
                    <div class="sc-card-title">E se rodar os dois juntos?</div>
                    <div class="sc-card-subtitle">Métricas da carteira formada pela união das estratégias ativas dos dois portfólios (peso médio quando repetida).</div>
                </div>
            </div>
            @if (! $combined['has_data'])
                <div class="sc-empty">Nenhum trade fechado para simular os dois portfólios juntos.</div>
            @else
                <div class="sc-card-body">
                    <div class="sc-stat-grid">
                        <div class="sc-mini">
                            <div class="sc-mini-label">Resultado líquido</div>
                            <div class="sc-mini-value {{ $valueTone($combined['net_profit'], 'money') }}">{{ $signedMoney($combined['net_profit']) }}</div>
                            <div class="sc-mini-label">Melhor individual: {{ $signedMoney($combined['best_individual_net_profit']) }}</div>
                        </div>
                        <div class="sc-mini">
                            <div class="sc-mini-label">Drawdown da combinação</div>
                            <div class="sc-mini-value sc-negative">{{ (float) $combined['max_drawdown'] > 0 ? '-' . $money($combined['max_drawdown']) : $money(0) }}</div>
                            <div class="sc-mini-label">Soma dos individuais: {{ $money($combined['drawdown_sum']) }}</div>
                        </div>
                        <div class="sc-mini">
                            <div class="sc-mini-label">Profit factor</div>
                            <div class="sc-mini-value">{{ $combined['profit_factor'] === null ? 'Sem perdas' : number_format((float) $combined['profit_factor'], 2, ',', '.') }}</div>
                            <div class="sc-mini-label">Lucro/DD: {{ $combined['net_profit_to_drawdown'] === null ? '—' : number_format((float) $combined['net_profit_to_drawdown'], 2, ',', '.') }}</div>
                        </div>
                        <div class="sc-mini">
                            <div class="sc-mini-label">Meses positivos</div>
                            <div class="sc-mini-value">{{ $percent($combined['positive_months_percent']) }}</div>
                            <div class="sc-mini-label">{{ $integer($combined['total_trades']) }} trades · Ulcer {{ number_format((float) $combined['ulcer_index'], 2, ',', '.') }}</div>
                        </div>
                    </div>

                    @if ($combined['drawdown_reduction'] > 0)
                        <div class="sc-alert sc-alert-success" style="margin-top: 1rem;">
                            Rodando os dois juntos, o drawdown máximo ficou {{ $money($combined['drawdown_reduction']) }}
                            ({{ $percent($combined['drawdown_reduction_percent']) }}) menor do que a soma dos drawdowns individuais — sinal de que os períodos ruins não coincidem totalmente.
                        </div>
                    @else
                        <div class="sc-alert sc-alert-warning" style="margin-top: 1rem;">
                            O drawdown da combinação ficou igual ou maior que a soma dos drawdowns individuais, indicando que os dois portfólios sofrem nos mesmos períodos.
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <p class="sc-muted" style="font-size: .75rem; text-align: right;">Comparação gerada em {{ $comparison['generated_at'] }}.</p>
    @endif
</div>
