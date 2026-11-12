<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')
                ->where('user_id', $this->user('api')->getAuthIdentifier())
                ->where('platform', $this->user('api')->platform)],
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
