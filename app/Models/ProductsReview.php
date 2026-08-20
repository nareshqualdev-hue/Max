<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $review_id
 * @property int        $products_id
 * @property string     $sku
 * @property string     $star_rate
 * @property string     $first_name
 * @property string     $city
 * @property string     $state
 * @property string     $country
 * @property string     $user_review
 * @property int        $customer_id
 * @property Date       $date
 * @property string     $ip_address
 * @property string     $email
 * @property string     $fragrance_smell
 */
class ProductsReview extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_products_review';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'review_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'products_id', 'sku', 'star_rate', 'first_name', 'city', 'state', 'country', 'user_review', 'customer_id', 'date', 'approved', 'ip_address', 'email', 'fragrance_smell'
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
        'review_id' => 'int', 'products_id' => 'int', 'sku' => 'string', 'star_rate' => 'string', 'first_name' => 'string', 'city' => 'string', 'state' => 'string', 'country' => 'string', 'user_review' => 'string', 'customer_id' => 'int', 'date' => 'date', 'ip_address' => 'string', 'email' => 'string', 'fragrance_smell' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'date'
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
