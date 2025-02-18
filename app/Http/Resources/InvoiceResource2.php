<?php

namespace App\Http\Resources;

use App\Http\Controllers\DateController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource2 extends JsonResource
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

        $state = 0; // not done
        if (count($barcodes) < $this->Sum) {
            $state = 0; // not done
        }elseif (count($barcodes) < $this->Sum) {
            $state = 0; // not done
        }elseif(count($barcodes) == $this->Sum) {
            $state = 1; // done
        } elseif (count($barcodes) > $this->Sum) {
            $state = 2; // over done
        }

        return [
            "id" => $this->id,
            "OrderID" => $this->OrderID,
            "OrderNumber" => $this->OrderNumber,
            "AddressName" => $this->address?->AddressName,
            "Address" => $this->address?->Address,
            'Sum' => $this->Sum,
            'Scanned' => count($barcodes),
            'Barcodes' => $barcodes,
            'State' => $state,

//            "DeliveryDate" => $this->DeliveryDate,
            'DeliveryDate' => explode(' ',(new DateController)->toPersian($this->DeliveryDate)),

//            "OrderItems" => InvoiceItemResource::collection($this->invoiceItems),
            'created_at' => explode(' ',(new DateController)->toPersian($this->created_at))[0].' '.explode(' ',(new DateController)->toPersian($this->created_at))[1]


        ];
    }
}
