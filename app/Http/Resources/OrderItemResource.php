<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "Id" => $this->Product->ProductID,
            "ProductName" => $this->Product->Name,
            "ProductNumber" => $this->Product->Number,
            "Quantity" => $this->Quantity,
            ];
    }
}
