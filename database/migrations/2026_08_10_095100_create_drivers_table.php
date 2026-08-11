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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone')->unique();

            // Availability. Do NOT add a `current_order_id` column here —
            // a driver may hold multiple simultaneous active orders
            // (see Driver::activeOrders() once the orders table exists).
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();

            // Current location snapshot only. Historical GPS trail belongs
            // in a separate `driver_locations` table (Phase 2+), not here.
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->decimal('last_accuracy', 8, 2)->nullable();
            $table->timestamp('last_location_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
