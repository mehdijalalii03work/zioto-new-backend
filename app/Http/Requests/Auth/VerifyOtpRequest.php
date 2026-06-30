<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'code' => ['required', 'string', 'size:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'شماره موبایل الزامی است',
            'phone.regex' => 'شماره موبایل معتبر نیست',
            'code.required' => 'کد تایید الزامی است',
            'code.size' => 'کد تایید باید ۵ رقم باشد',
        ];
    }
}
