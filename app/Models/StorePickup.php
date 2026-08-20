<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorePickup extends Model
{
    
    protected $table = 'pu_store_pickup';
    
    protected $primaryKey = 'order_id';
    
    public $incrementing = false;
    
    protected $keyType = 'int';
    
    public $timestamps = false;
    
    protected $fillable = [
        'order_id',
        'order_datetime',
        'status',
    ];
    
    protected $casts = [
        'order_datetime' => 'datetime',
    ];
}
