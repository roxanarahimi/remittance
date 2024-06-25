<?php

namespace App\Http\Resources;

use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "OrderID" => $this->OrderID,
            "OrderNumber" => $this->OrderNumber,

            "AddressName" => $this->address->AdderssName . ' ' .$this->OrderNumber,
            "Address" => $this->address->Address,
            "Phone" => $this->address->Phone,

            "Type" => $this->Type,
            'Sum' => $this->Sum,

            "CreationDate" => $this->CreationDate,//
            "DeliveryDate" => $this->CreationDate,
            "OrderItems" => InvoiceItemResource::collection($this->OrderItems),
            "ok" => 1,//

        ];
    }
}
