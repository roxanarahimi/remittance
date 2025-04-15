<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Token;
use App\Http\Resources\InvoiceBarcodeResource;
use App\Models\InvoiceBarcode;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceBarcodeController extends Controller
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
            $barIds = [];
            foreach ($barcodes as $item) {
                $bar =  InvoiceBarcode::create([
                    "invoice_id" => $request['id'],
                    "Barcode" => $item,
                ]);
                $barIds[] = $bar->id;
            }
            $info = InvoiceBarcode::orderByDesc('id')
                ->whereIn('id', $barIds)
                ->get();
            return response(InvoiceBarcodeResource::collection($info), 201);
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
                        InvoiceBarcode::create([
                            "invoice_id" => $request['id'],
                            "Barcode" => $item,
                        ]);
                    }
                    $info = InvoiceBarcode::orderByDesc('id')->where('invoice_id', $request['id'])->get();
                    if (count($info) == count($barcodes)) {
                        $i = 3;
                        return response(InvoiceBarcodeResource::collection($info), 201);
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

    public function destroy($id)
    {

        try {
            $invoiceBarcode = InvoiceBarcode::find($id);
            if(!$invoiceBarcode){
                return response('InvoiceBarcode dose not exist', 422);
            }
            $invoiceBarcode->delete();
            return response('InvoiceBarcode deleted', 200);
        } catch (\Exception $exception) {
            return response($exception);
        }
    }

}
