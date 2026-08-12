<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Audit trail for the offer/acceptance pipeline: which drivers were
     * offered an order, when, and how they responded. Rows are updated in
     * place as an offer resolves (pending -> accepted/rejected/expired/
     * cancelled) — unlike order_status_histories this is not append-only,
     * each offer has exactly one lifecycle to record.
     */
    public function up(): void
    {
        Schema::create('order_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->timestamp('offered_at');
            $table->timestamp('responded_at')->nullable();
            $table->string('result', 20)->default('pending');
            $table->timestamps();

            $table->index(['order_id', 'result']);
            $table->index(['driver_id', 'result']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_offers');
    }
};
