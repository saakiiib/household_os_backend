<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewals', function (Blueprint $table) {
            $table->foreignId('source_document_id')
                ->nullable()
                ->after('parent_renewal_id')
                ->constrained('documents')
                ->nullOnDelete();
            $table->index(['household_id', 'source_document_id']);
        });
    }

    public function down(): void
    {
        Schema::table('renewals', function (Blueprint $table) {
            $table->dropForeign(['source_document_id']);
            $table->dropIndex(['household_id', 'source_document_id']);
            $table->dropColumn('source_document_id');
        });
    }
};
