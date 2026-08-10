<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'customer_request' => ['nullable', 'string', 'max:1000'],
            'user_coupon_id' => ['nullable', 'integer', 'exists:user_coupons,id'],
            'point_used' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.menu_id' => ['required', 'integer', 'distinct', 'exists:menus,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
