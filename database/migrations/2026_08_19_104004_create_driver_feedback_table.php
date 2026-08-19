<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_feedback', function (Blueprint $table) {
            $table->id();

            // unique: exactly one feedback per order — the DB-enforced
            // half of the "one feedback per delivered order" rule; the
            // app layer (StoreOrderFeedbackRequest) is the friendly-error
            // half. cascadeOnDelete since a feedback row has no meaning
            // once its order is gone (orders have no destroy route today,
            // but this stays correct if that ever changes).
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            // Denormalized from orders.driver_id at submission time —
            // safe because a DELIVERED order's driver_id is already
            // locked (see UpdateOrderRequest's field-locking rules) and
            // never changes again. Kept as its own column, not derived
            // via order_id every time, so "all feedback for driver X" is
            // one indexed query rather than a join through orders.
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['driver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_feedback');
    }
};
