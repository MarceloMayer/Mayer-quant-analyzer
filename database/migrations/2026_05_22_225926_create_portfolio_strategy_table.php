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
        Schema::create('portfolio_strategy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->decimal('weight', 18, 8)->default(1);
            $table->timestamps();

            $table->index('portfolio_id');
            $table->index('strategy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_strategy');
    }
};
