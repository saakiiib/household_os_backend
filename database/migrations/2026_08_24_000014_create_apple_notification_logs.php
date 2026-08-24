<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * apple_notification_logs (command.txt §17).
     * notification_uuid is UNIQUE so duplicate Server Notifications are ignored.
     */
    public function up(): void
    {
        Schema::create('apple_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_uuid')->unique();
            $table->string('notification_type')->nullable();
            $table->string('subtype')->nullable();
            $table->string('environment')->nullable();
            $table->string('original_transaction_id')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->boolean('status')->default(false);
            $table->text('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_notification_logs');
    }
};
