<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'user_address_id' => ['nullable', 'integer', 'exists:user_addresses,id'],
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'gateway' => ['required', 'in:parsian,digipay,kamanlend,smartis,nopay'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'حداقل یک محصول انتخاب کنید',
            'gateway.required' => 'درگاه پرداخت الزامی است',
            'gateway.in' => 'درگاه پرداخت معتبر نیست',
        ];
    }
}
