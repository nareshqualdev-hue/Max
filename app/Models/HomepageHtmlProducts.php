<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $title
 * @property string     $product_html
 * @property string     $banner_image
 * @property int        $position
 * @property string     $main_link
 */
class HomepageHtmlProducts extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_homepage_html_products';

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
        'title', 'product_html', 'banner_image', 'position', 'main_link', 'status'
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
        'title' => 'string', 'product_html' => 'string', 'banner_image' => 'string', 'position' => 'int', 'main_link' => 'string'
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
