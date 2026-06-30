<?php

namespace App\Jobs;

use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckRenewalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting CheckRenewalsJob...");

        // Get all active renewals
        $renewals = Renewal::where('status', 'active')->get();

        foreach ($renewals as $renewal) {
            $days = $renewal->days_remaining;

            if ($days === 90 && !$renewal->reminder_sent_90d) {
                $this->sendReminder($renewal, 90);
                $renewal->update(['reminder_sent_90d' => true]);
            } elseif ($days === 30 && !$renewal->reminder_sent_30d) {
                $this->sendReminder($renewal, 30);
                $renewal->update(['reminder_sent_30d' => true]);
            } elseif ($days === 7 && !$renewal->reminder_sent_7d) {
                $this->sendReminder($renewal, 7);
                $renewal->update(['reminder_sent_7d' => true]);
            } elseif ($days === 0 && !$renewal->reminder_sent_due) {
                $this->sendReminder($renewal, 0);
                $renewal->update(['reminder_sent_due' => true]);
            } elseif ($days < 0) {
                // Escalation: Send high priority notification to all Admins/Co-Admins
                $this->escalateOverdue($renewal);
            }
        }

        Log::info("CheckRenewalsJob finished successfully.");
    }

    /**
     * Send standard reminder to responsible user.
     */
    private function sendReminder(Renewal $renewal, int $days): void
    {
        $timeStr = $days === 0 ? "today" : "in {$days} days";
        
        NotificationService::send([
            'household_id' => $renewal->household_id,
            'user_id' => $renewal->responsible_user_id,
            'notification_type' => "renewal_{$days}d",
            'title' => "Renewal Reminder: {$renewal->title}",
            'message' => "The renewal for '{$renewal->title}' is due {$timeStr} (Date: {$renewal->renewal_date->toDateString()}). Please take action.",
            'data' => [
                'renewal_id' => $renewal->id,
                'days_remaining' => $days
            ],
            'priority' => $days <= 7 ? 'high' : 'normal',
            'channels' => ['in_app', 'push']
        ]);
    }

    /**
     * Escalate overdue renewal to Admins/Co-Admins.
     */
    private function escalateOverdue(Renewal $renewal): void
    {
        // Find all admins and co-admins in the household
        $admins = HouseholdMember::where('household_id', $renewal->household_id)
            ->whereIn('role', ['admin', 'co-admin'])
            ->where('status', 'active')
            ->get();

        foreach ($admins as $admin) {
            NotificationService::send([
                'household_id' => $renewal->household_id,
                'user_id' => $admin->user_id,
                'notification_type' => 'renewal_overdue_escalation',
                'title' => "URGENT: Overdue Renewal - {$renewal->title}",
                'message' => "The renewal for '{$renewal->title}' assigned to {$renewal->responsibleUser?->name} was due on {$renewal->renewal_date->toDateString()} and is now overdue by " . abs($renewal->days_remaining) . " days.",
                'data' => [
                    'renewal_id' => $renewal->id,
                    'days_overdue' => abs($renewal->days_remaining)
                ],
                'priority' => 'urgent',
                'channels' => ['in_app', 'push']
            ]);
        }
    }
}
