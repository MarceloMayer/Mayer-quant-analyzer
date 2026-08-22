<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('strategies', function (Blueprint $table) {
            $table->unsignedBigInteger('magic_number')->nullable()->after('name');
        });

        DB::table('strategies')
            ->whereNull('magic_number')
            ->update(['magic_number' => DB::raw('id')]);

        Schema::table('strategies', function (Blueprint $table) {
            $table->unsignedBigInteger('magic_number')->nullable(false)->change();
            $table->unique('magic_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('strategies', function (Blueprint $table) {
            $table->dropUnique(['magic_number']);
            $table->dropColumn('magic_number');
        });
    }
};
