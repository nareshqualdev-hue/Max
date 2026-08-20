<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $orders_no
 * @property string     $sku
 * @property string     $quantity
 * @property string     $ship_first_name
 * @property string     $ship_last_name
 * @property string     $ship_email
 * @property string     $ship_address1
 * @property string     $ship_address2
 * @property string     $ship_city
 * @property string     $ship_state
 * @property string     $ship_country
 * @property string     $ship_zip
 * @property string     $ship_phone
 */
class ImportDropshiporder extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_import_dropshiporder';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    // protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'orders_no', 'sku', 'quantity', 'ship_first_name', 'ship_last_name', 'ship_email', 'ship_address1', 'ship_address2', 'ship_city', 'ship_state', 'ship_country', 'ship_zip', 'ship_phone'
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
        'orders_no' => 'string', 'sku' => 'string', 'quantity' => 'string', 'ship_first_name' => 'string', 'ship_last_name' => 'string', 'ship_email' => 'string', 'ship_address1' => 'string', 'ship_address2' => 'string', 'ship_city' => 'string', 'ship_state' => 'string', 'ship_country' => 'string', 'ship_zip' => 'string', 'ship_phone' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        
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
