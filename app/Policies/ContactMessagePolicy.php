<?php

namespace App\Policies;

class ContactMessagePolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'contact-message';
    }
}
