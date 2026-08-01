<?php

namespace App\Filament\Actions;

use App\Models\Strategy;
use App\Models\StrategyBacktestExecution;
use App\Services\Imports\Mt5MultipleReportImportService;
use App\Services\Imports\TradeImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ImportMt5CsvAction
{
    public static function make(string $name = 'importMt5Csv'): Action
    {
        return self::base($name)
            ->action(function (array $data, Strategy $record): void {
                self::handle($record, $data);
            });
    }

    public static function makeForRecord(Strategy $record, string $name = 'importMt5Csv'): Action
    {
        return self::base($name)
            ->action(function (array $data) use ($record): void {
                self::handle($record, $data);
            });
    }

    private static function base(string $name): Action
    {
        return Action::make($name)
            ->label('Carregar resultado do MT5')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Carregar resultado do MT5')
            ->modalSubmitActionLabel('Importar')
            ->schema([
                TextInput::make('backtest_id')
                    ->label('Identificador do backtest')
                    ->helperText('Use o mesmo identificador para importar relatórios XLSX de períodos diferentes do mesmo backtest.')
                    ->default(fn (?Strategy $record): ?string => $record?->id === null ? null : 'strategy-'.$record->id)
                    ->required(),
                TextInput::make('execution_name')
                    ->label('Nome da execução')
                    ->placeholder('Ex.: Backtest principal 2020-2024')
                    ->maxLength(255),
                Select::make('execution_type')
                    ->label('Tipo da execução')
                    ->options(StrategyBacktestExecution::executionTypeOptions())
                    ->default(StrategyBacktestExecution::TYPE_MAIN_BACKTEST)
                    ->native(false)
                    ->required(),
                TextInput::make('strategy_version')
                    ->label('Versão da estratégia')
                    ->maxLength(255),
                TextInput::make('timeframe')
                    ->label('Timeframe')
                    ->maxLength(255),
                TextInput::make('initial_capital')
                    ->label('Capital inicial')
                    ->numeric(),
                TextInput::make('initial_contracts')
                    ->label('Quantidade inicial de contratos')
                    ->numeric(),
                TextInput::make('slippage')
                    ->label('Slippage')
                    ->numeric(),
                TextInput::make('spread')
                    ->label('Spread')
                    ->numeric(),
                TextInput::make('data_source')
                    ->label('Origem dos dados')
                    ->default('MetaTrader 5')
                    ->maxLength(255),
                Textarea::make('parameters')
                    ->label('Parâmetros utilizados')
                    ->helperText('JSON opcional. Se não for JSON, o texto será preservado como observação de parâmetros.')
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('costs_description')
                    ->label('Custos considerados')
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Observações da execução')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('csv_file')
                    ->label('Arquivos CSV ou XLSX')
                    ->disk('local')
                    ->directory('imports/mt5')
                    ->preserveFilenames()
                    ->multiple()
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/octet-stream',
                    ])
                    ->required(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function handle(Strategy $strategy, array $data): void
    {
        $storedPaths = self::storedPaths($data);

        if ($storedPaths === []) {
            Notification::make()
                ->title('Arquivo não encontrado')
                ->danger()
                ->send();

            return;
        }

        try {
            $backtestId = self::backtestId($strategy, $data);
            $result = self::import($strategy, $backtestId, $storedPaths, $data);

            Notification::make()
                ->title('Arquivo importado')
                ->body(self::resultMessage($result))
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Falha ao importar arquivo')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            Storage::disk('local')->delete($storedPaths);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function storedPaths(array $data): array
    {
        $path = $data['csv_file'] ?? null;

        if (! is_array($path)) {
            $path = $path === null ? [] : [$path];
        }

        return array_values(array_filter($path, fn (mixed $item): bool => is_string($item) && $item !== ''));
    }

    /**
     * @param  array<int, string>  $storedPaths
     * @param  array<string, mixed>  $executionData
     * @return array<string, mixed>
     */
    private static function import(Strategy $strategy, string $backtestId, array $storedPaths, array $executionData): array
    {
        if (count($storedPaths) === 1 && ! self::isXlsx($storedPaths[0])) {
            return app(TradeImportService::class)->import(
                $strategy,
                Storage::disk('local')->path($storedPaths[0]),
                $backtestId,
                $executionData,
            );
        }

        foreach ($storedPaths as $storedPath) {
            if (! self::isXlsx($storedPath)) {
                throw new \RuntimeException('Importação múltipla aceita somente arquivos XLSX do MT5.');
            }
        }

        $files = array_map(fn (string $storedPath): array => [
            'path' => Storage::disk('local')->path($storedPath),
            'name' => basename($storedPath),
        ], $storedPaths);

        return app(Mt5MultipleReportImportService::class)->import($strategy, $backtestId, $files, $executionData);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function backtestId(Strategy $strategy, array $data): string
    {
        $backtestId = trim((string) ($data['backtest_id'] ?? ''));

        return $backtestId !== '' ? $backtestId : 'strategy-'.$strategy->id;
    }

    private static function isXlsx(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function resultMessage(array $result): string
    {
        if (array_key_exists('files_received', $result)) {
            $message = "Arquivos enviados: {$result['files_received']}. Importados: {$result['files_imported']}. Ignorados por hash: {$result['files_skipped_duplicate_hash']}. Trades encontrados: {$result['total_trades_found']}. Importados: {$result['total_trades_imported']}. Duplicados ignorados: {$result['total_trades_skipped_duplicates']}.";
            $warnings = array_slice($result['warnings'] ?? [], 0, 3);

            if ($warnings !== []) {
                $message .= ' Avisos: '.implode(' | ', $warnings);
            }

            return $message;
        }

        $message = "Linhas: {$result['total_rows']}. Importadas: {$result['imported_rows']}. Ignoradas: {$result['ignored_rows']}.";
        $warnings = count($result['warnings'] ?? []);

        if ($warnings > 0) {
            $message .= " Avisos: {$warnings}.";
        }

        return $message;
    }
}
