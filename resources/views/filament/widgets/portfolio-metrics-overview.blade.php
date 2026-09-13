<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach ($this->heroCards() as $card)
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                        <div class="mt-1 text-2xl font-bold {{ $card['valueClass'] }}">{{ $card['value'] }}</div>
                    </div>

                    @if (isset($card['aside_value']))
                        <div class="text-right">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['aside_label'] }}</div>
                            <div class="mt-1 text-base font-semibold text-gray-700 dark:text-gray-200">{{ $card['aside_value'] }}</div>
                        </div>
                    @endif
                </div>

                @if (isset($card['sub']))
                    <div class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                        @foreach ($card['sub'] as $sub)
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $sub['label'] }}</div>
                                <div class="mt-0.5 text-sm font-semibold {{ $sub['valueClass'] ?? 'text-gray-700 dark:text-gray-200' }}">{{ $sub['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (isset($card['total_days']))
                    <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ number_format($card['total_days'], 0, ',', '.') }} dias no total</span>
                            <span>
                                <span class="text-emerald-600 dark:text-emerald-400">{{ number_format($card['positive_days'], 0, ',', '.') }}+</span>
                                <span class="mx-1">/</span>
                                <span class="text-rose-600 dark:text-rose-400">{{ number_format($card['negative_days'], 0, ',', '.') }}-</span>
                            </span>
                        </div>
                        <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <span class="bg-emerald-500" style="width: {{ $card['positive_day_rate'] }}%;"></span>
                            <span class="bg-rose-500" style="width: {{ $card['negative_day_rate'] }}%;"></span>
                            <span class="bg-gray-400 dark:bg-gray-600" style="width: {{ $card['neutral_day_rate'] }}%;"></span>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($this->secondaryStats() as $stat)
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                <div class="mt-1 text-lg font-semibold {{ $stat['valueClass'] }}">{{ $stat['value'] }}</div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
