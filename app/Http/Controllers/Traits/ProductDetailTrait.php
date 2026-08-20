<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

use App\Models\Products;
use App\Models\ProductsReview;
use App\Models\MarkupPrices;
use App\Models\RewardRule;
use App\Models\BogoDiscount;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\Log;
use DateTime;
use DB;
use Session;
use cache;

trait ProductDetailTrait
{
	use CommonTrait;

	public function getProductCategories($products_id = 0){
		$ProdCatsData = DB::table('pu_products_category as pc')->select(DB::raw('GROUP_CONCAT(pc.category_id) as categories'))->where('pc.products_id', '=', $products_id)->first();
		return $ProdCatsData->categories;
	}

	public function getProductsDetail($products_id, $preview = '', $code = '', $resize = '')
	{
		$CatProdsQry = DB::table('pu_products as p')
			->select(
				'p.products_id',
				'p.sku',
				'p.product_name',
				'p.cosmo_sku',
				'p.pca_sku',
				'p.pca_our_price',
				'p.pca_wholesale_price',
				'p.pca_current_stock',
				'p.cosmo_our_price',
				'p.nandansons_our_price',
				'p.perfumeworldwide_our_price',
				'p.cosmo_wholesale_price',
				'p.nandansons_wholesale_price',
				'p.perfumeworldwide_wholesale_price',
				'p.perfumeworldwide_retail_price',
				'p.cosmo_current_stock',
				'p.nandansons_sku',
				'p.nandansons_current_stock',
				'p.perfumeworldwide_sku',
				'p.perfumeworldwide_currentstock',
				'p.short_description',
				'p.current_stock',
				'p.retail_price',
				'p.cosmo_retail_price',
				'p.pca_retail_price',
				'p.minimum_stock',
				'p.gender',
				'p.product_description',
				'p.imanufactureid',
				'p.add_datetime',
				'po.extra_images',
				'po.youtubelink',
				'p.vtype',
				'p.fragrance_family',
				'p.size',
				'p.formulation',
				'p.coverage',
				'p.finish',
				'p.skin_type',
				'p.is_atomizer',
				'm.vmanufacture',
				'p.variation_id',
				'p.fragrance_occasion',
				'p.fragrance_personality',
				'p.fragrance_seasons',
				'p.product_type',
				'p.gender',
				'p.UPC',
				'po.related_item',
				'm.imglogo',
				'm.brand_history',
				'po.point_multiplier',
				'p.is_gift_wrap',
				'p.image',
				'po.meta_title',
				'po.meta_keyword',
				'po.meta_description',
				'p.maxtwodaydelivery',
				'p.imanufactureid',
				'p.brand_id',
				'pc.category_id',
				'b.brand_name',
				'p.cosmo_sku',
				'p.cosmo_current_stock',
				'p.cosmo_wholesale_price',
				'p.cosmo_our_price',
				'p.pca_sku',
				'p.pca_current_stock',
				'p.pca_wholesale_price',
				'p.pca_our_price',
				'p.nandansons_sku',
				'p.nandansons_current_stock',
				'p.nandansons_wholesale_price',
				'p.nandansons_our_price',
				'p.nandansons_retail_price',
				'p.nd_sku',
				'p.nd_current_stock',
				'p.nd_wholesale_price',
				'p.nd_our_price',
				'p.nd_retail_price',
				'p.wholesale_price',
				'p.our_price',
				'p.sale_price',
				'po.product_story',
				'po.story_video',
				'po.story_youtubelink',
				'po.story_image_one',
				'po.story_image_two',
				'po.story_image_three',
				'po.story_image_four',
                'po.story_image_one_desc',
				'po.story_image_two_desc',
				'po.story_image_three_desc',
				'po.story_image_four_desc',
				'pa.topnote',
				'pa.middlenote',
				'pa.basenote',
				'pa.template',
				'pa.opening',
				'pa.heart',
				'pa.drydown',
				'pa.longevity',
				'pa.sec1_right_image',
				'pa.sec1_image1',
				'pa.sec1_image2',
				'pa.sec1_image3',
				'pa.sec2_banner',
				'pa.sec2_mobile_banner',
				'pa.brand_banner'
			)
			->join('pu_products_one as po', 'p.products_id', '=', 'po.products_id')
			->leftjoin('pu_products_attribute as pa', 'p.products_id', '=', 'pa.products_id')
			->join('pu_products_category as pc', 'p.products_id', '=', 'pc.products_id')
			->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
			->join('pu_brand as b', 'b.brand_id', '=', 'p.brand_id')
			->join('pu_manufacture as m', function ($join) {
				$join->on('p.imanufactureid', '=', 'm.imanufactureid');
				$join->on('b.imanufactureid', '=', 'm.imanufactureid');
			})
			->where('c.status', '=', '1');
		$CatProdsQry->where('p.products_id', '=', $products_id);
		if ($code != '') {

			if ($preview != 1) {
				$CatProdsQry->where('p.status', '=', '2');
				$CatProdsQry->where('po.is_private', '=', 'Yes');
				$CatProdsQry->where('po.private_code', '=', $code);
			}
			else
			{
				$CatProdsQry->where('p.status', '=', '1');
			}
		} else {
			if ($preview != 1) {
				$CatProdsQry->where('p.status', '=', '1');
			}
		}
		$CatProdsQry->groupBy('p.products_id');
		$CatProdsQry = $CatProdsQry->first();

		if ($CatProdsQry) {

			$CatProdsQry = $this->SetProduct($CatProdsQry);
			$CatProdsQry->vmanufacture = remove_html_entities(trim($CatProdsQry->vmanufacture));
			if ($CatProdsQry->vmanufacture != '') {
				$m_name = strtolower($CatProdsQry->vmanufacture);
				$m_name = str_replace("#", "", $m_name);
				$m_name = str_replace("&", "", $m_name);
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace(" ", "-", $m_name);
				$m_name = str_replace("'", "", $m_name);
				$m_name = remove_html_entities($m_name);
				$this->PageData['vmanufacture_link'] = config('global.SITE_URL') . $m_name . "/smid-" . $CatProdsQry->imanufactureid;
			}
			if ($CatProdsQry->brand_name != '' && $CatProdsQry->vmanufacture != '') {
                $CatProdsQry->brand_name = remove_html_entities($CatProdsQry->brand_name);
				//For url name
				$m_name = strtolower($CatProdsQry->vmanufacture);
				$m_name = str_replace("#", "", $m_name);
				$m_name = str_replace("&", "", $m_name);
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace(" ", "-", $m_name);
				$m_name = str_replace("'", "", $m_name);
				$m_name = remove_html_entities($m_name);
				//$CatProdsQry->referencedName = $CatProdsQry->brand_name . ' by ' . $CatProdsQry->vmanufacture;
				//$CatProdsQry->referencedName_main = $CatProdsQry->brand_name . ' by <a target="_blank" href="' . $this->PageData['vmanufacture_link'] . '"><U>' . $CatProdsQry->vmanufacture . '</U></a>';
				$CatProdsQry->referencedName = remove_html_entities(strip_tags($CatProdsQry->brand_name));
				$CatProdsQry->referencedName_main = remove_html_entities(strip_tags($CatProdsQry->brand_name));
			} else {
				$CatProdsQry->referencedName =  remove_html_entities(strip_tags($CatProdsQry->product_name));
				$CatProdsQry->referencedName_main = remove_html_entities(strip_tags($CatProdsQry->product_name));
			}
			$CatProdsQry->vmanufacture_link = $this->PageData['vmanufacture_link'];
			if ($resize == "resize") {
				$rezimg = $CatProdsQry->image;
				$rezimg = str_replace(".jpg", "_resize.jpg", $rezimg);

				if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProdsQry->image) and $CatProdsQry->image != '') {
					$CatProdsQry->large_image = config('global.PRD_LARGE_IMG_URL') . $rezimg;
				} else {
					$CatProdsQry->large_image = config('global.NO_IMAGE_LARGE');
				}

				if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$CatProdsQry->mainImage = config('global.PRD_MEDIUM_IMG_URL') . $rezimg;
				} else {
					$CatProdsQry->mainImage  = config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
					$CatProdsQry->isimage  = true;
				}

				######## change added
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$CatProdsQry->thumb_image = config('global.PRD_THUMB_IMG_URL') . $rezimg;
				} else {
					$CatProdsQry->thumb_image =  config('global.NO_IMAGE_THUMB');
				}
			} else {
				if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$newimageVal = config('global.PRD_LARGE_IMG_PATH') . stripslashes($CatProdsQry->image);
					$verP = filemtime($newimageVal);
					$CatProdsQry->large_image = config('global.PRD_LARGE_IMG_URL') . $CatProdsQry->image . "?ver=" . $verP;
				} else {
					$CatProdsQry->large_image = config('global.NO_IMAGE_LARGE');
				}

				if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$newimageVal = config('global.PRD_MEDIUM_IMG_PATH') . stripslashes($CatProdsQry->image);
					$verP = filemtime($newimageVal);
					$CatProdsQry->mainImage  = config('global.PRD_MEDIUM_IMG_URL') . $CatProdsQry->image . "?ver=" . $verP;
				} else {
					$CatProdsQry->mainImage =  config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
					$CatProdsQry->isimage  = true;
				}

				####### change added
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProdsQry->image);
					$verP = filemtime($newimageVal);
					$CatProdsQry->thumb_image = config('global.PRD_THUMB_IMG_URL') . $CatProdsQry->image . "?ver=" . $verP;
				} else {
					$CatProdsQry->thumb_image = config('global.NO_IMAGE_THUMB');
				}
			}

			if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '')
			{
				$CatProdsQry->mainImage = $CatProdsQry->large_image;
			}
			else if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '')
			{
				$CatProdsQry->mainImage = $CatProdsQry->mainImage;
			}
			else if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '')
			{
				$CatProdsQry->mainImage = $CatProdsQry->thumb_image;
			}

			if (file_exists(config('global.MANUFACTUR_IMAGE_PATH') . $CatProdsQry->imglogo) && trim($CatProdsQry->imglogo ?? '') != '') {
				$newimageVal = config('global.MANUFACTUR_IMAGE_PATH') . stripslashes($CatProdsQry->imglogo);
				$verP = filemtime($newimageVal);
				$CatProdsQry->manufacture_logo = config('global.MANUFACTUR_IMAGE_URL') . $CatProdsQry->imglogo . "?ver=" . $verP;
			} else {
				$CatProdsQry->manufacture_logo = '';
			}

			$CatProdsQry->referenced_products = $this->getReferencedProducts($CatProdsQry->products_id, $CatProdsQry->variation_id, $code, $resize);

			$CatProdsQry->referenced_products_keys =  array_keys($CatProdsQry->referenced_products);

            $CatProdsQry->related_item = [];
            /*
			if ($CatProdsQry->related_item != "") {
				$CatProdsQry->related_item = $this->GetSliderProducts($CatProdsQry->related_item, '', '', '', '');
			} else {
				$CatProdsQry->related_item = $this->GetSliderProducts($this->fetchSameManufactureProduct($CatProdsQry->imanufactureid, $CatProdsQry->gender, $CatProdsQry->products_id), '', '', '', '');
			}
            */

			// $CatProdsQry->product_url = $this->getProductRewriteURL($CatProdsQry->products_id, $CatProdsQry->product_name, $CatProdsQry->category_id, $CatProdsQry->vmanufacture);
			$CatProdsQry->product_url = SetProductURL($CatProdsQry->products_id, $CatProdsQry->product_name, $CatProdsQry->category_id);

			######### Extra images start
			$extraImgArr = array();
			$extraImgArrNew = [];
			if ($CatProdsQry->extra_images != '') {
				$extraImgArr = explode("#", $CatProdsQry->extra_images);
				$TotalExtraImages = count($extraImgArr);
				if ($TotalExtraImages > 0) {
					$pr = 0;
					for ($k = 0; $k < $TotalExtraImages; $k++) {
						if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $extraImgArr[$k]) && $extraImgArr[$k] != '') {
							$newimageVal = config('global.PRD_LARGE_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Meduim_Image = config('global.PRD_LARGE_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$newimageVal = config('global.PRD_LARGE_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Large_Image = config('global.PRD_LARGE_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$pr++;
							array_push($extraImgArrNew, (object)['Meduim_Image' => $Meduim_Image, 'Large_Image' => $Large_Image]);
						}
						else if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $extraImgArr[$k]) && $extraImgArr[$k] != '') {
							$newimageVal = config('global.PRD_MEDIUM_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Meduim_Image = config('global.PRD_MEDIUM_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$newimageVal = config('global.PRD_MEDIUM_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Large_Image = config('global.PRD_MEDIUM_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$pr++;
							array_push($extraImgArrNew, (object)['Meduim_Image' => $Meduim_Image, 'Large_Image' => $Large_Image]);
						}
						else if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $extraImgArr[$k]) && $extraImgArr[$k] != '') {
							$newimageVal = config('global.PRD_THUMB_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Meduim_Image = config('global.PRD_THUMB_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$newimageVal = config('global.PRD_THUMB_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Large_Image = config('global.PRD_THUMB_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$pr++;
							array_push($extraImgArrNew, (object)['Meduim_Image' => $Meduim_Image, 'Large_Image' => $Large_Image]);
						}
					}
				}
			}
			$CatProdsQry->extra_images = $extraImgArrNew;
			############ Extra images end

			########### you save and you save price start ###############
			$retail_price = $CatProdsQry->retail_price;
			$yousave = 0;
			if ($retail_price != '' && $retail_price != '0.00')
				$yousave = ($retail_price - $CatProdsQry->product_price) / $retail_price;
			$yousave = $yousave * 100;
			$yousave = number_format($yousave, 0);
			if(!empty($CatProdsQry->product_price)){
				$yousaveprice = $retail_price - (float)$CatProdsQry->product_price;
			}else{
				$yousaveprice = 0;
			}

			$CatProdsQry->yousave = $yousave;
			$CatProdsQry->yousaveprice = $yousaveprice;
			############# you save and you save price end #######################

			$CatProdsQry->PromotionBanner = $this->GetBanner(array('PRODUCT BANNER'), $CatProdsQry->imanufactureid, $CatProdsQry->sku);
			if ($CatProdsQry->PromotionBanner) {
				$CatProdsQry->PromotionBanner = $CatProdsQry->PromotionBanner['PRODUCT_BANNER'][0];
			}

			if (isset($CatProdsQry->range_price_counter) > 1) {
				$CatProdsQry->range_price = 1;
			} else {
				$CatProdsQry->range_price = 0;
			}

			$CatProdsQry->short_description = strip_tags($CatProdsQry->short_description);

			$CatProdsQry->short_description = remove_html_entities($CatProdsQry->short_description);

			$CatProdsQry->product_description = str_replace('\r\n','', $CatProdsQry->product_description);

			$CatProdsQry->product_description = remove_html_entities($CatProdsQry->product_description);

			$IsCosmo = "No";
			$IsNandansons = "No";
			$IsPerfumePW  = "No";
			$IsPCA  = "No";
			$IsND  = "No";
			$VendorSKU = "";
			$pca_our_price = 0;
			if (Session::has('eusertype') && Session::get('eusertype') && strtolower(Session::get('eusertype')) != 'wholesaler') {
				$cosmo_our_price = $CatProdsQry->cosmo_our_price;
				$nandansons_our_price = $CatProdsQry->nandansons_our_price;
				$perfumeworldwide_our_price = $CatProdsQry->perfumeworldwide_our_price;
				$pca_our_price = $CatProdsQry->pca_our_price;
				$nd_our_price = $CatProdsQry->nd_our_price;
			} else {
				$cosmo_our_price = $CatProdsQry->cosmo_wholesale_price;
				$nandansons_our_price = $CatProdsQry->nandansons_wholesale_price;
				$perfumeworldwide_our_price = $CatProdsQry->perfumeworldwide_wholesale_price;
				$pca_our_price = $CatProdsQry->pca_wholesale_price;
				$nd_our_price = $CatProdsQry->nd_wholesale_price;
			}

			############### stock condition start ########################
			//echo $CatProdsQry->WebsiteStock; exit;
			if (isset($CatProdsQry->WebsiteStock) && $CatProdsQry->WebsiteStock == "Out") {
				if ($CatProdsQry->cosmo_sku != '' &&  $CatProdsQry->cosmo_current_stock > 0 &&  $cosmo_our_price > 0) {
					$IsCosmo = "Yes";
					$VendorSKU = $CatProdsQry->cosmo_sku;
				} else if ($CatProdsQry->pca_sku != '' &&  $CatProdsQry->pca_current_stock > 0 && $pca_our_price > 0) {
					$IsPCA  = "Yes";
					$VendorSKU = $CatProdsQry->pca_sku;
				} else if ($CatProdsQry->nandansons_sku != '' &&  $CatProdsQry->nandansons_current_stock > 0 && $nandansons_our_price > 0) {
					$IsNandansons = "Yes";
					$VendorSKU = $CatProdsQry->nandansons_sku;
				}
				else if ($CatProdsQry->perfumeworldwide_sku != '' &&  $CatProdsQry->perfumeworldwide_currentstock > 0 && $perfumeworldwide_our_price > 0) {
					$IsPerfumePW  = "Yes";
					$VendorSKU = $CatProdsQry->perfumeworldwide_sku;
				}
				else if ($CatProdsQry->nd_sku != '' &&  $CatProdsQry->nd_current_stock > 0 && $nd_our_price > 0) {
					$IsND  = "Yes";
					$VendorSKU = $CatProdsQry->nd_sku;
				}
			}
			################### stock condition end ########################

			$CatProdsQry->IsCosmo = $IsCosmo;
			$CatProdsQry->IsNandansons = $IsNandansons;
			$CatProdsQry->IsPerfumePW = $IsPerfumePW;
			$CatProdsQry->IsPCA = $IsPCA;
			$CatProdsQry->IsND = $IsND;

			$CatProdsQry->SaleIcon = '';
			$CatProdsQry->sale_item = '0';
			if ($CatProdsQry->sale_price > 0 && strtolower(Session::get('eusertype')) != 'wholesaler') {
				$CatProdsQry->sale_item = '1';
			}

			if (isset($CatProdsQry->sale_item) && $CatProdsQry->sale_item == 1) {
				$CatProdsQry->SaleIcon = $this->getIconsSaleDeal("No", "Yes");
			}

			$CatProdsQry->GiftwrapIcon = '';
			if ($CatProdsQry->is_gift_wrap == "Yes") {
				$CatProdsQry->GiftwrapIcon = $this->getIconsSaleDeal("No", "No", "Yes");
			}
		}

		return $CatProdsQry;
	}

	function fetchSameManufactureProduct($imanufactureid, $gender, $prod_id)
	{
		$proData  = DB::table('pu_products as p')
			->select('p.sku')
			->where('p.imanufactureid', '=', $imanufactureid)
			->where('p.gender', '=', $gender)
			->where('p.products_id', '!=', $prod_id)
			->where('p.status', '=', 1)
			->inRandomOrder()->offset(0)->limit(20)->get();

		$prdSKUs = "";
		for ($m = 0; $m < count($proData); $m++) {
			$prdSKUs .= $proData[$m]->sku . "#";
		}
		return substr($prdSKUs, 0, -1);
	}

	function getIconsSaleDeal($isdeal = "No", $issale = "No", $isGift = 'No', $width = "100", $height = "20")
	{
		$Iconval  = "";
		if ($isdeal == "Yes") {
			$Iconval = '<svg class="sv sv-topdeal" aria-hidden="true" role="img" width="' . $width . '" height="' . $height . '"><use href="#sv-topdeal" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-topdeal"></use></svg>';
		}
		if ($issale == "Yes") {
			$Iconval = '<svg class="sv sv-sale" aria-hidden="true" role="img"  width="' . $width . '" height="' . $height . '"><use href="#sv-sale" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-sale"></use></svg>';
		}
		if ($isGift == "Yes") {
			$Iconval = '<svg class="sv sv-giftwrap" aria-hidden="true" role="img" width="60" height="60"><use href="#sv-giftwrap" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-giftwrap"></use></svg>';
		}

		return $Iconval;
	}

	function GetBanner($bannertype, $imanufactureid = '', $sku = '')
	{
		global $Site_URL;
		$bannerShow = "No";
		$mainbannerres = array();
		if(isset($bannertype[0]) && $bannertype[0]=="PRODUCT BANNER")
		{
			if(!Cache::has('PRODUCTBANNERCache'))
			{
				$mainBannerQuery = DB::table('pu_home_image')
				->select('home_image', 'mobile_image', 'position', 'title', 'link', 'image_alt', 'section', 'flag', 'include_sku', 'exclude_sku')
				->whereIn('section', $bannertype)
				->where(function ($qry) {
					$qry->where('start_date', '<=', date('Y-m-d'))->where('end_date', '>=', date('Y-m-d'))->OrWhere(function ($qry) {
						$qry->where('start_date', '=', '0000-00-00')->where('end_date', '>=', '0000-00-00');
					});
				})
				->where('status', '=', '1')
				->orderBy('position');

				$mainbannerres = $mainBannerQuery->get();
				Cache::put('PRODUCTBANNERCache', $mainbannerres);
			}
			else
			{
				$mainbannerres = Cache::get('PRODUCTBANNERCache');
			}
		}
		else
		{
			$mainBannerQuery = DB::table('pu_home_image')
				->select('home_image', 'mobile_image', 'position', 'title', 'link', 'image_alt', 'section', 'flag', 'include_sku', 'exclude_sku')
				->whereIn('section', $bannertype)
				->where(function ($qry) {
					$qry->where('start_date', '<=', date('Y-m-d'))->where('end_date', '>=', date('Y-m-d'))->OrWhere(function ($qry) {
						$qry->where('start_date', '=', '0000-00-00')->where('end_date', '>=', '0000-00-00');
					});
				})
				->where('status', '=', '1')
				->orderBy('position');

			$mainbannerres = $mainBannerQuery->get();
		}
		$arr_banner = array();
		if (count($mainbannerres) > 0) {
			$cnt_banner = count($mainbannerres);
			for ($n = 0; $n < $cnt_banner; $n++) {

				$banner_alt = $mainbannerres[$n]->image_alt;
				$title = $mainbannerres[$n]->title;
				if ($title == "Promotional" && Session::get('eusertype') == "Wholesaler") {
					continue;
				}
				$banner_url = $mainbannerres[$n]->link;
				$banner_url = str_replace('{$Site_URL}', $Site_URL, $banner_url);
				$banner_img = $mainbannerres[$n]->home_image;
				$mobile_banner_img = $mainbannerres[$n]->mobile_image;

				if (!empty($banner_img) && file_exists(config('global.HOME_IMAGE_PATH') . $banner_img)) {
					$banner_img = config('global.HOME_IMAGE_URL') . $banner_img;
				}

				if (!empty($mobile_banner_img) && file_exists(config('global.HOME_IMAGE_PATH') . $mobile_banner_img)) {
					$mobile_banner_img = config('global.HOME_IMAGE_URL') . $mobile_banner_img;
				}
				if ($mainbannerres[$n]->section == "PRODUCT BANNER") {

					$mainbannerres[$n]->exclude_sku = str_replace(", ",",",$mainbannerres[$n]->exclude_sku);
					$mainbannerres[$n]->include_sku = str_replace(", ",",",$mainbannerres[$n]->include_sku);

					$mainbannerres[$n]->exclude_sku = rtrim($mainbannerres[$n]->exclude_sku,",");
					$mainbannerres[$n]->include_sku = rtrim($mainbannerres[$n]->include_sku,",");

					$exclude_skuArr =  explode(",", $mainbannerres[$n]->exclude_sku);
					$include_skuArr	=  explode(",", $mainbannerres[$n]->include_sku);
					$flag 			=  $mainbannerres[$n]->flag;

					if ($flag == "brand") {
						if (in_array($sku, $exclude_skuArr)) {
							continue;
						}
						if (in_array($imanufactureid, $include_skuArr)) {
							$arr_banner[str_replace(" ", "_", $mainbannerres[$n]->section)][0] = array(
								"banner_url"		=> $banner_url,
								"banner"			=> $banner_img,
								"banner_alt"		=> $banner_alt,
								"mobile_banner_img" => $mobile_banner_img,
								"banner_title"		=> $title,
								"section"			=> $mainbannerres[$n]->section
							);
							$bannerShow = "Yes";
						}
					}

					if ($flag == "sku" && $bannerShow == "No") {

						if (in_array($sku, $exclude_skuArr)) {
							continue;
						}
						if (in_array($sku, $include_skuArr)) {

							$arr_banner[str_replace(" ", "_", $mainbannerres[$n]->section)][] = array(
								"banner_url"		=> $banner_url,
								"banner"			=> $banner_img,
								"banner_alt"		=> $banner_alt,
								"mobile_banner_img" => $mobile_banner_img,
								"banner_title"		=> $title,
								"section"			=> $mainbannerres[$n]->section
							);
						}
					}
				} else {
					$arr_banner[str_replace(" ", "_", $mainbannerres[$n]->section)][] = array(
						"banner_url"		=> $banner_url,
						"banner"			=> $banner_img,
						"banner_alt"		=> $banner_alt,
						"mobile_banner_img" => $mobile_banner_img,
						"banner_title"		=> $title,
						"section"			=> $mainbannerres[$n]->section
					);
				}
			}
		}
		return $arr_banner;
	}

	public function getRecent_ViewedItems($current_products_sku)
	{
		$proData  = DB::table('pu_products as p')
			->select('p.products_id', 'p.product_type')
			->whereIn('p.products_id', Session::get('RECENT_VIEWED_ITEMS'))
			->where('p.status', '=', 1)
			->where('p.product_type', '=', 'wholesaler')
			->get();

		if (count($proData) > 0) {
			if (strtolower(Session::get('eusertype')) == 'retailer' || !Session::has('eusertype')) {
				Session::forget('RECENT_VIEWED_ITEMS');
			}
		}

		$arr_recent_product_sku = array();
		if (Session::has('RECENT_VIEWED_ITEMS') and count(Session::get('RECENT_VIEWED_ITEMS')) > 0) {
			$arr_recent_product_sku = array_reverse(Session::get('RECENT_VIEWED_ITEMS'));
		}

		if ($current_products_sku != '') {
			$arr_recent_product_sku = array_diff($arr_recent_product_sku, array($current_products_sku));	// Skip current product
		}

		$arr_recent_item = array();
		if (count($arr_recent_product_sku) == 0) {
			return $arr_recent_item;
		}

		$arr_recent_product_sku = array_slice($arr_recent_product_sku, 0, 20);

		$ProductString = implode('#', $arr_recent_product_sku);

		$recentItem = $this->GetSliderProducts($ProductString, '', '', '', '');

		return $recentItem;
	}

	public function getReferencedProducts($products_id, $variation_id = '', $code, $resize)
	{

		########### return empty array if variation id is null or empty
		if ($variation_id == '' || $variation_id == NULL) {
			return array();
		}
		$isMobile = isMobile();

		$ProdQry = DB::table('pu_products as p')
			->select(
				'p.products_id',
				'p.sku',
				'p.is_gift_wrap',
				'p.short_description',
				'p.product_description',
				'p.UPC',
				'm.imglogo',
				'm.brand_history',
				'p.maxtwodaydelivery',
				'p.fragrance_family',
				'p.formulation',
				'po.extra_images',
				'po.youtubelink',
				'p.size',
				'p.coverage',
				'p.finish',
				'p.skin_type',
				'p.product_name',
				'p.vtype',
				'p.imanufactureid',
				'p.brand_id',
				'p.is_atomizer',
				'p.fragrance_seasons',
				'p.fragrance_occasion',
				'p.fragrance_personality',
				'p.image',
				'p.current_stock',
				'p.retail_price',
				'p.cosmo_retail_price',
				'p.pca_retail_price',
				'p.minimum_stock',
				'p.gender',
				'p.new_arrival',
				'p.featured',
				'p.clearance',
				'p.top_seller',
				'p.product_type',
				'p.cosmo_sku',
				'p.cosmo_current_stock',
				'p.cosmo_wholesale_price',
				'p.cosmo_our_price',
				'p.pca_sku',
				'p.pca_current_stock',
				'p.pca_wholesale_price',
				'p.pca_our_price',
				'p.nandansons_sku',
				'p.nandansons_current_stock',
				'p.nandansons_wholesale_price',
				'p.nandansons_our_price',
				'p.nandansons_retail_price',
				'p.perfumeworldwide_sku',
				'p.perfumeworldwide_currentstock',
				'p.perfumeworldwide_wholesale_price',
				'p.perfumeworldwide_our_price',
				'p.perfumeworldwide_retail_price',

				'p.nd_sku',
				'p.nd_current_stock',
				'p.nd_wholesale_price',
				'p.nd_our_price',
				'p.nd_retail_price',

				'p.wholesale_price',
				'p.our_price',
				'p.sale_price',
				'p.vtype',
				'p.variation_id',
				'm.vmanufacture',
				'p.product_type',
				'b.brand_name',
				'pc.category_id',
				'c.category_name',
				'c.parent_id',
				'pa.topnote',
				'pa.middlenote',
				'pa.basenote',
				'pa.template',
				'pa.opening',
				'pa.heart',
				'pa.drydown',
				'pa.longevity',
				'pa.sec1_right_image',
				'pa.sec1_image1',
				'pa.sec1_image2',
				'pa.sec1_image3',
				'pa.sec2_banner',
				'pa.sec2_mobile_banner',
				'pa.brand_banner',
				DB::raw('IF(c.parent_id!=0,(SELECT category_name from `pu_category` pcc WHERE pcc.category_id=c.parent_id),c.category_name)as parent_name')
			)
			->addSelect([
				'TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
					->where('approved', '=', 'Yes')->where('star_rate', '!=', '0')->where('sku', '=', 'p.sku'), 'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
					->where('approved', '=', 'Yes')->where('star_rate', '!=', '0')->where('sku', '=', 'p.sku')
			])
			->join('pu_products_one as po', 'p.products_id', '=', 'po.products_id')
			->leftjoin('pu_products_attribute as pa', 'p.products_id', '=', 'pa.products_id')
			->join('pu_products_category as pc', 'p.products_id', '=', 'pc.products_id')
			->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
			->join('pu_brand as b', 'b.brand_id', '=', 'p.brand_id')
			->join('pu_manufacture as m', function ($join) {
				$join->on('p.imanufactureid', '=', 'm.imanufactureid');
				$join->on('b.imanufactureid', '=', 'm.imanufactureid');
			})
			->where('p.variation_id', $variation_id)
			->where('p.status', '=', '1')
			->where('c.status', '=', '1')
			->orderBy('p.is_atomizer');

		if (Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
			$ProdQry->whereIn('p.product_type', ['both', 'retailer', 'wholesaler']);
		else
			$ProdQry->whereIn('p.product_type', ['both', 'retailer']);

		$ProdQry->groupBy('p.products_id');
		$prodResData = $ProdQry->get();

		$refproductsku = array();
		if (count($prodResData) > 0) {
			foreach ($prodResData as $key => $value) {
				array_push($refproductsku, $value->sku);
			}
		}

		$dealofweekss = GetDealOfWeek($sku = '', 'Weekly', '', $refproductsku);

		$outofstock = 0;
		$CheckArrVal = array();
		for ($j = 0; $j < count($prodResData); $j++) {

			$prodResDataOne[$j] = $this->SetProduct($prodResData[$j]);

			if ($prodResDataOne[$j]->products_id == $products_id && $prodResDataOne[$j]->current_stock > 0) {

				if ($prodResDataOne[$j]->category_id == 71 && in_array($prodResDataOne[$j]->products_id, $CheckArrVal)) {
					continue;
				}
				$CheckArrVal = array($prodResDataOne[$j]->products_id);
				$parent_name = $prodResDataOne[$j]->parent_name;
			}
			if ($prodResDataOne[$j]->current_stock <= 0) {
				$outofstock++;
			}

		}

		################# To  get order fragrance,mini,tester,giftset,bathandbody
		$prodResArray = array();
		$prodResArray1 = array();
		$prodResArray2 = array();
		$prodResArray3 = array();
		$prodResArray4 = array();
		$prodResArray5 = array();
		$prodResArray6 = array();
		$prodResArray7 = array();
		$prodResArray8 = array();
		$prodResArray_new_json = array();
		$parent_name = "";
		for ($m = 0; $m < count($prodResData); $m++) {

			$prodRes[$m] = $prodResData[$m];

			$IsCosmo = "No";
			$IsNandansons = "No";
			$IsPerfumePW  = "No";
			$IsPCA  = "No";
			$IsND  = "No";
			$VendorSKU = "";
			$pca_our_price = 0;

			if ($prodRes[$m]->WebsiteStock == "Out") {
				if ($prodRes[$m]->cosmo_sku != '' && $prodRes[$m]->cosmo_current_stock > 0 && $prodRes[$m]->cosmo_our_price > 0) {
					$IsCosmo = "Yes";
					$VendorSKU = $prodRes[$m]->cosmo_sku;
					// } else if ($prodRes[$m]->perfumeworldwide_sku != '' &&  $prodRes[$m]->perfumeworldwide_currentstock > 0 && $prodRes[$m]->perfumeworldwide_our_price > 0) {
					// 	$IsPerfumePW  = "Yes";
					// 	$VendorSKU = $prodRes[$m]->perfumeworldwide_sku;
				} else if ($prodRes[$m]->pca_sku != '' &&  $prodRes[$m]->pca_current_stock > 0 && $prodRes[$m]->pca_our_price > 0) {
					$IsPCA  = "Yes";
					$VendorSKU = $prodRes[$m]->pca_sku;
				} else if ($prodRes[$m]->nandansons_sku != '' &&  $prodRes[$m]->nandansons_current_stock > 0 && $prodRes[$m]->nandansons_our_price > 0) {
					$IsNandansons = "Yes";
					$VendorSKU = $prodRes[$m]->nandansons_sku;
				}
				else if ($prodRes[$m]->perfumeworldwide_sku != '' &&  $prodRes[$m]->perfumeworldwide_currentstock > 0 && $prodRes[$m]->perfumeworldwide_our_price > 0) {
					$IsPerfumePW = "Yes";
					$VendorSKU = $prodRes[$m]->perfumeworldwide_sku;
				}
				else if ($prodRes[$m]->nd_sku != '' &&  $prodRes[$m]->nd_current_stock > 0 && $prodRes[$m]->nd_our_price > 0) {
					$IsND = "Yes";
					$VendorSKU = $prodRes[$m]->nd_sku;
				}
			}

			$prodRes[$m]->Max2Day = "No";
			if ($prodRes[$m]->maxtwodaydelivery == "Yes" && Session::get('Max2Day') == "Yes" &&  $IsCosmo == "No" && $IsPCA == "No" && $IsNandansons == "No" && $IsPerfumePW == "No" && $IsND == "No") {
				$prodRes[$m]->Max2Day = "Yes";
			}

			$prodRes[$m]->product_url = SetProductURL($prodRes[$m]->products_id, $prodRes[$m]->product_name, $prodRes[$m]->category_id);

			if ($code != '' && isset($prodRes[$m]->private_code) && $prodRes[$m]->private_code == $code) {
				$prodRes[$m]->product_url = $prodRes[$m]->product_url . "/" . $code;
			}

			$prodRes[$m]->product_url = $prodRes[$m]->product_url;
            $imageValue = $prodRes[$m]->image;
			if ($resize == "resize" && $products_id == $prodRes[$m]->products_id) {
				$imgresize = str_replace(".jpg", "_resize.jpg", $prodRes[$m]->image);

				if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $prodRes[$m]->image) && $prodRes[$m]->image != '') {

					$prodRes[$m]->midium_image = config('global.PRD_MEDIUM_IMG_URL') . $imgresize;
					$prodRes[$m]->ref_midium_image = config('global.PRD_MEDIUM_IMG_URL') . $imgresize;
				} else {
					$prodRes[$m]->midium_image = config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
					$prodRes[$m]->ref_midium_image = config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
				}

				if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $prodRes[$m]->image) && $prodRes[$m]->image != '') {
					$prodRes[$m]->large_image = config('global.PRD_LARGE_IMG_URL') . $imgresize;
				} else {
					$prodRes[$m]->large_image = config('global.NO_IMAGE_LARGE');
				}

				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $prodRes[$m]->image) && $prodRes[$m]->image != '') {
					$prodRes[$m]->image = config('global.PRD_THUMB_IMG_URL') . $imgresize;
				} else {
					$prodRes[$m]->image = config('global.NO_IMAGE_THUMB');
				}
			} else {
				if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $prodRes[$m]->image) && $prodRes[$m]->image != '') {
					$newimageVal = config('global.PRD_MEDIUM_IMG_PATH') . stripslashes($prodRes[$m]->image);
					$verP = filemtime($newimageVal);
					$prodRes[$m]->midium_image = config('global.PRD_MEDIUM_IMG_URL') . $prodRes[$m]->image . "?ver=" . $verP;
					$prodRes[$m]->ref_midium_image = config('global.PRD_MEDIUM_IMG_URL') . $prodRes[$m]->image . "?ver=" . $verP;
				} else {
					$prodRes[$m]->midium_image = config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
					$prodRes[$m]->ref_midium_image = config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
				}

				if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $prodRes[$m]->image) && $prodRes[$m]->image != '') {
					$newimageVal = config('global.PRD_LARGE_IMG_PATH')  . stripslashes($prodRes[$m]->image);
					$verP = filemtime($newimageVal);
					$prodRes[$m]->large_image = config('global.PRD_LARGE_IMG_URL') . $prodRes[$m]->image . "?ver=" . $verP;
				} else {
					$prodRes[$m]->large_image = config('global.NO_IMAGE_LARGE');
				}

				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $prodRes[$m]->image) && $prodRes[$m]->image != '') {
					$newimageVal = config('global.PRD_THUMB_IMG_PATH') . stripslashes($prodRes[$m]->image);
					$verP = filemtime($newimageVal);
					$prodRes[$m]->image = config('global.PRD_THUMB_IMG_URL') . $prodRes[$m]->image . "?ver=" . $verP;
				} else {
					$prodRes[$m]->image = config('global.NO_IMAGE_THUMB');
				}
			}

			if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $imageValue) && $imageValue != '')
			{
				$prodRes[$m]->ref_midium_image = $prodRes[$m]->large_image;
				if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $imageValue) && $imageValue != '')
				{
					$prodRes[$m]->ref_midium_image = $prodRes[$m]->midium_image;
				}
				$prodRes[$m]->midium_image = $prodRes[$m]->large_image;
				$prodRes[$m]->large_image  =  $prodRes[$m]->large_image;
				$prodRes[$m]->image = $prodRes[$m]->large_image;

			}
			else if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $imageValue) && $imageValue != '')
			{
				$prodRes[$m]->midium_image = $prodRes[$m]->midium_image;
				$prodRes[$m]->large_image  =  $prodRes[$m]->midium_image;
				$prodRes[$m]->image = $prodRes[$m]->midium_image;
				$prodRes[$m]->ref_midium_image = $prodRes[$m]->midium_image;

			}
			else if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $imageValue) && $imageValue != '')
			{
				$prodRes[$m]->midium_image = $prodRes[$m]->image;
				$prodRes[$m]->large_image  =  $prodRes[$m]->image;
				$prodRes[$m]->image = $prodRes[$m]->image;
				$prodRes[$m]->ref_midium_image = $prodRes[$m]->image;
			}

			$retail_price = $prodRes[$m]->retail_price;
			if ($retail_price != '' && $retail_price != '0.00' && $retail_price != '') {
				$yousave = ($retail_price - $prodRes[$m]->product_price) / $retail_price;
				$yousave = $yousave * 100;
				$yousave = number_format($yousave, 0);
				$yousaveprice = $retail_price - $prodRes[$m]->product_price;
			} else {
				$yousave = 0;
				$yousaveprice = 0;
			}

			$prodRes[$m]->yousave = $yousave;
			$prodRes[$m]->yousaveprice = $yousaveprice;
			$prodRes[$m]->short_description = remove_html_entities(strip_tags($prodRes[$m]->short_description));

			if ($isMobile == 1) {
				if ($prodRes[$m]->size == '' && $prodRes[$m]->vtype == '') {
					$prodRes[$m]->CombineStr = $prodRes[$m]->category_name;
				} else {
					$CombineStr = '';
					$CombineStr .= $prodRes[$m]->vtype . "<br/>" . $prodRes[$m]->size;
					$prodRes[$m]->CombineStr = $CombineStr;
				}
			}

			######## Code for deal price setting :: Start
			$isDealProduct = 0;
			$prodRes[$m]->dealofdayRS = 0;
			$dealofdayRS = null;
			if (isset($dealofweekss[$prodRes[$m]->sku])) {
				$dealofdayRS = $dealofweekss[$prodRes[$m]->sku];
				$isDealProduct = 1;
				$prodRes[$m]->dealofdayRS = count($dealofweekss[$prodRes[$m]->sku]);
			}

			$prodRes[$m]->isDealProduct = $isDealProduct;
			$prodRes[$m]->deal_type = "";
			$prodRes[$m]->formatted_end_date	= "";
			$prodRes[$m]->formatted_end_month	= "";
			$prodRes[$m]->formatted_end_year	= "";
			$prodRes[$m]->DealStartDate = "";
			$prodRes[$m]->DealEndDate = "";

			if ($dealofdayRS != null && isset($prodRes[$m]->WebsiteStock) && $prodRes[$m]->WebsiteStock == "In") {
				if (isset($dealofdayRS['deal_price']) && $dealofdayRS['deal_price'] > 0 && $dealofdayRS["deal_price"] < $prodRes[$m]->product_price) {

					if ($dealofdayRS['description'] != '') {
						$prodRes[$m]->short_description = $dealofdayRS['description'];
					}

					$prodRes[$m]->yousave 	= $dealofdayRS['yousave'];
					$prodRes[$m]->yousaveprice = $dealofdayRS['yousaveprice'];
					$prodRes[$m]->dealprice = $dealofdayRS['deal_price'];
					$prodRes[$m]->short_description = remove_html_entities(strip_tags($prodRes[$m]->short_description));

					$prodRes[$m]->DateDiff = date_diff(date_create($dealofdayRS['start_date']), date_create($dealofdayRS['end_date']));
					$prodRes[$m]->formatted_end_date	= date('d', strtotime($dealofdayRS['end_date']));
					$prodRes[$m]->formatted_end_month	= date('m', strtotime($dealofdayRS['end_date']));
					$prodRes[$m]->formatted_end_year	= date('Y', strtotime($dealofdayRS['end_date']));
					$prodRes[$m]->DealStartDate = date("d-m-Y H:i:s");
					$prodRes[$m]->DealEndDate = $prodRes[$m]->formatted_end_date . "-" . $prodRes[$m]->formatted_end_month . "-" . $prodRes[$m]->formatted_end_year . " 23:59:59";
					$prodRes[$m]->deal_type = "Weekly";
				}
			}

			$prodRes[$m]->short_description = strip_tags(html_entity_decode($prodRes[$m]->short_description));

			$prodRes[$m]->short_description = remove_html_entities($prodRes[$m]->short_description);

			$prodRes[$m]->DealIcon = "";
			$prodRes[$m]->SaleIcon = "";
			$prodRes[$m]->GiftIcon = "";

			$prodRes[$m]->SaleIcon = '';
			$prodRes[$m]->sale_item = '0';
			if ($prodRes[$m]->sale_price > 0 && strtolower(Session::get('eusertype')) != 'wholesaler') {
				$prodRes[$m]->sale_item = '1';
			}

			$prodRes[$m]->dealprice = (isset($prodRes[$m]->dealprice) ? $prodRes[$m]->dealprice : 0);

			if ($prodRes[$m]->dealprice > 0) {
				$prodRes[$m]->DealIcon = $this->getIconsSaleDeal("Yes", "No", "No", "80", "15");
			} else if ($prodRes[$m]->sale_item == '1') {
				$prodRes[$m]->SaleIcon = $this->getIconsSaleDeal("No", "Yes", "No", "80", "15");
			} else if ($prodRes[$m]->is_gift_wrap == 'Yes') {
				$prodRes[$m]->GiftIcon = $this->getIconsSaleDeal("No", "No", "Yes");
			}

			if ($prodRes[$m]->parent_name == $parent_name  && $prodRes[$m]->current_stock > 0) {
				$prodResArray1[str_replace(" ", "_", $prodRes[$m]->parent_name)][] = $prodRes[$m];
				$prodResArray_new_json[$m] = $prodRes[$m];
			} else {
				if ($prodRes[$m]->parent_name != $parent_name && $prodRes[$m]->current_stock > 0) {
					if ($prodRes[$m]->is_atomizer == 'Yes' || $prodRes[$m]->parent_name == 'Pocket Perfume') {
						$prodResArray4[str_replace("_", " ", 'Pocket Perfume')][] = $prodRes[$m];
						$prodResArray_new_json[$m] = $prodRes[$m];
					} else if ($prodRes[$m]->parent_name == 'Testers' || $prodRes[$m]->parent_name == 'Tester') {
						$prodResArray4[str_replace(" ", "_", 'Testers')][] = $prodRes[$m];
						$prodResArray_new_json[$m] = $prodRes[$m];
					} else if ($prodRes[$m]->parent_name == 'Gift Sets' || $prodRes[$m]->parent_name == 'Gift Set') {
						$prodResArray5[str_replace(" ", "_", 'Sets')][] = $prodRes[$m];
						$prodResArray_new_json[$m] = $prodRes[$m];
					}else{ // 'Fragrance' ,'Bath and Body' ,'Mini and other in standard size'
						$prodResArray2[str_replace("_", " ", 'Standard Size')][] = $prodRes[$m];
						$prodResArray_new_json[$m] = $prodRes[$m];
					}
				}
			}
			if ($prodRes[$m]->current_stock <= 0) {
				$prodResArray8["OutOfStock"][] = $prodRes[$m];
				$prodResArray_new_json[$m] = $prodRes[$m];
			}
		}

		$prodResArray_1 = array();
		$prodResArray_2 = array();
		$prodResArray_3 = array();
		$prodResArray_4 = array();
		$prodResArray_5 = array();
		$prodResArray_6 = array();
		$prodResArray_7 = array();
		$prodResArray_8 = array();

		if (count($prodResArray1) > 0) {
			$key2 = array_keys($prodResArray1);
			for ($i = 0; $i < count($key2); $i++) {
				$prodResArray1[$i] = $this->uniqueAssocArray($prodResArray1[$key2[$i]], "products_id");
				$prodResArray_1[$key2[$i]] = $prodResArray1[$i];
			}
		}

		if (count($prodResArray2) > 0) {
			$key1 = array_keys($prodResArray2);
			for ($i = 0; $i < count($key1); $i++) {
				$prodResArray2[$i] = $this->uniqueAssocArray($prodResArray2[$key1[$i]], "products_id");
				$prodResArray_2[$key1[$i]] = $prodResArray2[$i];
			}
		}

		if (count($prodResArray8) > 0) {
			$key1 = array_keys($prodResArray8);
			for ($i = 0; $i < count($key1); $i++) {
				$prodResArray8[$i] = $this->uniqueAssocArray($prodResArray8[$key1[$i]], "products_id");
				$prodResArray_8[$key1[$i]] = $prodResArray8[$i];
			}
		}

		if (count($prodResArray3) > 0) {
			$key3 = array_keys($prodResArray3);
			for ($i = 0; $i < count($key3); $i++) {
				$prodResArray3[$i] = $this->uniqueAssocArray($prodResArray3[$key3[$i]], "products_id");
				$prodResArray_3[$key3[$i]] = $prodResArray3[$i];
			}
		}

		if (count($prodResArray4) > 0) {
			$key4 = array_keys($prodResArray4);
			for ($i = 0; $i < count($key4); $i++) {
				$prodResArray4[$i] = $this->uniqueAssocArray($prodResArray4[$key4[$i]], "products_id");
				$prodResArray_4[$key4[$i]] = $prodResArray4[$i];
			}
		}

		if (count($prodResArray5) > 0) {
			$key5 = array_keys($prodResArray5);
			for ($i = 0; $i < count($key5); $i++) {
				$prodResArray5[$i] = $this->uniqueAssocArray($prodResArray5[$key5[$i]], "products_id");
				$prodResArray_5[$key5[$i]] = $prodResArray5[$i];
			}
		}

		if (count($prodResArray6) > 0) {
			$key6 = array_keys($prodResArray6);
			for ($i = 0; $i < count($key6); $i++) {
				$prodResArray6[$i] = $this->uniqueAssocArray($prodResArray6[$key6[$i]], "products_id");
				$prodResArray_6[$key6[$i]] = $prodResArray6[$i];
			}
		}

		if (count($prodResArray7) > 0) {
			$key7 = array_keys($prodResArray7);
			for ($i = 0; $i < count($key7); $i++) {
				$prodResArray7[$i] = $this->uniqueAssocArray($prodResArray7[$key7[$i]], "products_id");
				$prodResArray_7[$key7[$i]] = $prodResArray7[$i];
			}
		}

		// if($products_id == 36662){
		// 	Log::info('prodResArray_1 : '.json_encode($prodResArray_1));
		// 	Log::info('prodResArray_2 : '.json_encode($prodResArray_2));
		// 	Log::info('prodResArray_3 : '.json_encode($prodResArray_3));
		// 	Log::info('prodResArray_4 : '.json_encode($prodResArray_4));
		// 	Log::info('prodResArray_5 : '.json_encode($prodResArray_5));
		// 	Log::info('prodResArray_6 : '.json_encode($prodResArray_6));
		// 	Log::info('prodResArray_7 : '.json_encode($prodResArray_7));
		// 	Log::info('prodResArray_8 : '.json_encode($prodResArray_8));
		// }

		$prodResArray = array_merge($prodResArray_1, $prodResArray_2, $prodResArray_3, $prodResArray_4, $prodResArray_5, $prodResArray_6, $prodResArray_7, $prodResArray_8);
		// if($products_id == 36662){
		// 	Log::info('prodResArray : '.json_encode($prodResArray));
		// }
		$group_found_res = $prodResArray_new_json;

		$group_cnt = count($group_found_res);
		$group_arr = array();
		if ($group_cnt > 0) {

			$cat_url_parent = '';
			$cat_url_sub = '';
			for ($n = 0; $n < $group_cnt; $n++) {
				$short_description   = '';
				$product_description = '';
				$product_name  = '';

				$group_found_res[$n]->short_description= strip_tags($group_found_res[$n]->short_description);

				$group_found_res[$n]->short_description = remove_html_entities($group_found_res[$n]->short_description);
				$short_description 	= $this->Jsn_Chtr_Remove($group_found_res[$n]->short_description);

				$product_description = $this->Jsn_Chtr_Remove($group_found_res[$n]->product_description);

				$group_found_res[$n]->UPC = $this->Jsn_Chtr_Remove(trim($group_found_res[$n]->UPC));
				$group_found_res[$n]->brand_name =  $this->Jsn_Chtr_Remove($this->clean_new($group_found_res[$n]->brand_name));
				$group_found_res[$n]->vmanufacture =  $this->Jsn_Chtr_Remove($this->clean_new($group_found_res[$n]->vmanufacture));

				$m_name_r = strtolower($group_found_res[$n]->vmanufacture);
				$m_name_r = str_replace("#", "", $m_name_r);
				$m_name_r = str_replace("&", "", $m_name_r);
				$m_name_r = str_replace("  ", " ", trim($m_name_r));
				$m_name_r = str_replace("  ", " ", trim($m_name_r));
				$m_name_r = str_replace(" ", "-", $m_name_r);

				$group_found_res[$n]->vmanufacture_link = config('global.SITE_URL') . $m_name_r . "/smid-" . $group_found_res[$n]->imanufactureid;

				if ($group_found_res[$n]->brand_name != '' && $group_found_res[$n]->vmanufacture != '') {
					//For url name
					$m_name = strtolower($group_found_res[$n]->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);

					//$group_found_res[$n]->referencedName = $group_found_res[$n]->brand_name . ' by ' . $group_found_res[$n]->vmanufacture;
					//Added on 16-oct-2018
					//$group_found_res[$n]->referencedName_main = $group_found_res[$n]->brand_name . ' by <a href=' . $group_found_res[$n]->vmanufacture_link . '><U>' . $group_found_res[$n]->vmanufacture . '</U></a>';
					$group_found_res[$n]->referencedName = $group_found_res[$n]->brand_name;
					$group_found_res[$n]->referencedName_main = $group_found_res[$n]->brand_name;
				} else {
					$group_found_res[$n]->referencedName = $group_found_res[$n]->product_name;
					//Added on 16-oct-2018
					$group_found_res[$n]->referencedName_main = $group_found_res[$n]->product_name;
				}

				if (file_exists(config('global.MANUFACTUR_IMAGE_PATH') . $group_found_res[$n]->imglogo) && trim($group_found_res[$n]->imglogo ?? '') != '') {
					$group_found_res[$n]->manufacture_logo = config('global.MANUFACTUR_IMAGE_URL') . $group_found_res[$n]->imglogo;
				} else {
					$group_found_res[$n]->manufacture_logo = '';
				}

				$product_name = $this->Jsn_Chtr_Remove($group_found_res[$n]->referencedName);
				//Added on 16-oct-2018
				$product_name_main = $this->Jsn_Chtr_Remove($group_found_res[$n]->referencedName_main);

				$cat_url_parent = '';
				$cat_url_sub = '';

				$short_description = $this->clean_new($short_description);
				$product_description = $this->clean_new($product_description);
				$product_name = $this->clean_new($product_name);
				$vmanufacture = $this->clean_new($group_found_res[$n]->vmanufacture);
				$deal_type = "";
				$dealclass = "";

				if ($group_found_res[$n]->sku != "" && isset($group_found_res[$n]->deal_type)) {
					if ($group_found_res[$n]->deal_type == trim("Weekly")) {
						$deal_type = "Weekly";
					}
				}

				$group_arr[$group_found_res[$n]->sku] = array(
					"product_id"		    => $group_found_res[$n]->products_id,
					"sku"				  	=> $group_found_res[$n]->sku,
					"UPC"					=> $group_found_res[$n]->UPC,
					"youtubelink"			=> '', //$group_found_res[$n]->youtubelink
					"product_name"	     	=> remove_html_entities($product_name),
					"product_name_main"	    => remove_html_entities($product_name_main),
					"short_description"   	=> remove_html_entities($short_description),
					"product_description" 	=> remove_html_entities($product_description),
					"product_price"	 		=> number_format((float)($group_found_res[$n]->product_price), 2, '.', ''),
					"retail_price"		  	=> number_format((float)($group_found_res[$n]->retail_price), 2, '.', ''),
					"stock"               	=> $group_found_res[$n]->stock,
					"upc"                 	=> $group_found_res[$n]->UPC,
					"mainImage"        		=> $group_found_res[$n]->midium_image,
					"large_image"         	=> $group_found_res[$n]->large_image,
					"thumb_image"			=> $group_found_res[$n]->image,
					"save"				  	=> $group_found_res[$n]->yousave,
					"parent_name"			=> $group_found_res[$n]->parent_name,
					"cat_name"			 	=> $group_found_res[$n]->category_name,
					"manufacture_logo"		=> $group_found_res[$n]->manufacture_logo,
					"vmanufacture"			=> remove_html_entities($vmanufacture),
					"deal_type"				=> $deal_type,
					"formatted_end_year"	=> $group_found_res[$n]->formatted_end_year,
					"formatted_end_month"	=> $group_found_res[$n]->formatted_end_month,
					"formatted_end_date"	=> $group_found_res[$n]->formatted_end_date,
					"DealStartDate"			=> $group_found_res[$n]->DealStartDate,
					"DealEndDate"			=> $group_found_res[$n]->DealEndDate
				);
			}
		}
		//echo "<pre>"; print_r($group_arr); exit;
		$str_ret = json_encode($group_arr);
		$str_ret = str_replace("\\\'", "\'", $str_ret);

		$this->PageData['referance_products_cnt'] = count($group_arr);

		$this->PageData['referance_products_arr'] = $str_ret;

		return $prodResArray;

		// $isIpad = preg_match("/(tablet|ipad)/i", $_SERVER["HTTP_USER_AGENT"]);
		// if ($isMobile == 1 && $isIpad == 0) {
		// 	return $prodRes;
		// } else {
		// 	return $prodResArray;
		// }
	}

	public function getWholesalerSpecialPricesDetails2($product_price)
	{
		if (config('Settings.WHOLESALE_MARKUP') == 'Yes') {
			$is_special_price_enable = 1;
		} else {
			$is_special_price_enable = 0;
		}
		$SpecialPriceDetails = '';
		if ($is_special_price_enable == 1) {

			$db_recs = MarkupPrices::get();

			$SpecialPriceDetails .= '<table class="table"><tbody><tr><th>Qty</th>';

			for ($i = 0; $i < count($db_recs); $i++) {
				if ($db_recs[$i]->markup_lable != "" && $db_recs[$i]->markup_lable != '0') {
					$is_special_price_enable = 1;
					$SpecialPriceDetails .= '<th>' . str_replace("Price", "", $db_recs[$i]->markup_lable) . '</th>';
				}
			}
			$SpecialPriceDetails .= '</tr>';

			$SpecialPriceDetails .= '<tr><th class="lightbg1">Price</th>';

			for ($j = 0; $j < count($db_recs); $j++) {
				if ($db_recs[$j]->markup_percent != "" && $db_recs[$j]->markup_percent != '0') {
					$is_special_price_enable = 1;
					$this->PageData['is_special_price_enable'] = $is_special_price_enable;
					$NewPrice = $product_price - ($product_price * $db_recs[$j]["markup_percent"]) / 100;
					$SpecialPriceDetails .= '<td>' . $this->Make_Price($NewPrice, true, false) . '</td>';
				}
			}
			$SpecialPriceDetails .= '</tr>';

			$SpecialPriceDetails .= '</tbody></table>';
		}

		return $SpecialPriceDetails;
	}

	function GetBogoProducts($sku, $category_id, $imanufactureid, $daydealofdayRS)
	{

		if(config('Settings.BOGODISCOUNTFLAG')=="No")
		{
			return null;
		}

		$IsGiftCertificateItem = '';
		$passArray = array();

		$BogoArray = DB::table('pu_bogo_discount')
			->where('start_date', '<=', date('Y-m-d'))
			->where('end_date', '>=', date('Y-m-d'))
			->where('status', '=', '1')
			->where('logo', '!=', '')
			->orderBy('bogo_discount_id', 'DESC')
			->get();

		$logo_img = "";
		$TotalBogoDiscoundRecords = count($BogoArray);

		if ($TotalBogoDiscoundRecords > 0) {
			$TotalBogo = count($BogoArray);

			$DogoDiscount = 0;
			for ($i = 0; $i < $TotalBogo; $i++) {

				$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$passArray,'No',0,0,'','',$sku);

				if ($BogoArray[$i]->orders == '2') {
					$QtySKU = trim($BogoArray[$i]->sku);

					########### For Multiple SKU ###############
					$arr_QtySKU  = explode(",", $QtySKU);

					$arr_QtySKU  = array_unique(array_map('trim', $arr_QtySKU));
					$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');

					//if ($sku != config('global.GIFT_CERTIFICATE_SKU') && $sku != config('global.GIFT_CERTIFICATE_SKU1') && $sku != config('global.GIFT_CERTIFICATE_SKU2') && in_array($sku, $arr_QtySKU) && $daydealofdayRS == 0) {
					if ($IsGiftCertificateItem == 'No' && in_array($sku, $arr_QtySKU) && $daydealofdayRS == 0)
					{

						if ($BogoArray[$i]->logo != "" && file_exists(config('global.PHYSICAL_PATH') . "images/icons/" . $BogoArray[$i]->logo)) {
							$logo_img = '<img class="dynamic-ico" src="' . config('global.SITE_URL') . 'images/icons/' . $BogoArray[$i]->logo . '" width="50">';
						}
					}
				} else if ($BogoArray[$i]->orders == '0') {
					$QtyCatID = trim($BogoArray[$i]->sku);
					$arr_QtyCatID    = explode(",", $QtyCatID);

					//if ($sku != config('global.GIFT_CERTIFICATE_SKU') && $sku != config('global.GIFT_CERTIFICATE_SKU1') && $sku != config('global.GIFT_CERTIFICATE_SKU2') && in_array($category_id, $arr_QtyCatID) && $daydealofdayRS <= 0) {
					if ($IsGiftCertificateItem == 'No' && in_array($category_id, $arr_QtyCatID) && $daydealofdayRS <= 0)
					{

						if ($BogoArray[$i]->logo != "" && file_exists(config('global.PHYSICAL_PATH') . "images/icons/" . $BogoArray[$i]->logo)) {
							$logo_img = '<img class="dynamic-ico" src="' . config('global.SITE_URL') . 'images/icons/' . $BogoArray[$i]->logo . '" width="50">';
						}
					}
				} else if ($BogoArray[$i]->orders == '1') {
					$QtySKU = trim($BogoArray[$i]->sku);

					########### For Multiple SKU ###############
					$QtyBrandID    	= trim($BogoArray[$i]->sku); // Category IDS
					$arr_QtyBrandID    = explode(",", $QtyBrandID);

					//if ($sku != config('global.GIFT_CERTIFICATE_SKU') && $sku != config('global.GIFT_CERTIFICATE_SKU1') && $sku != config('global.GIFT_CERTIFICATE_SKU2') && in_array($imanufactureid, $arr_QtyBrandID) && $daydealofdayRS == 0) {
					if ($IsGiftCertificateItem == 'No' && in_array($imanufactureid, $arr_QtyBrandID) && $daydealofdayRS == 0)
					{

						if ($BogoArray[$i]->logo != "" && file_exists(config('global.PHYSICAL_PATH') . "images/icons/" . $BogoArray[$i]->logo)) {
							$logo_img = '<img class="dynamic-ico" src="' . config('global.SITE_URL') . 'images/icons/' . $BogoArray[$i]->logo . '" width="50">';
						}
					}
				}
			}
		}
		return $logo_img;
	}

	function getProductNavigation($category_id)
	{
		$i = 0;
		$Bredcrum[$i]['title'] = 'Home';
		$Bredcrum[$i]['link'] = config('global.SITE_URL');

		if ($category_id && $category_id != '') {
			$CatDetails = Category::find($category_id);

			if ($CatDetails && $CatDetails->count() > 0) {
				$CatString = $CatLink = '';

				if ($CatDetails->parent != null && $CatDetails->parent->parent != null) {
					$MainCat = $CatDetails->parent->parent;
					$CatLink = config('global.SITE_URL') . remove_special_chars(trim($MainCat->category_name)) . '/cid/' . $CatDetails->category_id;
					$CatString = ucwords($MainCat->category_name);
					$i++;
					$Bredcrum[$i]['title'] = $CatString;
					$Bredcrum[$i]['link'] = $CatLink;
				}

				if ($CatDetails->parent != null) {
					$i++;
					$SubCat = $CatDetails->parent;
					$CatLink = config('global.SITE_URL') . remove_special_chars(trim($SubCat->category_name)) . '/cid/' . $CatDetails->category_id;
					if ($CatString != '')
						$CatString = ucwords($SubCat->category_name);
					else
						$CatString = ucwords($SubCat->category_name);

					$Bredcrum[$i]['title'] = $CatString;
					$Bredcrum[$i]['link'] = $CatLink;
				}

				if ($CatString != '' || $CatDetails->parent == null)
					$CatString =  $CatDetails->category_name;

				$i++;
				$Bredcrum[$i]['title'] = $CatString;
				$Bredcrum[$i]['link'] = "";
			}
		}
		return $Bredcrum;
	}

	function list_manufacturer_gender($productsId, $categoryId, $brandId, $manufacturer_id, $gender)
	{
		$manu_brand_array = array();
		$manu_id = $manufacturer_id;
		$ibrandid = $brandId;
		$iprodid = $productsId;
		$prod_brand_all = Brand::select('brand_id', 'brand_name')->where('imanufactureid', '=', $manu_id)->where('status', '=', '1')->orderBy('brand_name')->get();

		$brandArray = array();
		foreach ($prod_brand_all as $key => $value) {
			$brandArray[$value->brand_id] = $value->brand_name;
		}

		$brandIdArray = array_keys($brandArray);
		$brand_idm = Products::select('products_id', 'product_name', 'imanufactureid', 'brand_id', 'minimum_stock', 'gender', 'current_stock', 'current_stock', 'cosmo_current_stock', 'cosmo_current_stock', 'cosmo_sku', 'nandansons_current_stock', 'nandansons_current_stock', 'nandansons_sku', 'pca_current_stock', 'pca_current_stock', 'pca_sku','nd_sku','nd_current_stock','perfumeworldwide_sku','perfumeworldwide_currentstock')
			->whereIn('brand_id', $brandIdArray)->where('imanufactureid', '=', $manu_id)->whereIn('gender', $gender)->where('products_id', '!=', $iprodid)
			->where('status', '=', '1')->groupBy('brand_id')->groupBy('gender')->get();

		if (count($brand_idm) > 0) {
			foreach ($brand_idm as $key => $value) {
				$isStock = $this->getSystemStockAvalilable($value);
				if ($isStock == 'In') {
					$iproductidm = $value->products_id;
					$vbrand = $brandArray[$value->brand_id];

					$manu_brand_array[$value['gender']][] = array(
						"brand_name" => $vbrand,
						"brand_link" => SetProductURL($iproductidm, $value->product_name, $categoryId)
					);
				}
			}
		}
		return $manu_brand_array;
	}

	function ReviewRating($starRating)
	{
		if ($starRating == 1) {
			return '<span class="rating"><span class="star"></span><span class="star"></span><span class="star"></span><span class="star"></span><span class="star active"></span></span>';
		} else if ($starRating == 2) {
			return '<span class="rating"><span class="star"></span><span class="star"></span><span class="star"></span><span class="star active"></span><span class="star active"></span></span>';
		} else if ($starRating == 3) {
			return '<span class="rating"><span class="star"></span><span class="star"></span><span class="star active"></span><span class="star active"></span><span class="star active"></span></span>';
		} else if ($starRating == 4) {
			return '<span class="rating"><span class="star"></span><span class="star active"></span><span class="star active"></span><span class="star active"></span><span class="star active"></span></span>';
		} else if ($starRating == 5) {
			return '<span class="rating"><span class="star active"></span><span class="star active"></span><span class="star active"></span><span class="star active"></span><span class="star active"></span></span>';
		} else {
			return '<span class="rating"><span class="star"></span><span class="star"></span><span class="star"></span><span class="star"></span><span class="star"></span></span>';
		}
	}

	public function getProductsDetails($products_id, $preview = '', $code = '', $resize = '')
	{
		$CatProdsQry = DB::table('pu_products as p')
			->select(
				'p.products_id',
				'p.sku',
				'p.product_name',
				'p.cosmo_sku',
				'p.pca_sku',
				'p.pca_our_price',
				'p.pca_wholesale_price',
				'p.pca_current_stock',
				'p.cosmo_our_price',
				'p.nandansons_our_price',
				'p.perfumeworldwide_our_price',
				'p.cosmo_wholesale_price',
				'p.nandansons_wholesale_price',
				'p.perfumeworldwide_wholesale_price',
				'p.cosmo_current_stock',
				'p.nandansons_sku',
				'p.nandansons_current_stock',
				'p.perfumeworldwide_sku',
				'p.perfumeworldwide_currentstock',
				'p.short_description',
				'p.current_stock',
				'p.retail_price',
				'p.cosmo_retail_price',
				'p.pca_retail_price',
				'p.minimum_stock',
				'p.gender',
				'p.product_description',
				'p.imanufactureid',
				'po.extra_images',
				'po.youtubelink',
				'p.vtype',
				'p.fragrance_family',
				'p.size',
				'p.formulation',
				'p.coverage',
				'p.finish',
				'p.skin_type',
				'p.is_atomizer',
				'm.vmanufacture',
				'p.variation_id',
				'p.fragrance_occasion',
				'p.fragrance_personality',
				'p.fragrance_seasons',
				'p.product_type',
				'p.gender',
				'p.UPC',
				'po.related_item',
				'm.imglogo',
				'm.brand_history',
				'po.point_multiplier',
				'p.is_gift_wrap',
				'p.image',
				'po.meta_title',
				'po.meta_keyword',
				'po.meta_description',
				'p.maxtwodaydelivery',
				'p.imanufactureid',
				'p.brand_id',
				'pc.category_id',
				'b.brand_name',
				'p.cosmo_sku',
				'p.cosmo_current_stock',
				'p.cosmo_wholesale_price',
				'p.cosmo_our_price',
				'p.pca_sku',
				'p.pca_current_stock',
				'p.pca_wholesale_price',
				'p.pca_our_price',
				'p.nandansons_sku',
				'p.nandansons_current_stock',
				'p.nandansons_wholesale_price',
				'p.nandansons_our_price',
				'p.nandansons_retail_price',
				'p.nd_sku',
				'p.nd_current_stock',
				'p.nd_wholesale_price',
				'p.nd_our_price',
				'p.nd_retail_price',
				'p.wholesale_price',
				'p.our_price',
				'p.sale_price',
				'po.product_story',
				'po.story_video',
				'po.story_youtubelink',
				'po.story_image_one',
				'po.story_image_two',
				'po.story_image_three',
				'po.story_image_four',
				'po.story_image_one_desc',
				'po.story_image_two_desc',
				'po.story_image_three_desc',
				'po.story_image_four_desc',
				'pa.topnote',
				'pa.middlenote',
				'pa.basenote',
				'pa.template',
				'pa.opening',
				'pa.heart',
				'pa.drydown',
				'pa.longevity',
				'pa.sec1_right_image',
				'pa.sec1_image1',
				'pa.sec1_image2',
				'pa.sec1_image3',
				'pa.sec2_banner',
				'pa.sec2_mobile_banner',
				'pa.brand_banner'
			)
			->join('pu_products_one as po', 'p.products_id', '=', 'po.products_id')
			->leftjoin('pu_products_attribute as pa', 'p.products_id', '=', 'pa.products_id')
			->join('pu_products_category as pc', 'p.products_id', '=', 'pc.products_id')
			->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
			->join('pu_brand as b', 'b.brand_id', '=', 'p.brand_id')
			->join('pu_manufacture as m', function ($join) {
				$join->on('p.imanufactureid', '=', 'm.imanufactureid');
				$join->on('b.imanufactureid', '=', 'm.imanufactureid');
			})
			->where('c.status', '=', '1');
		$CatProdsQry->where('p.products_id', '=', $products_id);
		if ($code != '') {
			if ($preview != 1) {
				$CatProdsQry->where('p.status', '=', '2');
				$CatProdsQry->where('po.is_private', '=', 'Yes');
				$CatProdsQry->where('po.private_code', '=', $code);
			}
		} else {
			if ($preview != 1) {
				$CatProdsQry->where('p.status', '=', '1');
			}
		}
		$CatProdsQry->groupBy('p.products_id');
		$CatProdsQry = $CatProdsQry->first();

		$CatProdsQry = $this->SetProduct($CatProdsQry);
		if ($CatProdsQry) {
			if ($CatProdsQry->vmanufacture != '') {
				$m_name = strtolower($CatProdsQry->vmanufacture);
				$m_name = str_replace("#", "", $m_name);
				$m_name = str_replace("&", "", $m_name);
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace(" ", "-", $m_name);
				$this->PageData['vmanufacture_link'] = config('global.SITE_URL') . $m_name . "/smid-" . $CatProdsQry->imanufactureid;
			}
			if ($CatProdsQry->brand_name != '' && $CatProdsQry->vmanufacture != '') {
				//For url name
				$m_name = strtolower($CatProdsQry->vmanufacture);
				$m_name = str_replace("#", "", $m_name);
				$m_name = str_replace("&", "", $m_name);
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace("  ", " ", trim($m_name));
				$m_name = str_replace(" ", "-", $m_name);

				//$CatProdsQry->referencedName = $CatProdsQry->brand_name . ' by ' . $CatProdsQry->vmanufacture;
				//$CatProdsQry->referencedName_main = $CatProdsQry->brand_name . ' by <a target="_blank" href="' . $this->PageData['vmanufacture_link'] . '"><U>' . $CatProdsQry->vmanufacture . '</U></a>';
				$CatProdsQry->referencedName = $CatProdsQry->brand_name;
				$CatProdsQry->referencedName_main = $CatProdsQry->brand_name;
			} else {
				$CatProdsQry->referencedName =  $CatProdsQry->product_name;
				$CatProdsQry->referencedName_main = $CatProdsQry->product_name;
			}
			$CatProdsQry->vmanufacture_link = $this->PageData['vmanufacture_link'];
			if ($resize == "resize") {
				$rezimg = $CatProdsQry->image;
				$rezimg = str_replace(".jpg", "_resize.jpg", $rezimg);

				if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProdsQry->image) and $CatProdsQry->image != '') {
					$CatProdsQry->large_image = config('global.PRD_LARGE_IMG_URL') . $rezimg;
				} else {
					$CatProdsQry->large_image = config('global.NO_IMAGE_LARGE');
				}

				if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$CatProdsQry->mainImage = config('global.PRD_MEDIUM_IMG_URL') . $rezimg;
				} else {
					$CatProdsQry->mainImage  = config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
					$CatProdsQry->isimage = true;
				}

				######## change added
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$CatProdsQry->thumb_image = config('global.PRD_THUMB_IMG_URL') . $rezimg;
				} else {
					$CatProdsQry->thumb_image =  config('global.NO_IMAGE_THUMB');
				}
			} else {
				if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$newimageVal = config('global.PRD_LARGE_IMG_PATH') . stripslashes($CatProdsQry->image);
					$verP = filemtime($newimageVal);
					$CatProdsQry->large_image = config('global.PRD_LARGE_IMG_URL') . $CatProdsQry->image . "?ver=" . $verP;
				} else {
					$CatProdsQry->large_image = config('global.NO_IMAGE_LARGE');
				}

				if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$newimageVal = config('global.PRD_MEDIUM_IMG_PATH') . stripslashes($CatProdsQry->image);
					$verP = filemtime($newimageVal);
					$CatProdsQry->mainImage  = config('global.PRD_MEDIUM_IMG_URL') . $CatProdsQry->image . "?ver=" . $verP;
				} else {
					$CatProdsQry->mainImage =  config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
					$CatProdsQry->isimage = true;
				}

				####### change added
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '') {
					$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProdsQry->image);
					$verP = filemtime($newimageVal);
					$CatProdsQry->thumb_image = config('global.PRD_THUMB_IMG_URL') . $CatProdsQry->image . "?ver=" . $verP;
				} else {
					$CatProdsQry->thumb_image = config('global.NO_IMAGE_THUMB');
				}
			}

			if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '')
			{
				$CatProdsQry->mainImage = $CatProdsQry->large_image;
			}
			else if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '')
			{
				$CatProdsQry->mainImage = $CatProdsQry->mainImage;
			}
			else if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProdsQry->image) && $CatProdsQry->image != '')
			{
				$CatProdsQry->mainImage = $CatProdsQry->thumb_image;
			}

			if (file_exists(config('global.MANUFACTUR_IMAGE_PATH') . $CatProdsQry->imglogo) && trim($CatProdsQry->imglogo) != '') {
				$newimageVal = config('global.MANUFACTUR_IMAGE_PATH') . stripslashes($CatProdsQry->imglogo);
				$verP = filemtime($newimageVal);
				$CatProdsQry->manufacture_logo = config('global.MANUFACTUR_IMAGE_URL') . $CatProdsQry->imglogo . "?ver=" . $verP;
			} else {
				$CatProdsQry->manufacture_logo = '';
			}

            $CatProdsQry->related_item = [];
            /*
			if ($CatProdsQry->related_item != "") {
				$CatProdsQry->related_item = $this->GetSliderProducts($CatProdsQry->related_item, '', '', '', '');
			} else {
				$CatProdsQry->related_item = $this->GetSliderProducts($this->fetchSameManufactureProduct($CatProdsQry->imanufactureid, $CatProdsQry->gender, $CatProdsQry->products_id), '', '', '', '');
			}
            */
			// $CatProdsQry->product_url = $this->getProductRewriteURL($CatProdsQry->products_id, $CatProdsQry->product_name, $CatProdsQry->category_id, $CatProdsQry->vmanufacture);
			$CatProdsQry->product_url = SetProductURL($CatProdsQry->products_id, $CatProdsQry->product_name, $CatProdsQry->category_id);

			######### Extra images start
			$extraImgArr = array();
			$extraImgArrNew = array();
			if ($CatProdsQry->extra_images != '') {
				$extraImgArr = explode("#", $CatProdsQry->extra_images);

				$TotalExtraImages = count($extraImgArr);
				if ($TotalExtraImages > 0) {
					$pr = 0;
					for ($k = 0; $k < $TotalExtraImages; $k++) {
						if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $extraImgArr[$k]) && $extraImgArr[$k] != '') {
							$newimageVal = config('global.PRD_LARGE_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Meduim_Image = config('global.PRD_LARGE_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$newimageVal = config('global.PRD_LARGE_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Large_Image = config('global.PRD_LARGE_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;

							if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $extraImgArr[$k]) && $extraImgArr[$k] != '') {
								$Meduim_Image = config('global.PRD_MEDIUM_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							}

							array_push($extraImgArrNew, (object)['Meduim_Image' => $Meduim_Image, 'Large_Image' => $Large_Image]);
							$pr++;
						}
						else if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $extraImgArr[$k]) && $extraImgArr[$k] != '') {
							$newimageVal = config('global.PRD_MEDIUM_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Meduim_Image = config('global.PRD_MEDIUM_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$newimageVal = config('global.PRD_MEDIUM_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Large_Image = config('global.PRD_MEDIUM_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;

							array_push($extraImgArrNew, (object)['Meduim_Image' => $Meduim_Image, 'Large_Image' => $Large_Image]);
							$pr++;
						}
						else if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $extraImgArr[$k]) && $extraImgArr[$k] != '') {
							$newimageVal = config('global.PRD_THUMB_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Meduim_Image = config('global.PRD_THUMB_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;
							$newimageVal = config('global.PRD_THUMB_IMG_PATH') . $extraImgArr[$k];
							$verP = filemtime($newimageVal);
							$Large_Image = config('global.PRD_THUMB_IMG_URL') . $extraImgArr[$k] . "?ver=" . $verP;

							array_push($extraImgArrNew, (object)['Meduim_Image' => $Meduim_Image, 'Large_Image' => $Large_Image]);
							$pr++;
						}

					}
				}
			}

			$CatProdsQry->extra_images = $extraImgArrNew;
			############ Extra images end

			########### you save and you save price start ###############
			$retail_price = $CatProdsQry->retail_price;
			$yousave = 0;
			if ($retail_price != '' && $retail_price != '0.00')
				$yousave = ($retail_price - $CatProdsQry->product_price) / $retail_price;
			$yousave = $yousave * 100;
			$yousave = number_format($yousave, 0);
			$yousaveprice = $retail_price - $CatProdsQry->product_price;

			$CatProdsQry->yousave = $yousave;
			$CatProdsQry->yousaveprice = $yousaveprice;
			############# you save and you save price end #######################

			$CatProdsQry->PromotionBanner = $this->GetBanner(array('PRODUCT BANNER'), $CatProdsQry->imanufactureid, $CatProdsQry->sku);
			if ($CatProdsQry->PromotionBanner) {
				$CatProdsQry->PromotionBanner = $CatProdsQry->PromotionBanner['PRODUCT_BANNER'][0];
			}

			if (isset($CatProdsQry->range_price_counter) > 1) {
				$CatProdsQry->range_price = 1;
			} else {
				$CatProdsQry->range_price = 0;
			}
			$CatProdsQry->short_description = remove_html_entities(strip_tags($CatProdsQry->short_description));

			$IsCosmo = "No";
			$IsNandansons = "No";
			$IsPerfumePW  = "No";
			$IsPCA  = "No";
			$IsND  = "No";
			$VendorSKU = "";
			$pca_our_price = 0;
			if (Session::has('eusertype') && Session::get('eusertype') && strtolower(Session::get('eusertype')) != 'wholesaler') {
				$cosmo_our_price = $CatProdsQry->cosmo_our_price;
				$nandansons_our_price = $CatProdsQry->nandansons_our_price;
				$perfumeworldwide_our_price = $CatProdsQry->perfumeworldwide_our_price;
				$pca_our_price = $CatProdsQry->pca_our_price;
				$nd_our_price = $CatProdsQry->nd_our_price;
			} else {
				$cosmo_our_price = $CatProdsQry->cosmo_wholesale_price;
				$nandansons_our_price = $CatProdsQry->nandansons_wholesale_price;
				$perfumeworldwide_our_price = $CatProdsQry->perfumeworldwide_wholesale_price;
				$pca_our_price = $CatProdsQry->pca_wholesale_price;
				$nd_our_price = $CatProdsQry->nd_wholesale_price;
			}

			############### stock condition start ########################
			if (isset($CatProdsQry->WebsiteStock) && $CatProdsQry->WebsiteStock == "Out") {
				if ($CatProdsQry->cosmo_sku != '' &&  $CatProdsQry->cosmo_current_stock > 0 &&  $cosmo_our_price > 0) {
					$IsCosmo = "Yes";
					$VendorSKU = $CatProdsQry->cosmo_sku;
				} else if ($CatProdsQry->pca_sku != '' &&  $CatProdsQry->pca_current_stock > 0 && $pca_our_price > 0) {
					$IsPCA  = "Yes";
					$VendorSKU = $CatProdsQry->pca_sku;
				} else if ($CatProdsQry->nandansons_sku != '' &&  $CatProdsQry->nandansons_current_stock > 0 && $nandansons_our_price > 0) {
					$IsNandansons = "Yes";
					$VendorSKU = $CatProdsQry->nandansons_sku;
				}
				else if ($CatProdsQry->perfumeworldwide_sku != '' &&  $CatProdsQry->perfumeworldwide_currentstock > 0 && $perfumeworldwide_our_price > 0) {
					$IsPerfumePW = "Yes";
					$VendorSKU = $CatProdsQry->perfumeworldwide_sku;
				}
				else if ($CatProdsQry->nd_sku != '' &&  $CatProdsQry->nd_current_stock > 0 && $nd_our_price > 0) {
					$IsND = "Yes";
					$VendorSKU = $CatProdsQry->nd_sku;
				}
			}
			################### stock condition end ########################

			$CatProdsQry->IsCosmo = $IsCosmo;
			$CatProdsQry->IsNandansons = $IsNandansons;
			$CatProdsQry->IsPerfumePW = $IsPerfumePW;
			$CatProdsQry->IsPCA = $IsPCA;
			$CatProdsQry->IsND = $IsND;

			$CatProdsQry->SaleIcon = '';
			$CatProdsQry->sale_item = '0';
			if ($CatProdsQry->sale_price > 0 && strtolower(Session::get('eusertype')) != 'wholesaler') {
				$CatProdsQry->sale_item = '1';
			}

			if (isset($CatProdsQry->sale_item) && $CatProdsQry->sale_item == 1) {
				$CatProdsQry->SaleIcon = $this->getIconsSaleDeal("No", "Yes");
			}

			$CatProdsQry->GiftwrapIcon = '';
			if ($CatProdsQry->is_gift_wrap == "Yes") {
				$CatProdsQry->GiftwrapIcon = $this->getIconsSaleDeal("No", "No", "Yes");
			}
		}

		return $CatProdsQry;
	}
}
