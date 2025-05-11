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
        return $this->hasMany(Invoice::class,  'OrderNumber','OrderNumber')
            ->whereColumn('OrderID','=','orderID');
    }
}
