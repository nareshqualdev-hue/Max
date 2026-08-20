<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreLoginLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'pu_store_login_logs';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'log_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * Laravel won’t auto‐manage created_at/updated_at on this table.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'store_user_id',
        'store_id',
        'login_time',
        'logout_time',
    ];

    /**
     * Cast login_time and logout_time to Carbon instances.
     */
    protected $casts = [
        'login_time'  => 'datetime',
        'logout_time' => 'datetime',
    ];

    /**
     * (Optional) Relation to the store user.
     */
    public function storeUser()
    {
        return $this->belongsTo(StoreUser::class, 'store_user_id', 'store_user_id');
    }

    /**
     * (Optional) Relation to the store.
     */
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'store_id');
    }
}
