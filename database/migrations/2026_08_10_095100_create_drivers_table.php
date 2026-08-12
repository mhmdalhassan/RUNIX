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

            // Availability. `current_order_id` was deliberately left out
            // here — a driver could hold multiple simultaneous active
            // orders at this point in the project. Phase 4 reverses that
            // on purpose (a driver becomes occupied by an offer/assignment
            // and can only hold one order at a time) and adds it via
            // 2026_08_11_142849_add_current_order_id_to_drivers_table.php.
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
