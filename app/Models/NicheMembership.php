<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $niche_membership_id
 * @property string     $email
 * @property string     $first_name
 * @property string     $last_name
 */
class NicheMembership extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_niche_membership';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'niche_membership_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email', 'first_name', 'last_name', 'gender'
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
        'niche_membership_id' => 'int', 'email' => 'string', 'first_name' => 'string', 'last_name' => 'string'
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
