<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $orders_id
 * @property int        $customer_id
 * @property string     $orders_no
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
 * @property int        $Is_GiftCertificatPurchase
 * @property string     $list_outofstock
 * @property int        $shippingModeId
 * @property int        $shipping_days
 */
class DropshipperOrder extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_dropshipper_order';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'orders_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'orders_no', 'order_datetime', 'order_upd_datetime', 'sub_total', 'shipping_amt', 'tax', 'gift_charge', 'gift_message', 'is_gift_order', 'handling_charge', 'wire_discount', 'auto_discount', 'quantity_discount', 'reward_discount', 'coupon_amount', 'coupon_id', 'Second_coupon_id', 'coupon_code', 'gc_amount', 'gc_code', 'refer_id', 'refer_amount', 'order_total', 'shipinfo', 'payment_type', 'payment_method', 'pay_status', 'ccinfo', 'transaction_info', 'payment_gateway_response', 'order_comment', 'customer_comment', 'remark', 'customer_browser', 'status', 'currency_info', 'checkout_type', 'user_type', 'ilevelid', 'level_price', 'ship_first_name', 'ship_last_name', 'ship_company', 'ship_email', 'ship_address1', 'ship_address2', 'ship_city', 'ship_zip', 'ship_state', 'ship_country', 'ship_phone', 'bill_first_name', 'bill_last_name', 'bill_company', 'bill_email', 'bill_address1', 'bill_address2', 'bill_city', 'bill_zip', 'bill_state', 'bill_country', 'bill_phone', 'ship_status', 'ship_method', 'tracking_no', 'ship_date', 'vLang_flag', 'browser_info', 'customer_ip', 'is_review_email_send', 'return_transaction_info', 'return_amount', 'return_gc_amount', 'ref_reason', 'return_by_adm', 'comments', 'paypal_payer_id', 'paypal_transaction_id', 'paypal_transaction_status', 'paypal_transaction_date', 'is_only_gc', 'dropship', 'free_gift', 'gift_from', 'gift_to', 'gift_message_customer', 'stampsTxId', 'stamps_url', 'phone_order_receipt', 'is_pick_order', 'is_phone_order', 'cancellation_reasons', 'use_credit_limit', 'cust_current_credit_limit', 'apply_credit', 'remaining_credit', 'is_dropship_order', 'shipping_signature', 'is_count_sold_quantity', 'IsVender', 'Is_GiftCertificatPurchase', 'refund_amount', 'is_outofstock', 'list_outofstock', 'shippingModeId', 'is_order_deleted', 'shipping_days'
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
        'orders_id' => 'int', 'customer_id' => 'int', 'orders_no' => 'string', 'order_datetime' => 'timestamp', 'order_upd_datetime' => 'timestamp', 'gift_message' => 'string', 'is_gift_order' => 'string', 'coupon_id' => 'int', 'Second_coupon_id' => 'int', 'coupon_code' => 'string', 'gc_code' => 'string', 'refer_id' => 'int', 'shipinfo' => 'string', 'payment_type' => 'string', 'payment_method' => 'string', 'ccinfo' => 'string', 'transaction_info' => 'string', 'payment_gateway_response' => 'string', 'order_comment' => 'string', 'customer_comment' => 'string', 'remark' => 'string', 'customer_browser' => 'string', 'currency_info' => 'string', 'user_type' => 'string', 'ilevelid' => 'int', 'ship_first_name' => 'string', 'ship_last_name' => 'string', 'ship_company' => 'string', 'ship_email' => 'string', 'ship_address1' => 'string', 'ship_address2' => 'string', 'ship_city' => 'string', 'ship_zip' => 'string', 'ship_state' => 'string', 'ship_country' => 'string', 'ship_phone' => 'string', 'bill_first_name' => 'string', 'bill_last_name' => 'string', 'bill_company' => 'string', 'bill_email' => 'string', 'bill_address1' => 'string', 'bill_address2' => 'string', 'bill_city' => 'string', 'bill_zip' => 'string', 'bill_state' => 'string', 'bill_country' => 'string', 'bill_phone' => 'string', 'ship_method' => 'string', 'tracking_no' => 'string', 'ship_date' => 'date', 'vLang_flag' => 'string', 'browser_info' => 'string', 'customer_ip' => 'string', 'return_transaction_info' => 'string', 'ref_reason' => 'string', 'return_by_adm' => 'string', 'comments' => 'string', 'paypal_payer_id' => 'string', 'paypal_transaction_id' => 'string', 'paypal_transaction_status' => 'string', 'paypal_transaction_date' => 'string', 'free_gift' => 'string', 'gift_from' => 'string', 'gift_to' => 'string', 'gift_message_customer' => 'string', 'stampsTxId' => 'string', 'stamps_url' => 'string', 'phone_order_receipt' => 'string', 'Is_GiftCertificatPurchase' => 'int', 'list_outofstock' => 'string', 'shippingModeId' => 'int', 'shipping_days' => 'int'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'order_datetime', 'order_upd_datetime', 'ship_date'
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
