<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InvoiceItemResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceResource2;
use App\Http\Resources\RemittanceResource;
use App\Models\Invoice;
use App\Models\Remittance;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;


class InvoiceController extends Controller
{
    public function __construct(Request $request)
    {
        $this->middleware(Token::class)->except('readOnly1');
    }

    public function index(Request $request)
    {
        try {
            $data = Invoice::orderByDesc('id')->get();
            return response(InvoiceResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function filter(Request $request)
    {
        try {
            $perPage = 100;
            $page = max(1, (int)$request->input('page', 1));

            // Build base query
            $query = Invoice::where('Type', '!=', 'Order')->orderByDesc('id');

            // Apply order number filter
            if ($request->filled('OrderNumber')) {
                $query->where('OrderNumber', $request['OrderNumber']);
            }
            // Apply date filter if both start and end dates are provided
            if ($request->filled(['StartDate', 'EndDate'])) {
                $start = (new DateController)->jalali_to_gregorian($request->input('StartDate')) . ' 00:00:00';
                $end = (new DateController)->jalali_to_gregorian($request->input('EndDate')) . ' 23:59:59';
                $query->whereBetween('created_at', [$start, $end]);
            }


            // Fetch and transform data in a single step
            $filteredData = InvoiceResource2::collection($query->get())->toArray($request);

            // Filter out items where 'Difference' is zero
            if ($request->filled(['StartDiff', 'EndDiff'])) {
                $f = array_filter($filteredData, function ($item) use ($request) {

                    return ((integer)$item['Difference'] >= (integer)$request['StartDiff'] && (integer)$item['Difference'] <= (integer)$request['EndDiff']);

//                else{
//                    return $item['Difference'] != 0;
//                }
                });
                $filteredData = array_values($f);
            }

            // Paginate the filtered data
            $paginator = new LengthAwarePaginator(
                array_slice($filteredData, ($page - 1) * $perPage, $perPage),
                count($filteredData),
                $perPage,
                $page,
                ['path' => $request->url()]
            );

            return response()->json($paginator, 200);
            //-------------------------------------------------------------------------------------
            $dataQuery = Invoice::where('Type', '!=', 'Order')->orderByDesc('id');

            if ($request->filled('StartDate') && $request->filled('EndDate')) {
                $start = (new DateController)->jalali_to_gregorian($request->input('StartDate')) . ' 00:00:00';
                $end = (new DateController)->jalali_to_gregorian($request->input('EndDate')) . ' 23:59:59';
                $dataQuery->whereBetween('created_at', [$start, $end]);
            }

            if ($request->filled('OrderNumber')) {
                $dataQuery->where('OrderNumber', $request->input('OrderNumber'));
            }

// Fetch data once instead of reassigning multiple times
            $data = InvoiceResource2::collection($dataQuery->get())->toArray($request);

// Filter only necessary data before pagination
            $filteredData = array_values(array_filter($data, fn($item) => $item['Difference'] != 0));

// Pagination
            $perPage = 100;
            $page = max(1, (int)$request->input('page', 1));
            $paginator = new LengthAwarePaginator(
                array_slice($filteredData, ($page - 1) * $perPage, $perPage),
                count($filteredData),
                $perPage,
                $page,
                ['path' => $request->url()]
            );

            return response()->json($paginator, 200);
//--------------------------------------------------------------------
//
//            $data = Invoice::orderByDesc('id')->where('Type', '!=', 'Order');
//
//            if (isset($request['StartDate'])) {
//
//                $s = (new DateController)->jalali_to_gregorian($request['StartDate']);
//                $e = (new DateController)->jalali_to_gregorian($request['EndDate']);
//
//                $data = $data->whereBetween('created_at', [$s . ' 00:00:00', $e . ' 23:59:59']);
//
//            }
//
//            if (isset($request['OrderNumber'])) {
//                $data = $data->where('OrderNumber', $request['OrderNumber']);
//            }
//
//            $data = $data->get();
//            $info = InvoiceResource2::collection($data);
////
//            $infoo = array_filter(json_decode($info->toJson(), true), function ($element) {
//                return $element['Difference'] != 0;
//            });
//            $infooo = array_values($infoo);
//
//            $offset = 0;
//            $perPage = 100;
//            if ($request['page'] && $request['page'] > 1) {
//                $offset = ($request['page'] - 1) * $perPage;
//            }
//            $info = array_slice($infooo, $offset, $perPage);
//            $paginator = new LengthAwarePaginator($info, count($infooo), $perPage, $request['page']);
//            return response()->json($paginator, 200);

        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function show(Invoice $invoice)
    {
        try {
            return response(new InvoiceResource($invoice), 200);
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
                Invoice::create([
                    "orderID" => $request['OrderID'],
                    "addressName" => $request['name'],
                    "barcode" => $item,
                ]);
            }
            $invoices = Invoice::orderByDesc('id')->where('orderID', $request['OrderID'])->get();
            return response(InvoiceResource::collection($invoices), 201);
        } catch (\Exception $exception) {
            for ($i = 0; $i < 3; $i++) {
                try {
                    foreach ($orderItems as $item) {
                        Invoice::create([
                            "orderID" => $request['OrderID'],
                            "addressName" => $request['name'],
                            "barcode" => str_replace(' ', '', str_replace('"', '', $item)),
                        ]);
                    }
                    $invoices = Invoice::orderByDesc('id')->where('orderID', $request['OrderID'])->get();
                    if (count($invoices) == count($orderItems)) {
                        $i = 3;
                        return response(InvoiceResource::collection($invoices), 201);
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

    public function update(Request $request, Invoice $invoice)
    {
        $validator = Validator::make($request->all('title'),
            [
//              'title' => 'required|unique:Invoices,title,' . $invoice['id'],
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
            $invoice->update($request->all());
            return response(new InvoiceResource($invoice), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function destroy(Invoice $invoice)
    {

        try {
            $invoice->delete();
            return response('Invoice deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }
}
