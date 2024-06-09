<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryVoucherItem extends Model
{
    protected $connection= 'sqlsrv';
    protected $table = 'LGS3.InventoryVoucherItem';
    protected $hidden = ['Version'];
    public function Order()
    {
        return $this->belongsTo(InventoryVoucher::class,  'InventoryVoucherRef','InventoryVoucherID');
    }
    public function Part()
    {
        return $this->hasOne(Part::class,  'PartID','PartRef');
    }
//    public function Nood()
//    {
//        return $this->hasOne(Part::class,  'PartID','PartRef')
//            ->where('Name','LIKE','%نودالیت%');
//    }
}
