<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('reported');
            $table->string('mechanic_name')->nullable();
            $table->text('parts_used')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->dateTime('reported_at')->useCurrent();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_incidents');
    }
};
