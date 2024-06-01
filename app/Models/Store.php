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
        return $this->hasMany(InventoryVoucher::class,  'CounterpartStoreRef','StoreID');
    }
    public function Plant()
    {
        return $this->hasOne(Plant::class,  'PlantRef','PlantID');
    }
}
