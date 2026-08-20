<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $products_id
 * @property string     $sku
 * @property string     $product_name
 * @property string     $description
 * @property string     $product_image
 * @property string     $flag_range
 * @property string     $exclude_sku
 */
class FreeGiftProduct extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_free_gift_product';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'products_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sku', 'product_name', 'description', 'product_image', 'status', 'price_start_range', 'price_end_range', 'add_datetime', 'upd_datetime', 'start_date', 'end_date', 'flag_range', 'exclude_sku'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'products_id' => 'int', 'sku' => 'string', 'product_name' => 'string', 'description' => 'string', 'product_image' => 'string', 'add_datetime' => 'timestamp', 'upd_datetime' => 'timestamp', 'start_date' => 'timestamp', 'end_date' => 'timestamp', 'flag_range' => 'string', 'exclude_sku ' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'add_datetime', 'upd_datetime', 'start_date', 'end_date'
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    // Scopes...

    // Functions ...

    // Relations ...
}
