<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Friendly display name, e.g. "Church Bus", "Prophet's
            // Car". Plate number alone isn't how anyone actually
            // refers to a vehicle day to day, every other entity in
            // the app (Space, Asset) already has a name field.
            $table->string('name')->nullable()->after('plate_number');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
