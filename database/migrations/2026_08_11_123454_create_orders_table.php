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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Identity. Customer-facing identifiers — never expose the
            // internal `id` for these purposes.
            $table->string('order_number')->unique();
            $table->string('tracking_token', 64)->unique();

            // Customer. The relationship stays authoritative for anything
            // live (e.g. current phone), but the snapshot preserves what
            // the order actually said at the time it was placed — nullable
            // + nullOnDelete so a later customer deletion can never orphan
            // or corrupt an order's history, without ever letting a new
            // order be created customer-less (enforced in StoreOrderRequest).
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name_snapshot');
            $table->string('customer_phone_snapshot');

            // Pickup. No geocoding/route calculation yet — these are
            // future route-snapshot columns, deliberately unused for now.
            $table->text('pickup_address');
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();

            // Delivery.
            $table->text('delivery_address');
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();

            // Driver. Nullable + nullOnDelete for the same integrity reason
            // as customer_id. Deliberately no `is_busy`/current-order
            // pointer anywhere in the schema — a driver may hold multiple
            // simultaneous active orders (see Driver::activeOrders()).
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Status. Always written through
            // App\Services\Orders\OrderTransitionService — never set
            // directly by a controller. Matches OrderStatus::PENDING->value;
            // stored as a plain string (not a DB-native enum) so adding a
            // status later never needs an ALTER TABLE ... MODIFY.
            $table->string('status', 20)->default('pending');

            // Financial. V1 accounting currency is USD only — no FX. COD
            // fields exist regardless of whether COD is enabled
            // (config('runix.cod_enabled')); the application layer forces
            // merchant_amount/cod_amount to 0 while it's off.
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('merchant_amount', 12, 2)->default(0);
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('fee_payer', 20)->default('customer');

            // Driver earning is manually assigned per order (no
            // pricing_rules, no automatic calculation). Normally
            // driver_earning <= delivery_fee; going over that requires an
            // explicit, reasoned, Super-Admin-approved override — all of
            // it auditable via these four columns together.
            $table->decimal('driver_earning', 12, 2)->default(0);
            $table->boolean('driver_earning_override')->default(false);
            $table->text('driver_earning_override_reason')->nullable();
            $table->foreignId('driver_earning_set_by')->nullable()->constrained('users')->nullOnDelete();

            // Payment.
            $table->string('payment_method', 20)->default('cash');
            $table->string('payment_status', 20)->default('pending');

            // Route snapshot. No Google Maps / distance API calls yet —
            // these are populated by a future phase.
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();

            // Notes.
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['driver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
