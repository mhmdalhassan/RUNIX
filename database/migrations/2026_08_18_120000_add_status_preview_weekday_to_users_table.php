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
            // A Dispatcher/Super Admin's remembered choice of which
            // weekday to preview a restaurant's open/closed status for
            // on the restaurant show page (0-6, Sunday-first — same
            // indexing as Restaurant::closed_weekdays). Null means "no
            // preference set yet", which defaults to today — see
            // Admin\RestaurantController@show. Saved to the account
            // (not per-browser) so it follows the same staff member
            // across devices, and isn't scoped to one restaurant — it's
            // one preview-day preference reused across every
            // restaurant they look at.
            $table->unsignedTinyInteger('status_preview_weekday')->nullable()->after('restaurant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status_preview_weekday');
        });
    }
};
