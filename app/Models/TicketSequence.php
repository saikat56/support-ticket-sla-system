<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSequence extends Model
{
    protected $primaryKey = 'year';
    public $incrementing = false;

    protected $fillable = [
        'year',
        'last_number',
    ];
}

