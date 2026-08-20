<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $promotional_banner_id
 * @property string     $banner_title
 * @property string     $banner_image
 * @property string     $youtube_url
 * @property string     $banner_image_url
 * @property Date       $start_date
 * @property Date       $end_date
 */
class PromotionalBanner extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_promotional_banner';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'promotional_banner_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'banner_title', 'banner_image', 'youtube_url', 'banner_image_url', 'start_date', 'end_date'
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
        'promotional_banner_id' => 'int', 'banner_title' => 'string', 'banner_image' => 'string', 'youtube_url' => 'string', 'banner_image_url' => 'string', 'start_date' => 'date', 'end_date' => 'date'
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
