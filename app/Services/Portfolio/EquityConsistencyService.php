<?php

namespace App\Services\Portfolio;

class EquityConsistencyService
{
    public function __construct(
        private readonly UlcerIndexCalculator $ulcerIndexCalculator,
        private readonly LinearRegressionService $linearRegressionService,
    ) {}

    /**
     * Calculates consistency metrics for a simulated portfolio.
     *
     * @param  array<int, array{equity: float}>  $equityCurve
     * @param  float[]  $monthlyProfits  Only months that actually had trades
     * @param  float  $totalNetProfit
     * @param  float  $maxDrawdown  Absolute value
     * @return array{
     *     positive_months: int,
     *     negative_months: int,
     *     positive_months_percent: float,
     *     ulcer_index: float,
     *     equity_r2: float,
     *     net_profit_to_drawdown: float,
     * }
     */
    public function calculate(
        array $equityCurve,
        array $monthlyProfits,
        float $totalNetProfit,
        float $maxDrawdown,
    ): array {
        $positiveMonths = count(array_filter($monthlyProfits, fn (float $v): bool => $v > 0));
        $negativeMonths = count(array_filter($monthlyProfits, fn (float $v): bool => $v < 0));
        $monthsWithTrades = count($monthlyProfits);
        $positiveMonthsPercent = $monthsWithTrades > 0
            ? round(($positiveMonths / $monthsWithTrades) * 100, 2)
            : 0.0;

        $ulcerIndex = $this->ulcerIndexCalculator->calculate($equityCurve);

        $equityValues = array_column($equityCurve, 'equity');
        $equityR2 = $this->linearRegressionService->calculateR2($equityValues);

        // Cap the ratio at 100 when drawdown is zero to prevent infinity distorting normalization
        $absDrawdown = abs($maxDrawdown);
        $netProfitToDrawdown = $absDrawdown > 0
            ? round($totalNetProfit / $absDrawdown, 4)
            : ($totalNetProfit > 0 ? 100.0 : 0.0);

        return [
            'positive_months' => $positiveMonths,
            'negative_months' => $negativeMonths,
            'positive_months_percent' => $positiveMonthsPercent,
            'ulcer_index' => $ulcerIndex,
            'equity_r2' => $equityR2,
            'net_profit_to_drawdown' => $netProfitToDrawdown,
        ];
    }

    /**
     * Calculates the normalized consistency_score (0–100) for each result in the set.
     * Normalization is relative: metrics are scaled against the min/max within the run.
     *
     * Recovery factor (net_profit_to_drawdown) carries the largest weight since it is the
     * headline risk-adjusted-return metric traders judge a portfolio by. Correlation is
     * scored on its absolute value: a combination whose strategies are either redundant
     * (near +1) or offsetting each other's edge (near -1) is penalized the same way, since
     * both defeat the point of diversifying. A combination with too little overlapping data
     * to compute a correlation is neither rewarded nor penalized for it (neutral 100 score).
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    public function applyConsistencyScores(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        $ulcerValues = array_column($results, 'ulcer_index');
        $ratioValues = array_column($results, 'net_profit_to_drawdown');
        $correlationValues = array_values(array_filter(
            array_column($results, 'average_absolute_correlation'),
            fn (mixed $value): bool => $value !== null,
        ));

        $minUlcer = min($ulcerValues);
        $maxUlcer = max($ulcerValues);
        $minRatio = min($ratioValues);
        $maxRatio = max($ratioValues);
        $minCorrelation = $correlationValues === [] ? 0.0 : min($correlationValues);
        $maxCorrelation = $correlationValues === [] ? 0.0 : max($correlationValues);

        $ulcerRange = $maxUlcer - $minUlcer;
        $ratioRange = $maxRatio - $minRatio;
        $correlationRange = $maxCorrelation - $minCorrelation;

        foreach ($results as &$result) {
            $ulcerScore = $ulcerRange > 0
                ? (($maxUlcer - (float) $result['ulcer_index']) / $ulcerRange) * 100
                : 100.0;

            $ratioScore = $ratioRange > 0
                ? (((float) $result['net_profit_to_drawdown'] - $minRatio) / $ratioRange) * 100
                : 100.0;

            $r2Score = (float) $result['equity_r2'] * 100;
            $monthsScore = (float) $result['positive_months_percent'];

            $correlation = $result['average_absolute_correlation'] ?? null;
            $correlationScore = $correlation === null
                ? 100.0
                : ($correlationRange > 0
                    ? (($maxCorrelation - (float) $correlation) / $correlationRange) * 100
                    : 100.0);

            $result['consistency_score'] = round(
                ($ratioScore * 0.25)
                + ($r2Score * 0.25)
                + ($ulcerScore * 0.20)
                + ($correlationScore * 0.15)
                + ($monthsScore * 0.15),
                2
            );
        }
        unset($result);

        return $results;
    }
}
