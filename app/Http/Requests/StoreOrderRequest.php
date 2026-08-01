<?php

namespace App\Http\Requests;

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
            'gateway' => ['nullable', 'in:parsian,digipay,kamanlend,smartis,nopay'],
            'user_address_id' => ['nullable', 'integer', 'exists:user_addresses,id'],
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_method_id.required' => 'روش ارسال الزامی است',
            'shipping_method_id.exists' => 'روش ارسال نامعتبر است',
        ];
    }
}
