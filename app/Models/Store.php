<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $connection= 'sqlsrv';
    protected $table = 'LGS3.Store';
    public function Order()
    {
        return $this->hasMany(InventoryVoucher::class,  'StoreRef','StoreID');
    }
    public function Address()
    {
        return $this->hasOne(Address::class,  'AddressRef','AddressID');
    }
}
