<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Remittance extends Model
{
    use HasFactory;
    protected $guarded= ['id'];

    public function invoices()
    {
        return $this->belongsTo(Invoice::class,  'OrderNumber','OrderNumber')
            ->whereColumn('orderID','=','OrderID');
    }
}
