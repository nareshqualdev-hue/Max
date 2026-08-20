<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $category_id
 * @property int        $parent_id
 * @property string     $category_name
 * @property string     $description
 * @property string     $banner_image
 * @property string     $mob_banner_image
 * @property string     $mega_menu_image
 * @property string     $banner_thumb_image
 * @property string     $featured_productlist
 * @property int        $display_position
 * @property string     $meta_keywords
 * @property string     $meta_description
 * @property string     $meta_title
 * @property int        $ebay_category_id
 * @property string     $Jet_mapping_category
 * @property string     $ebaycatid
 * @property string     $ebaystorecatid1
 * @property string     $ebaystorecatid2
 * @property string     $mega_menu_link
 */
class Category extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_category';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'category_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'parent_id', 'category_name', 'description', 'banner_image', 'mob_banner_image', 'mega_menu_image', 'banner_thumb_image', 'featured_productlist', 'display_position', 'meta_keywords', 'meta_description', 'meta_title', 'ebay_category_id', 'display_top', 'Jet_mapping_category', 'status', 'ebaycatid', 'ebaystorecatid1', 'ebaystorecatid2', 'Template_list', 'mega_menu_link'
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
        'category_id' => 'int', 'parent_id' => 'int', 'category_name' => 'string', 'description' => 'string', 'banner_image' => 'string', 'mob_banner_image' => 'string', 'mega_menu_image' => 'string', 'banner_thumb_image' => 'string', 'featured_productlist' => 'string', 'display_position' => 'int', 'meta_keywords' => 'string', 'meta_description' => 'string', 'meta_title' => 'string', 'ebay_category_id' => 'int', 'Jet_mapping_category' => 'string', 'ebaycatid' => 'string', 'ebaystorecatid1' => 'string', 'ebaystorecatid2' => 'string', 'mega_menu_link' => 'string'
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
	
	public function children()
	{
		return $this->hasMany(self::class, 'parent_id');
	}

	public function parent()
	{
		return $this->belongsTo(self::class, 'parent_id');
	}
}
