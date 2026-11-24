<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => 'sometimes|required|string|max:255',
            'address_line' => 'sometimes|required|string',
            'district'     => 'sometimes|required|string|max:255',
            'city'         => 'sometimes|required|string|max:255',
            'postal_code'  => 'nullable|string|max:20',
            'is_default'   => 'boolean',
        ];
    }
}