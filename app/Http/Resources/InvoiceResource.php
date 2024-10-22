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
        if (count($testBarcodes) < $this->Sum) {
            $state = 0; // not done
        }elseif (count($testBarcodes) < $this->Sum) {
            $state = 0; // not done
        }elseif(count($testBarcodes) == $this->Sum) {
            $state = 1; // done
        } elseif (count($testBarcodes) > $this->Sum) {
            $state = 2; // over done
        }
        if ($this->OrderNumber== "64659") {
            $state = 2; // not done
        }
        return [
            "id" => $this->id,
            "OrderID" => $this->OrderID,
            "OrderNumber" => $this->OrderNumber,
            "AddressName" => $this->address->AddressName,
            "Address" => $this->address->Address,
            "City" => $this->address->city,
            "Phone" => $this->address->Phone,
            "Type" => $this->Type,
            'Sum' => $this->Sum,
            'Barcodes' => $barcodes,
            'TestBarcodes' => $testBarcodes,
            'Progress' => count($barcodes) . '/' . $this->Sum,
            'ProgressTest' => count($testBarcodes) . '/' . $this->Sum,
//            'State' => 2,
            'State' => $state,

            "DeliveryDate" => $this->DeliveryDate,
            "OrderItems" => InvoiceItemResource::collection($this->invoiceItems),

        ];
    }
}
