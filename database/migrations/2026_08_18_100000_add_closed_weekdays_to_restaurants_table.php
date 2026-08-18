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
        Schema::table('restaurants', function (Blueprint $table) {
            // Whole days off, on top of opens_at/closes_at's daily window
            // — e.g. closed every Monday. A JSON array of 0-6 (Sunday=0,
            // matching Carbon's ->dayOfWeek/PHP's date('w')), empty/null
            // meaning "closed no days". See Restaurant::isOpenNow().
            $table->json('closed_weekdays')->nullable()->after('closes_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('closed_weekdays');
        });
    }
};
