<?php

namespace App\Notifications;

use App\Models\LoginApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class NewDeviceLoginAttemptNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly LoginApprovalRequest $approvalRequest,
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
        $approval = $this->approvalRequest;
        $url = fn (string $decision): string => URL::temporarySignedRoute(
            'auth.device-approval.email.review',
            $approval->expires_at,
            ['approvalRequest' => $approval->id, 'decision' => $decision],
        );

        $data = [
            'deviceSummary' => $approval->device_name ?: 'Unknown device',
            'ipAddress' => $approval->ip_address,
            'requestedAt' => $approval->requested_at->timezone(config('app.timezone', 'UTC'))->format('M d, Y h:i A'),
            'expiresInMinutes' => max(1, now()->diffInMinutes($approval->expires_at)),
            'approveOnceUrl' => $url('approve-once'),
            'approveTrustUrl' => $url('approve-trust'),
            'denyUrl' => $url('deny'),
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
