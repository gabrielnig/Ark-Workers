<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Whether holding this role in any department grants
            // app-wide management permission (create/edit spaces,
            // assets, routines, assign tasks). Admin-toggled per role,
            // never hardcoded to a role name in policy code.
            $table->boolean('grants_management')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
