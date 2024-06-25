<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceAddress;
use App\Models\InvoiceItem;
use App\Models\InvoiceProduct;
use Illuminate\Console\Command;

class CacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        try {
            $inventoryVoucherIDs = Invoice::
//        where('DeliveryDate', '>=', today()->subDays(7))->
            where('Type','InventoryVoucher')->orderByDesc('id')->pluck('OrderID');

            $orderIDs = Invoice::
//        where('DeliveryDate', '>=', today()->subDays(7))->
            where('Type','Order')->orderByDesc('id')->pluck('OrderID');
            $d1 = $this->getInventoryVouchers($inventoryVoucherIDs);
            $d2 = $this->getOrders($orderIDs);


            foreach($d1 as $item){
                $invoice = Invoice::create([
                    'Type'=>'InventoryVoucher',
                    'OrderID'=>$item->InventoryVoucherID,
                    'OrderNumber'=>$item->Number,
                    'AddressID'=>$item->Store->Plant->Address->AddressID,
                    'Sum'=>$item->OrderItems->sum('Quantity'),
                    'DeliveryDate'=>$item->DeliveryDate
                ]);
                $address = InvoiceAddress::where('AddressID',$item->Store->Plant->Address->AddressID)->first();
                if(!$address){
                    InvoiceAddress::create([
                        'AddressID'=>$item->Store->Plant->Address->AddressID,
                        'AddressName'=>$item->Store->Name,
                        'Address'=>$item->Store->Plant->Address->Details,
                        'Phone'=>$item->Store->Plant->Address->Phone
                    ]);
                }
                foreach($item->OrderItems as $item2){
                    InvoiceItem::create([
                        'invoice_id'=>$invoice->id,
                        'ProductID'=>$item2->Part->PartID,
                        'Quantity'=>$item2->Quantity,
                    ]);
                    $product = InvoiceProduct::where('ProductID',$item2->ProductRef)->where('Type','Part')->first();
                    if(!$product){
                        InvoiceProduct::create([
                            'Type'=> 'Part',
                            'ProductID'=>$item2->Part->PartID,
                            'ProductName'=>$item2->Part->Name,
                            'ProductNumber'=>$item2->Part->Code
                        ]);
                    }
                }

            }
            foreach($d2 as $item){
                $invoice = Invoice::create([
                    'Type'=>'Order',
                    'OrderID'=>$item->OrderID,
                    'OrderNumber'=>$item->Number,
                    'AddressID'=>$item->Customer->CustomerAddress->Address->AddressID,
                    'Sum'=>$item->OrderItems->sum('Quantity'),
                    'DeliveryDate'=>$item->DeliveryDate
                ]);
                $address = InvoiceAddress::where('AddressID',$item->Customer->CustomerAddress->Address->AddressID)->first();
                if(!$address){
                    InvoiceAddress::create([
                        'AddressID'=>$item->Customer->CustomerAddress->Address->AddressID,
                        'AddressName'=>$item->Customer->CustomerAddress->Address->Name,
                        'Address'=>$item->Customer->CustomerAddress->Address->Details,
                        'Phone'=>$item->Customer->CustomerAddress->Address->Phone
                    ]);
                }
                foreach($item->OrderItems as $item2){
                    InvoiceItem::create([
                        'invoice_id'=>$invoice->id,
                        'ProductID'=>$item2->Product->ProductID,
                        'Quantity'=>$item2->Quantity,
                    ]);
                    $product = InvoiceProduct::where('ProductID',$item2->ProductRef)->where('Type','Product')->first();
                    if(!$product){
                        InvoiceProduct::create([
                            'Type'=> 'Product',
                            'ProductID'=>$item2->Product->ProductID,
                            'ProductName'=>$item2->Product->Name,
                            'ProductNumber'=>$item2->Product->Number
                        ]);
                    }
                }
            }
//            $d3 = Invoice::orderByDesc('id')->orderByDesc('OrderID')->orderByDesc('Type')->paginate(100);
//            $data = InvoiceResource::collection($d3);
//            return response()->json($d3, 200);
            echo '
' . now()->format('Y-m-d h:i:s') . ' - UTC: cache is ok';
        }catch (\Exception $exception){
            echo '
' . now()->format('Y-m-d h:i:s') . ' - UTC: '.$exception->getMessage();
        }
//        if ($result) {
//            echo '
//' . now()->format('Y-m-d h:i:s') . ' - UTC: cache created successfully!
//';
//        } else {
//            echo '
//' . now()->format('Y-m-d h:i:s') . ' - UTC: cache creation failed!
//';
//        }
    }
}
