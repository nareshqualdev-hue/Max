<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StoreCashDrawerDailyOpen extends Authenticatable
{
    /**
     * The table associated with the model.
     */
    protected $table = 'pu_store_cash_drawer_daily_open';

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
        'pennies_count',
        'pennies_value',
        'nickels_count',
        'nickels_value',
        'dimes_count',
        'dimes_value',
        'quarters_count',
        'quarters_value',
        'ones_count',
        'ones_value',
        'fives_count',
        'fives_value',
        'tens_count',
        'tens_value',
        'twenties_count',
        'twenties_value',
        'fifties_count',
        'fifties_value',
        'hundreds_count',
        'hundreds_value',
        'total_balance'
    ];

}
