<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $products_id
 * @property string     $meta_title
 * @property string     $meta_keyword
 * @property string     $meta_description
 * @property int        $upd_datetime
 * @property string     $ebay_title
 * @property string     $ebay_size
 * @property string     $ebay_desc
 * @property string     $skunew
 * @property string     $UPCnew
 * @property string     $extra_images
 * @property string     $youtubelink
 * @property int        $wish_update_date
 * @property int        $newarrival_update_date
 * @property int        $update_stock_datetime
 * @property string     $admin_email
 * @property int        $admin_count
 * @property int        $is_admin_added_date
 * @property string     $private_code
 * @property int        $point_multiplier
 * @property string     $alternate_UPC
 * @property string     $alternate_cosmo_sku
 * @property string     $alternate_nandansons_sku
 * @property string     $alternate_perfumeworldwide_sku
 * @property string     $alternate_pca_sku
 */
class ProductsTwo extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_products_two';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'products_id', 'meta_title', 'meta_keyword', 'meta_description', 'upd_datetime', 'is_ebay', 'ebay_title', 'ebay_size', 'ebay_desc', 'ebay_price', 'skunew', 'UPCnew', 'extra_images', 'youtubelink', 'vendor_updated', 'is_copied', 'active_seller_price', 'active_seller_update', 'is_wish_created', 'wish_update_date', 'newarrival_update_date', 'is_wish_express', 'walmart_active_seller_price', 'walmart_active_seller_update', 'update_stock_datetime', 'is_wish_disable', 'is_sent_wish_disable', 'is_walmart', 'is_sent_walmart_disable', 'googleshopping_enable', 'admin_email', 'is_admin_added', 'admin_count', 'is_admin_added_date', 'is_private', 'private_code', 'manual_inactivated', 'special_website_price', 'is_update_skuvault_by_cron', 'image_update', 'point_multiplier', 'alternate_UPC', 'alternate_cosmo_sku', 'alternate_nandansons_sku', 'alternate_perfumeworldwide_sku', 'alternate_pca_sku', 'is_skuvault', 'is_skuvault_price'
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
        'products_id' => 'int', 'meta_title' => 'string', 'meta_keyword' => 'string', 'meta_description' => 'string', 'upd_datetime' => 'timestamp', 'ebay_title' => 'string', 'ebay_size' => 'string', 'ebay_desc' => 'string', 'skunew' => 'string', 'UPCnew' => 'string', 'extra_images' => 'string', 'youtubelink' => 'string', 'wish_update_date' => 'timestamp', 'newarrival_update_date' => 'timestamp', 'update_stock_datetime' => 'timestamp', 'admin_email' => 'string', 'admin_count' => 'int', 'is_admin_added_date' => 'timestamp', 'private_code' => 'string', 'point_multiplier' => 'int', 'alternate_UPC' => 'string', 'alternate_cosmo_sku' => 'string', 'alternate_nandansons_sku' => 'string', 'alternate_perfumeworldwide_sku' => 'string', 'alternate_pca_sku' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'upd_datetime', 'wish_update_date', 'newarrival_update_date', 'update_stock_datetime', 'is_admin_added_date'
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
