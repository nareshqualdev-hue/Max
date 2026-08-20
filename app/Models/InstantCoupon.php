<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $sweep_id
 * @property string     $first_name
 * @property string     $email
 * @property string     $gender
 * @property int        $reg_datetime
 * @property string     $ip_address
 * @property string     $user_cookie
 */
class InstantCoupon extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_instant_coupon';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sweep_id', 'first_name', 'email', 'gender', 'reg_datetime', 'status', 'ip_address', 'user_cookie'
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
        'sweep_id' => 'int', 'first_name' => 'string', 'email' => 'string', 'gender' => 'string', 'reg_datetime' => 'timestamp', 'ip_address' => 'string', 'user_cookie' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'reg_datetime'
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
