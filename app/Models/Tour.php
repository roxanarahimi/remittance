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
}
