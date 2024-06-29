<?php

namespace App\Http\Resources;

use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "OrderID" => $this->OrderID,
            "OrderNumber" => $this->OrderNumber,

            "AddressName" => $this->address->AddressName . ' ' . $this->OrderNumber,
            "Address" => $this->address->Address,
            "Phone" => $this->address->Phone,

            "Type" => $this->Type,
            'Sum' => $this->Sum,
            'Done' => $this->invoiceItems->sum(function ($invoiceItem) {
                return $invoiceItem->barcodes->count();
            }) >= $this->Sum ? 1 : 0,
            'TestDone' => $this->invoiceItems->sum(function ($invoiceItem) {
                return $invoiceItem->testBarcodes->count();
            }) >= $this->Sum ? 1 : 0,

            "CreationDate" => $this->DeliveryDate,//
            "DeliveryDate" => $this->DeliveryDate,
            "OrderItems" => InvoiceItemResource::collection($this->invoiceItems),
            "ok" => 1,//

        ];
    }
}
