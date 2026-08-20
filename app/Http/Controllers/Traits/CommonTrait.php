<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

use App\Http\Controllers\Traits\VendorTrait;
use Illuminate\Support\Facades\Log;
use App\Models\HomepageProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductsCategory;
use App\Models\ProductsReview;
use App\Models\Listingmenu;
use App\Models\Manufacture;
use App\Models\Products;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\GiftCertificate;
use App\Models\Customer;
use App\Models\RewardRule;
use App\Models\MailBanner;
use App\Models\RewardPoint;
use App\Models\Dealofweek;
use App\Models\ReferFriend;
use App\Models\SiteSettings;
use App\Http\Services\CacheService;
use DB;
use Session;
use Cache;
use PDF;
use Carbon\Carbon;

trait CommonTrait
{
	use VendorTrait;

	public function Make_Price($text, $currency_symbol=false, $round=false) {
		$curr_rate = Session::get('currency_rate');
		$text1	   = $text*$curr_rate;

		if(preg_match("/".config('global.CONTROL_PANEL_NAME')."/i",$_SERVER['REQUEST_URI'])) {
			$text1 = $text;
			return number_format($text1, 2, '.', '');
		}
		if($currency_symbol == true) {
			if($round==true) {
				return Session::get('currency_symbol').number_format(round($text1), 0, '', ',');
			}else {
				return Session::get('currency_symbol').number_format($text1, 2, '.', ',');
			}
		}else {
			return number_format($text1, 2, '.', '');
		}
	}

	public function CountOptions($VariationIDs=[],$DisplayProducts=[],$CatArrVal=[],$Flag='',$ismaxTwoDay = '', $isPocketPerfume = '',$isMaxaromaEdit='No'){
		//echo count($DisplayProducts)nn; exit;
		$NewProduct = [];
		$VariationData = [];
		$VariationIDs = array_unique($VariationIDs);

		$VariationProducts = Cache::get('variation_cache');

		if (Cache::has('variation_cache')) {
			$VariationProducts = Cache::get('variation_cache');

			foreach($VariationProducts as $variation_id => $Product){

				if(Session::has("eusertype") && Session::get("eusertype")=="Wholesaler" )
				{
					$VariationData[$variation_id]['min_price'] = $Product['w_min_price'];
					$VariationData[$variation_id]['max_price'] = $Product['w_max_price'];
				}
				else
				{
					$VariationData[$variation_id]['min_price'] = $Product['min_price'];
					$VariationData[$variation_id]['max_price'] = $Product['max_price'];

				}
				$VariationData[$variation_id]['size_cnt'] = $Product['total_count'];
			}
		} else {
			$VariationProductQry = DB::table('pu_product_variations as pv')
								->select('pv.variation_id','pv.total_count','pv.min_price','pv.max_price','pv.w_min_price','pv.w_max_price')
								->whereIn('pv.variation_id',$VariationIDs);
			$VariationProducts = $VariationProductQry->get()->toArray();
			foreach($VariationProducts as $Product){

				if(Session::has("eusertype") && Session::get("eusertype")=="Wholesaler" )
				{
					$VariationData[$Product->variation_id]['min_price'] = $Product->w_min_price;
					$VariationData[$Product->variation_id]['max_price'] = $Product->w_max_price;
				}
				else
				{
					$VariationData[$Product->variation_id]['min_price'] = $Product->min_price;
					$VariationData[$Product->variation_id]['max_price'] = $Product->max_price;
				}
				$VariationData[$Product->variation_id]['size_cnt'] = $Product->total_count;
			}
		}
		//echo "<pre>"; print_r($VariationData['9944436102']); exit;
		foreach($DisplayProducts as $ProductNew){
			if(isset($VariationData[$ProductNew->variation_id])){
				$ProductNew->size_cnt = $VariationData[$ProductNew->variation_id]['size_cnt'];
			} else {
				$ProductNew->size_cnt = 0;
			}

			if(isset($VariationData[$ProductNew->variation_id]) && count($VariationData[$ProductNew->variation_id]) > 0)
			{
				$ProductNew->minPrice = $VariationData[$ProductNew->variation_id]['min_price'];
				$ProductNew->maxPrice = $VariationData[$ProductNew->variation_id]['max_price'];
			} else {
				$ProductNew->minPrice = 0;
				$ProductNew->maxPrice = 0;
			}
			$NewProduct[] = $ProductNew;
		}
		if($isMaxaromaEdit == 'No')
		{
			for($i=0;$i<count($NewProduct);$i++)
			{
				/*if($isPocketPerfume=='Y')
				{
					$sql = DB::table('pu_products as po')
							->join('pu_products_category as pc','po.products_id','=','pc.products_id')
							->join('pu_category as c','pc.category_id','=','c.category_id')
							->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
									'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
									'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
									'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price','po.vtype','po.variation_id','po.refine_feature','po.product_type','pc.category_id')
							->WHERE('po.variation_id',$NewProduct[$i]->variation_id);

							$pkt_perfume_arr = array(68,69,70,71);
							$sql->WhereIn('pc.category_id',$pkt_perfume_arr);
							$sql->Where('po.is_atomizer','Yes');

							$VariationProducts = $sql->groupBy('po.products_id')->get();

							$PriceArrVal = array();
							$WholesalePriceArrVal = array();
							for($k=0;$k<$VariationProducts->count();$k++)
							{
								$Product= $this->SetProduct($VariationProducts[$k]);
								$PriceArrVal[$k] = $Product->product_price;
							}
							$NewProduct[$i]->size_cnt  = 0;
							if($VariationProducts->count() > 0)
							{
								$NewProduct[$i]->size_cnt  = $VariationProducts->count();
							}
							$NewProduct[$i]->min_price = 0;
							$NewProduct[$i]->max_price = 0;

							if(count($PriceArrVal) > 0)
							{
								$NewProduct[$i]->minPrice= min($PriceArrVal);
								$NewProduct[$i]->maxPrice= max($PriceArrVal);
							}

				}*/
				if(($NewProduct[$i]->is_atomizer == "Yes" && $isPocketPerfume=='N') || $NewProduct[$i]->stock == "Out")
				{
					$sql = DB::table('pu_products as po')
							->join('pu_products_category as pc','po.products_id','=','pc.products_id')
							->join('pu_category as c','pc.category_id','=','c.category_id')
							->leftJoin('pu_products_one as p1','po.products_id','=','p1.products_id')
							// ->join('pu_brand as b','b.brand_id','=','po.brand_id')
							// ->join('pu_manufacture as m',function($join){
							// 	$join->on('po.imanufactureid','=','m.imanufactureid');
							// 	$join->on('b.imanufactureid','=','m.imanufactureid');
							// })
							->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
									'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
									'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
									'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price','po.vtype','po.variation_id','po.refine_feature',
									'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
									'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
									'po.product_type','pc.category_id','p1.extra_images')
							->WHERE('po.variation_id',$NewProduct[$i]->variation_id);
						//$sql->where('po.status','=','1')->where('c.status','=','1')->where('b.status','=','1')->where('m.status','=','1');
						$sql->where('po.status','=','1')->where('c.status','=','1');
						if($Flag == "TOP_SELLERS" || $Flag == "NEW_ARRIVALS")
						{
							if($Flag == "NEW_ARRIVALS"){
								if(count($CatArrVal) > 0)
									$sql->whereIn('pc.category_id',$CatArrVal);
								// $sql->whereNotIn('pc.category_id',['198','199','200','201']);

								// $sql->where(function($query){
								// 	$from = Carbon::now()->subDays(30);
								// 	$to = Carbon::now();
								// 	$query->where('po.new_arrival','=','Yes');
								// 	$query->orwhereBetween(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),[$from,$to]);
								// });
							}
						}

						if($ismaxTwoDay == 'Y'){
							$sql->where('po.maxtwodaydelivery','Yes');
							$sql->whereNotIn('po.sku', function ($query) {
									$query->select('product_sku')->from('pu_dealofweek')->where('status','=','1')->where('start_date','<=',date('Y-m-d H:i'))->where('end_date','>=',date('Y-m-d H:i'));
							});
						}

						$sql->orderBy('po.current_stock');
						$sql->orderBy('po.nandansons_current_stock');
						$sql->orderBy('po.cosmo_current_stock');
						$sql->orderBy('po.pca_current_stock');
						$sql->orderBy('po.perfumeworldwide_currentstock');
						$sql->orderBy('po.nd_current_stock');
						$VariationProducts = $sql->groupBy('po.products_id')->get();

					foreach($VariationProducts as $Product)
					{
						$Product->vmanufacture = $NewProduct[$i]->vmanufacture;
						$Product->brand_name = $NewProduct[$i]->brand_name;
						$Product->gender = $NewProduct[$i]->gender;

						if ($Product->products_id == $NewProduct[$i]->products_id)
							continue;
						if ($Product->variation_id != $NewProduct[$i]->variation_id)
							continue;

						$Product = $this->SetProduct($Product);

						if ($Product->is_atomizer == "Yes" && $Product->stock == "Out")
							continue;
						if ($Product->stock == "Out" && $Product->is_atomizer == "No")
							continue;

						if ($Product->is_atomizer == "No")
						{
							if ($Product->stock == "In" && $Product->category_id == $NewProduct[$i]->category_id)
							{
								$NewProduct[$i] = $this->PrepareProduct($Product,$i);
								if(isset($VariationData[$NewProduct[$i]->variation_id]['size_cnt']))
									$NewProduct[$i]->size_cnt = $VariationData[$NewProduct[$i]->variation_id]['size_cnt'];
								else
									$NewProduct[$i]->size_cnt = 0;

								if(isset($VariationData[$NewProduct[$i]->variation_id]) && count($VariationData[$NewProduct[$i]->variation_id]) > 0)
								{
									$NewProduct[$i]->minPrice = $VariationData[$NewProduct[$i]->variation_id]['min_price'];
									$NewProduct[$i]->maxPrice = $VariationData[$NewProduct[$i]->variation_id]['max_price'];

								} else {
									$NewProduct[$i]->minPrice = 0;
									$NewProduct[$i]->maxPrice = 0;
								}
								if (count($CatArrVal) > 0) {
									$isAtom1 = 'No';
									for ($j = 0; $j < count($CatArrVal); $j++) {
										if ($CatArrVal[$j] != 68 && $CatArrVal[$j] != 70 &&  $CatArrVal[$j] != 71 &&  $CatArrVal[$j] != 69) {
											$isAtom1 = 'Yes';
										}
									}
									if ($isAtom1 == 'Yes') {
										break;
									}
								} else {
									break;
								}
							} else if ($Product->stock == "In") {
								$NewProduct[$i] = $this->PrepareProduct($Product,$i);
								if(isset($VariationData[$NewProduct[$i]->variation_id]['size_cnt']))
									$NewProduct[$i]->size_cnt = $VariationData[$NewProduct[$i]->variation_id]['size_cnt'];
								else
									$NewProduct[$i]->size_cnt = 0;

								if(isset($VariationData[$NewProduct[$i]->variation_id]) && count($VariationData[$NewProduct[$i]->variation_id]) > 0)
								{
									$NewProduct[$i]->minPrice = $VariationData[$NewProduct[$i]->variation_id]['min_price'];
									$NewProduct[$i]->maxPrice = $VariationData[$NewProduct[$i]->variation_id]['max_price'];
								} else {
									$NewProduct[$i]->minPrice = 0;
									$NewProduct[$i]->maxPrice = 0;
								}

							} else {
								if ($NewProduct[$i]->is_atomizer != 'Yes' && $NewProduct[$i]->stock != 'In') {
									$NewProduct[$i] = $this->PrepareProduct($Product,$i);
									if(isset($VariationData[$NewProduct[$i]->variation_id]['size_cnt']))
										$NewProduct[$i]->size_cnt = $VariationData[$NewProduct[$i]->variation_id]['size_cnt'];
									else
										$NewProduct[$i]->size_cnt = 0;

									if(isset($VariationData[$NewProduct[$i]->variation_id]) && count($VariationData[$NewProduct[$i]->variation_id]) > 0)
									{
										$NewProduct[$i]->minPrice = $VariationData[$NewProduct[$i]->variation_id]['min_price'];
										$NewProduct[$i]->maxPrice = $VariationData[$NewProduct[$i]->variation_id]['max_price'];
									} else {
										$NewProduct[$i]->minPrice = 0;
										$NewProduct[$i]->maxPrice = 0;
									}
								}
							}
						} else {
							if ($Product->stock == "In" && ($NewProduct[$i]->stock != 'In' || in_array(68, $CatArrVal) || in_array(70, $CatArrVal) || in_array(71, $CatArrVal) || in_array(69, $CatArrVal))) {
								$NewProduct[$i] = $this->PrepareProduct($Product,$i);
								if(isset($VariationData[$NewProduct[$i]->variation_id]['size_cnt']))
									$NewProduct[$i]->size_cnt = $VariationData[$NewProduct[$i]->variation_id]['size_cnt'];
								else
									$NewProduct[$i]->size_cnt = 0;

								if(isset($VariationData[$NewProduct[$i]->variation_id]) && count($VariationData[$NewProduct[$i]->variation_id]) > 0)
								{
									$NewProduct[$i]->minPrice = $VariationData[$NewProduct[$i]->variation_id]['min_price'];
									$NewProduct[$i]->maxPrice = $VariationData[$NewProduct[$i]->variation_id]['max_price'];
								} else {
									$NewProduct[$i]->minPrice = 0;
									$NewProduct[$i]->maxPrice = 0;
								}
								$isAtom = 'No';
								for ($j = 0; $j < count($CatArrVal); $j++) {
									if ($CatArrVal[$j] == 68 || $CatArrVal[$j] == 70 ||  $CatArrVal[$j] == 71 ||  $CatArrVal[$j] == 69) {
										$isAtom = 'Yes';
									}
								}
								if ($isAtom == 'Yes')
									break;
							}
						}
					}
				}
			}
		}
		return $NewProduct;
		//$TotalVariations = array_count_values(array_column($VariationProducts, 'variation_id'));
	}

	public function ProductSlider($Flag='',$FileFlag, $CategoryID='')
	{
		if($Flag=='NEW ARRIVALS')
		{
			if (!Cache::has('HomePageProductss')) {
					$HomeProducts = HomepageProduct::where('chk_flag','=',$Flag)->orderBy('position')->get();
					Cache::put('HomePageProductss', $HomeProducts);

				}else{

					$HomeProducts = Cache::get('HomePageProductss');

				}

		}
		if($Flag=='TOP SELLERS')
		{

			if (!Cache::has('HomePageProductssTop')) {
					$HomeProducts = HomepageProduct::where('chk_flag','=',$Flag)->orderBy('position')->get();
					Cache::put('HomePageProductssTop', $HomeProducts);

				}else{

					$HomeProducts = Cache::get('HomePageProductssTop');

				}
		}
		$SliderProducts = [];
		if($Flag == 'TOP SELLERS')
			$Flag = "BEST SELLERS";
		if($HomeProducts->count() > 0)
		{
			foreach($HomeProducts as $key => $HomeProduct)
			{

				$SliderProducts[$key]['ihomepageproductid'] = $HomeProduct->ihomepageproductid;
				$SliderProducts[$key]['title'] = $HomeProduct->home_flag;
				$SliderProducts[$key]['product_link'] = $HomeProduct->product_link;
				$SliderProducts[$key]['numb'] = $key;
				if($HomeProduct->products != '')
				{
					if($FileFlag=="Home")
					{

						$SliderProducts[$key]['products'] = $this->GetHomeProducts($Flag);
					}
					else
					{
						$SliderProducts[$key]['products'] = $this->GetSliderProducts($HomeProduct->products,$Flag,$FileFlag,$CategoryID);

						if(count($SliderProducts[$key]['products']) < 12)
						{
							$getPrdLimit = 12 - count($SliderProducts[$key]['products']);
							$ExtraProds = $this->fetchhomeprodDev($Flag,$FileFlag,$getPrdLimit,$CategoryID);
							if(count($ExtraProds) > 0)
								$SliderProducts[$key]['products'] = array_merge($SliderProducts[$key]['products'],$ExtraProds);
						}
					}
				}
			}
		}
		//dd($SliderProducts);
		return $SliderProducts;
	}
	public function GetHomeProducts($Flag='')
	{
		$SldProducts = [];
		$VariationIDs = [];
		$CatArrVal = [];
		if($Flag == "NEW ARRIVALS")
		{
			$ProdQry = DB::table('pu_products_new_arrivals')
						->select('products_id','sku','extra_images','image','product_name','maxtwodaydelivery','category_id','product_price','total_variants','min_price','max_price','w_min_price','w_max_price','product_type','short_description','vmanufacture','gender','sale_price',
									'brand_name','stock','wholesale_product_price','retail_price','maxtwodaydelivery','imanufactureid','WebsiteStock','vtype');

			if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
				$ProdQry->whereIn('product_type',['both','retailer','wholesaler']);
			else
				$ProdQry->whereIn('product_type',['both','retailer']);

		  }
		 if($Flag == "BEST SELLERS")
		 {
			$ProdQry = DB::table('pu_products_best_sellers')
						->select('products_id','sku','extra_images','image','product_name','maxtwodaydelivery','category_id','product_price','total_variants','min_price','max_price','w_min_price','w_max_price','product_type','short_description','vmanufacture','gender','sale_price',
									'brand_name','stock','wholesale_product_price','retail_price','maxtwodaydelivery','imanufactureid','WebsiteStock','vtype');

			if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
				$ProdQry->whereIn('product_type',['both','retailer','wholesaler']);
			else
				$ProdQry->whereIn('product_type',['both','retailer']);

		  }

		 if($Flag == "NEW ARRIVALS")
		 {
			if (!Cache::has('homepage_newarrival_cache'))
			 {
				$Prods = $ProdQry->get();
				Cache::put('homepage_newarrival_cache', $Prods);
			 }
			 else{

				$Prods = Cache::get('homepage_newarrival_cache');
			}
			//echo "<pre>"; print_r($Prods); exit;
		 }
		 if($Flag == "BEST SELLERS")
		 {
			 if (!Cache::has('homepage_bestseller_cache'))
			 {
				$Prods = $ProdQry->get();
				Cache::put('homepage_bestseller_cache', $Prods);
			 }
			 else{
				$Prods = Cache::get('homepage_bestseller_cache');
			}
		 }

		  // echo "<pre>"; print_r($Prods); exit;
			$SkipVariationID = [];
			$TotalProds = 0;
			$ProdIds=[];
			if($Prods->count() > 0)
			{
				//$SliderCategory = $this->GetCategories($Prods);

				foreach($Prods as $key => $Product)
				{
					$Product->minPrice = $Product->min_price;
					$Product->maxPrice = $Product->max_price;
					if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
					{
						$Product->product_price = $Product->wholesale_product_price;
						$Product->minPrice = $Product->w_min_price;
						$Product->maxPrice = $Product->w_max_price;

					}

					$product_link = config('global.SITE_URL');

					$product_name = remove_special_chars($Product->product_name);
					//$product_link.= $CatInfo[$Product->category_id].$product_name."/pid/".$Product->products_id."/".$Product->category_id;
					$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);
					//Make Product Link End

                    if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
						$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($Product->image);
						$verP = filemtime($newimageVal);

						$Product->prod_image = config('global.PRD_LARGE_IMG_URL') . $Product->image . "?ver=" . $verP;

					} else {
						$Product->prod_image = config('global.NO_IMAGE_LARGE');
					}

					if ($Product->gender == 'M'){
						$Product->gender = "sv-men";
						$Product->gendernames = "Men";
						$for_gender = ' for Men';
					} elseif ($Product->gender == 'W'){
						$Product->gender = "sv-women";
						$Product->gendernames = "Women";
						$for_gender = ' for Women';
					} elseif ($Product->gender == 'K'){
						$Product->gender = "sv-kids";
						$Product->gendernames = "Kids";
						$for_gender = ' for Kids';
					} elseif ($Product->gender == 'U'){
						$Product->gender = "sv-unisex";
						$Product->gendernames = "Unisex";
						$for_gender = ' Unisex';
					} else{
						$Product->gender = "";
						$Product->gendernames = "";
						$for_gender = '';
					}

					if($Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
					}

					if($Product->brand_name != '' && $Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
					}

					if(strlen($Product->product_name) > 45){
						$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
					} else {
						$Product->product_name = $Product->product_name;
					}

					if((empty($Product->vmanufacture) && $Product->vmanufacture == '') || (empty($Product->brand_name) && $Product->brand_name == '')){
						$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
					}

					$ProductPrice = $Product->product_price;
					$RetailPrice = $Product->retail_price;
					if($ProductPrice > $RetailPrice)
					{
						$RetailPrice = $ProductPrice;
					}

					if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
						$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
						$yousave = $yousave * 100;
						$yousave = number_format($yousave, 0);
						$yousaveprice = $Product->retail_price - $Product->product_price;
					}else{
						$yousave = 0;
						$yousaveprice = 0;
					}

					$Product->yousave = $yousave;
					$Product->maxyousave = 0;
					if($yousave > 0)
					{
					$Product->maxyousave = number_format($Product->yousave, 0);
					}
					$Product->yousaveprice = $yousaveprice;
					$Product->autoid = $key;

					$Product->sale_item = '0';
					if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
					{
						$Product->sale_item = '1';
					}
					if(isset($Product->WebsiteStock) && $Product->WebsiteStock=="In")
					{
						$DealData = config('DealDetails');
						if(isset($DealData[$Product->sku]))
						{
							$Product->deal_price = $DealData[$Product->sku]['deal_price'];
							$Product->yousave = $DealData[$Product->sku]['yousave'];
							$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
						}
					}
					$Product->short_description = remove_html_entities(strip_tags($Product->short_description));
					$Product->size_cnt	= $Product->total_variants;

					$SldProducts[] = $Product;
				}
			}

		return $SldProducts;
	}

	public function PrepareProduct($Product,$key=0)
	{
		$product_link = config('global.SITE_URL');
		$product_name = remove_special_chars($Product->product_name);
		$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);

		if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
			$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($Product->image);
			$verP = filemtime($newimageVal);
			$Product->prod_image  = config('global.PRD_LARGE_IMG_URL') . $Product->image . "?ver=" . $verP;
		} else {
			$Product->prod_image = config('global.NO_IMAGE_LARGE');
		}

		if ($Product->gender == 'M'){
			$Product->gender = "sv-men";
			$Product->gendernames = "Men";
			$for_gender = ' for Men';
		} elseif ($Product->gender == 'W'){
			$Product->gender = "sv-women";
			$Product->gendernames = "Women";
			$for_gender = ' for Women';
		} elseif ($Product->gender == 'K'){
			$Product->gender = "sv-kids";
			$Product->gendernames = "Kids";
			$for_gender = ' for Kids';
		} elseif ($Product->gender == 'U'){
			$Product->gender = "sv-unisex";
			$Product->gendernames = "Unisex";
			$for_gender = ' Unisex';
		} else{
			$Product->gender = "";
			$Product->gendernames = "";
			$for_gender = '';
		}

		if($Product->vmanufacture != ''){
			$m_name = strtolower($Product->vmanufacture);
			$m_name = str_replace("#", "", $m_name);
			$m_name = str_replace("&", "", $m_name);
			$m_name = str_replace("  ", " ", trim($m_name));
			$m_name = str_replace("  ", " ", trim($m_name));
			$m_name = str_replace(" ", "-", $m_name);
			$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
		}

		if($Product->brand_name != '' && $Product->vmanufacture != ''){
			$m_name = strtolower($Product->vmanufacture);
			$m_name = str_replace("#", "", $m_name);
			$m_name = str_replace("&", "", $m_name);
			$m_name = str_replace("  ", " ", trim($m_name));
			$m_name = str_replace("  ", " ", trim($m_name));
			$m_name = str_replace(" ", "-", $m_name);
			$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
		}

		if(strlen($Product->product_name) > 45){
			$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
		} else {
			$Product->product_name = $Product->product_name;
		}
		$Product->product_name = strip_tags($Product->product_name);
		if($Product->vmanufacture == '' || $Product->brand_name == ''){
			$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
		}

		if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
			$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
			$yousave = $yousave * 100;
			$yousave = number_format($yousave, 0);
			$yousaveprice = $Product->retail_price - $Product->product_price;
		}else{
			$yousave = 0;
			$yousaveprice = 0;
		}

		$Product->yousave = $yousave;
        $Product->maxyousave = 0;
        if($yousave > 0)
        {
            $Product->maxyousave = number_format($Product->yousave, 0);
        }
		$Product->yousaveprice = $yousaveprice;
		$Product->autoid = $key;

		$Product->sale_item = '0';
		if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
		{
			$Product->sale_item = '1';
		}
		if(isset($Product->WebsiteStock) && $Product->WebsiteStock=="In")
		{
			$DealData = config('DealDetails');
			if(isset($DealData[$Product->sku]))
			{
				$Product->deal_price = $DealData[$Product->sku]['deal_price'];
				$Product->yousave = $DealData[$Product->sku]['yousave'];
				$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
			}
		}
		$Product->short_description = remove_html_entities(strip_tags($Product->short_description));
		/*
        $Product->avg_rate = 0;
		$total_review = $Product->TotalReview;
		if($total_review > 0)
			$Product->avg_rate = GetProductAverageRating($Product->TotalReview,$Product->TotalRate);
		*/
		return $Product;
	}

	public function GetSliderProducts($ProductString='',$Flag='',$FileFlag='',$CategoryID='',$ArrayFilters=[])
	{
		$SldProducts = [];
		$VariationIDs = [];
		$CatArrVal = [];

		if($ProductString != "")
		{
			if (strstr($ProductString, ','))
			{
				$ProductString = str_replace("  ", "", $ProductString);
				$ProductString = str_replace(" ", "", $ProductString);
				$ProductString = str_replace(",", "#", $ProductString);
			}
			$ProductString = trim($ProductString);

			$ProductString = rtrim($ProductString,"#");
			$ProductString = ltrim($ProductString,"#");
			$ProductString = explode("#", trim($ProductString));

			if($CategoryID=='68' || $CategoryID=='70' || $CategoryID=='71' || $CategoryID=='69')
				$CatArrVal = [$CategoryID];

			$ProdQry = DB::table('pu_products as po')
						->select('po.products_id',DB::raw('COUNT(po.variation_id) as variarioncnt'),'po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
									'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
									'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
									'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
									'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
									'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
									'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.category_name','c.parent_id','p1.extra_images')
						/*
                        ->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','po.sku')])
						*/
                        ->join('pu_products_category as pc','po.products_id','=','pc.products_id')
						->join('pu_category as c','pc.category_id','=','c.category_id')
						->join('pu_brand as b','b.brand_id','=','po.brand_id')
						->leftJoin('pu_products_one as p1','po.products_id','=','p1.products_id')
						->join('pu_manufacture as m',function($join){
							$join->on('po.imanufactureid','=','m.imanufactureid');
							$join->on('b.imanufactureid','=','m.imanufactureid');
						})
						->whereIn('po.sku',$ProductString)
						->where('po.status','=','1')
						->where('c.status','=','1');
						/*
						->whereIn('po.variation_id',function($query) use ($ProductString){
							$query->select('variation_id')->from('pu_products_one')
								->whereIn('sku',$ProductString);
						})*/

			if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
				$ProdQry->whereIn('po.product_type',['both','retailer','wholesaler']);
			else
				$ProdQry->whereIn('po.product_type',['both','retailer']);

			if($Flag == 'NEW ARRIVALS'){
				if($FileFlag == 'Home')
				{
					$ProductStringValue = trim(implode("#",array_filter($ProductString)));
					$ProductStringValue = str_replace("#","','","'".trim($ProductStringValue)."'");
					$ProdQry->whereNotIn('pc.category_id',['198','199','200','201']);
                    $ProdQry->orderByRaw('FIELD(po.sku, '.$ProductStringValue.')');
				}
				else
				{
					$ProdQry->whereNotIn('pc.category_id',['68','69','70','71','198','199','200','201']);
				}
			}
			if($Flag == 'BEST SELLERS')
			{
				$ProdQry->where(function($query){
					$query->orWhere('po.current_stock','>',0);
					$query->OrWhere(function($qry){
						$qry->where('po.cosmo_current_stock','>',0)->where('po.cosmo_sku','!=','');
					});
					$query->OrWhere(function($qry){
						$qry->where('po.pca_current_stock','>',0)->where('po.pca_sku','!=','');
					});
					$query->OrWhere(function($qry){
						$qry->where('po.nandansons_current_stock','>',0)->where('po.nandansons_sku','!=','');
					});
					$query->OrWhere(function($qry){
						$qry->where('po.perfumeworldwide_currentstock','>',0)->where('po.perfumeworldwide_sku','!=','');
					});
					$query->OrWhere(function($qry){
						$qry->where('po.nd_current_stock','>',0)->where('po.nd_sku','!=','');
					});
				});
			}
			$ProdQry->groupBy('po.products_id','po.variation_id');
			$Prods = $ProdQry->get();

			$SkipVariationID = [];
			$TotalProds = 0;
			$ProdIds=[];
			if($Prods->count() > 0)
			{
				//$SliderCategory = $this->GetCategories($Prods);

				foreach($Prods as $key => $Product)
				{
					if(!in_array($Product->sku,$ProductString))
						continue;
					$Product = $this->SetProduct($Product);
					/*
					if(isset($ArrayFilters['stock']) && $ArrayFilters['stock'] != '' && $Product->stock == '0')
						continue;

					if(isset($ArrayFilters['minprice']) && $ArrayFilters['minprice'] !='' && isset($ArrayFilters['maxprice']) && $ArrayFilters['maxprice'] != '')
					{
						if($Product->product_price < $ArrayFilters['minprice'] || $Product->product_price > $ArrayFilters['maxprice'] )
							continue;
					}*/

					if($Product->product_price <=0 && in_array($Product->sku,$ProductString))
					{
						$SkipVariationID[]=$Product->variation_id;
						continue;
					}

					if(in_array($Product->variation_id,$SkipVariationID))
						continue;

					$Product->size_cnt = 0;
					if($Product->is_atomizer == "Yes" || $Product->stock == "Out")
					{
						$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($Product->products_id,$Product->variation_id,$CategoryID,$CatArrVal,$Prods);
						if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != '')
							$Product = $SizeCountArr[0];
						else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In')
							$Product = $SizeCountArr[0];
						/*else
							$Product->size_cnt = $SizeCountArr;*/
					}/* else {

						$Product->size_cnt = $this->getReferencedProducts_CounterDev($Product->products_id,$Product->variation_id,$Prods);
					}*/

					if($CategoryID == '2')
						$Product->category_id = $CategoryID;

					//Make Product Link Start
					$product_link = config('global.SITE_URL');

					$product_name = remove_special_chars($Product->product_name);
					//$product_link.= $CatInfo[$Product->category_id].$product_name."/pid/".$Product->products_id."/".$Product->category_id;
					$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);
					//Make Product Link End

                    if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
						$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($Product->image);
						$verP = filemtime($newimageVal);
						$Product->prod_image = config('global.PRD_LARGE_IMG_URL') . $Product->image . "?ver=" . $verP;

					} else {

						$Product->prod_image = config('global.NO_IMAGE_LARGE');

					}

                    /*
					if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
						$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($Product->image);
						$verP = filemtime($newimageVal);
						$Product->prod_image = config('global.PRD_THUMB_IMG_URL') . $Product->image . "?ver=" . $verP;
					} else {
						$Product->prod_image = config('global.NO_IMAGE_THUMB');
                    }*/

					if ($Product->gender == 'M'){
						$Product->gender = "sv-men";
						$Product->gendernames = "Men";
						$for_gender = ' for Men';
					} elseif ($Product->gender == 'W'){
						$Product->gender = "sv-women";
						$Product->gendernames = "Women";
						$for_gender = ' for Women';
					} elseif ($Product->gender == 'K'){
						$Product->gender = "sv-kids";
						$Product->gendernames = "Kids";
						$for_gender = ' for Kids';
					} elseif ($Product->gender == 'U'){
						$Product->gender = "sv-unisex";
						$Product->gendernames = "Unisex";
						$for_gender = ' Unisex';
					} else{
						$Product->gender = "";
						$Product->gendernames = "";
						$for_gender = '';
					}

					if($Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
					}

					if($Product->brand_name != '' && $Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
					}

					if(strlen($Product->product_name) > 45){
						$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
					} else {
						$Product->product_name = $Product->product_name;
					}

					if((empty($Product->vmanufacture) && $Product->vmanufacture == '') || (empty($Product->brand_name) && $Product->brand_name == '')){
						$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
					}

					if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
						$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
						$yousave = $yousave * 100;
						$yousave = number_format($yousave, 0);
						$yousaveprice = $Product->retail_price - $Product->product_price;
					}else{
						$yousave = 0;
						$yousaveprice = 0;
					}

					$Product->yousave = $yousave;
					$Product->maxyousave = 0;
					if($yousave > 0)
					{
					$Product->maxyousave = number_format($Product->yousave, 0);
					}
					$Product->yousaveprice = $yousaveprice;
					$Product->autoid = $key;

					$Product->sale_item = '0';
					if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
					{
						$Product->sale_item = '1';
					}
					if(isset($Product->WebsiteStock) && $Product->WebsiteStock=="In")
					{
						$DealData = config('DealDetails');
						if(isset($DealData[$Product->sku]))
						{
							$Product->deal_price = $DealData[$Product->sku]['deal_price'];
							$Product->yousave = $DealData[$Product->sku]['yousave'];
							$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
						}
					}
					$Product->short_description = remove_html_entities(strip_tags($Product->short_description));
					$Product->size_cnt = $Product->variarioncnt;
					/*
                    $Product->avg_rate = 0;
					$total_review = $Product->TotalReview;
					if($total_review > 0)
						$Product->avg_rate = GetProductAverageRating($Product->TotalReview,$Product->TotalRate);
					*/
					$VariationIDs[] = $Product->variation_id;
					$SldProducts[] = $Product;
				}
			}
		}
		$SldProducts = $this->CountOptions($VariationIDs,$SldProducts,$CatArrVal);
		return $SldProducts;
	}

	public function CountOptions_Bk_Main_05_09($VariationIDs=[],$DisplayProducts=[],$CatArrVal=[],$Flag='',$ismaxTwoDay = '', $isPocketPerfume = '')
	{
		$VariationIDs = array_unique($VariationIDs);
		$VariationProductQry = DB::table('pu_products as po')
							->join('pu_products_category as pc','po.products_id','=','pc.products_id')
							->join('pu_category as c','pc.category_id','=','c.category_id')
							->join('pu_brand as b','b.brand_id','=','po.brand_id')
							->join('pu_manufacture as m',function($join){
								$join->on('po.imanufactureid','=','m.imanufactureid');
								$join->on('b.imanufactureid','=','m.imanufactureid');
							})
							->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
								'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.parent_id');
							/*
                            ->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')]);
							*/
                            //->whereIn('po.variation_id',$VariationIDs)

		if($isPocketPerfume == 'Y'){
			$pkt_perfume_arr = array(68,69,70,71);
			$VariationProductQry->WhereIn('pc.category_id',$pkt_perfume_arr);
			$VariationProductQry->Where('po.is_atomizer','Yes');
		}

		if($Flag == "TOP_SELLERS" || $Flag == "NEW_ARRIVALS")
		{
			if(count($CatArrVal) > 0)
				$VariationProductQry->whereIn('pc.category_id',$CatArrVal);
		}else{
			$VariationProductQry->whereIn('po.variation_id',$VariationIDs);
		}
			$VariationProductQry->where('po.status','=','1')->where('c.status','=','1')->where('b.status','=','1')->where('m.status','=','1');
		if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $VariationProductQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $VariationProductQry->whereIn('po.product_type',['both','retailer']);

		if($ismaxTwoDay == 'Y'){
			$VariationProductQry->where('po.maxtwodaydelivery','Yes');
			$VariationProductQry->whereNotIn('po.sku', function ($query) {
					$query->select('product_sku')->from('pu_dealofweek')->where('status','=','1')->where('start_date','<=',date('Y-m-d H:i'))->where('end_date','>=',date('Y-m-d H:i'));
			});
		}

		if($isPocketPerfume == 'Y'){
			//$pkt_perfume_arr = array(68,69,70,71);
			//$VariationProductQry->WhereIn('pc.category_id',$pkt_perfume_arr);
			//$VariationProductQry->Where('po.is_atomizer','Yes');
		}

		$VariationProductQry->orderBy('po.current_stock');
		$VariationProductQry->orderBy('po.nandansons_current_stock');
		$VariationProductQry->orderBy('po.cosmo_current_stock');
		$VariationProductQry->orderBy('po.pca_current_stock');
		$VariationProductQry->orderBy('po.perfumeworldwide_currentstock');
		$VariationProductQry->orderBy('po.nd_current_stock');
		$VariationProducts = $VariationProductQry->groupBy('po.products_id')->get()->toArray();
		//$VariationProducts = $VariationProductQry->groupBy('po.products_id')->limit(25)->get()->toArray();
		//$ProdQry->limit($getPrdLimit);

		$TotalVariations=[];
		$Variation='';
		$vcount = 0;

		$TotalVariations = array_count_values(array_column($VariationProducts, 'variation_id'));
		$ProdCnt = [];
		$Price = [];
		foreach($VariationProducts as $Product)
		{
			$Product = $this->SetProduct($Product);
			$Price[$Product->variation_id][] = (float)$Product->product_price;
		}
		$NewProduct = [];

		foreach($DisplayProducts as $ProductNew)
		{
			if(isset($TotalVariations[$ProductNew->variation_id]))
				$ProductNew->size_cnt = $TotalVariations[$ProductNew->variation_id];
			else
				$ProductNew->size_cnt = 0;

			if(isset($Price[$ProductNew->variation_id]) && count($Price[$ProductNew->variation_id]) > 0)
			{
				$ProductNew->minPrice = min($Price[$ProductNew->variation_id]);
				$ProductNew->maxPrice = max($Price[$ProductNew->variation_id]);
			} else {
				$ProductNew->minPrice = 0;
				$ProductNew->maxPrice = 0;
			}
			$NewProduct[] = $ProductNew;
		}

		for($i=0;$i<count($NewProduct);$i++)
		{
			if(($NewProduct[$i]->is_atomizer == "Yes" && $isPocketPerfume=='N') || $NewProduct[$i]->stock == "Out")
			//if(($NewProduct[$i]->is_atomizer == "Yes"  && ($isPocketPerfume == ''  || $isPocketPerfume == 'N')) || $NewProduct[$i]->stock == "Out")
			{
				foreach($VariationProducts as $Product)
				{
					if ($Product->products_id == $NewProduct[$i]->products_id)
					continue;
					if ($Product->variation_id != $NewProduct[$i]->variation_id)
						continue;

					$Product = $this->SetProduct($Product);

					if ($Product->is_atomizer == "Yes" && $Product->stock == "Out")
						continue;
					if ($Product->stock == "Out" && $Product->is_atomizer == "No")
						continue;

					if ($Product->is_atomizer == "No")
					{
						if ($Product->stock == "In" && $Product->category_id == $NewProduct[$i]->category_id)
						{
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;

							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}

							if (count($CatArrVal) > 0) {
								$isAtom1 = 'No';
								for ($j = 0; $j < count($CatArrVal); $j++) {
									if ($CatArrVal[$j] != 68 && $CatArrVal[$j] != 70 &&  $CatArrVal[$j] != 71 &&  $CatArrVal[$j] != 69) {
										$isAtom1 = 'Yes';
									}
								}
								if ($isAtom1 == 'Yes') {
									break;
								}
							} else {
								break;
							}
						} else if ($Product->stock == "In") {
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;

							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}

						} else {
							if ($NewProduct[$i]->is_atomizer != 'Yes' && $NewProduct[$i]->stock != 'In') {
								$NewProduct[$i] = $this->PrepareProduct($Product,$i);
								if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
									$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
								else
									$NewProduct[$i]->size_cnt = 0;

								if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
								{
									$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
									$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
								} else {
									$NewProduct[$i]->minPrice = 0;
									$NewProduct[$i]->maxPrice = 0;
								}
							}
						}
					} else {
						if ($Product->stock == "In" && ($NewProduct[$i]->stock != 'In' || in_array(68, $CatArrVal) || in_array(70, $CatArrVal) || in_array(71, $CatArrVal) || in_array(69, $CatArrVal))) {
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;

							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}
							$isAtom = 'No';
							for ($j = 0; $j < count($CatArrVal); $j++) {
								if ($CatArrVal[$j] == 68 || $CatArrVal[$j] == 70 ||  $CatArrVal[$j] == 71 ||  $CatArrVal[$j] == 69) {
									$isAtom = 'Yes';
								}
							}
							if ($isAtom == 'Yes')
								break;
						}
					}
				}
			}
		}
		//dd($NewProduct);
		return $NewProduct;
	}

	public function fetchhomeprodDev($Flag,$FileFlag='',$getPrdLimit='',$CategoryID='')
	{
		$SldProducts = [];
		$VariationIDs = [];
		$ProdIds=[];
		$ProdQry = DB::table('pu_products as po')
					->select('po.products_id','po.sku',DB::raw('COUNT(po.variation_id) as variarioncnt'),'po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
								'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.parent_id')
					/*
                    ->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')])
                    */
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->where('po.status','=','1');

		if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
			$ProdQry->whereIn('po.product_type',['both','retailer','wholesaler']);
		else
			$ProdQry->whereIn('po.product_type',['both','retailer']);

		if($Flag == 'NEW ARRIVALS'){
			if($FileFlag != 'Home')
				$ProdQry->whereNotIn('pc.category_id',['198','199','200','201']);
			else
				$ProdQry->whereNotIn('pc.category_id',['68','69','70','71','198','199','200','201']);

			$from = Carbon::now()->subDays(90);
			$to = Carbon::now();
			$ProdQry->whereBetween(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),[$from,$to])
					->where('po.cosmo_sku','=','')->where('po.pca_sku','=','')->where('po.nandansons_sku','=','')->where('po.perfumeworldwide_sku','=','')->where('po.nd_sku','=','')
					->where('po.current_stock','>',0)
					->orderBy('po.add_datetime','desc');
		}
		if($Flag == 'BEST SELLERS')
		{
			$ProdQry->where('pc.category_id','=','2');
			$ProdQry->where('po.is_sold_quantity','>','0');
			$ProdQry->where(function($query){
				$query->orWhere('po.current_stock','>',0);
				$query->OrWhere(function($qry){
					$qry->where('po.cosmo_current_stock','>',0)->where('po.cosmo_sku','!=','');
				});
				$query->OrWhere(function($qry){
					$qry->where('po.pca_current_stock','>',0)->where('po.pca_sku','!=','');
				});
				$query->OrWhere(function($qry){
					$qry->where('po.nandansons_current_stock','>',0)->where('po.nandansons_sku','!=','');
				});
				$query->OrWhere(function($qry){
					$qry->where('po.perfumeworldwide_currentstock','>',0)->where('po.perfumeworldwide_sku','!=','');
				});
				$query->OrWhere(function($qry){
					$qry->where('po.nd_current_stock','>',0)->where('po.nd_sku','!=','');
				});
			});
		}
		$ProdQry->groupBy('po.variation_id');
		$ProdQry->limit($getPrdLimit);
		$Prods = $ProdQry->get();

		$SkipVariationID = [];
		if($Prods->count() > 0)
		{
			//$SliderCategory = $this->GetCategories($Prods);
			foreach($Prods as $key => $Product)
			{
				$Product = $this->SetProduct($Product);

				if($Product->product_price <=0 && in_array($Product->sku,$ProductString))
				{
					$SkipVariationID[]=$Product->variation_id;
					continue;
				}

				if(in_array($Product->variation_id,$SkipVariationID))
					continue;

				$VariationIDs[] = $Product->variation_id;

                if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
					$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($Product->image);
					$verP = filemtime($newimageVal);
					$Product->prod_image = config('global.PRD_LARGE_IMG_URL') . $Product->image . "?ver=" . $verP;
				} else {
					$Product->prod_image = config('global.NO_IMAGE_LARGE');
				}
                /*
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
					$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($Product->image);
					$verP = filemtime($newimageVal);
					$Product->prod_image = config('global.PRD_THUMB_IMG_URL') . $Product->image . "?ver=" . $verP;
				} else {
					$Product->prod_image = config('global.NO_IMAGE_THUMB');
				}*/

				/*
				if($Product->is_atomizer == "Yes" || $Product->stock == "Out")
				{
					$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($Product->products_id,$Product->variation_id,$CategoryID,[],$Prods);
					if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != '')
						$Product = $SizeCountArr[0];
					else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In')
						$Product = $SizeCountArr[0];

				} else {
					//$Product->size_cnt = $this->getReferencedProducts_CounterDev($Product->products_id,$Product->variation_id,$Prods);
				}
				*/
				$PriceRange = $this->setPriceRange($Product->variation_id,$Prods);
				$Product->minPrice = $PriceRange['MinPrice'];
				$Product->maxPrice = $PriceRange['MaxPrice'];
				$Product->yousave = $PriceRange['YouSave'];

				//Make Product Link Start
				/*$product_link = config('global.SITE_URL');
				if($Product->parent_id != 0){
					$ProdCat = $Product->parent_id;
					$ProdCatDetails = $SliderCategory[$ProdCat];
					$category_url = remove_special_chars($ProdCatDetails->category_name);
					$product_link.=$category_url;
				}
				$ProdCat = $Product->category_id;
				$ProdCatDetails = $SliderCategory[$ProdCat];
				$category_url = remove_special_chars($ProdCatDetails->category_name);
				if($Product->parent_id != 0)
					$product_link.='/'.$category_url;
				else
					$product_link.=$category_url;

				$product_name = remove_special_chars($Product->product_name);
				$product_link.='/'.$product_name."/pid/".$Product->products_id."/".$Product->category_id;
				*/
				$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);
				//Make Product Link End

				if ($Product->gender == 'M'){
					$Product->gender = "sv-men";
					$Product->gendernames = "Men";
					$for_gender = ' for Men';
				} elseif ($Product->gender == 'W'){
					$Product->gender = "sv-women";
					$Product->gendernames = "Women";
					$for_gender = ' for Women';
				} elseif ($Product->gender == 'K'){
					$Product->gender = "sv-kids";
					$Product->gendernames = "Kids";
					$for_gender = ' for Kids';
				} elseif ($Product->gender == 'U'){
					$Product->gender = "sv-unisex";
					$Product->gendernames = "Unisex";
					$for_gender = ' Unisex';
				} else{
					$Product->gender = "";
					$Product->gendernames = "";
					$for_gender = '';
				}

				if($Product->vmanufacture != ''){
					$m_name = strtolower($Product->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
				}

				if($Product->brand_name != '' && $Product->vmanufacture != ''){
					$m_name = strtolower($Product->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
				}

				if(strlen($Product->product_name) > 45){
					$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
				} else {
					$Product->product_name = $Product->product_name;
				}

				if($Product->vmanufacture == '' || $Product->brand_name == ''){
					$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
				}

				if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
					$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$yousaveprice = $Product->retail_price - $Product->product_price;
				}else{
					$yousave = 0;
					$yousaveprice = 0;
				}

				$Product->yousave = $yousave;
				$Product->maxyousave = $Product->yousave;
				$Product->yousaveprice = $yousaveprice;
				$Product->autoid = $key;

				$Product->sale_item = '0';
				if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
				{
					$Product->sale_item = '1';
				}
				if(isset($Product->WebsiteStock) && $Product->WebsiteStock=="In")
				{
					$DealData = config('DealDetails');
					if(isset($DealData[$Product->sku]))
					{
						$Product->deal_price = $DealData[$Product->sku]['deal_price'];
						$Product->yousave = $DealData[$Product->sku]['yousave'];
						$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
					}
				}
				$Product->short_description = remove_html_entities(strip_tags($Product->short_description));
				$Product->size_cnt = $Product->variarioncnt;
				/*
                $Product->avg_rate = 0;
				$total_review = $Product->TotalReview;
				if($total_review > 0)
					$Product->avg_rate = GetProductAverageRating($Product->TotalReview,$Product->TotalRate);
				*/
				$SldProducts[] = $Product;
			}
		}
		$SldProducts = $this->CountOptions($VariationIDs,$SldProducts,[$CategoryID]);
		return $SldProducts;
	}
	public function GetCategories($Products)
	{
		$Categoies = [];
		$SliderCats = [];
		foreach($Products as $Product)
		{
			if($Product->parent_id != 0)
				$Categoies[] = $Product->parent_id;
			$Categoies[] = $Product->category_id;
		}
		if(count($Categoies) > 0)
		{
			$Categoies = array_unique($Categoies);
			$ProdCats = Category::whereIn('category_id',$Categoies)->where('status','=','1')->orderBy('category_name')->get();
			foreach($ProdCats as $Cat)
				$SliderCats[$Cat->category_id] = $Cat;
		}
		return $SliderCats;
	}
	public function getProductRewriteURL($products_id, $product_name = '', $category_id = '', $vmanufacture = '')
	{
		$product_name = remove_special_chars($product_name);
		if ($vmanufacture != '')
            $vmanufacture = remove_special_chars($vmanufacture) . "/";
		if ($category_id == '')
        {
			$CatDetails = CacheService::ProductCategories()->firstWhere('products_id',(int)$products_id);
			$category_id = $CatDetails->category_id;
			/*
			$CatDetails = DB::table('pu_products_category as pcr')
							->join('pu_category as c','pcr.category_id','=','c.category_id')
							->where('pcr.products_id','=',$products_id)
							->where('c.status','=','1')
							->orderBy('c.display_position')->orderBy('c.category_name')
							->limit(1)->get();
            $category_id = $CatDetails[0]->category_id;
			*/
        }
		$category_url = $this->getParentCategoryRewriteURL($category_id) . "/";
        return config('global.SITE_URL').$category_url.$product_name."/pid/".$products_id."/".$category_id;
	}

	public function getParentCategoryRewriteURL($category_id) {

        $new_vcat_name = '';
		//$CatDetails = Category::where('category_id','=',$category_id)->where('status','=','1')->orderBy('category_name')->get();
		$CatDetails = CacheService::Categories()->firstWhere('category_id',(int)$category_id);
        if($CatDetails)
        {
            $new_iparent_id = $CatDetails->parent_id;
            $new_icat_id = $CatDetails->category_id;
            $new_vcat_name = remove_special_chars(trim($CatDetails->category_name));
            while($new_iparent_id != 0)
            {
				//$ParentCatDetails = Category::where('category_id','=',$new_iparent_id)->where('status','=','1')->orderBy('category_name')->get();
				$ParentCatDetails = CacheService::Categories()->firstWhere('category_id',(int)$new_iparent_id);
                $new_iparent_id = $ParentCatDetails->parent_id;
                $new_icat_id = $ParentCatDetails->category_id;
                $new_vcat_name = remove_special_chars(trim($ParentCatDetails->category_name)) . "/" . $new_vcat_name;
            }
        }
		return $new_vcat_name;
    }

	public function SetFiltersNew($Params)
	{
        //dd($Params->filters);

        $AllFilters = [];
        if(isset($Params->filters) && $Params->filters != 'view')
        {

        } else {
            if(isset($Params->category_id) && $Params->category_id !='')
            {
                $AllFilters['cid'] = $Params->category_id;
            }
        }

        //$ExpFilters = explode("/",$Params->filters);
		$ExpFilters = is_string($Params->filters) ? explode("/", $Params->filters) : [];
		if(isset($Params->mid) && $Params->mid != '')
			$ExpFilters[]='mid-'.$Params->mid;

		$ParamString = ['cid' => 'categories', 'mid' => 'brands','family' => 'fragrance_family', 'type' => 'vtype',
				'formulation' => 'formulation', 'stock' => 'stock', 'size' => 'size',
				'special' => 'special', 'coverage' => 'coverage', 'finish' => 'finish',
				'skin' => 'skin_type', 'features' => 'features', 'gen' => 'gender', 'max2day' => 'max2day', 'ftype' => 'ftype', 'sort' => 'sort', 'InStockItems' => 'InStockItems', 'OnTopRated' => 'OnTopRated', 'OnSaleDeal' => 'OnSaleDeal','fragrance_occasion' => 'fragrance_occasion','fragrance_season' => 'fragrance_season','review_rating' => 'review_rating'];
		foreach($ExpFilters as $AllParam)
		{
			$ExpParam = explode("-",$AllParam);
			if(count($ExpParam)>0 && array_key_exists($ExpParam[0],$ParamString))
			{
				if(isset($ExpParam[0]) && $ExpParam[0] == 'size'){
					$size_param = $ExpParam[1];
					if(isset($ExpParam[2])){
						$size_param .= "-".$ExpParam[2];
					}
					$AllFilters['size'][] = $size_param; //$ExpParam[1]."-".$ExpParam[2];
				} else if(isset($ExpParam[0]) && $ExpParam[0] == 'fragrance_season'){
					$fragrance_season_param = $ExpParam[1];
					if(isset($ExpParam[2])){
						$fragrance_season_param .= "-".$ExpParam[2];
					}
					//$AllFilters['fragrance_season'][] = explode(",",$fragrance_season_param);
					$AllFilters['fragrance_season'] = explode(",",$fragrance_season_param);
				} else {
					$Key = $ParamString[$ExpParam[0]];
					if(isset($ExpParam[1])){
						$AllFilters[$Key] = explode(',',$ExpParam[1]);
					} else {
						$AllFilters[$Key] = array();
					}
				}
				//$Key = $ParamString[$ExpParam[0]];
				//$AllFilters[$Key] = array();
				/*if(isset($ExpParam[1])){
					$AllFilters[$Key] = explode(',',$ExpParam[1]);
				}*/
			} else if(count($ExpParam)>0 && $ExpParam[0] == 'key'){
				$AllFilters['key'] = $ExpParam[1];
			} else if(count($ExpParam)>0 && $ExpParam[0] == 'price'){
				$AllFilters['minprice'] = $ExpParam[1];
				if(isset($ExpParam[2])){
					$AllFilters['maxprice'] = $ExpParam[2];
				} else {
					$AllFilters['maxprice'] = "";
				}
				$AllFilters['price'][] = $AllFilters['minprice']."-".$AllFilters['maxprice'];
			}
		}
		if(isset($ExpFilters[0]) && $ExpFilters[0]=='8-ml-mini'){
			$AllFilters['vsize'][] = '8-ml-mini';
		}
		if(isset($ExpFilters[0]) && trim($ExpFilters[0]) =='discovery-sets'){
			$AllFilters['brand_discovery_set'][] = 'discovery-sets';
		}
		if(isset($ExpFilters[0]) && trim($ExpFilters[0]) =='f-pocket-perfume'){
			$AllFilters['fpocket_perfume'][] = 'f-pocket-perfume';
		}

		return $AllFilters;
	}

	public function GetProductsNewFilters($Flag,$CategoryID,$limit=12,$Filters=[])
	{
		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		$ismaxTwoDay = 'N';
		$isPocketPerfume = 'N';
		$isGiftSet = 'N';
		if(isset($Filters['sort']) && isset($Filters['sort'][0])){
			$Filters['sortby'] = $Filters['sort'][0];
		}
        /*
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			$ChildCats = GetMainCatsTree([$CategoryID]);
			if(count($ChildCats['CatList']) > 0)
				$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
			else
				$ChildCatArr = [$CategoryID];
		}
		*/
		if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
		}

		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';

		//$CatProdsQry = DB::table('pu_products as po')
		//$query = DB::raw("IF(INSTR(LOWER(po.size),'ml') > 0,ROUND((REPLACE(LOWER(po.size),'ml','') * 0.34),2),REPLACE(REPLACE(LOWER(po.size),'oz.',''),'oz','')) as psize");
		if(isset($Filters['size']) && count($Filters['size']) > 0){
			$query = DB::raw("IF(INSTR(LOWER(po.size),'ml') > 0,ROUND((REPLACE(LOWER(po.size),'ml','') * 0.34),2),REPLACE(REPLACE(LOWER(po.size),'oz.',''),'oz','')) as psize");
		} else {
			$query = DB::raw("po.size as psize");
		}

        $CatProdsQry = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->leftJoin('pu_products_one as po1', 'po.products_id', '=', 'po1.products_id')
					->select($query,'po.size',DB::raw('COUNT(po.variation_id) as variarioncnt')
,'po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
								'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
								'po.vtype','po.variation_id','po.refine_feature','po1.extra_images as extra_images','m.vmanufacture','po.product_type','b.brand_name','m.is_popular','pc.category_id','c.parent_id','po.add_datetime','po.upd_datetime')
					/*->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','m.is_popular','pc.category_id','c.parent_id')*/
					->where('po.status','=','1')
					->where('c.status','=','1');

        if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('po.product_type',['both','retailer']);

		//if(isset($Filters['size']) && $Filters['size']!='' && is_array($Filters['size'])){
		// if(isset($Filters['size']) && $Filters['size']!='' && is_array($Filters['size']) && count($Filters['size']) > 0 ){
		// 	$Filters['size'] = explode(",",$Filters['size'][0]);
		// 	for($s = 0; $s < count($Filters['size']); $s++){
		// 		$s_size = $Filters['size'][$s];
		// 		if($s_size != ''){
		// 			$s_size_arr = explode("-",$s_size);
		// 			if(count($s_size_arr) > 1){
		// 				$CatProdsQry->havingRaw('psize >= '.$s_size_arr[0].' ');
		// 				$CatProdsQry->havingRaw('psize <= '.$s_size_arr[1].' ');
		// 			} else if(count($s_size_arr) == 1){
		// 				$CatProdsQry->havingRaw('psize >= '.$s_size_arr[0].' ');
		// 			}
		// 		}
		// 	}
		// }

		if($Flag == 'DealofweekPage' && isset($Filters['size'])){
			$CatProdsQry->where(function($query) use ($Filters){
				for($s = 0; $s < count($Filters['size']); $s++ ){
					if($Filters['size'][$s] == 'mini' || $Filters['size'][$s] == 'set'){
						if($s == 0){
							$query->where('po.size','like','%'.$Filters['size'][$s].'%');
						} else {
							$query->orWhere('po.size','like','%'.$Filters['size'][$s].'%');
						}
					}
				}
			});
		}

		if(isset($Filters['size']) && $Filters['size']!='' && is_array($Filters['size']) && count($Filters['size']) > 0 ){
			if($Flag != 'DealofweekPage'){
				$Filters['size'] = explode(",",$Filters['size'][0]);
			}
			$minSizeArr = array();
			$maxSizeArr = array();
			$chkSize = 0;
			for($s = 0; $s < count($Filters['size']); $s++){
				$s_size = $Filters['size'][$s];
				if($s_size != '' && $s_size != 'mini' && $s_size != 'set'){
					$s_size_arr = explode("-",$s_size);
					if(count($s_size_arr) > 1){
						if($Flag == 'DealofweekPage'){
							if(isset($s_size_arr[0])){
								array_push($minSizeArr,$s_size_arr[0]);
							}
							if(isset($s_size_arr[1])){
								array_push($maxSizeArr,$s_size_arr[1]);
							}
						} else {
							$size_z = str_replace("oz","",strtolower($s_size_arr[0]));
							$size_o = str_replace("oz","",strtolower($s_size_arr[1]));

							$CatProdsQry->havingRaw('psize >= '.$size_z.' ');
							$CatProdsQry->havingRaw('psize <= '.$size_o.' ');

							/*$CatProdsQry->havingRaw('psize >= '.$s_size_arr[0].' ');
							$CatProdsQry->havingRaw('psize <= '.$s_size_arr[1].' ');*/
						}
					} else if(count($s_size_arr) == 1){
						$chkSize = 1;
						$size_zz = str_replace("oz","",strtolower($s_size_arr[0]));
						$CatProdsQry->havingRaw('psize >= '.$size_zz.' ');
						//$CatProdsQry->havingRaw('psize >= '.$s_size_arr[0].' ');
					}
				}
			}
			if($Flag == 'DealofweekPage' && (count($minSizeArr) > 0 || count($maxSizeArr) > 0))	{
				$min_size = "";
				$max_size = "";
				if(count($minSizeArr) > 0){
					$min_size = min(array_unique($minSizeArr));
				}
				if(count($maxSizeArr) > 0){
					$max_size = max(array_unique($maxSizeArr));
					//if($_SERVER['HTTP_CF_CONNECTING_IP'] == '2406:b400:d5:9866:9c36:1781:4b8e:bb5b'){
						if(stristr($max_size,",")){
							$mxSizeArr = explode(",",$max_size);
							if(count($mxSizeArr) > 0){
								$max_size = max(array_unique($mxSizeArr));
							}
						}
					//}
				}

				if($chkSize == 1){
					$CatProdsQry->where(function($query) use ($min_size, $max_size){
						if($min_size != ''){
							$query->havingRaw('psize >= '.$min_size.' ');
						}
						if($max_size != ''){
							$query->orhavingRaw('psize <= '.$max_size.' ');
						}
					});
				} else {
					if($min_size != ''){
						$CatProdsQry->havingRaw('psize >= '.$min_size.' ');
					}
					if($max_size != ''){
						$CatProdsQry->havingRaw('psize <= '.$max_size.' ');
					}
				}
			}
		}

		/*17022023
		if(isset($Filters['size']) && $Filters['size']!='' && is_array($Filters['size']) && count($Filters['size']) > 0 ){
			$Filters['size'] = explode(",",$Filters['size'][0]);
			for($s = 0; $s < count($Filters['size']); $s++){
				$s_size = $Filters['size'][$s];
				if($s_size != ''){
					$s_size_arr = explode("-",$s_size);
					if(count($s_size_arr) > 1){
						$CatProdsQry->havingRaw('psize >= '.$s_size_arr[0].' ');
						$CatProdsQry->havingRaw('psize <= '.$s_size_arr[1].' ');
					} else if(count($s_size_arr) == 1){
						$CatProdsQry->havingRaw('psize >= '.$s_size_arr[0].' ');
					}
				}
			}
		}*/
		$chkFragranceType = 'N';
		if(isset($Filters['fragrance_type']) && count($Filters['fragrance_type']) > 0){
			$chkFragranceType = 'Y';
			//$CatProdsQry->whereIn('po.vtype',$Filters['fragrance_type']);
			$CatProdsQry->where('po.fragrance_personality','!=','');
			$CatProdsQry->where(function($query) use ($Filters){
				for($f = 0; $f < count($Filters['fragrance_type']); $f++ ){
					if($f == 0){
						$query->where('po.fragrance_personality','like','%'.$Filters['fragrance_type'][$f].'%');
					} else {
						$query->orWhere('po.fragrance_personality','like','%'.$Filters['fragrance_type'][$f].'%');
					}
				}
			});
		}

		if(isset($Filters['ftype']) && count($Filters['ftype']) > 0){
			$chkFragranceType = 'Y';
			//$CatProdsQry->whereIn('po.vtype',$Filters['ftype']);
			$CatProdsQry->where('po.fragrance_personality','!=','');
			$CatProdsQry->where(function($query) use ($Filters){
				for($f = 0; $f < count($Filters['ftype']); $f++ ){
					if($f == 0){
						if(isset($Filters['ftype'][$f]) &&  $Filters['ftype'][$f] !=''){
							$query->where('po.fragrance_personality','like','%'.$Filters['ftype'][$f].'%');
						}
					} else {
						if(isset($Filters['ftype'][$f]) && $Filters['ftype'][$f]!=''){
							$query->orWhere('po.fragrance_personality','like','%'.$Filters['ftype'][$f].'%');
						}
					}
				}
			});
		}

		if(isset($Filters['categories']) && count($Filters['categories']) > 0){
			for($c = 0; $c < count($Filters['categories']); $c++){
				if(isset($Filters['categories'][$c]) && trim($Filters['categories'][$c])=="8-ml-mini"){
					$CatProdsQry->Where('po.size','like','%8 ml%');
				}

				if(isset($Filters['categories'][$c]) && trim($Filters['categories'][$c])=="f-pocket-perfume"){
					$isPocketPerfume = 'Y';
					$pkt_perfume_arr = array(68,69,70,71);
					//$CatProdsQry->WhereIn('pc.category_id',$pkt_perfume_arr);
					//$CatProdsQry->Where('po.is_atomizer','Yes');
					//09052024
					if($CategoryID == '68'){
						//$Filters['zz'] = json_encode($CategoryID);
						$isPocketPerfume = "Y";

						$CatProdsQry->Where('po.is_atomizer','Yes');
						$CatProdsQry->whereIn('pc.category_id',$pkt_perfume_arr);

						// $CatProdsQry->where(function($query) use ($pkt_perfume_arr){
						// 	$query->orWhere('po.is_atomizer','Yes');
						// 	$query->orwhereIn('pc.category_id',$pkt_perfume_arr);
						// });
					} else {
						$CatProdsQry->Where('po.is_atomizer','Yes');
					}
				}

				if(isset($Filters['categories'][$c]) && trim($Filters['categories'][$c])=="discovery-sets"){
					$CatProdsQry->Where('po.size_old','Yes');
					//$CatProdsQry->Where('b.brand_name','=','Discovery Set');
					//$CatProdsQry->Where('po.product_name','like','%Discovery Set%');
					// $CatProdsQry->where(function($query){
					// 	$query->where('po.product_name','like','%Discovery Set%');
					// 	$query->orWhere('po.product_name','like','%Discovery Sets%');
					// 	$query->orWhere('po.product_description','like','%Discovery Sets%');
					// 	$query->orWhere('po.product_description','like','%Discovery Set%');
					// });
				}

				if(isset($Filters['categories'][$c]) && trim($Filters['categories'][$c])=="7,8,11"){
					//$Filters['qq'] = json_encode($Filters);
					$isGiftSet = 'Y';
				}
			}
		}

		//$Filters['qq'] = json_encode($Filters);

		if(isset($Filters['vsize']) && count($Filters['vsize']) > 0){
			for($v = 0; $v < count($Filters['vsize']); $v++){
				if(isset($Filters['vsize'][$v]) && trim($Filters['vsize'][$v])=="8-ml-mini"){
					//echo "test test";
					$CatProdsQry->Where('po.size','like','%8 ml%');
				}
			}
		}

		if(isset($Filters['brand_discovery_set']) && count($Filters['brand_discovery_set']) > 0){
			for($v = 0; $v < count($Filters['brand_discovery_set']); $v++){
				if(isset($Filters['brand_discovery_set'][$v]) && trim($Filters['brand_discovery_set'][$v])=="discovery-sets"){
					$CatProdsQry->Where('po.size_old','Yes');
					//$CatProdsQry->Where('po.product_name','like','%Discovery Set%');
					//$CatProdsQry->Where('b.brand_name','=','Discovery Set');
					/*$CatProdsQry->where(function($query){
						$query->where('po.product_name','like','%Discovery Set%');
						$query->orWhere('po.product_name','like','%Discovery Sets%');
						$query->orWhere('po.product_description','like','%Discovery Sets%');
						$query->orWhere('po.product_description','like','%Discovery Set%');
					});*/
				}
			}
		}

		if($Flag == 'DealofweekPage' && isset($Filters['onTopRated']) && $Filters['onTopRated'] == 'Y'){
				$CatProdsQry->join('pu_products_rating as pr', 'pr.products_id', '=', 'po.products_id')
							->where('pr.rating', '>', 4);
		}

		if($Flag == 'DealofweekPage' && isset($Filters['stock']) && $Filters['stock'] == 'Out'){
			//$CatProdsQry->Where('po.current_stock > ','=','Discovery Set');
			$CatProdsQry->whereColumn('po.current_stock', '>', 'po.minimum_stock')
						->where(function ($query) {
							$query->where('po.current_stock', '>', 0)
								->orWhere('po.cosmo_current_stock', '>', 0)
								->orWhere('po.pca_current_stock', '>', 0)
								->orWhere('po.nandansons_current_stock', '>', 0)
								->orWhere('po.perfumeworldwide_currentstock', '>', 0)
								->orWhere('po.nd_current_stock', '>', 0);
						});

		}

		if(isset($Filters['fpocket_perfume']) && count($Filters['fpocket_perfume']) > 0){
			for($v = 0; $v < count($Filters['fpocket_perfume']); $v++){
				if(isset($Filters['fpocket_perfume'][$v]) && trim($Filters['fpocket_perfume'][$v])=="f-pocket-perfume"){
					$isPocketPerfume = 'Y';
					$pkt_perfume_arr = array(68,69,70,71);
					//$CatProdsQry->WhereIn('pc.category_id',$pkt_perfume_arr);
					$CatProdsQry->Where('po.is_atomizer','Yes');
				}
			}
		}
		//$Filters['f_size'] = $CatProdsQry->toSql();

        /*
		if(count($ChildCatArr) > 0)
			$CatProdsQry->whereIn('pc.category_id',$ChildCatArr);
            */
		$CatProdsQry->groupBy(['po.variation_id']);

		$FilterStock = '';
		$FilterSampleProducts = '';
		$FilterMinPrice = '';
		$FilterMaxPrice = '';
		$FilterKey = '';
		$BrandInSearch = 0;
        $CategoryInFilter = 0;
        $PriceFilter = [];
        $FilterCategories = [];
        $MenCategories = [3,8,43,70];
        $WomenCategories = [5,7,69];
        $UnisexCategories = [4,11,71];
        $GenderCategories = [1,63];
        $SelectedFilters = [];
        $SelGenders = [];

        foreach($Filters as $fkey => $Filter)
        {
            if(is_array($Filter) && count($Filter) > 0)
            {
                if($fkey == 'categories'){
                    $CatParentID = 0;
                    if(count($Filter) == 1 && in_array('ts',$Filter))
                    {

                    } else {
                        if(strstr($CategoryID,","))
                        {
                            $ExpCats = explode(",",$CategoryID);
                            $CategoryID = $ExpCats[0];
                            if(in_array($CategoryID,['m','w','u','ts']))
                            {
                                $CategoryID = 0;
                            }
                        }
                        if($CategoryID > 0)
                        {
                            $SelCatDetails = config('CATEGORY_INFO');
                            $CatInfo = $SelCatDetails['CatForProd'][$CategoryID];
                            if(count($CatInfo) > 0 )
                            {
                                $CatParentID = $CatInfo['root_parent_id'];
                            }
                        }
                    }

                    foreach($Filter as $ProdCat)
                    {
                        $ExpCats = explode(",",$ProdCat);
                        if($ProdCat == 'men' || $ProdCat == 'm')
                        {
                            // if($CatParentID == 1)
                            //     array_push($FilterCategories,3,8,43);
                            // if($CatParentID == 63)
                            //     array_push($FilterCategories,70);
                            //if(!in_array($CatParentID,$GenderCategories))
                                $SelGenders[]='M';
                        }
                        if($ProdCat == 'women' || $ProdCat == 'w')
                        {
                            // if($CatParentID == 1)
                            //     array_push($FilterCategories,5,7);
                            // if($CatParentID == 63)
                            //     array_push($FilterCategories,69);
                            //if(!in_array($CatParentID,$GenderCategories))
                                $SelGenders[]='W';
                        }
                        //if($ProdCat == 'unisex' || $ProdCat == 'w')
						if($ProdCat == 'unisex' || $ProdCat == 'u')
                        {
                            // if($CatParentID == 1)
                            //     array_push($FilterCategories,4,11);
                            // if($CatParentID == 63)
                            //     array_push($FilterCategories,71);
                            //if(!in_array($CatParentID,$GenderCategories))
                                $SelGenders[]='U';
                        }
                        if($ProdCat == 'niche-fragrances')
                        {
                            if($CatParentID == 1)
                                array_push($FilterCategories,2);
                        }
                        if($ProdCat == 'gift-sets')
                        {
                            if($CatParentID == 1)
                                array_push($FilterCategories,7,8,11);
                        }
                        if($ProdCat == 'top_seller' || $ProdCat == 'ts')
                        {
                            $Flag = "TOP_SELLERS";
                        }
                        if($ProdCat == 'new_arrival' || $ProdCat == 'na')
                        {
                            $Flag = "NEW_ARRIVALS";
                        }
                        if($ProdCat == 'max2day')
                        {
							$ismaxTwoDay = 'Y';
                            $CatProdsQry->where('po.maxtwodaydelivery','Yes');
                            $CatProdsQry->whereNotIn('po.sku', function ($query) {
								$query->select('product_sku')->from('pu_dealofweek')->where('status','=','1')->where('start_date','<=',date('Y-m-d H:i'))->where('end_date','>=',date('Y-m-d H:i'));
							});
                        }
						if($ProdCat == 'OnTopRated'){
								$CatProdsQry
									->join('pu_products_rating as pr', 'pr.products_id', '=', 'po.products_id')
									->where('pr.rating', '>', 4);
						}
						if($ProdCat == 'InStockItems'){
							$CatProdsQry->whereColumn('po.current_stock', '>', 'po.minimum_stock')
								->where(function ($query) {
									$query->where('po.current_stock', '>', 0)
										->orWhere('po.cosmo_current_stock', '>', 0)
										->orWhere('po.pca_current_stock', '>', 0)
										->orWhere('po.nandansons_current_stock', '>', 0)
										->orWhere('po.perfumeworldwide_currentstock', '>', 0)
										->orWhere('po.nd_current_stock', '>', 0);
								});
						}
						if($ProdCat == 'OnSaleDeal'){
							$CatProdsQry->leftJoin('pu_dealofweek as dow', 'dow.product_sku', '=', 'po.sku')
								->where(function($query) {
									$query->where('po.current_stock', '>', 0)
										->where('po.retail_price', '>', 0)
										->where('po.sale_price', '>', 0)
										->whereColumn('po.retail_price', '>', 'po.sale_price');
								})
								->orWhere(function($query) {
									$query->where('dow.status', '=', 1)
										->where('dow.start_date', '<=', date('Y-m-d H:i'))
										->where('dow.end_date', '>=', date('Y-m-d H:i'));
								});
						}
                        if(count(array_intersect($MenCategories, $ExpCats)) > 0)
                        {
                            $SelectedFilters['Category'] = 'Men';
                        }
                    }
                }
				else if($fkey == 'OnSaleDeal'){
					$CatProdsQry->leftJoin('pu_dealofweek as dow', 'dow.product_sku', '=', 'po.sku')
								->where(function($query) {
									$query->where('po.current_stock', '>', 0)
										->where('po.retail_price', '>', 0)
										->where('po.sale_price', '>', 0)
										->whereColumn('po.retail_price', '>', 'po.sale_price');
								})
								->orWhere(function($query) {
									$query->where('dow.status', '=', 1)
										->where('dow.start_date', '<=', date('Y-m-d H:i'))
										->where('dow.end_date', '>=', date('Y-m-d H:i'));
								});
                }
				else if($fkey == 'InStockItems'){
					//$CatProdsQry->where('po.current_stock', '>', 0);
					$CatProdsQry->whereColumn('po.current_stock', '>', 'po.minimum_stock')
								->where(function ($query) {
									$query->where('po.current_stock', '>', 0)
										->orWhere('po.cosmo_current_stock', '>', 0)
										->orWhere('po.pca_current_stock', '>', 0)
										->orWhere('po.nandansons_current_stock', '>', 0)
										->orWhere('po.perfumeworldwide_currentstock', '>', 0)
										->orWhere('po.nd_current_stock', '>', 0);
								});
                }
				else if($fkey == 'OnTopRated'){
					$CatProdsQry
						->join('pu_products_rating as pr', 'pr.products_id', '=', 'po.products_id')
						->where('pr.rating', '>', 4);
				}
				else if($fkey == 'max2day'){
					$ismaxTwoDay = 'Y';
                    $CatProdsQry->where('po.maxtwodaydelivery','Yes');
					$CatProdsQry->whereNotIn('po.sku', function ($query) {
						$query->select('product_sku')->from('pu_dealofweek')->where('status','=','1')->where('start_date','<=',date('Y-m-d H:i'))->where('end_date','>=',date('Y-m-d H:i'));
					});
                } else if($fkey == 'special'){
                    foreach($Filter as $ProdCat)
                    {
                        if($ProdCat == 'top_seller' || $ProdCat == 'ts')
                        {
                            $Flag = "TOP_SELLERS";
                        }
						if($ProdCat == 'featured' || $ProdCat == 'fe'){
							$CatProdsQry->where('po.featured','=','Yes');
						}
						if($ProdCat == 'clearance' || $ProdCat == 'cl'){
							$CatProdsQry->where('po.clearance','=','Yes');
						}
						if($ProdCat == 'celebrity' || $ProdCat == 'cp'){
							$CatProdsQry->where('po.celebrity','=','Yes');
						}
						if($ProdCat == 'sale_price' || $ProdCat == 'sl'){
							$CatProdsQry->where('po.sale_price','>',0);
						}
                        if($ProdCat == 'new_arrival' || $ProdCat == 'na')
                        {
                            $Flag = "NEW_ARRIVALS";
                        }
                    }
                }else if($fkey == 'gender'){
                    $SelGenders = array_map('strtoupper', $Filter);
                } else if($fkey == 'brands'){
                    $CatProdsQry->whereIn('po.imanufactureid',$Filter);
                    $BrandInSearch=1;
                } else if($fkey == 'fragrance_family'){
                    $CatProdsQry->whereIn('po.fragrance_family',$Filter);
                } else if($fkey == 'fragrance_occasion'){
                    $CatProdsQry->whereIn('po.fragrance_occasion',$Filter);
                } else if($fkey == 'review_rating'){
					// $CatProdsQry->join('pu_products_rating as pr', 'pr.products_id', '=', 'po.products_id')
        			// 			->whereIn('pr.rating', $Filter);

					$minRating = min($Filter);
					$CatProdsQry
						->join('pu_products_rating as pr', 'pr.products_id', '=', 'po.products_id')
						->where('pr.rating', '>=', $minRating);

					// $CatProdsQry
					// 	->join('pu_products_rating as pr', 'pr.products_id', '=', 'po.products_id')
					// 	->where(function ($q) use ($Filter) {
					// 		$placeholders = implode(',', array_fill(0, count($Filter), '?'));
					// 		$q->whereRaw("ROUND(pr.rating) IN ($placeholders)", $Filter);
					// });

					// $ratings = array_values(array_unique(array_map('intval', $Filter)));
					// $CatProdsQry->whereExists(function ($q) use ($ratings) {
					// 	$q->select(DB::raw(1))
					// 	->from('pu_products_rating as pr')
					// 	->whereColumn('pr.products_id', 'po.products_id')
					// 	->whereRaw(
					// 		'FLOOR(pr.rating) IN (' . implode(',', array_fill(0, count($ratings), '?')) . ')',
					// 		$ratings
					// 	);
					// });

					// $CatProdsQry->where(function($query) use ($Filter) {
					// 	foreach ($Filter as $value) {
					// 		$query->orWhereRaw('FLOOR(pr.rating) = ?', [$value]);
					// 	}
					// });
                } else if($fkey == 'fragrance_season'){
                    if(in_array('Year Around',$Filter)){
						$fltr = array("Spring","Summer","Fall","Winter");
						//$CatProdsQry->whereIn('po.fragrance_seasons',$fltr);
						$CatProdsQry->where(function($query) use ($fltr) {
							foreach ($fltr as $value) {
								$query->orWhere('po.fragrance_seasons', 'LIKE', '%' . $value . '%');
							}
						});
					} else {
						//$CatProdsQry->whereIn('po.fragrance_seasons',$Filter);
						$CatProdsQry->where(function($query) use ($Filter) {
							foreach ($Filter as $value) {
								$query->orWhere('po.fragrance_seasons', 'LIKE', '%' . $value . '%');
							}
						});
					}
                }else if($fkey == 'size'){
                    if(count($Filter) > 0)
                    {
                        foreach($Filter as $SizeFilter)
                        {
                            $ExpFilter = explode("-",$SizeFilter);
                        }
                        /*
                        if(count($ExpFilter) > 0)
                            $CatProdsQry->whereIn('po.size',$ExpFilter);
                        */
                    }
                } else if($fkey == 'price'){
                    $PriceFilter = $Filter;
                }
            }
			if($fkey == 'key'){
				$FilterKey = $Filter;
			}
			else if($fkey == 'stock'){
				$FilterStock = $Filter;
			}
        }

		if($isGiftSet == 'Y'){
			array_push($FilterCategories,7,8,11);
		}

        if(count($FilterCategories) > 0)
        {
            if(count(array_intersect($MenCategories, $FilterCategories)) > 0)
            {
                $SelectedFilters['Category'] = 'Men';
            }
            $CatProdsQry->whereIn('pc.category_id',$FilterCategories);
        } elseif($CategoryID != ''){
			$ChildCats = GetMainCatsTree([$CategoryID]);
			if(count($ChildCats['CatList']) > 0)
            {
				$FilterCategories = array_column($ChildCats['CatList'],'category_id');
            }else{
                $ExpCats = explode(",",$CategoryID);
				$FilterCategories = $ExpCats;

				if(isset($Filters['categories']) && ($CategoryID == 3  || $CategoryID == 4  || $CategoryID == 5)){
					$ChildCats = GetMainCatsTree([1]);
					$FilterCategories = array_column($ChildCats['CatList'],'category_id');
					///Log::info('Here 4444 -- '.json_encode($ChildCats));
				}else if(isset($Filters['categories']) && ($CategoryID == 69  || $CategoryID == 70  || $CategoryID == 71)){
					$ChildCats = GetMainCatsTree([68]);
					$FilterCategories = array_column($ChildCats['CatList'],'category_id');
					///Log::info('Here 4444 -- '.json_encode($ChildCats));
				}
            }
			//18032024	 //09052024
			if($CategoryID == '68'){
                $isPocketPerfume = "Y";
				$CatProdsQry->whereIn('pc.category_id',$FilterCategories);
				if(!isset($Filters['is_atomizer_n'])){
                    $CatProdsQry->where('po.is_atomizer','Yes');
                } else if(isset($Filters['is_atomizer_n']) && $Filters['is_atomizer_n']=='N'){
                    $isPocketPerfume = "N";
                }
				//$CatProdsQry->where('po.is_atomizer','Yes');
				// $CatProdsQry->where(function($query) use ($FilterCategories){
				// 	$query->orWhere('po.is_atomizer','Yes');
				// 	$query->orwhereIn('pc.category_id',$FilterCategories);
				// });
            } else {
            	$CatProdsQry->whereIn('pc.category_id',$FilterCategories);
			}
        }
		//echo implode(',',$FilterCategories);
        if(count($SelGenders) > 0)
        {
			//print_r($SelGenders);
			if(isset($SelGenders[0]) && $SelGenders[0] == 'U'){
				//$SelGenders = array('m','w','u');
				$SelGenders = array('u');
			}
            $CatProdsQry->whereIn('po.gender',$SelGenders);
        }

		if($Flag == "TOP_SELLERS")
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere('po.is_sold_quantity','>',0);
			});
		}

		if($Flag == "NEW_ARRIVALS")
		{
			$CatProdsQry->whereNotIn('pc.category_id',['198','199','200','201']);

			$CatProdsQry->where(function($query){
				$from = Carbon::now()->subDays(30);
				$to = Carbon::now();
				$query->where('po.new_arrival','=','Yes');
				$query->orwhereBetween(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),[$from,$to]);
			});
			/*$CatProdsQry->where(function($query){
				$query->where('po.new_arrival','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});*/
		}

		if($Flag == 'CategoryPage')
		{
			/*$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});*/
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else if($Flag == 'DealofweekPage'){
			$CatProdsQry->join('pu_dealofweek as dw','dw.product_sku','=','po.sku');
			$CatProdsQry->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did');
			$CatProdsQry->where('dw.deal_type','=','Weekly');
			$CatProdsQry->where('dw.status','=','1');
			$CatProdsQry->where('dw.start_date','<=',date('Y-m-d H:i'))->where('dw.end_date','>=',date('Y-m-d H:i'));
			if($FilterKey != '')
				$CatProdsQry->where('po.UPC','=',$FilterKey);

			if(isset($Filters['onTopRated']) && $Filters['onTopRated'] == 'Y'){
				$CatProdsQry->orderBy('pr.rating','desc');
			}

			$CatProdsQry->orderBy('dwt.deal_rank');
			$CatProdsQry->orderBy('dw.end_date');
			$CatProdsQry->orderBy('dw.display_rank');
		}else if($Flag == 'Promotional'){
			if(isset($Filters['categories'])){
				if (in_array("OnTopRated", $Filters['categories'])) {
					$CatProdsQry->orderBy('pr.rating','desc');
				}
			}
			if(isset($Filters['OnTopRated'])){
				$CatProdsQry->orderBy('pr.rating','desc');
			}
			if($FilterKey != ''){
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			}
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		else if($Flag == 'Maxtwoday'){
			if(isset($Filters['categories'])){
				if (in_array("OnTopRated", $Filters['categories'])) {
					$CatProdsQry->orderBy('pr.rating','desc');
				}
			}
			if(isset($Filters['OnTopRated'])){
				$CatProdsQry->orderBy('pr.rating','desc');
			}
			$ismaxTwoDay = 'Y';
			$CatProdsQry->where('po.maxtwodaydelivery','=','Yes');
			$CatProdsQry->whereNotIn('po.sku', function ($query) {
					$query->select('product_sku')->from('pu_dealofweek')->where('status','=','1')->where('start_date','<=',date('Y-m-d H:i'))->where('end_date','>=',date('Y-m-d H:i'));
			});
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		else if($Flag == 'ProductListPage' || $Flag == 'BrandPage'){
			if(isset($Filters['categories'])){
				if (in_array("OnTopRated", $Filters['categories'])) {
					$CatProdsQry->orderBy('pr.rating','desc');
				}
			}
			if(isset($Filters['OnTopRated'])){
				$CatProdsQry->orderBy('pr.rating','desc');
			}
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('b.brand_name');
			$CatProdsQry->orderBy('po.cosmo_sku');
			$CatProdsQry->orderBy('po.nandansons_sku');
			$CatProdsQry->orderBy('po.pca_sku');
			$CatProdsQry->orderBy('po.perfumeworldwide_sku');
			$CatProdsQry->orderBy('po.nd_sku');
			$CatProdsQry->orderBy('po.display_position');
		} else if($Flag == 'ShoppingCart'){
			$CatProdsQry->join('pu_products_viewed as pv','po.sku','=','pv.sku');
			$CatProdsQry->where('pv.customer_ip','!=',$_SERVER['REMOTE_ADDR']);
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		} else if($Flag == "TOP_SELLERS"){
			if(isset($Filters['categories'])){
				if (in_array("OnTopRated", $Filters['categories'])) {
					$CatProdsQry->orderBy('pr.rating','desc');
				}
			}
			if(isset($Filters['OnTopRated'])){
				$CatProdsQry->orderBy('pr.rating','desc');
			}
			//$CatProdsQry->orderBy('po.is_sold_quantity','desc');
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
			$CatProdsQry->orderBy('po.is_sold_quantity','desc');
		} else if($Flag == "NEW_ARRIVALS"){
			if(isset($Filters['categories'])){
				if (in_array("OnTopRated", $Filters['categories'])) {
					$CatProdsQry->orderBy('pr.rating','desc');
				}
			}
			if(isset($Filters['OnTopRated'])){
				$CatProdsQry->orderBy('pr.rating','desc');
			}
			//$CatProdsQry->orderBy('po.add_datetime','desc');
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else{
			if(isset($Filters['categories'])){
				if (in_array("OnTopRated", $Filters['categories'])) {
					$CatProdsQry->orderBy('pr.rating','desc');
				}
			}
			if(isset($Filters['OnTopRated'])){
				$CatProdsQry->orderBy('pr.rating','desc');
			}
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		if($SortBy == 'latest'){

			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}
		if($chkFragranceType == 'Y'){
			$CatProdsQry->orderBy('po.fragrance_personality','asc');
		}

		if($isPocketPerfume == 'Y'){
			$CatProdsQry->orderBy('po.current_stock','desc');
            $CatProdsQry->orderBy('po.cosmo_current_stock','desc');
            $CatProdsQry->orderBy('po.pca_current_stock','desc');
            $CatProdsQry->orderBy('po.nandansons_current_stock','desc');
            $CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
            $CatProdsQry->orderBy('po.product_name');
            $CatProdsQry->orderBy('po.cosmo_sku');
            $CatProdsQry->orderBy('po.nandansons_sku');
            $CatProdsQry->orderBy('po.pca_sku');
            $CatProdsQry->orderBy('po.perfumeworldwide_sku');
            $CatProdsQry->orderBy('po.nd_sku');
            $CatProdsQry->orderBy('po.display_position');
		}

		//echo $CatProdsQry->toSql();
		//$Filters['f_size'] = $CatProdsQry->toSql();
		//$CatProdsWithoutLimit = $CatProdsQry->get();
		//echo $SortBy;
		$ArrayFilters = ['sortby' => $SortBy, 'offset' => $Offset, 'limit' => $limit];

		$SKUs = '' ;
		$CatProducts = [];
		$CatDealProducts = [];
		$TotalProds = 0;
		$VariationIDs=[];
		$ProdIds=[];
		$DealData = GetDealOfWeek('',"Weekly");
		//$Filters['f_size'] = $CatProdsQry->toSql();
		if($_SERVER['REMOTE_ADDR'] == '2406:b400:d5:9866:14f4:4145:ec48:595d'){
			//echo $CatProdsQry->toSql();
		}

		if(isset($Filters['isSampleProduct']) && $Filters['isSampleProduct'] == 'Y'){
			$FilterSampleProducts = 'Y';
			$isPocketPerfume = 'Y';
		}

		//exit;
		//$CatProdsQry->offset($Offset)->chunk(1000, function($MyCatProdsWithoutLimit)use(&$CatProducts,&$VariationIDs,&$ProdIds,&$TotalProds,$CategoryID,$DealData,$FilterStock,$FilterMinPrice,$FilterMaxPrice,$BrandInSearch,$PriceFilter)
		$CatProdsQry->offset($Offset)->chunk(1000, function($MyCatProdsWithoutLimit)use(&$CatProducts,&$VariationIDs,&$ProdIds,&$TotalProds,$CategoryID,$DealData,$FilterStock,$FilterMinPrice,$FilterMaxPrice,$BrandInSearch,$PriceFilter,$Flag,$FilterSampleProducts)
		{

			foreach($MyCatProdsWithoutLimit as $key => $CatProd)
			{

				$CatProd = $this->SetProduct($CatProd);

				if($FilterSampleProducts == 'Y' && ($CatProd->stock == 'Out' || $CatProd->is_atomizer == 'Yes'))
					continue;

				if(is_array($FilterStock) && count($FilterStock) > 0 && $CatProd->stock == 'Out')
					continue;

                if($CatProd->product_price <= 0)
					continue;

                if(count($PriceFilter) > 0)
                {
                    $SelMinPrice = 0; $SelMaxPrice = 0;
                    $Prices = [];
                    foreach($PriceFilter as $FilterPrice)
                    {
                        $ExpPrice = explode("-",$FilterPrice);
                        for($p = 0; $p <count($ExpPrice); $p++)
                        {
                            $Prices[]=$ExpPrice[$p];
                        }
                    }
                    $SelMinPrice = min($Prices);
                    $SelMaxPrice = max($Prices);

                    if($SelMinPrice == $SelMaxPrice)
                    {
                        if((float)$CatProd->product_price < $SelMaxPrice )
                        continue;
                    } else {
                        if((float)$CatProd->product_price < $SelMinPrice || (float)$CatProd->product_price > $SelMaxPrice )
                        continue;
                    }
                } else {
                    if($FilterMaxPrice !='')
                    {
                        $FilterMaxPrice = (float)$FilterMaxPrice;
                        $FilterMinPrice = (float)$FilterMinPrice;
                        if((float)$CatProd->product_price < $FilterMinPrice || (float)$CatProd->product_price > $FilterMaxPrice )
                            continue;
                    }
                }

				$TotalProds++;
				$VariationIDs[]=$CatProd->variation_id;

				if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
					$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($CatProd->image);
					$verP = filemtime($newimageVal);
					$CatProd->prod_image  = config('global.PRD_LARGE_IMG_URL') . $CatProd->image . "?ver=" . $verP;
				} else {
					$CatProd->prod_image = config('global.NO_IMAGE_LARGE');
				}

				/*if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
					$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProd->image);
					$verP = filemtime($newimageVal);
					$CatProd->prod_image  = config('global.PRD_THUMB_IMG_URL') . $CatProd->image . "?ver=" . $verP;
				} else {
					$CatProd->prod_image = config('global.NO_IMAGE_THUMB');
				}*/

				$CatProd->BrandInSearch = $BrandInSearch;

				$PriceRange = $this->setPriceRange($CatProd->variation_id,$MyCatProdsWithoutLimit);

				$CatProd->minPrice = $PriceRange['MinPrice'];
				$CatProd->maxPrice = $PriceRange['MaxPrice'];
				$CatProd->yousave = $PriceRange['YouSave'];

				$CatProd->ProdPrice = $CatProd->product_price;
				if($CatProd->minPrice > 0 && $CatProd->maxPrice > 0 &&  $CatProd->maxPrice!=$CatProd->minPrice)
				{
					$CatProd->ProdPrice = $CatProd->maxPrice;
					if($SortBy=="priceLH")
					{
					   $CatProd->ProdPrice = $CatProd->minPrice;
					}
				}

				if($CategoryID == '2')
					$CatProd->category_id = $CategoryID;

				if($CatProd->parent_id != 0)
					$ProdCat = $CatProd->parent_id;
				else
					$ProdCat = $CatProd->category_id;

				$CatProd->product_url = SetProductURL($CatProd->products_id,$CatProd->product_name,$CatProd->category_id);

				if ($CatProd->gender == 'M'){
					$CatProd->gender = "sv-men";
					$CatProd->gendernames = "Men";
					$for_gender = ' for Men';
				} elseif ($CatProd->gender == 'W'){
					$CatProd->gender = "sv-women";
					$CatProd->gendernames = "Women";
					$for_gender = ' for Women';
				} elseif ($CatProd->gender == 'K'){
					$CatProd->gender = "sv-kids";
					$CatProd->gendernames = "Kids";
					$for_gender = ' for Kids';
				} elseif ($CatProd->gender == 'U'){
					$CatProd->gender = "sv-unisex";
					$CatProd->gendernames = "Unisex";
					$for_gender = ' Unisex';
				} else{
					$CatProd->gender = "";
					$CatProd->gendernames = "";
					$for_gender = '';
				}

				if($CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$m_name = str_replace("'", "", $m_name);
					$CatProd->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$CatProd->imanufactureid;
				}

				if($CatProd->brand_name != '' && $CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$m_name = str_replace("'", "", $m_name);
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><strong>' . $CatProd->brand_name . '</strong></a> by <a href='.$CatProd->vmanufacture_link.'><strong><br>'.$CatProd->vmanufacture.'</strong></a>'.$for_gender;
				}

				if(strlen($CatProd->product_name) > 45){
					$CatProd->product_name = substr($CatProd->product_name, 0, (45 - strlen($CatProd->product_name))). "..";
				} else {
					$CatProd->product_name = $CatProd->product_name;
				}

				$CatProd->product_name = strip_tags($CatProd->product_name);

				if($CatProd->vmanufacture == '' || $CatProd->brand_name == ''){
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '">' . $CatProd->product_name . '</a>';
				}

				if($CatProd->retail_price != '' && $CatProd->retail_price != '0.00' && isset($CatProd->product_price)){
					$yousave = ($CatProd->retail_price - $CatProd->product_price) / $CatProd->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$yousaveprice = $CatProd->retail_price - $CatProd->product_price;
				}else{
					$yousave = 0;
					$yousaveprice = 0;
				}

				$CatProd->yousave = $yousave;
				$CatProd->maxyousave = (($CatProd->yousave>0)?number_format($CatProd->yousave, 0):0);
				$CatProd->yousaveprice = $yousaveprice;
				$CatProd->autoid = $key;

				$CatProd->sale_item = '0';
				//if($CatProd->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
				if($CatProd->sale_price > 0 && Session::has('eusertype') && strtolower(trim(Session::get('eusertype') ?? ''))!='wholesaler')
				{
					$CatProd->sale_item = '1';
				}

				if(isset($DealData[$CatProd->sku]) && isset($CatProd->WebsiteStock) && $CatProd->WebsiteStock=="In")
				{
					$CatProd->deal_price = $DealData[$CatProd->sku]['deal_price'];
					$CatProd->ProdPrice = $CatProd->deal_price;
					$CatProd->yousave = $DealData[$CatProd->sku]['yousave'];
					$CatProd->yousaveprice = $DealData[$CatProd->sku]['yousaveprice'];
				}

				if(isset($Flag) && $Flag=="DealofweekPage" && $CatProd->WebsiteStock=="Out")
				{
					$CatProd->deal_price = $CatProd->ProdPrice ;
				}

				$CatProd->short_description = strip_tags($CatProd->short_description);
				$CatProd->size_cnt = $CatProd->variarioncnt;
				$ProdIds[]=$CatProd->products_id;

				$CatProducts[] = $CatProd;
			}
		});
		$TotalProducts = $TotalProds;

        $AllFilters = [];

        if(count($CatProducts)>0 && isset($ArrayFilters['sortby']) && $ArrayFilters['sortby'] != '')
		{
			/*if($ArrayFilters['sortby'] == 'latest'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($second->add_datetime),strtolower($first->add_datetime));
				});
			}*/

			if($ArrayFilters['sortby'] == 'latest'){
				usort($CatProducts, function($first, $second) {
					if ($first->stock !== $second->stock) {
						return ($first->stock === "In") ? -1 : 1;
					}
					return strcmp(strtolower($second->add_datetime), strtolower($first->add_datetime));
				});
			}

			if($ArrayFilters['sortby'] == 'priceHL'){
				usort($CatProducts, function($first, $second) {
				if ($first->stock != $second->stock) {
					return ($first->stock === "In") ? -1 : 1;
				}

				if ($first->ProdPrice == $second->ProdPrice) {
					return 0;
				}
				return ($first->ProdPrice < $second->ProdPrice) ? 1 : -1;
			});

			}
			if ($ArrayFilters['sortby'] == 'priceLH') {
				usort($CatProducts, function($first, $second) {
					if ($first->stock != $second->stock) {
						return ($first->stock === "In") ? -1 : 1;
					}

					if ($first->ProdPrice == $second->ProdPrice) {
						return 0;
					}
					return ($first->ProdPrice > $second->ProdPrice) ? 1 : -1;
				});
			}

			// if($ArrayFilters['sortby'] == 'priceAZ'){
			// 	usort($CatProducts,function($first,$second){
			// 		return strcmp(strtolower($first->brand_name),strtolower($second->brand_name));
			// 	});
			// }

			if ($ArrayFilters['sortby'] == 'priceAZ') {
				usort($CatProducts, function($a, $b) {
					if ($a->stock !== $b->stock) {
						return ($a->stock === "In") ? -1 : 1;
					}

					$aBrand = trim(strtolower(strip_tags($a->brand_name ?? '')));
					$bBrand = trim(strtolower(strip_tags($b->brand_name ?? '')));
					$cmp = strnatcmp($aBrand, $bBrand);
					if ($cmp !== 0) return $cmp;

					$aFragrance = trim(strtolower(strip_tags($a->vmanufacture ?? '')));
					$bFragrance = trim(strtolower(strip_tags($b->vmanufacture ?? '')));
					$cmp = strnatcmp($aFragrance, $bFragrance);
					if ($cmp !== 0) return $cmp;

					$aName = trim(strtolower(strip_tags($a->referencedName ?? '')));
					$bName = trim(strtolower(strip_tags($b->referencedName ?? '')));
					return strnatcmp($aName, $bName);
				});
			}

			// if($ArrayFilters['sortby'] == 'priceZA'){
			// 	usort($CatProducts,function($first,$second){
			// 		return strcmp(strtolower($second->brand_name),strtolower($first->brand_name));
			// 	});
			// }

			if ($ArrayFilters['sortby'] == 'priceZA') {
				usort($CatProducts, function($a, $b) {
					if ($a->stock !== $b->stock) {
						return ($a->stock === "In") ? -1 : 1;
					}

					$aBrand = trim(strtolower(strip_tags($a->brand_name ?? '')));
					$bBrand = trim(strtolower(strip_tags($b->brand_name ?? '')));
					$cmp = strnatcmp($bBrand, $aBrand);
					if ($cmp !== 0) return $cmp;

					$aFragrance = trim(strtolower(strip_tags($a->vmanufacture ?? '')));
					$bFragrance = trim(strtolower(strip_tags($b->vmanufacture ?? '')));
					$cmp = strnatcmp($bFragrance, $aFragrance);
					if ($cmp !== 0) return $cmp;

					$aName = trim(strtolower(strip_tags($a->referencedName ?? '')));
					$bName = trim(strtolower(strip_tags($b->referencedName ?? '')));
					return strnatcmp($bName, $aName);
				});
			}

		} else {
			//usort($CatProducts,function($first,$second){
				//return $first->WebsiteStock > $second->WebsiteStock ? 1 : -1;
			//});
		}

		if(count($CatProducts)>0 && isset($ArrayFilters['limit']) && $ArrayFilters['limit'] != '')
		{
			if($Flag == "DealofweekPage"){
				$CatDealProducts = $CatProducts;
			}
			if(count($CatProducts) > $ArrayFilters['offset'])
				$CatProducts = array_slice($CatProducts,$ArrayFilters['offset'],$ArrayFilters['limit']);
		}
		/*echo '<strong>Variation Id : </strong>'.json_encode($VariationIDs);
		echo '<br><strong>Cat Products : </strong>'.json_encode($CatProducts);
		echo '<br><strong>Child Cat : </strong>'.json_encode($ChildCatArr);
		echo '<br><strong>Flag : </strong>'.json_encode($Flag);*/
		//if($TotalProducts == 0){
			//$Filters['f_size'] = "Flag ---- ".$Flag."--- Category --- ".$CategoryID;
			//GetProductsNewFilters($Flag,$CategoryID,25);
		//}
		//echo $Flag;
		//echo $ismaxTwoDay;
		if($Flag == "DealofweekPage"){
			$AllFilters = $this->GetFilters($CatDealProducts,$Filters,$Flag);
		}
		 if($isPocketPerfume == 'N') {
			$CatProducts = $this->CountOptions($VariationIDs,$CatProducts,$ChildCatArr,$Flag,$ismaxTwoDay,$isPocketPerfume);
		}
		$ProductsDetails = ['Products' => $CatProducts,'TotalProducts' => $TotalProducts, 'LeftFilters' => $AllFilters, 'FilterCategories' => explode(",",$CategoryID ?? ''), 'SelectedFilters' => $Filters];

		return $ProductsDetails;
	}

	public function ProductFilters($ProductFilters=[], $ListFrom = '', $brandFilter = [], $min_price = '', $max_price = '')
    {
        $Filters = [];
        $i=0;
        //$Filters[$i]['Category']['Attr'] = ['id' => 'categories', 'title' => 'Category'];
        $MenSelected = $WomenSelected = $UnisexSelected = 'No';

        $MenCategories = [3,8,43,70];
        $WomenCategories = [5,7,69];
        $UnisexCategories = [4,11,71];
        $GenderCategories = [1,63];

        $SetMen = [];
        $SetWomen = [];
        $SetUnisex = [];
        $SetNiche = [];
        $SetGiftSets = [];

        $NicheDataValue = "";
        $GiftsetDataValue = "";
        $UnisexDataValue = "u";
        $MenDataValue = "m";
        $WomenDataValue = "w";
        $Max2day = "max2day";

		$InStockItemsSelected = "No";

		$OnSaleDealSelected = 'No';
		$OnTopRatedSelected = 'No';

        if(array_key_exists('cid',$ProductFilters))
        {
            if(strstr($ProductFilters['cid'],","))
            {
                $ExpCats = explode(",",$ProductFilters['cid']);
                $CategoryID = $ExpCats[0];
            } else {
                $CategoryID = $ProductFilters['cid'];
            }
            $SelCatDetails = config('CATEGORY_INFO');
			$CatInfo = array();
			if(isset($SelCatDetails['CatForProd'][$CategoryID])){
            	$CatInfo = $SelCatDetails['CatForProd'][$CategoryID];
			}
            $CatParentID = 0;
            if(count($CatInfo) > 0 )
            {
                $CatParentID = $CatInfo['root_parent_id'];
            }

            if($CatParentID == 1)
            {
                array_push($SetMen,3,8,43);
                array_push($SetWomen,5,7);
                array_push($SetUnisex,4,11);
                array_push($SetNiche,2);
                array_push($SetGiftSets,7,8,11);
            } else if($CatParentID == 63){
                array_push($SetMen,70);
                array_push($SetWomen,69);
                array_push($SetUnisex,71);
            }

            if(count(array_intersect($MenCategories, explode(",",$ProductFilters['cid']))) > 0)
                $MenSelected = 'Yes';
            if(count(array_intersect($WomenCategories, explode(",",$ProductFilters['cid']))) > 0)
                $WomenSelected = 'Yes';
            if(count(array_intersect($UnisexCategories, explode(",",$ProductFilters['cid']))) > 0)
                $UnisexSelected = 'Yes';

            // $MenDataValue = (count($SetMen) > 0)?implode(",",$SetMen):'m';
            // $WomenDataValue = (count($SetWomen) > 0)?implode(",",$SetWomen):'w';
            // $UnisexDataValue = (count($SetUnisex) > 0)?implode(",",$SetUnisex):'u';

			$MenDataValue = 'm';
            $WomenDataValue = 'w';
            $UnisexDataValue = 'u';

            $NicheDataValue = implode(",",$SetNiche);
            $GiftsetDataValue = implode(",",$SetGiftSets);
        }
        if(array_key_exists('gender',$ProductFilters))
        {
            foreach($ProductFilters['gender'] as $Gender)
            {
                if($Gender == 'm'){
                    $MenDataValue = 'm';
                    $MenSelected = 'Yes';
                }
                if($Gender == 'w'){
                    $WomenDataValue = 'w';
                    $WomenSelected = 'Yes';
                }
                if($Gender == 'u'){
                    $UnisexDataValue = 'u';
                    $UnisexSelected = 'Yes';
                }
            }
        }
		if(array_key_exists('InStockItems',$ProductFilters)){
			$InStockItemsSelected = "Yes";
		}
		if(array_key_exists('OnSaleDeal',$ProductFilters)){
			$OnSaleDealSelected = "Yes";
		}
		if(array_key_exists('OnTopRated',$ProductFilters)){
			$OnTopRatedSelected = "Yes";
		}
        $BestSellerSelected = "No";
        if(array_key_exists('special',$ProductFilters))
        {
            $BestSellerSelected = "Yes";
        }
        $Max2daySelected = "No";
        if(array_key_exists('max2day',$ProductFilters))
        {
            $Max2daySelected = "Yes";
        }
		$NewarrivalSelected = "No";
		if(array_key_exists('Special-na',$ProductFilters))
        {
            $NewarrivalSelected = "Yes";
        }

        $is_men_gender = ($MenDataValue=="m")?'Gender':'Category';
        $is_women_gender = ($WomenDataValue=="w")?'Gender':'Category';
        $is_unisex_gender = ($UnisexDataValue=="u")?'Gender':'Category';

        $i = 0;
		if($ListFrom != 'deals'){
			$Filters[$i]['Category']['Attr'] = ['id' => 'categories', 'title' => 'Category'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'Men', 'value' => 'Men', 'data_value' => $MenDataValue , 'selected' => $MenSelected, 'is_gender' => $is_men_gender];
			$Filters[$i]['Category']['Data'][] = ['label' => 'Women', 'value' => 'Women', 'data_value' => $WomenDataValue, 'selected' => $WomenSelected, 'is_gender' => $is_women_gender];
			$Filters[$i]['Category']['Data'][] = ['label' => 'Unisex', 'value' => 'Unisex', 'data_value' => $UnisexDataValue, 'selected' => $UnisexSelected, 'is_gender' => $is_unisex_gender];
			if($NicheDataValue != '')
				$Filters[$i]['Category']['Data'][] = ['label' => 'Niche Fragrances', 'value' => 'Niche Fragrance', 'data_value' => $NicheDataValue, 'selected' => 'No'];
			//$Filters[$i]['Category']['Data'][] = ['label' => '8 ML Mini', 'value' => '8 ML Mini', 'data_value' => '8-ml-mini', 'is_eightml' => '8-ml-mini', 'selected' => 'No'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'Pocket Perfume', 'value' => 'Pocket Perfume', 'data_value' => 'f-pocket-perfume', 'is_eightml' => 'f-pocket-perfume', 'selected' => 'No'];
			if($GiftsetDataValue != '')
				$Filters[$i]['Category']['Data'][] = ['label' => 'Gift Sets', 'value' => 'Gift Sets', 'data_value' => $GiftsetDataValue, 'selected' => 'No','is_gender' => 'GiftSets'];
				//$Filters[$i]['Category']['Data'][] = ['label' => 'Gift Sets', 'value' => 'Gift Sets', 'data_value' => $GiftsetDataValue, 'selected' => 'No'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'Best Sellers', 'value' => 'Best Sellers', 'data_value' => 'ts', 'selected' => $BestSellerSelected, 'is_gender' => 'Special'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'MAX 2DAY', 'value' => 'Max2day', 'data_value' => 'max2day', 'selected' => $Max2daySelected, 'is_gender' => 'Max2day'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'New Arrivals', 'value' => 'NewArrivals', 'data_value' => 'na', 'selected' => $NewarrivalSelected, 'is_gender' => 'Special'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'Discovery Sets', 'value' => 'DiscoverySets', 'data_value' => 'discovery-sets', 'selected' => 'No', 'is_gender' => 'DiscoverySet'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'In Stock', 'value' => 'InStockItems', 'data_value' => 'InStockItems', 'selected' => $InStockItemsSelected, 'is_gender' => 'InStockItems'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'On Sale / Deal', 'value' => 'OnSaleDeal', 'data_value' => 'OnSaleDeal', 'selected' => $OnSaleDealSelected, 'is_gender' => 'OnSaleDeal'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'Top Rated', 'value' => 'OnTopRated', 'data_value' => 'OnTopRated', 'selected' => $OnTopRatedSelected, 'is_gender' => 'OnTopRated'];
			$Filters[$i]['Category']['Selected'] = [];
		}

        $i++;

		if($ListFrom == 'deals'){

			$Filters[$i]['Category']['Attr'] = ['id' => 'categories', 'title' => 'Category'];
			$Filters[$i]['Category']['Data'][] = ['label' => 'Top Rated', 'value' => 'OnTopRated', 'data_value' => 'OnTopRated', 'selected' => $OnTopRatedSelected, 'is_gender' => 'OnTopRated'];
			$Filters[$i]['Category']['Selected'] = [];

			$Filters[$i]['Deals']['Attr'] = ['id' => 'deals', 'title' => 'Deals', 'list_from' => $ListFrom];
			$Filters[$i]['Deals']['Data'] = [];
			$Filters[$i]['Deals']['Selected'] = [];
		}

        $Filters[$i]['Brand']['Attr'] = ['id' => 'brands', 'title' => 'Brands', 'list_from' => $ListFrom];
        //$Filters[$i]['Brand']['Data'] = BrandsList();
		if($ListFrom == 'deals'){
			$brandIdArr = array();
			//print_r($brandFilter['Data']);
			if(isset($brandFilter['Data'])){
				foreach($brandFilter['Data'] as $key => $val){
					array_push($brandIdArr,$key);
				}
			}

			$Filters[$i]['Brand']['Data'] = BrandsList('',$brandIdArr);	// $brandFilter['Data']; //BrandsList();
		} else {
			$Filters[$i]['Brand']['Data'] = BrandsList();
		}
        $Filters[$i]['Brand']['Selected'] = [];

		//$brand_list = BrandsList();
		//print_r($brand_list);

        $i++;

        /*$Filters[$i]['Size']['Attr'] = ['id' => 'size', 'title' => 'Size'];
        $Filters[$i]['Size']['Data'][] = ['label' => '<1 OZ','min' => 0, 'max' => 1, 'value' => '0-1'];
        $Filters[$i]['Size']['Data'][] = ['label' => '1-3.4 OZ','min' => 1, 'max' => 3.4, 'value' => '1-3.4'];
        $Filters[$i]['Size']['Data'][] = ['label' => '$100 - $200','min' => 100, 'max' => 200, 'value' => '100-200'];
        $Filters[$i]['Size']['Data'][] = ['label' => '$200 - $400','min' => 200, 'max' => 400, 'value' => '200-400'];
        $Filters[$i]['Size']['Data'][] = ['label' => '$200 - $400','min' => 200, 'max' => 400, 'value' => '200-400'];
        $Filters[$i]['Size']['Selected'] = [];*/

		$Filters[$i]['Size']['Attr'] = ['id' => 'size', 'title' => 'Size'];
		if($ListFrom == 'deals'){
			$Filters[$i]['Size']['Data'][] = ['label' => 'Mini','min' => 0, 'max' => 0, 'value' => 'mini'];
			$Filters[$i]['Size']['Data'][] = ['label' => 'Set','min' => 0, 'max' => 0, 'value' => 'set'];
		}
        $Filters[$i]['Size']['Data'][] = ['label' => '< 1 OZ','min' => 0, 'max' => 1, 'value' => '0-1'];
        $Filters[$i]['Size']['Data'][] = ['label' => '1 - 3.4 OZ','min' => 1, 'max' => 3.4, 'value' => '1-3.4'];
        $Filters[$i]['Size']['Data'][] = ['label' => '> 3.4 OZ','min' => 3.4, 'max' => 0, 'value' => '3.4'];
        $Filters[$i]['Size']['Selected'] = [];

        $i++;

        $Filters[$i]['Price']['Attr'] = ['id' => 'price', 'title' => 'Price'];
        $Filters[$i]['Price']['Data'][] = ['label' => 'Below $20','min' => 0, 'max' => 20, 'value' => '0-20'];
        $Filters[$i]['Price']['Data'][] = ['label' => '$20 - $100','min' => 20, 'max' => 100, 'value' => '20-100'];
		$Filters[$i]['Price']['Data'][] = ['label' => '$100 - $200','min' => 100, 'max' => 200, 'value' => '100-200'];
		$Filters[$i]['Price']['Data'][] = ['label' => '$200 - $400','min' => 200, 'max' => 400, 'value' => '200-400'];
        $Filters[$i]['Price']['Data'][] = ['label' => 'Over $400','min' => 400, 'max' => '', 'value' => '400'];
        $Filters[$i]['Price']['Selected'] = [];

		$i++;

		$Filters[$i]['FragranceType']['Attr'] = ['id' => 'fragrance_type', 'title' => 'Fragrance Family'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Floral','value' => 'floral'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Fruity','value' => 'fruity'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Amber','value' => 'amber'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Woody','value' => 'woody'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Musk','value' => 'musk'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Aromatic','value' => 'aromatic'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Citrus','value' => 'citrus'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Vanilla','value' => 'vanilla'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Spicy','value' => 'spicy'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Fresh','value' => 'fresh'];
		$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Warm','value' => 'warm'];
		$Filters[$i]['FragranceType']['Selected'] = [];

		$Filters[$i]['FragranceOccasion']['Attr'] = ['id' => 'fragrance_occasion', 'title' => 'Fragrance Occasion'];
		$Filters[$i]['FragranceOccasion']['Data'][] = ['label' => 'Day','value' => 'Day'];
		$Filters[$i]['FragranceOccasion']['Data'][] = ['label' => 'Engagment','value' => 'Engagment'];
		$Filters[$i]['FragranceOccasion']['Data'][] = ['label' => 'Everyday Use','value' => 'Everyday use'];
		$Filters[$i]['FragranceOccasion']['Data'][] = ['label' => 'Night','value' => 'Night'];
		$Filters[$i]['FragranceOccasion']['Data'][] = ['label' => 'Special Occasions','value' => 'Special Occasions'];
		$Filters[$i]['FragranceOccasion']['Data'][] = ['label' => 'Other','value' => 'Other'];
		$Filters[$i]['FragranceOccasion']['Selected'] = [];

		$Filters[$i]['FragranceSeason']['Attr'] = ['id' => 'fragrance_season', 'title' => 'Fragrance Season'];
		$Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring','value' => 'Spring'];
		$Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Summer','value' => 'Summer'];
		$Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Fall','value' => 'Fall'];
		$Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Winter','value' => 'Winter'];
		$Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Year Around','value' => 'Year Around'];
		$Filters[$i]['FragranceSeason']['Selected'] = [];

        if($ListFrom != 'deals'){
			//$Filters[$i]['FragranceType']['Attr'] = ['id' => 'fragrance_type', 'title' => 'Fragrance Type'];

			$Filters[$i]['ReviewRating']['Attr'] = ['id' => 'review_rating', 'title' => 'Star Rating'];
			$Filters[$i]['ReviewRating']['Data'][] = ['label' => '5 Star','value' => '5', 'imgnm'=>"star-5.png"];
			$Filters[$i]['ReviewRating']['Data'][] = ['label' => '4 Star','value' => '4', 'imgnm'=>"star-4.png"];
			$Filters[$i]['ReviewRating']['Data'][] = ['label' => '3 Star','value' => '3', 'imgnm'=>"star-3.png"];
			$Filters[$i]['ReviewRating']['Selected'] = [];

			// $Filters[$i]['FragranceSeason']['Attr'] = ['id' => 'fragrance_season', 'title' => 'Fragrance Season'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Fall','value' => 'Fall'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'fall, summer, spring','value' => 'Fall, Summer, Spring'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Fall-Spring','value' => 'Fall-Spring'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Fall-Spring-Winter-Summer','value' => 'Fall-Spring-Winter-Summer'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Fall-Winter','value' => 'Fall-Winter'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Fall-Winter-Spring','value' => 'Fall-Winter-Spring'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Fall-Winter-Spring-Summer','value' => 'Fall-Winter-Spring-Summer'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Fall-Winter-Summer-Spring','value' => 'Fall-Winter-Summer-Spring'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring','value' => 'Spring'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring, Fall, Winter','value' => 'Spring, Fall, Winter'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring, Summer, Winter','value' => 'Spring, Summer, Winter'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring-Fall-Summer-Winter','value' => 'Spring-Fall-Summer-Winter'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring-Fall-Winter-Summer','value' => 'Spring-Fall-Winter-Summer'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring-Summer','value' => 'Spring-Summer'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring-Summer-Fall','value' => 'Spring-Summer-Fall'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Spring-Summer-Fall-Winter','value' => 'Spring-Summer-Fall-Winter'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Summer','value' => 'Summer'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Summer-Fall','value' => 'Summer-Fall'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Summer-Fall-Winter-Spring','value' => 'Summer-Fall-Winter-Spring'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Summer-Spring','value' => 'Summer-Spring'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Summer-Spring-Fall','value' => 'Summer-Spring-Fall'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Winter','value' => 'Winter'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'winter, summer, fall','value' => 'winter, summer, fall'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Winter-Fall','value' => 'Winter-Fall'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Winter-Fall-Spring-Summer','value' => 'Winter-Fall-Spring-Summer'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Winter-Spring','value' => 'Winter-Spring'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Winter-Spring-Fall','value' => 'Winter-Spring-Fall'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'winter-Summer','value' => 'winter-Summer'];
			// $Filters[$i]['FragranceSeason']['Data'][] = ['label' => 'Other','value' => 'Other'];
			// $Filters[$i]['FragranceSeason']['Selected'] = [];
		}
		/*print_r($Filters);
		exit;*/

        /*$Filters[$i]['FragranceType']['Data'][] = ['label' => 'Floral', 'name' => 'Floral', 'value' => 'floral'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Fruity', 'value' => 'fruity'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Amber', 'value' => 'amber'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Woody', 'value' => 'woody'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Musk', 'value' => 'musk'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Aromatic', 'value' => 'aromatic'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Citrus', 'value' => 'citrus'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Vanilla', 'value' => 'vanilla'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Spicy', 'value' => 'spicy'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Fresh', 'value' => 'fresh'];
		$Filters[$i]['FragranceType']['Data'][] = ['name' => 'Warm', 'value' => 'warm'];
        $Filters[$i]['FragranceType']['Selected'] = [];*/

        /*$i++;

        $Filters[$i]['FragranceFamily']['Attr'] = ['id' => 'price', 'title' => 'Price'];
        $Filters[$i]['FragranceFamily']['Data'][] = ['label' => 'Below $20','min' => 0, 'max' => 20, 'value' => '0-20'];
        $Filters[$i]['FragranceFamily']['Data'][] = ['label' => '$20 - $100','min' => 20, 'max' => 100, 'value' => '20-100'];
        $Filters[$i]['FragranceFamily']['Data'][] = ['label' => 'Over $400','min' => 400, 'max' => '', 'value' => '400'];
        $Filters[$i]['FragranceFamily']['Selected'] = [];
        */
        //dd($Filters);
        /*
        $Filters = [
            [
            'Category' =>[
                'Attr' => ['id' => 'categories', 'title' => 'Category'],
                'Data' => [
                    ['label' => 'Men', 'value' => 'Men', 'data_value' => $MenDataValue , 'selected' => $MenSelected, 'is_gender' => $is_men_gender],
                    ['label' => 'Women', 'value' => 'Women', 'data_value' => $WomenDataValue, 'selected' => $WomenSelected, 'is_gender' => $is_women_gender],
                    ['label' => 'Unisex', 'value' => 'Unisex', 'data_value' => $UnisexDataValue, 'selected' => $UnisexSelected, 'is_gender' => $is_unisex_gender],
                    ['label' => 'Niche Fragrances', 'value' => 'Niche Fragrance', 'data_value' => $NicheDataValue, 'selected' => 'No'],
                    ['label' => '8 ML Mini', 'value' => '8 ML Mini', 'data_value' => '8-ml-mini', 'selected' => 'No'],
                    ['label' => 'Gift Sets', 'value' => 'Gift Sets', 'data_value' => $GiftsetDataValue, 'selected' => 'No'],
                    ['label' => 'Best Sellers', 'value' => 'Best Sellers', 'data_value' => 'ts', 'selected' => $BestSellerSelected, 'is_gender' => 'Special'],
                    ['label' => 'MAX 2DAY', 'value' => 'Max2day', 'data_value' => 'max2day', 'selected' => $Max2daySelected, 'is_gender' => 'Max2day']
                ],
                'Selected' => []
            ]],
            ['Brand' => [
                'Attr' => ['id' => 'brands', 'title' => 'Brands'],
                'Data' => BrandsList(),
                'Selected' => []
            ]],
            ['Size'  => [
                'Attr' => ['id' => 'size', 'title' => 'Size'],
                'Data' => [
                    ['label' => '<1 OZ','min' => 0, 'max' => 1, 'value' => '0-1'],
                    ['label' => '1-3.4 OZ','min' => 1, 'max' => 3.4, 'value' => '1-3.4'],
                    ['label' => '>3.4 OZ','min' => 3.4, 'max' => '', 'value' => '3.4']
                ],
                'Selected' => []
            ]],
            ['Price' => [
                'Attr' => ['id' => 'price', 'title' => 'Price'],
                'Data' => [
                    ['label' => 'Below $20','min' => 0, 'max' => 20, 'value' => '0-20'],
                    ['label' => '$20 - $100','min' => 20, 'max' => 100, 'value' => '20-100'],
                    ['label' => '$100 - $200','min' => 100, 'max' => 200, 'value' => '100-200'],
                    ['label' => '$200 - $400','min' => 200, 'max' => 400, 'value' => '200-400'],
                    ['label' => 'Over $400','min' => 400, 'max' => '', 'value' => '400'],
                ],
                'Selected' => []
            ]],
            ['FragranceFamily' => [
                'Attr' => ['id' => 'fragrance_family', 'title' => 'Fragrance Family'],
                'Data' => [
                    ['label' => 'Floral', 'value' => 'Floral'],
                    ['label' => 'Fruity', 'value' => 'Fruity'],
                    ['label' => 'Amber', 'value' => 'Amber'],
                    ['label' => 'Woody', 'value' => 'Woody'],
                    ['label' => 'Musk', 'value' => 'Musk'],
                    ['label' => 'Aromatic', 'value' => 'Aromatic'],
                    ['label' => 'Vanilla', 'value' => 'Vanilla'],
                    ['label' => 'Spicy', 'value' => 'Spicy'],
                    ['label' => 'Fresh', 'value' => 'Fresh'],
                    ['label' => 'Warm', 'value' => 'Warm'],
                ],
                'Selected' => []
            ]]
        ];*/
        return $Filters;
    }

	public function SetFilters($Params)
	{
		//$ExpFilters = explode("/",$Params->filters);
		$ExpFilters = is_string($Params->filters) ? explode("/", $Params->filters) : [];
		if(isset($Params->mid) && $Params->mid != '')
			$ExpFilters[]='mid-'.$Params->mid;

		$AllFilters = [];
		$ParamString = ['cid' => 'categories', 'mid' => 'brands','family' => 'fragrance_family', 'type' => 'vtype',
				'formulation' => 'formulation', 'stock' => 'stock', 'size' => 'size',
				'special' => 'special', 'coverage' => 'coverage', 'finish' => 'finish',
				'skin' => 'skin_type', 'features' => 'features'];
		foreach($ExpFilters as $AllParam)
		{
			$ExpParam = explode("-",$AllParam);
			if(count($ExpParam)>0 && array_key_exists($ExpParam[0],$ParamString))
			{
				$Key = $ParamString[$ExpParam[0]];
                if(isset($ExpParam[1]))
				    $AllFilters[$Key] = explode(',',$ExpParam[1]);
			} else if(count($ExpParam)>0 && $ExpParam[0] == 'key'){
				$AllFilters['key'] = $ExpParam[1];
			} else if(count($ExpParam)>0 && $ExpParam[0] == 'price'){
				$AllFilters['minprice'] = $ExpParam[1];
				$AllFilters['maxprice'] = $ExpParam[2];
			}
		}
		return $AllFilters;
	}

	public function GetProducts($Flag,$CategoryID,$limit=12,$Filters=[])
	{

		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		if(count($Filters) > 0){
			foreach($Filters as $fkey => $Filter)
			{
				if($fkey == 'categories' && count($Filters) > 0){
					$ChildCatArr = $Filters['categories'];
				}
			}
		}
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			//$ChildCats = $this->GetChildCategories($CategoryID);
			$ChildCats = GetMainCatsTree([$CategoryID]);
			if(count($ChildCats['CatList']) > 0)
				$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
			else
				$ChildCatArr = [$CategoryID];
		}

		if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
		}

		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';

		$CatProdsQry = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_products_one as p1', 'po.products_id', '=', 'p1.products_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->select('po.products_id',DB::raw('COUNT(po.variation_id) as variarioncnt'),'po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
								'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','m.is_popular','pc.category_id','c.parent_id','p1.extra_images')
					/*
                    ->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')])
                    */
					->where('po.status','=','1')
					->where('c.status','=','1');

					//->where('po.sku','=','11111EMPTY');

        if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('po.product_type',['both','retailer']);

		if(count($ChildCatArr) > 0)
			$CatProdsQry->whereIn('pc.category_id',$ChildCatArr);

			//$CatProdsQry->groupBy(['po.brand_id','po.gender','po.imanufactureid']);
		$CatProdsQry->groupBy(['po.variation_id']);

		$FilterStock = '';
		$FilterMinPrice = '';
		$FilterMaxPrice = '';
		$FilterKey = '';
		$BrandInSearch = 0;

		foreach($Filters as $fkey => $Filter)
		{
			if(is_array($Filter) && count($Filter) > 0)
			{
				if($fkey == 'categories'){
					$CatProdsQry->whereIn('pc.category_id',$Filter);
				}else if($fkey == 'brands'){
					$CatProdsQry->whereIn('po.imanufactureid',$Filter);
					$BrandInSearch=1;
				}else if($fkey == 'features'){
					$CatProdsQry->whereIn('po.refine_feature',$Filter);
				}else if($fkey == 'special'){
					foreach($Filter as $Special)
					{
						if($Special == 'top_seller' || $Special == 'ts')
						{
							$Flag = "TOP_SELLERS";
							/*if(!$CategoryID)
								$Flag = "TOP_SELLERS";
							else
								$CatProdsQry->where('po.top_seller','=','Yes');
							*/
						}
						if($Special == 'new_arrival' || $Special == 'na')
						{
							$Flag = "NEW_ARRIVALS";
							/*if(!$CategoryID)
								$CatProdsQry->where('po.new_arrival','=','Yes');
							else
								$Flag = "NEW_ARRIVALS";
							*/
						}
						if($Special == 'featured' || $Special == 'fe')
							$CatProdsQry->where('po.featured','=','Yes');
						if($Special == 'clearance' || $Special == 'cl')
							$CatProdsQry->where('po.clearance','=','Yes');
						if($Special == 'celebrity' || $Special == 'cp')
							$CatProdsQry->where('po.celebrity','=','Yes');
						if($Special == 'sale_price' || $Special == 'sl')
							$CatProdsQry->where('po.sale_price','>',0);
					}
				} else if($fkey == 'stock'){
					$FilterStock = $Filter[0];
				} else if($fkey == 'ProductSKUs'){
					$CatProdsQry->whereIn('po.sku',$Filter);
				} else if($fkey == 'NotProductSKUs'){
					$CatProdsQry->whereNotIn('po.sku',$Filter);
				} else {
					$CatProdsQry->whereIn('po.'.$fkey,$Filter);
				}
			} else if($fkey == 'stock'){
				$FilterStock = $Filter;
			}else if($fkey == 'minprice'){
				$FilterMinPrice = $Filter;
			}else if($fkey == 'maxprice'){
				$FilterMaxPrice = $Filter;
			}else if($fkey == 'key'){
				$FilterKey = $Filter;
			}
		}

		if($Flag == "TOP_SELLERS")
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere('po.is_sold_quantity','>',0);
			});
		}
		if($Flag == "NEW_ARRIVALS")
		{
			$CatProdsQry->whereNotIn('pc.category_id',['198','199','200','201']);

			/*$CatProdsQry->where(function($query){
				$query->where('po.new_arrival','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});*/

				$CatProdsQry->where(function($query){
				$from = Carbon::now()->subDays(30);
				$to = Carbon::now();
				$query->where('po.new_arrival','=','Yes');
				$query->orwhereBetween(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),[$from,$to]);
			});

		}

		if($Flag == 'CategoryPage')
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else if($Flag == 'DealofweekPage'){
			$CatProdsQry->join('pu_dealofweek as dw','dw.product_sku','=','po.sku');
			$CatProdsQry->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did');
			$CatProdsQry->where('dw.deal_type','=','Weekly');
			$CatProdsQry->where('dw.status','=','1');
			$CatProdsQry->where('dw.start_date','<=',date('Y-m-d H:i'))->where('dw.end_date','>=',date('Y-m-d H:i'));
			if($FilterKey != '')
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			$CatProdsQry->orderBy('dwt.deal_rank');
			$CatProdsQry->orderBy('dw.end_date');
			$CatProdsQry->orderBy('dw.display_rank');
		}else if($Flag == 'Promotional'){
			if($FilterKey != ''){
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			}
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		else if($Flag == 'Maxtwoday'){
			$CatProdsQry->where('po.maxtwodaydelivery','=','Yes');
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		else if($Flag == 'ProductListPage' || $Flag == 'BrandPage'){
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('b.brand_name');
			$CatProdsQry->orderBy('po.cosmo_sku');
			$CatProdsQry->orderBy('po.nandansons_sku');
			$CatProdsQry->orderBy('po.pca_sku');
			$CatProdsQry->orderBy('po.perfumeworldwide_sku');
			$CatProdsQry->orderBy('po.nd_sku');
			$CatProdsQry->orderBy('po.display_position');
		} else if($Flag == 'ShoppingCart'){
		//	$CatProdsQry->join('pu_products_viewed as pv','po.sku','=','pv.sku');
		//	$CatProdsQry->where('pv.customer_ip','!=',$_SERVER['REMOTE_ADDR']);
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		} else if($Flag == "TOP_SELLERS"){
			$CatProdsQry->orderBy('po.is_sold_quantity','desc');
		} else if($Flag == "NEW_ARRIVALS"){
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else{
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		$CatProdsWithoutLimit = $CatProdsQry->get();

		//echo "<pre>"; print_r($CatProdsWithoutLimit); exit;
		//$CatProdsWithLimit = $CatProdsQry->offset($Offset)->limit($limit)->get();
		$ArrayFilters = ['sortby' => $SortBy, 'offset' => $Offset, 'limit' => $limit];
		$SKUs = '' ;
		$CatProducts = [];
		$TotalProds = 0;
		$VariationIDs=[];
		$ProdIds=[];
		$DealData = GetDealOfWeek('',"Weekly");
		$CatProdsQry->offset($Offset)->chunk(1000, function($MyCatProdsWithoutLimit)use(&$CatProducts,&$VariationIDs,&$ProdIds,&$TotalProds,$CategoryID,$DealData,$FilterStock,$FilterMinPrice,$FilterMaxPrice,$BrandInSearch)
		{
			//$SliderCategory = $this->GetCategories($CatProdsWithoutLimit);
			foreach($MyCatProdsWithoutLimit as $key => $CatProd)
			{
				$CatProd = $this->SetProduct($CatProd);

				if(is_array($FilterStock) && count($FilterStock) > 0 && $CatProd->stock == 'Out')
					continue;

				if($FilterMaxPrice !='')
				{
					$FilterMaxPrice = (float)$FilterMaxPrice;
					$FilterMinPrice = (float)$FilterMinPrice;
					if((float)$CatProd->product_price < $FilterMinPrice || (float)$CatProd->product_price > $FilterMaxPrice )
						continue;
				}

				if($CatProd->product_price <= 0)
					continue;

				$TotalProds++;
				/*$SKUs.= $CatProd->sku."#";
				$TotalProds++;*/
				$VariationIDs[]=$CatProd->variation_id;

                if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
					$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($CatProd->image);
					$verP = filemtime($newimageVal);
					$CatProd->prod_image  = config('global.PRD_LARGE_IMG_URL') . $CatProd->image . "?ver=" . $verP;
				} else {
					$CatProd->prod_image = config('global.NO_IMAGE_LARGE');
				}
                /*
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
					$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProd->image);
					$verP = filemtime($newimageVal);
					$CatProd->prod_image  = config('global.PRD_THUMB_IMG_URL') . $CatProd->image . "?ver=" . $verP;
				} else {
					$CatProd->prod_image = config('global.NO_IMAGE_THUMB');
				}*/

				/*if($CatProd->is_atomizer == "Yes" || $CatProd->stock == "Out")
				{
					$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($CatProd->products_id,$CatProd->variation_id,$CategoryID,[],$CatProdsWithoutLimit);
					if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != ''){
						$Product = $SizeCountArr[0];
					}else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In'){
						$Product = $SizeCountArr[0];
					}else{
						//$CatProd->size_cnt = $SizeCountArr;
					}
				} else {
					//$CatProd->size_cnt = $this->getReferencedProducts_CounterDev($CatProd->products_id,$CatProd->variation_id,$CatProdsWithoutLimit);
				}*/

				$CatProd->BrandInSearch = $BrandInSearch;

				$PriceRange = $this->setPriceRange($CatProd->variation_id,$MyCatProdsWithoutLimit);

				$CatProd->minPrice = $PriceRange['MinPrice'];
				$CatProd->maxPrice = $PriceRange['MaxPrice'];
				$CatProd->yousave = $PriceRange['YouSave'];

				$CatProd->ProdPrice = $CatProd->product_price;
				if($CatProd->minPrice > 0 && $CatProd->maxPrice > 0 &&  $CatProd->maxPrice!=$CatProd->minPrice)
				{
					$CatProd->ProdPrice = $CatProd->maxPrice;
					if($SortBy=="priceLH")
					{
					$CatProd->ProdPrice = $CatProd->minPrice;
					}

				}

				if($CategoryID == '2')
					$CatProd->category_id = $CategoryID;

				if($CatProd->parent_id != 0)
					$ProdCat = $CatProd->parent_id;
				else
					$ProdCat = $CatProd->category_id;
				/*
				$ProdCatDetails = $SliderCategory[$ProdCat];
				$category_url = remove_special_chars($ProdCatDetails->category_name).'/';
				$product_name = remove_special_chars($CatProd->product_name);
				$CatProd->product_url = config('global.SITE_URL').$ProdURL.$product_name."/pid/".$CatProd->products_id."/".$ProdCat;
				*/

				$CatProd->product_url = SetProductURL($CatProd->products_id,$CatProd->product_name,$CatProd->category_id);

				if ($CatProd->gender == 'M'){
					$CatProd->gender = "sv-men";
					$CatProd->gendernames = "Men";
					$for_gender = ' for Men';
				} elseif ($CatProd->gender == 'W'){
					$CatProd->gender = "sv-women";
					$CatProd->gendernames = "Women";
					$for_gender = ' for Women';
				} elseif ($CatProd->gender == 'K'){
					$CatProd->gender = "sv-kids";
					$CatProd->gendernames = "Kids";
					$for_gender = ' for Kids';
				} elseif ($CatProd->gender == 'U'){
					$CatProd->gender = "sv-unisex";
					$CatProd->gendernames = "Unisex";
					$for_gender = ' Unisex';
				} else{
					$CatProd->gender = "";
					$CatProd->gendernames = "";
					$for_gender = '';
				}

				if($CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$m_name = str_replace("'", "", $m_name);
					$CatProd->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$CatProd->imanufactureid;
				}

				if($CatProd->brand_name != '' && $CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$m_name = str_replace("'", "", $m_name);
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><strong>' . $CatProd->brand_name . '</strong></a> by <a href='.$CatProd->vmanufacture_link.'><strong><br>'.$CatProd->vmanufacture.'</strong></a>'.$for_gender;
				}

				if(strlen($CatProd->product_name) > 45){
					$CatProd->product_name = substr($CatProd->product_name, 0, (45 - strlen($CatProd->product_name))). "..";
				} else {
					$CatProd->product_name = $CatProd->product_name;
				}

				$CatProd->product_name = strip_tags($CatProd->product_name);

				if($CatProd->vmanufacture == '' || $CatProd->brand_name == ''){
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '">' . $CatProd->product_name . '</a>';
				}

				if($CatProd->retail_price != '' && $CatProd->retail_price != '0.00' && isset($CatProd->product_price)){
					$yousave = ($CatProd->retail_price - $CatProd->product_price) / $CatProd->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$yousaveprice = $CatProd->retail_price - $CatProd->product_price;
				}else{
					$yousave = 0;
					$yousaveprice = 0;
				}

				$CatProd->yousave = $yousave;
				$CatProd->maxyousave = (($CatProd->yousave>0)?number_format($CatProd->yousave, 0):0);
				$CatProd->yousaveprice = $yousaveprice;
				$CatProd->autoid = $key;

				$CatProd->sale_item = '0';
				if($CatProd->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
				{
					$CatProd->sale_item = '1';
				}

				if(isset($DealData[$CatProd->sku]) && isset($CatProd->WebsiteStock) && $CatProd->WebsiteStock=="In")
				{
					//echo "<pre>"; print_r($DealData); exit;
					$CatProd->deal_price = $DealData[$CatProd->sku]['deal_price'];
					$CatProd->yousave = $DealData[$CatProd->sku]['yousave'];
					$CatProd->yousaveprice = $DealData[$CatProd->sku]['yousaveprice'];
				}

				if(isset($Flag) && $Flag=="DealofweekPage" && $CatProd->WebsiteStock=="Out")
				{
					$CatProd->deal_price = $CatProd->ProdPrice;

				}

				$CatProd->short_description = remove_html_entities(strip_tags($CatProd->short_description));
				$CatProd->size_cnt = $CatProd->variarioncnt;
				/*
                $CatProd->avg_rate = 0;
				$total_review = $CatProd->TotalReview;
				if($total_review > 0)
					$CatProd->avg_rate = GetProductAverageRating($CatProd->TotalReview,$CatProd->TotalRate);
				*/
				$ProdIds[]=$CatProd->products_id;

				$CatProducts[] = $CatProd;

			}
			//$Products = $this->GetSliderProducts($SKUs,'','Category',$CategoryID,$ArrayFilters);
		});
		$TotalProducts = $TotalProds;
		if($Flag=="BrandPage")
		{
			$AllFilters = array();
		}
		else
		{
			$AllFilters = $this->GetFilters($CatProducts,$Filters,$Flag);
		}
		if(count($CatProducts)>0 && isset($ArrayFilters['sortby']) && $ArrayFilters['sortby'] != '')
		{
			if($ArrayFilters['sortby'] == 'priceHL'){
				usort($CatProducts,function($first,$second){
					return $first->ProdPrice < $second->ProdPrice ? 1 : -1;
				});
			}
			if($ArrayFilters['sortby'] == 'priceLH'){
				usort($CatProducts,function($first,$second){
					return $first->ProdPrice > $second->ProdPrice ? 1 : -1;
				});
			}
			if($ArrayFilters['sortby'] == 'priceAZ'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($first->brand_name),strtolower($second->brand_name));
				});
			}
			if($ArrayFilters['sortby'] == 'priceZA'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($second->brand_name),strtolower($first->brand_name));
				});
			}
		} else {
			//usort($CatProducts,function($first,$second){
				//return $first->WebsiteStock > $second->WebsiteStock ? 1 : -1;
			//});
		}

		if(count($CatProducts)>0 && isset($ArrayFilters['limit']) && $ArrayFilters['limit'] != '')
		{
			if(count($CatProducts) > $ArrayFilters['offset'])
				$CatProducts = array_slice($CatProducts,$ArrayFilters['offset'],$ArrayFilters['limit']);
		}

		$CatProducts = $this->CountOptions($VariationIDs,$CatProducts,$ChildCatArr,$Flag);

		$ProductsDetails = ['Products' => $CatProducts,'TotalProducts' => $TotalProducts, 'LeftFilters' => $AllFilters];

		return $ProductsDetails;
	}

	public function GetProductsForMaxaromaTemplate($brand_id,$skus,$pageFrom='')
	{
		$CatProdsQry = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_products_one as p1', 'po.products_id', '=', 'p1.products_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->select('po.products_id','po.sku','po.short_description','po.product_name','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender',
								'po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.vtype','po.variation_id','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.parent_id','po.fragrance_family','p1.extra_images')
					->where('po.status','=','1')
					->where('c.status','=','1')
					->where('po.imanufactureid',$brand_id);

		if(count($skus) > 0)
		{
			$CatProdsQry->whereIn('po.sku',$skus);
		}

        if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('po.product_type',['both','retailer']);

		$MaxQry = $CatProdsQry;

		if($pageFrom == 'PDP')
		{
			$CatProdsQry->join('pu_products_attribute as pa','po.products_id','=','pa.products_id');
			$CatProdsQry->where('pa.template','Maxaroma-Edit');
			$CatProdsQry->where('po.current_stock','>',0);
		}

		$CatProdsWithoutLimit = $CatProdsQry->get();

		if($pageFrom == 'PDP')
		{
			if(empty($CatProdsWithoutLimit))
			{
				$CatProdsWithoutLimit = $MaxQry->get();
			}
		}

		$CatProducts = [];
		$TotalProds = 0;
		$VariationIDs=[];
		$DealData = GetDealOfWeek('',"Weekly");

		foreach($CatProdsWithoutLimit as $key => $CatProd)
		{
			$CatProd = $this->SetProduct($CatProd);

			// if($CatProd->stock == 'Out' || $CatProd->product_price <= 0)
			// 	continue;

			$TotalProds++;
			$VariationIDs[]=$CatProd->variation_id;

			if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
				$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($CatProd->image);
				$verP = filemtime($newimageVal);
				$CatProd->prod_image  = config('global.PRD_LARGE_IMG_URL') . $CatProd->image . "?ver=" . $verP;
			} else {
				$CatProd->prod_image = config('global.NO_IMAGE_LARGE');
			}

			$ExtraImages = !empty($CatProd->extra_images)? explode('#', $CatProd->extra_images): [];
			$CatProd->second_image = "";
			if(count($ExtraImages) > 0)
			{
				if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $ExtraImages[0]) && trim($ExtraImages[0]) != '')
				{
					$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($ExtraImages[0]);
					$verP = filemtime($newimageVal);
					$CatProd->second_image  = config('global.PRD_LARGE_IMG_URL') . $ExtraImages[0] . "?ver=" . $verP;
				}
			}

			// $PriceRange = $this->setPriceRange($CatProd->variation_id,$CatProdsWithoutLimit);

			// $CatProd->minPrice = $PriceRange['MinPrice'];
			// $CatProd->maxPrice = $PriceRange['MaxPrice'];
			// $CatProd->yousave = $PriceRange['YouSave'];
			$CatProd->minPrice = $CatProd->product_price;
			$CatProd->maxPrice = $CatProd->product_price;

			$CatProd->ProdPrice = $CatProd->product_price;
			// if($CatProd->minPrice > 0 && $CatProd->maxPrice > 0 &&  $CatProd->maxPrice!=$CatProd->minPrice)
			// {
			// 	$CatProd->ProdPrice = $CatProd->maxPrice;
			// }

			if($CatProd->parent_id != 0)
				$ProdCat = $CatProd->parent_id;
			else
				$ProdCat = $CatProd->category_id;

			$CatProd->product_url = SetProductURL($CatProd->products_id,$CatProd->product_name,$CatProd->category_id);

			if($CatProd->vmanufacture != ''){
				$m_name = strtolower($CatProd->vmanufacture);
				$m_name = str_replace("#", "", $m_name);
				$m_name = str_replace("&", "", $m_name);
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace(" ", "-", $m_name);
				$m_name = str_replace("'", "", $m_name);
				$CatProd->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$CatProd->imanufactureid;
			}

			if(strlen($CatProd->product_name) > 45){
				$CatProd->product_name = substr($CatProd->product_name, 0, (45 - strlen($CatProd->product_name))). "..";
			}

			$CatProd->product_name = remove_html_entities(strip_tags($CatProd->product_name));

			if($CatProd->retail_price != '' && $CatProd->retail_price != '0.00' && isset($CatProd->product_price)){
				$yousave = ($CatProd->retail_price - $CatProd->product_price) / $CatProd->retail_price;
				$yousave = $yousave * 100;
				$yousave = number_format($yousave, 0);
				$yousaveprice = $CatProd->retail_price - $CatProd->product_price;
			}else{
				$yousave = 0;
				$yousaveprice = 0;
			}

			$CatProd->yousave = $yousave;
			$CatProd->maxyousave = (($CatProd->yousave>0)?number_format($CatProd->yousave, 0):0);
			$CatProd->yousaveprice = $yousaveprice;
			$CatProd->autoid = $key;

			$CatProd->sale_item = '0';
			if($CatProd->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
			{
				$CatProd->sale_item = '1';
			}

			if(isset($DealData[$CatProd->sku]))
			{
				$CatProd->deal_price = $DealData[$CatProd->sku]['deal_price'];
				$CatProd->yousave = $DealData[$CatProd->sku]['yousave'];
				$CatProd->yousaveprice = $DealData[$CatProd->sku]['yousaveprice'];
			}
			$CatProd->short_description = remove_html_entities(strip_tags($CatProd->short_description));
			$CatProd->size_cnt = 1;
			$CatProducts[] = $CatProd;
		}

		//$CatProducts = $this->CountOptionsForMaxaromaTemplate($VariationIDs,$CatProducts);
		//$CatProducts = $this->CountOptions($VariationIDs,$CatProducts,[],'','','','Yes');

		// $NewProducts = [];
		// if(count($CatProducts)>0)
		// {
		// 	foreach($skus as $selsku)
		// 	{
		// 		foreach($CatProducts as $CatProd)
		// 		{
		// 			if($selsku == $CatProd->sku)
		// 			{
		// 				$NewProducts[] = $CatProd;
		// 			}
		// 		}
		// 	}
		// }
		$ProductsDetails = ['Products' => $CatProducts];
		return $ProductsDetails;
	}

    public function GetProductsForMainCategory($Flag,$CategoryID,$limit=12,$Filters=[])
	{
		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		if(count($Filters) > 0){
			foreach($Filters as $fkey => $Filter)
			{
				if($fkey == 'categories' && count($Filters) > 0){
					$ChildCatArr = $Filters['categories'];
				}
			}
		}
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			//$ChildCats = $this->GetChildCategories($CategoryID);
			$ChildCats = GetMainCatsTree([$CategoryID]);
			if(count($ChildCats['CatList']) > 0)
				$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
			else
				$ChildCatArr = [$CategoryID];
		}

		if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
		}

		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';

		$CatProdsQry = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->select('po.products_id',DB::raw('COUNT(po.variation_id) as variarioncnt'),'po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
								'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','m.is_popular','pc.category_id','c.parent_id')
					/*
                    ->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')])
					*/
                    ->where('po.status','=','1')
					->where('c.status','=','1');

					//->where('po.sku','=','11111EMPTY');

        if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('po.product_type',['both','retailer']);

		if(count($ChildCatArr) > 0)
			$CatProdsQry->whereIn('pc.category_id',$ChildCatArr);

		$CatProdsQry->groupBy(['po.variation_id']);

		$FilterStock = '';
		$FilterMinPrice = '';
		$FilterMaxPrice = '';
		$FilterKey = '';
		$BrandInSearch = 0;

		foreach($Filters as $fkey => $Filter)
		{
			if(is_array($Filter) && count($Filter) > 0)
			{
				if($fkey == 'categories'){
					$CatProdsQry->whereIn('pc.category_id',$Filter);
				}else if($fkey == 'brands'){
					$CatProdsQry->whereIn('po.imanufactureid',$Filter);
					$BrandInSearch=1;
				}else if($fkey == 'features'){
					$CatProdsQry->whereIn('po.refine_feature',$Filter);
				}else if($fkey == 'special'){
					foreach($Filter as $Special)
					{
						if($Special == 'top_seller' || $Special == 'ts')
						{
							$Flag = "TOP_SELLERS";
						}
						if($Special == 'new_arrival' || $Special == 'na')
						{
							$Flag = "NEW_ARRIVALS";
						}
						if($Special == 'featured' || $Special == 'fe')
							$CatProdsQry->where('po.featured','=','Yes');
						if($Special == 'clearance' || $Special == 'cl')
							$CatProdsQry->where('po.clearance','=','Yes');
						if($Special == 'celebrity' || $Special == 'cp')
							$CatProdsQry->where('po.celebrity','=','Yes');
						if($Special == 'sale_price' || $Special == 'sl')
							$CatProdsQry->where('po.sale_price','>',0);
					}
				} else if($fkey == 'stock'){
					$FilterStock = $Filter[0];
				} else if($fkey == 'ProductSKUs'){
					$CatProdsQry->whereIn('po.sku',$Filter);
				} else if($fkey == 'NotProductSKUs'){
					$CatProdsQry->whereNotIn('po.sku',$Filter);
				} else {
					if($fkey != ''){
						$CatProdsQry->whereIn('po.'.$fkey,$Filter);
					}
				}
			} else if($fkey == 'stock'){
				$FilterStock = $Filter;
			}else if($fkey == 'minprice'){
				$FilterMinPrice = $Filter;
			}else if($fkey == 'maxprice'){
				$FilterMaxPrice = $Filter;
			}else if($fkey == 'key'){
				$FilterKey = $Filter;
			}
		}

		if($Flag == "TOP_SELLERS")
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere('po.is_sold_quantity','>',0);
			});
		}
		if($Flag == "NEW_ARRIVALS")
		{
			$CatProdsQry->whereNotIn('pc.category_id',['198','199','200','201']);

			/*$CatProdsQry->where(function($query){
				$query->where('po.new_arrival','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});*/

			$CatProdsQry->where(function($query){
				$from = Carbon::now()->subDays(30);
				$to = Carbon::now();
				$query->where('po.new_arrival','=','Yes');
				$query->orwhereBetween(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),[$from,$to]);
			});

		}

		if($Flag == 'CategoryPage')
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else if($Flag == 'DealofweekPage'){
			$CatProdsQry->join('pu_dealofweek as dw','dw.product_sku','=','po.sku');
			$CatProdsQry->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did');
			$CatProdsQry->where('dw.deal_type','=','Weekly');
			$CatProdsQry->where('dw.status','=','1');
			$CatProdsQry->where('dw.start_date','<=',date('Y-m-d H:i'))->where('dw.end_date','>=',date('Y-m-d H:i'));
			if($FilterKey != '')
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			$CatProdsQry->orderBy('dwt.deal_rank');
			$CatProdsQry->orderBy('dw.end_date');
			$CatProdsQry->orderBy('dw.display_rank');
		}else if($Flag == 'Maxtwoday'){
			$CatProdsQry->where('po.maxtwodaydelivery','=','Yes');
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		else if($Flag == 'Promotional'){
			if($FilterKey != ''){
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			}
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}else if($Flag == 'ProductListPage' || $Flag == 'BrandPage'){
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('b.brand_name');
			$CatProdsQry->orderBy('po.cosmo_sku');
			$CatProdsQry->orderBy('po.nandansons_sku');
			$CatProdsQry->orderBy('po.pca_sku');
			$CatProdsQry->orderBy('po.perfumeworldwide_sku');
			$CatProdsQry->orderBy('po.nd_sku');
			$CatProdsQry->orderBy('po.display_position');
		} else if($Flag == 'ShoppingCart'){
			//$CatProdsQry->join('pu_products_viewed as pv','po.sku','=','pv.sku');
			//$CatProdsQry->where('pv.customer_ip','!=',$_SERVER['REMOTE_ADDR']);
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		} else if($Flag == "TOP_SELLERS"){
			$CatProdsQry->orderBy('po.is_sold_quantity','desc');
		} else if($Flag == "NEW_ARRIVALS"){
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else{
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.perfumeworldwide_currentstock','desc');
			$CatProdsQry->orderBy('po.nd_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}

		//echo "<pre>"; print_r($CatProdsWithoutLimit); exit;
		//$CatProdsWithLimit = $CatProdsQry->offset($Offset)->limit($limit)->get();
		$ArrayFilters = ['sortby' => $SortBy, 'offset' => $Offset, 'limit' => $limit];
		$SKUs = '' ;
		$CatProducts = [];
		$TotalProds = 0;
		$VariationIDs=[];
		$ProdIds=[];
		$DealData = GetDealOfWeek('',"Weekly");

        $MyCatProdWithoutLimit = $CatProdsQry->get();

        $MyCatProdWithLimit = $CatProdsQry->offset($Offset)->limit(12)->get();

        foreach($MyCatProdWithLimit as $key => $CatProd)
        {
            $CatProd = $this->SetProduct($CatProd);
            if(is_array($FilterStock) && count($FilterStock) > 0 && $CatProd->stock == 'Out')
                continue;

            if($FilterMaxPrice !='')
            {
                $FilterMaxPrice = (float)$FilterMaxPrice;
                $FilterMinPrice = (float)$FilterMinPrice;
                if((float)$CatProd->product_price < $FilterMinPrice || (float)$CatProd->product_price > $FilterMaxPrice )
                    continue;
            }

            if($CatProd->product_price <= 0)
                continue;

            $TotalProds++;
            /*$SKUs.= $CatProd->sku."#";
            $TotalProds++;*/
            $VariationIDs[]=$CatProd->variation_id;

            if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
                $newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($CatProd->image);
                $verP = filemtime($newimageVal);
                $CatProd->prod_image  = config('global.PRD_LARGE_IMG_URL') . $CatProd->image . "?ver=" . $verP;
            } else {
                $CatProd->prod_image = config('global.NO_IMAGE_LARGE');
            }
            /*
            if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
                $newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProd->image);
                $verP = filemtime($newimageVal);
                $CatProd->prod_image  = config('global.PRD_THUMB_IMG_URL') . $CatProd->image . "?ver=" . $verP;
            } else {
                $CatProd->prod_image = config('global.NO_IMAGE_THUMB');
            }*/

            $CatProd->BrandInSearch = $BrandInSearch;

            $PriceRange = $this->setPriceRange($CatProd->variation_id,$MyCatProdWithLimit);
            $CatProd->minPrice = $PriceRange['MinPrice'];
            $CatProd->maxPrice = $PriceRange['MaxPrice'];
            $CatProd->yousave = $PriceRange['YouSave'];

            if($CategoryID == '2')
                $CatProd->category_id = $CategoryID;

            if($CatProd->parent_id != 0)
                $ProdCat = $CatProd->parent_id;
            else
                $ProdCat = $CatProd->category_id;

            $CatProd->product_url = SetProductURL($CatProd->products_id,$CatProd->product_name,$CatProd->category_id);

            if ($CatProd->gender == 'M'){
                $CatProd->gender = "sv-men";
                $CatProd->gendernames = "Men";
                $for_gender = ' for Men';
            } elseif ($CatProd->gender == 'W'){
                $CatProd->gender = "sv-women";
                $CatProd->gendernames = "Women";
                $for_gender = ' for Women';
            } elseif ($CatProd->gender == 'K'){
                $CatProd->gender = "sv-kids";
                $CatProd->gendernames = "Kids";
                $for_gender = ' for Kids';
            } elseif ($CatProd->gender == 'U'){
                $CatProd->gender = "sv-unisex";
                $CatProd->gendernames = "Unisex";
                $for_gender = ' Unisex';
            } else{
                $CatProd->gender = "";
                $CatProd->gendernames = "";
                $for_gender = '';
            }

            if($CatProd->vmanufacture != ''){
                $m_name = strtolower($CatProd->vmanufacture);
                $m_name = str_replace("#", "", $m_name);
                $m_name = str_replace("&", "", $m_name);
                $m_name = str_replace("  ", " ", trim($m_name));
                $m_name = str_replace("  ", " ", trim($m_name));
                $m_name = str_replace(" ", "-", $m_name);
                $CatProd->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$CatProd->imanufactureid;
            }

            if($CatProd->brand_name != '' && $CatProd->vmanufacture != ''){
                $m_name = strtolower($CatProd->vmanufacture);
                $m_name = str_replace("#", "", $m_name);
                $m_name = str_replace("&", "", $m_name);
                $m_name = str_replace("  ", " ", trim($m_name));
                $m_name = str_replace("  ", " ", trim($m_name));
                $m_name = str_replace(" ", "-", $m_name);
                $CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><strong>' . $CatProd->brand_name . '</strong></a> by <a href='.$CatProd->vmanufacture_link.'><strong><br>'.$CatProd->vmanufacture.'</strong></a>'.$for_gender;
            }

            if(strlen($CatProd->product_name) > 45){
                $CatProd->product_name = substr($CatProd->product_name, 0, (45 - strlen($CatProd->product_name))). "..";
            } else {
                $CatProd->product_name = $CatProd->product_name;
            }

            if($CatProd->vmanufacture == '' || $CatProd->brand_name == ''){
                $CatProd->referencedName = '<a href="' . $CatProd->product_url . '">' . $CatProd->product_name . '</a>';
            }

            if($CatProd->retail_price != '' && $CatProd->retail_price != '0.00' && isset($CatProd->product_price)){
                $yousave = ($CatProd->retail_price - $CatProd->product_price) / $CatProd->retail_price;
                $yousave = $yousave * 100;
                $yousave = number_format($yousave, 0);
                $yousaveprice = $CatProd->retail_price - $CatProd->product_price;
            }else{
                $yousave = 0;
                $yousaveprice = 0;
            }

            $CatProd->yousave = $yousave;
            $CatProd->maxyousave = (($CatProd->yousave>0)?number_format($CatProd->yousave, 0):0);
            $CatProd->yousaveprice = $yousaveprice;
            $CatProd->autoid = $key;

            $CatProd->sale_item = '0';
            if($CatProd->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
            {
                $CatProd->sale_item = '1';
            }

            if(isset($DealData[$CatProd->sku]) && isset($CatProd->WebsiteStock) && $CatProd->WebsiteStock=="In")
            {
                $CatProd->deal_price = $DealData[$CatProd->sku]['deal_price'];
                $CatProd->yousave = $DealData[$CatProd->sku]['yousave'];
                $CatProd->yousaveprice = $DealData[$CatProd->sku]['yousaveprice'];
            }
            if(isset($Flag) && $Flag == 'DealofweekPage' && $CatProd->WebsiteStock=="Out")
            {
				$CatProd->deal_price = $CatProd->product_price;
            }
            $CatProd->short_description = remove_html_entities(strip_tags($CatProd->short_description));
            $CatProd->size_cnt = $CatProd->variarioncnt;
            /*
            $CatProd->avg_rate = 0;
            $total_review = $CatProd->TotalReview;
            if($total_review > 0)
                $CatProd->avg_rate = GetProductAverageRating($CatProd->TotalReview,$CatProd->TotalRate);
            */
            $ProdIds[]=$CatProd->products_id;

            $CatProducts[] = $CatProd;
        }
			//$Products = $this->GetSliderProducts($SKUs,'','Category',$CategoryID,$ArrayFilters);

		//$TotalProducts = $TotalProds;
        $TotalProducts = $MyCatProdWithoutLimit->count();
		$AllFilters = $this->GetFilters($CatProducts,$Filters,$Flag);

		if(count($CatProducts)>0 && isset($ArrayFilters['sortby']) && $ArrayFilters['sortby'] != '')
		{
			if($ArrayFilters['sortby'] == 'priceHL'){
				usort($CatProducts,function($first,$second){
					return $first->product_price < $second->product_price ? 1 : -1;
				});
			}
			if($ArrayFilters['sortby'] == 'priceLH'){
				usort($CatProducts,function($first,$second){
					return $first->product_price > $second->product_price ? 1 : -1;
				});
			}
			if($ArrayFilters['sortby'] == 'priceAZ'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($first->brand_name),strtolower($second->brand_name));
				});
			}
			if($ArrayFilters['sortby'] == 'priceZA'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($second->brand_name),strtolower($first->brand_name));
				});
			}
		} else {
			//usort($CatProducts,function($first,$second){
				//return $first->WebsiteStock > $second->WebsiteStock ? 1 : -1;
			//});
		}
		if(count($CatProducts)>0 && isset($ArrayFilters['limit']) && $ArrayFilters['limit'] != '')
		{
			if(count($CatProducts) > $ArrayFilters['offset'])
				$CatProducts = array_slice($CatProducts,$ArrayFilters['offset'],$ArrayFilters['limit']);
		}

		$CatProducts = $this->CountOptions($VariationIDs,$CatProducts,$ChildCatArr,$Flag);
		$ProductsDetails = ['Products' => $CatProducts,'TotalProducts' => $TotalProducts, 'LeftFilters' => $AllFilters];

		return $ProductsDetails;
	}

    public function CountOptionsNew($VariationIDs=[],$DisplayProducts=[],$CatArrVal=[],$Flag='')
	{
		return $DisplayProducts;
		$VariationIDs = array_unique($VariationIDs);
		$VariationProductQry = DB::table('pu_products as po')
							->join('pu_products_category as pc','po.products_id','=','pc.products_id')
							->join('pu_category as c','pc.category_id','=','c.category_id')
							->join('pu_brand as b','b.brand_id','=','po.brand_id')
							->join('pu_manufacture as m',function($join){
								$join->on('po.imanufactureid','=','m.imanufactureid');
								$join->on('b.imanufactureid','=','m.imanufactureid');
							})
							->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
								'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.parent_id');

		if($Flag == "TOP_SELLERS" || $Flag == "NEW_ARRIVALS")
		{
			if(count($CatArrVal) > 0)
				$VariationProductQry->whereIn('pc.category_id',$CatArrVal);
		}else{
			$VariationProductQry->whereIn('po.variation_id',$VariationIDs);
		}
			$VariationProductQry->where('po.status','=','1')->where('c.status','=','1')->where('b.status','=','1')->where('m.status','=','1');
		if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $VariationProductQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $VariationProductQry->whereIn('po.product_type',['both','retailer']);

		$VariationProductQry->orderBy('po.current_stock');
		$VariationProductQry->orderBy('po.nandansons_current_stock');
		$VariationProductQry->orderBy('po.cosmo_current_stock');
		$VariationProductQry->orderBy('po.pca_current_stock');
		$VariationProductQry->orderBy('po.perfumeworldwide_currentstock');
		$VariationProductQry->orderBy('po.cosmo_current_stock');
		$VariationProducts = $VariationProductQry->groupBy('po.products_id')->get()->toArray();

		$TotalVariations=[];
		$Variation='';
		$vcount = 0;

		$TotalVariations = array_count_values(array_column($VariationProducts, 'variation_id'));
		$ProdCnt = [];
		$Price = [];
		foreach($VariationProducts as $Product)
		{
			$Product = $this->SetProduct($Product);
			$Price[$Product->variation_id][] = (float)$Product->product_price;
		}
		$NewProduct = [];

		foreach($DisplayProducts as $ProductNew)
		{
			if(isset($TotalVariations[$ProductNew->variation_id]))
				$ProductNew->size_cnt = $TotalVariations[$ProductNew->variation_id];
			else
				$ProductNew->size_cnt = 0;

			if(isset($Price[$ProductNew->variation_id]) && count($Price[$ProductNew->variation_id]) > 0)
			{
				$ProductNew->minPrice = min($Price[$ProductNew->variation_id]);
				$ProductNew->maxPrice = max($Price[$ProductNew->variation_id]);
			} else {
				$ProductNew->minPrice = 0;
				$ProductNew->maxPrice = 0;
			}
			$NewProduct[] = $ProductNew;
		}

		for($i=0;$i<count($NewProduct);$i++)
		{
			if($NewProduct[$i]->is_atomizer == "Yes" || $NewProduct[$i]->stock == "Out")
			{
				foreach($VariationProducts as $Product)
				{
					if ($Product->products_id == $NewProduct[$i]->products_id)
					continue;
					if ($Product->variation_id != $NewProduct[$i]->variation_id)
						continue;

					$Product = $this->SetProduct($Product);

					if ($Product->is_atomizer == "Yes" && $Product->stock == "Out")
						continue;
					if ($Product->stock == "Out" && $Product->is_atomizer == "No")
						continue;

					if ($Product->is_atomizer == "No")
					{
						if ($Product->stock == "In" && $Product->category_id == $NewProduct[$i]->category_id)
						{
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;

							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}

							if (count($CatArrVal) > 0) {
								$isAtom1 = 'No';
								for ($j = 0; $j < count($CatArrVal); $j++) {
									if ($CatArrVal[$j] != 68 && $CatArrVal[$j] != 70 &&  $CatArrVal[$j] != 71 &&  $CatArrVal[$j] != 69) {
										$isAtom1 = 'Yes';
									}
								}
								if ($isAtom1 == 'Yes') {
									break;
								}
							} else {
								break;
							}
						} else if ($Product->stock == "In") {
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;

							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}

						} else {
							if ($NewProduct[$i]->is_atomizer != 'Yes' && $NewProduct[$i]->stock != 'In') {
								$NewProduct[$i] = $this->PrepareProduct($Product,$i);
								if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
									$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
								else
									$NewProduct[$i]->size_cnt = 0;

								if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
								{
									$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
									$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
								} else {
									$NewProduct[$i]->minPrice = 0;
									$NewProduct[$i]->maxPrice = 0;
								}
							}
						}
					} else {
						if ($Product->stock == "In" && ($NewProduct[$i]->stock != 'In' || in_array(68, $CatArrVal) || in_array(70, $CatArrVal) || in_array(71, $CatArrVal) || in_array(69, $CatArrVal))) {
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;

							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}
							$isAtom = 'No';
							for ($j = 0; $j < count($CatArrVal); $j++) {
								if ($CatArrVal[$j] == 68 || $CatArrVal[$j] == 70 ||  $CatArrVal[$j] == 71 ||  $CatArrVal[$j] == 69) {
									$isAtom = 'Yes';
								}
							}
							if ($isAtom == 'Yes')
								break;
						}
					}
				}
			}
		}
		//dd($NewProduct);
		return $NewProduct;
	}

	public function GetFilters($Products,$SetFilters=[],$Flag='')
	{
		$Filters=[];
		$ListingMenu = $this->ListingMenu();
		$f=0;
		/*$CategoryList = $this->UniqueKey($Products,'category_id','category_name');
		if(count($CategoryList) > 0 )
		{
			$Filters[$f]['Categories']['Attr'] = ['title' => 'Category', 'id' => 'categories', 'filterval' => 'key'];
			$Filters[$f]['Categories']['Data'] = $CategoryList;
			$Filters[$f]['Categories']['Selected'] = isset($SetFilters['categories'])?$SetFilters['categories']:[];
			$Filters[$f]['Categories']['Order'] = 0;
		}
		$f++;*/

		$BrandList = $this->UniqueKey($Products,'imanufactureid','vmanufacture',$Flag);
		asort($BrandList);

		if(count($BrandList) > 0 )
		{
			$Filters[$f]['Brands']['Attr'] = ['title' => 'Brands', 'id' => 'brands', 'filterval' => 'key'];
			$Filters[$f]['Brands']['Data'] = $BrandList;
			$Filters[$f]['Brands']['Selected'] = isset($SetFilters['brands'])?$SetFilters['brands']:[];
			$Filters[$f]['Brands']['Order'] = $f;
		}
		$f++;

		if(count($ListingMenu) > 0)
		{
			foreach($ListingMenu as $Menu)
			{
				$List = $this->UniqueKey($Products,$Menu->table_fieldName,$Menu->table_fieldName);
				if(count($List) > 0 )
				{
					if($Menu->table_fieldName == 'size')
					{
						$SizeType = [];
						$NewSizes = [];
						foreach($List as $skey => $size)
						{
							$ExpSize = explode(" ",$size);
							if(strstr(strtolower($size),'oz') ||  isset($ExpSize[1]) && (strtolower($ExpSize[1]) == 'oz' || strtolower($ExpSize[1]) == 'oz.'))
							{
								$SizeType['oz'][$skey] = $size;
							}else if(strstr(strtolower($size),'ml') ||  isset($ExpSize[1]) && (strtolower($ExpSize[1]) == 'ml' || strtolower($ExpSize[1]) == 'ml.'))
							{
								$SizeType['ml'][$skey] = $size;
							}else if(strstr(strtolower($size),'mini'))
							{
								$SizeType['mini'][$skey] = $size;
							}else if(strstr(strtolower($size),'set'))
							{
								$SizeType['set'][$skey] = $size;
							}else {
								$SizeType['oth'][$skey] = $size;
							}
						}
						if(isset($SizeType['mini']))
						{
							$NewSizes = array_merge($NewSizes,$SizeType['mini']);
						}
						if(isset($SizeType['set']))
						{
							$NewSizes = array_merge($NewSizes,$SizeType['set']);
						}

						if(isset($SizeType['ml']))
						{
							$NewSizes = array_merge($NewSizes,$this->SetArray($SizeType['ml'],'ml'));
						}
						if(isset($SizeType['oz']))
						{
							$NewSizes = array_merge($NewSizes,$this->SetArray($SizeType['oz'],'oz'));
						}
						if(isset($SizeType['oth']))
						{
							$SortedSize = $this->SetArray($SizeType['oth'],'oth');
							$NewSizes = $NewSizes + $SortedSize;
							//$NewSizes = array_merge($NewSizes,$this->SetArray($SizeType['oth'],'oth'));
						}
						$List = $NewSizes;
						//asort($List);
					}else{
						asort($List);
					}
					$MenuName = str_replace(" ","",$Menu->menuname);
					$Filters[$f][$MenuName]['Attr'] = ['title' => $Menu->menuname, 'id' => $Menu->table_fieldName];
					$Filters[$f][$MenuName]['Data'] = $List;
					$Filters[$f][$MenuName]['Selected'] = isset($SetFilters[$Menu->table_fieldName])?$SetFilters[$Menu->table_fieldName]:[];
					$f++;
				}
			}
		}

		$FeatureList = $this->UniqueKey($Products,'refine_feature','refine_feature');
		asort($FeatureList);

		if(count($FeatureList) > 0 )
		{
			$Filters[$f]['Features']['Attr'] = ['title' => 'Features', 'id' => 'features', 'filterval' => 'key'];
			$Filters[$f]['Features']['Data'] = $FeatureList;
			$Filters[$f]['Features']['Selected'] = isset($SetFilters['features'])?$SetFilters['features']:[];
			$Filters[$f]['Features']['Order'] = $f;
		}
		$f++;

		$Filters[$f]['Avability']['Attr'] = ['title' => 'By Avability', 'id' => 'stock', 'filterval' => 'key' ];
		$Filters[$f]['Avability']['Data'] = ['In' => 'In Stock'];
		$Filters[$f]['Avability']['Selected'] = isset($SetFilters['stock'])?$SetFilters['stock']:[];

		$f++;
		$SpecialFilter = [];
		if(isset($SetFilters['special']))
		{
			foreach($SetFilters['special'] as $SFilter)
			{
				if($SFilter == 'ts')
					$SpecialFilter[] = 'top_seller';
				if($SFilter == 'na')
					$SpecialFilter[] = 'new_arrival';
				if($SFilter == 'fe')
					$SpecialFilter[] = 'featured';
				if($SFilter == 'cl')
					$SpecialFilter[] = 'clearance';
				if($SFilter == 'cp')
					$SpecialFilter[] = 'celebrity';
				if($SFilter == 'sl')
					$SpecialFilter[] = 'sale_price';
			}
		}
		$Filters[$f]['Special']['Attr'] = ['title' => 'By Special', 'id' => 'special', 'filterval' => 'key' ];
		$Filters[$f]['Special']['Data'] = [
							'top_seller' => 'Top Seller',
							'new_arrival' => 'New Arrival',
							'featured' => 'Featured',
							'clearance' => 'Clearance',
							'celebrity' => 'Celebrity Perfume',
							'sale_price' => 'Sale'];
		$Filters[$f]['Special']['Selected'] = $SpecialFilter;
		return $Filters;
	}

	public function SetArray($SizeArray=[],$sizekey)
	{
		$NewSizeArray = [];
		$SizeSortArray = [];
		foreach($SizeArray as $skey => $svalue)
		{
			$ExpSize = explode($sizekey,strtolower($svalue));
			array_push($NewSizeArray,['key' => $svalue, 'val' => trim($ExpSize[0])]);
		}

		if(count($NewSizeArray) > 0)
		{
			usort($NewSizeArray, function($a, $b) {
				//return $a['val'] > $b['val'];
				return $a['val'] <=> $b['val'];
			});
			foreach($NewSizeArray as $nkey => $nval)
			{
				$SizeSortArray[(string)$nval['key']] = $nval['key'];
			}
		}
		//dd($NewSizeArray);
		return $SizeSortArray;
	}

	public function UniqueKey($Array, $key, $column,$flag='') {
		$ItemsData = [];
		foreach ($Array as $item) {
			if(isset($item->$column) && $item->$column != ''){
				if($key == 'imanufactureid')
				{

					if(isset($flag) && isset($item->is_popular) && $item->is_popular == 'Yes' && $flag=='ProductListPage')
					{

						$ItemsData[ucwords($item->$key)] = ucwords(stripslashes($item->$column));
					}
					else if($flag!='ProductListPage')
					{
						$ItemsData[ucwords($item->$key)] = ucwords(stripslashes($item->$column));
					}
				} else {
					$ItemsData[ucwords($item->$key)] = ucwords(stripslashes($item->$column));
				}
			}
		}
		$ItemsData = array_unique($ItemsData);
		return $ItemsData;
	}

	public function getChildCatIdStr($category_id, $string_catID='',$type='')
	{
		$FindCat = Category::where('parent_id','=',$category_id)->where('status','=','1')->get();
		if($FindCat && $FindCat->count() > 0)
		{
			foreach($FindCat as $FindCatNew){
				$temp_id = $FindCatNew->category_id;
				if($type == ''){
					$string_catID.=$temp_id.",";
					$string_catID = $this->GetChildCats($temp_id,$string_catID,$type);
				} else {
					$string_catID[]= ['category_id' => $FindCatNew->category_id,'category_name' => $FindCatNew->category_name];
					$string_catID=$this->GetChildCats($temp_id,$string_catID,$type);
				}
			}
		}
		return $string_catID;
	}

	public function GetChildCats($ParentID,$string_catID,$type)
	{
		$ChildCat = Category::where('parent_id','=',$ParentID)->where('status','=','1')->get();
		if($ChildCat && $ChildCat->count() > 0)
		{
			foreach($ChildCat as $Child)
			{
				if($type == ''){
					$string_catID.= $Child->category_id.",";
					$this->GetChildCats($Child->category_id,$string_catID,$type);
				}else{
					$string_catID[]= ['category_id' => $Child->category_id,'category_name' => $Child->category_name];
					$this->GetChildCats($Child->category_id,$string_catID,$type);
				}
			}
		}
		return $string_catID;
	}
	public function ParentChild($CategoryID,$Bredcrum=[])
	{
		if($CategoryID != 0)
		{
			$CatDetails = Category::find($CategoryID);
			$Bredcrum[]=[
				'url' => config('global.SITE_URL').remove_special_chars(trim($CatDetails->category_name)).'/cid/'.$CatDetails->category_id,
				'name' => $CatDetails->category_name,
			];
			$this->ParentChild($CatDetails->parent_id,$Bredcrum);
		}
		return $Bredcrum;
	}
	public function GetCatTree($CatArray)
	{
		//$Categories = Category::where('parent_id','=','0')->where('status','=','1')->with('children')->get();
		$Categories = Category::where('status','=','1')->orderBy('display_position')->get();
		$SubCatsTree=[];$key=0;
		$AllCats = $this->MyCatTree($Categories);
		foreach($AllCats as $MainCat)
		{
			if(in_array($MainCat->category_id,$CatArray) || $CatArray[0] == 0)
			{
				$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'Level' => 0];

				if(isset($MainCat->childs) && count($MainCat->childs) > 0 ){
					foreach($MainCat->childs as $SubLevel1){
						$SubAllCats = isset($SubLevel1->childs)?$SubLevel1->childs:[];
						$SubCatsTree[$key][]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name,'hasChild' => ($SubAllCats != null && count($SubAllCats) > 0) ? 'Yes':'No', 'Level' => 1];
						$SubCats[]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name];
						if($SubAllCats){
							foreach($SubAllCats as $SubLevel2){
								$SubCatsTree[$key][]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name, 'Level' => 2];
								$SubCats[]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name];
								$key++;
							}
						}
						$key++;
					}
				}
				$key++;
			}
		}
		return $SubCatsTree;
	}

	public function MyCatTree($Cats)
	{
		$childs = array();
		foreach($Cats as $item){
			$childs[$item->parent_id][] = $item;
			unset($item);
		}
		foreach($Cats as $item){
			if (isset($childs[$item->category_id])){
				$item['childs'] = $childs[$item->category_id];
			}
		}
		return $childs[0];
	}

	public function GetChildCategories($Category)
	{
		$SubCats = [];
		$SubCatsTree=[];
		if(!is_object($Category))
		{
			$Category = Category::find($Category);
		}
		$key=0;
		$SubCatsTree[$key][]=['category_id' => $Category->category_id, 'category_name' => $Category->category_name];
		$SubCats[]=['category_id' => $Category->category_id, 'category_name' => $Category->category_name];
		if($Category->children){
			foreach($Category->children as $SubLevel1){
				$SubAllCats = $SubLevel1->children;
				$SubCatsTree[$key][]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name,'hasChild' => ($SubAllCats != null && count($SubAllCats) > 0) ? 'Yes':'No'];
				$SubCats[]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name];
				if($SubAllCats){
					foreach($SubLevel1->children as $SubLevel2){
						$SubCatsTree[$key][]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name];
						$SubCats[]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name];
						$key++;
					}
				}
				$key++;
			}
		}
		return ['CatList' => $SubCats, 'CatTree' => $SubCatsTree];
	}
	public function SetParentID($Category)
	{
		$ParentID = "";
		if($Category->parent != null && $Category->parent->parent != null){
			$ParentID =  $Category->parent->parent->category_id;
		}elseif($Category->parent != null){
			$ParentID =  $Category->parent->category_id;
		}else{
			$ParentID =  $Category->category_id;
		}
		return $ParentID;
	}
	public function Bredcrum($RequestParams)
	{
		$i=0;
		$ExpMyCat = [];
        $Bredcrum = [];
		$frombrandpage = 'N';
		if($RequestParams->category_id && $RequestParams->category_id != '')
		{
			$ExpMyCat = explode(',',$RequestParams->category_id);
		}
		if(isset($RequestParams->category_id) && $RequestParams->category_id != '' && count($ExpMyCat) == 1)
		{
			$CatDetails = config('CATEGORY_INFO');
            if(isset($CatDetails['CatForProd'][$RequestParams->category_id]))
            {
                $BredcrumInfo = $CatDetails['CatForProd'][$RequestParams->category_id]['bredcrum'];
                foreach($BredcrumInfo as $Binfo)
                {
                    $Bredcrum[]=$Binfo;
                    if($Binfo['id'] == $RequestParams->category_id)
                        break;
                }
            }
		} else if(isset($RequestParams->mid) && $RequestParams->mid != ''){
			$frombrandpage = 'Y';
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
			$i++;
			$Bredcrum[$i]['title'] = ucwords($RequestParams->brand_name);
			$Bredcrum[$i]['link'] = config('global.SITE_URL').strtolower($RequestParams->brand_name).'/p4u/mid-'.$RequestParams->mid.'/view';
		} else if(isset($RequestParams->keyword) && $RequestParams->keyword != ''){
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
			$i++;
			$Bredcrum[$i]['title'] = ucwords($RequestParams->keyword);
			$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/key-'.$RequestParams->keyword.'/view';
		} else {
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
		}
		$i=count($Bredcrum)-1;
		/*
		$Bredcrum[$i]['title'] = 'Home';
		$Bredcrum[$i]['link'] = config('global.SITE_URL');

		if($RequestParams->category_id && $RequestParams->category_id != '')
		{
			$CatDetails = Category::find($RequestParams->category_id);
			if($CatDetails && $CatDetails->count() > 0)
			{
				$CatString = '';
				$CatLink = '';
				if($CatDetails->parent != null && $CatDetails->parent->parent != null)
				{
					$MainCat = $CatDetails->parent->parent;
					$CatLink = config('global.SITE_URL').remove_special_chars(trim($MainCat->category_name)).'/cid/'.$CatDetails->category_id;
					$CatString.= ucwords($MainCat->category_name);
				}
				if($CatDetails->parent != null)
				{
					$SubCat = $CatDetails->parent;
					$CatLink = config('global.SITE_URL').remove_special_chars(trim($SubCat->category_name)).'/cid/'.$CatDetails->category_id;
					if($CatString !='')
						$CatString.=' - '.ucwords($SubCat->category_name);
					else
						$CatString.=ucwords($SubCat->category_name);
				}
				if($CatString != '')
					$CatString.=' - '.$CatDetails->category_name;
				$i++;
				$Bredcrum[$i]['title'] = $CatString;
				$Bredcrum[$i]['link'] = $CatLink;
			}
		}*/

		$OtherParams = ["new-arrivals"];
		$OtherCat = "";
		/*if(isset($RequestParams->category_name) && in_array($RequestParams->category_name,$OtherParams))
		{
			$i++;
			$OtherCat = str_replace("-"," ",$RequestParams->category_name);
			$Bredcrum[$i]['title'] = ucwords($OtherCat);
			$Bredcrum[$i]['link'] = '';
			$OtherCat = ucwords($OtherCat);
		}*/

		$NewParams = [];
		$sizeParams = [];
		if($RequestParams->filters != '')
		{
			$Params = explode("/",$RequestParams->filters);
			foreach($Params as $pkey => $Param)
			{
				$ExpParam = explode('-',$Param);
				//if(count($ExpParam)>1)
				if(count($ExpParam)>1 && $ExpParam[1] != "pocket") //09052024
					$NewParams[$ExpParam[0]] = $ExpParam[1];

				$sizeParams[$ExpParam[0]] = $ExpParam;
			}
		}

		foreach($NewParams as $fkey => $FParam)
		{
			if($fkey == 'cid')
			{
				$ExpCats = explode(",",$FParam);
				for($k=0;$k<count($ExpCats);$k++)
				{
					if(isset($RequestParams->category_id) && $ExpCats[$k] == $RequestParams->category_id)
						continue;
					$i++;
					$CatDetails = Category::find($ExpCats[$k]);
					if($CatDetails && $CatDetails->count() > 0)
					{
						$CatString = '';
						$CatLink = '';
						if($CatDetails->parent != null && $CatDetails->parent->parent != null)
						{
							$MainCat = $CatDetails->parent->parent;
							$CatLink = config('global.SITE_URL').remove_special_chars(trim($MainCat->category_name)).'/cid/'.$CatDetails->category_id;
							$CatString.= ucwords($MainCat->category_name);
						}
						if($CatDetails->parent != null)
						{
							$SubCat = $CatDetails->parent;
							$CatLink = config('global.SITE_URL').remove_special_chars(trim($SubCat->category_name)).'/cid/'.$CatDetails->category_id;
							if($CatString !='')
								$CatString.=' - '.ucwords($SubCat->category_name);
							else
								$CatString.=ucwords($SubCat->category_name);
						}
						if($CatString != '')
							$CatString.=' - '.$CatDetails->category_name;
						$Bredcrum[$i]['title'] = $CatString;
						$Bredcrum[$i]['link'] = $CatLink;
					}
				}
			}else if($fkey == 'mid')
			{
				$ExpBrands = explode(",",$FParam);
				if(count($ExpBrands) > 0)
				{
					$Manufactures = Manufacture::whereIn('imanufactureid',$ExpBrands)->get();
					if($Manufactures && $Manufactures->count() > 0)
					{
						foreach($Manufactures as $Manufacture)
						{
							$i++;
							$Bredcrum[$i]['title'] = ucwords($Manufacture->vmanufacture);
							$CatLink = config('global.SITE_URL').'p4u/';
							if(isset($RequestParams->category_id) && $RequestParams->category_id != '')
								$CatLink.='cid-'.$RequestParams->category_id.'/';
							$CatLink.='mid-'.$Manufacture->imanufactureid.'/view';
							$Bredcrum[$i]['link'] = $CatLink;
						}
					}
				}
			} else {
				$ExpParams = explode(",",$FParam);
				for($e=0;$e<count($ExpParams);$e++)
				{
					$BLink = '';
					$Title = '';
					if($RequestParams->category_id && $RequestParams->category_id != '')
						$BLink.='cid-'.$RequestParams->category_id.'/';
					if(isset($NewParams['mid']) && $NewParams['mid'] != '')
						$BLink.='mid-'.$NewParams['mid'].'/';
					if($fkey == 'special')
					{
						$ShowLink = 0;
						if($ExpParams[$e] == 'ts' || $ExpParams[$e] == 'top_seller'){
							$Title = 'Top Seller';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'na' || $ExpParams[$e] == 'new_arrival'){
							$Title = 'New Arrival';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'fe' || $ExpParams[$e] == 'featured'){
							$Title = 'Featured';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'cl' || $ExpParams[$e] == 'clearance'){
							$Title = 'Clearance';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'cp' || $ExpParams[$e] == 'celebrity'){
							$Title = 'Celebrity';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'sl' || $ExpParams[$e] == 'sale_price'){
							$Title = 'Sale';
							$ShowLink = 1;
						}
						if($ShowLink == 1)
							$BLink.= "special-".$ExpParams[$e].'/view';

						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords($Title);
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else if($fkey == 'ftype'){
						if($frombrandpage == 'Y'){
							$BLink.= strtolower($RequestParams->brand_name).'/p4u/mid-'.$RequestParams->mid.'/'.$fkey."-".$ExpParams[$e].'/view';
						} else {
							$BLink.= $fkey."-".$ExpParams[$e].'/view';
						}
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords($ExpParams[$e]);
							if($frombrandpage == 'Y'){
								$Bredcrum[$i]['link'] = config('global.SITE_URL').$BLink;
							} else {
								$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
							}
						}
					} else if($fkey == 'discovery') {
						$BLink.= $fkey."-".$ExpParams[$e].'/view';
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords("discovery sets");
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else if($fkey == 'max2day') {
						$BLink.= $fkey."-".$ExpParams[$e].'/view';
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords("max 2day");
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else if($fkey == 'InStockItems') {
						$BLink.= $fkey."-".$ExpParams[$e].'/view';
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords("In Stock Items");
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else if($fkey == 'OnTopRated'){
						$BLink.= $fkey."-".$ExpParams[$e].'/view';
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords("Top Rated");
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else if($fkey == 'OnSaleDeal') {
						$BLink.= $fkey."-".$ExpParams[$e].'/view';
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords("On Sale Deal");
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else if($fkey == 'review_rating') {
						$BLink.= $fkey."-".$ExpParams[$e].'/view';
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = $ExpParams[$e]." Star"; //ucwords("On Sale Deal");
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else if($fkey == 'price'){
						if($frombrandpage == 'Y'){
							$BLink.= strtolower($RequestParams->brand_name).'/p4u/mid-'.$RequestParams->mid.'/'.$fkey."-".$ExpParams[$e].'/view';
						} else {
							$BLink.= $fkey."-".$ExpParams[$e].'/view';
						}
						if($BLink != ''){
							$i++;
							if($sizeParams[$fkey][1] == "0"){
								$Bredcrum[$i]['title'] = ucwords("below $".$sizeParams[$fkey][2]);
							} else {
								if(isset($sizeParams[$fkey][1]) && isset($sizeParams[$fkey][2]) ){
									$Bredcrum[$i]['title'] = ucwords("$".$sizeParams[$fkey][1]." - $".$sizeParams[$fkey][2]);
								} else if(!isset($sizeParams[$fkey][2]) && isset($sizeParams[$fkey][1])) {
									$Bredcrum[$i]['title'] = "Over ".ucwords("$".$sizeParams[$fkey][1]);
								} else {
									$Bredcrum[$i]['title'] = '';
								}

								//if(isset())
								//$Bredcrum[$i]['title'] = ucwords("$".$sizeParams[$fkey][1]." - $".$sizeParams[$fkey][2]);
							}
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else if($fkey == 'size'){
						if($frombrandpage == 'Y'){
							$BLink.= strtolower($RequestParams->brand_name).'/p4u/mid-'.$RequestParams->mid.'/'.$fkey."-".$ExpParams[$e].'/view';
						} else {
							$BLink.= $fkey."-".$ExpParams[$e].'/view';
						}
						if($BLink != ''){
							$i++;
							if(isset($sizeParams[$fkey][1]) && $sizeParams[$fkey][1] == "0"){
								if(isset($sizeParams[$fkey][2])){
									$Bredcrum[$i]['title'] = ucwords("< ".$sizeParams[$fkey][2]." OZ" );
								} else {
									$Bredcrum[$i]['title'] = ucwords("< ".$sizeParams[$fkey][1]." OZ" );
								}
							} else if(isset($sizeParams[$fkey][1]) && $sizeParams[$fkey][1] == "3.4"){
								$Bredcrum[$i]['title'] = ucwords("> ".$sizeParams[$fkey][1]." OZ" );
							} else {
								if(isset($sizeParams[$fkey][1]) && isset($sizeParams[$fkey][2])){
									$Bredcrum[$i]['title'] = ucwords($sizeParams[$fkey][1]." - ".$sizeParams[$fkey][2]." OZ" );
								} else {
									$Bredcrum[$i]['title'] = ucwords($sizeParams[$fkey][1]." - OZ" );
								}
							}
							if($frombrandpage == 'Y'){
								$Bredcrum[$i]['link'] = config('global.SITE_URL').$BLink;
							} else {
								$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
							}
						}
					} else if($fkey == 'gen'){
						if($frombrandpage == 'Y'){
							$BLink.= strtolower($RequestParams->brand_name).'/p4u/mid-'.$RequestParams->mid.'/'.$fkey."-".$ExpParams[$e].'/view';
						} else {
							$BLink.= $fkey."-".$ExpParams[$e].'/view';
						}
						if($BLink != ''){
							$i++;
							if($ExpParams[$e] == 'm'){
								$Bredcrum[$i]['title'] = "Men";
							} else if($ExpParams[$e] == 'w'){
								$Bredcrum[$i]['title'] = "Women";
							} else if($ExpParams[$e] == 'u'){
								$Bredcrum[$i]['title'] = "Unisex";
							} else {
								$Bredcrum[$i]['title'] = ucwords($ExpParams[$e]);
							}
							if($frombrandpage == 'Y'){
								$Bredcrum[$i]['link'] = config('global.SITE_URL').$BLink;
							} else {
								$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
							}
						}
					} else {
						$BLink.= $fkey."-".$ExpParams[$e].'/view';
						//if($BLink != ''){
						if($BLink != '' && $fkey!='sort'){	//09052024
							$i++;
							$Bredcrum[$i]['title'] = ucwords($ExpParams[$e]);
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					}
				}
			}
		}
		$BredLink = '';
		foreach($Bredcrum as $key => $BHead)
		{
			if((count($Bredcrum)-1) == $key )
			{
				$BredLink.="<span class='active'>".$BHead['title']."</span>";
			} else {
				$BredLink.="<a href='".$BHead['link']."'>".$BHead['title']."</a>";
			}
		}
		$BredcrumCnt = 0;

		if($Bredcrum && count($Bredcrum) > 0)
		{
			$BredcrumCntVal = "";
			if(isset($Bredcrum[count($Bredcrum)-1]['title'])) {
				$BredcrumCntVal = $Bredcrum[count($Bredcrum)-1]['title'];
			}
		}
		else
		{
			$BredcrumCntVal = '';
		}

		$BredData = ['BredLink' => $BredLink, 'PageTitle' => $BredcrumCntVal];
		return $BredData;
	}

	public function BredcrumAjax($RequestParams)
	{
		$i=0;
		$CurrFilter = $RequestParams->currFilter;
		$from_brand_page = "";
		$brnd_nm = "";
		$cat_nm = "";
		$cat_id = "";
		$brnd_id = "";
		if(isset($RequestParams->from_brand_page) && $RequestParams->from_brand_page!=''){
			$from_brand_page = $RequestParams->from_brand_page;
		}
		if(isset($RequestParams->brand_name) && $RequestParams->brand_name!=''){
			$brnd_nm = $RequestParams->brand_name;
		}
		if(isset($RequestParams->brand_id) && $RequestParams->brand_id!=''){
			$brnd_id = $RequestParams->brand_id;
		}
		if(isset($RequestParams->cat_name) && $RequestParams->cat_name!=''){
			$cat_nm = $RequestParams->cat_name;
		}
		$Rank=0;
		$ExpMyCat = [];
		if($RequestParams->category_id && $RequestParams->category_id != '')
		{
			$cat_id = $RequestParams->category_id;
			$ExpMyCat = explode(',',$RequestParams->category_id);
		}
		if($RequestParams->category_id && $RequestParams->category_id != '' && count($ExpMyCat) == 1)
		{
			$CatDetails = config('CATEGORY_INFO');
			$BredcrumInfo = $CatDetails['CatForProd'][$RequestParams->category_id]['bredcrum'];
			foreach($BredcrumInfo as $Binfo)
			{
				$Binfo['rank'] = 0;
				if($CurrFilter==$Binfo['title'])
					$Binfo['rank'] = 1;
				$Bredcrum[]=$Binfo;
				if($Binfo['id'] == $RequestParams->category_id)
					break;
			}
		}
		else if(isset($RequestParams->keyword) && $RequestParams->keyword != ''){
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
			$Bredcrum[$i]['rank'] = 0;
			$Rank++;
			$i++;
			$Bredcrum[$i]['title'] = ucwords($RequestParams->keyword);
			$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/key-'.$RequestParams->keyword.'/view';
			$Bredcrum[$i]['rank'] = 1;
			$Rank++;
			$i++;
		}
		else {
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
			$Bredcrum[$i]['rank'] = 0;
			$i++;
			/*$Bredcrum[$i]['title'] = 'Search';
			$Bredcrum[$i]['link'] = '';
			$Bredcrum[$i]['rank'] = 1;*/
		}

		$i=count($Bredcrum)-1;
		/*$OtherParams = ["new-arrivals"];
		if(isset($RequestParams['category_name']) && in_array($RequestParams['category_name'],$OtherParams))
		{
			$i++;
			$OtherCat = str_replace("-"," ",$RequestParams['category_name']);
			$Bredcrum[$i]['title'] = ucwords($OtherCat);
			$Bredcrum[$i]['link'] = '';
		}*/
		$NewParams = array();
		if(isset($RequestParams->keyword) && $RequestParams->keyword != ''){
			if(!empty($RequestParams->setArrBreadCrumb)){
				$NewParams = array_reverse(array_unique($RequestParams->setArrBreadCrumb));
			}else{
				$NewParams = array();
			}
		}else{
			if(isset($RequestParams->filters))
			{
				$NewParams = json_decode($RequestParams->filters);
			}
		}
		//dd($NewParams);

		if(isset($RequestParams->keyword) && $RequestParams->keyword != ''){
			$ExpParams = array_unique($NewParams);

			if(is_array($ExpParams) && count($ExpParams) > 0)
			{
				foreach($ExpParams as $extraFilter)
				{
					//dd($ExpParams);
					$i++;
					//$Bredcrum[$i]['rank'] = 2;

					$Bredcrum[$i]['rank'] = 1;
					if($CurrFilter == ucwords($extraFilter)){
						$Bredcrum[$i]['rank'] = $Rank;
					}

					$Rank++;

					$CatLink = '';

					$extraFilter1 = str_replace("doubledot",":",$extraFilter);
					$extraFilter1 = str_replace("dot",".",$extraFilter1);
					$extraFilter1 = str_replace("dash","-",$extraFilter1);
					$extraFilter1 = str_replace("andd","&",$extraFilter1);
					$extraFilter1 = str_replace("singlecomma","'",$extraFilter1);
					$extraFilter1 = str_replace("_"," ",$extraFilter1);

					$Bredcrum[$i]['title'] = ucwords($extraFilter1);
					$CatLink .= config('global.SITE_URL').'p4u/key-'.rawurlencode(str_replace("-"," ",$extraFilter1)).'/view';

					$Bredcrum[$i]['link'] = $CatLink;
				}
			}
		}
		$f_test = '';
		foreach($NewParams as $fkey => $FParam)
		{
			if(!isset($RequestParams->keyword) && $RequestParams->keyword == ''){
			if($fkey == 'categories')
			{
				$ExpCats = $FParam;
				if(!isset($RequestParams->keyword) && $RequestParams->keyword == ''){
					for($k=0;$k<count($ExpCats);$k++)
					{
						if(isset($RequestParams->category_id) && $ExpCats[$k] == $RequestParams->category_id)
							continue;
						$i++;
						//$CatDetails = Category::find($ExpCats[$k]);
						$CatDetails = is_numeric($ExpCats[$k]) ? Category::find($ExpCats[$k]) : [];
						if($CatDetails && $CatDetails->count() > 0)
						{
							$CatString = '';
							$CatLink = '';
							$SelCat='';
							if($CatDetails->parent != null && $CatDetails->parent->parent != null)
							{
								$MainCat = $CatDetails->parent->parent;
								$CatLink = config('global.SITE_URL').remove_special_chars(trim($MainCat->category_name)).'/cid/'.$CatDetails->category_id;
								$CatString.= ucwords($MainCat->category_name);
								$SelCat = ucwords($MainCat->category_name);
							}
							if($CatDetails->parent != null)
							{
								$SubCat = $CatDetails->parent;
								$CatLink = config('global.SITE_URL').remove_special_chars(trim($SubCat->category_name)).'/cid/'.$CatDetails->category_id;
								if($CatString !='')
									$CatString.=' - '.ucwords($SubCat->category_name);
								else
									$CatString.=ucwords($SubCat->category_name);
								$SelCat = ucwords($SubCat->category_name);
							}
							if($CatString != '')
								$CatString.=' - '.$CatDetails->category_name;
							$Bredcrum[$i]['title'] = $CatString;
							$Bredcrum[$i]['link'] = $CatLink;
							$Bredcrum[$i]['rank'] = 0;
							if($CurrFilter==$SelCat)
								$Bredcrum[$i]['rank'] = 1;
						} else {
							if($from_brand_page == 'Y'){
								$lnk = config('global.SITE_URL').remove_special_chars($brnd_nm)."/p4u/mid-".$RequestParams->brand_id;
							} else {
								$lnk = config('global.SITE_URL').remove_special_chars($cat_nm)."/p4u/cid-".$cat_id;
							}
							if($ExpCats[$k] == 'm'){
								$Bredcrum[$i]['title'] = "Men";
								$Bredcrum[$i]['link'] = $lnk."/gen-m/view";
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'w'){
								$Bredcrum[$i]['title'] = "Women";
								$Bredcrum[$i]['link'] = $lnk."/gen-w/view";
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'u'){
								$Bredcrum[$i]['title'] = "Unisex";
								$Bredcrum[$i]['link'] = $lnk."/gen-u/view";
								$Bredcrum[$i]['rank'] = 1;
							//} else if($ExpCats[$k] == 'f-pocket-perfume'){
							} else if($cat_id != "68" && $ExpCats[$k] == 'f-pocket-perfume'){ //09052024
								$Bredcrum[$i]['title'] = "Pocket Perfume";
								$Bredcrum[$i]['link'] = $lnk."/f-pocket-perfume/view";
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'ts'){
								$Bredcrum[$i]['title'] = "Best Seller";
								$Bredcrum[$i]['link'] = $lnk."/special-ts/view"; //config('global.SITE_URL');
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'InStockItems'){
								$Bredcrum[$i]['title'] = "In Stock Items";
								$Bredcrum[$i]['link'] = $lnk."/InStockItems-yes/view"; //config('global.SITE_URL');
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'OnTopRated'){
								$Bredcrum[$i]['title'] = "Top Rated";
								$Bredcrum[$i]['link'] = $lnk."/OnTopRated-yes/view"; //config('global.SITE_URL');
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'OnSaleDeal'){
								$Bredcrum[$i]['title'] = "On Sale Deal";
								$Bredcrum[$i]['link'] = $lnk."/OnSaleDeal-yes/view"; //config('global.SITE_URL');
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'max2day'){
								$Bredcrum[$i]['title'] = "Max 2 Days";
								$Bredcrum[$i]['link'] = $lnk."/max2day-yes/view"; //config('global.SITE_URL');
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'na'){
								$Bredcrum[$i]['title'] = "New Arrivals";
								$Bredcrum[$i]['link'] = $lnk."/special-na/view"; //config('global.SITE_URL');
								$Bredcrum[$i]['rank'] = 1;
							} else if($ExpCats[$k] == 'discovery-sets'){
								$Bredcrum[$i]['title'] = "Discovery Sets";
								$Bredcrum[$i]['link'] = $lnk."/discovery-sets/view"; //config('global.SITE_URL');
								$Bredcrum[$i]['rank'] = 1;
							}
						}
					}
				}
			}else if($fkey == 'brands')
			{
				$ExpBrands = $FParam;

				if(!isset($RequestParams->keyword) && $RequestParams->keyword == ''){
					if(count($ExpBrands) > 0)
					{
						$Manufactures = Manufacture::whereIn('imanufactureid',$ExpBrands)->get();
						if($Manufactures && $Manufactures->count() > 0)
						{
							foreach($Manufactures as $Manufacture)
							{
								$i++;
								$Bredcrum[$i]['rank'] = 0;
								if($CurrFilter == ucwords($Manufacture->vmanufacture))
									$Bredcrum[$i]['rank'] = 1;
								$Bredcrum[$i]['title'] = ucwords($Manufacture->vmanufacture);
								$CatLink = config('global.SITE_URL').'p4u/';
								if(isset($RequestParams->category_id) && $RequestParams->category_id != '')
									$CatLink.='cid-'.$RequestParams->category_id.'/';
								$CatLink.='mid-'.$Manufacture->imanufactureid.'/view';
								if($from_brand_page == 'Y'){
									$CatLink = config('global.SITE_URL').remove_special_chars($Manufacture->vmanufacture)."/p4u/mid-".$Manufacture->imanufactureid."/";
									$Bredcrum[$i]['link'] = $CatLink."view";
								} else {
									$Bredcrum[$i]['link'] = $CatLink;
								}

								//$f_test = "test";
							}
						}
					}
				}
			} else {
				$ExpParams = $FParam;

				if(!isset($RequestParams->keyword) && $RequestParams->keyword == ''){
					if(is_array($ExpParams) && count($ExpParams) > 0)
					{
						for($e=0;$e<count($ExpParams);$e++)
						{
							$i++;
							$Bredcrum[$i]['title'] = ucwords($ExpParams[$e]);
							$BLink = config('global.SITE_URL').'p4u/';
							if($RequestParams->category_id && $RequestParams->category_id != '')
								$BLink.='cid-'.$RequestParams->category_id.'/';
							/*if(isset($NewParams['mid']) && $NewParams['mid'] != '')
								$BLink.='mid-'.$NewParams['mid'].'/';*/
							if($fkey == 'special')
							{
								$SpecialVal = "";
								if($ExpParams[$e] == 'ts' || $ExpParams[$e] == 'top_seller'){
									$Bredcrum[$i]['title'] = 'Top Seller';
									$SpecialVal = 'ts';
								}
								if($ExpParams[$e] == 'na' || $ExpParams[$e] == 'new_arrival'){
									$Bredcrum[$i]['title'] = 'New Arrival';
									$SpecialVal = 'na';
								}
								if($ExpParams[$e] == 'fe' || $ExpParams[$e] == 'featured'){
									$Bredcrum[$i]['title'] = 'Featured';
									$SpecialVal = 'fe';
								}
								if($ExpParams[$e] == 'cl' || $ExpParams[$e] == 'clearance'){
									$Bredcrum[$i]['title'] = 'Clearance';
									$SpecialVal = 'cl';
								}
								if($ExpParams[$e] == 'cp' || $ExpParams[$e] == 'celebrity' ){
									$Bredcrum[$i]['title'] = 'Celebrity';
									$SpecialVal = 'cp';
								}
								if($ExpParams[$e] == 'sl' || $ExpParams[$e] == 'sale_price'){
									$Bredcrum[$i]['title'] = 'Sale';
									$SpecialVal = 'ts';
								}
								$BLink.= $fkey."-".$SpecialVal.'/view';
							}
							else if($fkey == 'review_rating') {
								$BLink.= $fkey."-".$ExpParams[$e].'/view';
								if($BLink != ''){
									$Bredcrum[$i]['title'] = $ExpParams[$e]." Star"; //ucwords("On Sale Deal");
									$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
								}
							}
							else {
								if($from_brand_page == 'Y'){
									$CatLink = config('global.SITE_URL').remove_special_chars($brnd_nm)."/p4u/mid-".$brnd_id."/";
									$BLink = $CatLink;
								}
								$BLink.= $fkey."-".$ExpParams[$e].'/view';
							}
							$Bredcrum[$i]['link'] = $BLink;
							$Bredcrum[$i]['rank'] = 0;
							if($CurrFilter == $Bredcrum[$i]['title'])
								$Bredcrum[$i]['rank'] = 1;
						}
					}
				}
			}
			}
		}

		usort($Bredcrum, function($a, $b) {
			return $a['rank'] <=> $b['rank'];
		});

		$BredLink = '';
		foreach($Bredcrum as $key => $BHead)
		{
			if((count($Bredcrum)-1) == $key )
			{
				$BredLink.="<span class='active'>".$BHead['title']."</span>";
			} else {
				$BredLink.="<a href='".$BHead['link']."'>".$BHead['title']."</a>";
			}
		}
		$BredData = ['BredLink' => $BredLink, 'PageTitle' => $Bredcrum[count($Bredcrum)-1]['title']];
		return $BredData;
	}

	public function GetBredcrum($Category,$NoneCategory='')
	{
		$Bredcrum = '<a href="'.config('global.SITE_URL').'">Home</a>';
		if($Category != '')
		{
			if($Category->parent != null && $Category->parent->parent != null){
				$MainCat = $Category->parent->parent;
				$MainCatLink = config('global.SITE_URL').remove_special_chars(trim($MainCat->category_name)).'/cid/'.$MainCat->category_id;
				$Bredcrum.='<a href="'.$MainCatLink.'">'.$MainCat->category_name.'</a>';
			}
			if($Category->parent != null){
				$SubCat = $Category->parent;
				$SubCatLink = config('global.SITE_URL').remove_special_chars(trim($SubCat->category_name)).'/cid/'.$SubCat->category_id;
				$Bredcrum.='<a href="'.$SubCatLink.'">'.$SubCat->category_name.'</a>';
			}
			$Bredcrum.='<span class="active">'.$Category->category_name.'</span>';
		}
		if($NoneCategory != '') {
			$Bredcrum.='<span class="active">'.$NoneCategory.'</span>';
		}
		return $Bredcrum;
	}

	public function insertGiftCertificate(Request $request,$cookieegift='No')
	{
		//echo "sasa".$request['GiftImage'];exit;
		$temp_ary = array();
		 if(trim($cookieegift)=='Yes')
        {
			$giftcertificateflag = $this->checkexistgiftcertificate($request["GiftImage"]);
			if($giftcertificateflag==false)
			  return NULL;
		}
		$temp_ary['ProductID']   	= 0;

		$getGCNameRes = SiteSettings::where('var_name','=','GIFT_CERTIFICATE_IMAGE_TITLE1')
												->orWhere('var_name','=','GIFT_CERTIFICATE_IMAGE_TITLE2')
												->orWhere('var_name','=','GIFT_CERTIFICATE_IMAGE_TITLE3')
												->orWhere('var_name','=','GIFT_CERTIFICATE_IMAGE_TITLE4')
												->get();

		if($request["GiftImage"] == "GiftCard1.png")
		{
			$temp_ary['SKU']         	= config('global.GIFT_CERTIFICATE_SKU');
			$temp_ary['ProductName'] 	= $getGCNameRes[0]['setting'];
		}
		elseif($request["GiftImage"] == "GiftCard2.png")
		{
			$temp_ary['SKU']         	= config('global.GIFT_CERTIFICATE_SKU1');
			$temp_ary['ProductName'] 	= $getGCNameRes[1]['setting'];
		}
		elseif($request["GiftImage"] == "GiftCard3.png")
		{
			$temp_ary['SKU']         	= config('global.GIFT_CERTIFICATE_SKU2');
			$temp_ary['ProductName'] 	= $getGCNameRes[2]['setting'];
		}
		elseif($request["GiftImage"] == "GiftCard4.png")
		{
			$temp_ary['SKU']         	= config('global.GIFT_CERTIFICATE_SKU3');
			$temp_ary['ProductName'] 	= $getGCNameRes[3]['setting'];
		}

		$temp_ary['ProductName_description']= 'E-Gift Card';
		$temp_ary['short_description'] = '';
		$temp_ary['manufactureName'] = '';
		$temp_ary['CategoryName'] ="";

		if($request['dateflag'] == 'FutureDate')
		{
			$request['deliverydate'] = $request['d_start_date'];
		}
		else
		{
			$request['deliverydate'] = date("m/d/Y");

		}

		$temp_ary['ItemPrice']     	= number_format($request['gc_value'],2);
		$temp_ary['CategoryID']  	= 0;
		$temp_ary['Price']       	= number_format($request['gc_value'],2);
		$temp_ary['Qty'] 		 	= 1;
		$temp_ary['TotPrice']    	= number_format($request['gc_value'],2);

		$temp_ary['Image']			= "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL')."?ver=".time()."' border='0' width='125' />";
		$temp_ary['image_forpopup'] = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL')."?ver=".time()."' border='0' width='75' />";
		$temp_ary['Billing_Image']  = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL')."?ver=".time()."' border='0' width='195'/>";

		if(isset($request["GiftImage"]) && $request["GiftImage"] == "GiftCard1.png")
		{
			$temp_ary['Image']			= "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL')."?ver=".time()."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL')."?ver=".time()."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL')."?ver=".time()."' border='0' width='195'/>";
		}
		else if(isset($request["GiftImage"]) && $request["GiftImage"] == "GiftCard2.png"){
			$temp_ary['Image']			= "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL1')."?ver=".time()."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL1')."?ver=".time()."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL1')."?ver=".time()."' border='0' width='195'/>";
		}
		else if(isset($request["GiftImage"]) && $request["GiftImage"] == "GiftCard3.png"){
			$temp_ary['Image']			= "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL2')."?ver=".time()."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL2')."?ver=".time()."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL2')."?ver=".time()."' border='0' width='195'/>";
		}
		else if(isset($request["GiftImage"]) && $request["GiftImage"] == "GiftCard4.png"){
			$temp_ary['Image']			= "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL3')."?ver=".time()."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL3')."?ver=".time()."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.GC_IMAGE_URL3')."?ver=".time()."' border='0' width='195'/>";
		}
		else {
			$temp_ary['Image']			= "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.NO_IMAGE_LARGE')."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.NO_IMAGE_LARGE')."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/".config('global.NO_IMAGE_LARGE')."' border='0' width='195'/>";
		}

		$temp_ary['Prod_URL']       = "#";

		$temp_ary['RecipientName']	= $request['recname'];
		$temp_ary['RecipientEmail']	= $request['recemail'];
		$temp_ary['YourName']		= $request['yourname'];
		$temp_ary['YourEmail']		= $request['youremail'];
		$temp_ary['Subject']		= $request['subject'];
		$temp_ary['Message']		= $request['permassage'];
		//$temp_ary['Signature']		= $request['signature'];
		$temp_ary['DeliveryDate']	= $request['deliverydate'];
		$temp_ary['GiftImage']		= $request['GiftImage'];
		$temp_ary['IsDealProducts'] = 'No';
		$temp_ary['DealDiscountFlag'] = 'No';
		$temp_ary['ImanufactureID']  = '99999';
		$temp_ary['IS_Free_Gift']	 = '';
		$temp_ary['VendorSKU']	 = '';
		$temp_ary['IsCosmo']	 = 'No';
		$temp_ary['IsNandansons']	= 'No';
		$temp_ary['IsPerfumePW']	= 'No';
		$temp_ary['IsPCA']	 = 'No';
		$temp_ary['IsND']	 = 'No';
		$temp_ary['ItemWiseCouponDiscount']	 = '';
		$temp_ary['handling_time_str']	 = '';

		$temp_ary['IsGiftCertificateItem'] = 'Yes';
		$temp_ary['FinalSale']			= "";

	//	echo "<pre>"; print_r($temp_ary); exit;
		if($temp_ary['Price'] <= 0)
			return false;

		$this->setGiftCertiTotal($temp_ary['TotPrice']);

		//$this->session['ShoppingCart']['Cart'][] = $temp_ary;
		$cart_arr = array();
		if(Session::has('ShoppingCart.Cart')){
			$cart_arr = Session::get('ShoppingCart.Cart');
		}
		array_push($cart_arr,$temp_ary);
		Session::put('ShoppingCart.Cart',$cart_arr);

		$a = $this->CalculateSubTotal();
		return true;
	}

	public function setGiftCertiTotal($val)
	{
		$GiftCertiTotal = Session::get('ShoppingCart.GiftCertiTotal') + $val;
		Session::put('ShoppingCart.GiftCertiTotal',$GiftCertiTotal);

		$GiftCertiCount = Session::get('ShoppingCart.GiftCertiCount') + 1;
		Session::put('ShoppingCart.GiftCertiCount',$GiftCertiCount);
		return NULL;
	}

	public function checkexistgiftcertificate($GiftImage)
    {
		$GiftSkuVal = '';
		if($GiftImage == "GiftCard1.png")
		{
			$GiftSkuVal =  config('global.GIFT_CERTIFICATE_SKU');
		}
		else if($GiftImage == "GiftCard2.png")
		{
			$GiftSkuVal =  config('global.GIFT_CERTIFICATE_SKU1');
		}
		else if($GiftImage == "GiftCard3.png")
		{
			$GiftSkuVal = config('global.GIFT_CERTIFICATE_SKU2');
		}
		else if($GiftImage == "GiftCard4.png")
		{
			$GiftSkuVal = config('global.GIFT_CERTIFICATE_SKU3');
		}

		$shoppingcart = Session::get('ShoppingCart.Cart');
		$count = count($shoppingcart);

        for ($a = 0; $a < $count; $a++) {
            if ($GiftSkuVal == $shoppingcart[$a]['SKU']) {
               return false;
            }
        }
        return true;
	}

	public function CalculateSubTotal()
	{
		if(Session::has('ShoppingCart.Cart'))
		{
			$shoppingcart = Session::get('ShoppingCart.Cart');
			$count = count ( $shoppingcart );
			$SubTotal = 0;
			$TotalItemInCart = 0 ;

			for($a=0; $a<$count; $a++)
			{
				$SubTotal += $shoppingcart[$a]['TotPrice'];
				$TotalItemInCart += $shoppingcart[$a]['Qty'];
			}
			Session::put('ShoppingCart.SubTotal',NumberFormat($SubTotal));
			Session::put('ShoppingCart.TotalItemInCart',$TotalItemInCart);
		}
	}

	public function ListingMenu()
	{
		$ListingMenus = Listingmenu::where('display','=','Yes')->orderBy('id')->get();
		$Listing = [];
		if($ListingMenus && $ListingMenus->count() > 0)
		{
			foreach($ListingMenus as $ListingMenu)
				$Listing[$ListingMenu->table_fieldName] = $ListingMenu;
		}
		return $Listing;
	}

	//Wholesale Special Price Functions
	function GetSpecialPriceWholesaler(Request $request,$markuparr) {
		$var_extra='';
		$var_extra1='';

		$sql_part='';
		$prodRes=array();

		$add_extra_sql='';
		$add_extra_select='';
		$add_extra_ordBy='';

		$brand = $request['brand'];
		$flg = $request['flg'];
		$search_keyword = $request['search_keyword'];

		$page = $request['page'];
		if($page == "" || $page <= 1){
			$page = 1;
		}
		$reclimit = 6;
		$start_from = ($page - 1) * $reclimit;

		//$brand = "Frapin Parfums";
		//$search_keyword = "t";

		$prodCntSQL= Products::select('products_id')
								->where('status','=','1')
								->where('wholesale_price','!=','0.00')
								->whereIn('product_type',['both','wholesaler']);

		$prodSQL = Products::where('status','=','1')
								->where('wholesale_price','!=','0.00')
								->whereIn('product_type',['both','wholesaler']);
		if($brand!="")
		{
			//$brandSQL= "SELECT * FROM `".TABLE_PREFIX."manufacture` WHERE REPLACE(REPLACE(REPLACE(vmanufacture,\"\\\'\",''),\"&\",'and'),\"\\'\",'') = '".str_replace("\'","",str_replace("&","and",str_replace("\\'","",trim($brand))))."' and status = '1'";

			//~ $brand_name = str_replace("\'","",str_replace("&","and",str_replace("\\'","",trim($brand)));
			//~ $brandCntRes= DB::table('pu_manufacture')->select('imanufactureid')
							//~ ->where('status', '=', '1')
							//~ ->whereRaw('REPLACE(REPLACE(REPLACE(vmanufacture,\"\\\'\",''),\"&\",\"and\"),\"\\\'\","") = ?',[$brand_name])
							//~ ->get();

			$brandCntRes= Manufacture::where('vmanufacture', '=', $brand)
							->where('status', '=', '1')
							->get();

			$prodCntSQL->where('imanufactureid','=',$brandCntRes[0]->imanufactureid);
			$prodSQL->where('imanufactureid','=',$brandCntRes[0]->imanufactureid);
		}
		//echo "<pre>";print_r($brandCntRes);exit;

		if($flg!="")
		{
			if($flg=="na")
			{
				$prodCntSQL->where('new_arrival','=','Yes');
				$prodSQL->where('new_arrival','=','Yes');
			}
			else if($flg=="fe")
			{
				$prodCntSQL->where('featured','=','Yes');
				$prodSQL->where('featured','=','Yes');
			}
			else if($flg=="cl")
			{
				$prodCntSQL->where('clearance','=','Yes');
				$prodSQL->where('clearance','=','Yes');
			}
			else if($flg=="ts")
			{
				$prodCntSQL->where('top_seller','=','Yes');
				$prodSQL->where('top_seller','=','Yes');
			}
			else if($flg=="cp")
			{
				$prodCntSQL->where('celebrity','=','Yes');
				$prodSQL->where('celebrity','=','Yes');
			}
		}

		if($search_keyword!=""){
			//$search_keyword = str_replace("\'","",str_replace("&","and",str_replace("\\'","",trim($search_keyword))));

			//$add_extra_sql .= " and (REPLACE(REPLACE(REPLACE(product_name,\"\\\'\",''),\"&\",'and'),\"\\'\",'') LIKE \"%$search_keyword%\" || sku='".$search_keyword."' || UPC='".$search_keyword."')";

			$prodCntSQL->where(function($query) use ($search_keyword){
							$query->orWhere('product_name','LIKE','%'.$search_keyword.'%')
								  ->orWhere('sku','=',$search_keyword)
								  ->orWhere('UPC','=',$search_keyword);
						});

			$prodSQL->where(function($query) use ($search_keyword){
							$query->orWhere('product_name','LIKE','%'.$search_keyword.'%')
								  ->orWhere('sku','=',$search_keyword)
								  ->orWhere('UPC','=',$search_keyword);
						});
		}

		//==$prodCntRes = $prodCntSQL->get()->count();

		//echo $prodCntRes;exit;
		//$prodRes = $prodSQL->offset($start_from)->limit($reclimit)->get();
		if(Session::get('sess_useremail') == 'qqualdev@gmail.com')
		{
			ini_set('memory_limit', '512M');
			$prodRes = $prodSQL->limit(1000)->get();
		} else {
			$prodRes = $prodSQL->limit(1000)->get();
		}

		$SpecialProducts = [];
		for($i=0; $i < count($prodRes); $i++) {

			$prodRes[$i] = $this->SetProduct($prodRes[$i]);
			if(!isset($request->all_items))
			{
				if($prodRes[$i]->stock == 'Out')
					continue;
			}
			$imgname = stripslashes($prodRes[$i]['image']);

            if(file_exists(config('global.PRD_LARGE_IMG_PATH').stripslashes($prodRes[$i]['image'])) and !empty($imgname))
					{$thumb_image = config('global.PRD_LARGE_IMG_URL').rawurlencode($prodRes[$i]['image']);}
			else
					{$thumb_image = config('global.NO_IMAGE_LARGE');}
            /*
            if(file_exists(config('global.PRD_THUMB_IMG_PATH').stripslashes($prodRes[$i]['image'])) and !empty($imgname))
					{$thumb_image = config('global.PRD_THUMB_IMG_URL').rawurlencode($prodRes[$i]['image']);}
			else
					{$thumb_image = config('global.NO_IMAGE_THUMB');}
            */
			$prodRes[$i]['image'] = $thumb_image;

			$prodRes[$i]['product_description'] = remove_html_entities(strip_tags($prodRes[$i]['product_description']));

			$prodRes[$i]['short_description'] = remove_html_entities(strip_tags($prodRes[$i]['short_description']));

			//wholesale markup prices
			if($markuparr->count() > 0){
				$markuparr_cnt = $markuparr->count();
				for($d=0;$d< $markuparr_cnt;$d++)
				{
					if($markuparr[$d]->markup_value != "")
					{
						$per = $markuparr[$d]->markup_percent;
						$prodRes[$i]['wholesale_price_'.$d] = $prodRes[$i]['wholesale_price'] - ($prodRes[$i]['wholesale_price']*$per/100);
					}

				}
			}
			$SpecialProducts[]=$prodRes[$i];
			//wholesale markup prices
		}
		$start = 0;
		$end = $reclimit;
		if($page > 1)
		{
			$start = ($page-1) * $reclimit;
			$end = $start + $reclimit;
		}
		$NewSpecialProducts = array_slice($SpecialProducts,$start_from,$reclimit);
		//echo "<pre>";
		//print_r($prodSQL);
		//exit;

		$response_arr['TotalProducts'] = count($SpecialProducts);
		$response_arr['PerPage'] = $reclimit;
		$response_arr['DataArr'] = $NewSpecialProducts;
		return $response_arr;
	}

	public function GetProductsByQuery($Flag,$CategoryID,$limit=12,$Filters=[])
	{
		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		if(count($Filters) > 0){
			foreach($Filters as $fkey => $Filter)
			{
				if($fkey == 'categories' && count($Filters) > 0){
					$ChildCatArr = $Filters['categories'];
				}
			}
		}
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			$ChildCats = $this->GetChildCategories($CategoryID);
			$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
		}

		if(isset($Filters['page']) && $Filters['page'] > 1){
			/*if($Flag=='BrandPage')
			{*/
				$Offset = ($Filters['page']-1) * $limit;
			/*}else{
				$Offset = ($Filters['page']-1) * $limit;
			}*/
		}

		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';

		$CatProdsQry = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_products_one as p1', 'po.products_id', '=', 'p1.products_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
								'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
								'po.vtype','po.variation_id','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.parent_id','p1.extra_images')
					->where('po.status','=','1')
					->where('c.status','=','1');

        if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('po.product_type',['both','retailer']);

		if(count($ChildCatArr) > 0)
			$CatProdsQry->whereIn('pc.category_id',$ChildCatArr);

			//$CatProdsQry->groupBy(['po.brand_id','po.gender','po.imanufactureid']);
		$CatProdsQry->groupBy(['po.variation_id']);

		$FilterStock = '';
		$FilterMinPrice = '';
		$FilterMaxPrice = '';
		$FilterKey = '';
		foreach($Filters as $fkey => $Filter)
		{
			if(is_array($Filter) && count($Filter) > 0)
			{
				if($fkey == 'categories'){
					$CatProdsQry->whereIn('pc.category_id',$Filter);
				}else if($fkey == 'brands'){
					$CatProdsQry->whereIn('po.imanufactureid',$Filter);
				}else if($fkey == 'special'){
					foreach($Filter as $Special)
					{
						if($Special == 'top_seller' || $Special == 'ts')
							$CatProdsQry->where('po.top_seller','=','Yes');
						if($Special == 'new_arrival' || $Special == 'na')
							$CatProdsQry->where('po.new_arrival','=','Yes');
						if($Special == 'featured' || $Special == 'fe')
							$CatProdsQry->where('po.featured','=','Yes');
						if($Special == 'clearance' || $Special == 'cl')
							$CatProdsQry->where('po.clearance','=','Yes');
						if($Special == 'celebrity' || $Special == 'cp')
							$CatProdsQry->where('po.celebrity','=','Yes');
						if($Special == 'sale_price' || $Special == 'sl')
							$CatProdsQry->where('po.sale_price','>',0);
					}
				} else if($fkey == 'stock'){
					$FilterStock = $Filter[0];
				} else if($fkey == 'ProductSKUs'){
					$CatProdsQry->whereIn('po.sku',$Filter);
				} else if($fkey == 'NotProductSKUs'){
					$CatProdsQry->whereNotIn('po.sku',$Filter);
				} else {
					$CatProdsQry->whereIn('po.'.$fkey,$Filter);
				}
			} else if($fkey == 'stock'){
				$FilterStock = $Filter;
			}else if($fkey == 'minprice'){
				$FilterMinPrice = $Filter;
			}else if($fkey == 'maxprice'){
				$FilterMaxPrice = $Filter;
			}else if($fkey == 'key'){
				$FilterKey = $Filter;
			}
		}

		if($Flag == 'CategoryPage')
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else if($Flag == 'DealofweekPage'){
			$CatProdsQry->join('pu_dealofweek as dw','dw.product_sku','=','po.sku');
			$CatProdsQry->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did');
			$CatProdsQry->where('dw.deal_type','=','Weekly');
			$CatProdsQry->where('dw.status','=','1');
			$CatProdsQry->where('dw.start_date','<=',date('Y-m-d H:i'))->where('dw.end_date','>=',date('Y-m-d H:i'));
			if($FilterKey != '')
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			$CatProdsQry->orderBy('dwt.deal_rank');
			$CatProdsQry->orderBy('dw.end_date');
			$CatProdsQry->orderBy('dw.display_rank');
		}else if($Flag == 'ProductListPage'){
			$CatProdsQry->orderBy('b.brand_name');
			$CatProdsQry->orderBy('po.cosmo_sku');
			$CatProdsQry->orderBy('po.nandansons_sku');
			$CatProdsQry->orderBy('po.pca_sku');
			$CatProdsQry->orderBy('po.perfumeworldwide_sku');
			$CatProdsQry->orderBy('po.nd_sku');
			$CatProdsQry->orderBy('po.display_position');
		} else if($Flag == 'ShoppingCart'){
			//$CatProdsQry->join('pu_products_viewed as pv','po.sku','=','pv.sku');
			//$CatProdsQry->where('pv.customer_ip','!=',$_SERVER['REMOTE_ADDR']);
		}else{
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		$CatProdsWithoutLimit = $CatProdsQry->get();

		//$CatProdsWithLimit = $CatProdsQry->offset($Offset)->limit($limit)->get();
		$ArrayFilters = ['sortby' => $SortBy, 'offset' => $Offset, 'limit' => $limit];
		$SKUs = '' ;
		$CatProducts = [];
		$TotalProds = 0;

		if($CatProdsWithoutLimit && $CatProdsWithoutLimit->count() > 0)
		{
			$SliderCategory = $this->GetCategories($CatProdsWithoutLimit);
			foreach($CatProdsWithoutLimit as $key => $CatProd)
			{
				$CatProd = $this->SetProduct($CatProd);
				if($FilterStock != '' && $CatProd->stock == 'Out')
					continue;

				if($FilterMaxPrice !='')
				{
					if($CatProd->product_price < $FilterMinPrice || $CatProd->product_price > $FilterMaxPrice )
						continue;
				}
				/*$SKUs.= $CatProd->sku."#";
				$TotalProds++;*/

				if($CatProd->is_atomizer == "Yes" || $CatProd->stock == "Out")
				{
					$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($CatProd->products_id,$CatProd->variation_id,$CategoryID,[],$CatProdsWithoutLimit);
					if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != '')
						$Product = $SizeCountArr[0];
					else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In')
						$Product = $SizeCountArr[0];
					else
						$CatProd->size_cnt = $SizeCountArr;
				} else {
					$CatProd->size_cnt = $this->getReferencedProducts_CounterDev($CatProd->products_id,$CatProd->variation_id,$CatProdsWithoutLimit);
				}

				$PriceRange = $this->setPriceRange($CatProd->variation_id,$CatProdsWithoutLimit);
				$CatProd->minPrice = $PriceRange['MinPrice'];
				$CatProd->maxPrice = $PriceRange['MaxPrice'];
				$CatProd->yousave = $PriceRange['YouSave'];

				if($CategoryID == '2')
					$CatProd->category_id = $CategoryID;

				if($CatProd->parent_id != 0)
					$ProdCat = $CatProd->parent_id;
				else
					$ProdCat = $CatProd->category_id;

				$ProdCatDetails = $SliderCategory[$ProdCat];
				$category_url = remove_special_chars($ProdCatDetails->category_name).'/';
				$product_name = remove_special_chars($CatProd->product_name);
				$CatProd->product_url = config('global.SITE_URL').$category_url.$product_name."/pid/".$CatProd->products_id."/".$ProdCat;

				if ($CatProd->gender == 'M'){
					$CatProd->gender = "sp sp-strip-boy-icon";
					$CatProd->gendernames = "Men";
					$for_gender = ' for Men';
				} elseif ($CatProd->gender == 'W'){
					$CatProd->gender = "sp sp-strip-girl-icon";
					$CatProd->gendernames = "Women";
					$for_gender = ' for Women';
				} elseif ($CatProd->gender == 'K'){
					$CatProd->gender = "sp sp-strip-children-icon";
					$CatProd->gendernames = "Kids";
					$for_gender = ' for Kids';
				} elseif ($CatProd->gender == 'U'){
					$CatProd->gender = "sp sp-strip-uni-icon";
					$CatProd->gendernames = "Unisex";
					$for_gender = ' Unisex';
				} else{
					$CatProd->gender = "";
					$CatProd->gendernames = "";
					$for_gender = '';
				}

				if($CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$CatProd->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$CatProd->imanufactureid;
				}

				if($CatProd->brand_name != '' && $CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><strong><u>' . $CatProd->brand_name . '</u></strong></a> by <a href='.$CatProd->vmanufacture_link.'><strong><u>'.$CatProd->vmanufacture.'</strong></u></a>'.$for_gender;
				}

				if(strlen($CatProd->product_name) > 45){
					$CatProd->product_name = substr($CatProd->product_name, 0, (45 - strlen($CatProd->product_name))). "..";
				} else {
					$CatProd->product_name = $CatProd->product_name;
				}

				if($CatProd->vmanufacture == '' || $CatProd->brand_name == ''){
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><u>' . $CatProd->product_name . '</u></a>';
				}

				if($CatProd->retail_price != '' && $CatProd->retail_price != '0.00' && isset($CatProd->product_price)){
					$yousave = ($CatProd->retail_price - $CatProd->product_price) / $CatProd->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$yousaveprice = $CatProd->retail_price - $CatProd->product_price;
				}else{
					$yousave = 0;
					$yousaveprice = 0;
				}

				$CatProd->yousave = $yousave;
				$CatProd->maxyousave = number_format($CatProd->yousave, 0);
				$CatProd->yousaveprice = $yousaveprice;
				$CatProd->autoid = $key;

				$CatProd->sale_item = '0';
				if($CatProd->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
				{
					$CatProd->sale_item = '1';
				}
				if(isset($CatProd->WebsiteStock) && $CatProd->WebsiteStock=="In")
				{
					$DealData = config('DealDetails');
					if(isset($DealData[$CatProd->sku]))
					{
						$CatProd->deal_price = $DealData[$CatProd->sku]['deal_price'];
						$CatProd->yousave = $DealData[$CatProd->sku]['yousave'];
						$CatProd->yousaveprice = $DealData[$CatProd->sku]['yousaveprice'];
					}
				}
				if(isset($Flag) && $Flag == 'DealofweekPage' && $CatProd->WebsiteStock=="Out")
				{
					 $CatProd->deal_price = $CatProd->product_price;
				}
				$CatProd->short_description = remove_html_entities(strip_tags($CatProd->short_description));
				/*$CatProd->avg_rate = 0;
				$total_review = $CatProd->TotalReview;
				if($total_review > 0)
					$CatProd->avg_rate = GetProductAverageRating($CatProd->TotalReview,$CatProd->TotalRate);
				*/

				$CatProducts[] = $CatProd;
			}
			//$Products = $this->GetSliderProducts($SKUs,'','Category',$CategoryID,$ArrayFilters);
		}

		$AllFilters = $this->GetFilters($CatProducts,$Filters);
		if(count($CatProducts)>0 && isset($ArrayFilters['limit']) && $ArrayFilters['limit'] != '')
		{
			$CatProducts = array_slice($CatProducts,$ArrayFilters['offset'],$ArrayFilters['limit']);
		}
		if(count($CatProducts)>0 && isset($ArrayFilters['sortby']) && $ArrayFilters['sortby'] != '')
		{
			if($ArrayFilters['sortby'] == 'priceHL'){
				usort($CatProducts,function($first,$second){
					return $first->product_price < $second->product_price ? 1 : -1;
				});
			}
			if($ArrayFilters['sortby'] == 'priceLH'){
				usort($CatProducts,function($first,$second){
					return $first->product_price > $second->product_price ? 1 : -1;
				});
			}
			if($ArrayFilters['sortby'] == 'priceAZ'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($first->brand_name),strtolower($second->brand_name));
				});
			}
			if($ArrayFilters['sortby'] == 'priceZA'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($second->brand_name),strtolower($first->brand_name));
				});
			}
		}
		$ProductsDetails = ['Products' => $CatProducts,'TotalProducts' => $CatProdsWithoutLimit->count(), 'LeftFilters' => $AllFilters];
		return $ProductsDetails;
	}

	public function PhoneorderPaymentSuccess_bkNEWFG($payment_mode)
    {	//payment_mode = Stripe,Afterpay
		$OrderID = Session::get('phoneorder_detail.order_id');

		$IsGiftCertificateItem = '';

		// if($success ==1){

			$OrderRS = Order::where('orders_id', '=', $OrderID)
						->get();
			// echo "<pre>";print_r($OrderRS);exit;

			if($OrderRS->count() <= 0)
			{
				$err_msg = "Something went wrong, payment failed.";
				$res_arr['success'] = 0;
				$res_arr['err_msg'] = $err_msg;
				return $res_arr;
			}

			$OrderDetailRs = OrderDetail::where('orders_id', '=', $OrderID)
						->get();

			if($OrderRS[0]->gc_code != "")
			{
				$GiftCardRes = GiftCertificate::where('remaining_value','>','0')
												->where('status','=',"1")
												->where('gc_code','=',$OrderRS[0]->gc_code)
												->get();

				// echo "<pre>";print_r($GiftCardRes);exit;
				if($GiftCardRes->count() > 0)
				{
					$gc_remaining_value = 0;
					$applied_amount = $OrderRS[0]->gc_amount;	//applied gc amount

					if($applied_amount <= $GiftCardRes[0]->remaining_value)
					{
						$gc_remaining_value = $GiftCardRes[0]->remaining_value-$applied_amount;
					}

					if($GiftCardRes[0]->gc_code != '' && $GiftCardRes[0]->remaining_value > 0 )
					{
						$upgGif = array (
									'remaining_value'	=>	$gc_remaining_value,
									'last_used_date'	=>	date('Y-m-d H:i:s')
										);

						$uporderresgift = GiftCertificate::where('gc_code','=',$GiftCardRes[0]->gc_code)->update($upgGif);
					}
				}
			}

			$updAray = array (
							'phoneorder_paymentdate' => date("Y-m-d H:i:s")
						 );
			$updOrder = Order::where('orders_id','=',$OrderID)->update($updAray);

			$res_client = Customer::select('customer_id','iRewardpoint','referenced_by','email','registration_type','status')
								->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->where('status', '=', '1')
								->limit(1)->get();

			$NewReaminRewardpoint = $res_client[0]->iRewardpoint;
			if(strtolower($OrderRS[0]->user_type)=='retailer') {
				$rewardarray_use = array();
				$reward_discount =  $OrderRS[0]->reward_discount;

				if($reward_discount > 0) {
					// $res_client = Customer::select('customer_id','iRewardpoint')
								// ->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->where('status', '=', '1')
								// ->limit(1)->get();

					//////////////////////
					$Redeem_Reward = RewardRule::select('forderamount','fcharge')
								->where('erewardrule', '=', 'redeem')
								->get();

					$Max_Reward = RewardRule::select('fcharge')
								->where('erewardrule', '=', 'max')
								->get();

					$reward_point_deducted = ($reward_discount * $Redeem_Reward[0]->fcharge)/ $Redeem_Reward[0]->forderamount;

					/* if($res_client[0]->iRewardpoint >  $Max_Reward[0]->fcharge)
					{
						$refer_amount = ($res_client[0]->iRewardpoint/$Redeem_Reward[0]->fcharge);

						if($reward_discount < $OrderRS[0]->sub_total )
						{
							$remain_count = $Redeem_Reward[0]->fcharge * (int)$refer_amount;
							$reward_remaining = $res_client[0]->iRewardpoint - $remain_count;
							$Total_Reward_Point = $res_client[0]->iRewardpoint;
							$AppliedRewardPoint = $res_client[0]->iRewardpoint;
						}
					}

					if((int)$reward_remaining > 0  && $reward_discount>0) {
						 $FinalReaminRewardpoint = (int)$reward_remaining;
					}
					else{
						 $FinalReaminRewardpoint = $res_client[0]->iRewardpoint;
					}
					*/
					$FinalReaminRewardpoint = $res_client[0]->iRewardpoint;
					if($reward_point_deducted <= $res_client[0]->iRewardpoint){
						$FinalReaminRewardpoint = $res_client[0]->iRewardpoint - $reward_point_deducted;
					}
					$NewReaminRewardpoint = $FinalReaminRewardpoint;
					//echo "<pre>".$FinalReaminRewardpoint;print_r($_SESSION);exit;
					$upgCustomer = array (
											'iRewardpoint' => $FinalReaminRewardpoint
								   );
					$udpRefer = Customer::where('customer_id','=',$OrderRS[0]->customer_id)->update($upgCustomer);

					if($reward_point_deducted  > 0){
						$InsertCustomer = array (
													'customer_id' 	=> $OrderRS[0]->customer_id,
													'note'		  	=> "Deduct Reward Point By Phone Order",
													'iRewardpoint'	=> $reward_point_deducted,
													'Order_No'		=> $OrderRS[0]->orders_no
										   );
						RewardPoint::create($InsertCustomer);
					}
				}
			}

			$Rewardchk_arr = array();
			if($OrderDetailRs->count() > 0) {
				$DealTotalprice = 0;
				for($dl=0; $dl < $OrderDetailRs->count(); $dl++) {
					$dealofdayRS = Dealofweek::select('dealofweek_id','product_sku')
								->where('status', '=', '1')
								->where('start_date', '<=', date('Y-m-d H:i'))
								->where('end_date', '>=', date('Y-m-d H:i'))
								->where('product_sku', '=', $OrderDetailRs[$dl]->sku)
								->limit(1)->get();

					if($dealofdayRS->count() > 0) {
							$DealTotalprice = $DealTotalprice+$OrderDetailRs[$dl]->total;
					}
					else {
							$Rewardchk_arr[] = $OrderDetailRs[$dl]->sku;
					}
				}
			}

			if(strtolower($OrderRS[0]['user_type'])=='retailer') {
				$rewardsql = RewardRule::where('erewardrule', '=', 'reward')
								->get();

				if($rewardsql->count() > 0) {
					//Deal product's reward point count :: Start
					$Rewardtotal = $OrderRS[0]->order_total;
					$RewardtotalNext = $OrderRS[0]->order_total;
					$DealRewardpoint = 0;

					if($DealTotalprice>0) {
						$valuedeal = ( $DealTotalprice * 2)/$rewardsql[0]->forderamount;
						$DealRewardpoint = number_format($valuedeal, 0, '.', '');

						if($Rewardtotal>$DealTotalprice){
							$Rewardtotal = $Rewardtotal-$DealTotalprice;
						}
					}

					//Deal product's reward point count :: End
					$value = ($Rewardtotal * $rewardsql[0]->fcharge)/$rewardsql[0]->forderamount;
					$Rewardpoint = number_format($value, 0, '.', '');

					if($RewardtotalNext>$DealTotalprice && !empty($Rewardchk_arr))
						$Rewardpoint = $Rewardpoint+$DealRewardpoint;
					else
						$Rewardpoint = $DealRewardpoint;

					if($Rewardpoint>0) {
						// $res_client = Customer::select('iRewardpoint')
								// ->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->limit(1)->get();

						$FinalRewardpoint = $Rewardpoint + $NewReaminRewardpoint;
						$NewReaminRewardpoint = $FinalRewardpoint;

						$upgCustomer = array (
												'iRewardpoint' => $FinalRewardpoint
									   );
						$udpRefer = Customer::where('customer_id','=',$OrderRS[0]->customer_id)->update($upgCustomer);
						  // echo $Rewardpoint;exit;
						$InsertCustomer = array (
												'customer_id' 	=> $OrderRS[0]['customer_id'],
												'note'		  	=> "Reward Point Added By Phone Order",
												'iRewardpoint'	=> $Rewardpoint,
												'Order_No'		=> $OrderRS[0]["orders_no"]
									   );
						RewardPoint::create($InsertCustomer);
					}
				}
			}

			// $cust_res = Customer::select('referenced_by','email')
								// ->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->where('registration_type', '=', 'M')
								// ->where('status', '=', '1')
								// ->get();

			//$Remail = $cust_res[0]['email'];
			$referenced_by = "";
			if($res_client->count()>0 && $res_client[0]->registration_type == 'M' && $res_client[0]->status == '1' )
			{
				$referenced_by = $res_client[0]->referenced_by;

				if($referenced_by != ""){
					$new_str_arr = explode('#', $referenced_by);

					if(!empty($new_str_arr)){
						$id = $new_str_arr[0];
						$Remail =  $new_str_arr[1];
					}
				}
			}

			if($referenced_by!='' )
			{
				$referralRes = ReferFriend::select('sender','is_sender_notified','receiver')
								->where('customer_id', '=', $OrderRS[0]->customer_id)
								->where('receiver', '=', $Remail)
								->limit(1)->get();

				$datetime = date('Y-m-d H:i:s');

				if($referralRes->count()>0)
				{
					//Condition For Adding Referral Point First Time When Refferal Client Clicks in Link and Updating Referrel Customer Status//
					if($referralRes[0]->is_sender_notified == 'N')
					{
						/*$saveData['customer_id'] 		= $cust_id;
						$saveData['sender'] 		 	= $sender_email;
						$saveData['receiver'] 		 	= $email;*/
						$saveData['is_sender_notified'] = 'Y';
						$saveData['refer_datetime']	 	= $datetime;

						$referredId = ReferFriend::where('customer_id','=',$id)->where('receiver','=',$Remail)->update($saveData);

						// $cust_res = Customer::select('iRewardpoint')
								// ->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->get();
						// Query For Updating Reward Point in Customer Table //

						$reward_point = $NewReaminRewardpoint+100;
						$custdata['iRewardpoint'] = $reward_point;
						$custId = Customer::where('customer_id','=',$OrderRS[0]->customer_id)->update($custdata);

						$InsertCustomer = array (
													'customer_id' 	=> $OrderRS[0]->customer_id,
													'note'		  	=> "Reward Point For Adding Referral Point First Time",
													'iRewardpoint'	=> 100,
													'Order_No'		=> $OrderRS[0]->orders_no   // Change Order No
												);
						RewardPoint::create($InsertCustomer);
					}
				}
			}

			#### Deduct product stock Start #####
			if($payment_mode != "Stripe" || ($payment_mode == "Stripe" && $OrderRS[0]->pay_status == 'Paid')){
				if($OrderDetailRs->count() > 0){
					$tot_pro = $OrderDetailRs->count();

					for($i=0; $i < $tot_pro; $i++){
						$ProductSt = Products::select('current_stock','cosmo_current_stock','cosmo_sku','nandansons_sku','nandansons_current_stock','perfumeworldwide_sku','pca_sku','nd_sku','perfumeworldwide_currentstock','pca_current_stock','nd_current_stock')
								->where('status', '=', '1')
								->where('sku', '=', $OrderDetailRs[$i]->sku)
								->get();

						if($ProductSt->count() > 0 )
						{
							$new_stock=0;
							if($OrderDetailRs[$i]->IsCosmo =="Yes" && $OrderDetailRs[$i]->VendorSKU == $ProductSt[0]->cosmo_sku)
							{
								if($ProductSt[0]->cosmo_current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->cosmo_current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->cosmo_current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity -$ProductSt[0]->cosmo_current_stock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'cosmo_current_stock' => $new_stock
											 );
							}
							else if($OrderDetailRs[$i]->IsNandansons == "Yes" &&  $OrderDetailRs[$i]->VendorSKU ==$ProductSt[0]->nandansons_sku)
							{
								if($ProductSt[0]->nandansons_current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->nandansons_current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->nandansons_current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->nandansons_current_stock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'nandansons_current_stock' => $new_stock
											 );
							}

							else if($OrderDetailRs[$i]->IsPCA == "Yes" && $OrderDetailRs[$i]->VendorSKU ==$ProductSt[0]->pca_sku)
							{
								if($ProductSt[0]->pca_current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->pca_current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->pca_current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->pca_current_stock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'pca_current_stock' => $new_stock
											 );
							}
							else if($OrderDetailRs[$i]->IsPerfumePW =="Yes" && $OrderDetailRs[$i]->VendorSKU == $ProductSt[0]->perfumeworldwide_sku)
							{
								if($ProductSt[0]->perfumeworldwide_currentstock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->perfumeworldwide_currentstock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->perfumeworldwide_currentstock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->perfumeworldwide_currentstock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'perfumeworldwide_currentstock' => $new_stock
											 );
							}
							else if($OrderDetailRs[$i]->IsND =="Yes" && $OrderDetailRs[$i]->VendorSKU == $ProductSt[0]->nd_sku)
							{
								if($ProductSt[0]->nd_current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->nd_current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->nd_current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->nd_current_stock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'nd_current_stock' => $new_stock
											 );
							}
							else
							{
								if($ProductSt[0]->current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->current_stock;
								}
								if($new_stock<=0)
								{
										$new_stock=0;
								}

								$UpdateStock = array (
													'current_stock' => $new_stock
												 );
							}
							$result = Products::where('sku','=',$OrderDetailRs[$i]->sku)->update($UpdateStock);
						}
					}
				}
			}

			#### Deduct product stock End #####
			$Site_URL = config('global.SITE_URL');
			$STR_EMAIL_ITEM = '';
			$topmenubar = '<table cellpadding="0" cellspacing="0" width="100%" border="0" style="background-color:#2d2d2d;">
											<tr align="center">
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'fragrances/cid/1" style="color:#fff; text-decoration:none; padding:8px 0px; display:block; text-transform:uppercase;">Fragrances</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'skincare/cid/18" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Skincare</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'pocket-perfume/cid/68" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Pocket Perfume</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'bath-body/cid/12" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Bath &amp; Body</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'candles/cid/208" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Candles</a></td>
												<td><a href="'.$Site_URL.'offers.html" style="color:#ff0000; text-decoration:none; padding:5px; display:block;text-transform:uppercase;">SALES & OFFERS</a></td>
											</tr>
										</table>';

				//new
				$STR_EMAIL_ITEM .= '<table cellpadding="0" cellspacing="0" width="100%" border="0">
							<tr align="center" valign="top">
								<td style="background-color:#e5e5e5; padding:5px;"><strong>Gift Wrap</strong></td>
								<td style="background-color:#e5e5e5; padding:5px;"><strong>Images</strong></td>
								<td style="background-color:#e5e5e5; padding:5px;" align="left"><strong>Your Order Summary</strong></td>
								<td style="background-color:#e5e5e5; padding:5px;"><strong>Quantity</strong></td>
								<td style="background-color:#e5e5e5; padding:5px;" align="right"><strong>Price</strong></td>
							</tr>';

				$TotalProducts = 0;
				$is_gift_wrap = "No";
				for($n=0;$n < $OrderDetailRs->count(); $n++)
				{
						// $thumb_image = getItemThumb($OrderDetailRs[$n]['sku']);

						$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$OrderDetailRs[$n],'No');

						/*if($OrderDetailRs[$n]['sku']== config('global.GIFT_CERTIFICATE_SKU'))
						{
							$thumb_image	='<img src="'.config('global.GC_IMAGE_URL').'" width="125" border="0" class="img-resp-75" />';
						}
						else if($OrderDetailRs[$n]['sku'] == config('global.GIFT_CERTIFICATE_SKU1'))
						{
							$thumb_image	='<img src="'.config('global.GC_IMAGE_URL1').'" width="125" border="0" class="img-resp-75" />';
						}
						else if($OrderDetailRs[$n]['sku'] == config('global.GIFT_CERTIFICATE_SKU2'))
						{
							$thumb_image	='<img src="'.config('global.GC_IMAGE_URL2').'" width="125" border="0" class="img-resp-75" />';
						}*/

						if($IsGiftCertificateItem == 'Yes'){
							$thumb_image = $this->checkGiftCertificateItem('SetGiftCertificateImage',$OrderDetailRs[$n],'No');
						}
						else{
							$prod_res = Products::select('image')
								->where('sku', '=', $OrderDetailRs[$n]['sku'])
								->limit(1)->get();

							$image_name= $prod_res[0]['image'];

							if(file_exists(config('global.PRD_THUMB_IMG_PATH').$image_name) and !empty($image_name))
								$prod_image = config('global.PRD_THUMB_IMG_URL').$image_name;
							else
								$prod_image = config('global.NO_IMAGE_THUMB');

							$thumb_image	='<img src="'.$prod_image.'" width="125" border="0" class="img-resp-75" />';
						}

						$checked = '';
						if($OrderDetailRs[$n]['is_gift_wrap']=='Yes')
						{ $checked = 'checked="checked" ';$is_gift_wrap = "Yes";}

						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td valign="middle" style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><input type="checkbox"  disabled="disabled" '.$checked.' /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;">'.$thumb_image.'</a></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="left"><p style="color:#000; margin:0px;"><strong>'.$OrderDetailRs[$n]['product_name'].'</strong></p><p>SKU:'.$OrderDetailRs[$n]['sku'].'</p>';

						$STR_EMAIL_ITEM .= '</td>';
						$STR_EMAIL_ITEM .= '<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><strong>'.$OrderDetailRs[$n]['quantity'].'</strong></td>
						<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8;" align="right"><strong>$'.$OrderDetailRs[$n]['price'].'</strong></td>
						</tr>';

						$TotalProducts = (int)$TotalProducts + (int)$OrderDetailRs[$n]['quantity'];
				}

				if($is_gift_wrap == 'Yes')
				{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Gift Wrap:</strong></td><td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">Yes</td></tr>';
				}

				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong> Total item purchased:</strong></td>
					<td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">'.$TotalProducts.'</td>
				</tr>';

				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Subtotal:</td>
					<td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['sub_total'].'</td>
				</tr>';

				if($OrderRS[0]["shipping_amt"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Charge:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['shipping_amt'].'</td></tr>';
				}

				if($OrderRS[0]["tax"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Sales Tax:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['tax'].'</td></tr>';
				}

				if($OrderRS[0]["gift_charge"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Wrap Charge :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['gift_charge'].'</td></tr>';
				}

				if($OrderRS[0]["auto_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Auto Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['auto_discount'].'</td></tr>';
				}

				if($OrderRS[0]["quantity_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Quantity Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['quantity_discount'].'</td></tr>';
				}

				if($OrderRS[0]["coupon_amount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Coupon Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['coupon_amount'].'</td></tr>';
				}

				if($OrderRS[0]["gc_amount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Certificate Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['gc_amount'].'</td></tr>';
				}

				if($OrderRS[0]["reward_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Reward Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['reward_discount'].'</td></tr>';
				}

				// if($OrderRS[0]["refer_amount"]>0)
				// {
					// $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">'.$AUTO_REFER_DISCOUNT.' :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['refer_amount'].'</td></tr>';
				// }

				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Order Total:</strong></td>
					<td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right"><strong>$'.$OrderRS[0]['order_total'].'</strong></td>
				</tr>';
				$STR_EMAIL_ITEM .= '</table>';

				$mres = GetMailTemplate("ORDER_RECEIPT_NEW");
				$mail_content = stripslashes($mres[0]["mail_body"]);

				$freeshippinginfo = '';
				if(config('Settings.FREESHIPPING_VALUE')!="")
				{
					$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
				}

				$mail_content = str_replace('{$freeshippinginfo}', $freeshippinginfo, $mail_content);
				$mail_content = str_replace('{$topmenubar}', $topmenubar, $mail_content);
				$mail_content = str_replace('{$ordereddate}', date("d F, Y",$OrderRS[0]['order_datetime']), $mail_content);
				$mail_content = str_replace('{$ordertotal}', $OrderRS[0]['order_total'], $mail_content);
				$mail_content = str_replace('{$shipinfo}', $OrderRS[0]['shipinfo'], $mail_content);
				$mail_content = str_replace('{$CONTACT_MAIL}', config('Settings.CONTACT_MAIL'), $mail_content);

				$MailBanners = MailBanner::where('status','=','1')->get();

				$Addblock = '';
				if($MailBanners && $MailBanners->count() > 0)
				{
					$Addblock .= ' <td class="flex" valign="top" width="27%"><table width="100%" border="0" cellpadding="0" cellspacing="0">
							  <tbody>';
					foreach($MailBanners as $MailBanner)
					{
						$banner_img = config('global.MAIL_BANNERS_URL').$MailBanner->mail_banner_image.".jpg";
						$banner_link = $MailBanner->mail_banner_link;
						$Addblock .= ' <tr class="halftd">
											<td style="padding:5px;border:1px solid #e8e8e8" align="center"><a href="'.$banner_link.'"  target="_blank"><img src="'.$banner_img.'" alt="" class="img-responsive"/></a>
										</td></tr>';
					}
					$Addblock .= '</tbody></table></td>';
				}

				$mail_content = str_replace('{$Addblock}', $Addblock, $mail_content);
				$mail_content = str_replace('{$orders_no}', $OrderRS[0]['orders_no'], $mail_content);
				$mail_content = str_replace('{$order_datetime}', date("d F, Y",$OrderRS[0]['order_datetime']), $mail_content);
				$mail_content = str_replace('{$order_total}', $OrderRS[0]['order_total'], $mail_content);
				$mail_content = str_replace('{$shipinfo}', $OrderRS[0]['shipinfo'], $mail_content);
				//new

				$mail_content = str_replace('{$orders_id}', $OrderRS[0]['orders_id'], $mail_content);
				$BillAddress = $OrderRS[0]['bill_first_name'].' '.$OrderRS[0]['bill_last_name']."<br>";
				if($OrderRS[0]['bill_address2'] != '')
					$BillAddress.= $OrderRS[0]['bill_address1'].', '.$OrderRS[0]['bill_address2']."<br>";
				else
					$BillAddress.= $OrderRS[0]['bill_address1'].',<br>';
				$BillAddress.=$OrderRS[0]['bill_city'].', '.$OrderRS[0]['bill_state']."<br>";
				$BillAddress.=$OrderRS[0]['bill_zip'].' - '.$OrderRS[0]['bill_country'];

				$mail_content = str_replace('{$bill_address}',$BillAddress,$mail_content);

				$ShipAddress = $OrderRS[0]['ship_first_name'].' '.$OrderRS[0]['ship_last_name']."<br>";
				if($OrderRS[0]['ship_address2'] != '')
					$ShipAddress.= $OrderRS[0]['ship_address1'].', '.$OrderRS[0]['ship_address2']."<br>";
				else
					$ShipAddress.= $OrderRS[0]['ship_address1'].',<br>';
				$ShipAddress.=$OrderRS[0]['ship_city'].', '.$OrderRS[0]['ship_state']."<br>";
				$ShipAddress.=$OrderRS[0]['ship_zip'].' - '.$OrderRS[0]['ship_country'];

				$mail_content = str_replace('{$ship_address}',$ShipAddress,$mail_content);

				$mail_content = str_replace('{$STR_EMAIL_ITEM}',  $STR_EMAIL_ITEM, $mail_content);
				$mail_content = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$mail_content);
				$mail_content = str_replace('{$TOLL_FREE_NO}', config('global.CONTACT_PHONE_NO'), $mail_content);
				$mail_content = str_replace('{$Site_URL}', $Site_URL, $mail_content);
				$mail_content = str_replace('{$SITE_NAME}', config('global.SITE_TITLE'), $mail_content);

				$mail_subject = str_replace('{$SITE_NAME}', config('Settings.SITE_TITLE'), $mres[0]['subject']);
				$mail_subject = str_replace('{$OrderRs.orders_no}', $OrderRS[0]['orders_no'], $mail_subject);
				//$onesendstat = $generalobj->SMTP_Mail_Send($OrderRS[0]['bill_email'],$mail_subject, $mail_content, CONTACT_MAIL);

				$shipping_insurance = 'N';
				$shipping_sign = '';
				if(isset($OrderRS[0]["is_shipping_signature"]) && $OrderRS[0]["is_shipping_signature"] != ''){
					$shipping_sign = 'Y';
					if($OrderRS[0]["is_shipping_signature"] == 'No'){
						$shipping_sign = 'N';
					}
				}
				if(!empty($OrderRS[0]["route_shipping_insurance_charge"]) && $OrderRS[0]["route_shipping_insurance_charge"]>0)
				{
					$shipping_insurance = 'Y';
				}

				//$OrderRS[0]['bill_email']  = "qqualdev@gmail.com";
                if(config('global.OMNISEND_PROG') == false)
                {
                    SendMail($mail_subject,  $mail_content, $OrderRS[0]['bill_email'], config('Settings.ADMIN_MAIL'));
                } else {
                    /** OMANISEND **/
                    //$OtherData = ['toMail' => $OrderRS[0]['bill_email'], 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM];
					$OtherData = ['toMail' => $OrderRS[0]['bill_email'], 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customer_ip' => $OrderRS[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign];
                    OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRS[0],$OtherData);
                    /** OMANISEND **/
                }

				$err_msg = "Thank you for your payment. Your order will be processed as soon as possible. An Order Receipt E-mail has been sent to you.";

				// Session::flash('success',$err_msg);
				$res_arr['success'] = 1;
				$res_arr['err_msg'] = $err_msg;
				return $res_arr;

		/* }else{
			$err_msg = "Something went wrong, payment failed.";
			$res_arr['success'] = 0;
			$res_arr['err_msg'] = $err_msg;
			return $res_arr;
		} */
	}

	public function GetProductsNew($Flag,$CategoryID,$limit=12,$Filters=[])
	{
		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		if(count($Filters) > 0){
			foreach($Filters as $fkey => $Filter)
			{
				if($fkey == 'categories' && count($Filters) > 0){
					$ChildCatArr = $Filters['categories'];
				}
			}
		}
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			//$ChildCats = $this->GetChildCategories($CategoryID);
			$ChildCats = GetMainCatsTree([$CategoryID]);
			if(count($ChildCats['CatList']) > 0)
				$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
			else
				$ChildCatArr = [$CategoryID];
		}
		if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
		}

		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';

		$CatProdsQry = Products::select('pu_products.products_id','pu_products.sku','pu_products.is_gift_wrap','pu_products.short_description','pu_products.maxtwodaydelivery','pu_products.fragrance_family','pu_products.formulation','pu_products.size','pu_products.coverage','pu_products.finish','pu_products.skin_type','pu_products.product_name','pu_products.vtype','pu_products.imanufactureid','pu_products.brand_id','pu_products.is_atomizer',
								'pu_products.fragrance_seasons','pu_products.fragrance_occasion','pu_products.fragrance_personality','pu_products.image','pu_products.current_stock','pu_products.retail_price','pu_products.cosmo_retail_price','pu_products.pca_retail_price','pu_products.minimum_stock','pu_products.gender','pu_products.new_arrival','pu_products.featured','pu_products.clearance','pu_products.top_seller',
								'pu_products.product_type','pu_products.cosmo_sku','pu_products.cosmo_current_stock','pu_products.cosmo_wholesale_price','pu_products.cosmo_our_price','pu_products.pca_sku','pu_products.pca_current_stock','pu_products.pca_wholesale_price','pu_products.pca_our_price',
								'pu_products.nandansons_sku','pu_products.nandansons_current_stock','pu_products.nandansons_wholesale_price','pu_products.nandansons_our_price','pu_products.nandansons_retail_price','pu_products.perfumeworldwide_sku','pu_products.perfumeworldwide_currentstock','pu_products.perfumeworldwide_wholesale_price','pu_products.perfumeworldwide_our_price','pu_products.perfumeworldwide_retail_price',
								'pu_products.nd_sku','pu_products.nd_current_stock','pu_products.nd_wholesale_price','pu_products.nd_our_price','pu_products.nd_retail_price','pu_products.wholesale_price','pu_products.our_price','pu_products.sale_price',
								'pu_products.vtype','pu_products.variation_id','pu_products.refine_feature','pu_products.product_type','pu_products_one.extra_images')->leftJoin('pu_products_one','pu_products_one.product_id','=','pu_products.products_id');
		if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('product_type',['both','retailer']);

		if(count($ChildCatArr) > 0){
			$CatProdsQry->with(['prodCategory.Category' => function($q) use ($ChildCatArr){
				$q->whereIn('category_id',$ChildCatArr);
			}]);
		}

		/*$CatProducts = $CatProdsQry->limit(10)->get()->filter(function($prod){
							return $prod->stock == 'In';
						});*/
		$Sizes = $CatProdsQry->select('size')->where('size','!=','')->distinct('size')->orderBy('size')->get();
		$Fragrances = $CatProdsQry->select('fragrance_family')->where('fragrance_family','!=','')->distinct('fragrance_family')->orderBy('fragrance_family')->get();
		foreach($Fragrances as $Fragrance)
		{
			echo $Fragrance->fragrance_family."<br>";
		}

		$CatProducts = $CatProdsQry->limit(12)->groupBy(['variation_id'])->get();

		$Products = [];
		foreach($CatProducts as $CatProd)
		{
			if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
				$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProd->image);
				$verP = filemtime($newimageVal);
				$CatProd->prod_image  = config('global.PRD_THUMB_IMG_URL') . $CatProd->image . "?ver=" . $verP;
			} else {
				$CatProd->prod_image = config('global.NO_IMAGE_THUMB');
			}
			$Products[] = $CatProd;
			//dd($Prod->current_stock_vendor);
			//dd($Prod->retail_price_vendor);
			//dd($Prod->product_price);
			//dd($Prod->WebsiteStock);
			//dd($Prod->prodbrand->manufacturer);
			//dd($Prod->ratings->where('approved','=','Yes')->where('star_rate','!=','0')->sum('star_rate'));
			//dd($Prod->ratings->where('approved','=','Yes')->where('star_rate','!=','0')->count());
		}
		dd($Products);
	}

	public function GetProductsWithParms($ProductString='',$ManufactureID='',$CategoryID='',$ExcludeProductString='',$Flag='',$limit=10,$Filters=[],$isInStock='No',$CategoryString='',$ManufactureString='',$setPocketPerfume='No'){
		$SldProducts = [];
		$VariationIDs = [];
		$CatArrVal = [];

		//if($ProductString != ""){
		if($ProductString != ""){
			if (strstr($ProductString, ','))
			{
				$ProductString = str_replace("  ", "", $ProductString);
				$ProductString = str_replace(" ", "", $ProductString);
				$ProductString = str_replace(",", "#", $ProductString);
			}
			$ProductString = trim($ProductString);
			//$ProductString = substr($ProductString,0,strlen($ProductString)-1);
			$ProductString = rtrim($ProductString,"#");
			$ProductString = ltrim($ProductString,"#");
			$ProductString = explode("#", trim($ProductString));
		}

		if($ExcludeProductString != ""){
			if (strstr($ExcludeProductString, ','))
			{
				$ExcludeProductString = str_replace("  ", "", $ExcludeProductString);
				$ExcludeProductString = str_replace(" ", "", $ExcludeProductString);
				$ExcludeProductString = str_replace(",", "#", $ExcludeProductString);
			}
			$ExcludeProductString = trim($ExcludeProductString);
			//$ExcludeProductString = substr($ExcludeProductString,0,strlen($ExcludeProductString)-1);
			$ExcludeProductString = rtrim($ExcludeProductString,"#");
			$ExcludeProductString = ltrim($ExcludeProductString,"#");
			$ExcludeProductString = explode("#", trim($ExcludeProductString));
		}

		if($CategoryString != ""){
			if (strstr($CategoryString, ','))
			{
				$CategoryString = str_replace("  ", "", $CategoryString);
				$CategoryString = str_replace(" ", "", $CategoryString);
				$CategoryString = str_replace(",", "#", $CategoryString);
			}
			$CategoryString = trim($CategoryString);
			$CategoryString = rtrim($CategoryString,"#");
			$CategoryString = ltrim($CategoryString,"#");
			$CategoryString = explode("#", trim($CategoryString));
		}

		if($ManufactureString != ""){
			if (strstr($ManufactureString, ','))
			{
				$ManufactureString = str_replace("  ", "", $ManufactureString);
				$ManufactureString = str_replace(" ", "", $ManufactureString);
				$ManufactureString = str_replace(",", "#", $ManufactureString);
			}
			$ManufactureString = trim($ManufactureString);
			$ManufactureString = rtrim($ManufactureString,"#");
			$ManufactureString = ltrim($ManufactureString,"#");
			$ManufactureString = explode("#", trim($ManufactureString));
		}

			if($CategoryID=='68' || $CategoryID=='70' || $CategoryID=='71' || $CategoryID=='69'){
				$CatArrVal = [$CategoryID];
			}

			if(isset($Filters['limit']) && $Filters['limit'] > 1){
				$limit = $Filters['limit'];
			}else if(isset($limit) && $limit > 1){
				$limit = $limit;
			}else{
				$limit = 10;
			}

			if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
			}else{
				$Offset = 0;
			}

			$ProdQry = DB::table('pu_products as po')
						->select('po.products_id',DB::raw('COUNT(po.variation_id) as variarioncnt'),'po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
									'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
									'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
									'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
									'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
									'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
									'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.category_name','c.parent_id','p1.extra_images')
						/*
                        ->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','po.sku')])
                        */
						->join('pu_products_category as pc','po.products_id','=','pc.products_id')
						->leftJoin('pu_products_one as p1','po.products_id','=','p1.products_id')
						->join('pu_category as c','pc.category_id','=','c.category_id')
						->join('pu_brand as b','b.brand_id','=','po.brand_id')
						->join('pu_manufacture as m',function($join){
							$join->on('po.imanufactureid','=','m.imanufactureid');
							$join->on('b.imanufactureid','=','m.imanufactureid');
						})
						->where('po.status','=','1')
						->where('c.status','=','1');

			if($isInStock == 'Yes'){
				//$ProdQry->where('po.current_stock','>','0');
				$ProdQry->where(function($query){
					 $query->OrWhere(function($qry){
                        $qry->where('po.current_stock','>',0)->where('po.current_stock','>','po.minimum_stock' );
                    });
                    $query->OrWhere(function($qry){
                        $qry->where('po.cosmo_current_stock','>',0)->where('po.cosmo_sku','!=','');
                    });
                    $query->OrWhere(function($qry){
                        $qry->where('po.pca_current_stock','>',0)->where('po.pca_sku','!=','');
                    });
                    $query->OrWhere(function($qry){
                        $qry->where('po.nandansons_current_stock','>',0)->where('po.nandansons_sku','!=','');
                    });
                     $query->OrWhere(function($qry){
                        $qry->where('po.perfumeworldwide_currentstock','>',0)->where('po.perfumeworldwide_sku','!=','');
                    });
                    $query->OrWhere(function($qry){
                        $qry->where('po.nd_current_stock','>',0)->where('po.nd_sku','!=','');
                    });
                });
			}

			if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler'){
				$ProdQry->whereIn('po.product_type',['both','retailer','wholesaler']);
			}
			else{
				$ProdQry->whereIn('po.product_type',['both','retailer']);
			}

			if($ProductString != '' && count($ProductString) > 0){
				$ProdQry->whereIn('po.sku',$ProductString);
			}

			if($ExcludeProductString != '' && count($ExcludeProductString) > 0){
				$ProdQry->whereNotIn('po.sku',$ExcludeProductString);
			}

			if($CategoryString != '' && count($CategoryString) > 0){
				$ProdQry->whereIn('pc.category_id',$CategoryString);
			}

			if($ManufactureString != '' && count($ManufactureString) > 0){
				$ProdQry->whereIn('m.imanufactureid',$ManufactureString);
				if($setPocketPerfume == 'Yes'){
					$ProdQry->whereIn('c.category_id',[68,69,70,71]);
					$ProdQry->where('po.is_atomizer','=','Yes');
				}
			}

			if($ManufactureID != '' && $ManufactureID > 0){
				$ProdQry->where('m.imanufactureid','=',$ManufactureID);
			}
			if($CategoryID != '' && $CategoryID > 0){
				$ProdQry->where('c.category_id','=',$CategoryID);
			}

			$ProdQry->groupBy('po.products_id','po.variation_id');
			//echo $ProdQry->toSql();exit;

			$Prods = $ProdQry->get();
			//echo "<pre>";print_r($Prods);echo "</pre>";exit;

			$SkipVariationID = [];
			$TotalProds = 0;
			$ProdIds=[];

			$TotalProducts = $Prods->count();

			if($Prods->count() > 0)
			{
				//$SliderCategory = $this->GetCategories($Prods);

				foreach($Prods as $key => $Product)
				{

					if($ProductString != '' && count($ProductString) > 0){
						if(!in_array($Product->sku,$ProductString)){
							continue;
						}
					}

					$Product = $this->SetProduct($Product);

					if($ProductString != '' && count($ProductString) > 0){
						if($Product->product_price <=0 && in_array($Product->sku,$ProductString))
						{
							$SkipVariationID[]=$Product->variation_id;
							continue;
						}
					}else{
						if($Product->product_price <=0)
						{
							$SkipVariationID[]=$Product->variation_id;
							continue;
						}
					}

					if(in_array($Product->variation_id,$SkipVariationID))
						continue;

					$Product->size_cnt = 0;
					if($Product->is_atomizer == "Yes" || $Product->stock == "Out")
					{
						$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($Product->products_id,$Product->variation_id,$CategoryID,$CatArrVal,$Prods);
						if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != '')
							$Product = $SizeCountArr[0];
						else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In')
							$Product = $SizeCountArr[0];

					}

					if($CategoryID == '2')
						$Product->category_id = $CategoryID;

					//Make Product Link Start
					$product_link = config('global.SITE_URL');

					$product_name = remove_special_chars($Product->product_name);
					$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);
					//Make Product Link End

					if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
						$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($Product->image);
						$verP = filemtime($newimageVal);
						$Product->prod_image = config('global.PRD_THUMB_IMG_URL') . $Product->image . "?ver=" . $verP;
					} else {
						$Product->prod_image = config('global.NO_IMAGE_THUMB');
					}

					if ($Product->gender == 'M'){
						$Product->gender = "sv-men";
						$Product->gendernames = "Men";
						$for_gender = ' for Men';
					} elseif ($Product->gender == 'W'){
						$Product->gender = "sv-women";
						$Product->gendernames = "Women";
						$for_gender = ' for Women';
					} elseif ($Product->gender == 'K'){
						$Product->gender = "sv-kids";
						$Product->gendernames = "Kids";
						$for_gender = ' for Kids';
					} elseif ($Product->gender == 'U'){
						$Product->gender = "sv-unisex";
						$Product->gendernames = "Unisex";
						$for_gender = ' Unisex';
					} else{
						$Product->gender = "";
						$Product->gendernames = "";
						$for_gender = '';
					}

					if($Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
					}

					if($Product->brand_name != '' && $Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
					}

					if(strlen($Product->product_name) > 45){
						$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
					} else {
						$Product->product_name = $Product->product_name;
					}

					if($Product->vmanufacture == '' || $Product->brand_name == ''){
						$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
					}

					if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
						$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
						$yousave = $yousave * 100;
						$yousave = number_format($yousave, 0);
						$yousaveprice = $Product->retail_price - $Product->product_price;
					}else{
						$yousave = 0;
						$yousaveprice = 0;
					}

					$Product->yousave = isset($yousave)?(float)$yousave:0;
					$Product->maxyousave = isset($Product->yousave)?number_format((float)$Product->yousave, 0):0;
					$Product->yousaveprice = isset($yousaveprice)?(float)$yousaveprice:0;
					$Product->autoid = $key;

					$Product->sale_item = '0';
					if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
					{
						$Product->sale_item = '1';
					}
					if(isset($Product->WebsiteStock) && $Product->WebsiteStock=="In")
				    {
						$DealData = config('DealDetails');
						if(isset($DealData[$Product->sku]))
						{
							$Product->deal_price = $DealData[$Product->sku]['deal_price'];
							$Product->yousave = $DealData[$Product->sku]['yousave'];
							$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
						}
					}
					$Product->short_description = remove_html_entities(strip_tags($Product->short_description));
					$Product->size_cnt = $Product->variarioncnt;
					/*
                    $Product->avg_rate = 0;
					$total_review = $Product->TotalReview;
					if($total_review > 0)
						$Product->avg_rate = GetProductAverageRating($Product->TotalReview,$Product->TotalRate);
					*/
					$VariationIDs[] = $Product->variation_id;
					$SldProducts[] = $Product;
				}
			}
		//}
		$SldProducts = $this->CountOptions($VariationIDs,$SldProducts,$CatArrVal);

		if(count($SldProducts) > $Offset){
			$SldProducts = array_slice($SldProducts,$Offset,$limit);
		}

		$ProductsDetails = ['Products' => $SldProducts,'TotalProducts' => $TotalProducts];
		return $ProductsDetails;
	}

	public function UpdatePhoneorderInvoice($orders_id){

		$IsGiftCertificateItem = $IsGiftCertificateItem1 = '';

		// $OrderRs = Order::where('orders_id','=',$orders_id)->get();
		$OrderRs_data = DB::table('pu_orders')->where('orders_id', '=', $orders_id)->get();
		$OrderRs[] = (array)$OrderRs_data[0];
		// echo "<pre>";print_r($OrderRs);exit;

		if(count($OrderRs) > 0)
		{

			$todayDateTime = $EstimatedDeliveryDate = $show_ship_status = '';

			$todayDateTime = Carbon::now();
			$EstimatedDeliveryDate = $OrderRs[0]["EstimatedDeliveryDate"];
			$EstimatedDeliveryDate = Carbon::parse($EstimatedDeliveryDate);

			if($OrderRs[0]["ship_status"] == 'Shipped' && $todayDateTime->isAfter($EstimatedDeliveryDate)){
				$OrderRs[0]["show_ship_status"] = 'Delivered';
			}
			else{
				$OrderRs[0]["show_ship_status"] = $OrderRs[0]["ship_status"];
			}

			$customer_id = $OrderRs[0]['customer_id'];

			$paymentdate_phoneorder = "00/00/0000 00:00:00";

			if($OrderRs[0]["pay_status"] == "Paid"){
				if($OrderRs[0]['phoneorder_paymentdate'] != "0000-00-00 00:00:00"){
					$paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]['phoneorder_paymentdate']));
				}else{
					if($OrderRs[0]['order_upd_datetime'] != "0000-00-00 00:00:00"){
						$paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]['order_upd_datetime']));
					}else{
						$paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]['order_datetime']));
					}
				}
			}

			$OrderDetailRs_Data = DB::table('pu_order_detail as od')
							->select('od.orders_detail_id', 'od.orders_id','od.orders_no','od.products_id','od.sku','od.product_name','od.quantity', 'od.price', 'od.total','od.item_price','od.VendorSKU','od.IsCosmo','od.IsNandansons','od.IsPerfumePW','od.IsPCA','od.IsND','od.is_free_gift_products','od.excluded_flag','p.image', 'p.UPC')
							->join('pu_products as p','od.products_id','=','p.products_id')
							->where('od.orders_id', '=', $orders_id)
							->get();

			 // Set images in order detail arr
			for($p=0; $p<$OrderDetailRs_Data->count(); $p++)
			{
				$OrderDetailRs[$p] = (array)$OrderDetailRs_Data[$p];

				$OrderDetailRs[$p]['Image'] = "";
				$OrderDetailRs[$p]['RecipientName'] = "";
				$OrderDetailRs[$p]['RecipientEmail'] = "";

				$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$OrderDetailRs[$p],'No');

				//if($OrderDetailRs[$p]["sku"]== config('global.GIFT_CERTIFICATE_SKU') || $OrderDetailRs[$p]["sku"]== config('global.GIFT_CERTIFICATE_SKU1') || $OrderDetailRs[$p]["sku"]== config('global.GIFT_CERTIFICATE_SKU2'))
				if($IsGiftCertificateItem == 'Yes'){
					$GCRs = GiftCertificate::where('orders_detail_id','=',$OrderDetailRs[$p]['orders_detail_id'])
												->where('status','=',"1")
												->where('customer_id','=',$customer_id)
												->get();
					// echo "<pre>";print_r($GCRs);exit;
					if($GCRs->count() > 0){
						$OrderDetailRs[$p]['RecipientName']  = $GCRs[0]->recipient_name;
						$OrderDetailRs[$p]['RecipientEmail'] = $GCRs[0]->recipient_email;
						$OrderDetailRs[$p]['SenderName']  	 = $GCRs[0]->your_name;
						$OrderDetailRs[$p]['SenderEmail'] 	 = $GCRs[0]->your_email;

						/*if($OrderDetailRs[$p]["sku"]== config('global.GIFT_CERTIFICATE_SKU')){
							$OrderDetailRs[$p]['Image']	='<img src="'.config('global.GC_IMAGE_URL').'" border="0" width="125" >';
						}else if($OrderDetailRs[$p]["sku"]== config('global.GIFT_CERTIFICATE_SKU1')){
							$OrderDetailRs[$p]['Image']	='<img src="'.config('global.GC_IMAGE_URL1').'" border="0" width="125" >';
						}else if($OrderDetailRs[$p]["sku"]== config('global.GIFT_CERTIFICATE_SKU2')){
							$OrderDetailRs[$p]['Image']	='<img src="'.config('global.GC_IMAGE_URL2').'" border="0" width="125" >';
						}*/

						$OrderDetailRs[$p]['Image'] = $this->checkGiftCertificateItem('SetGiftCertificateImage',$OrderDetailRs[$p],'No',0,0,'','UpdatePhoneorderInvoice');

					}
				}else{
					$thumb_image = "";
					if($OrderDetailRs[$p]["is_free_gift_products"]=="Yes"){
						$prod_res = FreeGiftProduct::select("product_image")->where('sku','=',strtolower(trim($OrderDetailRs[$p]['sku'])))->limit(1)->get();

						if($prod_res->count() > 0){
							if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res[0]->product_image) && !empty($prod_res[0]->product_image)){
								$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res[0]->product_image;
							}else{
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
						}
					}else{
						$prod_res = Products::select('image')
								->where('sku', '=', strtolower(trim($OrderDetailRs[$p]['sku'])))
								->limit(1)
								->get();

						if($prod_res->count() > 0){
							if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res[0]->image) && !empty($prod_res[0]->image)){
								$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res[0]->image;
							}else{
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
						}
					}

					$OrderDetailRs[$p]['Image'] = "";
					if($thumb_image != ""){
						$OrderDetailRs[$p]['Image'] ='<img src="'.$thumb_image.'" border="0" width="150" >';
					}
				}
			}

			// echo "<pre>";print_r($OrderDetailRs);exit;

			if($OrderRs[0]["is_only_gc"] == 1){
				$GC_Only = 1;
			}else{
				$GC_Only = 0;
			}

			$html = '';
			// $blank_div_height='style="height:2px"';
			$blank_div_height='height="13px"';

			$html .= '<style type="text/css">table{font-family: Arial;color: #000000} </style><table border="0" cellspacing="0" cellpadding="0" align="center" width="98%">
						<tr>
							<td align="left" style="border-bottom:2px solid #333333;">';
							if($OrderRs[0]['is_dropship_order']=='Yes'){
								$html .= '<h1>'.$OrderRs[0]['bill_email'].'</h1>';
							}else{
								$html .= '<img src="'.config('global.SITE_IMAGES').'/logo.jpg" style="width: 180px !important;height: 40px !important;" >';
							}
							$html .= '
							</td>
						</tr>

						<tr>
							<td '.$blank_div_height.'></td>
						</tr>
						 <tr>
							<td>
								<table align="center" width="100%" border="0" cellpadding="3" cellspacing="0" class="tableborder" style="background:#b7b7b7; font-size:14px;display: table;border-collapse: separate;box-sizing: border-box;text-indent: initial;border-spacing: 1px;border: 1px solid grey;">
									<tr style="background-color: #333333;height: 25px;color: #ffffff;font-weight: bold;font-size:15px;padding: 5px;">
									  <td class="table-head-bgcolor" align="left">&nbsp;Order Information</td>
									</tr>
									<tr class="lightbg" style="background: #fbfbfb !important;padding: 3px;">
										<td>
											<table  border="0" cellspacing="0" cellpadding="2" align="center" width="100%">
												<tr>
													<td width="19%" class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Order Number :</td>
													<td width="48%" class="admin-text2" align="left" style="color: #222222;font-size:14px;">'.$OrderRs[0]['orders_no'].'</td>';

													if($OrderRs[0]["pay_status"] == "Paid"){
														$html .= '<td width="33%" valign="bottom" align="center" rowspan="2" >
														<span style="font-size:18px;"><strong>Amount Paid : $'.$OrderRs[0]["order_total"].'</strong></span>
														</td>';
													}else{
														$html .= '<td width="33%" valign="middle" align="center" rowspan="2" >
															&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
															<a target="_blank" href="'.config('global.SITE_URL').'payment/'.base64_encode($orders_id).'" style="background-color: red;border-color: red;color:white;font-size:16px;text-decoration:none;display: inline-block;vertical-align:middle;padding:8px 15px;">Pay Now</a>
														</td>';
													}

												$html .= '</tr>
												<tr>
													<td width="19%" class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Order Date :</td>
													<td width="48%" class="admin-text2" align="left" style="color: #222222;font-size:14px;">'.date("m/d/Y",strtotime($OrderRs[0]['order_datetime'])).'</td>
												</tr>
												<tr>
														<td width="19%" class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Order Status :</td>
														<td width="48%" class="admin-text2" align="left" style="color: #222222;font-size:14px;">'.$OrderRs[0]['status'].'</td>';

												if($OrderRs[0]["pay_status"] == "Paid" && $paymentdate_phoneorder != "00/00/0000 00:00:00"){
													$html .= '<td width="33%" align="center"><span style="color: #222222;font-size:14px;">on '.$paymentdate_phoneorder.'</span></td>';
												}else{
													$html .= '<td width="33%" align="center"><span style="font-size:18px;"><strong>Amount Due : $'.$OrderRs[0]["order_total"].'</strong></span></td>';
												}

									$html .= '</tr></table>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td '.$blank_div_height.'></td>
						</tr>
						<tr>
							<td>
								<table border="0" cellpadding="1" cellspacing="0" width="100%" align="center">
									<tr>

										<td width="50%" valign="top">
											<table border="0" cellpadding="3" cellspacing="0" width="100%" class="tableborder" style="background: #b7b7b7;font-size:15px;display: table;border-collapse: separate;box-sizing: border-box;text-indent: initial;border-spacing: 1px;border: 1px solid grey;">
												<tr class="table-head-bgcolor" style="background-color: #333333;height: 25px;color: #ffffff;font-weight: bold;font-size:15px;padding: 5px;">
													<td align="left">&nbsp;Billing Address</td>
												</tr>
												<tr class="lightbg" style="background: #fbfbfb;padding: 3px;">
													<td>
														<table  border="0" cellspacing="0" cellpadding="1" align="center" width="100%">
															<tr>
																<td width="30%" class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Name :</td>
																<td align="left"  style="font-size:14px;">'.$OrderRs[0]['bill_first_name'].' '.$OrderRs[0]['bill_last_name'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Address 1 :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['bill_address1'].'</td>
															</tr>';
															if ($OrderRs[0]['bill_address2']!='') {
															$html .= '<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Address 2 :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['bill_address2'].'</td>
															</tr>';
															}
															$html .= '<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">City :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['bill_city'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Zip Code :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['bill_zip'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">State :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['bill_state'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Country :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['bill_country'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Phone # :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['bill_phone'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Email ID :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['bill_email'].'</td>
															</tr>
														</table>
													</td>
												</tr>
											</table>
										</td>

										<td width="50%" valign="top">';
											if($OrderRs[0]['is_only_gc']==0) {
											$html .= '<table border="0" cellpadding="3" cellspacing="0" width="100%" class="tableborder" style="background: #b7b7b7;font-size:14px;display: table;border-collapse: separate;box-sizing: border-box;text-indent: initial;border-spacing: 1px;border: 1px solid grey;">
												<tr class="table-head-bgcolor" style="background-color: #333333;height: 25px;color: #ffffff;font-weight: bold;font-size:15px;padding: 5px;">
													<td align="left">&nbsp;Shipping Address</td>
												</tr>

												<tr class="lightbg" style="background: #fbfbfb;padding: 3px;">
													<td>
														<table  border="0" cellspacing="0" cellpadding="1" align="center" width="100%">
															<tr>
																<td class="admin-text" width="30%" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Name :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_first_name'].' '.$OrderRs[0]['ship_last_name'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Address 1 :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_address1'].'</td>
															</tr>';
															if ($OrderRs[0]['ship_address2']!='') {
															$html .= '<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Address 2 :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_address2'].'</td>
															</tr>';
															}
															$html .= '<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">City :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_city'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Zip Code :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_zip'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">State :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_state'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Country :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_country'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Phone # :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_phone'].'</td>
															</tr>
															<tr>
																<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Email ID :</td>
																<td align="left" style="font-size:14px;">'.$OrderRs[0]['ship_email'].'</td>
															</tr>
														</table>
													</td>
												</tr>
											</table>';
											}
										$html .= '</td>

									</tr>
								</table>
							</td>
						</tr>

						<tr>
							<td '.$blank_div_height.'></td>
						</tr>

						<tr>
							<td>
								<table width="100%" align="center" border="0" cellpadding="3" cellspacing="0"  class="tableborder" style="background:#b7b7b7; font-size:14px;display: table;border-collapse: separate;box-sizing: border-box;text-indent: initial;border-spacing: 1px;border: 1px solid grey;">
									<tr>
										<td class="table-head-bgcolor" style="background-color: #333333;color: #ffffff;font-weight: bold;font-size:15px;padding: 5px;" align="left">&nbsp;Payment Information & Shipping Information</td>
									</tr>
									<tr class="lightbg" style="background: #fbfbfb !important;padding: 3px;">
										<td>
											<table  border="0" cellspacing="0" cellpadding="1" align="center" width="100%">';
												if($OrderRs[0]['is_only_gc']==0) {
												$html .= '<tr>
													<td class="admin-text" width="30%" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Shipping Method :</td>
													<td align="left" style="color: #222222;font-size:14px;">'.$OrderRs[0]['shipinfo'].'</td>
												</tr>';
												}
												$html .= '<tr>
													<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Payment Method :</td>
													<td align="left" style="color: #222222;font-size:14px;">'.$OrderRs[0]['payment_method'].'</td>
												</tr>
												<tr>
													<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Order Payment Status :</td>
													<td align="left" style="color: #222222;font-size:14px;">'.$OrderRs[0]["pay_status"].'</td>
												</tr>
												<tr>
													<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Order Shipment Status :</td>
													<td align="left" style="color: #222222;font-size:14px;">'.$OrderRs[0]["ship_status"].'</td>
												</tr>';
												if(!empty($OrderRs[0]["ship_method"])) {
												$html .= '<tr>
													<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Shipping Via :</td>
													<td align="left" style="color: #222222;font-size:14px;">'.$OrderRs[0]["ship_method"].'</td>
												</tr>
												<tr>
													<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Tracking# :</td>
													<td align="left" style="color: #222222;font-size:14px;">'.$OrderRs[0]["tracking_no"].'</td>
												</tr>';
												}
												if(!empty($OrderRs[0]["order_comment"])) {
												$html .= '<tr>
													<td class="admin-text" align="left" style="color: #000000;font-size:14px;font-weight: bold;">Admin Comment :&nbsp;</td>
													<td align="left" style="color: #222222;font-size:14px;">'.str_replace("\n","<br>",$OrderRs[0]["order_comment"]).'</td>
												</tr>';
												}
											$html .= '</table>
										</td>
									</tr>
								</table>
							</td>
						</tr>

						<tr>
							<td '.$blank_div_height.'></td>
						</tr>

						<tr>
							<td>
								<table border="0" cellpadding="3" cellspacing="0" width="100%" align="center" class="tableborder" style="background:#b7b7b7; font-size:14px;display: table;border-collapse: separate;box-sizing: border-box;text-indent: initial;border-spacing: 1px;border: 1px solid grey;">
									<tr class="table-head-bgcolor" style="background-color: #333333;height: 25px;color: #ffffff;font-weight: bold;font-size:15px;padding: 5px;">
										<td style="" align="left">&nbsp;Items Ordered</td>
									</tr>

									<tr class="lightbg" style="background: #fbfbfb !important;padding: 3px;">
										<td>
											<table  border="0" cellspacing="0" cellpadding="2" align="center" width="100%" class="tableborder">
												<tr class="evenbgcolor" height="25" style="background-color:#f1f1f1; padding:3px;">
													<td width="55%" align="left" style="border-right: 1px solid grey;border-bottom: 1px solid grey;"><strong>Product Details</strong></td>
													<td width="15%" align="center" style="border-right: 1px solid grey;border-bottom: 1px solid grey;"><strong>Unit Price ($)</strong></td>
													<td width="15%" align="center" style="border-right: 1px solid grey;border-bottom: 1px solid grey;"><strong>Quantity</strong></td>
													<td width="15%" align="center" style="border-bottom: 1px solid grey;"><strong>Total Price ($)</strong></td>
												</tr>';

											foreach($OrderDetailRs as $orderItem)
											{
												$products_id	= $orderItem["products_id"];
												$item_sku 		= trim($orderItem["sku"]); // style number
												$product_name 	= trim($orderItem["product_name"]);

												$quantity 			= $orderItem["quantity"];
												$unit_price 		= number_format($orderItem["price"],2,'.','');
												$total_price 		= number_format($orderItem["total"],2,'.','');

												$VendorSKU	 = $orderItem["VendorSKU"];
												$IsCosmo	 = $orderItem["IsCosmo"];
												$IsNandansons	 = $orderItem["IsNandansons"];
												$IsPerfumePW	 = $orderItem["IsPerfumePW"];
												$IsPCA	 		  = $orderItem["IsPCA"];
												$IsND	 		  = $orderItem["IsND"];
												$final_sale = $orderItem["excluded_flag"];

												## Here get gc info
												/* if($item_sku==GIFT_CERTIFICATE_SKU)
												{
													$gcSql = "SELECT * FROM `".TABLE_PREFIX."gift_certificate` WHERE
													orders_detail_id ='".$orderItem["orders_detail_id"]."'
													AND customer_id ='".$OrderRs[0]["customer_id"]."'" ;
													$GCRs = $obj->select($gcSql);

													$orderItem['RecipientName']  = $GCRs[0]['recipient_name'];
													$orderItem['RecipientEmail'] = $GCRs[0]['recipient_email'];
												} */

												$html .= '<tr class="oddbgcolor" style="background-color:#fff; padding:3px;">
													<td valign="top" style="border-right: 1px solid grey;border-bottom: 1px solid grey;">
														<table border="0" cellpadding="2" cellspacing="0" width="100%" style="font-size:14px;">';

															if ($item_sku !='') {
															$html .= '<tr>
																<td align="left"><strong>Item SKU # : </strong>'.$item_sku.'</td>
															</tr>';
															}

															if($VendorSKU!='' && $IsCosmo=="Yes")
															{
															$html .= '<tr>
																<td align="left"><strong>Cosmo SKU # : </strong>'.$VendorSKU.'</td>
															</tr>';
															}
															elseif($VendorSKU!='' && $IsNandansons=="Yes")
															{
															$html .= '<tr>
																<td align="left"><strong>B & R SKU # : </strong>'.$VendorSKU.'</td>
															</tr>';
															}
															elseif($VendorSKU!='' && $IsPCA=="Yes")
															{
															$html .= '<tr>
																<td align="left"><strong>PCA SKU # : </strong>'.$VendorSKU.'</td>
															</tr>';
															}
															elseif($VendorSKU!='' && $IsPerfumePW=="Yes")
															{
															$html .= '<tr>
																<td align="left"><strong>Perfumeworldwide SKU # : </strong>'.$VendorSKU.'</td>
															</tr>';
															}
															elseif($VendorSKU!='' && $IsND=="Yes")
															{
															$html .= '<tr>
																<td align="left"><strong>Nandansons SKU # : </strong>'.$VendorSKU.'</td>
															</tr>';
															}

															if ( $product_name != '' ) {
															$html .= '<tr>
																<td align="left"><strong>Name : </strong>'.stripslashes($product_name);
															if($final_sale != ''){
																$html .= '<strong>Final Sale</strong>';
															}
															$html .= '</td>
															</tr>';
															}

															$IsGiftCertificateItem1 = $this->checkGiftCertificateItem('IsGiftCertificateItem',$orderItem,'No');

															//if($item_sku == config('global.GIFT_CERTIFICATE_SKU') and ($orderItem['RecipientName'] !='' or $orderItem['RecipientEmail']!=''))
															if($IsGiftCertificateItem1 == 'Yes' and ($orderItem['RecipientName'] !='' or $orderItem['RecipientEmail']!=''))
															{
																$html .= '<tr>
																	<td align="left"><strong>Recipient Name : </strong>'.$orderItem['RecipientName'].'</td>
																</tr>
																<tr>
																	<td align="left"><strong>Recipient Email : </strong>'.$orderItem['RecipientEmail'].'</td>
																</tr>';
															}
														$html .= '</table>
													</td>
													<td align="center" valign="top" style="border-right: 1px solid grey;border-bottom: 1px solid grey;">$'.$unit_price.'</td>
													<td align="center" valign="top" style="border-right: 1px solid grey;border-bottom: 1px solid grey;">'.$quantity.'</td>
													<td align="center" valign="top" style="border-bottom: 1px solid grey;">$'.$total_price.'</td>
												</tr>
												<!--<tr style="background-color:grey;">
													<td colspan="5" height="5"></td>
												</tr>-->';
											}
											$html .= '</table>
										</td>
									</tr>

									<tr  style="background: #fbfbfb !important;padding: 3px;" >
										<td >
											<!-- 	<table border="0" cellspacing="0" cellpadding="2"  width="100%">
												<tr>
													<td width="88%" align="right" class="admin-text" style="color: #000000;font-size:15px;font-weight: bold;">Order Sub Total :</td>
													<td width="12%" align="right" style=""><span style="margin-right:10px">$'.$OrderRs[0]["sub_total"].'</span></td>
												</tr>
												</table> -->
											<table border="0" cellspacing="0" cellpadding="2" width="100%">
												<tr>
													<td width="88%" align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Order Sub Total :</td>
													<td width="12%" align="right" style=""><span style="margin-right:10px">$'.$OrderRs[0]["sub_total"].'</span></td>
												</tr>';
												// if($OrderRs[0]["shipping_amt"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Shipping Charge :</td>
													<td align="right"><span style="margin-right:10px">$'.$OrderRs[0]["shipping_amt"].'</span></td>
												</tr>';
												// }
												if($OrderRs[0]["route_shipping_insurance_charge"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Shipping Insurance Charge :</td>
													<td align="right"><span style="margin-right:10px">$'.$OrderRs[0]["route_shipping_insurance_charge"].'</span></td>
												</tr>';
												}

												// if($OrderRs[0]["tax"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Sales Tax :</td>
													<td align="right"><span style="margin-right:10px">$'.$OrderRs[0]["tax"].'</span></td>
												</tr>';
												// }

												if($OrderRs[0]["shipping_signature"] > 0)
												{
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Shipping Signature :</td>
													<td align="right"><span style="margin-right:10px">$'.$OrderRs[0]["shipping_signature"].'</span></td>
												</tr>';
												}

												if($OrderRs[0]["gift_charge"]>0){

												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Gift Wrapping Charge :</td>
													<td align="right"><span style="margin-right:10px">$'.$OrderRs[0]["gift_charge"].'</span></td>
												</tr>';
												}

												if($OrderRs[0]["auto_discount"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Auto Discount :</td>
													<td align="right"><span style="margin-right:10px">-$'.$OrderRs[0]["auto_discount"].'</span></td>
												</tr>';
												}
												if($OrderRs[0]["quantity_discount"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Quntity Discount :</td>
													<td align="right"><span style="margin-right:10px">-$'.$OrderRs[0]["quantity_discount"].'</span></td>
												</tr>';
												}
												if($OrderRs[0]["reward_discount"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Reward Discount :</td>
													<td align="right"><span style="margin-right:10px">-$'.$OrderRs[0]["reward_discount"].'</span></td>
												</tr>';
												}
												if($OrderRs[0]["coupon_amount"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Coupon Discount :</td>
													<td align="right"><span style="margin-right:10px">-$'.$OrderRs[0]["coupon_amount"].'</span></td>
												</tr>';
												}
												if($OrderRs[0]["gc_amount"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Gift Certifcate Discount :</td>
													<td align="right"><span style="margin-right:10px">-$'.$OrderRs[0]["gc_amount"].'</span></td>
												</tr>';
												}

												if($OrderRs[0]["bogo_discount"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Bogo Discount :</td>
													<td align="right"><span style="margin-right:10px">-$'.$OrderRs[0]["bogo_discount"].'</span></td>
												</tr>';
												}
												if($OrderRs[0]["apply_credit"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Credit Discount :</td>
													<td align="right"><span style="margin-right:10px">-$'.$OrderRs[0]["apply_credit"].'</span></td>
												</tr>';
												}
												if($OrderRs[0]["refer_amount"]>0){
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Refer Discount :</td>
													<td align="right"><span style="margin-right:10px">-$'.$OrderRs[0]["refer_amount"].'</span></td>
												</tr>';
												}
												$html .= '<tr>
													<td align="right" class="admin-text" style="color: #000000;font-size:14px;font-weight: bold;">Order Total :</td>
													<td align="right"><span style="margin-right:10px">$'.$OrderRs[0]["order_total"].'</span></td>
												</tr>
											</table>
										</td>
									</tr>
								</table></td>
							</tr>
					</table>';

			// return PDF::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])->loadView('myaccount.orderdetailpdf', compact('OrderDetailRs', 'OrderRs', 'GC_Only', 'logo'))->download('Invoice-'.$OrderRs->orders_no.'.pdf');

			// echo $html;exit;
			return $html;

		}else{
			$html = "";
			return $html;
		}
	}

	public function checkGiftCertificateItem($switchCase='', $ary=[], $aryValInCaps='', $ordersDetailId=0, $custId=0,$isAmazon='No', $fromFlag='', $checkSku=''){

		$sku = $aryVal = '';

		if($aryValInCaps == '' || $aryValInCaps == 'Yes'){
			$aryVal = "SKU";
		}else{
			$aryVal = "sku";
		}

		if(isset($checkSku) && $checkSku != ''){
			$sku = $checkSku;
		}
		else{
			if(is_object($ary)){
				$ary = json_decode(json_encode($ary, true));
				$sku = $ary->$aryVal;
			}else{
				$ary = $ary;
				$sku = $ary[$aryVal];
			}
		}

		switch($switchCase)
		{
			case 'IsGiftCertificateItem' :

				$isGiftCertificateItem = 'No';

				if($sku == config('global.GIFT_CERTIFICATE_SKU') || $sku == config('global.GIFT_CERTIFICATE_SKU1') || $sku == config('global.GIFT_CERTIFICATE_SKU2') || $sku == config('global.GIFT_CERTIFICATE_SKU3')){
					$isGiftCertificateItem = 'Yes';
				}
				else{
					$isGiftCertificateItem = 'No';
				}
				return $isGiftCertificateItem;
				break;
			case 'SetGiftCertificateImageURL' :
				$GC_IMAGE_URL = "";

                if($sku == config('global.GIFT_CERTIFICATE_SKU')){
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL');
				}
                else if($sku == config('global.GIFT_CERTIFICATE_SKU1')){
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL1');
				}
                else if($sku == config('global.GIFT_CERTIFICATE_SKU2')){
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL2');
				}
				else if($sku == config('global.GIFT_CERTIFICATE_SKU3')){
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL3');
				}
				return $GC_IMAGE_URL;
				break;
			case 'InsertGiftCertificateInDB' :
				do
				{
					$status = '0';

					$minimum_purchase_value = '0.00';

					$date = date('Y-m-d');
					$newdate = date('Y-m-d', strtotime($date. ' + 1 years'));
					$expiry_date = $newdate;

					$GCInsert =	array(
						'customer_id' 							=> $custId,
						'orders_detail_id' 					=> $ordersDetailId,
						'gc_code' 								=> GCGenerateCode(),
						'gc_value' 								=> $ary['Price'],
						'minimum_purchase_value' => $minimum_purchase_value,
						'remaining_value' 					=> $ary['Price'],
						'recipient_name' 					=> $ary['RecipientName'],
						'recipient_email' 					=> $ary['RecipientEmail'],
						'subject' 									=> (isset($ary['Subject']))?$ary['Subject']:'',
						'message' 								=> (isset($ary['Message']))?$ary['Message']:'',
						'expiry_date' 							=> date("Y-m-d", strtotime($expiry_date)),
						'your_name' 							=> $ary['YourName'],
						'your_email' 							=> $ary['YourEmail'],
						'giftimage'								=> $ary['GiftImage'],
						'giftsku'									=> $sku,
						'deliverydate'							=> date("Y-m-d", strtotime($ary['DeliveryDate'])),
						'status' 									=> $status,
						'is_email'								=> 'No'
					);

					$gc_id = GiftCertificate::create($GCInsert) ;
				}
				while($gc_id == false);
				return $gc_id;
				break;
			case 'SetGiftCertificateImage' :
				$thumb_image = "";
				if($fromFlag == 'UpdatePhoneorderInvoice'){
					if($sku== config('global.GIFT_CERTIFICATE_SKU')){
						$thumb_image	='<img src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/'.config('global.GC_IMAGE_URL').'" border="0" width="125" >';
					}
					else if($sku== config('global.GIFT_CERTIFICATE_SKU1')){
						$thumb_image	='<img src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/'.config('global.GC_IMAGE_URL1').'" border="0" width="125" >';
					}
					else if($sku== config('global.GIFT_CERTIFICATE_SKU2')){
						$thumb_image	='<img src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/'.config('global.GC_IMAGE_URL2').'" border="0" width="125" >';
					}
					else if($sku== config('global.GIFT_CERTIFICATE_SKU3')){
						$thumb_image	='<img src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/'.config('global.GC_IMAGE_URL3').'" border="0" width="125" >';
					}
				}
				else{
					if($sku== config('global.GIFT_CERTIFICATE_SKU'))
					{
						$thumb_image	='<img src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/'.config('global.GC_IMAGE_URL').'" width="125" border="0" class="img-resp-75" />';
					}
					else if($sku == config('global.GIFT_CERTIFICATE_SKU1'))
					{
						$thumb_image	='<img src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/'.config('global.GC_IMAGE_URL1').'" width="125" border="0" class="img-resp-75" />';
					}
					else if($sku == config('global.GIFT_CERTIFICATE_SKU2'))
					{
						$thumb_image	='<img src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/'.config('global.GC_IMAGE_URL2').'" width="125" border="0" class="img-resp-75" />';
					}
					else if($sku == config('global.GIFT_CERTIFICATE_SKU3'))
					{
						$thumb_image	='<img src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/'.config('global.GC_IMAGE_URL3').'" width="125" border="0" class="img-resp-75" />';
					}
				}
				return $thumb_image;
				break;
			default :
				return 0;
				break;
		}
	}
public function PhoneorderPaymentSuccess($payment_mode)
    {	//payment_mode = Stripe,Afterpay
		$OrderID = Session::get('phoneorder_detail.order_id');

		$IsGiftCertificateItem = '';

		// if($success ==1){

			$OrderRS = Order::where('orders_id', '=', $OrderID)
						->get();
			// echo "<pre>";print_r($OrderRS);exit;

			if($OrderRS->count() <= 0)
			{
				$err_msg = "Something went wrong, payment failed.";
				$res_arr['success'] = 0;
				$res_arr['err_msg'] = $err_msg;
				return $res_arr;
			}

			$OrderDetailRs = OrderDetail::where('orders_id', '=', $OrderID)
						->get();

			if(isset($OrderRS[0]->gc_code) && $OrderRS[0]->gc_code != "")
			{
            $gcRES = GiftCertificate::where('gc_code','=',$OrderRS[0]->gc_code)->where('status','=','1')->get();
            if($gcRES && $gcRES->count() > 0)
            {
                $gc_remaining_value = NumberFormat($GiftCardRes[0]->remaining_value);

                if($OrderRS[0]->gc_code != '' && $OrderRS[0]->gc_amount > 0 )
                {
                    $upgGif = array (
                                    'remaining_value' => $gc_remaining_value,
                                    'last_used_date'  => date('Y-m-d H:i:s')
                                );
                    $udpGift = GiftCertificate::where('gc_code','=',$OrderRS[0]->gc_code)->update($upgGif);
                }
                $freeshippinginfo = '';
                if(config('Settings.FREESHIPPING_VALUE')!="" && config('Settings.FREESHIPPING_VALUE') > 0)
                {
                    $freeshippinginfo .= '<strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders';
                }
                $gcRESNew = GiftCertificate::where('gc_code','=',$OrderRS[0]->gc_code)->where('status','=','1')->get();

                $res_mail = GetMailTemplate("GC_USAGE");
                $to_recipient = $gcRES[0]['recipient_email'];
                $GC_Subject = $res_mail[0]['subject'];
                $GC_Subject = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$GC_Subject);

                $GCMailBody = $res_mail[0]['mail_body'];
                $GCMailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$GCMailBody);
                $GCMailBody = str_replace('{$recipient_name}',$gcRES[0]['recipient_name'],$GCMailBody);
                $GCMailBody = str_replace('{$gc_code}',$OrderRS[0]->gc_code,$GCMailBody);
                $GCMailBody = str_replace('{$gc_amount}',$OrderRS[0]->gc_amount,$GCMailBody);
                $GCMailBody = str_replace('{$remaining_value}',$gc_remaining_value,$GCMailBody);
                $GCMailBody = str_replace('{$TOLL_FREE_NO}',config('Settings.CONTACT_PHONE_NO'),$GCMailBody);
                $GCMailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$GCMailBody);
                $GCMailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$GCMailBody);
                $GCMailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$GCMailBody);

                $this->PageData["recipient_name"] = $gcRES[0]['recipient_name'];
                $this->PageData["gc_code"] = $OrderRS[0]->gc_code;
                $this->PageData["gc_amount"] = $OrderRS[0]->gc_amount;
                $this->PageData["remaining_value"] = $gcRESNew[0]['remaining_value'];
                $this->PageData["TOLL_FREE_NO"] = config('global.CONTACT_PHONE_NO');
                $this->PageData['Site_URL'] = config('global.SITE_URL');
                $this->PageData["freeshippinginfo"] = $freeshippinginfo;
                if(config('global.OMNISEND_PROG') == false)
                {
                    SendMail($GC_Subject,  $GCMailBody, $to_recipient, config('Settings.ADMIN_MAIL'));
                } else {
                   /** OMANISEND **/
                    $OtherData = ['gc_code' => $OrderRS[0]->gc_code, 'gc_amount' => $OrderRS[0]->gc_amount, 'remaining_value' => $gcRESNew[0]['remaining_value']];
                    OmanisendRequest('61fbcf88bf58ef001efc0243',$gcRES[0],$OtherData);
                    /** OMANISEND **/
                }
            }
        }

			$updAray = array (
							'phoneorder_paymentdate' => date("Y-m-d H:i:s")
						 );
			$updOrder = Order::where('orders_id','=',$OrderID)->update($updAray);

			$res_client = Customer::select('customer_id','iRewardpoint','referenced_by','email','registration_type','status')
								->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->where('status', '=', '1')
								->limit(1)->get();

			#### Deduct product stock Start #####

			 if($OrderRS[0]->pay_status == 'Paid')
				{
					for($n=0;$n<count($OrderDetailRs);$n++)
					{
						$this->ProductDeductStock($OrderDetailRs[$n]["sku"],$OrderDetailRs[$n]["quantity"],$OrderDetailRs[$n]["IsCosmo"],$OrderDetailRs[$n]["IsNandansons"],$OrderDetailRs[$n]["IsPerfumePW"],$OrderDetailRs[$n]["IsPCA"],$OrderDetailRs[$n]["IsND"],$OrderDetailRs[$n]["VendorSKU"]);
					}

				}
				else if($OrderRS[0]->payment_type=="PAYMENT_STRIPE")
				{
					for($n=0;$n<count($OrderDetailRs);$n++)
					{
						$this->ProductDeductStock($OrderDetailRs[$n]["sku"],$OrderDetailRs[$n]["quantity"],$OrderDetailRs[$n]["IsCosmo"],$OrderDetailRs[$n]["IsNandansons"],$OrderDetailRs[$n]["IsPerfumePW"],$OrderDetailRs[$n]["IsPCA"],$OrderDetailRs[$n]["IsND"],$OrderDetailRs[$n]["VendorSKU"]);

					}
				}

			#### Deduct product stock End #####
			$Site_URL = config('global.SITE_URL');
			$STR_EMAIL_ITEM = '';
			$topmenubar = '<table cellpadding="0" cellspacing="0" width="100%" border="0" style="background-color:#2d2d2d;">
											<tr align="center">
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'fragrances/cid/1" style="color:#fff; text-decoration:none; padding:8px 0px; display:block; text-transform:uppercase;">Fragrances</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'skincare/cid/18" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Skincare</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'pocket-perfume/cid/68" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Pocket Perfume</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'bath-body/cid/12" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Bath &amp; Body</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'candles/cid/208" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Candles</a></td>
												<td><a href="'.$Site_URL.'offers.html" style="color:#ff0000; text-decoration:none; padding:5px; display:block;text-transform:uppercase;">SALES & OFFERS</a></td>
											</tr>
										</table>';
				$STR_EMAIL_ITEM .= '<table cellpadding="0" cellspacing="0" width="100%" border="0"><tr align="center" valign="top"><td style="background-color:#e5e5e5; padding:5px;"><strong>Gift Wrap</strong></td><td style="background-color:#e5e5e5; padding:5px;"><strong>Images</strong></td><td style="background-color:#e5e5e5; padding:5px;" align="left"><strong>Your Order Summary</strong></td><td style="background-color:#e5e5e5; padding:5px;"><strong>Quantity</strong></td><td style="background-color:#e5e5e5; padding:5px;" align="right"><strong>Price</strong></td></tr>';
				$TotalProducts = 0;
				$is_gift_wrap = "No";
				for($n=0;$n < $OrderDetailRs->count(); $n++)
				{

						$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$OrderDetailRs[$n],'No');

						if($IsGiftCertificateItem == 'Yes'){
							$thumb_image = $this->checkGiftCertificateItem('SetGiftCertificateImage',$OrderDetailRs[$n],'No');
						}
						else{
							$prod_res = Products::select('image')
								->where('sku', '=', $OrderDetailRs[$n]['sku'])
								->limit(1)->get();

							$image_name= $prod_res[0]['image'];

							if(file_exists(config('global.PRD_THUMB_IMG_PATH').$image_name) and !empty($image_name))
								$prod_image = config('global.PRD_THUMB_IMG_URL').$image_name;
							else
								$prod_image = config('global.NO_IMAGE_THUMB');

							$thumb_image	='<img src="'.$prod_image.'" width="125" border="0" class="img-resp-75" />';
						}

						$checked = '';
						if($OrderDetailRs[$n]['is_gift_wrap']=='Yes')
						{ $checked = 'checked="checked" ';$is_gift_wrap = "Yes";}

						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td valign="middle" style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><input type="checkbox"  disabled="disabled" '.$checked.' /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;">'.$thumb_image.'</a></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="left"><p style="color:#000; margin:0px;"><strong>'.$OrderDetailRs[$n]['product_name'].'</strong></p><p>SKU:'.$OrderDetailRs[$n]['sku'].'</p>';

						$STR_EMAIL_ITEM .= '</td>';
						$STR_EMAIL_ITEM .= '<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><strong>'.$OrderDetailRs[$n]['quantity'].'</strong></td>
						<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8;" align="right"><strong>$'.$OrderDetailRs[$n]['price'].'</strong></td>
						</tr>';

						$TotalProducts = (int)$TotalProducts + (int)$OrderDetailRs[$n]['quantity'];
				}

				if($is_gift_wrap == 'Yes')
				{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Gift Wrap:</strong></td><td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">Yes</td></tr>';
				}

				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong> Total item purchased:</strong></td>
					<td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">'.$TotalProducts.'</td>
				</tr>';

				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Subtotal:</td>
					<td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['sub_total'].'</td>
				</tr>';

				if($OrderRS[0]["shipping_amt"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Charge:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['shipping_amt'].'</td></tr>';
				}

				if($OrderRS[0]["tax"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Sales Tax:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['tax'].'</td></tr>';
				}

				if($OrderRS[0]["gift_charge"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Wrap Charge :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['gift_charge'].'</td></tr>';
				}

				if($OrderRS[0]["auto_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Auto Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['auto_discount'].'</td></tr>';
				}

				if($OrderRS[0]["quantity_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Quantity Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['quantity_discount'].'</td></tr>';
				}

				if($OrderRS[0]["coupon_amount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Coupon Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['coupon_amount'].'</td></tr>';
				}

				if($OrderRS[0]["gc_amount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Certificate Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['gc_amount'].'</td></tr>';
				}

				if($OrderRS[0]["reward_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Reward Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['reward_discount'].'</td></tr>';
				}

				// if($OrderRS[0]["refer_amount"]>0)
				// {
					// $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">'.$AUTO_REFER_DISCOUNT.' :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['refer_amount'].'</td></tr>';
				// }

				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Order Total:</strong></td>
					<td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right"><strong>$'.$OrderRS[0]['order_total'].'</strong></td>
				</tr>';
				$STR_EMAIL_ITEM .= '</table>';

				$mres = GetMailTemplate("ORDER_RECEIPT_NEW");
				$mail_content = stripslashes($mres[0]["mail_body"]);

				$freeshippinginfo = '';
				if(config('Settings.FREESHIPPING_VALUE')!="")
				{
					$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
				}

				$mail_content = str_replace('{$freeshippinginfo}', $freeshippinginfo, $mail_content);
				$mail_content = str_replace('{$topmenubar}', $topmenubar, $mail_content);
				$mail_content = str_replace('{$ordereddate}', date("d F, Y",$OrderRS[0]['order_datetime']), $mail_content);
				$mail_content = str_replace('{$ordertotal}', $OrderRS[0]['order_total'], $mail_content);
				$mail_content = str_replace('{$shipinfo}', $OrderRS[0]['shipinfo'], $mail_content);
				$mail_content = str_replace('{$CONTACT_MAIL}', config('Settings.CONTACT_MAIL'), $mail_content);

				$MailBanners = MailBanner::where('status','=','1')->get();

				$Addblock = '';
				if($MailBanners && $MailBanners->count() > 0)
				{
					$Addblock .= ' <td class="flex" valign="top" width="27%"><table width="100%" border="0" cellpadding="0" cellspacing="0">
							  <tbody>';
					foreach($MailBanners as $MailBanner)
					{
						$banner_img = config('global.MAIL_BANNERS_URL').$MailBanner->mail_banner_image.".jpg";
						$banner_link = $MailBanner->mail_banner_link;
						$Addblock .= ' <tr class="halftd">
											<td style="padding:5px;border:1px solid #e8e8e8" align="center"><a href="'.$banner_link.'"  target="_blank"><img src="'.$banner_img.'" alt="" class="img-responsive"/></a>
										</td></tr>';
					}
					$Addblock .= '</tbody></table></td>';
				}

				$mail_content = str_replace('{$Addblock}', $Addblock, $mail_content);
				$mail_content = str_replace('{$orders_no}', $OrderRS[0]['orders_no'], $mail_content);
				$mail_content = str_replace('{$order_datetime}', date("d F, Y",$OrderRS[0]['order_datetime']), $mail_content);
				$mail_content = str_replace('{$order_total}', $OrderRS[0]['order_total'], $mail_content);
				$mail_content = str_replace('{$shipinfo}', $OrderRS[0]['shipinfo'], $mail_content);
				//new

				$mail_content = str_replace('{$orders_id}', $OrderRS[0]['orders_id'], $mail_content);
				$BillAddress = $OrderRS[0]['bill_first_name'].' '.$OrderRS[0]['bill_last_name']."<br>";
				if($OrderRS[0]['bill_address2'] != '')
					$BillAddress.= $OrderRS[0]['bill_address1'].', '.$OrderRS[0]['bill_address2']."<br>";
				else
					$BillAddress.= $OrderRS[0]['bill_address1'].',<br>';
				$BillAddress.=$OrderRS[0]['bill_city'].', '.$OrderRS[0]['bill_state']."<br>";
				$BillAddress.=$OrderRS[0]['bill_zip'].' - '.$OrderRS[0]['bill_country'];

				$mail_content = str_replace('{$bill_address}',$BillAddress,$mail_content);

				$ShipAddress = $OrderRS[0]['ship_first_name'].' '.$OrderRS[0]['ship_last_name']."<br>";
				if($OrderRS[0]['ship_address2'] != '')
					$ShipAddress.= $OrderRS[0]['ship_address1'].', '.$OrderRS[0]['ship_address2']."<br>";
				else
					$ShipAddress.= $OrderRS[0]['ship_address1'].',<br>';
				$ShipAddress.=$OrderRS[0]['ship_city'].', '.$OrderRS[0]['ship_state']."<br>";
				$ShipAddress.=$OrderRS[0]['ship_zip'].' - '.$OrderRS[0]['ship_country'];

				$mail_content = str_replace('{$ship_address}',$ShipAddress,$mail_content);

				$mail_content = str_replace('{$STR_EMAIL_ITEM}',  $STR_EMAIL_ITEM, $mail_content);
				$mail_content = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$mail_content);
				$mail_content = str_replace('{$TOLL_FREE_NO}', config('global.CONTACT_PHONE_NO'), $mail_content);
				$mail_content = str_replace('{$Site_URL}', $Site_URL, $mail_content);
				$mail_content = str_replace('{$SITE_NAME}', config('global.SITE_TITLE'), $mail_content);

				$mail_subject = str_replace('{$SITE_NAME}', config('Settings.SITE_TITLE'), $mres[0]['subject']);
				$mail_subject = str_replace('{$OrderRs.orders_no}', $OrderRS[0]['orders_no'], $mail_subject);
				//$onesendstat = $generalobj->SMTP_Mail_Send($OrderRS[0]['bill_email'],$mail_subject, $mail_content, CONTACT_MAIL);

				$shipping_signature = '';
				$shipping_sign = '';
				if(isset($OrderRS[0]->is_shipping_signature) && $OrderRS[0]->is_shipping_signature != '')
				{
					$shipping_signature = 'ON';
					$shipping_sign = 'Y';
					if($OrderRS[0]->is_shipping_signature == 'No'){
						$shipping_signature = 'OFF';
						$shipping_sign = 'N';
					}
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Signature ('.$shipping_signature.') :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRS[0]['shipping_signature']).'</td></tr>';
				}

				$shipping_insurance = 'N';
				if(!empty($OrderRS[0]["route_shipping_insurance_charge"]) && $OrderRS[0]["route_shipping_insurance_charge"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Insurance* :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRS[0]['route_shipping_insurance_charge']).'</td></tr>';
					$shipping_insurance = 'Y';
				}

				//$OrderRS[0]['bill_email']  = "qqualdev@gmail.com";
                if(config('global.OMNISEND_PROG') == false)
                {
                    SendMail($mail_subject,  $mail_content, $OrderRS[0]['bill_email'], config('Settings.ADMIN_MAIL'));
                } else if($OrderRS[0]->paystatus== 'Paid' && $OrderRS[0]->payment_type=="PAYMENT_STRIPE") {
                    /** OMANISEND **/
                    //$OtherData = ['toMail' => $OrderRS[0]['bill_email'], 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM];
					$OtherData = ['toMail' => $OrderRS[0]['bill_email'], 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customer_ip' => $OrderRS[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign];
                    OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRS[0],$OtherData);
                    /** OMANISEND **/
                }

				$err_msg = "Thank you for your payment. Your order will be processed as soon as possible. An Order Receipt E-mail has been sent to you.";

				// Session::flash('success',$err_msg);
				$res_arr['success'] = 1;
				$res_arr['err_msg'] = $err_msg;
				return $res_arr;

		/* }else{
			$err_msg = "Something went wrong, payment failed.";
			$res_arr['success'] = 0;
			$res_arr['err_msg'] = $err_msg;
			return $res_arr;
		} */
	}

	public function newBeaconTracking($event,$data){

		$ch = curl_init();

		if($event == 'pageview'){
			$beacon_url = 'https://analytics.searchspring.net/beacon/v2/faltym/product/pageview';
		}
		else if($event == 'cartAdd'){
			$beacon_url = 'https://analytics.searchspring.net/beacon/v2/faltym/cart/add';
		}
		else if($event == 'order'){
			$beacon_url = 'https://analytics.searchspring.net/beacon/v2/faltym/order/transaction';
		}

		$beacon_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
		$beacon_current_url = $beacon_protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$beacon_pageLoadId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);

		$beacon_newSessionId = $beacon_userId = '';

		if (isset($_COOKIE['ssSessionId'])) {
			$beacon_newSessionId = $_COOKIE['ssSessionId'];
		} else {
			// Fallback: If it doesn't exist (e.g., first visit), generate a new unique ID
			$beacon_newSessionId = bin2hex(random_bytes(16)); // Generates a 32-character hex string

			// Set the cookie for future requests (expires in 2 hours, or adjust as needed)
			setcookie('ssSessionId', $beacon_newSessionId, time() + 7200, "/");
		}

		if (isset($_COOKIE['ssUserId'])) {
			$beacon_userId = $_COOKIE['ssUserId'];
		}else{
			$beacon_userId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				mt_rand(0, 0xffff), mt_rand(0, 0xffff),
				mt_rand(0, 0xffff),
				mt_rand(0, 0x0fff) | 0x4000,
				mt_rand(0, 0x3fff) | 0x8000,
				mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
			);
			setcookie('ssUserId', $beacon_userId, time() + (365 * 24 * 60 * 60), '/');
		}

		$payload = [
			"context" => [
				"IP" => $_SERVER['REMOTE_ADDR'],
				"userAgent" => $_SERVER['HTTP_USER_AGENT'] ?? '',
				"timestamp" => now()->toIso8601ZuluString('millisecond'),
				"pageUrl" => $beacon_current_url ?? '',
				"userId" => $beacon_userId ?? '',
				"sessionId" => $beacon_newSessionId ?? '',
				"pageLoadId" => $beacon_pageLoadId ?? '',
				"shopperId" => "'".Session::get('sess_icustomerid')."'" ?? '',
				"initiator" => "searchspring/custom/1.0",
				"currency" => [
					"code" => "USD"
				],
				//"dev" => true,//comment this on live
			],
			"data" => $data
		];

		//echo "<pre>";print_r(json_encode($data));echo "</pre>";
		//echo "<pre>";print_r(json_encode($payload));echo "</pre>";

		curl_setopt_array($ch, [
			CURLOPT_URL            => $beacon_url,
			CURLOPT_POST           => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS     => json_encode($payload),
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/json',
				'Accept: application/json'
			],
		]);

		$response = curl_exec($ch);
		//echo "Beacon API Response - <pre>";print_r($response);echo "</pre>";
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		//echo "<pre>";print_r($statusCode);echo "</pre>";
	}

}
