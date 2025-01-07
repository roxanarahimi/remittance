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
                $info = $info->where('barcode','like', '%'.$request['search'].'%');
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
//                "invoice_id" => 2163,
//                "Barcode" => $item,
//            ]);
//        }
        $bars = InvoiceBarcode::where('id','>',900)->get();
        foreach ($bars as $item) {
           $item->delete();
        }
        $x = Invoice::where('id',2163)->first();
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

}
