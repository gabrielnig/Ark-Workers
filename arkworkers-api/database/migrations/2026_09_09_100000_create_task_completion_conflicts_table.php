<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_completion_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // The completion that was kept (first-sync-wins).
            $table->foreignId('kept_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('kept_completed_at');
            // The discarded attempt, logged rather than silently
            // dropped, per SECURITY.md 6.4.
            $table->foreignId('discarded_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('discarded_attempted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_completion_conflicts');
    }
};
