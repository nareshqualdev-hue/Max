<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $alert_id
 * @property string     $email
 * @property int        $prod_id
 * @property string     $sku
 * @property int        $datetime
 */
class Stockalert extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_stockalert';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'alert_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email', 'prod_id', 'sku', 'estatus', 'datetime'
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
        'alert_id' => 'int', 'email' => 'string', 'prod_id' => 'int', 'sku' => 'string', 'datetime' => 'timestamp'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'datetime'
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
