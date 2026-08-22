@php
    $fmt = fn (mixed $v, int $dec = 2): string => number_format((float) $v, $dec, ',', '.');
    $fmtMoney = fn (mixed $v): string => 'R$ ' . number_format(abs((float) $v), 2, ',', '.');
    $fmtSigned = fn (mixed $v): string =>
        ((float) $v > 0 ? '+' : ((float) $v < 0 ? '-' : '')) . 'R$ ' . number_format(abs((float) $v), 2, ',', '.');
    $fmtPct = fn (mixed $v): string => number_format((float) $v, 2, ',', '.') . '%';
    $fmtNull = fn (mixed $v, string $suffix = ''): string => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . $suffix;

    $assetOptions = \App\Models\Strategy::assetOptions();

    $columns = [
        'consistency_score'       => 'Score',
        'total_net_profit'        => 'Lucro Líquido',
        'max_drawdown'            => 'Drawdown',
        'max_drawdown_percent'    => 'DD %',
        'profit_factor'           => 'Prof. Factor',
        'payoff'                  => 'Payoff',
        'win_rate'                => 'Win Rate',
        'positive_months_percent' => 'Meses +',
        'ulcer_index'             => 'Ulcer',
        'equity_r2'               => 'R²',
        'net_profit_to_drawdown'  => 'L/DD',
        'strategies_count'        => 'Qtd Est.',
        'total_trades'            => 'Trades',
    ];
@endphp

@once
<style>
.pca-wrap { padding: 1.5rem 0; display: flex; flex-direction: column; gap: 1.5rem; }
.pca-card {
    background: rgb(255 255 255);
    border: 1px solid rgb(229 231 235);
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / .07);
    overflow: hidden;
}
.dark .pca-card {
    background: rgb(17 24 39);
    border-color: rgb(31 41 55);
}
.pca-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgb(229 231 235);
    gap: 0.75rem;
    flex-wrap: wrap;
}
.dark .pca-card-header { border-color: rgb(31 41 55); }
.pca-card-title {
    font-size: 0.9375rem;
    font-weight: 650;
    color: rgb(17 24 39);
}
.dark .pca-card-title { color: rgb(249 250 251); }
.pca-card-body { padding: 1.25rem; }

.pca-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.pca-grid-2 > *, .pca-analysis-params > * { min-width: 0; }
@media (max-width: 768px) { .pca-grid-2 { grid-template-columns: 1fr; } }

.pca-filter-stack { display: flex; flex-direction: column; gap: .875rem; }
.pca-analysis-params { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
.pca-card-actions { display: flex; flex-wrap: wrap; gap: .5rem; }

.pca-label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 600;
    color: rgb(55 65 81);
    margin-bottom: 0.3rem;
}
.dark .pca-label { color: rgb(209 213 219); }

.pca-input {
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
.pca-input:focus { border-color: rgb(96 165 250); box-shadow: 0 0 0 3px rgb(96 165 250 / .2); }
.dark .pca-input {
    background: rgb(31 41 55);
    border-color: rgb(75 85 99);
    color: rgb(249 250 251);
}
.dark .pca-input:focus { border-color: rgb(96 165 250); }

.pca-strategy-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.5rem;
    max-height: 280px;
    overflow-y: auto;
    padding: 0.25rem;
}
.pca-strategy-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid rgb(229 231 235);
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background .1s, border-color .1s;
    user-select: none;
}
.pca-strategy-item:hover { background: rgb(239 246 255); border-color: rgb(147 197 253); }
.dark .pca-strategy-item { border-color: rgb(55 65 81); }
.dark .pca-strategy-item:hover { background: rgb(30 58 138 / .3); border-color: rgb(96 165 250); }
.pca-strategy-item input[type=checkbox] { width: 1rem; height: 1rem; accent-color: rgb(59 130 246); flex-shrink: 0; }
.pca-strategy-name { font-size: 0.8125rem; color: rgb(55 65 81); flex: 1; min-width: 0; word-break: break-word; }
.dark .pca-strategy-name { color: rgb(209 213 219); }
.pca-badge {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.125rem 0.375rem;
    border-radius: 9999px;
    background: rgb(219 234 254);
    color: rgb(29 78 216);
    flex-shrink: 0;
}
.dark .pca-badge { background: rgb(30 58 138); color: rgb(147 197 253); }

.pca-stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media (max-width: 640px) { .pca-stat-grid { grid-template-columns: 1fr 1fr; } }
.pca-stat {
    background: rgb(249 250 251);
    border: 1px solid rgb(229 231 235);
    border-radius: 0.5rem;
    padding: 0.875rem 1rem;
    text-align: center;
}
.dark .pca-stat { background: rgb(31 41 55); border-color: rgb(55 65 81); }
.pca-stat-value { font-size: 1.5rem; font-weight: 700; color: rgb(59 130 246); line-height: 1.2; }
.pca-stat-label { font-size: 0.75rem; color: rgb(107 114 128); margin-top: 0.25rem; }
.dark .pca-stat-label { color: rgb(156 163 175); }

.pca-alert {
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    margin-top: 1rem;
}
.pca-alert-warning { background: rgb(254 243 199); color: rgb(120 53 15); border: 1px solid rgb(252 211 77); }
.pca-alert-danger  { background: rgb(254 226 226); color: rgb(127 29 29); border: 1px solid rgb(252 165 165); }
.pca-alert-info    { background: rgb(239 246 255); color: rgb(29 78 216); border: 1px solid rgb(147 197 253); }
.pca-alert-success { background: rgb(220 252 231); color: rgb(21 128 61); border: 1px solid rgb(134 239 172); }
.dark .pca-alert-warning { background: rgb(120 53 15 / .2); color: rgb(252 211 77); border-color: rgb(120 53 15); }
.dark .pca-alert-danger  { background: rgb(127 29 29 / .2); color: rgb(252 165 165); border-color: rgb(127 29 29); }
.dark .pca-alert-info    { background: rgb(29 78 216 / .2); color: rgb(147 197 253); border-color: rgb(30 58 138); }
.dark .pca-alert-success { background: rgb(21 128 61 / .2); color: rgb(134 239 172); border-color: rgb(21 128 61); }

.pca-btn {
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
.pca-btn:disabled { opacity: .5; cursor: not-allowed; }
.pca-btn-primary { background: rgb(59 130 246); color: #fff; }
.pca-btn-primary:hover:not(:disabled) { background: rgb(37 99 235); }
.pca-btn-success { background: rgb(34 197 94); color: #fff; }
.pca-btn-success:hover:not(:disabled) { background: rgb(22 163 74); }
.pca-btn-sm { padding: 0.25rem 0.625rem; font-size: 0.75rem; }
.pca-btn-ghost {
    background: transparent;
    color: rgb(107 114 128);
    border: 1px solid rgb(229 231 235);
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    font-weight: 500;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background .1s;
}
.pca-btn-ghost:hover { background: rgb(243 244 246); }
.dark .pca-btn-ghost { border-color: rgb(55 65 81); color: rgb(156 163 175); }
.dark .pca-btn-ghost:hover { background: rgb(31 41 55); }

.pca-table-wrap { overflow-x: auto; }
.pca-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.pca-table th {
    padding: 0.5rem 0.625rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(107 114 128);
    background: rgb(249 250 251);
    border-bottom: 1px solid rgb(229 231 235);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 1;
}
.dark .pca-table th { background: rgb(31 41 55); color: rgb(156 163 175); border-color: rgb(55 65 81); }
.pca-table th.sortable { cursor: pointer; user-select: none; }
.pca-table th.sortable:hover { color: rgb(59 130 246); }
.pca-table th.sort-active { color: rgb(59 130 246); }
.pca-table td {
    padding: 0.5rem 0.625rem;
    border-bottom: 1px solid rgb(243 244 246);
    color: rgb(55 65 81);
    vertical-align: middle;
}
.dark .pca-table td { color: rgb(209 213 219); border-color: rgb(31 41 55); }
.pca-table tr:last-child td { border-bottom: none; }
.pca-table tr:hover td { background: rgb(249 250 251); }
.dark .pca-table tr:hover td { background: rgb(31 41 55 / .5); }

.pca-profit-pos { color: rgb(22 163 74); font-weight: 600; }
.pca-profit-neg { color: rgb(220 38 38); font-weight: 600; }
.pca-score-high { color: rgb(22 163 74); font-weight: 700; }
.pca-score-mid  { color: rgb(202 138 4); font-weight: 600; }
.pca-score-low  { color: rgb(220 38 38); font-weight: 600; }
.pca-tag-list { display: flex; flex-wrap: wrap; gap: 0.25rem; max-width: 220px; }
.pca-tag {
    font-size: 0.6875rem;
    padding: 0.125rem 0.375rem;
    background: rgb(239 246 255);
    color: rgb(29 78 216);
    border-radius: 9999px;
    white-space: nowrap;
}
.dark .pca-tag { background: rgb(30 58 138 / .4); color: rgb(147 197 253); }
.pca-row-number { color: rgb(156 163 175); font-size: 0.75rem; text-align: right; padding-right: 0.25rem; }
.dark .pca-legend-title { color: rgb(249 250 251) !important; }
.dark .pca-legend-desc  { color: rgb(156 163 175) !important; }

@media (max-width: 640px) {
    .pca-wrap { padding: 1rem 0; gap: 1.5rem; }
    .pca-card-header, .pca-card-body { padding: .875rem 1rem; }
    .pca-input, .pca-btn, .pca-btn-ghost { min-height: 2.75rem; font-size: 1rem; }
    .pca-btn-sm { min-height: 2.25rem; font-size: .8125rem; }
    .pca-strategy-item { min-height: 2.75rem; }
    .pca-table-wrap { -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; }
}

@media (max-width: 420px) {
    .pca-analysis-params, .pca-stat-grid { grid-template-columns: 1fr; }
    .pca-card-actions > * { flex: 1 1 auto; }
}
</style>
@endonce

<div class="pca-wrap">

    {{-- ====================== CONFIGURATION ====================== --}}
    <div class="pca-card">
        <div class="pca-card-header">
            <span class="pca-card-title">Configurações da Análise</span>
        </div>
        <div class="pca-card-body">
            <div class="pca-grid-2">
                {{-- Filters column --}}
                <div class="pca-filter-stack">
                    <div>
                        <label class="pca-label">Buscar estratégia</label>
                        <input
                            class="pca-input"
                            type="text"
                            wire:model.live.debounce.300ms="nameSearch"
                            placeholder="Nome da estratégia..."
                        >
                    </div>
                    <div>
                        <label class="pca-label">Ativo</label>
                        <select class="pca-input" wire:model.live="assetFilter">
                            <option value="">Todos os ativos</option>
                            @foreach ($assetOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Analysis params column --}}
                <div class="pca-analysis-params">
                    <div>
                        <label class="pca-label">Máx. estratégias por portfólio</label>
                        <input
                            class="pca-input"
                            type="number"
                            wire:model.live="maxStrategies"
                            min="2"
                            max="20"
                        >
                    </div>
                    <div>
                        <label class="pca-label">Saldo inicial (R$)</label>
                        <input
                            class="pca-input"
                            type="number"
                            wire:model.live="initialBalance"
                            min="0"
                            step="1000"
                        >
                    </div>
                    <div>
                        <label class="pca-label">Data inicial</label>
                        <input
                            class="pca-input"
                            type="date"
                            wire:model.live="startDate"
                        >
                    </div>
                    <div>
                        <label class="pca-label">Data final</label>
                        <input
                            class="pca-input"
                            type="date"
                            wire:model.live="endDate"
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================== STRATEGY SELECTION ====================== --}}
    <div class="pca-card">
        <div class="pca-card-header">
            <span class="pca-card-title">
                Seleção de Estratégias
                @if (count($selectedStrategyIds) > 0)
                    &mdash;
                    <span style="color:rgb(59 130 246)">{{ count($selectedStrategyIds) }}</span>
                    selecionada{{ count($selectedStrategyIds) === 1 ? '' : 's' }}
                @endif
            </span>
            <div class="pca-card-actions">
                <button class="pca-btn-ghost" wire:click="selectAllStrategies">Selecionar todas</button>
                <button class="pca-btn-ghost" wire:click="deselectAllStrategies">Limpar</button>
            </div>
        </div>
        <div class="pca-card-body">
            @if ($availableStrategies->isEmpty())
                <p style="color:rgb(107 114 128);font-size:.875rem;text-align:center;padding:1rem 0;">
                    Nenhuma estratégia encontrada com os filtros aplicados.
                </p>
            @else
                <div class="pca-strategy-grid">
                    @foreach ($availableStrategies as $strategy)
                        <label class="pca-strategy-item">
                            <input
                                type="checkbox"
                                wire:model.live="selectedStrategyIds"
                                value="{{ $strategy->id }}"
                            >
                            <span class="pca-strategy-name">{{ $strategy->name }}</span>
                            <span class="pca-badge">{{ $assetOptions[$strategy->asset] ?? $strategy->asset }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ====================== PREVIEW + ANALYZE ====================== --}}
    <div class="pca-card">
        <div class="pca-card-header">
            <span class="pca-card-title">Prévia</span>
        </div>
        <div class="pca-card-body">
            <div class="pca-stat-grid">
                <div class="pca-stat">
                    <div class="pca-stat-value">{{ count($selectedStrategyIds) }}</div>
                    <div class="pca-stat-label">Estratégias selecionadas</div>
                </div>
                <div class="pca-stat">
                    <div class="pca-stat-value">{{ $maxStrategies }}</div>
                    <div class="pca-stat-label">Máximo por portfólio</div>
                </div>
                <div class="pca-stat">
                    <div class="pca-stat-value" style="{{ $exceedsLimit ? 'color:rgb(220 38 38)' : '' }}">
                        {{ number_format($combinationsCount, 0, ',', '.') }}
                    </div>
                    <div class="pca-stat-label">Combinações a analisar</div>
                </div>
            </div>

            @if ($exceedsLimit)
                <div class="pca-alert pca-alert-danger" style="margin-top:.875rem;">
                    <strong>Limite excedido.</strong>
                    Essa seleção geraria {{ number_format($combinationsCount, 0, ',', '.') }} combinações,
                    acima do limite permitido de {{ number_format($maxCombinations, 0, ',', '.') }}.
                    Reduza o número de estratégias selecionadas ou diminua o máximo de estratégias por portfólio.
                </div>
            @elseif ($combinationsCount > 0)
                <div class="pca-alert pca-alert-info" style="margin-top:.875rem;">
                    Serão analisadas <strong>{{ number_format($combinationsCount, 0, ',', '.') }}</strong> combinações de portfólios.
                </div>
            @elseif (count($selectedStrategyIds) > 0)
                <div class="pca-alert pca-alert-warning" style="margin-top:.875rem;">
                    Selecione ao menos 2 estratégias e defina o máximo ≥ 2 para gerar combinações.
                </div>
            @endif

            @if ($errorMessage)
                <div class="pca-alert pca-alert-danger" style="margin-top:.875rem;">
                    {{ $errorMessage }}
                </div>
            @endif

            <div style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
                <button
                    class="pca-btn pca-btn-primary"
                    wire:click="analyze"
                    wire:loading.attr="disabled"
                    @if ($exceedsLimit || count($selectedStrategyIds) < 2 || $combinationsCount === 0) disabled @endif
                >
                    <span wire:loading.remove wire:target="analyze">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-.15em;margin-right:.2rem"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Analisar Portfólios
                    </span>
                    <span wire:loading wire:target="analyze">Analisando...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ====================== RESULTS ====================== --}}
    @if ($isAnalyzed)
        <div class="pca-card">
            <div class="pca-card-header">
                <span class="pca-card-title">
                    Ranking de Portfólios
                    <span style="font-weight:400;color:rgb(107 114 128);font-size:.875rem;">
                        — {{ number_format(count($sortedResults), 0, ',', '.') }} combinações
                    </span>
                </span>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    @if (count($selectedToSave) > 0)
                        <button
                            class="pca-btn pca-btn-success"
                            wire:click="saveSelected"
                            wire:loading.attr="disabled"
                            wire:target="saveSelected"
                        >
                            <span wire:loading.remove wire:target="saveSelected">
                                Salvar {{ count($selectedToSave) }} selecionado{{ count($selectedToSave) === 1 ? '' : 's' }}
                            </span>
                            <span wire:loading wire:target="saveSelected">Salvando...</span>
                        </button>
                    @endif
                    <button class="pca-btn-ghost" wire:click="selectAllResults">Selecionar todos</button>
                    <button class="pca-btn-ghost" wire:click="deselectAllResults">Limpar seleção</button>
                </div>
            </div>

            <div class="pca-card-body" style="padding:0;">
                <div class="pca-table-wrap">
                    <table class="pca-table">
                        <thead>
                            <tr>
                                <th style="width:2.5rem;text-align:center;">
                                    <input
                                        type="checkbox"
                                        style="width:1rem;height:1rem;accent-color:rgb(59 130 246);"
                                        @change="$event.target.checked ? $wire.selectAllResults() : $wire.deselectAllResults()"
                                    >
                                </th>
                                <th style="width:2.5rem;">#</th>
                                <th>Estratégias</th>
                                @foreach ($columns as $col => $label)
                                    <th
                                        class="sortable {{ $sortColumn === $col ? 'sort-active' : '' }}"
                                        wire:click="sortBy('{{ $col }}')"
                                        title="{{ $label }}"
                                    >
                                        {{ $label }}
                                        @if ($sortColumn === $col)
                                            {{ $sortDirection === 'desc' ? '↓' : '↑' }}
                                        @endif
                                    </th>
                                @endforeach
                                <th style="width:9rem;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sortedResults as $i => $result)
                                @php
                                    $hash = $result['combination_hash'];
                                    $names = (array) $result['strategy_names'];
                                    $profit = (float) $result['total_net_profit'];
                                    $score = (float) $result['consistency_score'];
                                    $scoreClass = $score >= 70 ? 'pca-score-high' : ($score >= 40 ? 'pca-score-mid' : 'pca-score-low');
                                    $profitClass = $profit >= 0 ? 'pca-profit-pos' : 'pca-profit-neg';

                                    $displayNames = count($names) <= 3
                                        ? $names
                                        : array_slice($names, 0, 2);
                                    $extraCount = count($names) > 3 ? count($names) - 2 : 0;
                                @endphp
                                <tr>
                                    <td style="text-align:center;">
                                        <input
                                            type="checkbox"
                                            style="width:1rem;height:1rem;accent-color:rgb(59 130 246);"
                                            wire:model="selectedToSave"
                                            value="{{ $hash }}"
                                        >
                                    </td>
                                    <td class="pca-row-number">{{ $i + 1 }}</td>
                                    <td>
                                        <div class="pca-tag-list">
                                            @foreach ($displayNames as $n)
                                                <span class="pca-tag">{{ $n }}</span>
                                            @endforeach
                                            @if ($extraCount > 0)
                                                <span class="pca-tag" style="background:rgb(243 244 246);color:rgb(107 114 128);">+{{ $extraCount }}</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Score --}}
                                    <td><span class="{{ $scoreClass }}">{{ $fmt($score) }}</span></td>

                                    {{-- Lucro Líquido --}}
                                    <td class="{{ $profitClass }}">{{ $fmtSigned($profit) }}</td>

                                    {{-- Drawdown --}}
                                    <td class="pca-profit-neg">{{ $fmtMoney($result['max_drawdown']) }}</td>

                                    {{-- DD % --}}
                                    <td class="pca-profit-neg">{{ $fmtPct($result['max_drawdown_percent']) }}</td>

                                    {{-- Profit Factor --}}
                                    <td>{{ $fmtNull($result['profit_factor']) }}</td>

                                    {{-- Payoff --}}
                                    <td>{{ $fmtNull($result['payoff']) }}</td>

                                    {{-- Win Rate --}}
                                    <td>{{ $fmtPct($result['win_rate']) }}</td>

                                    {{-- Meses + --}}
                                    <td>
                                        {{ $fmtPct($result['positive_months_percent']) }}
                                        <span style="font-size:.7rem;color:rgb(107 114 128);">
                                            ({{ $result['positive_months'] }}/{{ (int)$result['positive_months'] + (int)$result['negative_months'] }})
                                        </span>
                                    </td>

                                    {{-- Ulcer --}}
                                    <td>{{ $fmt($result['ulcer_index'], 2) }}</td>

                                    {{-- R² --}}
                                    <td>{{ $fmt($result['equity_r2'], 4) }}</td>

                                    {{-- L/DD --}}
                                    <td>{{ $fmtNull($result['net_profit_to_drawdown'], 'x') }}</td>

                                    {{-- Qtd Est. --}}
                                    <td style="text-align:center;">{{ $result['strategies_count'] }}</td>

                                    {{-- Trades --}}
                                    <td style="text-align:center;">{{ number_format($result['total_trades'], 0, ',', '.') }}</td>

                                    {{-- Ações --}}
                                    <td>
                                        <div style="display:flex;gap:.375rem;">
                                            <button
                                                class="pca-btn pca-btn-primary pca-btn-sm"
                                                wire:click="viewResults('{{ $hash }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="viewResults('{{ $hash }}')"
                                                title="Ver resultados completos"
                                            >
                                                <span wire:loading.remove wire:target="viewResults('{{ $hash }}')">Ver</span>
                                                <span wire:loading wire:target="viewResults('{{ $hash }}')">...</span>
                                            </button>
                                            <button
                                                class="pca-btn pca-btn-success pca-btn-sm"
                                                wire:click="saveSingle('{{ $hash }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="saveSingle('{{ $hash }}')"
                                                title="Salvar como portfólio"
                                            >
                                                <span wire:loading.remove wire:target="saveSingle('{{ $hash }}')">Salvar</span>
                                                <span wire:loading wire:target="saveSingle('{{ $hash }}')">...</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) + 4 }}" style="text-align:center;padding:2rem;color:rgb(107 114 128);">
                                        Nenhuma combinação encontrada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ====================== COLUMN LEGEND ====================== --}}
    @if ($isAnalyzed)
    <div class="pca-card">
        <div class="pca-card-header">
            <span class="pca-card-title">Guia das colunas</span>
        </div>
        <div class="pca-card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">

                <div style="display:flex;gap:.625rem;">
                    <div style="flex-shrink:0;width:2rem;height:2rem;background:rgb(239 246 255);border-radius:.375rem;display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:.75rem;font-weight:700;color:rgb(29 78 216);">S</span>
                    </div>
                    <div>
                        <p class="pca-legend-title" style="font-size:.8125rem;font-weight:600;color:rgb(17 24 39);margin:0 0 .125rem;">Score de Consistência</p>
                        <p class="pca-legend-desc" style="font-size:.75rem;color:rgb(107 114 128);margin:0;line-height:1.4;">Pontuação de 0 a 100 que combina R², Ulcer, meses positivos e relação lucro/drawdown. Quanto maior, mais suave e crescente é a curva de capital.</p>
                    </div>
                </div>

                <div style="display:flex;gap:.625rem;">
                    <div style="flex-shrink:0;width:2rem;height:2rem;background:rgb(240 253 244);border-radius:.375rem;display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:.75rem;font-weight:700;color:rgb(21 128 61);">PF</span>
                    </div>
                    <div>
                        <p class="pca-legend-title" style="font-size:.8125rem;font-weight:600;color:rgb(17 24 39);margin:0 0 .125rem;">Profit Factor</p>
                        <p class="pca-legend-desc" style="font-size:.75rem;color:rgb(107 114 128);margin:0;line-height:1.4;">Soma dos ganhos dividida pela soma das perdas. Acima de 1,5 é considerado sólido; acima de 2 é excelente. Valores abaixo de 1 indicam estratégia deficitária.</p>
                    </div>
                </div>

                <div style="display:flex;gap:.625rem;">
                    <div style="flex-shrink:0;width:2rem;height:2rem;background:rgb(240 253 244);border-radius:.375rem;display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:.75rem;font-weight:700;color:rgb(21 128 61);">PY</span>
                    </div>
                    <div>
                        <p class="pca-legend-title" style="font-size:.8125rem;font-weight:600;color:rgb(17 24 39);margin:0 0 .125rem;">Payoff</p>
                        <p class="pca-legend-desc" style="font-size:.75rem;color:rgb(107 114 128);margin:0;line-height:1.4;">Média dos ganhos dividida pela média das perdas. Indica a relação entre quanto o portfólio ganha nas operações vencedoras versus quanto perde nas perdedoras.</p>
                    </div>
                </div>

                <div style="display:flex;gap:.625rem;">
                    <div style="flex-shrink:0;width:2rem;height:2rem;background:rgb(254 249 195);border-radius:.375rem;display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:.75rem;font-weight:700;color:rgb(133 77 14);">M+</span>
                    </div>
                    <div>
                        <p class="pca-legend-title" style="font-size:.8125rem;font-weight:600;color:rgb(17 24 39);margin:0 0 .125rem;">Meses Positivos</p>
                        <p class="pca-legend-desc" style="font-size:.75rem;color:rgb(107 114 128);margin:0;line-height:1.4;">Percentual de meses com resultado positivo em relação aos meses que tiveram operações. Meses sem trades são ignorados. Acima de 70% indica boa consistência mensal.</p>
                    </div>
                </div>

                <div style="display:flex;gap:.625rem;">
                    <div style="flex-shrink:0;width:2rem;height:2rem;background:rgb(254 226 226);border-radius:.375rem;display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:.75rem;font-weight:700;color:rgb(153 27 27);">UI</span>
                    </div>
                    <div>
                        <p class="pca-legend-title" style="font-size:.8125rem;font-weight:600;color:rgb(17 24 39);margin:0 0 .125rem;">Ulcer Index</p>
                        <p class="pca-legend-desc" style="font-size:.75rem;color:rgb(107 114 128);margin:0;line-height:1.4;">Mede a profundidade e a duração dos drawdowns abaixo do topo histórico. Diferente do drawdown máximo, penaliza também períodos longos sem nova máxima. Quanto menor, melhor.</p>
                    </div>
                </div>

                <div style="display:flex;gap:.625rem;">
                    <div style="flex-shrink:0;width:2rem;height:2rem;background:rgb(239 246 255);border-radius:.375rem;display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:.75rem;font-weight:700;color:rgb(29 78 216);">R²</span>
                    </div>
                    <div>
                        <p class="pca-legend-title" style="font-size:.8125rem;font-weight:600;color:rgb(17 24 39);margin:0 0 .125rem;">R² da Curva de Capital</p>
                        <p class="pca-legend-desc" style="font-size:.75rem;color:rgb(107 114 128);margin:0;line-height:1.4;">Coeficiente de determinação de 0 a 1. Mede o quanto a curva de capital se parece com uma linha reta crescente. Próximo de 1 = curva linear e suave; próximo de 0 = curva errática.</p>
                    </div>
                </div>

                <div style="display:flex;gap:.625rem;">
                    <div style="flex-shrink:0;width:2rem;height:2rem;background:rgb(240 253 244);border-radius:.375rem;display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:.6875rem;font-weight:700;color:rgb(21 128 61);">L/D</span>
                    </div>
                    <div>
                        <p class="pca-legend-title" style="font-size:.8125rem;font-weight:600;color:rgb(17 24 39);margin:0 0 .125rem;">Lucro / Drawdown (L/DD)</p>
                        <p class="pca-legend-desc" style="font-size:.75rem;color:rgb(107 114 128);margin:0;line-height:1.4;">Relação entre o lucro líquido total e o drawdown máximo absoluto. Indica quantas vezes o lucro supera a pior sequência de perdas. Quanto maior, mais eficiente é o portfólio em relação ao risco.</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endif

</div>
