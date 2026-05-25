<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mt5_report_files', function (Blueprint $table) {
            $table->id();
            $table->string('backtest_id');
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_hash', 64);
            $table->date('report_start_date')->nullable();
            $table->date('report_end_date')->nullable();
            $table->decimal('initial_deposit', 18, 8)->nullable();
            $table->decimal('reported_net_profit', 18, 8)->nullable();
            $table->unsignedInteger('imported_trades_count')->default(0);
            $table->unsignedInteger('skipped_duplicates_count')->default(0);
            $table->json('warnings')->nullable();
            $table->string('status')->default('imported');
            $table->dateTime('imported_at')->nullable();
            $table->timestamps();

            $table->index('backtest_id');
            $table->index('strategy_id');
            $table->unique(['backtest_id', 'file_hash'], 'mt5_report_files_backtest_hash_unique');
        });

        Schema::table('trades', function (Blueprint $table) {
            $table->dropUnique('trades_backtest_exit_deal_unique');
            $table->foreignId('mt5_report_file_id')
                ->nullable()
                ->after('backtest_id')
                ->constrained('mt5_report_files')
                ->nullOnDelete();
            $table->string('trade_fingerprint', 64)->nullable()->after('exit_order_id');

            $table->index('mt5_report_file_id');
            $table->index(['backtest_id', 'exit_deal_id']);
            $table->unique(['backtest_id', 'trade_fingerprint'], 'trades_backtest_fingerprint_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropUnique('trades_backtest_fingerprint_unique');
            $table->dropIndex(['backtest_id', 'exit_deal_id']);
            $table->dropForeign(['mt5_report_file_id']);
            $table->dropIndex(['mt5_report_file_id']);
            $table->dropColumn(['mt5_report_file_id', 'trade_fingerprint']);

            $table->unique(['backtest_id', 'exit_deal_id'], 'trades_backtest_exit_deal_unique');
        });

        Schema::dropIfExists('mt5_report_files');
    }
};
