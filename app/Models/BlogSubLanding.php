<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogSubLanding extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_blog_sublanding';

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
        'main_blogid','blog_section_id', 'main_banner_img', 'banner_img1', 'mobile_banner_img', 'banner_link', 'banner_title', 'banner_description', 'banner_link_title', 'rank', 'status','author_designation','author_image','meta_title','meta_keywords','meta_description', 'description_title'
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

    public function blogSections(){
		return $this->hasOne(BlogSection::class,'blog_section_id','blog_section_id');
	}

     public function blogmain(){
        return $this->hasOne(Blog::class,'id','main_blogid');
    }

}
