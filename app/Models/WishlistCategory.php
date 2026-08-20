<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $wishlist_category_id
 * @property int        $customer_id
 * @property string     $name
 * @property string     $description
 */
class WishlistCategory extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_wishlist_category';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'wishlist_category_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'name', 'description', 'status'
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
        'wishlist_category_id' => 'int', 'customer_id' => 'int', 'name' => 'string', 'description' => 'string'
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
