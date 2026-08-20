<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $bogo_discount_id
 * @property int        $quantity
 * @property string     $sku
 * @property Date       $start_date
 * @property Date       $end_date
 * @property string     $logo
 */
class BogoDiscount extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_bogo_discount';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'bogo_discount_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'quantity', 'bogo_discount_amount', 'type', 'sku', 'orders', 'start_date', 'end_date', 'status', 'logo','exclude_pocketperfume'
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
        'bogo_discount_id' => 'int', 'quantity' => 'int', 'sku' => 'string', 'start_date' => 'date', 'end_date' => 'date', 'logo' => 'string'
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
