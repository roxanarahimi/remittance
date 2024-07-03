<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartyAddress extends Model
{
    use HasFactory;
    protected $connection = 'sqlsrv';
    protected $table = 'GNR3.PartyAddress';
    protected $hidden = ['Version'];
    public function Address()
    {
        return $this->hasOne(Address::class, 'AddressID', 'AddressRef');
    }
}
