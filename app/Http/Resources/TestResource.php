<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
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
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
        ];
    }
}
