<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $coupon_id
 * @property string     $coupon_title
 * @property string     $coupon_number
 * @property Date       $start_date
 * @property Date       $end_date
 * @property string     $sku
 * @property string     $country
 * @property string     $detail
 * @property string     $remark
 * @property string     $free_shipping_value
 * @property string     $free_gift_product_value
 * @property string     $exclude_sku
 * @property int        $total_free_shipping
 */
class Coupon extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_coupon';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'coupon_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coupon_title', 'coupon_number', 'start_date', 'end_date', 'type', 'order_amount', 'sku', 'orders', 'country', 'discount', 'detail', 'remark', 'is_once', 'is_used', 'count_ship_tax', 'status', 'count_gc_purchase', 'coupon_user_type', 'allow_free_shipping', 'free_shipping_value', 'allow_free_gift_product', 'free_gift_product_value', 'exclude_sku', 'total_free_shipping', 'minimum_order_amount', 'autodiscount_flag', 'quantitydiscount_flag', 'dealdiscount_flag','bogodiscount_flag','source', 'customer_email'
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
        'coupon_id' => 'int', 'coupon_title' => 'string', 'coupon_number' => 'string', 'start_date' => 'date', 'end_date' => 'date', 'sku' => 'string', 'country' => 'string', 'detail' => 'string', 'remark' => 'string', 'free_shipping_value' => 'string', 'free_gift_product_value' => 'string', 'exclude_sku' => 'string', 'total_free_shipping' => 'int'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_date', 'end_date'
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
