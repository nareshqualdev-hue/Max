<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EdeskOrder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pu_edesk_orders';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'edeskId';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model should be timestamped.
     * The table uses `edesk_date_time` instead of `created_at`/`updated_at`.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'edesk_order_id',
        'order_id',
        'order_detail_ids',
        'edesk_date_time',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'edesk_date_time' => 'datetime',
    ];

    /**
     * Define a relationship to the main Order model.
     * Assumes you have an App\Models\Order model with primary key `order_id`.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    // public function order()
    // {
    //     return $this->belongsTo(Order::class, 'order_id', 'order_id');
    // }
}
?>