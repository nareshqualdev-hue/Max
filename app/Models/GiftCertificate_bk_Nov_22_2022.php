<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $gc_id
 * @property int        $customer_id
 * @property int        $orders_detail_id
 * @property string     $gc_code
 * @property string     $recipient_name
 * @property string     $recipient_email
 * @property string     $subject
 * @property string     $message
 * @property string     $note
 * @property int        $google_order_detail_id
 * @property string     $your_name
 * @property string     $your_email
 * @property string     $giftsku
 * @property string     $giftimage
 */
class GiftCertificate extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_gift_certificate';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'gc_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'orders_detail_id', 'gc_code', 'gc_value', 'remaining_value', 'recipient_name', 'recipient_email', 'subject', 'message', 'approved_datetime', 'last_used_date', 'purchase_datetime', 'note', 'status', 'google_order_detail_id', 'is_added_by_admin', 'your_name', 'your_email', 'deliverydate', 'giftsku', 'giftimage', 'is_email'
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
        'gc_id' => 'int', 'customer_id' => 'int', 'orders_detail_id' => 'int', 'gc_code' => 'string', 'recipient_name' => 'string', 'recipient_email' => 'string', 'subject' => 'string', 'message' => 'int', 'approved_datetime' => 'timestamp', 'last_used_date' => 'timestamp', 'purchase_datetime' => 'timestamp', 'note' => 'string', 'google_order_detail_id' => 'int', 'your_name' => 'string', 'your_email' => 'string', 'deliverydate ' => 'timestamp', 'giftsku' => 'string', 'giftimage' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'approved_datetime', 'last_used_date', 'purchase_datetime', 'deliverydate'
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
