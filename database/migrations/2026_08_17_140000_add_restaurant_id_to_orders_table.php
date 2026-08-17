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
        Schema::table('orders', function (Blueprint $table) {
            // Nullable + nullOnDelete, same integrity pattern as
            // customer_id/driver_id — a dispatcher-created order has no
            // restaurant at all (pickup_address is free text), so this
            // is only ever populated for a customer's own order, and a
            // later restaurant deletion must never corrupt an order's
            // history. Order::pickup_address/latitude/longitude stay the
            // authoritative snapshot of where the order was actually
            // picked up from — this column is for filtering/relating,
            // not a substitute for that snapshot.
            $table->foreignId('restaurant_id')->nullable()->after('customer_phone_snapshot')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_id');
        });
    }
};
