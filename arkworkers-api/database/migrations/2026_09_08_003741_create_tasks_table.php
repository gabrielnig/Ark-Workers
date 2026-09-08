<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            // Restrict, not cascade: a task's completion history is an
            // audit record and must not silently disappear if the
            // assigned user is deleted. A deleted user needs their
            // tasks reassigned first.
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('due_at');
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['assigned_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
