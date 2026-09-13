<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40)->default('general')->index();
            $table->string('subject', 120);
            $table->string('status', 30)->default('open')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('user_last_read_at')->nullable();
            $table->timestamp('admin_last_read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['household_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
