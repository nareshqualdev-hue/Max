<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string     $title
 * @property string     $author_name
 * @property date        $date
 * @property string     $blog_image
 * @property string     $blog_img_link
 * @property string     blog_page_title
 */
class Blog extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_blog_landing';

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
        'title', 'author_name', 'date', 'blog_image','blog_img_link','status','blog_page_title','blog_detail_video','mobile_detail_video','video_status','flag'
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
        'title' => 'string', 'author_name' => 'string', 'date' => 'date', 'blog_image' => 'string', 'blog_img_link'=> 'string','blog_page_title' => 'string',
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
