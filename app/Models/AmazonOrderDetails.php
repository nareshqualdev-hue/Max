<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $cba_iorder_detail_id
 * @property int        $cba_iorder_id
 * @property string     $AmazonOrderID
 * @property string     $AmazonOrderItemCode
 * @property string     $MerchantId
 * @property string     $Item_SKU
 * @property string     $Item_Title
 * @property string     $Item_Description
 * @property float      $Item_UnitPrice
 * @property float      $Item_SalePrice
 * @property float      $Item_Price
 * @property int        $Item_Quantity
 * @property float      $Item_Principal
 * @property float      $Item_Shipping
 * @property float      $Item_Tax
 * @property float      $Item_ShippingTax
 * @property float      $Item_PrincipalPromo
 * @property float      $Item_ShippingPromo
 * @property string     $Item_Attributes
 * @property string     $gift_message
 * @property float      $gift_msg_charge
 * @property string     $is_gift_wrap
 */
class AmazonOrderDetails extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_amazon_order_details';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'cba_iorder_detail_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'cba_iorder_id', 'AmazonOrderID', 'AmazonOrderItemCode', 'MerchantId', 'Item_SKU', 'Item_Title', 'Item_Description', 'Item_UnitPrice', 'Item_SalePrice', 'Item_Price', 'Item_Quantity', 'Item_Principal', 'Item_Shipping', 'Item_Tax', 'Item_ShippingTax', 'Item_PrincipalPromo', 'Item_ShippingPromo', 'Item_Attributes', 'gift_message', 'gift_msg_charge', 'is_gift_wrap'
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
        'cba_iorder_detail_id' => 'int', 'cba_iorder_id' => 'int', 'AmazonOrderID' => 'string', 'AmazonOrderItemCode' => 'string', 'MerchantId' => 'string', 'Item_SKU' => 'string', 'Item_Title' => 'string', 'Item_Description' => 'string', 'Item_UnitPrice' => 'double', 'Item_SalePrice' => 'double', 'Item_Price' => 'double', 'Item_Quantity' => 'int', 'Item_Principal' => 'double', 'Item_Shipping' => 'double', 'Item_Tax' => 'double', 'Item_ShippingTax' => 'double', 'Item_PrincipalPromo' => 'double', 'Item_ShippingPromo' => 'double', 'Item_Attributes' => 'string', 'gift_message' => 'string', 'gift_msg_charge' => 'double', 'is_gift_wrap' => 'string'
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
