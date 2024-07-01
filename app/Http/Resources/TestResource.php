<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=>$this->id,
            "invoice_id" => $this->invoice_id,
            "Barcode" => $this->Barcode,
            "OrderID" => $this->invoice?->OrderID,
        ];
    }
}
