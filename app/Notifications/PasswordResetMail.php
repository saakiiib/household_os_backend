<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetMail extends Notification
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
            ->subject('Reset your Household OS password')
            ->view('emails.password-reset', [
                'code' => $this->code,
                'notifiable' => $notifiable,
            ]);
    }
}
