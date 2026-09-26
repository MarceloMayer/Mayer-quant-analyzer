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
use Filament\Support\RawJs;

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
            'strategy_version' => $execution->strategy_version,
            'asset' => $execution->asset,
            'symbol' => $execution->symbol,
            'started_at' => $execution->started_at?->toDateString(),
            'ended_at' => $execution->ended_at?->toDateString(),
            'initial_capital' => $execution->initial_capital,
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
            TextInput::make('strategy_version')
                ->label('Versão da estratégia')
                ->maxLength(255),
            TextInput::make('asset')
                ->label('Ativo')
                ->maxLength(255),
            TextInput::make('symbol')
                ->label('Símbolo')
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
                ->prefix('R$')
                ->placeholder('0,00')
                ->mask(RawJs::make("\$money(\$input, ',', '.')"))
                ->formatStateUsing(fn (mixed $state): ?string => filled($state)
                    ? number_format((float) $state, 2, ',', '.')
                    : null)
                ->dehydrateStateUsing(fn (?string $state): ?float => filled($state)
                    ? (float) str_replace(['.', ','], ['', '.'], $state)
                    : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateExecution(array $data): void
    {
        $this->execution()->update([
            'name' => $data['name'] ?? null,
            'strategy_version' => $data['strategy_version'] ?? null,
            'asset' => $data['asset'] ?? null,
            'symbol' => $data['symbol'] ?? null,
            'started_at' => $data['started_at'] ?? null,
            'ended_at' => $data['ended_at'] ?? null,
            'initial_capital' => $data['initial_capital'] ?? null,
        ]);

        Notification::make()
            ->title('Execução atualizada')
            ->success()
            ->send();
    }
}
