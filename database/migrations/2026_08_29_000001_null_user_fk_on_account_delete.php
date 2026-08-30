<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow user-owned authorship/invite references to be nulled when an
     * account is deleted, instead of blocking the delete with a FK constraint.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->change();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->change();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->change();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('renewals', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->change();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('invited_by_user_id')->nullable()->change();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('accepted_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreignId('created_by_user_id')->nullable(false)->change();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreignId('created_by_user_id')->nullable(false)->change();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreignId('created_by_user_id')->nullable(false)->change();
        });

        Schema::table('renewals', function (Blueprint $table) {
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreignId('created_by_user_id')->nullable(false)->change();
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->foreign('invited_by_user_id')->references('id')->on('users');
            $table->foreignId('invited_by_user_id')->nullable(false)->change();
            $table->foreign('accepted_by_user_id')->references('id')->on('users');
        });
    }
};
