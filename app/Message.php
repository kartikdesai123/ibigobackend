<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
	protected $guarded = ['id'];
	protected $dates = ['message_date_time'];

    public function from_user()
	{
	  return $this->belongsTo(User::class);
	}

	public function to_user()
	{
	  return $this->belongsTo(User::class);
	}
}
