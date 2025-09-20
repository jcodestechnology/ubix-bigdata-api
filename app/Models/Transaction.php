<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id','user_id','account_number','transaction_date',
        'transaction_type','amount','currency','status','merchant_name',
        'merchant_category','description','reference_number','country',
        'city','ip_address','device','channel','fee','tax','balance_before',
        'balance_after','processing_time_ms','extra_field1','extra_field2',
        'extra_field3','extra_field4','extra_field5'
    ];
}
