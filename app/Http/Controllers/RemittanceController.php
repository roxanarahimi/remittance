<?php


namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InventoryVoucherResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\RemittanceResource;
use App\Models\InventoryVoucher;
use App\Models\InventoryVoucherItem;
use App\Models\Order;
use App\Models\Part;
use App\Models\Product;
use App\Models\Remittance;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;

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
        $data = json_encode([
            'OrderID' => $request['OrderID'],
            'OrderItems' => $request['OrderItems'],
            'name' => $request['name'],
        ]);
        $id = $request['OrderID'];
        $info = Redis::get($request['OrderID']);
        if (isset($info)) {
            $id = $request['OrderID'] . '-' . substr(explode(',', $request['OrderItems'])[0], -4);
        }
        Redis::set($id, $data);
        $value = Redis::get($id);
        $json = json_decode($value);
        $orderId = $json->{'OrderID'};
        $items = explode(',', $json->{'OrderItems'});
        $name = $json->{'name'};
        $myfile = fopen('../storage/logs/failed_data_entries/' . $id . ".log", "w") or die("Unable to open file!");
        $txt = json_encode([
            'OrderID' => $orderId,
            'name' => $name,
            'OrderItems' => $items
        ]);
        fwrite($myfile, $txt);
        fclose($myfile);

        $str = str_replace(' ', '', str_replace('"', '', $request['OrderItems']));
        $orderItems = explode(',', $str);
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
                        . $id .
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

    public function readOnly(Request $request){
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
                "LGS3.Store.Name as AddressName", "GNR3.Address.Details as Address", "Phone", "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate",
            ])
            ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
            ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')

            ->where('LGS3.InventoryVoucher.Date','>=',today()->subDays(7))
            ->whereIn('LGS3.Store.StoreID', $storeIDs)
            ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
            ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68,69])
            ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
            ->get()->toArray();
        foreach ($dat as $item) {
            $item->{'type'} = 'InventoryVoucher';
            $item->{'AddressName'} = $item->{'AddressName'} . ' '.$item->{'OrderNumber'};
            $details = DB::connection('sqlsrv')->table('LGS3.InventoryVoucherItem')
                ->select("LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity",
                    "LGS3.InventoryVoucherItem.Barcode as Barcode", "LGS3.Part.PartID as Id",
                    "LGS3.Part.Code as ProductNumber")
                ->join('LGS3.InventoryVoucherItemTrackingFactor', 'LGS3.InventoryVoucherItemTrackingFactor.InventoryVoucherItemRef', '=', 'LGS3.InventoryVoucherItem.InventoryVoucherItemID')
                ->join('LGS3.Part', 'LGS3.Part.PartID', '=', 'LGS3.InventoryVoucherItemTrackingFactor.PartRef')
                ->where('InventoryVoucherRef', $item->{'OrderID'})
                ->whereIn('LGS3.Part.PartID', $partIDs)
                ->get();
            $item->{'OrderItems'} = $details;
        }
        $filtered = array_filter($dat, function ($el) {
            return count($el->{'OrderItems'}) > 0;
        });
        $productIDs = Product::where('Name', 'like', '%نودالیت%')->pluck("ProductID");
        $dat2 = DB::connection('sqlsrv')->table('SLS3.Order')
            ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
            ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
            ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
            ->select(["SLS3.Order.OrderID as OrderID", "SLS3.Order.Number as OrderNumber",
                "GNR3.Address.Name as AddressName", "Details as Address", "Phone", "SLS3.Order.CreationDate", "DeliveryDate",
            ])
            ->whereHas('OrderItems', function ($q) {
                $q->havingRaw('SUM(Quantity) >= ?', [50]);
            })
            ->where('SLS3.CustomerAddress.Type', 2)
            ->where('SLS3.Order.FiscalYearRef', 1403)
            ->where('SLS3.Order.InventoryRef', 1)
            ->where('SLS3.Order.State', 2)
            ->orderBy('SLS3.Order.OrderID')
            ->get()->toArray();

        $dat2 = array_values($dat2);

        foreach ($dat2 as $item) {
            $item->{'type'} = 'Order';
            $item->{'ok'} = 1;
            $item->{'AddressName'} = $item->{'AddressName'} . ' '.$item->{'OrderNumber'};
            $item->{'noodElite'} = '';
            $noodElite = 0;
            $details = DB::connection('sqlsrv')->table('SLS3.OrderItem')
                ->select("SLS3.Product.Name as ProductName", "Quantity", "SLS3.Product.ProductID as Id",
                    "SLS3.Product.Number as ProductNumber")
                ->join('SLS3.Product', 'SLS3.Product.ProductID', '=', 'SLS3.OrderItem.ProductRef')
                ->whereIn('SLS3.Product.ProductID', $productIDs)
                ->where(function ($q) {
                    $q->where('SUM(Quantity) >= ?', [50]);
                })
                ->where('OrderRef', $item->{'OrderID'})->get();
            $item->{'OrderItems'} = $details;
            foreach ($details as $it) {
                if (str_contains($it->{'ProductName'}, 'نودالیت')) {
                    $noodElite += $it->{'Quantity'};
                }
            }
            $item->{'noodElite'} = $noodElite;

//            if ($noodElite >= 50) {
//                $item->{'ok'} = 1;
//            }
        }

        $filtered2 = array_filter($dat2, function ($el) {
            return $el->{'ok'} == 1;
        });

        $input1 = array_values($filtered);
        $offset = 0;
        $perPage = 100;

        $input2 = array_values($filtered2);

//
//            if (!$request['type'] || $request['type'] == ''){
//                $input = array_merge($input2,$input1);
//            }
//              if ($request['type'] && $request['type'] == 'Order'){
//                $input = $input2;
//            }
//              if ($request['type'] && $request['type'] == 'InventoryVoucher'){
//                $input = $input1;
//            }
        // $input = $input1;

        $input = array_merge($input2,$input1);

        if ($request['page'] && $request['page'] > 1) {
            $offset = ($request['page'] - 1) * $perPage;
        }
        $info = array_slice($input, $offset, $perPage);
        $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);

        return response()->json($paginator, 200);

    }
    public function readOnly0(Request $request)
    {
        try {

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
                ->where('LGS3.InventoryVoucher.Date','>=',today()->subDays(7))
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


    public function readOnly1(Request $request)
    {
        try {
            $dat = DB::connection('sqlsrv')->table('LGS3.InventoryVoucher')//InventoryVoucherItem//InventoryVoucherItemTrackingFactor//Part//Plant//Store
            ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->select([
                    "LGS3.InventoryVoucher.InventoryVoucherID as OrderID", "LGS3.InventoryVoucher.Number as OrderNumber",
                    "LGS3.Store.Name as AddressName", "GNR3.Address.Details as Address", "Phone", "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate",
                ])
                ->where('LGS3.InventoryVoucher.Date','>=',today()->subDays(7))
                ->whereNot('LGS3.Store.Name', 'LIKE', "%گرمدره%")//68, 69
                ->whereNot('GNR3.Address.Details', 'LIKE', "%گرمدره%")//68, 69
                ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', '=', 68)//68, 69
                ->orWhere('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', '=', 69)//68, 69
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
                ->get()->unique()->toArray();
            foreach ($dat as $item) {
                $item->{'type'} = 'InventoryVoucher';
                $item->{'ok'} = 0;
                $item->{'noodElite'} = '';
                $item->{'AddressName'} = $item->{'AddressName'} . substr($item->{'OrderID'}, -3);
                $noodElite = 0;
                $details = DB::connection('sqlsrv')->table('LGS3.InventoryVoucherItem')
                    ->join('LGS3.InventoryVoucherItemTrackingFactor', 'LGS3.InventoryVoucherItemTrackingFactor.InventoryVoucherItemRef', '=', 'LGS3.InventoryVoucherItem.InventoryVoucherItemID')
                    ->join('LGS3.Part', 'LGS3.Part.PartID', '=', 'LGS3.InventoryVoucherItemTrackingFactor.PartRef')
                    ->select(
                        "LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity", "LGS3.InventoryVoucherItem.Barcode as Barcode", "LGS3.Part.PartID as Id",
                        "LGS3.Part.Code as ProductNumber")
                    ->where('InventoryVoucherRef', $item->{'OrderID'})->get();

                $item->{'OrderItems'} = $details;


//                foreach ($details as $it) {
//                    if (str_contains($it->{'ProductName'}, 'نودالیت')) {
//                        $noodElite += $it->{'Quantity'};
//                    }
//                }
//                $item->{'noodElite'} = $noodElite;
//
//                if ($noodElite > 0) {
//                    $item->{'ok'} = 1;
//                }
//                if (str_contains($item->{'AddressName'}, 'گرمدره')){
//                    $item->{'ok'} = 0;
//                }
                $x = array_filter($details->toArray(), function ($el) {
                    return str_contains($el->{'ProductName'}, 'نودالیت');
                });
                if (count($x) > 0) {
                    $item->{'ok'} = 1;
                }
//                if (str_contains($item->{'AddressName'}, 'گرمدره')){
//                    $item->{'ok'} = 0;
//                }
            }

            $filtered = array_filter($dat, function ($el) {
                return $el->{'ok'} == 1;
            });
            $dat2 = DB::connection('sqlsrv')->table('SLS3.Order')
                ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
                ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
                ->select(["SLS3.Order.OrderID as OrderID", "SLS3.Order.Number as OrderNumber",
                    "GNR3.Address.Name as AddressName", "Details as Address", "Phone", "SLS3.Order.CreationDate", "DeliveryDate",
                ])
                ->where('SLS3.Order.InventoryRef', 1)
                ->where('SLS3.Order.State', 2)
                ->where('SLS3.Order.FiscalYearRef', 1403)
                ->orderBy('SLS3.Order.OrderID')
                ->get()->unique()->toArray();

            $dat2 = array_values($dat2);

            foreach ($dat2 as $item) {
                $item->{'type'} = 'Order';
                $item->{'ok'} = 0;
                $item->{'noodElite'} = '';
                $noodElite = 0;
                $details = DB::connection('sqlsrv')->table('SLS3.OrderItem')
                    ->select("SLS3.Product.Name as ProductName", "Quantity", "SLS3.Product.ProductID as Id", "SLS3.Product.Number as ProductNumber",
                        //"SLS3.Product.SecondCode",
                     //   "SLS3.OrderItem.MajorUnitQuantity", "SLS3.OrderItem.InitialQuantity", "SLS3.OrderItem.MajorUnitInitialQuantity"
                     //
                        )
                    ->join('SLS3.Product', 'SLS3.Product.ProductID', '=', 'SLS3.OrderItem.ProductRef')
                    ->where('OrderRef', $item->{'OrderID'})->get();

                $item->{'OrderItems'} = $details;
                foreach ($details as $it) {
                    if (str_contains($it->{'ProductName'}, 'نودالیت')) {
//                        if(str_contains($it->{'ProductName'},'پک 5 ع')){
                        $noodElite += $it->{'Quantity'};
//                        }else{
//                            $noodElite+= $it->{'Quantity'};
//                        }
                    }
                }
                $item->{'noodElite'} = $noodElite;

                if ($noodElite >= 50) {
                    $item->{'ok'} = 1;
                }
            }

            $filtered2 = array_filter($dat2, function ($el) {
                return $el->{'ok'} == 1;
            });

            $input1 = array_values($filtered);
            $offset = 0;
            $perPage = 100;

            $input2 = array_values($filtered2);

//
//            if (!$request['type'] || $request['type'] == ''){
//                $input = array_merge($input2,$input1);
//            }
//              if ($request['type'] && $request['type'] == 'Order'){
//                $input = $input2;
//            }
//              if ($request['type'] && $request['type'] == 'InventoryVoucher'){
//                $input = $input1;
//            }
           // $input = $input1;

                $input = array_merge($input2,$input1);

            if ($request['page'] && $request['page'] > 1) {
                $offset = ($request['page'] - 1) * $perPage;
            }
            $info = array_slice($input, $offset, $perPage);
            $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);

            return response()->json($paginator, 200);

//OK
            $y = Order::select("SLS3.Order.OrderID", "SLS3.Order.Number",
                "SLS3.Order.CreationDate", "Date as DeliveryDate", 'SLS3.Order.CustomerRef')
                ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
                ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
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
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->where('LGS3.InventoryVoucher.Date','>=',today()->subDays(7))
                ->whereIn('LGS3.Store.StoreID', $storeIDs)
                ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68, 69])
                ->whereHas('OrderItems', function ($q) use ($partIDs) {
                    $q->whereIn('PartRef', $partIDs);
                })
                ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
                ->get();


            $input = [];
            $input1 = OrderResource::collection($y);
            $input2 = InventoryVoucherResource::collection($dat);
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
            $partIDs = Part::where('Name', 'like', '%نودالیت%')->pluck("PartID");

            $x = InventoryVoucher::where('FiscalYearRef', 1403)
                ->whereIn('InventoryVoucherSpecificationRef', [68, 69])
                ->whereHas('Store', function ($q) use ($storeIDs) {
                    $q->whereIn('StoreID', $storeIDs);
                })
                ->whereHas('OrderItems', function ($q) use ($partIDs) {
                    $q->whereIn('PartRef', $partIDs);
                })
                ->orderByDesc('InventoryVoucherID')
                ->paginate(100);
            $data = InventoryVoucherResource::collection($x);
            return response()->json($x, 200);

            $x = InventoryVoucher::select("LGS3.InventoryVoucher.InventoryVoucherID", "LGS3.InventoryVoucher.Number",
                "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartStoreRef")
                ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->whereIn('LGS3.Store.StoreID', $storeIDs)
                ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68, 69])
                ->whereHas('OrderItems', function ($q) use ($partIDs) {
                    $q->whereIn('PartRef', $partIDs);
                })
                ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
                ->paginate(100);
            $data = InventoryVoucherResource::collection($x);
            return response()->json($x, 200);


            $x = InventoryVoucher::where('FiscalYearRef', 1403)
                ->whereIn('InventoryVoucherSpecificationRef', [68, 69])
                ->whereHas('Store', function ($q) use ($storeIDs) {
                    $q->whereIn('StoreID', $storeIDs);
                })
                ->whereHas('OrderItems', function ($q) use ($partIDs) {
                    $q->whereIn('PartRef', $partIDs);
                })
                ->orderByDesc('InventoryVoucherID')
                ->paginate(100);
            $data = InventoryVoucherResource::collection($x);
            return response()->json($x, 200);

            $x = InventoryVoucher::select("LGS3.InventoryVoucher.InventoryVoucherID", "LGS3.InventoryVoucher.Number",
                "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartStoreRef")
                ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->whereIn('LGS3.Store.StoreID', $storeIDs)
                ->whereIn('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', [68, 69])
                ->whereIn('LGS3.Part.PartID', $partIDs)
                ->orderBy('LGS3.InventoryVoucher.InventoryVoucherID', 'DESC')
                ->paginate(100);
            $data = InventoryVoucherResource::collection($x);
            return response()->json($x, 200);

            $x = Order::select("SLS3.Order.OrderID", "SLS3.Order.Number",
                "SLS3.Order.CreationDate", "Date as DeliveryDate", 'SLS3.Order.CustomerRef')
                ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
                ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
                ->where('SLS3.Order.InventoryRef', 1)
                ->where('SLS3.Order.State', 2)
                ->where('SLS3.Order.FiscalYearRef', 1403)
                ->where('SLS3.CustomerAddress.Type', 2)
                ->whereHas('OrderItems')
                ->whereHas('OrderItems', function ($q) {
                    $q->havingRaw('SUM(Quantity) >= ?', [50]);
                })
                ->orderBy('OrderID', 'DESC')
                ->paginate(20);

            $data = OrderResource::collection($x);
            return response()->json($x, 200);

//
//            //    return ceil($x->total()/100);
//
//            $perPage = 100;
//            $last = ceil($x->total() / 100);
//            $currentPage = $request['page']?$request['page'] : 1;
//            $next =  $currentPage == $last ? null : "/?page=" . $currentPage + 1;
//            $links = [];
//            $links[] = [
//                "url" => null,
//                "label" => "&laquo; Previous",
//                "active" => false
//            ];
//            for ($i = 1; $i <= $last; $i++) {
//                $links[] = [
//                    "url" => "/?page=" . $i,
//                    "label" => (string)$i,
//                    "active" => $currentPage == $i
//                ];
//            }
//            $links[] = [
//                "url" => "/?page=" . $currentPage + 1,
//                "label" => "Next &raquo;",
//                "active" => false
//            ];
////            $links = [
////        [
////            "url"=> null,
////            "label"=> "&laquo; Previous",
////            "active"=> false
////        ],
////        [
////            "url"=> "/?page=1",
////            "label"=> "1",
////            "active"=> true
////        ],
////        [
////            "url"=> "/?page=2",
////            "label"=> "2",
////            "active"=> false
////        ],
////        [
////            "url"=> "/?page=3",
////            "label"=> "3",
////            "active"=> false
////        ],
////        [
////            "url"=> "/?page=2",
////            "label"=> "Next &raquo;",
////            "active"=> false
////        ]
////    ];
//            $j = [
//                "current_page" => $currentPage,
//                "data" => $data,
//                "first_page_url" => "/?page=1",
//                "from" => ($perPage * $currentPage) - ($perPage - 1),
//                "last_page" => $last,
//                "last_page_url" => "/?page=" . $last,
//                "links" => $links,
//                "next_page_url" => $next,
//                "path" => "/",
//                "per_page" => $perPage,
//                "prev_page_url" => $currentPage > 1 ?  "/?page=" .$currentPage - 1 : null,
//                "to" => $perPage * $currentPage,
//                "total" => $x->total(),
//            ];
//            return response()->json($j, 200);


//

            $x = InventoryVoucher::select("LGS3.InventoryVoucher.InventoryVoucherID", "LGS3.InventoryVoucher.Number",
                "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartStoreRef")
                ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
//                ->where('LGS3.InventoryVoucher.CounterpartStoreRef', $request['id'])
                ->whereNot('LGS3.Store.Name', 'LIKE', "%مارکتینگ%")
                ->whereNot('LGS3.Store.Name', 'LIKE', "%گرمدره%")
                ->whereNot('GNR3.Address.Details', 'LIKE', "%گرمدره%")
                ->whereNot('LGS3.Store.Name', 'LIKE', "%ضایعات%")
                ->whereNot('LGS3.Store.Name', 'LIKE', "%برگشتی%")
                ->whereHas('OrderItems', function ($query) {
                    $query->whereHas('Part', function ($q) {
                        $q->where('Name', 'like', '%نودالیت%');
                    });
                })
                ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', '=', 68)
                ->orWhere('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', '=', 69)
                ->orderBy('LGS3.InventoryVoucher.InventoryVoucherID', 'DESC')
                ->paginate(20);
            $data = InventoryVoucherResource::collection($x);
            return response()->json($x, 200);
//
//            $offset = 0;
//            $perPage = 50;
//            $t = InventoryVoucherResource::collection($x);
//
//            $input1 = json_decode($t->toJson(), true);
//            $input = $input1;
//            if ($request['page'] && $request['page'] > 1) {
//                $offset = ($request['page'] - 1) * $perPage;
//            }
//            $info = array_slice($input, $offset, $perPage);
//            $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);
//            return response()->json($paginator, 200);


            $dat = DB::connection('sqlsrv')->table('LGS3.InventoryVoucher')//InventoryVoucherItem//InventoryVoucherItemTrackingFactor//Part//Plant//Store
            ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->select([
                    "LGS3.InventoryVoucher.InventoryVoucherID as OrderID", "LGS3.InventoryVoucher.Number as OrderNumber",
                    "LGS3.Store.Name as AddressName", "GNR3.Address.Details as Address", "Phone", "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate",
                ])
                ->whereNot('LGS3.Store.Name', 'LIKE', "%گرمدره%")//68, 69
                ->whereNot('GNR3.Address.Details', 'LIKE', "%گرمدره%")//68, 69
                ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', '=', 68)//68, 69
                ->orWhere('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', '=', 69)//68, 69
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
                ->get()->unique()->toArray();
            foreach ($dat as $item) {
                $item->{'type'} = 'InventoryVoucher';
                $item->{'ok'} = 0;
                $item->{'noodElite'} = '';
                $item->{'AddressName'} = $item->{'AddressName'} . substr($item->{'OrderID'}, -3);
                $noodElite = 0;
                $details = DB::connection('sqlsrv')->table('LGS3.InventoryVoucherItem')
                    ->join('LGS3.InventoryVoucherItemTrackingFactor', 'LGS3.InventoryVoucherItemTrackingFactor.InventoryVoucherItemRef', '=', 'LGS3.InventoryVoucherItem.InventoryVoucherItemID')
                    ->join('LGS3.Part', 'LGS3.Part.PartID', '=', 'LGS3.InventoryVoucherItemTrackingFactor.PartRef')
                    ->select(
                        "LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity", "LGS3.InventoryVoucherItem.Barcode as Barcode", "LGS3.Part.PartID as Id",
                        "LGS3.Part.Code as ProductNumber")
                    ->where('InventoryVoucherRef', $item->{'OrderID'})->get();

                $item->{'OrderItems'} = $details;


//                foreach ($details as $it) {
//                    if (str_contains($it->{'ProductName'}, 'نودالیت')) {
//                        $noodElite += $it->{'Quantity'};
//                    }
//                }
//                $item->{'noodElite'} = $noodElite;
//
//                if ($noodElite > 0) {
//                    $item->{'ok'} = 1;
//                }
//                if (str_contains($item->{'AddressName'}, 'گرمدره')){
//                    $item->{'ok'} = 0;
//                }
                $x = array_filter($details->toArray(), function ($el) {
                    return str_contains($el->{'ProductName'}, 'نودالیت');
                });
                if (count($x) > 0) {
                    $item->{'ok'} = 1;
                }
//                if (str_contains($item->{'AddressName'}, 'گرمدره')){
//                    $item->{'ok'} = 0;
//                }
            }

            $filtered = array_filter($dat, function ($el) {
                return $el->{'ok'} == 1;
            });
//            $dat2 = DB::connection('sqlsrv')->table('SLS3.Order')
//                ->join('SLS3.Customer', 'SLS3.Customer.CustomerID', '=', 'SLS3.Order.CustomerRef')
//                ->join('SLS3.CustomerAddress', 'SLS3.CustomerAddress.CustomerRef', '=', 'SLS3.Customer.CustomerID')
//                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'SLS3.CustomerAddress.AddressRef')
//                ->select(["SLS3.Order.OrderID as OrderID", "SLS3.Order.Number as OrderNumber",
//                    "GNR3.Address.Name as AddressName", "Details as Address", "Phone", "SLS3.Order.CreationDate", "DeliveryDate",
//                ])
//                ->where('SLS3.Order.InventoryRef', 1)
//                ->where('SLS3.Order.State', 2)
//                ->where('SLS3.Order.FiscalYearRef', 1403)
//                ->orderBy('SLS3.Order.OrderID')
//                ->get()->unique()->toArray();
//
//            $dat2 = array_values($dat2);
//
//            foreach ($dat2 as $item) {
//                $item->{'type'} = 'Order';
//                $item->{'ok'} = 0;
//                $item->{'noodElite'} = '';
//                $noodElite = 0;
//                $details = DB::connection('sqlsrv')->table('SLS3.OrderItem')
//                    ->select("SLS3.Product.Name as ProductName", "Quantity", "SLS3.Product.ProductID as Id", "SLS3.Product.Number as ProductNumber",
//                        //"SLS3.Product.SecondCode",
//                     //   "SLS3.OrderItem.MajorUnitQuantity", "SLS3.OrderItem.InitialQuantity", "SLS3.OrderItem.MajorUnitInitialQuantity"
//                     //
//                        )
//                    ->join('SLS3.Product', 'SLS3.Product.ProductID', '=', 'SLS3.OrderItem.ProductRef')
//                    ->where('OrderRef', $item->{'OrderID'})->get();
//
//                $item->{'OrderItems'} = $details;
//                foreach ($details as $it) {
//                    if (str_contains($it->{'ProductName'}, 'نودالیت')) {
////                        if(str_contains($it->{'ProductName'},'پک 5 ع')){
//                        $noodElite += $it->{'Quantity'};
////                        }else{
////                            $noodElite+= $it->{'Quantity'};
////                        }
//                    }
//                }
//                $item->{'noodElite'} = $noodElite;
//
//                if ($noodElite >= 50) {
//                    $item->{'ok'} = 1;
//                }
//            }
//
//            $filtered2 = array_filter($dat2, function ($el) {
//                return $el->{'ok'} == 1;
//            });
//
            $input1 = array_values($filtered);
            $offset = 0;
            $perPage = 100;
//
//            $input2 = array_values($filtered2);


//            if (!$request['type'] || $request['type'] == ''){
//                $input = array_merge($input2,$input1);
//            }
//              if ($request['type'] && $request['type'] == 'Order'){
//                $input = $input2;
//            }
//              if ($request['type'] && $request['type'] == 'InventoryVoucher'){
//                $input = $input1;
//            }
            $input = $input1;


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
            $dat = DB::connection('sqlsrv')->table('LGS3.Part')->select('PartID as ProductID', 'Name', 'PropertiesComment as Description', 'Code as Number')->where('Code', $id)->first();

//            if ($part == 'InventoryVoucher'){
//                $dat = DB::connection('sqlsrv')->table('LGS3.Part')->select('PartID as ProductID', 'Name', 'PropertiesComment as Description', 'Code as Number')->where('Code', $id)->first();
//            }elseif ($part == 'Order'){
//                $dat = DB::connection('sqlsrv')->table('SLS3.Product')->select('ProductID', 'Name', 'Description', 'Number')->where('Number', $id)->first();
//            }
            return response()->json($dat, 200);

        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function fix(Request $request)
    {

    }

}
