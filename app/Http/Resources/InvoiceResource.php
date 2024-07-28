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

//        $total = $this->invoiceItems->sum(function ($invoiceItem) {
//            return $invoiceItem->Quantity;
//        });
        $scanned = $this->invoiceItems->sum(function ($invoiceItem) {
            return $invoiceItem->barcodes->count();
        });
        $state = 0; // not done
        if ($scanned == $this->Sum) {
            $state = 1; // done
        } elseif ($scanned > $this->Sum) {
            $state = 2; // over done
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
            'Done' => $this->invoiceItems->sum(function ($invoiceItem) {
                    return $invoiceItem->barcodes->count();
                }) >= $this->Sum,
            'TestDone' => $this->invoiceItems->sum(function ($invoiceItem) {
                    return $invoiceItem->testBarcodes->count();
                }) >= $this->Sum,
            'TestBarcodes' => $testBarcodes,

            'Progress' => $this->invoiceItems->sum(function ($invoiceItem) {
                    return $invoiceItem->barcodes->count();
                }) . '/' .
                $this->invoiceItems->sum(function ($invoiceItem) {
                    return $invoiceItem->Quantity;
                }),
            'State' => $state,

            "DeliveryDate" => $this->DeliveryDate,
            "OrderItems" => InvoiceItemResource::collection($this->invoiceItems),

        ];
    }
}
