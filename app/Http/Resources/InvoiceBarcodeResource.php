<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceBarcodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
//            "id" => (int)$this->id,
            "Barcode" => $this->Barcode,
//            "Order"=>[
//                "Type" => $this->invoice->Type,
//                "OrderID" => $this->invoice->OrderID,
//                "OrderNumber" => $this->invoice->OrderNumber,
//                "AddressName" => $this->invoice->address->AddressName,
//                "Address" => $this->invoice->address->Address,
//            ]
        ];
    }
}
