<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $promotional_id
 * @property string     $title
 * @property string     $sub_title
 * @property string     $banner
 * @property string     $banner_mobile
 * @property string     $banner_link
 * @property string     $sku
 */
class Promotional extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_promotional';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'promotional_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'sub_title', 'banner', 'banner_mobile', 'banner_link', 'sku', 'type'
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
        'promotional_id' => 'int', 'title' => 'string', 'sub_title' => 'string', 'banner' => 'string', 'banner_mobile' => 'string', 'banner_link' => 'string', 'sku' => 'string'
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
