<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_logs', function (Blueprint $table) {
            $table->foreignId('duplicate_of_id')->nullable()->constrained('incident_logs')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incident_logs', function (Blueprint $table) {
            $table->dropForeign(['duplicate_of_id']);
            $table->dropColumn(['duplicate_of_id', 'voided_at']);
        });
    }
};
