<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourAssignmentItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'DSD3.TourAssignmentItem';
    protected $hidden = ['Version'];

    public function Tour()
    {
        return $this->belongsTo(Tour::class, 'TourID', 'TourRef');
    }
    public function Assignment()
    {
        return $this->belongsTo(Assignment::class, 'AssignmentID', 'AssignmentRef');
    }

}
