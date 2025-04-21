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
        $this->EndDate!=null? $end= explode(' ', (new DateController)->toPersian($this->EndDate))[0]: $end='';
        return [
            "TourID" => $this->TourID,
            "Number" => $this->Number,
            "StartDate" => explode(' ', (new DateController)->toPersian($this->StartDate))[0],

            "EndDate" => $end,
            "State" => $this->State,
//"FiscalYearRef"=> $this->FiscalYearRef,
            "CreationDate" => explode(' ', (new DateController)->toPersian($this->CreationDate))[0],
//"SentToHandheld"=> $this->SentToHandheld,
//"Description"=> $this->Description,
//            "items"=> $this->TourItems,
            "invoices" => TourInvoiceResource::collection($this->Invoices),

        ];
    }
}
