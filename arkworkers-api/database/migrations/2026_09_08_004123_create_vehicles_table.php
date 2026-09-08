<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number')->unique();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('users')->nullOnDelete();
            // Per document type: insurance, roadworthiness, registration.
            // Kept as JSON per ARCHITECTURE.md §3 rather than a separate
            // table, since the document set is small and fixed for now.
            $table->json('document_expiry')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
