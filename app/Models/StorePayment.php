<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $orders_id
 * @property int        $customer_id
 * @property string     $orders_no
 * @property string     $dropshipper_order_no
 * @property int        $order_datetime
 * @property int        $order_upd_datetime
 * @property string     $gift_message
 * @property string     $is_gift_order
 * @property int        $coupon_id
 * @property int        $Second_coupon_id
 * @property string     $coupon_code
 * @property string     $gc_code
 * @property int        $refer_id
 * @property string     $shipinfo
 * @property string     $payment_type
 * @property string     $payment_method
 * @property string     $ccinfo
 * @property string     $transaction_info
 * @property string     $payment_gateway_response
 * @property string     $order_comment
 * @property string     $customer_comment
 * @property string     $remark
 * @property string     $customer_browser
 * @property string     $currency_info
 * @property string     $user_type
 * @property int        $ilevelid
 * @property string     $ship_first_name
 * @property string     $ship_last_name
 * @property string     $ship_company
 * @property string     $ship_email
 * @property string     $ship_address1
 * @property string     $ship_address2
 * @property string     $ship_city
 * @property string     $ship_zip
 * @property string     $ship_state
 * @property string     $ship_country
 * @property string     $ship_phone
 * @property string     $bill_first_name
 * @property string     $bill_last_name
 * @property string     $bill_company
 * @property string     $bill_email
 * @property string     $bill_address1
 * @property string     $bill_address2
 * @property string     $bill_city
 * @property string     $bill_zip
 * @property string     $bill_state
 * @property string     $bill_country
 * @property string     $bill_phone
 * @property string     $ship_method
 * @property string     $tracking_no
 * @property Date       $ship_date
 * @property string     $vLang_flag
 * @property string     $browser_info
 * @property string     $customer_ip
 * @property string     $return_transaction_info
 * @property string     $ref_reason
 * @property string     $return_by_adm
 * @property string     $comments
 * @property string     $paypal_payer_id
 * @property string     $paypal_transaction_id
 * @property string     $paypal_transaction_status
 * @property string     $paypal_transaction_date
 * @property string     $free_gift
 * @property string     $gift_from
 * @property string     $gift_to
 * @property string     $gift_message_customer
 * @property string     $stampsTxId
 * @property string     $stamps_url
 * @property string     $phone_order_receipt
 * @property string     $AmazonAuthorizationId
 * @property string     $AmazonRequestId
 * @property string     $amazon_capture_response
 * @property string     $pepperjam_reason_code
 * @property int        $Is_GiftCertificatPurchase
 * @property string     $BraintreeResponse
 * @property string     $order_come_from
 * @property string     $fullshipping_info
 * @property string     $stripesessionid
 * @property string     $paymentintentid
 * @property string     $webhook_response
 * @property string     $webhook_event
 * @property string     $total_refund_amount
 * @property string     $total_credit_refunded
 * @property string     $refund_transaction_response
 * @property string     $refund_comment
 * @property string     $customer_cancelReason
 * @property string     $other_customer_cancelReason
 * @property DateTime   $CancelRequestDate
 * @property DateTime   $CancelApproveDate
 * @property string     $cancel_comments
 * @property DateTime   $phoneorder_paymentdate
 * @property int        $phoneorder_shipping_method_id
 * @property DateTime   $EstimatedDeliveryDate
 * @property string     $merge_note
 * @property string     $TransResponse3D
 * @property int        $old_customerid
 * @property string     $sf_orderid
 * @property Date       $sf_process_date
 * @property string     $afterpay_transaction_id
 * @property string     $afterpay_void_response
 * @property string     $route_shipping_insurance_response
 * @property string     $route_tracking_response
 * @property string     $route_cancel_response
 */
class StorePayment extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_store_payment';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'payment_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transaction_info', 'paymentintentid', 'stripesessionid', 'amount', 'Payment_type', 'orders_id','status'
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
