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
            "InventoryVoucherSpecificationRef" => $this->InventoryVoucherSpecificationRef,
            "CounterpartEntityRef" => $this->CounterpartEntityRef,

//            "AddressID" => $this->Store->Plant->Address->AddressID,
//            "AddressName" => $this->Store?->Name . $this->CounterpartEntityText . ' ' .$this->Number,
//            "Address" => $this->Store->Plant->Address->Details,
//            "Phone" => $this->Store->Plant->Address->Phone,
            "AddressID" => $this->Store->Plant?->Address->AddressID. $this->Store->Party?->PartyAddress->AddressID,
            "AddressName" => $this->Store?->Name . $this->CounterpartEntityText . ' ' .$this->Number,
            "Address" => $this->Store->Plant?->Address->Details. $this->Store->Party?->PartyAddress->Details,
            "Phone" => $this->Store->Plant?->Address->Phone. $this->Store->Party?->PartyAddress->Phone,

            "Type" => "InventoryVoucher",
            'Sum' => $this->OrderItems->sum('Quantity'),


            "CreationDate" => $this->CreationDate,
            "DeliveryDate" => $this->CreationDate,
            "OrderItems" => InventoryVoucherItemResource::collection($this->OrderItems),
            "ok" => 1,//

        ];
    }
}
