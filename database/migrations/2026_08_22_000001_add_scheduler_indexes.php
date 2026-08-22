<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tasks: scheduler queries "WHERE status != completed AND due_date = X AND assigned_user_id = Y"
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['status', 'due_date', 'assigned_user_id'], 'idx_tasks_scheduler');
        });

        // Renewals: scheduler queries "WHERE status = pending AND due_date <= X"
        Schema::table('renewals', function (Blueprint $table) {
            $table->index(['status', 'due_date'], 'idx_renewals_scheduler');
        });

        // Subscriptions: scheduler queries "WHERE status = X AND current_period_end <= Y"
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'current_period_end'], 'idx_subscriptions_scheduler');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tasks_scheduler');
        });

        Schema::table('renewals', function (Blueprint $table) {
            $table->dropIndex('idx_renewals_scheduler');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_scheduler');
        });
    }
};
