<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $image_id
 * @property string     $title
 * @property string     $detail_html
 * @property string     $link
 * @property string     $home_image
 * @property string     $mobile_image
 * @property int        $position
 * @property string     $image_alt
 * @property Date       $start_date
 * @property Date       $end_date
 * @property string     $flag
 * @property string     $include_sku
 * @property string     $exclude_sku
 */
class HomeImage extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_home_image';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'image_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'image_id', 'title', 'detail_html', 'link', 'home_image', 'mobile_image', 'position', 'status', 'image_alt', 'section', 'start_date', 'end_date', 'flag', 'include_sku', 'exclude_sku'
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
        'image_id' => 'int', 'title' => 'string', 'detail_html' => 'string', 'link' => 'string', 'home_image' => 'string', 'mobile_image' => 'string', 'position' => 'int', 'image_alt' => 'string', 'start_date' => 'date', 'end_date' => 'date', 'flag' => 'string', 'include_sku' => 'string', 'exclude_sku' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_date', 'end_date'
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
