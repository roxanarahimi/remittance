<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InvoiceBarcodeResource;
use App\Models\InvoiceBarcode;
use App\Models\InvoiceItem;
use Dotenv\Validator;
use Faker\Core\Barcode;
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
            $data = InvoiceBarcode::orderByDesc('id')->get();
            return response(InvoiceBarcodeResource::collection($data), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function show($id)
    {
        try {
            $invoiceBarcode = InvoiceBarcode::find($id);
            return response(new InvoiceBarcodeResource($invoiceBarcode), 200);
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
            ->get();
        return $invoiceItemId;
        $myfile = fopen('../storage/logs/failed_data_entries/' . $request['OrderNumber'] . ".log", "w") or die("Unable to open file!");
        $txt = json_encode([
            'OrderNumber' => $request['OrderNumber'],
            'OrderItems' => $request['OrderItems'],
            "invoice_item_id" => $request['invoice_item_id'],
        ]);
        fwrite($myfile, $txt);
        fclose($myfile);

        $str = str_replace(' ', '', str_replace('"', '', $request['OrderItems']));
        $orderItems = explode(',', $str);
        try {
            foreach ($orderItems as $item) {
                InvoiceBarcode::create([
                    "invoice_item_id" => $request['invoice_item_id'],
                    "Barcode" => $item,
                ]);
            }
            $invoiceBarcodes = InvoiceBarcode::orderByDesc('id')
                ->where('invoice_item_id',$request['invoice_item_id'])
                ->get();
            return response(InvoiceBarcodeResource::collection($invoiceBarcodes), 201);
        } catch (\Exception $exception) {
            return $exception;
            for ($i = 0; $i < 3; $i++) {
                try {
                    foreach ($orderItems as $item) {
                        InvoiceBarcode::create([
                            "orderID" => $request['OrderID'],
                            "addressName" => $request['name'],
                            "barcode" => str_replace(' ', '', str_replace('"', '', $item)),
                        ]);
                    }
                    $invoiceBarcodes = InvoiceBarcode::orderByDesc('id')->where('orderID', $request['OrderID'])->get();
                    if (count($invoiceBarcodes) == count($orderItems)) {
                        $i = 3;
                        return response(InvoiceBarcodeResource::collection($invoiceBarcodes), 201);
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

    public function update(Request $request, InvoiceBarcode $invoiceBarcode)
    {
        $validator = Validator::make($request->all('title'),
            [
//              'title' => 'required|unique:InvoiceBarcodes,title,' . $invoiceBarcode['id'],
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
            $invoiceBarcode->update($request->all());
            return response(new InvoiceBarcode($invoiceBarcode), 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

    public function destroy(InvoiceBarcode $invoiceBarcode)
    {

        try {
            $invoiceBarcode->delete();
            return response('InvoiceBarcode deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

}
