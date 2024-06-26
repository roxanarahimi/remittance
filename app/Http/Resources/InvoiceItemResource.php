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
        return [
            "invoice_item_id" => (int)$this->id,
            "Id" => $this->ProductID,
            "Product" => $this->productPart,
            "Product2" => $this->productProduct,
            "ProductName" => $this->productProduct->ProductName || $this->productPart->ProductName,
            "ProductNumber" => $this->productProduct->ProductNumber || $this->productPart->ProductNumber,
            "Quantity" => (string)$this->Quantity,
            "Barcodes" => $this->barcodes
        ];

    }
}
