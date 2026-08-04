<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $programName,
        public string $mode
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = config('app.frontend_url', 'http://localhost:3000') . '/dashboard/application-status';

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Application Received — Butimba Teachers College')
            ->greeting('Hello ' . $notifiable->full_name . ',')
            ->line('Your application has been successfully submitted.')
            ->line('Programme: ' . $this->programName)
            ->line('Mode: ' . $this->mode)
            ->line('Your application is now under review. Expected timeline: 3-5 business days.')
            ->action('Track Application Status', $url)
            ->line('Thank you for choosing Butimba Teachers College.');
    }
}
