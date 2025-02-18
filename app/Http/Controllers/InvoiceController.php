<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InvoiceResource;
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
        try {//where('CreationDate', '>=', today()->subDays(2))->
            $data = Invoice::where('created_at', '>=', today()->subWeeks(2))->withCount('Barcodes')->get();
                        return response($data, 200);

//            $data = Remittance::where('orderID','284128')->get();
//            return response(["count"=>count($data),"data"=>RemittanceResource::collection($data)], 200);

//            $data = Invoice::orderByDesc('id');
            if ($request['StartDate']) {
//                return $request['StartDate'];
                return (new DateController)->toGREGORIAN($request['StartDate']);
            }
            if ($request['EndDate']) {

            }
            if ($request['OrderNumber']) {

            }

//            $data = $data->get();
//            return response(InvoiceResource::collection($data), 200);
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
