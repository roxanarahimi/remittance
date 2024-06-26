<?php

namespace App\Http\Controllers;


use App\Models\InventoryVoucher;
use App\Models\Invoice;
use App\Models\InvoiceAddress;
use App\Models\InvoiceItem;
use App\Models\InvoiceProduct;
use App\Models\Order;
use App\Models\Part;
use Illuminate\Support\Facades\DB;

class CacheController extends Controller
{
    public function getInventoryVouchers($inventoryVoucherIDs)
    {
        $partIDs = Part::where('Name', 'like', '%نودالیت%')->pluck("PartID");
        $storeIDs = DB::connection('sqlsrv')->table('LGS3.Store')
            ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
            ->whereNot(function ($query) {
                $query->where('LGS3.Store.Name', 'LIKE', "%مارکتینگ%")
                    ->orWhere('LGS3.Store.Name', 'LIKE', "%گرمدره%")
                    ->orWhere('GNR3.Address.Details', 'LIKE', "%گرمدره%")
                    ->orWhere('LGS3.Store.Name', 'LIKE', "%ضایعات%")
                    ->orWhere('LGS3.Store.Name', 'LIKE', "%برگشتی%");
            })
            ->pluck('StoreID');
        $dat = InventoryVoucher::select("LGS3.InventoryVoucher.InventoryVoucherID", "LGS3.InventoryVoucher.Number",
            "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartStoreRef")
            ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
            ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
            ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(7))
            ->whereNotIn('LGS3.InventoryVoucher.InventoryVoucherID',$inventoryVoucherIDs)
            ->whereIn('LGS3.Store.StoreID', $storeIDs)
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
            ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68, 69])
            ->whereHas('OrderItems', function ($q) use ($partIDs) {
                $q->whereIn('PartRef', $partIDs);
            })
            ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
            ->get();

        return $dat;
    }

    public function getOrders($orderIDs)
    {
        $dat2 = Order::select("SLS3.Order.OrderID", "SLS3.Order.Number",
            "SLS3.Order.CreationDate", "Date as DeliveryDate", 'SLS3.Order.CustomerRef')
            ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
            ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
            ->where('SLS3.Order.Date', '>=', today()->subDays(7))
            ->whereNotIn('SLS3.Order.OrderID',$orderIDs)
            ->where('SLS3.Order.InventoryRef', 1)
            ->where('SLS3.Order.State', 2)
            ->where('SLS3.Order.FiscalYearRef', 1403)
            ->where('SLS3.CustomerAddress.Type', 2)
            ->whereHas('OrderItems')
            ->whereHas('OrderItems', function ($q) {
                $q->havingRaw('SUM(Quantity) >= ?', [50]);
            })
            ->orderBy('OrderID', 'DESC')
            ->get();

//        $dat2 = OrderResource::collection($dat2);
        return $dat2;
    }

    public function cacheInvoice()
    {
        try {
            $inventoryVoucherIDs = Invoice::
        where('DeliveryDate', '>=', today()->subDays(7))->
            where('Type','InventoryVoucher')->orderByDesc('id')->pluck('OrderID');

            $orderIDs = Invoice::
        where('DeliveryDate', '>=', today()->subDays(7))->
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
            echo now()->format('Y-m-d h:i:s') . ' - UTC: cache is ok
';
        }catch (\Exception $exception){
            echo  now()->format('Y-m-d h:i:s') . ' - UTC: '.$exception->getMessage().'
';
        }
//where('DeliveryDate', '>=', today()->subDays(7))

    }

}
