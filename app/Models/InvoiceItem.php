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
    public function product()
    {
        return $this->hasOne(InvoiceProduct::class,  'id','invoice_item_id');
    }
    public function barcodes()
    {
        return $this->hasMany(InvoiceBarcode::class,  'id','invoice_item_id');
    }

}
