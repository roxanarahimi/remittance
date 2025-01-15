<?php

namespace App\Http\Resources;

use App\Http\Controllers\DateController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemittanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => (string)$this->id,
            "orderID" => $this->orderID,
            "addressName" => $this->addressName,
            "barcode" => $this->barcode,
            'date' => explode(' ',(new DateController)->toPersian($this->created_at))[0].' '.explode(' ',(new DateController)->toPersian($this->created_at))[1]


        ];
    }
}
