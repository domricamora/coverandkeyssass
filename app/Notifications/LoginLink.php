<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sign-in link for guests who already have an account (guest checkout). */
class LoginLink extends Notification
{
    public function __construct(private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in link for '.config('app.name'))
            ->line('Use this button to sign in and finish your booking. The link expires in 30 minutes.')
            ->action('Sign in and continue', $this->url)
            ->line('If you did not ask for this, you can ignore this email.');
    }
}
