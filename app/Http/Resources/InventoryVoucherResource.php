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
            "OrderID" => $this->InventoryVoucherID,
            "OrderNumber" => $this->Number,

            "AddressName" => $this->Store,
//            "Address" => $this->Store->Address->Details,
//            "Phone" => $this->Store->Address->Phone,

            "CreationDate" => $this->CreationDate,
            "DeliveryDate" => $this->Date,


            "OrderItems" => InventoryVoucherItemResource::collection($this->OrderItems),

        ];
    }
}
