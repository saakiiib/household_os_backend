<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class InvitationMail extends Notification
{
    public $householdName;
    public $token;
    public $role;
    public $inviterName;

    public function __construct(string $householdName, string $token, string $role, string $inviterName)
    {
        $this->householdName = $householdName;
        $this->token = $token;
        $this->role = $role;
        $this->inviterName = $inviterName;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("You're Invited to {$this->householdName} - Household OS")
            ->line("{$this->inviterName} has invited you to join **{$this->householdName}** as a **{$this->role}**.")
            ->line('To accept this invitation:')
            ->line('1. Download Household OS from the App Store or Google Play Store')
            ->line('2. Open the app and tap "Join with Invite Code" on the dashboard')
            ->line("3. Enter your 6-digit code: **{$this->token}**")
            ->line('')
            ->line('**Download the app:**')
            ->line('[App Store (iOS)](https://apps.apple.com/app/household-os/id000000000)')
            ->line('[Google Play (Android)](https://play.google.com/store/apps/details?id=com.householdos.app)')
            ->line('')
            ->line('This code expires in 7 days and can only be used by this email address.')
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }
}
