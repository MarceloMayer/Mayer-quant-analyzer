@php
    $metrics = app(\App\Services\Trading\PortfolioMetricsService::class)->calculate($record);
@endphp

@include('filament.partials.performance-metrics', [
    'metrics' => $metrics,
    'emptyMessage' => 'Nenhum trade disponível para as estratégias deste portfólio.',
])
