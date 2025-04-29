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
        return $this->hasOne(Transporter::class, 'TransporterID', 'TransporterRef')->with('Party');
    }
    public function TourAssignmentItems()
    {
        return $this->hasMany(TourAssignmentItem::class, 'AssignmentRef', 'AssignmentID');
    }
}
