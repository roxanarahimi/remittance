<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use function Symfony\Component\String\s;

class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->invoice->Type == 'InventoryVoucher') {
            $productName = $this->productPart->ProductName;
            $productNumber = $this->productPart->ProductNumber;
        } else {
            $productName = $this->productProduct->ProductName;
            $productNumber = $this->productProduct->ProductNumber;
        }
        return [
            "invoice_item_id" => (int)$this->id,
            "Id" => $this->ProductID,
            "ProductName" => $productName,
            "ProductNumber" => $productNumber,
            "Quantity" => (string)$this->Quantity,
            "Barcodes" => $this->barcodes
        ];

    }
}
