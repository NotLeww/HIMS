<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalSystemErrorNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $errorId,
        public readonly string $module,
        public readonly string $operation,
        public readonly string $summary,
        public readonly ?string $actorName = null
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
        return (new MailMessage)
            ->error()
            ->subject(sprintf('[HIMS CRITICAL] System Incident #%s', $this->errorId))
            ->greeting('Attention Super Administrator,')
            ->line(sprintf('A critical system failure was intercepted during operation [%s] in module [%s].', $this->operation, $this->module))
            ->line(sprintf('Triggered by: %s', $this->actorName ?? 'System / Automated'))
            ->line(sprintf('Error Summary: %s', $this->summary))
            ->line('All ongoing database transactions were automatically rolled back to preserve clinical records and inventory data integrity.')
            ->line('Review the secure application logs using the incident reference and follow the established operational recovery procedure.');
    }
}
