<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->string('action', 40);
            $table->string('old_value', 24)->nullable();
            $table->string('new_value', 24)->nullable();
            $table->string('user_name', 120);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['incident_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_logs');
    }
};
