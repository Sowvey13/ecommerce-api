<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => 'required|string|max:255',
            'address_line' => 'required|string',
            'district'     => 'required|string|max:255',
            'city'         => 'required|string|max:255',
            'postal_code'  => 'nullable|string|max:20',
            'is_default'   => 'boolean',
        ];
    }
}