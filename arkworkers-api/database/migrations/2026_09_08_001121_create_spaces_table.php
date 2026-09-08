<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Self-referencing for drill-down (e.g. Building -> Floor -> Room).
            $table->foreignId('parent_space_id')
                ->nullable()
                ->constrained('spaces')
                ->nullOnDelete();
            // The single flag the entire authorization model hinges on
            // (SECURITY.md §4.2). Every query against Spaces, Assets,
            // Routines, and Tasks must check this alongside role.
            $table->boolean('is_restricted')->default(false);
            $table->timestamps();

            $table->index('is_restricted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
