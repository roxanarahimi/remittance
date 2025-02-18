<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'SLS3.Order';
    protected $hidden = ['Version'];


    public function OrderItems()
    {
        return $this->hasMany(OrderItem::class, 'OrderRef', 'OrderID')
            ->whereHas('Product', function ($q) {
                $q->where('Name', 'like', '%نودالیت%');
                $q->whereNot('Name', 'like', '%لیوانی%');
            });
    }

    public function Sum()
    {
        return $this->OrderItems()->sum('Quantity');
    }

    public function Customer()
    {
        return $this->belongsTo(Customer::class, 'CustomerRef', 'CustomerID');
    }


}
