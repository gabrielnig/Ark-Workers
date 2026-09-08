<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            // Every asset belongs to exactly one space, this is the FK
            // that AssetPolicy uses to delegate into SpacePolicy::view()
            // (SECURITY.md §4.2/§4.3). Restrict, not cascade: deleting a
            // space with assets in it must be a deliberate, explicit
            // action, not an accidental side effect.
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->string('name');
            // Varies by type, e.g. vehicle plate number vs. AC unit
            // refrigerant type (ARCHITECTURE.md §3).
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('space_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
