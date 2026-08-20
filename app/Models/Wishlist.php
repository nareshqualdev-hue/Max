<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $wishlist_id
 * @property int        $customer_id
 * @property int        $wishlist_category_id
 * @property int        $products_id
 * @property string     $sku
 * @property string     $description
 * @property int        $insert_datetime
 */
class Wishlist extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_wishlist';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'wishlist_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'wishlist_category_id', 'products_id', 'sku', 'description', 'insert_datetime'
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
        'wishlist_id' => 'int', 'customer_id' => 'int', 'wishlist_category_id' => 'int', 'products_id' => 'int', 'sku' => 'string', 'description' => 'string', 'insert_datetime' => 'timestamp'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'insert_datetime'
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
