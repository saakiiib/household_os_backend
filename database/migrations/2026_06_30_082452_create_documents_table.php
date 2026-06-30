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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->string('title');
            $table->enum('category', ['insurance', 'passport', 'medical', 'school', 'warranty', 'contract', 'deed', 'utility_bill', 'tax', 'other']);
            $table->text('description')->nullable();
            $table->string('file_type', 10);
            $table->string('file_name_original');
            $table->string('file_name_stored');
            $table->text('file_path');
            $table->bigInteger('file_size_bytes');
            $table->boolean('is_encrypted')->default(true);
            $table->string('encryption_method', 20)->default('AES-256-CBC');
            $table->text('encryption_key_hash')->nullable();
            $table->string('mime_type', 100);
            $table->string('checksum');
            $table->date('expiry_date')->nullable();
            $table->json('shared_with_roles')->nullable();
            $table->json('shared_with_users')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->integer('download_count')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
