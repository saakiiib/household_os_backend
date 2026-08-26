<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a `snooze` flag to tasks.
     *
     * When ON, the reminder scheduler additionally fires the escalating
     * "snooze" cascade (every reminder ladder step that is closer to the due
     * time than the chosen `reminder_before`), on top of the single base
     * reminder. When OFF, only the base `reminder_before` reminder is sent.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('snooze')->default(false)->after('reminder_before');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('snooze');
        });
    }
};
