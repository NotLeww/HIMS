<?php

namespace App\Enums;

enum ActivationCancellationReason: string
{
    case IncorrectUserInformation = 'incorrect_user_information';
    case IncorrectEmailAddress = 'incorrect_email_address';
    case DuplicateAccount = 'duplicate_account';
    case CreatedByMistake = 'created_by_mistake';
    case AccessNoLongerRequired = 'access_no_longer_required';
    case WrongRoleOrDepartment = 'wrong_role_or_department';
    case RequestWithdrawn = 'request_withdrawn';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IncorrectUserInformation => 'Incorrect User Information',
            self::IncorrectEmailAddress => 'Incorrect Email Address',
            self::DuplicateAccount => 'Duplicate Account',
            self::CreatedByMistake => 'Account Created by Mistake',
            self::AccessNoLongerRequired => 'User No Longer Requires Access',
            self::WrongRoleOrDepartment => 'Wrong Role or Department Assignment',
            self::RequestWithdrawn => 'Request Withdrawn',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason) => [$reason->value => $reason->label()])
            ->all();
    }
}
