<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $countries_id
 * @property string     $countries_name
 */
class Countries extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_countries';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'countries_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'countries_name', 'countries_iso_code_2', 'countries_iso_code_3', 'status'
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
        'countries_id' => 'int', 'countries_name' => 'string'
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
