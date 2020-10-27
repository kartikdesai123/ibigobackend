<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventInvite extends Model
{
    //protected $gaurded = ['id'];
    protected $fillable = ['event_id','user_id','group_id'];
}
