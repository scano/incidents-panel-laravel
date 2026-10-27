<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('description');
            $table->string('status', 24);
            $table->string('priority', 24);
            $table->string('requester_name', 120);
            $table->string('assigned_to', 120)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['priority', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
