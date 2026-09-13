<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends Notification
{
    public $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Verify your email - Household OS')
            ->view('emails.verify-email', [
                'code' => $this->code,
                'notifiable' => $notifiable,
            ]);
    }
}
