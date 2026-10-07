<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageHotel((int) $this->input('hotel_id'));
    }

    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('rooms', 'name')->where('hotel_id', $this->input('hotel_id')),
            ],
        ];
    }
}
