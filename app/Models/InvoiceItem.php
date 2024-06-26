<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
    }
    public function productPart()
    {
        return $this->hasOne(InvoiceProduct::class,  'ProductID','ProductID')->where('Type', 'Part');
    }
    public function productProduct()
    {
        return $this->hasOne(InvoiceProduct::class,  'ProductID','ProductID')->where('Type', 'Product');
    }
    public function barcodes()
    {
        return $this->hasMany(InvoiceBarcode::class,  'id','invoice_item_id');
    }

}
