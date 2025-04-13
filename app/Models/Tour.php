<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tour extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'DSD3.Tour';
    protected $hidden = ['Version'];

    use HasFactory;

    public function TourItems()
    {
        return $this->hasMany(TourItem::class, 'TourRef', 'TourID')->with(['Customer','CustomerAddress']);
//            ->whereHas('Part', function ($q) {
//                $q->where('Name', 'like', '%نودالیت%');
//                $q->whereNot('Name', 'like', '%لیوانی%');
//            })->orderBy('PartRef');
    }
    public function Invoices()
    {
        return $this->hasMany(TourInvoice::class, 'TourRef', 'TourID')->with(['Order']);
//            ->whereHas('Part', function ($q) {
//                $q->where('Name', 'like', '%نودالیت%');
//                $q->whereNot('Name', 'like', '%لیوانی%');
//            })->orderBy('PartRef');
    }

}
