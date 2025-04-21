<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "InvoiceID"=> $this->InvoiceID,
            "OrderID"=> $this->Order->OrderID,
            "OrderNumber"=> $this->Order->Number,
//            "Order"=> $this->Order,
            "Customer"=> [
                "CustomerID"=> $this->Order->Customer->CustomerID,
                "Number"=> $this->Order->Customer->Number,
                "Address"=> $this->Order->Customer->CustomerAddress,
                "Party"=> $this->Order->Customer->Party,



            ],

        ];
    }
}
