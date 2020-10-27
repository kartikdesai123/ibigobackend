<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'subscription_id', 'customer_id', 'mandate_id','subscription_date','subscription_status',
    ];
}
