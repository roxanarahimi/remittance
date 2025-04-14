<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransporterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "TransporterID"=>$this->TransporterID,
            "Code"=>$this->Code,
            "DrivingLicenseNumber"=>$this->DrivingLicenseNumber,
            "Status"=>$this->Status,
            "FirstName"=>$this->FirstName,
            "LastName"=>$this->LastName,
            "FatherName"=>$this->FatherName,
            "RegistrationNo"=>$this->RegistrationNo,
            "NationalCode"=>$this->NationalCode,
            "TelNumber"=>$this->TelNumber,
            "Address"=>$this->Address,
            "PartyRef"=>$this->PartyRef,
               "Assignments"=>$this->Assignments,
               "Party"=>$this->Party,
      ];
    }
}
