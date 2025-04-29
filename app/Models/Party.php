<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    use HasFactory;
    protected $connection = 'sqlsrv';
    protected $table = 'GNR3.Party';
    protected $hidden = ['Version'];
    public function Order()
    {
        return $this->hasMany(InventoryVoucher::class, 'CounterpartEntityRef', 'PartyID');
    }

    public function PartyAddress()
    {
        return $this->hasOne(PartyAddress::class,'PartyRef','PartyID');
    }
    public function Transporter()
    {
        return $this->belongsTo(Transporter::class,'PartyRef','PartyID');
//            ->whereHas('Assignments',function ($q){
//                $q->whereHas('TourAssignmentItem',function ($x){
//                    $x->whereHas('Tour');
//                });
//            });

    }
}
