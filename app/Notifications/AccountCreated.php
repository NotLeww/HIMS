<?php

namespace App\Notifications;

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
        return (new MailMessage)
            ->subject('Your HIMS account is ready to activate')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your HIMS account has been created.')
            ->line('Activate your account to verify your contact details and create your password.')
            ->action('Activate HIMS Account', route('activation.start'))
            ->line('If you did not expect this account, contact your HIMS administrator.');
    }

    /** @return array<string, never> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
