<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            // Soft-delete only, deliberately NOT paired with Prunable
            // the way Asset is. A deleted routine's task and proof
            // history (who did the routine, when, and any photo proof)
            // must stay permanently reachable, not just for a 30-day
            // grace period. See RoutineController::destroy().
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
