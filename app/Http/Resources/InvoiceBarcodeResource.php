<?php

namespace App\Http\Resources;

use App\Http\Controllers\DateController;
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
            "id"=>$this->id,
            "invoice_id" => $this->invoice_id,//
            "OrderID" => $this->invoice?->OrderID,
            "OrderNumber" => $this->invoice?->OrderNumber,//
            "AddressName" => $this->invoice?->address->AddressName,
            "Barcode" => $this->Barcode,
            "created_at" => explode(' ',(new DateController)->toPersian($this->created_at))[0],

        ];
    }
}
