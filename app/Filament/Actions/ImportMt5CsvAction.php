<?php

namespace App\Filament\Actions;

use App\Models\Strategy;
use App\Services\Trading\Mt5CsvImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
            ->label('Carregar CSV MT5')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Carregar resultado do MT5')
            ->modalSubmitActionLabel('Importar')
            ->schema([
                FileUpload::make('csv_file')
                    ->label('Arquivo CSV')
                    ->disk('local')
                    ->directory('imports/mt5')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                    ])
                    ->required(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function handle(Strategy $strategy, array $data): void
    {
        $storedPath = self::storedPath($data);

        if ($storedPath === null) {
            Notification::make()
                ->title('Arquivo CSV não encontrado')
                ->danger()
                ->send();

            return;
        }

        try {
            $result = app(Mt5CsvImportService::class)->import(
                $strategy,
                Storage::disk('local')->path($storedPath),
            );

            Notification::make()
                ->title('CSV importado')
                ->body("Trades importados: {$result['imported']}. Linhas ignoradas: {$result['skipped']}.")
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Falha ao importar CSV')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function storedPath(array $data): ?string
    {
        $path = $data['csv_file'] ?? null;

        if (is_array($path)) {
            $path = reset($path);
        }

        return is_string($path) && $path !== '' ? $path : null;
    }
}
