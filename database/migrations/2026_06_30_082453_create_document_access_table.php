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
        Schema::create('document_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('accessed_by_user_id')->constrained('users');
            $table->enum('action', ['viewed', 'downloaded', 'uploaded']);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('accessed_at')->useCurrent();

            $table->index('accessed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_access');
    }
};
