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
//        $state = 0;
//        if ($this->sum($this->barcodes->count()) == $this->Quantity){
//            $state = 1; // not done
//        }elseif ($this->sum($this->barcodes->count()) > $this->Quantity){
//            $state = 2;
//        }
        return [
            "id" => (int)$this->id,
            "product_id" => $this->product->id,
            "ProductName" => $this->product->ProductName,
            "ProductNumber" =>  $this->product?->ProductNumber,
            "Quantity" => (string)$this->Quantity,
            "Progress" => $this->sum($this->barcodes->count()) .'/'. $this->Quantity,
        ];

    }
}
