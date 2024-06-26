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
            "ProductID" => $this->invoiceItem->productProduct?->ProductID. $this->invoiceItem->productPart?->ProductID,
            "ProductName" => $this->invoiceItem->productProduct?->ProductName. $this->invoiceItem->productPart?->ProductName,
            "ProductNumber" => $this->invoiceItem->productProduct?->ProductNumber. $this->invoiceItem->productPart?->ProductNumber,
            "Quantity" => (string)$this->invoiceItem->Quantity,
            "Barcode" => $this->Barcode
        ];
    }
}
