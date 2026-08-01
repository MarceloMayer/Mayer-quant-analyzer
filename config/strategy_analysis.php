<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Critérios de curadoria de execuções
    |--------------------------------------------------------------------------
    |
    | Estes valores alimentam apenas a classificação sugerida. A decisão final
    | pode ser sobrescrita manualmente na tela de análise da execução.
    |
    */
    'criteria' => [
        'min_net_profit' => (float) env('STRATEGY_ANALYSIS_MIN_NET_PROFIT', 0),
        'min_profit_factor' => (float) env('STRATEGY_ANALYSIS_MIN_PROFIT_FACTOR', 1.2),
        'min_profit_drawdown_ratio' => (float) env('STRATEGY_ANALYSIS_MIN_PROFIT_DRAWDOWN_RATIO', 1.5),
        'min_trades' => (int) env('STRATEGY_ANALYSIS_MIN_TRADES', 30),
        'max_drawdown_percent' => (float) env('STRATEGY_ANALYSIS_MAX_DRAWDOWN_PERCENT', 25),
        'min_positive_month_rate' => (float) env('STRATEGY_ANALYSIS_MIN_POSITIVE_MONTH_RATE', 50),
        'min_months' => (int) env('STRATEGY_ANALYSIS_MIN_MONTHS', 3),
        'max_best_month_profit_concentration_percent' => (float) env('STRATEGY_ANALYSIS_MAX_BEST_MONTH_CONCENTRATION', 50),
        'max_best_year_profit_concentration_percent' => (float) env('STRATEGY_ANALYSIS_MAX_BEST_YEAR_CONCENTRATION', 60),
        'max_consecutive_losses' => (int) env('STRATEGY_ANALYSIS_MAX_CONSECUTIVE_LOSSES', 6),
    ],

    'alerts' => [
        'max_days_without_new_high' => (int) env('STRATEGY_ANALYSIS_MAX_DAYS_WITHOUT_NEW_HIGH', 120),
        'max_best_trade_profit_concentration_percent' => (float) env('STRATEGY_ANALYSIS_MAX_BEST_TRADE_CONCENTRATION', 35),
        'max_months_without_trades_percent' => (float) env('STRATEGY_ANALYSIS_MAX_MONTHS_WITHOUT_TRADES_PERCENT', 35),
        'short_period_months' => (int) env('STRATEGY_ANALYSIS_SHORT_PERIOD_MONTHS', 3),
        'report_net_profit_tolerance' => (float) env('STRATEGY_ANALYSIS_REPORT_NET_PROFIT_TOLERANCE', 0.05),
    ],
];
