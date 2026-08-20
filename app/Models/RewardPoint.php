<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $customer_id
 * @property string     $note
 * @property int        $iRewardpoint
 * @property string     $Order_No
 * @property int        $datetime
 * @property string     $admin_comment
 */
class RewardPoint extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_reward_point';

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
        'customer_id', 'note', 'iRewardpoint', 'Order_No', 'datetime', 'admin_comment'
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
        'customer_id' => 'int', 'note' => 'string', 'iRewardpoint' => 'int', 'Order_No' => 'string', 'datetime' => 'timestamp', 'admin_comment' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'datetime'
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
