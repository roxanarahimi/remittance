<?php


namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InventoryVoucherResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RemittanceResource;
use App\Models\InventoryVoucher;
use App\Models\InventoryVoucherItem;
use App\Models\Invoice;
use App\Models\InvoiceProduct;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use App\Models\Remittance;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// error_reporting(E_ALL);
// ini_set('display_errors', '1');

class RemittanceController extends Controller
{
    public function __construct(Request $request)
    {
        $this->middleware(Token::class)->except('readOnly1');
    }

    public function index(Request $request)
    {
        try {
            $data = Remittance::orderByDesc('id')->get();
            return response(RemittanceResource::collection($data), 200);
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

    public function readOnly1(Request $request)
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
            "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartStoreRef",
        "LGS3.InventoryVoucher.InventoryVoucherSpecificationRef","DelivererOrReceiverPartyRef")
            ->join('DNR3.Party', 'DNR3.Party.PartyID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
            ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
//            ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(7))
            ->whereIn('LGS3.Store.StoreID', $storeIDs)
            ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68])
//            ->where(function ($q) use ($storeIDs) {
//                $q->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [69])
//                    ->orwhere(function ($z) use ($storeIDs) {
//                        $z->whereIn('LGS3.Store.StoreID', $storeIDs)
//                            ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68]);
//                    });
//            })
            ->whereHas('OrderItems', function ($q) use ($partIDs) {
                $q->whereIn('PartRef', $partIDs);
            })
            ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
            ->paginate(100);
$data = InventoryVoucherResource::collection($dat);
        return $dat;

        $d3 = Invoice::where('DeliveryDate', '>=', today()->subDays(7))
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
        $input = array_merge($input2, $input1);


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

            $dat = DB::connection('sqlsrv')->table('LGS3.InventoryVoucher')
                ->select([
                    "LGS3.InventoryVoucher.InventoryVoucherID as OrderID", "LGS3.InventoryVoucher.Number as OrderNumber",
                    "LGS3.Store.Name as AddressName", "GNR3.Address.Details as Address", "Phone",
                    "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate"])
                ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->where('LGS3.InventoryVoucher.Date', '>=', today()->subDays(7))
                ->whereIn('LGS3.Store.StoreID', $storeIDs)
                ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68, 69])
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
                    ->get();
                $item->{'OrderItems'} = $details;
            }

            $filtered = array_filter($dat, function ($el) {
                return count($el->{'OrderItems'}) > 0;
            });
            $input = array_values($filtered);
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

    }

}
