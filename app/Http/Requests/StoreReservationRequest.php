<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'store_id' => [Rule::requiredIf($this->route('store') === null), 'integer', 'exists:stores,id'],
            'reservation_slot_id' => ['required', 'integer', 'exists:reservation_slots,id'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'customer_name' => ['required', 'string', 'max:50'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_request' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
