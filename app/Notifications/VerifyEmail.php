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
            ->subject('Your Verification Code - Household OS')
            ->line('Thanks for signing up!')
            ->line('Your verification code is:')
            ->line($this->code)
            ->line('This code expires in 15 minutes.')
            ->line('If you did not create an account, no action is needed.');
    }
}
