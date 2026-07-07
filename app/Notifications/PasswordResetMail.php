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
            ->subject('Reset Your Password - Household OS')
            ->line("Hi {$notifiable->first_name},")
            ->line('We received a request to reset your password.')
            ->line("Your password reset code is: **{$this->code}**")
            ->line('This code expires in 60 minutes.')
            ->line('If you did not request a password reset, you can safely ignore this email.');
    }
}
