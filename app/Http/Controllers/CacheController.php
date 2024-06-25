<?php

namespace App\Http\Controllers;

use App\Http\Resources\InventoryVoucherResource;
use App\Http\Resources\InvoiceProductResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\OrderResource;
use App\Models\InventoryVoucher;
use App\Models\Invoice;
use App\Models\InvoiceAddress;
use App\Models\InvoiceItem;
use App\Models\InvoiceProduct;
use App\Models\Order;
use App\Models\Part;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\In;

class CacheController extends Controller
{
    public function getInventoryVouchers($inventoryVoucherIDs): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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
            ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(2))
            ->whereNotIn('LGS3.InventoryVoucher.InventoryVoucherID',$inventoryVoucherIDs)
            ->whereIn('LGS3.Store.StoreID', $storeIDs)
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
            ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68, 69])
            ->whereHas('OrderItems', function ($q) use ($partIDs) {
                $q->whereIn('PartRef', $partIDs);
            })
            ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
            ->get();

        $dat = InventoryVoucherResource::collection($dat);
        return $dat;
    }

    public function getOrders($orderIDs): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $dat2 = Order::select("SLS3.Order.OrderID", "SLS3.Order.Number",
            "SLS3.Order.CreationDate", "Date as DeliveryDate", 'SLS3.Order.CustomerRef')
            ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
            ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
            ->where('SLS3.Order.Date', '>=', today()->subDays(2))
            ->whereNotIn('LGS3.InventoryVoucher.InventoryVoucherID',$orderIDs)
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

        $dat2 = OrderResource::collection($dat2);
        return $dat2;
    }

    public function cacheInvoice(Request $request): \Illuminate\Http\JsonResponse
    {
        $inventoryVoucherIDs = Invoice::where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(2))
            ->where('Type','InventoryVoucher')->orderByDesc('id')->pluck('OrderID');
        $orderIDs = Invoice::where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(2))
            ->where('Type','Order')->orderByDesc('id')->pluck('OrderID');
        return [$inventoryVoucherIDs,$orderIDs];
        $d1 = $this->getInventoryVouchers($inventoryVoucherIDs);
        $d2 = $this->getOrders($orderIDs);

        return [$d1,$d2];
        foreach($d1 as $item){
            Invoice::cteate([
                'Type'=>'InventoryVoucher',
                'OrderID'=>$item['OrderID'],
                'OrderNumber'=>$item['OrderNumber'],
                'AddressID'=>$item['AddressID'],
                'Sum'=>$item['Sum'],
                'DeliveryDate'=>$item['DeliveryDate']
            ]);
            $address = InvoiceAddress::where('AddressID',$item['AddressID'])->first();
            if(!$address){
                InvoiceAddress::create([
                    'AddressID'=>$item['AddressID'],
                    'AddressName'=>$item['AddressName'],
                    'Address'=>$item['Address'],
                    'Phone'=>$item['Phone']
                ]);
            }
            foreach($item['OrderItems'] as $item2){
                InvoiceItem::create([
                    'ProductID'=>$item2['ProductID'],
                    'Quantity'=>$item2['Quantity'],
                ]);
                $product = InvoiceProduct::where('ProductID',$item2['Id'])->where('Type','Part')->first();
                if(!$product){
                    InvoiceProduct::create([
                        'Type'=> 'Part',
                        'ProductID'=>$item2['ProductID'],
                        'ProductName'=>$item2['ProductName'],
                        'ProductNumber'=>$item2['ProductNumber']
                    ]);
                }
            }

        }
        foreach($d2 as $item){
            Invoice::cteate([
                'Type'=>'Order',
                'OrderID'=>$item['OrderID'],
                'OrderNumber'=>$item['OrderNumber'],
                'AddressID'=>$item['AddressID'],
                'Sum'=>$item['Sum'],
                'DeliveryDate'=>$item['DeliveryDate']
            ]);
            $address = InvoiceAddress::where('AddressID',$item['AddressID'])->first();
            if(!$address){
                InvoiceAddress::create([
                    'AddressID'=>$item['AddressID'],
                    'AddressName'=>$item['AddressName'],
                    'Address'=>$item['Address'],
                    'Phone'=>$item['Phone']
                ]);
            }
            foreach($item['OrderItems'] as $item2){
                InvoiceItem::create([
                    'ProductID'=>$item2['ProductID'],
                    'Quantity'=>$item2['Quantity'],
                ]);
                $product = InvoiceProduct::where('ProductID',$item2['Id'])->where('Type','Product')->first();
                if(!$product){
                    InvoiceProduct::create([
                        'Type'=> 'Product',
                        'ProductID'=>$item2['ProductID'],
                        'ProductName'=>$item2['ProductName'],
                        'ProductNumber'=>$item2['ProductNumber']
                    ]);
                }
            }

        }
        $d3 = Invoice::orderByDesc('id')->take(100)->get();
        return response()->json(InvoiceResource::collection($d3), 200);
    }

}
