<?php

namespace App\Http\Resources;

use App\Http\Controllers\DateController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->EndDate ? $end = (new DateController)->toPersian2(date($this->EndDate)) : $end = '';
        return [
            "TourID" => $this->TourID,
            "Number" => $this->Number,
            "StartDate" => (new DateController)->toPersian2($this->StartDate),
            "EndDate" => $this->$end,
            "State" => $this->State,
            "Visitor" => [
                "PartyID" => $this->TourAssignmentItem->Assignment->Transporter->Party->PartyID,
                "Number" => $this->TourAssignmentItem->Assignment->Transporter->Party->Number,
                "FullName" => $this->TourAssignmentItem->Assignment->Transporter->Party->FullName,
                "Mobile"=> $this->TourAssignmentItem->Assignment->Transporter->Party->Mobile,
                "NationalID"=> $this->TourAssignmentItem->Assignment->Transporter->Party->NationalID,
            ],
            "Transporter"=>[
                "TransporterID"=> $this->TourAssignmentItem->Assignment->Transporter->TransporterID,
                "Code"=> $this->TourAssignmentItem->Assignment->Transporter->Code,
                "FullName" => $this->TourAssignmentItem->Assignment->Transporter->FirstName .' '. $this->TourAssignmentItem->Assignment->Transporter->LastName,
            ],
            "CreationDate" => (new DateController)->toPersian2(date($this->CreationDate)),
            "Invoices" => TourInvoiceResource::collection($this->Invoices),

        ];
    }
}
