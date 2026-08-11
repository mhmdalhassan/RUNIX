<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backs App\Services\Orders\OrderNumberGenerator. One row per
     * calendar day; `next_number` is incremented under a row lock inside
     * the order-creation transaction, so order numbers are strictly
     * sequential per day and safe under concurrent creation — never
     * `Order::count() + 1`, never derived from `orders.id`. Accessed via
     * `DB::table()` only; deliberately not an Eloquent model.
     */
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->date('date_key')->primary();
            $table->unsignedInteger('next_number')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
