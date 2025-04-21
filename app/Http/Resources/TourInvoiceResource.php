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
            "OrderRef"=> $this->OrderRef,
            "OrderID"=> $this->Order->OrderID,
            "OrderNumber"=> $this->Order->OrderNumber,
//            "Order"=> $this->Order,
            "Customer"=> $this->Order->Customer,

        ];
    }
}
