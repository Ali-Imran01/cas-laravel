<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('oauth_client_id')->unique()->constrained('oauth_clients')->cascadeOnDelete();
            $table->string('code', 40)->unique(); // ams-prod
            $table->string('name', 120);
            $table->enum('environment', ['production', 'staging', 'sandbox'])->default('production');
            $table->enum('status', ['active', 'sandbox', 'disabled'])->default('active');
            $table->string('homepage_url')->nullable();
            $table->char('color', 7)->nullable();
            $table->jsonb('allowed_scopes'); // ["openid","profile","org.read"]
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('secret_rotated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('application_role', function (Blueprint $table) {
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('app_role', 50); // Viewer, Agent: sent as a claim
            $table->primary(['application_id', 'role_id']);
        });

        Schema::create('application_user', function (Blueprint $table) {
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('app_role', 50);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->primary(['application_id', 'user_id']);
        });

        // Sign-in history can now point at the app that was signed in to.
        Schema::table('login_attempts', function (Blueprint $table) {
            $table->foreign('application_id')->references('id')->on('applications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('login_attempts', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
        });
        Schema::dropIfExists('application_user');
        Schema::dropIfExists('application_role');
        Schema::dropIfExists('applications');
    }
};
