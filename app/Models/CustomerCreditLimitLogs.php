<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $credit_log_id
 * @property int        $orderid
 * @property int     	$custid
 * @property decimal    $current_credit_limit
 * @property decimal    $apply_credit
 * @property decimal    $remaining_credit
 * @property DateTime     $cdate
 */
class CustomerCreditLimitLogs extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_customer_credit_limit_logs';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'credit_log_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'orderid', 'custid', 'current_credit_limit', 'apply_credit', 'remaining_credit', 'cdate'
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
        'credit_log_id' => 'int', 'orderid' => 'int', 'custid' => 'int','cdate' => 'date' 
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'cdate'
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
