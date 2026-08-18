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
        Schema::table('users', function (Blueprint $table) {
            // Only ever set for role=restaurant_admin (App\Enums\UserRole)
            // — the one restaurant that account manages. Nullable/
            // nullOnDelete rather than cascading: deleting a restaurant
            // shouldn't silently delete the staff account tied to it, it
            // should just leave it with no restaurant (same "orphaned,
            // not destroyed" choice as Order's driver_earning_set_by).
            $table->foreignId('restaurant_id')->nullable()->after('role')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_id');
        });
    }
};
