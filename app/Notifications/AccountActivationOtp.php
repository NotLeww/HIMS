<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountActivationOtp extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] public readonly string $otp,
        public readonly int $expiresInMinutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('HIMS Account Activation Code')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Use this one-time code to continue activating your HIMS account:')
            ->line($this->otp)
            ->line("This code expires in {$this->expiresInMinutes} minutes. Never share it with anyone.")
            ->line('If you did not request this code, you can ignore this message.');
    }

    /** @return array<string, never> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
