<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('cancelled_at');
        });

        // Backfill: set expires_at = current_period_end + 3 days for existing active subscriptions
        DB::table('subscriptions')
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->update([
                'expires_at' => DB::raw('DATE_ADD(current_period_end, INTERVAL 3 DAY)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
