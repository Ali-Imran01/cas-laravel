<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // role_change, new_account, app_access ...
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            // Connected apps may only submit to workflows an admin has opened to them (never the built-in privileged ones).
            $table->boolean('allow_api')->default(false);
            $table->timestamps();
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('name', 120); // Head of Unit
            $table->enum('approver_type', ['role', 'user', 'unit_head', 'division_head']);
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sla_hours')->default(48);

            $table->unique(['workflow_id', 'level']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique(); // REQ-2291
            $table->foreignId('workflow_id')->constrained('approval_workflows');
            $table->foreignId('requester_id')->constrained('users');
            $table->string('subject_type', 100); // what the request is about: User, Role, Application ...
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->jsonb('payload'); // {"user_id":4,"to_role":"hr_officer"}
            // The workflow's steps as they were at submission: editing a workflow never changes requests already in flight.
            $table->jsonb('steps');
            $table->text('justification')->nullable();
            $table->enum('status', ['pending', 'info_requested', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('current_level')->default(1);
            $table->foreignId('source_application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index('requester_id');
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->foreignId('actor_id')->constrained('users');
            $table->enum('decision', ['submitted', 'approved', 'rejected', 'info_requested', 'commented', 'resubmitted', 'cancelled']);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('request_id');
        });

        // Transfer history can now point at the request that authorised it.
        Schema::table('user_assignments', function (Blueprint $table) {
            $table->foreign('approval_request_id')->references('id')->on('approval_requests')->nullOnDelete();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable(); // encrypted; the HMAC key for signing webhook calls
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_secret']);
        });
        Schema::table('user_assignments', function (Blueprint $table) {
            $table->dropForeign(['approval_request_id']);
        });
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
