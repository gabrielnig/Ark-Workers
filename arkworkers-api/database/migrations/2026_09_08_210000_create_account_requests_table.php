<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            // pending, approved, rejected. No password here, an
            // approved request only becomes a real login-capable User
            // once the invite link is used.
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            // Only the hash is ever stored, same principle as a
            // password, the plaintext token only ever exists in the
            // one email sent to the applicant.
            $table->string('invite_token_hash')->nullable();
            $table->timestamp('invite_expires_at')->nullable();
            // Set once the invite link is actually used, distinct from
            // reviewed_at (approval) since a request can be approved
            // and sit unconsumed for days.
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_requests');
    }
};
