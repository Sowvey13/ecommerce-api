<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'address_line' => $this->address_line,
            'district'     => $this->district,
            'city'         => $this->city,
            'postal_code'  => $this->postal_code,
            'is_default'   => (bool) $this->is_default,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}