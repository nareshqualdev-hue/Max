<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $auto_discount_id
 * @property Date       $start_date
 * @property Date       $end_date
 * @property string     $detail
 * @property string     $exclude_sku
 * @property string     $sku
 */
class AutoDiscount extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_auto_discount';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'auto_discount_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_amount', 'end_order_amount', 'auto_discount_amount', 'type', 'start_date', 'end_date', 'detail', 'exclude_sku', 'discount_coupon_flag', 'status', 'sku','exclude_pocketperfume','orders'
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
        'auto_discount_id' => 'int', 'start_date' => 'date', 'end_date' => 'date', 'detail' => 'string', 'exclude_sku' => 'string', 'sku' => 'string'
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
