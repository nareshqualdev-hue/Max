<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $sku
 * @property string     $customer_ip
 * @property string     $source
 * @property int        $datetime
 */
class ProductsViewed extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_products_viewed';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sku', 'customer_ip', 'source', 'datetime'
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
        'sku' => 'string', 'customer_ip' => 'string', 'source' => 'string', 'datetime' => 'timestamp'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'datetime'
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
