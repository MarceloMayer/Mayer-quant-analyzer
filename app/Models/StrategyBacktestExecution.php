<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'strategy_id',
    'backtest_id',
    'name',
    'execution_type',
    'strategy_version',
    'asset',
    'symbol',
    'timeframe',
    'started_at',
    'ended_at',
    'initial_capital',
    'initial_contracts',
    'parameters',
    'costs',
    'slippage',
    'spread',
    'data_source',
    'notes',
    'auto_classification_status',
    'manual_classification_status',
    'classification_notes',
    'imported_at',
])]
class StrategyBacktestExecution extends Model
{
    public const LEGACY_BACKTEST_ID = '__sem_backtest__';

    public const TYPE_MAIN_BACKTEST = 'backtest_principal';

    public const TYPE_NEIGHBOR_PARAMETER = 'parametro_vizinho';

    public const TYPE_IN_SAMPLE = 'dentro_da_amostra';

    public const TYPE_OUT_OF_SAMPLE = 'fora_da_amostra';

    public const TYPE_HIGHER_COSTS = 'custos_maiores';

    public const TYPE_STRESS = 'teste_estresse';

    public const TYPE_SIMULATOR = 'simulador';

    public const TYPE_VALIDATION = 'validacao';

    public const TYPE_OTHER = 'outro';

    public const STATUS_NOT_ANALYZED = 'nao_analisada';

    public const STATUS_APPROVED_NEXT_STEP = 'aprovada_proxima_etapa';

    public const STATUS_WATCHING = 'em_observacao';

    public const STATUS_INCONCLUSIVE = 'inconclusiva';

    public const STATUS_REJECTED = 'reprovada';

    public const STATUS_SENT_TO_SIMULATOR = 'enviada_simulador';

    public const STATUS_ARCHIVED = 'arquivada';

    /**
     * @return array<string, string>
     */
    public static function executionTypeOptions(): array
    {
        return [
            self::TYPE_MAIN_BACKTEST => 'Backtest principal',
            self::TYPE_NEIGHBOR_PARAMETER => 'Parâmetro vizinho',
            self::TYPE_IN_SAMPLE => 'Dentro da amostra',
            self::TYPE_OUT_OF_SAMPLE => 'Fora da amostra',
            self::TYPE_HIGHER_COSTS => 'Teste com custos maiores',
            self::TYPE_STRESS => 'Teste de estresse',
            self::TYPE_SIMULATOR => 'Simulador',
            self::TYPE_VALIDATION => 'Validação',
            self::TYPE_OTHER => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function classificationStatusOptions(): array
    {
        return [
            self::STATUS_NOT_ANALYZED => 'Não analisada',
            self::STATUS_APPROVED_NEXT_STEP => 'Aprovada para próxima etapa',
            self::STATUS_WATCHING => 'Em observação',
            self::STATUS_INCONCLUSIVE => 'Inconclusiva',
            self::STATUS_REJECTED => 'Reprovada',
            self::STATUS_SENT_TO_SIMULATOR => 'Enviada para simulador',
            self::STATUS_ARCHIVED => 'Arquivada',
        ];
    }

    public static function executionTypeLabel(?string $type): string
    {
        return self::executionTypeOptions()[$type ?? ''] ?? 'Outro';
    }

    public static function classificationStatusLabel(?string $status): string
    {
        return self::classificationStatusOptions()[$status ?? ''] ?? 'Não analisada';
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'strategy_id', 'strategy_id')
            ->where('backtest_id', $this->backtest_id);
    }

    public function mt5ReportFiles(): HasMany
    {
        return $this->hasMany(Mt5ReportFile::class, 'strategy_id', 'strategy_id')
            ->where('backtest_id', $this->backtest_id);
    }

    public function effectiveClassificationStatus(): string
    {
        return $this->manual_classification_status ?: $this->auto_classification_status ?: self::STATUS_NOT_ANALYZED;
    }

    public function effectiveClassificationLabel(): string
    {
        return self::classificationStatusLabel($this->effectiveClassificationStatus());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'initial_capital' => 'decimal:8',
            'initial_contracts' => 'decimal:8',
            'parameters' => 'array',
            'costs' => 'array',
            'slippage' => 'decimal:8',
            'spread' => 'decimal:8',
            'imported_at' => 'datetime',
        ];
    }
}
