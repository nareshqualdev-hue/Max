<?php
namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Afterpay\SDK\MerchantAccount as AfterpayMerchantAccount;
use Afterpay\SDK\HTTP\Request\GetConfiguration as AfterpayGetConfigurationRequest;
use App\Http\Controllers\Traits\EncryptTrait;

use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\ProductDetailTrait;
use App\Models\Products;
use App\Models\ProductsCategory;
use App\Models\Customer;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\AutoDiscount;
use App\Models\QuantityDiscount;
use App\Models\Manufacture;
use App\Models\GiftCertificate;
use App\Models\OrderDetail;
use App\Models\Shoppingcart;
use App\Models\BogoDiscount;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\FreeGiftProduct;
use App\Models\FreegiftBrand;
use App\Models\FreegiftCategory;
use App\Models\ShippingRule;
use App\Models\RewardRule;
use App\Models\RewardPoint;
use App\Models\StoreInventory;
use App\Models\FreeVialSampleProduct;
use App\Http\Services\CacheService;
use Carbon\Carbon;
use DB;
use Session;
use Cookie;

trait CartTrait
{
	use VendorTrait;
	use CommonTrait;
	use EncryptTrait;
	use ProductDetailTrait;
	private ?bool $giftCouponsAvailableCache = null;
	private ?bool $couponsAvailableCache = null;
	public function ShowCart()
	{
		$ShoppingCart = [];
		if(Session::has('ShoppingCart'))
		{
			$ShoppingCart = Session::get('ShoppingCart');
			/*
			$CartAttr = $this->SetCartAttributes();
			$ShoppingCart['IsPaypalExpressCheckout'] = $CartAttr['IsPaypalExpressCheckout'];
			$ShoppingCart['Amazon_pay_Checkout'] = $CartAttr['Amazon_pay_Checkout'];
			$ShoppingCart['Afterpay_Checkout'] = $CartAttr['Afterpay_Checkout'];
			*/
		}
		return $ShoppingCart;
	}

	public function AddToCart($products_id, $qty = 1, $cookiee = 'No', $Omniflag = 'Yes', $YotpoFreeGift = 'No', $OrderType = "Website")
	{
		//Session::forget('ShoppingCart');

		$success_msg = '';

		$ProductChkStock = $this->ProductCheckInStock($products_id, $qty,"insert",$cookiee, $OrderType);
		$ProductChkStockVal = $ProductChkStock["StockInfo"];

		$u_type = "retailer";
		if(Session::has('eusertype') && Session::get('eusertype')!=''){
			$u_type = strtolower(Session::get('eusertype'));
		}
		$CartErrors = [];
		if ($ProductChkStockVal == '1111')
			$CartErrors[] = config('message.Cart.ProductNotAvailable');
		//if($ProductChkStock == '2222')
		if ($ProductChkStockVal == '2222' && !Session::has('isPhoneOrder')) //15022024
			$CartErrors[] = config('message.Cart.QuantityNotAvailable');

		if(count($CartErrors) > 0)
		{
			Session::flash('CartErrors', $CartErrors);
			return response()->json(array('Added' => 0,'CartErrors' => $CartErrors));
		}

		if(isset($ProductChkStock['StockInfo']) > 0 && $ProductChkStock['StockInfo'] == '2222')
		{
			if(isset($ProductChkStock['availableStock']) && $ProductChkStock['availableStock']!='')
			{
				if($u_type == 'retailer' && $ProductChkStock['availableStock'] > 20){
					$maxqty_error = 1;
					$CartErrors[] = "The maximum quantity you can add is 20 pieces.";//config('message.Cart.QuantityNotAvailable');
				} else {
					$maxqty_error = 1;
					$CartErrors[] = "The maximum quantity you can add is ".$ProductChkStock['availableStock']." pieces.";//config('message.Cart.QuantityNotAvailable');
				}
			}
		}

		if($u_type == 'retailer' && $qty > 20){
			$CartErrors[] = "The maximum quantity you can add is 20 pieces.";
		}
		if($u_type == 'retailer' && Session::has('ShoppingCart.Cart')){
			$Cart = Session::get('ShoppingCart.Cart');
			if(Session::has('ShoppingCart.Cart') && count($Cart) > 0)
			{
				for($a=0; $a < count($Cart); $a++)
				{
					//if($Cart[$a]['ProductID'] == $products_id && $products_id != 0 && !isset($Cart[$a]['IS_Free_Gift'])){
					if($Cart[$a]['ProductID'] == $products_id && $products_id != 0 && !isset($Cart[$a]['IS_Free_Gift'])  && !isset($Cart[$a]['Is_Free_Sample']) ){
						//if($Cart[$a]['Qty'] > 20){
						if(($Cart[$a]['Qty'] + $qty) > 20){
							$CartErrors[] = "The maximum quantity you can add is 20 pieces.";
						}
					}
				}
			}
		}

		if(count($CartErrors) > 0)
		{
			Session::flash('CartErrors', $CartErrors);
			return response()->json(array('Added' => 0,'CartErrors' => $CartErrors));
		}

		if($cookiee=='Yes')
			$ProductChkFlg = $this->ProductCheckInCart($products_id, $qty,'insert',$cookiee);
		else
			$ProductChkFlg = $this->ProductCheckInCart($products_id, $qty);

		if($ProductChkFlg==1)
		{
			$a = $this->CalculateSubTotal();
            /** OMANISEND **/
            if($Omniflag == 'Yes'){
                OmanisendRequest('setCart',['CartData' => Session::get('ShoppingCart')]);
            }
            /** OMANISEND **/
            if ($OrderType == "Store") {
				$success_msg = 'Product added to cart.';
				return response()->json(array('Added' => 0, 'exist' => 1,'success' => $success_msg));
			}else{
				return response()->json(array('Added' => 0, 'exist' => 1));
			}
		}
		$per = 0;
		$val = 0;
		if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler')
		{
			if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
			{
				$specialpricedtl = GetSpecialPricePercentandValue($qty);
				$perval = explode("#",$specialpricedtl);
				$per = $perval[0];
				$val = $perval[1];
			}
		}

		$isStore = Auth::guard('store')->check() && $OrderType === "Store";

		$query = Products::query()
			->join('pu_products_one as po', 'pu_products.products_id', '=', 'po.products_id');

		if ($isStore) {
			$store = Auth::guard('store')->user();

			$query->join('pu_store_inventory as ps', 'pu_products.products_id', '=', 'ps.products_id')
				->where('ps.store_id', $store->store_id)
				->select('pu_products.*', 'ps.current_stock as store_currentStock');
		} else {
			$query->select('pu_products.*');
		}

		$ProdInfo = $query->where(function ($q) {
			$q->orWhere('pu_products.status', '=', '1')
				->orWhere(function ($qry) {
					$qry->where('pu_products.status', '=', '2')
						->where('po.is_private', '=', 'Yes')
						->where('po.private_code', '!=', '');
				});
		})
			->where('pu_products.products_id', $products_id)
			->distinct()
			->get();

		if(!$ProdInfo || $ProdInfo->count() == 0 )
		{
			return response()->json(array('Added' => 0));
		}

		$ProductRs = $this->SetProduct($ProdInfo[0]);

		$CodeVal = "";
		if($ProductRs->private_code!='' && $ProductRs->is_private == 'Yes' && $ProductRs->status == '2')
		{
			$CodeVal = $ProductRs->private_code;
		}
		## Here Overwrite sale Price Field
		$ProductRs->sale_price = $ProductRs->product_price;
		$actual_product_price = $ProductRs->product_price;

		$ProductRs->IsDealProducts = 'No';
		$ProductRs->DealDiscountFlag = 'No';

		// $ProductRs->sku = strtoupper($ProductRs->sku);
		$ProductRs->ItemPrice = NumberFormat($ProductRs->sale_price);
		if(isset($ProductRs->WebsiteStock) && $ProductRs->WebsiteStock=="In")
		{
			$DealOfWeek = GetDealOfWeek($ProductRs->sku,'Weekly','Cart');

			if(count($DealOfWeek) > 0)
			{
				if(isset($DealOfWeek[$ProductRs->sku]['deal_price']) && $DealOfWeek[$ProductRs->sku]['deal_price'] < $ProductRs->sale_price )
				{
					$dealprice = NumberFormat($DealOfWeek[$ProductRs->sku]['deal_price']);
					$ProductRs->sale_price = $dealprice;
					$ProductRs->ItemPrice  = $dealprice;
					if($DealOfWeek[$ProductRs->sku]['description']!='')
					{
						$ProductRs->short_description = $DealOfWeek[$ProductRs->sku]['description'];
						$ProductRs->short_description  = remove_html_entities($ProductRs->short_description);
					}
				}
				$ProductRs->DealDiscountFlag = 'No';
				if(isset($DealOfWeek[$ProductRs->sku]['discount_coupon_flag']))
				{
				$ProductRs->DealDiscountFlag = $DealOfWeek[$ProductRs->sku]['discount_coupon_flag'];
				}
				$ProductRs->IsDealProducts = 'Yes';
			}
		}
		/*
		$DailyDeal = GetDealOfWeek($ProductRs->sku,'Daily','Cart');
		if(count($DailyDeal) > 0)
		{
			if($DailyDeal[$ProductRs->sku]['deal_price']!='' && $DailyDeal[$ProductRs->sku]['deal_price'] < $ProductRs->sale_price )
			{
				$dealprice = NumberFormat($DailyDeal[$ProductRs->sku]['deal_price']);
				$ProductRs->sale_price = $dealprice;
				$ProductRs->ItemPrice  = $dealprice;
				if($DailyDeal[$ProductRs->sku]['description']!='')
				{
					$ProductRs->short_description = $DailyDeal[$ProductRs->sku]['description'];
				}
			}
			$ProductRs->DealDiscountFlag = $DailyDeal[$ProductRs->sku]['discount_coupon_flag'];
			$ProductRs->IsDealProducts = 'Yes';
		}*/

		if(file_exists(config('global.PRD_LARGE_IMG_PATH').stripslashes($ProductRs->image)) && !empty($ProductRs->image)){
			$imgver = filemtime(config('global.PRD_LARGE_IMG_PATH').stripslashes($ProductRs->image));
			$thumb_image = config('global.PRD_LARGE_IMG_URL').rawurlencode($ProductRs->image)."?ver=".$imgver;
		} else {
			$thumb_image = config('global.NO_IMAGE_LARGE');
		}

		$ProductRs->prod_image ='<img src="'.config('global.SPEED_SIZE_URL').$thumb_image.'" border="0" width="125" alt="'.$ProductRs->product_name.'"/>';
		$ProductRs->image_forpopup ='<img src="'.config('global.SPEED_SIZE_URL').$thumb_image.'" border="0" width="75" alt="'.$ProductRs->product_name.'"/>';
		$ProductRs->billing_image ='<img src="'.config('global.SPEED_SIZE_URL').$thumb_image.'" border="0" width="195" alt="'.$ProductRs->product_name.'" title="'.$ProductRs->product_name.'"/>';

		$p_link = $this->getProductRewriteURL($ProductRs->products_id, $ProductRs->product_name);
		if($CodeVal!='')
			$p_link = $p_link."/".$CodeVal;

		$IsCosmo = "No";
		$IsNandansons = "No";
		$IsPerfumePW  = "No";
		$IsPCA  = "No";
		$IsND  = "No";
		$VendorSKU = "";

		if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler')
		{
			$cosmo_our_price = $ProductRs->cosmo_wholesale_price;
			$nandansons_our_price = $ProductRs->nandansons_wholesale_price;
			$perfumeworldwide_our_price = $ProductRs->perfumeworldwide_wholesale_price;
			$pca_our_price = $ProductRs->pca_wholesale_price;
			$nd_our_price = $ProductRs->nd_wholesale_price;
		} else {
			$cosmo_our_price = $ProductRs->cosmo_our_price;
			$nandansons_our_price = $ProductRs->nandansons_our_price;
			$perfumeworldwide_our_price = $ProductRs->perfumeworldwide_our_price;
			$pca_our_price = $ProductRs->pca_our_price;
			$nd_our_price = $ProductRs->nd_our_price;
		}
		if($ProductRs->WebsiteStock == "Out" && !Auth::guard('store')->check())
		{
			if($ProductRs->cosmo_sku!='' &&  $ProductRs->cosmo_current_stock > 0 &&  $cosmo_our_price > 0)
			{
				$IsCosmo = "Yes";
				$VendorSKU = $ProductRs->cosmo_sku;
			}
			else if($ProductRs->pca_sku!='' &&  $ProductRs->pca_current_stock > 0 && $pca_our_price > 0)
			{
				$IsPCA  = "Yes";
				$VendorSKU = $ProductRs->pca_sku;
			}
			else if($ProductRs->nandansons_sku!='' &&  $ProductRs->nandansons_current_stock > 0 && $nandansons_our_price > 0)
			{
				$IsNandansons = "Yes";
				$VendorSKU = $ProductRs->nandansons_sku;
			}
			else if($ProductRs->perfumeworldwide_sku!='' &&  $ProductRs->perfumeworldwide_currentstock > 0 && $perfumeworldwide_our_price > 0)
			{
				$IsPerfumePW = "Yes";
				$VendorSKU = $ProductRs->perfumeworldwide_sku;
			}
			else if($ProductRs->nd_sku!='' &&  $ProductRs->nd_current_stock > 0 && $nd_our_price > 0)
			{
				$IsND = "Yes";
				$VendorSKU = $ProductRs->nd_sku;
			}
		}

		//$fetch_brand = DB::table('pu_manufacture')->where('imanufactureid','=',$ProductRs->imanufactureid)->get();
		$fetch_brand = CacheService::Brands()->firstWhere('imanufactureid',(int)$ProductRs->imanufactureid);
		/*
		$fetch_category = DB::table('pu_category as c')
                                ->join('pu_products_category as pc','c.category_id','=','pc.category_id')
                                ->join('pu_products as p', 'pc.products_id','=','p.products_id')
                                ->where('p.products_id','=',$ProductRs->products_id)->get();
		*/
		$fetch_category = CacheService::ProductCategories()->firstWhere('products_id',(int)$ProductRs->products_id);

	   $CategoryID = '0';
		if($fetch_category && $fetch_category->count() > 0)
		{
			$gcat = stripcslashes($fetch_category->category_name);
			$CatInfo = config('CATEGORY_INFO');
			$breadcrumbs = $CatInfo['CatForProd'][$fetch_category->category_id]['subcatbredcrum'];
			$CategoryID	= $fetch_category->category_id;
		}

		/* = "";
		if(($CategoryID==68 || $CategoryID==69 || $CategoryID==70 || $CategoryID==71) && $ProductRs->is_atomizer=="Yes")
		{
			$FinalSale = config('global.FINALSALE');
		}*/

		$FinalSale = "";
		$product_categories = $this->getProductCategories($products_id);
		if($product_categories != ''){
			$product_categories_arr = explode(",",$product_categories);
			if((in_array(68,$product_categories_arr) || in_array(69,$product_categories_arr) || in_array(70,$product_categories_arr) || in_array(71,$product_categories_arr)) &&  $ProductRs->is_atomizer == 'Yes'){
				$FinalSale = config('global.FINALSALE');
			}
		}

		$temp_ary = array();
		$temp_ary['CategoryID']   		= $CategoryID;
		$temp_ary['ProductID']   		= $ProductRs->products_id;
		$temp_ary['SKU']         		= $ProductRs->sku;
		$temp_ary['FinalSale']         	= $FinalSale;
		$temp_ary['ProductName'] 		= remove_html_entities($ProductRs->product_name);
		$temp_ary['short_description'] 	= remove_html_entities(strip_tags($ProductRs->short_description));
		$temp_ary['Billing_Image'] 		= $ProductRs->billing_image;
		$temp_ary['IsDealProducts']		= $ProductRs->IsDealProducts;
		$temp_ary['DealDiscountFlag']	= $ProductRs->DealDiscountFlag;
		$temp_ary['IsGiftWrapProduct']	= $ProductRs->is_gift_wrap;
		$temp_ary['VendorSKU']			= $VendorSKU;
		$temp_ary['IsCosmo']			= $IsCosmo;
		$temp_ary['IsNandansons']		= $IsNandansons;
		$temp_ary['IsPerfumePW']		= $IsPerfumePW;
		$temp_ary['IsPCA']				= $IsPCA;
		$temp_ary['IsND']				= $IsND;
		$temp_ary['ImanufactureID']		= $ProductRs->imanufactureid;
		$temp_ary['manufactureName']	= $fetch_brand->vmanufacture??'';
		$temp_ary['CategoryName']		= $breadcrumbs;
		$temp_ary['AutoItemWiseDiscout']	= 0;
		$temp_ary['QuantityItemWiseDiscout']	= 0;
		$temp_ary['CouponDisItemWiseDiscout']	= 0;
		$temp_ary['RewardItemWiseDiscout']	= 0;
		$temp_ary['BogoItemWiseDiscout']	= 0;

		$temp_ary['IsGiftCertificateItem'] = 'No';

		$temp_ary['OrderType'] = $OrderType;

		$ProductName_description 		= remove_html_entities($ProductRs->product_name).' '.remove_html_entities(strip_tags($ProductRs->short_description));

		if($ProductRs->WebsiteStock == "In")
		{
			$temp_ary['IsMaxaromaTwoDelivery'] = $ProductRs->maxtwodaydelivery;
		}
		if($ProductRs->shipping_weight == "Normal")
		{
			$temp_ary['shipping_weightVal'] = 'Normal';
		}
		if($ProductRs->shipping_weight == "Light")
		{
			$temp_ary['shipping_weightVal'] = 'Light';
		}
		if($ProductRs->shipping_weight == "Heavy")
		{
			$temp_ary['shipping_weightVal'] = 'Heavy';
		}

		if(isset($ProductRs->IsDealProducts) && $ProductRs->IsDealProducts == "Yes")
		{
			$temp_ary['IsMaxaromaTwoDelivery'] = 'No';
		}

		if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler')
		{
			$SpecialPriceDetails = '';
			if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
			{
				if($per > 0)
				{
					$ProductRs->sale_price = $ProductRs->sale_price - $ProductRs->sale_price* $per/100;
					$ProductRs->ItemPrice  = $ProductRs->ItemPrice - $ProductRs->ItemPrice* $per/100;
				}
				$SpecialPriceDetails = getWholesalerSpecialPricesDetails($actual_product_price);
			}
		}

		if(strlen($ProductName_description) > 34)
			$temp_ary['ProductName_description'] = substr($ProductName_description,0,34).'...';
		else
			$temp_ary['ProductName_description'] = $ProductName_description;

		//$temp_ary['ItemPrice'] = NumberFormat($ProductRs->ItemPrice);

		## set check out process price
        $SalePrice = NumberFormat($ProductRs->sale_price);
        $TotalPrice = NumberFormat($SalePrice*$qty);
        $ItemPrice = NumberFormat($ProductRs->ItemPrice);
        $YotpoFreeGiftCoupon = "";
        $IsYotpoFreeProduct = 'No';
        if($YotpoFreeGift == 'Yes')
        {
            $SalePrice = 0;
            $TotalPrice = 0;
            $ItemPrice = 0;
            $YotpoFreeGiftCoupon = config('YotpoFreeGiftCoupon');
            $IsYotpoFreeProduct = 'Yes';
        }
		if(Session::has('isPhoneOrder') && Session::has('ShoppingCart.PhoneOrderProductPrice')){
			$phoneOrderProductPrice = Session::get('ShoppingCart.PhoneOrderProductPrice');
			$SalePrice = $ItemPrice = $phoneOrderProductPrice[$ProductRs->products_id];
			//$TotalPrice = NumberFormat($SalePrice*$qty);
			$TotalPrice = NumberFormat((float)$SalePrice * (float)$qty);
		}
		if($qty<=0)
		{
			$qty = 1;
		}
        $temp_ary['IsYotpoFreeProduct'] = $IsYotpoFreeProduct;
        $temp_ary['ItemPrice'] = $ItemPrice;
		$temp_ary['Price']       	= $SalePrice;
		$temp_ary['Qty'] 		 	= $qty;
		$temp_ary['TotPrice']    	= $TotalPrice;
		$temp_ary['Image']       	= $ProductRs->prod_image;
		$temp_ary['Prod_URL']       = $p_link;
		$temp_ary['image_forpopup'] = $ProductRs->image_forpopup;
		$temp_ary['Product_Type'] 	= $ProductRs->product_type;
		$temp_ary['gift_wrap']		= 'No';

		if($ProductRs->point_multiplier <=0)
		{
			$ProductRs->point_multiplier = 0;
		}
		$temp_ary['RewardItemWise'] = $temp_ary['TotPrice'] * $ProductRs->point_multiplier;
		$temp_ary['RewardItemWise'] = NumberFormat($temp_ary['RewardItemWise']);
		$temp_ary['PointMultipier'] = $ProductRs->point_multiplier;

		if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler')
		{
			$temp_ary['Markup_Percent']		  = $per;
			$temp_ary['Markup_Value']		  = $val;
			$temp_ary['SpecialPriceDetails']  = $SpecialPriceDetails;
			$temp_ary['ActualWholesalePrice'] = $actual_product_price;
		}

		$temp_ary['HandlingTimeStr'] = '';
		if($ProductRs->WebsiteStock == "Out" && $ProductRs->stock == "In" && ($temp_ary['IsCosmo']=="Yes" || $temp_ary['IsPCA']=="Yes" || $temp_ary['IsNandansons']=="Yes" || $temp_ary['IsPerfumePW']=="Yes" || $temp_ary['IsND']=="Yes"))
		{
			$temp_ary['HandlingTimeStr'] = "3 Business Days Handling Time";
		}

		/** Display Product Stock Urgency Message **/
		if ($OrderType == "Store") {
			$StockLeft = $ProductRs->store_currentStock;
		} else {
			$StockLeft = ($ProductRs->current_stock - $ProductRs->minimum_stock);
		}
		$temp_ary['stock_left'] = $StockLeft;
		/** Display Product Stock Urgency Message **/

		if($temp_ary['Price'] <= 0 && $YotpoFreeGift == 'No' ){
			return response()->json(array('Added' => 0));
		}

		$Cart = Session::get('ShoppingCart.Cart');
		if($Cart && count($Cart) > 0)
			$Cart = array_values($Cart);
		$Cart[] = $temp_ary;
		Session::put('ShoppingCart.Cart',$Cart);
		Session::put('ShoppingCart.YotpoFreeGiftCoupon',$YotpoFreeGiftCoupon);
		if(!Session::has('eusertype') && $temp_ary['Product_Type'] == 'wholesaler' && $OrderType != "Store")
		{
			Session::forget('ShoppingCart.Cart');
		}
		if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler' && $temp_ary['Product_Type'] == 'retailer')
		{
			Session::forget('ShoppingCart.Cart');
		}
		if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='retailer' && $temp_ary['Product_Type'] == 'wholesaler')
		{
			Session::forget('ShoppingCart.Cart');
		}

		$a = $this->CalculateSubTotal();

		$AllDiscounts = $this->GetAllDiscounts();
		$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];
		/*
		if(config('Settings.FREEGIFTFLAG')=="Yes" && (!Session::has('eusertype') || (Session::has('eusertype') && strtolower(trim(Session::has('eusertype') ?? '') != 'wholesaler'))) && !Auth::guard('store')->check())
		{
			if(!Session::has('isPhoneOrder')){
				$TotalFreeGiftItems = $this->GetTotalItemsOfFreegift();
				$FreeGiftProductId  = $this->GetFreegiftId();
				$Gift_Free_In_Cart = $this->CheckFreeGiftInCart($TotalValue,$TotalFreeGiftItems,$FreeGiftProductId);
				if($Gift_Free_In_Cart == 'No')
				{
					$this->SetFreegift($Gift_Free_In_Cart);
				}
			}
		}
		*/
		$GA4 = googleAnalyticsGA4("Shoppingcart",$temp_ary,$this->GetNetTotal());
		Session::put('GACode',$GA4);

		if(isset($ProductRs->products_id))
		{
            /** OMANISEND **/
            if($Omniflag == 'Yes'){

                OmanisendRequest('setCart',['CartData' => Session::get('ShoppingCart')]);
            }

			//$myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
			$myFile = env('LOG_BASE_PATH') .'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$stringData = '';
				$fh = fopen($myFile, 'a+');
				if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
					$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate AddToCart Session Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
				}
				if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
					$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate AddToCart Session User Email : ".Session::get('sess_useremail')." :\n";
				}
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$logarr['ProductRs'] = $ProductRs;
			addLog("AddToCart",$logarr);

			//Show Bogo Discount Message
			// if($cookiee == 'Yes')
			// {
			// 	$this->CheckBOGODiscountProduct($temp_ary);
			// }
			//Show Bogo Discount Message
            //OmanisendRequest('setCart',$ProductRs,['quantity' => $qty,'CartData' => Session::get('ShoppingCart'),'imageUrl' => $thumb_image, 'prodLink' => $p_link]);
			/** OMANISEND **/

			if ($OrderType == "Store") {
				$success_msg = 'Product added to cart.';
				return response()->json(array('Added' => 1,'success' => $success_msg));
			}
			else{
				return response()->json(array('Added' => 1));
			}
		}
		return response()->json(array('Added' => 0));
	}

	public function ProductCheckInStock($Product_id,$qty=1,$opt,$cookiee='No',$OrderType = "Website")
	{
		//Below condition for Gift Wrap and gift certificate (Do not check stock)
		if($Product_id==0)
		{
			return ['StockInfo' => 3333];
		}
		if($qty<=0)
		{
			$qty = 1;
		}

		if (empty($cookiee)) {
			$cookiee = 'No';
		}

		$isStore = Auth::guard('store')->check() && $OrderType === "Store";

		$query = Products::query()
			->join('pu_products_one as po', 'pu_products.products_id', '=', 'po.products_id');

		if ($isStore) {
			$store = Auth::guard('store')->user();

			$query->join('pu_store_inventory as ps', 'pu_products.products_id', '=', 'ps.products_id')
				->where('ps.store_id', $store->store_id)
				->select('pu_products.*', 'ps.current_stock as store_currentStock');
		} else {
			$query->select('pu_products.*');
		}

		$ProdInfo = $query->where(function ($q) {
			$q->orWhere('pu_products.status', '=', '1')
				->orWhere(function ($qry) {
					$qry->where('pu_products.status', '=', '2')
						->where('po.is_private', '=', 'Yes')
						->where('po.private_code', '!=', '');
				});
		})
			->where('pu_products.products_id', $Product_id)
			->distinct()
			->get();

		if(!$ProdInfo || $ProdInfo->count() == 0)
			return ['StockInfo' => 1111];

		if($cookiee=='Yes' && $opt == "insert")
		{
			$originalquantity = $this->ProductStockInCart($Product_id);

			if( $originalquantity > $qty)
			{
				$productQuantity = $qty + $originalquantity;
			}
			else
			{
				$productQuantity = $originalquantity;
			}
		}

		if($cookiee=='No')
		{
			if($opt=="insert")
			{
				$productQuantity = $this->ProductStockInCart($Product_id) + $qty;
			}
			else
			{
				$productQuantity = $qty;
			}
		}

		if ($OrderType == "Store") {
			$ProductStock = $this->SetProduct($ProdInfo[0], "Store");
			$availableStock =  $ProductStock->store_currentStock;
		} else {
			$ProductStock = $this->SetProduct($ProdInfo[0]);
			$availableStock =  $ProductStock->current_stock - $ProductStock->minimum_stock;
		}

		$StockInfo = ($productQuantity > $availableStock)?'2222':'3333';
		$Details = ['StockInfo' => $StockInfo, 'ProdInfo' => $ProductStock, 'availableStock' => $availableStock];
		return $Details;
	}

	public function ProductCheckInCart($products_id, $qty, $opt = 'insert',$cookiee='No',$giftwrap='No')
	{
		$Cart = Session::get('ShoppingCart.Cart');
		$ProductInCart = 0;
		if(Session::has('ShoppingCart.Cart') && count($Cart) > 0)
		{
			if($qty == 0 )
				$qty = 1 ;
			for($a=0; $a < count($Cart); $a++)
			{
				//if($Cart[$a]['ProductID'] == $products_id && $products_id != 0 && !isset($Cart[$a]['IS_Free_Gift']))
				if($Cart[$a]['ProductID'] == $products_id && $products_id != 0 && !isset($Cart[$a]['IS_Free_Gift']) && !isset($Cart[$a]['Is_Free_Sample']))
				{
					if($opt == 'insert')
					{
						if($cookiee=='Yes')
						{
							if($Cart[$a]['Qty'] > $qty)
							{
								$Cart[$a]['Qty'] += $qty;
							}
							else
							{
								$Cart[$a]['Qty'] = $qty;
							}
						}
						else
						{
							$Cart[$a]['Qty'] += $qty;
						}
					}
					else
					{
						$Cart[$a]['Qty'] = $qty;
					}

					if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler')
					{
						if(Session::has('sess_useremail') && Session::get('sess_useremail') == 'qqualdev@gmail.com'){
							if($Cart[$a]['Qty'] < 5){
								$DealOfWeek = GetDealOfWeek($Cart[$a]['SKU'],'Weekly','Cart');
								if(count($DealOfWeek) > 0)
								{
									$ProdInfo = DB::table('pu_products as p')
										->join('pu_products_one as po','p.products_id','=','po.products_id')
										->where(function($query){
											$query->orWhere('p.status','=','1');
											$query->OrWhere(function($qry){
												$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');
											});
										})
										->where('p.products_id','=',$Cart[$a]['ProductID'])->get();
										$ProductRs = $this->SetProduct($ProdInfo[0]);
									if(isset($DealOfWeek[$Cart[$a]['SKU']]['deal_price']) && $DealOfWeek[$ProductRs->sku]['deal_price'] < $ProductRs->product_price )
									{
										$Cart[$a]['Price'] = NumberFormat($DealOfWeek[$Cart[$a]['SKU']]['deal_price']);
									} else {
										$Cart[$a]['Price'] = NumberFormat($ProductRs->product_price);
									}
								} else {
										$ProdInfo = DB::table('pu_products as p')
									->join('pu_products_one as po','p.products_id','=','po.products_id')
									->where(function($query){
										$query->orWhere('p.status','=','1');
										$query->OrWhere(function($qry){
											$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');
										});
									})
									->where('p.products_id','=',$Cart[$a]['ProductID'])->get();
									$ProductStock = $this->SetProduct($ProdInfo[0]);
									$per = '';
									$val = '';
									if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
									{
										$specialpricedtl = GetSpecialPricePercentandValue($Cart[$a]['Qty']);
										$perval = explode("#",$specialpricedtl);
										$per = $perval[0];
										$val = $perval[1];
									}

									if(!isset($Cart[$a]['ActualWholesalePrice']))
										$Cart[$a]['ActualWholesalePrice'] = $ProductStock->product_price;

									if($per != '')
									{
										$Cart[$a]['Price'] = NumberFormat($ProductStock->product_price - $ProductStock->product_price*$per/100);
									}
									else
									{
										$Cart[$a]['Price']  = NumberFormat($ProductStock->product_price);
									}

									$Cart[$a]['Markup_Percent'] = $per;
									$Cart[$a]['Markup_Value'] = $val;
								}
							} else {
								$ProdInfo = DB::table('pu_products as p')
								->join('pu_products_one as po','p.products_id','=','po.products_id')
								->where(function($query){
									$query->orWhere('p.status','=','1');
									$query->OrWhere(function($qry){
										$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');
									});
								})
								->where('p.products_id','=',$Cart[$a]['ProductID'])->get();
								$ProductStock = $this->SetProduct($ProdInfo[0]);
								$per = '';
								$val = '';
								if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
								{
									$specialpricedtl = GetSpecialPricePercentandValue($Cart[$a]['Qty']);
									$perval = explode("#",$specialpricedtl);
									$per = $perval[0];
									$val = $perval[1];
								}

								if(!isset($Cart[$a]['ActualWholesalePrice']))
									$Cart[$a]['ActualWholesalePrice'] = $ProductStock->product_price;

								$DealOfWeek = GetDealOfWeek($Cart[$a]['SKU'],'Weekly','Cart');
								if(count($DealOfWeek) > 0){
									$ProductStock->product_price = $DealOfWeek[$Cart[$a]['SKU']]['deal_price'];
								}

								if($per != '')
								{
									$Cart[$a]['Price'] = NumberFormat($ProductStock->product_price - $ProductStock->product_price*$per/100);
								}
								else
								{
									$Cart[$a]['Price']  = NumberFormat($ProductStock->product_price);
								}

								$Cart[$a]['Markup_Percent'] = $per;
								$Cart[$a]['Markup_Value'] = $val;
							}
						} else {
							$ProdInfo = DB::table('pu_products as p')
							->join('pu_products_one as po','p.products_id','=','po.products_id')
							->where(function($query){
								$query->orWhere('p.status','=','1');
								$query->OrWhere(function($qry){
									$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');
								});
							})
							->where('p.products_id','=',$Cart[$a]['ProductID'])->get();
							$ProductStock = $this->SetProduct($ProdInfo[0]);
							$per = '';
							$val = '';
							if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
							{
								$specialpricedtl = GetSpecialPricePercentandValue($Cart[$a]['Qty']);
								$perval = explode("#",$specialpricedtl);
								$per = $perval[0];
								$val = $perval[1];
							}

							if(!isset($Cart[$a]['ActualWholesalePrice']))
								$Cart[$a]['ActualWholesalePrice'] = $ProductStock->product_price;

							if($per != '')
							{
								$Cart[$a]['Price'] = NumberFormat($ProductStock->product_price - $ProductStock->product_price*$per/100);
							}
							else
							{
								$Cart[$a]['Price']  = NumberFormat($ProductStock->product_price);
							}

							$Cart[$a]['Markup_Percent'] = $per;
							$Cart[$a]['Markup_Value'] = $val;
						}

					}

					$Cart[$a]['TotPrice'] = NumberFormat($Cart[$a]['Qty'] * $Cart[$a]['Price']);
					$Cart[$a]['gift_wrap'] = $giftwrap;
					$ProductInCart = 1;
				}
			}
		}
		if($ProductInCart == 1)
		{
			Session::put('ShoppingCart.Cart',$Cart);
			return true;
		} else {
			return false;
		}
	}

	public function ProductStockInCart($Product_id)
	{
		$cart_qty=0;
		if(Session::has('ShoppingCart.Cart'))
		{
			$count = count(Session::get('ShoppingCart.Cart'));
			$Cart = Session::get('ShoppingCart.Cart');
			for($a=0; $a < $count; $a++)
			{
				if($Cart[$a]['ProductID'] == $Product_id && $Product_id != 0)
				{
					$cart_qty +=$Cart[$a]['Qty'];
				}
			}
		}
		return $cart_qty;
	}

	public function setGiftCertiTotalUpdate($val, $qty)
	{
		if(Session::has('ShoppingCart.GiftCertiTotal'))
		{
			$GiftCertiTotal = Session::get("GiftCertiTotal") + $val;
			$GiftCertiCount = Session::get("GiftCertiCount") + $qty;
		} else {
			$GiftCertiTotal = $val;
			$GiftCertiCount = $qty;
		}
		Session::put("ShoppingCart.GiftCertiTotal",$GiftCertiTotal);
		Session::put("ShoppingCart.GiftCertiCount",$GiftCertiCount);
	}

	public function ApplyCouponDiscount($couponCode, $customer_id = NULL)
	{
		/*if(Session::has('ShoppingCart.PromoCoupon'))
			Session::forget('ShoppingCart.PromoCoupon');
		if(Session::has('Niche_Fragrances_Membership'))
			Session::forget('Niche_Fragrances_Membership');
		*/

		$log['couponCode'] = $couponCode;
		addLog('ApplyCouponDiscountStart',$log);

		$error = 0;
		$CouponDiscount  = 0.0 ;
		$couponCode 	 = trim($couponCode);
		$customer_id 	 = (int)$customer_id;
		$FreeShippingFlg = false;
		$CouponDiscountItemWise = 0;
		$CartInfo = array();
		if(Session::get('ShoppingCart.Cart') && is_array(Session::get('ShoppingCart.Cart')))
		{
			$CartInfo 		 = Session::get('ShoppingCart.Cart');
		}
		$TotalItems 	 = count($CartInfo);
		$is_loggedin = 0;

		$CouponQry = Coupon::where('coupon_number','=',$couponCode)
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'));
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(Auth::user())
		if($normaluser)
		{
			$is_loggedin = 1;
			/*if(Auth::user()->eusertype !='')
				$CouponQry->where('coupon_user_type','=',Auth::user()->eusertype);
			else
				$CouponQry->where('coupon_user_type','=','Retailer');*/

			if($normaluser->eusertype !='')
				$CouponQry->where('coupon_user_type','=',$normaluser->eusertype);
			else
				$CouponQry->where('coupon_user_type','=','Retailer');
		} else {
			//$CouponQry->where('coupon_user_type','=','Retailer');
			//added during phoneorder in admin 15022024
			if(Session::has('isPhoneOrder') && Session::has('eusertype') && Session::get('eusertype')!=''){
				$eusertype = Session::get('eusertype');
				$CouponQry->where('coupon_user_type','=',$eusertype);
			} else {
				$CouponQry->where('coupon_user_type','=','Retailer');
			}
		}

		$CouponRS = $CouponQry->get();

		$log['CouponRS'] = json_encode($couponCode);
		addLog('ApplyCouponRecordSet',$log);

		//echo "<pre>"; print_r($CouponRS); exit;

		$CouponCode = $this->GetAllCoupons('CouponCode');
		/*
        if($CouponRS[0]['source']=="Website" && $CouponCode!='')
		{
			$msg = "Coupon Code already applied.";
			$error = 1;
			$Info = ['error' => $error, 'message' => $msg];
            if(Session::has('ShoppingCart.PromoCoupon'))
                Session::forget('ShoppingCart.PromoCoupon');
            if(Session::has('Niche_Fragrances_Membership'))
                Session::forget('Niche_Fragrances_Membership');
			return $Info;
		}
		*/

		$UnsetCart = 0;

		if($CouponRS && $CouponRS->count() > 0 )
		{
			if($CouponRS[0]["source"]!= "Yotpo")
			{
				foreach($CartInfo as $i => $Cart)
				{
					if(isset($Cart["FreeGiftCoupon"]) && $Cart["FreeGiftCoupon"]=="Yes")
					{
						Session::forget('ShoppingCart.Cart.'.$i);
						$UnsetCart = 1;
					}

				}
			}
		}
		if($UnsetCart == 1)
		{
			$NewShoppingCart = array_values(Session::get('ShoppingCart.Cart'));
			Session::put('ShoppingCart.Cart',$NewShoppingCart);
		}

        $CartInfo 		 = Session::get('ShoppingCart.Cart');
		$TotalItems 	 = count($CartInfo);

		$IsDeal="Yes";
		$TotalDealPrice = 0;

        $CartItem = "No";
		if($CouponRS && $CouponRS->count() > 0 )
		{
			//Exclude Pocket Perfume
			$pocketPerfumeCategory = $this->getPocketPerfumeCategory();
			//Exclude Pocket Perfume

			foreach($CartInfo as $i => $Cart)
			{
				$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$Cart);

				if($IsGiftCertificateItem == "No")
				{
					$CartItem = "Yes";
				}

				if((isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="No") || (isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="Yes" && ($Cart["DealDiscountFlag"]=="Yes" ||  (isset($CouponRS[0]["dealdiscount_flag"]) && $CouponRS[0]["dealdiscount_flag"]=="Yes"))))
					$IsDeal = "No";

				if((isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="Yes") && ($Cart["DealDiscountFlag"]=="No" || $Cart["DealDiscountFlag"]=='') && $CouponRS[0]["dealdiscount_flag"]=="No")
					$TotalDealPrice =  $TotalDealPrice  + $Cart["TotPrice"];

			}
			if($CartItem=="No")
			{
				if(Session::has('ShoppingCart.PromoCoupon'))
					Session::forget('ShoppingCart.PromoCoupon');
				if(Session::has('Niche_Fragrances_Membership'))
					Session::forget('Niche_Fragrances_Membership');

				if(Session::has('ShoppingCart.YotpoRewardCode'))
					Session::forget('ShoppingCart.YotpoRewardCode');

				if(Session::has('ShoppingCart.YotpoRewardDiscount'))
					Session::forget('ShoppingCart.YotpoRewardDiscount');

				$CouponDiscount = 0;
				$couponCode='';
				$msg = "Coupon code does not apply to the item you have in your bag.";
				$error = 1;
				$Info = ['error' => $error, 'message' => $msg];
				addLog('ApplyCouponDiscount',$Info);
				return $Info;
			}
			if($IsDeal == "Yes")
			{
				if(Session::has('ShoppingCart.PromoCoupon'))
					Session::forget('ShoppingCart.PromoCoupon');
				if(Session::has('Niche_Fragrances_Membership'))
					Session::forget('Niche_Fragrances_Membership');
				$CouponDiscount = 0;
				$couponCode='';
				$msg = "Coupon code does not apply to the item you have in your bag.";
				$error = 1;
				$Info = ['error' => $error, 'message' => $msg];
				addLog('ApplyCouponDiscount',$Info);
				return $Info;
			}
			if(trim($couponCode) == '' )
				$CouponDiscount = 0;

			if($CouponRS && $CouponRS->count() > 0)
			{

				if($CouponRS[0]['type'] == '1' )
				{
				Session::put('ShoppingCart.CountShipTax',$CouponRS[0]['count_ship_tax']);
				Session::put('ShoppingCart.CouponPercentage',$CouponRS[0]['discount']);

				}

				if($CouponRS[0]["autodiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.AutoDiscount',0.0);
					Session::put('ShoppingCart.AutoDiscountFlag', '');
				}
				if($CouponRS[0]["bogodiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.DogoDiscount', 0.0);
					Session::put('ShoppingCart.BogoDiscountFlag','');
				}
				if($CouponRS[0]["quantitydiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.QuantityDiscount', 0.0);
					Session::put('ShoppingCart.QuantityDiscountFlag','');
				}

				if($CouponRS && $CouponRS->count() <= 0)
					Session::put('ShoppingCart.PromoCoupon.CouponCode','');

				if(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') == '' && $CouponRS[0]["allow_free_gift_product"] == "Yes" && $CouponRS[0]["free_gift_product_value"] != '')
				{
					$this->RemoveFreeGiftValueProduct($CouponRS[0]["free_gift_product_value"]);
					$this->RemoveFreeGiftValueProduct($CouponRS[0]["freegift_product_sku"]);
				}
				if($CouponRS[0]['source']=="Yotpo")
				{
					$this->getAllDiscountBlank("Reward");
				}
				else
				{
				    $this->getAllDiscountBlank("Coupon");
				}

			}

			$TotalExcludePrice = 0;
			$ExcludeSKUListArr = [];
			if($TotalItems > 0 && $CouponRS && $CouponRS->count() > 0 && trim($CouponRS[0]["exclude_sku"])!='')
			{
				$ExcludeSKUListArr = [];
				$ExcludeSKUListArr  = explode(",",$CouponRS[0]["exclude_sku"]);
				$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
				$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');

				foreach($CartInfo as $i => $Cart)
				{
					if(in_array($Cart["SKU"],$ExcludeSKUListArr))
						$TotalExcludePrice =  $TotalExcludePrice  + $Cart["TotPrice"];
				}
			}

			//Exclude Pocket Perfume
			if($TotalItems > 0 && $CouponRS && $CouponRS->count() > 0 && trim($CouponRS[0]["exclude_pocketperfume"]) =='Yes')
			{
				foreach($CartInfo as $i => $Cart)
				{
					if(isset($Cart['CategoryID']) && in_array($Cart['CategoryID'], $pocketPerfumeCategory))
					{
						$ExcludeSKUListArr[] = $Cart['SKU'];
						$TotalExcludePrice =  $TotalExcludePrice  + $Cart["TotPrice"];
						continue;
					}
				}
			}
			//Exclude Pocket Perfume

			$GiftCertiTotal = 0;
			if(Session::has('ShoppingCart.GiftCertiTotal'))
				$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
			$SubTotal = Session::get('ShoppingCart.SubTotal');
			$subTotal = NumberFormat($SubTotal - $GiftCertiTotal - $TotalDealPrice - $TotalExcludePrice);
			$shippingCharge = $this->GetShippingCharge();

			$gc_certi_total = 0;
			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_gc_purchase'] == '0' && Session::has('ShoppingCart.GiftCertiTotal'))
				$gc_certi_total = Session::get('ShoppingCart.GiftCertiTotal');

			$SubTotal = Session::get('ShoppingCart.SubTotal');
			$GrandTotal = $SubTotal - $TotalDealPrice - $TotalExcludePrice;
			$GrandTotalSale = $SubTotal - $TotalDealPrice - $TotalExcludePrice;

			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_ship_tax'] == '1')
			{
				$TaxValue = $this->GetAllCharges('TaxValue');
				$GrandTotal = ($GrandTotal - $gc_certi_total) + $shippingCharge + $TaxValue;
				$GrandTotalSale = ($GrandTotalSale  - $gc_certi_total) + $shippingCharge + $TaxValue;
			}else{
				$GrandTotal = $GrandTotal - $gc_certi_total;
				$GrandTotalSale = $GrandTotalSale - $gc_certi_total;
			}

			Session::put("count_ship_tax",0);
			Session::put("coupon_per",0);

			if (!empty($CouponRS) && $CouponRS->count() > 0 && in_array($CouponRS[0]['is_once'], ['1', '2']) && !Session::has('sess_useremail'))
            {
				$msg = "Please log in to your account to apply this coupon.";
				$Info = ['error' => 1, 'message' => $msg];
				addLog('ApplyCouponDiscount',$Info);
				return $Info;
			}

			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '1')
			{ // only one time use
				if($CouponRS[0]['source']=="Yotpo")
				{
				$sqlorder = Order::where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))->where('status','!=','Declined')->where('pay_status','=','Paid')->get();
				}
				else
				{
				$sqlorder = Order::where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->where('status','!=','Declined')->where('pay_status','=','Paid')->get();
				}
				if($sqlorder && $sqlorder->count() > 0 )
					$switchCase = '';
				else
					$switchCase = $CouponRS[0]['orders'];
			}else if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '2' &&  Session::get('sess_icustomerid') != 0){
				// Once per customer

				if($CouponRS[0]['source']=="Yotpo")
				{
				$sqlorder = Order::where('customer_id','=',Session::get('sess_icustomerid'))->where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))->where('status','!=','Declined')->where('pay_status','=','Paid')->get();
				}
				else
				{
				$sqlorder = Order::where('customer_id','=',Session::get('sess_icustomerid'))->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->where('status','!=','Declined')->where('pay_status','=','Paid')->get();
				}
				if($sqlorder && $sqlorder->count() > 0 )
				{

					$switchCase = '';
				}else{
					if(Session::get('etype') == "G" && isset($Billing['email']) && $Billing['email']!='')
					{
						$Billing  = Session::get('ShoppingCart.BillingAddress');

						if($CouponRS[0]['source']=="Yotpo")
						{
						$sqlorder = Order::select('orders_id')->where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))
									->where('bill_email','=',$Billing['email'])->get();
						}
						else
						{

						$sqlorder = Order::select('orders_id')->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])
									->where('bill_email','=',$Billing['email'])->get();
						}

						if($sqlorder && $sqlorder->count() > 0 )
							$switchCase = '';
						else
							$switchCase = $CouponRS[0]['orders'];
					}else{
						$switchCase = $CouponRS[0]['orders'];
					}
				}
			}else{
				$switchCase = $CouponRS[0]['orders'];
			}

			switch ($switchCase)
			{
				## On Order Amount
				case '0' :
					$tempsubTotal = $GrandTotal;
					$tempSaleTotal = $GrandTotalSale;
					$log['tempsubTotal'] = $tempsubTotal;
					$log['tempSaleTotal'] = $tempSaleTotal;
					// Added code on 17 July 2012
					if($CouponRS[0]['count_ship_tax']=='1'){
						// Added by CK on 7th Feb, 2012 for Sale Item Coupon
						$tempSaleTotal=$tempSaleTotal;
						Session::put("count_ship_tax",1);
					}else{
						$tempsubTotal = $SubTotal;
						$tempSaleTotal = Session::get('ShoppingCart.SubTotal') - $gc_certi_total - $TotalDealPrice - $TotalExcludePrice;
						$tempSaleTotal = $tempSaleTotal;
					}

					if($CouponRS[0]['discount']<=0 && isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='')
					{

						$CouponDiscount = 0;
					}
					if($tempSaleTotal >= $CouponRS[0]['order_amount'] && $tempSaleTotal >= $CouponRS[0]["minimum_order_amount"])
					{
						if($CouponRS[0]['type'] == 1 )
							$CouponDiscount = ( $tempSaleTotal * ($CouponRS[0]['discount']/100) );
						else
							$CouponDiscount = $CouponRS[0]['discount'];
					}
					else
					{

						$msg = "Coupon code does not apply to the item you have in your bag.";
						$error = 1;
						$log['msg'] = $msg;
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					/*
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}*/
					//dd($CouponRS[0]);
					if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"] != '' && $error!=1)
					{
							$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);
					}
					$TotalAmount = 0;

					$tempCart  = Session::get('ShoppingCart.Cart');

						for ($a=0; $a<count($tempCart); $a++)
						{
							if(($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'],$ExcludeSKUListArr))
							{

								$TotalAmount =  $TotalAmount + $tempCart[$a]['TotPrice'];
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * ($CouponRS[0]['discount']/100));

									if($CouponRS[0]['source']=="Yotpo")
									{
										Session::put('ShoppingCart.Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountItemWise);
									}
									else
									{
										Session::put('ShoppingCart.Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountItemWise);
									}
								}

							}
						}

					if($CouponRS[0]['type'] == 0 && $TotalAmount > 0)
					{
						$tempCart  = Session::get('ShoppingCart.Cart');

						for ($a=0; $a<count($tempCart); $a++)
						{
							if(($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'],$ExcludeSKUListArr))
							{
								$CouponDiscountItemWise = (($CouponRS[0]['discount']*100)/$TotalAmount);
								$CouponDiscountCal = (($tempCart[$a]['TotPrice']  * $CouponDiscountItemWise)/100);

								if($CouponRS[0]['source']=="Yotpo")
								{

								Session::put('ShoppingCart.Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountCal);

								}
								else
								{
								Session::put('ShoppingCart.Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountCal);
								}

							}
						}
					}
					addLog('ApplyCouponOrderAmount',$log);
					break;
				## On Product SKU
				case '1' :
					$CouponSKU = trim($CouponRS[0]['sku']);
					########### For Multiple SKU ###############
					$arr_CouponSKU  = explode(",",$CouponSKU);
					$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
					$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');

					$Matched_Item_Total = 0;
					$IS_Any_Matched 	= 0;
					$TotalAmount 		= 0;
					if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
					{
						$tempCart  = Session::get('ShoppingCart.Cart');
						for ($a=0; $a<count($tempCart); $a++)
						{
							if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								$IS_Any_Matched = $IS_Any_Matched+1;
								$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
								if($CouponRS[0]['type'] == 1 )
								{
									$CouponDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * ($CouponRS[0]['discount']/100));
									if($CouponRS[0]['source']=="Yotpo")
									{
										Session::put('ShoppingCart.Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountItemWise);
									}
									else
									{
										Session::put('ShoppingCart.Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountItemWise);
									}
									$Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
								}
							}
						}
						if($CouponRS[0]['type'] == 0 && $TotalAmount > 0)
						{
							for ($a=0; $a<count($tempCart); $a++)
							{
								if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{

										$CouponDiscountItemWise = (($CouponRS[0]['discount']*100)/$TotalAmount);
										$CouponDiscountCal = (($tempCart[$a]['TotPrice']  * $CouponDiscountItemWise)/100);
										$Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;//27012026
										if($CouponRS[0]['source']=="Yotpo")
										{
											Session::put('ShoppingCart.Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountCal);
										}
										else
										{
											Session::put('ShoppingCart.Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountCal);
										}
								}
							}
						}
					}
					if($IS_Any_Matched >0 )
					{
						if($CouponRS[0]["count_ship_tax"]=='1')
						{
							if($CouponRS[0]['type'] == 1 )
							{
								if(!empty($this->GetAllCharges('TaxValue')))
									$Matched_Item_Total = $Matched_Item_Total + $this->GetAllCharges('TaxValue');
								if($shippingCharge > 0)
									$Matched_Item_Total = $Matched_Item_Total + $shippingCharge;
							}
						}
						if($CouponRS[0]["count_gc_purchase"]=='1')
						{
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
							$Matched_Item_Total = $Matched_Item_Total + $GiftCertiTotal;
						}
						if($CouponRS[0]['discount']<=0 && isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='')
						{
							$CouponDiscount = 0;
						}
						else if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
							}
						}
						elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
							}
						}
						else
						{
							$CouponDiscount = 0;
							$msg = "Coupon code is invalid or does not exists.";
							$error = 1;
						}
					}
					else
					{
						$msg = "Coupon code is invalid or does not exists.";
						$error = 1;
					}
					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.PromoCoupon.FreeShipping', 'Yes');
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					/*
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
					{

						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}*/
					if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='' && $error!=1 && $TotalAmount >= $CouponRS[0]['minimum_order_amount'])
					{

						$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);
					}
					addLog('ApplyCouponProductSKU');
					####################################################
					break;
				case '7' :
						$CouponSKU = trim($CouponRS[0]['sku']);

						$CouponSKU = unserialize($CouponSKU);
						$arr_CouponSKU = array();
						$arr_CouponDiscount = array();
						for($d=0;$d<count($CouponSKU);$d++)
						{
							if($CouponSKU[$d]["sku"]!='')
							{
								$arr_CouponSKU[] = $CouponSKU[$d]["sku"];
								$arr_CouponDiscount[$CouponSKU[$d]["sku"]] = $CouponSKU[$d]["discount"];
							}
						}
						########### For Multiple SKU ###############
						$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
						$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
					//	echo "<pre>"; print_r($arr_CouponSKU); exit;

						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;
						$CouponDiscountCalculate	= 0;
						$CouponDiscount = 0;
						$TotalAmount =0;
						if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
						{
							$tempCart  = Session::get('ShoppingCart.Cart');
							for ($a=0; $a<count($tempCart); $a++)
							{
								if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$Current_Item_Total  = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									$Matched_Item_Total = $Current_Item_Total + $Matched_Item_Total;

									$CouponDiscountCalculate = ($Current_Item_Total *($arr_CouponDiscount[$tempCart[$a]['SKU']] /100));
									$CouponDiscountCalculate = NumberFormat($CouponDiscountCalculate);
									$CouponDiscount = $CouponDiscount + $CouponDiscountCalculate;
									//item wise discount for cj
									$tempCart[$a]['ItemWiseCouponDiscount_CJ'] = $CouponDiscountCalculate;
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									//item wise discount for cj
									if($CouponRS[0]['source']=="Yotpo")
									{
										$tempCart[$a]['RewardItemWiseDiscout']  = $CouponDiscountCalculate;
									}
									else
									{
										$tempCart[$a]['CouponDisItemWiseDiscout']  = $CouponDiscountCalculate;
									}
								}
							}
							Session::put('ShoppingCart.Cart',$tempCart);
						}

						if($IS_Any_Matched >0 )
						{
							if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
							{
								$CouponDiscount = $CouponDiscount;
							}
							elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
							{
								$CouponDiscount = $CouponDiscount;
							}
							else
							{
								$CouponDiscount = 0;
								$msg = "Coupon code is invalid or does not exists.";
								$error = 1;
							}
						}
						else
						{
							$msg = "Coupon code is invalid or does not exists.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						/*
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						*/
						if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= ''  && $error!=1 && $TotalAmount >= $CouponRS[0]['minimum_order_amount'])
						{
							$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);
						}
						addLog('ApplyCouponCase7');
						####################################################
						break;
				## On Product Brand
				case '2' :
						break;
				## On Product Category
				case '3' :
						$CouponCatID    	= trim($CouponRS[0]['sku']); // Category IDS
						$arr_CouponCatID    = explode(",",$CouponCatID);

						$CouponDiscount = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_CouponCatID)->get();
						$arr_active_CatID = array();
						if($Res_active_CatID && $Res_active_CatID->count() > 0)
						{
							for($h=0;$h<$Res_active_CatID->count();$h++)
							{
								$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
							}
						}

						if(count($arr_active_CatID) > 0 )
						{
							$log['active_cat_id'] = json_encode($arr_active_CatID);
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}

							$ProdIds = ProductsCategory::select('products_id')->distinct()
										->whereIn('category_id',$arr_active_CatID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$cat_prod_id  = array();
							for ($a=0; $a < $ProdIds->count(); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$log['cat_prod_id'] = json_encode($cat_prod_id);
							$TotalAmount = 0;
							for ($a=0; $a<count($tempCart); $a++)
							{
								if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($CouponRS[0]['type'] == 1 )
									{
										$CouponDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * ($CouponRS[0]['discount']/100));
										if($CouponRS[0]['source']=="Yotpo")
										{
											Session::put('ShoppingCart.Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountItemWise);

										}
										else
										{
											Session::put('ShoppingCart.Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountItemWise);
										}
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $CouponDiscount;
									}
									$found_cat = true; // make true if category match
									$log['found_cat'] = json_encode($found_cat);
								}
							}
							if($CouponRS[0]['type'] == 0 && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{
									if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
									{
										$CouponDiscountItemWise = (($CouponRS[0]['discount']*100)/$TotalAmount);
										$CouponDiscountCal = (($tempCart[$a]['TotPrice']  * $CouponDiscountItemWise)/100);
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $CouponDiscount;//27012026
											if($CouponRS[0]['source']=="Yotpo")
											{
											 Session::put('ShoppingCart.Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountCal);
											}
											else
											{
											 Session::put('ShoppingCart.Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountCal);
											}

									}
								}
							}
						}
						else
						{
							$CouponDiscount = 0;
						}

						if($found_cat==true)
						{

							if($CouponRS[0]["count_ship_tax"]=='1')
							{
								if($CouponRS[0]['type'] == 1 )
								{
									if(!empty($this->GetAllCharges('TaxValue')))
										$CouponDiscount  = $CouponDiscount  + $this->GetAllCharges('TaxValue');
									if($shippingCharge > 0)
										$CouponDiscount  = $CouponDiscount  + $shippingCharge;
								}
						  }
						  if($CouponRS[0]["count_gc_purchase"]=='1')
						  {
								$GiftCertiTotal = 0;
								if(Session::has('ShoppingCart.GiftCertiTotal'))
									$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
								$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
						  }
						  if($CouponRS[0]['discount']<=0 && isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='')
						  {
							$CouponDiscount = 0;
						  }
						  elseif($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						  {
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
						  }
						  elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
						  {
							  if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
						  }
						  else
						  {
							  $CouponDiscount = 0;
							  $msg = "Coupon code is invalid or does not exists.";
							  $error = 1;
						  }
						}
						if($found_cat==false)
						{
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						/*
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						*/
						if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='' && $found_cat==true && $TotalAmount >= $CouponRS[0]['minimum_order_amount'])
						{
								$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);
						}
						addLog('ApplyDiscountProductCategory',$log);
						break;

				case '5' :
							break;

				## On Product Brand
				case '6' :
						$CouponBrandID    	= trim($CouponRS[0]['sku']); // Brand IDS
						$arr_CouponBrandID  = explode(",",$CouponBrandID);

						$CouponDiscount = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')
												->whereIn('imanufactureid',$arr_CouponBrandID)->get();
						$arr_active_BrandID = array();
						for($h=0;$h<count($Res_active_BrandID);$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}
						$log['arr_active_BrandID'] = json_encode($arr_active_BrandID);
						if(count($arr_active_BrandID) > 0 )
						{
							$log['arr_active_BrandID'] = json_encode($arr_active_BrandID);
							## Get Cart Prod ID
							$tempCart = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								if(isset($tempCart[$a]['IS_Free_Gift']) && $tempCart[$a]['IS_Free_Gift']=="Yes")
								{
									continue;
								}

								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}

							$ProdIds = Products::select('products_id')->distinct()
										->whereIn('imanufactureid',$arr_active_BrandID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$log['brand_prod_id'] = json_encode($brand_prod_id);
							$TotalAmount = 0;

							for ($a=0; $a<count($tempCart); $a++)
							{
								if(isset($tempCart[$a]['IS_Free_Gift']) && $tempCart[$a]['IS_Free_Gift']=="Yes")
								{
									continue;
								}
								if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($CouponRS[0]['type'] == 1 )
									{
										$CouponDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * ($CouponRS[0]['discount']/100));
										if($CouponRS[0]['source']=="Yotpo")
										{
											if(empty(Session::get('ShoppingCart.YotpoRewardDiscount')) || Session::get('ShoppingCart.YotpoRewardDiscount') <= 0)
											{
												Session::put('ShoppingCart.Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountItemWise);
											}
										}
										else
										{
											Session::put('ShoppingCart.Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountItemWise);
										}
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+$CouponDiscount;
									}
									else
									{
										$CouponDiscount = $CouponRS[0]['discount']  ;
									}
									$found_brand = true; // make true if category match
								}
							}
							if($CouponRS[0]['type'] == 0 && $TotalAmount > 0)
							{

								for ($a=0; $a<count($tempCart); $a++)
								{
									if(isset($tempCart[$a]['IS_Free_Gift']) && $tempCart[$a]['IS_Free_Gift']=="Yes")
									{
										continue;
									}

									if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
									{

										$CouponDiscountItemWise = (($CouponRS[0]['discount']*100)/$TotalAmount);
										$CouponDiscountCal = (($tempCart[$a]['TotPrice']  * $CouponDiscountItemWise)/100);
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+$CouponDiscount;//27012026
										if($CouponRS[0]['source']=="Yotpo")
										{
										Session::put('ShoppingCart.Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountCal);
										}
										else
										{
										Session::put('ShoppingCart.Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountCal);
										}

									}
								}

							}
						}
						else
						{
							$CouponDiscount = 0;
						}

						if($found_brand==true)
						{
							$log['found_brand'] = json_encode($found_brand);
						  if($CouponRS[0]["count_ship_tax"]=='1')
						  {
							if($CouponRS[0]['type'] == 1 )
							{
								if(!empty($this->GetAllCharges('TaxValue')))
									$CouponDiscount  = $CouponDiscount  + $this->GetAllCharges('TaxValue');
								if($shippingCharge > 0)
									$CouponDiscount  = $CouponDiscount  + $shippingCharge;
							}
						  }
						  if($CouponRS[0]["count_gc_purchase"]=='1')
						  {
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
							$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
						  }

						    if($CouponRS[0]['discount']<=0 && isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='')
							{
							$CouponDiscount = 0;
						    }
							elseif($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
							{
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
							}
							elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
							{
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
							}
							else
							{
							  $CouponDiscount = 0;
							  $msg = "Coupon code is invalid or does not exists.";
							  $error = 1;
							}

						}

						if($found_brand==false)
						{
							$log['found_brand'] = json_encode($found_brand);
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$log['msg'] = $msg;
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
							$log['FreeShippingFlg'] = json_encode($FreeShippingFlg);
						}
						if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '' && $found_brand==true && $TotalAmount >= $CouponRS[0]['minimum_order_amount'])
						{
						    $this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);
						}
						addLog('ApplyDiscountProductBrand',$log);
						break;

				## For Free Shipping
				case '4' :
						$CouponDiscount = 0;
						$Total_Item_count_val  = Session::get('ShoppingCart.TotalItemInCart');

						if($CouponRS[0]['minimum_order_amount'] == 0.00 ||  $CouponRS[0]['minimum_order_amount'] == 0)
						{
							if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								/*
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}*/
								if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '')
								{
										$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);

								}
								$FreeShippingFlg = true;
							}
							else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

									if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
									{
										Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
										Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
										Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
										$FreeShippingFlg = true;
									}
									/*
									if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
									{
										$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
									}*/
									if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '')
									{
											$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);

									}
									$FreeShippingFlg = true;
							}
							else
							{
								$FreeShippingFlg = false;
							}
					   }

					  elseif(Session::get('ShoppingCart.SubTotal') >= $CouponRS[0]['minimum_order_amount'])
						{
							if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								/*
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}*/
								if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '')
								{
									$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);
								}
								$FreeShippingFlg = true;
							}
							else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								/*
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}*/
								if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '')
								{
									$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);
								}
								$FreeShippingFlg = true;
							}
							else
							{
								$FreeShippingFlg = false;
							}
					   }
					   else
					   {
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
							$FreeShippingFlg = false;
					   }
					   addLog('ApplyDiscountFreeShipping',$log);
					break;
				default :
						$CouponDiscount = 0;
						$couponCode='';
						$msg = "Coupon code does not apply to the item you have in your bag.";
						$error = 1;
						break;
			}
			if($FreeShippingFlg==false)
			{
				Session::put('ShoppingCart.PromoCoupon.FreeShipping','No');
			}
			$CouponDiscount = NumberFormat($CouponDiscount);
			$msg='';

			if(isset($CouponRS[0]['source']) && $CouponRS[0]['source'] == "Yotpo" && strtolower(Session::get('eusertype') ?? '') == "retailer" && Session::get('sess_icustomerid') > 0)
			{
				if(!empty(Session::get('ShoppingCart.YotpoRewardDiscount')) && Session::get('ShoppingCart.YotpoRewardDiscount') > 0)
				{
					Session::forget('ShoppingCart.YotpoRewardCode');
					Session::forget('ShoppingCart.YotpoRewardDiscount');
					Session::put('ShoppingCart.YotpoRewardCode',$couponCode);
					Session::put('ShoppingCart.YotpoRewardDiscount',$CouponDiscount);
					//$msg = "Remove existing reward discount.";
					//$error = 1;
				}
				else
				{
					if(isset($CouponDiscount) && $CouponDiscount > 0 && strtolower(Session::get('eusertype') ?? '') == "retailer" && Session::get('sess_icustomerid') > 0)
					{
						Session::put('ShoppingCart.YotpoRewardCode',$couponCode);
						Session::put('ShoppingCart.YotpoRewardDiscount',$CouponDiscount);
						$msg = "Reward discount code applied.";
					}
					else
					{

						Session::forget('ShoppingCart.YotpoRewardCode');
						Session::forget('ShoppingCart.YotpoRewardDiscount');
						$msg = "Reward discount code not found.";
					}
				}
			}
			else
			{
			if($CouponDiscount > 0 or $FreeShippingFlg==true)
			{
				if($CouponRS[0]['source'] != "Yotpo")
				{
					Session::put('ShoppingCart.PromoCoupon.CouponCode',$couponCode);
					Session::put('ShoppingCart.Coupon_Detail_CJ.CouponCode',$couponCode);
					Session::put('ShoppingCart.Coupon_Detail_CJ.orders',$CouponRS[0]['orders']);
				}
			}
			else
			{
				$CartInfoVal 		 = Session::get('ShoppingCart.Cart');
				$FreeGiftCouponVal = "No";
				foreach($CartInfoVal as $i => $Cart)
				{
					if(isset($Cart["FreeGiftCoupon"]) && $Cart["FreeGiftCoupon"]=="Yes")
					{
						$FreeGiftCouponVal = "Yes";
						break;
					}
				}

				if($FreeGiftCouponVal=="Yes")
				{
					Session::put('ShoppingCart.PromoCoupon.CouponCode',$couponCode);
					Session::put('ShoppingCart.Coupon_Detail_CJ.CouponCode',$couponCode);
					Session::put('ShoppingCart.Coupon_Detail_CJ.orders',$CouponRS[0]['orders']);
				}

				if($FreeGiftCouponVal == "No")
				{
					if(Session::has('ShoppingCart.PromoCoupon'))
						Session::forget('ShoppingCart.PromoCoupon');
					if(Session::has('Niche_Fragrances_Membership'))
						Session::forget('Niche_Fragrances_Membership');
				}
				//Session::put('ShoppingCart.PromoCoupon.CouponCode','');
			}

			if($CouponDiscount > 0 && $CouponRS[0]['source'] != "Yotpo")
			{
				Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',$CouponDiscount);
			}
			else
			{
				Session::put('ShoppingCart.PromoCoupon.CouponDiscount',0);
				Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',0);
			}
			if(Session::get('ShoppingCart.PromoCoupon.CouponCode') !='' && $CouponRS[0]['source'] != "Yotpo")
			{
				$msg = "Coupon code applied successfully.";
			}
			else
			{
				$error = 1;
				$msg = "Invalid Coupon Code.";
				$log['message'] = $msg;
				addLog('ApplyDiscountInvalidCoupon',$log);
				if($is_loggedin == 0 && $CouponRS[0]['source'] == "Yotpo"){
					$error = 1;
					$msg = "Please login or create an account with Maxaroma to use the Referral Coupon code";
					$log['message'] = $msg;
					addLog('ApplyDiscountInvalidYotpoCoupon',$log);
				}
			}
		}
		} else {
			$error = 1;
			$msg = "Invalid Coupon Code.";
			$log['message'] = $msg;
			addLog('ApplyDiscountInvalidCoupon',$log);
		}

		if($CouponRS->count() <= 0)
		{
			if(Session::has('ShoppingCart.PromoCoupon'))
                Session::forget('ShoppingCart.PromoCoupon');
            if(Session::has('Niche_Fragrances_Membership'))
                Session::forget('Niche_Fragrances_Membership');
		}
			$Info = ['error' => $error, 'message' => $msg];
			addLog('ApplyCouponDiscount',$Info);
			return $Info;
	}

	public function ApplyCouponDiscountOLD($couponCode, $customer_id = NULL)
	{
		/*if(Session::has('ShoppingCart.PromoCoupon'))
			Session::forget('ShoppingCart.PromoCoupon');
		if(Session::has('Niche_Fragrances_Membership'))
			Session::forget('Niche_Fragrances_Membership');
		*/

		$error = 0;
		$CouponDiscount  = 0.0 ;
		$couponCode 	 = trim($couponCode);
		$customer_id 	 = (int)$customer_id;
		$FreeShippingFlg = false;
		$CartInfo 		 = Session::get('ShoppingCart.Cart');
		$TotalItems 	 = count($CartInfo);

		$CouponQry = Coupon::where('coupon_number','=',$couponCode)
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'));
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		//if(Auth::user())
		if($normaluser)
		{
			/*if(Auth::user()->eusertype !='')
				$CouponQry->where('coupon_user_type','=',Auth::user()->eusertype);
			else
				$CouponQry->where('coupon_user_type','=','Retailer');*/
			if($normaluser->eusertype !='')
				$CouponQry->where('coupon_user_type','=',$normaluser->eusertype);
			else
				$CouponQry->where('coupon_user_type','=','Retailer');
		} else {
			$CouponQry->where('coupon_user_type','=','Retailer');
		}

		$CouponRS = $CouponQry->get();

		$CouponCode = $this->GetAllCoupons('CouponCode');
		/*
        if($CouponRS[0]['source']=="Website" && $CouponCode!='')
		{
			$msg = "Coupon Code already applied.";
			$error = 1;
			$Info = ['error' => $error, 'message' => $msg];
            if(Session::has('ShoppingCart.PromoCoupon'))
                Session::forget('ShoppingCart.PromoCoupon');
            if(Session::has('Niche_Fragrances_Membership'))
                Session::forget('Niche_Fragrances_Membership');
			return $Info;
		}
		*/
		$IsDeal="Yes";
		$TotalDealPrice = 0;

		if($CouponRS && $CouponRS->count() > 0 )
		{
			foreach($CartInfo as $i => $Cart)
			{
				if((isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="No") || (isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="Yes" && ($Cart["DealDiscountFlag"]=="Yes" ||  (isset($CouponRS[0]["dealdiscount_flag"]) && $CouponRS[0]["dealdiscount_flag"]=="Yes"))))
					$IsDeal = "No";

				if((isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="Yes") && ($Cart["DealDiscountFlag"]=="No" || $Cart["DealDiscountFlag"]=='') && $CouponRS[0]["dealdiscount_flag"]=="No")
					$TotalDealPrice =  $TotalDealPrice  + $Cart["TotPrice"];

			}

			if($IsDeal == "Yes")
			{
				if(Session::has('ShoppingCart.PromoCoupon'))
					Session::forget('ShoppingCart.PromoCoupon');
				if(Session::has('Niche_Fragrances_Membership'))
					Session::forget('Niche_Fragrances_Membership');
				$CouponDiscount = 0;
				$couponCode='';
				$msg = "Coupon code does not apply to the item you have in your bag.";
				$error = 1;
				$Info = ['error' => $error, 'message' => $msg];
				return $Info;
			}
			if(trim($couponCode) == '' )
				$CouponDiscount = 0;

			if($CouponRS && $CouponRS->count() > 0)
			{
				if($CouponRS[0]["autodiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.AutoDiscount',0.0);
					Session::put('ShoppingCart.AutoDiscountFlag', '');
				}
				if($CouponRS[0]["bogodiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.DogoDiscount', 0.0);
					Session::put('ShoppingCart.BogoDiscountFlag','');
				}
				if($CouponRS[0]["quantitydiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.QuantityDiscount', 0.0);
					Session::put('ShoppingCart.QuantityDiscountFlag','');
				}

				if($CouponRS && $CouponRS->count() <= 0)
					Session::put('ShoppingCart.PromoCoupon.CouponCode','');

				if(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') == '' && $CouponRS[0]["allow_free_gift_product"] == "Yes" && $CouponRS[0]["free_gift_product_value"] != '')
					$this->RemoveFreeGiftValueProduct($CouponRS[0]["free_gift_product_value"]);
			}

			$TotalExcludePrice = 0;
			$ExcludeSKUListArr = [];

			if($TotalItems > 0 && $CouponRS && $CouponRS->count() > 0 && trim($CouponRS[0]["exclude_sku"])!='')
			{

				$ExcludeSKUListArr = [];
				$ExcludeSKUListArr  = explode(",",$CouponRS[0]["exclude_sku"]);
				$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
				$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');

				foreach($CartInfo as $i => $Cart)
				{
					if(in_array($Cart["SKU"],$ExcludeSKUListArr))
					{
						$TotalExcludePrice =  $TotalExcludePrice  + $Cart["TotPrice"];

					}
				}
			}

			$GiftCertiTotal = 0;
			if(Session::has('ShoppingCart.GiftCertiTotal'))
				$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
			$SubTotal = Session::get('ShoppingCart.SubTotal');
			$subTotal = NumberFormat($SubTotal - $GiftCertiTotal - $TotalDealPrice - $TotalExcludePrice);
			$shippingCharge = $this->GetShippingCharge();

			$gc_certi_total = 0;
			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_gc_purchase'] == '0' && Session::has('ShoppingCart.GiftCertiTotal'))
				$gc_certi_total = Session::get('ShoppingCart.GiftCertiTotal');

			$SubTotal = Session::get('ShoppingCart.SubTotal');
			$GrandTotal = $SubTotal - $TotalDealPrice - $TotalExcludePrice;
			$GrandTotalSale = $SubTotal - $TotalDealPrice - $TotalExcludePrice;

			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_ship_tax'] == '1')
			{
				$TaxValue = $this->GetAllCharges('TaxValue');
				$GrandTotal = ($GrandTotal - $gc_certi_total) + $shippingCharge + $TaxValue;
				$GrandTotalSale = ($GrandTotalSale  - $gc_certi_total) + $shippingCharge + $TaxValue;
			}else{
				$GrandTotal = $GrandTotal - $gc_certi_total;
				$GrandTotalSale = $GrandTotalSale - $gc_certi_total;
			}

			Session::put("count_ship_tax",0);
			Session::put("coupon_per",0);

			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '1')
			{ // only one time use
				if($CouponRS[0]['source']=="Yotpo")
				{
				$sqlorder = Order::where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))->get();
				}
				else
				{
				$sqlorder = Order::where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->get();
				}
				if($sqlorder && $sqlorder->count() > 0 )
					$switchCase = '';
				else
					$switchCase = $CouponRS[0]['orders'];
			}else if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '2' &&  Session::get('sess_icustomerid') != 0){
				// Once per customer

				if($CouponRS[0]['source']=="Yotpo")
				{
				$sqlorder = Order::where('customer_id','=',Session::get('sess_icustomerid'))->where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))->get();
				}
				else
				{
				$sqlorder = Order::where('customer_id','=',Session::get('sess_icustomerid'))->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->get();
				}
				if($sqlorder && $sqlorder->count() > 0 )
				{

					$switchCase = '';
				}else{
					if(Session::get('etype') == "G" && isset($Billing['email']) && $Billing['email']!='')
					{
						$Billing  = Session::get('ShoppingCart.BillingAddress');

						if($CouponRS[0]['source']=="Yotpo")
						{
						$sqlorder = Order::select('orders_id')->where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))
									->where('bill_email','=',$Billing['email'])->get();
						}
						else
						{

						$sqlorder = Order::select('orders_id')->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])
									->where('bill_email','=',$Billing['email'])->get();
						}

						if($sqlorder && $sqlorder->count() > 0 )
							$switchCase = '';
						else
							$switchCase = $CouponRS[0]['orders'];
					}else{
						$switchCase = $CouponRS[0]['orders'];
					}
				}
			}else{
				$switchCase = $CouponRS[0]['orders'];
			}

			switch ($switchCase)
			{
				## On Order Amount
				case '0' :
					$tempsubTotal = $GrandTotal;
					$tempSaleTotal = $GrandTotalSale;
					// Added code on 17 July 2012
					if($CouponRS[0]['count_ship_tax']=='1'){
						// Added by CK on 7th Feb, 2012 for Sale Item Coupon
						$tempSaleTotal=$tempSaleTotal;
						Session::put("count_ship_tax",1);
					}else{
						$tempsubTotal = $SubTotal;
						$tempSaleTotal = Session::get('ShoppingCart.SubTotal') - $gc_certi_total - $TotalDealPrice - $TotalExcludePrice;
						$tempSaleTotal = $tempSaleTotal;
					}
					if($tempSaleTotal >= $CouponRS[0]['order_amount'])
					{
						if($CouponRS[0]['type'] == 1 )
							$CouponDiscount = ( $tempSaleTotal * ($CouponRS[0]['discount']/100) );
						else
							$CouponDiscount = $CouponRS[0]['discount'];
					}
					else
					{
						$msg = "Coupon code does not apply to the item you have in your bag.";
						$error = 1;
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}

					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					break;
				## On Product SKU
				case '1' :
					$CouponSKU = trim($CouponRS[0]['sku']);
					########### For Multiple SKU ###############
					$arr_CouponSKU  = explode(",",$CouponSKU);
					$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
					$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');

					$Matched_Item_Total = 0;
					$IS_Any_Matched 	= 0;

					if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
					{
						$tempCart  = Session::get('ShoppingCart.Cart');
						for ($a=0; $a<count($tempCart); $a++)
						{
							if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								$IS_Any_Matched = $IS_Any_Matched+1;
								if($CouponRS[0]['type'] == 1 )
								{
									$Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
								}
							}
						}
					}
					if($IS_Any_Matched >0 )
					{
						if($CouponRS[0]["count_ship_tax"]=='1')
						{
							if($CouponRS[0]['type'] == 1 )
							{
								if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
									$Matched_Item_Total = $Matched_Item_Total + Session::get('ShoppingCart.TaxValue');
								if($shippingCharge > 0)
									$Matched_Item_Total = $Matched_Item_Total + $shippingCharge;
							}
						}
						if($CouponRS[0]["count_gc_purchase"]=='1')
						{
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
							$Matched_Item_Total = $Matched_Item_Total + $GiftCertiTotal;
						}

						if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
							}
						}
						elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
							}
						}
						else
						{
							$CouponDiscount = 0;
							$msg = "Coupon code is invalid or does not exists.";
							$error = 1;
						}
					}
					else
					{
						$msg = "Coupon code is invalid or does not exists.";
						$error = 1;
					}
					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.PromoCoupon.FreeShipping', 'Yes');
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
					{

						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					####################################################
					break;
				case '7' :
						$CouponSKU = trim($CouponRS[0]['sku']);

						$CouponSKU = unserialize($CouponSKU);
						$arr_CouponSKU = array();
						$arr_CouponDiscount = array();
						for($d=0;$d<count($CouponSKU);$d++)
						{
							if($CouponSKU[$d]["sku"]!='')
							{
								$arr_CouponSKU[] = $CouponSKU[$d]["sku"];
								$arr_CouponDiscount[$CouponSKU[$d]["sku"]] = $CouponSKU[$d]["discount"];
							}
						}
						########### For Multiple SKU ###############
						$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
						$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
					//	echo "<pre>"; print_r($arr_CouponSKU); exit;

						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;
						$CouponDiscountCalculate	= 0;
						$CouponDiscount = 0;
						if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
						{
							$tempCart  = Session::get('ShoppingCart.Cart');
							for ($a=0; $a<count($tempCart); $a++)
							{
								if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$Current_Item_Total  = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									$Matched_Item_Total = $Current_Item_Total + $Matched_Item_Total;

									$CouponDiscountCalculate = ($Current_Item_Total *($arr_CouponDiscount[$tempCart[$a]['SKU']] /100));
									$CouponDiscountCalculate = NumberFormat($CouponDiscountCalculate);
									$CouponDiscount = $CouponDiscount + $CouponDiscountCalculate;
									//item wise discount for cj
									$tempCart[$a]['ItemWiseCouponDiscount_CJ'] = $CouponDiscountCalculate;
									//item wise discount for cj
								}
							}
							Session::put('ShoppingCart.Cart',$tempCart);
						}

						if($IS_Any_Matched >0 )
						{
							if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
							{
								$CouponDiscount = $CouponDiscount;
							}
							elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
							{
								$CouponDiscount = $CouponDiscount;
							}
							else
							{
								$CouponDiscount = 0;
								$msg = "Coupon code is invalid or does not exists.";
								$error = 1;
							}
						}
						else
						{
							$msg = "Coupon code is invalid or does not exists.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						####################################################
						break;
				## On Product Brand
				case '2' :
						break;
				## On Product Category
				case '3' :
						$CouponCatID    	= trim($CouponRS[0]['sku']); // Category IDS
						$arr_CouponCatID    = explode(",",$CouponCatID);

						$CouponDiscount = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_CouponCatID)->get();
						$arr_active_CatID = array();
						if($Res_active_CatID && $Res_active_CatID->count() > 0)
						{
							for($h=0;$h<$Res_active_CatID->count();$h++)
							{
								$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
							}
						}

						if(count($arr_active_CatID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}

							$ProdIds = ProductsCategory::select('products_id')->distinct()
										->whereIn('category_id',$arr_active_CatID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$cat_prod_id  = array();
							for ($a=0; $a < $ProdIds->count(); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}

							for ($a=0; $a<count($tempCart); $a++)
							{
								if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									if($CouponRS[0]['type'] == 1 )
									{
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $CouponDiscount;
									}
									$found_cat = true; // make true if category match
								}
							}
						}
						else
						{
							$CouponDiscount = 0;
						}
						if($found_cat==true)
						{
							if($CouponRS[0]["count_ship_tax"]=='1')
							{
								if($CouponRS[0]['type'] == 1 )
								{
									if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
										$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
									if($shippingCharge > 0)
										$CouponDiscount  = $CouponDiscount  + $shippingCharge;
								}
						  }
						  if($CouponRS[0]["count_gc_purchase"]=='1')
						  {
								$GiftCertiTotal = 0;
								if(Session::has('ShoppingCart.GiftCertiTotal'))
									$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
								$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
						  }
						  if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						  {
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
						  }
						  elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
						  {
							  if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
						  }
						  else
						  {
							  $CouponDiscount = 0;
							  $msg = "Coupon code is invalid or does not exists.";
							  $error = 1;
						  }
						}
						if($found_cat==false)
						{
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						break;

				case '5' :
							break;

				## On Product Brand
				case '6' :
						$CouponBrandID    	= trim($CouponRS[0]['sku']); // Brand IDS
						$arr_CouponBrandID  = explode(",",$CouponBrandID);

						$CouponDiscount = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')
												->whereIn('imanufactureid',$arr_CouponBrandID)->get();
						$arr_active_BrandID = array();
						for($h=0;$h<count($Res_active_BrandID);$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}

						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}

							$ProdIds = Products::select('products_id')->distinct()
										->whereIn('imanufactureid',$arr_active_BrandID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}

							for ($a=0; $a<count($tempCart); $a++)
							{
								if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									if($CouponRS[0]['type'] == 1 )
									{
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+$CouponDiscount;
									}
									else
									{
										$CouponDiscount = $CouponRS[0]['discount']  ;
									}
									$found_brand = true; // make true if category match
								}
							}
						}
						else
						{
							$CouponDiscount = 0;
						}

						if($found_brand==true)
						{

						  if($CouponRS[0]["count_ship_tax"]=='1')
						  {
							if($CouponRS[0]['type'] == 1 )
							{
								if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
									$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
								if($shippingCharge > 0)
									$CouponDiscount  = $CouponDiscount  + $shippingCharge;
							}
						  }
						  if($CouponRS[0]["count_gc_purchase"]=='1')
						  {
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
							$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
						  }
							if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
							{
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
							}
							elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
							{
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
							}
							else
							{
							  $CouponDiscount = 0;
							  $msg = "Coupon code is invalid or does not exists.";
							  $error = 1;
							}

						}

						if($found_brand==false)
						{
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						break;

				## For Free Shipping
				case '4' :
						$CouponDiscount = 0;
						$Total_Item_count_val  = Session::get('ShoppingCart.TotalItemInCart');

						if($CouponRS[0]['minimum_order_amount'] == 0.00 ||  $CouponRS[0]['minimum_order_amount'] == 0)
						{
							if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}
								$FreeShippingFlg = true;
							}
							else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

									if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
									{
										Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
										Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
										Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
										$FreeShippingFlg = true;
									}
									if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
									{
										$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
									}
									$FreeShippingFlg = true;
							}
							else
							{
								$FreeShippingFlg = false;
							}
					   }

					  elseif(Session::get('ShoppingCart.SubTotal') >= $CouponRS[0]['minimum_order_amount'])
						{
							if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}
								$FreeShippingFlg = true;
							}
							else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}
								$FreeShippingFlg = true;
							}
							else
							{
								$FreeShippingFlg = false;
							}
					   }
					   else
					   {
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
							$FreeShippingFlg = false;
					   }
					break;
				default :
						$CouponDiscount = 0;
						$couponCode='';
						$msg = "Coupon code does not apply to the item you have in your bag.";
						$error = 1;
						break;
			}
			if($FreeShippingFlg==false)
			{
				Session::put('ShoppingCart.PromoCoupon.FreeShipping','No');
			}
			$CouponDiscount = NumberFormat($CouponDiscount);
			$msg='';

			if(isset($CouponRS[0]['source']) && $CouponRS[0]['source'] == "Yotpo" && strtolower(Session::get('eusertype') ?? '') == "retailer" && Session::get('sess_icustomerid') > 0)
			{
				if(!empty(Session::get('ShoppingCart.YotpoRewardDiscount')) && Session::get('ShoppingCart.YotpoRewardDiscount') > 0)
				{
					$msg = "Remove existing reward discount.";
					$error = 1;
				}
				else
				{
					if(isset($CouponDiscount) && $CouponDiscount > 0 && strtolower(Session::get('eusertype') ?? '') == "retailer" && Session::get('sess_icustomerid') > 0)
					{
						Session::put('ShoppingCart.YotpoRewardCode',$couponCode);
						Session::put('ShoppingCart.YotpoRewardDiscount',$CouponDiscount);
						$msg = "Reward discount code applied.";
					}
					else
					{

						Session::forget('ShoppingCart.YotpoRewardCode');
						Session::forget('ShoppingCart.YotpoRewardDiscount');
						$msg = "Reward discount code not found.";
					}
				}
			}
			else
			{
			if($CouponDiscount > 0 or $FreeShippingFlg==true)
			{
				if($CouponRS[0]['source'] != "Yotpo")
				{
				Session::put('ShoppingCart.PromoCoupon.CouponCode',$couponCode);
				Session::put('ShoppingCart.Coupon_Detail_CJ.CouponCode',$couponCode);
				Session::put('ShoppingCart.Coupon_Detail_CJ.orders',$CouponRS[0]['orders']);
				}
			}
			else
			{
                if(Session::has('ShoppingCart.PromoCoupon'))
                    Session::forget('ShoppingCart.PromoCoupon');
                if(Session::has('Niche_Fragrances_Membership'))
                    Session::forget('Niche_Fragrances_Membership');
				//Session::put('ShoppingCart.PromoCoupon.CouponCode','');
			}

			if($CouponDiscount > 0 && $CouponRS[0]['source'] != "Yotpo")
			{
				Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',$CouponDiscount);
			}
			else
			{
				Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',0);
			}
			if(Session::get('ShoppingCart.PromoCoupon.CouponCode') !='' && Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount') > 0 && $CouponRS[0]['source'] != "Yotpo")
			{
				$msg = "Coupon code applied successfully.";
			}
			else
			{
				$error = 1;
				$msg = "Coupon code not found.";
			}
		}
		} else {
			$error = 1;
			$msg = "Coupon code not found.";
		}

		if($CouponRS->count() <= 0)
		{
			if(Session::has('ShoppingCart.PromoCoupon'))
                Session::forget('ShoppingCart.PromoCoupon');
            if(Session::has('Niche_Fragrances_Membership'))
                Session::forget('Niche_Fragrances_Membership');
		}
			$Info = ['error' => $error, 'message' => $msg];
			return $Info;
	}

	public function ApplyCouponDiscountSecond($couponCode, $customer_id = NULL)
	{
		if(Session::get('ShoppingCart.AutoDiscountFlag') == "No" && Session::get('ShoppingCart.AutoDiscountFlag') !='')
		{
			$CouponDiscount = 0;
			$couponCode='';
			$msg = "Coupon code does not apply to the item you have in your bag.";
			Session:flash('CartError',$msg);
			return null;
		}
		if(Session::get('ShoppingCart.QuantityDiscountFlag') == "No" && Session::get('ShoppingCart.QuantityDiscountFlag') !='')
		{
			$CouponDiscount = 0;
			$couponCode='';
			$msg = "Coupon code does not apply to the item you have in your bag.";
			Session::flash('CartError',$msg);
			return null;
		}

		$CouponDiscount  = 0.0 ;
		$couponCode 	 = trim($couponCode);
		$customer_id 	 = (int)$customer_id;
		$FreeShippingFlg = false;
		$CartInfo 		 = Session::get('ShoppingCart.Cart');
		$TotalItems 	 = count($CartInfo);

		$CouponQry = Coupon::where('coupon_number','=',$couponCode)
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'));

		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		//if(Auth::user())
		if($normaluser)
		{
			/*if(Auth::user()->eusertype !='')
				$CouponQry->where('coupon_user_type','=',Auth::user()->eusertype);
			else
				$CouponQry->where('coupon_user_type','=','Retailer');*/

			if($normaluser->eusertype !='')
				$CouponQry->where('coupon_user_type','=',$normaluser->eusertype);
			else
				$CouponQry->where('coupon_user_type','=','Retailer');
		} else {
			$CouponQry->where('coupon_user_type','=','Retailer');
		}

		$CouponRS = $CouponQry->get();
		$IsDeal="Yes";
		$TotalDealPrice = 0;

		foreach($CartInfo as $i => $Cart)
		{
			if((isset($Cart[$i]["IsDealProducts"]) && $Cart[$i]["IsDealProducts"]!="" && ($Cart[$i]["DealDiscountFlag"]=="Yes" ||  $CouponRS[0]["dealdiscount_flag"]=="Yes")))
				$IsDeal = "No";

			if(isset($Cart[$i]["IsDealProducts"])&& $Cart[$i]["IsDealProducts"]=="Yes" && ($Cart[$i]["DealDiscountFlag"]=="No" || $Cart[$i]["DealDiscountFlag"]=='') && $CouponRS[0]["dealdiscount_flag"]=="No")
				$TotalDealPrice =  $TotalDealPrice  + $Cart[$i]["TotPrice"];
		}
		if($IsDeal == "Yes")
		{
			$CouponDiscount = 0;
			$couponCode='';
			$msg = "Coupon code does not apply to the item you have in your bag.";
			return response()->json(array('error' => 1,'Message' => $msg));
		}
		if(trim($couponCode) == '' )
			$CouponDiscount = 0;

		if($CouponRS && $CouponRS->count() <= 0)
			Session::put('ShoppingCart.PromoCoupon.SecondPromoCoupon','');

		if(Session::has('ShoppingCart.PromoCoupon.SecondPromoCoupon') && Session::get('ShoppingCart.PromoCoupon.SecondPromoCoupon') == '' && $CouponRS[0]["allow_free_gift_product"] == "Yes" && $CouponRS[0]["free_gift_product_value"] != '')
			$this->RemoveFreeGiftValueProduct($CouponRS[0]["free_gift_product_value"]);

		$TotalExcludePrice = 0;
		if($TotalItems > 0 && $CouponRS && $CouponRS->count() > 0 && trim($CouponRS[0]["exclude_sku"])!='')
		{
			$ExcludeSKUListArr = array();
			$ExcludeSKUListArr  = explode(",",$CouponRS[0]["exclude_sku"]);
			$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
			$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');

			foreach($CartInfo as $i => $Cart)
			{
				if(in_array($Cart[$i]["SKU"],$ExcludeSKUListArr))
					$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$i]["TotPrice"];
			}
		}
		$GiftCertiTotal = 0;
		if(Session::has('ShoppingCart.GiftCertiTotal'))
			$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$subTotal = $this->NumberFormat($SubTotal - $GiftCertiTotal - $TotalDealPrice - $TotalExcludePrice);
		$shippingCharge = $this->GetShippingCharge();

		$gc_certi_total = 0;
		if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_gc_purchase'] == '0' && Session::has('ShoppingCart.GiftCertiTotal'))
			$gc_certi_total = Session::get('ShoppingCart.GiftCertiTotal');

		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$GrandTotal = $SubTotal - $TotalDealPrice - $TotalExcludePrice;
		$GrandTotalSale = $SubTotal - $TotalDealPrice - $TotalExcludePrice;

		if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_ship_tax'] == '1')
		{
			$TaxValue = $this->GetAllCharges('TaxValue');
			$GrandTotal = ($GrandTotal - $gc_certi_total) + $shippingCharge + $TaxValue;
			$GrandTotalSale = ($GrandTotalSale  - $gc_certi_total) + $shippingCharge + $TaxValue;
		}else{
			$GrandTotal = $GrandTotal - $gc_certi_total;
			$GrandTotalSale = $GrandTotalSale - $gc_certi_total;
		}

		Session::put("count_ship_tax",0);
		Session::put("coupon_per",0);

		if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '1')
		{ // only one time use
			$sqlorder = Order::where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->get();
			if($sqlorder && $sqlorder->count() > 0 )
				$switchCase = '';
			else
				$switchCase = $CouponRS[0]['orders'];
		}else if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '2' && $customer_id != 0){
			// Once per customer
			$sqlorder = Order::where('customer_id','=',$customer_id)->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->get();
			if($sqlorder && $sqlorder->count() > 0 )
			{
				$switchCase = '';
			}else{
				$normaluser = Auth::user();
				if (Auth::guard('store')->check()) {
					$normaluser = Auth::guard('web')->user();
				}
				//if(Auth::user() && Session::get('etype') == "G")
				if($normaluser && Session::get('etype') == "G")
				{
					$Billing  = Session::get('ShoppingCart.BillingAddress');
					$sqlorder = Order::select('orders_id')->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])
								->where('bill_email','=',$Billing['email'])->get();
					if($sqlorder && $sqlorder->count() > 0 )
						$switchCase = '';
					else
						$switchCase = $CouponRS[0]['orders'];
				}else{
					$switchCase = $CouponRS[0]['orders'];
				}
			}
		}else{
			$switchCase = $CouponRS[0]['orders'];
		}

		switch ($switchCase)
		{
			## On Order Amount
			case '0' :
				$tempsubTotal = $GrandTotal;
				$tempSaleTotal = $GrandTotalSale;
				// Added code on 17 July 2012
				if($CouponRS[0]['count_ship_tax']=='1'){
					// Added by CK on 7th Feb, 2012 for Sale Item Coupon
					$tempSaleTotal=$tempSaleTotal;
					Session::put("count_ship_tax",1);
				}else{
					$tempsubTotal = $SubTotal;
					$tempSaleTotal = Session::get('ShoppingCart.SubTotal') - $gc_certi_total - $TotalDealPrice - $TotalExcludePrice;
					$tempSaleTotal = $tempSaleTotal;
				}
				if($tempSaleTotal >= $CouponRS[0]['order_amount'])
				{
					if($CouponRS[0]['type'] == 1 )
						$CouponDiscount = ( $tempSaleTotal * ($CouponRS[0]['discount']/100) );
					else
						$CouponDiscount = $CouponRS[0]['discount'];
				}
				else
				{
					$msg = "Coupon code does not apply to the item you have in your bag.";
					Session::flash('CartError',$msg);
				}

				if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
				{
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
					$FreeShippingFlg = true;
				}

				if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
				{
					$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
				}
				break;
			## On Product SKU
			case '1' :
				$CouponSKU = trim($CouponRS[0]['sku']);
				########### For Multiple SKU ###############
				$arr_CouponSKU  = explode(",",$CouponSKU);
				$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
				$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');

				$Matched_Item_Total = 0;
				$IS_Any_Matched 	= 0;

				if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
				{
					$tempCart  = Session::get('ShoppingCart.Cart');
					for ($a=0; $a<count($tempCart); $a++)
					{
						if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
						{
							$IS_Any_Matched = $IS_Any_Matched+1;
							if($CouponRS[0]['type'] == 1 )
							{
								$Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
							}
						}
					}
				}
				if($IS_Any_Matched >0 )
				{
					if($CouponRS[0]["count_ship_tax"]=='1')
					{
						if($CouponRS[0]['type'] == 1 )
						{
							if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
								$Matched_Item_Total = $Matched_Item_Total + Session::get('ShoppingCart.TaxValue');
							if($shippingCharge > 0)
								$Matched_Item_Total = $Matched_Item_Total + $shippingCharge;
						}
					}
					if($CouponRS[0]["count_gc_purchase"]=='1')
					{
						$GiftCertiTotal = 0;
						if(Session::has('ShoppingCart.GiftCertiTotal'))
							$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
						$Matched_Item_Total = $Matched_Item_Total + $GiftCertiTotal;
					}

					if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
					{
						if($CouponRS[0]['type'] == 1)
						{
							$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
						}
						else
						{
							 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
						}
					}
					elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
					{
						if($CouponRS[0]['type'] == 1)
						{
							$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
						}
						else
						{
							 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
						}
					}
					else
					{
						$CouponDiscount = 0;
						$msg = "Coupon code is invalid or does not exists.";
						Session::flash('CartError',$msg);
					}
				}
				else
				{
					$msg = "Coupon code is invalid or does not exists.";
					Session::flash('CartError',$msg);
				}
				if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
				{
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping', 'Yes');
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
					$FreeShippingFlg = true;
				}
				if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
				{

					$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
				}
				####################################################
				break;
			case '7' :
					$CouponSKU = trim($CouponRS[0]['sku']);

					$CouponSKU = unserialize($CouponSKU);
					$arr_CouponSKU = array();
					$arr_CouponDiscount = array();
					for($d=0;$d<count($CouponSKU);$d++)
					{
						if($CouponSKU[$d]["sku"]!='')
						{
							$arr_CouponSKU[] = $CouponSKU[$d]["sku"];
							$arr_CouponDiscount[$CouponSKU[$d]["sku"]] = $CouponSKU[$d]["discount"];
						}
					}
					########### For Multiple SKU ###############
					$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
					$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
				//	echo "<pre>"; print_r($arr_CouponSKU); exit;

					$Matched_Item_Total = 0;
					$IS_Any_Matched 	= 0;
					$CouponDiscountCalculate	= 0;
					$CouponDiscount = 0;
					if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
					{
						$tempCart  = Session::get('ShoppingCart.Cart');
						for ($a=0; $a<count($tempCart); $a++)
						{
							if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								$IS_Any_Matched = $IS_Any_Matched+1;
								$Current_Item_Total  = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
								$Matched_Item_Total = $Current_Item_Total + $Matched_Item_Total;

								$CouponDiscountCalculate = ($Current_Item_Total *($arr_CouponDiscount[$tempCart[$a]['SKU']] /100));
								$CouponDiscountCalculate = $this->NumberFormat($CouponDiscountCalculate);
								$CouponDiscount = $CouponDiscount + $CouponDiscountCalculate;
								//item wise discount for cj
								$tempCart[$a]['ItemWiseCouponDiscount_CJ'] = $CouponDiscountCalculate;
								//item wise discount for cj
							}
						}
						Session::put('ShoppingCart.Cart',$tempCart);
					}

					if($IS_Any_Matched >0 )
					{
						if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						{
							$CouponDiscount = $CouponDiscount;
						}
						elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
						{
							$CouponDiscount = $CouponDiscount;
						}
						else
						{
							$CouponDiscount = 0;
							$msg = "Coupon code is invalid or does not exists.";
							Session::flash('CartError',$msg);
						}
					}
					else
					{
						$msg = "Coupon code is invalid or does not exists.";
						Session::flash('CartError',$msg);
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					####################################################
					break;
			## On Product Brand
			case '2' :
					break;
			## On Product Category
			case '3' :
					$CouponCatID    	= trim($CouponRS[0]['sku']); // Category IDS
					$arr_CouponCatID    = explode(",",$CouponCatID);

					$CouponDiscount = 0;
					$found_cat = false; // Use for if coupon valid but category not found in cart;

					## Get Active Cat ID
					$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_CouponCatID)->get();
					$arr_active_CatID = array();
					if($Res_active_CatID && $Res_active_CatID->count() > 0)
					{
						for($h=0;$h<$Res_active_CatID->count();$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
					}

					if(count($arr_active_CatID) > 0 )
					{
						## Get Cart Prod ID
						$tempCart  	    = Session::get('ShoppingCart.Cart');
						$temp_prod_id   = array();

						for ($a=0; $a<count($tempCart); $a++)
						{
							$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
						}

						$ProdIds = ProductsCategory::select('products_id')->distinct()
									->whereIn('category_id',$arr_active_CatID)
									->whereIn('products_id',$temp_prod_id)
									->get();
						$cat_prod_id  = array();
						for ($a=0; $a<count($ProdIds); $a++)
						{
							$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
						}

						for ($a=0; $a<count($tempCart); $a++)
						{
							if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								if($CouponRS[0]['type'] == 1 )
								{
									$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $CouponDiscount;
								}
								$found_cat = true; // make true if category match
							}
						}
					}
					else
					{
						$CouponDiscount = 0;
					}
					if($found_cat==true)
					{
						if($CouponRS[0]["count_ship_tax"]=='1')
						{
							if($CouponRS[0]['type'] == 1 )
							{
								if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
									$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
								if($shippingCharge > 0)
									$CouponDiscount  = $CouponDiscount  + $shippingCharge;
							}
					  }
					  if($CouponRS[0]["count_gc_purchase"]=='1')
					  {
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
							$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
					  }
					  if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
					  {
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = $CouponRS[0]['discount'];
							}
					  }
					  elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
					  {
						  if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = $CouponRS[0]['discount'];
							}
					  }
					  else
					  {
						  $CouponDiscount = 0;
						  $msg = "Coupon code is invalid or does not exists.";
						  Session::flash('CartError',$msg);
					  }

					}
					if($found_cat==false)
					{
						$msg = "Coupon code does not apply to the item you have in your bag.";
						Session::flash('CartError',$msg);
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
					{
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					break;

			case '5' :
				        break;

			## On Product Brand
			case '6' :
					$CouponBrandID    	= trim($CouponRS[0]['sku']); // Brand IDS
					$arr_CouponBrandID  = explode(",",$CouponBrandID);

					$CouponDiscount = 0;
					$found_brand = false; // Use for if coupon valid but category not found in cart;

					## Get Active Cat ID
					$Res_active_BrandID = Manufacture::where('status','=','1')
											->whereIn('imanufactureid',$arr_CouponBrandID)->get();
					$arr_active_BrandID = array();
					for($h=0;$h<count($Res_active_BrandID);$h++)
					{
						$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
					}
					if(count($arr_active_BrandID) > 0 )
					{
						## Get Cart Prod ID
						$tempCart = Session::get('ShoppingCart.Cart');
						$temp_prod_id   = array();

						for ($a=0; $a<count($tempCart); $a++)
						{
							$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
						}

						$ProdIds = Products::select('products_id')->distinct()
									->whereIn('imanufactureid',$arr_active_BrandID)
									->whereIn('products_id',$temp_prod_id)
									->get();
						$brand_prod_id  = array();
						for ($a=0; $a<count($ProdIds); $a++)
						{
							$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
						}

						for ($a=0; $a<count($tempCart); $a++)
						{
							if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								if($CouponRS[0]['type'] == 1 )
								{
									$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+$CouponDiscount;
								}
								else
								{
									$CouponDiscount = $CouponRS[0]['discount']  ;
								}
								$found_brand = true; // make true if category match
							}
						}
					}
					else
					{
						$CouponDiscount = 0;
					}

				    if($found_brand==true)
				    {

					  if($CouponRS[0]["count_ship_tax"]=='1')
					  {
						if($CouponRS[0]['type'] == 1 )
						{
							if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
								$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
							if($shippingCharge > 0)
								$CouponDiscount  = $CouponDiscount  + $shippingCharge;
						}
					  }
					  if($CouponRS[0]["count_gc_purchase"]=='1')
					  {
						$GiftCertiTotal = 0;
						if(Session::has('ShoppingCart.GiftCertiTotal'))
							$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
						$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
					  }
						if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						{
					 	    if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = $CouponRS[0]['discount'];
							}
						}
						elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = $CouponRS[0]['discount'];
							}
						}
						else
					    {
						  $CouponDiscount = 0;
						  $msg = "Coupon code is invalid or does not exists.";
						  Session::flash('CartError',$msg);
					    }

				    }

					if($found_brand==false)
					{
						$msg = "Coupon code does not apply to the item you have in your bag.";
						Session::flash('CartError',$msg);
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
					{
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					break;

			## For Free Shipping
			case '4' :
					$CouponDiscount = 0;
					$Total_Item_count_val  = Session::get('ShoppingCart.TotalItemInCart');

					if($CouponRS[0]['minimum_order_amount'] == 0.00 ||  $CouponRS[0]['minimum_order_amount'] == 0)
					{
						if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
						{
							$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingModeID',$ShippingID);

							if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
							{
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
								$FreeShippingFlg = true;
							}
							if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
							{
								$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
							}
							$FreeShippingFlg = true;
						}
						else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
						{
							$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}
								$FreeShippingFlg = true;
						}
						else
						{
							$FreeShippingFlg = false;
						}
				   }

				  elseif(Session::get('ShoppingCart.SubTotal') >= $CouponRS[0]['minimum_order_amount'])
					{
						if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
						{
							$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingModeID',$ShippingID);

							if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
							{
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
								$FreeShippingFlg = true;
							}
							if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
							{
								$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
							}
							$FreeShippingFlg = true;
						}
						else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
						{
							$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingModeID',$ShippingID);

							if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
							{
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
								$FreeShippingFlg = true;
							}
							if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
							{
								$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
							}
							$FreeShippingFlg = true;
						}
						else
						{
							$FreeShippingFlg = false;
						}
				   }
				   else
				   {
					    $msg = "Coupon code does not apply to the item you have in your bag.";
						Session::flash('CartError',$msg);
						$FreeShippingFlg = false;
				   }
				break;
			default :
					$CouponDiscount = 0;
					$couponCode='';
					$msg = "Coupon code does not apply to the item you have in your bag.";
					Session::flash('CartError',$msg);
					break;
		}
		if($FreeShippingFlg==false)
		{
			Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','No');
		}
		$CouponDiscount = $this->NumberFormat($CouponDiscount);

		if($CouponDiscount > 0 or $FreeShippingFlg==true)
		{
			Session::put('ShoppingCart.PromoCoupon.SecondPromoCoupon',$couponCode);
		}
		else
		{
			Session::put('ShoppingCart.PromoCoupon.CouponCodeSecond','');
		}

		if($CouponDiscount > 0 )
		{
			Session::put('ShoppingCart.PromoCoupon.SecondCouponDiscount',$CouponDiscount);
		}
		else
		{
			Session::put('ShoppingCart.PromoCoupon.SecondCouponDiscount',0);
		}
		/*
		if($this->request['actiononcart']=='apply_coupon')
		{
			if(Session::get('ShoppingCart.PromoCoupon.CouponCode') !='' && Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount') > 0)
			{
				$msg = "Coupon code applied successfully.";
				Session::flash('CartError',$msg);
			}
		}*/
		return NULL;
	}

	public function GetShippingCharge()
	{
		if(Session::has('ShoppingCart.Shipping'))
		{
			$temp = Session::get('ShoppingCart.Shipping');
			if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($temp['ShippingMethodID'],Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
				Session::put('ShoppingCart.Shipping.ShippingCharge',0.00);

			if(Session::get('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag')== 'Yes' && (in_array($temp['ShippingMethodID'],Session::get('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.SecondFreeShipping') == 'Yes')
				Session::put('ShoppingCart.Shipping.ShippingCharge',0.00);

			if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $temp['ShippingMethodID'] == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
				Session::put('ShoppingCart.Shipping.ShippingCharge',0.00);

			if(Session::get('ShoppingCart.PromoCoupon.SecondFreeShipping') == 'Yes' && $temp['ShippingMethodID'] == Session::get('ShoppingCart.PromoCoupon.SecondFreeShippingModeID'))
				Session::put('ShoppingCart.Shipping.ShippingCharge',0.00);
			return NumberFormat(Session::get('ShoppingCart.Shipping.ShippingCharge'));
		}
	}
	public function getPocketPerfumeCategory()
	{
		return [68,69,70,71];
	}

	public function ApplyAutoDiscount()
	{
		$auto_discount = 0;
		$NewSubTotal = 0;
		$AutoDiscountItemWise = 0;

		$pocketPerfumeCategory = $this->getPocketPerfumeCategory();
		$log['pocketPerfumeCategory'] = json_encode($pocketPerfumeCategory);
		addLog('ApplyAutoDiscountStart',$log);
        if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
        {
			$this->getAllDiscountBlank("Auto");
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			if($normaluser && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.AutoDiscount',0);
				return null;
			}

			if(Session::has('isPhoneOrder') && Session::has('eusertype') && Session::get('eusertype') == "Wholesaler"){
				Session::put('ShoppingCart.AutoDiscount',0);
				return null;
			}

            if(Session::has('ShoppingCart.SubTotal'))
                $NewSubTotal = NumberFormat(Session::get('ShoppingCart.SubTotal'));
            $GiftCertiTotal = 0;
            if(Session::has('ShoppingCart.GiftCertiTotal'))
                $GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
            $subTotal = $NewSubTotal - $GiftCertiTotal;

			$DealSubTotal = $this->getDealSubTotal();

			$subTotal = $subTotal - $DealSubTotal;

			$log['NewSubTotal'] = $NewSubTotal;
			$log['GiftCertiTotal'] = $GiftCertiTotal;
			$log['DealSubTotal'] = $DealSubTotal;
			$log['subTotal'] = $subTotal;
			addLog('ApplyAutoDiscount',$log);

            $Cart = Session::get('ShoppingCart.Cart');
            $discount_coupon_flag = '';
            if($subTotal <= 0 )
            {
                Session::put('ShoppingCart.AutoDiscount',0);
                Session::put('ShoppingCart.AutoDiscountFlag','');
                return NULL;
            }
            $CouponCode = "";
            if(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') != '')
                $CouponCode = Session::get('ShoppingCart.PromoCoupon.CouponCode');
           $today = date('Y-m-d');
            if($CouponCode !='')
            {

                $coupon_res = Coupon::select('autodiscount_flag')
							->where('coupon_number', $CouponCode)
							->where('status', '1')
							->where('start_date', '<=', $today)
							->where('end_date', '>=', $today)
							->first();

                if ($coupon_res) {
						$log['coupon_res'] = $coupon_res;
						addLog('ApplyAutoDiscount', $log);

						if($coupon_res->autodiscount_flag == "No") {
							Session::put('ShoppingCart.AutoDiscount', 0);
							Session::put('ShoppingCart.AutoDiscountFlag', '');

							return null;
						}
					}

            }

			$baseQuery = AutoDiscount::where('start_date', '<=', $today)
				->where('end_date', '>=', $today)
				->where('status', '1');

			$AutoRS = (clone $baseQuery)
				->where('end_order_amount', '>=', $subTotal)
				->where('order_amount', '<=', $subTotal)
				->orderByDesc('end_order_amount')
				->get();

			if ($AutoRS->isEmpty()) {
				$AutoRS = (clone $baseQuery)
					->where('end_order_amount', '<=', $subTotal)
					->orderByDesc('end_order_amount')
					->get();
			}

			$log['AutoRS'] = $AutoRS;
			addLog('ApplyAutoDiscount',$log);

            $TotalItems = count(Session::get('ShoppingCart.Cart'));
            $TotalAutoDiscountRecords = $AutoRS->count();
            $TotalExcludePrice = 0;
			$AmountBasedDiscountExcludeSku = "No";
            if($TotalAutoDiscountRecords > 0)
            {
                $SKURemoveArr = '';

                $allActiveBrandIds = Cache::rememberForever('active_brand_ids', function () {
						return Manufacture::where('status', '1')
							->pluck('imanufactureid')
							->map(fn ($id) => (string) $id)
							->toArray();
					});

                 for($i=0;$i<$TotalAutoDiscountRecords;$i++)
                 {
                    $discount_coupon_flag = $AutoRS[$i]->discount_coupon_flag;
                    $ExcludeSKUListArr = array();
                    $TotalExcludePrice = 0;

                    if($TotalItems > 0 && $TotalAutoDiscountRecords > 0)
                    {
						if(isset($AutoRS[$i]->exclude_sku) && trim($AutoRS[$i]->exclude_sku)!='')
						{
							$ExcludeSKUListArr = array();
							$ExcludeSKUListArr  = explode(",",$AutoRS[$i]->exclude_sku);
							$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
							$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
							$TotalExcludePrice = 0;
							for($p=0;$p<$TotalItems;$p++)
							{
								if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
								{
									$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
								}
							}
						}
						if(isset($AutoRS[$i]->exclude_pocketperfume) && $AutoRS[$i]->exclude_pocketperfume=="Yes")
						{

							for($p=0;$p<$TotalItems;$p++)
							{
								if(!empty($Cart[$p]["CategoryID"]) && $Cart[$p]["CategoryID"] > 0  && in_array($Cart[$p]["CategoryID"],$pocketPerfumeCategory))
								{
									$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
								}
							}
						}
                    }
					if($AutoRS[$i]["orders"]=='0')
					{
						$QtySKU = trim($AutoRS[$i]['sku']);
						########### For Multiple SKU ###############
						$arr_QtySKU  = explode(",",$QtySKU);

						$arr_QtySKU  = array_unique(array_map('trim',$arr_QtySKU));
						$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');

						$AutoDiscount1 = 0;

						if(is_array($arr_QtySKU) and !empty($arr_QtySKU))
						{
							$tempCart  = Session::get('ShoppingCart.Cart');
							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$total_qty = 0;
							$total_price = 0;
							$TotalAmount =0;
							for ($a=0; $a<count($tempCart); $a++)
							{

								if(trim($AutoRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}

								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];

								$FreeSample = '';
								if(isset($tempCart[$a]["Is_Free_Sample"]))
								  $FreeSample = $tempCart[$a]["Is_Free_Sample"];

								if(in_array($tempCart[$a]['SKU'] , $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && $FreeSample!='Yes' && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
								{
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($AutoRS[$i]['type'] == 1 )
									{
									   $AutoDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $AutoRS[$i]['auto_discount_amount']/100);
									   Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',$AutoDiscountItemWise);
									   $AutoDiscount1 = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $AutoDiscount1;
									}
									$SKURemoveArr.= $tempCart[$a]['SKU'].",";
								}
							}
							if($AutoRS[$i]['type'] == 0 && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{

									if(trim($AutoRS[$i]["exclude_pocketperfume"])=='Yes')
									{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}
									}

									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

								    $FreeSample = '';
									if(isset($tempCart[$a]["Is_Free_Sample"]))
										$FreeSample = $tempCart[$a]["Is_Free_Sample"];

									if(in_array($tempCart[$a]['SKU'] , $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && $FreeSample!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
									{
										$AmountBasedDiscountExcludeSku = "Yes";
										$AutoDiscountItemWise = (($AutoRS[$i]['auto_discount_amount']*100)/$TotalAmount);
										$AutoDiscountCal = (($tempCart[$a]['TotPrice']  * $AutoDiscountItemWise)/100);
										Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',$AutoDiscountCal);
									}
								}
							}
							$MatchNewAutoDiscount=  0;
							if($auto_discount > 0)
							{
								$MatchNewAutoDiscount = $auto_discount;
							}

							if($AutoRS[$i]['type'] == 1)
							{
								$auto_discount = ($AutoDiscount1 * ($AutoRS[$i]['auto_discount_amount']/100));
							}
							else
							{
								if($AmountBasedDiscountExcludeSku == 'Yes'){
									$auto_discount = $AutoRS[$i]['auto_discount_amount'];
								}
							}
							$auto_discount = $auto_discount + $MatchNewAutoDiscount;

						}
					}
					else if($AutoRS[$i]["orders"] == '1')
					{
						if($AutoRS[$i]->sku !='')
						{

						$QtyBrandID = trim($AutoRS[$i]->sku);
						//echo $QtyBrandID; exit;

						$arr_QtyBrandID = array_values(array_unique(array_filter(
							array_map('trim', explode(',', $QtyBrandID))
						)));

						$arr_active_BrandID = array_values(
							array_intersect($arr_QtyBrandID, $allActiveBrandIds)
						);

						$AutoDiscount1 = 0;
						$found_brand = false;

						$log['arr_active_BrandID'] = $arr_active_BrandID;
						addLog('ApplyAutoDiscount',$log);
                        if(!empty($arr_active_BrandID) )
                        {
                            ## Get Cart Prod ID
                            $tempCart = Session::get('ShoppingCart.Cart', []);
                            $temp_prod_id = collect($tempCart)
											->pluck('ProductID')
											->filter()
											->unique()
											->values()
											->toArray();

							$log['temp_prod_id'] = $temp_prod_id;
							addLog('ApplyAutoDiscount', $log);
                            $brand_prod_id = Products::whereIn('imanufactureid', $arr_active_BrandID)
											->whereIn('products_id', $temp_prod_id)
											->distinct()
											->pluck('products_id')
											->toArray();

							$log['brand_prod_id'] = $brand_prod_id;
							addLog('ApplyAutoDiscount',$log);
                            $SKURemoveArrNew = [];
                            if($SKURemoveArr!='')
                            {
                                $SKURemoveArrNew = explode(",",$SKURemoveArr);
                                $SKURemoveArrNew = array_filter($SKURemoveArrNew);
                                $SKURemoveArrNew = array_values($SKURemoveArrNew);
                            }
                            $totalPrice = 0;
                            for ($a=0; $a<count($tempCart); $a++)
                            {
                                $FreeGift = "";
                                if(isset($tempCart[$a]["IS_Free_Gift"]))
                                    $FreeGift = $tempCart[$a]["IS_Free_Gift"];

                                $FreeSample = '';
								if(isset($tempCart[$a]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$a]["Is_Free_Sample"];

                                if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
                                {

									if(!empty($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && isset($AutoRS[$i]["exclude_pocketperfume"]) && $AutoRS[$i]["exclude_pocketperfume"]=="Yes" && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}

									$totalPrice = $totalPrice + $tempCart[$a]['TotPrice'];
                                    if($AutoRS[$i]['type'] == 1 )
                                    {
										$AutoDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $AutoRS[$i]['auto_discount_amount']/100);
										Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',$AutoDiscountItemWise);
                                        $AutoDiscount1 = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $AutoDiscount1;
                                    }
                                    $found_brand = true;
                                    $SKURemoveArr.= $tempCart[$a]['SKU'].",";
                                }
                            }
                            if($AutoRS[$i]['type'] == 0 && $totalPrice > 0)
                            {
								for ($a=0; $a<count($tempCart); $a++)
								{

									if(!empty($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && isset($AutoRS[$i]["exclude_pocketperfume"]) && $AutoRS[$i]["exclude_pocketperfume"]=="Yes" && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}

									$FreeGift = "";
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

									$FreeSample = '';
									if(isset($tempCart[$a]["Is_Free_Sample"]))
										$FreeSample = $tempCart[$a]["Is_Free_Sample"];

									if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" &&  $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
										{
											$AutoDiscountItemWise = (($AutoRS[$i]['auto_discount_amount']*100)/$totalPrice);

											$AutoDiscountCal = (($tempCart[$a]['TotPrice']  * $AutoDiscountItemWise)/100);

											Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',$AutoDiscountCal);

										}
								}

							}
                        }
                        if($found_brand==true)
                        {
                            $QuantityDiscountFlag = $AutoRS[$i]["discount_coupon_flag"];
                            $ExcludeSKUListArr = array();
                            $TotalExcludePrice = 0;
                            if($TotalItems > 0 && $TotalAutoDiscountRecords > 0 && trim($AutoRS[$i]["exclude_sku"])!='')
                            {
                                $ExcludeSKUListArr = array();
                                $ExcludeSKUListArr  = explode(",",$AutoRS[$i]["exclude_sku"]);
                                $ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
                                $ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
                                $TotalExcludePrice = 0;
                                for($p=0;$p<$TotalItems;$p++)
                                {
                                    if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
                                    {

										if(isset($Cart[$p]["TotPrice"]) && $Cart[$p]["TotPrice"] > 0)
										{
											$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
										}
                                    }
                                }
                            }

                            for($p=0;$p<$TotalItems;$p++)
                            {
								if(!empty($tempCart[$p]["CategoryID"]) && $tempCart[$p]["CategoryID"] > 0 && isset($AutoRS[$i]["exclude_pocketperfume"]) && $AutoRS[$i]["exclude_pocketperfume"]=="Yes" && in_array($tempCart[$p]["CategoryID"],$pocketPerfumeCategory))
								{
									if(isset($Cart[$p]["TotPrice"]) && $Cart[$p]["TotPrice"] > 0)
									{
										$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
									}
								}
							}

                            $MatchNewAutoDiscount=  0;
                            if($auto_discount > 0)
                            {
                                $MatchNewAutoDiscount = $auto_discount;
                            }
                            if($AutoRS[$i]['type'] == 1)
                            {
                                $auto_discount = ($AutoDiscount1  * $AutoRS[$i]['auto_discount_amount']/100);
                            }
                            else
                            {
                                $auto_discount = $AutoRS[$i]['auto_discount_amount'];
                            }
                            $auto_discount = $auto_discount + $MatchNewAutoDiscount;
                        }
                    }
					}
                    else
                    {

                        $today = date('Y-m-d');

						$baseQuery = AutoDiscount::where('start_date', '<=', $today)
							->where('end_date', '>=', $today)
							->where('status', '1')
							->where('sku', '');

						$AutoRS1 = (clone $baseQuery)
							->where('end_order_amount', '>=', $subTotal)
							->where('order_amount', '<=', $subTotal)
							->orderByDesc('end_order_amount')
							->first();

						if (!$AutoRS1) {
							$AutoRS1 = (clone $baseQuery)
								->where('end_order_amount', '<=', $subTotal)
								->orderByDesc('end_order_amount')
								->first();
						}

                        if($AutoRS1 > 0)
                        {
							$log['AutoRS1'] = $AutoRS1;
							addLog('ApplyAutoDiscount',$log);
							$tempCart = Session::get('ShoppingCart.Cart');

							$SKURemoveArrNew = [];
                            if($SKURemoveArr!='')
                            {
                                $SKURemoveArrNew = explode(",",$SKURemoveArr);
                                $SKURemoveArrNew = array_filter($SKURemoveArrNew);
                                $SKURemoveArrNew = array_values($SKURemoveArrNew);
                            }

                            $ExcludeSKUListArr = array();
							$ExcludeSKUListArr  = explode(",",$AutoRS1->exclude_sku);
							$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
							$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
                            $totalPrice = 0;
                            for ($a=0; $a<count($tempCart); $a++)
                            {
								//Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',0);
                                $FreeGift = "";
                                if(isset($tempCart[$a]["IS_Free_Gift"]))
                                    $FreeGift = $tempCart[$a]["IS_Free_Gift"];

                                $FreeSample = '';
								if(isset($tempCart[$a]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$a]["Is_Free_Sample"];

                                if (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" &&  $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
                                {

									if(isset($AutoRS1[0]["exclude_pocketperfume"]) && $AutoRS1[0]["exclude_pocketperfume"]=="Yes" && !empty($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0&&  in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory) )
									{
										continue;
									}

									$totalPrice = $totalPrice + $tempCart[$a]['TotPrice'];
                                    if($AutoRS1[0]["type"] == 1 )
                                    {
										$AutoDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $AutoRS1->auto_discount_amount/100);
										Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',$AutoDiscountItemWise);

                                    }
                                    $SKURemoveArr.= $tempCart[$a]['SKU'].",";
                                }
                            }

							if($AutoRS1[0]["type"] == 0 && $totalPrice > 0)
                            {
								for ($a=0; $a<count($tempCart); $a++)
								{
									//Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',0);
									$FreeGift = "";
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

									$FreeSample = '';
									if(isset($tempCart[$a]["Is_Free_Sample"]))
										$FreeSample = $tempCart[$a]["Is_Free_Sample"];

									if (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" &&  $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
									{

										if(isset($AutoRS1[0]["exclude_pocketperfume"]) && $AutoRS1[0]["exclude_pocketperfume"]=="Yes" && !empty($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}

										$AutoDiscountItemWise = (($AutoRS1->auto_discount_amount*100)/$totalPrice);
										$AutoDiscountCal = (($tempCart[$a]['TotPrice']  * $AutoDiscountItemWise)/100);
										Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',$AutoDiscountCal);
									}
								}
							}

                            $discount_coupon_flag = $AutoRS1->discount_coupon_flag;
                            $subTotal = $subTotal - $TotalExcludePrice;
                            if($AutoRS1[0]['type'] == '1')
                                $auto_discount = ( $subTotal * ($AutoRS1->auto_discount_amount/100) );
                            else
                                $auto_discount = $AutoRS1->auto_discount_amount;
                            break;
                        }
                        else
                        {
                             $auto_discount = 0;
                        }
                    }
                 }
            }
            Session::put('ShoppingCart.AutoDiscount',NumberFormat($auto_discount));
            Session::put('ShoppingCart.AutoDiscountFlag',$discount_coupon_flag);
			$log['AutoDiscount'] = $auto_discount;
			$log['discount_coupon_flag'] = $discount_coupon_flag;
			addLog('ApplyAutoDiscountEnd',$log);
        }
		return NULL;
	}

	public function GetAllDiscounts($DiscountName='')
	{
		$log['DiscountName'] = $DiscountName;
		addLog('GetAllDiscountStart',$log);
		$Discounts = [];

		If(Session::has('ShoppingCart.AutoDiscount') && Session::get('ShoppingCart.AutoDiscount') > 0)
			$Discounts['AutoDiscount'] = ['label' => 'Auto Discount', 'discount' => Session::get('ShoppingCart.AutoDiscount')];
		If(Session::has('ShoppingCart.YotpoRewardDiscount') && Session::get('ShoppingCart.YotpoRewardDiscount') > 0)
			$Discounts['YotpoRewardDiscount'] = ['label' => 'Reward Discount', 'discount' => Session::get('ShoppingCart.YotpoRewardDiscount'),'Ricon' => 'Yes', 'dataid' => 'YotpoRewardDiscount'];
		If(Session::has('ShoppingCart.QuantityDiscount') && Session::get('ShoppingCart.QuantityDiscount') > 0)
			$Discounts['QuantityDiscount'] = ['label' => 'Quantity Discount', 'discount' => Session::get('ShoppingCart.QuantityDiscount')];
		$CouponTotal = 0;
		If(Session::has('ShoppingCart.PromoCoupon.FirstCouponDiscount') && Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount') > 0)
			$CouponTotal += NumberFormat(Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount'));
		If(Session::has('ShoppingCart.PromoCoupon.SecondCouponDiscount') && Session::get('ShoppingCart.PromoCoupon.SecondCouponDiscount') > 0)
			$CouponTotal += NumberFormat(Session::get('ShoppingCart.PromoCoupon.SecondCouponDiscount'));
		if($CouponTotal > 0)
			Session::put('ShoppingCart.PromoCoupon.CouponDiscount',$CouponTotal);

		If(Session::has('ShoppingCart.PromoCoupon.CouponDiscount') && Session::get('ShoppingCart.PromoCoupon.CouponDiscount') > 0)
			$Discounts['CouponDiscount'] = ['label' => 'Coupon Discount', 'discount' => Session::get('ShoppingCart.PromoCoupon.CouponDiscount'),'Ricon' => 'Yes', 'dataid' => 'CouponDiscount'];
		If(Session::has('ShoppingCart.GiftCoupon') && Session::get('ShoppingCart.GiftCoupon') > 0)
		{
			$GiftCoupon = Session::get('ShoppingCart.GiftCoupon');
			$Discounts['GiftCoupon'] = ['label' => 'Gift Certificate Discount', 'discount' => $GiftCoupon['Value'],'Ricon' => 'Yes', 'dataid' => 'GiftCoupon'];
		}
		If(Session::has('ShoppingCart.AutoReferDiscount') && Session::get('ShoppingCart.AutoReferDiscount') > 0)
			$Discounts['AutoReferDiscount'] = ['label' => 'Auto Refer Discount', 'discount' => Session::get('ShoppingCart.AutoReferDiscount')];
		If(Session::has('ShoppingCart.Reward_array.RewardDiscount') && Session::get('ShoppingCart.Reward_array.RewardDiscount') > 0)
			$Discounts['AutoRewardDiscount'] = ['label' => 'Reward Discount', 'discount' => Session::get('ShoppingCart.Reward_array.RewardDiscount')];
		If(Session::has('ShoppingCart.credit_limit_discount') && Session::get('ShoppingCart.credit_limit_discount') > 0)
			$Discounts['CreditLimitDiscount'] = ['label' => 'Credit Limit Discount', 'discount' => Session::get('ShoppingCart.credit_limit_discount')];
		If(Session::has('ShoppingCart.DogoDiscount') && Session::get('ShoppingCart.DogoDiscount') > 0)
			$Discounts['DogoDiscount'] = ['label' => 'Bogo Discount', 'discount' => Session::get('ShoppingCart.DogoDiscount')];
		if($DiscountName != '')
		{
			$log['Discounts'] = json_encode($Discounts);
			addLog('GetAllDiscount',$log);

			$DiscountDetail = 0;
			if(isset($Discounts[$DiscountName]))
				$DiscountDetail = NumberFormat($Discounts[$DiscountName]['discount']);
			return $DiscountDetail;
		} else {
			$TotalDiscount = array_sum(array_map('floatval', array_column($Discounts, 'discount')));
			$DiscountInfo  = ['Discounts' => $Discounts, 'TotalDiscount' => NumberFormat($TotalDiscount)];
			$log['DiscountInfo'] = json_encode($DiscountInfo);
			addLog('GetAllDiscount',$log);
			return $DiscountInfo;
		}
	}

	public function GetAllCharges($ChargeName='')
	{

		if(isset($ChargeName) && $ChargeName == "TaxValue")
		{
			$ChargeName = "Tax";
		}
		$log['ChargeName'] = $ChargeName;
		addLog('GetAllChargesStart',$log);
		$Charges = [];
		If(Session::has('ShoppingCart.Shipping.ShippingCharge') && Session::get('ShoppingCart.Shipping.ShippingCharge') > 0)
			$Charges['ShippingCharge'] = ['label' => 'Shipping Charge', 'charge' => Session::get('ShoppingCart.Shipping.ShippingCharge')];
		If(Session::has('ShoppingCart.Tax') && Session::get('ShoppingCart.Tax') > 0)
			$Charges['Tax'] = ['label' => 'Sales Tax', 'charge' => Session::get('ShoppingCart.Tax')];
		if(Session::has('ShoppingCart.GiftWrapping'))
		{
			$giftWrap = Session::get('ShoppingCart.GiftWrapping');
			if($giftWrap['Charge'] > 0)
				$Charges['GiftWrappingCharge'] = ['label' => 'Gift Wrapping Charge', 'charge' => $giftWrap['Charge']];
		}
		if(Session::has('ShoppingCart.ShippingSignature'))
			$Charges['ShippingSignature'] = ['label' => 'Shipping Signature', 'charge' => Session::get('ShoppingCart.ShippingSignature')];
		if(Session::has('shipping_insurance_charge'))
			$Charges['ShippingInsurance'] = ['label' => 'Shipping Insurance Charge', 'charge' => Session::get('shipping_insurance_charge')];

		if($ChargeName != '')
		{
			$Charge = (isset($Charges[$ChargeName])?NumberFormat($Charges[$ChargeName]['charge']):0);
			$log['Charge'] = $Charge;
			addLog('GetAllCharges',$log);
			return $Charge;
		} else {
			$TotalCharges = array_sum(array_column($Charges,'charge'));
			$ChargesInfo  = ['Charges' => $Charges, 'TotalCharges' => NumberFormat($TotalCharges)];
			$log['ChargesInfo'] = $ChargesInfo;
			addLog('GetAllCharges',$log);
			return $ChargesInfo;
		}
	}

	public function GetAllCoupons($CouponID='')
	{
		$Coupons = ['CouponCode' => '','CouponCodeSecond' => '', 'GiftCoupon'];
		If(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') != '')
			$Coupons['CouponCode'] = Session::get('ShoppingCart.PromoCoupon.CouponCode');
		If(Session::has('ShoppingCart.PromoCoupon.CouponCodeSecond') && Session::get('ShoppingCart.PromoCoupon.CouponCodeSecond') != '')
			$Coupons['CouponCodeSecond'] = Session::get('ShoppingCart.PromoCoupon.CouponCodeSecond');
		If(Session::has('ShoppingCart.GiftCoupon') && Session::get('ShoppingCart.GiftCoupon') != '')
			$Coupons['GiftCoupon'] = Session::get('ShoppingCart.GiftCoupon');
		if($CouponID != '') {
			if(isset($Coupons[$CouponID]))
				return $Coupons[$CouponID];
		} else {
			return $Coupons;
		}
	}

	public function getAllDiscountBlank($DiscountFlag='')
	{
		$Cart = Session::get('ShoppingCart.Cart');

		if(isset($DiscountFlag) && $DiscountFlag=="Auto")
		{
			$TotalItems = count(Session::get('ShoppingCart.Cart'));
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.AutoItemWiseDiscout',0);
			}
		}
		if(isset($DiscountFlag) && $DiscountFlag=="Quantity")
		{
			$TotalItems = count(Session::get('ShoppingCart.Cart'));
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.QuantityItemWiseDiscout',0);
			}
		}
		if(isset($DiscountFlag) && $DiscountFlag=="Coupon")
		{
			$TotalItems = count(Session::get('ShoppingCart.Cart'));
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.CouponDisItemWiseDiscout',0);
			}
		}
		if(isset($DiscountFlag) && $DiscountFlag=="Reward")
		{
			$TotalItems = count(Session::get('ShoppingCart.Cart'));
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.RewardItemWiseDiscout',0);
			}
		}
		if(isset($DiscountFlag) && $DiscountFlag=="Bogo")
		{
			$TotalItems = count(Session::get('ShoppingCart.Cart'));
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.BogoItemWiseDiscout',0);
			}
		}
	}

	public function ApplyQuantityDiscount()
	{
		$pocketPerfumeCategory = $this->getPocketPerfumeCategory();
		$QuantityDiscount = 0;
		$NewSubTotal = 0;
		$QuantityDiscountItemWise = 0;

		$log['QuantityDiscount'] = $QuantityDiscount;
		$log['NewSubTotal'] = $NewSubTotal;
		$log['QuantityDiscountItemWise'] = $QuantityDiscountItemWise;
		$log['pocketPerfumeCategory'] = json_encode($pocketPerfumeCategory);
		addLog('ApplyQuantityDiscountStart',$log);

		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$this->getAllDiscountBlank("Quantity");
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			if($normaluser && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.QuantityDiscount',0);
				return null;
			}

			if(Session::has('isPhoneOrder') && Session::has('eusertype') && Session::get('eusertype') == "Wholesaler"){
				Session::put('ShoppingCart.QuantityDiscount',0);
				return null;
			}

			if(Session::has('ShoppingCart.SubTotal'))
				$NewSubTotal = NumberFormat(Session::get('ShoppingCart.SubTotal'));
			$GiftCertiTotal = 0;
			$GiftCertiCount = 0 ;
			if(Session::has('ShoppingCart.GiftCertiTotal'))
			{
				$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
				$GiftCertiCount = NumberFormat(Session::get('ShoppingCart.GiftCertiCount'));
			}
			$subTotal = $NewSubTotal - $GiftCertiTotal;
			$log['GiftCertiTotal'] = $GiftCertiTotal;
			$Cart = Session::get('ShoppingCart.Cart');

			$TotalItem 	= Session::get('ShoppingCart.TotalItemInCart') - $GiftCertiCount;
			$QuantityDiscountFlag = '';
			if($subTotal <= 0 || $TotalItem <= 0)
			{
				Session::put('ShoppingCart.QuantityDiscount', 0);
				Session::put('ShoppingCart.QuantityDiscountFlag', '');
				$log['QuantityDiscount'] = '0';
				$log['QuantityDiscountFlag'] = '';
				addLog('ApplyQuantityDiscount',$log);
				return NULL;
			}
			$CouponCode = $this->GetAllCoupons('CouponCode');
			if($CouponCode != '')
			{

				$today = date('Y-m-d');
                $coupon_res = Coupon::select('quantitydiscount_flag')
							->where('coupon_number', $CouponCode)
							->where('status', '1')
							->where('start_date', '<=', $today)
							->where('end_date', '>=', $today)
							->first();
				if($coupon_res)
				{
					$log['coupon_res'] = json_encode($coupon_res);
					if($coupon_res->quantitydiscount_flag == "No") {
							Session::put('ShoppingCart.QuantityDiscount', 0);
							Session::put('ShoppingCart.QuantityDiscountFlag', '');
							$log['QuantityDiscount_1'] = '0';
							$log['QuantityDiscountFlag_1'] = '';
							addLog('ApplyQuantityDiscount',$log);
							return null;
					}

				}
			}

			$today = date('Y-m-d');

			$QtyRS = QuantityDiscount::where('status', '1')
				->where('start_date', '<=', $today)
				->where('end_date', '>=', $today)
				->where('quantity', '<=', $TotalItem)
				->orderByDesc('quantity_discount_id')
				->get();

			$TotalQuantityDiscoundRecords = $QtyRS->count();

			$CartItems = Session::get('ShoppingCart.Cart', []);
			$TotalItems = count($CartItems);

			$TotalExcludePrice = 0;

			$log['QtyRS'] = $QtyRS->toJson();

			if($TotalQuantityDiscoundRecords > 0)
			{
			   $TotalQuantity = $TotalQuantityDiscoundRecords;
			   $SKURemoveArr = '';

			  $allActiveCategoryIds = Cache::rememberForever('active_category_ids', function () {
				return Category::where('status', '1')
					->pluck('category_id')
					->map(fn ($id) => (string) $id)
					->toArray();
			   });

			   $allActiveBrandIds = Cache::rememberForever('active_brand_ids', function () {
									return Manufacture::where('status', '1')
										->pluck('imanufactureid')
										->map(fn ($id) => (string) $id)
										->toArray();
								});

			   for($i=0;$i<$TotalQuantity;$i++)
			   {
					$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];
					$ExcludeSKUListArr = array();
					$TotalExcludePrice = 0;
					$TotalQty = 0;
					if($TotalItems > 0 && $TotalQuantityDiscoundRecords > 0 && trim($QtyRS[$i]["exclude_sku"])!='')
					{
						$ExcludeSKUListArr = array();
						$ExcludeSKUListArr  = explode(",",$QtyRS[$i]["exclude_sku"]);
						$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
						$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
						$TotalExcludePrice = 0;
						$log['ExcludeSKUListArr'] = json_encode($ExcludeSKUListArr);

						for($p=0;$p<$TotalItems;$p++)
						{
							if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
							{
								$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
							}

						}
						$log['TotalExcludePrice_1'] = json_encode($TotalExcludePrice);
					}

					if($TotalItems > 0 && $TotalQuantityDiscoundRecords > 0 && trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
					{
						for($p=0;$p<$TotalItems;$p++)
						{
							if(isset($Cart[$p]["CategoryID"]) && $Cart[$p]["CategoryID"] > 0 && in_array($Cart[$p]["CategoryID"],$pocketPerfumeCategory))
							{
								$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
							}

						}
						$log['TotalExcludePrice_2'] = json_encode($TotalExcludePrice);
					}

					if($QtyRS[$i]["orders"]=='0')
					{
						$QtySKU = trim($QtyRS[$i]['sku']);
						########### For Multiple SKU ###############
						$arr_QtySKU  = explode(",",$QtySKU);

						$arr_QtySKU  = array_unique(array_map('trim',$arr_QtySKU));
						$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');

						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;

						$log['arr_QtySKU'] = json_encode($arr_QtySKU);

						if(is_array($arr_QtySKU) and !empty($arr_QtySKU))
						{
							$tempCart  = Session::get('ShoppingCart.Cart');
							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$total_qty = 0;
							$total_price = 0;
							$TotalAmount =0;
							$TotalQty = 0;

							for($p=0;$p<$TotalItems;$p++)
							{
								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$p]["CategoryID"]) && $tempCart[$p]["CategoryID"] > 0 && in_array($tempCart[$p]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}
								$FreeGift = '';
								if(isset($tempCart[$p]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$p]["IS_Free_Gift"];

								$FreeSample = '';
								if(isset($tempCart[$p]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$p]["Is_Free_Sample"];

								if (in_array( $tempCart[$p]['SKU'] , $arr_QtySKU) && (isset($tempCart[$p]["IsDealProducts"]) && $tempCart[$p]["IsDealProducts"]!="Yes") && $FreeGift!="Yes"  && $FreeSample!="Yes" && !in_array($tempCart[$p]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$p]['SKU'] , $SKURemoveArrNew))
								{
									$TotalQty = $TotalQty + $Cart[$p]["Qty"];
								}
							}
							$log['TotalQty'] = json_encode($TotalQty);

							for ($a=0; $a<count($tempCart); $a++)
							{

								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}

								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];

								$FreeSample = '';
								if(isset($tempCart[$a]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$a]["Is_Free_Sample"];

								if(in_array($tempCart[$a]['SKU'] , $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($QtyRS[$i]['type'] == 1 )
									{
									   if($TotalQty >= $QtyRS[$i]['quantity'])
										{
										   $QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS[$i]['quantity_discount_amount']/100);
										   Session::put('ShoppingCart.Cart.'.$a.'.QuantityItemWiseDiscout',$QuantityDiscountItemWise);
										   $Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
										}
									}
									$SKURemoveArr.= $tempCart[$a]['SKU'].",";
								}
							}
							if($QtyRS[$i]['type'] == 0 && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{

									if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
									{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}
									}

									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

									$FreeSample = '';
									if(isset($tempCart[$a]["Is_Free_Sample"]))
										$FreeSample = $tempCart[$a]["Is_Free_Sample"];

									if(in_array($tempCart[$a]['SKU'], $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'], $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
									{

									  if($TotalQty >= $QtyRS[$i]['quantity'])
										{
										$QuantityDiscountItemWise = (($QtyRS[$i]['quantity_discount_amount']*100)/$TotalAmount);
										$QuantityDiscountCal = (($tempCart[$a]['TotPrice']  * $QuantityDiscountItemWise)/100);
										Session::put('ShoppingCart.Cart.'.$a.'.QuantityItemWiseDiscout',$QuantityDiscountCal);
										}

									}
								}
							}
							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($IS_Any_Matched > 0  && $total_qty >=$QtyRS[$i]['quantity'])
							{
								if($QtyRS[$i]['type'] == 1)
								{
									$QuantityDiscount = ($Matched_Item_Total * ($QtyRS[$i]['quantity_discount_amount']/100));
								}
								else
								{
									$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
								}
							}
						}
					}
					else if($QtyRS[$i]["orders"]=='1')
					{
						$QtySKU = trim($QtyRS[$i]['sku']);
						########### For Multiple SKU ###############
						$QtyCatID    	= trim($QtyRS[$i]['sku']); // Category IDS

						$arr_QtyCatID = array_values(array_unique(array_filter(
												array_map('trim', explode(',', $QtySKU))
											)));

						$arr_active_CatID = array_values(
												array_intersect($arr_QtyCatID, $allActiveCategoryIds)
											);

						$QuantityDiscount1 = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID

						if(!empty($arr_active_CatID))
						{

							$tempCart = Session::get('ShoppingCart.Cart', []);

							$temp_prod_id = collect($tempCart)
									->pluck('ProductID')
									->filter()
									->unique()
									->values()
									->toArray();

							$cat_prod_id = [];

							if(!empty($temp_prod_id)) {
									$cat_prod_id = ProductsCategory::whereIn('category_id', $arr_active_CatID)
										->whereIn('products_id', $temp_prod_id)
										->distinct()
										->pluck('products_id')
										->toArray();
								}

							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							$TotalAmount=0;
							$TotalQty = 0;

							for($p=0;$p<$TotalItems;$p++)
							{
								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$p]["CategoryID"]) && $tempCart[$p]["CategoryID"] > 0 && in_array($tempCart[$p]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}
								$FreeGift = '';
								if(isset($tempCart[$p]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$p]["IS_Free_Gift"];

								$FreeSample = '';
								if(isset($tempCart[$p]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$p]["Is_Free_Sample"];

								if (in_array( $tempCart[$p]['ProductID'] , $cat_prod_id) && (isset($tempCart[$p]["IsDealProducts"]) && $tempCart[$p]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$p]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$p]['SKU'] , $SKURemoveArrNew))
								{
									$TotalQty = $TotalQty + $Cart[$p]["Qty"];
								}
							}

							for ($a=0; $a<count($tempCart); $a++)
							{

								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}
								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];

								$FreeSample = '';
								if(isset($tempCart[$a]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$a]["Is_Free_Sample"];

								if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
								{
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$total_price = $total_price + ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($tempCart[$a]['Qty'] >= $QtyRS[$i]['quantity'] || $total_qty >= $QtyRS[$i]['quantity'])
									{

										if($QtyRS[$i]['type'] == 1 )
										{
											//$QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS[$i]['quantity_discount_amount']/100);
											//Session::put('ShoppingCart.Cart.'.$a.'.QuantityItemWiseDiscout',$QuantityDiscountItemWise);
											$total_percentage = true;
										}
										$found_cat = true;
										$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}

									if($TotalQty >= $QtyRS[$i]['quantity'])
									{
										if($QtyRS[$i]['type'] == 1 )
										{
											if (empty(Session::get('ShoppingCart.Cart.' . $a . '.QuantityItemWiseDiscout')))
											{

												$QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS[$i]['quantity_discount_amount'] / 100);
												Session::put('ShoppingCart.Cart.' . $a . '.QuantityItemWiseDiscout', $QuantityDiscountItemWise);

											}

										}
										//$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}

								}
							}
							if($QtyRS[$i]['type'] == 0 && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{
									if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
									{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}
									}
									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

									$FreeSample = '';
									if(isset($tempCart[$a]["Is_Free_Sample"]))
										$FreeSample = $tempCart[$a]["Is_Free_Sample"];

									if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
									{

										if($TotalQty >= $QtyRS[$i]['quantity'])
										{

											$QuantityDiscountItemWise = (($QtyRS[$i]['quantity_discount_amount']*100)/$TotalAmount);
											$QuantityDiscountCal = (($tempCart[$a]['TotPrice']  * $QuantityDiscountItemWise)/100);
											Session::put('ShoppingCart.Cart.'.$a.'.QuantityItemWiseDiscout',$QuantityDiscountCal);
										}

									}
								}
							}
							if($total_percentage == true)
							{
								$QuantityDiscount1 = $total_price + $QuantityDiscount1;
							}
						}
						if($found_cat==true)
						{
							$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];

							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($QtyRS[$i]['type'] == 1)
							{

								$QuantityDiscount = ($QuantityDiscount1  * $QtyRS[$i]['quantity_discount_amount']/100);
							}
							else
							{
								$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
							}

							if($QuantityDiscount > $MatchNewQTYDiscount)
							{
								$QuantityDiscount = $QuantityDiscount;
							}
							elseif($MatchNewQTYDiscount > $QuantityDiscount)
							{
								$QuantityDiscount = $MatchNewQTYDiscount;
							}
							$log['QuantityDiscount_1'] = json_encode($QuantityDiscount);
						}
				   }

				   else if($QtyRS[$i]["orders"]=='2')
				   {
						$QtySKU = trim($QtyRS[$i]['sku']);

						########### For Multiple SKU ###############

						$QtyBrandID    	= trim($QtyRS[$i]['sku']); // Category IDS
						$arr_QtyBrandID = array_values(array_unique(array_filter(
												array_map('trim', explode(',', $QtySKU))
											)));

						$QuantityDiscount1 = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						$arr_active_BrandID = array_values(
												array_intersect($arr_QtyBrandID, $allActiveBrandIds)
											);

						if(!empty($arr_active_BrandID) )
						{
							## Get Cart Prod ID

							$tempCart = Session::get('ShoppingCart.Cart', []);

							$temp_prod_id = collect($tempCart)
								->pluck('ProductID')
								->filter()
								->unique()
								->values()
								->toArray();

							$log['temp_prod_id'] = $temp_prod_id;

							$brand_prod_id = [];

							if (!empty($temp_prod_id)) {
								$brand_prod_id = Products::whereIn(
										'imanufactureid',
										$arr_active_BrandID
									)
									->whereIn('products_id', $temp_prod_id)
									->distinct()
									->pluck('products_id')
									->toArray();
							}

							$log['brand_prod_id'] = $brand_prod_id;

							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$log['SKURemoveArrNew'] = json_encode($SKURemoveArrNew);
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							$TotalAmount =0;
							$TotalQty = 0;

							for($p=0;$p<$TotalItems;$p++)
							{
								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$p]["CategoryID"]) && $tempCart[$p]["CategoryID"] > 0 && in_array($tempCart[$p]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}
								$FreeGift = '';
								if(isset($tempCart[$p]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$p]["IS_Free_Gift"];

								$FreeSample = '';
								if(isset($tempCart[$p]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$p]["Is_Free_Sample"];

								if (in_array( $tempCart[$p]['ProductID'] , $brand_prod_id) && (isset($tempCart[$p]["IsDealProducts"]) && $tempCart[$p]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$p]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$p]['SKU'] , $SKURemoveArrNew))
								{
									$TotalQty = $TotalQty + $Cart[$p]["Qty"];
								}

							}

							for ($a=0; $a<count($tempCart); $a++)
							{

								if($QtyRS[$i]["exclude_pocketperfume"]=='Yes')
								{
									if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}

								 }

								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];

								$FreeSample = '';
								if(isset($tempCart[$a]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$a]["Is_Free_Sample"];

								if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
								{

									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$total_price = $total_price + ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($tempCart[$a]['Qty'] >= $QtyRS[$i]['quantity'] || $total_qty >= $QtyRS[$i]['quantity'])
									{
										if($QtyRS[$i]['type'] == 1 )
										{

											$total_percentage = true;
										}
										$found_brand = true;
										$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}

									if($TotalQty >= $QtyRS[$i]['quantity'])
									{
										if($QtyRS[$i]['type'] == 1 )
										{

											if (empty(Session::get('ShoppingCart.Cart.' . $a . '.QuantityItemWiseDiscout'))) {
												$QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS[$i]['quantity_discount_amount'] / 100);
												Session::put('ShoppingCart.Cart.' . $a . '.QuantityItemWiseDiscout', $QuantityDiscountItemWise);
											}

										}
										//$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}

								}
							}
							if($QtyRS[$i]['type'] == 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{
									if($QtyRS[$i]["exclude_pocketperfume"]=='Yes')
									{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}

									 }
									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

									$FreeSample = '';
									if(isset($tempCart[$a]["Is_Free_Sample"]))
										$FreeSample = $tempCart[$a]["Is_Free_Sample"];

									if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
									{

										if($TotalQty >= $QtyRS[$i]['quantity'])
										{
											$QuantityDiscountItemWise = (($QtyRS[$i]['quantity_discount_amount']*100)/$TotalAmount);
											$QuantityDiscountCal = (($tempCart[$a]['TotPrice']  * $QuantityDiscountItemWise)/100);
											Session::put('ShoppingCart.Cart.'.$a.'.QuantityItemWiseDiscout',$QuantityDiscountCal);
										}
									}
								}

							}
							if($total_percentage == true)
							{
								$QuantityDiscount1 = $total_price + $QuantityDiscount1;
							}
						}

						if($found_brand==true)
						{
							$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];

							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($QtyRS[$i]['type'] == 1)
							{
								 $QuantityDiscount = ($QuantityDiscount1  * $QtyRS[$i]['quantity_discount_amount']/100);
							}
							else
							{
								$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
							}

							if($QuantityDiscount > $MatchNewQTYDiscount)
							{
								$QuantityDiscount = $QuantityDiscount;
							}
							elseif($MatchNewQTYDiscount > $QuantityDiscount)
							{
								$QuantityDiscount = $MatchNewQTYDiscount;
							}
						}
				   }
				    else
				    {

					   $today = date('Y-m-d');

					   $QtyRS1 = QuantityDiscount::where('status', '1')
							->where('start_date', '<=', $today)
							->where('end_date', '>=', $today)
							->where('quantity', '<=', $TotalItem)
							->where('orders', '')
							->orderBy('quantity_discount_id')
							->first();

						$IS_Any_Matched 	= 0;
						$total_qty			= 0;
						$TotalAmount 		= 0;
						//echo "<pre>"; print_r($QtyRS1)
						if($QtyRS1)
						{
							$tempCart  = Session::get('ShoppingCart.Cart');
							$subTotal = 0;
							for ($a=0; $a<count($tempCart); $a++)
							{
								if(trim($QtyRS1->exclude_pocketperfume)=='Yes')
								{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}
								}
								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];

								$FreeSample = '';
								if(isset($tempCart[$a]["Is_Free_Sample"]))
									$FreeSample = $tempCart[$a]["Is_Free_Sample"];

								if($tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$subTotal+=$tempCart[$a]['TotPrice'];
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($QtyRS1[0]['type'] == '1')
									{
									$QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS1->quantity_discount_amount/100);
									Session::put('ShoppingCart.Cart.'.$a.'.QuantityItemWiseDiscout',$QuantityDiscountItemWise);
									}
								}
							}
							if($QtyRS1[0]['type'] == '0' && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{
									if(trim($QtyRS1->exclude_pocketperfume)=='Yes')
									{
											if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 &&  in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
											{
												continue;
											}
									}
									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

									$FreeSample = '';
									if(isset($tempCart[$a]["Is_Free_Sample"]))
										$FreeSample = $tempCart[$a]["Is_Free_Sample"];

									if($tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && $FreeSample != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
									{
										$QuantityDiscountItemWise = (($QtyRS1->quantity_discount_amount*100)/$TotalAmount);
										$QuantityDiscountCal = (($tempCart[$a]['TotPrice']  * $QuantityDiscountItemWise)/100);
										Session::put('ShoppingCart.Cart.'.$a.'.QuantityItemWiseDiscout',$QuantityDiscountCal);
									}
								}
							}
							if($IS_Any_Matched > 0  && $total_qty >=$QtyRS1->quantity)
							{
								if($QtyRS1[0]['type'] == '1')
								$QuantityDiscount = ( $subTotal * ($QtyRS1->quantity_discount_amount/100) );
								else
								$QuantityDiscount = $QtyRS1->quantity_discount_amount;
							}

							break;
						}
						else
						{
							$QuantityDiscount = 0;
						}
				   }

			   }
			}
			else
			{
				$QuantityDiscountFlag = '';
				$QuantityDiscount = 0;
			}
			Session::put('ShoppingCart.QuantityDiscount',NumberFormat($QuantityDiscount));
			Session::put('ShoppingCart.QuantityDiscountFlag',$QuantityDiscountFlag);
			$log['QuantityDiscount'] = $QuantityDiscount;
			$log['QuantityDiscountFlag'] = $QuantityDiscountFlag;
			addLog('ApplyQuantityDiscount',$log);
			return NULL;
		}
	}

	public function GetNetTotal()
	{
		$GiftCouponInfo = $this->GetAllCoupons('GiftCoupon');
		$giftWrapCharge = 0;
		if(Session::has('ShoppingCart.GiftWrapping'))
		{
			$giftWrap = Session::get('ShoppingCart.GiftWrapping');
			$giftWrapCharge = (float)$giftWrap['Charge'];
		}
		$ShippingSignature = 0;
		if(Session::has('ShoppingCart.ShippingSignature'))
			$ShippingSignature = (float)Session::get('ShoppingCart.ShippingSignature');

		$AllCharges = $this->GetAllCharges();
		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$TotalAmount = $SubTotal + $AllCharges['TotalCharges'];

		$AllDiscount = $this->GetAllDiscounts();

		$TotalDiscount = $AllDiscount['TotalDiscount'];
		$NetTotal = $TotalAmount - $TotalDiscount;

		if($NetTotal <= 0)
			$NetTotal = 0;

		return NumberFormat( $NetTotal );
	}

	public function ApplyGiftWrapping($ProductID='')
	{
		addLog('ApplyGiftWrappingStart');
		$shopcart = [];
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$shopcart = Session::get('ShoppingCart.Cart');
			$total_gift_charge = 0;
			$GiftWrappingCharge = config('Settings.GIFT_WRAPPING_CHARGE');
			for($i=0;$i<count($shopcart);$i++)
			{
				if((isset($shopcart[$i]['gift_wrap']) && $shopcart[$i]['gift_wrap']=='Yes') || ($ProductID != '' && $shopcart[$i]['ProductID'] == $ProductID))
				{
					$total_gift_charge+=$shopcart[$i]['Qty'] * $GiftWrappingCharge;
				}
			}
			$tempAry['Charge'] 	= NumberFormat($total_gift_charge);
			$tempAry['Applied'] = 'Yes';
			Session::put('ShoppingCart.GiftWrapping',$tempAry);
			addLog('ApplyGiftWrapping',$tempAry);
			return null;
		}
	}

	public function GetCreditLimitAmount()
	{
		$CreditAmt = 0;
		$RemainCreditLimit = 0;
		$CreditLimitFlag = 0;

		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		if ($normaluser && $CreditAmt = $normaluser->credit_limit > 0 && $normaluser->registration_type == 'M' && config('Settings.WHOLESALE_CREDIT_LIMIT') == 'Yes' && $normaluser->is_dropshipper != 'Yes')
		{
			$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
			if(Session::has('ShoppingCart.customer_remaining_credit_amount'))
				$RemainCreditLimit = $this->Make_Price(Session::get('ShoppingCart.customer_remaining_credit_amount'));

			$NetTotal = $this->GetNetTotal();
			$CreditAmt = $normaluser->credit_limit;
			if($CreditDiscount <= 0 && $NetTotal > 0){
				$CreditLimitFlag = 1;
			}elseif($CreditDiscount > 0){
				$CreditLimitFlag = 2;
			}else{
				$CreditAmt = 0;
			}
		}

		return ['CreditLimitFlag' => $CreditLimitFlag, 'CreditLimit' => $CreditAmt, 'RemainCreditLimit' => $RemainCreditLimit];
	}

	public function isCouponsAvailable()
	{
		if ($this->couponsAvailableCache !== null) {
			return $this->couponsAvailableCache;
		}
		$Today =date("Y-m-d");
		$CouponRS = Coupon::select('quantitydiscount_flag')
                            ->where('status','=','1')
                            ->where('start_date','<=',$Today)
                            ->where('end_date','>=',$Today)
                            ->get();

		$this->couponsAvailableCache = $CouponRS->isNotEmpty();

		return $this->couponsAvailableCache;
	}

	public function isSecondCouponsAvailable()
	{
		$CouponRS = Coupon::select('quantitydiscount_flag')
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'))
							->limit(2)
							->get();
		if($CouponRS && $CouponRS->count() >= 2)
			return true;
		else
			return false;
	}

	public function SetCartAttributes($cartpopup='')
	{

		$Attrs = [];
		$onlyGCPurchased = 1;
		$CheckGCPurchasedVal = 0;
		$RewardPointItemWiseTotal = 0;
		$critieostr = '';
		$IsVenderItem = "No";
		$IsCosmo = "No";
		$IsNandansons = "No";
		$IsPerfumePW = "No";
		$IsPCA = "No";
		$IsND = "No";
		$IsMaxaromaTwoDelivery = "No";
		$ISMax2day = "";
		$ISMaxTwoItem = "No";

		$IsGiftCertificateItem = $IsGiftCertificateItem1 = '';

		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$ShopCartItems = Session::get('ShoppingCart.Cart');
			$TempCart = [];
			foreach($ShopCartItems as $ShopItem)
			{
				$GiftChkOpt = "Yes";
				//if(isset($ShopItem['IS_Free_Gift']) && $ShopItem['IS_Free_Gift'] =="Yes")
				if((isset($ShopItem['IS_Free_Gift']) && $ShopItem['IS_Free_Gift'] =="Yes") || (isset($ShopItem['Is_Free_Sample']) && $ShopItem['Is_Free_Sample'] == 'Yes'))
					$GiftChkOpt = "No";

				$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$ShopItem);

				//if($ShopItem['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $ShopItem['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
				if($IsGiftCertificateItem == 'Yes')
				{
					$GiftChkOpt = "No";
					$CheckGCPurchasedVal = 1;
				}

				if(isset($ShopItem['IsGiftWrapProduct']) && $ShopItem['IsGiftWrapProduct'] == 'No')
					$GiftChkOpt = "No";
				if(isset($ShopItem['HandlingTimeStr']) && $ShopItem['HandlingTimeStr'] != '')
					$GiftChkOpt = "No";

				$IsGiftCertificateItem1 = $this->checkGiftCertificateItem('IsGiftCertificateItem',$ShopItem);

				//if($ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU1'))
				if($IsGiftCertificateItem1 == 'No'){
					$onlyGCPurchased = 0;
				}

				$ShopItem['ShowGiftChkOpt'] = $GiftChkOpt;
				$normaluser = Auth::user();
				if (Auth::guard('store')->check()) {
					$normaluser = Auth::guard('web')->user();
				}

				//if(Auth::user() && strtolower(Auth::user()->eusertype ?? '') == 'retailer' && Session::get('sess_icustomerid') > 0 && Session::get('etype') == 'M')
				if($normaluser && strtolower($normaluser->eusertype ?? '') == 'retailer' && Session::get('sess_icustomerid') > 0 && Session::get('etype') == 'M')
				{
					if(isset($ShopItem['PointMultipier']) && $ShopItem['PointMultipier'] > 0)
					{
						$ShopItem['RewardItemWise'] = $ShopItem['TotPrice'] * $ShopItem['PointMultipier'];
						$ShopItem['RewardItemWise'] = NumberFormat($ShopItem['RewardItemWise']);
						$RewardPointItemWiseTotal = $RewardPointItemWiseTotal + $ShopItem['RewardItemWise'];
					}
				}

				$critieostr.='{ id: "'.$ShopItem["SKU"].'", price: '.$ShopItem["Price"].', quantity: '.$ShopItem["Qty"].' } ,';

				if(((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") || (isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=='Yes') || (isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=='Yes') || (isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=='Yes') || (isset($ShopItem['IsND']) && $ShopItem['IsND']=='Yes')) && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
				{
					$IsVenderItem = "Yes";
				}
				if((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
				{
					$IsCosmo = "Yes";
				}
				if((isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
				{
					$IsNandansons = "Yes";
				}
				if((isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
				{
					$IsPerfumePW = "Yes";
				}
				if((isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
				{
					$IsPCA = "Yes";
				}
				if((isset($ShopItem['IsND']) && $ShopItem['IsND']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
				{
					$IsND = "Yes";
				}

				if(isset($ShopItem['IsMaxaromaTwoDelivery']) && $ShopItem['IsMaxaromaTwoDelivery']=="Yes" && $ShopItem['IsMaxaromaTwoDelivery']!='')
				{
					$IsMaxaromaTwoDelivery = "Yes";
					$ISMaxTwoItem = "Yes";
				}
				else
				{
					$ISMax2day = "No";
				}

				$TempCart[] = $ShopItem;
			}
			Session::put('ShoppingCart.Cart',$TempCart);
			Session::put('ShoppingCart.RewardPointItemWiseTotal', ceil($RewardPointItemWiseTotal));
		}

		/*if($ISMax2day=="No")
		{
			$IsMaxaromaTwoDelivery = "No";
		}*/
		//echo "dd".$IsMaxaromaTwoDelivery; exit;
		$onlyWireTrabsfer = 0;
		$onlyAmazonPaypal = 0;
		if(Session::get('payment_amount') > 0 && Session::get('sess_icustomerid') > 0 && Session::get('etype') == "M")
		{
			$NetTotal = $this->GetNetTotal();
			if($NetTotal <= Session::get('payment_amount')){
				$onlyAmazonPaypal = 1;
				$onlyWireTrabsfer = 1;
			}else if($NetTotal > Session::get('payment_amount')){
				$onlyAmazonPaypal = 0;
				$onlyWireTrabsfer = 1;
			}
		} else {
			$NetTotal = $this->GetNetTotal();
			if($NetTotal > 0  && $NetTotal <= 5000){
				$onlyAmazonPaypal = 1;
				$onlyWireTrabsfer = 1;
			}else if($NetTotal > 5000){
				$onlyAmazonPaypal=0;
				$onlyWireTrabsfer = 1;
			}
		}
		if(Session::has('ShoppingCart.GiftCoupon'))
		{
			$GiftCouponInfo = Session::get('ShoppingCart.GiftCoupon');
			if($GiftCouponInfo['Code'] !='' && $GiftCouponInfo['Code'] != null)
			{
				$new_total = NumberFormat($this->GetNetTotal() + $GiftCouponInfo['Value']);
				if($new_total <= $GiftCouponInfo["Applicable_Value"])
					Session::put('ShoppingCart.GiftCoupon.Value',$new_total);
				else
					Session::put('ShoppingCart.GiftCoupon.Value',$GiftCouponInfo['Applicable_Value']);
			}
		}

		$IsPaypalExpressCheckout ='No';
		$IsGoogleCheckout ='No';
		## Amazon Checkout Display Setting
		Session::forget('Afterpay');
		$Amazon_pay_Checkout ='No';
		$Afterpay_Checkout ='No';

		if($cartpopup=='')
		{
			$PaymentMethods = Cache::rememberForever('afterpay_payment_method_ca', function () {
			 return PaymentMethod::select(
						'pm_group_name',
						'pm_gateway_name',
						'pm_details'
					)
					->where('pm_status', 'Active')
					->get();
			});
			if($PaymentMethods->isNotEmpty() )
			{
			foreach($PaymentMethods as $PayeMethod)
			{
				if($PayeMethod->pm_group_name =='PAYMENT_AMAZONC')
				{
					$pm_details = unserialize($PayeMethod->pm_details);
					$payment_methods_settings = [];
					foreach ( $pm_details as $pm_var_name => $pm_var_value )
					{
						$payment_methods_settings[$pm_var_name] = $pm_var_value;
					}
				}
				if($PayeMethod->pm_group_name == 'PAYMENT_PAYWITHAMAZON')
				{
					$pm_details = unserialize($PayeMethod->pm_details);
					foreach ( $pm_details as $pm_var_name => $pm_var_value )
					{
						$payment_methods_settings[$pm_var_name] = $pm_var_value;
					}
					if(count($payment_methods_settings) > 0 && $payment_methods_settings['paywithamazon_Access_Key_Id'] !='' && $payment_methods_settings['paywithamazon_Secret_Key_ID'] !='' && $payment_methods_settings['paywithamazon_Merchant_Id'] != '')
					{
						$Amazon_pay_Checkout ='Yes';
					}
					config(['CLIENT_ID' => $this->decrypt($pm_details['paywithamazon_Client_ID'])]);
					config(['MERCHANT_ID' => $this->decrypt($pm_details['paywithamazon_Merchant_Id'])]);
					//config(['CALLBACK_URL' => url('/billing-amazon-checkout')]);
					config(['CALLBACK_URL' => url('/setupamazon')]);
					config(['CALLBACK_CHECKOUT_URL' => url('amazon/checkoutlogin')]);

					if(strtoupper(trim($payment_methods_settings['paywithamazon_Transaction_Mode'])) == 'SANDBOX'){
						config(['JS_SERVER_URL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js?sellerId='.config('MERCHANT_ID')]);
					}else{
						config(['JS_SERVER_URL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js?sellerId='.config('MERCHANT_ID')]);
					}
				}

				if($PayeMethod->pm_group_name == 'PAYMENT_PAYPALEC')
				{
					$arrPEVar	= unserialize($PayeMethod->pm_details);
					$Mode 		= trim(strtolower($arrPEVar['paypalec_Transaction_Mode'] ?? ''));
					$client_id 	= $this->decrypt($arrPEVar['paypalec_Username']);
					//$Mode  		= "sandbox";
					if(isset($Mode)&& $Mode == 'live')
					{
						$Attrs['PaypalClientID'] = $client_id;
					}
					else
					{
						$client_id 	= 'AQymJLkSRgzhHf0AjiYOGL_OHQZ60bCggeySkd8F31n_2ery6HK7HXYQGeeBCfszGgAin8XfJbvZuByn';
						$Attrs['PaypalClientID'] = $client_id;
					}
					if($this->Is_WholeSaler_Allow() == false)
						$IsPaypalExpressCheckout ='No';
					else
						$IsPaypalExpressCheckout ='Yes';
				}
				if($PayeMethod->pm_group_name == 'PAYMENT_PAYWITHAFTERPAY')
				{
					$normaluser = Auth::user();
					if (Auth::guard('store')->check()) {
						$normaluser = Auth::guard('web')->user();
					}
					$Afterpay_Checkout ='Yes';
					//if(Auth::user() && Auth::user()->eusertype == 'Wholesaler')
					if($normaluser && $normaluser->eusertype == 'Wholesaler')
						$Afterpay_Checkout ='No';
				}
			}
		}

		$show_AP = 'Yes';
		if($Afterpay_Checkout == "Yes" && $show_AP == "Yes"){
			if(!Session::has('isPhoneOrder')){//15022024
				$this->AfterpayMinMax();
			}
		}
		}
		$CouponCode = $this->GetAllCoupons('CouponCode');
		$CouponCodeSecond = $this->GetAllCoupons('CouponCodeSecond');
		$YotpoCouponCode ='';
		if(Session::has('ShoppingCart.YotpoRewardCode') && Session::get('ShoppingCart.YotpoRewardCode') != '')
		{
			$YotpoCouponCode = Session::get('ShoppingCart.YotpoRewardCode');
		}
		if($cartpopup=='')
		{
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//$CustomerID = (Auth::user() ? Session::get('sess_icustomerid'):null);
			$CustomerID = ($normaluser ? Session::get('sess_icustomerid'):null);
			if($CouponCode != ''){
				$this->ApplyCouponDiscount($CouponCode,$CustomerID);
			}
			if($CouponCodeSecond != '')
				$this->ApplyCouponDiscountSecond($CouponCodeSecond,$CustomerID);

			$this->ApplyItemWiseCoupon($CouponCode);

		}

		if($cartpopup=='')
		{
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//$CustomerID = (Auth::user() ? Session::get('sess_icustomerid'):null);
			$CustomerID = ($normaluser ? Session::get('sess_icustomerid'):null);
			if($YotpoCouponCode != ''){
				$this->ApplyCouponDiscount($YotpoCouponCode,$CustomerID);
			}
		}

		//15022024 to check phone order auto discount start
		if(Session::has('isPhoneOrder')){
			$this->ApplyAutoDiscount();
		}
		//15022024 to check phone order auto discount end

		$this->Is_WholeSaler_Allow(true);

		$DiscountInfo = $this->GetAllDiscounts();

		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$TotalValue = ($SubTotal - $DiscountInfo['TotalDiscount']);
		$Attrs['TotalValue'] = $TotalValue;

		if(config('Settings.CHECKOUT_SHOIPPINGCART') == "No")
		{
			$this->RemoveFreeGiftCache();
		}

		if($cartpopup=='')
		{
			$CreditData = $this->GetCreditLimitAmount();
			$Attrs = array_merge($Attrs,$CreditData);
		}
		if(Session::has('ShoppingCart.Reward_array') && count(Session::get('ShoppingCart.Reward_array')) > 0)
		{
			$Attrs['RemainRewardPoint'] = Session::get('ShoppingCart.Reward_array.RemainRewardPoint');
			$Attrs['TotalRewardPoint'] = Session::get('ShoppingCart.Reward_array.TotalRewardPoint');
			$Attrs['AppliedRewardPoint'] = Session::get('ShoppingCart.Reward_array.AppliedRewardPoint');
		}
		$Attrs['IsVenderItem'] = $IsVenderItem;
		$Attrs['IsCosmo'] = $IsCosmo;
		$Attrs['IsNandansons'] = $IsNandansons;
		$Attrs['IsPerfumePW'] = $IsPerfumePW;
		$Attrs['IsPCA'] = $IsPCA;
		$Attrs['IsND'] = $IsND;
		$Attrs['IsMaxaromaTwoDelivery'] = $IsMaxaromaTwoDelivery;
		$Attrs['ISMaxTwoItem'] = $ISMaxTwoItem;
		$Attrs['onlyGCPurchased'] = $onlyGCPurchased;
		$Attrs['onlyAmazonPaypal'] = $onlyAmazonPaypal;
		$Attrs['onlyWireTrabsfer'] = $onlyWireTrabsfer;
		$Attrs['CheckGCPurchasedVal'] = $CheckGCPurchasedVal;
		$Attrs['ISMax2dayVal'] = $ISMax2day;
		if($cartpopup=='')
		{
			$Attrs['isCouponsAvailable'] = $this->isCouponsAvailable();
		}
		$Attrs['coupon_number'] = $CouponCode;
		if($cartpopup=='')
		{
			//$Attrs['isSecondCouponAvalilabe'] = $this->isSecondCouponsAvailable(); // Comment not used
		}
		$Attrs['second_coupon_number'] = $CouponCodeSecond;
		if($cartpopup=='')
		{
			$Attrs['isGiftCouponsAvailable'] = $this->isGiftCouponsAvailable();
		}
		$Attrs['Amazon_pay_Checkout'] = $Amazon_pay_Checkout;
		$Attrs['IsPaypalExpressCheckout'] = $IsPaypalExpressCheckout;
		$Attrs['Afterpay_Checkout'] = $Afterpay_Checkout;
		if($cartpopup=='')
		{
			$Attrs['show_AP'] = $show_AP;
		}
		$Attrs['critieostr'] = ($critieostr != '' ? substr($critieostr,0,-1):'');
		$Attrs['allow_gift'] = 0;
		if(strtolower(trim(Session::get('eusertype') ?? '')) !="wholesaler" && trim(Session::get('is_dropshipper') ?? '') != "Yes" )
		{
			$Attrs['allow_gift'] = 1;
		}
		$Attrs['FundFlag'] = 0;
		$Attrs['available_funds'] = 0;
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(Auth::user() && Auth::user()->is_dropshipper == "Yes" && Auth::user()->eusertype == "Wholesaler")
		if($normaluser && $normaluser->is_dropshipper == "Yes" && $normaluser->eusertype == "Wholesaler")
		{
			$Attrs['FundFlag'] = 1;
			/*if(Auth::user()->available_funds > 0)
				$Attrs['available_funds'] = Auth::user()->available_funds;*/
			if($normaluser->available_funds > 0)
				$Attrs['available_funds'] = $normaluser->available_funds;
		}

		$CartItem = Session::get('ShoppingCart.Cart');
		$grouped = collect($CartItem)->groupBy('OrderType');
		$storeCount = $grouped->get('Store', collect())->count();
		$websiteCount = $grouped->get('Website', collect())->count();

		Session::put('ShoppingCart.OrderType', "Website");
		if ($storeCount > 0 && $websiteCount > 0) {
			Session::put('ShoppingCart.OrderType', "Both");
		} else if ($storeCount > 0) {
			Session::put('ShoppingCart.OrderType', "Store");
		}

		return $Attrs;

	}

	function ApplyAutoRewardDiscount()
    {
		if(config('global.YOTPO_PROG') == false)
        {
			return null;
		}
		Session::forget('ShoppingCart.Reward_array');
		$NetTotal = $this->GetNetTotal();
		$AllDiscount = $this->GetAllDiscounts();
		$discount = $AllDiscount['TotalDiscount'];
		$subtotal = NumberFormat($NetTotal - $discount);
		$reward_discount = 0;
		if(Session::get('etype') == 'M' && strtolower(Session::get('eusertype') ?? '')=='retailer')
		{
			$Customer_Reward = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->where('status','=','1')->get();
			$Redeem_Reward = RewardRule::where('erewardrule','=','redeem')->get();
			$Max_Reward = RewardRule::where('erewardrule','=','max')->get();

			if($Customer_Reward && $Customer_Reward->count() > 0 && $Max_Reward && $Max_Reward->count($Max_Reward) > 0 )
			{
				if($Customer_Reward[0]['iRewardpoint'] >= $Max_Reward[0]["fcharge"])
				{
					$refer_amount = ($Customer_Reward[0]['iRewardpoint']/$Redeem_Reward[0]["fcharge"]);
					$reward_discount = (int)$refer_amount*$Redeem_Reward[0]["forderamount"];
					$remain_count = $Redeem_Reward[0]["fcharge"] * (int)$refer_amount;
					$reward_remaining = $Customer_Reward[0]['iRewardpoint'] - $remain_count;
					$Total_Reward_Point = $Customer_Reward[0]['iRewardpoint'];

					$temp_reward =array();
					if(NumberFormat($reward_discount) < $subtotal)
					{
						$temp_reward['RemainRewardPoint'] = NumberFormat($reward_remaining);
						$temp_reward['TotalRewardPoint'] = NumberFormat($Total_Reward_Point);
						$temp_reward['RewardDiscount'] = NumberFormat($reward_discount);
						$temp_reward['AppliedRewardPoint'] = NumberFormat($remain_count);
						Session::put('ShoppingCart.Reward_array',$temp_reward);
					}
				}
			}
			return NULL;
		}
	}

	public function GetCartAttribute($Attribute='')
	{
		if($Attribute != '')
		{
			$CartAttr = $this->SetCartAttributes();
			if(isset($CartAttr[$Attribute]))
				return $CartAttr[$Attribute];
		}
	}
	public function isGiftCouponsAvailable()
	{
		if(config('Settings.GIFTCERTIFICATEFLAG')=="No")
		{
			return false;
		}
		if ($this->giftCouponsAvailableCache !== null) {
            return $this->giftCouponsAvailableCache;
        }

		 $CouponRS = GiftCertificate::select('gc_id')->where('remaining_value', '>', 0)
        ->where('status', '=', '1')
        ->get();

		$this->giftCouponsAvailableCache = $CouponRS->isNotEmpty();

        return $this->giftCouponsAvailableCache;

	}

	public function StoreShopCartInCookie()
	{
		$IsGiftCertificateItem = '';

		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$tempCart = Session::get('ShoppingCart.Cart');
			$ArrMyCookie = array();
			for($p = 0; $p < count($tempCart); $p++)
			{
                if(isset($tempCart[$p]['IsYotpoFreeProduct']) && $tempCart[$p]['IsYotpoFreeProduct'] == 'Yes')
                    continue;
				$temp_ary = array();
				$temp_ary['SKU'] = $tempCart[$p]['SKU'];
				$temp_ary['Qty'] = $tempCart[$p]['Qty'];

				$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$tempCart[$p]);

			   //if($tempCart[$p]['SKU'] == config('Settings.GIFT_CERTIFICATE_SKU') || $tempCart[$p]['SKU'] == config('Settings.GIFT_CERTIFICATE_SKU1') || $tempCart[$p]['SKU'] == config('Settings.GIFT_CERTIFICATE_SKU2'))
			   if($IsGiftCertificateItem == 'Yes')
				{
					$temp_ary['RecipientName']		= $tempCart[$p]['RecipientName'];
					$temp_ary['RecipientEmail']		= $tempCart[$p]['RecipientEmail'];
					$temp_ary['YourName']			= $tempCart[$p]['YourName'];
					$temp_ary['YourEmail']			= $tempCart[$p]['YourEmail'];
					$temp_ary['Subject']			= $tempCart[$p]['Subject'];
					$temp_ary['Message']			= $tempCart[$p]['Message'];
					//$temp_ary['Signature']			= $tempCart[$p]['Signature'];
					$temp_ary['DeliveryDate']		= $tempCart[$p]['DeliveryDate'];
					$temp_ary['GCPrice'] 			= $tempCart[$p]['Price'];
					$temp_ary['GCPrice'] 			= $tempCart[$p]['Price'];
					$temp_ary['SKU'] 				= $tempCart[$p]['SKU'];
					$temp_ary['GiftImage'] 			= $tempCart[$p]['GiftImage'];
				}
				$ArrMyCookie[] = $temp_ary;
			}
			 ## First Delete The Old Cart From Table
			if(Cookie::has("MY_SHOP_CART_COOKIE") && Cookie::get("MY_SHOP_CART_COOKIE") != "" )
			{
				$cookie_id = Cookie::get("MY_SHOP_CART_COOKIE");
				Shoppingcart::where('cookie_id','=',$cookie_id)->where('customer_id','=',0)->delete();
                /*Shoppingcart::where('cookie_id','=',$cookie_id)->where('customer_id','=',0)->chunk(5000, function ($SCarts) {
                    foreach($SCarts as $SCart) {
                        $SCart->delete();
                    }
                    sleep(2);
                });*/
			}
			if(count($ArrMyCookie)>0)
			{
				$cookie_id = time()."_".Session::getId();
				Cookie::queue(Cookie::make('MY_SHOP_CART_COOKIE',$cookie_id,time()+60*60*24*15));
				$normaluser = Auth::user();
				if (Auth::guard('store')->check()) {
					$normaluser = Auth::guard('web')->user();
				}
				//if(Auth::user())
				if($normaluser)
				{
					$result = Shoppingcart::where('customer_id','=',Session::get('sess_icustomerid'))->get();

					if($result && $result->count() <=0)
					{
						$InsertCart = array(
										'customer_id' 		=> Session::get('sess_icustomerid'),
										'cookie_id' 		=> $cookie_id,
										'cart_string' 		=> serialize($ArrMyCookie),
										'created_date' 		=> date("Y-m-d H:i:s")
									);
						DB::table('pu_shoppingcart')->insert($InsertCart);
					}else{
						$UpdateCart = array(
										'cookie_id' 		=> $cookie_id,
										'cart_string' 		=> serialize($ArrMyCookie),
										'created_date' 		=> date("Y-m-d H:i:s")
								  );
						DB::table('pu_shoppingcart')->where('customer_id','=',Session::get('sess_icustomerid'))->update($UpdateCart);
					}
				}else{
					$InsertCart = array(
									'customer_id' 		=> '0',
									'cookie_id' 		=> $cookie_id,
									'cart_string' 		=> serialize($ArrMyCookie),
									'created_date' 		=> date("Y-m-d H:i:s")
								);
					DB::table('pu_shoppingcart')->insert($InsertCart);
				}
			}
		} else {
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//if(Auth::user())
			if($normaluser)
			{
				Shoppingcart::where('customer_id','=',Session::get('sess_icustomerid'))->delete();
			} else {
				$cookie_id = Cookie::get("MY_SHOP_CART_COOKIE");
				Shoppingcart::where('cookie_id','=',$cookie_id)->where('customer_id','=',0)->delete();
                /*Shoppingcart::where('cookie_id','=',$cookie_id)->where('customer_id','=',0)->chunk(5000, function ($SCarts) {
                    foreach($SCarts as $SCart) {
                        $SCart->delete();
                    }
                    sleep(2);
                });*/
				$cookie_id = time()."_".Session::getId();
				Cookie::queue(Cookie::make('MY_SHOP_CART_COOKIE',$cookie_id,time()+60*60*24*15));
			}
		}
	}

	public function ApplyDogoDiscount_bk_2025_11_18()
	{
		$pocketPerfumeCategory = $this->getPocketPerfumeCategory();

		$IsGiftCertificateItem = $IsGiftCertificateItem1 = $IsGiftCertificateItem2 = '';

		$log['pocketPerfumeCategory'] = json_encode($pocketPerfumeCategory);
		addLog("ApplyDogoDiscountStart",$log);

		$DogoDiscount = 0;
		$BogoItemWiseDiscout = 0;
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0 )
		{
			$this->getAllDiscountBlank("Bogo");
			$CartInfo = Session::get('ShoppingCart.Cart');
			$tempCart1 = Session::get('ShoppingCart.Cart');
			$GiftCertiTotal = 0;
			$GiftCertiCount = 0;
			if(Session::has('ShoppingCart.GiftCertiTotal') && Session::get('ShoppingCart.GiftCertiTotal') != '')
				$GiftCertiTotal = Session::get('ShoppingCart.GiftCertiTotal');
			if(Session::has('ShoppingCart.GiftCertiCount') && Session::get('ShoppingCart.GiftCertiCount') != '')
				$GiftCertiCount = Session::get('ShoppingCart.GiftCertiCount');

			$subTotal = Session::get('ShoppingCart.SubTotal') - $GiftCertiTotal;

			$Cart = Session::get('ShoppingCart.Cart');
			$TotalItem 	= Session::get('ShoppingCart.TotalItemInCart');
			$DogoDiscountFlag = '';
			if($subTotal <= 0 || $TotalItem <= 0)
			{
				Session::put('ShoppingCart.DogoDiscount',0);
				return null;
			}
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			if($normaluser && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.DogoDiscount',0);
				return null;
			}
			if(Session::has('isPhoneOrder') && Session::has('eusertype') && Session::get('eusertype') == "Wholesaler"){
				Session::put('ShoppingCart.DogoDiscount',0);
				return null;
			}
			$CouponCode = $this->GetAllCoupons('CouponCode');
			if($CouponCode !='')
			{
				$coupon_res = Coupon::select('bogodiscount_flag')->where('coupon_number','=',$CouponCode)
								->where('status','=','1')
								->where('start_date','<=',DB::raw('curdate()'))
								->where('end_date','>=',DB::raw('curdate()'))
								->get();
				if($coupon_res && $coupon_res->count() > 0)
				{
					$log['coupon_res'] = json_encode($coupon_res);
					addLog("ApplyDogoDiscount",$log);
					if($coupon_res[0]->bogodiscount_flag == "No")
					{
						Session::put('ShoppingCart.DogoDiscount',0);
						Session::put('ShoppingCart.BogoDiscountFlag','');
						$log['DogoDiscount'] = "0";
						$log['BogoDiscountFlag'] = "";
						addLog("ApplyDogoDiscount",$log);
						return null;
					}
				}
			}

			$DogoRS = BogoDiscount::where('start_date','<=',DB::raw('curdate()'))
						->where('end_date','>=',DB::raw('curdate()'))
						->where('status','=','1')->orderBy('bogo_discount_id','desc')->get();

			if($DogoRS && $DogoRS->count() > 0)
			{
				$log['DogoRS'] = json_encode($DogoRS);
			   $DogoDiscount = 0;
			   for($i=0;$i < $DogoRS->count();$i++)
			   {
				   if($DogoRS[$i]["orders"]=='2')
				   {
						$QtySKU = trim($DogoRS[$i]['sku']);
						########### For Multiple SKU ###############
						$arr_QtySKU  = explode(",",$QtySKU);
						$arr_QtySKU  = array_unique(array_map('trim',$arr_QtySKU));
						$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');
						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;
						$SKUArrValCheck = array();
						if(is_array($arr_QtySKU) and !empty($arr_QtySKU))
						{
							$CartVal  = Session::get('ShoppingCart.Cart');
							$tempCart = array();
							for($a=0;$a<count($CartVal); $a++)
							{

								$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$CartVal[$a]);
								$FreeGiftCheck = isset($CartVal[$a]["IS_Free_Gift"])?$CartVal[$a]["IS_Free_Gift"]:'No';

								$FreeSample = '';
								if(isset($CartVal[$a]["Is_Free_Sample"]))
									$FreeSample = $CartVal[$a]["Is_Free_Sample"];
								if($IsGiftCertificateItem == 'No' && in_array($CartVal[$a]['SKU'] , $arr_QtySKU) && $CartVal[$a]["IsDealProducts"]!="Yes" && $FreeGiftCheck != "Yes" && $FreeSample != "Yes")
								{
									if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($CartVal[$a]['CategoryID']) && in_array($CartVal[$a]['CategoryID'] ,$pocketPerfumeCategory))
									{
										continue;
									}

									if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
										$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
										if(count($exclude_skus_arr) > 0){
											if (in_array($CartVal[$a]['SKU'], $exclude_skus_arr)){
												continue;
											}
										}
									}

									$SKUArrValCheck[] = $CartVal[$a]['SKU'];
									$tempCart[] = $CartVal[$a];
								}
							}
							if(count($tempCart) > 0)
							{
								$ItemQtyArr =  array();
								foreach ($tempCart as $array)
								{
								  $ItemQtyArr= array_merge($ItemQtyArr, array_fill(0, $array['Qty'], $array));
								}

								$prices = array_column($ItemQtyArr, 'Price');
								$quantites = array_column($tempCart, 'Qty');

								$modCount =0;
								if(intdiv(array_sum($quantites), 2) >= 1){
									$modCount  = intdiv(array_sum($quantites), 2);
								}
							//	echo "<pre>"; print_r($ItemQtyArr); exit;

								if($DogoRS[$i]['sortBy']=="High")
								{
									rsort($prices);
								}
								else if($DogoRS[$i]['sortBy']=="Low")
								{
									sort($prices);
								}
								$DogoDiscount = 0;
								for ($a=0; $a<$modCount; $a++)
								{
									$DogoDiscount = $DogoDiscount + $prices[$a];
								}
								for($d=0;$d<count($tempCart1);$d++)
								{
									if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($tempCart1[$d]['CategoryID']) && in_array($tempCart1[$d]['CategoryID'] ,$pocketPerfumeCategory))
									{
										continue;
									}

									if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
										$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
										if(count($exclude_skus_arr) > 0){
											if (in_array($tempCart1[$d]['SKU'], $exclude_skus_arr)){
												continue;
											}
										}
									}
									$MatchTotal = 0;
									for ($a=0; $a<$modCount; $a++)
									{
										if(isset($prices[$a]) && $tempCart1[$d]["Price"]==$prices[$a] && in_array($tempCart1[$d]["SKU"],$SKUArrValCheck))
										{
										  $MatchTotal = $MatchTotal + $prices[$a];
										}

									}
									if(!empty($MatchTotal) && $MatchTotal > 0)
									{
										Session::put('ShoppingCart.Cart.'.$d.'.BogoItemWiseDiscout',NumberFormat($MatchTotal));
									}
								}

							}

						}
					  }

					else if($DogoRS[$i]["orders"]=='0')
					{

						//echo "Test";
						$QtyCatID = trim($DogoRS[$i]['sku']);
						$arr_QtyCatID    = explode(",",$QtyCatID);

						//$DogoDiscount = 0; Commented on 16122022
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_QtyCatID)->get();
						$arr_active_CatID = array();
						for($h=0;$h<count($Res_active_CatID);$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
						$SKUArrValCheck = array();
						if(count($arr_active_CatID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = ProductsCategory::select('products_id')->distinct()
								->whereIn('category_id',$arr_active_CatID)
								->whereIn('products_id',$temp_prod_id)->get();

							$cat_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;

							$CartVal  = Session::get('ShoppingCart.Cart');
							$tempCart = array();
							for($a=0;$a<count($CartVal); $a++)
							{

								if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($CartVal[$a]['CategoryID']) && in_array($CartVal[$a]['CategoryID'] ,$pocketPerfumeCategory))
								{
									continue;
								}

								if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
									$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
									if(count($exclude_skus_arr) > 0){
										if (in_array($CartVal[$a]['SKU'], $exclude_skus_arr)){
											continue;
										}
									}
								}

								if(!isset($CartVal[$a]["IS_Free_Gift"]))
									$CartVal[$a]["IS_Free_Gift"]="No";
								if(!isset($CartVal[$a]["IsDealProducts"]))
									$CartVal[$a]["IsDealProducts"]="No";

								$FreeSample = '';
								if(isset($CartVal[$a]["Is_Free_Sample"]))
									$FreeSample = $CartVal[$a]["Is_Free_Sample"];

								$IsGiftCertificateItem1 = $this->checkGiftCertificateItem('IsGiftCertificateItem',$CartVal[$a]);

								//if($CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU1') && $CartVal[$a]['SKU'] != config('global.GIFT_CERTIFICATE_SKU2') && $CartVal[$a]['SKU'] != config('global.GIFT_CERTIFICATE_SKU3') && in_array($CartVal[$a]['ProductID'] , $cat_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" &&  isset($CartVal[$a]["IS_Free_Gift"]) && $CartVal[$a]["IS_Free_Gift"]!="Yes")
								if($IsGiftCertificateItem1 == 'No' && in_array($CartVal[$a]['ProductID'] , $cat_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" &&  isset($CartVal[$a]["IS_Free_Gift"]) && $CartVal[$a]["IS_Free_Gift"]!="Yes" && $FreeSample != "Yes")
								{
									$tempCart[] = $CartVal[$a];
									$SKUArrValCheck[] = $CartVal[$a]["SKU"];
								}
							}
							if(count($tempCart) > 0)
							{
								$ItemQtyArr =  array();
								foreach ($tempCart as $array)
								{
								  $ItemQtyArr= array_merge($ItemQtyArr, array_fill(0, $array['Qty'], $array));
								}

								$prices = array_column($ItemQtyArr, 'Price');
								$quantites = array_column($tempCart, 'Qty');

								$modCount =0;
								if(intdiv(array_sum($quantites), 2) >= 1){
									$modCount  = intdiv(array_sum($quantites), 2);
								}

								if($DogoRS[$i]['sortBy']=="High")
								{
									rsort($prices);
								}
								else if($DogoRS[$i]['sortBy']=="Low")
								{
									sort($prices);
								}

								$DogoDiscount = 0;
								for ($a=0; $a<$modCount; $a++)
								{
									$DogoDiscount = $DogoDiscount + $prices[$a];
								}

								for($d=0;$d<count($tempCart1);$d++)
								{
									if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($tempCart1[$d]['CategoryID']) && in_array($tempCart1[$d]['CategoryID'] ,$pocketPerfumeCategory))
									{
										continue;
									}

									if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
										$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
										if(count($exclude_skus_arr) > 0){
											if (in_array($tempCart1[$d]['SKU'], $exclude_skus_arr)){
												continue;
											}
										}
									}

									$MatchTotal = 0;
									for ($a=0; $a<$modCount; $a++)
									{
										if(isset($prices[$a]) && $tempCart1[$d]["Price"]==$prices[$a] && in_array($tempCart1[$d]["SKU"],$SKUArrValCheck))
										{
										  $MatchTotal = $MatchTotal + $prices[$a];
										}

									}
									if(!empty($MatchTotal) && $MatchTotal > 0)
									{
										Session::put('ShoppingCart.Cart.'.$d.'.BogoItemWiseDiscout',NumberFormat($MatchTotal));
									}
								}

							}

						}
					}
					else if($DogoRS[$i]["orders"]=='1')
					{
						$QtySKU = trim($DogoRS[$i]['sku']);
						########### For Multiple SKU ###############
						$QtyBrandID    	= trim($DogoRS[$i]['sku']); // Category IDS
						$arr_QtyBrandID    = explode(",",$QtyBrandID);

						//$Dogodiscount = 0; Commented on 16122022
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')->whereIn('imanufactureid',$arr_QtyBrandID)->get();
						$arr_active_BrandID = array();

						for($h=0;$h<$Res_active_BrandID->count();$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}
						$SKUArrValCheck = array();
						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = Products::select('products_id')->distinct()
								->whereIn('imanufactureid',$arr_active_BrandID)
								->whereIn('products_id',$temp_prod_id)->get();

							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							//print_r($brand_prod_id);
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							$tempCart = array();
							$CartVal  = Session::get('ShoppingCart.Cart');

							for($a=0;$a<count($CartVal); $a++)
							{
								if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($CartVal[$a]['CategoryID']) && in_array($CartVal[$a]['CategoryID'] ,$pocketPerfumeCategory))
								{
									continue;
								}

								if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
									$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
									if(count($exclude_skus_arr) > 0){
										if (in_array($CartVal[$a]['SKU'], $exclude_skus_arr)){
											continue;
										}
									}
								}

								if(!isset($CartVal[$a]["IS_Free_Gift"]))
									$CartVal[$a]["IS_Free_Gift"]="No";
								if(!isset($CartVal[$a]["IsDealProducts"]))
									$CartVal[$a]["IsDealProducts"]="No";

								$FreeSample = '';
								if(isset($CartVal[$a]["Is_Free_Sample"]))
									$FreeSample = $CartVal[$a]["Is_Free_Sample"];

								$IsGiftCertificateItem2 = $this->checkGiftCertificateItem('IsGiftCertificateItem',$CartVal[$a]);

								//if($CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU1') && $CartVal[$a]['SKU'] != config('global.GIFT_CERTIFICATE_SKU2') && $CartVal[$a]['SKU'] != config('global.GIFT_CERTIFICATE_SKU3') && in_array($CartVal[$a]['ProductID'] , $brand_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" && $CartVal[$a]["IS_Free_Gift"]!="Yes")
								if($IsGiftCertificateItem2 == 'No' && in_array($CartVal[$a]['ProductID'] , $brand_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" && $CartVal[$a]["IS_Free_Gift"]!="Yes" && $FreeSample != "Yes")
								{
									$tempCart[] = $CartVal[$a];
									$SKUArrValCheck[]=$CartVal[$a]["SKU"];
								}

							}

							if(count($tempCart) > 0)
							{
								$ItemQtyArr =  array();
								foreach ($tempCart as $array)
								{
								  $ItemQtyArr= array_merge($ItemQtyArr, array_fill(0, $array['Qty'], $array));
								}

								$prices = array_column($ItemQtyArr, 'Price');
								$quantites = array_column($tempCart, 'Qty');

								$modCount =0;
								if(intdiv(array_sum($quantites), 2) >= 1){
									$modCount  = intdiv(array_sum($quantites), 2);
								}
							//	echo "<pre>"; print_r($ItemQtyArr); exit;

								if($DogoRS[$i]['sortBy']=="High")
								{
									rsort($prices);
								}
								else if($DogoRS[$i]['sortBy']=="Low")
								{
									sort($prices);
								}
								$DogoDiscount = 0;
								for ($a=0; $a<$modCount; $a++)
								{
									$DogoDiscount = $DogoDiscount + $prices[$a];
								}
								for($d=0;$d<count($tempCart1);$d++)
								{
									if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" &&  isset($tempCart1[$d]['CategoryID'])  && in_array($tempCart1[$d]['CategoryID'] ,$pocketPerfumeCategory))
									{
										continue;
									}

									if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
										$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
										if(count($exclude_skus_arr) > 0){
											if (in_array($tempCart1[$d]['SKU'], $exclude_skus_arr)){
												continue;
											}
										}
									}

									$MatchTotal = 0;
									for ($a=0; $a<$modCount; $a++)
									{
										if(isset($prices[$a]) && $tempCart1[$d]["Price"]==$prices[$a] && in_array($tempCart1[$d]["SKU"],$SKUArrValCheck))
										{
										  $MatchTotal = $MatchTotal + $prices[$a];
										}

									}
									if(!empty($MatchTotal) && $MatchTotal > 0)
									{
										Session::put('ShoppingCart.Cart.'.$d.'.BogoItemWiseDiscout',NumberFormat($MatchTotal));
									}
								}
							}
						}
					}

				}
			}else{
				$DogoDiscount = 0;
			}
		//	echo "<pre>"; print_r(Session::get('ShoppingCart.Cart')); exit;
			//echo "<br>DogoDiscount 4 --- ".$DogoDiscount;
			Session::put('ShoppingCart.DogoDiscount',NumberFormat($DogoDiscount));
			$log['DogoDiscount'] = $DogoDiscount;
			addLog("ApplyDogoDiscount",$log);
		}
		//echo "<br><b>DogoDiscount Final </b> --- ".$DogoDiscount;
	}
	public function ApplyDogoDiscount()
	{
		$pocketPerfumeCategory = $this->getPocketPerfumeCategory();

		$IsGiftCertificateItem = $IsGiftCertificateItem1 = $IsGiftCertificateItem2 = '';

		$log['pocketPerfumeCategory'] = json_encode($pocketPerfumeCategory);
		addLog("ApplyDogoDiscountStart",$log);

		$DogoDiscount = 0;
		$BogoItemWiseDiscout = 0;
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0 )
		{
			$this->getAllDiscountBlank("Bogo");
			$CartInfo = Session::get('ShoppingCart.Cart');
			$tempCart1 = Session::get('ShoppingCart.Cart');
			$GiftCertiTotal = 0;
			$GiftCertiCount = 0;
			if(Session::has('ShoppingCart.GiftCertiTotal') && Session::get('ShoppingCart.GiftCertiTotal') != '')
				$GiftCertiTotal = Session::get('ShoppingCart.GiftCertiTotal');
			if(Session::has('ShoppingCart.GiftCertiCount') && Session::get('ShoppingCart.GiftCertiCount') != '')
				$GiftCertiCount = Session::get('ShoppingCart.GiftCertiCount');

			$subTotal = Session::get('ShoppingCart.SubTotal') - $GiftCertiTotal;

			$Cart = Session::get('ShoppingCart.Cart');
			$TotalItem 	= Session::get('ShoppingCart.TotalItemInCart');
			$DogoDiscountFlag = '';
			if($subTotal <= 0 || $TotalItem <= 0)
			{
				Session::put('ShoppingCart.DogoDiscount',0);
				return null;
			}
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			if($normaluser && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.DogoDiscount',0);
				return null;
			}
			if(Session::has('isPhoneOrder') && Session::has('eusertype') && Session::get('eusertype') == "Wholesaler"){
				Session::put('ShoppingCart.DogoDiscount',0);
				return null;
			}
			$CouponCode = $this->GetAllCoupons('CouponCode');
			if($CouponCode !='')
			{
				$coupon_res = Coupon::select('bogodiscount_flag')->where('coupon_number','=',$CouponCode)
								->where('status','=','1')
								->where('start_date','<=',DB::raw('curdate()'))
								->where('end_date','>=',DB::raw('curdate()'))
								->get();
				if($coupon_res && $coupon_res->count() > 0)
				{
					$log['coupon_res'] = json_encode($coupon_res);
					addLog("ApplyDogoDiscount",$log);
					if($coupon_res[0]->bogodiscount_flag == "No")
					{
						Session::put('ShoppingCart.DogoDiscount',0);
						Session::put('ShoppingCart.BogoDiscountFlag','');
						$log['DogoDiscount'] = "0";
						$log['BogoDiscountFlag'] = "";
						addLog("ApplyDogoDiscount",$log);
						return null;
					}
				}
			}
			for ($c=0; $c<count($Cart); $c++)
			{
				Session::put('ShoppingCart.Cart.' . $c . '.BogoDiscountMessage',"");
			}
			$DogoRS = BogoDiscount::where('start_date','<=',DB::raw('curdate()'))
						->where('end_date','>=',DB::raw('curdate()'))
						->where('status','=','1')->orderBy('bogo_discount_id','desc')->get();

			if($DogoRS && $DogoRS->count() > 0)
			{
				$log['DogoRS'] = json_encode($DogoRS);
			   $DogoDiscount = 0;
			   for($i=0;$i < $DogoRS->count();$i++)
			   {
				   if($DogoRS[$i]["orders"]=='2')
				   {
						$QtySKU = trim($DogoRS[$i]['sku']);
						########### For Multiple SKU ###############
						$arr_QtySKU  = explode(",",$QtySKU);
						$arr_QtySKU  = array_unique(array_map('trim',$arr_QtySKU));
						$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');
						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;
						$SKUArrValCheck = array();
						if(is_array($arr_QtySKU) and !empty($arr_QtySKU))
						{
							$CartVal  = Session::get('ShoppingCart.Cart');
							$tempCart = array();
							for($a=0;$a<count($CartVal); $a++)
							{

								$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$CartVal[$a]);
								$FreeGiftCheck = isset($CartVal[$a]["IS_Free_Gift"])?$CartVal[$a]["IS_Free_Gift"]:'No';

								$FreeSample = '';
								if(isset($CartVal[$a]["Is_Free_Sample"]))
									$FreeSample = $CartVal[$a]["Is_Free_Sample"];
								if($IsGiftCertificateItem == 'No' && in_array($CartVal[$a]['SKU'] , $arr_QtySKU) && $CartVal[$a]["IsDealProducts"]!="Yes" && $FreeGiftCheck != "Yes" && $FreeSample != "Yes")
								{
									if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($CartVal[$a]['CategoryID']) && in_array($CartVal[$a]['CategoryID'] ,$pocketPerfumeCategory))
									{
										continue;
									}

									if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
										$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
										if(count($exclude_skus_arr) > 0){
											if (in_array($CartVal[$a]['SKU'], $exclude_skus_arr)){
												continue;
											}
										}
									}

									$SKUArrValCheck[] = $CartVal[$a]['SKU'];
									$tempCart[] = $CartVal[$a];
								}
							}
							if(count($tempCart) > 0)
							{
								$ItemQtyArr =  array();
								$SKU_With_Price = [];
								foreach ($tempCart as $array)
								{
								  	$ItemQtyArr= array_merge($ItemQtyArr, array_fill(0, $array['Qty'], $array));
								  	//$SKU_With_Price[] = ['SKU' => $array['SKU'], 'Price' => $array['Price']];
								  	if((int)$array['Qty'] > 0)
									{
										for($q=0;$q<(int)$array['Qty'];$q++)
										{
											$SKU_With_Price[] = ['SKU' => $array['SKU'], 'Price' => $array['Price']];
										}
									}
								}

								$prices = array_column($ItemQtyArr, 'Price');
								$quantites = array_column($tempCart, 'Qty');
								$SKUs = array_column($ItemQtyArr, 'SKU');

								$modCount =0;
								if(intdiv(array_sum($quantites), 2) >= 1){
									$modCount  = intdiv(array_sum($quantites), 2);
								}

								//MultuQuantity Discount
								$QuantityCount = 0;
								if ($DogoRS[$i]['type'] == '2' && $DogoRS[$i]['quantity'] > 0)
								{
									$ProcessQuantityCount = $DogoRS[$i]['quantity'] + 1;
									if (intdiv(array_sum($quantites), $ProcessQuantityCount) >= 1)
									{
										$QuantityCount  = intdiv(array_sum($quantites), $ProcessQuantityCount);
									}
								}
								//MultuQuantity Discount

								if($DogoRS[$i]['sortBy']=="High")
								{
									rsort($prices);
									usort($SKU_With_Price, function($a, $b) {
										return $b['Price'] <=> $a['Price'];
									});
								}
								else if($DogoRS[$i]['sortBy']=="Low")
								{
									sort($prices);
									usort($SKU_With_Price, function($a, $b) {
										return $a['Price'] <=> $b['Price'];
									});
								}
								//$DogoDiscount = 0;
								$ItemWiseDiscount = [];
								if ($DogoRS[$i]['type'] == '1' || $DogoRS[$i]['type'] == '0')
								{
									for ($a = 0; $a < $modCount; $a++)
									{
										//Second Item Percentage Discount
										if ($DogoRS[$i]['type'] == '1')
										{
											$Percentage = $DogoRS[$i]['percentage'];
											$DisAmount = $prices[$a] * ($Percentage / 100);
											//$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
											if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
											{
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] +=$DisAmount;
											} else {
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
											}
											$DogoDiscount = $DogoDiscount + $DisAmount;
										} else {
											//Default BOGO Discount
											//$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $prices[$a];
											if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
											{
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] +=$prices[$a];
											} else {
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $prices[$a];
											}
											$DogoDiscount = $DogoDiscount + $prices[$a];
										}
									}
								} elseif ($DogoRS[$i]['type'] == '2') {
									//MultuQuantity Discount
									for ($a = 0; $a < $QuantityCount; $a++)
									{
										$Percentage = $DogoRS[$i]['percentage'];
										$DisAmount = $prices[$a] * ($Percentage / 100);
										//$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
										if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
										{
											$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] +=$DisAmount;
										} else {
											$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
										}
										$DogoDiscount = $DogoDiscount + $DisAmount;
									}
									//MultuQuantity Discount
								}

								for($d=0;$d<count($tempCart1);$d++)
								{
									if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($tempCart1[$d]['CategoryID']) && in_array($tempCart1[$d]['CategoryID'] ,$pocketPerfumeCategory))
									{
										continue;
									}

									if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
										$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
										if(count($exclude_skus_arr) > 0){
											if (in_array($tempCart1[$d]['SKU'], $exclude_skus_arr)){
												continue;
											}
										}
									}
									$MatchTotal = 0;
									//Display Bogo Discount Message
									$FinalCount = $modCount;
									if($DogoRS[$i]['type'] == '2' && $DogoRS[$i]['quantity'] > 0)
									{
										$FinalCount = $QuantityCount;
									}
									//Display Bogo Discount Message
									for ($a=0; $a<$FinalCount; $a++)
									{
										if(isset($prices[$a]) && $tempCart1[$d]["Price"]==$prices[$a] && in_array($tempCart1[$d]["SKU"],$SKUArrValCheck))
										{
										  $MatchTotal = $MatchTotal + $prices[$a];
										}

									}
									$CurrentSKU = Session::get('ShoppingCart.Cart.' . $d . '.SKU');
									//Session::put('ShoppingCart.Cart.' . $d . '.BogoItemWiseDiscout',"");

									if (!empty($MatchTotal) && $MatchTotal > 0 && isset($ItemWiseDiscount[$CurrentSKU]))
									{
										Session::put('ShoppingCart.Cart.' . $d . '.BogoItemWiseDiscout', NumberFormat($ItemWiseDiscount[$CurrentSKU]));
										//Display Bogo Discount Message
										$BogoDiscountMessage = "";
										if($DogoRS[$i]['type'] == '0')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1);
										}
										elseif($DogoRS[$i]['type'] == '1')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1,$DogoRS[$i]['percentage']);
										}
										elseif($DogoRS[$i]['type'] == '2')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1,$DogoRS[$i]['percentage'],$DogoRS[$i]['quantity']);
										}
										Session::put('ShoppingCart.Cart.' . $d . '.BogoDiscountMessage', $BogoDiscountMessage);
										//Display Bogo Discount Message
									} else if(in_array($CurrentSKU, $SKUs)) {
										//Display Bogo Discount Message
										$BogoDiscountMessage = "";
										if($DogoRS[$i]['type'] == '0')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0);
										}
										elseif($DogoRS[$i]['type'] == '1')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0,$DogoRS[$i]['percentage']);
										}
										elseif($DogoRS[$i]['type'] == '2')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0,$DogoRS[$i]['percentage'],$DogoRS[$i]['quantity']);
										}
										Session::put('ShoppingCart.Cart.' . $d . '.BogoDiscountMessage', $BogoDiscountMessage);
										//Display Bogo Discount Message
									}
								}
							}
						}
					}
					else if($DogoRS[$i]["orders"]=='0')
					{

						//echo "Test";
						$QtyCatID = trim($DogoRS[$i]['sku']);
						$arr_QtyCatID    = explode(",",$QtyCatID);

						//$DogoDiscount = 0; Commented on 16122022
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_QtyCatID)->get();
						$arr_active_CatID = array();
						for($h=0;$h<count($Res_active_CatID);$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
						$SKUArrValCheck = array();
						if(count($arr_active_CatID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = ProductsCategory::select('products_id')->distinct()
								->whereIn('category_id',$arr_active_CatID)
								->whereIn('products_id',$temp_prod_id)->get();

							$cat_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;

							$CartVal  = Session::get('ShoppingCart.Cart');
							$tempCart = array();
							for($a=0;$a<count($CartVal); $a++)
							{

								if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($CartVal[$a]['CategoryID']) && in_array($CartVal[$a]['CategoryID'] ,$pocketPerfumeCategory))
								{
									continue;
								}

								if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
									$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
									if(count($exclude_skus_arr) > 0){
										if (in_array($CartVal[$a]['SKU'], $exclude_skus_arr)){
											continue;
										}
									}
								}

								if(!isset($CartVal[$a]["IS_Free_Gift"]))
									$CartVal[$a]["IS_Free_Gift"]="No";
								if(!isset($CartVal[$a]["IsDealProducts"]))
									$CartVal[$a]["IsDealProducts"]="No";

								$FreeSample = '';
								if(isset($CartVal[$a]["Is_Free_Sample"]))
									$FreeSample = $CartVal[$a]["Is_Free_Sample"];

								$IsGiftCertificateItem1 = $this->checkGiftCertificateItem('IsGiftCertificateItem',$CartVal[$a]);

								//if($CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU1') && $CartVal[$a]['SKU'] != config('global.GIFT_CERTIFICATE_SKU2') && $CartVal[$a]['SKU'] != config('global.GIFT_CERTIFICATE_SKU3') && in_array($CartVal[$a]['ProductID'] , $cat_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" &&  isset($CartVal[$a]["IS_Free_Gift"]) && $CartVal[$a]["IS_Free_Gift"]!="Yes")
								if($IsGiftCertificateItem1 == 'No' && in_array($CartVal[$a]['ProductID'] , $cat_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" &&  isset($CartVal[$a]["IS_Free_Gift"]) && $CartVal[$a]["IS_Free_Gift"]!="Yes" && $FreeSample != "Yes")
								{
									$tempCart[] = $CartVal[$a];
									$SKUArrValCheck[] = $CartVal[$a]["SKU"];
								}
							}
							if(count($tempCart) > 0)
							{
								$ItemQtyArr =  array();
								$SKU_With_Price = [];
								foreach ($tempCart as $array)
								{
								  	$ItemQtyArr= array_merge($ItemQtyArr, array_fill(0, $array['Qty'], $array));
								  	//$SKU_With_Price[] = ['SKU' => $array['SKU'], 'Price' => $array['Price']];
								  	if((int)$array['Qty'] > 0)
									{
										for($q=0;$q<(int)$array['Qty'];$q++)
										{
											$SKU_With_Price[] = ['SKU' => $array['SKU'], 'Price' => $array['Price']];
										}
									}
								}

								$prices = array_column($ItemQtyArr, 'Price');
								$quantites = array_column($tempCart, 'Qty');
								$SKUs = array_column($ItemQtyArr, 'SKU');

								$modCount =0;
								if(intdiv(array_sum($quantites), 2) >= 1){
									$modCount  = intdiv(array_sum($quantites), 2);
								}

								//MultuQuantity Discount
								$QuantityCount =0;
								if($DogoRS[$i]['type'] == '2' && $DogoRS[$i]['quantity'] > 0)
								{
									$ProcessQuantityCount = $DogoRS[$i]['quantity'] + 1;
									if(intdiv(array_sum($quantites), $ProcessQuantityCount) >= 1)
									{
										$QuantityCount  = intdiv(array_sum($quantites), $ProcessQuantityCount);
									}
								}
								//MultuQuantity Discount

								if($DogoRS[$i]['sortBy']=="High")
								{
									rsort($prices);
									usort($SKU_With_Price, function($a, $b) {
										return $b['Price'] <=> $a['Price'];
									});
								}
								else if($DogoRS[$i]['sortBy']=="Low")
								{
									sort($prices);
									usort($SKU_With_Price, function($a, $b) {
										return $a['Price'] <=> $b['Price'];
									});
								}

								//$DogoDiscount = 0;
								$ItemWiseDiscount=[];

								if ($DogoRS[$i]['type'] == '1' || $DogoRS[$i]['type'] == '0')
								{
									for ($a = 0; $a < $modCount; $a++)
									{
										//Second Item Percentage Discount
										if ($DogoRS[$i]['type'] == '1')
										{
											$Percentage = $DogoRS[$i]['percentage'];
											$DisAmount = $prices[$a] * ($Percentage / 100);
											//$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
											if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
											{
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] +=$DisAmount;
											} else {
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
											}
											$DogoDiscount = $DogoDiscount + $DisAmount;
										} else {
											//Default BOGO Discount
											//$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $prices[$a];
											if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
											{
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] +=$prices[$a];
											} else {
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $prices[$a];
											}
											$DogoDiscount = $DogoDiscount + $prices[$a];
										}
									}
								} elseif ($DogoRS[$i]['type'] == '2') {
									//MultuQuantity Discount
									for ($a = 0; $a < $QuantityCount; $a++)
									{
										$Percentage = $DogoRS[$i]['percentage'];
										$DisAmount = $prices[$a] * ($Percentage / 100);
										//$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
										if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
										{
											$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] +=$DisAmount;
										} else {
											$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
										}
										$DogoDiscount = $DogoDiscount + $DisAmount;
									}
									//MultuQuantity Discount
								}

								for($d=0;$d<count($tempCart1);$d++)
								{
									if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($tempCart1[$d]['CategoryID']) && in_array($tempCart1[$d]['CategoryID'] ,$pocketPerfumeCategory))
									{
										continue;
									}

									if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
										$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
										if(count($exclude_skus_arr) > 0){
											if (in_array($tempCart1[$d]['SKU'], $exclude_skus_arr)){
												continue;
											}
										}
									}

									$MatchTotal = 0;
									//Display Bogo Discount Message
									$FinalCount = $modCount;
									if($DogoRS[$i]['type'] == '2' && $DogoRS[$i]['quantity'] > 0)
									{
										$FinalCount = $QuantityCount;
									}
									//Display Bogo Discount Message
									for ($a=0; $a<$FinalCount; $a++)
									{
										if(isset($prices[$a]) && $tempCart1[$d]["Price"]==$prices[$a] && in_array($tempCart1[$d]["SKU"],$SKUArrValCheck))
										{
										  $MatchTotal = $MatchTotal + $prices[$a];
										}

									}
									$CurrentSKU = Session::get('ShoppingCart.Cart.' . $d . '.SKU');
									//Session::put('ShoppingCart.Cart.' . $d . '.BogoItemWiseDiscout',"");

									if (!empty($MatchTotal) && $MatchTotal > 0 && isset($ItemWiseDiscount[$CurrentSKU]))
									{
										Session::put('ShoppingCart.Cart.' . $d . '.BogoItemWiseDiscout', NumberFormat($ItemWiseDiscount[$CurrentSKU]));
										//Display Bogo Discount Message
										$BogoDiscountMessage = "";
										if($DogoRS[$i]['type'] == '0')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1);
										}
										elseif($DogoRS[$i]['type'] == '1')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1,$DogoRS[$i]['percentage']);
										}
										elseif($DogoRS[$i]['type'] == '2')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1,$DogoRS[$i]['percentage'],$DogoRS[$i]['quantity']);
										}
										Session::put('ShoppingCart.Cart.' . $d . '.BogoDiscountMessage', $BogoDiscountMessage);
										//Display Bogo Discount Message
									} else if(in_array($CurrentSKU, $SKUs)) {
										//Display Bogo Discount Message
										$BogoDiscountMessage = "";
										if($DogoRS[$i]['type'] == '0')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0);
										}
										elseif($DogoRS[$i]['type'] == '1')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0,$DogoRS[$i]['percentage']);
										}
										elseif($DogoRS[$i]['type'] == '2')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0,$DogoRS[$i]['percentage'],$DogoRS[$i]['quantity']);
										}
										Session::put('ShoppingCart.Cart.' . $d . '.BogoDiscountMessage', $BogoDiscountMessage);
										//Display Bogo Discount Message
									}
								}

							}

						}
					}
					else if($DogoRS[$i]["orders"]=='1')
					{
						$QtySKU = trim($DogoRS[$i]['sku']);
						########### For Multiple SKU ###############
						$QtyBrandID    	= trim($DogoRS[$i]['sku']); // Category IDS
						$arr_QtyBrandID    = explode(",",$QtyBrandID);

						//$Dogodiscount = 0; Commented on 16122022
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')->whereIn('imanufactureid',$arr_QtyBrandID)->get();
						$arr_active_BrandID = array();

						for($h=0;$h<$Res_active_BrandID->count();$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}
						$SKUArrValCheck = array();
						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = Products::select('products_id')->distinct()
								->whereIn('imanufactureid',$arr_active_BrandID)
								->whereIn('products_id',$temp_prod_id)->get();

							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							//print_r($brand_prod_id);
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							$tempCart = array();
							$CartVal  = Session::get('ShoppingCart.Cart');

							for($a=0;$a<count($CartVal); $a++)
							{
								if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" && isset($CartVal[$a]['CategoryID']) && in_array($CartVal[$a]['CategoryID'] ,$pocketPerfumeCategory))
								{
									continue;
								}

								if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
									$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
									if(count($exclude_skus_arr) > 0){
										if (in_array($CartVal[$a]['SKU'], $exclude_skus_arr)){
											continue;
										}
									}
								}

								if(!isset($CartVal[$a]["IS_Free_Gift"]))
									$CartVal[$a]["IS_Free_Gift"]="No";
								if(!isset($CartVal[$a]["IsDealProducts"]))
									$CartVal[$a]["IsDealProducts"]="No";

								$FreeSample = '';
								if(isset($CartVal[$a]["Is_Free_Sample"]))
									$FreeSample = $CartVal[$a]["Is_Free_Sample"];

								$IsGiftCertificateItem2 = $this->checkGiftCertificateItem('IsGiftCertificateItem',$CartVal[$a]);

								//if($CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU1') && $CartVal[$a]['SKU'] != config('global.GIFT_CERTIFICATE_SKU2') && $CartVal[$a]['SKU'] != config('global.GIFT_CERTIFICATE_SKU3') && in_array($CartVal[$a]['ProductID'] , $brand_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" && $CartVal[$a]["IS_Free_Gift"]!="Yes")
								if($IsGiftCertificateItem2 == 'No' && in_array($CartVal[$a]['ProductID'] , $brand_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" && $CartVal[$a]["IS_Free_Gift"]!="Yes" && $FreeSample != "Yes")
								{
									$tempCart[] = $CartVal[$a];
									$SKUArrValCheck[]=$CartVal[$a]["SKU"];
								}

							}

							if(count($tempCart) > 0)
							{
								$ItemQtyArr =  array();
								$SKU_With_Price =[];
								foreach ($tempCart as $array)
								{
									$ItemQtyArr= array_merge($ItemQtyArr, array_fill(0, $array['Qty'], $array));
								  	if((int)$array['Qty'] > 0)
									{
										for($q=0;$q<(int)$array['Qty'];$q++)
										{
											$SKU_With_Price[] = ['SKU' => $array['SKU'], 'Price' => $array['Price']];
										}
									}
								}

								$prices = array_column($ItemQtyArr, 'Price');
								$quantites = array_column($tempCart, 'Qty');
								$SKUs = array_column($tempCart, 'SKU');

								$modCount =0;
								if(intdiv(array_sum($quantites), 2) >= 1){
									$modCount  = intdiv(array_sum($quantites), 2);
								}

								//MultuQuantity Discount
								$QuantityCount =0;
								if($DogoRS[$i]['type'] == '2' && $DogoRS[$i]['quantity'] > 0)
								{
									$ProcessQuantityCount = $DogoRS[$i]['quantity'] + 1;
									if(intdiv(array_sum($quantites), $ProcessQuantityCount) >= 1)
									{
										$QuantityCount  = intdiv(array_sum($quantites), $ProcessQuantityCount);
									}
								}
								//MultuQuantity Discount

								if($DogoRS[$i]['sortBy']=="High")
								{
									rsort($prices);
									usort($SKU_With_Price, function($a, $b) {
										return $b['Price'] <=> $a['Price'];
									});
								}
								else if($DogoRS[$i]['sortBy']=="Low")
								{
									sort($prices);
									usort($SKU_With_Price, function($a, $b) {
										return $a['Price'] <=> $b['Price'];
									});
								}

								$ItemWiseDiscount = [];
								if ($DogoRS[$i]['type'] == '1' || $DogoRS[$i]['type'] == '0')
								{
									for ($a = 0; $a < $modCount; $a++)
									{
										//Second Item Percentage Discount
										if ($DogoRS[$i]['type'] == '1') {
											$Percentage = $DogoRS[$i]['percentage'];
											$DisAmount = $prices[$a] * ($Percentage / 100);
											if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
											{
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] +=$DisAmount;
											} else {
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
											}
											$DogoDiscount = $DogoDiscount + $DisAmount;
										} else {
											//Default BOGO Discount
											//$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $prices[$a];
											if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
											{
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] += $prices[$a];
											} else {
												$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $prices[$a];
											}
											$DogoDiscount = $DogoDiscount + $prices[$a];
										}
									}
								} elseif ($DogoRS[$i]['type'] == '2') {
									//MultuQuantity Discount
									for ($a = 0; $a < $QuantityCount; $a++)
									{
										$Percentage = $DogoRS[$i]['percentage'];
										$DisAmount = $prices[$a] * ($Percentage / 100);
										//$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
										if(array_key_exists($SKU_With_Price[$a]['SKU'], $ItemWiseDiscount))
										{
											$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] +=$DisAmount;
										} else {
											$ItemWiseDiscount[$SKU_With_Price[$a]['SKU']] = $DisAmount;
										}
										$DogoDiscount = $DogoDiscount + $DisAmount;
									}
									//MultuQuantity Discount
								}
								for($d=0;$d<count($tempCart1);$d++)
								{
									if(isset($DogoRS[$i]['exclude_pocketperfume']) && $DogoRS[$i]['exclude_pocketperfume'] == "Yes" &&  isset($tempCart1[$d]['CategoryID'])  && in_array($tempCart1[$d]['CategoryID'] ,$pocketPerfumeCategory))
									{
										continue;
									}

									if(isset($DogoRS[$i]['exclude_product_skus']) && $DogoRS[$i]['exclude_product_skus']!=''){
										$exclude_skus_arr = explode(",",$DogoRS[$i]['exclude_product_skus']);
										if(count($exclude_skus_arr) > 0){
											if (in_array($tempCart1[$d]['SKU'], $exclude_skus_arr)){
												continue;
											}
										}
									}

									$MatchTotal = 0;
									//Display Bogo Discount Message
									$FinalCount = $modCount;

									if($DogoRS[$i]['type'] == '2' && $DogoRS[$i]['quantity'] > 0)
									{
										$FinalCount = $QuantityCount;
									}
									//Display Bogo Discount Message
									for ($a=0; $a<$FinalCount; $a++)
									{
										if(isset($prices[$a]) && $tempCart1[$d]["Price"]==$prices[$a] && in_array($tempCart1[$d]["SKU"],$SKUArrValCheck))
										{
										  $MatchTotal = $MatchTotal + $prices[$a];
										}

									}
									$CurrentSKU = Session::get('ShoppingCart.Cart.' . $d . '.SKU');
									//Session::put('ShoppingCart.Cart.' . $d . '.BogoItemWiseDiscout',"");

									if (!empty($MatchTotal) && $MatchTotal > 0 && isset($ItemWiseDiscount[$CurrentSKU]))
									{
										Session::put('ShoppingCart.Cart.' . $d . '.BogoItemWiseDiscout', NumberFormat($ItemWiseDiscount[$CurrentSKU]));
										//Display Bogo Discount Message
										$BogoDiscountMessage = "";
										if($DogoRS[$i]['type'] == '0')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1);
										}
										elseif($DogoRS[$i]['type'] == '1')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1,$DogoRS[$i]['percentage']);
										}
										elseif($DogoRS[$i]['type'] == '2')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,1,$DogoRS[$i]['percentage'],$DogoRS[$i]['quantity']);
										}
										Session::put('ShoppingCart.Cart.' . $d . '.BogoDiscountMessage', $BogoDiscountMessage);
										//Display Bogo Discount Message
									} else if(in_array($CurrentSKU, $SKUs)) {
										//Display Bogo Discount Message
										$BogoDiscountMessage = "";
										if($DogoRS[$i]['type'] == '0')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0);
										}
										elseif($DogoRS[$i]['type'] == '1')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0,$DogoRS[$i]['percentage']);
										}
										elseif($DogoRS[$i]['type'] == '2')
										{
											$BogoDiscountMessage = $this->SetBogoMessage($DogoRS[$i]->type,0,$DogoRS[$i]['percentage'],$DogoRS[$i]['quantity']);
										}
										Session::put('ShoppingCart.Cart.' . $d . '.BogoDiscountMessage', $BogoDiscountMessage);
										//Display Bogo Discount Message
									}
								}
							}
						}
					}
				}
			}else{
				$DogoDiscount = 0;
			}
		//	echo "<pre>"; print_r(Session::get('ShoppingCart.Cart')); exit;
			//echo "<br>DogoDiscount 4 --- ".$DogoDiscount;
			Session::put('ShoppingCart.DogoDiscount',NumberFormat($DogoDiscount));
			$log['DogoDiscount'] = $DogoDiscount;
			addLog("ApplyDogoDiscount",$log);
		}
		//echo "<br><b>DogoDiscount Final </b> --- ".$DogoDiscount;
	}
	public function RemoveFreeGiftValueProduct($sku)
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')))
		{
			$count = count(Session::get('ShoppingCart.Cart'));
			$TempCart = array();
			$CartInfo = Session::get('ShoppingCart.Cart');
			foreach($CartInfo as $a => $Cart)
			{
				if($Cart['SKU'] == $sku)
				{
					if(isset($Cart["IS_Free_Gift"]) && $Cart['IS_Free_Gift']=="Yes")
						unset($Cart);
				}else if($Cart['SKU']==""){
					unset($Cart);
				}else{
					$TempCart[] = $Cart;
				}
			}
			Session::put('ShoppingCart.Cart',$TempCart);
			$this->CalculateSubTotal();
		}
	}

	public function ApplyItemWiseCoupon($couponCode)
	{
		$IsGiftCertificateItem = $IsGiftCertificateItem1 = '';

		if(Session::has('ShoppingCart.Cart'))
		{
			$TotalItems = count(Session::get('ShoppingCart.Cart'));
			$Cart = Session::get('ShoppingCart.Cart');
			if($couponCode=='')
			{
				for($p=0;$p<$TotalItems;$p++)
					$Cart[$p]["ItemWiseCouponDiscount"] = 0;
				Session::put('ShoppingCart.Cart',$Cart);
				return null;
			}
			$UserType = 'Retailer';
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//if(Auth::user())
			if($normaluser)
			{
				/*if(Auth::user()->eusertype != '' && Session::get('sess_icustomerid') != '')
					$UserType = Auth::user()->eusertype;*/
				if($normaluser->eusertype != '' && Session::get('sess_icustomerid') != '')
					$UserType = $normaluser->eusertype;
			}

			$CouponRS = Coupon::select('bogodiscount_flag')->where('coupon_number','=',$couponCode)
								->where('status','=','1')
								->where('start_date','<=',DB::raw('curdate()'))
								->where('end_date','>=',DB::raw('curdate()'))
								->where('coupon_user_type','=',$UserType)
								->get();

			if($CouponRS && $CouponRS->count() > 0)
			{
				$switchCase = $CouponRS[0]['orders'];
				$TotalCouponCount = $CouponRS->count();
				if($TotalItems > 0)
				{
					if($switchCase=='3')
					{
						$CouponCatID    	= trim($CouponRS[0]['sku']); // Category IDS
						$arr_CouponCatID    = explode(",",$CouponCatID);

						$CouponDiscount = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_CouponCatID)->get();
						$arr_active_CatID = array();

						for($h=0;$h<$Res_active_CatID->count();$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
						if(count($arr_active_CatID) > 0 )
						{
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = Products::select('products_id')->distinct()
										->whereIn('imanufactureid',$arr_active_BrandID)
										->whereIn('products_id',$temp_prod_id)->get();
							$cat_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
						}
					}
					if($switchCase=='6')
					{

						$CouponBrandID    	= trim($CouponRS[0]['sku']); // Brand IDS
						$arr_CouponBrandID  = explode(",",$CouponBrandID);

						$CouponDiscount = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')->whereIn('imanufactureid',$arr_CouponBrandID)->get();
						$arr_active_BrandID = array();

						for($h=0;$h<count($Res_active_BrandID);$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}

						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}

							$ProdIds = Products::select('products_id')->distinct()
											->whereIn('imanufactureid',$arr_active_BrandID)
											->whereIn('products_id',$temp_prod_id)->get();
							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
						}
					}
					$ExcludeSKUListArr = array();
					$ExcludeSKUListArr  = explode(",",$CouponRS[0]["exclude_sku"]);
					$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
					$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
					$IsMatchItem = 0;
					for($p=0;$p<$TotalItems;$p++)
					{
						$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$Cart[$p]);

						if((isset($Cart[$p]["IsDealProducts"]) && $Cart[$p]["IsDealProducts"]=="No") || ((isset($Cart[$p]["IsDealProducts"]) && $Cart[$p]["IsDealProducts"]=="Yes") && ($Cart[$p]["DealDiscountFlag"]=="Yes" ||  $CouponRS[0]["dealdiscount_flag"]=="Yes")))
						{
							if($switchCase=='3')
							{
								if(!in_array($Cart[$p]["SKU"],$ExcludeSKUListArr) && in_array($Cart[$p]['ProductID'] , $cat_prod_id))
								{
									/*if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}*/
									if($IsGiftCertificateItem == 'Yes' && $CouponRS[0]["count_gc_purchase"]=='1'){
										$IsMatchItem = $IsMatchItem + 1;
									}
									else
									{
										//if($Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										if($IsGiftCertificateItem == 'No')
										{
											$IsMatchItem = $IsMatchItem + 1;
										}
									}
								}
							}
							else if($switchCase=='1')
							{
								$CouponSKU = trim($CouponRS[0]['sku']);

								$arr_CouponSKU  = explode(",",$CouponSKU);
								$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
								$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
								if(in_array($Cart[$p]["SKU"] , $arr_CouponSKU) && !in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
								{
									/*if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}*/

									if($IsGiftCertificateItem == 'Yes' && $CouponRS[0]["count_gc_purchase"]=='1'){
										$IsMatchItem = $IsMatchItem + 1;
									}
									else
									{
										//if($Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										if($IsGiftCertificateItem == 'No')
										{
											$IsMatchItem = $IsMatchItem + 1;
										}
									}
								}
							}
							else if($switchCase=='6')
							{
								if(!in_array($Cart[$p]["SKU"],$ExcludeSKUListArr) && in_array($Cart[$p]['ProductID'] , $brand_prod_id))
								{
									/*if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}*/

									if($IsGiftCertificateItem == 'Yes' && $CouponRS[0]["count_gc_purchase"]=='1'){
										$IsMatchItem = $IsMatchItem + 1;
									}
									else
									{
										//if($Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										if($IsGiftCertificateItem == 'No')
										{
											$IsMatchItem = $IsMatchItem + 1;
										}
									}
								}
							}
							else
							{
								if(!in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
								{
									/*if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}*/

									if($IsGiftCertificateItem == 'Yes' && $CouponRS[0]["count_gc_purchase"]=='1'){
										$IsMatchItem = $IsMatchItem + 1;
									}
									else
									{
										//if($Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										if($IsGiftCertificateItem == 'No')
										{
											$IsMatchItem = $IsMatchItem + 1;
										}
									}
								}
							}
						}
					}
					$tempCart = [];
					for($i=0;$i<$TotalItems;$i++)
					{
						$IsGiftCertificateItem1 = $this->checkGiftCertificateItem('IsGiftCertificateItem',$Cart[$i]);

						if((isset($Cart[$p]["IsDealProducts"]) && $Cart[$i]["IsDealProducts"]=="No") || (isset($Cart[$p]["IsDealProducts"])&& $Cart[$i]["IsDealProducts"]=="Yes" && ($Cart[$i]["DealDiscountFlag"]=="Yes" ||  $CouponRS[0]["dealdiscount_flag"]=="Yes")))
						{
							$CouponDiscount  = 0;

							if(!in_array($Cart[$i]["SKU"],$ExcludeSKUListArr))
							{
								switch ($switchCase)
								{
									case '0' :

									/*if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										if($CouponRS[0]['type'] == 1 )
											$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
										else
											$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

										$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
									}
									else if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										if($CouponRS[0]['type'] == 1 )
											$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
										else
											$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

										$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
									}*/

									if($IsGiftCertificateItem1 == 'Yes' && $CouponRS[0]["count_gc_purchase"]=='1'){
										if($CouponRS[0]['type'] == 1 )
											$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
										else
											$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

										$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
									}
									else
									{
										//if($Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										if($IsGiftCertificateItem1 == 'No')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
									}

									$CouponDiscount = number_format($CouponDiscount, 2, '.', ',');

									$Cart[$i]["ItemWiseCouponDiscount"] = $CouponDiscount;
									break;
									case '1' :
									$CouponSKU = trim($CouponRS[0]['sku']);

									$arr_CouponSKU  = explode(",",$CouponSKU);
									$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
									$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
									if(in_array($Cart[$i]["SKU"] , $arr_CouponSKU))
									{
										/*if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}*/

										if($IsGiftCertificateItem1 == 'Yes' && $CouponRS[0]["count_gc_purchase"]=='1'){
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else
										{
											//if($Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
											if($IsGiftCertificateItem1 == 'No')
											{
												if($CouponRS[0]['type'] == 1 )
													$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
												else
													$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

												$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
											}
										}
									}

									$CouponDiscount = NumberFormat($CouponDiscount);
									$Cart[$i]["ItemWiseCouponDiscount"] = $CouponDiscount;

									break;
									case '2' :
									break;
									case '3' :
									if(in_array($Cart[$i]['ProductID'] , $cat_prod_id))
									{
										/*if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}*/

										if($IsGiftCertificateItem1 == 'Yes' && $CouponRS[0]["count_gc_purchase"]=='1'){
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else
										{
											//if($Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
											if($IsGiftCertificateItem1 == 'No')
											{
												if($CouponRS[0]['type'] == 1 )
													$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
												else
													$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

												$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
											}
										}
										$CouponDiscount = $this->NumberFormat($CouponDiscount);
										$Cart[$i]["ItemWiseCouponDiscount"] = $CouponDiscount;
									}
									else
									{
										$Cart[$i]["ItemWiseCouponDiscount"] =0;
									}
									break;
									case '6' :
									if(in_array($Cart[$i]['ProductID'] , $brand_prod_id))
									{
										/*if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}*/

										if($IsGiftCertificateItem1 == 'Yes' && $CouponRS[0]["count_gc_purchase"]=='1'){
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else
										{
											//if($Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
											if($IsGiftCertificateItem1 == 'No')
											{
												if($CouponRS[0]['type'] == 1 )
													$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
												else
													$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

												$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
											}

										}
										$CouponDiscount = NumberFormat($CouponDiscount);
										$Cart[$i]["ItemWiseCouponDiscount"] = $CouponDiscount;
									}
									else
									{
										$Cart[$i]["ItemWiseCouponDiscount"] =0;
									}
									break;
									Default:
									$Cart[$i]["ItemWiseCouponDiscount"] =0;
									break;
								}
							}
							else
							{
								$Cart[$i]["ItemWiseCouponDiscount"] = 0;
							}
						}
						else
						{
							$Cart[$i]["ItemWiseCouponDiscount"] = 0;
						}
					}
					Session::put('ShoppingCart.Cart',$Cart);
				}
			}
		}
	}

	public function Is_WholeSaler_Allow($is_set_msg=false)
	{
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(Auth::user() && Auth::user()->is_dropshipper != 'Yes' && strtolower(Auth::user()->eusertype ?? '') == 'wholesaler')
		if($normaluser && $normaluser->is_dropshipper != 'Yes' && strtolower($normaluser->eusertype ?? '') == 'wholesaler')
		{
			if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
			{
				$order_sub_total  = Session::get('ShoppingCart.SubTotal');
				$w_min_order_amt  = NumberFormat(config('Settings.WHOLESALER_MIN_ORDER_AMOUNT'));
				if($order_sub_total < $w_min_order_amt)
				{
					if($is_set_msg == true)
					{
						$msg = "For wholesaler minimum order amount should be ".$this->Make_Price($w_min_order_amt,true);
						Session::flash('CartError',$msg);
					}
					return false;
				}else{
					return true;
				}
			}
		}
		return true;
	}

	public function FreeGiftInsertWithCoupon($products_sku,$type="")
	{
		$OutofStockMsg="";
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{

			if($type == 'CouponRemove')
			{
				$count = count (Session::get('ShoppingCart.Cart'));
					$Cart = Session::get('ShoppingCart.Cart');
					$Cart = array_values($Cart);
					for($a=0; $a<$count; $a++)
					{
						if(isset($Cart[$a]["FreeGiftCoupon"]) && $Cart[$a]['FreeGiftCoupon']=="Yes")
						{
						  unset($Cart[$a]);
						}
					}

					$Cart = array_values($Cart);
					Session::put('ShoppingCart.Cart',$Cart);
				return true;
			}

			/*
			if($type == 'Coupon')
			{
				$Coupon = Coupon::where('coupon_number','=',$products_sku)->get();
				if($Coupon->count() > 0)
				{
					$products_sku = $Coupon[0]->freegift_product_sku;
				}
			}*/
			$FreeGiftProd = Products::where('sku','=',$products_sku)->where('status','=','1')->get();
			if($FreeGiftProd && $FreeGiftProd->count() > 0)
			{
				$free_gift_res = $this->SetProduct($FreeGiftProd[0]);

				if($free_gift_res->current_stock > 0 || ($free_gift_res->cosmo_current_stock > 0 && $free_gift_res->cosmo_sku!='') || ($free_gift_res->nandansons_current_stock > 0 && $free_gift_res->nandansons_sku!='')  || ($free_gift_res->pca_current_stock > 0 && $free_gift_res->pca_sku!='') || ($free_gift_res->perfumeworldwide_currentstock > 0 && $free_gift_res->perfumeworldwide_sku!='') || ($free_gift_res->nd_current_stock > 0 && $free_gift_res->nd_sku!=''))
				{
					if(file_exists(config('global.PRD_THUMB_IMG_PATH').$free_gift_res->image) && !empty($free_gift_res->image))
						$thumb_image = config('global.PRD_THUMB_IMG_URL').$free_gift_res->image;
					else
						$thumb_image = config('global.NO_IMAGE_THUMB');

					//$thumb_image = str_replace(config('global.SITE_URL'),config('global.SECURED_PATH'),$thumb_image);

					$free_gift_res->prod_image ='<img src="'.$thumb_image.'" border="0" width="125"  alt="'.$free_gift_res->product_name.'" />';
					$free_gift_res->image_forpopup ='<img src="'.$thumb_image.'" border="0" width="75"  alt="'.$free_gift_res->product_name.'" />';
					$free_gift_res->billing_image ='<img src="'.$thumb_image.'" border="0" width="195" alt="'.$free_gift_res->product_name.'" title="'.$free_gift_res->product_name.'"/>';

					$VendorSKU 		= "";
					$IsCosmo  		= "";
					$IsNandansons	= "";
					$IsPerfumePW	= "";
					$IsPCA	= "";
					$IsND	= "";

					$breadcrumbs = '';
					$fetch_brand = DB::table('pu_manufacture')->where('imanufactureid','=',$free_gift_res->imanufactureid)->get();

					$fetch_category = DB::table('pu_category as c')
											->join('pu_products_category as pc','c.category_id','=','pc.category_id')
											->join('pu_products as p', 'pc.products_id','=','p.products_id')
											->where('p.products_id','=',$free_gift_res->products_id)->get();

				   $CategoryID	= 0;
					if($fetch_category && $fetch_category->count() > 0)
					{
						$gcat = stripcslashes($fetch_category[0]->category_name);
						$CatInfo = config('CATEGORY_INFO');
						$breadcrumbs = $CatInfo['CatForProd'][$fetch_category[0]->category_id]['subcatbredcrum'];

						$CategoryID	= $fetch_category[0]->category_id;

					}

					if($free_gift_res->stock == "Out")
					{
						if($free_gift_res->cosmo_sku !='' &&  $free_gift_res->cosmo_current_stock > 0 )
						{
							$IsCosmo = "Yes";
							$VendorSKU = $free_gift_res->cosmo_sku;
						}
						else if($free_gift_res->pca_sku !='' &&  $free_gift_res->pca_current_stock > 0)
						{
							$IsPCA  = "Yes";
							$VendorSKU = $free_gift_res->pca_sku;
						}else if($free_gift_res->nandansons_sku !='' &&  $free_gift_res->nandansons_current_stock > 0)
						{
							$IsNandansons = "Yes";
							$VendorSKU = $free_gift_res->nandansons_sku;
						}
						else if($free_gift_res->perfumeworldwide_sku !='' &&  $free_gift_res->perfumeworldwide_currentstock > 0)
						{
							$IsPerfumePW = "Yes";
							$VendorSKU = $free_gift_res->perfumeworldwide_sku;
						}
						else if($free_gift_res->nd_sku !='' &&  $free_gift_res->nd_current_stock > 0)
						{
							$IsND = "Yes";
							$VendorSKU = $free_gift_res->nd_sku;
						}
					}
					$temp_ary = array();

					if($free_gift_res->WebsiteStock == "In")
					{
						$temp_ary['IsMaxaromaTwoDelivery'] = $free_gift_res->maxtwodaydelivery;
					}

					$temp_ary['ProductID']   		= $free_gift_res->products_id;
					$temp_ary['SKU']         		= "GIFT-".$free_gift_res->sku;
					$temp_ary['ORGSKU']         	= $free_gift_res->sku;
					$temp_ary['CategoryID']         = $CategoryID;
					$temp_ary['ProductName'] 		= stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_gift_res->product_name));
					$temp_ary['short_description'] 	= strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_gift_res->short_description)));
					$temp_ary['Billing_Image'] 		= $free_gift_res->billing_image;
					$temp_ary['Price']       		= 0;
					$temp_ary['Qty'] 		 		= 1;
					$temp_ary['TotPrice']    		= 0;
					$temp_ary['Image']       		= $free_gift_res->prod_image;
					$temp_ary['Prod_URL']       	= "";
					$temp_ary['IS_Free_Gift']       = "Yes";
					$temp_ary['FreeGiftCoupon']     = "Yes";
					$temp_ary['image_forpopup']		= $free_gift_res->image_forpopup;
					$temp_ary['freeproductsid']		= $free_gift_res->products_id;
					$temp_ary['VendorSKU']			= $VendorSKU;
					$temp_ary['IsCosmo']			= $IsCosmo;
					$temp_ary['IsNandansons']		= $IsNandansons;
					$temp_ary['IsPerfumePW']		= $IsPerfumePW;
					$temp_ary['IsPCA']				= $IsPCA;
					$temp_ary['IsND']				= $IsND;
					$temp_ary['ImanufactureID']		= $free_gift_res->imanufactureid;
					$temp_ary['IsDealProducts']		= "No";
					$temp_ary['DealDiscountFlag']	= "No";
					$temp_ary['dealdiscount_flag']	= "No";
					$temp_ary['manufactureName']	= $fetch_brand[0]->vmanufacture;
					$temp_ary['CategoryName']		= $breadcrumbs;
					$temp_ary['FinalSale']         	= '';

					$count = count (Session::get('ShoppingCart.Cart'));
					$Cart = Session::get('ShoppingCart.Cart');
					$Cart = array_values($Cart);
					for($a=0; $a<$count; $a++)
					{
						if(isset($Cart[$a]["IS_Free_Gift"]) && $Cart[$a]['IS_Free_Gift']=="Yes")
						{
						  unset($Cart[$a]);
						}
					}

					$Cart = array_values($Cart);

					$Cart[]=$temp_ary;
					Session::put('ShoppingCart.Cart',$Cart);
					$this->CalculateSubTotal();
				}else{
					$OutofStockMsg = "The Free bundle is out of  stock and cannot be added to your order";
					Session::flash('OutOfStockBundle','The Free bundle is out of  stock and cannot be added to your order');
				}
			}
		}
		return $OutofStockMsg;
	}

	public function FreeSampleInsertProductValue($products_id)
	{
		$OutofStockMsg="";
		$SKUList = "";
		$log['products_id'] = $products_id;
		// $log['freeproductsid'] = $freeproductsid;
		// $log['freeproductsid'] = $OneGift;
		addLog("FreeSampleInsertProductValueStart",$log);
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{

			$CartArr  = Session::get('ShoppingCart.Cart');
			$ProdIDVal = array_column($CartArr, 'ProductID');
			$isFreeSamplesVal = array_column($CartArr, 'Is_Free_Sample');
			$ProdArrte = explode(",",$products_id);

			// if(count($ProdArrte) > 0)
			// {
			// 	for($d=0;$d<count($ProdArrte);$d++)
			// 	{

			// 			if(in_array($ProdArrte[$d], $ProdIDVal) && in_array("Yes", $isFreeSamplesVal))
			// 			{
			// 				$Msg = "Same free sample product already added";
			// 				$log['message'] = $Msg;
			// 				addLog("SameFreeSample",$log);
			// 				return $Msg;
			// 			}
			// 	}
			// }

			$count = count (Session::get('ShoppingCart.Cart'));
			$Cart = Session::get('ShoppingCart.Cart');
			$Cart = array_values($Cart);
			$ProdArr = explode(",",$products_id);

			for($a=0; $a<$count; $a++)
			{
				// if(isset($Cart[$a]["FreeGiftCoupon"]) && $Cart[$a]["FreeGiftCoupon"]=="Yes")
				// {
				// 	addLog("FreeGiftNull");
				// 	return null;
				// }
				//else

				if(isset($Cart[$a]["Is_Free_Sample"]) && $Cart[$a]['Is_Free_Sample']=="Yes")
				{
					addLog("FreeSampleUnset");
				  	unset($Cart[$a]);
				}
			}
			$Cart = array_values($Cart);
			Session::put('ShoppingCart.Cart',$Cart);
			$FreeSampleAdd = "No";
			$ProdArr = explode(",",$products_id);

			$FreeSampleProd = Products::whereIn('products_id',$ProdArr)->where('status','=','1')->get();

			$TotalFreeSample = count($FreeSampleProd ?? []);
			if($FreeSampleProd && $TotalFreeSample> 0)
			{

				for($i=0;$i<$TotalFreeSample;$i++)
				{
				$temp_ary = [];
				$free_sample_res = $this->SetProduct($FreeSampleProd[$i]);
				if($free_sample_res->current_stock > 0 || ($free_sample_res->cosmo_current_stock > 0 && $free_sample_res->cosmo_sku!='') || ($free_sample_res->nandansons_current_stock > 0 && $free_sample_res->nandansons_sku!='')  || ($free_sample_res->pca_current_stock > 0 && $free_sample_res->pca_sku!='') || ($free_sample_res->perfumeworldwide_currentstock > 0 && $free_sample_res->perfumeworldwide_sku!='') || ($free_sample_res->nd_current_stock > 0 && $free_sample_res->nd_sku!=''))
				{
					if(file_exists(config('global.PRD_THUMB_IMG_PATH').$free_sample_res->image) && !empty($free_sample_res->image))
						$thumb_image = config('global.PRD_THUMB_IMG_URL').$free_sample_res->image;
					else
						$thumb_image = config('global.NO_IMAGE_THUMB');

					//$thumb_image = str_replace(config('global.SITE_URL'),config('global.SECURED_PATH'),$thumb_image);

					$free_sample_res->prod_image ='<img src="'.$thumb_image.'" border="0" width="125" />';
					$free_sample_res->image_forpopup ='<img src="'.$thumb_image.'" border="0" width="75" />';
					$free_sample_res->billing_image ='<img src="'.$thumb_image.'" border="0" width="195" alt="'.$free_sample_res->product_name.'" title="'.$free_sample_res->product_name.'"/>';

					$VendorSKU 		= "";
					$IsCosmo  		= "";
					$IsNandansons	= "";
					$IsPerfumePW	= "";
					$IsPCA	= "";
					$IsND	= "";

					if($free_sample_res->stock == "Out")
					{
						if($free_sample_res->cosmo_sku !='' &&  $free_sample_res->cosmo_current_stock > 0 )
						{
							$IsCosmo = "Yes";
							$VendorSKU = $free_sample_res->cosmo_sku;
						}
						else if($free_sample_res->pca_sku !='' &&  $free_sample_res->pca_current_stock > 0)
						{
							$IsPCA  = "Yes";
							$VendorSKU = $free_sample_res->pca_sku;
						}
						else if($free_sample_res->nandansons_sku !='' &&  $free_sample_res->nandansons_current_stock > 0)
						{
							$IsNandansons = "Yes";
							$VendorSKU = $free_sample_res->nandansons_sku;
						}
						else if($free_sample_res->perfumeworldwide_sku !='' &&  $free_sample_res->perfumeworldwide_currentstock > 0)
						{
							$IsPerfumePW = "Yes";
							$VendorSKU = $free_sample_res->perfumeworldwide_sku;
						}
						else if($free_sample_res->nd_sku !='' &&  $free_sample_res->nd_current_stock > 0)
						{
							$IsND = "Yes";
							$VendorSKU = $free_sample_res->nd_sku;
						}
					}
					$temp_ary = array();

					if($free_sample_res->WebsiteStock == "In")
					{
						$temp_ary['IsMaxaromaTwoDelivery'] = $free_sample_res->maxtwodaydelivery;
					}

					$fetch_category = DB::table('pu_category as c')
											->join('pu_products_category as pc','c.category_id','=','pc.category_id')
											->join('pu_products as p', 'pc.products_id','=','p.products_id')
											->where('p.products_id','=',$free_sample_res->products_id)->get();

				   $CategoryID = '0';
					if($fetch_category && $fetch_category->count() > 0)
					{
						$gcat = stripcslashes($fetch_category[0]->category_name);
						$CatInfo = config('CATEGORY_INFO');
						$breadcrumbs = $CatInfo['CatForProd'][$fetch_category[0]->category_id]['subcatbredcrum'];
						$CategoryID	= $fetch_category[0]->category_id;

					}

					$free_sample_res->product_name = remove_html_entities($free_sample_res->product_name);
					$free_sample_res->short_description = remove_html_entities($free_sample_res->short_description);

					$temp_ary['ProductID']   		= $free_sample_res->products_id;
					$temp_ary['CategoryID']   		= $CategoryID;
					$temp_ary['SKU']         		= "SAMPLE-".$free_sample_res->sku;
					$temp_ary['ORGSAMPLESKU']       = $free_sample_res->sku;
					$temp_ary['OrderType']       	= "Website";
					$temp_ary['ProductName'] 		= stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_sample_res->product_name));
					$temp_ary['short_description'] 	= strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_sample_res->short_description)));
					$temp_ary['Billing_Image'] 		= $free_sample_res->billing_image;
					$temp_ary['Price']       		= 0;
					$temp_ary['Qty'] 		 		= 1;
					$temp_ary['TotPrice']    		= 0;
					$temp_ary['Image']       		= $free_sample_res->prod_image;
					$temp_ary['Prod_URL']       	= "";
					$temp_ary['Is_Free_Sample']       = "Yes";
					$temp_ary['image_forpopup']		= $free_sample_res->image_forpopup;
					//$temp_ary['freeproductsid']		= $freeproductsid;
					$temp_ary['freesampleproductsid']	= $free_sample_res->products_id;
					$temp_ary['VendorSKU']			= $VendorSKU;
					$temp_ary['IsCosmo']			= $IsCosmo;
					$temp_ary['IsNandansons']		= $IsNandansons;
					$temp_ary['IsPerfumePW']		= $IsPerfumePW;
					$temp_ary['IsPCA']				= $IsPCA;
					$temp_ary['IsND']				= $IsND;
					$temp_ary['ImanufactureID']		= $free_sample_res->imanufactureid;
					$temp_ary['IsDealProducts']		= "No";
					$temp_ary['DealDiscountFlag']	= "No";
					$temp_ary['dealdiscount_flag']	= "No";
					$temp_ary['manufactureName']	= '';
					$temp_ary['CategoryName']		= '';
					$temp_ary['FinalSale']			= "";
					$Cart[]=$temp_ary;
					$this->CalculateSubTotal();
					$FreeSampleAdd = "Yes";
				}else{
					$SKUList .= $free_sample_res->sku.",";

				}
			}
			if(isset($SKUList) && $SKUList!='')
			{
				$SKUList = substr($SKUList,0,-1);
				$OutofStockMsg = "The Free bundle is out of  stock and cannot be added to your order and out of stock products ".$SKUList;
				$log['OutofStockMsg'] = $OutofStockMsg;
				addLog("FreeSampleInsertProductValueOutofStock",$log);
				Session::flash('OutOfStockBundle','The Free bundle is out of  stock and cannot be added to your order '.$SKUList);
			}
			if(count($Cart) > 0 && $FreeSampleAdd == "Yes")
			{
				   	$OutofStockMsg = "";
					Session::put('ShoppingCart.Cart',$Cart);
			}

		}
		}
		addLog("FreeSampleInsertProductValue");
		return 	$OutofStockMsg;

	}

	public function FreeGiftInsertProductValue($products_id,$freeproductsid=0,$OneGift="No")
	{
		$OutofStockMsg="";
		$SKUList = "";
		$log['products_id'] = $products_id;
		$log['freeproductsid'] = $freeproductsid;
		$log['freeproductsid'] = $OneGift;
		addLog("FreeGiftInsertProductValueStart",$log);
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{

			$CartArr  = Session::get('ShoppingCart.Cart');
			$ProdIDVal = array_column($CartArr, 'ProductID');
			$isFreeGiftsVal = array_column($CartArr, 'IS_Free_Gift');
			$ProdArrte = explode(",",$products_id);

			if(count($ProdArrte) > 0)
			{
				for($d=0;$d<count($ProdArrte);$d++)
				{

						if(in_array($ProdArrte[$d], $ProdIDVal) && in_array("Yes", $isFreeGiftsVal))
						{
							$Msg = "Same free gift product already added";
							$log['message'] = $Msg;
							addLog("SameFreeGift",$log);
							return $Msg;
						}
				}
			}

			$count = count (Session::get('ShoppingCart.Cart'));
			$Cart = Session::get('ShoppingCart.Cart');
			$Cart = array_values($Cart);
			$ProdArr = explode(",",$products_id);

			for($a=0; $a<$count; $a++)
			{
				/*if($Cart[$a]['SKU'] == $sku && $sku !='' )
				{
					unset($Cart[$a]);
				}*/
				if(isset($Cart[$a]["FreeGiftCoupon"]) && $Cart[$a]["FreeGiftCoupon"]=="Yes")
				{
					addLog("FreeGiftNull");
					return null;
				}
				else if(isset($Cart[$a]["IS_Free_Gift"]) && $Cart[$a]['IS_Free_Gift']=="Yes"  && $OneGift=="Yes")
				{
					addLog("FreeGiftUnset");
				  	unset($Cart[$a]);
				}
			}
			$Cart = array_values($Cart);
			Session::put('ShoppingCart.Cart',$Cart);
			$FreeGiftAdd = "No";
			$ProdArr = explode(",",$products_id);

			$FreeGiftProd = Products::whereIn('products_id',$ProdArr)->where('status','=','1')->get();

			$TotalFreeGift = count($FreeGiftProd ?? []);
			if($FreeGiftProd && $TotalFreeGift> 0)
			{

				for($i=0;$i<$TotalFreeGift;$i++)
				{
				$temp_ary = [];
				$free_gift_res = $this->SetProduct($FreeGiftProd[$i]);
				if($free_gift_res->current_stock > 0 || ($free_gift_res->cosmo_current_stock > 0 && $free_gift_res->cosmo_sku!='') || ($free_gift_res->nandansons_current_stock > 0 && $free_gift_res->nandansons_sku!='')  || ($free_gift_res->pca_current_stock > 0 && $free_gift_res->pca_sku!='') || ($free_gift_res->perfumeworldwide_currentstock > 0 && $free_gift_res->perfumeworldwide_sku!='') || ($free_gift_res->nd_current_stock > 0 && $free_gift_res->nd_sku!=''))
				{
					if(file_exists(config('global.PRD_THUMB_IMG_PATH').$free_gift_res->image) && !empty($free_gift_res->image))
						$thumb_image = config('global.PRD_THUMB_IMG_URL').$free_gift_res->image;
					else
						$thumb_image = config('global.NO_IMAGE_THUMB');

					//$thumb_image = str_replace(config('global.SITE_URL'),config('global.SECURED_PATH'),$thumb_image);

					$free_gift_res->prod_image ='<img src="'.$thumb_image.'" border="0" width="125"  alt="'.$free_gift_res->product_name.'" />';
					$free_gift_res->image_forpopup ='<img src="'.$thumb_image.'" border="0" width="75"  alt="'.$free_gift_res->product_name.'" />';
					$free_gift_res->billing_image ='<img src="'.$thumb_image.'" border="0" width="195" alt="'.$free_gift_res->product_name.'" title="'.$free_gift_res->product_name.'"/>';

					$VendorSKU 		= "";
					$IsCosmo  		= "No";
					$IsNandansons	= "No";
					$IsPerfumePW	= "No";
					$IsPCA	= "No";
					$IsND	= "No";

					if($free_gift_res->stock == "Out")
					{
						if($free_gift_res->cosmo_sku !='' &&  $free_gift_res->cosmo_current_stock > 0 )
						{
							$IsCosmo = "Yes";
							$VendorSKU = $free_gift_res->cosmo_sku;
						}
						else if($free_gift_res->pca_sku !='' &&  $free_gift_res->pca_current_stock > 0)
						{
							$IsPCA  = "Yes";
							$VendorSKU = $free_gift_res->pca_sku;
						}else if($free_gift_res->nandansons_sku !='' &&  $free_gift_res->nandansons_current_stock > 0)
						{
							$IsNandansons = "Yes";
							$VendorSKU = $free_gift_res->nandansons_sku;
						}
						else if($free_gift_res->perfumeworldwide_sku !='' &&  $free_gift_res->perfumeworldwide_currentstock > 0)
						{
							$IsPerfumePW = "Yes";
							$VendorSKU = $free_gift_res->perfumeworldwide_sku;
						}
						else if($free_gift_res->nd_sku !='' &&  $free_gift_res->nd_current_stock > 0)
						{
							$IsND = "Yes";
							$VendorSKU = $free_gift_res->nd_sku;
						}
					}
					$temp_ary = array();

					if($free_gift_res->WebsiteStock == "In")
					{
						$temp_ary['IsMaxaromaTwoDelivery'] = $free_gift_res->maxtwodaydelivery;
					}

					$fetch_category = DB::table('pu_category as c')
											->join('pu_products_category as pc','c.category_id','=','pc.category_id')
											->join('pu_products as p', 'pc.products_id','=','p.products_id')
											->where('p.products_id','=',$free_gift_res->products_id)->get();

				   $CategoryID = '0';
					if($fetch_category && $fetch_category->count() > 0)
					{
						$gcat = stripcslashes($fetch_category[0]->category_name);
						$CatInfo = config('CATEGORY_INFO');
						$breadcrumbs = $CatInfo['CatForProd'][$fetch_category[0]->category_id]['subcatbredcrum'];
						$CategoryID	= $fetch_category[0]->category_id;

					}

					$free_gift_res->product_name = remove_html_entities($free_gift_res->product_name);
					$free_gift_res->short_description = remove_html_entities($free_gift_res->short_description);

					$temp_ary['ProductID']   		= $free_gift_res->products_id;
					$temp_ary['CategoryID']   		= $CategoryID;
					$temp_ary['SKU']         		= "GIFT-".$free_gift_res->sku;
					$temp_ary['ORGSKU']         	= $free_gift_res->sku;
					$temp_ary['ProductName'] 		= stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_gift_res->product_name));
					$temp_ary['short_description'] 	= strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_gift_res->short_description)));
					$temp_ary['Billing_Image'] 		= $free_gift_res->billing_image;
					$temp_ary['Price']       		= 0;
					$temp_ary['Qty'] 		 		= 1;
					$temp_ary['TotPrice']    		= 0;
					$temp_ary['Image']       		= $free_gift_res->prod_image;
					$temp_ary['Prod_URL']       	= "";
					$temp_ary['IS_Free_Gift']       = "Yes";
					$temp_ary['image_forpopup']		= $free_gift_res->image_forpopup;
					$temp_ary['freeproductsid']		= $freeproductsid;
					$temp_ary['VendorSKU']			= $VendorSKU;
					$temp_ary['IsCosmo']			= $IsCosmo;
					$temp_ary['IsNandansons']		= $IsNandansons;
					$temp_ary['IsPerfumePW']		= $IsPerfumePW;
					$temp_ary['IsPCA']				= $IsPCA;
					$temp_ary['IsND']				= $IsND;
					$temp_ary['ImanufactureID']		= $free_gift_res->imanufactureid;
					$temp_ary['IsDealProducts']		= "No";
					$temp_ary['DealDiscountFlag']	= "No";
					$temp_ary['dealdiscount_flag']	= "No";
					$temp_ary['manufactureName']	= '';
					$temp_ary['CategoryName']		= '';
					$temp_ary['FinalSale']			= "";
					$Cart[]=$temp_ary;
					$this->CalculateSubTotal();
					$FreeGiftAdd = "Yes";
				}else{
					$SKUList .= $free_gift_res->sku.",";

				}
			}
			if(isset($SKUList) && $SKUList!='')
			{
				$SKUList = substr($SKUList,0,-1);
				$OutofStockMsg = "The Free bundle is out of  stock and cannot be added to your order and out of stock products ".$SKUList;
				$log['OutofStockMsg'] = $OutofStockMsg;
				addLog("FreeGiftInsertProductValueOutofStock",$log);
				Session::flash('OutOfStockBundle','The Free bundle is out of  stock and cannot be added to your order '.$SKUList);
			}
			if(count($Cart) > 0 && $FreeGiftAdd == "Yes")
			{
				   	$OutofStockMsg = "";
					Session::put('ShoppingCart.Cart',$Cart);
					$this->removeSampleItemsFromCart();
			}

		}
		}
		addLog("FreeGiftInsertProductValue");
		return 	$OutofStockMsg;

	}

	public function CheckFreeSampleInCart(){
		$is_free_sample_cart = "N";
		if(config('Settings.FREESAMPLE_VALUE')=="No" || (Session::has('eusertype') && Session::get('eusertype')=="Wholesaler"))
		{
			return null;
		}
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			//$Cart[$a]['Is_Free_Sample']=="Yes"
			$cartitem = Session::get('ShoppingCart.Cart');
			if(count($cartitem) > 0)
			{
				for($d=0;$d<count($cartitem);$d++)
				{
					if(isset($cartitem[$d]['Is_Free_Sample']) && $cartitem[$d]['Is_Free_Sample']=="Yes"){
						return "Yes";
					}
				}
			}

		}
	}

   public function CheckFreeGiftInCart($TotalValue, $TotalFreeGiftItems = 0, $FreeGiftProductId = 0)
{
    $onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');

    if ($onlyGCPurchased == 1) {
        return null;
    }

    if (
        config('Settings.FREEGIFTFLAG') == "No" ||
        (Session::has('eusertype') && Session::get('eusertype') == "Wholesaler")
    ) {
        return null;
    }

    $GiftCertiTotal = 0;

    if (Session::has('ShoppingCart.GiftCertiTotal')) {
        $GiftCertiTotal = NumberFormat(
            Session::get('ShoppingCart.GiftCertiTotal')
        );
    }

    $TotalValue = NumberFormat($TotalValue - $GiftCertiTotal);

    if (
        !Session::has('ShoppingCart.Cart') ||
        count(Session::get('ShoppingCart.Cart')) == 0
    ) {
        return "No";
    }

    $cartitem = Session::get('ShoppingCart.Cart');

    /*
     * ============================================================
     * BUILD REAL PURCHASE CART
     *
     * Free gifts / free samples are NOT used for rule matching.
     * ============================================================
     */
   $purchaseItems = [];

    foreach ($cartitem as $item) {

        if (
            isset($item["Is_Free_Sample"]) &&
            $item["Is_Free_Sample"] == "Yes"
        ) {
            continue;
        }

        /*
         * Existing FreeGiftCoupon behaviour
         */
        if (
            isset($item["FreeGiftCoupon"]) &&
            $item["FreeGiftCoupon"] == "Yes"
        ) {
            return "Yes";
        }

        /*
         * Free gift NEVER participates in rule calculation.
         */
        if (
            isset($item["IS_Free_Gift"]) &&
            $item["IS_Free_Gift"] == "Yes"
        ) {
            continue;
        }

        $purchaseItems[] = $item;
    }

    if (count($purchaseItems) == 0) {

    /*
     * No real purchase products left.
     *
     * Therefore any existing free gift is no longer valid.
     */
    for ($i = count($cartitem) - 1; $i >= 0; $i--) {

        if (
            isset($cartitem[$i]["IS_Free_Gift"]) &&
            $cartitem[$i]["IS_Free_Gift"] == "Yes"
        ) {
            unset($cartitem[$i]);
        }
    }

    $cartitem = array_values($cartitem);

    /*
     * Existing Yotpo free product handling
     */
    if (
        count($cartitem) == 1 &&
        isset($cartitem[0]['IsYotpoFreeProduct']) &&
        $cartitem[0]['IsYotpoFreeProduct'] == 'Yes'
    ) {
        $cartitem = [];
    }

    Session::put(
        'ShoppingCart.Cart',
        $cartitem
    );

    return "No";
}

    $today = date("Y-m-d");

    /*
     * ============================================================
     * GET ALL ACTIVE RULES
     * ============================================================
     */
    $rules = DB::table('pu_free_gift_product')
        ->select('*')
        ->where('status', '1')
        ->whereDate('start_date', '<=', $today)
        ->whereDate('end_date', '>=', $today)
        ->get();

    if ($rules->count() == 0) {
        return "No";
    }

    /*
     * ============================================================
     * FIND VALID RULES
     * ============================================================
     */
    $validRules = [];

    foreach ($rules as $rule) {

        $flag = trim((string)$rule->flag_range);

        /*
         * --------------------------------------------------------
         * RULE BRAND IDs
         * --------------------------------------------------------
         */
        $ruleBrandIds = [];

        if (
            $flag == "Brand" ||
            $flag == "Brand,Category"
        ) {

            $ruleBrandIds = DB::table('pu_freegift_brand')
                ->where('products_id', $rule->products_id)
                ->pluck('imanufactureid')
                ->map(function ($id) {
                    return (int)$id;
                })
                ->toArray();

            if (count($ruleBrandIds) == 0) {
                continue;
            }
        }

        /*
         * --------------------------------------------------------
         * RULE CATEGORY IDs
         * --------------------------------------------------------
         */
        $ruleCategoryIds = [];

        if (
            $flag == "Category" ||
            $flag == "Brand,Category"
        ) {

            $ruleCategoryIds = DB::table('pu_freegift_category')
                ->where('products_id', $rule->products_id)
                ->pluck('categoryid')
                ->map(function ($id) {
                    return (int)$id;
                })
                ->toArray();

            if (count($ruleCategoryIds) == 0) {
                continue;
            }
        }

        /*
         * ========================================================
         * CALCULATE THIS RULE'S QUALIFYING TOTAL
         * ========================================================
         */
        $ruleTotal = 0;

        /*
         * PRICE RULE
         */
        if ($flag == "") {

            foreach ($purchaseItems as $item) {

                $ruleTotal += NumberFormat(
                    isset($item["TotPrice"])
                        ? $item["TotPrice"]
                        : 0
                );
            }
        }

        /*
         * BRAND RULE
         */
        elseif ($flag == "Brand") {

            foreach ($purchaseItems as $item) {

                $itemBrandId = isset($item["ImanufactureID"])
                    ? (int)$item["ImanufactureID"]
                    : 0;

                if (
                    $itemBrandId > 0 &&
                    in_array($itemBrandId, $ruleBrandIds)
                ) {

                    $ruleTotal += NumberFormat(
                        isset($item["TotPrice"])
                            ? $item["TotPrice"]
                            : 0
                    );
                }
            }
        }

        /*
         * CATEGORY RULE
         */
        elseif ($flag == "Category") {

            foreach ($purchaseItems as $item) {

                $itemCategoryId = isset($item["CategoryID"])
                    ? (int)$item["CategoryID"]
                    : 0;

                if (
                    $itemCategoryId > 0 &&
                    in_array($itemCategoryId, $ruleCategoryIds)
                ) {

                    $ruleTotal += NumberFormat(
                        isset($item["TotPrice"])
                            ? $item["TotPrice"]
                            : 0
                    );
                }
            }
        }

        /*
         * BRAND + CATEGORY RULE
         */
        elseif ($flag == "Brand,Category") {

            foreach ($purchaseItems as $item) {

                $itemBrandId = isset($item["ImanufactureID"])
                    ? (int)$item["ImanufactureID"]
                    : 0;

                $itemCategoryId = isset($item["CategoryID"])
                    ? (int)$item["CategoryID"]
                    : 0;

                /*
                 * BOTH must match.
                 */
                if (
                    $itemBrandId > 0 &&
                    $itemCategoryId > 0 &&
                    in_array($itemBrandId, $ruleBrandIds) &&
                    in_array($itemCategoryId, $ruleCategoryIds)
                ) {

                    $ruleTotal += NumberFormat(
                        isset($item["TotPrice"])
                            ? $item["TotPrice"]
                            : 0
                    );
                }
            }
        }

        else {
            continue;
        }

        $ruleTotal = NumberFormat($ruleTotal);

        /*
         * ========================================================
         * EXCLUDE SKU
         * ========================================================
         */
        if (
            isset($rule->exclude_sku) &&
            trim($rule->exclude_sku) != ''
        ) {

            $excludeSkus = array_unique(
                array_filter(
                    array_map(
                        'trim',
                        explode('#', $rule->exclude_sku)
                    ),
                    'strlen'
                )
            );

            foreach ($purchaseItems as $item) {

                if (
                    isset($item["SKU"]) &&
                    in_array($item["SKU"], $excludeSkus)
                ) {

                    $ruleTotal -= NumberFormat(
                        isset($item["TotPrice"])
                            ? $item["TotPrice"]
                            : 0
                    );
                }
            }
        }

        /*
         * ========================================================
         * EXCLUDE POCKET PERFUME
         * ========================================================
         */
        if (
            isset($rule->exclude_pocketperfume) &&
            $rule->exclude_pocketperfume == "Yes"
        ) {

            $pocketPerfumeCategory =
                $this->getPocketPerfumeCategory();

            foreach ($purchaseItems as $item) {

                $itemCategoryId = isset($item["CategoryID"])
                    ? (int)$item["CategoryID"]
                    : 0;

                if (
                    $itemCategoryId > 0 &&
                    in_array(
                        $itemCategoryId,
                        $pocketPerfumeCategory
                    )
                ) {

                    $ruleTotal -= NumberFormat(
                        isset($item["TotPrice"])
                            ? $item["TotPrice"]
                            : 0
                    );
                }
            }
        }

        $ruleTotal = NumberFormat($ruleTotal);

        /*
         * ========================================================
         * RANGE CHECK
         * ========================================================
         */
        $start = NumberFormat($rule->price_start_range);
        $end   = NumberFormat($rule->price_end_range);

        /*
         * Rule starts only when qualifying amount
         * reaches start range.
         */
        if ($ruleTotal < $start) {
            continue;
        }

        /*
         * This rule is valid.
         */
        $validRules[] = [
            'rule'       => $rule,
            'rule_total' => $ruleTotal,
            'start'      => $start,
            'end'        => $end,
            'flag'       => $flag
        ];
    }

    /*
     * ============================================================
     * NO VALID RULE
     *
     * IMPORTANT:
     * Existing gift stays.
     * ============================================================
     */
    if (count($validRules) == 0) {

    $hasFreeGift = false;

    foreach ($cartitem as $item) {

        if (
            isset($item["IS_Free_Gift"]) &&
            $item["IS_Free_Gift"] == "Yes"
        ) {
            $hasFreeGift = true;
            break;
        }
    }

		if ($hasFreeGift) {

			for ($i = count($cartitem) - 1; $i >= 0; $i--) {

				if (
					isset($cartitem[$i]["IS_Free_Gift"]) &&
					$cartitem[$i]["IS_Free_Gift"] == "Yes"
				) {
					unset($cartitem[$i]);
				}
			}

			$cartitem = array_values($cartitem);

			if (
				count($cartitem) == 1 &&
				isset($cartitem[0]['IsYotpoFreeProduct']) &&
				$cartitem[0]['IsYotpoFreeProduct'] == 'Yes'
			) {
				$cartitem = [];
			}

			Session::put(
				'ShoppingCart.Cart',
				$cartitem
			);
		}

		return "No";
	}

    /*
     * ============================================================
     * SELECT BEST RULE
     * ============================================================
     */
    usort($validRules, function ($a, $b) {

        /*
         * Highest start range wins.
         */
        if ($a['start'] != $b['start']) {
            return ($a['start'] < $b['start']) ? 1 : -1;
        }

        /*
         * Same range:
         *
         * Brand + Category
         *      >
         * Brand
         *      >
         * Category
         *      >
         * Price
         */
        $priority = [
            'Brand,Category' => 4,
            'Brand'          => 3,
            'Category'       => 2,
            ''               => 1
        ];

        $pa = isset($priority[$a['flag']])
            ? $priority[$a['flag']]
            : 0;

        $pb = isset($priority[$b['flag']])
            ? $priority[$b['flag']]
            : 0;

        return $pb <=> $pa;
    });

    $free_gift_res = $validRules[0]['rule'];

    /*
     * ============================================================
     * GET FREE GIFT SKUs
     * ============================================================
     */
    $FreeGiftValue = [];

    if (
        isset($free_gift_res->sku) &&
        trim($free_gift_res->sku) != ''
    ) {

        $FreeGiftValue = array_filter(
            array_map(
                'trim',
                explode('#', $free_gift_res->sku)
            ),
            'strlen'
        );
    }

    if (count($FreeGiftValue) == 0) {
        return "No";
    }

    /*
     * ============================================================
     * GET FREE GIFT PRODUCTS
     * ============================================================
     */
    $product_res = Products::whereIn('sku', $FreeGiftValue)
        ->where('is_free_gift_products', 'Yes')
        ->where('status', '1')
        ->get();

    $TotalProducts = count($product_res);
    $isProducts = "No";

    if ($TotalProducts > 0) {

        for ($i = 0; $i < $TotalProducts; $i++) {

            $product_res[$i] =
                $this->SetProduct($product_res[$i]);

            if (
                $product_res[$i]["current_stock"] > 0 ||

                (
                    $product_res[$i]["cosmo_current_stock"] > 0 &&
                    $product_res[$i]["cosmo_sku"] != ''
                ) ||

                (
                    $product_res[$i]["nandansons_current_stock"] > 0 &&
                    $product_res[$i]["nandansons_sku"] != ''
                ) ||

                (
                    $product_res[$i]["pca_current_stock"] > 0 &&
                    $product_res[$i]["pca_sku"] != ''
                ) ||

                (
                    $product_res[$i]["perfumeworldwide_currentstock"] > 0 &&
                    $product_res[$i]["perfumeworldwide_sku"] != ''
                ) ||

                (
                    $product_res[$i]["nd_current_stock"] > 0 &&
                    $product_res[$i]["nd_sku"] != ''
                )
            ) {

                $isProducts = "Yes";
                break;
            }
        }
    }

    /*
     * ============================================================
     * NO STOCK
     * ============================================================
     */
    if ($isProducts != "Yes") {

        if ($TotalProducts == 1) {
            return "one";
        }

        /*
         * No usable new gift.
         * Existing gift stays.
         */
        foreach ($cartitem as $item) {

            if (
                isset($item["IS_Free_Gift"]) &&
                $item["IS_Free_Gift"] == "Yes"
            ) {
                return "Yes";
            }
        }

        return "No";
    }

    /*
     * ============================================================
     * IMPORTANT: FREE GIFT COUNT
     * ============================================================
     *
     * $TotalFreeGiftItems =
     * number of free gifts currently in cart.
     *
     * freegift_add_count =
     * maximum number of gifts allowed for THIS rule.
     *
     * Example:
     *
     * add_count = 2
     *
     * cart gifts = 0 => popup
     * cart gifts = 1 => popup
     * cart gifts = 2 => no popup
     *
     * IMPORTANT:
     * We check this AFTER selecting the correct rule.
     * ============================================================
     */
    $currentFreeGiftCount = (int)$TotalFreeGiftItems;
    $ruleFreeGiftCount    = (int)$free_gift_res->freegift_add_count;

    /*
     * ============================================================
     * CHECK WHETHER CURRENT RULE IS SAME RULE
     * ============================================================
     */
    $sameRule = false;

    if (
        !empty($free_gift_res->products_id) &&
        !empty($FreeGiftProductId) &&
        (int)$free_gift_res->products_id ==
        (int)$FreeGiftProductId
    ) {

        $sameRule = true;
    }

    /*
     * ============================================================
     * DIFFERENT RULE
     *
     * A NEW VALID RULE has arrived.
     *
     * Remove existing old free gifts.
     *
     * ONLY when FreeGiftProductId confirms that the
     * currently selected/previous rule is different.
     * ============================================================
     */
    if (
        !$sameRule &&
        !empty($free_gift_res->products_id) &&
        !empty($FreeGiftProductId)
    ) {

        $hasOldFreeGift = false;

        foreach ($cartitem as $item) {

            if (
                isset($item["IS_Free_Gift"]) &&
                $item["IS_Free_Gift"] == "Yes"
            ) {
                $hasOldFreeGift = true;
                break;
            }
        }

        if ($hasOldFreeGift) {

            for ($i = count($cartitem) - 1; $i >= 0; $i--) {

                if (
                    isset($cartitem[$i]["IS_Free_Gift"]) &&
                    $cartitem[$i]["IS_Free_Gift"] == "Yes"
                ) {

                    unset($cartitem[$i]);
                }
            }

            $cartitem = array_values($cartitem);

            /*
             * Existing Yotpo free product handling.
             */
            if (
                count($cartitem) == 1 &&
                isset($cartitem[0]['IsYotpoFreeProduct']) &&
                $cartitem[0]['IsYotpoFreeProduct'] == 'Yes'
            ) {
                $cartitem = [];
            }

            Session::put(
                'ShoppingCart.Cart',
                $cartitem
            );

            /*
             * New rule exists.
             * Let popup flow run again.
             */
            return "No";
        }
    }

    /*
     * ============================================================
     * SAME RULE + FREE GIFT COUNT
     * ============================================================
     *
     * This is the missing part.
     *
     * If rule allows 2 gifts:
     *
     * 0 added -> No
     * 1 added -> No
     * 2 added -> Yes
     * ============================================================
     */
    if ($sameRule) {
        if ($ruleFreeGiftCount > 0) {

            if (
                $currentFreeGiftCount <
                $ruleFreeGiftCount
            ) {

                /*
                 * More gifts can still be selected.
                 *
                 * DO NOT remove existing gifts.
                 */
                return "No";

            } else {

			/*
                 * Required number already added.
                 *
                 * Popup should NOT open again.
                 */
                return "Yes";
            }
        }

        /*
         * freegift_add_count = 0
         *
         * Existing behaviour:
         * rule has no count restriction.
         */
        return "Yes";
    }

    /*
     * ============================================================
     * NO EXISTING RULE/GIFT ID
     *
     * If there is no FreeGiftProductId, do not remove anything.
     *
     * Count still needs to be respected.
     * ============================================================
     */
    if (empty($FreeGiftProductId)) {

        if ($ruleFreeGiftCount > 0) {

            if (
                $currentFreeGiftCount <
                $ruleFreeGiftCount
            ) {
                return "No";
            }

            return "Yes";
        }

        return "No";
    }

    /*
     * ============================================================
     * NEW RULE WITH NO EXISTING GIFT
     *
     * Popup/add flow should continue.
     * ============================================================
     */
    return "No";
}

  	public function SetFreegift($Gift_Free_In_Cart)
	{
		if(Auth::guard('store')->check())
		{
			return null;
		}
		if($Gift_Free_In_Cart == 'No')
		{
			$AllDiscounts = $this->GetAllDiscounts();
			$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];
			if(strtolower(trim(Session::get('eusertype') ?? '')) !="wholesaler" && trim(Session::get('is_dropshipper') ?? '') != "Yes" && $Gift_Free_In_Cart == "No")
			{
				$TotalFreeGiftItems = $this->GetTotalItemsOfFreegift();
				$this->PageData['TotalFreeGiftItems'] = $TotalFreeGiftItems;
				$FreeGiftProductId  = $this->GetFreegiftId();
				$Free_Gift_Res = $this->GetFreeCouponPopup($this->GetNetTotal(),$TotalFreeGiftItems,$FreeGiftProductId);
				$this->PageData['TotalFreeavailItems'] = !empty($Free_Gift_Res) ? count($Free_Gift_Res) : 0;

				if(isset($Free_Gift_Res) && is_array($Free_Gift_Res) && count($Free_Gift_Res) == 1)
				{
					$products_id = $Free_Gift_Res[0]['products_id'];
					$freeproductsid = $Free_Gift_Res[0]['free_gift_products_id'];
					$FreeGiftMsg = $this->FreeGiftInsertProductValue($products_id,$freeproductsid,"Yes");
					$TotalValueFree = (isset($Free_Gift_Res)  &&  count($Free_Gift_Res) ==1 ) ? "Yes" : "No";
					Session::put('ShoppingCart.IsFreeGiftCount',$TotalValueFree);

					if(trim($FreeGiftMsg) == "")
						$Msg = "Free Gift Product Added Successfully";
					else
						$Msg = $FreeGiftMsg;
					Session::flash('CartSuccess',$Msg);
				}
			}
		}
	}

	public function RemoveFreeSampleCache()
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$cartitem = Session::get('ShoppingCart.Cart');
			if(count($cartitem) > 0)
			{
				for($i=0;$i<count($cartitem);$i++)
				{
					if(isset($cartitem[$i]["Is_Free_Sample"]) && $cartitem[$i]["Is_Free_Sample"]=="Yes")
					{
						$this->removeSampleItemsFromCartOne($cartitem[$i]["SKU"]);
					}
				}
			}
		}
	}

	public function removeSampleItemsFromCartOne($sku)
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')))
		{
			$count = count(Session::get('ShoppingCart.Cart'));
			$TempCart = array();
			$CartInfo = Session::get('ShoppingCart.Cart');
			foreach($CartInfo as $a => $Cart)
			{
				if($Cart['SKU'] == $sku)
				{
					if(isset($Cart["Is_Free_Sample"]) && $Cart['Is_Free_Sample']=="Yes")
						unset($Cart);
				}else if($Cart['SKU']==""){
					unset($Cart);
				}else{
					$TempCart[] = $Cart;
				}
			}
			Session::put('ShoppingCart.Cart',$TempCart);
			$this->CalculateSubTotal();
		}
	}

	public function RemoveFreeGiftCache()
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$cartitem = Session::get('ShoppingCart.Cart');
			if(count($cartitem) > 0)
			{
				for($i=0;$i<count($cartitem);$i++)
				{
					if(isset($cartitem[$i]["IS_Free_Gift"]) && $cartitem[$i]["IS_Free_Gift"]=="Yes")
					{
						$this->RemoveFreeGiftValueProduct($cartitem[$i]["SKU"]);
					}
				}
			}
		}
	}

	public function GenerateShopCartFromCookieAfterLogin()
	{
		$ArrMyShopCart = array();

		$IsGiftCertificateItem = '';
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		//if( Auth::user())
		if($normaluser)
		{
			$CustomerID = Session::get('sess_icustomerid');
			$ArrMyShopCart = Shoppingcart::where('customer_id','=',$CustomerID)->get();
			if($ArrMyShopCart && $ArrMyShopCart->count() > 0)
				$ArrMyShopCart = unserialize(stripslashes($ArrMyShopCart[0]["cart_string"]));
		}elseif(trim(Cookie::get('MY_SHOP_CART_COOKIE')) != ''){
			$CookieID = trim(Cookie::get('MY_SHOP_CART_COOKIE'));
			$ArrMyShopCart = Shoppingcart::where('cookie_id','=',$CookieID)->get();
			if($ArrMyShopCart && $ArrMyShopCart->count() > 0)
				$ArrMyShopCart = unserialize(stripslashes($ArrMyShopCart[0]["cart_string"]));
		}

        if (count($ArrMyShopCart) == 0) {
            return null;
        }

        Session::put("RemoveItem",'');
        $RemoveItem = '';
		$CartRequest = new \Illuminate\Http\Request();

		if (count($ArrMyShopCart) > 0){
		for ($p = 0; $p < count($ArrMyShopCart); $p++) {
            $prod_sku = strtolower(trim($ArrMyShopCart[$p]['SKU'] ?? ''));
            $quantity = (int) $ArrMyShopCart[$p]['Qty'];

            $IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$ArrMyShopCart[$p]);

            //if($ArrMyShopCart[$p]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $ArrMyShopCart[$p]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
             if($IsGiftCertificateItem == 'Yes')
			{

				$data['gc_value'] = 0;

				if(isset($ArrMyShopCart[$p]['Price']) && $ArrMyShopCart[$p]['Price']  > 0)
				{
					$data['gc_value'] = $ArrMyShopCart[$p]['Price'];
				}else{
					$data['gc_value'] = $data['gc_value'];
				}

				if(isset($ArrMyShopCart[$p]['RecipientName']) && $ArrMyShopCart[$p]['RecipientName'] != ''){
					$data['recipient_name'] = $ArrMyShopCart[$p]['RecipientName'];
				}

				if(isset($ArrMyShopCart[$p]['RecipientEmail']) && $ArrMyShopCart[$p]['RecipientEmail'] != ''){
					$data['recipient_email'] = $ArrMyShopCart[$p]['RecipientEmail'];
				}

				if(isset($ArrMyShopCart[$p]['Subject']) && $ArrMyShopCart[$p]['Subject'] != ''){
					$data['subject'] = $ArrMyShopCart[$p]['Subject'];
				}

				if(isset($ArrMyShopCart[$p]['Message']) && $ArrMyShopCart[$p]['Message'] != ''){
					$data['message'] = $ArrMyShopCart[$p]['Message'];
				}

				/*if(isset($ArrMyShopCart[$p]['Signature']) && $ArrMyShopCart[$p]['Signature'] != ''){
					$data['signature'] = $ArrMyShopCart[$p]['Signature'];
				}*/

				if(isset($ArrMyShopCart[$p]['DeliveryDate']) && $ArrMyShopCart[$p]['DeliveryDate'] != ''){
					$data['deliverydate'] = $ArrMyShopCart[$p]['DeliveryDate'];
				}

				if(isset($ArrMyShopCart[$p]['YourName']) && $ArrMyShopCart[$p]['YourName'] != ''){
					$data['yourname'] = $ArrMyShopCart[$p]['YourName'];
				}

				if(isset($ArrMyShopCart[$p]['YourEmail']) && $ArrMyShopCart[$p]['YourEmail'] != ''){
					$data['youremail'] = $ArrMyShopCart[$p]['YourEmail'];
				}

				if(isset($ArrMyShopCart[$p]['GiftImage']) && $ArrMyShopCart[$p]['GiftImage'] != ''){
					$data["GiftImage"] = $ArrMyShopCart[$p]['GiftImage'];
				}

				if($data['gc_value'] >= config('Settings.MINIMUM_GIFTCERTIFICATE_AMOUNT') && $data['gc_value'] <= config('Settings.MAXIMUM_GIFTCERTIFICATE_AMOUNT'))
				{
					$CartRequest->merge($data);
					$this->insertGiftCertificate($CartRequest);
				}
			}else{
				$ProductRs = Products::where('status','=','1')->where(DB::raw('lower(sku)'),'=',$prod_sku)->get();
				if($ProductRs && $ProductRs->count() > 0)
				{
					$ProductRs = $this->SetProduct($ProductRs[0]);
					if($ProductRs->product_price > 0 && ($ProductRs->current_stock > 0 || ($ProductRs->cosmo_current_stock > 0 && $ProductRs->cosmo_sku!='') || ($ProductRs->nandansons_current_stock > 0 && $ProductRs->nandansons_sku!='') || ($ProductRs->perfumeworldwide_currentstock > 0 && $ProductRs->perfumeworldwide_sku!='') || ($ProductRs->nd_current_stock > 0 && $ProductRs->nd_sku!='')))
					{
						// $RemoveItem.= $prod_sku.",";
						// $products_id = $ProductRs->products_id;
						// $this->AddToCart($products_id,$quantity,'Yes');
						$availableStock =  $ProductRs->current_stock - $ProductRs->minimum_stock;
						if($quantity > $availableStock)
						{
							$quantity = $availableStock;
						}
						$RemoveItem.= $prod_sku.",";
						$products_id = $ProductRs->products_id;
						if(Session::has('eusertype') &&	Session::get('eusertype') == 'Wholesaler' && ((isset($ArrMyShopCart[$p]['IS_Free_Gift']) && $ArrMyShopCart[$p]['IS_Free_Gift']=="Yes") || isset($ArrMyShopCart[$p]['Is_Free_Sample']) && $ArrMyShopCart[$p]['Is_Free_Sample']=="Yes" )  ){
							continue;
						}

						$this->AddToCart($products_id,$quantity,'Yes');
					}
				} else {
					continue;
				}
			}
        }}
		//$this->StoreShopCartInCookie();
        Session::put("RemoveItem",substr($RemoveItem,0,-1));
        return null;
    }

	public function ReformatCartPrice()
	{

		$IsGiftCertificateItem = '';

		if(Session::has('ShoppingCart.Cart'))
		{
			$Cart = Session::get('ShoppingCart.Cart');
			$count = 0;
			if($Cart != null)
			{
				$count = count (Session::get('ShoppingCart.Cart'));
				if($count <=0)
					return null;
			}
			$TempCart = Session::get('ShoppingCart.Cart');
			Session::forget('ShoppingCart');
			$CartRequest = new \Illuminate\Http\Request();
			for($a=0; $a<$count; $a++)
			{
				$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$TempCart[$a]);

				//if($TempCart[$a]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $TempCart[$a]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
				if($IsGiftCertificateItem == 'Yes')
				{
					$data['gc_value']     	= $TempCart[$a]['Price'];
					$data['recipient_name']	= $TempCart[$a]['RecipientName'];
					$data['recipient_email']	= $TempCart[$a]['RecipientEmail'];
					$data['subject']			= $TempCart[$a]['Subject'];
					$data['message']			= $TempCart[$a]['Message'];
					//$data['signature']		= (isset($TempCart[$a]['Signature']) ? $TempCart[$a]['Signature'] : '');
					$data['deliverydate']		= $TempCart[$a]['DeliveryDate'];
					$data['yourname']			= $TempCart[$a]['YourName'];
					$data['youremail']		= $TempCart[$a]['YourEmail'];
					$data["GiftImage"]		= $TempCart[$a]['GiftImage'];
					$CartRequest->merge($data);
					$this->insertGiftCertificate($CartRequest);
				}
				else
				{
					$products_id 	= (int)$TempCart[$a]['ProductID'];
					$quantity  		= (int)$TempCart[$a]['Qty'];

					if(isset($TempCart[$a]["freeproductsid"]) && $TempCart[$a]["freeproductsid"]  > 0)
					{
						continue;
					}

					$this->AddToCart($products_id,$quantity);
				}
			}
			$a = $this->CalculateSubTotal();
		}
	}

	public function ApplyCreditDiscount($check)
	{
		Session::put('ShoppingCart.credit_limit_discount',0);
		Session::put('ShoppingCart.customer_remaining_credit_amount',0);
		$NetTotal = $this->GetNetTotal() - Session::get('shipping_insurance_charge');
		$log[] = "";

		//$user = Auth::guard('web')->user();
		$user = Auth::user();
		if (Auth::guard('store')->check()) {
			$user = Auth::guard('web')->user();
		}

		$creditLimit = $user->credit_limit ?? 0;

		if($user && Session::get('etype') == "M" && $user->is_dropshipper !='Yes' && config('Settings.WHOLESALE_CREDIT_LIMIT') == 'Yes')
		{
			if($creditLimit > 0 && $check==1)
			{
				if($creditLimit > $NetTotal)
				{
					$credit_limit_discount = $this->GetNetTotal();
					$rem_amt = $creditLimit - $NetTotal;
				}
				else
				{
					$credit_limit_discount = $creditLimit;
					$rem_amt = 0.00;
				}
				Session::put('ShoppingCart.credit_limit_discount',$credit_limit_discount);
				$log['credit_limit_discount'] = $credit_limit_discount;
				$CartInfo = Session::get('ShoppingCart.Cart');
				$TotalAmount = 0;
				for($i=0;$i<count($CartInfo);$i++)
				{
					$FreeGift = '';
					if(isset($CartInfo[$i]["IS_Free_Gift"]))
						$FreeGift = $CartInfo[$i]["IS_Free_Gift"];

					$FreeSample = '';
					if(isset($CartInfo[$i]["Is_Free_Sample"]))
						$FreeSample = $CartInfo[$i]["Is_Free_Sample"];

					if(isset($CartInfo[$i]["IsDealProducts"]) && $CartInfo[$i]["IsDealProducts"]!="Yes" && $FreeGift!="Yes" && $FreeSample != "Yes")
					{
						$TotalAmount = $TotalAmount + $CartInfo[$i]['TotPrice'];
					}

				}
				if($TotalAmount  > 0)
				{
					for($i=0;$i<count($CartInfo);$i++)
					{
						$FreeGift = '';
						if(isset($CartInfo[$i]["IS_Free_Gift"]))
							$FreeGift = $CartInfo[$i]["IS_Free_Gift"];

						$FreeSample = '';
						if(isset($CartInfo[$i]["Is_Free_Sample"]))
							$FreeSample = $CartInfo[$i]["Is_Free_Sample"];
						if((isset($CartInfo[$i]["IsDealProducts"]) && $CartInfo[$i]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes")
						{

							$CreditLimitDiscountItemWise = (($credit_limit_discount*100)/$TotalAmount);
							$CreditLimitDiscountCal = (($CartInfo[$i]['TotPrice']  * $CreditLimitDiscountItemWise)/100);

							Session::put('ShoppingCart.Cart.'.$i.'.CreditLimitItemWiseDiscout',$CreditLimitDiscountCal);
						}

					}
				}

			}
			else
			{
				Session::put('ShoppingCart.credit_limit_discount',0.00);
				$rem_amt = 0.00;
			}
		}
		else
		{
			Session::put('ShoppingCart.credit_limit_discount',0.00);
			$rem_amt = 0.00;
		}
		//echo  Session::get('ShoppingCart.credit_limit_discount'); exit;
		Session::put('ShoppingCart.customer_remaining_credit_amount',$rem_amt);
		$log['customer_remaining_credit_amount'] = $rem_amt;
		addLog('ApplyCreditDiscount',$log);
		return NumberFormat($rem_amt)."###".$this->GetNetTotal();
	}

	public function ApplyGiftCoupons($coupon)
	{
		$log['coupon'] = json_encode($coupon);
		addLog("ApplyGiftCouponsStart",$log);
		$totvalue = $this->GetNetTotal();

		if($totvalue<=0)
		{
			$totvalue = 0;
		}

		$CartInfo = Session::get('ShoppingCart.Cart');

		$Gifcard = "No";

		$TotalAmount = 0;
		if(count($CartInfo) > 0)
		{
			for($i=0;$i<count($CartInfo);$i++)
			{
				if(isset($CartInfo[$i]['IsGiftCertificateItem']) && $CartInfo[$i]['IsGiftCertificateItem']=="Yes")
				{
					$Gifcard = "Yes";
					break;
				}
				$FreeGift = '';
				if(isset($CartInfo[$i]["IS_Free_Gift"]))
					$FreeGift = $CartInfo[$i]["IS_Free_Gift"];

				$FreeSample = '';
				if(isset($CartInfo[$i]["Is_Free_Sample"]))
					$FreeSample = $CartInfo[$i]["Is_Free_Sample"];
				if(isset($CartInfo[$i]["IsDealProducts"]) && $CartInfo[$i]["IsDealProducts"]!="Yes" && $FreeGift!="Yes" && $FreeSample != "Yes")
				{
					$TotalAmount = $TotalAmount + $CartInfo[$i]['TotPrice'];
				}

			}
		}

		if(isset($Gifcard) &&  $Gifcard=="Yes")
		{
			Session::put('ShoppingCart.GiftCoupon.Code','');
			Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value',0.0);
			addLog("ResetGiftCoupon");

			return $Gifcard;
		}

		$coupon = trim($coupon);
		$CouponRS = GiftCertificate::where('remaining_value','>',0)->where('status','=','1')->where('gc_code','=',$coupon)->where('expiry_date','>=',DB::raw('curdate()'))->get();

		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$log['giftcouponrecordset'] = json_encode($CouponRS);
		addLog("GiftCouponRecordset",$log);

		if($CouponRS && $CouponRS->count() > 0 && $SubTotal >= $CouponRS[0]['minimum_purchase_value'] && isset($CouponRS[0]['gc_id']) && $CouponRS[0]['gc_id'] >0 && isset($CouponRS[0]['is_added_by_admin']) && $CouponRS[0]['is_added_by_admin']=='No')
		{

			$OrderRESData = OrderDetail::where('orders_detail_id','=',$CouponRS[0]['orders_detail_id'])->get();

			if($OrderRESData->count() > 0 && isset($OrderRESData[0]['orders_detail_id']) && $OrderRESData[0]['orders_detail_id'] > 0)
			{

				$OrderResVal = Order::where('orders_id','=',$OrderRESData[0]['orders_id'])->whereIn('status',['Pending','Completed'])->get();

				if($OrderResVal->count() > 0 && isset($OrderResVal[0]['orders_id']) && $OrderResVal[0]['orders_id'] > 0)
				{

					$remainingValue = $CouponRS[0]['remaining_value'];
					if($CouponRS[0]['remaining_value'] >= $totvalue)
					{
						$CouponRS[0]['remaining_value'] = $totvalue;
						$CouponRS[0]['remaining_value'] = NumberFormat($CouponRS[0]['remaining_value']);
					}

					Session::put('ShoppingCart.GiftCoupon.Code',$CouponRS[0]['gc_code']);
					Session::put('ShoppingCart.GiftCoupon.Value', $CouponRS[0]['remaining_value']);
					Session::put('ShoppingCart.GiftCoupon.Applicable_Value', $CouponRS[0]['remaining_value']);
					$NewValue = $remainingValue - Session::get('ShoppingCart.GiftCoupon.Applicable_Value');
					if($TotalAmount > 0)
					{
						for($i=0;$i<count($CartInfo);$i++)
						{
							$FreeGift = '';
							if(isset($CartInfo[$i]["IS_Free_Gift"]))
								$FreeGift = $CartInfo[$i]["IS_Free_Gift"];

							$FreeSample = '';
							if(isset($CartInfo[$i]["Is_Free_Sample"]))
								$FreeSample = $CartInfo[$i]["Is_Free_Sample"];
							if((isset($CartInfo[$i]["IsDealProducts"]) && $CartInfo[$i]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes")
							{
								$GiftCertificateDiscountItemWise = (($CouponRS[0]['remaining_value']*100)/$TotalAmount);
								$GiftCertificateDiscountCal = (($CartInfo[$i]['TotPrice']  * $GiftCertificateDiscountItemWise)/100);
								Session::put('ShoppingCart.Cart.'.$i.'.GiftCertificateItemWiseDiscout',$GiftCertificateDiscountCal);
							}

						}

					}
					Session::put('ShoppingCart.GiftCoupon.Remaining_Value',$NewValue);
					$log['giftcouponRemaining_Value'] = json_encode($NewValue);
					addLog("SetGiftCouponRemainingValue",$log);
					return 1;
				 }
				 else
				 {
					Session::put('ShoppingCart.GiftCoupon.Code','');
					Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
					Session::put('ShoppingCart.GiftCoupon.Applicable_Value',0.0);
					addLog("ResetGiftCoupon_2");
					return 2;
				 }

			}
			else
			 {
				Session::put('ShoppingCart.GiftCoupon.Code','');
				Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
				Session::put('ShoppingCart.GiftCoupon.Applicable_Value',0.0);
				addLog("ResetGiftCoupon_3");
				return 2;
			 }
		}
		else if($CouponRS && $CouponRS->count() > 0 && $SubTotal >= $CouponRS[0]['minimum_purchase_value'] && isset($CouponRS[0]['is_added_by_admin']) && $CouponRS[0]['is_added_by_admin']=='Yes'&& isset($CouponRS[0]['gc_id']) && $CouponRS[0]['gc_id'] >0)
		{
			$remainingValue = $CouponRS[0]['remaining_value'];
			if($CouponRS[0]['remaining_value'] >= $totvalue)
			{
				$CouponRS[0]['remaining_value'] = $totvalue;
				$CouponRS[0]['remaining_value'] = NumberFormat($CouponRS[0]['remaining_value']);
			}

			Session::put('ShoppingCart.GiftCoupon.Code',$CouponRS[0]['gc_code']);
			Session::put('ShoppingCart.GiftCoupon.Value', $CouponRS[0]['remaining_value']);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value', $CouponRS[0]['remaining_value']);
			$NewValue = $remainingValue - Session::get('ShoppingCart.GiftCoupon.Applicable_Value');
			Session::put('ShoppingCart.GiftCoupon.Remaining_Value',$NewValue);
			$log['giftcouponRemaining_Value'] = json_encode($NewValue);

			if($TotalAmount > 0)
			{
				for($i=0;$i<count($CartInfo);$i++)
				{
					$FreeGift = '';
					if(isset($CartInfo[$i]["IS_Free_Gift"]))
						$FreeGift = $CartInfo[$i]["IS_Free_Gift"];

					$FreeSample = '';
					if(isset($CartInfo[$i]["Is_Free_Sample"]))
						$FreeSample = $CartInfo[$i]["Is_Free_Sample"];

					if((isset($CartInfo[$i]["IsDealProducts"]) && $CartInfo[$i]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && $FreeSample != "Yes")
					{
						$GiftCertificateDiscountItemWise = (($CouponRS[0]['remaining_value']*100)/$TotalAmount);
						$GiftCertificateDiscountCal = (($CartInfo[$i]['TotPrice']  * $GiftCertificateDiscountItemWise)/100);
						Session::put('ShoppingCart.Cart.'.$i.'.GiftCertificateItemWiseDiscout',$GiftCertificateDiscountCal);
					}

				}

			}

			addLog("SetGiftCoupon",$log);
			return 1;
		}

		else
		{
			Session::put('ShoppingCart.GiftCoupon.Code','');
			Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value',0.0);
			addLog("ResetGiftCoupon_4");
			return 2;
		}
	}

	public function removeSampleItemsFromCart(){
		if(Session::has('ShoppingCart.Cart')){
			$count = count(Session::get('ShoppingCart.Cart'));
			$Cart = Session::get('ShoppingCart.Cart');
			$Cart = array_values($Cart);
			for($a=0; $a<count(Session::get('ShoppingCart.Cart')); $a++)
			{
				if(isset($Cart[$a]["Is_Free_Sample"]) && $Cart[$a]['Is_Free_Sample']=="Yes")
				{
					unset($Cart[$a]);
				}
			}
			$Cart = array_values($Cart);
			Session::put('ShoppingCart.Cart',$Cart);

		}
		$this->CalculateSubTotal();
	}

	public function getSampleProductsCustomerChoice($TotalValue){

		$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');
		if($onlyGCPurchased==1)
		{
			return null;
		}

        $GiftCertiTotal = 0;
        if(Session::has('ShoppingCart.GiftCertiTotal'))
          $GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));

        $TotalValue = $TotalValue - $GiftCertiTotal;

		$ProductArr = array();
		$customer_choice = "0";
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart'))){
			$cartitem = Session::get('ShoppingCart.Cart');
			$today = Carbon::today()->toDateString();
			$ProductArr = array();
			$matchingSamples = FreeVialSampleProduct::where('status', '1')
				->where('price_start_range', '<=', $TotalValue)
				->where('price_end_range', '>=', $TotalValue)
				->where('start_date', '<=', $today)
				->where('end_date', '>=', $today)
				->first();

			if($matchingSamples){
				$customer_choice = $matchingSamples->customer_choice;
			}
		}
		return $customer_choice;
	}

	public function getSampleProductsPopup($TotalValue,$TotalFreeSampleItems = 0){
		$ProductArr = array();

		$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');
		if($onlyGCPurchased==1)
		{
			return null;
		}

        $GiftCertiTotal = 0;
        if(Session::has('ShoppingCart.GiftCertiTotal'))
          $GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));

        $TotalValue = $TotalValue - $GiftCertiTotal;

		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart'))){
			$cartitem = Session::get('ShoppingCart.Cart');
			$today = Carbon::today()->toDateString();
			$ProductArr = array();
			$matchingSamples = FreeVialSampleProduct::where('status', '1')
				->where('price_start_range', '<=', $TotalValue)
				->where('price_end_range', '>=', $TotalValue)
				->where('start_date', '<=', $today)
				->where('end_date', '>=', $today)
				->first();

			if($matchingSamples){
				if($matchingSamples->sku!=''){
					//$ProductArr['customer_choice'] = $matchingSamples->customer_choice;
					$sampleProducts = explode("#",$matchingSamples->sku);
					$totalConfiguredFreeSamples = $matchingSamples->total_samples;

					$n_sampleProducts = Products::from('pu_products as po')
								->join('pu_products_category as pc', 'po.products_id', '=', 'pc.products_id')
								->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
								->join('pu_brand as b', 'b.brand_id', '=', 'po.brand_id')
								->join('pu_manufacture as m', function($join) {
									$join->on('po.imanufactureid', '=', 'm.imanufactureid')
										->on('b.imanufactureid', '=', 'm.imanufactureid');
								})
								->where('po.status', '1')
								->where('c.status', '1')
								->where('po.current_stock', '>', 0)
								->whereIn('po.product_type', ['both', 'retailer'])
								->whereIn('po.sku', $sampleProducts)
								//->select('po.products_id')
								->groupBy('po.products_id')
								->count('po.products_id');
					//$n_sampleProducts = $n_product_res->count();

					$SKUIDsVal = [];
					if(Session::has('ShoppingCart.Cart'))
					{
						$SKUIDsVal = array_column(Session::get('ShoppingCart.Cart'), 'ORGSAMPLESKU');
					}

					// if($totalConfiguredFreeSamples > count($sampleProducts)){
					// 	$newlim = $totalConfiguredFreeSamples - count($sampleProducts);
					if($totalConfiguredFreeSamples > $n_sampleProducts){
						$newlim = $totalConfiguredFreeSamples - $n_sampleProducts;

						/*$ChildCats = GetMainCatsTree([252]);
						if(count($ChildCats['CatList']) > 0)
							$ChildCatArr = array_column($ChildCats['CatList'],'category_id');*/

						$ChildCatArr = array(252,253,254,255); // added static to reduce query load in cart pages.

						// $randomProducts = DB::table('pu_products as po')
						// 			->join('pu_products_category as pc', 'po.products_id', '=', 'pc.products_id')
						// 			->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
						// 			->join('pu_brand as b', 'b.brand_id', '=', 'po.brand_id')
						// 			->join('pu_manufacture as m', function($join) {
						// 				$join->on('po.imanufactureid', '=', 'm.imanufactureid')
						// 					->on('b.imanufactureid', '=', 'm.imanufactureid');
						// 			})
						// 			->where('po.status', '1')
						// 			->where('c.status', '1')
						// 			// ->where('po.current_stock', '>', 0)
						// 			// ->where('po.is_atomizer', 'Yes')
						// 			->whereIn('po.product_type', ['both', 'retailer'])
						// 			->whereIn('pc.category_id', $ChildCatArr)
						// 			->whereNotIn('po.sku', $sampleProducts)
						// 			->select('po.products_id', 'po.sku')
						// 			->inRandomOrder()
						// 			->limit($newlim)
						// 			->get();

						$products = DB::table('pu_products as po')
									->join('pu_products_category as pc', 'po.products_id', '=', 'pc.products_id')
									->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
									->join('pu_brand as b', 'b.brand_id', '=', 'po.brand_id')
									->join('pu_manufacture as m', function($join) {
										$join->on('po.imanufactureid', '=', 'm.imanufactureid')
											->on('b.imanufactureid', '=', 'm.imanufactureid');
									})
									->where('po.status', '1')
									->where('c.status', '1')
									->where('po.current_stock', '>', 0)
									// ->where('po.is_atomizer', 'Yes')
									->whereIn('po.product_type', ['both', 'retailer'])
									->whereIn('pc.category_id', $ChildCatArr)
									->whereNotIn('po.sku', $sampleProducts)
									->select('po.products_id', 'po.sku')
									->inRandomOrder()
									//->limit($newlim)
									->get();

						$includeCartSKU = $products->whereIn('sku', $SKUIDsVal);
						$others = $products->whereNotIn('sku', $includeCartSKU)->shuffle();
						$randomProducts = $includeCartSKU->merge($others)->take($newlim);

						foreach ($randomProducts as $prod) {
							array_push($sampleProducts, $prod->sku);
						}
					}

					//$product_res = Products::whereIn('sku',$sampleProducts)->where('status','=','1')->get();
					$product_res = Products::from('pu_products as po')
								->join('pu_products_category as pc', 'po.products_id', '=', 'pc.products_id')
								->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
								->join('pu_brand as b', 'b.brand_id', '=', 'po.brand_id')
								->join('pu_manufacture as m', function($join) {
									$join->on('po.imanufactureid', '=', 'm.imanufactureid')
										->on('b.imanufactureid', '=', 'm.imanufactureid');
								})
								->where('po.status', '1')
								->where('c.status', '1')
								// ->where('po.current_stock', '>', 0)
								// ->where('po.is_atomizer', 'Yes')
								->whereIn('po.product_type', ['both', 'retailer'])
								->whereIn('po.sku', $sampleProducts)
								->select('po.*')
								->groupBy('po.products_id')
								->inRandomOrder()
								->get();
					$TotalProducts = $product_res->count();

					$SKUIDsVal = [];
					if(Session::has('ShoppingCart.Cart'))
					{
						$SKUIDsVal = array_column(Session::get('ShoppingCart.Cart'), 'ORGSAMPLESKU');
					}

					for($i=0;$i<$TotalProducts;$i++)
					{
						$product_res[$i] = $this->SetProduct($product_res[$i]);
						if(isset($product_res[$i]['stock']) && $product_res[$i]['stock']=='Out'){
							continue;
						}
						$products_id = $product_res[$i]["products_id"];
						$product_name = $product_res[$i]["product_name"];
						$sku	= $product_res[$i]["sku"];
						$image = $product_res[$i]["image"];
						$short_description = strip_tags($product_res[$i]["short_description"]);

						if(file_exists(config('global.PRD_THUMB_IMG_PATH').$product_res[$i]['image']) && !empty($product_res[$i]['image']))
							$thumb_image = config('global.PRD_THUMB_IMG_URL').$product_res[$i]['image'];
						else
							$thumb_image = config('global.NO_IMAGE_THUMB');

						if(in_array($sku,$SKUIDsVal))
						{
							$FoundSku = "Yes";
						} else {
							$FoundSku = "No";
						}

						$ProductArr[] = array(
										"products_id"			=> $products_id,
										"product_name" 			=> $product_name,
										"sku"					=> $sku,
										"thumb_image" 			=> $thumb_image,
										"short_description"		=> $short_description,
										"customer_choice"		=> $matchingSamples->customer_choice,
										"FoundSku"				=> $FoundSku
									);
					}
				}
			}
		}
		return $ProductArr;
	}

		public function GetFreeCouponPopup($TotalValue, $TotalFreeGiftItems = 0, $FreeGiftProductId = 0)
{
    $onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');

    if ($onlyGCPurchased == 1) {
        return null;
    }

    /*
     * ============================================================
     * GIFT CERTIFICATE
     * ============================================================
     */
    $GiftCertiTotal = 0;

    if (Session::has('ShoppingCart.GiftCertiTotal')) {
        $GiftCertiTotal = NumberFormat(
            Session::get('ShoppingCart.GiftCertiTotal')
        );
    }

    $TotalValue = NumberFormat($TotalValue - $GiftCertiTotal);

    /*
     * ============================================================
     * CART CHECK
     * ============================================================
     */
    if (
        !Session::has('ShoppingCart.Cart') ||
        count(Session::get('ShoppingCart.Cart')) == 0
    ) {
        return [];
    }

    $cartitem = Session::get('ShoppingCart.Cart');

    /*
     * ============================================================
     * REAL PURCHASE ITEMS
     *
     * Free sample / free gift are NOT used for rule matching.
     * ============================================================
     */
    $purchaseItems = [];

    foreach ($cartitem as $item) {

        if (
            isset($item["Is_Free_Sample"]) &&
            $item["Is_Free_Sample"] == "Yes"
        ) {
            continue;
        }

        if (
            isset($item["IS_Free_Gift"]) &&
            $item["IS_Free_Gift"] == "Yes"
        ) {
            continue;
        }

        $purchaseItems[] = $item;
    }

    if (count($purchaseItems) == 0) {
        return [];
    }

    $today = date("Y-m-d");

    /*
     * ============================================================
     * GET ACTIVE RULES
     * ============================================================
     */
    $rules = DB::table('pu_free_gift_product')
        ->select('*')
        ->where('status', '1')
        ->whereDate('start_date', '<=', $today)
        ->whereDate('end_date', '>=', $today)
        ->get();

    if ($rules->count() == 0) {
        return [];
    }

    $validRules = [];

    /*
     * ============================================================
     * CHECK EACH RULE
     *
     * IMPORTANT:
     * Each rule calculates its OWN qualifying total.
     *
     * Price              = complete purchase total
     * Brand              = matching brand subtotal
     * Category           = matching category subtotal
     * Brand + Category   = matching brand + category subtotal
     * ============================================================
     */
    foreach ($rules as $rule) {

        $flag = trim((string)$rule->flag_range);

        $ruleBrandIds = [];
        $ruleCategoryIds = [];

        /*
         * --------------------------------------------------------
         * BRAND IDS
         * --------------------------------------------------------
         */
        if (
            $flag == "Brand" ||
            $flag == "Brand,Category"
        ) {

            $ruleBrandIds = DB::table('pu_freegift_brand')
                ->where('products_id', $rule->products_id)
                ->pluck('imanufactureid')
                ->map(function ($id) {
                    return (int)$id;
                })
                ->toArray();

            if (count($ruleBrandIds) == 0) {
                continue;
            }
        }

        /*
         * --------------------------------------------------------
         * CATEGORY IDS
         * --------------------------------------------------------
         */
        if (
            $flag == "Category" ||
            $flag == "Brand,Category"
        ) {

            $ruleCategoryIds = DB::table('pu_freegift_category')
                ->where('products_id', $rule->products_id)
                ->pluck('categoryid')
                ->map(function ($id) {
                    return (int)$id;
                })
                ->toArray();

            if (count($ruleCategoryIds) == 0) {
                continue;
            }
        }

        /*
         * ========================================================
         * CALCULATE RULE TOTAL
         * ========================================================
         */
        $ruleTotal = 0;

        foreach ($purchaseItems as $item) {

            $itemPrice = NumberFormat(
                isset($item["TotPrice"])
                    ? $item["TotPrice"]
                    : 0
            );

            $itemBrandId = isset($item["ImanufactureID"])
                ? (int)$item["ImanufactureID"]
                : 0;

            $itemCategoryId = isset($item["CategoryID"])
                ? (int)$item["CategoryID"]
                : 0;

            /*
             * PRICE RULE
             */
            if ($flag == "") {

                $ruleTotal += $itemPrice;
            }

            /*
             * BRAND RULE
             */
            elseif ($flag == "Brand") {

                if (
                    $itemBrandId > 0 &&
                    in_array($itemBrandId, $ruleBrandIds)
                ) {
                    $ruleTotal += $itemPrice;
                }
            }

            /*
             * CATEGORY RULE
             */
            elseif ($flag == "Category") {

                if (
                    $itemCategoryId > 0 &&
                    in_array($itemCategoryId, $ruleCategoryIds)
                ) {
                    $ruleTotal += $itemPrice;
                }
            }

            /*
             * BRAND + CATEGORY RULE
             */
            elseif ($flag == "Brand,Category") {

                if (
                    $itemBrandId > 0 &&
                    $itemCategoryId > 0 &&
                    in_array($itemBrandId, $ruleBrandIds) &&
                    in_array($itemCategoryId, $ruleCategoryIds)
                ) {
                    $ruleTotal += $itemPrice;
                }
            }
        }

        $ruleTotal = NumberFormat($ruleTotal);

        /*
         * ========================================================
         * EXCLUDE SKU
         * ========================================================
         */
        if (
            isset($rule->exclude_sku) &&
            trim($rule->exclude_sku) != ''
        ) {

            $excludeSkus = array_unique(
                array_filter(
                    array_map(
                        'trim',
                        explode('#', $rule->exclude_sku)
                    ),
                    'strlen'
                )
            );

            foreach ($purchaseItems as $item) {

                if (
                    isset($item["SKU"]) &&
                    in_array($item["SKU"], $excludeSkus)
                ) {
                    $ruleTotal -= NumberFormat(
                        isset($item["TotPrice"])
                            ? $item["TotPrice"]
                            : 0
                    );
                }
            }
        }

        /*
         * ========================================================
         * EXCLUDE POCKET PERFUME
         * ========================================================
         */
        if (
            isset($rule->exclude_pocketperfume) &&
            $rule->exclude_pocketperfume == "Yes"
        ) {

            $pocketPerfumeCategory =
                $this->getPocketPerfumeCategory();

            foreach ($purchaseItems as $item) {

                $itemCategoryId = isset($item["CategoryID"])
                    ? (int)$item["CategoryID"]
                    : 0;

                if (
                    $itemCategoryId > 0 &&
                    in_array(
                        $itemCategoryId,
                        $pocketPerfumeCategory
                    )
                ) {
                    $ruleTotal -= NumberFormat(
                        isset($item["TotPrice"])
                            ? $item["TotPrice"]
                            : 0
                    );
                }
            }
        }

        $ruleTotal = NumberFormat($ruleTotal);

        /*
         * ========================================================
         * RANGE CHECK
         * ========================================================
         */
        $start = NumberFormat($rule->price_start_range);
        $end   = NumberFormat($rule->price_end_range);

        /*
         * Below starting range = NOT valid.
         */
        if ($ruleTotal < $start) {
            continue;
        }

        /*
         * ========================================================
         * SAVE VALID RULE
         * ========================================================
         */
        $validRules[] = [
            'rule'       => $rule,
            'rule_total' => $ruleTotal,
            'start'      => $start,
            'end'        => $end,
            'flag'       => $flag
        ];
    }

    /*
     * ============================================================
     * NO VALID RULE
     * ============================================================
     */
    if (count($validRules) == 0) {
        return [];
    }

    /*
     * ============================================================
     * RULE PRIORITY
     *
     * Highest START range wins.
     *
     * Same range:
     *
     * Brand + Category
     *       ↓
     * Brand
     *       ↓
     * Category
     *       ↓
     * Price
     * ============================================================
     */
    usort($validRules, function ($a, $b) {

        if ($a['start'] != $b['start']) {
            return ($a['start'] < $b['start']) ? 1 : -1;
        }

        $priority = [
            'Brand,Category' => 4,
            'Brand'          => 3,
            'Category'       => 2,
            ''               => 1
        ];

        $pa = isset($priority[$a['flag']])
            ? $priority[$a['flag']]
            : 0;

        $pb = isset($priority[$b['flag']])
            ? $priority[$b['flag']]
            : 0;

        return $pb <=> $pa;
    });

    /*
     * ============================================================
     * ONLY BEST RULE
     * ============================================================
     */
    $free_gift_res = $validRules[0]['rule'];

    /*
     * ============================================================
     * FREE GIFT SKU LIST
     * ============================================================
     */
    $FreeGiftValue = [];

    if (
        isset($free_gift_res->sku) &&
        trim($free_gift_res->sku) != ''
    ) {

        $FreeGiftValue = array_filter(
            array_map(
                'trim',
                explode('#', $free_gift_res->sku)
            ),
            'strlen'
        );
    }

    if (count($FreeGiftValue) == 0) {
        return [];
    }

    /*
     * ============================================================
     * GET GIFT PRODUCTS
     * ============================================================
     */
    $product_res = Products::whereIn('sku', $FreeGiftValue)
        ->where('is_free_gift_products', 'Yes')
        ->where('status', '1')
        ->get();

    if ($product_res->count() == 0) {
        return [];
    }

    /*
     * ============================================================
     * CURRENT FREE GIFTS FROM CART
     *
     * IMPORTANT:
     *
     * Only gifts belonging to THIS selected rule are counted.
     *
     * This prevents another rule's gift from affecting
     * freegift_add_count.
     * ============================================================
     */
    $existingGiftSkus = [];
    $existingGiftProductIds = [];

    foreach ($cartitem as $item) {

        if (
            isset($item["IS_Free_Gift"]) &&
            $item["IS_Free_Gift"] == "Yes"
        ) {

            if (isset($item["SKU"])) {
                $existingGiftSkus[] =
                    trim($item["SKU"]);
            }

            if (isset($item["ORGSKU"])) {
                $existingGiftSkus[] =
                    trim($item["ORGSKU"]);
            }

            if (isset($item["products_id"])) {
                $existingGiftProductIds[] =
                    (int)$item["products_id"];
            }
        }
    }

    $existingGiftSkus =
        array_unique(
            array_filter($existingGiftSkus, 'strlen')
        );

    $existingGiftProductIds =
        array_unique(
            array_filter($existingGiftProductIds)
        );

    /*
     * ============================================================
     * FREE GIFT COUNT
     *
     * Example:
     *
     * Rule freegift_add_count = 2
     *
     * Popup has 3 products.
     *
     * Customer already added 1:
     *      1 existing
     *      2 remaining selectable
     *
     * Customer added 2:
     *      count complete
     *      popup = []
     * ============================================================
     */
    $freeGiftAddCount =
        (int)$free_gift_res->freegift_add_count;

    /*
     * If no count configured, keep existing behaviour:
     * all products can be shown.
     */
    if ($freeGiftAddCount <= 0) {
        $freeGiftAddCount = count($FreeGiftValue);
    }

    /*
     * ============================================================
     * BUILD CURRENT GIFTS BELONGING TO THIS RULE
     * ============================================================
     */
    $existingThisRuleCount = 0;

    foreach ($FreeGiftValue as $ruleSku) {

        $ruleSku = trim($ruleSku);

        if (
            $ruleSku != '' &&
            in_array($ruleSku, $existingGiftSkus)
        ) {
            $existingThisRuleCount++;
        }
    }

    /*
     * Also allow products_id matching.
     */
    foreach ($existingGiftProductIds as $existingProductId) {

        $alreadyCounted = false;

        foreach ($product_res as $product) {

            if (
                (int)$product->products_id ==
                (int)$existingProductId
            ) {

                $productSku = trim($product->sku);

                if (
                    in_array(
                        $productSku,
                        $existingGiftSkus
                    )
                ) {
                    $alreadyCounted = true;
                }
            }
        }

        if (!$alreadyCounted) {

            foreach ($product_res as $product) {

                if (
                    (int)$product->products_id ==
                    (int)$existingProductId
                ) {
                    $existingThisRuleCount++;
                    break;
                }
            }
        }
    }

    /*
     * ============================================================
     * COUNT COMPLETE
     *
     * Example:
     * freegift_add_count = 2
     * existing = 2
     *
     * No popup.
     * ============================================================
     */
    if ($existingThisRuleCount >= $freeGiftAddCount) {
        return [];
    }

    /*
     * ============================================================
     * CURRENT CART SKUS
     * ============================================================
     */
    $SKUIDsVal = [];

    if (Session::has('ShoppingCart.Cart')) {

        $SKUIDsVal = array_column(
            Session::get('ShoppingCart.Cart'),
            'ORGSKU'
        );

        $SKUIDsVal = array_merge(
            $SKUIDsVal,
            array_column(
                Session::get('ShoppingCart.Cart'),
                'SKU'
            )
        );

        $SKUIDsVal = array_unique(
            array_filter(
                array_map(
                    'trim',
                    $SKUIDsVal
                ),
                'strlen'
            )
        );
    }

    /*
     * ============================================================
     * BUILD POPUP
     * ============================================================
     */
    $ProductArr = [];

    foreach ($product_res as $product) {

        $product =
            $this->SetProduct($product);

        $products_id =
            $product["products_id"];

        $product_name =
            $product["product_name"];

        $sku =
            trim($product["sku"]);

        $short_description =
            strip_tags(
                $product["short_description"]
            );

        /*
         * --------------------------------------------------------
         * IMAGE
         * --------------------------------------------------------
         */
        if (
            file_exists(
                config('global.PRD_THUMB_IMG_PATH') .
                $product['image']
            ) &&
            !empty($product['image'])
        ) {

            $thumb_image =
                config('global.PRD_THUMB_IMG_URL') .
                $product['image'];

        } else {

            $thumb_image =
                config('global.NO_IMAGE_THUMB');
        }

        /*
         * --------------------------------------------------------
         * ALREADY IN CART
         * --------------------------------------------------------
         */
        $FoundSku = "No";

        if (
            in_array(
                $sku,
                $SKUIDsVal
            )
        ) {
            $FoundSku = "Yes";
        }

        /*
         * --------------------------------------------------------
         * ADD PRODUCT
         * --------------------------------------------------------
         */
        $ProductArr[] = [
            "free_gift_products_id" =>
                $free_gift_res->products_id,

            "freegift_add_count" =>
                $freeGiftAddCount,

            "products_id" =>
                $products_id,

            "product_name" =>
                $product_name,

            "sku" =>
                $sku,

            "thumb_image" =>
                $thumb_image,

            "short_description" =>
                $short_description,

            /*
             * Existing gift => Yes
             * New gift      => No
             */
            "FoundSku" =>
                $FoundSku
        ];
    }

    /*
     * ============================================================
     * ONLY ONE AVAILABLE GIFT
     * ============================================================
     *
     * Keep your existing behaviour.
     *
     * BUT:
     * If this product is already in cart, don't insert again.
     * ============================================================
     */
    if (
        count($ProductArr) == 1 &&
        $existingThisRuleCount == 0
    ) {

        /*
         * IMPORTANT:
         * Existing FreeGiftInsertProductValue is NOT changed.
         */
        if (
			config('Settings.FREEGIFTFLAG') == "Yes" &&
			(!Session::has('eusertype') ||
				strtolower(trim(Session::get('eusertype'))) != 'wholesaler') &&
			!Auth::guard('store')->check()
		) {
			$this->FreeGiftInsertProductValue(
				$ProductArr[0]["products_id"],
				$ProductArr[0]["free_gift_products_id"],
				"Yes"
			);
		}
    }

    /*
     * ============================================================
     * RETURN POPUP
     * ============================================================
     */
    return $ProductArr;
}

	public function SetBillingAddress($Billing)
	{
		$temp = [];
		if($Billing['bill_country'] != 'US')
		{
			if(isset($Billing['bill_other_state']))
			{
				$state = $Billing['bill_other_state'];
			}
			else
			{
				$state = "";
			}
		}
		else
		{
			$state = $Billing['bill_state'];
		}
		$temp['first_name'] 	= stripslashes($Billing['bill_fname']);
		$temp['last_name']  	= stripslashes($Billing['bill_lname']);
		if(isset($Billing['bill_company']))
		{
		$temp['company']    	= stripslashes($Billing['bill_company']);
		}
		else
		{
			$temp['company']    	= "";
		}

		$Billing['bill_address1'] = str_replace("__"," ",$Billing['bill_address1']);
		$temp['address1'] 		= stripslashes($Billing['bill_address1']);
		if(isset($Billing['bill_address2']))
		{
		$Billing['bill_address2'] = str_replace("__"," ",$Billing['bill_address2']);
		}
		else
		{
			$Billing['bill_address2'] =  "";
		}
		$temp['address2'] 		= stripslashes($Billing['bill_address2']);

		$temp['city'] 			= stripslashes($Billing['bill_city']);
		$temp['country'] 		= $Billing['bill_country'];
		$temp['state'] 			= $state;
		$temp['zip'] 			= $Billing['bill_zip'];
		if(isset($Billing['bill_phone']))
		{
		$temp['phone'] 			= $Billing['bill_phone'];
		}
		else
		{
		$temp['phone'] 			= "";
		}
		$temp['email'] 			= $Billing['bill_email'];
		$temp['confirm_email'] 	= $Billing['bill_cemail'];
		Session::put('ShoppingCart.BillingAddress',$temp);
		$BillingAsShipping = 'No';
		if(isset($Billing['sameasbill']))
			$BillingAsShipping = $Billing['sameasbill'];
		Session::put('ShoppingCart.BillingAsShipping',$BillingAsShipping);
		return null;
	}

		public function SetShippingAddress($Shipping)
	{
		$temp = [];
		$prefix = 'ship';

		if(Session::has('ShoppingCart.AfterPay.Checkout_Token') && Session::get('ShoppingCart.AfterPay.Checkout_Token') != ""){
			$Shipping['sameasbill'] = 'Yes';
		}

		if($Shipping['sameasbill'] == 'Yes')
		{
			$prefix = 'bill';
			$Billing = Session::get('ShoppingCart.BillingAddress');
			Session::put('ShoppingCart.ShippingAddress',$Billing);
			return null;
		}

		if($Shipping[$prefix.'_country'] != 'US')
			$state = $Shipping[$prefix.'_other_state'];
		else
			$state = $Shipping[$prefix.'_state'];

		$temp['first_name'] 	= stripslashes($Shipping[$prefix.'_fname']);
		$temp['last_name']  	= stripslashes($Shipping[$prefix.'_lname']);
		$temp['company']    	= stripslashes($Shipping[$prefix.'_company']);

		$Shipping[$prefix.'_address1'] = str_replace("Unions","Union",$Shipping[$prefix.'_address1']);
		$Shipping[$prefix.'_address1'] = str_replace("unions","union",$Shipping[$prefix.'_address1']);
		$temp['address1'] 		= stripslashes($Shipping[$prefix.'_address1']);

		$Shipping[$prefix.'_address2'] = str_replace("Unions","Union",$Shipping[$prefix.'_address2']);
		$Shipping[$prefix.'_address2'] = str_replace("unions","union",$Shipping[$prefix.'_address2']);
		$temp['address2'] 		= stripslashes($Shipping[$prefix.'_address2']);

		$temp['city'] 			= stripslashes($Shipping[$prefix.'_city']);
		$temp['country'] 		= $Shipping[$prefix.'_country'];
		$temp['state'] 			= $state;
		$temp['zip'] 			= $Shipping[$prefix.'_zip'];
		$temp['phone'] 			= $Shipping[$prefix.'_phone'];
		$temp['email'] 			= $Shipping[$prefix.'_email'];
		Session::put('ShoppingCart.ShippingAddress',$temp);
		return null;
	}

	public function FreeGiftValue($subtotal)
	{
		$free_gift_array = array();

		if($subtotal >= config('Settings.FREEGIFT_VALUE'))
		{
			if(config('Settings.BEAUTY_SAMPLE') == "Yes")
			{
				$free_gift_array[] = "Beauty & Accessories Sample";
			}
			if(config('Settings.PERFUME_SAMPLE') == "Yes")
			{
				$free_gift_array[] = "Perfume Sample";
			}
		} else {
			return $free_gift_array;
		}
		return $free_gift_array;
	}

	  	public function setPaymentDetail($request)
	{
		addLog("setPaymentDetailStart");
		$temp = [];
		$temp['Payment_Type']     	= $request->PaymentMethod;
		$temp['Payment_Method']   	= $this->GetPaymentMethodName($request->PaymentMethod);

		if ($temp['Payment_Type'] == "PAYMENT_SPLIT") {
			$temp['orderCashAmount'] = trim($request->CashPayment);
		} else if ($temp['Payment_Type'] == "PAYMENT_CASH") {
			$temp['orderCashAmount'] = $this->GetNetTotal();
		} else {
			$temp['orderCashAmount'] = 0.00;
		}

		if($temp['Payment_Type'] == 'PAYMENT_AUTHORIZENETCC' || $temp['Payment_Type'] =='PAYMENT_PAYPALCC' || $temp['Payment_Type'] =='PAYMENT_BRAINTREECC')
		{
			$temp['CCName']   	= trim($request->CCholdername);
			$temp['CCType']   	= $request->CCType;
			$temp['CCNumber'] 	= $request->CCNumber;
			$temp['CCMonth']  	= $request->CCMonth;
			$temp['CCYear']   	= $request->CCYear;
			$temp['CSC']      	= $request->CSC;
			$temp['BRAINNONCE']     = '';
			$temp['BRAINTREEDEVICEDATE'] = '';
		}
		else
		{
			$temp['CCName']   	= '';
			$temp['CCType']   	= '';
			$temp['CCNumber'] 	= '';
			$temp['CCMonth']  	= '';
			$temp['CCYear']   	= '';
			$temp['CSC']      	= '';
			$temp['BRAINNONCE']     = '';
			$temp['BRAINTREEDEVICEDATE'] = '';
		}
		$log['payment_details'] = $temp;
		addLog("setPaymentDetail",$log);
		Session::put('ShoppingCart.Payment_Detail',$temp);
		return NULL;
	}

	   	public function GetPaymentMethodName($pType)
	{
		switch ($pType)
		{
			case 'PAYMENT_PAYPALEC':
				return 'Paypal Express Checkout';
				break;
                        case 'PAYMENT_BRAINTREECC':
                        return 'Credit Card';
			break;
			case 'PAYMENT_PAYPALCC':
				return 'Credit Card';
				break;
			case 'PAYMENT_AUTHORIZENETCC':
				return 'Credit Card';
				break;
			case 'PAYMENT_GOOGLEC':
				return 'Google Checkout';
				break;
			case 'PAYMENT_GIFT_CERTIFICATE':
				return 'Gift Certificate';
				break;
			case 'PAYMENT_2CO':
				return '2Checkout';
				break;
			case 'PAYMENT_MOC':
				return 'Check or Money Order';
				break;
			case 'PAYMENT_WT':
				return 'Wire Transfer';
				break;
			case 'PAYMENT_PH':
				return 'Phone Order';
				break;
            case 'PAYMENT_CL':
				return 'Credit Limit';
				break;
            case 'PAYMENT_DS':
				return 'Dropshipper Fund';
				break;
			case 'PAYMENT_PAYWITHAMAZON':
				return 'Pay With Amazon';
				break;
			case 'PAYMENT_STRIPE':
				return 'Credit Card';
				break;
			case 'PAYMENT_STRIPE_NORMAL':
				return 'Credit Card';
				break;
			case 'PAYMENT_SPLIT':
				return 'Pay With Split Payment';
				break;
			case 'PAYMENT_CASH':
				return 'Pay With Cash Payment';
				break;
			case 'PAYMENT_PAYWITHAFTERPAY':
				return 'Pay With Afterpay';
				break;
			case 'PAYMENT_FREEITEM':
				return 'Free Items - POS';
				break;
			default:
				return NULL;
				break;
		}
		return NULL;
	}

	public function InsertGiftCertificateDB($ary, $orders_detail_id, $custId = 0,$is_amazon="No" )
	{
		do
		{
			$status = ($is_amazon=='Yes')?'0':'1';
			//$status = '0';

			$minimum_purchase_value = '0.00';

			$date = date('Y-m-d');
			$newdate = date('Y-m-d', strtotime($date. ' + 1 years'));
			$expiry_date = $newdate;

			$GCInsert =	array(
				'customer_id' 		=> $custId,
				'orders_detail_id' 	=> $orders_detail_id,
				'gc_code' 			=> GCGenerateCode(),
				'gc_value' 			=> $ary['Price'],
				'minimum_purchase_value' => $minimum_purchase_value,
				'remaining_value' 	=> $ary['Price'],
				'recipient_name' 	=> $ary['RecipientName'],
				'recipient_email' 	=> $ary['RecipientEmail'],
				'subject' 			=> (isset($ary['Subject']))?$ary['Subject']:'',
				'message' 			=> (isset($ary['Message']))?$ary['Message']:'',
				'expiry_date' => date("Y-m-d", strtotime($expiry_date)),
				'your_name' 		=> $ary['YourName'],
				'your_email' 		=> $ary['YourEmail'],
				'giftimage'			=> $ary['GiftImage'],
				'giftsku'			=> $ary['SKU'],
				'deliverydate'		=> date("Y-m-d", strtotime($ary['DeliveryDate'])),
				'status' 			=> $status,
				'is_email'			=> 'No'
			);

			$gc_id = GiftCertificate::create($GCInsert) ;
		}
		while($gc_id == false);
		return $gc_id;
	}

	public function ProductDeductStock($sku, $qty = 1, $IsCosmo = "", $IsNandansons = "", $IsPerfumePW = "", $IsPCA = "", $IsND = "", $VendorSKU = "", $OrderType = "Website",$products_id=0)
	{
		$ProdInfo = DB::table('pu_products as p');

		$isStore = Auth::guard('store')->check() && $OrderType == "Store";

		if ($isStore) {

		$store = Auth::guard('store')->user();

		 return DB::table('pu_store_inventory')
			->where('store_id', $store->store_id)
			->where('products_id', $products_id)
			->update([
				'current_stock' => DB::raw('GREATEST(current_stock - ' . (int)$qty . ', 0)')
			]);

		} else {
			$ProdInfo = $ProdInfo->select(
				'p.products_id',
				'p.current_stock',
				'p.cosmo_current_stock',
				'p.cosmo_sku',
				'p.nandansons_sku',
				'p.nandansons_current_stock',
				'p.perfumeworldwide_sku',
				'p.pca_sku',
				'p.perfumeworldwide_currentstock',
				'p.nd_sku',
				'p.nd_current_stock',
				'p.pca_current_stock'
			);
		}

		$ProductSt = $ProdInfo
			->where('p.sku', '=', $sku)
			->distinct()
			->get();

		if ($ProductSt->count() <= 0) {
			return NULL;
		}

		$new_stock = 0;
		if (Auth::guard('store')->check() && isset($OrderType) && $OrderType == "Store") {
			if ($ProductSt[0]->store_current_stock > $qty) {
				$new_stock = $ProductSt[0]->store_current_stock - $qty;
			} else if ($qty > $ProductSt[0]->store_current_stock) {
				$new_stock = $qty - $ProductSt[0]->store_current_stock;
			}
			if ($new_stock <= 0) {
				$new_stock = 0;
			}
			$UpdateStock = array('current_stock' => $new_stock);
		} else if ($IsCosmo == "Yes" && $VendorSKU == $ProductSt[0]->cosmo_sku) {
			if ($ProductSt[0]->cosmo_current_stock > $qty) {
				$new_stock = $ProductSt[0]->cosmo_current_stock - $qty;
			} else if ($qty > $ProductSt[0]->cosmo_current_stock) {
				$new_stock = $qty - $ProductSt[0]->cosmo_current_stock;
			}
			if ($new_stock <= 0) {
				$new_stock = 0;
			}
			$UpdateStock = array('cosmo_current_stock' => $new_stock);
		} else if ($IsNandansons == "Yes" && $VendorSKU == $ProductSt[0]->nandansons_sku) {
			if ($ProductSt[0]->nandansons_current_stock > $qty) {
				$new_stock = $ProductSt[0]->nandansons_current_stock - $qty;
			} else if ($qty > $ProductSt[0]->nandansons_current_stock) {
				$new_stock = $qty - $ProductSt[0]->nandansons_current_stock;
			}
			if ($new_stock <= 0) {
				$new_stock = 0;
			}
			$UpdateStock = array('nandansons_current_stock' => $new_stock);
		} else if ($IsPerfumePW == "Yes" && $VendorSKU == $ProductSt[0]->perfumeworldwide_sku) {
			if ($ProductSt[0]->perfumeworldwide_currentstock > $qty) {
				$new_stock = $ProductSt[0]->perfumeworldwide_currentstock - $qty;
			} else if ($qty > $ProductSt[0]->perfumeworldwide_currentstock) {
				$new_stock = $qty - $ProductSt[0]->perfumeworldwide_currentstock;
			}
			if ($new_stock <= 0) {
				$new_stock = 0;
			}
			$UpdateStock = array('perfumeworldwide_currentstock' => $new_stock);
		} else if ($IsPCA == "Yes" && $VendorSKU == $ProductSt[0]->pca_sku) {
			if ($ProductSt[0]->pca_current_stock > $qty) {
				$new_stock = $ProductSt[0]->pca_current_stock - $qty;
			} else if ($qty > $ProductSt[0]->pca_current_stock) {
				$new_stock = $qty - $ProductSt[0]->pca_current_stock;
			}
			if ($new_stock <= 0) {
				$new_stock = 0;
			}
			$UpdateStock = array('pca_current_stock' => $new_stock);
		} else if ($IsND == "Yes" && $VendorSKU == $ProductSt[0]->nd_sku) {
			if ($ProductSt[0]->nd_current_stock > $qty) {
				$new_stock = $ProductSt[0]->nd_current_stock - $qty;
			} else if ($qty > $ProductSt[0]->nd_current_stock) {
				$new_stock = $qty - $ProductSt[0]->nd_current_stock;
			}
			if ($new_stock <= 0) {
				$new_stock = 0;
			}
			$UpdateStock = array('nd_current_stock' => $new_stock);
		} else {
			if ($ProductSt[0]->current_stock > $qty) {
				$new_stock = $ProductSt[0]->current_stock - $qty;
			} else if ($qty > $ProductSt[0]->current_stock) {
				$new_stock = $qty - $ProductSt[0]->current_stock;
			}
			if ($new_stock <= 0) {
				$new_stock = 0;
			}
			$UpdateStock = array('current_stock' => $new_stock);
		}
		//$result = true;

		if (Auth::guard('store')->check() && isset($OrderType) && $OrderType == "Store") {

			$result = StoreInventory::where('products_id', '=', $ProductSt[0]->products_id)
				->where('store_id', '=', Auth::guard('store')->user()->store_id)
				->update($UpdateStock);
		} else {
			$result = Products::where('sku', '=', $sku)->update($UpdateStock);
		}
		if ($result)
			return true;
		else
			return false;
	}
	public function RouteShippingInsuranceOrderProcess($order,$order_details)
	{
		$route_token = "test-c836e757-a9af-4844-84ac-30777c420121";
		//$route_token = "2b3983e3-6b7d-45e4-bc21-a4ee1db24609";
		$datas["source_order_id"] = $order["orders_no"]; //$order["orders_id"];
		$datas["subtotal"] = $order["sub_total"];
		$datas["taxes"] = $order["tax"];
		$datas["insurance_selected"] = true;

		$datas["customer_details"]["first_name"] = $order["bill_first_name"];//"John";
		$datas["customer_details"]["last_name"] = $order["bill_last_name"];
		$datas["customer_details"]["email"] = $order["bill_email"];

		$datas["shipping_details"]["first_name"] = $order["ship_first_name"];
		$datas["shipping_details"]["last_name"] = $order["ship_last_name"];
		$datas["shipping_details"]["street_address1"] = $order["ship_address1"];//"8400 NW 25TH ST STE 100";
		$datas["shipping_details"]["street_address2"] = $order["ship_address2"];
		$datas["shipping_details"]["province"] = $order["ship_state"];
		$datas["shipping_details"]["city"] = $order['ship_city'];
		$datas["shipping_details"]["zip"] = $order['ship_zip'];
		$datas["shipping_details"]["country_code"] = $order['ship_country'];

		for($i = 0; $i < count($order_details); $i++)
		{
			$line_items[$i]['source_product_id'] = $order_details[$i]['products_id'];
			$line_items[$i]['sku'] = $order_details[$i]['sku'];
			$line_items[$i]['name'] = $order_details[$i]['product_name'];
			$line_items[$i]['price'] = $order_details[$i]['price'];
			$line_items[$i]['quantity'] = $order_details[$i]['quantity'];
			//$line_items[$i]['upc'] = "1234";
			//$line_items[$i]['image_url'] = "https://exampleimageurl.com";
		}
		$datas['line_items'] = $line_items;
		$d = json_encode($datas);
		$order_url = "https://api.route.com/v1/orders";

		$chs = curl_init();
		curl_setopt_array($chs, array(
	  		CURLOPT_URL => $order_url,
		  	CURLOPT_RETURNTRANSFER => true,
		  	CURLOPT_ENCODING => "",
		  	CURLOPT_MAXREDIRS => 10,
		  	CURLOPT_TIMEOUT => 30,
		  	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  	CURLOPT_CUSTOMREQUEST => "POST",
		  	CURLOPT_POSTFIELDS => $d,//  http_build_query($data),
		  	CURLOPT_HTTPHEADER => array(
				"cache-control: no-cache",
				"Content-Type: application/json",
				"token: ".$route_token."",
				//"Authorization: Basic ".$basic_auth."",
				"Accept: application/json"),
			));
			$results = curl_exec($chs);
			if($results === false)
			{
    			//dd(curl_error($results));
			} else {
				$responses = json_decode($results,true);
				if(isset($route_response["email_account_id"]) && $route_response["email_account_id"]!="")
				{
					$route_res_db_store = "email_account_id:".$route_response["email_account_id"]."||id:".$route_response["id"]."||order_number:".$route_response["order_number"]."||source_order_id:".$route_response["source_order_id"];
					$route_upd_arr = array(
						'route_shipping_insurance_response' => $route_res_db_store
					);
					$udpRoute = Order::where('orders_id','=',$order["orders_id"])->update($route_upd_arr);
				}
			}
		/*
		$headers = [
				"cache-control" => "no-cache",
				"Content-Type" => "application/json",
				"token" => $route_token,
				"Accept" => "application/json"];
		$curl = new \GuzzleHttp\Client(['headers' => $headers,'verify' => false,'debug' => true]);
		$order_url = 'https://api.route.com/v1/orders';
		$order_url = (string)$order_url;
		$response = $curl->request('POST',$order_url,$datas);
		*/
		//dd($response);
	}

	public function AfterpayMinMax()
	{
		$Afterpay_Checkout = $this->Afterpay_Checkout ?? '';
		if(isset($Afterpay_Checkout) && $Afterpay_Checkout == "Yes"){
			$ap_configs = $this->GetAfterpayMinMaxConfig();
			if(!empty($ap_configs))
			{
				// Session::put('Afterpay.Min_AP_AMT',$ap_configs["Min_AP_AMT"]);
				// Session::put('Afterpay.Max_AP_AMT',$ap_configs["Max_AP_AMT"]);

				Session::put('Afterpay.Min_AP_AMT', $ap_configs['Min_AP_AMT'] ?? '');
        		Session::put('Afterpay.Max_AP_AMT', $ap_configs['Max_AP_AMT'] ?? '');
			}
		}
		/*$Afterpay_Checkout = $this->Afterpay_Checkout;
		if($Afterpay_Checkout == "Yes"){
			$ap_configs = $this->GetAfterpayMinMaxConfig();
			if(!empty($ap_configs))
			{
				Session::put('Afterpay.Min_AP_AMT',$ap_configs["Min_AP_AMT"]);
				Session::put('Afterpay.Max_AP_AMT',$ap_configs["Max_AP_AMT"]);
			}
		}*/

		/*
		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_PAYWITHAFTERPAY')
							->where('pm_status', '=', 'Active')
							->get();

		if($db_res->count() > 0)
		{
			$arrPEVar		= unserialize($db_res[0]->pm_details);
			#############################
			$this->ap_arr['PaywithAfterpay_Merchant_ID']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_ID']);
			$this->ap_arr['PaywithAfterpay_Merchant_Secret_Key']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_Secret_Key']);
			$this->ap_arr['PaywithAfterpay_Header_Authorization']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_Authorization']);
			$this->ap_arr['PaywithAfterpay_Header_User_Agent']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_User_Agent']);
			#############################
			if( strtoupper(trim($arrPEVar['PaywithAfterpay_Transaction_Mode'])) == 'SANDBOX'){
				$this->TRANSACTION_MODE = 'sandbox';
				$this->Payment_Url = "https://api.us-sandbox.afterpay.com/v2/";
				//$Payment_Url = "https://api.us-sandbox.afterpay.com/v1/";
				$this->Token_JS_Url = "https://portal.sandbox.afterpay.com/afterpay.js";
			}else{
				$this->TRANSACTION_MODE = 'production';
				$this->Payment_Url = "https://api.us.afterpay.com/v2/";
				$this->Token_JS_Url = "https://portal.afterpay.com/afterpay.js";
			}

            $merchant = new AfterpayMerchantAccount();
            $merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
                    ->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
                    ->setApiEnvironment($this->TRANSACTION_MODE)
                    ->setCountryCode('US');
            $getConfigurationRequest = new AfterpayGetConfigurationRequest();
            $getConfigurationRequest->setMerchantAccount($merchant);
            $getConfigurationRequest->send();
            $body = $getConfigurationRequest->getResponse()->getParsedBody();

            if($body && isset($body->minimumAmount))
            {
				Session::put('Afterpay.Min_AP_AMT',($body->minimumAmount->amount));
                Session::put('Afterpay.Max_AP_AMT',($body->maximumAmount->amount));

                // Session::put('Afterpay.Min_AP_AMT',($body->minimumAmount->amount * 100 ));
                // Session::put('Afterpay.Max_AP_AMT',($body->maximumAmount->amount * 100));
            }
		}
		*/

	}

	public function SetShippingInsuranceCharge($action='add',$NetTotal=0,$phoneOrder="No")
	{
		$log['action'] = $action;
		$log['NetTotal'] = $NetTotal;
		$log['phoneOrder'] = $phoneOrder;
		addLog('SetShippingInsuranceChargeStart',$log);

		if($phoneOrder=="No")
		{
		Session::forget('shipping_insurance_charge');
		}
		if (Session::has('ShoppingCart.OrderType') && Session::get('ShoppingCart.OrderType') === 'Store') {
			return 0;
		}
		if(Session::has('ShoppingCart.Shipping.ShippingMethodID') && Session::get('ShoppingCart.Shipping.ShippingMethodID')==46)
		{
			return 0;
		}
		if($action == 'add')
		{
			//Session::forget('ShoppingCart.ShippingSignature');
			if($phoneOrder=="No")
			{
				//$NetTotal = $this->GetNetTotal();
				$taxAmount = 0;
				If(Session::has('ShoppingCart.Tax') && Session::get('ShoppingCart.Tax') > 0){
					$taxAmount =  Session::get('ShoppingCart.Tax');
				}
				$NetTotal = $this->GetNetTotal();
				if($taxAmount > 0)
				{
					$NetTotal = $NetTotal - $taxAmount;
				}
			}
			$ShipInsurance = 0;
			if($NetTotal > 0 )
			{
				if($NetTotal <= 99)
				{
					$ShipInsurance = 1.55;
				} else {
					$ShipInsurance = NumberFormat($NetTotal*0.02) + 1.55;
				}
			}
			if($phoneOrder=="No")
			{
				Session::put('shipping_insurance_charge',NumberFormat($ShipInsurance));
			}
			if($phoneOrder=="Yes")
			{
			 return NumberFormat($ShipInsurance);
			}
		}
	}

	public function SetAmazonConfig($PageFrom='')
	{
		$PaymentMethodsAmazon = Cache::rememberForever('afterpay_payment_method_ca', function () {
		 return PaymentMethod::select(
					'pm_group_name',
					'pm_gateway_name',
					'pm_details'
				)
				->where('pm_status', 'Active')
				->get();
		});

		$PaymentMethod = $PaymentMethodsAmazon->firstWhere('pm_group_name', 'PAYMENT_PAYWITHAMAZON');

		if ($PaymentMethod)
		{
			$pm_details = unserialize($PaymentMethod->pm_details);
			foreach ( $pm_details as $pm_var_name => $pm_var_value )
			{
				$payment_methods_settings[$pm_var_name] = $pm_var_value;
			}
			config(['CLIENT_ID' => $this->decrypt($pm_details['paywithamazon_Client_ID'])]);
			config(['MERCHANT_ID' => $this->decrypt($pm_details['paywithamazon_Merchant_Id'])]);
			if($PageFrom != '')
				config(['CALLBACK_URL' => url('/setupamazon/'.$PageFrom)]);
			else
				config(['CALLBACK_URL' => url('/setupamazon')]);

			if($PageFrom == "phoneorder_payment_receipt"){
				config(['CALLBACK_CHECKOUT_URL' => url('/setupamazon/phoneorder_payment_receipt')]);
			}else{
				config(['CALLBACK_CHECKOUT_URL' => url('/setupamazon')]);
			}
			// config(['CALLBACK_CHECKOUT_URL' => url('/setupamazon')]);
			/*if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
			{
				$payment_methods_settings['paywithamazon_Transaction_Mode'] = 'Sandbox';
			}*/

			// if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.6.77"){
				// $payment_methods_settings['paywithamazon_Transaction_Mode'] = 'sandbox';
			// }
			//$payment_methods_settings['paywithamazon_Transaction_Mode'] = 'sandbox';
			if(strtoupper(trim($payment_methods_settings['paywithamazon_Transaction_Mode'])) == 'SANDBOX')
				config(['JS_SERVER_URL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js?sellerId='.config('MERCHANT_ID')]);
			else
				config(['JS_SERVER_URL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js?sellerId='.config('MERCHANT_ID')]);
		}
	}

	public function GoogleTagManager($Data=[])
	{
		if($Data['page'] == 'order_receipt')
		{
			$Coupon_DiscLevel = "";
			$coupon_code = $this->GetAllCoupons('CouponCode');
			$discountInfo = $this->GetAllDiscounts();
			$discount = $discountInfo['TotalDiscount'];

			if($coupon_code != "" && Session::has('ShoppingCart.Coupon_Detail_CJ.orders'))
			{
				$coupon_order = Session::get('ShoppingCart.Coupon_Detail_CJ.orders');
				if($coupon_order != ""){
					if($coupon_order == 0){
						//on order amount so whole order discount
						$Coupon_DiscLevel = "Order";
					}else if($coupon_order == 4){
						//free shipping so no discount
						$Coupon_DiscLevel = "None";
					}else{
						//category,sku,brand etc.
						$Coupon_DiscLevel = "Item";
					}
				}
			}

			$cj_discount = $discount;
			if($Coupon_DiscLevel == "None" || $Coupon_DiscLevel == "Item"){
				$cj_discount = "0";
			}
		}
		if($Data['page'] == 'shoppingcart' || $Data['page'] == 'billing' || $Data['page'] == 'billing_amazon' || $Data['page'] == 'order_receipt')
		{
			$pid_fstr = '';
			$tot_qty = 0;
			$sf_item_array = array();
			$tempCart = (Session::has('ShoppingCart.Cart'))?Session::get('ShoppingCart.Cart'):[];
			$item_array = array();
			$cnt_row = count($tempCart);
			if($cnt_row > 1)
			{
				for($ir=0;$ir<$cnt_row;$ir++)
				{
					if($tempCart[$ir]['SKU'] != "")
					{
						$product_quantity = $tempCart[$ir]['Qty'];
						$tot_qty += $product_quantity;

						$product_name = $tempCart[$ir]['ProductName'];
						$product_name = str_replace("\"","'",$product_name);

						$item_array[$ir]['product_name'] = $product_name;
						$item_array[$ir]['product_id'] = $tempCart[$ir]['SKU'];
						$item_array[$ir]['product_price'] = $tempCart[$ir]['Price'];
						$item_array[$ir]['product_quantity'] = $product_quantity;

						$ItemWiseDiscount = 0;
						if($Data['page'] == 'order_receipt')
						{
							if(((isset($tempCart[$ir]['ItemWiseCouponDiscount']) && $tempCart[$ir]['ItemWiseCouponDiscount'] > 0) || (isset($tempCart[$ir]['ItemWiseCouponDiscount_CJ']) && $tempCart[$ir]['ItemWiseCouponDiscount_CJ'] > 0)) && $Coupon_DiscLevel == "Item"){
								if($coupon_order == "7"){
									$ItemWiseDiscount = $tempCart[$ir]['ItemWiseCouponDiscount_CJ'];
								}else{
									$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
								}
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						} else {
							if(isset($tempCart[$ir]['ItemWiseCouponDiscount']) && $tempCart[$ir]['ItemWiseCouponDiscount'] > 0){
								$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						}
						$pid_fstr .= $tempCart[$ir]['SKU'].",";

						//sf track cart start
						$sf_item_array[$ir]['item'] = $tempCart[$ir]['SKU'];
						$sf_item_array[$ir]['quantity'] = $product_quantity;
						$sf_item_array[$ir]['price'] = $tempCart[$ir]['Price'];
						$sf_item_array[$ir]['unique_id'] = $tempCart[$ir]['SKU'];
						//sf track cart end
					}
				}
				$pid_fstr = substr($pid_fstr,0,-1);
				if($pid_fstr != "")
				{
					$Data['RemarketingprodID'] = explode(",",$pid_fstr);
				}
			}
			else
			{
				for($ir=0;$ir<$cnt_row;$ir++)
				{
					if($tempCart[$ir]['SKU'] != "")
					{
						$product_quantity = $tempCart[$ir]['Qty'];
						$tot_qty += $product_quantity;

						$product_name = $tempCart[$ir]['ProductName'];
						$product_name = str_replace("\"","'",$product_name);

						$item_array[$ir]['product_name'] = $product_name;
						$item_array[$ir]['product_id'] = $tempCart[$ir]['SKU'];
						$item_array[$ir]['product_price'] = $tempCart[$ir]['Price'];
						$item_array[$ir]['product_quantity'] = $product_quantity;

						$ItemWiseDiscount = 0;
						$tempCart[$ir]['ItemWiseCouponDiscount'] = (isset($tempCart[$ir]['ItemWiseCouponDiscount'])?$tempCart[$ir]['ItemWiseCouponDiscount']:0);
						$tempCart[$ir]['ItemWiseCouponDiscount_CJ'] = (isset($tempCart[$ir]['ItemWiseCouponDiscount_CJ'])?$tempCart[$ir]['ItemWiseCouponDiscount_CJ']:0);
						if($Data['page'] == 'order_receipt')
						{
							if(($tempCart[$ir]['ItemWiseCouponDiscount'] > 0 || $tempCart[$ir]['ItemWiseCouponDiscount_CJ'] > 0) && $Coupon_DiscLevel == "Item"){
								if($coupon_order == "7"){
									$ItemWiseDiscount = $tempCart[$ir]['ItemWiseCouponDiscount_CJ'];
								}else{
									$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
								}
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						} else {
							if($tempCart[$ir]['ItemWiseCouponDiscount'] > 0){
								$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						}

						$pid_fstr .= $tempCart[$ir]['SKU'];

						//sf track cart start
						$sf_item_array[$ir]['item'] = $tempCart[$ir]['SKU'];
						$sf_item_array[$ir]['quantity'] = $product_quantity;
						$sf_item_array[$ir]['price'] = $tempCart[$ir]['Price'];
						$sf_item_array[$ir]['unique_id'] = $tempCart[$ir]['SKU'];
						//sf track cart end
					}
				}
				if($pid_fstr != "")
				{
					$Data['RemarketingprodID'] = $pid_fstr;
				}
			}
			$Data['RemarketingtotalValue'] = Session::get('ShoppingCart.SubTotal');

			if($Data['page'] != 'order_receipt')
				$Data['SF_TrackCart'] = $sf_item_array;

			/*$line_items = json_encode($item_array);
			$line_items = str_replace("'","\'",$line_items);
			*/
			//$temp_items_arr = $file_value."==".$line_items;

			//sf track cart start
				/*$sf_items = json_encode($sf_item_array);
				$sf_items = str_replace("'","\'",$sf_items);
				*/
			//sf track cart end

		}

		$DataLayer = [];
		$DataLayer['RemarketingprodID'] = isset($Data['RemarketingprodID'])?$Data['RemarketingprodID']:'';
		$DataLayer['RemarketingpageType'] = $Data['pagetype'];
		if(isset($Data['RemarketingtotalValue']))
			$DataLayer['RemarketingtotalValue'] = $Data['RemarketingtotalValue'];
		$DataLayer['RemarketingOnly'] = "true";
		if(isset($Data['search_query']))
			$DataLayer['search_query'] = $Data['search_query'];
		if(isset($Data['SF_TrackCart']))
			$DataLayer['SF_TrackCart'] = $Data['SF_TrackCart'];

		if($Data['page'] == 'shoppingcart')
		{
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
		}
		if($Data['page'] == 'billing' || $Data['page'] == 'billing_amazon')
		{
			$DataLayer['line_items_array'] = $item_array;
			$DataLayer['line_items'] = $item_array;
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
		}

		if($Data['page'] == 'order_receipt')
		{
			$DataLayer['RemarketingConversionLanguage'] = 'en';
			$DataLayer['RemarketingConversionFormat'] = '3';
			$DataLayer['RemarketingConversionColor'] = 'ffffff';
			$DataLayer['RemarketingConversionLabel'] = 'O5inCKztgGsQkqLpuQM';
			$DataLayer['RemarketingOnly'] = "false";
			$DataLayer['RemarketingCouponCode'] = $coupon_code;
			$DataLayer['RemarketingDiscount'] = $discount;
			$DataLayer['RemarketingDiscountCJ'] = $cj_discount;
			$DataLayer['RemarketingOrderId'] = Session::get('ShoppingCart.OrderID');
			$DataLayer['line_items_array'] = $item_array;
			$DataLayer['line_items'] = $item_array;
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
			$DataLayer['SF_TrackPurchase'] = $sf_item_array;
			$GData['LabelVal'] = 'O5inCKztgGsQkqLpuQM';
		}

		if($Data['pagetype'] == 'other')
		{
			$DataLayer['RemarketingConversionLanguage'] = 'en';
			$DataLayer['RemarketingConversionFormat'] = '3';
			$DataLayer['RemarketingConversionColor'] = 'ffffff';
			$DataLayer['RemarketingOnly'] = "false";
			if($Data['page'] == 'register')
			{
				$DataLayer['RemarketingConversionLabel'] = 'FFhXCJKNhmsQkqLpuQM';
				$DataLayer['lead_type'] = 'RegistrationForm';
				$GData['LabelVal'] = 'FFhXCJKNhmsQkqLpuQM';
			}
			if($Data['page'] == 'wholesaleregister')
			{
				$DataLayer['RemarketingConversionLabel'] = 'SX50CJaUhmsQkqLpuQM';
				$DataLayer['lead_type'] = 'RegistrationForm';
				$GData['LabelVal'] = 'SX50CJaUhmsQkqLpuQM';
			}
			if($Data['page'] == 'myaccount')
			{
				if(Session::get('eusertype') == 'Retailer')
				{
					$DataLayer['RemarketingConversionLabel'] = 'FFhXCJKNhmsQkqLpuQM';
					$GData['LabelVal'] = 'FFhXCJKNhmsQkqLpuQM';
				}
				if(Session::get('eusertype') == 'Wholesaler')
				{
					$DataLayer['RemarketingConversionLabel'] = 'SX50CJaUhmsQkqLpuQM';
					$GData['LabelVal'] = 'SX50CJaUhmsQkqLpuQM';
				}
			}
			if($Data['page'] == 'contact_us')
			{
				$DataLayer['RemarketingConversionLabel'] = 'I4tECI6u7WoQkqLpuQM';
				$GData['LabelVal'] = 'I4tECI6u7WoQkqLpuQM';
			}
		}

		//Log::info($DataLayer);
		$DataLayer = json_encode($DataLayer);
		$GData['pagetype'] = $Data['pagetype'];
		$GData['google_remarketing_codes']="<script type='text/javascript'>window.dataLayer = window.dataLayer || []; dataLayer.push(".$DataLayer.");</script>";
		if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0 && Session::get('sess_useremail') != "" )
		{
			$GData['google_remarketing_codes'].= "<script type='text/javascript'>
				if(dataLayer.length > 0){
					dataLayer[0].SF_EmailUniqueId='".Session::get('sess_useremail')."';
				}else{
					dataLayer.push({
						'SF_EmailUniqueId': '".Session::get('sess_useremail')."'
					});
				}
			 </script>";
		}
		return $GData;
	}

	public function GetShippingChargeDays($ship_zip,$ship_state,$ship_country,$shipping_mode_id)
	{
		$DiscountAll = $this->GetAllDiscounts();
		$TotalDiscount = $DiscountAll['TotalDiscount'];
		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$subTotal = $SubTotal - $TotalDiscount;
		$ship_country  = substr($ship_country, 0, 2);
		$shipping_mode_id = (int)$shipping_mode_id;

		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id', '=', $shipping_mode_id)
					->where('zipcode_to','>=',$ship_zip)
					->where('zipcode_from','<=',$ship_zip)
					->where('state','like','%'.$ship_state.'%')
					->where('country','like','%'.$ship_country.'%')->get();

			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id', '=', $shipping_mode_id)
					->where('zipcode_to','>=',$ship_zip)
					->where('zipcode_from','<=',$ship_zip)
					->where('country','like','%'.$ship_country.'%')->get();

				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id', '=', $shipping_mode_id)
							->where('state','like','%'.$ship_state.'%')
							->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id', '=', $shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('zipcode_to','=','')
								->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}
		}

		$shipping_rule_id 	= $rid[0]["shipping_rule_id"];
		$rule_type  		= $rid[0]["rule_type"];
		$NewdaysVal			= $rid[0]["days"];

		########### END CODE FOR CALCULATE PROP SHIP CHARGE###########
		//$this->setShippingCharge($temp_ShippingCharge);
		return $NewdaysVal;
	}

	public function GetCartForStripe()
	{
		$CartItems = [];
		$i=0;
		if(Session::has('ShoppingCart'))
		{
			$ShoppingCart = Session::get('ShoppingCart');
			if(isset($ShoppingCart['Cart']) && count($ShoppingCart['Cart']) > 0)
			{
				foreach($ShoppingCart['Cart'] as $key => $Cart)
				{
					$CartItems[$i]['amount'] = round($Cart['Price']*100);
					$CartItems[$i]['label'] = $Cart['ProductName'];
					$i++;
				}
				$AllCharges = $this->GetAllCharges();
				foreach($AllCharges['Charges'] as $Charge)
				{
					$CartItems[$i]['amount'] = round($Charge['charge']*100);
					$CartItems[$i]['label'] = $Charge['label'];
					$i++;
				}

				$AllDiscount = $this->GetAllDiscounts();
				foreach($AllDiscount['Discounts'] as $discount)
				{
					$CartItems[$i]['amount'] = round($discount['discount']*100);
					$CartItems[$i]['label'] = $discount['label'];
					$i++;
				}
			}
		}
		return $CartItems;
	}

	public function Merge_Guest_Register($email,$customerid){
		$merge_data = Customer::where('status','=','1')->where('email','=',$email)->where('registration_type','=','M')->get();

		if($merge_data->count() > 0){
			$check_cust_email = Customer::where('status','=','1')->where('email','=',$email)->where('registration_type','=','G')->where('is_deleted','=','No')->get();

			if($check_cust_email->count() > 0){
				$merge_id = $merge_data[0]['customer_id'];
				$registration_type = $merge_data[0]['registration_type'];
				$merge_log = "Merge with ".$merge_data[0]['eusertype'].$registration_type." customer id: ".$merge_id;

				$CustUpdateArr = array ('is_deleted'=> "Yes",
										'merge_log' => $merge_log
										);
				$cust_upd = Customer::where('customer_id','=',$check_cust_email[0]['customer_id'])->update($CustUpdateArr);

				$merge_log = "Merge with ".$merge_data[0]['eusertype'].$registration_type." customer id: ".$merge_id."<br>Previous customer id: ".$check_cust_email[0]['customer_id'];
				$OrderUpdateArr = array('customer_id'  => $merge_id,
										'merge_note' => $merge_log,
										'old_customerid' => $check_cust_email[0]['customer_id']
										);
				$cust_upd = Order::where('customer_id','=',$check_cust_email[0]['customer_id'])->update($OrderUpdateArr);
			}
		}
	}
	public function getDealSubTotal()
	{
		 $tempCart = Session::get('ShoppingCart.Cart');
	//	 echo "<pre>"; print_r($tempCart); exit;
		 $TotalDeal = 0;
		 for($a=0;$a<count($tempCart);$a++)
		 {
			if(isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]=='Yes')
			{
			  $TotalDeal = $TotalDeal + $tempCart[$a]["TotPrice"];
			}
		 }
		 return $TotalDeal;
	}

	public function GetWholesalePrice($products_id, $qty = 1)
	{
		$per = 0;
		$val = 0;
		if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler')
		{
			if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
			{
				$specialpricedtl = GetSpecialPricePercentandValue($qty);
				$perval = explode("#",$specialpricedtl);
				$per = $perval[0];
				$val = $perval[1];
			}

			$ProdInfo = DB::table('pu_products as p')
					->join('pu_products_one as po','p.products_id','=','po.products_id')
					->where(function($query){
						$query->orWhere('p.status','=','1');
						$query->OrWhere(function($qry){
							$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');
						});
					})
					->where('p.products_id','=',$products_id)->get();
			if(!$ProdInfo || $ProdInfo->count() == 0 )
			{
				return response()->json(array('Error' => 0));
			}

			$ProductRs = $this->SetProduct($ProdInfo[0]);

			## Here Overwrite sale Price Field
			$ProductRs->sale_price = $ProductRs->product_price;
			$actual_product_price = $ProductRs->product_price;

			$ProductRs->IsDealProducts = 'No';
			$ProductRs->DealDiscountFlag = 'No';

			$ProductRs->ItemPrice = NumberFormat($ProductRs->sale_price);
			$DealOfWeek = GetDealOfWeek($ProductRs->sku,'Weekly','Cart');
			if(count($DealOfWeek) > 0)
			{
				if($DealOfWeek[$ProductRs->sku]['deal_price']!='' && $DealOfWeek[$ProductRs->sku]['deal_price'] < $ProductRs->sale_price )
				{
					$dealprice = NumberFormat($DealOfWeek[$ProductRs->sku]['deal_price']);
					$ProductRs->sale_price = $dealprice;
					$ProductRs->ItemPrice  = $dealprice;
				}
			}

			if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler')
			{
				if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
				{
					if($per > 0)
						$ProductRs->sale_price = $ProductRs->sale_price - $ProductRs->sale_price* $per/100;
				}
			}
			return response()->json(array('Price' => Price($ProductRs->sale_price)));
		}
	}
	public function GetAllFBView()
	{

		$firstname = "";
		$lastname = "";
		$city = "";
		$state = "";
		$country ="";
		$zip ="";
		$phone = "";
		$CustInfoArr = "";
		$emailAddress	= "";

		if(Session::has('ShoppingCart.ShippingAddress'))
		{
			$Shipping = Session::get('ShoppingCart.ShippingAddress');

			$firstname 	= (isset($Shipping["first_name"])  && $Shipping["first_name"]!='')? $Shipping["first_name"]:'';
			$lastname 	= (isset($Shipping["last_name"])  && $Shipping["last_name"]!='')? $Shipping["last_name"]:'';
			$country 	= (isset($Shipping["country"])  && $Shipping["country"]!='')? $Shipping["country"]:'';
			$zip 	  	= (isset($Shipping["zip"])  && $Shipping["zip"]!='')? $Shipping["zip"]:'';
			$state	  	= (isset($Shipping["state"])  && $Shipping["state"]!='')? $Shipping["state"]:'';
			$phone 	= (isset($Shipping["phone"])  && $Shipping["phone"]!='')? $Shipping["phone"]:'';;
			$city 	= (isset($Shipping["city"])  && $Shipping["city"]!='')? $Shipping["city"]:'';
			$emailAddress= (isset($Shipping["email"])  && $Shipping["email"]!='')? $Shipping["email"]:'';
		}
		else if(Session::has('etype') && Session::get('etype')=="M" && Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0)
		{
			if(Session::has('sess_custname'))
			{

				$CustInfoArrV = explode("|",Session::get('sess_custname'));
				if(isset($CustInfoArrV[0]) && $CustInfoArrV[0]!='')
				{
					$firstname = $CustInfoArrV[0];
				}
				if(isset($CustInfoArrV[1]) && $CustInfoArrV[1]!='')
				{
					$lastname = $CustInfoArrV[1];
				}

			}
			$emailAddress= Session::get('sess_useremail');
			if(Session::has('sess_useraddress'))
			{
				$CustInfoArrVT = explode("|",Session::get('sess_useraddress'));

				if(isset($CustInfoArrVT[0]) && $CustInfoArrVT[0]!='')
				{
					$city = $CustInfoArrVT[0];
				}
				if(isset($CustInfoArrVT[1]) && $CustInfoArrVT[1]!='')
				{
					$state = $CustInfoArrVT[1];
				}
				if(isset($CustInfoArrVT[2]) && $CustInfoArrVT[2]!='')
				{
					$country = $CustInfoArrVT[2];
				}
				if(isset($CustInfoArrVT[3]) && $CustInfoArrVT[3]!='')
				{
					$zip = $CustInfoArrVT[3];
				}
				if(isset($CustInfoArrVT[4]) && $CustInfoArrVT[4]!='')
				{
					$phone = $CustInfoArrVT[4];
				}

			}
		}
		$FbCode = "";
		if(Session::has('ShoppingCart.ShippingAddress'))
		{
		$FbCode="dataLayer.push({
								  event: 'pageview',
								  user_data :{
										address :
											{
												first_name : '".$firstname."',
												last_name : '".$lastname."',
												email_address: '".$emailAddress."',
												phone_number : '".$phone."',
												city		 : '".$city."',
												country		 : '".$country."',
												state		 : '".$state."',
												zip_code	 : '".$zip."',
											}
									},
									});
								";
		}
		else if(Session::has('etype') && Session::get('etype')=="M" && Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0)
		{
		 $FbCode="dataLayer.push({
								  event: 'pageview',
								  user_data :{
										address :
											{
												first_name : '".$firstname."',
												last_name : '".$lastname."',
												email_address: '".$emailAddress."',
												phone_number : '".$phone."',
												city		 : '".$city."',
												country		 : '".$country."',
												state		 : '".$state."',
												zip_code	 : '".$zip."',
											}
									},
									});
								";
		}
		else if(Session::has('sess_news_surname') && Session::get('sess_news_surname')!="")
		{
			if(Session::has('sess_news_surname'))
			{

				$CustInfoArrV = explode("|",Session::get('sess_news_surname'));
				if(isset($CustInfoArrV[0]) && $CustInfoArrV[0]!='')
				{
					$firstname = $CustInfoArrV[0];
				}
				if(isset($CustInfoArrV[1]) && $CustInfoArrV[1]!='')
				{
					$lastname = $CustInfoArrV[1];
				}

			}
			if(Session::has('sess_phone'))
			{
				$phone = Session::get('sess_phone');
			}
			if(Session::has('sess_news_email1'))
			{
				$emailAddress = Session::get('sess_news_email1');
			}
			 $FbCode="dataLayer.push({
								  event: 'pageview',
								  user_data :{
										address :
											{
												first_name : '".$firstname."',
												last_name : '".$lastname."',
												email_address: '".$emailAddress."',
												phone_number : '".$phone."'
											}
									},
									});
								";

		}

		return $FbCode;
	}

	public function GetTotalItemsOfFreeSampleInCart(){
		if(Session::has('ShoppingCart.Cart'))
		{
			return count(array_filter(array_column(Session::get('ShoppingCart.Cart'), 'Is_Free_Sample')));
		}
	}

	public function GetTotalItemsOfFreegift()
	{
		if(Session::has('ShoppingCart.Cart'))
		{
			return count(array_filter(array_column(Session::get('ShoppingCart.Cart'), 'IS_Free_Gift')));
		}
	}
	public function GetFreegiftId()
	{
		if(Session::has('ShoppingCart.Cart'))
		{
			$sku_values = array_filter(array_column(Session::get('ShoppingCart.Cart'), 'freeproductsid'));
			$sku = reset($sku_values);
			return $sku;
			//return reset(array_filter(array_column(Session::get('ShoppingCart.Cart'), 'freeproductsid')));
		}
	}

	public function GetFreeSampleId(){
		if(Session::has('ShoppingCart.Cart'))
		{
			$sku_values = array_filter(array_column(Session::get('ShoppingCart.Cart'), 'freesampleproductsid'));
			$sku = reset($sku_values);
			return $sku;
		}
	}

	public function CheckBOGODiscountProduct($CartProduct)
	{
		$DiscountMessage = "";
		$SKU = $CartProduct['SKU'];
		$Category = $CartProduct['CategoryID'];
		$Brand = $CartProduct['ImanufactureID'];

		$CheckSKUInBOGO = DB::table('pu_bogo_discount')
			->where('start_date', '<=', DB::raw('curdate()'))
			->where('end_date', '>=', DB::raw('curdate()'))
			->where(function ($query) use ($SKU,$Category,$Brand) {
					$query->orWhereRaw('FIND_IN_SET(?, sku)', [$SKU]);
					$query->orWhereRaw('FIND_IN_SET(?, sku)', [$Category]);
					$query->orWhereRaw('FIND_IN_SET(?, sku)', [$Brand]);
			})
			->where('status','1')
			->orderBy('bogo_discount_id', 'desc')
			->get();

		if($CheckSKUInBOGO && count($CheckSKUInBOGO) > 0)
		{
			$pocketPerfumeCategory = $this->getPocketPerfumeCategory();
			if($CheckSKUInBOGO[0]->exclude_pocketperfume == 'Yes' && in_array($Category,$pocketPerfumeCategory))
			{
				return "";
			}
			if(!empty($CheckSKUInBOGO[0]->exclude_product_skus))
			{
				$ExcludedSKUs = explode(",",$CheckSKUInBOGO[0]->exclude_product_skus);
				if(in_array($SKU,$ExcludedSKUs))
				{
					return "";
				}
			}

			if($CheckSKUInBOGO[0]->type == '0')
			{
				$DiscountMessage = $this->SetBogoMessage($CheckSKUInBOGO[0]->type,0);
				if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
				{
					$CartDetails = Session::get('ShoppingCart.Cart');
					$BogoApplied = 'No';

					foreach($CartDetails as $key => &$Cart)
					{
						if($Cart['SKU'] == $SKU)
						{
							if((float)$Cart['BogoItemWiseDiscout'] > 0)
							{
								$DiscountMessage = $this->SetBogoMessage($CheckSKUInBOGO[0]->type,1);
							}
							$Cart['BogoDiscountMessage'] = $DiscountMessage;
							$Cart['BogoDetail'] = ['type' => $CheckSKUInBOGO[0]->type, 'percentage' => $CheckSKUInBOGO[0]->percentage, 'quantity' => $CheckSKUInBOGO[0]->quantity];
							$BogoApplied = 'Yes';
							break;
						}
					}
					if($BogoApplied == 'Yes')
					{
						Session::put("ShoppingCart.Cart", $CartDetails);
					}
				}

			} else if($CheckSKUInBOGO[0]->type == '1')
			{
				$Percentage = $CheckSKUInBOGO[0]->percentage;
				$DiscountMessage = $this->SetBogoMessage($CheckSKUInBOGO[0]->type,0,$Percentage);
				if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
				{
					$CartDetails = Session::get('ShoppingCart.Cart');
					$BogoApplied = 'No';
					foreach($CartDetails as $key => &$Cart)
					{
						if($Cart['SKU'] == $SKU)
						{
							if((float)$Cart['BogoItemWiseDiscout'] > 0)
							{
								$DiscountMessage = $this->SetBogoMessage($CheckSKUInBOGO[0]->type,1,$Percentage);
							}
							$Cart['BogoDiscountMessage'] = $DiscountMessage;
							$Cart['BogoDetail'] = ['type' => $CheckSKUInBOGO[0]->type, 'percentage' => $CheckSKUInBOGO[0]->percentage, 'quantity' => $CheckSKUInBOGO[0]->quantity];
							$BogoApplied = 'Yes';
							break;
						}
					}
					if($BogoApplied == 'Yes')
					{
						Session::put("ShoppingCart.Cart", $CartDetails);
					}
				}
			} else if($CheckSKUInBOGO[0]->type == '2')
			{
				$Percentage = $CheckSKUInBOGO[0]->percentage;
				$Quantity = $CheckSKUInBOGO[0]->quantity;
				$Percentage = $CheckSKUInBOGO[0]->percentage;
				$DiscountMessage = $this->SetBogoMessage($CheckSKUInBOGO[0]->type,0,$Percentage,$Quantity);

				if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
				{
					$CartDetails = Session::get('ShoppingCart.Cart');
					$BogoApplied = 'No';
					foreach($CartDetails as $key => &$Cart)
					{
						if($Cart['SKU'] == $SKU)
						{
							if((float)$Cart['BogoItemWiseDiscout'] > 0)
							{
								$DiscountMessage = $this->SetBogoMessage($CheckSKUInBOGO[0]->type,1,$Percentage,$Quantity);
							}
							$Cart['BogoDiscountMessage'] = $DiscountMessage;
							$Cart['BogoDetail'] = ['type' => $CheckSKUInBOGO[0]->type, 'percentage' => $CheckSKUInBOGO[0]->percentage, 'quantity' => $CheckSKUInBOGO[0]->quantity];
							$BogoApplied = 'Yes';
							break;
						}
					}
					if($BogoApplied == 'Yes')
					{
						Session::put("ShoppingCart.Cart", $CartDetails);
					}
				}
			}
		}
		return $DiscountMessage;
	}

	public function SetBogoMessage($BogoType,$Applied=0,$Percentage=0,$Quantity=0)
	{
		$BogoDiscountMessage = '<div class="pdpsuggest-div">';
		$BogoMessage = "";
		$BogoIcon = [0 => 'svg-suggestgift', 1 => 'svg-suggestsales', 2 => 'svg-suggestsales'];
		if($Applied == 0)
		{
			if($BogoType == '0')
				$BogoMessage = "Buy One Get One Free";
			elseif($BogoType == '1')
				$BogoMessage = "You save ".$Percentage."% on your 2nd bottle";
			elseif($BogoType == '2')
				$BogoMessage = "You save ".$Percentage."% off your ". addOrdinalSuffix($Quantity+1) . " bottle";
		} else {
			if($BogoType == '0')
				$BogoMessage = "Discount unlocked: 100% off your 2nd bottle!";
			elseif($BogoType == '1')
				$BogoMessage = "Discount unlocked: " . $Percentage . "% off your 2nd bottle!";
			elseif($BogoType == '2')
				$BogoMessage = "Discount unlocked: " . $Percentage . "% off your " . addOrdinalSuffix($Quantity+1) . " bottles!";
		}
		$DisplayIcon = $BogoIcon[$BogoType];
		$BogoDiscountMessage.='
			<svg class="'.$DisplayIcon.'" aria-hidden="true" role="img" width="16" height="16" focusable="false">
        		<use href="#'.$DisplayIcon.'" xmlns:xdivnk="http://www.w3.org/1999/xlink" xlink:href="#'.$DisplayIcon.'"></use>
    		</svg>';
		$BogoDiscountMessage.='<span class="pdpsuggest-txt">'.$BogoMessage.'</span>';
		$BogoDiscountMessage.='</div>';
		return $BogoDiscountMessage;
	}

	public function PromoBadges($Product)
	{
		if(!Cache::has('PromoBadges'))
		{
			$PromoBadges = Cache::remember('PromoBadges', 3600, function() {
				$PromoDetail = DB::table('pu_promo_badges')->get();
				if($PromoDetail && count($PromoDetail) > 0)
					return $PromoDetail[0];
			});
		}
		$Badge = [];
		$PromoBadges = Cache::get('PromoBadges');
		$BadgeTitle = $PromoBadges->badge_title;
		$BadgeDetails = $PromoBadges->badge_details;
		$BadgeTextColor = $PromoBadges->badge_color;
		$BadgeBackColor = $PromoBadges->badge_backcolor;
		$BadgeGradientColor = $PromoBadges->badge_gradient;
		$BadgeCategories = (!empty($PromoBadges->categories)?explode(',',$PromoBadges->categories):[]);
		$BadgeBrands = (!empty($PromoBadges->brands)?explode(',',$PromoBadges->brands):[]);
		$BadgeSkus = (!empty($PromoBadges->skus)?explode(',',$PromoBadges->skus):[]);
		$BadgeExcludeSkus = (!empty($PromoBadges->exclude_skus)?explode(',',$PromoBadges->exclude_skus):[]);
		$BadgeExcludePocketPerfume = $PromoBadges->exclude_pocket_perfume;
		$BadgeStatus = $PromoBadges->status;
		$IsBadgeProduct = 'No';
		if($BadgeStatus == '1')
		{
			$pocketPerfumeCategory = $this->getPocketPerfumeCategory();
			if($BadgeExcludePocketPerfume == 'Yes' && in_array($Product['CategoryID'],$pocketPerfumeCategory))
			{
				return [];
			}
			if(!in_array($Product['SKU'], $BadgeExcludeSkus))
			{
				if(in_array($Product['CategoryID'],$BadgeCategories))
				{
					$IsBadgeProduct = 'Yes';
				} else if(in_array($Product['ImanufactureID'],$BadgeBrands))
				{
					$IsBadgeProduct = 'Yes';
				} else if(in_array($Product['SKU'],$BadgeSkus))
				{
					$IsBadgeProduct = 'Yes';
				}
			}
		}
		if($IsBadgeProduct == 'Yes')
		{
			$Badge = ['BadgeTitle' => $BadgeTitle, 'BadgeDetails' => $BadgeDetails,'TextColor' => $BadgeTextColor, 'BackColor' => $BadgeBackColor, 'GradientColor' => $BadgeGradientColor];
		}
		return $Badge;
	}
}
