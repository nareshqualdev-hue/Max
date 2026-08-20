<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;

use Hash;
use Session;
use App\Models\MetaInfo;
use App\Models\Customer;
use App\Models\ProductsCategory;
use App\Models\Category;
use App\Models\Products;
use App\Models\FreeGiftProduct;
use App\Models\ProductsOne;
use App\Models\MarkupPrices;
use App\Models\Stockalert;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PaymentMethod;
use App\Models\Dealofweek;
use App\Models\RewardRule;
use App\Models\ReferFriend;
use App\Models\RewardPoint;
use App\Models\MailBanner;
use App\Models\GiftCertificate;
use App\Models\ShippingMode;
use App\Models\AutoDiscount;
use App\Models\QuantityDiscount;
use App\Models\Coupon;
use App\Models\TaxAreas;
use App\Models\TaxRates;

use App\Models\Manufacture;
use App\Models\SiteControl;

use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\EncryptTrait;
use App\Http\Controllers\Traits\AfterpayTrait;
use App\Http\Controllers\Traits\CartTrait;
use DB;
use Mail;
use PDF;
use Cache;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportOrders;

class GeneralController extends Controller
{
	use CommonTrait;
	use VendorTrait;
	use EncryptTrait;
	use AfterpayTrait;
	use CartTrait;

	public $PageData;
	public function __construct()
    {
		$PageType = 'NR';
		$MetaInfo = MetaInfo::where('type','=',$PageType)->get();
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
			$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
		}
	}

	public function WholeSaleProducts(Request $request)
	{

		if(!Auth::user())
			return redirect('/login.html');

		$markuparr = MarkupPrices::get();
		$search_keyword = $request['search_keyword'];
		$SearchAll = 'No';
		if(isset($request->all_items))
			$SearchAll = 'Yes';
		$this->PageData['SearchAll'] = $SearchAll;
		$product_arr = $this->GetSpecialPriceWholesaler($request,$markuparr);
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Special Product Price List';
		$this->PageData['JSFILES'] = ['jquery-ui1.12.1.js','wholesaleproducts.js'];
		$this->PageData['CSSFILES'] = ['jquery-ui1.12.1.css','myaccount.css','custom.css'];
		$this->PageData['ProductArr'] = $product_arr['DataArr'];
		$this->PageData['MarkupArr'] = $markuparr;
		$this->PageData['Search_Keyword'] = $search_keyword;
		$this->PageData['TotalProducts'] = $product_arr['TotalProducts'];
		$this->PageData['PerPage'] = $product_arr['PerPage'];

		if($request->isMethod('post')){
			$ProductHTML = view('myaccount.wholesaleproduct')->with($this->PageData)->render();

			return response()->json(array('TotalProducts' => $product_arr['TotalProducts'], 'ProductHTML'=>$ProductHTML, 'PerPage'=>$product_arr['PerPage']));
		}else{
			return view('myaccount.wholesaleproducts')->with($this->PageData);
		}
	}

	public function SearchWholeSaleProducts(Request $request)
	{
		$letters = $request['search_keyword'];
		$letters = mb_convert_encoding($letters,"HTML-ENTITIES", "UTF-8" );
		$letters = preg_replace("/[^a-z0-9,&; \'\’]/si","",$letters);

		$prodData = DB::table('pu_products as po')
						->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
							'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
							'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
							'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
							'po.nd_sku','po.nd_current_stock','po.nd_wholesale_price','po.nd_our_price','po.nd_retail_price',
							'po.perfumeworldwide_sku','po.perfumeworldwide_currentstock','po.perfumeworldwide_wholesale_price','po.perfumeworldwide_our_price','po.perfumeworldwide_retail_price',
							'po.vtype','po.variation_id','po.refine_feature','po.product_type','c.category_id','po.UPC','m.vmanufacture')

							//->select('p.products_id','p.image','p.imanufactureid','c.category_id','m.vmanufacture','p.product_name','p.sku','p.UPC')
							->join('pu_products_category as pc','po.products_id','=','pc.products_id')
							->join('pu_category as c','c.category_id','=','pc.category_id')
							->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
							//->where('p.product_name','REGEXP','[[:<:]]'.$letters)
							->where(function($query) use ($letters){
									$query->orWhere('po.product_name','LIKE','%'.$letters.'%')
										  ->orWhere('po.sku','=',$letters)
										  ->orWhere('po.UPC','=',$letters);
								})
							->where('po.status','=','1')->groupBy('po.products_id')->limit(100)->get();

		$search_detail_arr = array();
		if ( count($prodData) > 0 )
		{
			for($i=0; $i<count($prodData); $i++)
			{
				$prodData[$i] = $this->SetProduct($prodData[$i]);
				if($request['all_items'] == 'No')
				{
					if($prodData[$i]->stock == 'Out')
						continue;
				}

				if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prodData[$i]->image) && $prodData[$i]->image != '')
				{$thumb_image = config('global.PRD_THUMB_IMG_URL').$prodData[$i]->image; }
			    else
				{$thumb_image = config('global.NO_IMAGE_THUMB');}

				$product_name = $prodData[$i]->product_name;
				$pid 	= $prodData[$i]->products_id;
				$product_url = $this->getProductRewriteURL($prodData[$i]->products_id,$prodData[$i]->product_name,$prodData[$i]->category_id,$prodData[$i]->vmanufacture);
				//$strtest = '<a href='.$db_recs[$i]['product_url'].'>'.$thumb_image.' '.ucwords($product_name).'</a>';
				$search_detail_arr[$i]['data']['thumb_image'] = $thumb_image;
				$search_detail_arr[$i]['data']['product_name'] = $product_name;
				$search_detail_arr[$i]['data']['pid'] = $pid;
				$search_detail_arr[$i]['data']['product_url'] = $product_url;
				$search_detail_arr[$i]['data']['sku'] = $prodData[$i]->sku;
				$search_detail_arr[$i]['data']['upc'] = $prodData[$i]->UPC;

				$search_detail_arr[$i]['value'] = $pid;
				$search_detail_arr[$i]['label'] = $product_name;

			}
		} else {
			$search_detail_arr[0]['value'] = "0";
			$search_detail_arr[0]['label'] = $letters;
			$search_detail_arr[0]['data']['thumb_image'] = "";
			$search_detail_arr[0]['data']['product_name'] = $letters;
			$search_detail_arr[0]['data']['pid'] = "0";
			$search_detail_arr[0]['data']['product_url'] = "";
			$search_detail_arr[0]['data']['sku'] = "";
			$search_detail_arr[0]['data']['upc'] = "";
		}
		//array_unshift($search_detail_arr,$data_key);

		//echo "<pre>";print_r($search_detail_arr);exit;
		return response()->json($search_detail_arr);
	}

	public function SpecialWholeSaleProductList(Request $request)
	{
		if(!Auth::user())
			return redirect('/login.html');

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Download Special Wholesaler Product Pricelist';
		$this->PageData['JSFILES'] = ['jquery-ui1.12.1.js','wholesaleproducts.js'];
		$this->PageData['CSSFILES'] = ['jquery-ui1.12.1.css','myaccount.css','custom.css'];

		$cust_detail = Customer::select('DownloadSpecialPricelist')
								->where('customer_id', '=', Session::get('sess_icustomerid'))
								->get();

		/* $SpecialCustomerFlag = "No";
		if(isset($cust_detail[0]->DownloadSpecialPricelist)){
			$SpecialCustomerFlag = $cust_detail[0]->DownloadSpecialPricelist;
		}

		$this->PageData['SpecialCustomerFlag'] = $SpecialCustomerFlag; */

		if(Session::get('eusertype')=='Wholesaler' && Session::get('is_dropshipper')!='Yes' && Session::get('SpecialCustomerFlag') !="Yes" )
		{
			return redirect('/');
		}

		return view('myaccount.download_special_wholesaler_list')->with($this->PageData);
	}

	public function SpecialWholeSaleProductList_Download(Request $request)
	{
		if(!Auth::user())
			return redirect('/login.html');

		if(Session::get('eusertype')!='Wholesaler' && Session::get('is_dropshipper')!='Yes' && Session::get('SpecialCustomerFlag') != "Yes" )
		{
			return redirect('/');
		}

		$cust_detail = Customer::select('warehouse')
								->where('customer_id', '=', Session::get('sess_icustomerid'))
								->where('warehouse', '!=', '')
								->get();

		if($cust_detail->count()<=0)
		{
			$err_msg = "File Not Found";
			Session::flash('error',$err_msg);
			return redirect(config('global.SITE_URL').'specialwholesaleproductpricelist');
		}

		$warehouse = $cust_detail[0]->warehouse;

		if($warehouse=="")
		{
			$err_msg = "File Not Found";
			Session::flash('error',$err_msg);
			return redirect(config('global.SITE_URL').'specialwholesaleproductpricelist');
		}
		$warehouseArr = explode("#",$warehouse);
		$fd1 = "";
		$fd2 = "";
		$fd3 = "";
		$fd4 = "";
		$fd5 = "";
		$fd6 = "";

		$download_control = array();
		if (!Cache::has('downloadSiteControl')) {
			$site_control = SiteControl::find(1);
			Cache::put('downloadSiteControl', $site_control);
			if(isset($site_control->description) && $site_control->description != "")
			{
				$download_control = json_decode($site_control->description,true);
			}
		} else {
			$site_control = Cache::get('downloadSiteControl');
			if(isset($site_control->description) && $site_control->description != "")
			{
				$download_control = json_decode($site_control->description,true);
			}
		}

		for($p=0; $p < count($warehouseArr); $p++)
		{
			if($warehouseArr[$p]=="Website")
			{
				 $fd1 = "Website";
			}
			if($warehouseArr[$p]=="Cosmo")
			{
				 $fd2 = "Cosmo";
			}
			if($warehouseArr[$p]=="Nandansons")
			{
				 $fd3 = "Nandansons";
			}
			if($warehouseArr[$p]=="Perfumeworldwide")
			{
				 $fd4 = "Perfumeworldwide";
			}
			if($warehouseArr[$p]=="PCA")
			{
				 $fd5 = "PCA";
			}
			if($warehouseArr[$p]=="Nandansons")
			{
				$fd6 = "Nandansons";
			}
		}

		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Download_Special_Wholesale_Product_Pricelist".Session::get('sess_icustomerid').".xls"))
		{
			unlink(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Download_Special_Wholesale_Product_Pricelist".Session::get('sess_icustomerid').".xls");
		}

		$export_file_name = "Download_Special_Wholesale_Product_Pricelist".Session::get('sess_icustomerid').".xls";
		$export_file_path = config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH').$export_file_name;

		$start_limit   = 0;
		$end_limit	   = 300;
		$process_batch = 0;
		$total_batch   = 0;

		$StockCondition = '';
		$aliasname = "p";
		$casewhenprice1 = "";
		$casewhenend = "";

		if(Session::get('sess_useremail') == 'qqualdev@gmail.com')
		{
			/*$res_total_prods = DB::table('pu_products as p')
			->select('p.products_id')
			->join('pu_brand as pb','pb.brand_id','=','p.brand_id')
			->join('pu_products_one as po','po.products_id','=','p.products_id')
			->join('pu_manufacture as pm','pm.imanufactureid','=','p.imanufactureid')
			->join('pu_products_category as pc','pc.products_id','=','p.products_id')
			->join('pu_category as c','c.category_id','=','pc.category_id')
			->where('p.status','=','1')
			->where('c.status','=','1')
			->whereNotIn('c.category_id',['68','69','70','71'])
			->whereIn('p.product_type',['both','wholesaler'])
			->groupBy('p.products_id')->get();*/
			$res_total_prods_sql = DB::table('pu_products as p')
			->select('p.products_id')
			->join('pu_brand as pb','pb.brand_id','=','p.brand_id')
			->join('pu_products_one as po','po.products_id','=','p.products_id')
			->join('pu_manufacture as pm','pm.imanufactureid','=','p.imanufactureid')
			->join('pu_products_category as pc','pc.products_id','=','p.products_id')
			->join('pu_category as c','c.category_id','=','pc.category_id')
			->where('p.status','=','1')
			->where('c.status','=','1')
			//->whereNotIn('c.category_id',['68','69','70','71'])
			->whereIn('p.product_type',['both','wholesaler','retailer']);
			//->groupBy('p.products_id')->get();
			if(isset($download_control['exclude_pocket_perfume']) && $download_control['exclude_pocket_perfume']=='Yes'){
				$res_total_prods_sql = $res_total_prods_sql->whereNotIn('c.category_id',['68','69','70','71'])->Where('p.is_atomizer','=','No');				 
			}
			if(isset($download_control['imanufactureid']) && count($download_control['imanufactureid']) > 0){
				$res_total_prods_sql = $res_total_prods_sql->whereNotIn('p.imanufactureid',$download_control['imanufactureid']);
			}
			$res_total_prods = $res_total_prods_sql->groupBy('p.products_id')->get();
		} else {
			/*$res_total_prods = DB::table('pu_products as p')
			->select('p.products_id')
			->join('pu_brand as pb','pb.brand_id','=','p.brand_id')
			->join('pu_products_one as po','po.products_id','=','p.products_id')
			->join('pu_manufacture as pm','pm.imanufactureid','=','p.imanufactureid')
			->join('pu_products_category as pc','pc.products_id','=','p.products_id')
			->join('pu_category as c','c.category_id','=','pc.category_id')
			->where('p.status','=','1')
			->where('c.status','=','1')
			->whereNotIn('c.category_id',['68','69','70','71'])
			->whereIn('p.product_type',['both','wholesaler'])
			->groupBy('p.products_id')->get();*/

			$res_total_prods_sql = DB::table('pu_products as p')
			->select('p.products_id')
			->join('pu_brand as pb','pb.brand_id','=','p.brand_id')
			->join('pu_products_one as po','po.products_id','=','p.products_id')
			->join('pu_manufacture as pm','pm.imanufactureid','=','p.imanufactureid')
			->join('pu_products_category as pc','pc.products_id','=','p.products_id')
			->join('pu_category as c','c.category_id','=','pc.category_id')
			->where('p.status','=','1')
			->where('c.status','=','1')
			//->whereNotIn('c.category_id',['68','69','70','71'])
			->whereIn('p.product_type',['both','wholesaler','retailer']);
			//->groupBy('p.products_id')->get();
			if(isset($download_control['exclude_pocket_perfume']) && $download_control['exclude_pocket_perfume']=='Yes'){
				$res_total_prods_sql = $res_total_prods_sql->whereNotIn('c.category_id',['68','69','70','71'])->Where('p.is_atomizer','=','No');
			}
			if(isset($download_control['imanufactureid']) && count($download_control['imanufactureid']) > 0){
				$res_total_prods_sql = $res_total_prods_sql->whereNotIn('p.imanufactureid',$download_control['imanufactureid']);
			}
			$res_total_prods = $res_total_prods_sql->groupBy('p.products_id')->get();
		}
		$total_records = $res_total_prods->count();
		$total_batch   = ceil($total_records/$end_limit);
		$csv_data = array();
		// $total_batch = 2;

		if($total_records > 0)
		{
			$per = '';
			$db_recs = MarkupPrices::get();
			//echo "<pre>"; print_r($db_recs); exit;

			$tot_markup_rice = $db_recs->count();

			if($request['rc']=='')
			$rc=0;
			else
			$rc=$request['rc'];

			if(file_exists($export_file_path))
			{
			  unlink($export_file_path);
			}

			for($b=0; $b<$total_batch; $b++){
				/*$result = DB::table('pu_products as p')
						->select('p.products_id','p.minimum_stock','p.sku','p.product_name','p.display_position','p.product_description','p.short_description','p.cosmo_sku','p.cosmo_current_stock','p.nandansons_sku','p.gender','p.size','p.nandansons_current_stock','p.perfumeworldwide_sku','p.perfumeworldwide_currentstock','p.current_stock','p.cosmo_wholesale_price','p.nandansons_wholesale_price','p.perfumeworldwide_wholesale_price','p.pca_sku','p.pca_wholesale_price','p.pca_current_stock','p.cosmo_price','p.nandansons_price','p.perfumeworldwide_price','p.pca_price','p.w_our_cost','p.wholesale_price','pm.vmanufacture','pb.brand_name','p.retail_price','p.image','c.category_name','p.UPC','c.category_id','po.special_website_price','p.cosmo_retail_price','p.nandansons_retail_price','p.perfumeworldwide_retail_price','p.pca_retail_price',
						'p.nd_sku','p.nd_current_stock','p.nd_wholesale_price','p.nd_retail_price','p.nd_price')
						->join('pu_products_one as po','po.products_id','=','p.products_id')
						->join('pu_brand as pb','pb.brand_id','=','p.brand_id')
						->join('pu_manufacture as pm','pm.imanufactureid','=','p.imanufactureid')
						->join('pu_products_category as pc','pc.products_id','=','p.products_id')
						->join('pu_category as c','c.category_id','=','pc.category_id')
						->where('p.status','=','1')
						->where('c.status','=','1')
						// ->where('p.sku','=','UP085715163103')
						->whereNotIn('c.category_id',['68','69','70','71'])
						->whereIn('p.product_type',['both','wholesaler'])
						->groupBy('p.products_id')
						->orderBy('pm.vmanufacture')
						->offset($start_limit)
						->limit($end_limit)
						->get();*/

				$result_sql = DB::table('pu_products as p')
						->select('p.products_id','p.minimum_stock','p.sku','p.product_name','p.display_position','p.product_description','p.short_description','p.cosmo_sku','p.cosmo_current_stock','p.nandansons_sku','p.gender','p.size','p.nandansons_current_stock','p.perfumeworldwide_sku','p.perfumeworldwide_currentstock','p.current_stock','p.cosmo_wholesale_price','p.nandansons_wholesale_price','p.perfumeworldwide_wholesale_price','p.pca_sku','p.pca_wholesale_price','p.pca_current_stock','p.cosmo_price','p.nandansons_price','p.perfumeworldwide_price','p.pca_price','p.w_our_cost','p.wholesale_price','pm.vmanufacture','pb.brand_name','p.retail_price','p.image','c.category_name','p.UPC','c.category_id','po.special_website_price','p.cosmo_retail_price','p.nandansons_retail_price','p.perfumeworldwide_retail_price','p.pca_retail_price',
						'p.nd_sku','p.nd_current_stock','p.nd_wholesale_price','p.nd_retail_price','p.nd_price','p.product_type')
						->join('pu_products_one as po','po.products_id','=','p.products_id')
						->join('pu_brand as pb','pb.brand_id','=','p.brand_id')
						->join('pu_manufacture as pm','pm.imanufactureid','=','p.imanufactureid')
						->join('pu_products_category as pc','pc.products_id','=','p.products_id')
						->join('pu_category as c','c.category_id','=','pc.category_id')
						->where('p.status','=','1')
						->where('c.status','=','1')
						// ->where('p.sku','=','UP085715163103')
						//->whereNotIn('c.category_id',['68','69','70','71'])
						->whereIn('p.product_type',['both','wholesaler','retailer']);
						/*->groupBy('p.products_id')
						->orderBy('pm.vmanufacture')
						->offset($start_limit)
						->limit($end_limit)
						->get();	*/	

					if(isset($download_control['exclude_pocket_perfume']) && $download_control['exclude_pocket_perfume']=='Yes'){
						$result_sql = $result_sql->whereNotIn('c.category_id',['68','69','70','71'])->Where('p.is_atomizer','=','No');
					}
					if(isset($download_control['imanufactureid']) && count($download_control['imanufactureid']) > 0){
						$result_sql = $result_sql->whereNotIn('p.imanufactureid',$download_control['imanufactureid']);
					}
					$result = $result_sql->groupBy('p.products_id')->orderBy('pm.vmanufacture')
						->offset($start_limit)
						->limit($end_limit)
						->get();			

				$file_content = '';
				$cnt_tot_prd = $result->count();

				$check_categoryarray = ['15','39','74','203','7','8','11','27'];
				$fd1_check_stock = 8;
				$check_stock_1 = 71;
				$check_stock_2 = 36;

				for( $p=0; $p<$cnt_tot_prd; $p++) {
					//get product price
						$product_price = 0;
						$retail_price = 0;
						$product_price_minimum = 0;
						$minimum_product_price_arr = [];

						$WebsiteStock = "In";
						if($result[$p]->current_stock <= $fd1_check_stock || $result[$p]->minimum_stock > $result[$p]->current_stock){
							$WebsiteStock = "Out";
						}
						$is_website = "No";
						$is_cosmo = "No";
						$is_nandanson = "No";
						$is_pww = "No";
						$is_pca = "No";
						$is_nd = "No";

						if($fd1!='')
						{
							if($WebsiteStock == "In"){
								$is_website = "Yes";
								$product_price = ($result[$p]->wholesale_price > 0) ? $result[$p]->wholesale_price : 0;
								$retail_price = ($result[$p]->retail_price > 0) ? $result[$p]->retail_price : 0;
							}
						}
						if($fd2!='')
						{
							$product_price_cosmo = "";

							if($result[$p]->cosmo_sku != "" && $result[$p]->cosmo_current_stock > $check_stock_1){
								$is_cosmo = "Yes";
								$product_price_cosmo = ($result[$p]->cosmo_price > 0) ? $result[$p]->cosmo_price : 0;
								$retail_price_cosmo = ($result[$p]->cosmo_retail_price > 0) ? $result[$p]->cosmo_retail_price : 0;

							}else if($result[$p]->cosmo_sku != "" && $result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_cosmo = "Yes";
								$product_price_cosmo = ($result[$p]->cosmo_price > 0) ? $result[$p]->cosmo_price : 0;
								$retail_price_cosmo = ($result[$p]->cosmo_retail_price > 0) ? $result[$p]->cosmo_retail_price : 0;
							}

							if(isset($product_price_cosmo) && $product_price_cosmo > 0){
								$minimum_product_price_arr[] = ($product_price_cosmo > 0) ? $product_price_cosmo : 9999999999;
								if($is_cosmo == "Yes" && $is_website == "No"){
									$product_price = $product_price_cosmo;
									$retail_price = $retail_price_cosmo;
								}
							}
						}

						if($fd3!='')
						{
							$product_price_nand = "";

							if($result[$p]->nandansons_sku != "" && $result[$p]->nandansons_current_stock > $check_stock_1){
								$is_nandanson = "Yes";
								$product_price_nand = ($result[$p]->nandansons_price > 0) ? $result[$p]->nandansons_price : 0;
								$retail_price_nand = ($result[$p]->nandansons_retail_price > 0) ? $result[$p]->nandansons_retail_price : 0;
							}else if($result[$p]->nandansons_sku != "" && $result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_nandanson = "Yes";
								$product_price_nand = ($result[$p]->nandansons_price > 0) ? $result[$p]->nandansons_price : 0;
								$retail_price_nand = ($result[$p]->nandansons_retail_price > 0) ? $result[$p]->nandansons_retail_price : 0;
							}

							if(isset($product_price_nand) && $product_price_nand > 0){
								$minimum_product_price_arr[] = ($product_price_nand > 0) ? $product_price_nand : 9999999999;
								if($is_nandanson == "Yes" && $is_cosmo == "No" && $is_website == "No"){
									$product_price = $product_price_nand;
									$retail_price = $retail_price_nand;
								}
							}
						}
						if($fd4!='')
						{
							$product_price_prfm = "";

							if($result[$p]->perfumeworldwide_sku != "" && $result[$p]->perfumeworldwide_currentstock > $check_stock_1){
								$is_pww = "Yes";
								$product_price_prfm = ($result[$p]->perfumeworldwide_price > 0) ? $result[$p]->perfumeworldwide_price : 0;
								$retail_price_prfm = ($result[$p]->perfumeworldwide_retail_price > 0) ? $result[$p]->perfumeworldwide_retail_price : 0;
							}else if($result[$p]->perfumeworldwide_sku != "" && $result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_pww = "Yes";
								$product_price_prfm = ($result[$p]->perfumeworldwide_price > 0) ? $result[$p]->perfumeworldwide_price : 0;
								$retail_price_prfm = ($result[$p]->perfumeworldwide_retail_price > 0) ? $result[$p]->perfumeworldwide_retail_price : 0;
							}

							if(isset($product_price_prfm) && $product_price_prfm > 0){
								$minimum_product_price_arr[] = ($product_price_prfm > 0) ? $product_price_prfm : 9999999999;
								if($is_pww == "Yes" && $is_nandanson == "No" && $is_cosmo == "No" && $is_website == "No"){
									$product_price = $product_price_prfm;
									$retail_price = $retail_price_prfm;
								}
							}
						}
						if($fd5!='')
						{
							$product_price_pca = "";

							if($result[$p]->pca_sku != "" && $result[$p]->pca_current_stock > $check_stock_1){
								$is_pca = "Yes";
								$product_price_pca = ($result[$p]->pca_price > 0) ? $result[$p]->pca_price : 0;
								$retail_price_pca = ($result[$p]->pca_retail_price > 0) ? $result[$p]->pca_retail_price : 0;
							}else if($result[$p]->pca_sku != "" && $result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_pca = "Yes";
								$product_price_pca = ($result[$p]->pca_price > 0) ? $result[$p]->pca_price : 0;
								$retail_price_pca = ($result[$p]->pca_retail_price > 0) ? $result[$p]->pca_retail_price : 0;
							}

							if(isset($product_price_pca) && $product_price_pca > 0){
								$minimum_product_price_arr[] = ($product_price_pca > 0) ? $product_price_pca : 9999999999;
								if($is_pca == "Yes" && $is_pww == "No" && $is_nandanson == "No" && $is_cosmo == "No" && $is_website == "No"){
									$product_price = $product_price_pca;
									$retail_price = $retail_price_pca;
								}
							}
						}

						if($fd6!='')
						{
							$product_price_nd = "";

							if($result[$p]->nd_sku != "" && $result[$p]->nd_current_stock > $check_stock_1){
								$is_nd = "Yes";
								$product_price_nd = ($result[$p]->nd_price > 0) ? $result[$p]->nd_price : 0;
								$retail_price_nd = ($result[$p]->nd_retail_price > 0) ? $result[$p]->nd_retail_price : 0;
							}else if($result[$p]->nd_sku != "" && $result[$p]->nd_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_nd = "Yes";
								$product_price_nd = ($result[$p]->nd_price > 0) ? $result[$p]->nd_price : 0;
								$retail_price_nd = ($result[$p]->nd_retail_price > 0) ? $result[$p]->nd_retail_price : 0;
							}

							if(isset($product_price_nd) && $product_price_nd > 0){
								$minimum_product_price_arr[] = ($product_price_nd > 0) ? $product_price_nd : 9999999999;
								if($is_nd == 'Yes' && $is_pca == "No" && $is_pww == "No" && $is_nandanson == "No" && $is_cosmo == "No" && $is_website == "No"){
									$product_price = $product_price_nd;
									$retail_price = $retail_price_nd;
								}
							}
						}

					if($product_price <= 0){
						continue;
					 }
					$result[$p]->product_price = $product_price;
					$result[$p]->retail_price = $retail_price;
					$result[$p]->minimum_product_price = $product_price;
					if(isset($minimum_product_price_arr) && count($minimum_product_price_arr) > 0){
						$result[$p]->minimum_product_price = min($minimum_product_price_arr);
					}

					if($fd1!='' && $WebsiteStock == "In")
				    {
						for($i=0; $i<$tot_markup_rice; $i++)
						{
							if($db_recs[$i]->markup_value !="")
							{
								$mvalu = explode("-",$db_recs[$i]->markup_value);
								$mvalcount = count($mvalu);

								if($mvalcount>1)
								{
									$per = $db_recs[$i]->markup_percent;
									$result[$p]->{'wholesale_price_'.$i} = number_format(($result[$p]->product_price - $result[$p]->product_price*$per/100),2,".","");
								}
								else
								{
									$per = $db_recs[$i]->markup_percent;
									$result[$p]->{'wholesale_price_'.$i} = number_format(($result[$p]->product_price - $result[$p]->product_price*$per/100),2,".","");
								}
							}
						}
					}
					$data_arr = (array)$result[$p];
					extract($data_arr);

					$products_id = $result[$p]->products_id;
					$SKU = trim($result[$p]->sku);

					$UPC = trim($result[$p]->UPC);

					$Product_Name = trim($result[$p]->product_name);
					$Product_Name = str_replace('"','""',$Product_Name);

					$CatNewName = "";
					if(preg_match('/unboxed/i',strtolower($Product_Name)) || preg_match('/unbox/i',strtolower($Product_Name)) || preg_match('/unboxes/i',strtolower($Product_Name)))
					{
						$CatNewName = "Unboxed";
					}

					$Product_Name = str_replace("Gift Set","",trim($Product_Name));
					$Product_Name = str_replace("Gift set","",trim($Product_Name));
					$Product_Name = str_replace("gift Set","",trim($Product_Name));
					$Product_Name = str_replace("Gifts Set","",trim($Product_Name));
					$Product_Name = str_replace("Gifts Sets","",trim($Product_Name));
					$Product_Name = str_replace("gift set","",trim($Product_Name));
					$Product_Name = str_replace("gifts set","",trim($Product_Name));
					$Product_Name = str_replace("gifts sets","",trim($Product_Name));
					$Product_Name = str_replace("giftset","",trim($Product_Name));
					$Product_Name = str_replace("giftsets","",trim($Product_Name));
					$Product_Name = str_replace("Giftset","",trim($Product_Name));
					$Product_Name = str_replace("Giftsets","",trim($Product_Name));
					$Product_Name = str_replace("GiftSet","",trim($Product_Name));
					$Product_Name = str_replace("GiftSets","",trim($Product_Name));
					$Product_Name = str_replace("Sets","",trim($Product_Name));
					$Product_Name = str_replace("sets","",trim($Product_Name));
					$Product_Name = str_replace("Set","",trim($Product_Name));
					$Product_Name = str_replace("set","",trim($Product_Name));
					$Product_Name = str_replace("sets","",trim($Product_Name));
					$Product_Name = str_replace("Spray","",trim($Product_Name));
					$Product_Name = str_replace("spray","",trim($Product_Name));
					$Product_Name = str_replace("perfume","",trim($Product_Name));
					$Product_Name = str_replace("Perfume","",trim($Product_Name));
					$Product_Name = str_replace("Spray","",trim($Product_Name));
					$Product_Name = str_replace("spray","",trim($Product_Name));

					$Product_NameArr = preg_split("/\bfor\b/iu", $Product_Name);

					if(!isset($Product_NameArr[1]) || $Product_NameArr[1]=='')
					{
						$CatNewName='';
					}

					if($result[$p]->gender == "W")
					{
						$result[$p]->gender = "L";
					}

					if($CatNewName!='')
					{
						$Product_Name = trim($Product_NameArr[0]).' '.$CatNewName;
					}
					else
					{
						$Product_Name = trim($Product_NameArr[0]);
					}

					$Product_Description = trim($result[$p]->short_description);
					$Product_Description = str_replace('"','""',$Product_Description);

					$perfumesize = "";
					if(preg_match('/pieces set/i',strtolower($Product_Description)) || preg_match('/pieces/i',strtolower($Product_Description)) || preg_match('/set/i',strtolower($Product_Description)) || preg_match('/piece set/i',strtolower($Product_Description))  || preg_match('/piece/i',strtolower($Product_Description)) || preg_match('/gift set/i',strtolower($Product_Description))  || preg_match('/gift sets/i',strtolower($Product_Description)) || preg_match('/gifts set/i',strtolower($Product_Description)) || preg_match('/gifts sets/i',strtolower($Product_Description)) || preg_match('/giftsets/i',strtolower($Product_Description))  || preg_match('/giftset/i',strtolower($Product_Description)) || preg_match('/sets/i',strtolower($Product_Description)))
					{
						$perfumesize = "Gift Set";
					}elseif(preg_match('/eau de parfum/i',strtolower($Product_Description)))
					{
						$perfumesize = "EDP";
					}elseif(preg_match('/eau de toilette/i',strtolower($Product_Description)))
					{
						$perfumesize = "EDT";
					}elseif(preg_match('/eau de cologne/i',strtolower($Product_Description)))
					{
						$perfumesize = "EDC";
					}

					$perfumesizetester = "";
					if(preg_match('/tester/i',strtolower($Product_Description)) || preg_match('/testers/i',strtolower($Product_Description)) || preg_match('/(tester)/i',strtolower($Product_Description)) || preg_match('/(testers)/i',strtolower($Product_Description)))
					{
						$perfumesizetester = "Tester";
					}

					if($perfumesize=='Gift Set')
					{
						$Product_Name = trim($Product_Name)." ".trim($perfumesize);
						if($result[$p]->gender!='')
						{
							$Product_Name = $Product_Name." (".trim($result[$p]->gender).")";
						}

					}else if($perfumesizetester=="")
					{
						$Product_Name = trim($Product_Name);
						if($result[$p]->gender !='')
						{
							$Product_Name = $Product_Name." (".trim($result[$p]->gender).")";
						}
						if($perfumesize!='')
						{
							$Product_Name = $Product_Name." ".trim($perfumesize);
						}
						if($result[$p]->size!='')
						{
							$Product_Name = $Product_Name." ".trim($result[$p]->size);
						}

					}

					if($perfumesizetester == "Tester")
					{
						$Product_Name = trim($Product_Name);
					    if($result[$p]->gender!='')
					    {
							$Product_Name = $Product_Name." (".	trim($result[$p]->gender).")";
						}
						if($perfumesize!='')
						{
							$Product_Name = $Product_Name." ".trim($perfumesize);
						}
						if($result[$p]->size!='')
						{
							$Product_Name = $Product_Name." ".trim($result[$p]->size);
						}
						$Product_Name = $Product_Name." (".trim($perfumesizetester).")";
					}

					/*$Product_Name = str_replace("perfume","",trim($Product_Name));
					$Product_Name = str_replace("Perfume","",trim($Product_Name));
					$Product_Name = str_replace("Spray","",trim($Product_Name));
					$Product_Name = str_replace("spray","",trim($Product_Name));
					*/

					$product_category = $result[$p]->category_name;

					if($product_category=="Men")
					{
						$product_category = "M";
					}elseif($product_category=="Women"){
						$product_category = "W";
					}elseif($product_category=="Unisex"){
						$product_category = "U";
					}elseif($product_category=="Kids"){
						$product_category = "K";
					}elseif($product_category=="Women Testers"){
						$product_category = "W Testers";
					}elseif($product_category=="Women Gift Sets"){
						$product_category = "W Gift Sets";
					}elseif($product_category=="Men Gift Sets"){
						$product_category = "M Gift Sets";
					}elseif($product_category=="Unisex Testers"){
						$product_category = "U Testers";
					}elseif($product_category=="Unisex Gift Sets"){
						$product_category = "U Gift Sets";
					}elseif($product_category=="Men Testers"){
						$product_category = "M Testers";
					}
					$manufacturer = $result[$p]->vmanufacture;

					if(file_exists(config('global.PRD_LARGE_IMG_PATH') . $result[$p]->image) and ! empty($result[$p]->image)) {
						$mainImageUrl = config('global.PRD_LARGE_IMG_URL').$result[$p]->image;

					} else {
						$mainImageUrl = config('global.NO_IMAGE_LARGE');
					}

					$Wholesale_Price = number_format($result[$p]->product_price,2,".","");
					$retail_price = number_format($result[$p]->retail_price,2,".","");

					$warehouse = "FD1";
					$category_idArr = array(15,39,74,203,7,8,11,27);

					if($WebsiteStock == "Out" && $fd1!='')
					{

						$diffrence = 0;
						if(isset($result[$p]->minimum_product_price)){
							$diffrence = $result[$p]->product_price - $result[$p]->minimum_product_price;
						}
						// echo $product_price."==".$result[$p]->sku."==".$result[$p]->current_stock."==".$result[$p]->product_price."==".$result[$p]->minimum_product_price."<br>";
						if($diffrence > 0.50)
						{
							if($result[$p]->cosmo_sku!='' && ($result[$p]->cosmo_current_stock > $check_stock_1 || ($result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr)))  && $fd2!='' && $result[$p]->cosmo_wholesale_price > 0 && $result[$p]->cosmo_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->cosmo_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								 $warehouse = "FD2";
								 $result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->nandansons_sku != '' && ($result[$p]->nandansons_current_stock > $check_stock_1 || ($result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd3!='' && $result[$p]->nandansons_wholesale_price > 0 && $result[$p]->nandansons_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->nandansons_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD3";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->perfumeworldwide_sku!='' && ($result[$p]->perfumeworldwide_currentstock > $check_stock_1 || ($result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd4!='' && $result[$p]->perfumeworldwide_wholesale_price > 0 && $result[$p]->perfumeworldwide_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->perfumeworldwide_currentstock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD4";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->pca_sku != '' && ($result[$p]->pca_current_stock > $check_stock_1 || ($result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd5!='' && $result[$p]->pca_wholesale_price > 0 && $result[$p]->pca_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->pca_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD5";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->nd_sku != '' && ($result[$p]->nd_current_stock > $check_stock_1 || ($result[$p]->nd_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd6!='' && $result[$p]->nd_wholesale_price > 0 && $result[$p]->nd_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->nd_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD6";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}
						}else{
							if($result[$p]->cosmo_sku !='' && ($result[$p]->cosmo_current_stock > $check_stock_1 || ($result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd2!='' && $result[$p]->cosmo_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->cosmo_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								 $warehouse = "FD2";

							}else if($result[$p]->nandansons_sku != '' && ($result[$p]->nandansons_current_stock > $check_stock_1 || ($result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd3!='' && $result[$p]->nandansons_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->nandansons_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD3";
							}
							else if($result[$p]->perfumeworldwide_sku != '' && ($result[$p]->perfumeworldwide_currentstock > $check_stock_1 || ($result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd4!='' && $result[$p]->perfumeworldwide_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->perfumeworldwide_currentstock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD4";
							}
							else if($result[$p]->pca_sku != '' && ($result[$p]->pca_current_stock > $check_stock_1 || ($result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd5!='' && $result[$p]->pca_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->pca_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD5";
							}else if($result[$p]->nd_sku != '' && ($result[$p]->nd_current_stock > $check_stock_1 || ($result[$p]->nd_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd6!='' && $result[$p]->nd_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->nd_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD6";
							}

						}
					}

					if($fd1=='')
					{
					    $diffrence = 0;
						if(isset($result[$p]->minimum_product_price)){
							$diffrence = $result[$p]->product_price - $result[$p]->minimum_product_price;
						}

						if($diffrence > 0.50)
						{
							if($result[$p]->cosmo_sku != '' && ($result[$p]->cosmo_current_stock > $check_stock_1 || ($result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd2!='' && $result[$p]->cosmo_wholesale_price > 0 && $result[$p]->cosmo_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->cosmo_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD2";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->nandansons_sku!='' &&  ($result[$p]->nandansons_current_stock > $check_stock_1 || ($result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd3!='' && $result[$p]->nandansons_wholesale_price > 0 && $result[$p]->nandansons_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->nandansons_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD3";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}
							else if($result[$p]->perfumeworldwide_sku!='' &&  ($result[$p]->perfumeworldwide_currentstock > $check_stock_1 || ($result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd4!='' && $result[$p]->perfumeworldwide_wholesale_price > 0 && $result[$p]->perfumeworldwide_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->perfumeworldwide_currentstock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD4";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}
							else if($result[$p]->pca_sku!='' &&   ($result[$p]->pca_current_stock > $check_stock_1 || ($result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd5!='' && $result[$p]->pca_wholesale_price > 0 && $result[$p]->pca_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->pca_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD5";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->ns_sku!='' &&  ($result[$p]->nd_current_stock > $check_stock_1 || ($result[$p]->nd_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd6!='' && $result[$p]->nd_wholesale_price > 0 && $result[$p]->nd_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->nd_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD6";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}

						}
						else
						{
							if($result[$p]->cosmo_sku != '' && ($result[$p]->cosmo_current_stock > $check_stock_1 || ($result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd2!='' && $result[$p]->cosmo_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->cosmo_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD2";
							}else if($result[$p]->nandansons_sku!='' &&  ($result[$p]->nandansons_current_stock > $check_stock_1 || ($result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd3!='' && $result[$p]->nandansons_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->nandansons_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD3";
							}
							else if($result[$p]->perfumeworldwide_sku!='' && ($result[$p]->perfumeworldwide_currentstock > $check_stock_1 || ($result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd4!='' && $result[$p]->perfumeworldwide_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->perfumeworldwide_currentstock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD4";
							}
							else if($result[$p]->pca_sku!='' &&  ($result[$p]->pca_current_stock > $check_stock_1 || ($result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd5!='' && $result[$p]->pca_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->pca_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD5";
							}else if($result[$p]->nd_sku!='' &&  ($result[$p]->nd_current_stock > $check_stock_1 || ($result[$p]->nd_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd6!='' && $result[$p]->nd_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->nd_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD6";
							}

						}
					}

					if($WebsiteStock == "In" && $fd1!='')
					{
						if(isset($result[$p]->wholesale_price_3)){
							$result[$p]->product_price = $result[$p]->wholesale_price_3;
						}
					}

					if($WebsiteStock == "Out" && $fd1!='')
					{
						if($result[$p]->product_price < 7)
						{
							$result[$p]->product_price = $result[$p]->product_price + 0.75;
						}
						else if($result[$p]->product_price >= 7 && $result[$p]->product_price <= 20)
						{
							$result[$p]->product_price = $result[$p]->product_price + 1.25;
						}
						else if($result[$p]->product_price > 20 && $result[$p]->product_price <= 35)
						{
							$result[$p]->product_price = $result[$p]->product_price + 1.75;
						}
						else if($result[$p]->product_price > 35 && $result[$p]->product_price <= 50)
						{
							$result[$p]->product_price = $result[$p]->product_price + 2.25;
						}
						else if($result[$p]->product_price > 50 && $result[$p]->product_price <= 65)
						{
							$result[$p]->product_price = $result[$p]->product_price + 3.25;
						}
						else if($result[$p]->product_price > 65 && $result[$p]->product_price <= 80)
						{
							$result[$p]->product_price = $result[$p]->product_price + 4;
						}
						else if($result[$p]->product_price > 80 && $result[$p]->product_price <= 95)
						{
							$result[$p]->product_price = $result[$p]->product_price + 5;
						}
						else if($result[$p]->product_price > 95 && $result[$p]->product_price <= 110)
						{
							$result[$p]->product_price = $result[$p]->product_price + 5.75;
						}
						else if($result[$p]->product_price > 110 && $result[$p]->product_price <= 125)
						{
							$result[$p]->product_price = $result[$p]->product_price + 6.25;
						}
						else if($result[$p]->product_price > 125)
						{
							$result[$p]->product_price = $result[$p]->product_price + 8;
						}
					}

					if($fd1=='')
					{
						if($result[$p]->product_price < 7)
						{
							$result[$p]->product_price = $result[$p]->product_price + 0.75;
						}
						else if($result[$p]->product_price >= 7 && $result[$p]->product_price <= 20)
						{
							$result[$p]->product_price = $result[$p]->product_price + 1.25;
						}
						else if($result[$p]->product_price > 20 && $result[$p]->product_price <= 35)
						{
							$result[$p]->product_price = $result[$p]->product_price + 1.75;
						}
						else if($result[$p]->product_price > 35 && $result[$p]->product_price <= 50)
						{
							$result[$p]->product_price = $result[$p]->product_price + 2.25;
						}
						else if($result[$p]->product_price > 50 && $result[$p]->product_price <= 65)
						{
							$result[$p]->product_price = $result[$p]->product_price + 3.25;
						}
						else if($result[$p]->product_price > 65 && $result[$p]->product_price <= 80)
						{
							$result[$p]->product_price = $result[$p]->product_price + 4;
						}
						else if($result[$p]->product_price > 80 && $result[$p]->product_price <= 95)
						{
							$result[$p]->product_price = $result[$p]->product_price + 5;
						}
						else if($result[$p]->product_price > 95 && $result[$p]->product_price <= 110)
						{
							$result[$p]->product_price = $result[$p]->product_price + 5.75;
						}
						else if($result[$p]->product_price >  110 && $result[$p]->product_price <= 125)
						{
							$result[$p]->product_price = $result[$p]->product_price + 6.25;
						}
						else if($result[$p]->product_price > 125)
						{
							$result[$p]->product_price = $result[$p]->product_price + 8;
						}
					}

					$result[$p]->product_price = number_format($result[$p]->product_price,2,".","");

					$product_price = 0;
					if($result[$p]->product_price > 0)
					{
						$product_price	= floor($result[$p]->product_price);

						$fraction = $result[$p]->product_price - $product_price;
						$fraction = number_format($fraction,2,".","");

						if($fraction >= 0.01  && $fraction <= 0.25)
						{
							$product_price = $product_price + 0.25;
						}
						else if($fraction > 0.25  && $fraction <= 0.50)
						{
							$product_price = $product_price + 0.50;
						}
						else if($fraction > 0.50  && $fraction <= 0.75)
						{
							$product_price = $product_price + 0.75;
						}
						else if($fraction > 0.75  && $fraction <= 0.99)
						{
							$product_price = $product_price + 1;
						}

					}

					$OrderQuantity ='';
					$chkRetailProductPrice = "N";
					if($WebsiteStock == "In" && $fd1!='' &&  $result[$p]->special_website_price > 0)
					{
						//$product_price = $result[$p]['special_website_price'];
						$OCost = "";
						if($result[$p]->w_our_cost > 0){
							$OCost = $result[$p]->w_our_cost * 1.05;
						}
						if($result[$p]->special_website_price >0 && $result[$p]->current_stock > 8)
						{
							if($result[$p]->special_website_price < $OCost)
							{
								//echo "<font color='Red'>$".$result[$p]["special_website_price"]."</font>";
								$product_price = number_format($result[$p]->wholesale_price,2,".","");
							}else{
								$product_price = number_format($result[$p]->special_website_price,2,".","");
							}
							if(!empty($product_price) && $product_price > 0)
							{
								$chkRetailProductPrice = "Y";
							}
						}else if($result[$p]->current_stock > 8 && $result[$p]->wholesale_price > 0){
							for($k=0; $k < $tot_markup_rice; $k++)
							{
								if($db_recs[$k]->markup_value != "")
								{
									$mvalu = explode("-",$db_recs[$k]->markup_value);
									$mvalcount = count($mvalu);

									if($mvalcount>1)
									{
										$per = $db_recs[$k]->markup_percent;
										$result[$p]->{'wholesale_price_'.$p} = number_format(($result[$p]->wholesale_price - $result[$p]->wholesale_price *$per/100),2,".","");
									}
									else
									{
										$per = $db_recs[$k]->markup_percent;
										$result[$p]->{'wholesale_price_'.$p} = number_format(($result[$p]->wholesale_price - $result[$p]->wholesale_price*$per/100),2,".","");
									}
								}
							}

							if(isset($result[$p]->wholesale_price_3) && $result[$p]->wholesale_price_3 > 0)
							{
								$wholesale_price_3	= floor($result[$p]->wholesale_price_3);

								$fraction = $result[$p]->wholesale_price_3 - $wholesale_price_3;

								$fraction = number_format($fraction,2,".","");

								if($fraction >= 0.01  && $fraction <= 0.25)
								{
									$wholesale_price_3 = $wholesale_price_3 + 0.25;
								}
								else if($fraction > 0.25  && $fraction <= 0.50)
								{
									$wholesale_price_3 = $wholesale_price_3 + 0.50;
								}
								else if($fraction > 0.50  && $fraction <= 0.75)
								{
									$wholesale_price_3 = $wholesale_price_3 + 0.75;
								}
								else if($fraction > 0.75  && $fraction <= 0.99)
								{
									$wholesale_price_3 = $wholesale_price_3 + 1;
								}
								$product_price = $wholesale_price_3;
							}
						}
					}

					$yousave = 0;
					if(isset($retail_price) && $retail_price >  0 && isset($product_price) && $product_price > 0 && $retail_price > $product_price)
					{
					$yousave = ($retail_price - $product_price) / $retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					}

					if(isset($result[$p]->product_type) && $result[$p]->product_type == 'retailer' && isset($chkRetailProductPrice) && $chkRetailProductPrice == 'Y' && $product_price <= 0){
						continue;
					}
					if($retail_price < $product_price)
					{
						$retail_price = "$".$product_price;
					}
					else if($retail_price > 0)
					{
						$retail_price = "$".$retail_price;
					}
					if($product_price > 0)
					{
						$product_price = "$".$product_price;
					}
					if($yousave > 0)
					{
						$yousave = $yousave."%";
					}

					$csv_data[$rc][] = $SKU;
					$csv_data[$rc][] = $Product_Name;
					$csv_data[$rc][] = $product_category;
					$csv_data[$rc][] = $manufacturer;
					$csv_data[$rc][] = $UPC;
					$csv_data[$rc][] = $retail_price;
					$csv_data[$rc][] = $yousave;
					$csv_data[$rc][] = $product_price;
					$csv_data[$rc][] = $result[$p]->current_stock;
					$csv_data[$rc][] = $mainImageUrl;
					$csv_data[$rc][] = $SKU;
					$csv_data[$rc][] = $OrderQuantity;
					$csv_data[$rc][] = $warehouse;

					$rc=$rc+1;
				}
				$start_limit = $start_limit+$end_limit;
				$process_batch 	= $process_batch + 1;

				// if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.9.87"){
					// echo "<pre>";print_r($csv_data);exit;
				// }
				if($process_batch == $total_batch){
					//$workbook->send($export_file_name);
					// $workbook->close();
				}
			}
		}

		// $header_row = ['Item#','Product Name','Category','Brand Name','UPC','Price','Quantity','Image','Order Quantity','Warehouse'];
		// array_unshift($csv_data,$header_row);
		// echo "<pre>";print_r($csv_data);exit;

		// if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.9.87"){
			// echo "<pre>";print_r($csv_data);exit;
		// }
		if(!empty($csv_data) && count($csv_data) > 0)
		{
			$header_row = ['Item#','Product Name','Category','Brand Name','UPC','MSRP','Discount (%)','Price','Quantity','Image','Image Link','Order Quantity','Warehouse'];
			return Excel::download(new ExportOrders($csv_data, $header_row,'SpecialProducts'), $export_file_name);
		} else {
			Session::flash('error', 'No Data Found!');
			// return redirect()->back();
			return redirect(config('global.SITE_URL').'/specialwholesaleproductpricelist');
		}

	}

	public function GetProductDetailAlert(Request $request)
	{
		$productid = $request['iproductid'];
		$sku = $request['vsku'];
		$alert = $request['alert'];
		if($alert == "yes"){
			$validatedData = $request->validate([
								'alert_email' => 'required|email'
					        ], [
					            'alert_email.required' => config('message.Validate.Email'),
					            'alert_email.email' => config('message.Validate.ValidEmail'),
					        ]);

			$alert_product = array(
							'email'    => $request['alert_email'],
							'estatus'  => 'No',
							'prod_id'  => $productid,
							'sku'      => $sku,
					);
			$alert_id = Stockalert::create($alert_product);
			$ProductHTML = "Thank you for your request. We will email you as soon as the item is in stock.";

		}else{
			$prodData = DB::table('pu_products as p')
									->select('p.products_id','p.image','p.short_description','p.product_name','p.sku','p.UPC','p.product_description')
									->where('p.sku','=',$sku)
									->get();
			if ( count($prodData) > 0 )
			{
				if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prodData[0]->image) && $prodData[0]->image != '')
				{$thumb_image = config('global.PRD_THUMB_IMG_PATH').$prodData[0]->image; }
				else
				{$thumb_image = config('global.NO_IMAGE_THUMB');}

				//~ $prod_detail['iproductid'] = $prodData[0]->products_id;
				//~ $prod_detail['vsku'] = $prodData[0]->sku;
				//~ $prod_detail['product_name'] = $prodData[0]->product_name;
				//~ $prod_detail['image'] = $thumb_image;
				//~ $prod_detail['short_description'] = $prodData[0]->short_description;
				//~ $prod_detail['product_description'] = $prodData[0]->product_description;

				$prodData[0]->image = $thumb_image;
				$this->PageData['prod_detail'] = $prodData;
			}

			//echo "<pre>";print_r($this->PageData);exit;
			$ProductHTML = view('myaccount.wproductsnotifypopup')->with($this->PageData)->render();
		}

		return response()->json(array('ProductHTML'=>$ProductHTML));
	}

	public function ChangeCurrency(Request $request){
		$currency_id = $request['currency'];
		$currency_arr = config('Currencies');

		$success = "0";
		//echo "<pre>";print_r($currency_arr);exit;
		if($currency_id != "" && $currency_id > 0){
			$currency_data = $currency_arr->firstWhere('currency_id',$currency_id);

			if(count($currency_data) > 0 and $currency_data['exchange_rate'] > 0){
				Session::put('currency_code',$currency_data['currency_code']);
				Session::put('currency_symbol',$currency_data['currency_symbol']);
				Session::put('currency_rate',$currency_data['exchange_rate']);

				$success = "1";
			}
		}

		//echo "<pre>";print_r($get_currency);exit;
		return response()->json(array('success'=>$success));
	}

	public function DownloadPPL(Request $request){
		if(!Auth::user())
			return redirect('/login.html');

		$complete_newarrival_export_file_name_pdf='';

		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Complete_NewArrival_Wholesale_Price_List.pdf"))
		{
			$complete_newarrival_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Complete_NewArrival_Wholesale_Price_List.pdf?ver=".time();
		}
		$complete_tester_export_file_name_pdf = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Tester_Wholesale_Price_List.pdf"))
		{
			$complete_tester_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Tester_Wholesale_Price_List.pdf?ver=".time();
		}
		$complete_giftset_export_file_name_pdf = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Giftset_Wholesale_Price_List.pdf"))
		{
			$complete_giftset_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Giftset_Wholesale_Price_List.pdf?ver=".time();
		}
		$complete_wholesale_export_file_name_pdf = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Complete_Wholesale_Price_List.pdf"))
		{
			$complete_wholesale_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Complete_Wholesale_Price_List.pdf?ver=".time();
		}
		$sunglasses_wholesale_export_file_name_pdf = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Sunglasses_Wholesale_Price_List.pdf"))
		{
			$sunglasses_wholesale_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Sunglasses_Wholesale_Price_List.pdf?ver=".time();
		}

		$complete_tester_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Tester_Wholesale_Price_List.xls"))
		{
			$complete_tester_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Tester_Wholesale_Price_List.xls?ver=".time();
		}
		$complete_giftset_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Giftset_Wholesale_Price_List.xls"))
		{
			$complete_giftset_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Giftset_Wholesale_Price_List.xls?ver=".time();
		}
		$complete_wholesale_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Complete_Wholesale_Price_List.xls"))
		{
			$complete_wholesale_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Complete_Wholesale_Price_List.xls?ver=".time();
		}
		$complete_newarrival_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Complete_NewArrival_Wholesale_Price_List.xls"))
		{
			$complete_newarrival_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Complete_NewArrival_Wholesale_Price_List.xls?ver=".time();
		}
		$sunglasses_wholesale_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Sunglasses_Wholesale_Price_List.xls"))
		{
			$sunglasses_wholesale_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Sunglasses_Wholesale_Price_List.xls?ver=".time();
		}

		//echo "<pre>";print_r($get_currency);exit;

		$this->PageData['complete_newarrival_export_file_name_pdf'] = $complete_newarrival_export_file_name_pdf;
		$this->PageData['complete_tester_export_file_name_pdf'] = $complete_tester_export_file_name_pdf;
		$this->PageData['complete_giftset_export_file_name_pdf'] = $complete_giftset_export_file_name_pdf;
		$this->PageData['complete_wholesale_export_file_name_pdf'] = $complete_wholesale_export_file_name_pdf;
		$this->PageData['sunglasses_wholesale_export_file_name_pdf'] = $sunglasses_wholesale_export_file_name_pdf;

		$this->PageData['complete_tester_export_file_name_xls'] = $complete_tester_export_file_name_xls;
		$this->PageData['complete_giftset_export_file_name_xls'] = $complete_giftset_export_file_name_xls;
		$this->PageData['complete_wholesale_export_file_name_xls'] = $complete_wholesale_export_file_name_xls;
		$this->PageData['complete_newarrival_export_file_name_xls'] = $complete_newarrival_export_file_name_xls;
		$this->PageData['sunglasses_wholesale_export_file_name_xls'] = $sunglasses_wholesale_export_file_name_xls;

		return view('myaccount.downloadppl')->with($this->PageData);
	}

	function PhoneorderPayReceipt_bkNEW_funValN(Request $request){
		$OrderID = base64_decode($request['id']);
		$this->PageData['meta_title']  = "Order Receipt Print :: MaxAroma";
		$this->PageData['CSSFILES'] = ['checkout.css','myaccount.css','phoneorder_payment_receipt.css'];	//'shoppingcart.css',
		$this->PageData['JSFILES'] = ['phoneorder_payment_receipt.js'];

		$afterpay_details = $this->getAfterPayDetails();
		$this->PageData['token_js_url'] = $afterpay_details['token_js_url'];
		// echo "<pre>";print_r($this);exit;

		if($OrderID == "" || $OrderID <=0)
		{
			return redirect('/');
		}

		// $OrderRs = Order::select('orders_id', 'orders_no','customer_id', 'sub_total', 'tax', 'shipping_amt', 'order_total', 'refund_amount', 'wire_discount', 'auto_discount', 'quantity_discount', 'reward_discount', 'coupon_amount', 'gc_amount', 'bogo_discount', 'pay_status', 'status', 'payment_type', 'use_credit_limit', 'payment_method','payment_gateway_response','order_datetime','phoneorder_paymentdate','phoneorder_shipping_method_id','route_shipping_insurance_charge','is_shipping_signature','shipping_signature','order_upd_datetime')
							// ->where('orders_id', '=', $OrderID)
							// ->get();
		$OrderRs = DB::table('pu_orders')->select('orders_id', 'orders_no','customer_id', 'sub_total', 'tax', 'shipping_amt', 'order_total', 'refund_amount', 'wire_discount', 'auto_discount', 'quantity_discount', 'reward_discount', 'coupon_amount', 'gc_amount', 'bogo_discount', 'pay_status', 'status', 'payment_type', 'use_credit_limit', 'payment_method','payment_gateway_response','order_datetime','phoneorder_paymentdate','phoneorder_shipping_method_id','route_shipping_insurance_charge','is_shipping_signature','shipping_signature','order_upd_datetime','gift_charge','apply_credit','refer_amount','bill_email')
							->where('orders_id', '=', $OrderID)
							->get();

		if($OrderRs->count() <= 0)
		{
			return redirect('/');
		}

		if($OrderRs[0]->order_total == "" || $OrderRs[0]->order_total < 0){
			$OrderRs[0]->order_total = 0;
		}
		$customer_id = $OrderRs[0]->customer_id;


		$OrderDetailRs = DB::table('pu_order_detail as od')
							->select('od.is_gift_wrap', 'od.dtl_ship_status','od.price', 'od.quantity', 'od.total', 'p.image', 'p.product_name', 'p.sku', 'p.short_description', 'b.brand_name')
							->join('pu_products as p','od.products_id','=','p.products_id')
							->join('pu_brand as b','p.brand_id','=','b.brand_id')
							->where('od.orders_id', '=', $OrderID)
							->get();

		$OrderDetailCnt = $OrderDetailRs->count();

		for($d=0; $d<$OrderDetailCnt; $d++){
			$orderDetails[$d]['product_img'] = $OrderDetailRs[$d]->image;
			$orderDetails[$d]['product_title'] = remove_html_entities($OrderDetailRs[$d]->product_name);
			$orderDetails[$d]['gift_wrap'] = $OrderDetailRs[$d]->is_gift_wrap;
			$orderDetails[$d]['ship_status'] = $OrderDetailRs[$d]->dtl_ship_status;
			$orderDetails[$d]['unit_price'] = $OrderDetailRs[$d]->price;
			$orderDetails[$d]['quantity'] = $OrderDetailRs[$d]->quantity;
			$orderDetails[$d]['toal_price'] = $OrderDetailRs[$d]->total;
			$orderDetails[$d]['product_sku'] = $OrderDetailRs[$d]->sku;
			$orderDetails[$d]['short_description'] = remove_html_entities(strip_tags($OrderDetailRs[$d]->short_description));
			$orderDetails[$d]['brand_name'] = $OrderDetailRs[$d]->brand_name;
		}

		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->whereIn('pm_group_name', ['PAYMENT_STRIPE','PAYMENT_PAYWITHAFTERPAY','PAYMENT_PAYPALEC','PAYMENT_PAYWITHAMAZON'])
							->where('pm_status', '=', 'Active')
							->get();

		$IsStripeCheckout = "No";
		$arrAuthnetVar = array();
		$IsPaypalExpressCheckout = "No";
		$PaypalActionMode = "";
		$IsPayWithAmazonCheckout ='No';
		$Afterpay_Checkout ='No';

		$tot_records = $db_res->count();
		if( $tot_records > 0)
		{
			for($i=0; $i < $tot_records ; $i++){
				if($db_res[$i]->pm_group_name == "PAYMENT_STRIPE"){
					$IsStripeCheckout = "Yes";
					$arrAuthnetVar = unserialize($db_res[$i]->pm_details);

					$STRIPE_KEY  = $arrAuthnetVar['Secret_Key'];
					$PUBLISH_KEY = $arrAuthnetVar['Publishable_Key'];

					#############################

					$STRIPE_KEY   = $this->decrypt($STRIPE_KEY);
					$PUBLISH_KEY   = $this->decrypt($PUBLISH_KEY);
					#############################
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYWITHAFTERPAY"){
					$Afterpay_Checkout ='Yes';
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYPALEC"){
					$IsPaypalExpressCheckout ='Yes';
					$PaypalActionMode = "sandbox";
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYWITHAMAZON"){
					$IsPayWithAmazonCheckout ='Yes';
				}
			}
		}

		$cust_detail = Customer::select('eusertype')
								->where('customer_id', '=', $customer_id)
								->get();

		if($cust_detail->count() > 0 && $cust_detail[0]->eusertype == "Wholesaler"){
			$Afterpay_Checkout ='No';
		}
		// echo $Afterpay_Checkout;exit;

		if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.6.77"){
			 config(['app.debug' => true]);
		}

		if($Afterpay_Checkout == "Yes"){
			$payload = array();

			$getconfigs = $this->GetAfterPayResult($payload,"configuration","No");

			$Min_AP = $Max_AP = 0;
			if(isset($getconfigs['minimumAmount']['amount'])){
				$Min_AP = $getconfigs['minimumAmount']['amount'];
			}
			if(isset($getconfigs['maximumAmount']['amount'])){
				$Max_AP = $getconfigs['maximumAmount']['amount'];
			}

			$Min_AP_AMT = round($Min_AP * 100);
			$Max_AP_AMT = round($Max_AP * 100);

			$this->PageData["Min_AP_AMT"] = $Min_AP_AMT;
			$this->PageData["Max_AP_AMT"] = $Max_AP_AMT;

			if($OrderRs[0]->order_total < $Min_AP || $OrderRs[0]->order_total > $Max_AP){
				$Afterpay_Checkout = "No";
			}

			$ShippingModeRS = ShippingMode::where('status','=','1')->where('shipping_mode_id','=',$OrderRs[0]->phoneorder_shipping_method_id)->get();
			// echo "<pre>";print_r($ShippingModeRS);exit;
			$currency = "USD";
			$shipping_arr_app[0]["id"] = (string)$OrderRs[0]->phoneorder_shipping_method_id;
			$shipping_arr_app[0]["name"] = strip_tags($ShippingModeRS[0]->type);

			$description = ($ShippingModeRS[0]->detail != "") ? $ShippingModeRS[0]->detail : $ShippingModeRS[0]->type;
			$shipping_arr_app[0]["description"] = strip_tags($description);

			$shipping_arr_app[0]["shippingAmount"]["amount"] = $OrderRs[0]->shipping_amt;
			$shipping_arr_app[0]["shippingAmount"]["currency"] = $currency;
			$shipping_arr_app[0]["taxAmount"]["amount"] = $OrderRs[0]->tax;
			$shipping_arr_app[0]["taxAmount"]["currency"] = $currency;
			$shipping_arr_app[0]["orderAmount"]["amount"] = $OrderRs[0]->order_total;
			$shipping_arr_app[0]["orderAmount"]["currency"] = $currency;

			$this->PageData["Shipping_Arr_AP"] = json_encode($shipping_arr_app);
			// echo "<pre>";print_r($this->PageData["Shipping_Arr_AP"]);exit;
		}

		// echo $Afterpay_Checkout."<br>".$PUBLISH_KEY;exit;
		$Payment_Method_Message = "";
		if(($OrderRs[0]->payment_type == 'PAYMENT_AUTHORIZENETCC' || $OrderRs[0]->payment_type =='PAYMENT_PAYPALCC') and $OrderRs[0]->payment_gateway_response !='')
		{
			$arr_gateway_response = explode(",",$OrderRs[0]->payment_gateway_response);

			if ($arr_gateway_response[0] == 4)
			{
				$Payment_Method_Message = "<h3>Thank you! Your order will be processed pending a standard transaction review.</h3>
			<p>We hope you enjoyed shopping with us. Your order will be processed as soon as possible. We will contact you with updates. <br />Please allow 24hrs to process the payment. An E-mail Confirmation will be sent upon payment received.</p>";
			}
		}

		$OrderRs[0]->datetime_order = date("m/d/Y H:i:s",strtotime($OrderRs[0]->order_datetime));

		$OrderRs[0]->paymentdate_phoneorder = "00/00/0000 00:00:00";
		if($OrderRs[0]->phoneorder_paymentdate != "0000-00-00 00:00:00"){
			$OrderRs[0]->paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]->phoneorder_paymentdate));
		}else{
			if($OrderRs[0]->order_upd_datetime != "0000-00-00 00:00:00"){
				$OrderRs[0]->paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]->order_upd_datetime));
			}else{
				$OrderRs[0]->paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]->order_datetime));
			}
		}
		$this->PageData['Payment_Method_Message'] = $Payment_Method_Message;

		$viewInvoiceUrl = config('global.SITE_URL').'invoice/'.base64_encode($OrderRs[0]->orders_no);
		$this->PageData['viewInvoiceUrl'] = $viewInvoiceUrl;

		$payInvoiceUrl = config('global.SITE_URL').'stripe/phoneorder';
		$this->PageData['payInvoiceUrl'] = $payInvoiceUrl;
		$this->PageData['OrderID'] = base64_encode($OrderID);
		$this->PageData['OrderRs'] = $OrderRs[0];
		$this->PageData['OrderDetails'] = $orderDetails;

		$this->PageData["PaypalActionMode"] = $PaypalActionMode;
		$this->PageData["Afterpay_Checkout"] = $Afterpay_Checkout;
		$this->PageData["IsPayWithAmazonCheckout"] = $IsPayWithAmazonCheckout;
		$this->PageData["IsPaypalExpressCheckout"] = $IsPaypalExpressCheckout;
		$this->PageData["IsStripeCheckout"] = $IsStripeCheckout;

		Session::forget('phoneorder_detail');
		Session::put('phoneorder_detail.order_id',$OrderID);
		Session::put('phoneorder_detail.order_amt',$OrderRs[0]->order_total);
		Session::put('phoneorder_detail.customer_id',$OrderRs[0]->customer_id);

		if($Afterpay_Checkout == "Yes" && isset($Min_AP_AMT) && isset($Max_AP_AMT)){
			Session::put('phoneorder_detail.Afterpay.Min_AP_AMT',$Min_AP_AMT);
			Session::put('phoneorder_detail.Afterpay.Max_AP_AMT',$Max_AP_AMT);
		}

		$Checkout_Available = "Yes";
		if($IsStripeCheckout == "No" && $IsPaypalExpressCheckout == "No" && $IsPayWithAmazonCheckout == "No" && $Afterpay_Checkout == "No"){
			$Checkout_Available = "No";
		}
		$this->PageData["Checkout_Available"] = $Checkout_Available;

		$this->SetAmazonConfig('phoneorder_payment_receipt');

		return view('myaccount.phoneorderpayreceipt')->with($this->PageData);
	}

	public function PhoneorderPayReceiptResponse(Request $request){
		$Id_order = $request['id'];
		$OrderID = base64_decode($Id_order);
		$success = base64_decode($request['success']);

		if($success == 1){
			$response_arr = $this->PhoneorderPaymentSuccess('Stripe');
			if($response_arr['success'] == "1"){
				Session::flash('success',$response_arr['err_msg']);
			}else{
				Session::flash('error',$response_arr['err_msg']);
			}
		}else{
			$err_msg = "Something went wrong, payment failed.";
			Session::flash('error',$err_msg);
		}

		return redirect(config('global.SITE_URL')."payment/".$Id_order);

	}

	public function ProductPage(Request $request)
	{
		$this->PageData['CSSFILES'] = ['listing.css','jquery-ui-slider.css','listpage.css','custom.css'];
		$this->PageData['JSFILES'] = ['jquery-1.11.3.js','jquery.mCustomScrollbar.concat.min.js','jquery-ui-slider.min.js','listing_page.js'];
		$this->PageData['ListFrom'] = 'Category';
		$ProdCat = $request->category_id;
		$this->PageData['SelCat'] = $request->category_id;
		$this->PageData['SelectedCat'] = [$request->category_id];

		$SetFilters = $this->SetFilters($request);
		if(isset($SetFilters['categories']) && count($SetFilters['categories']) > 0){
			$ProdCat = $SetFilters['categories'];
			$this->PageData['SelectedCat'] = $ProdCat;
			$this->PageData['SelCat'] = implode(',',$ProdCat);
		}

		$ProductsDetails = $this->GetProductsNew('ProductListPage',$ProdCat,12,[]);
		$Products = $ProductsDetails['Products'];
		$TotalProducts = $ProductsDetails['TotalProducts'];
		$this->PageData['Products'] = $Products;
		$this->PageData['TotalProducts'] = $TotalProducts;
		$this->PageData['Filters'] = $ProductsDetails['LeftFilters'];
		if(isset($request->category_id) && $request->category_id != '')
		{
			$CatDetails = Category::find($request->category_id);
			$this->PageData['PageTitle'] = ucwords(remove_special_chars($CatDetails->category_name));
			$ParentID = $this->SetParentID($CatDetails);
			$this->PageData['Category'] = $CatDetails;
		} else {
			$ParentID = 0;
		}
		$Bredcrum = $this->Bredcrum($request);
		$this->PageData['Bredcrum'] = $Bredcrum['BredLink'];
		$this->PageData['PageTitle'] = $Bredcrum['PageTitle'];

		$this->PageData['MinPrice'] = 0;
		$this->PageData['MaxPrice'] = 0;
		$this->PageData['PageName'] = $request->category_name;

		return view('product.test')->with($this->PageData);
	}

	public function AmazonPhoneOrderCheckout(Request $request)
	{
		$OrderID = Session::get('phoneorder_detail.order_id');

		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];
		$this->PageData['JSFILES'] = ['phoneorder_payment_receipt.js'];

		$this->PageData['meta_title'] = "Amazon Payment :: ".config('Settings.SITE_TITLE');

		$Id_order = base64_encode($OrderID);
		$this->PageData['back_url'] = config('global.SITE_URL')."payment/".$Id_order;
		$this->SetAmazonConfig('phoneorder_payment_receipt');

		$updAray = array (
							'status'			=> 'Pending - Phoneorder',
							'payment_type' 		=> 'PAYMENT_PAYWITHAMAZON',
							'payment_method' 	=> 'Pay With Amazon'
						 );
		$uporderres = Order::Where("orders_id","=",$OrderID)->update($updAray);
		return view('checkout.amazon-phoneordercheckout')->with($this->PageData);
	}

	public function PhoneorderDownloadInvoice(Request $request){
		$invoice_no = $request['invoice_no'];

		$order_id = str_ireplace("OR","",base64_decode($invoice_no));
		$Id_order = base64_encode($order_id);

		// $OrderRs = Order::where('orders_id','=',$orders_id)->get();

		$data_html = $this->UpdatePhoneorderInvoice($order_id);
		// $ProductHTML = view('myaccount.wholesaleproduct')->with($this->PageData)->render();

	/* 	if($_SERVER['HTTP_X_FORWARDED_FOR'] == "27.109.8.106"){
			// config(['app.debug' => true]);
			$data["data_html"] = $data_html;
			// echo $data_html;exit;
			return PDF::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])->loadView('myaccount.phoneorderpdf', $data)->stream("Maxaroma-".base64_decode(	$invoice_no)."-Invoice.pdf", array("Attachment" => false));
			exit;
		} */

		if($data_html != ""){
			$data["data_html"] = $data_html;

			#########to download pdf#########
			return PDF::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])->loadView('myaccount.phoneorderpdf', $data)->download("Maxaroma-".base64_decode($invoice_no)."-Invoice.pdf");
			#################################

			#########to view pdf#########
			// return PDF::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])->loadView('myaccount.phoneorderpdf', $data)->stream("Maxaroma-".base64_decode(	$invoice_no)."-Invoice.pdf", array("Attachment" => false));
			#############################
		}else{
			Session::flash('error',"Sorry, PDF file can not found, please contact admin.");
			return redirect(config('global.SITE_URL')."payment/".$Id_order);
		}

		exit;

		/*
		$filename_old = "Invoice-".base64_decode($invoice_no).".pdf";
		$filename_new = "Maxaroma-".base64_decode($invoice_no)."-Invoice.pdf";
		$pdf_path_old = config('global.PHYSICAL_PATH')."phoneorder_invoice/".$filename_old;
		$pdf_path_new = config('global.PHYSICAL_PATH')."phoneorder_invoice/".$filename_new;

		if(file_exists($pdf_path_new)){
			$pdf_file_name = $filename_new;
		}else if(file_exists($pdf_path_old)){
			$pdf_file_name = $filename_old;
		}else{
			Session::flash('error',"Sorry, PDF file can not found, please contact admin.");
			return redirect(config('global.SITE_URL')."payment/".$Id_order);
		}

		$pdfFile = config('global.PHYSICAL_PATH').'phoneorder_invoice/'.$pdf_file_name;

		$downloadName = 'Invoice.pdf';
		header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1
		header('Pragma: no-cache'); // HTTP 1.0
		header('Expires: 0'); // Proxies

		header('Content-Type:application/pdf');
		header('Content-Length:'.filesize($pdfFile));
		header('Content-Transfer-Encoding:Binary');
		header('Content-Disposition: attachment;filename='.$downloadName);
		readfile($pdfFile);
		exit;
		 */
	}
	public function PhoneorderPayReceipt(Request $request){
		// echo "<br><br>Current Time===== ".date("Y-m-d H:i:s");

		$OrderID = base64_decode($request['id']);
		$this->PageData['meta_title']  = "Order Receipt Print :: MaxAroma";
		$this->PageData['CSSFILES'] = ['checkout.css','myaccount.css','phoneorder_payment_receipt.css'];	//'shoppingcart.css',
		$this->PageData['JSFILES'] = ['phoneorder_payment_receipt.js'];

		$afterpay_details = $this->getAfterPayDetails();
		$this->PageData['token_js_url'] = $afterpay_details['token_js_url'];
		// echo "<pre>";print_r($this);exit;

		if($OrderID == "" || $OrderID <=0)
		{
			return redirect('/');
		}

		// $OrderRs = Order::select('orders_id', 'orders_no','customer_id', 'sub_total', 'tax', 'shipping_amt', 'order_total', 'refund_amount', 'wire_discount', 'auto_discount', 'quantity_discount', 'reward_discount', 'coupon_amount', 'gc_amount', 'bogo_discount', 'pay_status', 'status', 'payment_type', 'use_credit_limit', 'payment_method','payment_gateway_response','order_datetime','phoneorder_paymentdate','phoneorder_shipping_method_id','route_shipping_insurance_charge','is_shipping_signature','shipping_signature','order_upd_datetime')
							// ->where('orders_id', '=', $OrderID)
							// ->get();

		$OrderRs = DB::table('pu_orders')->select('orders_id', 'orders_no','customer_id', 'sub_total', 'tax', 'shipping_amt', 'order_total', 'refund_amount', 'wire_discount', 'auto_discount', 'quantity_discount', 'reward_discount', 'coupon_amount', 'gc_amount', 'bogo_discount', 'pay_status', 'status', 'payment_type', 'use_credit_limit', 'payment_method','payment_gateway_response','order_datetime','phoneorder_paymentdate','phoneorder_shipping_method_id','route_shipping_insurance_charge','is_shipping_signature','shipping_signature','order_upd_datetime','gift_charge','apply_credit','refer_amount','coupon_code','shipping_amt','gc_code','coupon_code','coupon_id','tax','phoneorder_shipping_method_id','ship_zip','ship_state','ship_country','ship_city','route_shipping_insurance_charge','is_shipping_signature','shipping_signature')
							->where('orders_id', '=', $OrderID)
							->get();

		if($OrderRs->count() <= 0)
		{
			return redirect('/');
		}

		if($OrderRs[0]->order_total == "" || $OrderRs[0]->order_total < 0){
			$OrderRs[0]->order_total = 0;
		}
		$customer_id = $OrderRs[0]->customer_id;

		$orderDetails = array();
		$OrderDetailRs = DB::table('pu_order_detail as od')
							->select('od.orders_detail_id','od.is_gift_wrap', 'od.dtl_ship_status','od.price', 'od.quantity', 'od.total', 'p.image', 'p.product_name', 'p.sku', 'p.short_description', 'b.brand_name','od.VendorSKU','od.IsCosmo','od.IsNandansons','od.IsPerfumePW','od.IsPCA','od.IsND','p.minimum_stock','p.current_stock','p.cosmo_current_stock','p.cosmo_sku','p.nandansons_current_stock','p.nandansons_current_stock','p.nandansons_sku','p.pca_current_stock','p.pca_current_stock','p.pca_sku','p.nd_sku','p.nd_current_stock','p.perfumeworldwide_sku','p.perfumeworldwide_currentstock','od.discount_price','od.excluded_flag')
							->join('pu_products as p','od.products_id','=','p.products_id')
							->join('pu_brand as b','p.brand_id','=','b.brand_id')
							->where('od.orders_id', '=', $OrderID)
							->get();

		$OrderDetailCnt = $OrderDetailRs->count();
		$isStockAvaliable = "Yes";
		$IsStockCondition = "Yes";

		$SubTotal  = 0;
		$qty =0;

		$TotalItems = 0;
		$TotalVal = 0;
		$TotalDealPrice = 0;
		$AutoDiscount = 0;
		$QtyDiscount =0;
		$BogoDiscount = 0;
		$RewardDiscount = 0;
		$GiftCertificateDiscount = 0;
		$CreditLimitDiscount = 0;
		$CouponDiscount = 0;
		$WebsiteAvailStock = "No";
		$payPalorderDetails = array();
		for($d=0; $d<$OrderDetailCnt; $d++)
		{

			if(isset($OrderDetailRs[$d]->image) && $OrderDetailRs[$d]->image!='' && file_exists(config('global.PRD_THUMB_IMG_PATH').$OrderDetailRs[$d]->image))
			{
				$newimageVal = config('global.PRD_LARGE_IMG_PATH').$OrderDetailRs[$d]->image;
				$verP = filemtime($newimageVal);
				$orderDetails[$d]['product_img'] = config('global.SPEED_SIZE_URL').config('global.PRD_LARGE_IMG_URL').$OrderDetailRs[$d]->image. "?ver=" . $verP;
			}
			else
			{
				$orderDetails[$d]['product_img'] = config('global.NO_IMAGE_LARGE');
			}

			$orderDetails[$d]['product_title'] = remove_html_entities($OrderDetailRs[$d]->product_name);
			$orderDetails[$d]['gift_wrap'] = $OrderDetailRs[$d]->is_gift_wrap;
			$orderDetails[$d]['ship_status'] = $OrderDetailRs[$d]->dtl_ship_status;
			$orderDetails[$d]['unit_price'] = $OrderDetailRs[$d]->price;
			$orderDetails[$d]['quantity'] = $OrderDetailRs[$d]->quantity;
			$orderDetails[$d]['toal_price'] = $OrderDetailRs[$d]->total;
			$orderDetails[$d]['product_sku'] = $OrderDetailRs[$d]->sku;
			$orderDetails[$d]['short_description'] = remove_html_entities(strip_tags($OrderDetailRs[$d]->short_description));
			$orderDetails[$d]['brand_name'] = $OrderDetailRs[$d]->brand_name;
			$orderDetails[$d]['final_sale'] = $OrderDetailRs[$d]->excluded_flag;
			$orderDetails[$d]['stock'] = 'In';
			$isStockAvaliable = "Yes";

			$payPalorderDetails[$d]['name'] = remove_html_entities($OrderDetailRs[$d]->product_name);
			$payPalorderDetails[$d]['unit_amount']['currency_code'] = 'USD';
			$payPalorderDetails[$d]['unit_amount']['value'] = $OrderDetailRs[$d]->price;
			$payPalorderDetails[$d]['quantity'] = (string)$OrderDetailRs[$d]->quantity;
			$payPalorderDetails[$d]['description'] = remove_html_entities(strip_tags($OrderDetailRs[$d]->short_description));
			$payPalorderDetails[$d]['sku'] = $OrderDetailRs[$d]->sku;

			if(isset($OrderDetailRs[$d]->VendorSKU) && $OrderDetailRs[$d]->VendorSKU!='')
			{
				if(isset($OrderDetailRs[$d]->IsCosmo) && $OrderDetailRs[$d]->IsCosmo=='Yes')
				{
					if($OrderDetailRs[$d]->cosmo_current_stock <=0 || $OrderDetailRs[$d]->quantity > $OrderDetailRs[$d]->cosmo_current_stock)
					{
						$isStockAvaliable = "No";
						$orderDetails[$d]['stock'] = "Out";
						$IsStockCondition = "No";
					}

				}
				elseif(isset($OrderDetailRs[$d]->IsNandansons) && $OrderDetailRs[$d]->IsNandansons=='Yes')
				{
					if($OrderDetailRs[$d]->nandansons_current_stock <=0 || $OrderDetailRs[$d]->quantity > $OrderDetailRs[$d]->nandansons_current_stock)
					{
						$isStockAvaliable = "No";
						$orderDetails[$d]['stock'] = "Out";
						$IsStockCondition = "No";
					}

				}
				elseif(isset($OrderDetailRs[$d]->IsPCA) && $OrderDetailRs[$d]->IsPCA=='Yes')
				{
					if($OrderDetailRs[$d]->pca_current_stock <=0 || $OrderDetailRs[$d]->quantity > $OrderDetailRs[$d]->pca_current_stock)
					{
						$isStockAvaliable = "No";
						$orderDetails[$d]['stock'] = "Out";
						$IsStockCondition = "No";
					}

				}
				elseif(isset($OrderDetailRs[$d]->IsPerfumePW) && $OrderDetailRs[$d]->IsPerfumePW=='Yes')
				{
					if($OrderDetailRs[$d]->perfumeworldwide_currentstock <=0 || $OrderDetailRs[$d]->quantity > $OrderDetailRs[$d]->perfumeworldwide_currentstock)
					{
						$isStockAvaliable = "No";
						$orderDetails[$d]['stock'] = "Out";
						$IsStockCondition = "No";
					}

				}
				elseif(isset($OrderDetailRs[$d]->IsND) && $OrderDetailRs[$d]->IsND=='Yes')
				{
					if($OrderDetailRs[$d]->perfumeworldwide_currentstock <=0 || $OrderDetailRs[$d]->quantity > $OrderDetailRs[$d]->perfumeworldwide_currentstock)
					{
						$isStockAvaliable = "No";
						$orderDetails[$d]['stock'] = "Out";
						$IsStockCondition = "No";
					}

				}
				else
				{
					$WebsiteAvailStock = "Yes";
				}

			}
			else
			{
				if($OrderDetailRs[$d]->minimum_stock >= $OrderDetailRs[$d]->current_stock || $OrderDetailRs[$d]->quantity > $OrderDetailRs[$d]->current_stock)
				{
					$isStockAvaliable = "No";
					$orderDetails[$d]['stock'] = "Out";
					$IsStockCondition = "No";
				}
				else
				{
					$WebsiteAvailStock = "Yes";
				}
			}

			if($orderDetails[$d]['stock']=="Out" && isset($request->method) && $request->method=="view")
			{

				OrderDetail::where('orders_detail_id', '=', $OrderDetailRs[$d]->orders_detail_id)->delete();
				$IsStockCondition = "Yes";
			}
			if($orderDetails[$d]['stock']=="In" && isset($request->method) && $request->method=="view")
			{
				$SubTotal		=	$SubTotal + $OrderDetailRs[$d]->total;
				$qty 			= 	$qty +  $OrderDetailRs[$d]->quantity;
				if(isset($OrderDetailRs[$d]->is_phone_deal) && $OrderDetailRs[$d]->is_phone_deal=="Yes")
				{
					$TotalDealPrice	= $TotalDealPrice + $OrderDetailRs[$d]->total;
				}

			}

			if($isStockAvaliable!="No" && isset($OrderDetailRs[$d]->discount_price) && $OrderDetailRs[$d]->discount_price!='')
			{

				$DiscountPriceArr = explode(",",$OrderDetailRs[$d]->discount_price);

				for($k=0;$k<count($DiscountPriceArr);$k++)
				{
					if(preg_match('/Auto/i',$DiscountPriceArr[$k]))
					{
						$AutoDiscount = $AutoDiscount + str_replace("Auto","",$DiscountPriceArr[$k]);
					}
					else if(preg_match('/Qty/i',$DiscountPriceArr[$k]))
					{
						$QtyDiscount = $QtyDiscount + str_replace("Qty","",$DiscountPriceArr[$k]);

					}
					else if(preg_match('/Bogo/i',$DiscountPriceArr[$k]))
					{
						$BogoDiscount = $BogoDiscount + str_replace("Bogo","",$DiscountPriceArr[$k]);
					}
					else if(preg_match('/Reward/i',$DiscountPriceArr[$k]))
					{
						$RewardDiscount = $BogoDiscount + str_replace("Reward","",$DiscountPriceArr[$k]);
					}
					else if(preg_match('/Coupon/i',$DiscountPriceArr[$k]))
					{
						$CouponDiscount = $CouponDiscount + str_replace("Coupon","",$DiscountPriceArr[$k]);
					}
					else if(preg_match('/Giftcertificate/i',$DiscountPriceArr[$k]))
					{
						$GiftCertificateDiscount = $GiftCertificateDiscount + str_replace("Giftcertificate","",$DiscountPriceArr[$k]);
					}
					else if(preg_match('/Creditlimit/i',$DiscountPriceArr[$k]))
					{
						$CreditLimitDiscount = $CreditLimitDiscoun + str_replace("Creditlimit","",$DiscountPriceArr[$k]);
					}
				}
			}

		}

		if(isset($request->method) && $request->method=="view")
		{
			$SubTotal = NumberFormat($SubTotal);
			$customer_RES = Customer::select('eusertype','is_dropshipper','registration_type')
								->where('customer_id', '=', $customer_id)
							->get();

			$totalDiscount = 0;
			$totalShipping = 0;
			$OrderDetailRs = DB::table('pu_order_detail as od')
								->select('od.orders_detail_id','od.is_gift_wrap', 'od.dtl_ship_status','od.price', 'od.quantity', 'od.total', 'p.image', 'p.product_name', 'p.sku', 'p.short_description', 'b.brand_name','od.VendorSKU','od.IsCosmo','od.IsNandansons','od.IsPerfumePW','od.IsPCA','od.IsND','od.excluded_flag','p.minimum_stock','p.current_stock','p.cosmo_current_stock','p.cosmo_sku','p.nandansons_current_stock','p.nandansons_current_stock','p.nandansons_sku','p.pca_current_stock','p.pca_current_stock','p.pca_sku','p.perfumeworldwide_sku','p.perfumeworldwide_currentstock','p.nd_sku','p.nd_current_stock','od.products_id','pc.category_id')
								->join('pu_products as p','od.products_id','=','p.products_id')
								->join('pu_brand as b','p.brand_id','=','b.brand_id')
								->join('pu_products_category as pc','p.products_id','=','pc.products_id')
								->where('od.orders_id', '=', $OrderID)
								->get();

			$OrderDetailCnt = $OrderDetailRs->count();
			//$AutoDiscountPhoneOrder = $this->PhoneOrderApplyAutoDiscount($SubTotal,$customer_RES[0]["eusertype"],$OrderDetailCnt,$OrderDetailRs);

			/*$GiftDiscountPhoneOrder = 0;
			$gccode = $OrderRs[0]->gc_code;
			if(isset($OrderRs[0]->gc_code) && $OrderRs[0]->gc_code!='')
			{
				$GiftDiscountPhoneOrder = $this->PhoneOrderApplyGiftCertificate($gccode,$SubTotal);
				if($GiftDiscountPhoneOrder <= 0)
				{
					$gccode = '';
				}
			}*/
			$couponCode = "";
			$coupon_id  = "";
			if(isset($CouponDiscount) && $CouponDiscount > 0)
			{
				$couponCode 	= 	$OrderRs[0]->coupon_code;
				$coupon_id 		= 	$OrderRs[0]->coupon_id;
			}

			if(!empty($OrderRs[0]->coupon_code) && $OrderRs[0]->coupon_code!='')
			{

				$CouponRES = DB::table('pu_coupon')
								->select('coupon_number')
								->where('coupon_number', '=', $OrderRs[0]->coupon_code)
								->where('orders', '=', '4')
								->get();
				$CouponRESCnt = $CouponRES->count();

				if($CouponRESCnt > 0)
				{
					$couponCode 	= 	$OrderRs[0]->coupon_code;
					$coupon_id 		= 	$OrderRs[0]->coupon_id;
				}

			}

			$taxvalue  = 0;
			if(isset($OrderRs[0]->ship_country) && isset($OrderRs[0]->ship_zip) && isset($OrderRs[0]->ship_state) && strtolower($customer_RES[0]["eusertype"])!= "wholesaler")
			{

				$OrderSubTotal = $SubTotal - ($CouponDiscount + $AutoDiscount + $QtyDiscount+$RewardDiscount + $GiftCertificateDiscount + $BogoDiscount);
				$OrderSubTotal = $OrderSubTotal + $OrderRs[0]->shipping_amt;
				$OrderSubTotal = NumberFormat($OrderSubTotal);

				//$taxvalue = $this->PhoneOrderTaxCalculation($OrderSubTotal,$OrderRs[0]->ship_country,$OrderRs[0]->ship_state,$OrderRs[0]->ship_zip);
				$taxvalue = $this->PhoneOrderTaxCalculation($OrderSubTotal,$OrderRs[0]->ship_country,$OrderRs[0]->ship_state,$OrderRs[0]->ship_zip,$OrderRs[0]->ship_city);

			}

			$ShippingSignature = 0;

			if($OrderRs[0]->is_shipping_signature=="Yes" && $OrderRs[0]->shipping_signature > 0 && $OrderRs[0]->ship_country=="US")
			{

				$TotalVal = $SubTotal + $OrderRs[0]->shipping_amt + NumberFormat($taxvalue)  + $ShippingSignature;
			    $NetTotal = $TotalVal - (NumberFormat($AutoDiscount) + NumberFormat($GiftCertificateDiscount) + NumberFormat($QtyDiscount)+ NumberFormat($CouponDiscount)+NumberFormat($BogoDiscount)+NumberFormat($RewardDiscount));

				if($NetTotal >= 200){
					$ShippingSignature = 0;
				}else{
					if(strtolower($customer_RES[0]["eusertype"])=="wholesaler" && strtolower($customer_RES[0]["registration_type"])=="m" && $customer_RES[0]["is_dropshipper"]=="Yes")
					{
					 $ShippingSignature = config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE');
					}
					else
					{
					$ShippingSignature = config('Settings.SHIPPING_SIGNATURE');
					}

				}

			}

			$InsuranceCharge = 0;
			if(!empty($OrderRs[0]->route_shipping_insurance_charge) && $OrderRs[0]->route_shipping_insurance_charge > 0)
			{
				$TotalVal = $SubTotal + $OrderRs[0]->shipping_amt + NumberFormat($taxvalue)  + $ShippingSignature;
			    $NetTotal = $TotalVal - (NumberFormat($AutoDiscount) + $OrderRs[0]->gc_amount + NumberFormat($QtyDiscount) + NumberFormat($CouponDiscount)+NumberFormat($BogoDiscount)+NumberFormat($RewardDiscount));

			    $NetTotal = NumberFormat($NetTotal);

			    $InsuranceCharge = $this->SetShippingInsuranceCharge('add',$NetTotal,"Yes");
				$InsuranceCharge = NumberFormat($InsuranceCharge);

			}

			$TotalVal = $SubTotal + $OrderRs[0]->shipping_amt + NumberFormat($taxvalue) + $InsuranceCharge + $ShippingSignature;
			$NetTotal = $TotalVal - NumberFormat($AutoDiscount) - NumberFormat($GiftCertificateDiscount) - NumberFormat($QtyDiscount)- NumberFormat($CouponDiscount)-NumberFormat($BogoDiscount)-NumberFormat($RewardDiscount);

			$NetTotal = NumberFormat($NetTotal);

			$OrderDataArray = array(
				'auto_discount' 					=> NumberFormat($AutoDiscount),
				'sub_total' 						=> $SubTotal,
				'order_total'						=> $NetTotal,
				'quantity_discount'					=> NumberFormat($QtyDiscount),
				'coupon_id'							=> $coupon_id,
				'coupon_code'						=> $couponCode,
				'coupon_amount'						=> NumberFormat($CouponDiscount),
				'tax'								=> NumberFormat($taxvalue),
				'route_shipping_insurance_charge'	=> $InsuranceCharge,
				'shipping_signature'				=> $ShippingSignature,
				'reward_discount'					=> NumberFormat($RewardDiscount),
				'gc_amount'							=> NumberFormat($GiftCertificateDiscount),
				'bogo_discount'						=> NumberFormat($BogoDiscount)

			);

			//echo "<pre>"; print_r($OrderDataArray); exit;
			$OrderArr = Order::find($OrderID);
			$OrderArr->update($OrderDataArray);

		}

		if(isset($request->method) && $request->method=="view")
		{
		$OrderRs = DB::table('pu_orders')->select('orders_id', 'orders_no','customer_id', 'sub_total', 'tax', 'shipping_amt', 'order_total', 'refund_amount', 'wire_discount', 'auto_discount', 'quantity_discount', 'reward_discount', 'coupon_amount', 'gc_amount', 'bogo_discount', 'pay_status', 'status', 'payment_type', 'use_credit_limit', 'payment_method','payment_gateway_response','order_datetime','phoneorder_paymentdate','phoneorder_shipping_method_id','route_shipping_insurance_charge','is_shipping_signature','shipping_signature','order_upd_datetime','gift_charge','apply_credit','refer_amount','coupon_code','shipping_amt')
							->where('orders_id', '=', $OrderID)
							->get();
	//	echo "<pre>"; print_r($OrderRs); exit;
		}

		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->whereIn('pm_group_name', ['PAYMENT_STRIPE','PAYMENT_PAYWITHAFTERPAY','PAYMENT_PAYPALEC','PAYMENT_PAYWITHAMAZON'])
							->where('pm_status', '=', 'Active')
							->get();

		$IsStripeCheckout = "No";
		$arrAuthnetVar = array();
		$IsPaypalExpressCheckout = "No";
		$PaypalActionMode = "";
		$IsPayWithAmazonCheckout ='No';
		$Afterpay_Checkout ='No';

		$tot_records = $db_res->count();
		if( $tot_records > 0)
		{
			for($i=0; $i < $tot_records ; $i++){
				if($db_res[$i]->pm_group_name == "PAYMENT_STRIPE"){
					$IsStripeCheckout = "Yes";
					$arrAuthnetVar = unserialize($db_res[$i]->pm_details);

					$STRIPE_KEY  = $arrAuthnetVar['Secret_Key'];
					$PUBLISH_KEY = $arrAuthnetVar['Publishable_Key'];

					#############################

					$STRIPE_KEY   = $this->decrypt($STRIPE_KEY);
					$PUBLISH_KEY   = $this->decrypt($PUBLISH_KEY);
					#############################
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYWITHAFTERPAY"){
					$Afterpay_Checkout ='Yes';
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYPALEC"){
					$IsPaypalExpressCheckout ='Yes';
					$PaypalActionMode = "sandbox";
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYWITHAMAZON"){
					$IsPayWithAmazonCheckout ='Yes';
				}
			}
		}

		$cust_detail = Customer::select('eusertype')
								->where('customer_id', '=', $customer_id)
								->get();

		if($cust_detail->count() > 0 && $cust_detail[0]->eusertype == "Wholesaler"){
			$Afterpay_Checkout ='No';
		}
		// echo $Afterpay_Checkout;exit;

			if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.6.77"){
				 config(['app.debug' => true]);
			}
		if($Afterpay_Checkout == "Yes"){
			$payload = array();

			$getconfigs = $this->GetAfterPayResult($payload,"configuration","No");
			//echo "<pre>";print_r($getconfigs);echo "</pre>";
			$Min_AP = $Max_AP = 0;

			if(isset($getconfigs['minimumAmount']['amount'])){
				$Min_AP = $getconfigs['minimumAmount']['amount'];
			}
			if(isset($getconfigs['maximumAmount']['amount'])){
				$Max_AP = $getconfigs['maximumAmount']['amount'];
			}

			$Min_AP_AMT = round($Min_AP * 100);
			$Max_AP_AMT = round($Max_AP * 100);

			$this->PageData["Min_AP_AMT"] = $Min_AP_AMT;
			$this->PageData["Max_AP_AMT"] = $Max_AP_AMT;

			$this->PageData["RemoveOutOfStock"] = "";

			if(isset($request->method) && $request->method=='view')
			{
				$this->PageData["RemoveOutOfStock"] = $request->method;
				$isStockAvaliable = "Yes";
			}

			// $getToken = $this->GetAfterPayToken($OrderRs[0]->order_total,"checkouts/");

			if($OrderRs[0]->order_total < $Min_AP || $OrderRs[0]->order_total > $Max_AP){
				$Afterpay_Checkout = "No";
			}

			$ShippingModeRS = ShippingMode::where('status','=','1')->where('shipping_mode_id','=',$OrderRs[0]->phoneorder_shipping_method_id)->get();
			// echo "<pre>";print_r($ShippingModeRS);exit;
			$currency = "USD";
			$shipping_arr_app[0]["id"] = (string)$OrderRs[0]->phoneorder_shipping_method_id;
			$shipping_arr_app[0]["name"] = strip_tags($ShippingModeRS[0]->type);

			$description = ($ShippingModeRS[0]->detail != "") ? $ShippingModeRS[0]->detail : $ShippingModeRS[0]->type;
			$shipping_arr_app[0]["description"] = strip_tags($description);

			$shipping_arr_app[0]["shippingAmount"]["amount"] = $OrderRs[0]->shipping_amt;
			$shipping_arr_app[0]["shippingAmount"]["currency"] = $currency;
			$shipping_arr_app[0]["taxAmount"]["amount"] = $OrderRs[0]->tax;
			$shipping_arr_app[0]["taxAmount"]["currency"] = $currency;
			$shipping_arr_app[0]["orderAmount"]["amount"] = $OrderRs[0]->order_total;
			$shipping_arr_app[0]["orderAmount"]["currency"] = $currency;

			$this->PageData["Shipping_Arr_AP"] = json_encode($shipping_arr_app);
			// echo "<pre>";print_r($this->PageData["Shipping_Arr_AP"]);exit;
		}

		// echo $Afterpay_Checkout."<br>".$PUBLISH_KEY;exit;
		$Payment_Method_Message = "";
		if(($OrderRs[0]->payment_type == 'PAYMENT_AUTHORIZENETCC' || $OrderRs[0]->payment_type =='PAYMENT_PAYPALCC') and $OrderRs[0]->payment_gateway_response !='')
		{
			$arr_gateway_response = explode(",",$OrderRs[0]->payment_gateway_response);

			if ($arr_gateway_response[0] == 4)
			{
				$Payment_Method_Message = "<h3>Thank you! Your order will be processed pending a standard transaction review.</h3>
			<p>We hope you enjoyed shopping with us. Your order will be processed as soon as possible. We will contact you with updates. <br />Please allow 24hrs to process the payment. An E-mail Confirmation will be sent upon payment received.</p>";
			}
		}

		// echo $OrderRs[0]->phoneorder_paymentdate;
		// echo "<pre>";print_r($OrderRs);exit;
		// echo $OrderRs[0]->phoneorder_paymentdate;exit;

		$OrderRs[0]->datetime_order = date("m/d/Y H:i:s",strtotime($OrderRs[0]->order_datetime));

		$OrderRs[0]->paymentdate_phoneorder = "00/00/0000 00:00:00";
		if($OrderRs[0]->phoneorder_paymentdate != "0000-00-00 00:00:00"){
			$OrderRs[0]->paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]->phoneorder_paymentdate));
		}else{
			if($OrderRs[0]->order_upd_datetime != "0000-00-00 00:00:00"){
				$OrderRs[0]->paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]->order_upd_datetime));
			}else{
				$OrderRs[0]->paymentdate_phoneorder = date("m/d/Y H:i:s",strtotime($OrderRs[0]->order_datetime));
			}
		}
		//$totalDiscount = NumberFormat($AutoDiscount) + NumberFormat($GiftCertificateDiscount) + NumberFormat($QtyDiscount) + NumberFormat($CouponDiscount) + NumberFormat($BogoDiscount) + NumberFormat($RewardDiscount);

		$totalShipping = NumberFormat($OrderRs[0]->shipping_amt) + NumberFormat($OrderRs[0]->shipping_signature);

		$totalDiscount = NumberFormat($OrderRs[0]->refer_amount) + NumberFormat($OrderRs[0]->bogo_discount) + NumberFormat($OrderRs[0]->gc_amount) + NumberFormat($OrderRs[0]->coupon_amount) + NumberFormat($OrderRs[0]->reward_discount) + NumberFormat($OrderRs[0]->quantity_discount) + NumberFormat($OrderRs[0]->auto_discount);

		//echo NumberFormat($AutoDiscount)."--".NumberFormat($GiftCertificateDiscount)."--".NumberFormat($QtyDiscount)."--".NumberFormat($CouponDiscount)."--".NumberFormat($BogoDiscount)."--".NumberFormat($RewardDiscount);

		//$this->PageData['CSSFILES'] = ['myaccount.css'];
		$this->PageData['Payment_Method_Message'] = $Payment_Method_Message;

		$viewInvoiceUrl = config('global.SITE_URL').'invoice/'.base64_encode($OrderRs[0]->orders_no);
		$PaymentURL = config('global.SITE_URL').'payment/'.$request['id']."/view";
		$this->PageData['viewInvoiceUrl'] = $viewInvoiceUrl;
		$this->PageData['PaymentURL'] = $PaymentURL;

		$payInvoiceUrl = config('global.SITE_URL').'stripe/phoneorder';
		$this->PageData['payInvoiceUrl'] = $payInvoiceUrl;

		$this->PageData['OrderID'] = base64_encode($OrderID);
		$this->PageData['OrderRs'] = $OrderRs[0];
		$this->PageData['OrderDetails'] = $orderDetails;

		if($totalDiscount > 0){
			$this->PageData['totalDiscount'] = NumberFormat($totalDiscount);
		} else {
			$this->PageData['totalDiscount'] = 0;
		}

		if($totalShipping > 0){
			$this->PageData['totalShipping'] = NumberFormat($totalShipping);
		} else {
			$this->PageData['totalShipping'] = 0;
		}

		if(!empty($payPalorderDetails)){
			$this->PageData['payPalorderDetails'] = json_encode($payPalorderDetails);
		} else {
			$this->PageData['payPalorderDetails'] = '';
		}

		//echo "<pre>"; print_r($orderDetails); exit;

		$this->PageData["PaypalActionMode"] = $PaypalActionMode;
		$this->PageData["Afterpay_Checkout"] = $Afterpay_Checkout;
		$this->PageData["IsPayWithAmazonCheckout"] = $IsPayWithAmazonCheckout;
		$this->PageData["IsPaypalExpressCheckout"] = $IsPaypalExpressCheckout;
		$this->PageData["IsStripeCheckout"] = $IsStripeCheckout;
		$this->PageData["isStockAvaliable"]	= $isStockAvaliable;
		$this->PageData["IsStockCondition"]	= $IsStockCondition;
		$this->PageData["WebsiteAvailStock"]= $WebsiteAvailStock;

		// $this->PageData["Afterpay_Token"] = $getToken;

		Session::forget('phoneorder_detail');
		Session::put('phoneorder_detail.order_id',$OrderID);
		Session::put('phoneorder_detail.order_amt',$OrderRs[0]->order_total);
		Session::put('phoneorder_detail.customer_id',$OrderRs[0]->customer_id);

		if($Afterpay_Checkout == "Yes" && isset($Min_AP_AMT) && isset($Max_AP_AMT)){
			Session::put('phoneorder_detail.Afterpay.Min_AP_AMT',$Min_AP_AMT);
			Session::put('phoneorder_detail.Afterpay.Max_AP_AMT',$Max_AP_AMT);
		}

		$Checkout_Available = "Yes";
		if($IsStripeCheckout == "No" && $IsPaypalExpressCheckout == "No" && $IsPayWithAmazonCheckout == "No" && $Afterpay_Checkout == "No"){
			$Checkout_Available = "No";
		}
		$this->PageData["Checkout_Available"] = $Checkout_Available;

		$this->SetAmazonConfig('phoneorder_payment_receipt');

		// echo "<pre>";print_r($this->PageData);exit;
		return view('myaccount.phoneorderpayreceipt')->with($this->PageData);
	}

	public function PhoneOrderTaxCalculation($subTotal,$ship_country,$ship_state,$ship_zip,$ship_city = ''){
		$ship_city = trim($ship_city);	
		$isFromPayPalProductPage = "No";
		
		$subTotal = max(0, NumberFormat($subTotal));
		
		$ship_zip = $ship_zip ?: '0';		
	
		$calculateTax = function ($area) use ($subTotal, $isFromPayPalProductPage) {			
		
			$rate = TaxRates::where('tax_areas_id', $area->tax_areas_id)
				->where('amount_from', '<=', $subTotal)
				->orderBy('amount_from', 'desc')
				->first();
		
			if (!$rate) return null;

			$tax = ($rate->amount_in_percent == 'Y')
				? ($subTotal * $rate->charge_amount) / 100
				: $rate->charge_amount;

			return $tax;
		};

		// =========================================================
		// PRIORITY 1: City + State + ZIP + Country (STRICT MATCH)
		// =========================================================
		
		if (!empty($ship_city)) {
			$area = TaxAreas::where('country', $ship_country)
				->where('states', $ship_state)
				->whereRaw('LOWER(county) = ?', [strtolower($ship_city)])
				->where('zip_from', '<=', (int)$ship_zip)
				->where('zip_to', '>=', (int)$ship_zip)
				->where('status', '1')->first();
				
			
			if ($area) return $calculateTax($area);
		}
		
		// =========================================================
		// PRIORITY 2: ZIP + State + Country
		// =========================================================
		$area = TaxAreas::where('country', $ship_country)
			->where('states', $ship_state)
			->where('zip_from', '<=', (int)$ship_zip)
			->where('zip_to', '>=', (int)$ship_zip)
			->where('status', '1')
			->first();

		if ($area) return $calculateTax($area);
		
		
		
		// =========================================================
		// PRIORITY 3: ZIP And country only
		// =========================================================
		$area = TaxAreas::where('country', $ship_country)
			->where('states', '')
			->where('zip_from', '<=', (int)$ship_zip)
			->where('zip_to', '>=', (int)$ship_zip)
			->where('status', '1')
			->first();

		if ($area) return $calculateTax($area);
		
		
		
		// =========================================================
		// PRIORITY 4: Country And State
		// =========================================================
			$area = TaxAreas::where('country', $ship_country)
				->where('states', $ship_state)
				->where('zip_from','=','')->where('zip_to','=','')
				->where('status', '1')
				->first();

			if ($area) return $calculateTax($area);
		
		
		
		// =========================================================
		// PRIORITY 5: City + State + Country
		// =========================================================
		if (!empty($ship_city)) {
			$area = TaxAreas::where('country', $ship_country)
				->where('states', $ship_state)
				->whereRaw('LOWER(county) = ?', [strtolower($ship_city)])
				->where('status', '1')
				->first();

			if ($area) return $calculateTax($area);
		}
		
		// =========================================================
		// PRIORITY 5: State only
		// =========================================================
		/*$area = TaxAreas::where('country', $ship_country)
			->where('states', $ship_state)
			->where('status', '1')

			->where(function ($q) use ($ship_city) {
				 $q->whereRaw('LOWER(county) = ?', [strtolower(trim($ship_city))]);
			})

			->where(function ($q) use ($ship_zip) {
				$q->where('zip_from', '<=', (int)$ship_zip)
				  ->where(function ($q2) use ($ship_zip) {
					  $q2->where('zip_to', '>=', (int)$ship_zip)
						 ->orWhereNull('zip_to')
						 ->orWhere('zip_to', '');
				  });
			})

			->first();
		
		if (!$area) 
		{

		$area = TaxAreas::where('country', $ship_country)
			->where('states', $ship_state)
			->where('status', '1')
			->whereNull('county')
			->where(function ($q) use ($ship_zip) {
				$q->where('zip_from', '<=', (int)$ship_zip)
				  ->where(function ($q2) use ($ship_zip) {
					  $q2->where('zip_to', '>=', (int)$ship_zip)
						 ->orWhereNull('zip_to')
						 ->orWhere('zip_to', '');
				  });
			})
			->first();
		}
		
		if ($area) return $calculateTax($area);
        */
		// =========================================================
		// PRIORITY 6: Country
		// =========================================================
		$area = TaxAreas::where('country', $ship_country)
			->where('status', '1')
			->where('country', '!=', 'US')
			->first();
		
		if ($area) return $calculateTax($area);

		// Default = 0
		if ($isFromPayPalProductPage == "Yes") {
			return 0;
		} else {			
			return null;
		}

	}

	public function PhoneOrderTaxCalculation_old($subTotal,$ship_country,$ship_state,$ship_zip)
	{

		if($ship_zip == '' )
			$ship_zip = '0';

		$temp_tax = 0;
		## Compare Zip and country
		 $taxtareas = TaxAreas::where('zip_from','>=',(int)$ship_zip)->where('zip_to','<=',(int)$ship_zip)
								->where('zip_from','!=','')->where('zip_to','!=','')->where('states','=','')
								->where('country','=',$ship_country)->where('status','=','1')->get();
		if($taxtareas && $taxtareas->count() > 0)
		{
			$taxrates = TaxRates::where('tax_areas_id','=',(int)$taxtareas[0]["tax_areas_id"])
								->where('amount_from','<=',$subTotal)->orderBy('amount_from','desc')->get();
			if($taxrates && $taxrates->count() > 0)
			{
				$pertex = $taxrates[0]["charge_amount"];
				if($subTotal>=$taxrates[0]["amount_from"])
				{
					if ($taxrates[0]["amount_in_percent"] == 'Y')
					{
						$temp_tax = (($subTotal * $pertex) / 100);

						return $temp_tax;
					}
					else
					{
						return $pertex;
					}
				}
			}
		}

		## Compare Country or State or Zip
		$taxtareas = TaxAreas::where('zip_from','>=',(int)$ship_zip)->where('zip_to','<=',(int)$ship_zip)
								->where('zip_from','!=','')->where('zip_to','!=','')
								->where('states','!=','')->where('states','=',$ship_state)
								->where('country','=',$ship_country)->where('status','=','1')->get();
		if($taxtareas && $taxtareas->count() > 0)
		{
			$taxrates = TaxRates::where('tax_areas_id','=',(int)$taxtareas[0]["tax_areas_id"])
								->where('amount_from','<=',$subTotal)->orderBy('amount_from','desc')->get();

			if($taxrates && $taxrates->count() > 0)
			{
				$pertex = $taxrates[0]["charge_amount"];
				if($subTotal >= $taxrates[0]["amount_from"])
				{
					if ($taxrates[0]["amount_in_percent"] == 'Y')
					{
						$temp_tax = (($subTotal * $pertex) / 100);
						return $temp_tax;
					}
					else
					{
						return $pertex;
					}
				}
			}
		}

		### Code on New perfume4us
		## Compare Country AND  State
		$taxtareas = TaxAreas::where('zip_from','=','')->where('zip_to','=','')
								->where('states','!=','')->where('states','=',$ship_state)
								->where('country','=',$ship_country)->where('status','=','1')->get();
		if($taxtareas && $taxtareas->count() > 0)
		{
			$taxrates = TaxRates::where('tax_areas_id','=',(int)$taxtareas[0]["tax_areas_id"])
								->where('amount_from','<=',$subTotal)->orderBy('amount_from','desc')->get();

			if($taxrates && $taxrates->count() > 0)
			{
				$pertex = $taxrates[0]["charge_amount"];
				if($subTotal>=$taxrates[0]["amount_from"])
				{
					if ($taxrates[0]["amount_in_percent"] == 'Y')
					{
						$temp_tax = (($subTotal * $pertex) / 100);
						return $temp_tax;
					}
					else
					{
						return $pertex;
					}
				}
			}
		}
		## Compare Country AND  State
		### Code on New perfume4us

		## Compare Country
		$taxtareas = TaxAreas::where('country','=',$ship_country)->where('country','!=','US')->where('status','=','1')->get();

		if($taxtareas && $taxtareas->count() > 0)
		{
			$taxrates = TaxRates::where('tax_areas_id','=',(int)$taxtareas[0]["tax_areas_id"])
								->where('amount_from','<=',$subTotal)->orderBy('amount_from','desc')->get();

			if($taxrates && $taxrates->count() > 0)
			{
				$pertex = $taxrates[0]["charge_amount"];
				if($subTotal>=$taxrates[0]["amount_from"])
				{
					if ($taxrates[0]["amount_in_percent"] == 'Y')
					{
						$temp_tax = (($subTotal * $pertex) / 100);
						return $temp_tax;
					}
					else
					{
						return $pertex;
					}
				}
			}
		}
		return $temp_tax;
	}

}
?>
