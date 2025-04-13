<?php

namespace App\Http\Resources;

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
"FiscalYearRef"=> $this->FiscalYearRef,
"CreationDate"=> $this->CreationDate,
"SentToHandheld"=> $this->SentToHandheld,
"Description"=> $this->Description,
            "items"=> $this->TourItems,
            "invoices"=> $this->Invoices,

        ];
    }
}
