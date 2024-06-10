<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SLS3.CustomerAddress';
    protected $hidden = ['Version'];

    public function Address()
    {
        return $this->hasOne(Address::class, 'AddressID', 'AddressRef');
    }
}
