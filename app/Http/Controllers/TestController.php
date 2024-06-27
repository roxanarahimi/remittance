<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\TestResource;
use App\Models\Test;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(Request $request)
    {
        $this->middleware(Token::class);
    }

    public function index(Request $request)
    {
        try {
            $data = Test::orderByDesc('id')->get();
            return response(TestResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function show($id)
    {
        try {
            $test = Test::find($id);
            return response(new TestResource($test), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function store(Request $request)
    {
        $invoiceItemId = InvoiceItem::where('ProductID', $request['ProductID'])
            ->with('invoice')
            ->whereHas('invoice',function($q) use ($request) {
                $q->where('Type',$request['Type'])->where('OrderNumber',$request['OrderNumber']);
            })
            ->first()->id;

        $myfile = fopen('../storage/logs/failed_data_entries/' . $request['OrderNumber'] . ".log", "w") or die("Unable to open file!");
        $txt = json_encode([
            'OrderNumber' => $request['OrderNumber'],
            'Barcodes' => $request['Barcodes'],
            "invoice_item_id" => $invoiceItemId,
        ]);
        fwrite($myfile, $txt);
        fclose($myfile);

        $str = str_replace(' ', '', str_replace('"', '', $request['Barcodes']));
        $barcodes = explode(',', $str);
        try {
            foreach ($barcodes as $item) {
                Test::create([
                    "invoice_item_id" => $invoiceItemId,
                    "Barcode" => $item,
                ]);
            }
            $tests = Test::orderByDesc('id')
                ->whereIn('Barcode',$barcodes)
                ->get();
            return response(TestResource::collection($tests), 201);
        } catch (\Exception $exception) {
            return $exception;
            for ($i = 0; $i < 3; $i++) {
                try {
                    foreach ($barcodes as $item) {
                        Test::create([
                            "invoice_item_id" => $invoiceItemId,
                            "Barcode" => $item,
                        ]);
                    }
                    $tests = Test::orderByDesc('id')->where('orderID', $request['OrderID'])->get();
                    if (count($tests) == count($barcodes)) {
                        $i = 3;
                        return response(TestResource::collection($tests), 201);
                    }
                } catch (\Exception $exception) {
                    return response(['message' =>
                        'خطای پایگاه داده. لطفا کد '
                        . $request['OrderNumber'] .
                        ' را یادداشت کرده و جهت ثبت بارکد ها به پشتیبانی اطلاع دهید'], 500);
                }
            }
        }


    }

    public function update(Request $request, Test $test)
    {
        $validator = Validator::make($request->all('title'),
            [
//              'title' => 'required|unique:Tests,title,' . $test['id'],
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
            $test->update($request->all());
            return response(new TestResource($test), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function destroy(Test $test)
    {

        try {
            $test->delete();
            return response('Test deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

}
