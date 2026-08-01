<?php

namespace App\Filament\Resources\Strategies\Pages;

use App\Filament\Resources\Strategies\StrategyResource;
use App\Models\Strategy;
use App\Models\StrategyBacktestExecution;
use App\Services\Metrics\StrategyExecutionMetricsService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StrategyExecutionAnalysisPage extends ViewRecord
{
    protected static string $resource = StrategyResource::class;

    protected static ?string $title = 'Análise da Execução';

    public string $backtestId = '';

    public function mount(int|string $record, ?string $backtestId = null): void
    {
        parent::mount($record);

        $this->backtestId = rawurldecode((string) $backtestId);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.strategies.pages.strategy-execution-analysis-page')
                    ->viewData(fn (): array => [
                        'strategy' => $this->strategy(),
                        'execution' => $this->execution(),
                        'metrics' => app(StrategyExecutionMetricsService::class)->calculate(
                            $this->strategy(),
                            $this->backtestId,
                            $this->execution(),
                        ),
                    ]),
            ]);
    }

    public function getSubheading(): ?string
    {
        $execution = $this->execution();

        return ($execution->name ?: $execution->backtest_id).' - '.StrategyBacktestExecution::executionTypeLabel($execution->execution_type);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Resultados')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(fn (): string => StrategyResource::getUrl('results', ['record' => $this->strategy()]))
                ->color('gray'),
            Action::make('editExecution')
                ->label('Editar execução')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalHeading('Editar metadados da execução')
                ->modalSubmitActionLabel('Salvar')
                ->fillForm(fn (): array => $this->executionFormData())
                ->schema($this->executionSchema())
                ->action(function (array $data): void {
                    $this->updateExecution($data);
                }),
            Action::make('classifyExecution')
                ->label('Classificar')
                ->icon(Heroicon::OutlinedFlag)
                ->modalHeading('Classificação manual')
                ->modalSubmitActionLabel('Salvar')
                ->fillForm(fn (): array => [
                    'manual_classification_status' => $this->execution()->manual_classification_status,
                    'classification_notes' => $this->execution()->classification_notes,
                ])
                ->schema([
                    Select::make('manual_classification_status')
                        ->label('Classificação manual')
                        ->placeholder('Usar classificação sugerida')
                        ->options(StrategyBacktestExecution::classificationStatusOptions())
                        ->native(false),
                    Textarea::make('classification_notes')
                        ->label('Observação da curadoria')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->execution()->update([
                        'manual_classification_status' => filled($data['manual_classification_status'] ?? null)
                            ? $data['manual_classification_status']
                            : null,
                        'classification_notes' => $data['classification_notes'] ?? null,
                    ]);

                    Notification::make()
                        ->title('Classificação atualizada')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }

    private function strategy(): Strategy
    {
        /** @var Strategy $strategy */
        $strategy = $this->record;

        return $strategy;
    }

    private function execution(): StrategyBacktestExecution
    {
        return StrategyBacktestExecution::query()->firstOrCreate(
            [
                'strategy_id' => $this->strategy()->id,
                'backtest_id' => $this->backtestId,
            ],
            [
                'name' => $this->backtestId === StrategyBacktestExecution::LEGACY_BACKTEST_ID
                    ? 'Sem identificador de backtest'
                    : $this->backtestId,
                'execution_type' => StrategyBacktestExecution::TYPE_MAIN_BACKTEST,
                'asset' => $this->strategy()->asset,
                'auto_classification_status' => StrategyBacktestExecution::STATUS_NOT_ANALYZED,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function executionFormData(): array
    {
        $execution = $this->execution();

        return [
            'name' => $execution->name,
            'execution_type' => $execution->execution_type,
            'strategy_version' => $execution->strategy_version,
            'asset' => $execution->asset,
            'symbol' => $execution->symbol,
            'timeframe' => $execution->timeframe,
            'started_at' => $execution->started_at?->toDateString(),
            'ended_at' => $execution->ended_at?->toDateString(),
            'initial_capital' => $execution->initial_capital,
            'initial_contracts' => $execution->initial_contracts,
            'parameters' => $execution->parameters === null ? null : json_encode($execution->parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'costs_description' => data_get($execution->costs, 'observacoes'),
            'slippage' => $execution->slippage,
            'spread' => $execution->spread,
            'data_source' => $execution->data_source,
            'notes' => $execution->notes,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function executionSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nome da execução')
                ->maxLength(255),
            Select::make('execution_type')
                ->label('Tipo da execução')
                ->options(StrategyBacktestExecution::executionTypeOptions())
                ->native(false)
                ->required(),
            TextInput::make('strategy_version')
                ->label('Versão da estratégia')
                ->maxLength(255),
            TextInput::make('asset')
                ->label('Ativo')
                ->maxLength(255),
            TextInput::make('symbol')
                ->label('Símbolo')
                ->maxLength(255),
            TextInput::make('timeframe')
                ->label('Timeframe')
                ->maxLength(255),
            TextInput::make('started_at')
                ->label('Data inicial')
                ->placeholder('AAAA-MM-DD')
                ->maxLength(10),
            TextInput::make('ended_at')
                ->label('Data final')
                ->placeholder('AAAA-MM-DD')
                ->maxLength(10),
            TextInput::make('initial_capital')
                ->label('Capital inicial')
                ->numeric(),
            TextInput::make('initial_contracts')
                ->label('Quantidade inicial de contratos')
                ->numeric(),
            Textarea::make('parameters')
                ->label('Parâmetros utilizados')
                ->helperText('JSON opcional. Se não for JSON, será preservado como texto.')
                ->rows(5)
                ->columnSpanFull(),
            Textarea::make('costs_description')
                ->label('Custos considerados')
                ->rows(2)
                ->columnSpanFull(),
            TextInput::make('slippage')
                ->label('Slippage')
                ->numeric(),
            TextInput::make('spread')
                ->label('Spread')
                ->numeric(),
            TextInput::make('data_source')
                ->label('Origem dos dados')
                ->maxLength(255),
            Textarea::make('notes')
                ->label('Observações')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateExecution(array $data): void
    {
        $costs = $this->execution()->costs ?? [];

        if (filled($data['costs_description'] ?? null)) {
            $costs['observacoes'] = trim((string) $data['costs_description']);
        }

        $this->execution()->update([
            'name' => $data['name'] ?? null,
            'execution_type' => $data['execution_type'] ?? StrategyBacktestExecution::TYPE_MAIN_BACKTEST,
            'strategy_version' => $data['strategy_version'] ?? null,
            'asset' => $data['asset'] ?? null,
            'symbol' => $data['symbol'] ?? null,
            'timeframe' => $data['timeframe'] ?? null,
            'started_at' => $data['started_at'] ?? null,
            'ended_at' => $data['ended_at'] ?? null,
            'initial_capital' => $data['initial_capital'] ?? null,
            'initial_contracts' => $data['initial_contracts'] ?? null,
            'parameters' => $this->parameters($data['parameters'] ?? null),
            'costs' => $costs === [] ? null : $costs,
            'slippage' => $data['slippage'] ?? null,
            'spread' => $data['spread'] ?? null,
            'data_source' => $data['data_source'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Notification::make()
            ->title('Execução atualizada')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parameters(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['texto' => trim($value)];
        }

        return is_array($decoded) ? $decoded : null;
    }
}
