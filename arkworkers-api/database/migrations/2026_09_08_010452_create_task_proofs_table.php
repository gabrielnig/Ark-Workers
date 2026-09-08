<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // Path within the private disk, never a public URL, per
            // ARCHITECTURE.md §6. Served only through an authenticated,
            // policy-checked route.
            $table->string('file_path');
            $table->string('file_type');
            $table->timestamp('uploaded_at')->useCurrent();
            // Resumable upload support is Phase 3 scope, not this
            // column's job yet, kept nullable so the schema does not
            // need to change when that lands.
            $table->string('chunk_upload_session_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_proofs');
    }
};
