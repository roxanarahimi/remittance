<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceBarcode extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class,  'id','invoice_item_id');
    }
}
