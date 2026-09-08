<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            // Flexible per-type checklist/routine definition
            // (ARCHITECTURE.md §3), e.g. AC unit vs. vehicle have
            // completely different default routines.
            $table->json('default_routine_template')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_types');
    }
};
