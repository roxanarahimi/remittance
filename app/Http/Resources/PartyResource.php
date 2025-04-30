<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
//            "PartyID" => $this->PartyID,
            "Number" => $this->Number,
            "FullName" => $this->FullName,
            "Mobile" => $this->Mobile,

//            "TransporterID" => $this->Transporter->TransporterID,
//            "TransporterCode" => $this->Transporter->Code,
//            "TransporterName" => $this->Transporter->FirstName . ' ' . $this->Transporter->LastName,
            "TransporterName" => '',

            "Tours" => [],
        ];
    }
}
