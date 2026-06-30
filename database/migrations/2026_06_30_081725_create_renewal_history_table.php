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
        Schema::create('renewal_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_id')->constrained('renewals')->cascadeOnDelete();
            $table->foreignId('renewed_by_user_id')->constrained('users');
            $table->date('previous_date');
            $table->date('new_date');
            $table->decimal('cost', 10, 2);
            $table->text('notes')->nullable();
            // receipt_document_id added in Phase 5 after documents table
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_history');
    }
};
