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
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id')->orderBy('PartRef');
    }
    public function address()
    {
        return $this->hasOne(InvoiceAddress::class, 'AddressID', 'AddressID');
    }
    public function barcodes()
    {
        return $this->hasMany(InvoiceBarcode::class,  'invoice_id','id');
    }
    public function testBarcodes()
    {
        return $this->hasMany(Test::class,  'invoice_id','id');
    }

}
