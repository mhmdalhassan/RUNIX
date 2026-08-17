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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('remember_token');
            $table->text('address')->nullable()->after('phone');
        });

        Schema::table('customers', function (Blueprint $table) {
            // Relaxed: no longer collected at registration — see
            // CompleteProfileController, which asks for it after first
            // login instead. Still required at the form-validation level
            // once a customer is completing their profile; just no
            // longer a blanket DB constraint, since a customer freshly
            // registered but who hasn't completed their profile yet
            // legitimately has none.
            $table->string('phone')->nullable()->change();
            // A real uniqueness guarantee, not just an index, now that
            // email doubles as a login identifier — safe to add: MySQL's
            // unique index already allows unlimited NULLs (unaffected
            // rows stay unaffected), and no two existing rows share a
            // non-null email (checked before writing this migration).
            //
            // ->nullable() is repeated here even though email was
            // already nullable before this migration — Laravel's
            // ->change() re-specifies a column's FULL definition rather
            // than patching just what you name; omitting it silently
            // flips the column to NOT NULL instead of leaving nullability
            // alone. Caught by inspecting the table with DESCRIBE after
            // a first attempt without this line.
            $table->string('email')->nullable()->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->string('phone')->nullable(false)->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token', 'email_verified_at', 'address']);
        });
    }
};
