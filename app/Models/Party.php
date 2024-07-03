<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    use HasFactory;
    protected $connection = 'sqlsrv';
    protected $table = 'GNR3.Party';
    protected $hidden = ['Version'];
    public function Order()
    {
        return $this->hasMany(InventoryVoucher::class, 'PartyID','CounterpartEntityRef');
    }

    public function PartyAddress()
    {
        return $this->hasOne(Address::class,'AddressID','AddressRef');
    }
}
