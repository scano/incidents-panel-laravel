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
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

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

        Schema::create('incident_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->string('old_value', 24)->nullable();
            $table->string('new_value', 24)->nullable();
            $table->string('user_name', 120);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('incident_logs')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->index(['incident_id', 'created_at', 'id']);
        });

        Schema::create('incident_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('author_name', 120);
            $table->text('body');
            $table->timestamps();
            $table->index(['incident_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {

        Schema::dropIfExists('incident_comments');
        Schema::dropIfExists('incident_logs');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
