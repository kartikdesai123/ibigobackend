<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class Admin extends Model
{
	use HasApiTokens, Notifiable;

    protected $table = 'admins';
    protected $primaryKey = 'id';

    protected $fillable = ['email','password','reset_password_token'];
}
