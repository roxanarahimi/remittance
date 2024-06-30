<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceBarcode extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function invoice()
    {
        return $this->belongsTo(Invoice::class,  'invoice_id','id');
    }
}
