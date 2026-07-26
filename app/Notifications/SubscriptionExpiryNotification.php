<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public string $message,
        public string $type, // 'renewal' or 'grace_period'
    ) {}

    public function via(object $notifier): array
    {
        return ['database', 'fcm'];
    }

    public function toArray(object $notifier): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'household_id' => $this->subscription->household_id,
            'plan_name' => $this->subscription->plan?->name,
            'type' => $this->type,
            'message' => $this->message,
            'current_period_end' => $this->subscription->current_period_end?->toIso8601String(),
            'expires_at' => $this->subscription->expires_at?->toIso8601String(),
            'action' => $this->type === 'grace_period' ? 'renew_now' : 'view_subscription',
        ];
    }

    public function toMail(object $notifier): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription ' . ($this->type === 'grace_period' ? 'Expiring' : 'Renewal Reminder'))
            ->line($this->message)
            ->action('View Subscription', '/subscription');
    }
}
