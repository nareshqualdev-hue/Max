<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StoreSalesPerson extends Authenticatable
{
    /**
     * The table associated with the model.
     */
    protected $table = 'pu_store_sales_person';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'sales_person_id';

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
    const CREATED_AT = 'created_date';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
		'sales_person_id',
        'store_id',
        'name',
        'email',
        'status',
        'created_date'
    ];

}
