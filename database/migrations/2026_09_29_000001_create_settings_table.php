<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A small key-value store for admin-editable settings (security policy overrides, email templates, ...).
        // Absent keys simply fall back to the shipped defaults, so every row here is optional.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
