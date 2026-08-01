<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'gateway' => ['required', 'in:parsian,digipay,kamanlend,smartis,nopay'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'شناسه سفارش الزامی است',
            'order_id.exists' => 'سفارش یافت نشد',
            'gateway.required' => 'درگاه پرداخت الزامی است',
        ];
    }
}
