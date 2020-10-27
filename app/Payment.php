<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'payment_id', 'subscription_id', 'payment_amount', 'payment_date','payment_status',
    ];
}
