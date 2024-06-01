<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryVoucherItem extends Model
{
    protected $connection= 'sqlsrv';
    protected $table = 'LGS3.InventoryVoucherItem';
}
