<?php

namespace App\Services\DeviceSecurity;

use App\Models\LoginApprovalRequest;

class DeviceLoginResult
{
    public const TRUSTED = 'trusted';

    public const WAITING_APPROVAL = 'waiting_approval';

    public const REQUIRES_EMAIL_CONFIRMATION = 'requires_email_confirmation';

    public const PROCEED_WITH_MFA = 'proceed_with_mfa';

    public function __construct(
        public readonly string $status,
        public readonly ?LoginApprovalRequest $approvalRequest = null,
        public readonly ?string $token = null,
        public readonly ?string $message = null,
    ) {}

    public static function trusted(): self
    {
        return new self(self::TRUSTED);
    }

    public static function waitingApproval(LoginApprovalRequest $request, string $token): self
    {
        return new self(self::WAITING_APPROVAL, $request, $token);
    }

    public static function makeEmailConfirmationRequired(LoginApprovalRequest $request, string $token): self
    {
        return new self(self::REQUIRES_EMAIL_CONFIRMATION, $request, $token);
    }

    public static function proceedWithMfa(): self
    {
        return new self(self::PROCEED_WITH_MFA);
    }

    public function isTrusted(): bool
    {
        return $this->status === self::TRUSTED;
    }

    public function isWaitingApproval(): bool
    {
        return $this->status === self::WAITING_APPROVAL;
    }

    public function requiresEmailConfirmation(): bool
    {
        return $this->status === self::REQUIRES_EMAIL_CONFIRMATION;
    }

    public function isProceedWithMfa(): bool
    {
        return $this->status === self::PROCEED_WITH_MFA;
    }
}
