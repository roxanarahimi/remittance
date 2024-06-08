<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryVoucher extends Model
{
    //use HasFactory;
    protected $connection= 'sqlsrv';
    protected $table = 'LGS3.InventoryVoucher';
    protected $hidden = ['Version'];

    public function OrderItems()
    {
        return $this->hasMany(InventoryVoucherItem::class,  'InventoryVoucherRef','InventoryVoucherID');
    }
    public function OkItems()
    {
        $t = Part::where('Name','like', '%نودالیت%')->get('PartID')->toArray();
        $ids = [];
        foreach ($t as $item){
            $ids[] = (integer)$item['PartID'];
        }
        return $this->hasOne(InventoryVoucherItem::class,  'InventoryVoucherRef','InventoryVoucherID') ->whereIn('PartRef', $ids);
    }
    public function Store()
    {
        return $this->belongsTo(Store::class, 'CounterpartStoreRef','StoreID');
    }


}
