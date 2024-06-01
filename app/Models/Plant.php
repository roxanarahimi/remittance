<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plant extends Model
{
    protected $connection= 'sqlsrv';
    protected $table = 'LGS3.Plant';

    public function Address()
    {
        return $this->hasOne(Address::class,  'AddressID','AddressRef');
    }


}
