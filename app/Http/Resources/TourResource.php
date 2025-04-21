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
//"FiscalYearRef"=> $this->FiscalYearRef,
            "CreationDate" => (new DateController)->toPersian2(date($this->CreationDate)),

//"SentToHandheld"=> $this->SentToHandheld,
//"Description"=> $this->Description,
//            "items"=> $this->TourItems,
            "invoices" => TourInvoiceResource::collection($this->Invoices),

        ];
    }
}
