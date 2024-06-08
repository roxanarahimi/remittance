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
        $x= $this->hasMany(InventoryVoucherItem::class,  'InventoryVoucherRef','InventoryVoucherID');
        foreach ($x as $item){
            if(str_contains($item->Part->Name,'نودالیت')){
                return true;
            }else{
                return false;
            }
        }
    }
    public function Store()
    {
        return $this->belongsTo(Store::class, 'CounterpartStoreRef','StoreID');
    }


}
