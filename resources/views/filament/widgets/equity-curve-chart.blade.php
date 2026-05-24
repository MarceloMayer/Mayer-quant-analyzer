<x-filament-widgets::widget>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        @if ($this->points() === [])
            <div class="rounded-md bg-gray-50 px-3 py-8 text-center text-sm text-gray-500 dark:bg-gray-950/40 dark:text-gray-400">
                Nenhuma curva de capital disponível.
            </div>
        @else
            <div class="relative">
                <svg viewBox="0 0 900 250" class="h-80 w-full overflow-visible" preserveAspectRatio="none" role="img">
                    @foreach ($this->yAxisTicks() as $tick)
                        <line x1="54" y1="{{ $tick['y'] }}" x2="882" y2="{{ $tick['y'] }}" stroke="#1f2937" stroke-width="1" />
                        <text x="18" y="{{ $tick['y'] + 4 }}" fill="#9ca3af" font-size="11">{{ $tick['label'] }}</text>
                    @endforeach

                    <line x1="54" y1="202" x2="882" y2="202" stroke="#374151" stroke-width="1" />
                    <line x1="54" y1="18" x2="54" y2="202" stroke="#374151" stroke-width="1" />

                    <polygon points="{{ $this->svgAreaPoints() }}" fill="#86efac" fill-opacity="0.22" />
                    <polyline points="{{ $this->svgPoints() }}" fill="none" stroke="#4ade80" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />

                    @foreach ($this->xAxisTicks() as $tick)
                        <line x1="{{ $tick['x'] }}" y1="202" x2="{{ $tick['x'] }}" y2="207" stroke="#4b5563" stroke-width="1" />
                        <text x="{{ $tick['x'] }}" y="229" text-anchor="middle" fill="#9ca3af" font-size="11">{{ $tick['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
