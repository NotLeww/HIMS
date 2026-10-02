<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ScheduledReport extends Model
{
    public const FREQUENCIES = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ];

    public const DAYS_OF_WEEK = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    protected $fillable = [
        'report_type',
        'report_format',
        'frequency',
        'day_of_week',
        'day_of_month',
        'run_at',
        'filters',
        'recipient_user_id',
        'created_by_user_id',
        'is_active',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ScheduledReportExecution::class);
    }

    public function nextRunAfter(Carbon $after): Carbon
    {
        $candidate = $after->copy()->startOfDay()->setTimeFromTimeString($this->run_at);

        if ($this->frequency === 'weekly') {
            $candidate->addDays(((int) $this->day_of_week - $candidate->dayOfWeek + 7) % 7);
            if ($candidate->lessThanOrEqualTo($after)) {
                $candidate->addWeek();
            }

            return $candidate;
        }

        if ($this->frequency === 'monthly') {
            $candidate->day(min((int) $this->day_of_month, $candidate->daysInMonth));
            if ($candidate->lessThanOrEqualTo($after)) {
                $candidate->addMonthNoOverflow()->startOfMonth();
                $candidate->day(min((int) $this->day_of_month, $candidate->daysInMonth));
                $candidate->setTimeFromTimeString($this->run_at);
            }

            return $candidate;
        }

        return $candidate->lessThanOrEqualTo($after) ? $candidate->addDay() : $candidate;
    }

    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            'weekly' => 'Weekly on '.self::DAYS_OF_WEEK[(int) $this->day_of_week],
            'monthly' => 'Monthly on day '.$this->day_of_month,
            default => 'Daily',
        };
    }
}
