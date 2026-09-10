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
        Schema::table('account_requests', function (Blueprint $table) {
            // Optional, what the worker wants to be called day to day,
            // separate from their full/baptismal name in name.
            $table->string('display_name')->nullable()->after('name');
            // Ministry office, e.g. Brother, Sister, Evangelist,
            // Deacon, Deaconess, Pastor. A plain administrative label
            // carried through to User::title on activation, never
            // checked by any policy, same as the existing users.title
            // column, see 0001_01_01_000000_create_users_table.
            $table->string('title')->nullable()->after('display_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_requests', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'title']);
        });
    }
};
