<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['incident_logs', 'incident_comments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['incident_comments', 'incident_logs'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
