<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('combination_hash', 40)->nullable()->unique()->after('description');
            $table->decimal('initial_balance', 18, 2)->nullable()->after('combination_hash');
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn(['combination_hash', 'initial_balance']);
        });
    }
};
