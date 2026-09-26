<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->nestedSet(); // parent_id, _lft, _rgt (kalnoy/nestedset)
            $table->enum('type', ['headquarters', 'division', 'unit', 'state_office']);
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            // Circular with users.org_unit_id: the FK is added after users exists.
            $table->unsignedBigInteger('head_user_id')->nullable();
            $table->string('cost_centre', 30)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('org_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_units');
    }
};
