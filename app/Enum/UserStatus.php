<?php

namespace App\Enum;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}
