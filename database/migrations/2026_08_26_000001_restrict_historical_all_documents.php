<?php

use App\Models\Document;
use App\Models\HouseholdMember;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Convert legacy "all" documents into "specific" documents that are
     * shared only with the household's current active members. This keeps
     * existing members' access intact while ensuring members who join the
     * household later cannot see these previously-created documents unless
     * they are explicitly assigned.
     */
    public function up(): void
    {
        Document::where('visibility', 'all')->chunkById(100, function ($documents) {
            foreach ($documents as $document) {
                $memberIds = HouseholdMember::where('household_id', $document->household_id)
                    ->where('status', 'active')
                    ->pluck('user_id')
                    ->all();

                if (!empty($memberIds)) {
                    $document->allowedMembers()->sync($memberIds);
                }

                $document->update(['visibility' => 'specific']);
            }
        });
    }

    public function down(): void
    {
        // Intentionally not reversible: the original "all" intent cannot be
        // safely reconstructed. Reverting individually can be done manually.
    }
};
