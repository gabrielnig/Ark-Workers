<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            // One role per department per worker, not one row per
            // possible role, a worker's standing in a department is
            // singular even though their department memberships aren't.
            $table->unique(['department_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user');
    }
};
