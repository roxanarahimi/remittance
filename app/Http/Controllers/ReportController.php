<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceBarcodeResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceResource2;
use App\Http\Resources\RemittanceResource;
use App\Models\InventoryVoucher;
use App\Models\Invoice;
use App\Models\InvoiceBarcode;
use App\Models\InvoiceItem;
use App\Models\OrderItem;
use App\Models\Remittance;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function fix(Request $request)
    {
        $x= InventoryVoucher::where('InventoryVoucherID','321009')
            ->with('OrderItems')
            ->first();
        return $x;
        $rrr = DB::table('remittances')
            ->select('OrderID', DB::raw('COUNT(*) as count'))
            ->groupBy('OrderID')
//            ->having('count', '>', 1)
//            ->pluck('OrderID');
            ->get();
        return $rrr;
        $r = Remittance::orderBy('id')->with('invoice')->paginate('200');
        return $r;
        $rs = DB::table('remittances')
            ->select('orderID',  DB::raw('COUNT(*) as count'))
            ->groupBy('orderID')
//            ->having('count', '>', 1)
            ->where('invoice_id', null)
            ->get();
//        return $rs;
        $rs->each(function ($r){
            $invoice= Invoice::where('OrderID',$r->orderID)->first();
            $b = Remittance::where('orderID',$r->orderID)->get();
            $b->each(function ($u) use ($invoice) {
                $u->update(['invoice_id'=>$invoice->id]);
            });
        });

        return 'ok';


        $duplicates = DB::table('invoices')
            ->select('OrderID', 'OrderNumber', 'Type', DB::raw('COUNT(*) as count'))
            ->groupBy('OrderID', 'OrderNumber', 'Type')
            ->having('count', '>', 1)
//            ->pluck('OrderID');
            ->get();

        $all = InvoiceItem::has('invoice', '=', 0)->get();

        $rd = DB::table('remittances')
            ->select('orderID', 'OrderNumber', 'barcode', DB::raw('COUNT(*) as count'))
            ->groupBy('orderID', 'OrderNumber', 'barcode')
            ->having('count', '>', 1)
            ->get();

        return ['iitems:'=>$all, 'invoice dd'=>$duplicates,'rr'=>$rd];

//        $all->each(function ($invoice) {
//            $invoice->invoiceItems->each->delete(); // delete each InvoiceItem
//            $invoice->delete();              // delete the Invoice
//        });
        return $all;
        return [$duplicates, count($duplicates)];

        foreach ($duplicates as $dup) {
            $d = Invoice::select('*')
                ->where('OrderID', $dup->OrderID)
                ->whereHas('rrBarcodes')
                ->with('barcodes')
                ->get();
            return $d;
            if ($d[1]->barcodes->count() == 0) {
                $d[1]->delete();
            }
        }

        return 'ok';
        $d = Invoice::select('*')
            ->whereIn('OrderID', $duplicates)
            ->orderBy('OrderID')
//            ->wherehas('barcodes')
            ->whereHas('rrBarcodes')
//            ->with('barcodes')
//            ->with('rrBarcodes')
            ->paginate(100);
        return $d;

        $duplicates = DB::table('invoices')
            ->select('OrderID', 'OrderNumber', 'Type', DB::raw('COUNT(*) as count'))
            ->groupBy('OrderID', 'OrderNumber', 'Type')
            ->having('count', '>', 1)
            ->get();


        return $duplicates;


        $all = InvoiceItem::has('invoice', '=', 1)
            ->take(100)->get();

//        $all->each(function ($invoice) {
//            $invoice->invoiceItems->each->delete(); // delete each InvoiceItem
//            $invoice->delete();              // delete the Invoice
//        });
        return $all;

        $duplicates = DB::table('remittances')
            ->select('orderID', 'OrderNumber', 'barcode', DB::raw('COUNT(*) as count'))
            ->groupBy('orderID', 'OrderNumber', 'barcode')
            ->having('count', '>', 1)
            ->get();
        return [$duplicates, count($duplicates)];
        foreach ($duplicates as $item) {
//        $item = $duplicates['0'];
            $x = Remittance::orderBy('id')
                ->where('orderID', $item->orderID)
                ->where('OrderNumber', $item->OrderNumber)
                ->where('barcode', $item->barcode)
                ->get();
//            return $x[1]->delete();
            foreach ($x as $d) {
                if ($d->id != $x[0]->id) {
                    $d->delete();
                }
            }

        }
        $duplicates = DB::table('remittances')
            ->select('orderID', 'OrderNumber', 'barcode', DB::raw('COUNT(*) as count'))
            ->groupBy('orderID', 'OrderNumber', 'barcode')
            ->having('count', '>', 1)
            ->get();
        return $duplicates;
        return [count($x), $x[0]];

        // Step 1: Subquery to get the duplicate keys (grouped)
        $duplicateKeys = DB::table('invoices')
            ->select('OrderID', 'OrderNumber', 'Type')
            ->groupBy('OrderID', 'OrderNumber', 'Type')
            ->where('Type', '!=', 'Order')
            ->havingRaw('COUNT(*) > 1');

// Step 2: Join back to original table to get full rows including 'id'
        $duplicates = DB::table('invoices')
            ->orderBy('OrderID')
            ->joinSub($duplicateKeys, 'dupes', function ($join) {
                $join->on('invoices.OrderID', '=', 'dupes.OrderID')
                    ->on('invoices.OrderNumber', '=', 'dupes.OrderNumber')
                    ->on('invoices.Type', '=', 'dupes.Type');
            })
            ->select('invoices.*') // includes 'id' and all other columns
            ->pluck('id');
//            ->get();
//        return $duplicates;


        try {
            $os = DB::table('remittances')
                ->select('orderID', 'OrderNumber', DB::raw('count(*) as total'))
                ->groupBy('orderID', 'OrderNumber')
                ->get()->toArray();
//        return $os;
            foreach ($os as $item) {
                $ON = Invoice::where('OrderID', $item->orderID)->where('Type', '!=', 'Order')->first();
                $item->checkON = $ON->OrderNumber;
//               $rs = Remittance::where('orderID',$item->orderID)->get();
//               $rs->each(function($item2) use ($ON) {
//                   $item2->update(['OrderNumber' => $ON->OrderNumber]);
//               });
            }
            return $os;
        } catch (\Exception $exception) {
            return $exception;
        }


//        $os = DB::table('remittances')
//            ->select('OrderNumber', DB::raw('count(*) as total'))
//            ->groupBy('OrderNumber')
//            ->pluck('OrderNumber');
//        return $os;

        $os = DB::table('remittances')
            ->select('OrderNumber', DB::raw('count(*) as total'))
            ->groupBy('OrderNumber')
//            ->whereHas('invoices')
            ->get();

        $os = Invoice::orderBy('id')
            ->has('invoices', '=', 2)
            ->with('invoices')
            ->take(1000)->get()->unique();
        return $os;

        $is = Invoice::select('OrderNumber', 'OrderID')
            ->orderBy('OrderNumber')
            ->WhereIn('Type', ['Deputation', 'InventoryVoucher'])
            ->WhereIn('OrderNumber', $os)
            ->WhereHas('rrBarcodes')
            ->with('rrBarcodes')
//            ->take(100)
            ->get();

        return $is;


        $os = DB::table('remittances')
            ->select('OrderNumber', DB::raw('count(*) as total'))
            ->groupBy('OrderNumber')
//            ->whereHas('invoices')
            ->get();
        return $os;
//        $os = DB::table('remittances')
//            ->select('OrderNumber','barcode')
//            ->where('OrderNumber',137)
////            ->where('barcode','701031835101800000058A22004000003652')
//            ->get();
        return count($os);

//            ->where('invoice_id', null)

//        return $os;
//        return count($os);
//        $oss = Remittance::whereIn('OrderNumber', $os)
//            ->select('OrderNumber','orderID')
//            ->orderBy('OrderNumber')
//            ->take(2000)
//            ->get();
//        return $oss;
        $is = Invoice::select('OrderNumber', 'OrderID')
            ->orderBy('OrderNumber')
//            ->Where('OrderNumber','100214')
            ->WhereIn('Type', ['Deputation', 'InventoryVoucher'])
            ->WhereIn('OrderNumber', $os)
            ->WhereHas('rrBarcodes')
            ->with('rrBarcodes')
            ->take(100)
            ->get();
        $is = Invoice::select('OrderNumber', 'OrderID')
            ->orderBy('OrderNumber')
            ->WhereIn('Type', ['Deputation', 'InventoryVoucher'])
//            ->WhereIn('OrderNumber',$os)
//            ->with('rrBarcodes')
            ->whereDoesntHave('invoiceItems')
            ->with('invoiceItems')
            ->take(100)
            ->get();
        return $is;
        return [count($os), count($is)];


        foreach ($os as $OrderNumber) {
            $r = Remittance::where('OrderNumber', $OrderNumber)->get();
//            return $r[0]['orderID'];
            $invoice = Invoice::where('OrderNumber', $OrderNumber)
                ->where('OrderID', $r->toArray()[0]['orderID'])->first();
            if ($invoice != null) {
                foreach ($r as $item) {
                    $item->update(['invoice_id' => $invoice['id']]);
                }
            }


        }
        $info = Remittance::orderBy('id')->paginate(200);
        return $info;


        $os = DB::table('remittances')
            ->select('orderID', DB::raw('count(*) as total'))
            ->groupBy('orderID')
            ->where('OrderNumber', null)
            ->pluck('orderID');

//        $info = Remittance::orderBy('id')->get();

        foreach ($os as $orderID) {
            $info = Remittance::where('orderID', $orderID)->get();
            foreach ($info as $item) {
                $x = $item['addressName'];
                $y = explode(' ', $x);
                $orderNumber = $y[count($y) - 1];
                $item->update(['OrderNumber' => $orderNumber]);
            }
        }

        $n = Remittance::orderBy('id')->paginate(200);
        return RemittanceResource::collection($n);
//        $x = 'شرکت مهرگان کاوه هیرکان 39432';
//        $y = explode(' ',$x);
//        $orderNumber =  $y[count($y)-1];
//        $info = Remittance::orderByDesc('id');
        if (isset($request['OrderNumber'])) {
            $info = $info->where('OrderID', $request['OrderID']);
        }
        if (isset($request['search'])) {
            $info = $info->where('barcode', 'like', '%' . $request['search'] . '%');
        } else {
            $info = $info->take(500);
        }
        $info = $info->get();

        return RemittanceResource::collection($info);


//        $dat = Part::where('Name', 'نودالیت قارچ و پنیر آماده لذیذ')->get();
        $dat = Invoice::where('OrderNumber', "6536")->first();
        $dat2 = InvoiceBarcode::where('invoice_id', $dat['id'])->paginate(100);
        return response($dat2, 200);

        return response([count($dat->barcodes), new InvoiceResource($dat)], 200);


        $dat = InventoryVoucher::where('Number', "8659")
            ->with('OrderItems', function ($q) {
                $q->with('Part');
            })
            ->get();
        return $dat;

        $invoice = Invoice::create([
            'Type' => 'Deputation',
            'OrderID' => "000001",
            'OrderNumber' => "1149071",
            'AddressID' => "119558",
            'Sum' => "10000",
            'DeliveryDate' => now(),
        ]);

        $invoiceItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'ProductNumber' => "7010301351",
            'Quantity' => "5000",
        ]);

        $invoiceItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'ProductNumber' => "7010302351",
            'Quantity' => "5000",
        ]);


        return response(new InvoiceResource($invoice), 200);
        $x = 100 - 200;//-100

        $t = (integer)$request['ss'] >= $x;
        return (boolean)$t;
//        $info = Invoice::where('Sum', 0)->get();
        $invoice = Invoice::where('OrderID', '4277467')->with('invoiceItems')->first();
        return $invoice;
        $dat = OrderItem::where('OrderRef', $invoice->OrderID)
            ->where('OrderRef', $invoice->OrderID)
            ->get();

        foreach ($dat as $item2) {

            if (!str_contains($item2->Product->Name, 'لیوانی') && str_contains($item2->Product->Name, 'نودالیت')) {
                $invoiceItem = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'ProductNumber' => $item2->Product->Number,
                    'Quantity' => $item2->Quantity,
                ]);
            }

        }

        return InvoiceResource::collection($invoice);


        foreach ($info as $item) {
            $dat = OrderItem::where('OrderRef', $info->OrderID)->get();
            foreach ($dat as $item2) {
                if (!str_contains($item2->Product->Name, 'لیوانی') && str_contains($item2->Product->Name, 'نودالیت')) {
                    $invoiceItem = InvoiceItem::create([
                        'invoice_id' => $item->id,
                        'ProductNumber' => $item2->Product->Number,
                        'Quantity' => $item2->Quantity,
                    ]);
                }
            }
            $item->update(["Sum" => $item->invoiceItems->sum('Quantity')]);
        }
        return InvoiceResource::collection($info);
        return $info;

        $tt = Invoice::orderByDesc('id')->get();
        foreach ($tt as $item) {
            $item->update(["Sum" => $item->invoiceItems->sum('Quantity')]);
        }
        return 'Done';
//        if (isset($request['StartDate'])){
//            $s = (new DateController)->jalali_to_gregorian($request['StartDate']);
//            $e = (new DateController)->jalali_to_gregorian($request['EndDate']);
//
////            $data = $data->where('created_at', '>=', $s)
////                ->where('created_at', '<=', $e);
//
//            return [$s,$e];

//
//        $info = InvoiceBarcode::orderByDesc('id')->where('Barcode','like', '%'.$request['search'].'%')->get();
//
//        $info2 = Remittance::orderByDesc('id')->where('barcode', 'like', '%' . $request['search'] . '%')->get();
//
//        return response([$info,$info2],200);
//        $bars = InvoiceBarcode::where('id', '>', 200)->get();
//        foreach ($bars as $item) {
//            $item->delete();
//        }
//        DB::statement('ALTER TABLE invoice_barcodes AUTO_INCREMENT = 201;');
//        foreach ($t as $item) {
//            $bar = InvoiceBarcode::create([
//                "invoice_id" => 2175,
//                "Barcode" => $item,
//            ]);
//        }

//        $x = Invoice::where('id', 2175)->first();
//        return response(new InvoiceResource($x), 200);
//        $t = InvoiceItem::where('id','9773')->first();
//        $t->update([
//            'Quantity'=>'320'
//        ]);
//        $t2 = InvoiceItem::where('id','9774')->first();
//        $t2->update([
//            'Quantity'=>'320'
//        ]);
//        $x=Invoice::where('id','2163')->first();
//        $x->update(['Sum'=>700]);
//        $d3 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
//            ->whereNot('Type', 'Order')
//            ->orderByDesc('Type')
//            ->orderByDesc('OrderID')
//            ->paginate(100);
//        $dat1 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
//            ->orderByDesc('OrderID')
//            ->where('Type','InventoryVoucher')
//            ->get()->count();
//        $dat2 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
//            ->orderByDesc('OrderID')
//            ->where('Type','Deputation')
//            ->get()->count();
//        return ['InventoryVoucher'=> $dat1, 'Deputation'=> $dat2, ];
//        $datetime = new \DateTime( "now", new \DateTimeZone( "Asia/Tehran" ));
//
//        $nowHour  = $datetime->format( 'G');
//        if (((int)$nowHour < 8) || ((int)$nowHour > 19)){
//            return 0;
//        }
//
//            $d3 = Invoice::where('DeliveryDate', '>=', today()->subDays(15))
//                ->orderByDesc('OrderID')
//                ->orderByDesc('Type')
//                ->paginate(50);
////            $data = InvoiceResource::collection($d3);
//            return response()->json($d3, 200);


//        $dat1 = InvoiceAddress::orderBy('id')->get();
//        foreach ($dat1 as $item) {
//            if ($item['city'] == '') {
//                $dat2 = Address::select('GNR3.Address.AddressID', 'GNR3.Address.Name as AddressName', 'GNR3.RegionalDivision.Name as City')
//                    ->join('GNR3.RegionalDivision', 'GNR3.RegionalDivision.RegionalDivisionID', '=', 'GNR3.Address.RegionalDivisionRef')
//                    ->where('AddressID', $item['AddressID'])->first();
//                $item->update(['city' => $dat2['City']]);
//            }
//        }
//        $dat3 = Address::select('GNR3.Address.AddressID', 'GNR3.Address.Name as AddressName', 'GNR3.RegionalDivision.Name as City')
//            ->join('GNR3.RegionalDivision', 'GNR3.RegionalDivision.RegionalDivisionID', '=', 'GNR3.Address.RegionalDivisionRef')
//           ->paginate(100);
//        return $dat3;
    }
    public function getInvoiceBarcodes(Request $request)
    {
        $info = InvoiceBarcode::orderByDesc('id');
        if (isset($request['OrderNumber'])) {
            $info = $info->whereHas('invoice', function ($q) use ($request) {
                $q->where('OrderNumber', $request['OrderNumber']);
            });
        }
        if (isset($request['search'])) {
            $info = $info->where('Barcode', 'like', '%' . $request['search'] . '%');
        } else {
            if (isset($request['count'])) {
                $info = $info->take($request['count']);
            } else {
                $info = $info->take(500);
            }
        }
        $info = $info->get();
        return InvoiceBarcodeResource::collection($info);

    }
    public function getRemittances(Request $request)
    {
        $info = Remittance::orderByDesc('id');
        if (isset($request['OrderNumber'])) {
            $info = $info->where('OrderNumber', $request['OrderNumber']);
        }
        if (isset($request['search'])) {
            $info = $info->where('barcode', 'like', '%' . $request['search'] . '%');
        } else {
            $info = $info->take(500);
        }
        $info = $info->get();

        return RemittanceResource::collection($info);

    }
    public function report(Request $request)
    {
        try {
            $i2 = $this->getRemittances($request);
            if (count($i2)) {
                $i1 = $this->getInvoiceBarcodes($request);
                $filtered = json_decode(json_encode($i1));
                $filtered2 = json_decode(json_encode($i2));
                $input1 = array_values($filtered);
                $input2 = array_values($filtered2);
                $input = array_merge($input1, $input2);

                $offset = 0;
                $perPage = 200;
                if ($request['page'] && $request['page'] > 1) {
                    $offset = ($request['page'] - 1) * $perPage;
                }

                $info = array_slice($input, $offset, $perPage);
                $paginator = new LengthAwarePaginator($info, count($input), $perPage, $request['page']);

                return response()->json($paginator, 200);
            } else {
                $info = InvoiceBarcode::orderByDesc('id');
                if (isset($request['OrderNumber'])) {
                    $info = $info->whereHas('invoice', function ($q) use ($request) {
                        $q->where('OrderNumber', $request['OrderNumber']);
                    });
                }
                if (isset($request['search'])) {
                    $info = $info->where('Barcode', 'like', '%' . $request['search'] . '%');
                }
                if (isset($request['count']) && $request['count'] <= 500) {
                    $info = $info->take($request['count'])->get();
                } else {
                    $info = $info->paginate(200);
                }
                return InvoiceBarcodeResource::collection($info);
            }


//            if (isset($request['duplicate']) && $request['duplicate'] == 1) {
//                $bars1 = array_column($input1, 'Barcode');
//                $duplicates1 = array_values(array_unique(array_diff_assoc($bars1, array_unique($bars1))));
//                $bars2 = array_column($input2, 'barcode');
//                $duplicates2 = array_values(array_unique(array_diff_assoc($bars2, array_unique($bars2))));
//                return response()->json([['duplicates' => [$duplicates1, $duplicates2]], $paginator], 200);
//}



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

}
