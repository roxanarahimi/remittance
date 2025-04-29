<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transporter extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'LGS3.Transporter';
    protected $hidden = ['Version'];

    public function Party()
    {
        return $this->belongsTo(Party::class,  'PartyID','PartyRef');
    }
    public function Assignments()
    {
        return $this->hasMany(Assignment::class,  'TransporterRef','TransporterID')
            ->whereHas('TourAssignmentItem',function ($a){
                $a->whereHas('Tour');
            })
            ->with('TourAssignmentItem',function ($q){
                $q->with('Tour');
            });
    }
}
