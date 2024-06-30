<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceProduct extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function invoiceItem()
    {
        return $this->hasOne(InvoiceProduct::class,  'ProductNumber','ProductNumber');
    }
}
