<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendedProduct extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_recommended_product';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'recommendedproductid';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'products', 'show_products_by', 'start_date', 'end_date', 'status'
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
        'recommendedproductid ' => 'int', 'title' => 'string', 'products' => 'string', 'start_date' => 'timestamp', 'end_date' => 'timestamp'
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
