<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $auth_log_id
 * @property string     $inv_order_id
 * @property int        $customer_id
 * @property string     $transaction_info
 * @property string     $payment_gateway_response
 * @property string     $stripesessionid
 * @property string     $paymentintentid
 * @property string     $webhook_response
 * @property string     $webhook_event
 */
class AuthorizeFundLog extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_authorize_fund_logs';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'auth_log_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'inv_order_id', 'customer_id', 'status', 'pay_status', 'fund_amount', 'transaction_info', 'payment_gateway_response', 'order_date', 'stripesessionid', 'paymentintentid', 'webhook_response', 'webhook_event'
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
        'auth_log_id' => 'int', 'customer_id' => 'int', 'inv_order_id' => 'string', 'order_date' => 'timestamp', 'transaction_info' => 'string', 'payment_gateway_response' => 'string', 'stripesessionid' => 'string', 'paymentintentid' => 'string', 'webhook_response' => 'string', 'webhook_event' => 'string'
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
