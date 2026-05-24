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
        Schema::table('trades', function (Blueprint $table) {
            $table->string('backtest_id')->nullable()->after('strategy_id');
            $table->string('entry_deal_id')->nullable()->after('order_id');
            $table->string('entry_order_id')->nullable()->after('entry_deal_id');
            $table->string('exit_deal_id')->nullable()->after('entry_order_id');
            $table->string('exit_order_id')->nullable()->after('exit_deal_id');
            $table->string('symbol')->nullable()->after('asset');
            $table->decimal('balance_after_trade', 18, 8)->nullable()->after('net_profit');
            $table->text('comment')->nullable()->after('balance_after_trade');

            $table->index('backtest_id');
            $table->unique(['backtest_id', 'exit_deal_id'], 'trades_backtest_exit_deal_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropUnique('trades_backtest_exit_deal_unique');
            $table->dropIndex(['backtest_id']);
            $table->dropColumn([
                'backtest_id',
                'entry_deal_id',
                'entry_order_id',
                'exit_deal_id',
                'exit_order_id',
                'symbol',
                'balance_after_trade',
                'comment',
            ]);
        });
    }
};
