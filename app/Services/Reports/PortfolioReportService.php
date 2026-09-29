<?php

namespace App\Services\Reports;

use App\Models\Portfolio;
use App\Models\Strategy;
use App\Services\Metrics\PortfolioAnalyzerService;
use App\Services\Metrics\PortfolioCorrelationService;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Builds the printable, self-contained PDF version of the portfolio results page.
 */
class PortfolioReportService
{
    public function __construct(
        private readonly PortfolioAnalyzerService $analyzer,
        private readonly PortfolioCorrelationService $correlationService,
        private readonly PdfChartBuilder $charts,
    ) {}

    public function pdf(Portfolio $portfolio): PDF
    {
        // A long trade history makes the consolidated metrics and the SVG/PDF layout heavy.
        @set_time_limit(180);

        $metrics = $this->analyzer->calculate($portfolio);
        $correlation = $this->correlationService->calculate(
            $portfolio,
            PortfolioCorrelationService::PERIOD_DAILY,
            PortfolioCorrelationService::METRIC_PROFIT_LOSS,
        );
        $equityCurve = $metrics['consolidated_equity_curve'] ?? [];
        $monthlyTable = $metrics['monthly_table'] ?? [];

        return PdfFacade::loadView('reports.portfolio', [
            'portfolio' => $portfolio,
            'metrics' => $metrics,
            'correlation' => $correlation,
            'assetLabels' => Strategy::assetOptions(),
            'generatedAt' => now(),
            'period' => $this->period($equityCurve),
            'charts' => [
                'equity' => $this->charts->equityCurve($equityCurve),
                'drawdown' => $this->charts->drawdownCurve($metrics['drawdown_curve'] ?? []),
                'monthly' => $this->charts->monthlyBars($monthlyTable),
                'yearly' => $this->charts->yearlyBars($monthlyTable),
            ],
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'Helvetica', 'isRemoteEnabled' => false]);
    }

    public function fileName(Portfolio $portfolio): string
    {
        return 'relatorio-'.Str::slug($portfolio->name ?: 'portfolio-'.$portfolio->getKey()).'-'.now()->format('Ymd').'.pdf';
    }

    /**
     * @param  array<int, array<string, mixed>>  $equityCurve
     * @return array{start: ?string, end: ?string, days: int}
     */
    private function period(array $equityCurve): array
    {
        $first = $equityCurve[0]['date'] ?? null;
        $last = $equityCurve[array_key_last($equityCurve)]['date'] ?? null;

        if (blank($first) || blank($last)) {
            return ['start' => null, 'end' => null, 'days' => 0];
        }

        $start = CarbonImmutable::parse((string) $first)->startOfDay();
        $end = CarbonImmutable::parse((string) $last)->startOfDay();

        return [
            'start' => $start->format('d/m/Y'),
            'end' => $end->format('d/m/Y'),
            'days' => (int) $start->diffInDays($end) + 1,
        ];
    }
}
