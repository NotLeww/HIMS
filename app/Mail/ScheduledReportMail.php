<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $reportTitle,
        public readonly Carbon $scheduledFor,
        public readonly array $filterSummary,
        public readonly string $attachmentData,
        public readonly string $attachmentName,
        public readonly string $attachmentMime,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('HIMS Scheduled Report: '.$this->reportTitle)
            ->markdown('emails.scheduled-report')
            ->attachData($this->attachmentData, $this->attachmentName, ['mime' => $this->attachmentMime]);
    }
}
