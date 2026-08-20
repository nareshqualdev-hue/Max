<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $cart_id
 * @property int        $customer_id
 * @property string     $cookie_id
 * @property string     $cart_string
 * @property int        $created_date
 */
class Shoppingcart extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_shoppingcart';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'cart_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'cookie_id', 'cart_string', 'created_date'
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
        'cart_id' => 'int', 'customer_id' => 'int', 'cookie_id' => 'string', 'cart_string' => 'string', 'created_date' => 'timestamp'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_date'
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
