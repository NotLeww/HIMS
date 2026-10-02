<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreated extends Notification
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = [
            'appName' => config('app.name'),
            'name' => $notifiable->name,
            'activationUrl' => (new VerifyEmail)->toMail($notifiable)->actionUrl,
        ];

        return (new MailMessage)
            ->subject('Your HIMS account is ready to activate')
            ->view('emails.auth.account-created', $data)
            ->text('emails.auth.account-created-text', $data);
    }

    /** @return array<string, never> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
