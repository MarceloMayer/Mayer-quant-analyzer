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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->string('order_id')->nullable();
            $table->string('asset');
            $table->string('direction')->nullable();
            $table->decimal('volume', 18, 8)->nullable();
            $table->dateTime('entry_time')->nullable();
            $table->dateTime('exit_time')->nullable();
            $table->decimal('entry_price', 18, 8)->nullable();
            $table->decimal('exit_price', 18, 8)->nullable();
            $table->decimal('gross_profit', 18, 8)->nullable();
            $table->decimal('commission', 18, 8)->nullable();
            $table->decimal('swap', 18, 8)->nullable();
            $table->decimal('net_profit', 18, 8)->default(0);
            $table->timestamps();

            $table->index('strategy_id');
            $table->index('exit_time');
            $table->index('asset');
            $table->index(['strategy_id', 'exit_time']);
            $table->index(['strategy_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
