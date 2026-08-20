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
class StoreInventory extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_store_inventory';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'inventory_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'current_stock', 'products_id', 'store_id'
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
        'inventory_id' => 'int', 'current_stock' => 'int', 'products_id' => 'int', 'store_id' => 'int'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
   
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
}
