<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A dedicated table, not a shared one with staff's password_reset_tokens
     * — full isolation between the two account universes, so a customer and
     * a staff member sharing an email (however unlikely) could never
     * collide on a reset token record. See config/auth.php's `customers`
     * password broker, which points here.
     */
    public function up(): void
    {
        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
    }
};
