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
        $this->OkItems? $val = ['ok', 1]:  $val = ['ok', 0];

        return [
            "OrderID" => $this->InventoryVoucherID,
            "OrderNumber" => $this->Number,

            "AddressName" => $this->Store->Name,
            "Address" => $this->Store->Plant->Address->Details,
            "Phone" => $this->Store->Plant->Address->Phone,

            "CreationDate" => $this->CreationDate,
            "DeliveryDate" => $this->Date,
            "OrderItems" => InventoryVoucherItemResource::collection($this->OrderItems),
//            "OkItems" => $this->OkItems,
        ];
    }
}
