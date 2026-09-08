<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Phone is the primary identifier for login (PIN or OTP),
            // per SECURITY.md §3.1, low-tech-literacy staff base.
            $table->string('phone')->unique();
            $table->timestamp('phone_verified_at')->nullable();
            // One of: admin, pastor, facility_manager, cleaning_staff,
            // maintenance, security, driver, per SECURITY.md §4.1.
            $table->string('role');
            // Adaptive hash (bcrypt) of the user's PIN. Nullable because
            // a user provisioned for OTP-only login may not have a PIN set.
            $table->string('pin_hash')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
