<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $shipping_rule_id
 * @property int        $shipping_mode_id
 * @property string     $country
 * @property string     $state
 * @property int        $zipcode_to
 * @property int        $zipcode_from
 * @property int        $prop_item
 * @property int        $days
 * @property string     $zone_id
 * @property string     $normal_charge
 * @property string     $light_charge
 * @property string     $heavy_charge
 */
class ShippingRule extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_shipping_rule';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'shipping_rule_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'shipping_mode_id', 'country', 'state', 'zipcode_to', 'zipcode_from', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge', 'days', 'zone_id', 'normal_charge', 'light_charge', 'heavy_charge'
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
        'shipping_rule_id' => 'int', 'shipping_mode_id' => 'int', 'country' => 'string', 'state' => 'string', 'zipcode_to' => 'int', 'zipcode_from' => 'int', 'prop_item' => 'int', 'days' => 'int', 'zone_id' => 'string', 'normal_charge' => 'string', 'light_charge' => 'string', 'heavy_charge' => 'string'
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
