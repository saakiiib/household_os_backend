<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewals', function (Blueprint $table) {
            $table->foreignId('parent_renewal_id')->nullable()->constrained('renewals')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->index('parent_renewal_id');
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::table('renewals', function (Blueprint $table) {
            $table->dropForeign(['parent_renewal_id']);
            $table->dropForeign(['document_id']);
            $table->dropColumn(['parent_renewal_id', 'document_id']);
        });
    }
};
