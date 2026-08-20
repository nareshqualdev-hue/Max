<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $state_id
 * @property int        $countries_id
 * @property string     $code
 * @property string     $name
 */
class State extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_state';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'state_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'countries_id', 'code', 'name', 'status'
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
        'state_id' => 'int', 'countries_id' => 'int', 'code' => 'string', 'name' => 'string'
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
