<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $return_id
 * @property int        $customer_id
 * @property int        $orders_id
 * @property int        $orders_detail_id
 * @property int        $quantity
 * @property string     $reason
 * @property string     $damaged_item_image
 * @property string     $comments
 * @property string     $carrier
 * @property string     $order_rma_no
 * @property string     $return_shipping_label
 * @property string     $return_tracking_number
 */
class ReturnOrders extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_return_orders';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'return_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'orders_id', 'orders_detail_id', 'quantity', 'reason', 'damaged_item_image', 'submit_date', 'approv_date', 'comments', 'carrier', 'is_rma_scan', 'order_rma_no', 'status', 'return_shipping_label', 'return_tracking_number', 'receive_date', 'rejected_date', 'requested_page', 'browser_info'
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
        'return_id' => 'int', 'customer_id' => 'int', 'orders_id' => 'string', 'orders_detail_id' => 'int', 'quantity' => 'int', 'reason' => 'string', 'damaged_item_image' => 'string', 'submit_date' => 'timestamp', 'approv_date' => 'timestamp', 'comments' => 'string', 'carrier' => 'string', 'order_rma_no' => 'string', 'return_shipping_label' => 'string', 'return_tracking_number' => 'string', 'receive_date' => 'timestamp', 'rejected_date ' => 'timestamp', 'requested_page ' => 'string', 'browser_info ' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'submit_date', 'approv_date', 'receive_date', 'rejected_date'
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
