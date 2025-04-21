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
                "Address"=> $this->Order->Customer->CustomerAddress->Address,
//                "Party"=> $this->Order->Customer->Party,
                "FullName"=> $this->Order->Customer->Party->FullName,
                "NationalID"=> $this->Order->Customer->Party->NationalID,
                "Mobile"=> $this->Order->Customer->Party->Mobile,
                "Tel"=> $this->Order->Customer->Party->Tel,



            ],

        ];
    }
}
