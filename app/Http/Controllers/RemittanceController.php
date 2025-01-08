<?php


namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InventoryVoucherItemResource;
use App\Http\Resources\InventoryVoucherResource;
use App\Http\Resources\InvoiceItemResource;
use App\Http\Resources\InvoiceResource;
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
//        $t = [
//        "7011762071090000000346A1000800349429", "7011762071090000000346A1000800349441", "7011762071090000000346A1000800349548", "7011762071090000000346A1000800349518",
//        "7011762071090000000346A1000800349536", "7011762071090000000346A1000800349535", "7011762071090000000346A1000800349638", "7011762071090000000346A1000800349574", "7011762071090000000346A1000800349621", "7011762071090000000346A1000800349636", "7011762071090000000346A1000800349620", "7011762071090000000346A1000800349572", "7011762071090000000346A1000800349627",
//        "7011762071090000000346A1000800349639", "7011762071090000000346A1000800349645", "7011762071090000000346A1000800349531", "7011762071090000000346A1000800349549", "7011762071090000000346A1000800349637", "7011762440090000000026A1000000001308", "7011762440090000000026A1000000001274", "7011762440090000000026A1000000001328", "7011762440090000000026A1000000001312",
//        "7011762440090000000026A1000000001293", "7011762440090000000026A1000000001296", "7011762440090000000026A1000000001291", "7011762440090000000026A1000000001309", "7011762440090000000026A1000000001287", "7011762440090000000026A1000000001290", "7011762439090000000016B1000000001250", "7011762439090000000016B1000000001270", "7011762439090000000016B1000000001300",
//        "7011762439090000000016B1000000001236", "7011762440090000000026A1000000001301", "7011762440090000000026A1000000001295", "7011762440090000000026A1000000001343", "7011762440090000000026A1000000001322", "7011762440090000000026A1000000001318", "7011762440090000000026A1000000001297", "7011762440090000000026A1000000001313", "7011762440090000000026A1000000001294",
//        "7011762439090000000016B1000000001292", "7011762439090000000016B1000000001280", "7011762439090000000016B1000000001238", "7011762439090000000016B1000000001305", "7011762439090000000016B1000000001303", "7011762439090000000016B1000000001268", "7011762439090000000016B1000000001261", "7011762439090000000015A1000000000892", "7011762439090000000016B1000000001258",
//        "7011762439090000000016B1000000001272", "7011762439090000000016B1000000001283", "7011762439090000000016B1000000001289", "7011762438090000000027A1000000002593", "7011762438090000000027A1000000002592", "7011762438090000000027A1000000002573", "7011762438090000000027A1000000002588", "7011762438090000000027A1000000002611", "7011762438090000000027A1000000002589",
//        "7011762439090000000016B1000000001307", "7011762439090000000016B1000000001306", "7011762439090000000016B1000000001239", "7011762439090000000016B1000000001293", "7011762438090000000027A1000000002557", "7011762438090000000027A1000000002587", "7011762438090000000027A1000000002597", "7011762438090000000027A1000000002606", "7011762438090000000027A1000000002580",
//        "7011762440090000000026A1000000001289", "7011762440090000000026A1000000001311", "7011762438090000000027A1000000002598", "7011762438090000000027A1000000002599", "7011762438090000000027A1000000002614", "7011762438090000000027A1000000002568", "7011762438090000000027A1000000002607", "7011762438090000000027A1000000002594", "7011762438090000000027A1000000002586",
//        "7011762438090000000027A1000000002600", "7011762438090000000027A1000000002579", "7011762071090000000346A1000800349553", "7011762071090000000346A1000800349646", "7011762071090000000347A2000800350107", "7011762071090000000347A2000800350136", "7011762071090000000347A2000800350119", "7011762071090000000347A2000800350138", "7011762071090000000347A2000800350098",
//        "7011762071090000000347A2000800350117", "7011762071090000000347A2000800350141", "7011762071090000000347A2000800350140", "7011762071090000000347A2000800350103", "7011762071090000000347A2000800350076", "7011762071090000000347A2000800350070", "7011762071090000000347A2000800350110", "7011762071090000000347A2000800350106", "7011762071090000000347A2000800350100",
//        "7011762071090000000347A2000800350116", "7011762071090000000347A2000800350120", "7011762071090000000347A2000800350113", "7011762071090000000347A2000800350095", "7011762071090000000347A2000800350121", "7011762071090000000347A2000800350128", "7011762071090000000347A2000800350127", "7011762071090000000347A2000800350126", "7011762071090000000347A2000800350118",
//        "7011762071090000000347A2000800350125", "7011762071090000000347A2000800350145", "7011762071090000000347A2000800350146", "7011762071090000000347A2000800350099", "7011762071090000000347A2000800350087", "7011762071090000000347A2000800350123", "7011762071090000000347A2000800350079", "7011762071090000000347A2000800350114", "7011762071090000000347A2000800350094",
//        "7011762071090000000347A2000800350101", "7011762071090000000347A2000800350115", "7011762071090000000347A2000800350091", "7011762071090000000347A2000800350105", "7011762071090000000347A2000800350097", "7011762071090000000347A2000800350071", "7011762071090000000347A2000800350132", "7011762071090000000347A2000800350144", "7011762071090000000347A2000800350122",
//        "7011762071090000000347A2000800350096", "7011762071090000000347A2000800350131", "7011762071090000000347A2000800350133", "7011762071090000000347A2000800350147", "7011762071090000000347A2000800350142", "7011762071090000000347A2000800350135", "7011762071090000000347A2000800350143", "7011762071090000000347A2000800350129", "7011762071090000000347A2000800350130",
//        "7011762071090000000347A2000800350090", "7011762071090000000347A2000800350108", "7011762071090000000347A2000800350092", "7011762071090000000347A2000800350069", "7011762071090000000347A2000800350104", "7011762071090000000347A2000800350082", "7011762071090000000347A2000800350225", "7011762071090000000347A2000800350224", "7011762071090000000347A2000800350221",
//        "7011762071090000000347A2000800350226", "7011762071090000000347A2000800350217", "7011762071090000000347A2000800350210", "7011762071090000000347A2000800350192", "7011762071090000000347A2000800350218", "7011762071090000000347A2000800350222", "7011762071090000000347A2000800350220", "7011762071090000000347A2000800350182", "7011762071090000000347A2000800350214",
//        "7011762071090000000347A2000800350196", "7011762071090000000347A2000800350212", "7011762071090000000347A2000800350187", "7011762071090000000347A2000800350183", "7011762071090000000347A2000800350200", "7011762071090000000347A2000800350195", "7011762071090000000347A2000800350188", "7011762071090000000347A2000800350194", "7011762071090000000347A2000800350216",
//        "7011762071090000000347A2000800350204", "7011762071090000000347A2000800350201", "7011762071090000000347A2000800350078", "7011762071090000000347A2000800350086", "7011762071090000000347A2000800350229", "7011762071090000000347A2000800350083", "7011762071090000000347A2000800350084", "7011762071090000000347A2000800350067", "7011762071090000000347A2000800350080",
//        "7011762071090000000347A2000800350219", "7011762071090000000347A2000800350227", "7011762071090000000347A2000800350093", "7011762071090000000347A2000800350064", "7011762071090000000347A2000800350228", "7011762071090000000347A2000800350211", "7011762071090000000347A2000800350205", "7011762071090000000347A2000800350193", "7011762071090000000347A2000800350063",
//        "7011762071090000000347A2000800350089", "7011762071090000000347A2000800350072", "7011762071090000000347A2000800350075", "7011762071090000000347A2000800350112", "7011762071090000000347A2000800350111", "7011762071090000000346A1000800349514", "7011762071090000000346A1000800349436", "7011762071090000000346A1000800349651", "7011762071090000000346A1000800349655",
//        "7011762071090000000346A1000800349419", "7011762071090000000346A1000800349513", "7011762071090000000346A1000800349438", "7011762071090000000346A1000800349650", "7011762071090000000346A1000800349417", "7011762071090000000346A1000800349432", "7011762071090000000346A1000800349657", "7011762071090000000346A1000800349659", "7011762071090000000346A1000800349445",
//        "7011762071090000000346A1000800349440", "7011762071090000000346A1000800349434", "7011762071090000000346A1000800349430", "7011762071090000000346A1000800349443", "7011762071090000000346A1000800349424", "7011762071090000000346A1000800349426", "7011762071090000000346A1000800349444", "7011762071090000000346A1000800349402", "7011762071090000000346A1000800349447",
//        "7011762071090000000346A1000800349407", "7011762071090000000346A1000800349433", "7011762071090000000346A1000800349442", "7011762071090000000346A1000800349449", "7011762071090000000346A1000800349406", "7011762071090000000346A1000800349446", "7011762071090000000346A1000800349428", "7011762071090000000346A1000800349411", "7011762071090000000346A1000800349403",
//        "7011762071090000000346A1000800349401", "7011762071090000000346A1000800349400", "7011762071090000000346A1000800349455", "7011762071090000000346A1000800349468", "7011762071090000000346A1000800349412", "7011762071090000000346A1000800349448", "7011762071090000000346A1000800349456", "7011762071090000000346A1000800349459", "7011762071090000000346A1000800349454",
//        "7011762071090000000346A1000800349458", "7011762071090000000346A1000800349466", "7011762071090000000346A1000800349457", "7011762071090000000346A1000800349431", "7011762071090000000346A1000800349493", "7011762071090000000346A1000800349467", "7011762071090000000346A1000800349398", "7011762071090000000346A1000800349399", "7011762071090000000346A1000800349465",
//        "7011762071090000000346A1000800349464", "7011762071090000000346A1000800349491", "7011762071090000000346A1000800349477", "7011762071090000000346A1000800349648", "7011762071090000000346A1000800349452", "7011762071090000000346A1000800349404", "7011762071090000000346A1000800349451", "7011762071090000000346A1000800349396", "7011762071090000000346A1000800349490",
//        "7011762071090000000346A1000800349489", "7011762071090000000346A1000800349494", "7011762071090000000346A1000800349439", "7011762071090000000346A1000800349414", "7011762071090000000346A1000800349480", "7011762071090000000346A1000800349476", "7011762071090000000346A1000800349484", "7011762071090000000346A1000800349483", "7011762071090000000344A1000800346171",
//        "7011762071090000000346A1000800349478", "7011762071090000000346A1000800349470", "7011762071090000000344A1000800346170", "7011762071090000000346A1000800349471", "7011762071090000000344A1000800346162", "7011762071090000000346A1000800349475", "7011762071090000000346A1000800349479", "7011762071090000000344A1000800346192", "7011762071090000000344A1000800346172",
//        "7011762071090000000346A1000800349461", "7011762071090000000346A1000800349416", "7011762071090000000346A1000800349469", "7011762071090000000346A1000800349488", "7011762071090000000346A1000800349397", "7011762071090000000346A1000800349415", "7011762071090000000346A1000800349485", "7011762071090000000346A1000800349474", "7011762071090000000346A1000800349492",
//        "7011762071090000000346A1000800349463", "7011762071090000000346A1000800349460", "7011762071090000000344A1000800346193", "7011762071090000000344A1000800346186", "7011762071090000000346A1000800349462", "7011762071090000000346A1000800349482", "7011762071090000000346A1000800349472", "7011762071090000000345A2000800346991", "7011762071090000000344A1000800346181",
//        "7011762071090000000344A1000800346184", "7011762071090000000345A2000800347103", "7011762071090000000344A1000800346167", "7011762071090000000344A1000800346177", "7011762071090000000344A1000800346191", "7011762071090000000346A1000800349473", "7011762071090000000346A1000800349641", "7011762071090000000346A1000800349533", "7011762071090000000346A1000800349537",
//        "7011762071090000000346A1000800349551", "7011762071090000000346A1000800349534", "7011762071090000000346A1000800349422", "7011762071090000000346A1000800349532", "7011762071090000000346A1000800349542", "7011762071090000000346A1000800349543", "7011762071090000000346A1000800349642", "7011762071090000000346A1000800349624", "7011762071090000000346A1000800349630",
//        "7011762071090000000346A1000800349504", "7011762071090000000346A1000800349539", "7011762071090000000346A1000800349516", "7011762071090000000346A1000800349520", "7011762071090000000346A1000800349507", "7011762071090000000346A1000800349420", "7011762071090000000346A1000800349652", "7011762071090000000346A1000800349649", "7011762071090000000346A1000800349453",
//        "7011762071090000000346A1000800349423", "7011762071090000000346A1000800349654", "7011762071090000000346A1000800349545", "7011762071090000000346A1000800349421", "7011762071090000000346A1000800349658", "7011762071090000000346A1000800349538", "7011762071090000000346A1000800349546", "7011762071090000000346A1000800349540", "7011762071090000000346A1000800349541",
//        "7011762071090000000346A1000800349505", "7011762071090000000346A1000800349647", "7011762071090000000346A1000800349508", "7011762071090000000346A1000800349512", "7011762071090000000346A1000800349544", "7011762071090000000346A1000800349506", "7011762071090000000346A1000800349517", "7011762071090000000346A1000800349519", "7011762071090000000346A1000800349510",
//        "7011762071090000000346A1000800349653", "7011762071090000000346A1000800349425", "7011762071090000000346A1000800349435", "7011762071090000000346A1000800349656", "7011762071090000000346A1000800349418", "7011762071090000000346A1000800349509", "7011762071090000000346A1000800349511", "7011762071090000000346A1000800349515", "7011762071090000000346A1000800349547",
//        "7011762071090000000346A1000800349427", "7011762071090000000346A1000800349405", "7011762071090000000347A2000800350461", "7011762071090000000347A2000800350460", "7011762071090000000347A2000800350456", "7011762071090000000347A2000800350465", "7011762071090000000347A2000800350468", "7011762071090000000347A2000800350473", "7011762071090000000347A2000800350467",
//        "7011762071090000000347A2000800350477", "7011762071090000000347A2000800350466", "7011762071090000000347A2000800350469", "7011762071090000000347A2000800350485", "7011762071090000000347A2000800350479", "7011762071090000000343A2000800340482", "7011762071090000000343A2000800339995", "7011762071090000000347A2000800350346", "7011762071090000000347A2000800350363",
//        "7011762071090000000347A2000800350358", "7011762071090000000347A2000800350359", "7011762071090000000347A2000800350385", "7011762071090000000347A2000800350376", "7011762071090000000347A2000800350352", "7011762071090000000347A2000800350333", "7011762071090000000347A2000800350319", "7011762071090000000347A2000800350378", "7011762071090000000347A2000800350374",
//        "7011762071090000000347A2000800350355", "7011762071090000000347A2000800350326", "7011762071090000000347A2000800350349", "7011762071090000000347A2000800350321", "7011762071090000000347A2000800350332", "7011762071090000000347A2000800350482", "7011762071090000000347A2000800350472", "7011762071090000000347A2000800350330", "7011762071090000000347A2000800350342",
//        "7011762071090000000347A2000800350334", "7011762071090000000347A2000800350317", "7011762071090000000347A2000800350353", "7011762071090000000347A2000800350373", "7011762071090000000347A2000800350364", "7011762071090000000347A2000800350391", "7011762071090000000347A2000800350340", "7011762071090000000347A2000800350351", "7011762071090000000347A2000800350393",
//        "7011762071090000000347A2000800350341", "7011762071090000000347A2000800350480", "7011762071090000000347A2000800350483", "7011762071090000000347A2000800350470", "7011762071090000000347A2000800350325", "7011762071090000000347A2000800350387", "7011762071090000000347A2000800350368", "7011762081090000000380B1000800350773", "7011762081090000000380B1000800350361",
//        "7011762081090000000380B1000800350354", "7011762081090000000380B1000800350774", "7011762081090000000380B1000800350786", "7011762081090000000380B1000800350730", "7011762081090000000380B1000800350746", "7011762081090000000380B1000800350726", "7011762081090000000380B1000800350733", "7011762081090000000380B1000800350362", "7011762081090000000380B1000800350735",
//        "7011762081090000000380B1000800350356", "7011762081090000000380B1000800350740", "7011762081090000000380B1000800350742", "7011762081090000000380B1000800350736", "7011762081090000000380B1000800350787", "7011762081090000000380B1000800350731", "7011762081090000000380B1000800350728", "7011762081090000000380B1000800350797", "7011762081090000000380B1000800350766",
//        "7011762081090000000380B1000800350770", "7011762081090000000380B1000800350739", "7011762081090000000380B1000800350745", "7011762081090000000380B1000800350738", "7011762081090000000380B1000800350780", "7011762081090000000380B1000800350763", "7011762081090000000380B1000800350781", "7011762081090000000380B1000800350359", "7011762081090000000380B1000800350750",
//        "7011762081090000000380B1000800350729", "7011762081090000000380B1000800350355", "7011762081090000000380B1000800350756", "7011762081090000000380B1000800350743", "7011762081090000000380B1000800350744", "7011762081090000000380B1000800350358", "7011762081090000000380B1000800350727", "7011762081090000000380B1000800350360", "7011762081090000000380B1000800350767",
//        "7011762081090000000380B1000800350755", "7011762081090000000380B1000800350491", "7011762081090000000380B1000800350752", "7011762081090000000380B1000800350768", "7011762081090000000380B1000800350724", "7011762081090000000380B1000800350741", "7011762081090000000380B1000800350796", "7011762081090000000380B1000800350732", "7011762081090000000380B1000800350776",
//        "7011762081090000000380B1000800350795", "7011762081090000000380B1000800350794", "7011762081090000000380B1000800350309", "7011762081090000000380B1000800350777", "7011762081090000000380B1000800350792", "7011762081090000000380B1000800350654", "7011762081090000000380B1000800350308", "7011762081090000000380B1000800350327", "7011762081090000000380B1000800350317",
//        "7011762081090000000380B1000800350635", "7011762081090000000380B1000800350631", "7011762081090000000380B1000800350628", "7011762081090000000380B1000800350614", "7011762081090000000380B1000800350772", "7011762081090000000380B1000800350782", "7011762081090000000380B1000800350618", "7011762081090000000380B1000800350613", "7011762081090000000380B1000800350637",
//        "7011762081090000000380B1000800350606", "7011762081090000000380B1000800350611", "7011762081090000000380B1000800350623", "7011762081090000000380B1000800350649", "7011762081090000000380B1000800350605", "7011762081090000000380B1000800350630", "7011762081090000000380B1000800350620", "7011762081090000000380B1000800350615", "7011762081090000000380B1000800350608",
//        "7011762081090000000380B1000800350633", "7011762081090000000380B1000800350640", "7011762081090000000380B1000800350592", "7011762081090000000380B1000800350603", "7011762081090000000380B1000800350656", "7011762081090000000380B1000800350320", "7011762081090000000380B1000800350619", "7011762081090000000380B1000800350604", "7011762081090000000380B1000800350788",
//        "7011762081090000000380B1000800350793", "7011762081090000000380B1000800350607", "7011762081090000000380B1000800350612", "7011762081090000000380B1000800350641", "7011762081090000000380B1000800350626", "7011762081090000000380B1000800350642", "7011762081090000000380B1000800350632", "7011762081090000000380B1000800350629", "7011762081090000000380B1000800350617",
//        "7011762081090000000380B1000800350659", "7011762081090000000380B1000800350645", "7011762081090000000380B1000800350643", "7011762081090000000380B1000800350647", "7011762081090000000380B1000800350646", "7011762081090000000380B1000800350634", "7011762081090000000380B1000800350658", "7011762081090000000380B1000800350749", "7011762081090000000380B1000800350725",
//        "7011762081090000000380B1000800350747", "7011762081090000000380B1000800350748", "7011762081090000000380B1000800350734", "7011762081090000000380B1000800350753", "7011762081090000000380B1000800350765", "7011762081090000000380B1000800350737", "7011762081090000000380B1000800350762", "7011762081090000000380B1000800350760", "7011762081090000000380B1000800350757",
//        "7011762081090000000380B1000800350357", "7011762081090000000380B1000800350779", "7011762081090000000380B1000800350751", "7011762081090000000380B1000800350754", "7011762081090000000380B1000800350771", "7011762081090000000380B1000800350784", "7011762081090000000380B1000800350758", "7011762081090000000380B1000800350791", "7011762081090000000380B1000800350775",
//        "7011762081090000000380B1000800350798", "7011762081090000000380B1000800350624", "7011762081090000000380B1000800350625", "7011762081090000000380B1000800350764", "7011762081090000000380B1000800350801", "7011762081090000000380B1000800350785", "7011762081090000000380B1000800350783", "7011762081090000000380B1000800350306", "7011762081090000000380B1000800350664",
//        "7011762081090000000380B1000800350778", "7011762081090000000380B1000800350636", "7011762081090000000380B1000800350800", "7011762081090000000380B1000800350650", "7011762081090000000380B1000800350655", "7011762081090000000380B1000800350601", "7011762081090000000380B1000800350609", "7011762081090000000380B1000800350610", "7011762081090000000380B1000800350759",
//        "7011762081090000000380B1000800350769", "7011762081090000000380B1000800350326", "7011762081090000000380B1000800350319", "7011762081090000000380B1000800350648", "7011762081090000000380B1000800350657", "7011762081090000000380B1000800350651", "7011762081090000000380B1000800350652", "7011762081090000000380B1000800350639", "7011762081090000000380B1000800350638",
//        "7011762081090000000380B1000800350627", "7011762081090000000380B1000800350622", "7011762081090000000380B1000800350616", "7011762081090000000380B1000800350621", "7011762081090000000380B1000800350708", "7011762081090000000380B1000800350721", "7011762081090000000380B1000800350706", "7011762081090000000380B1000800350722", "7011762081090000000380B1000800350344",
//        "7011762081090000000380B1000800350711", "7011762081090000000380B1000800350351", "7011762081090000000380B1000800351156", "7011762081090000000380A1000800351309", "7011762081090000000380B1000800350338", "7011762081090000000380B1000800350345", "7011762081090000000380B1000800351161", "7011762081090000000380B1000800351168", "7011762081090000000380B1000800351169",
//        "7011762081090000000380B1000800351158", "7011762081090000000380B1000800350346", "7011762081090000000380B1000800350336", "7011762081090000000380B1000800351173", "7011762081090000000380B1000800351167", "7011762081090000000380A1000800351300", "7011762081090000000380B1000800351184", "7011762081090000000380A1000800351296", "7011762081090000000380B1000800351172",
//        "7011762081090000000380B1000800351166", "7011762081090000000380B1000800351125", "7011762081090000000380B1000800351160", "7011762081090000000380A1000800351299", "7011762081090000000380B1000800350699", "7011762081090000000380B1000800350341", "7011762081090000000380B1000800350707", "7011762081090000000380B1000800351126", "7011762081090000000380B1000800351163",
//        "7011762081090000000380B1000800350350", "7011762081090000000380B1000800350342", "7011762081090000000380B1000800351129", "7011762081090000000380B1000800351127", "7011762081090000000380A1000800351306", "7011762081090000000380B1000800351183", "7011762081090000000380B1000800351185", "7011762081090000000380B1000800351162", "7011762081090000000380B1000800351122",
//        "7011762081090000000380B1000800351174", "7011762081090000000380A1000800351311", "7011762081090000000380B1000800351179", "7011762081090000000380A1000800351267", "7011762081090000000380A1000800351285", "7011762081090000000380B1000800351081", "7011762081090000000380A1000800351307", "7011762081090000000380A1000800351777", "7011762081090000000380A1000800351751",
//        "7011762081090000000380A1000800351507", "7011762081090000000380A1000800351504", "7011762081090000000380A1000800351491", "7011762081090000000380A1000800351508", "7011762081090000000380A1000800351500", "7011762081090000000380A1000800351510", "7011762081090000000380A1000800351726", "7011762081090000000380A1000800351727", "7011762081090000000380A1000800351691",
//        "7011762081090000000380A1000800351715", "7011762081090000000380A1000800351718", "7011762081090000000380A1000800351722", "7011762081090000000380A1000800351693", "7011762081090000000380A1000800351706", "7011762081090000000380A1000800351717", "7011762081090000000380A1000800351685", "7011762081090000000380A1000800351762", "7011762081090000000380A1000800351729",
//        "7011762081090000000380A1000800351742", "7011762081090000000380A1000800351772", "7011762081090000000380A1000800351707", "7011762081090000000380A1000800351748", "7011762081090000000380A1000800351713", "7011762081090000000380A1000800351731", "7011762081090000000380A1000800351686", "7011762081090000000380A1000800351703", "7011762081090000000380A1000800351506",
//        "7011762081090000000380A1000800351478", "7011762081090000000380A1000800351714", "7011762081090000000380A1000800351702", "7011762081090000000380A1000800351724", "7011762081090000000380A1000800351704", "7011762081090000000380A1000800351728", "7011762081090000000380A1000800351730", "7011762081090000000380A1000800351725", "7011762081090000000380A1000800351705",
//        "7011762081090000000380A1000800351753", "7011762081090000000380A1000800351690", "7011762081090000000380A1000800351754", "7011762081090000000380A1000800351758", "7011762081090000000380A1000800351757", "7011762081090000000380A1000800351723", "7011762081090000000380A1000800351745", "7011762081090000000380A1000800351760", "7011762081090000000380A1000800351774",
//        "7011762081090000000380A1000800351766", "7011762081090000000380A1000800351746", "7011762081090000000380A1000800351696", "7011762081090000000380A1000800351453", "7011762081090000000380A1000800351435", "7011762081090000000380A1000800351664", "7011762081090000000380A1000800351662", "7011762081090000000380A1000800351667", "7011762081090000000380A1000800351678",
//        "7011762081090000000380A1000800351666", "7011762081090000000380A1000800351684", "7011762081090000000380A1000800351496", "7011762081090000000380A1000800351477", "7011762081090000000380A1000800351469", "7011762081090000000380A1000800351499", "7011762081090000000380A1000800351471", "7011762081090000000380A1000800351452", "7011762081090000000380A1000800351449",
//        "7011762081090000000380A1000800351450", "7011762081090000000380A1000800351465", "7011762081090000000380A1000800351464", "7011762081090000000380A1000800351442", "7011762081090000000380A1000800351466", "7011762081090000000380A1000800351493", "7011762081090000000380A1000800351497", "7011762081090000000380A1000800351492", "7011762081090000000380A1000800351460",
//        "7011762081090000000380A1000800351494", "7011762081090000000380A1000800351488", "7011762081090000000380A1000800351458", "7011762081090000000380A1000800351467", "7011762081090000000380A1000800351419", "7011762081090000000380A1000800351432", "7011762081090000000380A1000800351487", "7011762081090000000380A1000800351461", "7011762081090000000380A1000800351484",
//        "7011762081090000000380A1000800351483", "7011762081090000000380A1000800351441", "7011762081090000000380A1000800351454", "7011762081090000000380A1000800351445", "7011762081090000000380A1000800351456", "7011762081090000000380A1000800351457", "7011762081090000000380A1000800351485", "7011762081090000000380A1000800351463", "7011762081090000000380A1000800351448",
//        "7011762081090000000380A1000800351439", "7011762081090000000380A1000800351688", "7011762081090000000380A1000800351424", "7011762081090000000380A1000800351692", "7011762081090000000380A1000800351489", "7011762081090000000380A1000800351699", "7011762081090000000380A1000800351502", "7011762081090000000380B1000800350719", "7011762081090000000391A2000800371792",
//        "7011762081090000000391A2000800370983", "7011762081090000000391A2000800370962", "7011762081090000000391A2000800370955", "7011762081090000000391A2000800370948", "7011762081090000000391A2000800370957", "7011762081090000000391A2000800370972", "7011762081090000000391A2000800370968", "7011762081090000000391A2000800371821", "7011762081090000000391A2000800371818",
//        "7011762081090000000391A2000800371810", "7011762081090000000391A2000800370950", "7011762081090000000391A2000800371803", "7011762081090000000391A2000800371801", "7011762081090000000391A2000800370947", "7011762081090000000391A2000800370961", "7011762081090000000391A2000800370965", "7011762081090000000391A2000800370954", "7011762081090000000391A2000800370973",
//        "7011762081090000000391A2000800370958", "7011762081090000000381B1000800354794", "7011762081090000000380A1000800351474"
//    ];
//        foreach ($t as $item) {
//            $bar =  InvoiceBarcode::create([
//                "invoice_id" => 2175,
//                "Barcode" => $item,
//            ]);
//        }
        $bars = InvoiceBarcode::where('id', '>', 200)->get();
        foreach ($bars as $item) {
            $item->delete();
        }
        DB::statement('ALTER TABLE invoice_barcodes AUTO_INCREMENT = 201;');
        $t = [
            "701030135101800000883B21004000512996",
            "701030135101800000883B11004000512339",
            "701030135101800000883B21004000512973",
            "701030135101800000883B21004000512985",
            "701030135101800000883B21004000513002",
            "701030135101800000883B21004000513000",
            "701030135101800000883B21004000512997",
            "701030135101800000883B11004000512346",
            "701030135101800000883B11004000512388",
            "701030135101800000883B11004000512366",
            "701030135101800000883B11004000512352",
            "701030135101800000883B11004000512356",
            "701030135101800000883B11004000512375",
            "701030135101800000883B11004000512360",
            "701030135101800000883B11004000512390",
            "701030135101800000883B11004000512400",
            "701030135101800000883B11004000512371",
            "701030135101800000883B11004000512351",
            "701030135101800000883B11004000512383",
            "701030135101800000883B11004000512373",
            "701030135101800000883B11004000512410",
            "701030135101800000883B11004000512404",
            "701030135101800000883B11004000512379",
            "701030135101800000883B11004000512415",
            "701030135101800000883B11004000512423",
            "701030135101800000883B11004000512416",
            "701030135101800000883B11004000512367",
            "701030135101800000883B11004000512401",
            "701030135101800000883B11004000512389",
            "701030135101800000883B11004000512357",
            "701030135101800000883B11004000512347",
            "701030135101800000883B11004000512344",
            "701030135101800000883B11004000512348",
            "701030135101800000883B11004000512378",
            "701030135101800000883B11004000512422",
            "701030135101800000883B11004000512406",
            "701030135101800000883B11004000512384",
            "701030135101800000883B11004000512403",
            "701030135101800000883B11004000512343",
            "701030135101800000883B11004000512349",
            "701030135101800000883B11004000512385",
            "701030135101800000883B11004000512399",
            "701030135101800000883B11004000512382",
            "701030135101800000883B11004000512407",
            "701030135101800000883B11004000512391",
            "701030135101800000883B11004000512412",
            "701030135101800000883B11004000512421",
            "701030135101800000883B11004000512418",
            "701030135101800000883B11004000512419",
            "701030135101800000883B11004000512396",
            "701030135101800000884B12004000513173",
            "701030135101800000884B12004000513177",
            "701030135101800000884B12004000513166",
            "701030135101800000884B12004000513185",
            "701030135101800000884B12004000513167",
            "701030135101800000884B12004000513178",
            "701030135101800000884B12004000513170",
            "701030135101800000884B12004000513160",
            "701030135101800000884B12004000513169",
            "701030135101800000884B12004000513174",
            "701030135101800000884B12004000513164",
            "701030135101800000884B12004000513161",
            "701030135101800000884B12004000513165",
            "701030135101800000884B12004000513201",
            "701030135101800000883B11004000512524",
            "701030135101800000883B11004000512527",
            "701030135101800000883B11004000512511",
            "701030135101800000883B11004000512518",
            "701030135101800000883B11004000512520",
            "701030135101800000883B11004000512536",
            "701030135101800000883B11004000512546",
            "701030135101800000883B11004000512541",
            "701030135101800000883B11004000512542",
            "701030135101800000883B11004000512532",
            "701030135101800000883B11004000512538",
            "701030135101800000883B11004000512534",
            "701030135101800000884B12004000513196",
            "701030135101800000884B12004000513203",
            "701030135101800000884B12004000513152",
            "701030135101800000884B12004000513159",
            "701030135101800000884B12004000513195",
            "701030135101800000884B12004000513188",
            "701030135101800000883B11004000512526",
            "701030135101800000883B11004000512531",
            "701030135101800000884B12004000513175",
            "701030135101800000884B12004000513176",
            "701030135101800000883B11004000512513",
            "701030135101800000883B11004000512509",
            "701030135101800000883B11004000512529",
            "701030135101800000883B11004000512517",
            "701030135101800000883B11004000512519",
            "701030135101800000883B11004000512528",
            "701030135101800000883B11004000512535",
            "701030135101800000883B11004000512553",
            "701030135101800000883B11004000512565",
            "701030135101800000883B11004000512550",
            "701030135101800000883B11004000512554",
            "701030135101800000883B11004000512555",
            "701030135101800000883B11004000512566",
            "701030135101800000883B11004000512545",
            "701030135101800000884B12004000513276",
            "701030135101800000884B12004000513242",
            "701030135101800000884B12004000513262",
            "701030135101800000884B12004000513249",
            "701030135101800000884B12004000513244",
            "701030135101800000884B12004000513259",
            "701030135101800000884B12004000513272",
            "701030135101800000884B12004000513280",
            "701030135101800000884B12004000513267",
            "701030135101800000884B12004000513284",
            "701030135101800000884B12004000513133",
            "701030135101800000884B12004000513286",
            "701030135101800000884B12004000513263",
            "701030135101800000884B12004000513268",
            "701030135101800000884B12004000513277",
            "701030135101800000884B12004000513278",
            "701030135101800000884B12004000513288",
            "701030135101800000884B12004000513274",
            "701030135101800000884B12004000513283",
            "701030135101800000884B12004000513285",
            "701030135101800000884B12004000513131",
            "701030135101800000884B12004000513128",
            "701030135101800000884B12004000513140",
            "701030135101800000884B12004000513134",
            "701030135101800000884B12004000513139",
            "701030135101800000884B12004000513125",
            "701030135101800000884B12004000513137",
            "701030135101800000884B12004000513182",
            "701030135101800000884B12004000513124",
            "701030135101800000884B12004000513147",
            "701030135101800000884B12004000513141",
            "701030135101800000884B12004000513123",
            "701030135101800000884B12004000513149",
            "701030135101800000884B12004000513142",
            "701030135101800000884B12004000513122",
            "701030135101800000884B12004000513154",
            "701030135101800000884B12004000513127",
            "701030135101800000884B12004000513126",
            "701030135101800000884B12004000513158",
            "701030135101800000884B12004000513163",
            "701030135101800000884B12004000513136",
            "701030135101800000884B12004000513121",
            "701030135101800000884B12004000513271",
            "701030135101800000884B12004000513138",
            "701030135101800000884B12004000513156",
            "701030135101800000884B12004000513129",
            "701030135101800000884B12004000513135",
            "701030135101800000884B12004000513151",
            "701030135101800000884B12004000513130",
            "701030135101800000884B12004000513132",
            "701030435101800000611A32004000281314",
            "701030435101800000611A32004000281319",
            "701030435101800000611A32004000281298",
            "701030435101800000611A32004000281335",
            "701030435101800000611A32004000281309",
            "701030435101800000611A32004000281318",
            "701030435101800000611A32004000281303",
            "701030435101800000611A32004000281302",
            "701030435101800000611A32004000281340",
            "701030435101800000611A32004000281310",
            "701030435101800000611A32004000281312",
            "701030435101800000611A32004000281339",
            "701030435101800000611A32004000281594",
            "701030435101800000611A32004000281308",
            "701030435101800000611A32004000281300",
            "701030435101800000611A32004000281316",
            "701030435101800000611A32004000281353",
            "701030435101800000611A32004000281354",
            "701030435101800000611A32004000281611",
            "701030435101800000611A32004000281590",
            "701030435101800000611A32004000281327",
            "701030435101800000611A32004000281360",
            "701030435101800000611A32004000281336",
            "701030435101800000611A32004000281299",
            "701030435101800000611A32004000281359",
            "701030435101800000611A32004000281387",
            "701030435101800000611A32004000281338",
            "701030435101800000611A32004000281305",
            "701030435101800000611A32004000281329",
            "701030435101800000611A32004000281330",
            "701030435101800000611A32004000281337",
            "701030435101800000611A32004000281334",
            "701030435101800000611A32004000281297",
            "701030435101800000611A32004000281295",
            "701030435101800000611A32004000281345",
            "701030435101800000611A32004000281296",
            "701030435101800000611A32004000281328",
            "701030435101800000611A32004000281306",
            "701030435101800000611A32004000281363",
            "701030435101800000611A32004000281301",
            "701030435101800000611A32004000281324",
            "701030435101800000611A32004000281304",
            "701030435101800000611A32004000281294",
            "701030435101800000611A32004000281386",
            "701030435101800000611A32004000281361",
            "701030435101800000611A32004000281325",
            "701030435101800000611A32004000281385",
            "701030435101800000611A32004000281346",
            "701030435101800000611A32004000281348",
            "701030435101800000611A32004000281332",
            "701030435101800000623B12004000295783",
            "701030435101800000623B12004000295746",
            "701030435101800000623B12004000295730",
            "701030435101800000623B12004000295769",
            "701030435101800000623B12004000295756",
            "701030435101800000623B12004000295732",
            "701030435101800000623B12004000295762",
            "701030435101800000623B12004000295724",
            "701030435101800000623B12004000295750",
            "701030435101800000623B12004000295745",
            "701030435101800000623B12004000295737",
            "701030435101800000623B12004000295723",
            "701030435101800000623B12004000295898",
            "701030435101800000623B12004000295895",
            "701030435101800000623B12004000295879",
            "701030435101800000623B12004000295891",
            "701030435101800000623B12004000295748",
            "701030435101800000623B12004000295799",
            "701030435101800000623B12004000295734",
            "701030435101800000623B12004000295733",
            "701030435101800000623B12004000295720",
            "701030435101800000623B12004000295899",
            "701030435101800000623B12004000295722",
            "701030435101800000623B12004000295735",
            "701030435101800000623B12004000295770",
            "701030435101800000623B12004000295841",
            "701030435101800000623B12004000295892",
            "701030435101800000623B12004000295727",
            "701030435101800000623B12004000295753",
            "701030435101800000623B12004000295793",
            "701030435101800000623B12004000295726",
            "701030435101800000623B12004000295751",
            "701030435101800000623B12004000295774",
            "701030435101800000623B12004000295780",
            "701030435101800000623B12004000295744",
            "701030435101800000623B12004000295796",
            "701030435101800000623B12004000295758",
            "701030435101800000623B12004000295784",
            "701030435101800000623B12004000295764",
            "701030435101800000623B12004000295749",
            "701030435101800000623B12004000295739",
            "701030435101800000623B12004000295721",
            "701030435101800000623B12004000295752",
            "701030435101800000623B12004000295775",
            "701030435101800000623B12004000295728",
            "701030435101800000623B12004000295761",
            "701030435101800000623B12004000295760",
            "701030435101800000623B12004000295736",
            "701030435101800000623B12004000295740",
            "701030435101800000623B12004000295765",
            "701030435101800000623B12004000295818",
            "701030435101800000623B12004000295845",
            "701030435101800000623B12004000295820",
            "701030435101800000623B12004000295836",
            "701030435101800000623B12004000295843",
            "701030435101800000623B12004000295876",
            "701030435101800000623B12004000295827",
            "701030435101800000623B12004000295826",
            "701030435101800000623B12004000295829",
            "701030435101800000623B12004000295830",
            "701030435101800000623B12004000295819",
            "701030435101800000623B12004000295837",
            "701030435101800000623B12004000295801",
            "701030435101800000623B12004000295806",
            "701030435101800000623B12004000295975",
            "701030435101800000623B12004000295967",
            "701030435101800000623B12004000295972",
            "701030435101800000623B12004000295816",
            "701030435101800000623B12004000295955",
            "701030435101800000623B12004000295959",
            "701030435101800000623B12004000295951",
            "701030435101800000623B12004000295974",
            "701030435101800000623B12004000295817",
            "701030435101800000623B12004000295807",
            "701030435101800000623B12004000295963",
            "701030435101800000623B12004000295970",
            "701030435101800000623B12004000295968",
            "701030435101800000623B12004000295824",
            "701030435101800000623B12004000295803",
            "701030435101800000623B12004000295812",
            "701030435101800000623B12004000295878",
            "701030435101800000623B12004000295868",
            "701030435101800000623B12004000295821",
            "701030435101800000623B12004000295813",
            "701030435101800000623B12004000295869",
            "701030435101800000623B12004000295877",
            "701030435101800000623B12004000295840",
            "701030435101800000623B12004000295815",
            "701030435101800000623B12004000295804",
            "701030435101800000623B12004000295802",
            "701030435101800000623B12004000295960",
            "701030435101800000623B12004000295956",
            "701030435101800000623B12004000295814",
            "701030435101800000623B12004000295838",
            "701030435101800000623B12004000295848",
            "701030435101800000623B12004000295833",
            "701030435101800000623B12004000295886",
            "701030435101800000623B12004000295832",
            "701030435101800000623B12004000295890",
            "701030435101800000623B12004000295849",
            "7011762081090000000377B2000800344099",
            "7011762081090000000377B2000800344094",
            "7011762081090000000377B2000800344098",
            "7011762081090000000377B2000800344086",
            "7011762081090000000377B2000800344080",
            "7011762081090000000377B2000800344076",
            "7011762081090000000377B2000800344109",
            "7011762081090000000377B2000800344105",
            "7011762081090000000377B2000800344090",
            "7011762081090000000377B2000800344123",
            "7011762081090000000377B2000800344097",
            "7011762081090000000377B2000800344093",
            "7011762081090000000377B2000800344048",
            "7011762081090000000377B2000800344038",
            "7011762081090000000377B2000800344144",
            "7011762081090000000377B2000800344133",
            "7011762081090000000377B2000800344141",
            "7011762081090000000377B2000800344157",
            "7011762081090000000377B2000800344130",
            "7011762081090000000377B2000800344127",
            "7011762081090000000377B2000800344515",
            "7011762081090000000377B2000800344495",
            "7011762081090000000377B2000800344492",
            "7011762081090000000377B2000800344136",
            "7011762081090000000377B2000800344499",
            "7011762081090000000377B2000800344497",
            "7011762081090000000377B2000800344115",
            "7011762081090000000377B2000800344095",
            "7011762081090000000377B2000800344137",
            "7011762081090000000377B2000800344110",
            "7011762081090000000377B2000800344511",
            "7011762081090000000377B2000800344158",
            "7011762081090000000377B2000800344153",
            "7011762081090000000377B2000800344135",
            "7011762081090000000377B2000800344146",
            "7011762081090000000377B2000800344125",
            "7011762081090000000377B2000800344121",
            "7011762081090000000377B2000800344103",
            "7011762081090000000377B2000800344107",
            "7011762081090000000377B2000800344111",
            "7011762081090000000377B2000800344145",
            "7011762081090000000377B2000800344138",
            "7011762081090000000377B2000800344159",
            "7011762081090000000377B2000800344036",
            "7011762081090000000377B2000800344510",
            "7011762081090000000377B2000800344525",
            "7011762081090000000377B2000800344481",
            "7011762081090000000377B2000800344514",
            "7011762081090000000377B2000800344498",
            "7011762081090000000377B2000800344507",
            "7011762081090000000377B2000800344020",
            "7011762081090000000377B2000800344011",
            "7011762081090000000377B2000800343996",
            "7011762081090000000377B2000800344028",
            "7011762081090000000377B2000800344089",
            "7011762081090000000377B2000800344083",
            "7011762081090000000377B2000800343909",
            "7011762081090000000377B2000800344005",
            "7011762081090000000377B2000800344003",
            "7011762081090000000377B2000800344014",
            "7011762081090000000377B2000800344053",
            "7011762081090000000377B2000800343986",
            "7011762081090000000377B2000800343989",
            "7011762081090000000377B2000800344001",
            "7011762081090000000377B2000800344004",
            "7011762081090000000377B2000800344008",
            "7011762081090000000377B2000800343994",
            "7011762081090000000377B2000800343991",
            "7011762081090000000377B2000800343999",
            "7011762081090000000377B2000800343907",
            "7011762081090000000377B2000800343990",
            "7011762081090000000377B2000800344052",
            "7011762081090000000377B2000800343910",
            "7011762081090000000377B2000800344016",
            "7011762081090000000377B2000800343992",
            "7011762081090000000377B2000800344050",
            "7011762081090000000377B2000800344000",
            "7011762081090000000377B2000800343908",
            "7011762081090000000377B2000800344002",
            "7011762081090000000377B2000800343993",
            "7011762081090000000377B2000800343998",
            "7011762081090000000377B2000800343995",
            "7011762081090000000377B2000800343911",
            "7011762081090000000377B2000800344049",
            "7011762081090000000377B2000800344024",
            "7011762081090000000377B2000800344010",
            "7011762081090000000377B2000800344007",
            "7011762081090000000377B2000800343997",
            "7011762081090000000377B2000800343912",
            "7011762081090000000377B2000800344051",
            "7011762081090000000377B2000800344055",
            "7011762081090000000377B2000800344054",
            "7011762081090000000377B2000800344013",
            "7011762081090000000377B2000800344009",
            "7011762081090000000377B2000800344081",
            "7011762081090000000377B2000800344078",
            "7011762081090000000377B2000800344088",
            "7011762081090000000377B2000800344077",
            "7011762081090000000377B2000800344147",
            "7011762081090000000377B2000800344084",
            "7011762081090000000377B2000800343893",
            "7011762081090000000377B2000800343861",
            "7011762081090000000377B2000800343838",
            "7011762081090000000377B2000800343834",
            "7011762081090000000377B2000800343878",
            "7011762081090000000377B2000800343824",
            "7011762081090000000377B2000800343782",
            "7011762081090000000377B2000800343784",
            "7011762081090000000377B2000800343814",
            "7011762081090000000377B2000800343807",
            "7011762081090000000377B2000800343813",
            "7011762081090000000377B2000800343832",
            "7011762081090000000377B2000800343829",
            "7011762081090000000377B2000800343794",
            "7011762081090000000377B2000800343812",
            "7011762081090000000377B2000800343818",
            "7011762081090000000377B2000800343808",
            "7011762081090000000377B2000800343810",
            "7011762081090000000377B2000800343805",
            "7011762081090000000377B2000800343825",
            "7011762081090000000377B2000800343850",
            "7011762081090000000377B2000800343842",
            "7011762081090000000377B2000800343869",
            "7011762081090000000377B2000800343800",
            "7011762081090000000377B2000800343874",
            "7011762081090000000377B2000800343837",
            "7011762081090000000377B2000800343802",
            "7011762081090000000377B2000800343840",
            "7011762081090000000377B2000800343845",
            "7011762081090000000377B2000800343852",
            "7011762081090000000377B2000800343815",
            "7011762081090000000377B2000800343817",
            "7011762081090000000377B2000800343796",
            "7011762081090000000377B2000800343793",
            "7011762081090000000377B2000800343828",
            "7011762081090000000377B2000800343877",
            "7011762081090000000377B2000800343835",
            "7011762081090000000377B2000800343856",
            "7011762081090000000377B2000800343846",
            "7011762081090000000377B2000800343831",
            "7011762081090000000377B2000800343876",
            "7011762081090000000377B2000800343859",
            "7011762081090000000377B2000800343820",
            "7011762081090000000377B2000800343816",
            "7011762081090000000377B2000800343863",
            "7011762081090000000377B2000800343862",
            "7011762081090000000377B2000800343803",
            "7011762081090000000377B2000800343830",
            "7011762081090000000377B2000800343858",
            "7011762081090000000377B2000800343880",
            "701030235101800000887B22004000501349",
            "701030235101800000887B22004000501307",
            "701030235101800000887B22004000501431",
            "701030235101800000887B22004000501417",
            "701030235101800000887B22004000501344",
            "701030235101800000887B22004000501340",
            "701030235101800000887B22004000501385",
            "701030235101800000887B22004000501430",
            "701030235101800000887B22004000501325",
            "701030235101800000887B22004000501403",
            "701030235101800000887B22004000501429",
            "701030235101800000887B22004000501423",
            "701030235101800000887B22004000501433",
            "701030235101800000887B22004000501469",
            "701030235101800000887B22004000501439",
            "701030235101800000887B22004000501414",
            "701030235101800000887B22004000501415",
            "701030235101800000887B22004000501327",
            "701030235101800000887B22004000501460",
            "701030235101800000887B22004000501456",
            "701030235101800000887B22004000501444",
            "701030235101800000887B22004000501379",
            "701030235101800000887B22004000501318",
            "701030235101800000887B22004000501470",
            "701030235101800000887B22004000501463",
            "701030235101800000888A31004000503095",
            "701030235101800000887B22004000501405",
            "701030235101800000887B22004000501358",
            "701030235101800000887B22004000501342",
            "701030235101800000887B22004000501310",
            "701030235101800000887B22004000501406",
            "701030235101800000887B22004000501450",
            "701030235101800000887B22004000501472",
            "701030235101800000887B22004000501427",
            "701030235101800000887B22004000501447",
            "701030235101800000887B22004000501458",
            "701030235101800000887B22004000501459",
            "701030235101800000887B22004000501441",
            "701030235101800000887B22004000501453",
            "701030235101800000887B22004000501467",
            "701030235101800000887B22004000501442",
            "701030235101800000887B22004000501457",
            "701030235101800000887B22004000501468",
            "701030235101800000887B22004000501440",
            "701030235101800000887B22004000501455",
            "701030235101800000887B22004000501464",
            "701030235101800000887B22004000501452",
            "701030235101800000887B22004000501465",
            "701030235101800000887B22004000501315",
            "701030235101800000887B22004000501351",
            "701030235101800000887B22004000501515",
            "701030235101800000887B22004000501412",
            "701030235101800000887B22004000501541",
            "701030235101800000887B22004000501542",
            "701030235101800000887B22004000501543",
            "701030235101800000887B22004000501545",
            "701030235101800000887B22004000501551",
            "701030235101800000887B22004000501527",
            "701030235101800000887B22004000501532",
            "701030235101800000887B22004000501536",
            "701030235101800000887B22004000501547",
            "701030235101800000887B22004000501549",
            "701030235101800000887B22004000501538",
            "701030235101800000887B22004000501513",
            "701030235101800000887B22004000501531",
            "701030235101800000887B22004000501510",
            "701030235101800000887B22004000501540",
            "701030235101800000887B22004000501539",
            "701030235101800000887B22004000501535",
            "701030235101800000887B22004000501518",
            "701030235101800000887B22004000501506",
            "701030235101800000887B22004000501438",
            "701030235101800000887B22004000501505",
            "701030235101800000887B22004000501529",
            "701030235101800000887B22004000501521",
            "701030235101800000887B22004000501522",
            "701030235101800000887B22004000501526",
            "701030235101800000887B22004000501520",
            "701030235101800000887B22004000501519",
            "701030235101800000887B22004000501495",
            "701030235101800000887B22004000501530",
            "701030235101800000887B22004000501528",
            "701030235101800000887B22004000501548",
            "701030235101800000887B22004000501534",
            "701030235101800000887B22004000501550",
            "701030235101800000887B22004000501533",
            "701030235101800000887B22004000501500",
            "701030235101800000887B22004000501514",
            "701030235101800000887B22004000501554",
            "701030235101800000887B22004000501558",
            "701030235101800000887B22004000501552",
            "701030235101800000887B22004000501553",
            "701030235101800000887B22004000501556",
            "701030235101800000887B22004000501544",
            "701030235101800000887B22004000501401",
            "701030235101800000887B22004000501413",
            "701030235101800000887B22004000501569",
            "701030235101800000887B22004000501420",
            "701030235101800000887B22004000501425",
            "701030235101800000887B22004000501408",
            "701030235101800000886B21004000500767",
            "701030235101800000886B21004000500806",
            "701030235101800000886B21004000500827",
            "701030235101800000886B21004000500789",
            "701030235101800000886B21004000500786",
            "701030235101800000886B21004000500763",
            "701030235101800000886B21004000500816",
            "701030235101800000886B21004000500838",
            "701030235101800000886B21004000500777",
            "701030235101800000886B21004000500774",
            "701030235101800000886B21004000500826",
            "701030235101800000886B21004000500809",
            "701030235101800000886B21004000500791",
            "701030235101800000886B21004000500821",
            "701030235101800000886B21004000500781",
            "701030235101800000886B21004000500805",
            "701030235101800000886B21004000500776",
            "701030235101800000886B21004000500771",
            "701030235101800000886B21004000500810",
            "701030235101800000886B21004000500818",
            "701030235101800000886B21004000500757",
            "701030235101800000886B21004000500764",
            "701030235101800000886B21004000500779",
            "701030235101800000886B21004000500788",
            "701030235101800000886B21004000500768",
            "701030235101800000886B21004000500807",
            "701030235101800000886B21004000500761",
            "701030235101800000886B21004000500794",
            "701030235101800000886B21004000500759",
            "701030235101800000886B21004000500801",
            "701030235101800000886B21004000500749",
            "701030235101800000886B21004000500833",
            "701030235101800000886B21004000500824",
            "701030235101800000886B21004000500812",
            "701030235101800000886B21004000500775",
            "701030235101800000886B21004000500751",
            "701030235101800000886B21004000500762",
            "701030235101800000886B21004000500780",
            "701030235101800000886B21004000500797",
            "701030235101800000886B21004000500790",
            "701030235101800000886B21004000500792",
            "701030235101800000886B21004000500825",
            "701030235101800000886B21004000500778",
            "701030235101800000886B21004000500820",
            "701030235101800000886B21004000500822",
            "701030235101800000886B21004000500834",
            "701030235101800000886B21004000500945",
            "701030235101800000886B21004000500823",
            "701030235101800000886B21004000500839",
            "701030235101800000902B11004000520961",
            "701031735101800000308A31004000095838",
            "701031735101800000308A31004000095723",
            "701031735101800000308A31004000095686",
            "701031735101800000308A31004000095768",
            "701031735101800000308A31004000095790",
            "701031735101800000308A31004000095751",
            "701031735101800000308A31004000095847",
            "701031735101800000308A31004000095737",
            "701031735101800000308A31004000095758",
            "701031735101800000308A31004000095682",
            "701031735101800000308A31004000095752",
            "701031735101800000308A31004000095705",
            "701031735101800000308A31004000095728",
            "701031735101800000308A31004000095804",
            "701031735101800000308A31004000095712",
            "701031735101800000308A31004000095702",
            "701031735101800000308A31004000095844",
            "701031735101800000308A31004000095778",
            "701031735101800000308A31004000095829",
            "701031735101800000308A31004000095687",
            "701031735101800000308A31004000095787",
            "701031735101800000308A31004000095821",
            "701031735101800000308A31004000095816",
            "701031735101800000308A31004000095815",
            "701031735101800000308A31004000095853",
            "701031735101800000308A31004000095805",
            "701031735101800000308A31004000095792",
            "701031735101800000308A31004000095689",
            "701031735101800000308A31004000095724",
            "701031735101800000308A31004000095707",
            "701031735101800000308A31004000095732",
            "701031735101800000308A31004000095716",
            "701031735101800000308A31004000095802",
            "701031735101800000308A31004000095754",
            "701031735101800000308A31004000095795",
            "701031735101800000308A31004000095833",
            "701031735101800000308A31004000095709",
            "701031735101800000308A31004000095756",
            "701031735101800000308A31004000095825",
            "701031735101800000308A31004000095700",
            "701031735101800000308A31004000095807",
            "701031735101800000308A31004000095794",
            "701031735101800000308A31004000095118",
            "701031735101800000308A31004000095136",
            "701031735101800000308A31004000096013",
            "701031735101800000308A31004000095774",
            "701031735101800000308A31004000095793",
            "701031735101800000308A31004000095809",
            "701031735101800000308A31004000095061",
            "701031735101800000308A31004000095817",
            "7011762423090000000104B1000800056646",
            "7011762423090000000104B1000800056674",
            "7011762423090000000104B1000800056673",
            "7011762423090000000104B1000800056683",
            "7011762423090000000104B1000800056694",
            "7011762423090000000104B1000800056693",
            "7011762423090000000104B1000800056664",
            "7011762423090000000104B1000800056687",
            "7011762423090000000104B1000800056666",
            "7011762423090000000104B1000800056643",
            "7011762423090000000104B1000800056653",
            "7011762423090000000104B1000800056652",
            "7011762423090000000104B1000800056681",
            "7011762423090000000104B1000800056682",
            "7011762423090000000104B1000800056679",
            "7011762423090000000104B1000800056709",
            "7011762423090000000104B1000800056680",
            "7011762423090000000104B1000800056700",
            "7011762423090000000104B1000800056708",
            "7011762423090000000104B1000800056691",
            "7011762423090000000104B1000800056726",
            "7011762423090000000104B1000800056729",
            "7011762423090000000104B1000800056720",
            "7011762423090000000104B1000800056685",
            "7011762423090000000104B1000800056702",
            "7011762423090000000104B1000800056624",
            "7011762423090000000104B1000800056689",
            "7011762423090000000104B1000800056718",
            "7011762423090000000104B1000800056658",
            "7011762423090000000104B1000800056699",
            "7011762423090000000104B1000800056707",
            "7011762423090000000104B1000800056635",
            "7011762423090000000104B1000800056672",
            "7011762423090000000104B1000800056669",
            "7011762423090000000104B1000800056703",
            "7011762423090000000104B1000800056711",
            "7011762423090000000104B1000800056710",
            "7011762423090000000104B1000800056705",
            "7011762423090000000104B1000800056631",
            "7011762423090000000104B1000800056662",
            "7011762423090000000104B1000800056668",
            "7011762423090000000104B1000800056663",
            "7011762423090000000104B1000800056692",
            "7011762423090000000104B1000800056677",
            "7011762423090000000104B1000800056678",
            "7011762423090000000104B1000800056704",
            "7011762423090000000104B1000800056686",
            "7011762423090000000104B1000800057035",
            "7011762423090000000104B1000800057038",
            "7011762423090000000104B1000800056973",
            "701031835101800000082A21004000067302",
            "701031835101800000082A21004000067295",
            "701031835101800000082A21004000067254",
            "701031835101800000082A21004000067251",
            "701031835101800000082A21004000067291",
            "701031835101800000082A21004000067296",
            "701031835101800000082A21004000067275",
            "701031835101800000082A21004000067272",
            "701031835101800000082A21004000067268",
            "701031835101800000082A21004000067276",
            "701031835101800000082A21004000067308",
            "701031835101800000082A21004000067292",
            "701031835101800000082A21004000067258",
            "701031835101800000082A21004000067271",
            "701031835101800000082A21004000067294",
            "701031835101800000082A21004000067314",
            "701031835101800000082A21004000067330",
            "701031835101800000082A21004000067301",
            "701031835101800000082A21004000067305",
            "701031835101800000082A21004000067307",
            "701031835101800000082A21004000067309",
            "701031835101800000082A21004000067329",
            "701031835101800000082A21004000067280",
            "701031835101800000082A21004000067300",
            "701031835101800000082A21004000067266",
            "701031835101800000082A21004000067313",
            "701031835101800000082A21004000067257",
            "701031835101800000082A21004000067281",
            "701031835101800000082A21004000067249",
            "701031835101800000082A21004000067259",
            "701031835101800000082A21004000067269",
            "701031835101800000082A21004000067303",
            "701031835101800000082A21004000067304",
            "701031835101800000082A21004000067248",
            "701031835101800000082A21004000067283",
            "701031835101800000082A21004000067287",
            "701031835101800000082A21004000067312",
            "701031835101800000083A31004000067564",
            "701031835101800000083A31004000067576",
            "701031835101800000083A31004000067543",
            "701031835101800000083A31004000067570",
            "701031835101800000083A31004000067568",
            "701031835101800000083A31004000067561",
            "701031835101800000083A31004000067538",
            "701031835101800000083A31004000067539",
            "701031835101800000083A31004000067559",
            "701031835101800000083A31004000067556",
            "701031835101800000083A31004000067547",
            "701031835101800000083A31004000067565",
            "701031835101800000083A31004000067545",
            "701030335101800000312A32004000106989",
            "701030335101800000312A32004000106949",
            "701030335101800000312A32004000106943",
            "701030335101800000318A32004000113079",
            "701030335101800000318A32004000113057",
            "701030335101800000318A32004000113062",
            "701030335101800000318A32004000113051",
            "701030335101800000312A32004000106971",
            "701030335101800000312A32004000106974",
            "701030335101800000312A32004000107010",
            "701030335101800000313A31004000108675",
            "701030335101800000312A32004000107000",
            "701030335101800000312A32004000106981",
            "701030335101800000312A32004000106968",
            "701030335101800000312A32004000107008",
            "701030335101800000312A32004000106977",
            "701030335101800000312A32004000107006",
            "701030335101800000312A32004000107007",
            "701030335101800000312A32004000106999",
            "701030335101800000312A32004000106990",
            "701030335101800000312A32004000107529",
            "701030335101800000312A32004000107497",
            "701030335101800000312A32004000107003",
            "701030335101800000312A32004000107004",
            "701030335101800000312A32004000107528",
            "701030335101800000312A32004000107504",
            "701030335101800000312A32004000106987",
            "701030335101800000312A32004000106985",
            "701030335101800000312A32004000106984",
            "701030335101800000312A32004000106957",
            "701030335101800000312A32004000106986",
            "701030335101800000312A32004000106958",
            "701030335101800000312A32004000106995",
            "701030335101800000312A32004000106988",
            "701030335101800000312A32004000106996",
            "701030335101800000312A32004000106998",
            "701030335101800000312A32004000107001",
            "701030335101800000312A32004000106992",
            "701030335101800000312A32004000106991",
            "701030335101800000312A32004000107002",
            "701030335101800000312A32004000107011",
            "701030335101800000312A32004000107005",
            "701030335101800000312A32004000107013",
            "701030335101800000312A32004000107500",
            "701030335101800000312A32004000107538",
            "701030335101800000312A32004000107539",
            "701030335101800000312A32004000107512",
            "701030335101800000312A32004000107541",
            "701030335101800000312A32004000107502",
            "701030335101800000312A32004000107494",
            "7011762422090000000044A1000800032644",
            "7011762422090000000044A1000800032634",
            "7011762422090000000044A1000800032573",
            "7011762422090000000044A1000800032618",
            "7011762422090000000044A1000800032650",
            "7011762422090000000044A1000800032631",
            "7011762422090000000044A1000800032641",
            "7011762422090000000044A1000800032629",
            "7011762422090000000044A1000800032059",
            "7011762422090000000044A1000800032664",
            "7011762422090000000044A1000800032654",
            "7011762422090000000044A1000800032645",
            "7011762422090000000044A1000800032633",
            "7011762422090000000044A1000800032636",
            "7011762422090000000044A1000800032100",
            "7011762422090000000044A1000800032101",
            "7011762422090000000044A1000800032081",
            "7011762422090000000044A1000800032097",
            "7011762422090000000044A1000800032091",
            "7011762422090000000044A1000800032070",
            "7011762422090000000044A1000800031998",
            "7011762422090000000044A1000800031989",
            "7011762422090000000044A1000800032002",
            "7011762422090000000044A1000800031956",
            "7011762422090000000044A1000800032003",
            "7011762422090000000044A1000800031969",
            "7011762422090000000044A1000800032635",
            "7011762422090000000044A1000800032649",
            "7011762422090000000044A1000800032657",
            "7011762422090000000044A1000800032079",
            "7011762422090000000044A1000800032725",
            "7011762422090000000044A1000800032051",
            "7011762422090000000044A1000800032647",
            "7011762422090000000044A1000800032047",
            "7011762422090000000044A1000800031946",
            "7011762422090000000044A1000800031943",
            "7011762422090000000044A1000800032072",
            "7011762422090000000044A1000800032623",
            "7011762422090000000044A1000800032624",
            "7011762422090000000044A1000800032069",
            "7011762422090000000044A1000800032104",
            "7011762422090000000044A1000800032011",
            "7011762422090000000044A1000800031954",
            "7011762422090000000044A1000800032071",
            "7011762422090000000044A1000800031963",
            "7011762422090000000044A1000800031947",
            "7011762422090000000044A1000800032013",
            "7011762422090000000044A1000800031970",
            "7011762422090000000044A1000800031952",
            "7011762422090000000044A1000800031977",
            "7011762424090000000098A1000800047739",
            "7011762424090000000098A1000800047722",
            "7011762424090000000098A1000800047734",
            "7011762424090000000098A1000800047749",
            "7011762424090000000098A1000800047782",
            "7011762424090000000098A1000800047781",
            "7011762424090000000098A1000800047762",
            "7011762424090000000098A1000800047740",
            "7011762424090000000098A1000800047678",
            "7011762424090000000098A1000800047744",
            "7011762424090000000098A1000800047704",
            "7011762424090000000098A1000800047677",
            "7011762424090000000098A1000800047685",
            "7011762424090000000098A1000800047738",
            "7011762424090000000098A1000800047692",
            "7011762424090000000098A1000800047693",
            "7011762424090000000098A1000800047682",
            "7011762424090000000098A1000800047736",
            "7011762424090000000098A1000800047705",
            "7011762424090000000098A1000800047691",
            "7011762424090000000099A1000800049970",
            "7011762424090000000099A1000800049942",
            "7011762424090000000099A1000800049934",
            "7011762424090000000099A1000800049929",
            "7011762424090000000099A1000800049977",
            "7011762424090000000099A1000800049957",
            "7011762424090000000098A1000800047696",
            "7011762424090000000098A1000800047751",
            "7011762424090000000098A1000800047755",
            "7011762424090000000098A1000800047769",
            "7011762424090000000098A1000800047727",
            "7011762424090000000098A1000800047730",
            "7011762424090000000098A1000800047680",
            "7011762424090000000098A1000800047694",
            "7011762424090000000098A1000800047725",
            "7011762424090000000098A1000800047745",
            "7011762424090000000098A1000800047753",
            "7011762424090000000098A1000800047760",
            "7011762424090000000098A1000800047673",
            "7011762424090000000098A1000800047687",
            "7011762424090000000098A1000800047703",
            "7011762424090000000099A1000800049917",
            "7011762424090000000099A1000800049939",
            "7011762424090000000099A1000800049927",
            "7011762424090000000099A1000800049928",
            "7011762424090000000099A1000800049958",
            "7011762424090000000099A1000800049959",
            "7011762424090000000099A1000800049945",
            "7011762424090000000099A1000800049972",
            "7011762424090000000099A1000800049968"
        ];
//        foreach ($t as $item) {
//            $bar = InvoiceBarcode::create([
//                "invoice_id" => 2175,
//                "Barcode" => $item,
//            ]);
//        }

        $x = Invoice::where('id', 2175)->first();
        return response(new InvoiceResource($x), 200);
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

    public function report(Request $request)
    {
        try {
            $data = Invoice::orderByDesc('id')->get();
            return response(InvoiceResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

}
