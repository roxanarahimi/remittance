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
        $type = $this->invoiceItem->invoice->Type;

        return [
            "id" => (int)$this->id,
            "ProductID" => $this->invoiceItem->ProductID,
            "ProductName" => $type == 'Inventoryvoucher'? $this->invoiceItem->productPart?->ProductName : $this->invoiceItem->productProduct?->ProductName,
            "ProductNumber" => $type == 'Inventoryvoucher'? $this->invoiceItem->productPart?->ProductNumber : $this->invoiceItem->productProduct?->ProductNumber,
            "Quantity" => (string)$this->invoiceItem->Quantity,
            "Barcode" => $this->Barcode
        ];
    }
}
