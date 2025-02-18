<?php

namespace App\Http\Resources;

use App\Http\Controllers\DateController;
use App\Models\Remittance;
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
        $r = Remittance::orderByDesc('id')->where('orderID', $this->OrderID)->get()->toArray();
        $cc = count($r);

        return [
            "id" => $this->id,
            "OrderID" => $this->OrderID,
            "OrderNumber" => $this->OrderNumber,
            "AddressName" => $this->address?->AddressName,
            "Address" => $this->address?->Address,
            'count' => $this->invoiceItems->sum('Quantity'),
            'Sum' => $this->Sum,
            'Scanned' => count($barcodes) + $cc,
            'Difference' => ($this->invoiceItems->sum('Quantity')) - (count($barcodes) + $cc),
            'created_at' => explode(' ', (new DateController)->toPersian($this->created_at))[0] . ' ' . explode(' ', (new DateController)->toPersian($this->created_at))[1],

            'Barcodes' => 'http://5.34.204.23/api/report?api_key=Rsxw_q25jhk92345/624087Mnhi.oxcv&OrderNumber='.$this->OrderNumber,
//            "DeliveryDate" => $this->DeliveryDate,


        ];
    }
}
