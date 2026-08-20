<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $imanufactureid
 * @property string     $vmanufacture
 * @property string     $vdetail
 * @property string     $vlink
 * @property string     $vpage_header
 * @property string     $vmeta_keyword
 * @property string     $vmeta_description
 * @property string     $featured_skus
 * @property string     $topseller_sku
 * @property string     $brandpage_image
 * @property string     $imglogo
 * @property int        $MenEbayID
 * @property int        $WomenEbayID
 * @property int        $c_imenid
 * @property string     $c_iwomenid
 * @property int        $iwomenid
 * @property int        $imenid
 * @property int        $ishopid
 * @property string     $header_image
 * @property string     $mobile_header_image
 * @property string     $header_desc
 * @property string     $youtubelink1
 * @property string     $youtubelink2
 */
class Manufacture extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_manufacture';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'imanufactureid';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
		'vmanufacture', 'vdetail', 'vlink', 'status', 'vpage_header', 'vmeta_keyword', 'vmeta_description', 'elogo', 'ebrandpage', 'featured_skus', 'topseller_sku', 'brandpage_image', 'imglogo', 'MenEbayID', 'WomenEbayID', 'c_imenid', 'c_iwomenid', 'iwomenid', 'imenid', 'ishopid', 'header_image', 'mobile_header_image', 'header_desc', 'youtubelink1', 'youtubelink2', 'video_show', 'showhide', 'designerid', 'exclude_sku', 'sku', 'brand_history', 'history_images', 'overlap_brand_logo', 'authorized_dealer_logo', 'is_new_design', 'is_brand_show', 'is_popular'
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
        'imanufactureid' => 'int', 'vmanufacture' => 'string', 'vdetail' => 'string', 'vlink' => 'string', 'vpage_header' => 'string', 'vmeta_keyword' => 'string', 'vmeta_description' => 'string', 'featured_skus' => 'string', 'topseller_sku' => 'string', 'brandpage_image' => 'string', 'imglogo' => 'string', 'MenEbayID' => 'int', 'WomenEbayID' => 'int', 'c_imenid' => 'int', 'c_iwomenid' => 'string', 'iwomenid' => 'int', 'imenid' => 'int', 'ishopid' => 'int', 'header_image' => 'string', 'mobile_header_image' => 'string', 'header_desc' => 'string', 'youtubelink1' => 'string', 'youtubelink2' => 'string'
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
    
    public function products()
    {
        return $this->hasOne(Products::class, 'imanufactureid')->where('status','=','1')->groupBy('imanufactureid');
    }

}
