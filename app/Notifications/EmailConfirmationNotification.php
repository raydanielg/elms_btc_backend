<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EmailConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = config('app.frontend_url', 'http://localhost:3000') . '/auth/confirm-email/' . $this->token;

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Confirm Your Email — Butimba Teachers College LMS')
            ->greeting('Hello ' . $notifiable->full_name . ',')
            ->line('Please confirm your email address to activate your account.')
            ->action('Confirm Email', $url)
            ->line('This link will expire in 24 hours.')
            ->line('If you did not create an account, no further action is required.');
    }
}
