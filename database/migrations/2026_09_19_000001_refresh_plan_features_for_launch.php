<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            'free' => ['5 active Tasks', '3 active Renewals', '5 documents', '50 MB Document Locker', '2 household members'],
            'essentials' => ['All Tasks features', 'All Renewals features', 'Tasks + Renewals combined', '250 MB Document Locker', 'Save vs buying separately'],
            'documents' => ['500 MB secure storage', 'Encrypted document storage', 'Multi-file upload', 'Secure file download & share', 'Search by title & category'],
            'complete' => ['All Tasks features', 'All Renewals features', 'All Document Locker features', '500 MB secure Document Locker', 'Household sharing', 'All future modules included'],
        ];

        foreach ($updates as $code => $features) {
            DB::table('subscription_plans')->where('code', $code)->update([
                'features' => json_encode($features),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Marketing copy is intentionally not rolled back to obsolete launch wording.
    }
};
