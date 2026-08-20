<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedCustomerlog extends Model
{
    use HasFactory;
    
    protected $table = 'pu_blocked_customers_logs';
    
    protected $primaryKey = 'block_id';
    
    protected $fillable = [
        'block_id',
        'customer_id',
        'customer_email',
        'from_action',
        'from_ip',
        'from_browser',
        'customer_datetime',
    ];
    
    protected $casts = [
        'block_id' => 'int',
        'customer_id' => 'int',
        'customer_datetime' => 'datetime',
    ];

    public $timestamps = false;
}
