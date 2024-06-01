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
//        $ok = 0;
//        foreach ($this->OrderItems as $item) {
//            if (str_contains($item['ProductName'], 'نودالیت')) {
//                $ok += 1;
//            }
//        }
        return [
            "OrderID" => $this->InventoryVoucherID,
            "OrderNumber" => $this->Number,

            "AddressName" => $this->Store? $this->Store->Name: $this->StoreRef,
            "Address" => $this->Store?->Plant->Address->Details,
            "Phone" => $this->Store?->Plant->Address->Phone,

            "CreationDate" => $this->CreationDate,
            "DeliveryDate" => $this->Date,


//            "ok" => $ok,
            "OrderItems" => InventoryVoucherItemResource::collection($this->OrderItems),

        ];
    }
}
