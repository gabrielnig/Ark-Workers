<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('value', 12, 2);
            // Who logged it, an audit fact, not authorization, same
            // reasoning as tasks.completed_by. Restrict, not cascade,
            // a log entry's history must not silently disappear if
            // the logging user is later deleted.
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('logged_at');
            $table->timestamps();

            $table->index(['vehicle_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_logs');
    }
};
