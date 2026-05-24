<x-filament-widgets::widget>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">{{ $heading }}</div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->items() as $item)
                <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-950/40">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $item['label'] }}</div>
                    <div class="mt-1 text-lg font-semibold {{ $item['classes'] }}">{{ $item['value'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
