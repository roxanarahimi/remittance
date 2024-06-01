<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryVoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "orderID" => $this->InventoryVoucherID,
            "Number" => $this->Number,
            "OrderItems" => $this->OrderItems,

        ];
    }
}
