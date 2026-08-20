<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $ihomepageproductid
 * @property string     $products
 * @property string     $home_flag
 * @property string     $product_link
 * @property int        $position
 */
class HomepageProduct extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_homepage_product';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ihomepageproductid';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'products', 'home_flag', 'product_link', 'chk_flag', 'position'
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
        'ihomepageproductid' => 'int', 'products' => 'string', 'home_flag' => 'string', 'product_link' => 'string', 'position' => 'int'
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
