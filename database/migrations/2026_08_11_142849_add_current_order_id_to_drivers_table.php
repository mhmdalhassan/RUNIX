<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 4 deliberately reverses the Phase 3 decision to have no busy
     * flag: a driver may now hold at most one order at a time. The
     * `unique()` constraint is the actual guarantee — it's what makes
     * "claim this driver" a real, database-enforced atomic operation
     * (see App\Services\Orders\ClaimOrderForDriverService), not just an
     * application-level convention two concurrent requests could both
     * pass.
     */
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('current_order_id')
                ->nullable()
                ->unique()
                ->after('is_online')
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_order_id');
        });
    }
};
