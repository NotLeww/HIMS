<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuspiciousLoginBlockedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $deviceSummary,
        public readonly ?string $ipAddress,
        public readonly int $cooldownMinutes,
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
            'cooldownMinutes' => $this->cooldownMinutes,
        ];

        return (new MailMessage)
            ->subject('Suspicious sign-in attempt blocked')
            ->view('emails.auth.suspicious-login-blocked', $data)
            ->text('emails.auth.suspicious-login-blocked-text', $data);
    }

    /**
     * @return array<string, never>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
