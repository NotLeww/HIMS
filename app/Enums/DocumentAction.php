<?php

namespace App\Enums;

enum DocumentAction: string
{
    case Uploaded = 'uploaded';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Updated = 'updated';
    case Replaced = 'replaced';
    case Deleted = 'deleted';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Viewed => 'Viewed',
            self::Downloaded => 'Downloaded',
            self::Updated => 'Updated',
            self::Replaced => 'Replaced',
            self::Deleted => 'Deleted',
            self::Restored => 'Restored',
        };
    }
}
