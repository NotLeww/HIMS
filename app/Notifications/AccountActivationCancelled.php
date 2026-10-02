<?php

namespace App\Notifications;

use App\Enums\ActivationCancellationReason;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountActivationCancelled extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ActivationCancellationReason $reason,
        private readonly ?string $details,
    ) {}

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
            'reason' => $this->reason->label(),
            'details' => $this->details,
        ];

        return (new MailMessage)
            ->subject('HIMS Account Activation Cancelled')
            ->view('emails.auth.account-activation-cancelled', $data)
            ->text('emails.auth.account-activation-cancelled-text', $data);
    }

    /** @return array<string, never> */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
