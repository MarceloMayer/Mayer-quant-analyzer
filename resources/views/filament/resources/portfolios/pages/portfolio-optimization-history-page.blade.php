@php
    $formatMoney = fn (mixed $value): string => 'R$ ' . number_format(abs((float) $value), 2, ',', '.');
    $formatSignedMoney = fn (mixed $value): string => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . $formatMoney($value);
    $formatPercent = fn (mixed $value): string => number_format((float) $value, 2, ',', '.') . '%';
    $percentMetrics = ['positive_months_percent', 'max_drawdown_percent'];
@endphp

@include('filament.partials.weight-optimizer-styles')

<div class="space-y-4">
    <div class="mqa-wopt-toolbar">
        <label class="mqa-wopt-check">
            <input type="checkbox" wire:model.live="onlyFavorites">
            <span>Somente favoritas</span>
        </label>
        <span class="mqa-wopt-count">{{ $optimizations->count() }} {{ $optimizations->count() === 1 ? 'sugestão' : 'sugestões' }}</span>
    </div>

    @forelse ($optimizations as $optimization)
        @php
            $result = $optimization->result ?? [];
            $improvement = $result['improvement'] ?? null;
            $improvementMetric = $improvement['metric'] ?? null;
            $formatImprovement = fn (mixed $value): string => in_array($improvementMetric, $percentMetrics, true)
                ? $formatPercent($value)
                : number_format((float) $value, 2, ',', '.');
            $isExpanded = $expandedId === $optimization->id;
        @endphp

        <section class="mqa-wopt-card" wire:key="wopt-{{ $optimization->id }}">
            <div class="mqa-wopt-card-head">
                <button
                    type="button"
                    class="mqa-wopt-star {{ $optimization->is_favorite ? 'mqa-wopt-star-on' : '' }}"
                    wire:click="toggleFavorite({{ $optimization->id }})"
                    title="{{ $optimization->is_favorite ? 'Remover dos favoritos' : 'Marcar como favorita' }}"
                    aria-label="{{ $optimization->is_favorite ? 'Remover dos favoritos' : 'Marcar como favorita' }}"
                >{!! $optimization->is_favorite ? '&#9733;' : '&#9734;' !!}</button>

                <div class="mqa-wopt-card-main">
                    <div class="mqa-wopt-card-title">
                        {{ $result['objective_label'] ?? $optimization->objective }}
                        <span class="mqa-wopt-card-date">&middot; {{ $optimization->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="mqa-wopt-card-meta">
                        Pesos de {{ $optimization->min_weight }} a {{ $optimization->max_weight }}
                        @if ($optimization->max_correlation !== null)
                            &middot; correlação média máx. {{ number_format((float) $optimization->max_correlation, 2, ',', '.') }}
                        @endif
                        @if ($improvement !== null)
                            &middot; {{ $formatImprovement($improvement['current']) }} &rarr; <strong class="{{ ($improvement['better'] ?? false) ? 'mqa-wopt-up' : '' }}">{{ $formatImprovement($improvement['suggested']) }}</strong>
                        @endif
                    </div>
                    <div class="mqa-wopt-chips">
                        @foreach ($result['strategies'] ?? [] as $strategy)
                            @php $chipChanged = abs((float) $strategy['current_weight'] - (float) $strategy['suggested_weight']) >= 0.01; @endphp
                            <span class="mqa-wopt-chip {{ $chipChanged ? 'mqa-wopt-chip-changed' : '' }}">
                                {{ $strategy['name'] }}: {{ rtrim(rtrim(number_format((float) $strategy['current_weight'], 2, ',', '.'), '0'), ',') }}x &rarr; {{ rtrim(rtrim(number_format((float) $strategy['suggested_weight'], 2, ',', '.'), '0'), ',') }}x
                            </span>
                        @endforeach
                    </div>
                    @if ($optimization->applied_at !== null)
                        <div class="mqa-wopt-badge">Aplicada em {{ $optimization->applied_at->format('d/m/Y H:i') }}</div>
                    @endif
                </div>

                <div class="mqa-wopt-actions">
                    <button type="button" class="mqa-wopt-btn mqa-wopt-btn-ghost" wire:click="toggleExpanded({{ $optimization->id }})">
                        {{ $isExpanded ? 'Ocultar detalhes' : 'Ver detalhes' }}
                    </button>
                    <button
                        type="button"
                        class="mqa-wopt-btn mqa-wopt-btn-primary"
                        wire:click="applyOptimization({{ $optimization->id }})"
                        wire:confirm="Aplicar os pesos desta sugestão ao portfólio? Os pesos atuais serão substituídos."
                        wire:loading.attr="disabled"
                        wire:target="applyOptimization({{ $optimization->id }})"
                    >Aplicar estes pesos</button>
                    <button
                        type="button"
                        class="mqa-wopt-btn mqa-wopt-btn-ghost"
                        wire:click="deleteOptimization({{ $optimization->id }})"
                        wire:confirm="Excluir esta sugestão do histórico?"
                    >Excluir</button>
                </div>
            </div>

            @if ($isExpanded)
                <div class="mqa-wopt-body">
                    @include('filament.partials.weight-suggestion-details', [
                        'suggestion' => $result,
                        'formatMoney' => $formatMoney,
                        'formatSignedMoney' => $formatSignedMoney,
                        'formatPercent' => $formatPercent,
                    ])
                </div>
            @endif
        </section>
    @empty
        <div class="mqa-wopt-empty">
            @if ($onlyFavorites)
                Nenhuma sugestão favorita ainda. Marque a estrela em uma sugestão para vê-la aqui.
            @else
                Nenhuma otimização registrada para este portfólio. Use "Otimizar pesos" na página de resultados para gerar a primeira.
            @endif
        </div>
    @endforelse
</div>
