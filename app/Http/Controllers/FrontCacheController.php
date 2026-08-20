<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Blade;
use Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\UploadedFile;
use App\Http\Services\CacheService;

class FrontCacheController extends Controller
{
	public function ClearFrontCache(Request $request)
	{
		$DateVaRnEWvAL = 'DealProd'.date("Y-m-d");
		$DealHomeWholesalerProd = 'DealHomeWholesalerProd'.date("Y-m-d");
		$DealHomeRetailerProd = 'DealHomeRetailerProd'.date("Y-m-d");
		$CacheServiceVariables = [
			'brandlist' => ['active_brands','active_product_categories'],
			'AllTheCategory' => ['active_categories', 'active_product_categories']
		];
		//'BLMetaInfo','NRMetaInfo','DPMetaInfo','AllTheBrands','FragranceLandingPageMainBanner','BlogVideoQuery','BlogPageQuery','BlogFontQuery'
		$cacheArray = array('categories','menu_array','brandlist','popular_brands','arr_currency','BottomHtmlText','HomeBanners','HomeCategoryBanners','HOMEMETAINFO','DefaultMetaInfo','DealBrands','settingvars_cache','generalsettingvars_cache','HomeBannersVideo','HomePageProductss','HomePageProductssTop','DealProductCache','HomeDealBanners','RecommendedProductCache','homepage_newarrival_cache','homepage_bestseller_cache','AllTheCategory','VariationProductCache','CATEMETAINFO','BLMetaInfo','DPMetaInfo','NRMetaInfo','AllTheBrands','FragranceLandingPageMainBanner','BlogVideoQuery','BlogPageQuery','BlogFontQuery',$DateVaRnEWvAL,'ShippingStaticCache','Afterpay_payCache','PRODUCTBANNERCache','FragranceLandingPage','SiteOfferCache','TopFirstHeaderBar','TopSecondHeaderBar','topHeaderPopupText','DropshipperFAQ','RetailerFAQ',$DealHomeWholesalerProd,$DealHomeRetailerProd,'PromoBadges','variation_cache','Menu','MaxaromaEditLandingPage','downloadSiteControl','afterpay_payment_method_ca','country_list_ca','state_list_ca','active_brand_ids');
		if (in_array($request->cachevarialbe, $cacheArray)) {
			Cache::forget($request->cachevarialbe);
			$array = array(
				'message' => $request->cachevarialbe.'cache clear sucessfully',
			);

			if(array_key_exists($request->cachevarialbe, $CacheServiceVariables))
			{
				foreach($CacheServiceVariables[$request->cachevarialbe] as $ClearCache)
				{
					CacheService::clearCache($ClearCache);
				}
			}

			return response()->json($array);
		}
	}
	public function CreateFrontCache(Request $request)
	{
		$cacheArray = array('VariationProductCacheCreate');
		if (in_array($request->cachevarialbe, $cacheArray)) {
			Cache::put('variation_cache', json_decode($request->creatcachevar,true));
			$array = array(
				'message' => $request->cachevarialbe.'cache clear sucessfully',
			);

			return response()->json($array);
		}
	}

	public function CreateMaxaromaEditPageCache(Request $request)
	{
		$MaxaromaEditData = $request->all();

		$ImagePath = config('global.MAXAROMA_EDIT_IMAGE_PATH');
		$CacheData = [];
		$ignoreKeys = [];
		foreach($MaxaromaEditData as $key => $secData)
		{
			foreach($secData as $key2 => $secDataDetail)
			{
				if($secDataDetail['section_id'] == '1')
				{
					$newLogo = config('global.MAXAROMA_EDIT_IMAGE_URL').$secDataDetail['hid_logo'];
					if(isset($secDataDetail['logo']))
					{
						$file = $secDataDetail['logo'][0];

						if ($file instanceof UploadedFile && $file->isValid())
						{
							if(file_exists($ImagePath.'preview_logo'))
							{
								unlink($ImagePath.'preview_logo');
							}
							$newLogo = 'preview_logo' . '.' . $file->getClientOriginalExtension();
							$file->move($ImagePath, $newLogo);
							$newLogo = config('global.MAXAROMA_EDIT_IMAGE_URL').$newLogo;
						}
					}
					$newHeaderImage = config('global.MAXAROMA_EDIT_IMAGE_URL').$secDataDetail['hid_header_image'];
					if(isset($secDataDetail['header_image']))
					{
						$file = $secDataDetail['logo'][0];

						if ($file instanceof UploadedFile && $file->isValid())
						{
							if(file_exists($ImagePath.'preview_header_image'))
							{
								unlink($ImagePath.'preview_header_image');
							}
							$newHeaderImage = 'preview_header_image' . '.' . $file->getClientOriginalExtension();
							$file->move($ImagePath, $newHeaderImage);
							$newHeaderImage = config('global.MAXAROMA_EDIT_IMAGE_URL').$newHeaderImage;
						}
					}
					$CacheData[$secDataDetail['section_id']] = [
						'short_description' => $secDataDetail['short_description'],
						'logo' => $newLogo,
						'header_image' => $newHeaderImage
					];
				}
				if($secDataDetail['section_id'] == '3')
				{

					$header_image = $secDataDetail['hid_sec3_banner'];
					$header_image = config('global.MAXAROMA_EDIT_IMAGE_URL').$header_image;

					$file = 'sec3_banner';
					if ($file instanceof UploadedFile && $file->isValid())
					{
						$PreviewImgName = 'preview_sec3_banner.' . $file->getClientOriginalExtension();
						if(file_exists($ImagePath.$PreviewImgName))
						{
							unlink($ImagePath.$PreviewImgName);
						}
						$file->move($ImagePath, $PreviewImgName);
						$header_image = config('global.MAXAROMA_EDIT_IMAGE_URL').$PreviewImgName;
					}

					$header_image_mobile = $secDataDetail['hid_sec3_banner_mobile'];
					$header_image_mobile = config('global.MAXAROMA_EDIT_IMAGE_URL').$header_image_mobile;

					$file = 'sec3_banner_mobile';
					if ($file instanceof UploadedFile && $file->isValid())
					{
						$PreviewImgName = 'preview_sec3_banner_mobile.' . $file->getClientOriginalExtension();
						if(file_exists($ImagePath.$PreviewImgName))
						{
							unlink($ImagePath.$PreviewImgName);
						}
						$file->move($ImagePath, $PreviewImgName);
						$header_image_mobile = config('global.MAXAROMA_EDIT_IMAGE_URL').$PreviewImgName;
					}
					$CacheData[$secDataDetail['section_id']] = [
						'introduction' => $secDataDetail['introduction'],
						'title' => $secDataDetail['title'],
						'header_image' => $header_image,
						'header_image_mobile' => $header_image_mobile,
						'link' => $secDataDetail['link']
					];

				}
				if($secDataDetail['section_id'] == '2' || $secDataDetail['section_id'] == '4' || $secDataDetail['section_id'] == '6')
				{
					foreach($secDataDetail as $dataKey => $dataVal)
					{
						$field = 'brand_image_';
						$CompareField = substr($dataKey,0,12);
						if(strstr($dataKey,$field) && $CompareField == $field)
						{
							$expKey = explode("_",$dataKey);
							$rowId = $expKey[count($expKey)-1];
							$ignoreKeys[] = $dataKey;
							if(isset($secDataDetail['brand_image_'.$rowId][0]))
							{
								$file = $secDataDetail['brand_image_'.$rowId][0];
								$newName = config('global.MAXAROMA_EDIT_IMAGE_URL').$secDataDetail['hid_brand_image_'.$rowId];

								if ($file instanceof UploadedFile && $file->isValid())
								{
									if(file_exists($ImagePath.'preview_brand_image_'.$secDataDetail['section_id']."_".$rowId))
									{
										unlink($ImagePath.'preview_brand_image_'.$secDataDetail['section_id']."_".$rowId);
									}
									$newName = 'preview_brand_image_'.$secDataDetail['section_id']."_".$rowId . '.' . $file->getClientOriginalExtension();
									$file->move($ImagePath, $newName);
									$newName = config('global.MAXAROMA_EDIT_IMAGE_URL').$newName;
								}

								$ignoreKeys[] = 'hid_'.$dataKey;

								$CacheData[$secDataDetail['section_id']][] = [
									'id' => (int)$rowId,
									'brand_title_'.$rowId => $secDataDetail['brand_title_'.$rowId],
									'link_'.$rowId => $secDataDetail['link_'.$rowId],
									'brand_image_'.$rowId => $newName,
								];
							}
						}
					}

					foreach($secDataDetail as $dataKey => $dataVal)
					{
						$field = 'hid_brand_image_';
						if(!in_array($dataKey,$ignoreKeys) && strstr($dataKey,$field))
						{
							$expKey = explode("_",$dataKey);
							$rowId = $expKey[count($expKey)-1];
							$CacheData[$secDataDetail['section_id']][] = [
								'id' => (int)$rowId,
								'brand_title_'.$rowId => $secDataDetail['brand_title_'.$rowId],
								'brand_image_'.$rowId => config('global.MAXAROMA_EDIT_IMAGE_URL').$secDataDetail['hid_brand_image_'.$rowId],
								'link_'.$rowId => $secDataDetail['link_'.$rowId]
							];
						}
					}

					if(isset($CacheData[$secDataDetail['section_id']]) && count($CacheData[$secDataDetail['section_id']]) > 0)
					{
						array_multisort(array_column($CacheData[$secDataDetail['section_id']], 'id'), SORT_ASC, $CacheData[$secDataDetail['section_id']]);
					}
				}
				if($secDataDetail['section_id'] == '5')
				{
					$header_image = $secDataDetail['hid_sec5_banner'];
					$header_image = config('global.MAXAROMA_EDIT_IMAGE_URL').$header_image;

					$file = 'sec5_banner';
					if ($file instanceof UploadedFile && $file->isValid())
					{
						$PreviewImgName = 'preview_sec5_banner.' . $file->getClientOriginalExtension();
						if(file_exists($ImagePath.$PreviewImgName))
						{
							unlink($ImagePath.$PreviewImgName);
						}
						$file->move($ImagePath, $PreviewImgName);
						$header_image = config('global.MAXAROMA_EDIT_IMAGE_URL').$PreviewImgName;
					}

					$header_image_mobile = $secDataDetail['hid_sec5_banner_mobile'];
					$header_image_mobile = config('global.MAXAROMA_EDIT_IMAGE_URL').$header_image_mobile;

					$file = 'sec5_banner_mobile';
					if ($file instanceof UploadedFile && $file->isValid())
					{
						$PreviewImgName = 'preview_sec5_banner_mobile.' . $file->getClientOriginalExtension();
						if(file_exists($ImagePath.$PreviewImgName))
						{
							unlink($ImagePath.$PreviewImgName);
						}
						$file->move($ImagePath, $PreviewImgName);
						$header_image_mobile = config('global.MAXAROMA_EDIT_IMAGE_URL').$PreviewImgName;
					}
					$CacheData[$secDataDetail['section_id']] = [
						'title' => $secDataDetail['title'],
						'header_image' => $header_image,
						'header_image_mobile' => $header_image_mobile,
						'link' => $secDataDetail['link']
					];

				}
			}
		}
		Cache::put('MaxaromaEditLandingPagePreview', $CacheData);
		$array = array(
			'status' => '1',
			'message' => 'MaxaromaEditLandingPagePreview cache clear sucessfully',
		);
		return response()->json($array);
	}
}
