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
        $barcodes = [];
        foreach ($this->barcodes as $item){
            $barcodes[] = $item->Barcode;
        }$testBarcodes = [];
        foreach ($this->testBarcodes as $item){
            $testBarcodes[] = $item->Barcode;
        }
        return [
            "id" => $this->id,
            "OrderID" => $this->OrderID,
            "OrderNumber" => $this->OrderNumber,
            "AddressName" => $this->address->AddressName . ' ' . $this->OrderNumber,
            "Address" => $this->address->Address,
            "Phone" => $this->address->Phone,
            "Type" => $this->Type,
            'Sum' => $this->Sum,
            'Barcodes' => $barcodes,
            'TestBarcodes' => $testBarcodes,
            "DeliveryDate" => $this->DeliveryDate,
            "OrderItems" => InvoiceItemResource::collection($this->invoiceItems),

        ];
    }
}
