<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';

    case Manager = 'manager';

    case Operator = 'operator';

    case Financial = 'financial';

    case Content = 'content';

    /** @return list<string> */
    public static function staffValues(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
