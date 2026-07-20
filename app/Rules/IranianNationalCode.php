<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranianNationalCode implements ValidationRule
{
    private const ALL_EQUAL = [
        '0000000000', '1111111111', '2222222222', '3333333333',
        '4444444444', '5555555555', '6666666666', '7777777777',
        '8888888888', '9999999999',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $code = (string) $value;

        if (strlen($code) !== 10 || ! ctype_digit($code)) {
            $fail('کد ملی باید ۱۰ رقم باشد');

            return;
        }

        if (in_array($code, self::ALL_EQUAL, true)) {
            $fail('کد ملی معتبر نیست');

            return;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $checkDigit = (int) $code[9];

        $valid = ($remainder < 2 && $checkDigit === $remainder)
            || ($remainder >= 2 && (11 - $remainder) === $checkDigit);

        if (! $valid) {
            $fail('کد ملی معتبر نیست');
        }
    }
}
