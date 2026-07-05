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
            ->line('1. Create an account or log in with this email address')
            ->line('2. Go to Members and tap "Accept Invitation"')
            ->line("Your 6-digit invitation code: **{$this->token}**")
            ->line('This code expires in 7 days and can only be used by this email address.')
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }
}
