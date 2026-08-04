<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ApplicationReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $decision,
        public string $programName
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = config('app.frontend_url', 'http://localhost:3000') . '/dashboard/application-status';

        $message = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Application Update — Butimba Teachers College')
            ->greeting('Hello ' . $notifiable->full_name . ',');

        if ($this->decision === 'ACCEPTED') {
            $message->line('Congratulations! Your application has been APPROVED.')
                ->line('Programme: ' . $this->programName)
                ->line('Please pay the admission fee to activate your student account.')
                ->action('Pay Admission Fee', $url)
                ->line('Welcome to Butimba Teachers College!');
        } else {
            $message->line('Your application has not been successful at this time.')
                ->line('Programme: ' . $this->programName)
                ->line('You may reapply in the next intake period.')
                ->line('If you have questions, please contact the admissions office.');
        }

        return $message;
    }
}
