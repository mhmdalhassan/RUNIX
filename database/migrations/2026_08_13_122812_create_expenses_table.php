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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Simple, free-text expenses (not categorized) — amount,
            // what it was for, and the date it applies to. `date` is
            // deliberately separate from `created_at`: a dispatcher
            // entering yesterday's fuel receipt today should have it
            // count toward yesterday's totals, not today's.
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->date('date');

            // Audit trail, same shape as orders.driver_earning_set_by —
            // nullable + nullOnDelete so a later staff account deletion
            // can never corrupt an expense record.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
