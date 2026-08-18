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
            // A single daily window (same hours every day), not a
            // per-day-of-week schedule — kept deliberately simple for
            // this phase. Both null means "no hours configured", which
            // Restaurant::isOpenNow() treats as always open (subject to
            // is_active, unchanged). closes_at can be earlier than
            // opens_at to express an overnight window (e.g. 18:00-02:00);
            // isOpenNow() handles that case explicitly.
            $table->time('opens_at')->nullable()->after('is_active');
            $table->time('closes_at')->nullable()->after('opens_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['opens_at', 'closes_at']);
        });
    }
};
