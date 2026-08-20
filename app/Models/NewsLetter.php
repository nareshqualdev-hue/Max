<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $news_letter_id
 * @property string     $email
 * @property string     $first_name
 * @property string     $last_name
 * @property int        $insert_datetime
 */
class NewsLetter extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_news_letter';

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
        'news_letter_id', 'email', 'first_name', 'last_name', 'insert_datetime', 'status'
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
        'news_letter_id' => 'int', 'email' => 'string', 'first_name' => 'string', 'last_name' => 'string', 'insert_datetime' => 'timestamp'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'insert_datetime'
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
