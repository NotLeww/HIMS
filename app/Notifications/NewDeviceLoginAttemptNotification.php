<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDeviceLoginAttemptNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $deviceSummary,
        public readonly ?string $ipAddress,
        public readonly string $requestedAt,
        #[\SensitiveParameter] public readonly ?string $otp = null,
        public readonly int $expiresInMinutes = 5,
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
            'requestedAt' => $this->requestedAt,
            'otp' => $this->otp,
            'expiresInMinutes' => $this->expiresInMinutes,
        ];

        return (new MailMessage)
            ->subject('New sign-in attempt to your HIMS account')
            ->view('emails.auth.new-device-login-attempt', $data)
            ->text('emails.auth.new-device-login-attempt-text', $data);
    }

    /**
     * @return array<string, never>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
