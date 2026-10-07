<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('room'));
    }

    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('rooms', 'name')->where('hotel_id', $room->hotel_id)->ignore($room->id),
            ],
        ];
    }
}
