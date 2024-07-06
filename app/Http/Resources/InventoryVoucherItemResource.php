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

        $q = $this->Quantity;
        if(str_contains($this->PartUnit->Name,'پک')){
            $q = $this->Quantity/8;
        }
        return [
            "Id" => $this->Part->PartID,
            "ProductName" => $this->Part->Name,
            "ProductNumber" => $this->Part->Code,
            "Quantity" => $q,
        ];
    }
}
