<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $hidden = ['Version'];
    protected $connection= 'sqlsrv';
    protected $table = 'SLS3.Order';

}
