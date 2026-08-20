<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StoreCardReader extends Authenticatable
{
    public $timestamps = false;
    /**
     * The table associated with the model.
     */
    protected $table = 'pu_store_card_reader';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'store_card_reader_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'store_id',
        'reader_id',
        'reader_name',
        'location',
        'location_label',
        'status',
        'added_by'
    ];

}
