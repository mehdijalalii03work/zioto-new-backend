<?php

namespace App\Http\Requests;

use App\Rules\IranianNationalCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'national_id' => ['required', 'string', 'size:10', new IranianNationalCode],
            'employee_id' => ['required', 'string', 'max:50'],
            'gateway' => ['nullable', 'in:parsian,digipay,kamanlend,smartis'],
            'user_address_id' => ['nullable', 'integer', 'exists:user_addresses,id'],
            'shipping_address_snapshot' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام الزامی است',
            'phone.required' => 'شماره تلفن الزامی است',
            'national_id.required' => 'کد ملی الزامی است',
            'national_id.size' => 'کد ملی باید ۱۰ رقم باشد',
            'national_id' => 'کد ملی معتبر نیست',
            'employee_id.required' => 'کد پرسنلی الزامی است',
            'items.required' => 'حداقل یک محصول انتخاب کنید',
            'items.min' => 'حداقل یک محصول انتخاب کنید',
            'shipping_method_id.required' => 'روش ارسال الزامی است',
            'shipping_cost.required' => 'هزینه ارسال الزامی است',
        ];
    }
}
