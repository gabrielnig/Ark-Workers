<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            // Who granted access, for audit trail, per SECURITY.md §4.2.
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamps();

            // A user can only have one active grant per space.
            $table->unique(['user_id', 'space_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_access_grants');
    }
};
