<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('provider', 255)->nullable()->after('description');
            $table->string('reference_number', 255)->nullable()->after('provider');
            $table->date('document_date')->nullable()->after('reference_number');
            $table->string('metadata_source', 50)->nullable()->after('document_date');
            $table->timestamp('metadata_confirmed_at')->nullable()->after('metadata_source');
            $table->index('provider');
            $table->index('reference_number');
            $table->index('document_date');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropIndex(['reference_number']);
            $table->dropIndex(['document_date']);
            $table->dropColumn([
                'provider',
                'reference_number',
                'document_date',
                'metadata_source',
                'metadata_confirmed_at',
            ]);
        });
    }
};
