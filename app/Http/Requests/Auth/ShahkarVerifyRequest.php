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
            'first_name' => ['required', 'string', 'min:2', 'max:50'],
            'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'national_code' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'توکن احراز هویت الزامی است',
            'first_name.required' => 'نام الزامی است',
            'first_name.min' => 'نام باید حداقل ۲ کاراکتر باشد',
            'last_name.required' => 'نام خانوادگی الزامی است',
            'last_name.min' => 'نام خانوادگی باید حداقل ۲ کاراکتر باشد',
            'national_code.required' => 'کد ملی الزامی است',
            'national_code.size' => 'کد ملی باید ۱۰ رقم باشد',
            'national_code.regex' => 'کد ملی باید شامل ۱۰ رقم باشد',
            'birth_date.before' => 'تاریخ تولد نامعتبر است',
        ];
    }
}
