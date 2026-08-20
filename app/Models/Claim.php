<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $claim_id
 * @property int        $orders_id 
 * @property string     $claim_reason
 */
class Claim extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_claim';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'claim_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'claim_id', 'customer_id', 'orders_id', 'orders_no', 'claim_reason', 'status', 'cust_comment', 'attach_item_image'
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
        'claim_id' => 'int', 'customer_id' => 'int', 'orders_id' => 'int', 'orders_no' => 'string', 'claim_reason' => 'string', 'status' => 'string', 'cust_comment' => 'string', 'attach_item_image' => 'string'
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
