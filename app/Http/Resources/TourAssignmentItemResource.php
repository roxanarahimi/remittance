<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourAssignmentItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return TourResource
     */
    public function toArray(Request $request): TourResource
    {
        return new TourResource($this->Tour);
    }
}
