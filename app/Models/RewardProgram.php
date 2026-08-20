<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardProgram extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_reward_program';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'section_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'section_header', 'section_title', 'value1', 'value2', 'button_text', 'button_link', 'image_name', 'mobile_image_name', 'section_rank', 'status'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        
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