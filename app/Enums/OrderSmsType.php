<?php

namespace App\Enums;

enum OrderSmsType: string
{
    case Confirmation = 'confirmation';

    case ReceivedHealth = 'received_health';
}
