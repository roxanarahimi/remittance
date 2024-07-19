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
            "Id" => $this->Part? $this->Part->PartID : $this->Party->PartyID,
            "ProductName" => $this->Part? $this->Part->Name : $this->Party->Name,
            "ProductNumber" => $this->Part? $this->Part->Code : $this->Party->Code,
            "Quantity" => $this->Quantity,
        ];
    }
}
