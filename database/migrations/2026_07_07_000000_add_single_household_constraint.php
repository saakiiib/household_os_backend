<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate active memberships per user (keep most recent)
        DB::statement('
            DELETE hm1 FROM household_members hm1
            INNER JOIN household_members hm2
            ON hm1.user_id = hm2.user_id
            AND hm1.status = hm2.status
            AND hm1.id < hm2.id
        ');

        Schema::table('household_members', function (Blueprint $table) {
            $table->unique(['user_id', 'status'], 'unique_user_status');
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropIndex('unique_user_status');
        });
    }
};
