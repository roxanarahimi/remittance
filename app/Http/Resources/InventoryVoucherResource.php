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

            "AddressID" => $this->Store->Plant->Address->AddressID,
            "AddressName" => $this->Store->Name . ' ' .$this->Number,
            "Address" => $this->Store->Plant->Address->Details,
            "Phone" => $this->Store->Plant->Address->Phone,

            "Type" => "InventoryVoucher",
            'Sum' => $this->OrderItems->sum('Quantity'),

            "CreationDate" => $this->CreationDate,
            "DeliveryDate" => $this->CreationDate,
            "OrderItems" => InventoryVoucherItemResource::collection($this->OrderItems),
            "ok" => 1,//

        ];
    }
}
