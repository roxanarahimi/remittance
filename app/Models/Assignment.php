<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'DSD3.Assignment';
    protected $hidden = ['Version'];
    public function Transporter()
    {
        return $this->hasOne(Transporter::class, 'TransporterID', 'TransporterRef');
    }
    public function TourAssignmentItem()
    {
        return $this->hasOne(TourAssignmentItem::class, 'AssignmentRef', 'AssignmentID');
    }
}
