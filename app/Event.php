<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    //protected $gaurded = ['id'];
    //protected $dates = ['start_date_time', 'end_date_time'];

    protected $fillable = ['event_title','event_slug','event_unique_id','start_date_time','end_date_time','event_description','event_category','location','event_cover','host_id','host_group'];
}
