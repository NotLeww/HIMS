<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionTakeoverNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $deviceSummary,
        public readonly ?string $ipAddress,
        public readonly string $takenOverAt,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = [
            'deviceSummary' => $this->deviceSummary,
            'ipAddress' => $this->ipAddress,
            'takenOverAt' => $this->takenOverAt,
        ];

        return (new MailMessage)
            ->subject('HIMS session transferred to trusted browser')
            ->view('emails.auth.session-takeover', $data)
            ->text('emails.auth.session-takeover-text', $data);
    }

    /**
     * @return array<string, never>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
