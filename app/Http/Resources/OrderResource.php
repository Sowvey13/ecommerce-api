<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'address_id'   => $this->address_id,
            'total_amount' => (float) $this->total_amount,
            'status'       => $this->status,
            'address'      => new AddressResource($this->whenLoaded('address')),
            'items'        => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id'           => $item->id,
                        'product_id'   => $item->product_id,
                        'product_name' => $item->product_name,
                        'unit_price'   => (float) $item->unit_price,
                        'quantity'     => (int) $item->quantity,
                        'line_total'   => round((float) $item->unit_price * (int) $item->quantity, 2),
                        'product'      => $item->relationLoaded('product') && $item->product
                            ? new ProductResource($item->product)
                            : null,
                    ];
                });
            }),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}