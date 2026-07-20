<?php

namespace App\Http\Requests;

use App\Rules\IranianNationalCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $count = $this->user()->addresses()->count();

        if ($count >= 10) {
            return false;
        }

        return true;
    }

    public function failedAuthorization(): void
    {
        throw new \Illuminate\Validation\ValidationException(
            validator([], [], ['addresses' => 'حداکثر ۱۰ آدرس قابل ذخیره است. از صفحه حساب کاربری آدرس قبلی را حذف کنید.']),
        );
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'province_id' => ['required', 'exists:provinces,id'],
            'city_id' => ['required', 'exists:cities,id'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'regex:/^\d{10}$/'],
            'address_line' => ['required', 'string', 'min:10', 'max:1000'],
            'plate' => ['required', 'string', 'max:20'],
            'unit' => ['nullable', 'string', 'max:20'],
            'receiver_name' => ['nullable', 'string', 'max:100'],
            'receiver_phone' => ['nullable', 'regex:/^09\d{9}$/'],
            'receiver_national_code' => ['nullable', 'string', 'max:10', new IranianNationalCode],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_default' => ['boolean'],
            'is_billing' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.max' => 'عنوان نمی‌تواند بیشتر از ۵۰ کاراکتر باشد',
            'province_id.required' => 'انتخاب استان الزامی است',
            'province_id.exists' => 'استان انتخاب شده معتبر نیست',
            'city_id.required' => 'انتخاب شهر الزامی است',
            'city_id.exists' => 'شهر انتخاب شده معتبر نیست',
            'postal_code.regex' => 'کد پستی باید ۱۰ رقم باشد',
            'address_line.required' => 'آدرس کامل الزامی است',
            'plate.required' => 'پلاک الزامی است',
            'address_line.min' => 'آدرس باید حداقل ۱۰ کاراکتر باشد',
            'receiver_phone.regex' => 'شماره تلفن معتبر نیست',
            'receiver_national_code' => 'کد ملی معتبر نیست',
        ];
    }
}
