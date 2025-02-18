<?php


namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InventoryVoucherItemResource;
use App\Http\Resources\InventoryVoucherResource;
use App\Http\Resources\InvoiceBarcodeResource;
use App\Http\Resources\InvoiceItemResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceResource2;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RemittanceResource;
use App\Models\Address;
use App\Models\InventoryVoucher;
use App\Models\InventoryVoucherItem;
use App\Models\Invoice;
use App\Models\InvoiceAddress;
use App\Models\InvoiceBarcode;
use App\Models\InvoiceItem;
use App\Models\InvoiceProduct;
use App\Models\Order;
use App\Models\Part;
use App\Models\PartUnit;
use App\Models\PartyAddress;
use App\Models\Product;
use App\Models\Remittance;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function Laravel\Prompts\select;
use App\Models\Unit;
use function PHPUnit\Framework\returnSelf;

// error_reporting(E_ALL);
// ini_set('display_errors', '1');

class RemittanceController extends Controller
{
    public function __construct(Request $request)
    {
        $this->middleware(Token::class)->except('readOnly1', 'readOnly2');
    }

    public function index(Request $request)
    {
        try {
            $info = Remittance::orderByDesc('id');
            if (isset($request['orderID'])) {
                $info = $info->where('orderID', $request['orderID']);
            }
            if (isset($request['search'])) {
                $info = $info->where('barcode', 'like', '%' . $request['search'] . '%');
            }
            if (isset($request['count'])) {
                $count = $request['count'];
                $info = $info->take($count)->get();
                $info = RemittanceResource::collection($info);
            } else {
                $info = $info->paginate(100);
                $data = RemittanceResource::collection($info);
            }

            return response($info, 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function show(Remittance $remittance)
    {
        try {
            return response(new RemittanceResource($remittance), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function store(Request $request)
    {
        $str = str_replace(' ', '', str_replace('"', '', $request['OrderItems']));
        $orderItems = explode(',', $str);
        $myfile = fopen('../storage/logs/failed_data_entries/' . $request['OrderID'] . ".log", "w") or die("Unable to open file!");
        $txt = json_encode([
            'OrderID' => $request['OrderID'],
            'name' => $request['name'],
            'OrderItems' => $orderItems
        ]);
        fwrite($myfile, $txt);
        fclose($myfile);


        try {
            foreach ($orderItems as $item) {
                Remittance::create([
                    "orderID" => $request['OrderID'],
                    "addressName" => $request['name'],
                    "barcode" => $item,
                ]);
            }
            $remittances = Remittance::orderByDesc('id')->where('orderID', $request['OrderID'])->get();
            return response(RemittanceResource::collection($remittances), 201);
        } catch (\Exception $exception) {
            for ($i = 0; $i < 3; $i++) {
                try {
                    foreach ($orderItems as $item) {
                        Remittance::create([
                            "orderID" => $request['OrderID'],
                            "addressName" => $request['name'],
                            "barcode" => str_replace(' ', '', str_replace('"', '', $item)),
                        ]);
                    }
                    $remittances = Remittance::orderByDesc('id')->where('orderID', $request['OrderID'])->get();
                    if (count($remittances) == count($orderItems)) {
                        $i = 3;
                        return response(RemittanceResource::collection($remittances), 201);
                    }
                } catch (\Exception $exception) {
                    return response(['message' =>
                        'خطای پایگاه داده. لطفا کد '
                        . $request['OrderID'] .
                        ' را یادداشت کرده و جهت ثبت بارکد ها به پشتیبانی اطلاع دهید'], 500);
                }
            }
        }


    }

    public function update(Request $request, Remittance $remittance)
    {
        $validator = Validator::make($request->all('title'),
            [
//              'title' => 'required|unique:Remittances,title,' . $remittance['id'],
//                'title' => 'required',
            ],
            [
//                'title.required' => 'لطفا عنوان را وارد کنید',
//                'title.unique' => 'این عنوان قبلا ثبت شده است',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->messages(), 422);
        }
        try {
            $remittance->update($request->all());
            return response(new RemittanceResource($remittance), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function destroy(Remittance $remittance)
    {

        try {
            $remittance->delete();
            return response('Remittance deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function getInventoryVouchers()
    {
        $partIDs = Part::where('Name', 'like', '%نودالیت%')->whereNot('Name', 'like', '%لیوانی%')->pluck("PartID");
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

    public function getOrders()
    {
        $dat2 = Order::select("SLS3.Order.OrderID", "SLS3.Order.Number",
            "SLS3.Order.CreationDate", "Date as DeliveryDate", 'SLS3.Order.CustomerRef')
            ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
            ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
            ->where('SLS3.Order.Date', '>=', today()->subDays(7))
            ->where('SLS3.Order.InventoryRef', 1)
            ->where('SLS3.Order.State', 2)
            ->where('SLS3.Order.FiscalYearRef', 1403)
            ->where('SLS3.CustomerAddress.Type', 1)//2?
            ->whereHas('OrderItems')
            ->whereHas('OrderItems', function ($q) {
                $q->havingRaw('SUM(Quantity) >= ?', [50]);
            })
            ->orderBy('OrderID', 'DESC')
            ->get();

        $dat2 = OrderResource::collection($dat2);
        return $dat2;
    }

    public function readOnly2(Request $request)
    {
        $partIDs = Part::where('Name', 'like', '%نودالیت%')->whereNot('Name', 'like', '%لیوانی%')->pluck("PartID");
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

        $dat = DB::connection('sqlsrv')->table('LGS3.InventoryVoucher')->
        select([
            "LGS3.InventoryVoucher.InventoryVoucherID as OrderID", "LGS3.InventoryVoucher.Number as OrderNumber",
            "LGS3.Store.Name as AddressName", "GNR3.Address.Details as Address", "Phone",
            "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartEntityText"])
            ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
            ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
            ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(7))
            ->whereIn('LGS3.Store.StoreID', $storeIDs)
            ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 68)
            ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
            ->get()->toArray();
        $dat2 = DB::connection('sqlsrv')->table('LGS3.InventoryVoucher')->
        select([
            "LGS3.InventoryVoucher.InventoryVoucherID as OrderID", "LGS3.InventoryVoucher.Number as OrderNumber",
            "GNR3.Address.Name as AddressName", "GNR3.Address.Details as Address", "Phone",
            "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartEntityText", "CounterpartEntityRef"])
            ->join('GNR3.Party', 'GNR3.Party.PartyID', '=', 'LGS3.InventoryVoucher.CounterpartEntityRef')
            ->join('GNR3.PartyAddress', 'GNR3.PartyAddress.PartyRef', '=', 'GNR3.Party.PartyID')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'GNR3.PartyAddress.AddressRef')
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
            ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(7))
            ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 69)
            ->where('GNR3.PartyAddress.IsMainAddress', "1")
            ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
            ->get()->toArray();
        foreach ($dat as $item) {
            $item->{'type'} = 'InventoryVoucher';
            $item->{'ok'} = 1;
            $item->{'AddressName'} = $item->{'AddressName'} . ' ' . $item->{'OrderNumber'};
            $details = DB::connection('sqlsrv')->table('LGS3.InventoryVoucherItem')
                ->select(["LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity",
                    "LGS3.Part.PartID as Id", "LGS3.Part.Code as ProductNumber"])
                ->join('LGS3.Part', 'LGS3.Part.PartID', '=', 'LGS3.InventoryVoucherItem.PartRef')
                ->where('InventoryVoucherRef', $item->{'OrderID'})
                ->whereIn('PartRef', $partIDs)
                ->get()->toArray();
            $item->{'OrderItems'} = $details;

        }
        foreach ($dat2 as $item) {
            $item->{'type'} = 'InventoryVoucher';
            $item->{'ok'} = 1;
            $item->{'AddressName'} = $item->{'CounterpartEntityText'} . ' ' . $item->{'OrderNumber'};
            $details = DB::connection('sqlsrv')->table('LGS3.InventoryVoucherItem')
                ->select(["InventoryVoucherItemID", "LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity",
                    "LGS3.InventoryVoucherItem.PartRef", "LGS3.Part.PartID as Id", "LGS3.Part.Code as ProductNumber"])
                ->join('LGS3.Part', 'LGS3.Part.PartID', '=', 'LGS3.InventoryVoucherItem.PartRef')
                ->where('InventoryVoucherRef', $item->{'OrderID'})
                ->whereIn('PartRef', $partIDs)
                ->OrderBy('PartRef')
                ->get()->toArray();

            foreach ($details as $itemN) {
                $itemX = InventoryVoucherItem::where('InventoryVoucherItemID', $itemN->{'InventoryVoucherItemID'})->first();
                $q = $itemX->Quantity;
                $int = (int)$itemX->Quantity;
                if (str_contains($itemX->PartUnit->Name, 'پک')) {
                    $t = (int)PartUnit::where('PartID', $itemX->PartRef)->where('Name', 'like', '%کارتن%')->pluck('DSRatio')[0];
                    $q = (string)floor($int / $t);
                    $itemN->{'Quantity'} = $q;
                }
            }

            $detailsU = [];
            foreach ($details as $d) {
                $ref = array_filter($details, function ($b) use ($d) {
                    return $b->PartRef == $d->PartRef;
                });
                $q = array_sum(array_column($ref, 'Quantity'));

                $f = array_filter($detailsU, function ($e) use ($d) {
                    return $e->PartRef == $d->PartRef;
                });
                if (!$f) {
                    $d->Quantity = $q;
                    $detailsU[] = $d;
                }


            }
            $item->{'OrderItems'} = $detailsU;
        }

        $filtered = array_filter($dat, function ($el) {
            return count($el->{'OrderItems'}) > 0;
        });
        $filtered2 = array_filter($dat2, function ($el) {
            return count($el->{'OrderItems'}) > 0;
        });
        $input1 = array_values($filtered);
        $input2 = array_values($filtered2);
        $input = [];
        foreach ($input1 as $item) {
            $input[] = $item;
        }
        foreach ($input2 as $item) {
            $input[] = $item;
        }
        $offset = 0;
        $perPage = 100;
        if ($request['page'] && $request['page'] > 1) {
            $offset = ($request['page'] - 1) * $perPage;
        }
        $info = array_slice($input, $offset, $perPage);
        $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);
        return response()->json($paginator, 200);

    }

    public function readOnly1(Request $request)
    {
//        $t = PartUnit::where('PartID',"1746")->where('Name','like','%کارتن%')->get();
//        return $t;
//        $t = InventoryVoucherItem::where('InventoryVoucherRef',"203084")
//            ->where('PartRef','500')
//            ->with('Unit')
//            ->with('PartUnit')
//            ->get();
//        return InventoryVoucherItemResource::collection($t);
        $d3 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
            ->whereNot('Type', 'Order')
            ->orderByDesc('Type')
            ->orderByDesc('OrderID')
            ->paginate(100);
        $data = InvoiceResource::collection($d3);
        return response()->json($d3, 200);

        $partIDs = Part::where('Name', 'like', '%نودالیت%')->whereNot('Name', 'like', '%لیوانی%')->pluck("PartID");
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
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
            ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(7))
            ->whereIn('LGS3.Store.StoreID', $storeIDs)
            ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 68)
            ->whereHas('OrderItems', function ($q) use ($partIDs) {
                $q->whereIn('PartRef', $partIDs);
            })
            ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
            ->get();


        $d3 = Invoice::where('DeliveryDate', '>=', today()->subDays(7))
            ->where(function ($q) {
                $q->where('Type', 'InventoryVoucher')
                    ->orWhere('Type', 'Deputation');
            })
            ->orderByDesc('OrderID')
            ->orderByDesc('Type')
            ->paginate(100);
        $data = InvoiceResource::collection($d3);
        return response()->json($d3, 200);


        $dat = $this->getInventoryVouchers();
        $dat2 = $this->getOrders();
        $filtered = json_decode(json_encode($dat));
        $filtered2 = json_decode(json_encode($dat2));
        $input1 = array_values($filtered);
        $input2 = array_values($filtered2);
        $input = array_merge($input1, $input2);


        $offset = 0;
        $perPage = 100;
        if ($request['page'] && $request['page'] > 1) {
            $offset = ($request['page'] - 1) * $perPage;
        }
        $info = array_slice($input, $offset, $perPage);
        $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);

        return response()->json($paginator, 200);

    }

    public function readOnly(Request $request)
    {
        try {
//            $d3 = Invoice::where('DeliveryDate', '>=', today()->subDays(7))
//                ->orderByDesc('OrderID')
//                ->orderByDesc('Type')
//                ->paginate(50);
//            $data = InvoiceResource::collection($d3);
//            return response()->json($d3, 200);


            //Mainnnnnnnnn
            $partIDs = Part::where('Name', 'like', '%نودالیت%')->whereNot('Name', 'like', '%لیوانی%')->pluck("PartID");
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

            $dat = DB::connection('sqlsrv')->table('LGS3.InventoryVoucher')->
            select([
                "LGS3.InventoryVoucher.InventoryVoucherID as OrderID", "LGS3.InventoryVoucher.Number as OrderNumber",
                "LGS3.Store.Name as AddressName", "GNR3.Address.Details as Address", "Phone",
                "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartEntityText"])
                ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(7))
                ->whereIn('LGS3.Store.StoreID', $storeIDs)
                ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 68)
                ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
                ->get()->toArray();
            $dat2 = DB::connection('sqlsrv')->table('LGS3.InventoryVoucher')->
            select([
                "LGS3.InventoryVoucher.InventoryVoucherID as OrderID", "LGS3.InventoryVoucher.Number as OrderNumber",
                "GNR3.Address.Name as AddressName", "GNR3.Address.Details as Address", "Phone",
                "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartEntityText", "CounterpartEntityRef"])
                ->join('GNR3.Party', 'GNR3.Party.PartyID', '=', 'LGS3.InventoryVoucher.CounterpartEntityRef')
                ->join('GNR3.PartyAddress', 'GNR3.PartyAddress.PartyRef', '=', 'GNR3.Party.PartyID')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'GNR3.PartyAddress.AddressRef')
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(7))
                ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 69)
                ->where('GNR3.PartyAddress.IsMainAddress', "1")
                ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
                ->get()->toArray();
            foreach ($dat as $item) {
                $item->{'type'} = 'InventoryVoucher';
                $item->{'ok'} = 1;
                $item->{'AddressName'} = $item->{'AddressName'} . ' ' . $item->{'OrderNumber'};
                $details = DB::connection('sqlsrv')->table('LGS3.InventoryVoucherItem')
                    ->select(["LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity",
                        "LGS3.Part.PartID as Id", "LGS3.Part.Code as ProductNumber"])
                    ->join('LGS3.Part', 'LGS3.Part.PartID', '=', 'LGS3.InventoryVoucherItem.PartRef')
                    ->where('InventoryVoucherRef', $item->{'OrderID'})
                    ->whereIn('PartRef', $partIDs)
                    ->get()->toArray();
                $item->{'OrderItems'} = $details;

            }
            foreach ($dat2 as $item) {
                $item->{'type'} = 'InventoryVoucher';
                $item->{'ok'} = 1;
                $item->{'AddressName'} = $item->{'CounterpartEntityText'} . ' ' . $item->{'OrderNumber'};
                $details = DB::connection('sqlsrv')->table('LGS3.InventoryVoucherItem')
                    ->select(["InventoryVoucherItemID", "LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity",
                        "LGS3.InventoryVoucherItem.PartRef", "LGS3.Part.PartID as Id", "LGS3.Part.Code as ProductNumber"])
                    ->join('LGS3.Part', 'LGS3.Part.PartID', '=', 'LGS3.InventoryVoucherItem.PartRef')
                    ->where('InventoryVoucherRef', $item->{'OrderID'})
                    ->whereIn('PartRef', $partIDs)
                    ->OrderBy('PartRef')
                    ->get()->toArray();

                foreach ($details as $itemN) {
                    $itemX = InventoryVoucherItem::where('InventoryVoucherItemID', $itemN->{'InventoryVoucherItemID'})->first();
                    $q = $itemX->Quantity;
                    $int = (int)$itemX->Quantity;
                    if (str_contains($itemX->PartUnit->Name, 'پک')) {
                        $t = (int)PartUnit::where('PartID', $itemX->PartRef)->where('Name', 'like', '%کارتن%')->pluck('DSRatio')[0];
                        $q = (string)floor($int / $t);
                        $itemN->{'Quantity'} = $q;
                    }
                }

                $detailsU = [];
                foreach ($details as $d) {
                    $ref = array_filter($details, function ($b) use ($d) {
                        return $b->PartRef == $d->PartRef;
                    });
                    $q = array_sum(array_column($ref, 'Quantity'));

                    $f = array_filter($detailsU, function ($e) use ($d) {
                        return $e->PartRef == $d->PartRef;
                    });
                    if (!$f) {
                        $d->Quantity = (string)$q;
                        $detailsU[] = $d;
                    }


                }
                $item->{'OrderItems'} = $detailsU;
            }

            $filtered = array_filter($dat, function ($el) {
                return count($el->{'OrderItems'}) > 0;
            });
            $filtered2 = array_filter($dat2, function ($el) {
                return count($el->{'OrderItems'}) > 0;
            });
            $input1 = array_values($filtered);
            $input2 = array_values($filtered2);
            $input = [];
            foreach ($input1 as $item) {
                $input[] = $item;
            }
            foreach ($input2 as $item) {
                $input[] = $item;
            }
            $offset = 0;
            $perPage = 100;
            if ($request['page'] && $request['page'] > 1) {
                $offset = ($request['page'] - 1) * $perPage;
            }
            $info = array_slice($input, $offset, $perPage);
            $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);
            return response()->json($paginator, 200);

        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function getStores(Request $request)
    {

        try {
            $t = Store::select("LGS3.Store.StoreID", "LGS3.Store.Name as Name", "GNR3.Address.Details")
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->whereNot('LGS3.Store.Name', 'LIKE', "%مارکتینگ%")
                ->whereNot('LGS3.Store.Name', 'LIKE', "%گرمدره%")
                ->whereNot('GNR3.Address.Details', 'LIKE', "%گرمدره%")
                ->whereNot('LGS3.Store.Name', 'LIKE', "%ضایعات%")
                ->whereNot('LGS3.Store.Name', 'LIKE', "%برگشتی%");
            if (isset($request['search'])) {
                $t = $t->where('LGS3.Store.Name', 'LIKE', "%" . $request['search'] . "%")
                    ->orWhere('GNR3.Address.Details', 'LIKE', "%" . $request['search'] . "%");
            }
            $t = $t->get();
            return response()->json($t, 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function showProduct($id)
    {
        try {
            $dat = Part::select('PartID as ProductID', 'Name', 'PropertiesComment as Description', 'Code as Number')->where('Code', $id)->first();
            if (!$dat) {
                $dat = Product::select('ProductID', 'Name', 'Description', 'Number')->where('Number', $id)->first();
            }
            return response()->json($dat, 200);

        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function showProductTest($id)
    {
        try {
            $dat = InvoiceProduct::select('id', 'ProductName as Name', 'ProductNumber', 'Description')->where('ProductNumber', $id)->first();
            return response()->json($dat, 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function fix(Request $request)
    {
                $info = Invoice::where('Sum',0)->get();
        return $info;

//        $info = Invoice::where('Sum',0)->get();
        $orderIDs = Invoice::where('Type', 'Order')->orderBy('id')->pluck('OrderID');
        $d3 = $this->getOrders($orderIDs);

        foreach($info as $item){
            $dat = Order::select("SLS3.Order.OrderID", "SLS3.Order.Number",
                "SLS3.Order.CreationDate", "Date as DeliveryDate", 'SLS3.Order.CustomerRef',
                'GNR3.Address.AddressID','GNR3.RegionalDivision.Name as City')
                ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
                ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
                ->join('GNR3.RegionalDivision', 'GNR3.RegionalDivision.RegionalDivisionID','=','GNR3.Address.RegionalDivisionRef' )
//            ->where('SLS3.Order.Date', '>=', today()->subDays(2))
                ->whereNotIn('SLS3.Order.OrderID', $orderIDs)
                ->where('SLS3.Order.OrderID', $info['OrderID'])
                ->where('SLS3.Order.InventoryRef', 1)
                ->where('SLS3.Order.State', 2)
                ->where('SLS3.Order.FiscalYearRef', 1403)
                ->where('SLS3.CustomerAddress.Type', 2)
                ->whereHas('OrderItems')
                ->whereHas('OrderItems', function ($q) {
                    $q->havingRaw('SUM(Quantity) >= ?', [50]);
                })
                ->orderBy('OrderID')
                ->get();

            return $details;
            foreach($details as $item){
                if (!str_contains($item2->Product->Name,'لیوانی')){
                    $invoiceItem = InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'ProductNumber' => $item2->Product->Number,
                        'Quantity' => $item2->Quantity,
                    ]);
                }
            }
            $item->update(["Sum"=>$item->invoiceItems->sum('Quantity')]);
        }
        return InvoiceResource::collection($info);
        return $info;

        $tt = Invoice::orderByDesc('id')->get();
        foreach($tt as $item){
            $item->update(["Sum"=>$item->invoiceItems->sum('Quantity')]);
        }
        return 'Done';
//        if (isset($request['StartDate'])){
//            $s = (new DateController)->jalali_to_gregorian($request['StartDate']);
//            $e = (new DateController)->jalali_to_gregorian($request['EndDate']);
//
////            $data = $data->where('created_at', '>=', $s)
////                ->where('created_at', '<=', $e);
//
//            return [$s,$e];

//
//        $info = InvoiceBarcode::orderByDesc('id')->where('Barcode','like', '%'.$request['search'].'%')->get();
//
//        $info2 = Remittance::orderByDesc('id')->where('barcode', 'like', '%' . $request['search'] . '%')->get();
//
//        return response([$info,$info2],200);
//        $bars = InvoiceBarcode::where('id', '>', 200)->get();
//        foreach ($bars as $item) {
//            $item->delete();
//        }
//        DB::statement('ALTER TABLE invoice_barcodes AUTO_INCREMENT = 201;');
//        foreach ($t as $item) {
//            $bar = InvoiceBarcode::create([
//                "invoice_id" => 2175,
//                "Barcode" => $item,
//            ]);
//        }

//        $x = Invoice::where('id', 2175)->first();
//        return response(new InvoiceResource($x), 200);
//        $t = InvoiceItem::where('id','9773')->first();
//        $t->update([
//            'Quantity'=>'320'
//        ]);
//        $t2 = InvoiceItem::where('id','9774')->first();
//        $t2->update([
//            'Quantity'=>'320'
//        ]);
//        $x=Invoice::where('id','2163')->first();
//        $x->update(['Sum'=>700]);
//        $d3 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
//            ->whereNot('Type', 'Order')
//            ->orderByDesc('Type')
//            ->orderByDesc('OrderID')
//            ->paginate(100);
//        $dat1 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
//            ->orderByDesc('OrderID')
//            ->where('Type','InventoryVoucher')
//            ->get()->count();
//        $dat2 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
//            ->orderByDesc('OrderID')
//            ->where('Type','Deputation')
//            ->get()->count();
//        return ['InventoryVoucher'=> $dat1, 'Deputation'=> $dat2, ];
//        $datetime = new \DateTime( "now", new \DateTimeZone( "Asia/Tehran" ));
//
//        $nowHour  = $datetime->format( 'G');
//        if (((int)$nowHour < 8) || ((int)$nowHour > 19)){
//            return 0;
//        }
//
//            $d3 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
//                ->orderByDesc('OrderID')
//                ->orderByDesc('Type')
//                ->paginate(50);
////            $data = InvoiceResource::collection($d3);
//            return response()->json($d3, 200);


//        $dat1 = InvoiceAddress::orderBy('id')->get();
//        foreach ($dat1 as $item) {
//            if ($item['city'] == '') {
//                $dat2 = Address::select('GNR3.Address.AddressID', 'GNR3.Address.Name as AddressName', 'GNR3.RegionalDivision.Name as City')
//                    ->join('GNR3.RegionalDivision', 'GNR3.RegionalDivision.RegionalDivisionID', '=', 'GNR3.Address.RegionalDivisionRef')
//                    ->where('AddressID', $item['AddressID'])->first();
//                $item->update(['city' => $dat2['City']]);
//            }
//        }
//        $dat3 = Address::select('GNR3.Address.AddressID', 'GNR3.Address.Name as AddressName', 'GNR3.RegionalDivision.Name as City')
//            ->join('GNR3.RegionalDivision', 'GNR3.RegionalDivision.RegionalDivisionID', '=', 'GNR3.Address.RegionalDivisionRef')
//           ->paginate(100);
//        return $dat3;
    }

    public function query(Request $request)
    {
        switch ($request['type']) {
            case('انبار'):
            {
                $dat = InventoryVoucher::select("LGS3.InventoryVoucher.InventoryVoucherID", "LGS3.InventoryVoucher.Number",
                    "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartStoreRef", "AddressID")
                    ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                    ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                    ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                    ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 68)
                    ->where('LGS3.InventoryVoucher.Number', $request['orderNumber'])
                    ->first();

                return new InventoryVoucherResource($dat);

            }
            case('نمایندگی'):
            {
                $dat = InventoryVoucher::select("LGS3.InventoryVoucher.InventoryVoucherID",
                    "LGS3.InventoryVoucher.Number",
                    "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartEntityRef", "CounterpartEntityText",
                    "AddressID", 'GNR3.Address.Name as AddressName', 'GNR3.Address.Phone', 'Details')
                    ->join('GNR3.Party', 'GNR3.Party.PartyID', '=', 'LGS3.InventoryVoucher.CounterpartEntityRef')
                    ->join('GNR3.PartyAddress', 'GNR3.PartyAddress.PartyRef', '=', 'GNR3.Party.PartyID')
                    ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'GNR3.PartyAddress.AddressRef')
                    ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', 69)
                    ->where('LGS3.InventoryVoucher.Number', $request['orderNumber'])
                    ->first();

                return new InventoryVoucherResource($dat);
//               return $dat;


            }
            case('فروش'):
            {
                $dat = Order::select("SLS3.Order.OrderID", "SLS3.Order.Number",
                    "SLS3.Order.CreationDate", "Date as DeliveryDate", 'SLS3.Order.CustomerRef',
                    'GNR3.Address.AddressID')
                    ->where('SLS3.Order.Number', $request['orderNumber'])
                    ->first();

                return new OrderResource($dat);
            }
            default:
            {
                $m = ' لطفا نوع حواله را ارسال کنید.
              type:
              نمایندگی
              انبار
              فروش';
                return $m;

            }
        }

    }


    public function getInvoiceBarcodes(Request $request)
    {
        $info = InvoiceBarcode::orderByDesc('id');
        if (isset($request['search'])){
            $info = $info->where('Barcode','like', '%'.$request['search'].'%');
        }else{
            $info = $info->take(500);
        }
        $info = $info->get();
        return InvoiceBarcodeResource::collection($info);

    }
    public function getRemittances(Request $request)
    {
        $info = Remittance::orderByDesc('id');
//        if (isset($request['OrderNumber'])){
//            $info = $info->where('orderID', 'like', '%' . $request['search'] . '%');
//        }
        if (isset($request['search'])) {
            $info = $info->where('barcode', 'like', '%' . $request['search'] . '%');
        }else{
            $info = $info->take(500);
        }
        $info = $info->get();

        return RemittanceResource::collection($info);

    }
    public function report(Request $request)
    {
        try {
            $i1 = $this->getInvoiceBarcodes($request);
            $i2 = $this->getRemittances($request);

            $filtered = json_decode(json_encode($i1));
            $filtered2 = json_decode(json_encode($i2));
            $input1 = array_values($filtered);
            $input2 = array_values($filtered2);
            $input = array_merge($input1, $input2);


            $offset = 0;
            $perPage = 200;
            if ($request['page'] && $request['page'] > 1) {
                $offset = ($request['page'] - 1) * $perPage;
            }
            $info = array_slice($input, $offset, $perPage);
            $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);


            return response()->json($paginator, 200);


        } catch (\Exception $exception) {
            return response($exception);
        }
    }

}
