<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            // Either a specific asset (concrete instance), or a
            // type-level default template. Not both required, per
            // ARCHITECTURE.md §3.
            $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('calendar_interval_days')->nullable();
            $table->unsignedInteger('meter_threshold')->nullable();
            $table->boolean('requires_proof')->default(false);
            $table->timestamps();

            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routines');
    }
};
