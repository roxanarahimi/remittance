<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $connection= 'sqlsrv';
    protected $table = 'LGS3.Part';
    public function Item()
    {
        return $this->belongsTo(InventoryVoucherItem::class,    'PartID','PartRef');
    }}
