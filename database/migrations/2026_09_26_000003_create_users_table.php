<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('staff_id', 20)->unique();
            $table->string('name', 150);
            $table->string('email', 191)->unique();
            $table->string('password')->nullable(); // null = SSO-only / pending invite
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'locked', 'inactive'])->default('pending');
            $table->timestamp('locked_until')->nullable();
            $table->unsignedTinyInteger('failed_login_count')->default(0);
            $table->boolean('mfa_enabled')->default(false);
            $table->text('mfa_secret')->nullable(); // encrypted cast
            $table->text('mfa_recovery_codes')->nullable(); // encrypted cast
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['org_unit_id', 'status']);
            $table->index('status');
            $table->index('last_login_at');
        });

        // Closes the org_units <-> users cycle (both sides nullable, so no deferral needed).
        Schema::table('org_units', function (Blueprint $table) {
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->dropForeign(['head_user_id']);
        });
        Schema::dropIfExists('users');
    }
};
