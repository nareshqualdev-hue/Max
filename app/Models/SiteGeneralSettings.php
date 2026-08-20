<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $site_settings_id
 * @property string     $title
 * @property string     $var_name
 * @property string     $description
 * @property string     $setting
 * @property int        $display_order
 * @property int        $section
 */
class SiteGeneralSettings extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_site_general_cron';

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
        'site_general_id', 'title', 'var_name', 'description', 'setting', 'display_order', 'status'
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
        'site_general_id' => 'int', 'title' => 'string', 'var_name' => 'string', 'description' => 'string', 'setting' => 'string', 'display_order' => 'int'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        
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
