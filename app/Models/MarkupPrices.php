<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $markup_price_id
 * @property string     $markup_lable
 * @property string     $markup_value
 * @property string     $markup_percent
 */
class MarkupPrices extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_markup_prices';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'markup_price_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'markup_price_id', 'markup_lable', 'markup_value', 'markup_percent'
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
        'markup_price_id' => 'int', 'markup_lable' => 'string', 'markup_value' => 'string', 'markup_percent' => 'string'
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
