<?php


namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InventoryVoucherResource;
use App\Http\Resources\RemittanceResource;
use App\Models\InventoryVoucher;
use App\Models\InventoryVoucherItem;
use App\Models\Part;
use App\Models\Remittance;
use http\Env\Response;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
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
            $search = 'نودالیت';

            $x = InventoryVoucher::select("LGS3.InventoryVoucher.InventoryVoucherID", "LGS3.InventoryVoucher.Number",
                "LGS3.InventoryVoucher.CreationDate", "Date as DeliveryDate", "CounterpartStoreRef")
                ->join('LGS3.Store', 'LGS3.Store.StoreID', '=', 'LGS3.InventoryVoucher.CounterpartStoreRef')
                ->join('LGS3.Plant', 'LGS3.Plant.PlantID', '=', 'LGS3.Store.PlantRef')
                ->join('GNR3.Address', 'GNR3.Address.AddressID', '=', 'LGS3.Plant.AddressRef')
                ->whereNot('LGS3.Store.Name', 'LIKE', "%گرمدره%")//68, 69
                ->whereNot('GNR3.Address.Details', 'LIKE', "%گرمدره%")//68, 69
                ->where('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', '=', 68)//68, 69
                ->orWhere('LGS3.InventoryVoucher.InventoryVoucherSpecificationRef', '=', 69)//68, 69
                ->where('LGS3.InventoryVoucher.FiscalYearRef', 1403)
                ->where('LGS3.InventoryVoucher.CounterpartStoreRef')
                ->with(['OrderItems.Part' => function($q) {
                    $q->whereHas('Name', function($query) {
                        $query->where('Name', 'LIKE', '%نودالیت%');
                    });
                }])
                ->orderByDesc('LGS3.InventoryVoucher.InventoryVoucherID')
                ->take(100)->get();
//            $x = InventoryVoucherResource::collection($x);
            return response()->json(InventoryVoucherResource::collection($x), 200);
            $offset = 0;
            $perPage = 100;
            $input1 = $x;
            $input = $input1;
            if ($request['page'] && $request['page'] > 1) {
                $offset = ($request['page'] - 1) * $perPage;
            }
            $info = array_slice($input, $offset, $perPage);

            $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);
            return response()->json($paginator, 200);


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

    }

}
