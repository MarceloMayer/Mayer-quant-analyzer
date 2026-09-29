{{--
    Shared multi-series equity chart. Expects the array built by
    App\Services\Charts\DatedLineChartBuilder::build() as $chart, an accessible $ariaLabel and a
    $formatMoney callable used for the end-point tooltips.
--}}
<svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="h-80 w-full" style="height: 20rem; width: 100%;" preserveAspectRatio="none" role="img" aria-label="{{ $ariaLabel }}">
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
                    <title>{{ $series['label'] }} | {{ $formatMoney($series['final_equity']) }}</title>
                </circle>
            @endif
        @endif
    @endforeach

    @foreach ($chart['x_ticks'] as $tick)
        <line x1="{{ $tick['x'] }}" y1="{{ $chart['bottom'] }}" x2="{{ $tick['x'] }}" y2="{{ $chart['bottom'] + 5 }}" stroke="#6b7280" stroke-width="1" />
        <text x="{{ $tick['x'] }}" y="{{ $chart['bottom'] + 22 }}" text-anchor="{{ $tick['anchor'] }}" fill="#9ca3af" font-size="11">{{ $tick['label'] }}</text>
    @endforeach
</svg>
