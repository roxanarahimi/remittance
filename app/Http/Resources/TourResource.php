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
        return [
            "TourID" => $this->TourID,
            "Number" => $this->Number,
            "StartDate" => explode(' ', (new DateController)->toPersian($this->StartDate))[0] . ' ' . explode(' ', (new DateController)->toPersian($this->StartDate))[1],

            "EndDate" => explode(' ', (new DateController)->toPersian($this->EndDate))[0] . ' ' . explode(' ', (new DateController)->toPersian($this->EndDate))[1],
            "State" => $this->State,
//"FiscalYearRef"=> $this->FiscalYearRef,
            "CreationDate" => explode(' ', (new DateController)->toPersian($this->CreationDate))[0] . ' ' . explode(' ', (new DateController)->toPersian($this->CreationDate))[1],
//"SentToHandheld"=> $this->SentToHandheld,
//"Description"=> $this->Description,
//            "items"=> $this->TourItems,
            "invoices" => TourInvoiceResource::collection($this->Invoices),

        ];
    }
}
