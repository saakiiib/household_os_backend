<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->time('due_time')->nullable()->after('due_date');
            $table->string('reminder_before')->nullable()->after('due_time')->comment('e.g. 15_minutes, 1_hour, 1_day, 3_days, 1_week');
            $table->string('repeat')->nullable()->after('reminder_before')->comment('e.g. does_not_repeat, daily, weekly, monthly');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['due_time', 'reminder_before', 'repeat']);
        });
    }
};
