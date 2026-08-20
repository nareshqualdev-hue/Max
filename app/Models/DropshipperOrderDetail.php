<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $orders_detail_id
 * @property int        $orders_id
 * @property string     $orders_no
 * @property int        $products_id
 * @property string     $sku
 * @property string     $product_name
 * @property string     $attribute_info
 * @property int        $quantity
 * @property int        $returned_qty
 * @property string     $returned_note
 * @property string     $dtl_ship_method
 * @property string     $dtl_tracking_no
 * @property Date       $dtl_ship_date
 * @property string     $VendorSKU
 * @property string     $po_number
 * @property int        $customer_id
 * @property int        $Original_product_stock
 */
class DropshipperOrderDetail extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_dropshipper_order_detail';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'orders_detail_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'orders_id', 'orders_no', 'products_id', 'sku', 'product_name', 'attribute_info', 'quantity', 'price', 'total', 'status', 'item_price', 'eback_order', 'ereturn_item', 'returned_qty', 'returned_note', 'dtl_ship_status', 'dtl_ship_method', 'dtl_tracking_no', 'dtl_ship_date', 'percent_discount', 'item_qty_discount', 'display_price', 'free_product', 'excluded_flag', 'is_gift_wrap', 'is_free_gift_products', 'VendorSKU', 'IsCosmo', 'IsNandansons', 'IsPerfumePW', 'IsPCA', 'po_number', 'is_po_number', 'is_outofstock', 'coupon_itemwise_discount', 'customer_id', 'Original_product_stock'
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
        'orders_detail_id' => 'int', 'orders_id' => 'int', 'orders_no' => 'string', 'products_id' => 'int', 'sku' => 'string', 'product_name' => 'string', 'attribute_info' => 'string', 'quantity' => 'int', 'returned_qty' => 'int', 'returned_note' => 'string', 'dtl_ship_method' => 'string', 'dtl_tracking_no' => 'string', 'dtl_ship_date' => 'date', 'VendorSKU' => 'string', 'po_number' => 'string', 'customer_id' => 'int', 'Original_product_stock' => 'int'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'dtl_ship_date'
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
