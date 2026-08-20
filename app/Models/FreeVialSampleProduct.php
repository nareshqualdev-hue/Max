<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class FreeVialSampleProduct extends Model
{
    // Table name
    protected $table = 'pu_free_vialsample_product';

    // Primary key
    protected $primaryKey = 'vial_id';

    // Indicates if the model should be timestamped.
    public $timestamps = false;

    // Mass assignable fields
    protected $fillable = [
        'sku',
        'product_name',
        'description',
        'product_image',
        'status',
        'orders',
        'price_start_range',
        'price_end_range',
        'add_datetime',
        'upd_datetime',
        'start_date',
        'end_date',
        'flag_range',
        'exclude_sku',
        'freevial_add_count',
        'exclude_pocketperfume',
        'customer_choice',
        'total_samples',
    ];

    // Casts for data types
    protected $casts = [
        'add_datetime'           => 'datetime',
        'upd_datetime'           => 'datetime',
        'start_date'             => 'date',
        'end_date'               => 'date',
        'price_start_range'      => 'integer',
        'price_end_range'        => 'integer',
        'freevial_add_count'     => 'integer',
        'customer_choice'        => 'integer',
        'total_samples'          => 'integer',
    ];    
}
?>