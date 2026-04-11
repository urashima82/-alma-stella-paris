<?php

declare(strict_types=1);

namespace App\Enum;

enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::SuperAdmin => '#C9A84C',
            self::Admin => '#3b82f6',
        };
    }
}
