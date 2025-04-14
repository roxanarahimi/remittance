<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TourInvoice extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'DSD3.Invoice';
    protected $hidden = ['Version'];
    public function Tour()
    {
        return $this->belongsTo(Tour::class, 'TourID', 'TourRef');
    }
    public function Order()
    {
        return $this->belongsTo(Order::class, 'OrderID', 'OrderRef')->with('OrderItems');
    }
}
