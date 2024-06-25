<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            "Id" => $this->ProductID,
            "ProductName" => $this->product->ProductName,
            "ProductNumber" => $this->product->ProductNumber,
            "Quantity" => $this->Quantity,
            "Barcodes" => $this->barcodes
        ];
    }
}
