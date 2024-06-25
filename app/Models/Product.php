<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SLS3.Product';
    protected $hidden = ['Version'];

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class,  'ProductID','ProductID');
    }
}
