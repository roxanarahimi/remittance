<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InvoiceBarcodeResource;
use App\Models\InvoiceBarcode;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Validator;
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
                InvoiceBarcode::create([
                    "invoice_item_id" => $invoiceItemId,
                    "Barcode" => $item,
                ]);
            }
            $invoiceBarcodes = InvoiceBarcode::orderByDesc('id')
                ->whereIn('Barcode',$barcodes)
                ->get();
            return response(InvoiceBarcodeResource::collection($invoiceBarcodes), 201);
        } catch (\Exception $exception) {
            for ($i = 0; $i < 3; $i++) {
                try {
                    foreach ($barcodes as $item) {
                        InvoiceBarcode::create([
                            "invoice_item_id" => $invoiceItemId,
                            "Barcode" => $item,
                        ]);
                    }
                    $invoiceBarcodes = InvoiceBarcode::orderByDesc('id')->where('orderID', $request['OrderID'])->get();
                    if (count($invoiceBarcodes) == count($barcodes)) {
                        $i = 3;
                        return response(InvoiceBarcodeResource::collection($invoiceBarcodes), 201);
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
            return response(new InvoiceBarcodeResource($invoiceBarcode), 200);
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
