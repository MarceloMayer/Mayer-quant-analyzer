@php
    $formatMoney = fn (mixed $value): string => number_format((float) $value, 2, ',', '.');
    $moneyTone = fn (mixed $value): string => match (true) {
        (float) $value > 0 => 'text-emerald-600 dark:text-emerald-400',
        (float) $value < 0 => 'text-rose-600 dark:text-rose-400',
        default => 'text-gray-600 dark:text-gray-300',
    };

    $cards = [
        ['label' => 'Estratégias cadastradas', 'value' => $metrics['strategies_count'], 'tone' => 'text-gray-950 dark:text-white'],
        ['label' => 'Trades importados', 'value' => $metrics['trades_count'], 'tone' => 'text-gray-950 dark:text-white'],
        ['label' => 'Portfólios salvos', 'value' => $metrics['portfolios_count'], 'tone' => 'text-gray-950 dark:text-white'],
        ['label' => 'Resultado líquido geral', 'value' => $formatMoney($metrics['net_profit']), 'tone' => $moneyTone($metrics['net_profit'])],
    ];
@endphp

<div class="space-y-5">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                <div class="mt-2 text-2xl font-semibold {{ $card['tone'] }}">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Ações rápidas</div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ $createStrategyUrl }}" class="inline-flex items-center justify-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Cadastrar estratégia
            </a>
            <a href="{{ $createPortfolioUrl }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-900">
                Criar portfólio
            </a>
        </div>
    </div>
</div>
