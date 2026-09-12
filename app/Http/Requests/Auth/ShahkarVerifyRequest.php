<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ShahkarVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'national_code' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/'],
            'birth_date' => ['required', 'date', 'before:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'توکن احراز هویت الزامی است',
            'national_code.required' => 'کد ملی الزامی است',
            'national_code.size' => 'کد ملی باید ۱۰ رقم باشد',
            'national_code.regex' => 'کد ملی باید شامل ۱۰ رقم باشد',
            'birth_date.required' => 'تاریخ تولد الزامی است',
            'birth_date.before' => 'تاریخ تولد نامعتبر است',
        ];
    }
}
