<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Remittance extends Model
{
    use HasFactory;
    protected $guarded= ['id'];

    public function invoice()
    {
        return $this->hasMany(Invoice::class,  'id','invoice_id');
    }
}
