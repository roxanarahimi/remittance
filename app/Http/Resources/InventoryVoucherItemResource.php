<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryVoucherItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "Id" => $this->Part->PartID,
            "ProductName" => $this->Pard->Name,
            "ProductNumber" => $this->Pard->Code,
            "Quantity" => $this->Quantity,

        ];
    }
}