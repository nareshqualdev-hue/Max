<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $ipn_log_id
 * @property int        $customer_id
 * @property string     $paypal_ipn_response
 * @property string     $txn_id
 * @property string     $payment_status
 */
class PaypalIpnLog extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_paypal_ipn_log';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ipn_log_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'order_date', 'customer_id', 'cust_available_fund', 'cust_requested_fund', 'paypal_ipn_response', 'txn_id', 'payment_status'
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
        'ipn_log_id' => 'int', 'customer_id' => 'int', 'paypal_ipn_response' => 'string', 'order_date' => 'timestamp', 'txn_id' => 'string', 'payment_status' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'order_date'
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
