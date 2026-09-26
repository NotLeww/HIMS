<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDeviceApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $deviceSummary,
        public readonly ?string $ipAddress,
        public readonly string $approvedAt,
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
            'approvedAt' => $this->approvedAt,
        ];

        return (new MailMessage)
            ->subject('New device approved for your HIMS account')
            ->view('emails.auth.new-device-approved', $data)
            ->text('emails.auth.new-device-approved-text', $data);
    }

    /**
     * @return array<string, never>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
