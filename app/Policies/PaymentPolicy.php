<?php

namespace App\Policies;

class PaymentPolicy extends AdminPolicy
{
    public static function entity(): string
    {
        return 'payment';
    }
}
