@php
    $metrics = app(\App\Services\Trading\StrategyMetricsService::class)->calculate($record);
@endphp

@include('filament.partials.performance-metrics', [
    'metrics' => $metrics,
    'emptyMessage' => 'Nenhum trade importado para esta estratégia.',
])
