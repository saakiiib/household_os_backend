<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class InvitationMail extends Notification
{
    public $householdName;
    public $role;
    public $inviterName;

    public function __construct(string $householdName, string $role, string $inviterName)
    {
        $this->householdName = $householdName;
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
            ->line("{$this->inviterName} has invited you to join **{$this->householdName}**.")
            ->line('To accept this invitation:')
            ->line('1. Download Household OS from the App Store or Google Play Store')
            ->line('2. Sign up or log in with this email address')
            ->line('3. You will see a prompt to accept the invitation')
            ->line('')
            ->line('**Download the app:**')
            ->line('[App Store (iOS)](https://apps.apple.com/app/household-os/id000000000)')
            ->line('[Google Play (Android)](https://play.google.com/store/apps/details?id=com.householdos.app)')
            ->line('')
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }
}
