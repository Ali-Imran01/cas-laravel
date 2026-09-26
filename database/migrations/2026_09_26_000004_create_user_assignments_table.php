<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units');
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->date('started_at');
            $table->date('ended_at')->nullable(); // null = current; transfer history
            // FK to approval_requests is added in Phase 5, when that table exists.
            $table->unsignedBigInteger('approval_request_id')->nullable();

            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_assignments');
    }
};
