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
        foreach ($this->barcodes as $item) {
            $barcodes[] = $item->Barcode;
        }
        $testBarcodes = [];
        foreach ($this->testBarcodes as $item) {
            $testBarcodes[] = $item->Barcode;
        }

        $state = 0; // not done
        if (count($barcodes) == $this->Sum) {
            $state = 1; // done
        } elseif (count($barcodes) > $this->Sum) {
            $state = 2; // over done
        }
        return [
            "id" => $this->id,
            "OrderID" => $this->OrderID,
            "OrderNumber" => $this->OrderNumber,
            "AddressName" => $this->address->AddressName,
            "Address" => $this->address->Address,
            "Phone" => $this->address->Phone,
            "Type" => $this->Type,
            'Sum' => $this->Sum,
            'Barcodes' => $barcodes,
            'TestBarcodes' => $testBarcodes,
//            'Done' => count($barcodes) >= $this->Sum,
//            'TestDone' => count($testBarcodes) >= $this->Sum,
            'Progress' => count($barcodes) . '/' . $this->Sum,
            'State' => $state,

            "DeliveryDate" => $this->DeliveryDate,
            "OrderItems" => InvoiceItemResource::collection($this->invoiceItems),

        ];
    }
}
