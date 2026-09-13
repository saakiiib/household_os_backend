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
        $companyName = \App\Models\Setting::get('company_name', 'Household OS');

        return (new MailMessage)
            ->subject("You're invited to join {$this->householdName} - {$companyName}")
            ->view('emails.invitation', [
                'householdName' => $this->householdName,
                'inviterName' => $this->inviterName,
                'notifiable' => $notifiable,
            ]);
    }
}
