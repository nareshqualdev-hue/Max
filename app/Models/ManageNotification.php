<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $notification_text
 * @property string     $color_picker
 * @property string     $show_notification
 * @property Date       $start_date
 * @property Date       $end_date
 * @property string     $upcoming_notification_text
 * @property string     $upcoming_color_picker
 * @property Date       $upcoming_start_date
 * @property Date       $upcoming_end_date
 */
class ManageNotification extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_manage_notification';

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
        'notification_text', 'color_picker', 'show_notification', 'start_date', 'end_date', 'upcoming_notification_text', 'upcoming_color_picker', 'upcoming_start_date', 'upcoming_end_date'
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
        'notification_text' => 'string', 'color_picker' => 'string', 'show_notification' => 'string', 'start_date' => 'date', 'end_date' => 'date', 'upcoming_notification_text' => 'string', 'upcoming_color_picker' => 'string', 'upcoming_start_date' => 'date', 'upcoming_end_date' => 'date'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_date', 'end_date', 'upcoming_start_date', 'upcoming_end_date'
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
