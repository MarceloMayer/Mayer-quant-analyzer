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
            $table->string('ticket')->nullable();
            $table->string('symbol')->nullable();
            $table->string('type')->nullable();
            $table->decimal('volume', 16, 4)->default(0);
            $table->decimal('price', 16, 5)->nullable();
            $table->decimal('commission', 16, 2)->default(0);
            $table->decimal('swap', 16, 2)->default(0);
            $table->decimal('profit', 16, 2)->default(0);
            $table->decimal('balance', 16, 2)->nullable();
            $table->dateTime('closed_at')->index();
            $table->timestamps();

            $table->index(['strategy_id', 'closed_at']);
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
