<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // No foreign keys: history must outlive the users and apps it mentions, and the
            // append-only trigger below would block the UPDATE that "on delete set null" needs.
            $table->unsignedBigInteger('actor_id')->nullable(); // null = System
            $table->string('action', 30); // CREATE UPDATE DELETE LOCK LOCKOUT ...
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('description', 255);
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->enum('result', ['success', 'failed', 'blocked'])->default('success');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['actor_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['action', 'created_at']);
        });

        // Append-only at the database level, so no code path (or stray query) can rewrite history.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_logs_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs is append-only';
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::unprepared('CREATE TRIGGER audit_logs_no_change BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION audit_logs_immutable()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_immutable()');
        }
    }
};
