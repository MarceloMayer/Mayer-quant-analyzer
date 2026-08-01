<?php

namespace App\Services\Metrics;

use App\Models\StrategyBacktestExecution;

class StrategyExecutionClassificationService
{
    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    public function classify(array $metrics, ?StrategyBacktestExecution $execution = null): array
    {
        $criteria = config('strategy_analysis.criteria', []);
        $checks = $this->checks($metrics, $criteria);
        $failed = collect($checks)->filter(fn (array $check): bool => $check['passed'] === false)->values()->all();
        $unavailable = collect($checks)->filter(fn (array $check): bool => $check['passed'] === null)->values()->all();
        $alerts = $this->alerts($metrics, $criteria);
        $totalTrades = (int) ($metrics['total_trades'] ?? 0);
        $netProfit = (float) ($metrics['net_profit'] ?? 0);

        $suggestedStatus = match (true) {
            $totalTrades === 0 => StrategyBacktestExecution::STATUS_INCONCLUSIVE,
            count($failed) === 0 && count($unavailable) === 0 => StrategyBacktestExecution::STATUS_APPROVED_NEXT_STEP,
            $netProfit < 0 || count($failed) >= 3 => StrategyBacktestExecution::STATUS_REJECTED,
            count($unavailable) > 0 && count($failed) === 0 => StrategyBacktestExecution::STATUS_INCONCLUSIVE,
            default => StrategyBacktestExecution::STATUS_WATCHING,
        };

        $manualStatus = $execution?->manual_classification_status;

        return [
            'suggested_status' => $suggestedStatus,
            'suggested_label' => StrategyBacktestExecution::classificationStatusLabel($suggestedStatus),
            'manual_status' => $manualStatus,
            'manual_label' => $manualStatus === null ? null : StrategyBacktestExecution::classificationStatusLabel($manualStatus),
            'final_status' => $manualStatus ?: $suggestedStatus,
            'final_label' => StrategyBacktestExecution::classificationStatusLabel($manualStatus ?: $suggestedStatus),
            'manual_notes' => $execution?->classification_notes,
            'criteria' => $checks,
            'passed_criteria' => collect($checks)->filter(fn (array $check): bool => $check['passed'] === true)->values()->all(),
            'failed_criteria' => $failed,
            'unavailable_criteria' => $unavailable,
            'alerts' => $alerts,
            'reasons' => $this->reasons($suggestedStatus, $failed, $unavailable, $alerts),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $criteria
     * @return array<int, array<string, mixed>>
     */
    private function checks(array $metrics, array $criteria): array
    {
        return [
            $this->check(
                'min_net_profit',
                'Lucro líquido mínimo',
                $metrics['net_profit'] ?? null,
                $criteria['min_net_profit'] ?? 0,
                fn (float $actual, float $threshold): bool => $actual >= $threshold,
                'maior ou igual',
            ),
            $this->check(
                'min_profit_factor',
                'Profit factor mínimo',
                ($metrics['profit_factor'] ?? null) !== null
                    ? $metrics['profit_factor']
                    : (((float) ($metrics['gross_profit'] ?? 0) > 0 && (float) ($metrics['gross_loss'] ?? 0) === 0.0)
                        ? ($criteria['min_profit_factor'] ?? 1.2)
                        : null),
                $criteria['min_profit_factor'] ?? 1.2,
                fn (float $actual, float $threshold): bool => $actual >= $threshold,
                'maior ou igual',
            ),
            $this->check(
                'min_profit_drawdown_ratio',
                'Relação lucro/drawdown mínima',
                $metrics['profit_drawdown_ratio'] ?? null,
                $criteria['min_profit_drawdown_ratio'] ?? 1.5,
                fn (float $actual, float $threshold): bool => $actual >= $threshold,
                'maior ou igual',
            ),
            $this->check(
                'min_trades',
                'Número mínimo de operações',
                $metrics['total_trades'] ?? null,
                $criteria['min_trades'] ?? 30,
                fn (float $actual, float $threshold): bool => $actual >= $threshold,
                'maior ou igual',
            ),
            $this->check(
                'max_drawdown_percent',
                'Drawdown percentual máximo',
                $metrics['max_drawdown_percent'] ?? null,
                $criteria['max_drawdown_percent'] ?? 25,
                fn (float $actual, float $threshold): bool => $actual <= $threshold,
                'menor ou igual',
            ),
            $this->check(
                'min_positive_month_rate',
                'Percentual mínimo de meses positivos',
                data_get($metrics, 'monthly_summary.positive_month_rate'),
                $criteria['min_positive_month_rate'] ?? 50,
                fn (float $actual, float $threshold): bool => $actual >= $threshold,
                'maior ou igual',
            ),
            $this->check(
                'min_months',
                'Quantidade mínima de meses analisados',
                data_get($metrics, 'monthly_summary.active_months'),
                $criteria['min_months'] ?? 3,
                fn (float $actual, float $threshold): bool => $actual >= $threshold,
                'maior ou igual',
            ),
            $this->check(
                'max_best_month_profit_concentration_percent',
                'Concentração máxima no melhor mês',
                data_get($metrics, 'monthly_summary.best_month_concentration_percent'),
                $criteria['max_best_month_profit_concentration_percent'] ?? 50,
                fn (float $actual, float $threshold): bool => $actual <= $threshold,
                'menor ou igual',
            ),
            $this->check(
                'max_best_year_profit_concentration_percent',
                'Concentração máxima no melhor ano',
                data_get($metrics, 'annual_summary.best_year_concentration_percent'),
                $criteria['max_best_year_profit_concentration_percent'] ?? 60,
                fn (float $actual, float $threshold): bool => $actual <= $threshold,
                'menor ou igual',
            ),
            $this->check(
                'max_consecutive_losses',
                'Máximo de perdas consecutivas',
                data_get($metrics, 'streaks.max_losing.count'),
                $criteria['max_consecutive_losses'] ?? 6,
                fn (float $actual, float $threshold): bool => $actual <= $threshold,
                'menor ou igual',
            ),
        ];
    }

    /**
     * @param  callable(float, float): bool  $rule
     * @return array<string, mixed>
     */
    private function check(string $key, string $label, mixed $actual, mixed $threshold, callable $rule, string $direction): array
    {
        $actualValue = is_numeric($actual) ? (float) $actual : null;
        $thresholdValue = is_numeric($threshold) ? (float) $threshold : null;

        return [
            'key' => $key,
            'label' => $label,
            'actual' => $actualValue,
            'threshold' => $thresholdValue,
            'direction' => $direction,
            'passed' => $actualValue === null || $thresholdValue === null ? null : $rule($actualValue, $thresholdValue),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $criteria
     * @return array<int, array{level: string, message: string}>
     */
    private function alerts(array $metrics, array $criteria): array
    {
        $alerts = [];
        $alertConfig = config('strategy_analysis.alerts', []);
        $netProfit = (float) ($metrics['net_profit'] ?? 0);
        $totalTrades = (int) ($metrics['total_trades'] ?? 0);
        $minTrades = (int) ($criteria['min_trades'] ?? 30);

        if ($totalTrades > 0 && $totalTrades < $minTrades) {
            $alerts[] = ['level' => 'warning', 'message' => "Poucas operações para a regra atual ({$totalTrades}/{$minTrades})."];
        }

        if ($totalTrades === 0) {
            $alerts[] = ['level' => 'warning', 'message' => 'Nenhuma operação fechada disponível para análise.'];
        }

        if ($netProfit < 0) {
            $alerts[] = ['level' => 'danger', 'message' => 'Resultado líquido negativo.'];
        }

        if (($metrics['max_drawdown_percent'] ?? null) !== null
            && (float) $metrics['max_drawdown_percent'] > (float) ($criteria['max_drawdown_percent'] ?? 25)) {
            $alerts[] = ['level' => 'danger', 'message' => 'Drawdown percentual acima do limite configurado.'];
        }

        if (($metrics['profit_factor'] ?? null) !== null
            && (float) $metrics['profit_factor'] < (float) ($criteria['min_profit_factor'] ?? 1.2)) {
            $alerts[] = ['level' => 'warning', 'message' => 'Profit factor abaixo do limite configurado.'];
        }

        if (($metrics['profit_factor'] ?? null) === null && $totalTrades > 0) {
            $alerts[] = ['level' => 'info', 'message' => (string) ($metrics['profit_factor_label'] ?? 'Profit factor não calculável.')];
        }

        if ($netProfit > 0 && ($metrics['profit_drawdown_ratio'] ?? null) !== null
            && (float) $metrics['profit_drawdown_ratio'] < (float) ($criteria['min_profit_drawdown_ratio'] ?? 1.5)) {
            $alerts[] = ['level' => 'warning', 'message' => 'Estratégia positiva, mas com relação lucro/drawdown fraca.'];
        }

        if (($metrics['max_days_without_new_high'] ?? 0) > (int) ($alertConfig['max_days_without_new_high'] ?? 120)) {
            $alerts[] = ['level' => 'warning', 'message' => 'Longo período sem novo topo da curva.'];
        }

        if (! (bool) ($metrics['costs_available'] ?? false) || abs((float) ($metrics['costs_total'] ?? 0)) === 0.0) {
            $alerts[] = ['level' => 'info', 'message' => 'Custos ausentes ou zerados nos dados importados.'];
        }

        if (($metrics['initial_capital'] ?? null) === null) {
            $alerts[] = ['level' => 'info', 'message' => 'Capital inicial ausente; retornos percentuais ficam indisponíveis.'];
        }

        if (! (bool) data_get($metrics, 'equity_drawdown.available')) {
            $alerts[] = ['level' => 'info', 'message' => 'Drawdown de equity indisponível porque não há dados intratrade/equity importados.'];
        }

        if (($concentration = data_get($metrics, 'monthly_summary.best_month_concentration_percent')) !== null
            && (float) $concentration > (float) ($criteria['max_best_month_profit_concentration_percent'] ?? 50)) {
            $alerts[] = ['level' => 'warning', 'message' => 'Lucro concentrado no melhor mês.'];
        }

        if (($concentration = data_get($metrics, 'annual_summary.best_year_concentration_percent')) !== null
            && (float) $concentration > (float) ($criteria['max_best_year_profit_concentration_percent'] ?? 60)) {
            $alerts[] = ['level' => 'warning', 'message' => 'Lucro concentrado em um único ano.'];
        }

        if (($concentration = $metrics['best_trade_concentration_percent'] ?? null) !== null
            && (float) $concentration > (float) ($alertConfig['max_best_trade_profit_concentration_percent'] ?? 35)) {
            $alerts[] = ['level' => 'warning', 'message' => 'Resultado dependente de poucos trades.'];
        }

        if (($missingMonths = data_get($metrics, 'monthly_summary.no_trade_months_percent')) !== null
            && (float) $missingMonths > (float) ($alertConfig['max_months_without_trades_percent'] ?? 35)) {
            $alerts[] = ['level' => 'info', 'message' => 'Muitos meses no período não possuem operações.'];
        }

        if (($months = data_get($metrics, 'monthly_summary.total_months_in_period')) !== null
            && (int) $months > 0
            && (int) $months < (int) ($alertConfig['short_period_months'] ?? 3)) {
            $alerts[] = ['level' => 'warning', 'message' => 'Período analisado curto para curadoria.'];
        }

        if (($metrics['reported_net_profit_difference'] ?? null) !== null
            && abs((float) $metrics['reported_net_profit_difference']) > (float) ($alertConfig['report_net_profit_tolerance'] ?? 0.05)) {
            $alerts[] = ['level' => 'warning', 'message' => 'Divergência entre resultado importado do relatório e soma dos trades.'];
        }

        if ($netProfit > 0) {
            $buyNet = (float) data_get($metrics, 'direction_summary.buy.net_profit', 0);
            $sellNet = (float) data_get($metrics, 'direction_summary.sell.net_profit', 0);

            if ($buyNet > 0 && $sellNet <= 0) {
                $alerts[] = ['level' => 'info', 'message' => 'Estratégia positiva somente em compras.'];
            }

            if ($sellNet > 0 && $buyNet <= 0) {
                $alerts[] = ['level' => 'info', 'message' => 'Estratégia positiva somente em vendas.'];
            }
        }

        return $alerts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $failed
     * @param  array<int, array<string, mixed>>  $unavailable
     * @param  array<int, array<string, string>>  $alerts
     * @return array<int, string>
     */
    private function reasons(string $status, array $failed, array $unavailable, array $alerts): array
    {
        if ($status === StrategyBacktestExecution::STATUS_APPROVED_NEXT_STEP) {
            return ['Todos os critérios configurados foram atendidos.'];
        }

        $reasons = collect($failed)
            ->map(fn (array $check): string => "{$check['label']} fora do critério configurado.")
            ->merge(collect($unavailable)->map(fn (array $check): string => "{$check['label']} indisponível com os dados atuais."))
            ->merge(collect($alerts)->take(3)->pluck('message'))
            ->unique()
            ->values()
            ->all();

        return $reasons === [] ? ['Dados insuficientes para uma sugestão conclusiva.'] : $reasons;
    }
}
