<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class CleanupNotifications extends Command
{
    protected $signature = 'notifications:cleanup';
    protected $description = 'Retain bell notifications for 30 days and at most 100 per user';

    public function handle(): int
    {
        // Actionable membership items are retained until their workflow resolves;
        // routine history is automatically pruned after 30 days.
        $actionableTypes = ['invitation', 'household_invitation', 'member_request', 'join_request'];

        Notification::where('created_at', '<', now()->subDays(30))
            ->whereNotIn('type', $actionableTypes)
            ->delete();

        Notification::query()->select('user_id')->distinct()->pluck('user_id')->each(function ($userId) use ($actionableTypes) {
            $keepIds = Notification::where('user_id', $userId)
                ->orderByDesc('created_at')->limit(100)->pluck('id');
            Notification::where('user_id', $userId)
                ->whereNotIn('id', $keepIds)
                ->whereNotIn('type', $actionableTypes)
                ->delete();
        });

        return Command::SUCCESS;
    }
}
