<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $admin_id
 * @property string     $email
 * @property string     $password
 * @property int        $role_id
 */
class Admin extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_admin';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'admin_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email', 'password', 'insert_datetime', 'update_datetime', 'status', 'admin_type', 'rights', 'role_id', '2fa_security'
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
        'admin_id' => 'int', 'email' => 'string', 'password' => 'string', 'insert_datetime' => 'timestamp', 'update_datetime' => 'timestamp', 'role_id' => 'int'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'insert_datetime', 'update_datetime'
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
