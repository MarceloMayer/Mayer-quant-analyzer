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
        Schema::create('portfolio_weight_optimizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('objective', 40);
            $table->unsignedSmallInteger('min_weight');
            $table->unsignedSmallInteger('max_weight');
            $table->decimal('max_correlation', 4, 3)->nullable();
            $table->boolean('changed')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('applied_at')->nullable();
            $table->json('result');
            $table->timestamps();

            $table->index(['portfolio_id', 'is_favorite', 'created_at'], 'pwo_portfolio_favorite_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_weight_optimizations');
    }
};
