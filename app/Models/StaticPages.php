<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $static_pages_id
 * @property string     $name
 * @property string     $title
 * @property string     $content
 * @property string     $meta_title
 * @property string     $meta_keywords
 * @property string     $meta_description
 * @property int        $position
 */
class StaticPages extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_static_pages';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'static_pages_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'title', 'content', 'meta_title', 'meta_keywords', 'meta_description', 'status', 'position'
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
        'static_pages_id' => 'int', 'name' => 'string', 'title' => 'string', 'content' => 'string', 'meta_title' => 'string', 'meta_keywords' => 'string', 'meta_description' => 'string', 'position' => 'int'
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
