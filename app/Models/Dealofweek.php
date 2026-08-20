<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $dealofweek_id
 * @property int        $did
 * @property string     $product_sku
 * @property Date       $start_date
 * @property Date       $end_date
 * @property string     $description
 * @property int        $display_rank
 */
class Dealofweek extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_dealofweek';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'dealofweek_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'did', 'product_sku', 'start_date', 'end_date', 'description', 'deal_price', 'status', 'display_rank', 'display_on_home', 'deal_type', 'discount_coupon_flag'
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
        'dealofweek_id' => 'int', 'did' => 'int', 'product_sku' => 'string', 'start_date' => 'date', 'end_date' => 'date', 'description' => 'string', 'display_rank' => 'int'
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
