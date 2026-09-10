<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            // Was cascadeOnDelete(). A decommissioned Asset is
            // eventually hard-pruned (30 days, see Asset::prunable()),
            // and that hard delete happens at the database engine
            // level via the FK constraint, bypassing Eloquent's
            // SoftDeletes entirely. With cascadeOnDelete, that prune
            // was silently hard-deleting the Asset's Routines too,
            // which then cascaded again onto Tasks/task_proofs via
            // routines.id's own cascade, reintroducing the exact
            // history-loss problem already fixed for direct routine
            // deletion, just through a different door. nullOnDelete
            // keeps the Routine row (and its Task/proof history)
            // intact, only severing the now-meaningless link to the
            // pruned Asset. Decided 2026-09-10, see BUILD-PLAN.md.
            $table->dropForeign(['asset_id']);
            $table->foreign('asset_id')->references('id')->on('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->foreign('asset_id')->references('id')->on('assets')->cascadeOnDelete();
        });
    }
};
