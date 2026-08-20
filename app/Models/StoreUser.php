<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StoreUser extends Authenticatable
{
    /**
     * The table associated with the model.
     */
    protected $table = 'pu_store_user';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'store_user_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'int';

    /**
     * Custom timestamp columns
     */
    const CREATED_AT = 'store_user_created_at';
    const UPDATED_AT = 'store_user_updated_at';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'store_id',
        'store_user_email',
        'store_user_password',
        'first_name',
        'last_name',
        'phone',
        'store_user_role',
        '2fa_security',
        '2fa_security_code',
        'last_login_date_time',
        'last_login_ip',
        'last_login_browser',
        'created_by',
        'store_user_status'
    ];

}
