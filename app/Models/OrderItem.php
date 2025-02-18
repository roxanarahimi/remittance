<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'SLS3.OrderItem';
    protected $hidden = ['Version'];

    public function Order()
    {
        return $this->belongsTo(Order::class, 'OrderRef', 'OrderID');
    }

    public function Product()
    {
        return $this->hasOne(Product::class, 'ProductID', 'ProductRef');
    }

}
