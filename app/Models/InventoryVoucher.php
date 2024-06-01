<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryVoucher extends Model
{
    //use HasFactory;
    protected $connection= 'sqlsrv';
    protected $table = 'LGS3.InventoryVoucher';
    public function OrderItems()
    {
        return $this->hasMany(InventoryVoucherItem::class,  'InventoryVoucherRef','InventoryVoucherID');
    }
    public function Store()
    {
        return $this->belongsTo(Store::class,  'CounterpartStoreRef','StoreID');
    }


}
