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
            "invoice_item_id" => (int)$this->id,
            "Id" => $this->invoiceItem->ProductID,
            "ProductName" => $this->invoiceItem->ProductName,
            "ProductNumber" => $this->invoiceItem->ProductNumber,
            "Quantity" => (string)$this->invoiceItem->Quantity,
            "Barcode" => $this->Barcode
        ];
    }
}
