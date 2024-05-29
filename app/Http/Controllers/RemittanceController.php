<?php


namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\RemittanceResource;
use App\Models\Remittance;
use http\Env\Response;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;
use Illuminate\Pagination\Paginator;
use function Laravel\Prompts\select;
use Illuminate\Support\Facades\Redis;

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
//        $info = Redis::get($request['OrderID']);
//        if (isset($info)){
//            Redis::set($request['OrderID'], $info.$data);
//        }else{
            Redis::set($request['OrderID'], $data);
//        }
        $value = Redis::get($request['OrderID']);
        $json = json_decode($value);
        $id = $json->{'OrderID'};
        $items = explode(',', $json->{'OrderItems'});
        $name = $json->{'name'};

        $orderItems = explode(',', $request['OrderItems']);
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
                            "barcode" => $item,
                        ]);
                    }
                    $remittances = Remittance::orderByDesc('id')->where('orderID', $request['OrderID'])->get();
                    if (count($remittances) == count($orderItems)) {
                        $i = 3;
                        return response(RemittanceResource::collection($remittances), 201);
                    }
                } catch (\Exception $exception) {
                    $myfile = fopen('../storage/logs/failed_data_entries/' . $request['OrderID'] . ".log", "w") or die("Unable to open file!");
                    $txt = json_encode([
                        'OrderID' => $id,
                        'name' => $name,
                        'OrderItems' => $items
                    ]);
                    fwrite($myfile, $txt);
                    fclose($myfile);
                    return response(['message' => 'خطای پایگاه داده. لطفا نام حواله را یادداشت کرده و جهت ثبت حواله به پشتیبانی اطلاع دهید'], 500);
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

    public function readOnly(Request $request)
    {
        try {



/// real place
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
                        "LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity", "LGS3.Part.PartID as Id",
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
                        "LGS3.Part.Name as ProductName", "LGS3.InventoryVoucherItem.Quantity as Quantity","LGS3.InventoryVoucherItem.Barcode as Barcode", "LGS3.Part.PartID as Id",
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

    public function showProduct($id)
    {
        try {
            $dat = DB::connection('sqlsrv')->table('LGS3.Part')->select('PartID as ProductID', 'Name', 'PropertiesComment as Description', 'Code as Number')->where('Code', $id)->first();
//            $dat = DB::connection('sqlsrv')->table('SLS3.Product')->select('ProductID', 'Name', 'Description', 'Number')->where('Number', $id)->first();
            return response()->json($dat, 200);

        } catch (\Exception $exception) {
            return response($exception);
        }
    }
 public function fix(Request $request)
    {
        try {


            $data = [
                [
                    "id"=> "130",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003707",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "129",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003651",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "128",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003701",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "127",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003942",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "126",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003681",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "125",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003592",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "124",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003657",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "123",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003744",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "122",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003696",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "121",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003686",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "120",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003710",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "119",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003676",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "118",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003688",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "117",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003661",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "116",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003595",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "115",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003711",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "114",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003652",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "113",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003597",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "112",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003668",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "111",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003658",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "110",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003666",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "109",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003743",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "108",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003601",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "107",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003664",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "106",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003695",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "105",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003753",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "104",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003704",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "103",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003700",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "102",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003663",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "101",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "701031835101800000058A22004000003653",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "100",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005455",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "99",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005447",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "98",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005548",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "97",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005562",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "96",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005540",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "95",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005545",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "94",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005558",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "93",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005557",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "92",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005539",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "91",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005560",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "90",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005417",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "89",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005407",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "88",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005523",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "87",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005508",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "86",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005537",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "85",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005513",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "84",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005499",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "83",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005498",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "82",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005503",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "81",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005504",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "80",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005543",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "79",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005554",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "78",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005549",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "77",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005555",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "76",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005530",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "75",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005556",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "74",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005494",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "73",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005564",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "72",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005565",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "71",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005568",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "70",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005509",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "69",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005533",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "68",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005495",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "67",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005566",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "66",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005522",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "65",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005521",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "64",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005484",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "63",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005485",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "62",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005563",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "61",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005528",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "60",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005519",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "59",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005526",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "58",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005507",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "57",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005542",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "56",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005515",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "55",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005525",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "54",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005541",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "53",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005506",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "52",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005520",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],
    [
        "id"=> "51",
        "orderID"=> "191137",
        "addressName"=> "انبار فروش بابل137",
        "barcode"=> "7011762405090000000067B1000800005534",
        "created_at"=> "2024-05-29",
        "updated_at"=> "2024-05-29"
    ],




            ];

                foreach ($data as $item) {
                    Remittance::create([
                        "orderID"=> "191137",
                        "addressName"=> "انبار فروش بابل137",
                        "barcode" => $item['barcode'],
                    ]);
                }
            return $data;

        } catch (\Exception $exception) {
            return response($exception);
        }
    }

}
