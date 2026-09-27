<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    use HasFactory;

    public const TYPE_PRIVACY_POLICY = 'privacy_policy';
    public const TYPE_AUDIT_BROWSER_LOCATION = 'audit_browser_location';

    public const STATUS_CONSENTED = 'consented';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_DECLINED = 'declined';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'consent_type',
        'policy_version',
        'status',
        'is_mandatory',
        'consented_at',
        'withdrawn_at',
        'ip_address',
        'user_agent',
        'source',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'consented_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeConsented(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONSENTED)->whereNull('withdrawn_at');
    }

    public function scopeWithdrawn(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WITHDRAWN);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('consent_type', $type);
    }

    public function scopeCurrentVersion(Builder $query, ?string $version = null): Builder
    {
        return $query->where('policy_version', $version ?? config('privacy.policy_version', 'v1.0'));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_CONSENTED && $this->withdrawn_at === null;
    }

    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN || $this->withdrawn_at !== null;
    }

    public function isMandatory(): bool
    {
        return (bool) $this->is_mandatory;
    }
}
