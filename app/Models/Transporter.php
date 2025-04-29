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
        return $this->hasOne(Party::class, 'PartyID', 'PartyRef');
    }
    public function Assignment()
    {
        return $this->belongsTo(Assignment::class,  'TransporterRef','TransporterID')
            ->with('TourAssignmentItems',function ($q){
                $q->with('Tour');
            });
    }
}
