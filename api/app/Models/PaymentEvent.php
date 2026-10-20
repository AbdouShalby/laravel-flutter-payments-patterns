<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    protected $fillable = ['provider_event_id', 'payment_intent_id', 'type', 'payload'];
    protected $casts = ['payload' => 'array'];
}
