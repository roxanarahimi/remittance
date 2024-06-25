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
            "Id" => $this->ProductID,
            "ProductName" => $this->product->ProductName,
            "ProductNumber" => $this->product->ProductNumber,
            "Quantity" => (string)$this->Quantity,
            "Barcodes" => $this->barcodes
        ];
    }
}
