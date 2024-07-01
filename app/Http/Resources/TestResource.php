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
            "OrderNumber" => $this->invoice?->OrderNumber,
            "OrderID" => $this->invoice?->OrderID,
            "created_at" => $this->created_at,
        ];
    }
}
