<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $customer_id
 * @property string     $sender
 * @property string     $receiver
 * @property timestamp  $refer_datetime
 * @property string     $refer_comment
 */
class ReferFriend extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_referfriend';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'referid';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'sender', 'receiver', 'refer_datetime', 'refer_comment', 'is_sender_notified', 'estatus'
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
        'customer_id' => 'int', 'sender' => 'string', 'receiver' => 'string', 'refer_datetime' => 'timestamp', 'refer_comment' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'refer_datetime'
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
