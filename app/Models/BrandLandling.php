<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $title
 * @property string     $sku
 * @property int        $position
 * @property string     $banner_image
 * @property string     $mobile_banner_image
 * @property string     $bundle_banner_image
 * @property string     $banner_link
 * @property string     $youtube_link
 */
class BrandLandling extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_brand_landling';

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
        'title', 'sku', 'position', 'banner_image', 'mobile_banner_image', 'bundle_banner_image','banner_link','youtube_link', 'video_show', 'status'
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
        'title' => 'string', 'sku' => 'string', 'position' => 'int', 'banner_image' => 'string', 'mobile_banner_image' => 'string', 'bundle_banner_image' => 'string', 'banner_link'=> 'string', 'youtube_link' => 'string'
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
