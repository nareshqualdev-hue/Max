<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $currency_id
 * @property string     $currency_code
 * @property string     $currency_name
 * @property float      $exchange_rate
 * @property string     $currency_symbol
 * @property string     $image_name
 * @property string     $class_name
 */
class Currency extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_currency';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'currency_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'currency_code', 'currency_name', 'exchange_rate', 'currency_symbol', 'image_name', 'class_name', 'status'
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
        'currency_id' => 'int', 'currency_code' => 'string', 'currency_name' => 'string', 'exchange_rate' => 'float', 'currency_symbol' => 'string', 'image_name' => 'string', 'class_name' => 'string'
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
