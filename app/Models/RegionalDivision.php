<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionalDivision extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'GNR3.RegionalDivision';
    protected $hidden = ['Version'];


}
