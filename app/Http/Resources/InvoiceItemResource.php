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
        if($this->type == 'Part'){
            return [
                "Id" => $this->ProductID,
                "ProductName" => $this->productPart->ProductName,
                "ProductNumber" => $this->productPart->ProductNumber,
                "Quantity" => (string)$this->Quantity,
                "Barcodes" => $this->barcodes
            ];
        }elseif ($this->type == 'Product'){
            return [
                "Id" => $this->ProductID,
                "ProductName" => $this->productProduct->ProductName,
                "ProductNumber" => $this->productProduct->ProductNumber,
                "Quantity" => (string)$this->Quantity,
                "Barcodes" => $this->barcodes
            ];
        }

    }
}
