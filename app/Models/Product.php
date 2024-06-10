<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SLS3.Part';
    protected $hidden = ['Version'];

    public function Item()
    {
        return $this->belongsTo(OrderItem::class, 'ProductID', 'ProductRef');
    }
}
