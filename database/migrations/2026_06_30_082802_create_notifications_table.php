<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification_type', 50); // e.g. task_assigned, renewal_90d
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // e.g. {"task_id": 127}
            $table->text('action_url')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->json('channels')->nullable(); // default in_app (will be set via model or database)
            $table->enum('status', ['pending', 'sent', 'read', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'read_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
