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
"TourID"=>$this->TourID,
"Number"=>$this->Number,
"StartDate"=> $this->StartDate,
"EndDate"=> $this->EndDate,
"State"=> $this->State,
//"FiscalYearRef"=> $this->FiscalYearRef,
//"CreationDate"=> $this->CreationDate,
//'CreationDate' => explode(' ',(new DateController)->toPersian($this->CreationDate))[0].' '.explode(' ',(new DateController)->toPersian($this->CreationDate))[1],

            "CreationDate"=>(new DateController)->toPersian(date($this->CreationDate)),

//"SentToHandheld"=> $this->SentToHandheld,
//"Description"=> $this->Description,
//            "items"=> $this->TourItems,
            "invoices"=> TourInvoiceResource::collection($this->Invoices) ,

        ];
    }
}
