<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Session;
/**
 * @property int        $products_id
 * @property string     $sku
 * @property string     $image
 * @property string     $product_name
 * @property string     $product_description
 * @property string     $short_description
 * @property string     $vtype
 * @property string     $gender
 * @property int        $brand_id
 * @property int        $imanufactureid
 * @property int        $minimum_stock
 * @property int        $current_stock
 * @property string     $UPC
 * @property int        $w_markup_percent
 * @property int        $is_sold_quantity
 * @property string     $cosmo_sku
 * @property int        $cosmo_current_stock
 * @property string     $nandansons_sku
 * @property int        $nandansons_current_stock
 * @property string     $perfumeworldwide_sku
 * @property int        $perfumeworldwide_currentstock
 * @property string     $pca_sku
 * @property int        $pca_current_stock
 * @property int        $display_position
 * @property int        $add_datetime
 * @property int        $upd_datetime
 * @property string     $refine_feature
 * @property string     $fragrance_family
 * @property string     $formulation
 * @property string     $coverage
 * @property string     $finish
 * @property string     $skin_type
 * @property string     $fragrance_occasion
 * @property string     $fragrance_personality
 * @property string     $fragrance_seasons
 * @property string     $size
 * @property string     $variation_id
 */
class Products extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_products';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'products_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sku', 'image', 'product_name', 'product_description', 'short_description', 'vtype', 'gender', 'brand_id', 'imanufactureid', 'retail_price', 'our_price', 'sale_price', 'wholesale_price', 'minimum_stock', 'current_stock', 'celebrity', 'new_arrival', 'featured', 'clearance', 'top_seller', 'status', 'UPC', 'product_type', 'w_our_cost', 'w_markup_percent', 'is_sold_quantity', 'is_free_gift_products', 'cosmo_sku', 'cosmo_retail_price', 'cosmo_price', 'cosmo_our_price', 'cosmo_current_stock', 'nandansons_sku', 'nandansons_retail_price', 'nandansons_price', 'nandansons_our_price', 'nandansons_current_stock', 'perfumeworldwide_sku', 'perfumeworldwide_retail_price', 'perfumeworldwide_price', 'perfumeworldwide_our_price', 'perfumeworldwide_currentstock', 'cosmo_wholesale_price', 'nandansons_wholesale_price', 'perfumeworldwide_wholesale_price', 'pca_sku', 'pca_retail_price', 'pca_price', 'pca_our_price', 'pca_current_stock', 'pca_wholesale_price', 'display_position', 'add_datetime', 'upd_datetime', 'refine_feature', 'fragrance_family', 'formulation', 'coverage', 'finish', 'skin_type', 'is_atomizer', 'fragrance_occasion', 'fragrance_personality', 'fragrance_seasons', 'size', 'maxtwodaydelivery', 'is_gift_wrap', 'variation_id', 'shipping_weight', 'is_two_day_enable'
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
        'products_id' => 'int', 'sku' => 'string', 'image' => 'string', 'product_name' => 'string', 'product_description' => 'string', 'short_description' => 'string', 'vtype' => 'string', 'gender' => 'string', 'brand_id' => 'int', 'imanufactureid' => 'int', 'minimum_stock' => 'int', 'current_stock' => 'int', 'UPC' => 'string', 'w_markup_percent' => 'int', 'is_sold_quantity' => 'int', 'cosmo_sku' => 'string', 'cosmo_current_stock' => 'int', 'nandansons_sku' => 'string', 'nandansons_current_stock' => 'int', 'perfumeworldwide_sku' => 'string', 'perfumeworldwide_currentstock' => 'int', 'pca_sku' => 'string', 'pca_current_stock' => 'int', 'display_position' => 'int', 'add_datetime' => 'timestamp', 'upd_datetime' => 'timestamp', 'refine_feature' => 'string', 'fragrance_family' => 'string', 'formulation' => 'string', 'coverage' => 'string', 'finish' => 'string', 'skin_type' => 'string', 'fragrance_occasion' => 'string', 'fragrance_personality' => 'string', 'fragrance_seasons' => 'string', 'size' => 'string', 'variation_id' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'add_datetime', 'upd_datetime'
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
    /*
    public function getShortDescriptionAttribute($short_description)
    {
       return $short_description;
    }
    */
    
    public function manufacture()
	{
		return $this->belongsTo(Manufacture::class, 'imanufactureid')->where('status','=','1')->groupBy('imanufactureid')->orderBy('vmanufacture');
	}
}
