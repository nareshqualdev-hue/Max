<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $cba_iorder_id
 * @property string     $AmazonOrderID
 * @property string     $NotificationReferenceId
 * @property string     $OrderChannel
 * @property string     $OrderDate
 * @property string     $BuyerName
 * @property string     $BuyerEmailAddress
 * @property string     $BuyerPhoneNumber
 * @property string     $Ship_Name
 * @property string     $Ship_AddressFieldOne
 * @property string     $Ship_AddressFieldTwo
 * @property string     $Ship_AddressFieldThree
 * @property string     $Ship_City
 * @property string     $Ship_State
 * @property string     $Ship_PostalCode
 * @property string     $Ship_CountryCode
 * @property string     $Ship_PhoneNumber
 * @property string     $ShippingServiceLevel
 * @property float      $OrderSubTotal
 * @property float      $OrderShippingCharge
 * @property float      $OrderSalesTax
 * @property float      $GiftWrappingCharge
 * @property float      $OrderPromotionalDiscount
 * @property float      $OrderTotal
 * @property string     $OrderStatus
 * @property string     $OrderComments
 * @property string     $OrderTransactionID
 * @property string     $OrderShipTrackingNo
 * @property string     $OrderShipCarrier
 * @property string     $OrderShipMethod
 * @property string     $OrderDisplayShipMethod
 * @property Date       $OrderShipDate
 * @property string     $OrderAckStatus
 * @property string     $coupon_no
 * @property float      $coupon_amount
 * @property string     $gc_id
 * @property string     $gc_code
 * @property float      $gc_amount
 * @property string     $admin_notes
 * @property float      $auto_discount
 * @property float      $quantity_discount
 * @property float      $reward_discount
 * @property float      $refer_amount
 * @property int        $order_upd_datetime
 */
class AmazonOrder extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_amazon_order';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'cba_iorder_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'AmazonOrderID', 'NotificationReferenceId', 'OrderChannel', 'OrderDate', 'BuyerName', 'BuyerEmailAddress', 'BuyerPhoneNumber', 'Ship_Name', 'Ship_AddressFieldOne', 'Ship_AddressFieldTwo', 'Ship_AddressFieldThree', 'Ship_City', 'Ship_State', 'Ship_PostalCode', 'Ship_CountryCode', 'Ship_PhoneNumber', 'ShippingServiceLevel', 'OrderSubTotal', 'OrderShippingCharge', 'OrderSalesTax', 'GiftWrappingCharge', 'OrderPromotionalDiscount', 'OrderTotal', 'OrderStatus', 'OrderComments', 'OrderTransactionID', 'OrderShipTrackingNo', 'OrderShipCarrier', 'OrderShipMethod', 'OrderDisplayShipMethod', 'OrderShipDate', 'OrderAckStatus', 'coupon_no', 'coupon_amount', 'gc_id', 'gc_code', 'gc_amount', 'admin_notes', 'auto_discount', 'quantity_discount', 'reward_discount', 'refer_amount', 'order_upd_datetime', 'is_count_sold_quantity'
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
        'cba_iorder_id' => 'int', 'AmazonOrderID' => 'string', 'NotificationReferenceId' => 'string', 'OrderChannel' => 'string', 'OrderDate' => 'string', 'BuyerName' => 'string', 'BuyerEmailAddress' => 'string', 'BuyerPhoneNumber' => 'string', 'Ship_Name' => 'string', 'Ship_AddressFieldOne' => 'string', 'Ship_AddressFieldTwo' => 'string', 'Ship_AddressFieldThree' => 'string', 'Ship_City' => 'string', 'Ship_State' => 'string', 'Ship_PostalCode' => 'string', 'Ship_CountryCode' => 'string', 'Ship_PhoneNumber' => 'string', 'ShippingServiceLevel' => 'string', 'OrderSubTotal' => 'double', 'OrderShippingCharge' => 'double', 'OrderSalesTax' => 'double', 'GiftWrappingCharge' => 'float', 'OrderPromotionalDiscount' => 'double', 'OrderTotal' => 'double', 'OrderStatus' => 'string', 'OrderComments' => 'string', 'OrderTransactionID' => 'string', 'OrderShipTrackingNo' => 'string', 'OrderShipCarrier' => 'string', 'OrderShipMethod' => 'string', 'OrderDisplayShipMethod' => 'string', 'OrderShipDate' => 'date', 'OrderAckStatus' => 'string', 'coupon_no' => 'string', 'coupon_amount' => 'float', 'gc_id' => 'string', 'gc_code' => 'string', 'gc_amount' => 'float', 'admin_notes' => 'string', 'auto_discount' => 'float', 'quantity_discount' => 'float', 'reward_discount' => 'float', 'refer_amount' => 'float', 'order_upd_datetime' => 'timestamp'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'OrderShipDate', 'order_upd_datetime'
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
