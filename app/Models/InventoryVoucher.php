<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryVoucher extends Model
{
    //use HasFactory;
    protected $connection = 'sqlsrv';
    protected $table = 'LGS3.InventoryVoucher';
    protected $hidden = ['Version'];

    public function OrderItems()
    {
        return $this->hasMany(InventoryVoucherItem::class, 'InventoryVoucherRef', 'InventoryVoucherID')
            ->whereHas('Part', function ($q) {
                $q->where('Name', 'like', '%نودالیت%');
            });
    }

    public function Store()
    {
        return $this->belongsTo(Store::class, 'CounterpartStoreRef', 'StoreID');
    }
    public function Party()
    {
        return $this->belongsTo(Party::class, 'CounterpartEntityRef', 'PartyID');
    }
}
