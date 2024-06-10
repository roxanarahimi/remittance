<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            "OrderNumber" => $this->Number,

            "AddressName" => $this->Customer->CustomerAddress->Address->Name . substr($this->OrderID, -3),

            "Address" => $this->Customer->CustomerAddress->Address->Details,
            "Phone" => $this->Customer->CustomerAddress->Address->Phone,
            "Type" => "Order",

            'Sum' => $this->OrderItems->sum('Quantity'),

            "CreationDate" => $this->CreationDate,
            "DeliveryDate" => $this->CreationDate,
            "OrderItems" => OrderItemResource::collection($this->OrderItems),
        ];
    }
}
