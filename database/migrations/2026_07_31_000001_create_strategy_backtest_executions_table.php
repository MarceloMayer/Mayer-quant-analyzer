<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_backtest_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->string('backtest_id');
            $table->string('name')->nullable();
            $table->string('execution_type')->default('backtest_principal');
            $table->string('strategy_version')->nullable();
            $table->string('asset')->nullable();
            $table->string('symbol')->nullable();
            $table->string('timeframe')->nullable();
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->decimal('initial_capital', 18, 8)->nullable();
            $table->decimal('initial_contracts', 18, 8)->nullable();
            $table->json('parameters')->nullable();
            $table->json('costs')->nullable();
            $table->decimal('slippage', 18, 8)->nullable();
            $table->decimal('spread', 18, 8)->nullable();
            $table->string('data_source')->nullable();
            $table->text('notes')->nullable();
            $table->string('auto_classification_status')->default('nao_analisada');
            $table->string('manual_classification_status')->nullable();
            $table->text('classification_notes')->nullable();
            $table->dateTime('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['strategy_id', 'backtest_id'], 'strategy_execution_strategy_backtest_unique');
            $table->index(['strategy_id', 'execution_type'], 'strategy_execution_type_index');
            $table->index(['strategy_id', 'started_at', 'ended_at'], 'strategy_execution_period_index');
        });

        $this->backfillFromMt5Reports();
        $this->backfillFromTrades();
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_backtest_executions');
    }

    private function backfillFromMt5Reports(): void
    {
        if (! Schema::hasTable('mt5_report_files')) {
            return;
        }

        DB::table('mt5_report_files')
            ->whereNotNull('backtest_id')
            ->orderBy('strategy_id')
            ->orderBy('backtest_id')
            ->get()
            ->groupBy(fn (object $report): string => $report->strategy_id.'|'.$report->backtest_id)
            ->each(function ($reports): void {
                $first = $reports->first();
                $strategy = DB::table('strategies')->where('id', $first->strategy_id)->first();
                $startedAt = $reports->pluck('report_start_date')->filter()->min();
                $endedAt = $reports->pluck('report_end_date')->filter()->max();
                $initialCapital = $reports->pluck('initial_deposit')->filter(fn (mixed $value): bool => $value !== null)->first();
                $importedAt = $reports->pluck('imported_at')->filter()->max();
                $now = now();

                DB::table('strategy_backtest_executions')->updateOrInsert(
                    [
                        'strategy_id' => $first->strategy_id,
                        'backtest_id' => $first->backtest_id,
                    ],
                    [
                        'name' => $first->backtest_id,
                        'execution_type' => 'backtest_principal',
                        'asset' => $strategy->asset ?? null,
                        'started_at' => $startedAt,
                        'ended_at' => $endedAt,
                        'initial_capital' => $initialCapital,
                        'data_source' => 'MetaTrader 5',
                        'imported_at' => $importedAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    private function backfillFromTrades(): void
    {
        if (! Schema::hasTable('trades')) {
            return;
        }

        DB::table('trades')
            ->select('strategy_id', 'backtest_id')
            ->whereNotNull('backtest_id')
            ->distinct()
            ->orderBy('strategy_id')
            ->orderBy('backtest_id')
            ->get()
            ->each(function (object $tradeGroup): void {
                $exists = DB::table('strategy_backtest_executions')
                    ->where('strategy_id', $tradeGroup->strategy_id)
                    ->where('backtest_id', $tradeGroup->backtest_id)
                    ->exists();

                if ($exists) {
                    return;
                }

                $strategy = DB::table('strategies')->where('id', $tradeGroup->strategy_id)->first();
                $period = DB::table('trades')
                    ->where('strategy_id', $tradeGroup->strategy_id)
                    ->where('backtest_id', $tradeGroup->backtest_id)
                    ->selectRaw('MIN(DATE(exit_time)) as started_at, MAX(DATE(exit_time)) as ended_at')
                    ->first();
                $now = now();

                DB::table('strategy_backtest_executions')->insert([
                    'strategy_id' => $tradeGroup->strategy_id,
                    'backtest_id' => $tradeGroup->backtest_id,
                    'name' => $tradeGroup->backtest_id,
                    'execution_type' => 'backtest_principal',
                    'asset' => $strategy->asset ?? null,
                    'started_at' => $period->started_at ?? null,
                    'ended_at' => $period->ended_at ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
};
