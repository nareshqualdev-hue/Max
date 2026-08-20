<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMode extends Model
{	
    public $timestamps = false;
    
    protected $table = 'pu_shipping_mode'; 
    
    protected $primaryKey = 'shipping_mode_id';
    
    protected $fillable = ['type','detail','eusertype','display_position','days','status'];
}