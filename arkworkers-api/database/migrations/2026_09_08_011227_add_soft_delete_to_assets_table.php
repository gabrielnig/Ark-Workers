<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Marks an asset decommissioned rather than immediately
            // wiping its routine/task/proof history. A scheduled prune
            // permanently removes it 30 days after this is set, so
            // deleting an asset never means instantly losing data.
            $table->timestamp('decommissioned_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['decommissioned_at', 'deleted_at']);
        });
    }
};
