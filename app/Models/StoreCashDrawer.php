<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StoreCashDrawer extends Authenticatable
{
    /**
     * The table associated with the model.
     */
    protected $table = 'pu_store_cash_drawer';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'store_cash_drawer_id';

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
    const CREATED_AT = 'added_datetime';
    //const UPDATED_AT = 'store_user_updated_at';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'store_id',
        'open_balance',
        'cash_sales',
        'cash_refunds',
        'bank_deposite',
        'current_balance'
    ];

}
