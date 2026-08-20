<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $icust_requestid
 * @property string     $vname
 * @property string     $vemail
 * @property string     $vproduct
 * @property int        $product_qty
 * @property Date       $delivery_date
 * @property Date       $ddateadded
 */
class CustRequest extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_cust_request';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'icust_requestid';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'vname', 'vemail', 'vproduct', 'product_price', 'product_qty', 'delivery_date', 'ddateadded'
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
        'icust_requestid' => 'int', 'vname' => 'string', 'vemail' => 'string', 'vproduct' => 'string', 'product_qty' => 'int', 'delivery_date' => 'date', 'ddateadded' => 'date'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'delivery_date', 'ddateadded'
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
