<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $category_id
 * @property int        $products_id
 */
class ProductsCategory extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_products_category';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'products_category_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'category_id', 'products_id'
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
        'category_id' => 'int', 'products_id' => 'int'
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
	
	public function Category()
	{
		return $this->belongsTo(Category::class,'category_id')->where('status','1');
	}
}
