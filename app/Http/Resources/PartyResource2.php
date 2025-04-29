<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyResource2 extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "PartyID" => $this->PartyID,
            "Number" => $this->Number,
            "FullName" => $this->FullName,
            "Mobile"=> $this->Mobile,
            "NationalID"=> $this->NationalID,

            "Tours"=> TourAssignmentItemResource::collection($this->Transporter->Assignment->TourAssignmentItems)
        ];
    }
}
