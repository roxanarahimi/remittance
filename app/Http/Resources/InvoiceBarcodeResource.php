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
            "id" => (int)$this->id,
            "ProductID" => $this->invoiceItem->ProductID,
            "ProductName" => $this->invoiceItem->ProductName,
            "ProductNumber" => $this->invoiceItem->ProductNumber,
            "Quantity" => (string)$this->invoiceItem->Quantity,
            "Barcode" => $this->Barcode
        ];
    }
}
