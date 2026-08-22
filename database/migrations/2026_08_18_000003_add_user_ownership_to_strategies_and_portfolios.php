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
        Schema::table('strategies', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->dropUnique('strategies_magic_number_unique');
            $table->unique(['user_id', 'magic_number'], 'strategies_user_magic_number_unique');
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->dropUnique('portfolios_combination_hash_unique');
            $table->unique(['user_id', 'combination_hash'], 'portfolios_user_combination_hash_unique');
        });

        Schema::table('trades', function (Blueprint $table) {
            $table->dropUnique('trades_backtest_fingerprint_unique');
            $table->unique(['strategy_id', 'backtest_id', 'trade_fingerprint'], 'trades_strategy_backtest_fingerprint_unique');
        });

        Schema::table('mt5_report_files', function (Blueprint $table) {
            $table->dropUnique('mt5_report_files_backtest_hash_unique');
            $table->unique(['strategy_id', 'backtest_id', 'file_hash'], 'mt5_report_files_strategy_backtest_hash_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mt5_report_files', function (Blueprint $table) {
            $table->dropUnique('mt5_report_files_strategy_backtest_hash_unique');
            $table->unique(['backtest_id', 'file_hash'], 'mt5_report_files_backtest_hash_unique');
        });

        Schema::table('trades', function (Blueprint $table) {
            $table->dropUnique('trades_strategy_backtest_fingerprint_unique');
            $table->unique(['backtest_id', 'trade_fingerprint'], 'trades_backtest_fingerprint_unique');
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropUnique('portfolios_user_combination_hash_unique');
            $table->unique('combination_hash');
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('strategies', function (Blueprint $table) {
            $table->dropUnique('strategies_user_magic_number_unique');
            $table->unique('magic_number');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
