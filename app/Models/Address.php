<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'GNR3.Address';
    protected $hidden = ['Version'];

    public function Plant()
    {
        return $this->belongsTo(Store::class, 'AddressRef', 'AddressID');
    }

    public function CustomerAddress()
    {
        return $this->belongsTo(CustomerAddress::class, 'AddressRef', 'AddressID');
    }

    public function PartyAddress()
    {
        return $this->belongsTo(PartyAddress::class, 'AddressRef', 'AddressID');
    }

}
