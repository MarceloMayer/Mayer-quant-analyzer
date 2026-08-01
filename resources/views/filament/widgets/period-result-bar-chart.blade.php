<x-filament-widgets::widget>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        @if (filled($heading) || filled($description))
            <div class="mb-3">
                @if (filled($heading))
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $heading }}</div>
                @endif

                @if (filled($description))
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
                @endif
            </div>
        @endif

        @if ($this->bars() === [])
            <div class="rounded-md bg-gray-50 px-3 py-8 text-center text-sm text-gray-500 dark:bg-gray-950/40 dark:text-gray-400">
                Nenhum resultado disponível para este gráfico.
            </div>
        @else
            <svg viewBox="0 0 900 240" class="h-72 w-full overflow-visible" preserveAspectRatio="none" role="img">
                <line x1="48" y1="{{ $this->bars()[0]['zero_y'] ?? 120 }}" x2="882" y2="{{ $this->bars()[0]['zero_y'] ?? 120 }}" stroke="#6b7280" stroke-width="1" />

                @foreach ($this->bars() as $bar)
                    <rect
                        x="{{ $bar['x'] }}"
                        y="{{ $bar['y'] }}"
                        width="{{ $bar['width'] }}"
                        height="{{ $bar['height'] }}"
                        rx="3"
                        fill="{{ $this->fill($bar['value']) }}"
                        opacity="0.88"
                    >
                        <title>{{ $bar['label'] }} | {{ $this->formatMoney($bar['value']) }} | {{ $bar['trades'] }} operações</title>
                    </rect>
                @endforeach

                @foreach ($this->bars() as $index => $bar)
                    @if ($index === 0 || $index === count($this->bars()) - 1 || $index % max(1, (int) floor(count($this->bars()) / 6)) === 0)
                        <text x="{{ $bar['label_x'] }}" y="226" text-anchor="middle" fill="#9ca3af" font-size="11">{{ $bar['label'] }}</text>
                    @endif
                @endforeach
            </svg>
        @endif
    </div>
</x-filament-widgets::widget>
