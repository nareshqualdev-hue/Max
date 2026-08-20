<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $amazon_log_id
 * @property string     $inv_order_id
 * @property int        $customer_id
 * @property string     $AmazonAuthorizationId
 * @property string     $AmazonRequestId
 * @property string     $transaction_info
 * @property string     $payment_gateway_response
 * @property string     $amazon_capture_response
 */
class AmazonFundLog extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_amazon_fund_logs';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'amazon_log_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'inv_order_id', 'customer_id', 'status', 'pay_status', 'AmazonAuthorizationId', 'AmazonRequestId', 'fund_amount', 'transaction_info', 'payment_gateway_response', 'order_date', 'amazon_capture_response'
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
        'amazon_log_id' => 'int', 'customer_id' => 'int', 'inv_order_id' => 'string', 'order_date' => 'timestamp', 'AmazonAuthorizationId' => 'string', 'AmazonRequestId' => 'string', 'transaction_info' => 'string', 'payment_gateway_response' => 'string', 'amazon_capture_response' => 'string'
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
