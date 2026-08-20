<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $title
 * @property string     $detail_html
 * @property string     $instant_image
 * @property string     $sucessmsg
 * @property string     $success_image
 * @property int        $update_time
 */
class InstantCouponText extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_instant_coupon_text';

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
        'title', 'detail_html', 'instant_image', 'sucessmsg', 'success_image', 'update_time'
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
        'title' => 'string', 'detail_html' => 'string', 'instant_image' => 'string', 'sucessmsg' => 'string', 'success_image' => 'string', 'update_time' => 'timestamp'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'update_time'
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
