<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryVoucherItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'LGS3.InventoryVoucherItem';
    protected $hidden = ['Version'];

    public function Order()
    {
        return $this->belongsTo(InventoryVoucher::class, 'InventoryVoucherRef', 'InventoryVoucherID');
    }

    public function Part()
    {
        return $this->hasOne(Part::class, 'PartID', 'PartRef')->ordeBy('Name');
    }
    public function Unit()
    {
        return $this->hasOne(Unit::class, 'UnitID','UnitRef' )->ordeBy('Name');
    }
    public function PartUnit()
    {
        return $this->hasOne(PartUnit::class, 'PartUnitID','PartUnitRef' )->ordeBy('Name');
    }
}
