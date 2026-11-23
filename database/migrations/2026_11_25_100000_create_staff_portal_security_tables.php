<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff portal hardening (completion sprint, milestones 1–6). Additive only: existing rows keep working
 * (new columns are nullable or default to the previous behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Incremented to invalidate every staff session of this account (revoke sessions / reset access).
            $table->unsignedInteger('staff_session_epoch')->default(0);
            $table->boolean('password_change_required')->default(false);
            // JSON array of HMAC-SHA256 hashes of unused recovery codes (codes are shown once, never stored in plain text).
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 255)->nullable();
            $table->timestamp('last_staff_login_at')->nullable();
            $table->string('last_staff_login_ip', 45)->nullable();
        });

        // Separate token store so a staff reset token can never be redeemed through the customer reset form.
        Schema::create('staff_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->index();
            $table->string('name', 120);
            $table->foreignId('role_id')->constrained('roles');
            $table->json('department_ids')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('staff_password_reset_tokens');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'staff_session_epoch', 'password_change_required', 'two_factor_recovery_codes', 'suspended_at',
            'suspension_reason', 'last_staff_login_at', 'last_staff_login_ip',
        ]));
    }
};
