<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'DSD3.TourItem';
    protected $hidden = ['Version'];
    public function Tour()
    {
        return $this->belongsTo(Tour::class, 'TourID', 'TourRef');
    }
    public function Costomer()
    {
        return $this->hasOne(Customer::class, 'CustomerID', 'CustomerRef');
    }
    public function CustomerAddress()
    {
        return $this->belongsTo(CustomerAddress::class,'CustomerAddressID', 'CustomerAddressRef');
    }
}
