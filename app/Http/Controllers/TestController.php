<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InvoiceBarcodeResource;
use App\Http\Resources\TestResource;
use App\Models\Invoice;
use App\Models\InvoiceAddress;
use App\Models\InvoiceBarcode;
use App\Models\InvoiceProduct;
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
//             Invoice::all()->each->delete();
//             InvoiceItem::all()->each->delete();
//             InvoiceProduct::all()->each->delete();
//             InvoiceAddress::all()->each->delete();
//             InvoiceBarcode::all()->each->delete();
//             Test::all()->each->delete();
//
//             return Invoice::all();
//            (new CacheController)->cacheProducts();
//            return InvoiceProduct::select('id','ProductName as Name','ProductNumber','Description')->get();

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
        try {
            $myfile = fopen('../storage/logs/failed_data_entries/' . $request['id'] . ".log", "w") or die("Unable to open file!");
            $txt = json_encode([
                "invoice_id" => $request['id'],
                'Barcodes' => $request['Barcodes'],
            ]);
            fwrite($myfile, $txt);
            fclose($myfile);
            $str = str_replace(' ', '', str_replace('"', '', $request['Barcodes']));
            $barcodes = explode(',', $str);
            foreach ($barcodes as $item) {
                Test::create([
                    "invoice_id" => $request['id'],
                    "Barcode" => $item,
                ]);
            }
            $info = Test::orderByDesc('id')
                ->whereIn('Barcode', $barcodes)
                ->get();
            return response(TestResource::collection($info), 201);
        } catch (\Exception $exception) {
//            $myfile = fopen('../storage/logs/failed_data_entries/' . $request['id'] . ".log", "w") or die("Unable to open file!");
//            $txt = json_encode([
//                "invoice_id" => $request['id'],
//                'Barcodes' => $request['Barcodes'],
//            ]);
//            fwrite($myfile, $txt);
//            fclose($myfile);
            for ($i = 0; $i < 3; $i++) {
                try {
                    foreach ($barcodes as $item) {
                        Test::create([
                            "invoice_id" => $request['id'],
                            "Barcode" => $item,
                        ]);
                    }
                    $info = Test::orderByDesc('id')->where('invoice_item_id', $request['id'])->get();
                    if (count($info) == count($barcodes)) {
                        $i = 3;
                        return response(TestResource::collection($info), 201);
                    }
                } catch (\Exception $exception) {
                    return response(['message' =>
                        'خطای پایگاه داده. لطفا کد '
                        . $request['id'] .
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
