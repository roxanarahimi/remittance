<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use function Symfony\Component\String\s;

class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $barcodes = [];
        foreach($this->barcodes as $item){
            $barcodes[]=$item->Barcode;
        }
        $testBarcodes = [];
        foreach($this->testBarcodes as $item){
            $testBarcodes[]=$item->Barcode;
        }
        $type = $this->invoice->Type;
        return [
            "invoice_item_id" => (int)$this->id,
            "Id" => $this->ProductID,
            "ProductName" => $type == 'InventoryVoucher' ? $this->productPart?->ProductName : $this->productProduct?->ProductName,
            "ProductNumber" =>  $type == 'InventoryVoucher' ? $this->productPart?->ProductNumber : $this->productProduct?->ProductNumber,
            "Quantity" => (string)$this->Quantity,
//            "Done"=> count($barcodes) >= $this->Quantity ? 1 : 0,
            "Done"=> count($testBarcodes),
//            "Done"=> 1,
            "Barcodes" => $barcodes,
            "TestBarcodes" => $testBarcodes
        ];

    }
}
