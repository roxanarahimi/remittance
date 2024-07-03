<?php

namespace App\Http\Controllers;


use App\Models\InventoryVoucher;
use App\Models\Invoice;
use App\Models\InvoiceAddress;
use App\Models\InvoiceItem;
use App\Models\InvoiceProduct;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CacheController extends Controller
{
    public function cacheProducts()
    {
//        InvoiceProduct::query()->truncate();
        $productnumbers = InvoiceProduct:://        where('CreationDate', '>=', today()->subDays(2))->
        pluck('ProductNumber');
        $products = Product::where('Name', 'like', '%نودالیت%')->whereNotIn('Number', $productnumbers)->get();
        foreach ($products as $item) {
            InvoiceProduct::create([
                'ProductName' => $item->Name,
                'ProductNumber' => $item->Number,
                'Description' => $item->Description
            ]);
        }
        $Codes = InvoiceProduct:://        where('CreationDate', '>=', today()->subDays(2))->
        pluck('ProductNumber');
        $parts = Part::where('Name', 'like', '%نودالیت%')->whereNotIn('Code', $Codes)->get();
        foreach ($parts as $item) {
            InvoiceProduct::create([
                'ProductName' => $item->Name,
                'ProductNumber' => $item->Code,
                'Description' => $item->Description
            ]);
        }
    }

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
//            ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(2))
            ->whereNotIn('LGS3.InventoryVoucher.InventoryVoucherID', $inventoryVoucherIDs)
            ->whereIn('LGS3.Store.StoreID', $storeIDs)
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
            ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 68)
            ->whereHas('OrderItems', function ($q) use ($partIDs) {
                $q->whereIn('PartRef', $partIDs);
            })
            ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
            ->get();

        return $dat;
    }
    public function getInventoryVouchersDeputation($inventoryVoucherIDs)
    {
        $partIDs = Part::where('Name', 'like', '%نودالیت%')->pluck("PartID");
        $dat = InventoryVoucher::select( [ "LGS3.InventoryVoucher.InventoryVoucherID as OrderID", "LGS3.InventoryVoucher.Number as OrderNumber",
            "GNR3.Address.Name as AddressName", "GNR3.Address.Details as Address", "Phone",
            "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate","CounterpartEntityText","CounterpartEntityRef"])
                ->join('GNR3.Party', 'GNR3.Party.PartyID', '=', 'LGS3.InventoryVoucher.CounterpartEntityRef')
        ->join('GNR3.PartyAddress', 'GNR3.PartyAddress.PartyRef', '=', 'GNR3.Party.PartyID')
        ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'GNR3.PartyAddress.AddressRef')

        //            ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(2))
            ->whereNotIn('LGS3.InventoryVoucher.InventoryVoucherID', $inventoryVoucherIDs)
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
            ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 69)
            ->whereHas('OrderItems', function ($q) use ($partIDs) {
                $q->whereIn('PartRef', $partIDs);
            })
            ->where('GNR3.PartyAddress.IsMainAddress', "1")
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
//            ->where('SLS3.Order.Date', '>=', today()->subDays(2))
            ->whereNotIn('SLS3.Order.OrderID', $orderIDs)
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
            Invoice::query()->truncate();

            $this->cacheProducts();
            $inventoryVoucherIDs = Invoice::
//            where('DeliveryDate', '>=', today()->subDays(2))->
            where('Type', 'InventoryVoucher')->orderByDesc('id')->pluck('OrderID');
            $deputationIds = Invoice::
//            where('DeliveryDate', '>=', today()->subDays(2))->
            where('Type', 'Deputation')->orderByDesc('id')->pluck('OrderID');
            $orderIDs = Invoice::
//            where('DeliveryDate', '>=', today()->subDays(2))->
            where('Type', 'Order')->orderByDesc('id')->pluck('OrderID');
            $d1 = $this->getInventoryVouchers($inventoryVoucherIDs);
            $d2 = $this->getInventoryVouchersDeputation($inventoryVoucherIDs);
            $d3 = $this->getOrders($orderIDs);

            foreach ($d1 as $item) {
                $invoice = Invoice::create([
                    'Type' => 'InventoryVoucher',
                    'OrderID' => $item->InventoryVoucherID,
                    'OrderNumber' => $item->Number,
                    'AddressID' => $item->Store->Plant->Address->AddressID,
                    'Sum' => $item->OrderItems->sum('Quantity'),
                    'DeliveryDate' => $item->DeliveryDate
                ]);
                $address = InvoiceAddress::where('AddressID', $item->Store->Plant->Address->AddressID)->first();
                if (!$address) {
                    InvoiceAddress::create([
                        'AddressID' => $item->Store->Plant->Address->AddressID,
                        'AddressName' => $item->Store->Name,
                        'Address' => $item->Store->Plant->Address->Details,
                        'Phone' => $item->Store->Plant->Address->Phone
                    ]);
                }
                foreach ($item->OrderItems as $item2) {
                    $invoiceItem = InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'ProductNumber' => $item2->Part->Code,
                        'Quantity' => $item2->Quantity,
                    ]);
                    $product = InvoiceProduct::where('ProductNumber', $item2->Part->Code)->first();
                    if (!$product) {
                        InvoiceProduct::create([
                            'ProductName' => $item2->Part->Name,
                            'ProductNumber' => $item2->Part->Code,
                            'Description' => $item2->Part->Description,
                        ]);
                    }
                }

            }
            foreach ($d2 as $item) {
                $invoice = Invoice::create([
                    'Type' => 'InventoryVoucher',
                    'OrderID' => $item->InventoryVoucherID,
                    'OrderNumber' => $item->Number,
                    'AddressID' => $item->Party->PartyAddress->Address->AddressID,
                    'Sum' => $item->OrderItems->sum('Quantity'),
                    'DeliveryDate' => $item->DeliveryDate
                ]);
                $address = InvoiceAddress::where('AddressID', $item->Party->PartyAddress->Address->AddressID)->first();
                if (!$address) {
                    InvoiceAddress::create([
                        'AddressID' => $item->Party->PartyAddress->Address->AddressID,
                        'AddressName' => $item->Party->PartyAddress->Address->Name,
                        'Address' => $item->Party->PartyAddress->Address->Details,
                        'Phone' => $item->Party->PartyAddress->Address->Phone
                    ]);
                }
                foreach ($item->OrderItems as $item2) {
                    $invoiceItem = InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'ProductNumber' => $item2->Part->Code,
                        'Quantity' => $item2->Quantity,
                    ]);
                    $product = InvoiceProduct::where('ProductNumber', $item2->Part->Code)->first();
                    if (!$product) {
                        InvoiceProduct::create([
                            'ProductName' => $item2->Part->Name,
                            'ProductNumber' => $item2->Part->Code,
                            'Description' => $item2->Part->Description,
                        ]);
                    }
                }

            }
            foreach ($d3 as $item) {
                $invoice = Invoice::create([
                    'Type' => 'Order',
                    'OrderID' => $item->OrderID,
                    'OrderNumber' => $item->Number,
                    'AddressID' => $item->Customer->CustomerAddress->Address->AddressID,
                    'Sum' => $item->OrderItems->sum('Quantity'),
                    'DeliveryDate' => $item->DeliveryDate
                ]);
                $address = InvoiceAddress::where('AddressID', $item->Customer->CustomerAddress->Address->AddressID)->first();
                if (!$address) {
                    InvoiceAddress::create([
                        'AddressID' => $item->Customer->CustomerAddress->Address->AddressID,
                        'AddressName' => $item->Customer->CustomerAddress->Address->Name,
                        'Address' => $item->Customer->CustomerAddress->Address->Details,
                        'Phone' => $item->Customer->CustomerAddress->Address->Phone
                    ]);
                }
                foreach ($item->OrderItems as $item2) {
                    $invoiceItem = InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'ProductNumber' => $item2->Product->Number,
                        'Quantity' => $item2->Quantity,
                    ]);
                    $product = InvoiceProduct::where('ProductNumber', $item2->Product->Number)->first();
                    if (!$product) {
                        InvoiceProduct::create([
                            'ProductName' => $item2->Product->Name,
                            'ProductNumber' => $item2->Product->Number,
                            'Description' => $item2->Product->Description
                        ]);
                    }
                }
            }
            echo now()->format('Y-m-d h:i:s') . ' - UTC: cache is ok
';
        } catch (\Exception $exception) {
            echo now()->format('Y-m-d h:i:s') . ' - UTC: ' . $exception->getMessage() . '
';
        }
//where('DeliveryDate', '>=', today()->subDays(2))

    }

}
