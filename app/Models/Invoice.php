<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id')->orderBy('ProductNumber');
    }
    public function address()
    {
        return $this->hasOne(InvoiceAddress::class, 'AddressID', 'AddressID');
    }
    public function barcodes()
    {
        return $this->hasMany(InvoiceBarcode::class,  'invoice_id','id');
    }
    public function barcodes2()
    {
        return $this->hasMany(Remittance::class,  'orderID','OrderID');
    }
    public function testBarcodes()
    {
        return $this->hasMany(Test::class,  'invoice_id','id');
    }
    public function rrBarcodes()
    {
        return $this->hasMany(Remittance::class, 'orderID', 'OrderID')
;
    }

}
