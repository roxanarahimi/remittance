<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'SLS3.Customer';
    protected $hidden = ['Version'];

    public function Order()
    {
        return $this->hasMany(Order::class, 'CustomerRef', 'CustomerID');
    }

    public function CustomerAddress()
    {
        return $this->hasOne(CustomerAddress::class, 'CustomerRef','CustomerID');
    }
    public function Party()
    {
        return $this->hasOne(Party::class, 'PartyID','PartyRef');
    }
}
