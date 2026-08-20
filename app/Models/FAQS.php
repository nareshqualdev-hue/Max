<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $faq_id
 * @property string     $question
 * @property string     $answer
 * @property int     	$rank
 * @property string     $status
 */
class FAQS extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_faq';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'faq_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'question', 'answer', 'rank', 'status'
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
        'faq_id' => 'int', 'question' => 'string', 'answer' => 'string', 'rank' => 'int', 'status' => 'string'
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
