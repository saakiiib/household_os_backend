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
        Schema::table('renewals', function (Blueprint $table) {
            $table->string('document_file_path')->nullable()->after('notes');
            $table->string('document_original_name')->nullable()->after('document_file_path');
            $table->string('document_mime_type')->nullable()->after('document_original_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('renewals', function (Blueprint $table) {
            $table->dropColumn(['document_file_path', 'document_original_name', 'document_mime_type']);
        });
    }
};
