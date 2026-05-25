@php
    use App\Models\Strategy;

    $formatMoney = function (mixed $value, bool $withSign = false): string {
        $amount = (float) $value;
        $formatted = 'R$ ' . number_format(abs($amount), 2, ',', '.');

        if (! $withSign || $amount === 0.0) {
            return $amount < 0 ? '-' . $formatted : $formatted;
        }

        return ($amount > 0 ? '+' : '-') . $formatted;
    };

    $formatNumber = fn (mixed $value): string => number_format((int) $value, 0, ',', '.');
    $formatDate = fn (mixed $value): ?string => $value ? $value->format('d/m/Y H:i') : null;
    $assetLabel = fn (?string $asset): string => Strategy::assetOptions()[$asset] ?? ($asset ?: '-');

    $netProfit = (float) $metrics['net_profit'];
    $netProfitTone = match (true) {
        $netProfit > 0 => 'positive',
        $netProfit < 0 => 'negative',
        default => 'neutral',
    };

    $metricCards = [
        [
            'icon' => 'heroicon-o-beaker',
            'label' => 'Estratégias cadastradas',
            'value' => $formatNumber($metrics['strategies_count']),
            'description' => 'Estratégias disponíveis para importar e analisar resultados.',
            'tone' => 'neutral',
        ],
        [
            'icon' => 'heroicon-o-table-cells',
            'label' => 'Trades importados',
            'value' => $formatNumber($metrics['trades_count']),
            'description' => 'Operações únicas salvas e prontas para métricas.',
            'tone' => 'neutral',
        ],
        [
            'icon' => 'heroicon-o-briefcase',
            'label' => 'Portfólios salvos',
            'value' => $formatNumber($metrics['portfolios_count']),
            'description' => 'Conjuntos de estratégias preservados para revisão.',
            'tone' => 'neutral',
        ],
        [
            'icon' => 'heroicon-o-banknotes',
            'label' => 'Resultado líquido geral',
            'value' => $formatMoney($netProfit, true),
            'description' => 'Soma do resultado líquido de todos os trades importados.',
            'tone' => $netProfitTone,
        ],
    ];

    $quickActions = [
        [
            'icon' => 'heroicon-o-plus-circle',
            'title' => 'Cadastrar estratégia',
            'description' => 'Adicionar uma estratégia por nome e ativo.',
            'url' => $createStrategyUrl,
        ],
        [
            'icon' => 'heroicon-o-folder-plus',
            'title' => 'Criar portfólio',
            'description' => 'Combinar estratégias e salvar uma composição.',
            'url' => $createPortfolioUrl,
        ],
        [
            'icon' => 'heroicon-o-arrow-up-tray',
            'title' => 'Importar relatório MT5',
            'description' => 'Abrir estratégias para carregar relatórios XLSX ou CSV.',
            'url' => $strategiesUrl,
        ],
        [
            'icon' => 'heroicon-o-chart-pie',
            'title' => 'Ver portfólios',
            'description' => 'Revisar resultados consolidados dos portfólios salvos.',
            'url' => $portfoliosUrl,
        ],
    ];

    $latestStrategy = $operational['latest_strategy'];
    $latestPortfolio = $operational['latest_portfolio'];
    $latestReportFile = $operational['latest_report_file'];

    $statusItems = [
        [
            'icon' => 'heroicon-o-beaker',
            'label' => 'Última estratégia cadastrada',
            'title' => $latestStrategy?->name,
            'description' => $latestStrategy
                ? $assetLabel($latestStrategy->asset) . ' · ' . $formatDate($latestStrategy->created_at)
                : 'Nenhuma estratégia cadastrada ainda.',
        ],
        [
            'icon' => 'heroicon-o-document-chart-bar',
            'label' => 'Último backtest importado',
            'title' => $latestReportFile?->original_filename,
            'description' => $latestReportFile
                ? trim(($latestReportFile->strategy?->name ?? 'Estratégia não informada') . ' · ' . ($formatDate($latestReportFile->imported_at) ?? $formatDate($latestReportFile->created_at)), ' ·')
                : 'Nenhum relatório importado ainda.',
        ],
        [
            'icon' => 'heroicon-o-folder-open',
            'label' => 'Arquivos MT5 importados',
            'title' => $formatNumber($operational['mt5_report_files_count']),
            'description' => 'Relatórios registrados no histórico de importação.',
        ],
        [
            'icon' => 'heroicon-o-briefcase',
            'label' => 'Último portfólio criado',
            'title' => $latestPortfolio?->name,
            'description' => $latestPortfolio
                ? $formatDate($latestPortfolio->created_at)
                : 'Nenhum portfólio criado ainda.',
        ],
    ];
@endphp

@once
    <style>
        .mqa-dashboard {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .mqa-dashboard-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1fr);
        }

        @media (min-width: 768px) {
            .mqa-dashboard-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .mqa-dashboard-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .mqa-dashboard-section {
            border: 1px solid rgb(229 231 235);
            border-radius: .5rem;
            background: rgb(255 255 255);
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .dark .mqa-dashboard-section {
            border-color: rgba(75, 85, 99, .58);
            background: rgb(17 24 39);
            box-shadow: none;
        }

        .mqa-dashboard-section-header {
            margin-bottom: 1rem;
        }

        .mqa-dashboard-section-title {
            color: rgb(17 24 39);
            font-size: .95rem;
            font-weight: 650;
            line-height: 1.25rem;
        }

        .dark .mqa-dashboard-section-title {
            color: rgb(249 250 251);
        }

        .mqa-dashboard-section-description {
            margin-top: .25rem;
            color: rgb(107 114 128);
            font-size: .8125rem;
            line-height: 1.25rem;
        }

        .dark .mqa-dashboard-section-description {
            color: rgb(156 163 175);
        }

        .mqa-dashboard-card {
            min-width: 0;
            border: 1px solid rgb(229 231 235);
            border-radius: .5rem;
            background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(249, 250, 251, .92));
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .dark .mqa-dashboard-card {
            border-color: rgba(75, 85, 99, .62);
            background: linear-gradient(180deg, rgba(31, 41, 55, .92), rgba(17, 24, 39, .92));
            box-shadow: none;
        }

        .mqa-dashboard-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
        }

        .mqa-dashboard-icon {
            display: inline-flex;
            width: 2.25rem;
            height: 2.25rem;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            border-radius: .5rem;
            background: rgb(243 244 246);
            color: rgb(75 85 99);
        }

        .dark .mqa-dashboard-icon {
            background: rgba(55, 65, 81, .72);
            color: rgb(209 213 219);
        }

        .mqa-dashboard-icon svg {
            width: 1.2rem;
            height: 1.2rem;
        }

        .mqa-metric-label {
            margin-top: .9rem;
            color: rgb(107 114 128);
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
            line-height: 1rem;
            text-transform: uppercase;
        }

        .dark .mqa-metric-label {
            color: rgb(156 163 175);
        }

        .mqa-metric-value {
            margin-top: .35rem;
            color: rgb(17 24 39);
            font-size: 1.65rem;
            font-variant-numeric: tabular-nums;
            font-weight: 760;
            line-height: 2rem;
        }

        .dark .mqa-metric-value {
            color: rgb(249 250 251);
        }

        .mqa-metric-description {
            margin-top: .55rem;
            color: rgb(107 114 128);
            font-size: .8125rem;
            line-height: 1.25rem;
        }

        .dark .mqa-metric-description {
            color: rgb(156 163 175);
        }

        .mqa-tone-positive .mqa-dashboard-icon {
            background: rgba(16, 185, 129, .11);
            color: rgb(5 150 105);
        }

        .dark .mqa-tone-positive .mqa-dashboard-icon {
            background: rgba(16, 185, 129, .16);
            color: rgb(52 211 153);
        }

        .mqa-tone-positive .mqa-metric-value {
            color: rgb(5 150 105);
        }

        .dark .mqa-tone-positive .mqa-metric-value {
            color: rgb(52 211 153);
        }

        .mqa-tone-negative .mqa-dashboard-icon {
            background: rgba(244, 63, 94, .11);
            color: rgb(225 29 72);
        }

        .dark .mqa-tone-negative .mqa-dashboard-icon {
            background: rgba(244, 63, 94, .16);
            color: rgb(251 113 133);
        }

        .mqa-tone-negative .mqa-metric-value {
            color: rgb(225 29 72);
        }

        .dark .mqa-tone-negative .mqa-metric-value {
            color: rgb(251 113 133);
        }

        .mqa-action-card {
            display: flex;
            min-width: 0;
            align-items: flex-start;
            gap: .85rem;
            border: 1px solid rgb(229 231 235);
            border-radius: .5rem;
            background: rgb(255 255 255);
            padding: 1rem;
            text-decoration: none;
            transition: border-color .16s ease, background-color .16s ease, box-shadow .16s ease, transform .16s ease;
        }

        .mqa-action-card:hover {
            border-color: rgba(16, 185, 129, .55);
            background: rgba(236, 253, 245, .42);
            box-shadow: 0 8px 22px rgba(15, 23, 42, .07);
            transform: translateY(-1px);
        }

        .dark .mqa-action-card {
            border-color: rgba(75, 85, 99, .62);
            background: rgba(31, 41, 55, .72);
        }

        .dark .mqa-action-card:hover {
            border-color: rgba(52, 211, 153, .52);
            background: rgba(6, 78, 59, .18);
            box-shadow: none;
        }

        .mqa-action-title {
            display: block;
            color: rgb(17 24 39);
            font-size: .9rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .dark .mqa-action-title {
            color: rgb(249 250 251);
        }

        .mqa-action-description {
            display: block;
            margin-top: .25rem;
            color: rgb(107 114 128);
            font-size: .8rem;
            line-height: 1.2rem;
        }

        .dark .mqa-action-description {
            color: rgb(156 163 175);
        }

        .mqa-status-item {
            display: flex;
            min-width: 0;
            gap: .85rem;
            border: 1px solid rgb(229 231 235);
            border-radius: .5rem;
            background: rgb(249 250 251);
            padding: .95rem;
        }

        .dark .mqa-status-item {
            border-color: rgba(75, 85, 99, .52);
            background: rgba(31, 41, 55, .7);
        }

        .mqa-status-content {
            min-width: 0;
        }

        .mqa-status-label {
            color: rgb(107 114 128);
            font-size: .74rem;
            font-weight: 700;
            letter-spacing: .04em;
            line-height: 1rem;
            text-transform: uppercase;
        }

        .dark .mqa-status-label {
            color: rgb(156 163 175);
        }

        .mqa-status-title {
            overflow: hidden;
            margin-top: .25rem;
            color: rgb(17 24 39);
            font-size: .95rem;
            font-weight: 700;
            line-height: 1.3rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .mqa-status-title {
            color: rgb(249 250 251);
        }

        .mqa-status-empty {
            color: rgb(107 114 128);
            font-weight: 600;
        }

        .dark .mqa-status-empty {
            color: rgb(156 163 175);
        }

        .mqa-status-description {
            margin-top: .25rem;
            color: rgb(107 114 128);
            font-size: .8rem;
            line-height: 1.2rem;
        }

        .dark .mqa-status-description {
            color: rgb(156 163 175);
        }
    </style>
@endonce

<div class="mqa-dashboard">
    <section class="mqa-dashboard-grid" aria-label="Métricas principais">
        @foreach ($metricCards as $card)
            <article class="mqa-dashboard-card mqa-tone-{{ $card['tone'] }}">
                <div class="mqa-dashboard-card-top">
                    <span class="mqa-dashboard-icon">
                        <x-filament::icon :icon="$card['icon']" />
                    </span>
                </div>

                <div class="mqa-metric-label">{{ $card['label'] }}</div>
                <div class="mqa-metric-value">{{ $card['value'] }}</div>
                <p class="mqa-metric-description">{{ $card['description'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mqa-dashboard-section">
        <div class="mqa-dashboard-section-header">
            <h2 class="mqa-dashboard-section-title">Ações rápidas</h2>
            <p class="mqa-dashboard-section-description">Acesse os fluxos principais do Mayer Quant Analyzer.</p>
        </div>

        <div class="mqa-dashboard-grid">
            @foreach ($quickActions as $action)
                <a href="{{ $action['url'] }}" class="mqa-action-card">
                    <span class="mqa-dashboard-icon">
                        <x-filament::icon :icon="$action['icon']" />
                    </span>

                    <span>
                        <span class="mqa-action-title">{{ $action['title'] }}</span>
                        <span class="mqa-action-description">{{ $action['description'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mqa-dashboard-section">
        <div class="mqa-dashboard-section-header">
            <h2 class="mqa-dashboard-section-title">Status do sistema</h2>
            <p class="mqa-dashboard-section-description">Resumo operacional dos cadastros e importações mais recentes.</p>
        </div>

        <div class="mqa-dashboard-grid">
            @foreach ($statusItems as $item)
                <article class="mqa-status-item">
                    <span class="mqa-dashboard-icon">
                        <x-filament::icon :icon="$item['icon']" />
                    </span>

                    <div class="mqa-status-content">
                        <div class="mqa-status-label">{{ $item['label'] }}</div>
                        <div class="mqa-status-title {{ $item['title'] ? '' : 'mqa-status-empty' }}">
                            {{ $item['title'] ?: $item['description'] }}
                        </div>

                        @if ($item['title'])
                            <div class="mqa-status-description">{{ $item['description'] }}</div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
