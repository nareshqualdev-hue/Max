<?php
namespace App\Http\Controllers;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\POSController;
use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\CartTrait;
use App\Http\Controllers\Traits\AfterpayTrait;
use App\Http\Controllers\Traits\StripeTrait;
use App\Http\Controllers\POSConnecterController;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;
use App\Models\MetaInfo;
use App\Models\Category;
use App\Models\Products;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\NewsLetter;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RewardPoint;
use App\Models\ShippingMode;
use App\Models\ShippingRule;
use App\Models\ShippingRate;
use App\Models\ShippingHoliday;
use App\Models\TaxAreas;
use App\Models\TaxAreaState;
use App\Models\TaxRates;
use App\Models\PaymentMethod;
use App\Models\GiftCertificate;
use App\Models\MailBanner;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderDetails;
use App\Models\Dealofweek;
use App\Models\ReferFriend;
use App\Models\Shoppingcart;
use App\Models\PaypalIpnLog;
use App\Models\CustomerCreditLimitLogs;
use App\Models\RecommendedProduct;
use App\Models\StorePickup;
use App\Models\StoreSalesPerson;
use App\Models\StoreInventory;
use App\Models\StoreCardReader;
use Illuminate\Support\Facades\DB;
use App\Http\Services\CacheService;
use Session;
use Cookie;
use Cache;

class ShoppingcartController extends Controller
{
	use CommonTrait;
	use VendorTrait;
	use CartTrait;
	use AfterpayTrait;
	use StripeTrait;
	public function __construct()
	{
		config(['logging.default' => 'shoppingcart']);
		$this->PageData['CSSFILES'] = ['static.css'];
		$PageType = 'NR';
		if (!Cache::has('SHOPPINGCARTMETAINFO')) {
			$MetaInfo = MetaInfo::where('type','=',$PageType)->get();
			Cache::put('SHOPPINGCARTMETAINFO', $MetaInfo);
		}else{
			$MetaInfo = Cache::get('SHOPPINGCARTMETAINFO');
		}
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_title'] = getMetaTitleDescription($MetaInfo[0]->meta_title);
			$this->PageData['meta_description'] = getMetaTitleDescription($MetaInfo[0]->meta_description);
			$this->PageData['meta_keywords'] = getMetaTitleDescription($MetaInfo[0]->meta_keywords);
		}
	}

	public function SetCart(Request $request)
	{
		if($request->ajax()){
			if(isset($request->action))
			{
				if ($request->action == 'insert') {
				$products_id = $request->products_id;
				$searchTerm = $request->products_id;
				if (!isset($request->prodqty) && empty($request->prodqty))
					$quantity = 1;
				else
					$quantity = (int)$request->prodqty;

				$OrderType	= "Website";

				if (isset($request->btlform) && $request->btlform == "store" && $products_id != 0)
				{

					$OrderType	= "Store";

					$ProdIds = Products::select('products_id')
						->distinct()
						->where(function ($query) use ($products_id) {
							$query->where('sku', $products_id)
								  ->orWhere('upc', $products_id);
						})
						->get();

					if ($ProdIds->count() > 0 && isset($ProdIds[0]['products_id']) && $ProdIds[0]['products_id'] > 0) {
						$products_id = $ProdIds[0]['products_id'];
					} else {
						if(isset($request->device) && !empty($request->device))
						{
							$DeviceLogData = [
								'device_id' 					=> $request->device,
								'device_name' 					=> $request->device,
								'last_loggedIn_store_id'		=> Session::get('sess_store_id'),
								'last_loggedIn_store_user_id'	=> Session::get('sess_storeuserid'),
								'log_type'						=> 'Error',
								'message'						=> "Product Not Found. Invalid Product SKU or UPC Number.",
								'product'						=> $searchTerm,
								'metadata'						=> json_encode(['No' => "No"])
							];
							DB::table('pu_store_device_log')->insert($DeviceLogData);
						}
						return response()->json(['No' => "No"]);
					}
				}
				$response = $this->AddToCart($products_id, $quantity, $cookiee = 'No', '', '', $OrderType);

				/*Beacon Tracking(results & cart object) Starts*/

				$resultProducts = array();
				$cartProducts = array();

				if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
					$getCartForTracking = Session::get('ShoppingCart')['Cart'];

					for($b=0;$b<count($getCartForTracking);$b++){
						if($getCartForTracking[$b]['ProductID'] == $products_id){
							$resultProducts[] = [
								"parentId"  => "0",
								"uid" => "'".$products_id."'",
								"sku" => $getCartForTracking[$b]['SKU'],
								"qty" => $quantity,
								//"price" => (float)number_format($getCartForTracking[$b]['ItemPrice'], 2, '.', '')
								"price" => isset($getCartForTracking[$b]['ItemPrice']) ? round((float) $getCartForTracking[$b]['ItemPrice'], 2) : 0
							];
						}

						$cartProducts[] = [
							"parentId"  => "0",
							"uid" => "'".$getCartForTracking[$b]['ProductID']."'",
							"sku" => $getCartForTracking[$b]['SKU'],
							"qty" => $getCartForTracking[$b]['Qty'],
							//"price" => (float)number_format($getCartForTracking[$b]['ItemPrice'], 2, '.', '')
							"price" => isset($getCartForTracking[$b]['ItemPrice']) ? round((float) $getCartForTracking[$b]['ItemPrice'], 2) : 0
						];
					}
				}
				/*Beacon Tracking(results & cart object) Endd*/

				/*Beacon Tracking Starts*/
				if(count($resultProducts) > 0 && count($cartProducts) > 0){
					$dataBeacon = [
						"results" => $resultProducts,
						"cart" => $cartProducts,
					];
					$this->newBeaconTracking('cartAdd',$dataBeacon);
				}
				/*Beacon Tracking Ends*/

				if(isset($request->btlform) && $request->btlform == "store" && $products_id != 0 && isset($request->device) && !empty($request->device))
				{
					$responseData = $response->getData(true);
					$log_type = "Normal";
					$message = "";
					$Exists = $responseData['exist']??'';
					if($responseData['Added'] == 0 )
					{
						if($Exists != 1)
						{
							$log_type = "Error";
							$message = $responseData['CartErrors'][0]??'';
						}
					}
					if($log_type == 'Error')
					{
						$DeviceLogData = [
							'device_id' 					=> $request->device,
							'device_name' 					=> $request->device,
							'last_loggedIn_store_id'		=> Session::get('sess_store_id'),
							'last_loggedIn_store_user_id'	=> Session::get('sess_storeuserid'),
							'log_type'						=> $log_type,
							'message'						=> $message,
							'product'						=> $searchTerm,
							'metadata'						=> json_encode($responseData)
						];
						DB::table('pu_store_device_log')->insert($DeviceLogData);
					}
				}
				return $response;
				//return $this->AddToCart($products_id, $quantity, $cookiee = 'No', '', '', $OrderType);
				}

                if($request->action == 'yotpo_free_gift_insert')
				{
					$products_id=(int)$request->products_id;
					if(!isset($request->prodqty) && empty($request->prodqty))
						$quantity = 1;
					else
						$quantity = (int)$request->prodqty;
                    config(['YotpoFreeGiftCoupon' => $request->yotpo_free_gift_coupon]);
					return $this->AddToCart($products_id, $quantity ,$cookiee='No','No','Yes');
				}
				if($request->action == 'reorder')
				{
					if(isset($request->prodDetails) && count($request->prodDetails) > 0)
					{
						$ProdDetails = $request->prodDetails;
						foreach($ProdDetails as $Prod)
						{
							$products_id=(int)$Prod['productID'];
							$quantity = (int)$Prod['prodqty'];
							$this->AddToCart($products_id, $quantity ,$cookiee='No');
						}
					}
				}
				if($request->action == 'remove')
				{
					return $this->RemoveFromCart($request->CartID);
				}
				if($request->action == 'update')
				{
					$products_id=(int)$request->products_id;
					$giftwrap = 'No';
					if(isset($request->giftwrap) && $request->giftwrap == 'Yes')
						$giftwrap = $request->giftwrap;
					if(!isset($request->prodqty) && empty($request->prodqty))
						$quantity = 1;
					else
						$quantity = (int)$request->prodqty;
					$ListingPage = $request->ListingPage??"N";
					return $this->UpdateCart($products_id,$quantity,$giftwrap,$ListingPage);
				}
				if($request->action == 'apply_coupon')
				{
					$CouponNumber ="";
					if(isset($request->coupon_number) && $request->coupon_number!='')
					{
						$normaluser = Auth::user();
						if (Auth::guard('store')->check()) {
							$normaluser = Auth::guard('web')->user();
						}
						$CouponNumber = $request->coupon_number;
						$CustomerID = ($normaluser?Session::get('sess_icustomerid'):null); //(Auth::user()?Session::get('sess_icustomerid'):null);
						$result = $this->ApplyCouponDiscount($CouponNumber,$CustomerID);
						$this->SetupCart();
						if(isset($result['error']) &&  $result['error']==1)
						{
							Session::flash('CartError',$result['message']);
						}
						else
						{
							Session::flash('CartSuccess',$result['message']);
						}
					}
					else
					{
						$error = 1;
						$msg = "Invalid Coupon Code.";
						$result['error'] = $error;
						$result['message'] = $msg;
					}
					return response()->json(['error' => $result['error'],'message' => $result['message']]);
				}
				if($request->action == 'apply_credit_limit')
				{
					$res = $this->ApplyCreditDiscount(1);
					$Msg = "";
					$normaluser = Auth::user();
					if (Auth::guard('store')->check()) {
						$normaluser = Auth::guard('web')->user();
					}
					//if(Auth::user() && Auth::user()->eusertype == 'Wholesaler')
					if($normaluser && $normaluser->eusertype == 'Wholesaler')
					{
						$Msg = 'Applied credit limit successfully.';
					} else {
						$Msg = 'Applied store credit successfully.';
					}
					Session::flash('CartSuccess',$Msg);
					$this->SetupCart();
					return response()->json(['message' => $Msg,'NetTotal' => $this->GetNetTotal()]);
				}
				if($request->action == 'remove_credit_limit')
				{
					Session::put('ShoppingCart.credit_limit_discount',0);
					Session::put('ShoppingCart.customer_remaining_credit_amount',0);
					$Msg = "";
					$normaluser = Auth::user();
					if (Auth::guard('store')->check()) {
						$normaluser = Auth::guard('web')->user();
					}
					//if(Auth::user() && Auth::user()->eusertype == 'Wholesaler')
					if($normaluser && $normaluser->eusertype == 'Wholesaler')
						$Msg = 'Applied Credit Limit removed successfully.';
					else
						$Msg = 'Applied Store Credit removed successfully.';
					$this->SetupCart();
					addLog("remove_credit_limit");
					return response()->json(['message' => $Msg,'NetTotal' => $this->GetNetTotal()]);
				}
				if($request->action == 'apply_gift_coupon' && config('Settings.GIFTCERTIFICATEFLAG')=="Yes")
				{
					if(isset($request->giftcard) && $request->giftcard != '')
					{
						$error = 0;
						$GiftMsgContent = $this->ApplyGiftCoupons($request->giftcard);

						if(isset($GiftMsgContent) && $GiftMsgContent=="Yes" ){
							$Msg ='Gift card cannot be applied to purchase another gift card.';

							$error = 1;
							Session::flash('CartError',$Msg);
						}
						else if(isset($GiftMsgContent) && $GiftMsgContent==1)
						{
							$Msg = 'E-gift card code is successfully applied.';
							$error = 0;
							Session::flash('CartSuccess',$Msg);
						}
						else{
							$Msg = 'E-gift card code is invalid or does not exists!'.$GiftMsgContent;
							$error = 1;
							Session::flash('CartError',$Msg);
						}
						$this->SetupCart();
						$log['error'] = $error;
						$log['message'] = $Msg;
						addLog("GiftCouponInfo",$log);
						return response()->json(['error' => $error,'message' => $Msg]);
					}
				}
				if($request->action == 'remove_yotporeward')
				{
					if(Session::has('ShoppingCart.YotpoRewardRedeemDiscount')){
						//this session creates when redeem action done using dropdown on checkout page
						if(Session::has('ShoppingCart.YotpoRewardCode') && trim(Session::get('ShoppingCart.YotpoRewardCode'))!=''){
							//Here coupon get deactive or cancel
							$normaluser = Auth::user();
							if (Auth::guard('store')->check()) {
								$normaluser = Auth::guard('web')->user();
							}
							$yotpo_coupon = trim(Session::get('ShoppingCart.YotpoRewardCode'));
							$coupon_status['status'] = '0';
							$coupon_customer_email = trim($normaluser->email); //trim(Auth::user()->email); //'qqualdev@gmail.com';
							$updateCoupon = Coupon::where('coupon_number','=',$yotpo_coupon)->where('customer_email','=',$coupon_customer_email)->where('start_date','=',DB::raw('curdate()'))->update($coupon_status);
							//$updateCoupon = Coupon::where('coupon_number','=',$yotpo_coupon)->update($coupon_status);
						}
						Session::forget('ShoppingCart.YotpoRewardRedeemDiscount');
					}
					Session::put('ShoppingCart.YotpoRewardDiscount','');
					Session::put('ShoppingCart.YotpoRewardCode','');
					$Msg = "Applied yotpo reward discount removed successfully.";
					Session::flash('CartSuccess',$Msg);
					$this->SetupCart();
					$log['message'] = $Msg;
					addLog("RemoveYotpoReward",$log);
					return response()->json(['message' => $Msg]);
				}
				if($request->action == 'remove_gift_coupon')
				{
					Session::put('ShoppingCart.GiftCoupon.Code','');
					Session::put('ShoppingCart.GiftCoupon.Value','');
					Session::put('ShoppingCart.GiftCoupon.Applicable_Value','');
					$Msg = "Applied E-gift card code removed successfully.";
					$this->SetupCart();
					$log['message'] = $Msg;
					addLog("RemoveGiftCoupon",$log);
					return response()->json(['message' => $Msg]);
				}
				if($request->action == 'remove_coupon')
				{
					// Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',0);
					// Session::put('ShoppingCart.PromoCoupon.SecondCouponDiscount',0);
					// Session::put('ShoppingCart.PromoCoupon.CouponCode','');
					// Session::put('ShoppingCart.PromoCoupon.CouponDiscount',0);
					// Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag','');
					// Session::put('ShoppingCart.PromoCoupon.FreeShipping','');
					// Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID','');
					// $this->FreeGiftInsertWithCoupon(Session::get('ShoppingCart.PromoCoupon.CouponCode'),'CouponRemove');
					// $Msg = "Applied coupon code removed successfully.";
					// $this->SetupCart();
					// Session::flash('CartSuccess',$Msg);
					// return response()->json(['message' => $Msg]);

					$paymentintentid = "";
					if(Session::has("StripePaymentType") &&	(Session::get("StripePaymentType")=="Google Pay" || Session::get("StripePaymentType")=="Apple Pay"))
					{
						$paymentintentid = Session::has("ShoppingCart.apple_google_paymentintentid")?Session::get("ShoppingCart.apple_google_paymentintentid"):'';
					}

					if($paymentintentid == "")	{
						Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',0);
						Session::put('ShoppingCart.PromoCoupon.SecondCouponDiscount',0);
						Session::put('ShoppingCart.PromoCoupon.CouponCode','');
						Session::put('ShoppingCart.PromoCoupon.CouponDiscount',0);
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag','');
						Session::put('ShoppingCart.PromoCoupon.FreeShipping','');
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID','');
						$this->FreeGiftInsertWithCoupon(Session::get('ShoppingCart.PromoCoupon.CouponCode'),'CouponRemove');
						$Msg = "Applied coupon code removed successfully.";
						$this->SetupCart();
						Session::flash('CartSuccess',$Msg);
						$log['message'] = $Msg;
						addLog("RemoveCoupon",$log);
						return response()->json(['message' => $Msg]);
					} else {
						$Msg = 'Coupon Code cannot be removed for now.';
						//Session::flash('CartError',$Msg);
						Session::flash('CartSuccess',$Msg);
						$log['message'] = $Msg;
						addLog("cantRemoveCoupon",$log);
						return response()->json(['message' => $Msg]);
					}
				}

				if($request->action == 'clear_bag')
				{
					Session::forget('ShoppingCart');
                       OmanisendRequest('removeCart');
                    $this->StoreShopCartInCookie();
					addLog("ClearBag");
					return true;
				}
				if($request->action == 'FreeSample'){
					$products_id 	= $request->products_id;
					$CartArr  = array();
					$FreeSampleMsg = $this->FreeSampleInsertProductValue($products_id);
					if(trim($FreeSampleMsg) == "" )
					{
						$Msg = "Free Sample Product Added Successfully";
						Session::flash('CartSuccess',$Msg);
					}else{
						$Msg = $FreeSampleMsg;
						Session::flash('CartError',$Msg);
					}
					$this->SetupCart();
					$log['message'] = $Msg;
					addLog("FreeSampleMessage",$log);
					return response()->json(['message' => $Msg]);
				}
				if($request->action == 'FreeGift')
				{
					$this->removeSampleItemsFromCart();
					$freeproductsid = $request->freeproductsid;
					$products_id 	= $request->products_id;

					$CartArr  = array();
					if(Session::has('ShoppingCart.Cart'))
					{
						$CartArr  = Session::get('ShoppingCart.Cart');
						$ProdIDVal = array_column($CartArr, 'ProductID');
						$isFreeGiftsVal = array_column($CartArr, 'IS_Free_Gift');

						if(in_array($products_id, $ProdIDVal) && in_array("Yes", $isFreeGiftsVal))
						{
							$Msg = "Same free gift product already added";
							Session::flash('CartError',$Msg);
							$log['message'] = $Msg;
							addLog("SameFreeGift",$log);
							return response()->json(['message' => $Msg]);
						}
					}

					$FreeGiftMsg = $this->FreeGiftInsertProductValue($products_id,$freeproductsid);
					if(trim($FreeGiftMsg) == "")
					{
						$Msg = "Free Gift Product Added Successfully";
						Session::flash('CartSuccess',$Msg);
					}else{
						$Msg = $FreeGiftMsg;
						Session::flash('CartError',$Msg);
					}
					$this->SetupCart();
					$log['message'] = $Msg;
					addLog("FreeGiftMessage",$log);
					return response()->json(['message' => $Msg]);
				}
				if($request->action == 'apply_free_gift')
				{
					if(isset($request->GiftValue) && $request->GiftValue != '' )
					{
						if(strtolower(trim($request->GiftMessage ?? ''))=="*gift message")
						{
							$request->GiftMessage = '';
						}
						return $this->ApplyFreeGift($request->GiftValue,$request->GiftFrom,$request->GiftTo,$request->GiftMessage);
					}
					else
					{
						$log['message'] = 'Error';
						addLog("ApplyFreeGiftErrorMessage",$log);
						return response()->json(['success' => '0','message' => 'Error']);
					}
				}
			}
		}
	}

	public function RemoveFromCart($CartID)
	{
		$ShoppingCart = Session::get('ShoppingCart.Cart');
		if(isset($CartID)){
			$log['CartID'] = $CartID;
			addLog('RemoveFromCartStart',$log);
		} else {
			addLog('RemoveFromCartStart');
		}
		if($CartID != '' && $ShoppingCart != null && count($ShoppingCart) > 0)
		{
            $IsYotpoFreeProduct = 'No';
            $GA4 = "";

			if(isset($ShoppingCart[$CartID]["ProductID"]) && $ShoppingCart[$CartID]["ProductID"] > 0)
			{
				$GA4 = googleAnalyticsGA4("RemoveFromShoppingcart",$ShoppingCart[$CartID]);
			}
			if(isset($ShoppingCart[$CartID]['IsYotpoFreeProduct']) && $ShoppingCart[$CartID]['IsYotpoFreeProduct'] == 'Yes')
			{
				$IsYotpoFreeProduct = 'Yes';
				Session::forget('ShoppingCart.YotpoFreeGiftCoupon');
			}
			unset($ShoppingCart[$CartID]);

			$ShoppingCart = array_values($ShoppingCart);
            if(count($ShoppingCart) == 1 && isset($ShoppingCart[0]['IsYotpoFreeProduct']) && $ShoppingCart[0]['IsYotpoFreeProduct'] == 'Yes')
            {
                $ShoppingCart = [];
            }
            Session::put('GA4RemoveCart',$GA4);
			Session::put('ShoppingCart.Cart',$ShoppingCart);
			$this->CalculateSubTotal();
			$this->SetupCart();
			$Msg = "Item removed successfully.";
            /** OMANISEND **/
            OmanisendRequest('setCart',['CartData' => Session::get('ShoppingCart')]);
			addLog('RemoveFromCart');
            /** OMANISEND **/
			return response()->json(['message' => $Msg,'IsYotpoFreeProduct' => $IsYotpoFreeProduct]);
		}
	}

	public function ShoppingcartPage(Request $Request)
	{
		addLog('ShoppingcartPageStart');
		if (Auth::guard('store')->check()) {
			Session::forget('ShoppingCart.OrderID');
			Session::forget('ShoppingCart.StoreOrderID');
			Session::forget('ShoppingCart.StoreCashSplitPaymentId');
			Session::forget('ShoppingCart.WebsiteCashSplitPaymentId');
			Session::forget('ShoppingCart.StoreCashPaymentId');
			Session::forget('ShoppingCart.WebsiteOrderID');
		}
		$myFile = env('LOG_BASE_PATH') .'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$stringData = '';
			$fh = fopen($myFile, 'a+');
			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate ShoppingcartPage Session Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
			}
			if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Place order start Session User Email : ".Session::get('sess_useremail')." :\n";
			}
			fwrite($fh, $stringData);
			fclose($fh);
		}
	  // echo "<pre>"; print_r(Session::get('ShoppingCart.Cart')); exit;
		$skrSKU= $this->OutOfStockItemsRemove();

		if(count($skrSKU) > 0)
		{
			$err_msg ="Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			Session::flash('PlaceOrderError',$err_msg);
			$log['err_msg'] = $err_msg;
			addLog('ShoppingcartPage',$log);
			//return redirect('/shoppingcart');
		}

		$allCartItems = '';

		$IsGiftCertificateItem = '';

		$this->PageData['CSSFILES'] = ['slick.css','shoppingcart.css'];
        $this->PageData['JSFILES'] = ['slick.js','shoppingcart_page.js'];
		$this->PageData['meta_title'] = "Shopping Bag :: ".config('Settings.SITE_TITLE');
		if(config('global.SHOPP_STATUS') == 'Close')
		{
			Session::forget('ShoppingCart');
			return redirect('/');
		}

		if(Session::has('ShoppingCart.GiftCoupon.Code') && Session::get('ShoppingCart.GiftCoupon.Code') !='' )
		{
			Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value', 0.0);
			if(config('Settings.GIFTCERTIFICATEFLAG')=="Yes")
		    {
				$this->ApplyGiftCoupons(Session::get('ShoppingCart.GiftCoupon.Code'));
			}
		}

		if(isset($Request->method) && $Request->method == 'view')
		{
			Session::forget('StripePaymentType');
			Session::forget('PayMethodRes');
			Session::forget('PayPalToken');
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');
			Session::forget("ShoppingCart.apple_google_paymentintentid");
			addLog('ShoppingcartPageView');
			$this->SetShippingInsuranceCharge('remove');
		}

		$this->SetupCart();

		//OmanisendRequest('62ebc221684de40017a5a922',[]);

		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$CartInfo = Session::get('ShoppingCart.Cart');
			/*if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
				{
				 echo "<pre>"; print_r($CartInfo); exit;

				}
				*/
			$SKUINCART = [];
			/* foreach($CartInfo as $Cart)
				$SKUINCART[]=$Cart['SKU']; */
				foreach($CartInfo as $Cart){

					$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$Cart);
					$Cart['IsGiftCertificateItem'] = $IsGiftCertificateItem;

					$allCartItems .= $Cart['SKU'].",";
					$SKUINCART[]=$Cart['SKU'];
				}
				$allCartItems = rtrim($allCartItems,",");

			$Filters['NotProductSKUs']= $SKUINCART;
			//$RecentViewProducts = $this->GetProducts('ShoppingCart','',12,$Filters);
			$RecentViewProducts['Products'] = [];
			$this->PageData['RecentViewProducts'] = $RecentViewProducts['Products'];
			$this->PageData['RecentViewAttr'] = [
				'Title' => 'We think you’ll also love',
				'Slider' => 'products-slider',
				'SeeMore' => '',
			];
			$this->constructfunc_afterpaydetails();
			$this->PageData['token_js_url'] = $this->Token_JS_Url;
			$GA4 = googleAnalyticsGA4("ViewCartPage",$CartInfo,$this->GetNetTotal());
			$this->PageData['GA4'] = $GA4;
		}
		$GTMDATA = ['page' => 'shoppingcart', 'pagetype' => 'cart'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		$this->PageData['body_class'] = 'cart-body';
		$this->PageData['allCartItems'] = $allCartItems;
		$this->PageData['skrSKU'] = $skrSKU;
		$this->PageData['CanonicalURL'] = config('global.SITE_URL') ."shoppingcart";

		if(Session::has('ShoppingCart'))
		{
			$ShoppingCart = Session::get('ShoppingCart');
			$this->PageData['CartDetails'] = $ShoppingCart;
		}
		//dd(Session::get('ShoppingCart'));
		return view('shoppingcart.cart')->with($this->PageData);
	}

	public function SetupCart($pageFrom='')
	{

		addLog('SetupCartStart');
		$AllCharges = $AllDiscounts = $CreditDiscount = 0;
		$TotalFreeSampleItems = 0;
		$totalAllowSampleProducts = 0;
		$totalAllowSampleCustomerChoice = 0;

		if($pageFrom != 'add_to_cart' && $pageFrom !='update_side_cart')
		{
			if(!Session::has('eusertype') || (Session::has('eusertype') && strtolower(trim(Session::has('eusertype') ?? '') != 'wholesaler')))
			{

				if(config('Settings.AUTODISCOUNTFLAG')=="Yes")
				{
					$this->ApplyAutoDiscount();
				}
				if(config('Settings.QUANTITYDISCOUNTFLAG')=="Yes")
				{
					$this->ApplyQuantityDiscount();
				}
			}

			if(Session::has('Niche_Fragrances_Membership') && Session::get('Niche_Fragrances_Membership') == 'Yes')
			{
				$cname = trim(config('Settings.NICHEFRAGRANCESCODE'));
				$niche_res = Coupon::select('coupon_number')->where('coupon_number','=',$cname)->limit(1)->get();
				if($niche_res && $niche_res->count() > 0)
				{
					$this->ApplyCouponDiscount($cname,Session::get('sess_icustomerid'));
				}
			}
			if(config('Settings.BOGODISCOUNTFLAG')=="Yes")
			{
				$this->ApplyDogoDiscount();
			}

			if(config('global.BOGO_QTY__AUTO_COMBINED') == '1')
			{
				$BoGoDiscount = $this->GetAllDiscounts('DogoDiscount');
				if(!empty($BoGoDiscount) && $BoGoDiscount > 0)
				{
					Session::put('ShoppingCart.AutoDiscount',0);
					Session::put('ShoppingCart.QuantityDiscount',0);

				}

			}

			//Session::forget('ShoppingCart.Reward_array');
			//$this->ApplyAutoRewardDiscount();

			if(Session::has('ShoppingCart.credit_limit_discount') && Session::get('ShoppingCart.credit_limit_discount') > 0)
			{
				$this->ApplyCreditDiscount(1);
			}

			$CartAttr = $this->SetCartAttributes();
			$this->PageData['CartAttr'] = $CartAttr;
			$this->ApplyGiftWrapping();

			if(Session::has('ShoppingCart.BillingAddress') && isset($CartAttr['onlyGCPurchased']) && $CartAttr['onlyGCPurchased']!= 1 )
			{
				if(Session::has('ShoppingCart.BillingAsShipping') && Session::get('ShoppingCart.BillingAsShipping') == "No")
				{
					$Shipping = Session::get('ShoppingCart.ShippingAddress');
					$ship_country = $Shipping["country"];
					$ship_zip 	  = $Shipping["zip"];
					$ship_state	  = $Shipping["state"];
					$ship_address1 = trim($Shipping["address1"]);
					$ship_address2 = trim($Shipping["address2"]);
					$ship_city 	   = trim($Shipping["city"]);
				}
				else
				{
					$Billing = Session::get('ShoppingCart.BillingAddress');
					$ship_country = $Billing["country"];
					$ship_zip 	  = $Billing["zip"];
					$ship_state	  = $Billing["state"];
					$ship_address1 = trim($Billing["address1"]);
					$ship_address2 = trim($Billing["address2"]);
					$ship_city 	   = trim($Billing["city"]);
				}

				$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$CartAttr['onlyGCPurchased'],'',$ship_city);
			}

			if($CartAttr['onlyGCPurchased'] == 1)
			{
				Session::forget('ShoppingCart.Shipping');
				Session::forget('ShoppingCart.Tax');
				Session::forget('ShoppingCart.GiftWrapping');
				Session::forget('ShoppingCart.ShippingSignature');
				Session::forget('shipping_insurance_charge');
				Session::forget('ShoppingCart.ShippingSignature');
			}

			$AllCharges = $this->GetAllCharges();
			$this->PageData['AllCharges'] = $AllCharges['Charges'];
			$AllDiscounts = $this->GetAllDiscounts();
			$this->PageData['AllDiscounts'] = $AllDiscounts['Discounts'];
			$CreditDiscount = (isset($AllDiscounts['Discounts']['CreditLimitDiscount']['discount']) ? $AllDiscounts['Discounts']['CreditLimitDiscount']['discount']:0);
			$CreditLimit = (isset($CartAttr["CreditLimit"])?$CartAttr["CreditLimit"]:0);
			$this->PageData['CreditDiscount'] = $CreditDiscount;

			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}

			if($normaluser && ($CreditLimit <= 0 || config('Settings.WHOLESALE_CREDIT_LIMIT') != 'Yes' || Session::get("etype") != "M" || $normaluser->is_dropshipper == "Yes") && $CreditDiscount > 0)
			{
				Session::put('ShoppingCart.credit_limit_discount',0);
				Session::put('ShoppingCart.customer_remaining_credit_amount',0);
			}

			if(Session::has('ShoppingCart.GiftCoupon.Code') && Session::get('ShoppingCart.GiftCoupon.Code') !='' )
			{
				Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
				Session::put('ShoppingCart.GiftCoupon.Applicable_Value', 0.0);
				if(config('Settings.GIFTCERTIFICATEFLAG')=="Yes")
				{
					$this->ApplyGiftCoupons(Session::get('ShoppingCart.GiftCoupon.Code'));
				}
			}

			$AllDiscounts = $this->GetAllDiscounts();
			$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];

			if(
				config('Settings.FREEGIFTFLAG') == "Yes" &&
				(
					!Session::has('eusertype') ||
					strtolower(trim(Session::get('eusertype'))) != 'wholesaler'
				) &&
				!Auth::guard('store')->check()
			)

			{

				$TotalFreeGiftItems = $this->GetTotalItemsOfFreegift();
				$this->PageData['TotalFreeGiftItems'] = $TotalFreeGiftItems;
				$FreeGiftProductId  = $this->GetFreegiftId();
				$Gift_Free_In_Cart = $this->CheckFreeGiftInCart($TotalValue,$TotalFreeGiftItems,$FreeGiftProductId);
				$this->PageData['allFreeGiftsInCart'] = $Gift_Free_In_Cart;

				if($Gift_Free_In_Cart == 'No')
				{
					$this->SetFreegift($Gift_Free_In_Cart);
					//$freeSampleInCartArr = array_column(Session::get('ShoppingCart.Cart'), 'Is_Free_Sample');
					$freeSampleInCartArr = array_column(Session::get('ShoppingCart.Cart') ?? [], 'Is_Free_Sample');

					if (in_array('Yes', $freeSampleInCartArr)) {
						//$TotalFreeGiftItems = $this->GetTotalItemsOfFreegift();
						//$FreeGiftProductId  = $this->GetFreegiftId();
						//$Gift_Free_In_Cart = $this->CheckFreeGiftInCart($TotalValue,$TotalFreeGiftItems,$FreeGiftProductId);

						$Free_Gift_Res = $this->GetFreeCouponPopup($TotalValue,$TotalFreeGiftItems,$FreeGiftProductId);
						if(isset($Free_Gift_Res) && $Free_Gift_Res != '' && count($Free_Gift_Res) > 1)
						{
							$this->removeSampleItemsFromCart();
						}
					}
					/*
					if(strtolower(trim(Session::get('eusertype'))) !="wholesaler" && trim(Session::get('is_dropshipper')) != "Yes" && $Gift_Free_In_Cart == "No")
					{
						$Free_Gift_Res = $this->GetFreeCouponPopup($this->GetNetTotal());
						if(count($Free_Gift_Res) == 1)
						{
							$products_id = $Free_Gift_Res[0]['products_id'];
							$freeproductsid = $Free_Gift_Res[0]['free_gift_products_id'];
							$this->FreeGiftInsertProductValue($products_id,$freeproductsid);
							$Msg = "Free Gift Product Added Successfully";
							Session::flash('CartSuccess',$Msg);
						}
					}	*/
				}
			}

			if(config('Settings.FREESAMPLE_VALUE')=="Yes" && (!Session::has('eusertype') || (Session::has('eusertype') && strtolower(trim(Session::has('eusertype') ?? '') != 'wholesaler'))) && !Auth::guard('store')->check())
			{
				$TotalFreeSampleItems = $this->GetTotalItemsOfFreeSampleInCart();
				$sampleProductsPopupArr = $this->getSampleProductsPopup($TotalValue,$TotalFreeSampleItems);
				$totalAllowSampleCustomerChoice = $this->getSampleProductsCustomerChoice($TotalValue);
				$totalAllowSampleProducts = count($sampleProductsPopupArr ?? []);
				if($TotalValue == 0){
					$this->removeSampleItemsFromCart();
				}
				if($TotalFreeSampleItems > 0 && empty($sampleProductsPopupArr)){
					$this->removeSampleItemsFromCart();
					//$this->RemoveFreeSampleCache();
				}

				//echo json_encode($sampleProductsPopupArr);

				// $TotalFreeSampleItems = $this->GetTotalItemsOfFreeSampleInCart();
				// $this->PageData['TotalFreeSampleItems'] = $TotalFreeSampleItems;

			}
		}
		$this->PageData['TotalFreeSampleItems'] = $TotalFreeSampleItems;
		$this->PageData['totalAllowSampleProducts'] = $totalAllowSampleProducts;
		$this->PageData['totalAllowSampleCustomerChoice'] = $totalAllowSampleCustomerChoice;

		$freeSampleInCart = "No";
		$freeGiftInCart = "No";
		if(Session::has('ShoppingCart.Cart'))
		{
			$freeSampleInCartArr = array_column(Session::get('ShoppingCart.Cart'), 'Is_Free_Sample');
			if (in_array('Yes', $freeSampleInCartArr)) {
				$freeSampleInCart = "Yes";
			}
		}
		$this->PageData['freeSampleInCart'] = $freeSampleInCart;

		if(Session::has('ShoppingCart.Cart'))
		{
			$freeGiftInCartArr = array_column(Session::get('ShoppingCart.Cart'), 'IS_Free_Gift');
			if (in_array('Yes', $freeGiftInCartArr)) {
				$freeGiftInCart = "Yes";
			}
		}
		$this->PageData['freeGiftInCart'] = $freeGiftInCart;

		$this->PageData['NetTotal'] = $this->GetNetTotal();
		$this->StoreShopCartInCookie();
		addLog('SetupCartEnd');

	}
	/*
    public function GetCartHTML(Request $request)
	{
		if($request->ajax())
		{
			$ShoppingCart = [];
			$TotalItemInCart = 0;
			if(Session::has('ShoppingCart'))
			{
				$ShoppingCart = Session::get('ShoppingCart');
				if(isset($ShoppingCart['Cart']) && count($ShoppingCart['Cart']) > 0)
					$TotalItemInCart = $ShoppingCart['TotalItemInCart'];
			}

			$CartAttr = $this->SetCartAttributes();
			$ShoppingCart['IsPaypalExpressCheckout'] = $CartAttr['IsPaypalExpressCheckout'];
			$ShoppingCart['Amazon_pay_Checkout'] = $CartAttr['Amazon_pay_Checkout'];
			$ShoppingCart['Afterpay_Checkout'] = $CartAttr['Afterpay_Checkout'];
			$this->PageData['CartDetails'] = $ShoppingCart;
			$this->SetAmazonConfig();
			$this->StoreShopCartInCookie();
			$MerchantID = config('MERCHANT_ID');
			$CallBackURL = config('CALLBACK_CHECKOUT_URL');
			$CartHTML = view('layouts.sidecart_ajax')->with($this->PageData)->render();
			return response()->json(array('ShoppingCart' => $CartHTML,'TotalItemInCart' => $TotalItemInCart, 'MerchantID' => $MerchantID, 'CallBackURL' => $CallBackURL));
		}
	}
    */
	public function GetCartHTML(Request $request)
	{
		if($request->ajax())
		{
			$cartpopup = $request->cartpopup;
			$ShoppingCart = [];
			$TotalItemInCart = 0;
			if(Session::has('ShoppingCart'))
			{
				$ShoppingCart = Session::get('ShoppingCart');
				if(isset($ShoppingCart['Cart']) && count($ShoppingCart['Cart']) > 0)
					$TotalItemInCart = $ShoppingCart['TotalItemInCart'];
			}

			$CartAttr = $this->SetCartAttributes($cartpopup);
			//$ShoppingCart['IsPaypalExpressCheckout'] = $CartAttr['IsPaypalExpressCheckout'];
			//$ShoppingCart['Amazon_pay_Checkout'] = $CartAttr['Amazon_pay_Checkout'];
            //$ShoppingCart['Afterpay_Checkout'] = $CartAttr['Afterpay_Checkout'];
			$this->PageData['CartDetails'] = $ShoppingCart;
			if(Session::has('GACode')) {
				$this->PageData['GA4']	= Session::get("GACode");
				Session::forget('GACode');
			}
			if(Session::has('GA4RemoveCart')) {
				$this->PageData['GA4RemoveCartCode']	= Session::get("GA4RemoveCart");
				Session::forget('GA4RemoveCart');
			}

			//$this->SetAmazonConfig();
			$this->StoreShopCartInCookie();
			//$MerchantID = config('MERCHANT_ID');
			//$CallBackURL = config('CALLBACK_CHECKOUT_URL');
			$CartHTML = view('layouts.sidecart_ajax')->with($this->PageData)->render();
			//return response()->json(array('ShoppingCart' => $CartHTML,'TotalItemInCart' => $TotalItemInCart, 'MerchantID' => $MerchantID, 'CallBackURL' => $CallBackURL));
            return response()->json(array('ShoppingCart' => $CartHTML,'TotalItemInCart' => $TotalItemInCart));
		}
	}

	public function UpdateCart($products_id,$prodqty,$giftwrap='No',$ListingPage='Y')
	{

		$data = Session::get('ShoppingCart');

		$Arrindex = array_search($products_id, array_column($data['Cart'], 'ProductID'));

		$orderType = $Arrindex !== false ? $data['Cart'][$Arrindex]['OrderType'] : null;

		$ProductChkStock = $this->ProductCheckInStock($products_id, $prodqty, "update", '', $orderType);

		$u_type = "retailer";
		if(Session::has('eusertype') && Session::get('eusertype')!=''){
			$u_type = strtolower(Session::get('eusertype'));
		}

		$maxqty_error = 0;
		$CartErrors = [];
		if(isset($ProductChkStock['StockInfo']) && $ProductChkStock['StockInfo'] == '1111'){
			$CartErrors[] =  config('message.Cart.ProductNotAvailable');
			Session::flash('CartError',config('message.Cart.ProductNotAvailable'));
		}

		// if(isset($ProductChkStock['StockInfo']) > 0 && $ProductChkStock['StockInfo'] == '2222')
		// 	$CartErrors[] = config('message.Cart.QuantityNotAvailable');

		if(isset($ProductChkStock['StockInfo']) > 0 && $ProductChkStock['StockInfo'] == '2222'){
			if(isset($ProductChkStock['availableStock']) && $ProductChkStock['availableStock']!=''){
				if($u_type == 'retailer' && $ProductChkStock['availableStock'] > 20){
					$maxqty_error = 1;
					$CartErrors[] = "The maximum quantity you can add is 20 pieces.";//config('message.Cart.QuantityNotAvailable');
					Session::flash('CartError',"The maximum quantity you can add is 20 pieces.");
				} else {
					$maxqty_error = 1;
					Session::flash('CartError',"The maximum quantity you can add is ".$ProductChkStock['availableStock']." pieces.");
					$CartErrors[] = "The maximum quantity you can add is ".$ProductChkStock['availableStock']." pieces.";//config('message.Cart.QuantityNotAvailable');
				}
			}
		}

		if($u_type == 'retailer' && $prodqty > 20){
			$maxqty_error = 1;
			$CartErrors[] = "The maximum quantity you can add is 20 pieces.";
			Session::flash('CartError',"The maximum quantity you can add is 20 pieces.");
		}

		if(count($CartErrors) > 0)
		{
			$logarr['CartErrors'] = $CartErrors;
			addLog("UpdateCart",$logarr);
			Session::flash('CartErrors', $CartErrors);
			return response()->json(array('Update' => 0,'CartErrors' => $CartErrors));
		}
		$ProductChkFlg = $this->ProductCheckInCart($products_id, $prodqty, 'update','No',$giftwrap);
		$this->CalculateSubTotal();

		if($ListingPage == 'N')
		{
			$YotpoCouponCode ='';
			if(Session::has('ShoppingCart.YotpoRewardCode') && Session::get('ShoppingCart.YotpoRewardCode') != '')
			{
				$normaluser = Auth::user();
				if (Auth::guard('store')->check()) {
					$normaluser = Auth::guard('web')->user();
				}
				$CustomerID = ($normaluser?Session::get('sess_icustomerid'):null); //(Auth::user()?Session::get('sess_icustomerid'):null);
				$result = $this->ApplyCouponDiscount(Session::get('ShoppingCart.YotpoRewardCode'),$CustomerID);
			}
			$this->SetupCart();
		} else {
			$this->SetupCart('update_side_cart');
		}
         /** OMANISEND **/
        OmanisendRequest('setCart',['CartData' => Session::get('ShoppingCart')]);
		addLog("UpdateCart");
        /** OMANISEND **/
	}

	public function GetCartPartial(Request $request)
	{
		$allCartItems = '';

		$IsGiftCertificateItem = '';

		$allSectionRecommendedPrds = array();

		if($request->ajax())
		{
			$skrSKU= $this->OutOfStockItemsRemove();

			if(count($skrSKU) > 0)
			{
				$err_msg ="Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
				Session::flash('PlaceOrderError',$err_msg);
				//return redirect('/shoppingcart');
			}
			$ShoppingCart = [];
			$TotalItemInCart = 0;
			if(Session::has('ShoppingCart'))
			{
				$this->SetupCart($request->pageFrom??'');
				$ShoppingCart = Session::get('ShoppingCart');
				if(isset($ShoppingCart['Cart']) && count($ShoppingCart['Cart']) > 0){
					$TotalItemInCart = $ShoppingCart['TotalItemInCart'];

					$allSectionRecommendedPrds = $this->GetRecommendedProducts();
				}else{
					$allSectionRecommendedPrds = array();
				}
			}else{
				$allSectionRecommendedPrds = array();
			}
			if(isset($ShoppingCart['Cart']) && count($ShoppingCart['Cart']) > 0)
			{
				foreach($ShoppingCart['Cart'] as $Cart){

					$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$Cart);
					$Cart['IsGiftCertificateItem'] = $IsGiftCertificateItem;

					$allCartItems .= $Cart['SKU'].",";
					$SKUINCART[]=$Cart['SKU'];
				}
			}
			$allCartItems = rtrim($allCartItems,",");
			$this->PageData['allCartItems'] = $allCartItems;
			$this->PageData['CartDetails'] = $ShoppingCart;

			$this->PageData['allSectionRecommendedPrds'] = $allSectionRecommendedPrds;

			$this->PageData['skrSKU'] = $skrSKU;
			$NetTotal = NumberFormat($this->GetNetTotal());
			if($TotalItemInCart > 0)
			{
				$CartHTML = $SubtotalBoxHTML = $CreditCouponBoxesHtml = $SideCartHTML = "";
				if($request->pageFrom != 'add_to_cart' && $request->pageFrom !='update_side_cart')
				{
					$CartHTML = view('shoppingcart.cart_table')->with($this->PageData)->render();
					$SubtotalBoxHTML = view('shoppingcart.subtotalbox')->with($this->PageData)->render();
					$CreditCouponBoxesHtml = view('shoppingcart.creditcouponboxes')->with($this->PageData)->render();
					//Show Bogo Discount Message
					$SideCartHTML = view('layouts.sidecart_ajax')->with($this->PageData)->render();
					//Show Bogo Discount Message
				} else {
					$CartHTML = $SubtotalBoxHTML = $CreditCouponBoxesHtml = "";
					//Show Bogo Discount Message
					$SideCartHTML = view('layouts.sidecart_ajax')->with($this->PageData)->render();
					//Show Bogo Discount Message
				}
				$allSectionRecommendedPrdsHTML = view('shoppingcart.recommended')->with($this->PageData)->render();

                return response()->json(array('Cart' => $CartHTML,'SubtotalBoxHTML' => $SubtotalBoxHTML,'CreditCouponBoxesHtml' => $CreditCouponBoxesHtml, 'TotalItemInCart' => $TotalItemInCart, 'Total' => $NetTotal, 'allSectionRecommendedPrds' => $allSectionRecommendedPrdsHTML, 'SideCartHTML' => $SideCartHTML, 'cart_action' => $request->pageFrom??''));
				//return response()->json(array('Cart' => $CartHTML,'SubtotalBoxHTML' => $SubtotalBoxHTML,'CreditCouponBoxesHtml' => $CreditCouponBoxesHtml, 'TotalItemInCart' => $TotalItemInCart, 'Total' => $NetTotal, 'allSectionRecommendedPrds' => $allSectionRecommendedPrdsHTML));
			} else {
				$EmptyCartHTML = view('shoppingcart.empty')->with($this->PageData)->render();
				return response()->json(array('EmptyCartHTML' => $EmptyCartHTML,'TotalItemInCart' => $TotalItemInCart));
			}
		}
	}

	public function GetSampleProducts(Request $request){

		if(config("Settings.FREESAMPLE_VALUE")!="Yes" || Auth::guard('store')->check()){
			return;
		}
		$chkTootalFreGiftInCart = $this->GetTotalItemsOfFreegift();
		if($chkTootalFreGiftInCart > 0){
			return;
		}
		// if(config("Settings.FREESAMPLE_VALUE")!="Yes"){
		// 	return;
		// }
		$AllDiscounts = $this->GetAllDiscounts();
		$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];

		// if((Session::has('eusertype') && strtolower(trim(Session::get('eusertype') ?? '')) !="wholesaler") && trim(Session::get('is_dropshipper') ?? '') != "Yes" && $Gift_Free_In_Cart == "Yes")
		// {
		// 	return;
		// }

		if(config("Settings.FREEGIFTFLAG")=="Yes"){
			$TotalFreeGiftItems = $this->GetTotalItemsOfFreegift();
			$FreeGiftProductId  = $this->GetFreegiftId();
			$Gift_Free_In_Cart = $this->CheckFreeGiftInCart($TotalValue,$TotalFreeGiftItems,$FreeGiftProductId);
			$Free_Gift_Res = $this->GetFreeCouponPopup($TotalValue,$TotalFreeGiftItems,$FreeGiftProductId);
			$TotalValueFree = (isset($Free_Gift_Res)  &&  count($Free_Gift_Res) ==1 ) ? "Yes" : "No";
			if($Gift_Free_In_Cart == "Yes"){
				return;
			}

			if(	isset($Free_Gift_Res) && $Free_Gift_Res != '' && count($Free_Gift_Res) > 1)
			{
				return;
			}
		}
		//Log::info('TotalValue '.$TotalValue);

		$this->PageData['JSFILES'] = ['modal.js'];
		if(config('Settings.CHECKOUT_SHOIPPINGCART') == "Yes" && $TotalValue > 0){
			//Log::info('TotalValueGG '.$TotalValue);
			if(strtolower(trim(Session::get('eusertype') ?? '')) !="wholesaler" && trim(Session::get('is_dropshipper') ?? '') != "Yes")
			{
				$TotalFreeSampleItems = $this->GetTotalItemsOfFreeSampleInCart();
				$sampleProductsPopup = $this->getSampleProductsPopup($TotalValue,$TotalFreeSampleItems);
				if(isset($sampleProductsPopup[0]["customer_choice"]) &&	$TotalFreeSampleItems !== $sampleProductsPopup[0]["customer_choice"]){
					$this->PageData['TotalListItems'] = $sampleProductsPopup[0]["customer_choice"];
					$this->PageData['Free_Sample_Products'] = $sampleProductsPopup;
					return view('popup.freesample-popup')->with($this->PageData)->render();
				}
				else if(!isset($sampleProductsPopup[0]["customer_choice"])){
					$this->removeSampleItemsFromCart();
				}
				else {
					//added on 27-01-2026 - start
					if($TotalFreeSampleItems > 0 && empty($sampleProductsPopup)){
						$this->removeSampleItemsFromCart();
						//$this->RemoveFreeSampleCache();
					}
					//added on 27-01-2026 - end
				}
				/*else {
					$this->removeSampleItemsFromCart();
				}*/
			}
		} else {
			//Log::info('TotalValueQQ '.$TotalValue);
			$this->removeSampleItemsFromCart();
		}
	}

	public function GetFreeGiftProducts(Request $request)
	{
		if (Auth::guard('store')->check()) {
			return;
		}
		$this->PageData['JSFILES'] = ['modal.js'];
		$AllDiscounts = $this->GetAllDiscounts();
		$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];

		$freeSampleInCart = "No";
		if(Session::has('ShoppingCart.Cart'))
		{
			$freeSampleInCartArr = array_column(Session::get('ShoppingCart.Cart'), 'Is_Free_Sample');
			if (in_array('Yes', $freeSampleInCartArr)) {
				$freeSampleInCart = "Yes";
			}
		}

		if(config('Settings.CHECKOUT_SHOIPPINGCART') == "Yes" && $TotalValue > 0){
		//if(config('Settings.CHECKOUT_SHOIPPINGCART') == "Yes" && $TotalValue > 0 && $freeSampleInCart == "No"){
			$TotalFreeGiftItems = $this->GetTotalItemsOfFreegift();
			$FreeGiftProductId  = $this->GetFreegiftId();
			$Gift_Free_In_Cart = $this->CheckFreeGiftInCart($TotalValue,$TotalFreeGiftItems,$FreeGiftProductId);

			if(strtolower(trim(Session::get('eusertype') ?? '')) !="wholesaler" && trim(Session::get('is_dropshipper') ?? '') != "Yes" && $Gift_Free_In_Cart == "No")
			{
				$Free_Gift_Res = $this->GetFreeCouponPopup($TotalValue,$TotalFreeGiftItems,$FreeGiftProductId);
				$TotalValueFree = (isset($Free_Gift_Res)  &&  count($Free_Gift_Res) ==1 ) ? "Yes" : "No";
				Session::put('ShoppingCart.IsFreeGiftCount',$TotalValueFree);
				if(isset($Free_Gift_Res) && $Free_Gift_Res != '' && count($Free_Gift_Res) > 1)
				{

					$this->PageData['TotalListItems'] = $Free_Gift_Res[0]["freegift_add_count"];
					$this->PageData['Free_Gift_Res'] = $Free_Gift_Res;
					return view('popup.freegift-popup')->with($this->PageData)->render();
				}
			}
		}
	}

	public function SetCheckoutCommonDetails($request){
		//afterpay details start
		addLog('SetCheckoutCommonDetailsStart');
		$this->constructfunc_afterpaydetails();
		$Afterpay_Checkout = $this->Afterpay_Checkout;
		$OrderTotal = NumberFormat($this->GetNetTotal());
		$Is_Afterpay_Checkout = "No";

		if($Afterpay_Checkout == "Yes"){
			if(Session::has('ShoppingCart') && is_array(Session::get('ShoppingCart.Cart')) > 0 && !Session::has('Afterpay.Min_AP_AMT') && !Session::has('Afterpay.Max_AP_AMT'))
			{
				$ap_configs = $this->GetAfterpayMinMaxConfig();
				if(!empty($ap_configs))
				{
					Session::put('Afterpay.Min_AP_AMT',$ap_configs["Min_AP_AMT"]);
					Session::put('Afterpay.Max_AP_AMT',$ap_configs["Max_AP_AMT"]);
				}
			}

			if($OrderTotal >= Session::get('Afterpay.Min_AP_AMT') && $OrderTotal <= Session::get('Afterpay.Max_AP_AMT'))
			{
				$Is_Afterpay_Checkout = 'Yes';
			}
		}

		$this->PageData['token_js_url'] = $this->Token_JS_Url;
		$this->PageData['Is_Afterpay_Checkout'] = $Is_Afterpay_Checkout;

		// if($request->method == 'AP'){
			$afterpay_checkout_token = "";
			$show_afterpay_widget_box = "No";
			if(Session::has('ShoppingCart.AfterPay.Checkout_Token') && Session::get('ShoppingCart.AfterPay.Checkout_Token') != ""){
				$afterpay_checkout_token = Session::get('ShoppingCart.AfterPay.Checkout_Token');
				$show_afterpay_widget_box = "Yes";
			}

			if($Is_Afterpay_Checkout == "No")
			{
				// $show_afterpay_widget_box = 'No';
			}
			$this->PageData['afterpay_checkout_token'] = $afterpay_checkout_token;
			$this->PageData['show_afterpay_widget_box'] = $show_afterpay_widget_box;
		// }

		$payment_method_url = "";
		if($request->SelPayMethod == 'AP'){
			$payment_method_url = "/".$request->SelPayMethod;
		}
		if($request->SelPayMethod == 'paypal'){
			$payment_method_url = "/".$request->SelPayMethod;
		}
		// echo $payment_method_url;exit;
		$this->PageData['payment_method_url'] = $payment_method_url;
		$log['payment_method_url'] = $payment_method_url;
		addLog('SetCheckoutCommonDetails',$log);

		//afterpay details end
	}

	public function CheckCouponToRedeem(Request $request){
		$coupon_number = '';
		$coupon_discount = '';
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		if($request->ajax())
		{
			//if(Auth::user())
			if($normaluser)
			{
				//if(trim(Auth::user()->email) != ''){
				if(trim($normaluser->email) != ''){
					$check_coupon = Coupon::where('customer_email','=',trim($normaluser->email))//trim(Auth::user()->email))
										->where('status','=','1')
										->where('source','=','Yotpo')
										->where('start_date','=',DB::raw('curdate()'))->orderBy('coupon_id','desc')->take(1)->get();
					if($check_coupon->count() > 0){
						$coupon_number = $check_coupon[0]['coupon_number'];
						$coupon_discount = NumberFormat($check_coupon[0]['discount']);
					}
				}
			} else if(Auth::guard('store')->check() && Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
				if(trim(Session::get('sess_useremail')) != ''){
					$check_coupon = Coupon::where('customer_email','=',trim(Session::get('sess_useremail')))//trim(Auth::user()->email))
										->where('status','=','1')
										->where('source','=','Yotpo')
										->where('start_date','=',DB::raw('curdate()'))->orderBy('coupon_id','desc')->take(1)->get();
					if($check_coupon->count() > 0){
						$coupon_number = $check_coupon[0]['coupon_number'];
						$coupon_discount = NumberFormat($check_coupon[0]['discount']);
					}
				}
			}
			return response()->json(['coupon_number' => $coupon_number]);
		}

	}

	public function CheckoutPage(Request $request)
	{

		// if(Session::has('sess_useremail') && (Session::get('sess_useremail') == 'gequaldev@gmail.com' || Session::get('sess_useremail') == 'tempchecknew@gmail.com'))
		// {
		// 	config(['global.address_verification' => true]);
		// }

		// if(!Auth::guard('store')->check() && $request->cookie('pos_maxaroma_token') && !empty($request->cookie('pos_maxaroma_token')))
        // {
        //     return redirect('/store/login-from-cookiee');
        // }

		if(isset($request->method) && $request->method == "AP"){
            config(['global.address_verification' => false]);
        }

        if (Auth::guard('store')->check() && Session::has('ShoppingCart.OrderType') &&  Session::get('ShoppingCart.OrderType') == "Store") {
		Session::forget('shipping_insurance_charge');
		Session::forget('ShoppingCart.ShippingSignature');
		}

        if (Auth::guard('store')->check()) {
			Session::forget('ShoppingCart.OrderID');
			Session::forget('ShoppingCart.StoreOrderID');
			Session::forget('ShoppingCart.StoreCashSplitPaymentId');
			Session::forget('ShoppingCart.WebsiteCashSplitPaymentId');
			Session::forget('ShoppingCart.StoreCashPaymentId');
			Session::forget('ShoppingCart.WebsiteOrderID');
		}

		addLog('CheckoutPageStart');
		$myFile =env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$stringData = '';
			$fh = fopen($myFile, 'a+');
			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Checkoutpage Session Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
			}
			if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Place order start Session User Email : ".Session::get('sess_useremail')." :\n";
			}
			fwrite($fh, $stringData);
			fclose($fh);
		}

		$skrSKU= $this->OutOfStockItemsRemove();

		if(count($skrSKU) > 0)
		{
			$err_msg = "Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			$log['err_msg'] = $err_msg;
			addLog('CheckoutPage',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/shoppingcart');
		}
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];
		//$this->PageData['JSFILES'] = ['afterpay.js','billing.js','login.js','login_validate.js','poscheckout.js'];
		$this->PageData['JSFILES'] = ['afterpay.js','jquery-ui1.12.1.js','billing.js','login.js','login_validate.js','poscheckout.js'];

		if(Session::has('ShoppingCart.GiftCoupon.Code') && Session::get('ShoppingCart.GiftCoupon.Code') !='' )
		{
			Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value', 0.0);
			if(config('Settings.GIFTCERTIFICATEFLAG')=="Yes")
			{
				$this->ApplyGiftCoupons(Session::get('ShoppingCart.GiftCoupon.Code'));
			}
		}

		if(isset($request->method) && $request->method == "userecommendedaddress"){
			$fname = '';
			$lname = '';
			$email = '';
			$company = '';
			$phone = '';
			$lname = '';

			$googleAddreSuggestion = array();
			$retAddress = array();

			if(isset($request->fname) && $request->fname != ""){
				$fname = $request->fname;
			}
			if(isset($request->lname) && $request->lname != ""){
				$lname = $request->lname;
			}
			if(isset($request->email) && $request->email != ""){
				$email =  $request->email;
			}
			if(isset($request->company) && $request->company != ""){
				$company = $request->company;
			}
			if(isset($request->phone) && $request->phone != ""){
				$phone = $request->phone;
			}
			Session::put('ShoppingCart.GoogleSuggestedAddress.userecommendedaddress', 'Y');
			if(isset($request->shippingSameAsBill) && $request->shippingSameAsBill == "Y"){
				Session::put('ShoppingCart.BillingAsShipping','Yes');
			} else {
				Session::put('ShoppingCart.BillingAsShipping','No');
			}
			if(Session::has('ShoppingCart.GoogleSuggestedAddress.USPS') && Session::get('ShoppingCart.GoogleSuggestedAddress.USPS') !=''){
				$googleAddreUSPSSuggestion = Session::get('ShoppingCart.GoogleSuggestedAddress.USPS');

				if(Session::has('ShoppingCart.BillingAsShipping') && Session::get('ShoppingCart.BillingAsShipping') == 'Yes'){
					Session::put('ShoppingCart.BillingAddress.first_name',$fname);
					Session::put('ShoppingCart.BillingAddress.last_name',$lname);
					Session::put('ShoppingCart.BillingAddress.address1','');
					Session::put('ShoppingCart.BillingAddress.address2','');
					Session::put('ShoppingCart.BillingAddress.city','');
					Session::put('ShoppingCart.BillingAddress.zip','');
					Session::put('ShoppingCart.BillingAddress.state','');
					Session::put('ShoppingCart.BillingAddress.country','');
					Session::put('ShoppingCart.BillingAddress.email',$email);
					Session::put('ShoppingCart.BillingAddress.company',$company);
					Session::put('ShoppingCart.BillingAddress.phone',$phone);

					if(isset($googleAddreUSPSSuggestion['firstAddressLine']) && trim($googleAddreUSPSSuggestion['firstAddressLine'])!=''){
						$retAddress['Billing']['address1'] = $googleAddreUSPSSuggestion['firstAddressLine'];
						Session::put('ShoppingCart.BillingAddress.address1', $googleAddreUSPSSuggestion['firstAddressLine']);
					}

					// if(isset($googleAddreSuggestion['address']['postalAddress']['addressLines'][1]) && trim($googleAddreSuggestion['address']['postalAddress']['addressLines'][1])!=''){
					// 	$retAddress['Billing']['address2'] = $googleAddreSuggestion['address']['postalAddress']['addressLines'][1];
					// 	Session::put('ShoppingCart.BillingAddress.address2', $googleAddreSuggestion['address']['postalAddress']['addressLines'][1]);
					// }

					if(isset($googleAddreUSPSSuggestion['city']) && trim($googleAddreUSPSSuggestion['city'])!=''){
						$retAddress['Billing']['city'] = $googleAddreUSPSSuggestion['city'];
						Session::put('ShoppingCart.BillingAddress.city', $googleAddreUSPSSuggestion['city']);
					}

					if(isset($googleAddreUSPSSuggestion['zipCode']) && trim($googleAddreUSPSSuggestion['zipCode'])!=''){
						$retAddress['Billing']['zip'] = $googleAddreUSPSSuggestion['zipCode'];
						Session::put('ShoppingCart.BillingAddress.zip', $googleAddreUSPSSuggestion['zipCode']);
					}

					if(isset($googleAddreUSPSSuggestion['state']) && trim($googleAddreUSPSSuggestion['state'])!=''){
						$retAddress['Billing']['state'] = $googleAddreUSPSSuggestion['state'];
						Session::put('ShoppingCart.BillingAddress.state', $googleAddreUSPSSuggestion['state']);
					}

					if(isset($googleAddreSuggestion['address']['postalAddress']['regionCode']) && trim($googleAddreSuggestion['address']['postalAddress']['regionCode'])!=''){
						$retAddress['Billing']['country'] = $googleAddreSuggestion['address']['postalAddress']['regionCode'];
						Session::put('ShoppingCart.BillingAddress.country', $googleAddreSuggestion['address']['postalAddress']['regionCode']);
					}
				} else {

					Session::put('ShoppingCart.ShippingAddress.first_name',$fname);
					Session::put('ShoppingCart.ShippingAddress.last_name',$lname);
					Session::put('ShoppingCart.ShippingAddress.company',$company);
					Session::put('ShoppingCart.ShippingAddress.phone',$phone);
					Session::put('ShoppingCart.ShippingAddress.email',$email);

					Session::put('ShoppingCart.ShippingAddress.address1','');
					Session::put('ShoppingCart.ShippingAddress.address2','');
					Session::put('ShoppingCart.ShippingAddress.city','');
					Session::put('ShoppingCart.ShippingAddress.zip','');
					Session::put('ShoppingCart.ShippingAddress.state','');
					Session::put('ShoppingCart.ShippingAddress.country','');

					if(isset($googleAddreUSPSSuggestion['firstAddressLine']) && trim($googleAddreUSPSSuggestion['firstAddressLine'])!=''){
						$retAddress['Shipping']['address1'] = $googleAddreUSPSSuggestion['firstAddressLine'];
						Session::put('ShoppingCart.ShippingAddress.address1', $googleAddreUSPSSuggestion['firstAddressLine']);
					}

					/*if(isset($googleAddreSuggestion['address']['postalAddress']['addressLines'][1]) && trim($googleAddreSuggestion['address']['postalAddress']['addressLines'][1])!=''){
						$retAddress['Shipping']['address2'] = $googleAddreSuggestion['address']['postalAddress']['addressLines'][1];
						Session::put('ShoppingCart.ShippingAddress.address2', $googleAddreSuggestion['address']['postalAddress']['addressLines'][1]);
					}*/

					if(isset($googleAddreUSPSSuggestion['city']) && trim($googleAddreUSPSSuggestion['city'])!=''){
						$retAddress['Shipping']['city'] = $googleAddreUSPSSuggestion['city'];
						Session::put('ShoppingCart.ShippingAddress.city', $googleAddreUSPSSuggestion['city']);
					}

					if(isset($googleAddreUSPSSuggestion['zipCode']) && trim($googleAddreUSPSSuggestion['zipCode'])!=''){
						$retAddress['Shipping']['zip'] = $googleAddreUSPSSuggestion['zipCode'];
						Session::put('ShoppingCart.ShippingAddress.zip', $googleAddreUSPSSuggestion['zipCode']);
					}

					if(isset($googleAddreUSPSSuggestion['state']) && trim($googleAddreUSPSSuggestion['state'])!=''){
						$retAddress['Shipping']['state'] = $googleAddreUSPSSuggestion['state'];
						Session::put('ShoppingCart.ShippingAddress.state',$googleAddreUSPSSuggestion['state']);
					}

					if(isset($googleAddreSuggestion['address']['postalAddress']['regionCode']) && trim($googleAddreSuggestion['address']['postalAddress']['regionCode'])!=''){
						$retAddress['Shipping']['country'] = $googleAddreSuggestion['address']['postalAddress']['regionCode'];
						Session::put('ShoppingCart.ShippingAddress.country', $googleAddreSuggestion['address']['postalAddress']['regionCode']);
					}
				}
			}
			$retAddress['suggested'] = Session::get('ShoppingCart.GoogleSuggestedAddress');
			return json_encode($retAddress);
		}

		if(isset($request->method) && $request->method == "verifygoogleaddress"){
			//$arr = array($request->method);
			$verifyShipping = array();
			if(isset($request->address1) && trim($request->address1)!=''){
				$verifyShipping['address1'] = trim($request->address1);
			}
			if(isset($request->address2) && trim($request->address2)!=''){
				$verifyShipping['address2'] = trim($request->address2);
			}
			if(isset($request->city) && trim($request->city)!=''){
				$verifyShipping['city'] = trim($request->city);
			}
			if(isset($request->state) && trim($request->state)!=''){
				$verifyShipping['state'] = trim($request->state);
			}
			if(isset($request->zip) && trim($request->zip)!=''){
				$verifyShipping['zip'] = trim($request->zip);
			}
			if(isset($request->country) && trim($request->country)!=''){
				$verifyShipping['country'] = trim($request->country);
			}
			return json_encode($this->GoogleAddressValidate($verifyShipping));
			//return json_encode($verifyShipping);
		}

		if($request->method=="pa")
		{
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');
			//$request->method = '';
		}

		if(!Session::has('ShoppingCart.Cart') || count(Session::get('ShoppingCart.Cart')) == 0)
			return redirect('/shoppingcart');

		$GA4 = googleAnalyticsGA4("BeginCheckout",Session::get('ShoppingCart.Cart'),$this->GetNetTotal(),$this->GetAllCoupons('CouponCode'));
		$this->PageData['GA4'] = $GA4;

		$GALogin4= "";
		if(Session::has('MyAccountGA4') && Session::get('MyAccountGA4')!='')
		{
		$GALogin4= googleAnalyticsGA4("LoginPage");
		Session::forget("MyAccountGA4");
		}
		$this->PageData['GALogin4'] = $GALogin4;

		if($this->Is_WholeSaler_Allow() == false)
		{
			return redirect('/shoppingcart');
		}
		$this->PageData['meta_title'] = "Billing and Shipping Information :: ".config('Settings.SITE_TITLE');
		$this->PageData['Countries'] = GetCountries();
		$this->PageData['States'] = GetStates();
		$this->PageData['SelPayMethod'] = (isset($request->method)?$request->method:'');
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		 }
		$Billing = Session::get('ShoppingCart.BillingAddress', []);
		$Shipping = Session::get('ShoppingCart.ShippingAddress', []);

		//if(Auth::user())
		if($normaluser)
		{

			$Billing = [];
			$Billing['first_name'] = '';
			$Billing['last_name']= '';
			$Billing['company']= '';
			$Billing['address1']= '';
			$Billing['address2']= '';
			$Billing['city']= '';
			$Billing['zip']= '';
			$Billing['state']= '';
			$Billing['country']= 'US';
			$Billing['phone']= '';
			$Billing['email']= '';
			$Billing['confirm_email']= '';
			$Shipping=[];
			$Shipping['first_name'] 		= '';
			$Shipping['last_name']  		= '';
			$Shipping['company']    		= '';
			$Shipping['address1']   		= '';
			$Shipping['address2']   		= '';
			$Shipping['city'] 	   		= '';
			$Shipping['zip'] 	   		= '';
			$Shipping['state'] 	   		= '';
			$Shipping['country']    	= 'US';
			$Shipping['phone'] 	   		= '';
			$Shipping['email'] 	   		= '';
			$Shipping['confirm_email'] 	= '';

			$check_news = NewsLetter::select('news_letter_id')->where('email','=',trim($normaluser->email))->first(); //trim(Auth::user()->email))->get();
			$this->PageData['ISNewsletter'] = "Yes";
			if ($check_news)
			{
				$this->PageData['ISNewsletter'] = "No";
			}

			if(Session::has('ShoppingCart.BillingAddress') && count(Session::get('ShoppingCart.BillingAddress')) > 0 )
			{

				$Billing = Session::get('ShoppingCart.BillingAddress');
			} else {
				$normaluser = Auth::user();
				if (Auth::guard('store')->check()) {
					$normaluser = Auth::guard('web')->user();
				}
				$Billing['first_name'] 		= $normaluser->first_name; //Auth::user()->first_name;
				$Billing['last_name']  		= $normaluser->last_name;  //Auth::user()->last_name;
				$Billing['company']    		= $normaluser->company_name;  //Auth::user()->company_name;
				$Billing['address1']   		= $normaluser->address1;  //Auth::user()->address1;
				$Billing['address2']   		= $normaluser->address2; //Auth::user()->address2;
				$Billing['city'] 	   		= $normaluser->city; //Auth::user()->city;
				$Billing['zip'] 	   		= $normaluser->zip; //Auth::user()->zip;
				$Billing['state'] 	   		= $normaluser->state; //Auth::user()->state;
				$Billing['country']    		= $normaluser->country; //Auth::user()->country;
				$Billing['phone'] 	   		= $normaluser->phone; //Auth::user()->phone;
				$Billing['email'] 	   		= $normaluser->email; //Auth::user()->email;
				$Billing['confirm_email'] 	= '';

			}

			if(Session::has('ShoppingCart.ShippingAddress') && count(Session::get('ShoppingCart.ShippingAddress')) > 0 )
			{
				$Shipping = Session::get('ShoppingCart.ShippingAddress');
			} else {
				$Shipping['first_name'] 		= '';
				$Shipping['last_name']  		= '';
				$Shipping['company']    		= '';
				$Shipping['address1']   		= '';
				$Shipping['address2']   		= '';
				$Shipping['city'] 	   		= '';
				$Shipping['zip'] 	   		= '';
				$Shipping['state'] 	   		= '';
				$Shipping['country']    	= 'US';
				$Shipping['phone'] 	   		= '';
				$Shipping['email'] 	   		= '';
				$Shipping['confirm_email'] 	= '';
			}

			$this->PageData['IsBillingAsShipping'] = 'No';
			if(Session::has('ShoppingCart.BillingAsShipping'))
				$this->PageData['IsBillingAsShipping'] = Session::has('ShoppingCart.BillingAsShipping');
		}

		$log['Billing'] = json_encode($Billing);
		$log['Shipping'] = json_encode($Shipping);

		$this->SetCheckoutCommonDetails($request);

		if(isset($request->method) && $request->method == 'AP')
		{

			if(Session::has('ShoppingCart.AfterPay.Customer_Details')){
				$Customer_Details = Session::get('ShoppingCart.AfterPay.Customer_Details');

				$Billing['first_name'] 		= (isset($Customer_Details['ship_fname'])?$Customer_Details['ship_fname']:'');
				$Billing['last_name']  		= (isset($Customer_Details['ship_lname'])?$Customer_Details['ship_lname']:'');
				$Billing['company']    		= "";
				$Billing['address1']   		= (isset($Customer_Details['ship_address1'])?$Customer_Details['ship_address1']:'');
				$Billing['address2']   		= (isset($Customer_Details['ship_address2'])?$Customer_Details['ship_address2']:'');
				$Billing['city'] 	   		= (isset($Customer_Details['ship_city'])?$Customer_Details['ship_city']:'');
				$Billing['zip'] 	   		= (isset($Customer_Details['ship_zip'])?$Customer_Details['ship_zip']:'');
				$Billing['state'] 	   		= (isset($Customer_Details['ship_state'])?$Customer_Details['ship_state']:'');
				$Billing['country']    		= (isset($Customer_Details['ship_country'])?$Customer_Details['ship_country']:'');
				$Billing['phone'] 	   		= (isset($Customer_Details['ship_phone'])?$Customer_Details['ship_phone']:'');
				$Billing['email'] 	   		= (isset($Customer_Details['email'])?$Customer_Details['email']:'');
				$Billing['confirm_email'] 	= '';

				$Shipping = $Billing;
				$this->PageData['IsBillingAsShipping'] = 'Yes';
			}
			$log['APBilling'] = json_encode($Billing);

			$this->PageData['AfterPayAP'] =$request->method;
			// $this->PageData['afterpay_checkout_token'] = (Session::has('ShoppingCart.AfterPay.Checkout_Token')) ? Session::get('ShoppingCart.AfterPay.Checkout_Token') : "";
		}
		//if(isset($request->method) && $request->method == 'paypal')
		if(Session::has('PayPalToken') && Session::get("PayPalToken") != '' && Session::has('ShoppingCart.BillingAddress') && count(Session::get('ShoppingCart.BillingAddress')) > 0  && Session::has('ShoppingCart.ShippingAddress') && count(Session::get('ShoppingCart.ShippingAddress')) > 0)
		{

			$Billing = Session::get('ShoppingCart.BillingAddress');
			$Shipping = Session::get('ShoppingCart.ShippingAddress');
			$this->PageData['IsBillingAsShipping'] = 'No';
			if(Session::has('ShoppingCart.BillingAsShipping'))
				$this->PageData['IsBillingAsShipping'] = Session::has('ShoppingCart.BillingAsShipping');
		}

		$this->PageData['Billing'] = $Billing;
		$this->PageData['IsBillingAsShipping'] = 'No';
		$this->PageData['Shipping'] = $Shipping;
		//$this->SetupCart();
		$this->PageData['IsGuestCheckout'] = '0';
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		if(!$normaluser && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			$this->PageData['IsGuestCheckout'] = '1';

		//$this->SetShippingInsuranceCharge('remove');
		//Session::forget('ShoppingCart.ShippingSignature');
		$this->SetShippingInsuranceCharge('add');
		$this->SetupCart();

		$ShippingInsuranceCharge = (Session::has('shipping_insurance_charge')) ? Session::get('shipping_insurance_charge'):0;
			$ShippingSignature = (Session::has('shipping_signature')) ? Session::get('shipping_signature'):0;
			$InsureAmount = $this->GetNetTotal() - $ShippingInsuranceCharge;

			if($InsureAmount > 200)
			{
				Session::forget('ShoppingCart.ShippingSignature');
			}

		$this->PageData['InsureAmount'] = $this->GetNetTotal() - $ShippingInsuranceCharge - $ShippingSignature;
		$this->SetAmazonConfig('billing');
		$this->PageData['PageMethod'] = (isset($request->method)?$request->method:'billing');
		$GTMDATA = ['page' => 'billing', 'pagetype' => 'cart'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(!Auth::user()){
		if(!$normaluser){
			$show_billing_info_div = "display:none";
			$show_guest_checkout_div = "";
			$show_have_acc_div = "";
			$show_other_payments_checkout = "";
		}else{
			$show_billing_info_div = "";
			$show_guest_checkout_div = "display:none";
			$show_have_acc_div = "display:none";
			$show_other_payments_checkout = "";
		}

		if(Session::get('PayPalToken') != null)
		{
			$show_billing_info_div = "";
			$show_guest_checkout_div = "display:none";
			$show_have_acc_div = "display:none";
			$show_other_payments_checkout = "";
		}

		if(isset($request->method) && $request->method == 'AP')
		{
			$show_billing_info_div = "";
			$show_guest_checkout_div = "display:none";
			$show_have_acc_div = "display:none";
			$show_other_payments_checkout = "display:none";
		}

		if (config('global.POSMode') == true) {
			$total_store_agents = 0;
			$salesAgentsForCommission = array();
			if (Session::has('sess_storeuseremail') && Session::get('sess_storeuseremail') != '') {
				$store = auth('store')->user();
				$total_Sales_persons = StoreSalesPerson::where('status', '=', '1')
					->where('email', '=', trim(Session::get('sess_storeuseremail')))
					->where('store_id', '=', $store->store_id)
					->first();
				//$total_store_agents = count($total_Sales_persons);
			}
			$stusr = 0;
			//if(!empty($total_Sales_persons)) {
			if (!empty($total_Sales_persons) && !Session::has('ShoppingCart.SalesAgent')) {
				Session::put('ShoppingCart.SalesAgent.' . $stusr . '.sales_person_id', $total_Sales_persons->sales_person_id);
				Session::put('ShoppingCart.SalesAgent.' . $stusr . '.sales_person_email', $total_Sales_persons->email);
				Session::put('ShoppingCart.SalesAgent.' . $stusr . '.sales_person_name', $total_Sales_persons->name);
				Session::put('ShoppingCart.SalesAgent.' . $stusr . '.sales_person_per', '100');
				Session::save();
			}

			//$this->PageData['total_store_agents'] = $total_store_agents;
			//$this->PageData['store_agents'] = $total_Sales_persons;
			if (Session::has('ShoppingCart.SalesAgent') && !empty(Session::get('ShoppingCart.SalesAgent'))) {
				$salesAgentsForCommission = Session::get('ShoppingCart.SalesAgent');
				//print_r($salesAgentsForCommission);
			}
			$this->PageData['salesAgentsForCommission'] = $salesAgentsForCommission;
		}

		$this->PageData['show_billing_info_div'] = $show_billing_info_div;
		$this->PageData['show_guest_checkout_div'] = $show_guest_checkout_div;
		$this->PageData['show_have_acc_div'] = $show_have_acc_div;
		$this->PageData['show_other_payments_checkout'] = $show_other_payments_checkout;
		addLog('CheckoutPage',$log);
		if(Session::has('ShoppingCart'))
		{
			$ShoppingCart = Session::get('ShoppingCart');
			$this->PageData['CartDetails'] = $ShoppingCart;
		}
		return view('checkout.index')->with($this->PageData);

		//if($_SERVER['HTTP_CF_CONNECTING_IP']=='2406:b400:d5:8f9b:3d81:a294:68cf:3232'){
		// if(Auth::user() && (trim(Auth::user()->email) == 'qqualdev@gmail.com' || trim(Auth::user()->email) == 'hamed@maxaroma.com' || trim(Auth::user()->email) == 'adam@maxaroma.com' || trim(Auth::user()->email) == 'sonia@maxaroma.com')){
		// 	return view('checkout.temp')->with($this->PageData);
		// } else {
		// 	return view('checkout.index')->with($this->PageData);
		// }
	}

	public function ShippingMethods(Request $request)
	{
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		addLog('ShippingMethodStart');
		$skrSKU = array();
		if(!Session::has('isPhoneOrder')){
			$skrSKU= $this->OutOfStockItemsRemove();
		}

		if (Auth::guard('store')->check()) {
			Session::forget('ShoppingCart.OrderID');
			Session::forget('ShoppingCart.StoreOrderID');
			Session::forget('ShoppingCart.StoreCashSplitPaymentId');
			Session::forget('ShoppingCart.WebsiteCashSplitPaymentId');
			Session::forget('ShoppingCart.StoreCashPaymentId');
			Session::forget('ShoppingCart.WebsiteOrderID');
		}

		if (Auth::guard('store')->check() && Session::has('ShoppingCart.OrderType') &&  Session::get('ShoppingCart.OrderType') == "Store") {
		return redirect('/checkout');
		}

		//if(!Session::has('isPhoneOrder') &&	(!Session::has('ShoppingCart.Cart') || count(Session::get('ShoppingCart.Cart')) == 0))
		if(!Session::has('isPhoneOrder') && $request->subaction !== 'paypalproductpage' && (!Session::has('ShoppingCart.Cart') || count(Session::get('ShoppingCart.Cart')) == 0))
			return redirect('/shoppingcart');

		if(count($skrSKU) > 0)
		{
			$err_msg = "Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			$log['err_msg'] = $err_msg;
			addLog('ShippingMethod',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/shoppingcart');
		}
		$this->PageData["GARegister1"] = "";

		if(Session::has('GARegsiter') && Session::get('GARegsiter')!='')
		{
			$GARegister= googleAnalyticsGA4("RegisterPage");

			Session::forget('GARegsiter');
			$this->PageData["GARegister1"] = $GARegister;
		}

		$IsGiftCertificateItem = '';

        $this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];
		$this->PageData['JSFILES'] = ['billing.js'];

		$this->SetCheckoutCommonDetails($request);

		$this->PageData['SelPayMethod'] = (isset($request->SelPayMethod)?$request->SelPayMethod:'');
		if(isset($request->action) && $request->action == 'setshippinginsurance')
		{
			if(isset($request->shipping_signature) && $request->shipping_signature != '' && $request->shipping_signature != '0')
			{
				Session::put('ShoppingCart.ShippingSignature',$request->shipping_signature);
			} else {
				Session::forget('ShoppingCart.ShippingSignature');
			}
			$this->SetShippingInsuranceCharge($request->subaction);
			$this->SetupCart();
			return view('checkout.subtotalbox')->with($this->PageData)->render();
		}

		if((isset($request->action) && $request->action == 'shippinginfo') || Session::get('ShoppingCart.action') == 'shippinginfo')
		{

			$this->PageData['PageFrom'] = $request->PageFrom;

			$this->PageData['VendorPopup'] = '';
			if($request->OnlyHead == '0' || Session::get('ShoppingCart.OnlyHead')== '0')
			{

				if(Session::get('ShoppingCart.Shipping.ShippingMethodID') > 0 && $request->PageFrom!='amazon_billing')
				{

					$IsMaxaromaTwoDelivery	= Session::get('ShoppingCart.IsMaxaromaTwoDelivery');

					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsMaxaromaTwoDelivery	=  $request["IsMaxaromaTwoDelivery"];
					}
					$ISMaxTwoItem 			= Session::get('ShoppingCart.ISMaxTwoItem');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$ISMaxTwoItem 			= $request["ISMaxTwoItem"];
					}
					$IsVenderItem 			= Session::get('ShoppingCart.IsVenderItem');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsVenderItem 			= $request["IsVenderItem"];
					}
					$IsCosmo 	 			= Session::get('ShoppingCart.IsCosmo');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsCosmo 	 			= $request["IsCosmo"];
					}
					$IsNandansons 			= Session::get('ShoppingCart.IsNandansons');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsNandansons 			= $request["IsNandansons"];
					}
					$IsPerfumePW  			= Session::get('ShoppingCart.IsPerfumePW');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsPerfumePW  			= $request["IsPerfumePW"];
					}
					$IsPCA  				= Session::get('ShoppingCart.IsPCA');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsPCA  				= $request["IsPCA"];
					}
					$ISMax2dayVal 			= Session::get('ShoppingCart.ISMax2dayVal');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$ISMax2dayVal 			= $request["ISMax2dayVal"];
					}
					$IsND  				= Session::get('ShoppingCart.IsND');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsND  				= $request["IsND"];
					}
					$onlyGCPurchased 		= Session::get('ShoppingCart.onlyGCPurchased');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$onlyGCPurchased 		= $request["onlyGCPurchased"];
					}
				}
				else
				{
					$IsMaxaromaTwoDelivery	= $request["IsMaxaromaTwoDelivery"];
					$ISMaxTwoItem = $request['ISMaxTwoItem'];
					$IsVenderItem = $request["IsVenderItem"];
					$IsCosmo 	 = $request["IsCosmo"];
					$IsNandansons = $request["IsNandansons"];
					$IsPerfumePW  = $request["IsPerfumePW"];
					$IsPCA  	= $request["IsPCA"];
					$IsND  	= $request["IsND"];
					$ISMax2dayVal = $request["ISMax2dayVal"];
					$onlyGCPurchased = $request['onlyGCPurchased'];
				}
				$ship_country = '';
				$ship_zip 	  = '';
				$ship_state	  = '';
				$ship_address1 = '';
				$ship_address2 = '';
				$ship_city = '';

				if(Session::has('ShoppingCart.BillingAddress'))
				{
					if(Session::has('ShoppingCart.BillingAsShipping') && Session::get('ShoppingCart.BillingAsShipping') == "No")
					{
						$Shipping = Session::get('ShoppingCart.ShippingAddress');
						$ship_country = $Shipping["country"];
						$ship_zip 	  = $Shipping["zip"];
						$ship_state	  = $Shipping["state"];
						$ship_address1 = trim($Shipping["address1"]);
						$ship_address2 = trim($Shipping["address2"]);
						$ship_city	   = trim($Shipping["city"]);
						$log['ShippingMethodShipping'] = $Shipping;
					}
					else

					{
						$Billing = Session::get('ShoppingCart.BillingAddress');
						$ship_country = $Billing["country"];
						$ship_zip 	  = $Billing["zip"];
						$ship_state	  = $Billing["state"];
						$ship_address1 = trim($Billing["address1"]);
						$ship_address2 = trim($Billing["address2"]);
						$ship_city	   = trim($Billing["city"]);
						$log['ShippingMethodBilling'] = $Billing;
					}
					addLog('ShippingMethod',$log);
				}

				if(isset($request->PageFrom) && $request->PageFrom =='AmazonBilling')
				{
					$ship_state = Session::get('AmazonShipState');
					$ship_zip = Session::get('AmazonShipZip');
					$ship_country = Session::get('AmazonShipCountry');
					if(Session::has('AmazonShipCity'))
					{
						$ship_city = Session::get('AmazonShipCity');
					}
				}

				//if(isset($request->subaction) && $request->subaction =='stripecart')
				if(isset($request->subaction) && ($request->subaction =='stripecart' || $request->subaction =='paypalproductpage' || $request->subaction =='paypalcart'))
				{
					$log['ShippingMethodsubaction'] = $request->subaction;
					addLog('ShippingMethod',$log);
					$ShopCartItems = Session::get('ShoppingCart.Cart');
					$TempCart = [];
					$IsMaxaromaTwoDelivery = "No";
					$AllMaxTwoDay = 'No';
					if($request->subaction =='stripecart' ||  $request->subaction =='paypalcart'){
						foreach($ShopCartItems as $ShopItem)
						{
							if((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") || (isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=='Yes') || (isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=='Yes') || (isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=='Yes') || (isset($ShopItem['IsND']) && $ShopItem['IsND']=='Yes') && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
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
							}else{
								$AllMaxTwoDay = "Yes";
							}

							$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$ShopItem);

							//if($ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU1'))
							if($IsGiftCertificateItem == 'No'){
								$onlyGCPurchased = 0;
							}
						}
					}

					if($request->zip != ""){
						$ship_zip = $request->zip;
						$ship_state = $request->state;
						$ship_country = $request->country;
						$ship_address1 = trim($request->address1);
						$ship_address2 = trim($request->address2);
						if(isset($request->city) && $request->city!='')
						{
							$ship_city = trim($request->city);
						}
					} else {
						$normaluser = Auth::user();
						if (Auth::guard('store')->check()) {
							$normaluser = Auth::guard('web')->user();
						}
						//if(Auth::user())
						if($normaluser)
						{
							$ship_zip = $normaluser->zip; //Auth::user()->zip;
							$ship_state = $normaluser->state; //Auth::user()->state;
							$ship_country = $normaluser->country; //Auth::user()->country;
							$ship_address1 = trim($normaluser->address1); //trim(Auth::user()->address1);
							$ship_address2 = trim($normaluser->address2); //trim(Auth::user()->address2);
						} else {

							$ship_city = "";
							if(isset($request->city) && $request->city!='')
							{
								$ship_city = trim($request->city);
							}

							$ship_zip = "";
							if(isset($request->zip) && $request->zip!='')
							{
								$ship_zip = $request->zip;
							}
							$ship_state = "";
							if(isset($request->state) && $request->state!='')
							{
								$ship_state = trim($request->state);
							}

							$ship_country = "";
							if(isset($request->country) && $request->country!='')
							{
								$ship_country = trim($request->country);
							}

							$ship_address1 = "";
							if(isset($request->address1) && $request->address1!='')
							{
								$ship_address1 = trim($request->address1);
							}
							$ship_address2 = "";
							if(isset($request->address2) && $request->address1!='')
							{
								$ship_address2 = trim($request->address2);
							}

						}
					}
				}

				$ShippingModeRS = ShippingMode::where('status','=','1')->orderBy('display_position','asc')->get();
				$log['ShippingModeRS'] = json_encode($ShippingModeRS);
				addLog('ShippingMethod',$log);
				$Sess_ShippingInfo = "";
				if(Session::has('ShoppingCart.Shipping'))
					$Sess_ShippingInfo = Session::get('ShoppingCart.Shipping');

				$shipping_mode_idMainArr = $this->CheckAvailableShippingMethod(29, $ship_country,$ship_state,$ship_zip);
				$shipping_mode_idMainArr = explode("###",$shipping_mode_idMainArr);
				$shipping_mode_id =(int) $shipping_mode_idMainArr[0];
				$istwoday = "No";
				$log['shipping_mode_idMainArr'] = json_encode($shipping_mode_idMainArr);
				addLog('ShippingMethod',$log);

				if($shipping_mode_id >0 )
				{
					$istwoday = "Yes";
				}
				$AddressCheck = "No";
				$APOFPO = "No";

				if(isset($ship_address1) && $ship_address1!='')
				{
					if(preg_match('/\bP\.?\s*O\.?(\s*B\.?\s*O\.?\s*X|\s*Box|\d+)?\b/i', $ship_address1 ?? ''))
{
						$istwoday = "No";
						$AddressCheck = "Yes";
					}
				}
				if(isset($ship_address2) && $ship_address2!='')
				{
					if(preg_match('/\bP\.?\s*O\.?(\s*B\.?\s*O\.?\s*X|\s*Box|\d+)?\b/i', $ship_address2 ?? ''))
					{
						$istwoday = "No";
						$AddressCheck = "Yes";
					}
				}
				if(isset($ship_address1) && $ship_address1!='')
				{
					if(preg_match("/apo/i",strtolower($ship_address1)) || preg_match("/fpo/i",strtolower($ship_address1)))
					{
						$APOFPO = "Yes";
					}
				}
				if(isset($ship_address2) && $ship_address2!='')
				{
					if(preg_match("/apo/i",strtolower($ship_address2)) || preg_match("/fpo/i",strtolower($ship_address2)))
					{
						$APOFPO = "Yes";
					}
				}
				if(isset($ship_city) && $ship_city!='')
				{
					if(preg_match("/apo/i",strtolower($ship_city)) || preg_match("/fpo/i",strtolower($ship_city)))
					{
						$APOFPO = "Yes";
					}
				}
				//echo $AddressCheck; exit;

				$count = 0; // This var used for count availabe method
				$Checkcounter = 0;
				$MsgVal=[];
				$ChargeInfo = [];
				$SelShipMethod = 0;
				$shipping_mode_idArr="";
				$Max2Days = 0;
				$MsgSucess = 0;
				$isPickUp = "No";

				for($p=0; $p<count($ShippingModeRS); $p++ )
				{

					if($AddressCheck =="Yes" && $ShippingModeRS[$p]['shipping_mode_id']!=9 && $ShippingModeRS[$p]['shipping_mode_id']!=22)
					{
						continue;
					}

					if($AddressCheck=="Yes" && $ShippingModeRS[$p]['shipping_mode_id']==29)
					{
						continue;
					}

					if($IsVenderItem == "Yes" && $ShippingModeRS[$p]['shipping_mode_id']==46)
					{
						$isPickUp = "No";
						continue;
					}

					if($ShippingModeRS[$p]['shipping_mode_id']==46)
					{
						$isPickUp = "Yes";
					}

					if(($istwoday=="Yes" && $IsMaxaromaTwoDelivery =='Yes' && ($ShippingModeRS[$p]['shipping_mode_id']==22 || $ShippingModeRS[$p]['shipping_mode_id']==34 || $ShippingModeRS[$p]['shipping_mode_id']==29 || $ShippingModeRS[$p]['shipping_mode_id']==46)))
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="No" && $IsMaxaromaTwoDelivery =='Yes' )
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="Yes" && $IsMaxaromaTwoDelivery =='No' &&  $ShippingModeRS[$p]['shipping_mode_id']!=29)
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="No" && $IsMaxaromaTwoDelivery =='No' )
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}

					if(strtolower(Session::get('eusertype') ?? '')=="wholesaler")
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					$normalWeight = 0;
					$lightWeight = 0;
					$heavyWeight = 0;
					$shipping_mode_id = 0;

					$log['shipping_mode_idArr'] = json_encode($shipping_mode_idArr);
					addLog('ShippingMethod',$log);

					if($shipping_mode_idArr != '' && !is_array($shipping_mode_idArr))
					{
						//if(!is_array($shipping_mode_idArr))
						$shipping_mode_idArr = explode("###",$shipping_mode_idArr);
						$shipping_mode_id =(int) $shipping_mode_idArr[0];
						$normalWeight = $shipping_mode_idArr[1];
						$lightWeight = $shipping_mode_idArr[2];
						$heavyWeight = $shipping_mode_idArr[3];
					}
					if($AddressCheck == 'No' && $IsMaxaromaTwoDelivery=="No" && $istwoday=="No" && strtolower(Session::get('eusertype') ?? '') != "wholesaler")
					{
						$MsgVal[] = 'Your order is not eligible for Max2days shipping as one of the item is not eligible.<br/>Since you are shipping to a different address this order is not eligible for Max2days shipping option.';
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					else if($AddressCheck == 'No' && $istwoday=="No" && strtolower(Session::get('eusertype') ?? '') != "wholesaler")
					{
						$MsgVal[] = 'Since you are shipping to a different address this order is not eligible for Max2days shipping option.';
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					else if($AddressCheck == 'No' && $IsMaxaromaTwoDelivery=="Yes" && $ISMaxTwoItem == 'Yes' && isset($ISMax2dayVal) && $ISMax2dayVal=="No" && strtolower(Session::get('eusertype') ?? '') != "wholesaler"  && $IsVenderItem=="No")
					{
						$MsgVal[] = 'Great News, Your order was Upgraded to Free Second Day Shipping Service.';
						$MsgSucess =1;
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					else if($AddressCheck == 'No' && $IsMaxaromaTwoDelivery=="Yes" && $ISMaxTwoItem == 'Yes' && isset($ISMax2dayVal) && $ISMax2dayVal=="No" && strtolower(Session::get('eusertype') ?? '') != "wholesaler"  && $IsVenderItem=="Yes")
					{
						$MsgVal[] = 'Great News, Your order was Upgraded to Free Second Day Shipping Service.Order was Upgraded to Free 2DAY Shipping Service, Please add 2 Extra Business days because of some items in your cart.';
						$MsgSucess =1;
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					if($AddressCheck == 'Yes' && $shipping_mode_id == 22)
					{
						$MsgVal[] = 'Your order is not eligible for Max2days shipping because our carrier does not ship using this service to PO BOX Addresses';
						//continue;
					}
					$log['ShippingMethodAddressCheck'] = $AddressCheck;
					$log['ShippingMethodMsgVal'] = $MsgVal;
					addLog('ShippingMethod',$log);
					if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
					{

						$paypal_subtotal = "";
						$paypal_prodqty = 0;
						if(isset($request->subaction) && $request->subaction == 'paypalproductpage' && isset($request->paypal_total_price) && $request->paypal_total_price != '')
						{
							$paypal_subtotal = $request->paypal_total_price;
							$paypal_prodqty = isset($request->paypal_prodqty) ? $request->paypal_prodqty : 1;
							$paypal_productid = isset($request->paypal_productid) ? $request->paypal_productid : 0;

							$ProductChkStock = $this->ProductCheckInStock($paypal_productid, $paypal_prodqty,"insert");
							$ProductRs = $ProductChkStock['ProdInfo'];

							if($ProductRs->WebsiteStock == "Out")
							{
								if($ProductRs->cosmo_sku!='' &&  $ProductRs->cosmo_current_stock > 0 &&  $ProductRs->cosmo_our_price > 0)
								{
									$IsCosmo = "Yes";
									$VendorSKU = $ProductRs->cosmo_sku;
								}
								else if($ProductRs->pca_sku!='' &&  $ProductRs->pca_current_stock > 0 && $ProductRs->pca_our_price > 0)
								{
									$IsPCA  = "Yes";
									$VendorSKU = $ProductRs->pca_sku;
								}
								else if($ProductRs->nandansons_sku!='' &&  $ProductRs->nandansons_current_stock > 0 && $ProductRs->nandansons_our_price > 0)
								{
									$IsNandansons = "Yes";
									$VendorSKU = $ProductRs->nandansons_sku;
								}
								else if($ProductRs->perfumeworldwide_sku!='' &&  $ProductRs->perfumeworldwide_currentstock > 0 && $ProductRs->perfumeworldwide_our_price > 0)
								{
									$IsPerfumePW = "Yes";
									$VendorSKU = $ProductRs->perfumeworldwide_sku;
								}
								else if($ProductRs->nd_sku!='' &&  $ProductRs->nd_current_stock > 0 && $ProductRs->nd_our_price > 0)
								{
									$IsND = "Yes";
									$VendorSKU = $ProductRs->nd_sku;
								}
							}

							if($ProductRs->WebsiteStock == "In"){
								$IsMaxaromaTwoDelivery	= $ProductRs->maxtwodaydelivery;
								$ISMaxTwoItem = (isset($ProductRs->maxtwodaydelivery) && $ProductRs->maxtwodaydelivery!='' &&$ProductRs->maxtwodaydelivery=='Yes') ? 'Yes' : 'No';
								$ISMax2dayVal = $ISMaxTwoItem;
							}

							if(isset($ProductRs->IsDealProducts) && $ProductRs->IsDealProducts == "Yes")
							{
								$IsMaxaromaTwoDelivery = 'No';
							}

							//$IsPerfumePW  = "";

							if(($IsCosmo == "Yes" || $IsNandansons  == "Yes" || $IsPCA  == "Yes" || $IsPerfumePW == "Yes" || $IsND  == "Yes") && $VendorSKU != ''){			  $IsVenderItem = "Yes";
							}

							//$onlyGCPurchased = $request['onlyGCPurchased'];
						}
						//Log::info('CalculateAvailableShippingCharge: ship_zip - '.$ship_zip.' --ship_state -'.$ship_state.'--ship_country'.$ship_country.'--shipping_mode_id'.$shipping_mode_id.'--paypal_subtotal'.$paypal_subtotal);

						$tempChargeStr = $this->CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id,$paypal_subtotal,$paypal_prodqty);
						$tempChargeArr = explode("###",$tempChargeStr);

						$log['tempChargeStr'] = $tempChargeStr;
						$log['tempChargeArr'] = json_encode($tempChargeArr);
						addLog('ShippingMethod',$log);

						$tempCharge = $tempChargeArr[0];
						$days		= $tempChargeArr[1];
						if(!Session::has('isPhoneOrder'))
						{
							if(Session::get('ShoppingCart.Shipping.ShippingMethodID') > 0 && isset($request->PageFrom) && $request->PageFrom=='amazon_billing')
							{
							$IsCosmo 	 	= $request["IsCosmo"];
							$IsNandansons	= $request["IsNandansons"];
							$IsPerfumePW  	= $request["IsPerfumePW"];
							$IsPCA  		= $request["IsPCA"];
							$IsND  			= $request["IsND"];
							}
						}

						$VendorDays = 0;

						if(($IsVenderItem=="Yes" && $IsPerfumePW=="Yes"))
						{
							$days		= $tempChargeArr[1];
							$days = $days + 3;
							$VendorDays = 3;
						}
						else if(($IsVenderItem=="Yes" && $IsCosmo=="Yes") || ($IsVenderItem=="Yes" && $IsPCA=="Yes") || ($IsVenderItem=="Yes" && $IsNandansons=="Yes") || ($IsVenderItem=="Yes" && $IsPerfumePW=="Yes") || ($IsVenderItem=="Yes" && $IsND=="Yes"))
						{

							$days		= $tempChargeArr[1];
							$days = $days + 3;
							$VendorDays = 3;
						}
						$ShippingModeRS[$p]['days']	= $days;
						$DayVal = date("H@@a");
						$DayValArr = explode("@@",$DayVal);

						$DaynameVal = date("l");
						if($DayValArr[1] == "pm" && isset($DaynameVal) && $DaynameVal!='Saturday' && $DaynameVal!="Sunday")
						{
						   if($DayValArr[0] >=14)
						   {
							   $ShippingModeRS[$p]['days'] = $ShippingModeRS[$p]['days'] + 1;
						   }
						}

						if(isset($shipping_mode_id) && ($shipping_mode_id == 33 || $shipping_mode_id == 34 || $shipping_mode_id == 29) && ($DaynameVal=="Saturday" || $DaynameVal=="Sunday"))
						{
							if($DaynameVal=="Saturday")
							{
								$ShippingModeRS[$p]['days'] = $ShippingModeRS[$p]['days'] + 2;
							}
							else if($DaynameVal=="Sunday")
							{
								$ShippingModeRS[$p]['days'] = $ShippingModeRS[$p]['days'] + 1;
							}
						}

						$normalPWeight = 0;
						$lightPWeight = 0;
						$heavyPWeight = 0;
						$CartArr = Session::get('ShoppingCart.Cart');

						if($paypal_subtotal == ""){
							for($t=0;$t<count($CartArr);$t++)
							{
								if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Normal" && $normalWeight > 0)
								{
									$normalPWeight = $normalPWeight + ($normalWeight * $CartArr[$t]["Qty"] );
								}
								if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Light" && $lightWeight > 0)
								{
									$lightPWeight = $lightPWeight + ($lightWeight * $CartArr[$t]["Qty"] );
								}
								if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Heavy" && $heavyWeight > 0)
								{
									$heavyPWeight = $heavyPWeight + ($heavyWeight * $CartArr[$t]["Qty"] );
								}
							}
						}

						$tempCharge = $tempCharge + $normalPWeight + $lightPWeight + $heavyPWeight;

						$charge_str = '';
						if($tempCharge>0)
						{
							$charge_str = Price($tempCharge,true);
						}

						if(empty(Session::get('ShoppingCart.Shipping.ShippingMethodID')))
						{
							 if($shipping_mode_id==29)
							 {
								$r_sel = " checked ";
								$r_sel_box = 'active';
							 }
							 else if($count==0)
							 {
								$r_sel = " checked ";
								$r_sel_box = 'active';
							 }
							else
							{
								$r_sel = "";
								$r_sel_box = '';
							}
						}
						else
						{

							if(Session::get('ShoppingCart.Shipping.ShippingMethodID')==$ShippingModeRS[$p]['shipping_mode_id'])
							{
								$r_sel = " checked ";
								$r_sel_box = 'active';
								$SelShipMethod = Session::get('ShoppingCart.Shipping.ShippingMethodID');
							}
							else
							{
								$r_sel = "";
								$r_sel_box = '';
							}
						}
						$estimateShipDate='';
						$DateFieldVal = '';
						$EstimatedDeliveryDate = '';
						$DateSuffix = '';
						$DayNewValOf = '';

						if($ShippingModeRS[$p]['days']!='')
						{
							if($ShippingModeRS[$p]['days']==0)
							{
								$estimateShipDate='';
								$EstimatedDeliveryDate = '';
								$DateFieldVal = '';
								$DateSuffix = '';
								$DayNewValOf = '';
							}
							else
							{
								$holiday_day_arr = ShippingHoliday::where('holiday_status','=','1')->where('holiday_date','>',date("Y-m-d"))->get();

								$holiday_day = $holiday_day_arr->count();
								$HolidayArrVal = array();

								foreach($holiday_day_arr as $HolidayVal)
								{

									$HolidayArrVal[] = $edate = date('Y-m-d', strtotime($HolidayVal->holiday_date));

								}
								$k=$ShippingModeRS[$p]['days'];

								for($d=1;$d<=$k;$d++)
								{

									$edate = date('Y-m-d', strtotime("+" . $d . "days"));

									$daynew = $this->checkday($edate);
									if ($daynew == 'saturday' || $daynew == 'sunday')
									{
										$k++;
									}
									else if(in_array($edate,$HolidayArrVal))
									{
										$k++;

									}

								}

							//	echo "<pre>"; print_r($ChargeInfo); exit;
								$dt_date =  date('M d', strtotime($edate));

								$estimateShipDate='Estimated Delivery on or before <b>'.$dt_date.'</b>';
								$EstimatedDeliveryDate =  $edate;
							}
						}else
						{
							$estimateShipDate='';
							$DateFieldVal = '';
							$EstimatedDeliveryDate = '';
							$DateSuffix = '';
							$DayNewValOf = '';
						}
						$Checkcounter = 1;

						if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($ShippingModeRS[$p]['shipping_mode_id'],Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
						{
							$charge_str = '';
						}

						if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $ShippingModeRS[$p]['shipping_mode_id'] == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
						{
							$charge_str = '';
						}

						if($r_sel_box == 'active' && $IsVenderItem == 'Yes')
						{

							$dt_date =  date('m/d', strtotime("+".$VendorDays. "days"));
							$VendorPopup = str_replace('{$daysval}',$dt_date,config('Settings.VENDORITEM_POPUP_WINDOW'));
							$VendorPopup = str_replace('{$days}',$VendorDays,$VendorPopup);

							//echo $VendorPopup; exit;
							$this->PageData['VendorPopup'] = $VendorPopup;
						}

						if($ShippingModeRS[$p]['shipping_mode_id'] == 29 && $tempCharge <= 0)
						{
							$Max2Days = 1;
						}

						if(empty($EstimatedDeliveryDate) || $EstimatedDeliveryDate=='')
						{
							$EstimatedDeliveryDate = date("Y-m-d");
						}

						if($charge_str!='')
						{
							$ChargeInfo[] = [
								'active' => $r_sel_box,
								'days' => $days,
								'charge' => $this->Make_Price($tempCharge,true),
								'chargewithoutformat' => $tempCharge,
								'checked' => $r_sel,
								'shipping_mode_id' => $ShippingModeRS[$p]['shipping_mode_id'],
								//'display_date' => $DateFieldVal.'<sup>'.$DateSuffix.'</sup> '.$DayNewValOf,
								'display_date' => date('D, F d',strtotime($EstimatedDeliveryDate)),
								'estdate' =>  date('m/d/Y',strtotime($EstimatedDeliveryDate)),
								'method_name' => $ShippingModeRS[$p]['type'],
								'charge_str' => $charge_str,
								'estimateShipDate' => $estimateShipDate,
								'dateSort' => $EstimatedDeliveryDate
							];
						}
						else
						{
							$ChargeInfo[] = [
								'active' => $r_sel_box,
								'checked' => $r_sel,
								'charge' => 0,
								'chargewithoutformat' => 0,
								'days' => $days,
								'shipping_mode_id' => $ShippingModeRS[$p]['shipping_mode_id'],
								//'display_date' => $DateFieldVal.'<sup>'.$DateSuffix.'</sup> '.$DayNewValOf,
								'display_date' => date('D, F d',strtotime($EstimatedDeliveryDate)),
								'estdate' =>  date('m/d/Y',strtotime($EstimatedDeliveryDate)),
								'method_name' => $ShippingModeRS[$p]['type'],
								'charge_str' => '<span class="clsfree">Free</span>',
								'estimateShipDate' => $EstimatedDeliveryDate,
								'dateSort' => 0
							];
						}
						$count = $count +1;
					}
					else
					{
						continue;
					}
				}

				if(count($ChargeInfo)>0)
				{
					$log['ChargeInfo'] = json_encode($ChargeInfo);
					addLog('ShippingMethod',$log);
					$NewMethods = [];
					foreach($ChargeInfo as $CheckForMaxDay)
					{
						if($Max2Days == 1 && $CheckForMaxDay['shipping_mode_id'] == 22)
						{
							continue;
						}
						if($CheckForMaxDay['shipping_mode_id']==22 && $isPickUp=="Yes")
						{
							$CheckForMaxDay['dateSort'] = '-1';

						}
						$NewMethods[]=$CheckForMaxDay;
					}
					$ChargeInfo = $NewMethods;
				}

				$shipping_insurance_checked = 'checked="checked"';
				$shipping_insurance_widget_checked = 'data-default-checked="true"';
				$shipping_signature_css = 'style="display:none;"';

				if(Session::has('ShoppingCart.Shipping.ShippingMethodID') && Session::get('ShoppingCart.Shipping.ShippingMethodID') == 46)
				{
					$shipping_insurance_checked = '';
					$shipping_insurance_widget_checked = 'data-default-checked="false"';
					$shipping_signature_css = '';
				}

				if(Session::has('shipping_insurance') && Session::get('shipping_insurance') == "N")
				{
					$shipping_insurance_checked = '';
					$shipping_insurance_widget_checked = 'data-default-checked="false"';
					$shipping_signature_css = '';
				}
				$shipping_insurance_charge = 0;
				if(Session::has('shipping_insurance_charge') && Session::get('shipping_insurance_charge') !=""){
					$shipping_insurance_charge = Session::get('shipping_insurance_charge');
				}

				$ShippingSignatureInfo = [];
				if($Checkcounter==1)
				{
					if(config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') == "Yes" && Session::get('eusertype') == "Wholesaler" && Session::get('etype') == "M" && $ship_country=="US")
					{
						/*$ShippingSignatureInfo[]= '
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
							<label style="padding-left:0px; vertical-align:top;" class="fsbold">$'.config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE').' Request Signature &nbsp;&nbsp;</label>
							<label class="switch">
								<input type="checkbox"  value="Yes" name="shipping_signature" id="shipping_signature" >
								<span class="slider round text_off" id="slider_round">Off</span>
							</label>
						</div>';*/
						$ShippingSignatureInfo[]='
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
						<span>$'.config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE').' Request Signature</span>
						<label class="switch" id="insurance">
							<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature">
							<span class="slider round"></span>
						</label>
						</div>
						';
					}
					if(config('Settings.SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') !="Yes" && $ship_country=="US")
					{
						/*$ShippingSignatureInfo[]= '
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
							<label class="fsbold" style="padding-left:0px; vertical-align:top;">$'.config('Settings.SHIPPING_SIGNATURE').' Request Signature &nbsp;&nbsp;</label><label class="switch">
								<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature" ">
								<span class="slider round text_off" id="slider_round">Off</span>
							</label>
						</div>';*/
						$ShippingSignatureInfo[]='
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
						<span>$'.config('Settings.SHIPPING_SIGNATURE').' Request Signature</span>
						<label class="switch" id="insurance">
							<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature">
							<span class="slider round"></span>
						</label>
						</div>
						';
					}
				}

				$RouteWidget ='<div id="RouteWidget" '.$shipping_insurance_widget_checked.'></div><input type="checkbox" value="Yes" name="shipping_insurance" '.$shipping_insurance_checked.' style="display:none;" id="shipping_insurance" ><input type="hidden" id="shipping_insurance_charge" value="'.$shipping_insurance_charge.'">';
				if(count($ChargeInfo) > 0)
				{
					/*if($IsVenderItem=="Yes" && config('Settings.VENDORITEM_POPUP_WINDOW') !='')
					{
						$RouteWidget.=' <a href="Javascript:void(0);" onclick="SetPaymentMethods();" data-target="#myModalPopUp" data-toggle="modal" class="button btn-1 btn-medium">Continue</a>';
					}
					else
					{
						$RouteWidget.=' <a href="Javascript:void(0);" onclick="SetPaymentMethods();"  class="button btn-1 btn-medium">Continue</a>';
					}*/
				}

				if($Checkcounter == 0)
				{
					$ChargeInfo = [];
				}else {
					$sortDates = array_column($ChargeInfo, 'dateSort');
					array_multisort($sortDates, SORT_ASC, $ChargeInfo);
				}

				$APOFPOArr = array();
				if(isset($APOFPO) &&  $APOFPO=="Yes" && count($ChargeInfo) > 0)
				{
					$APOFPOArr = $ChargeInfo;

					foreach($APOFPOArr as $key => $value)
					{
						if(isset($value["shipping_mode_id"]) && $value["shipping_mode_id"] == 47)
						{
							$ChargeInfo = array();
							$ChargeInfo[] = $value;
							Session::put('ShoppingCart.Shipping.ShippingMethodID',47);
						}
						else
						{
							continue;
						}
					}
				}

				if(count($ChargeInfo) > 0)
				{
					$log['ChargeInfo_1'] = json_encode($ChargeInfo);
					addLog('ShippingMethod',$log);
					if($SelShipMethod == 0 || $SelShipMethod==''){
						Session::put('ShoppingCart.EstimatedDeliveryDate',$ChargeInfo[0]['estdate']);
						Session::put('ShoppingCart.Shipping.ShippingMethodName',$ChargeInfo[0]['method_name']);
						Session::put('ShoppingCart.Shipping.ShippingMethodID',$ChargeInfo[0]['shipping_mode_id']);
						Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ChargeInfo[0]['days']);
						Session::put('ShoppingCart.Shipping.ShippingDays',$ChargeInfo[0]['estimateShipDate']);
						Session::put('ShoppingCart.Shipping.ShippingCharge',NumberFormat($ChargeInfo[0]['chargewithoutformat']));
						$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
					} else {
						foreach($ChargeInfo as $key => $ShipCharge)
						{

							if(Session::get('ShoppingCart.Shipping.ShippingMethodID')==$ShipCharge['shipping_mode_id'])
							{
								Session::put('ShoppingCart.EstimatedDeliveryDate',$ShipCharge['estdate']);
								Session::put('ShoppingCart.Shipping.ShippingMethodName',$ShipCharge['method_name']);
								Session::put('ShoppingCart.Shipping.ShippingMethodID',$ShipCharge['shipping_mode_id']);
								Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ShipCharge['days']);
								Session::put('ShoppingCart.Shipping.ShippingDays',$ShipCharge['estimateShipDate']);
								Session::put('ShoppingCart.Shipping.ShippingCharge',NumberFormat($ShipCharge['chargewithoutformat']));
								$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
							}
							else if($key == 0 && empty(Session::get('ShoppingCart.Shipping.ShippingMethodID')))
							{
								Session::put('ShoppingCart.EstimatedDeliveryDate',$ShipCharge['estdate']);
								Session::put('ShoppingCart.Shipping.ShippingMethodName',$ShipCharge['method_name']);
								Session::put('ShoppingCart.Shipping.ShippingMethodID',$ShipCharge['shipping_mode_id']);
								Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ShipCharge['days']);
								Session::put('ShoppingCart.Shipping.ShippingDays',$ShipCharge['estimateShipDate']);
								Session::put('ShoppingCart.Shipping.ShippingCharge',NumberFormat($ShipCharge['chargewithoutformat']));
								$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
							}
						}
					}
				}

				$CurrDate = date('Y-m-d');
				$CurrDayVal = date("H@@a");
				$CurrDayValArr = explode("@@",$CurrDayVal);
				$this->PageData['datediff'] = '';
				if($CurrDayValArr[1] == "pm")
				{
					if($CurrDayValArr[0] >=14)
					{
						$NewCurrDate = date_create(date('Y-m-d H:i:s'));
						$NewDate = date_create(date('Y-m-d H:i:s', strtotime(date('Y-m-d 14:00:00') . ' +1 day')));
						$DateDiff = $NewCurrDate->diff($NewDate);

						if($DateDiff->format('%h') > 0)
							$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
						else
							$this->PageData['datediff'] = $DateDiff->format("%i minutes");
					} else {
						$NewCurrDate = date_create(date('Y-m-d H:i:s'));
						$NewDate = date_create(date('Y-m-d 14:00:00'));
						$DateDiff = date_diff($NewCurrDate,$NewDate);

						if($DateDiff->format('%h') > 0)
							$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
						else
							$this->PageData['datediff'] = $DateDiff->format("%i minutes");
					}
				} else {
					$NewCurrDate = date_create(date('Y-m-d H:i:s'));
					$NewDate = date_create(date('Y-m-d 14:00:00'));
					$DateDiff = date_diff($NewCurrDate,$NewDate);

					if($DateDiff->format('%h') > 0)
						$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
					else
						$this->PageData['datediff'] = $DateDiff->format("%i minutes");
				}

				$this->PageData['ShippingMessage'] = array_unique($MsgVal);
				$this->PageData['MsgSucess'] = $MsgSucess;

				$this->PageData['ShippingSignatureInfo'] = $ShippingSignatureInfo;
				$this->PageData['RouteWidget'] = $RouteWidget;
				$this->PageData['ShippingMethods'] = $ChargeInfo;
			}
			$this->PageData['OnlyHead'] = $request->OnlyHead;

			if(empty($request->FirstStepGpay) && isset($request->subaction) && $request->subaction!='paypalcart')
			{
				$this->SetShippingInsuranceCharge('remove');
			}
			if(empty($request->subaction))
			{
				$this->SetShippingInsuranceCharge('add');
			}
			else if(isset($request->subaction) && isset($request->isLastNew) && $request->isLastNew=='isLastNew')
			{
				$this->SetShippingInsuranceCharge('add');
			}
			$this->SetupCart();
			$ShippingInsuranceCharge = (Session::has('shipping_insurance_charge')) ? Session::get('shipping_insurance_charge'):0;
			$ShippingSignature = (Session::has('shipping_signature')) ? Session::get('shipping_signature'):0;
			$InsureAmount = $this->GetNetTotal() - $ShippingInsuranceCharge - $ShippingSignature;

			//if(isset($request->subaction) && $request->subaction=='stripecart' && empty($request->isLastNew) && empty($request->FirstStepGpay))
			if(isset($request->subaction) && ($request->subaction=='stripecart' || $request->subaction=='paypalproductpage') && empty($request->isLastNew) && empty($request->FirstStepGpay))
			{
				$this->SetShippingInsuranceCharge('remove');
			}
			if($InsureAmount > 200)
			{
				Session::forget('ShoppingCart.ShippingSignature');
			}

			$this->PageData['InsureAmount'] = $this->GetNetTotal() - $ShippingInsuranceCharge - $ShippingSignature;

			if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
			{
				Session::put('ShoppingCart.IsMaxaromaTwoDelivery',$request["IsMaxaromaTwoDelivery"]);
			}
			if(isset($request["ISMaxTwoItem"]) && $request["ISMaxTwoItem"]!='')
			{
				Session::put('ShoppingCart.ISMaxTwoItem',$request["ISMaxTwoItem"]);
			}
			if(isset($request["IsVenderItem"]) && $request["IsVenderItem"]!='')
			{
				Session::put('ShoppingCart.IsVenderItem',$request["IsVenderItem"]);
			}
			if(isset($request["IsCosmo"]) && $request["IsCosmo"]!='')
			{
				Session::put('ShoppingCart.IsCosmo',$request["IsCosmo"]);
			}
			if(isset($request["IsNandansons"]) && $request["IsNandansons"]!='')
			{
				Session::put('ShoppingCart.IsNandansons',$request["IsNandansons"]);
			}
			if(isset($request["IsPerfumePW"]) && $request["IsPerfumePW"]!='')
			{
				Session::put('ShoppingCart.IsPerfumePW',$request["IsPerfumePW"]);
			}
			if(isset($request["IsPCA"]) && $request["IsPCA"]!='')
			{
				Session::put('ShoppingCart.IsPCA',$request["IsPCA"]);
			}
			if(isset($request["IsND"]) && $request["IsND"]!='')
			{
				Session::put('ShoppingCart.IsND',$request["IsND"]);
			}
			if(isset($request["ISMax2dayVal"]) && $request["ISMax2dayVal"]!='')
			{
				Session::put('ShoppingCart.ISMax2dayVal',$request["ISMax2dayVal"]);
			}
			if(isset($request["onlyGCPurchased"]) && $request["onlyGCPurchased"]!='')
			{
				Session::put('ShoppingCart.onlyGCPurchased',$request["onlyGCPurchased"]);
			}
			if(Session::get('ShoppingCart.Shipping.ShippingMethodID')> 0 && $request->PageFrom!='amazon_billing')
			{
				Session::put('ShoppingCart.action',"shippinginfo");
				Session::put('ShoppingCart.OnlyHead',0);
			}

			//if(isset($request->subaction) && $request->subaction =='stripecart')
			if(isset($request->subaction) && ($request->subaction =='stripecart' || $request->subaction =='paypalcart' ||$request->subaction =='paypalproductpage'))
			{
				$log['ChargeInfo_2'] = json_encode($ChargeInfo);
				addLog('ShippingMethod',$log);
				$shipping_mode_tmp_arr = [];
				$chkActive = 'N';
				$chkPaypalActive = 'N';
				$MtVal = false;

				foreach($ChargeInfo as $ckey => $SMethod)
				{
					$MtVal = false;
					if($request->subaction =='stripecart'){
						$shipping_mode_tmp_arr[] = array(
							'id'=>(string)$SMethod['shipping_mode_id'],
							'label'=>strip_tags($SMethod['method_name']),
							'detail'=>$SMethod['display_date'],
							'amount'=>round($SMethod['chargewithoutformat']*100),
						);
					}
					if($request->subaction =='paypalproductpage' ){
						if($chkActive == 'N'){
							$chkActive = $r_sel_box == 'active' ? 'Y' : 'N';
						}
						$amount_arr['value'] = (string)$SMethod['chargewithoutformat'];//round($SMethod['chargewithoutformat']*100);
						$amount_arr['currency_code'] = 'USD';
						$shipping_mode_tmp_arr[] = array(
							'id'=>(string)$SMethod['shipping_mode_id'],
							'label'=>strip_tags($SMethod['method_name'])."-".$SMethod['display_date'],
							'selected' => $r_sel_box == 'active' ? true : false,
							'type' => 'SHIPPING',
							//'detail'=>$SMethod['display_date'],
							'amount'=>$amount_arr,
						);
					}

					if($request->subaction =='paypalcart' ){

						if(Session::get('ShoppingCart.Shipping.ShippingMethodID')==$SMethod['shipping_mode_id'])
						{
						   $chkPaypalActive ='Y';
						   	$MtVal = true;
						}
						$amount_arr['value'] = (string)$SMethod['chargewithoutformat'];//round($SMethod['chargewithoutformat']*100);
						$amount_arr['currency_code'] = 'USD';
						$shipping_mode_tmp_arr[] = array(
							'id'=>(string)$SMethod['shipping_mode_id'],
							'label'=>strip_tags($SMethod['method_name'])."-".$SMethod['display_date'],
							'selected' => $MtVal,
							'type' => 'SHIPPING',
							//'detail'=>$SMethod['display_date'],
							'amount'=>$amount_arr,
						);
					}

				}

				if($chkActive == 'N' && $request->subaction =='paypalproductpage') {
						$shipping_mode_tmp_arr[0]['selected'] = true;
					}
				if($chkPaypalActive == 'N' && $request->subaction =='paypalcart' && count($shipping_mode_tmp_arr) > 0) {

						$shipping_mode_tmp_arr[0]['selected'] = true;
					}
				$log['shipping_mode_tmp_arr'] = json_encode($shipping_mode_tmp_arr);
				addLog('ShippingMethod',$log);

				return $shipping_mode_tmp_arr;
			}
			/*$GA4 = "";
			$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');
			if($onlyGCPurchased==0)
			{
				$GA4 = googleAnalyticsGA4("ShippingMethods",Session::get('ShoppingCart.Cart'),$this->GetNetTotal(), $this->GetAllCoupons('CouponCode'));
			}
			$this->PageData['GA4'] = $GA4;
			*/
			if(isset($request->PageFrom) && $request->PageFrom == 'amazon_billing')
			{
				$ShipMethodsHtml = view('checkout.shipping-methods')->with($this->PageData)->render();
				$CheckoutBoxHTML = view('checkout.subtotalbox')->with($this->PageData)->render();
				return response()->json(['ShipMethodsHtml' => $ShipMethodsHtml, 'CheckoutBoxHTML' => $CheckoutBoxHTML]);
			} else {
				$this->PageData['Shipping'] = Session::get('ShoppingCart.ShippingAddress');

				return view('checkout.shipping-methods-page')->with($this->PageData);
			}
		}

	}

	public function ShippingMethodsPayPal(Request $request)
	{
		addLog('PaymentMethodsStart');
		$skrSKU = array();
		if(!Session::has('isPhoneOrder')){
			$skrSKU= $this->OutOfStockItemsRemove();
		}

		if(count($skrSKU) > 0)
		{
			$err_msg = "Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/shoppingcart');
		}
		$this->PageData["GARegister1"] = "";

		if(Session::has('GARegsiter') && Session::get('GARegsiter')!='')
		{
			$GARegister= googleAnalyticsGA4("RegisterPage");

			Session::forget('GARegsiter');
			$this->PageData["GARegister1"] = $GARegister;
		}

		$IsGiftCertificateItem = '';

        $this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];
		$this->PageData['JSFILES'] = ['billing.js'];

		$this->SetCheckoutCommonDetails($request);

		$this->PageData['SelPayMethod'] = (isset($request->SelPayMethod)?$request->SelPayMethod:'');
		if(isset($request->action) && $request->action == 'setshippinginsurance')
		{
			if(isset($request->shipping_signature) && $request->shipping_signature != '' && $request->shipping_signature != '0')
			{
				Session::put('ShoppingCart.ShippingSignature',$request->shipping_signature);
			} else {
				Session::forget('ShoppingCart.ShippingSignature');
			}
			$this->SetShippingInsuranceCharge($request->subaction);
			$this->SetupCart();
			return view('checkout.subtotalbox')->with($this->PageData)->render();
		}

		if((isset($request->action) && $request->action == 'shippinginfo') || Session::get('ShoppingCart.action') == 'shippinginfo')
		{

			$this->PageData['PageFrom'] = $request->PageFrom;

			$this->PageData['VendorPopup'] = '';
			if($request->OnlyHead == '0' || Session::get('ShoppingCart.OnlyHead')== '0')
			{

				if(Session::get('ShoppingCart.Shipping.ShippingMethodID') > 0 && $request->PageFrom!='amazon_billing')
				{

					$IsMaxaromaTwoDelivery	= Session::get('ShoppingCart.IsMaxaromaTwoDelivery');

					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsMaxaromaTwoDelivery	=  $request["IsMaxaromaTwoDelivery"];
					}
					$ISMaxTwoItem 			= Session::get('ShoppingCart.ISMaxTwoItem');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$ISMaxTwoItem 			= $request["ISMaxTwoItem"];
					}
					$IsVenderItem 			= Session::get('ShoppingCart.IsVenderItem');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsVenderItem 			= $request["IsVenderItem"];
					}
					$IsCosmo 	 			= Session::get('ShoppingCart.IsCosmo');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsCosmo 	 			= $request["IsCosmo"];
					}
					$IsNandansons 			= Session::get('ShoppingCart.IsNandansons');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsNandansons 			= $request["IsNandansons"];
					}
					$IsPerfumePW  			= Session::get('ShoppingCart.IsPerfumePW');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsPerfumePW  			= $request["IsPerfumePW"];
					}
					$IsND  			= Session::get('ShoppingCart.IsND');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsND  			= $request["IsND"];
					}
					$IsPCA  				= Session::get('ShoppingCart.IsPCA');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$IsPCA  				= $request["IsPCA"];
					}
					$ISMax2dayVal 			= Session::get('ShoppingCart.ISMax2dayVal');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$ISMax2dayVal 			= $request["ISMax2dayVal"];
					}
					$onlyGCPurchased 		= Session::get('ShoppingCart.onlyGCPurchased');
					if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
					{
						$onlyGCPurchased 		= $request["onlyGCPurchased"];
					}
				}
				else
				{
					$IsMaxaromaTwoDelivery	= $request["IsMaxaromaTwoDelivery"];
					$ISMaxTwoItem = $request['ISMaxTwoItem'];
					$IsVenderItem = $request["IsVenderItem"];
					$IsCosmo 	 = $request["IsCosmo"];
					$IsNandansons = $request["IsNandansons"];
					$IsPerfumePW  = $request["IsPerfumePW"];
					$IsPCA  	= $request["IsPCA"];
					$IsND  	= $request["IsND"];
					$ISMax2dayVal = $request["ISMax2dayVal"];
					$onlyGCPurchased = $request['onlyGCPurchased'];
				}
				$ship_country = '';
				$ship_zip 	  = '';
				$ship_state	  = '';
				$ship_address1 = '';
				$ship_address2 = '';
				$ship_city = '';

				if(Session::has('ShoppingCart.BillingAddress'))
				{
					if(Session::has('ShoppingCart.BillingAsShipping') && Session::get('ShoppingCart.BillingAsShipping') == "No")
					{
						$Shipping = Session::get('ShoppingCart.ShippingAddress');
						$ship_country = $Shipping["country"];
						$ship_zip 	  = $Shipping["zip"];
						$ship_state	  = $Shipping["state"];
						$ship_address1 = trim($Shipping["address1"]);
						$ship_address2 = trim($Shipping["address2"]);
						$ship_city	   = trim($Shipping["city"]);
					}
					else

					{
						$Billing = Session::get('ShoppingCart.BillingAddress');
						$ship_country = $Billing["country"];
						$ship_zip 	  = $Billing["zip"];
						$ship_state	  = $Billing["state"];
						$ship_address1 = trim($Billing["address1"]);
						$ship_address2 = trim($Billing["address2"]);
						$ship_city	   = trim($Billing["city"]);
					}
				}

				if(isset($request->PageFrom) && $request->PageFrom =='AmazonBilling')
				{
					$ship_state = Session::get('AmazonShipState');
					$ship_zip = Session::get('AmazonShipZip');
					$ship_country = Session::get('AmazonShipCountry');
					if(Session::has('AmazonShipCity'))
					{
						$ship_city = Session::get('AmazonShipCity');
					}
				}

				//if(isset($request->subaction) && $request->subaction =='stripecart')
				if(isset($request->subaction) && ($request->subaction =='stripecart' || $request->subaction =='paypalproductpage' || $request->subaction =='paypalcart'))
				{
					$ShopCartItems = Session::get('ShoppingCart.Cart');
					$TempCart = [];
					$IsMaxaromaTwoDelivery = "No";
					$AllMaxTwoDay = 'No';
					if($request->subaction =='stripecart' ||  $request->subaction =='paypalcart'){
						foreach($ShopCartItems as $ShopItem)
						{
							if((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") || (isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=='Yes') || (isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=='Yes') || (isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=='Yes') || (isset($ShopItem['IsND']) && $ShopItem['IsND']=='Yes') && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
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
							}else{
								$AllMaxTwoDay = "Yes";
							}

							$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$ShopItem);

							//if($ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU1'))
							if($IsGiftCertificateItem == 'No'){
								$onlyGCPurchased = 0;
							}
						}
					}

					if($request->zip != ""){
						$ship_zip = $request->zip;
						$ship_state = $request->state;
						$ship_country = $request->country;
						$ship_address1 = trim($request->address1);
						$ship_address2 = trim($request->address2);
						if(isset($request->city) && $request->city!='')
						{
							$ship_city = trim($request->city);
						}
					} else {
						$normaluser = Auth::user();
						if (Auth::guard('store')->check()) {
							$normaluser = Auth::guard('web')->user();
						}
						if($normaluser)
						{
							$ship_zip = $normaluser->zip; //Auth::user()->zip;
							$ship_state = $normaluser->state; //Auth::user()->state;
							$ship_country = $normaluser->country; //Auth::user()->country;
							$ship_address1 = trim($normaluser->address1); //trim(Auth::user()->address1);
							$ship_address2 = trim($normaluser->address2); //trim(Auth::user()->address2);
						} else {
							$ship_city = "";
							if(isset($request->city) && $request->city!='')
							{
								$ship_city = trim($request->city);
							}

							$ship_zip = "";
							if(isset($request->zip) && $request->zip!='')
							{
								$ship_zip = $request->zip;
							}
							$ship_state = "";
							if(isset($request->state) && $request->state!='')
							{
								$ship_state = trim($request->state);
							}

							$ship_country = "";
							if(isset($request->country) && $request->country!='')
							{
								$ship_country = trim($request->country);
							}

							$ship_address1 = "";
							if(isset($request->address1) && $request->address1!='')
							{
								$ship_address1 = trim($request->address1);
							}
							$ship_address2 = "";
							if(isset($request->address2) && $request->address1!='')
							{
								$ship_address2 = trim($request->address2);
							}
						}
					}
				}

				$ShippingModeRS = ShippingMode::where('status','=','1')->orderBy('display_position','asc')->get();
				$Sess_ShippingInfo = "";
				if(Session::has('ShoppingCart.Shipping'))
					$Sess_ShippingInfo = Session::get('ShoppingCart.Shipping');

				$shipping_mode_idMainArr = $this->CheckAvailableShippingMethod(29, $ship_country,$ship_state,$ship_zip);
				$shipping_mode_idMainArr = explode("###",$shipping_mode_idMainArr);
				$shipping_mode_id =(int) $shipping_mode_idMainArr[0];
				$istwoday = "No";

				if($shipping_mode_id >0 )
				{
					$istwoday = "Yes";
				}
				$AddressCheck = "No";
				$APOFPO = "No";

				if(isset($ship_address1) && $ship_address1!='')
				{
					if(preg_match('/\bP\.?\s*O\.?(\s*B\.?\s*O\.?\s*X|\s*Box|\d+)?\b/i', $ship_address1 ?? ''))
					{
						$istwoday = "No";
						$AddressCheck = "Yes";
					}
				}
				if(isset($ship_address2) && $ship_address2!='')
				{
					if(preg_match('/\bP\.?\s*O\.?(\s*B\.?\s*O\.?\s*X|\s*Box|\d+)?\b/i', $ship_address2 ?? ''))
					{
						$istwoday = "No";
						$AddressCheck = "Yes";
					}
				}
				if(isset($ship_address1) && $ship_address1!='')
				{
					if(preg_match("/apo/i",strtolower($ship_address1)) || preg_match("/fpo/i",strtolower($ship_address1)))
					{
						$APOFPO = "Yes";
					}
				}
				if(isset($ship_address2) && $ship_address2!='')
				{
					if(preg_match("/apo/i",strtolower($ship_address2)) || preg_match("/fpo/i",strtolower($ship_address2)))
					{
						$APOFPO = "Yes";
					}
				}
				if(isset($ship_city) && $ship_city!='')
				{
					if(preg_match("/apo/i",strtolower($ship_city)) || preg_match("/fpo/i",strtolower($ship_city)))
					{
						$APOFPO = "Yes";
					}
				}
				//echo $AddressCheck; exit;

				$count = 0; // This var used for count availabe method
				$Checkcounter = 0;
				$MsgVal=[];
				$ChargeInfo = [];
				$SelShipMethod = 0;
				$shipping_mode_idArr="";
				$Max2Days = 0;
				$MsgSucess = 0;
				$isPickUp = "No";

				for($p=0; $p<count($ShippingModeRS); $p++ )
				{

					if($AddressCheck =="Yes" && $ShippingModeRS[$p]['shipping_mode_id']!=9 && $ShippingModeRS[$p]['shipping_mode_id']!=22)
					{
						continue;
					}

					if($AddressCheck=="Yes" && $ShippingModeRS[$p]['shipping_mode_id']==29)
					{
						continue;
					}

					if($IsVenderItem == "Yes" && $ShippingModeRS[$p]['shipping_mode_id']==46)
					{
						$isPickUp = "No";
						continue;
					}

					if($ShippingModeRS[$p]['shipping_mode_id']==46)
					{
						$isPickUp = "Yes";
					}

					if(($istwoday=="Yes" && $IsMaxaromaTwoDelivery =='Yes' && ($ShippingModeRS[$p]['shipping_mode_id']==22 || $ShippingModeRS[$p]['shipping_mode_id']==34 || $ShippingModeRS[$p]['shipping_mode_id']==29 || $ShippingModeRS[$p]['shipping_mode_id']==46)))
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="No" && $IsMaxaromaTwoDelivery =='Yes' )
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="Yes" && $IsMaxaromaTwoDelivery =='No' &&  $ShippingModeRS[$p]['shipping_mode_id']!=29)
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="No" && $IsMaxaromaTwoDelivery =='No' )
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}

					if(strtolower(Session::get('eusertype') ?? '')=="wholesaler")
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					$normalWeight = 0;
					$lightWeight = 0;
					$heavyWeight = 0;
					$shipping_mode_id = 0;

					if($shipping_mode_idArr != '' && !is_array($shipping_mode_idArr))
					{
						//if(!is_array($shipping_mode_idArr))
						$shipping_mode_idArr = explode("###",$shipping_mode_idArr);
						$shipping_mode_id =(int) $shipping_mode_idArr[0];
						$normalWeight = $shipping_mode_idArr[1];
						$lightWeight = $shipping_mode_idArr[2];
						$heavyWeight = $shipping_mode_idArr[3];
					}
					if($AddressCheck == 'No' && $IsMaxaromaTwoDelivery=="No" && $istwoday=="No" && strtolower(Session::get('eusertype') ?? '') != "wholesaler")
					{
						$MsgVal[] = 'Your order is not eligible for Max2days shipping as one of the item is not eligible.<br/>Since you are shipping to a different address this order is not eligible for Max2days shipping option.';
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					else if($AddressCheck == 'No' && $istwoday=="No" && strtolower(Session::get('eusertype') ?? '') != "wholesaler")
					{
						$MsgVal[] = 'Since you are shipping to a different address this order is not eligible for Max2days shipping option.';
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					else if($AddressCheck == 'No' && $IsMaxaromaTwoDelivery=="Yes" && $ISMaxTwoItem == 'Yes' && isset($ISMax2dayVal) && $ISMax2dayVal=="No" && strtolower(Session::get('eusertype') ?? '') != "wholesaler"  && $IsVenderItem=="No")
					{
						$MsgVal[] = 'Great News, Your order was Upgraded to Free Second Day Shipping Service.';
						$MsgSucess =1;
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					else if($AddressCheck == 'No' && $IsMaxaromaTwoDelivery=="Yes" && $ISMaxTwoItem == 'Yes' && isset($ISMax2dayVal) && $ISMax2dayVal=="No" && strtolower(Session::get('eusertype') ?? '') != "wholesaler"  && $IsVenderItem=="Yes")
					{
						$MsgVal[] = 'Great News, Your order was Upgraded to Free Second Day Shipping Service.Order was Upgraded to Free 2DAY Shipping Service, Please add 2 Extra Business days because of some items in your cart.';
						$MsgSucess =1;
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					if($AddressCheck == 'Yes' && $shipping_mode_id == 22)
					{
						$MsgVal[] = 'Your order is not eligible for Max2days shipping because our carrier does not ship using this service to PO BOX Addresses';
						//continue;
					}
					if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
					{

						$paypal_subtotal = "";
						$paypal_prodqty = 0;
						if(isset($request->subaction) && $request->subaction == 'paypalproductpage' && isset($request->paypal_total_price) && $request->paypal_total_price != '')
						{
							$paypal_subtotal = $request->paypal_total_price;
							$paypal_prodqty = isset($request->paypal_prodqty) ? $request->paypal_prodqty : 1;
							$paypal_productid = isset($request->paypal_productid) ? $request->paypal_productid : 0;

							$ProductChkStock = $this->ProductCheckInStock($paypal_productid, $paypal_prodqty,"insert");
							$ProductRs = $ProductChkStock['ProdInfo'];

							if($ProductRs->WebsiteStock == "Out")
							{
								if($ProductRs->cosmo_sku!='' &&  $ProductRs->cosmo_current_stock > 0 &&  $ProductRs->cosmo_our_price > 0)
								{
									$IsCosmo = "Yes";
									$VendorSKU = $ProductRs->cosmo_sku;
								}
								else if($ProductRs->pca_sku!='' &&  $ProductRs->pca_current_stock > 0 && $ProductRs->pca_our_price > 0)
								{
									$IsPCA  = "Yes";
									$VendorSKU = $ProductRs->pca_sku;
								}
								else if($ProductRs->nandansons_sku!='' &&  $ProductRs->nandansons_current_stock > 0 && $ProductRs->nandansons_our_price > 0)
								{
									$IsNandansons = "Yes";
									$VendorSKU = $ProductRs->nandansons_sku;
								}
								else if($ProductRs->perfumeworldwide_sku!='' &&  $ProductRs->perfumeworldwide_currentstock > 0 && $ProductRs->perfumeworldwide_our_price > 0)
								{
									$IsPerfumePW = "Yes";
									$VendorSKU = $ProductRs->perfumeworldwide_sku;
								}
								else if($ProductRs->nd_sku!='' &&  $ProductRs->nd_current_stock > 0 && $ProductRs->nd_our_price > 0)
								{
									$IsND = "Yes";
									$VendorSKU = $ProductRs->nd_sku;
								}

							}

							if($ProductRs->WebsiteStock == "In"){
								$IsMaxaromaTwoDelivery	= $ProductRs->maxtwodaydelivery;
								$ISMaxTwoItem = (isset($ProductRs->maxtwodaydelivery) && $ProductRs->maxtwodaydelivery!='' &&$ProductRs->maxtwodaydelivery=='Yes') ? 'Yes' : 'No';
								$ISMax2dayVal = $ISMaxTwoItem;
							}

							if(isset($ProductRs->IsDealProducts) && $ProductRs->IsDealProducts == "Yes")
							{
								$IsMaxaromaTwoDelivery = 'No';
							}

							$IsPerfumePW  = "";

							if(($IsCosmo == "Yes" || $IsNandansons  == "Yes" || $IsPCA  == "Yes" || $IsPerfumePW == "Yes" || $IsND == "Yes") && $VendorSKU != ''){
								$IsVenderItem = "Yes";
							}

							//$onlyGCPurchased = $request['onlyGCPurchased'];
						}
						//Log::info('CalculateAvailableShippingCharge: ship_zip - '.$ship_zip.' --ship_state -'.$ship_state.'--ship_country'.$ship_country.'--shipping_mode_id'.$shipping_mode_id.'--paypal_subtotal'.$paypal_subtotal);

						$tempChargeStr = $this->CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id,$paypal_subtotal,$paypal_prodqty);
						$tempChargeArr = explode("###",$tempChargeStr);

						$tempCharge = $tempChargeArr[0];
						$days		= $tempChargeArr[1];
						if(!Session::has('isPhoneOrder'))
						{
							if(Session::get('ShoppingCart.Shipping.ShippingMethodID') > 0 && isset($request->PageFrom) && $request->PageFrom=='amazon_billing')
							{
							$IsCosmo 	 	= $request["IsCosmo"];
							$IsNandansons	= $request["IsNandansons"];
							$IsPerfumePW  	= $request["IsPerfumePW"];
							$IsPCA  		= $request["IsPCA"];
							$IsND  			= $request["IsND"];

							}
						}

						$VendorDays = 0;

						if(($IsVenderItem=="Yes" && $IsPerfumePW=="Yes"))
						{
							$days		= $tempChargeArr[1];
							$days = $days + 3;
							$VendorDays = 3;
						}
						else if(($IsVenderItem=="Yes" && $IsCosmo=="Yes") || ($IsVenderItem=="Yes" && $IsPCA=="Yes") || ($IsVenderItem=="Yes" && $IsNandansons=="Yes") || ($IsVenderItem=="Yes" && $IsND=="Yes"))
						{

							$days		= $tempChargeArr[1];
							$days = $days + 3;
							$VendorDays = 3;
						}
						$ShippingModeRS[$p]['days']	= $days;
						$DayVal = date("H@@a");
						$DayValArr = explode("@@",$DayVal);
						$DaynameVal = date("l");
						if($DayValArr[1] == "pm" && isset($DaynameVal) && $DaynameVal!='Sunday' && $DaynameVal!='Saturday')
						{
						   if($DayValArr[0] >=14)
						   {
							   $ShippingModeRS[$p]['days'] = $ShippingModeRS[$p]['days'] + 1;
						   }
						}

						if(isset($shipping_mode_id) && ($shipping_mode_id == 33 || $shipping_mode_id == 34) && ($DaynameVal=="Saturday" || $DaynameVal=="Sunday"))
						{
							$ShippingModeRS[$p]['days'] = $ShippingModeRS[$p]['days'] + 1;
						}

						$normalPWeight = 0;
						$lightPWeight = 0;
						$heavyPWeight = 0;
						$CartArr = Session::get('ShoppingCart.Cart');

						if($paypal_subtotal == ""){
							for($t=0;$t<count($CartArr);$t++)
							{
								if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Normal" && $normalWeight > 0)
								{
									$normalPWeight = $normalPWeight + ($normalWeight * $CartArr[$t]["Qty"] );
								}
								if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Light" && $lightWeight > 0)
								{
									$lightPWeight = $lightPWeight + ($lightWeight * $CartArr[$t]["Qty"] );
								}
								if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Heavy" && $heavyWeight > 0)
								{
									$heavyPWeight = $heavyPWeight + ($heavyWeight * $CartArr[$t]["Qty"] );
								}
							}
						}

						$tempCharge = $tempCharge + $normalPWeight + $lightPWeight + $heavyPWeight;

						$charge_str = '';
						if($tempCharge>0)
						{
							$charge_str = Price($tempCharge,true);
						}

						if(empty(Session::get('ShoppingCart.Shipping.ShippingMethodID')))
						{
							 if($shipping_mode_id==29)
							 {
								$r_sel = " checked ";
								$r_sel_box = 'active';
							 }
							 else if($count==0)
							 {
								$r_sel = " checked ";
								$r_sel_box = 'active';
							 }
							else
							{
								$r_sel = "";
								$r_sel_box = '';
							}
						}
						else
						{

							if(Session::get('ShoppingCart.Shipping.ShippingMethodID')==$ShippingModeRS[$p]['shipping_mode_id'])
							{
								$r_sel = " checked ";
								$r_sel_box = 'active';
								$SelShipMethod = Session::get('ShoppingCart.Shipping.ShippingMethodID');
							}
							else
							{
								$r_sel = "";
								$r_sel_box = '';
							}
						}
						$estimateShipDate='';
						$DateFieldVal = '';
						$EstimatedDeliveryDate = '';
						$DateSuffix = '';
						$DayNewValOf = '';

						if($ShippingModeRS[$p]['days']!='')
						{
							if($ShippingModeRS[$p]['days']==0)
							{
								$estimateShipDate='';
								$EstimatedDeliveryDate = '';
								$DateFieldVal = '';
								$DateSuffix = '';
								$DayNewValOf = '';
							}
							else
							{
								$holiday_day_arr = ShippingHoliday::where('holiday_status','=','1')->where('holiday_date','>',date("Y-m-d"))->get();

								$holiday_day = $holiday_day_arr->count();
								$HolidayArrVal = array();

								foreach($holiday_day_arr as $HolidayVal)
								{

									$HolidayArrVal[] = $edate = date('Y-m-d', strtotime($HolidayVal->holiday_date));

								}
								$k=$ShippingModeRS[$p]['days'];

								for($d=1;$d<=$k;$d++)
								{

									$edate = date('Y-m-d', strtotime("+" . $d . "days"));

									$daynew = $this->checkday($edate);
									if ($daynew == 'saturday' || $daynew == 'sunday')
									{
										$k++;
									}
									else if(in_array($edate,$HolidayArrVal))
									{
										$k++;

									}

								}

							//	echo "<pre>"; print_r($ChargeInfo); exit;
								$dt_date =  date('M d', strtotime($edate));

								$estimateShipDate='Estimated Delivery on or before <b>'.$dt_date.'</b>';
								$EstimatedDeliveryDate =  $edate;
							}
						}else
						{
							$estimateShipDate='';
							$DateFieldVal = '';
							$EstimatedDeliveryDate = '';
							$DateSuffix = '';
							$DayNewValOf = '';
						}
						$Checkcounter = 1;

						if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($ShippingModeRS[$p]['shipping_mode_id'],Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
						{
							$charge_str = '';
						}

						if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $ShippingModeRS[$p]['shipping_mode_id'] == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
						{
							$charge_str = '';
						}

						if($r_sel_box == 'active' && $IsVenderItem == 'Yes')
						{

							$dt_date =  date('m/d', strtotime("+".$VendorDays. "days"));
							$VendorPopup = str_replace('{$daysval}',$dt_date,config('Settings.VENDORITEM_POPUP_WINDOW'));
							$VendorPopup = str_replace('{$days}',$VendorDays,$VendorPopup);

							//echo $VendorPopup; exit;
							$this->PageData['VendorPopup'] = $VendorPopup;
						}

						if($ShippingModeRS[$p]['shipping_mode_id'] == 29 && $tempCharge <= 0)
						{
							$Max2Days = 1;
						}

						if(empty($EstimatedDeliveryDate) || $EstimatedDeliveryDate=='')
						{
							$EstimatedDeliveryDate = date("Y-m-d");
						}

						if($charge_str!='')
						{
							$ChargeInfo[] = [
								'active' => $r_sel_box,
								'days' => $days,
								'charge' => $this->Make_Price($tempCharge,true),
								'chargewithoutformat' => $tempCharge,
								'checked' => $r_sel,
								'shipping_mode_id' => $ShippingModeRS[$p]['shipping_mode_id'],
								//'display_date' => $DateFieldVal.'<sup>'.$DateSuffix.'</sup> '.$DayNewValOf,
								'display_date' => date('D, F d',strtotime($EstimatedDeliveryDate)),
								'estdate' =>  date('m/d/Y',strtotime($EstimatedDeliveryDate)),
								'method_name' => $ShippingModeRS[$p]['type'],
								'charge_str' => $charge_str,
								'estimateShipDate' => $estimateShipDate,
								'dateSort' => $EstimatedDeliveryDate
							];
						}
						else
						{
							$ChargeInfo[] = [
								'active' => $r_sel_box,
								'checked' => $r_sel,
								'charge' => 0,
								'chargewithoutformat' => 0,
								'days' => $days,
								'shipping_mode_id' => $ShippingModeRS[$p]['shipping_mode_id'],
								//'display_date' => $DateFieldVal.'<sup>'.$DateSuffix.'</sup> '.$DayNewValOf,
								'display_date' => date('D, F d',strtotime($EstimatedDeliveryDate)),
								'estdate' =>  date('m/d/Y',strtotime($EstimatedDeliveryDate)),
								'method_name' => $ShippingModeRS[$p]['type'],
								'charge_str' => 'Free',
								'estimateShipDate' => $EstimatedDeliveryDate,
								'dateSort' => 0
							];
						}
						$count = $count +1;
					}
					else
					{
						continue;
					}
				}

				if(count($ChargeInfo)>0)
				{
					$NewMethods = [];
					foreach($ChargeInfo as $CheckForMaxDay)
					{
						if($Max2Days == 1 && $CheckForMaxDay['shipping_mode_id'] == 22)
						{
							continue;
						}
						if($CheckForMaxDay['shipping_mode_id']==22 && $isPickUp=="Yes")
						{
							$CheckForMaxDay['dateSort'] = '-1';

						}
						$NewMethods[]=$CheckForMaxDay;
					}
					$ChargeInfo = $NewMethods;
				}

				$shipping_insurance_checked = 'checked="checked"';
				$shipping_insurance_widget_checked = 'data-default-checked="true"';
				$shipping_signature_css = 'style="display:none;"';

				if(Session::has('ShoppingCart.Shipping.ShippingMethodID') && Session::get('ShoppingCart.Shipping.ShippingMethodID') == 46)
				{
					$shipping_insurance_checked = '';
					$shipping_insurance_widget_checked = 'data-default-checked="false"';
					$shipping_signature_css = '';
				}

				if(Session::has('shipping_insurance') && Session::get('shipping_insurance') == "N")
				{
					$shipping_insurance_checked = '';
					$shipping_insurance_widget_checked = 'data-default-checked="false"';
					$shipping_signature_css = '';
				}
				$shipping_insurance_charge = 0;
				if(Session::has('shipping_insurance_charge') && Session::get('shipping_insurance_charge') !=""){
					$shipping_insurance_charge = Session::get('shipping_insurance_charge');
				}

				$ShippingSignatureInfo = [];
				if($Checkcounter==1)
				{
					if(config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') == "Yes" && Session::get('eusertype') == "Wholesaler" && Session::get('etype') == "M" && $ship_country=="US")
					{
						/*$ShippingSignatureInfo[]= '
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
							<label style="padding-left:0px; vertical-align:top;" class="fsbold">$'.config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE').' Request Signature &nbsp;&nbsp;</label>
							<label class="switch">
								<input type="checkbox"  value="Yes" name="shipping_signature" id="shipping_signature" >
								<span class="slider round text_off" id="slider_round">Off</span>
							</label>
						</div>';*/
						$ShippingSignatureInfo[]='
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
						<span>$'.config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE').' Request Signature</span>
						<label class="switch" id="insurance">
							<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature">
							<span class="slider round"></span>
						</label>
						</div>
						';
					}
					if(config('Settings.SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') !="Yes" && $ship_country=="US")
					{
						/*$ShippingSignatureInfo[]= '
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
							<label class="fsbold" style="padding-left:0px; vertical-align:top;">$'.config('Settings.SHIPPING_SIGNATURE').' Request Signature &nbsp;&nbsp;</label><label class="switch">
								<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature" ">
								<span class="slider round text_off" id="slider_round">Off</span>
							</label>
						</div>';*/
						$ShippingSignatureInfo[]='
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
						<span>$'.config('Settings.SHIPPING_SIGNATURE').' Request Signature</span>
						<label class="switch" id="insurance">
							<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature">
							<span class="slider round"></span>
						</label>
						</div>
						';
					}
				}

				$RouteWidget ='<div id="RouteWidget" '.$shipping_insurance_widget_checked.'></div><input type="checkbox" value="Yes" name="shipping_insurance" '.$shipping_insurance_checked.' style="display:none;" id="shipping_insurance" ><input type="hidden" id="shipping_insurance_charge" value="'.$shipping_insurance_charge.'">';
				if(count($ChargeInfo) > 0)
				{
					/*if($IsVenderItem=="Yes" && config('Settings.VENDORITEM_POPUP_WINDOW') !='')
					{
						$RouteWidget.=' <a href="Javascript:void(0);" onclick="SetPaymentMethods();" data-target="#myModalPopUp" data-toggle="modal" class="button btn-1 btn-medium">Continue</a>';
					}
					else
					{
						$RouteWidget.=' <a href="Javascript:void(0);" onclick="SetPaymentMethods();"  class="button btn-1 btn-medium">Continue</a>';
					}*/
				}

				if($Checkcounter == 0)
				{
					$ChargeInfo = [];
				}else {
					$sortDates = array_column($ChargeInfo, 'dateSort');
					array_multisort($sortDates, SORT_ASC, $ChargeInfo);
				}

				$APOFPOArr = array();
				if(isset($APOFPO) &&  $APOFPO=="Yes" && count($ChargeInfo) > 0)
				{
					$APOFPOArr = $ChargeInfo;

					foreach($APOFPOArr as $key => $value)
					{
						if(isset($value["shipping_mode_id"]) && $value["shipping_mode_id"] == 47)
						{
							$ChargeInfo = array();
							$ChargeInfo[] = $value;
							Session::put('ShoppingCart.Shipping.ShippingMethodID',47);
						}
						else
						{
							continue;
						}
					}
				}

				if(count($ChargeInfo) > 0)
				{

					if($SelShipMethod == 0 || $SelShipMethod==''){
						Session::put('ShoppingCart.EstimatedDeliveryDate',$ChargeInfo[0]['estdate']);
						Session::put('ShoppingCart.Shipping.ShippingMethodName',$ChargeInfo[0]['method_name']);
						Session::put('ShoppingCart.Shipping.ShippingMethodID',$ChargeInfo[0]['shipping_mode_id']);
						Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ChargeInfo[0]['days']);
						Session::put('ShoppingCart.Shipping.ShippingDays',$ChargeInfo[0]['estimateShipDate']);
						Session::put('ShoppingCart.Shipping.ShippingCharge',NumberFormat($ChargeInfo[0]['chargewithoutformat']));
						$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
					} else {
						foreach($ChargeInfo as $key => $ShipCharge)
						{

							if(Session::get('ShoppingCart.Shipping.ShippingMethodID')==$ShipCharge['shipping_mode_id'])
							{
								Session::put('ShoppingCart.EstimatedDeliveryDate',$ShipCharge['estdate']);
								Session::put('ShoppingCart.Shipping.ShippingMethodName',$ShipCharge['method_name']);
								Session::put('ShoppingCart.Shipping.ShippingMethodID',$ShipCharge['shipping_mode_id']);
								Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ShipCharge['days']);
								Session::put('ShoppingCart.Shipping.ShippingDays',$ShipCharge['estimateShipDate']);
								Session::put('ShoppingCart.Shipping.ShippingCharge',NumberFormat($ShipCharge['chargewithoutformat']));
								$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
							}
							else if($key == 0 && empty(Session::get('ShoppingCart.Shipping.ShippingMethodID')))
							{
								Session::put('ShoppingCart.EstimatedDeliveryDate',$ShipCharge['estdate']);
								Session::put('ShoppingCart.Shipping.ShippingMethodName',$ShipCharge['method_name']);
								Session::put('ShoppingCart.Shipping.ShippingMethodID',$ShipCharge['shipping_mode_id']);
								Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ShipCharge['days']);
								Session::put('ShoppingCart.Shipping.ShippingDays',$ShipCharge['estimateShipDate']);
								Session::put('ShoppingCart.Shipping.ShippingCharge',NumberFormat($ShipCharge['chargewithoutformat']));
								$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
							}
						}
					}
				}

				$CurrDate = date('Y-m-d');
				$CurrDayVal = date("H@@a");
				$CurrDayValArr = explode("@@",$CurrDayVal);
				$this->PageData['datediff'] = '';
				if($CurrDayValArr[1] == "pm")
				{
					if($CurrDayValArr[0] >=14)
					{
						$NewCurrDate = date_create(date('Y-m-d H:i:s'));
						$NewDate = date_create(date('Y-m-d H:i:s', strtotime(date('Y-m-d 14:00:00') . ' +1 day')));
						$DateDiff = $NewCurrDate->diff($NewDate);

						if($DateDiff->format('%h') > 0)
							$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
						else
							$this->PageData['datediff'] = $DateDiff->format("%i minutes");
					} else {
						$NewCurrDate = date_create(date('Y-m-d H:i:s'));
						$NewDate = date_create(date('Y-m-d 14:00:00'));
						$DateDiff = date_diff($NewCurrDate,$NewDate);

						if($DateDiff->format('%h') > 0)
							$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
						else
							$this->PageData['datediff'] = $DateDiff->format("%i minutes");
					}
				} else {
					$NewCurrDate = date_create(date('Y-m-d H:i:s'));
					$NewDate = date_create(date('Y-m-d 14:00:00'));
					$DateDiff = date_diff($NewCurrDate,$NewDate);

					if($DateDiff->format('%h') > 0)
						$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
					else
						$this->PageData['datediff'] = $DateDiff->format("%i minutes");
				}

				$this->PageData['ShippingMessage'] = array_unique($MsgVal);
				$this->PageData['MsgSucess'] = $MsgSucess;

				$this->PageData['ShippingSignatureInfo'] = $ShippingSignatureInfo;
				$this->PageData['RouteWidget'] = $RouteWidget;
				$this->PageData['ShippingMethods'] = $ChargeInfo;
			}
			$this->PageData['OnlyHead'] = $request->OnlyHead;

			if(empty($request->FirstStepGpay) && isset($request->subaction) && $request->subaction!='paypalcart')
			{
				$this->SetShippingInsuranceCharge('remove');
			}
			if(empty($request->subaction))
			{
				$this->SetShippingInsuranceCharge('add');
			}
			else if(isset($request->subaction) && isset($request->isLastNew) && $request->isLastNew=='isLastNew')
			{
				$this->SetShippingInsuranceCharge('add');
			}
			$this->SetupCart();
			$ShippingInsuranceCharge = (Session::has('shipping_insurance_charge')) ? Session::get('shipping_insurance_charge'):0;
			$ShippingSignature = (Session::has('shipping_signature')) ? Session::get('shipping_signature'):0;
			$InsureAmount = $this->GetNetTotal() - $ShippingInsuranceCharge - $ShippingSignature;

			//if(isset($request->subaction) && $request->subaction=='stripecart' && empty($request->isLastNew) && empty($request->FirstStepGpay))
			if(isset($request->subaction) && ($request->subaction=='stripecart' || $request->subaction=='paypalproductpage') && empty($request->isLastNew) && empty($request->FirstStepGpay))
			{
				$this->SetShippingInsuranceCharge('remove');
			}
			if($InsureAmount > 200)
			{
				Session::forget('ShoppingCart.ShippingSignature');
			}

			$this->PageData['InsureAmount'] = $this->GetNetTotal() - $ShippingInsuranceCharge - $ShippingSignature;

			if(isset($request["IsMaxaromaTwoDelivery"]) && $request["IsMaxaromaTwoDelivery"]!='')
			{
				Session::put('ShoppingCart.IsMaxaromaTwoDelivery',$request["IsMaxaromaTwoDelivery"]);
			}
			if(isset($request["ISMaxTwoItem"]) && $request["ISMaxTwoItem"]!='')
			{
				Session::put('ShoppingCart.ISMaxTwoItem',$request["ISMaxTwoItem"]);
			}
			if(isset($request["IsVenderItem"]) && $request["IsVenderItem"]!='')
			{
				Session::put('ShoppingCart.IsVenderItem',$request["IsVenderItem"]);
			}
			if(isset($request["IsCosmo"]) && $request["IsCosmo"]!='')
			{
				Session::put('ShoppingCart.IsCosmo',$request["IsCosmo"]);
			}
			if(isset($request["IsNandansons"]) && $request["IsNandansons"]!='')
			{
				Session::put('ShoppingCart.IsNandansons',$request["IsNandansons"]);
			}
			if(isset($request["IsPerfumePW"]) && $request["IsPerfumePW"]!='')
			{
				Session::put('ShoppingCart.IsPerfumePW',$request["IsPerfumePW"]);
			}
			if(isset($request["IsND"]) && $request["IsND"]!='')
			{
				Session::put('ShoppingCart.IsND',$request["IsND"]);
			}
			if(isset($request["IsPCA"]) && $request["IsPCA"]!='')
			{
				Session::put('ShoppingCart.IsPCA',$request["IsPCA"]);
			}
			if(isset($request["ISMax2dayVal"]) && $request["ISMax2dayVal"]!='')
			{
				Session::put('ShoppingCart.ISMax2dayVal',$request["ISMax2dayVal"]);
			}
			if(isset($request["onlyGCPurchased"]) && $request["onlyGCPurchased"]!='')
			{
				Session::put('ShoppingCart.onlyGCPurchased',$request["onlyGCPurchased"]);
			}
			if(Session::get('ShoppingCart.Shipping.ShippingMethodID')> 0 && $request->PageFrom!='amazon_billing')
			{
				Session::put('ShoppingCart.action',"shippinginfo");
				Session::put('ShoppingCart.OnlyHead',0);
			}

			//if(isset($request->subaction) && $request->subaction =='stripecart')
			if(isset($request->subaction) && ($request->subaction =='stripecart' || $request->subaction =='paypalcart' ||$request->subaction =='paypalproductpage'))
			{
				$shipping_mode_tmp_arr = [];
				$chkActive = 'N';
				$chkPaypalActive = 'N';
				$MtVal = false;

				foreach($ChargeInfo as $ckey => $SMethod)
				{
					$MtVal = false;
					if($request->subaction =='stripecart'){
						$shipping_mode_tmp_arr[] = array(
							'id'=>(string)$SMethod['shipping_mode_id'],
							'label'=>strip_tags($SMethod['method_name']),
							'detail'=>$SMethod['display_date'],
							'amount'=>round($SMethod['chargewithoutformat']*100),
						);
					}
					if($request->subaction =='paypalproductpage' ){
						if($chkActive == 'N'){
							$chkActive = $r_sel_box == 'active' ? 'Y' : 'N';
						}
						$amount_arr['value'] = (string)$SMethod['chargewithoutformat'];//round($SMethod['chargewithoutformat']*100);
						$amount_arr['currency_code'] = 'USD';
						$shipping_mode_tmp_arr[] = array(
							'id'=>(string)$SMethod['shipping_mode_id'],
							'label'=>strip_tags($SMethod['method_name'])."-".$SMethod['display_date'],
							'selected' => $r_sel_box == 'active' ? true : false,
							'type' => 'SHIPPING',
							//'detail'=>$SMethod['display_date'],
							'amount'=>$amount_arr,
						);
					}

					if($request->subaction =='paypalcart' ){

						if(Session::get('ShoppingCart.Shipping.ShippingMethodID')==$SMethod['shipping_mode_id'])
						{
						   $chkPaypalActive ='Y';
						   	$MtVal = true;
						}
						$amount_arr['value'] = (string)$SMethod['chargewithoutformat'];//round($SMethod['chargewithoutformat']*100);
						$amount_arr['currency_code'] = 'USD';
						$shipping_mode_tmp_arr[] = array(
							'id'=>(string)$SMethod['shipping_mode_id'],
							'label'=>strip_tags($SMethod['method_name'])."-".$SMethod['display_date'],
							'selected' => $MtVal,
							'type' => 'SHIPPING',
							//'detail'=>$SMethod['display_date'],
							'amount'=>$amount_arr,
						);
					}

				}

				if($chkActive == 'N' && $request->subaction =='paypalproductpage') {
						$shipping_mode_tmp_arr[0]['selected'] = true;
					}
				if($chkPaypalActive == 'N' && $request->subaction =='paypalcart' && count($shipping_mode_tmp_arr) > 0) {

						$shipping_mode_tmp_arr[0]['selected'] = true;
					}

				return $shipping_mode_tmp_arr;
			}
			/*$GA4 = "";
			$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');
			if($onlyGCPurchased==0)
			{
				$GA4 = googleAnalyticsGA4("ShippingMethods",Session::get('ShoppingCart.Cart'),$this->GetNetTotal(), $this->GetAllCoupons('CouponCode'));
			}
			$this->PageData['GA4'] = $GA4;
			*/
			if(isset($request->PageFrom) && $request->PageFrom == 'amazon_billing')
			{
				$ShipMethodsHtml = view('checkout.shipping-methods')->with($this->PageData)->render();
				$CheckoutBoxHTML = view('checkout.subtotalbox')->with($this->PageData)->render();
				return response()->json(['ShipMethodsHtml' => $ShipMethodsHtml, 'CheckoutBoxHTML' => $CheckoutBoxHTML]);
			} else {
				$this->PageData['Shipping'] = Session::get('ShoppingCart.ShippingAddress');

				return view('checkout.shipping-methods-page')->with($this->PageData);
			}
		}

	}

	public function SetShippingInsurance(Request $request)
	{
		if(isset($request->action) && $request->action == 'setshippinginsurance')
		{
			/*if($request->ShippingInsuranceCharge == '')
				Session::forget('shipping_insurance_charge');
			else
				Session::put('shipping_insurance_charge',NumberFormat($request->ShippingInsuranceCharge));
			*/
			/*if($request->shipping_signature == 'Yes')
				Session::put('ShoppingCart.ShippingSignature',config('Settings.SHIPPING_SIGNATURE'));
			else
				Session::forget('ShoppingCart.ShippingSignature');*/

			if($request->shipping_signature != '' && $request->shipping_signature != '0')
			{
				Session::put('ShoppingCart.ShippingSignature',$request->shipping_signature);
			} else {
				Session::forget('ShoppingCart.ShippingSignature');
			}
			$this->SetShippingInsuranceCharge($request->subaction);
			$this->SetupCart();
			return view('checkout.subtotalbox')->with($this->PageData)->render();
		}
	}

    public function SetCreditLimit(Request $request)
    {
        if($request->ajax())
		{
			//if(isset($request->action) && $request->action == 'setcreditlimit'){
				$check = $request->check;
				$this->ApplyCreditDiscount($check);
				$ship_country = Session::get('ShoppingCart.ShippingAddress.country');
				$ship_state = Session::get('ShoppingCart.ShippingAddress.state');
				$ship_zip = Session::get('ShoppingCart.ShippingAddress.zip');
				$ship_city = Session::get('ShoppingCart.ShippingAddress.city');
				$onlyGCPurchased = $request->onlyGCPurchased;
				$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
				if($request->ShipInsCharge == 'yes')
				{
					$this->SetShippingInsuranceCharge('remove');
					$this->SetShippingInsuranceCharge('add');
				}
				$this->SetupCart();
				$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
				$this->PageData['CreditDiscount'] = $CreditDiscount;
				$CreditData = $this->GetCreditLimitAmount();
				if($check == 1)
					$RemainCreditLimit = $CreditData['RemainCreditLimit'];
				else
					$RemainCreditLimit = $CreditData['CreditLimit'];
				$CheckoutBoxHTML = view('checkout.subtotalbox')->with($this->PageData)->render();
				$CreditLimitBoxHTML = view('checkout.credit-limit')->with($this->PageData)->render();
				$NetTotal = $this->GetNetTotal();
				return response()->json(['CheckoutBoxHTML' => $CheckoutBoxHTML, 'CreditLimitBoxHTML' => $CreditLimitBoxHTML,'UnformatedRemainCreditLimit' => $RemainCreditLimit,'RemainCreditLimit' => Price($RemainCreditLimit), 'NetTotal' => $NetTotal]);
			//}
		}
    }

	public function PaymentMethods(Request $request)
	{

		if(!Session::has('ShoppingCart.Cart') || count(Session::get('ShoppingCart.Cart')) == 0)
			return redirect('/shoppingcart');
		addLog('PaymentMethodsStart');

		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$stringData = '';
			$fh = fopen($myFile, 'a+');
			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate PaymentMethod Session Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
			}
			if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Place order start Session User Email : ".Session::get('sess_useremail')." :\n";
			}
			fwrite($fh, $stringData);
			fclose($fh);
		}

		$skrSKU= $this->OutOfStockItemsRemove();

		if(count($skrSKU) > 0)
		{
			$err_msg = "Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			$log['err_msg'] = $err_msg;
			addLog('PaymentMethods',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/shoppingcart');
		}

		//echo "<pre>"; print_r(Session::get('ShoppingCart.Shipping')); exit;
        if($request->isMethod('GET') && $request->onlyGCPurchasedVal==0)
            return redirect('checkout');

		$this->PageData = [];
        $this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];
		$this->PageData['JSFILES'] = ['billing.js'];
		$Views = [];
		//echo Session::get('ShoppingCart.Shipping.ShippingDays'); exit;
		$this->SetCheckoutCommonDetails($request);
		$this->PageData['shipping_signature']  = "No";

		if(isset($request->shipping_signature))
		{
			$this->PageData['shipping_signature'] = $request->shipping_signature;
			$log['shipping_signature'] = $request->shipping_signature;
		}

		$CurrDate = date('Y-m-d');
		$CurrDayVal = date("H@@a");
		$CurrDayValArr = explode("@@",$CurrDayVal);
		$this->PageData['datediff'] = '';
		if($CurrDayValArr[1] == "pm")
		{
			if($CurrDayValArr[0] >=14)
			{
				$NewCurrDate = date_create(date('Y-m-d H:i:s'));
				$NewDate = date_create(date('Y-m-d H:i:s', strtotime(date('Y-m-d 14:00:00') . ' +1 day')));
				$DateDiff = $NewCurrDate->diff($NewDate);

				if($DateDiff->format('%h') > 0)
					$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
				else
					$this->PageData['datediff'] = $DateDiff->format("%i minutes");
			} else {
				$NewCurrDate = date_create(date('Y-m-d H:i:s'));
				$NewDate = date_create(date('Y-m-d 14:00:00'));
				$DateDiff = date_diff($NewCurrDate,$NewDate);

				if($DateDiff->format('%h') > 0)
					$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
				else
					$this->PageData['datediff'] = $DateDiff->format("%i minutes");
			}
		} else {
			$NewCurrDate = date_create(date('Y-m-d H:i:s'));
			$NewDate = date_create(date('Y-m-d 14:00:00'));
			$DateDiff = date_diff($NewCurrDate,$NewDate);

			if($DateDiff->format('%h') > 0)
				$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
			else
				$this->PageData['datediff'] = $DateDiff->format("%i minutes");
		}

		if($request->ajax())
		{
			if(isset($request->action) && $request->action == 'setcreditlimit')
			{
				$log['ajax_request'] = $request->action;
				addLog('PaymentMethods',$log);
				$check = $request->check;
				$this->ApplyCreditDiscount($check);
				$ship_country = Session::get('ShoppingCart.ShippingAddress.country');
				$ship_state = Session::get('ShoppingCart.ShippingAddress.state');
				$ship_zip = Session::get('ShoppingCart.ShippingAddress.zip');
				$ship_city = Session::get('ShoppingCart.ShippingAddress.city');
				$onlyGCPurchased = $request->onlyGCPurchased;
				$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
				if($request->ShipInsCharge == 'yes')
				{
					$this->SetShippingInsuranceCharge('remove');
					$this->SetShippingInsuranceCharge('add');
				}
				$this->SetupCart();
				$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
				$this->PageData['CreditDiscount'] = $CreditDiscount;
				$CreditData = $this->GetCreditLimitAmount();
				if($check == 1)
					$RemainCreditLimit = $CreditData['RemainCreditLimit'];
				else
					$RemainCreditLimit = $CreditData['CreditLimit'];
				$CheckoutBoxHTML = view('checkout.subtotalbox')->with($this->PageData)->render();
				$CreditLimitBoxHTML = view('checkout.credit-limit')->with($this->PageData)->render();
				$NetTotal = $this->GetNetTotal();

				$log['NetTotal'] = $this->GetNetTotal();
				$log['UnformatedRemainCreditLimit'] = $RemainCreditLimit;
				addLog('PaymentMethods',$log);

				return response()->json(['CheckoutBoxHTML' => $CheckoutBoxHTML, 'CreditLimitBoxHTML' => $CreditLimitBoxHTML,'UnformatedRemainCreditLimit' => $RemainCreditLimit,'RemainCreditLimit' => Price($RemainCreditLimit), 'NetTotal' => $NetTotal]);
			}
		}
		if(isset($request->action) && $request->action == 'paymentinfo' || (isset($request->takeaction) && $request->takeaction=="TakeAction"))
		{

			$log['ajax_request'] = $request->action;
			addLog('PaymentMethods',$log);
			$this->PageData['SelMethod'] = '';
			$this->PageData['is_paypal'] = 'no';
			$this->PageData['is_afterpay'] = 'no';
			$this->PageData['SelPayMethod'] = (isset($request->SelPayMethod)?$request->SelPayMethod:'');
			if($request->OnlyHead == '0' || $request->OnlyHeadval=='0')
			{
				$Billing  = Session::get('ShoppingCart.BillingAddress');
				$this->PageData['Billing'] = $Billing;
				$MethodNoShow = "No";
				$onlyAmazonPaypal = 0;
				$allowpaymentoption = ['PAYMENT_STRIPE','PAYMENT_MOC'];
				$OrderTotal = NumberFormat($this->GetNetTotal()) * 100;
				$IsPaypalExpressCheckout ='No';
				$Amazon_pay_Checkout ='No';
				$PaymentMethods =  PaymentMethod::where('pm_status','=','Active')->get();
				$log['PaymentMethods'] = json_encode($PaymentMethods);
				addLog('PaymentMethods',$log);
				if((isset($request->SelPayMethod) && $request->SelPayMethod == 'paypal') || (Session::has('PayPalToken') && Session::get("PayPalToken") != '')){
					$allowpaymentoption=['PAYMENT_PAYPALEC'];
					$this->PageData['SelMethod'] = 	'PAYMENT_PAYPALEC';
					$this->PageData['is_paypal'] = 'yes';
				}
				if($PaymentMethods && $PaymentMethods->count() > 0 && !Session::has('PayPalToken') && Session::get("PayPalToken") == '' )
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
								if($this->Is_WholeSaler_Allow() == false)
									$IsPaypalExpressCheckout ='No';
								else
									$IsPaypalExpressCheckout ='Yes';
							}

						}
				}

				if($this->PageData['Is_Afterpay_Checkout'] == "Yes" && (isset($request->SelPayMethod) && $request->SelPayMethod == 'AP' && Session::has('ShoppingCart.AfterPay.Checkout_Token') && Session::get('ShoppingCart.AfterPay.Checkout_Token') != ""))
				{
					$allowpaymentoption=['PAYMENT_PAYWITHAFTERPAY'];
					$this->PageData['SelMethod'] = 	'PAYMENT_PAYWITHAFTERPAY';
					$this->PageData['is_afterpay'] = 'yes';
				}

				$this->PageData['AmazonPayButton']  = $Amazon_pay_Checkout;
				$this->PageData['PaypalPayButton'] = $IsPaypalExpressCheckout;

				$show="No";
				$OnlyWT = 0;
				$NetTotal = $this->GetNetTotal();
				if(Session::get('payment_amount') > 0 && Session::get('sess_icustomerid') > 0 && Session::get('etype') == "M")
				{
					if($NetTotal > Session::get('payment_amount'))
					{
						$allowpaymentoption = ['PAYMENT_WT'];
						$OnlyWT = 1;
						$show="No";
						$MethodNoShow = "No";
					}
				}
				else
				{
					if($NetTotal > 10000)
					{
						$allowpaymentoption = ['PAYMENT_WT'];
						$OnlyWT = 1;
						$show="No";
						$MethodNoShow = "No";
					}
				}
				$OnlyGiftCert = 0;
				$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
				if(strtolower(trim(Session::get('eusertype') ?? ''))!="wholesaler" && trim(Session::get('is_dropshipper') ?? '') !="Yes" && $this->isGiftCouponsAvailable() == 1)
				{
					if($NetTotal <=0 && $CreditDiscount <= 0)
					{
						$allowpaymentoption = "'PAYMENT_GIFT_CERTIFICATE'";
						$OnlyGiftCert = 1;
						$show="No";
						$MethodNoShow = "No";
					}
				}
				if($OnlyGiftCert!=1)
				{
					$PaymentMethodList = PaymentMethod::where('pm_status','=','Active')->whereIn('pm_group_name',$allowpaymentoption)->orderBy('pm_type','desc')->get();
					if(count($PaymentMethodList)==1)
					{
						$log['PaymentMethodList_1'] = json_encode($PaymentMethodList);
						addLog('PaymentMethods',$log);
						if($PaymentMethodList[0]['pm_group_name']=='PAYMENT_WT')
						{
							$allowpaymentoption = ['PAYMENT_WT'];
							$OnlyWT = 1;
							$show="No";
							$MethodNoShow = "No";
						}
					}
					if(count($PaymentMethodList)<=0)
					{
						$PaymentMethodList = PaymentMethod::whereIn('pm_group_name',['PAYMENT_PAYPALCC'])->orderBy('pm_type','desc')->get();
						$log['PaymentMethodList_2'] = json_encode($PaymentMethodList);
						addLog('PaymentMethods',$log);
					}
				}
				else
				{
					$PaymentMethodList = array("0"=>array("pm_group_name"=>"PAYMENT_GIFT_CERTIFICATE","pm_name"=>"Gift Certificate "));
					$log['PaymentMethodList_3'] = json_encode($PaymentMethodList);
					addLog('PaymentMethods',$log);
				}
				$this->PageData['MethodNoShow'] = $MethodNoShow;
				$this->PageData['OnlyWT'] = $OnlyWT;
				$this->PageData['onlyAmazonPaypal'] = $onlyAmazonPaypal;
				$this->PageData['show'] = $show;
				$this->PageData['OnlyGiftCert'] = $OnlyGiftCert;
				$is_ds = 0;
				$dropshipFundSection = "No";
				if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') == 'Wholesaler' && !Session::has('PayPalToken') && Session::get("PayPalToken") == '' && !isset($request->SelPayMethod) && !Session::has('ShoppingCart.AfterPay.Checkout_Token') && Session::get('ShoppingCart.AfterPay.Checkout_Token') !== "")
				{
					$is_ds = 1;
					$DropshipperAccountDetails = $this->GetDropshipperAccountDetails();
					if(count($DropshipperAccountDetails) > 0)
					{
						$this->PageData["DropshipperAccountDetails"] = $DropshipperAccountDetails;
						if($DropshipperAccountDetails['total_fund']>=$DropshipperAccountDetails['total_payment']  && $DropshipperAccountDetails['fund_available'] == 'Yes'){
							$PaymentMethodList = array("0"=>array("pm_group_name"=>"PAYMENT_DS","pm_name"=>"Dropshipper Fund"));
						}else{
							$dropshipFundSection = "Yes";
							$PaymentMethodList = array();
						}
					}
				}

				$this->PageData['PaymentMethodList'] = $PaymentMethodList;

				$ptype = (isset($request['SelPayMethod']) && $request['SelPayMethod'] == "AP") ? "AP" : "";
				$this->PageData['ptype'] = $ptype;
				$this->PageData['dropshipFundSection'] = $dropshipFundSection;
				$this->PageData['is_ds'] = $is_ds;

				$GiftValue= $this->FreeGiftValue($this->GetNetTotal());
				$giftflag = 0;
				$freegiftcombo = '';
				if(count($GiftValue) > 0)
				{
					$giftflag = 1;
					$freegiftcombo = "<select name='freegiftvalue' id='freegiftvalue' class='form-control'>";
					$freegiftcombo .="<option value=''>Select Gift</option>";

					for($i=0;$i<count($GiftValue);$i++)
					{
						$selected="";
						if(Session::get('ShoppingCart.FreeGift') == $GiftValue[$i])
						{
							$selected = "selected=selected";
						}
						$freegiftcombo .="<option value=\"".$GiftValue[$i]."\" $selected >".$GiftValue[$i]."</option>";
					}
					$freegiftcombo .= "</select>";
				}
				$this->PageData['giftflag'] = $giftflag;
				$this->PageData['NetTotal'] = $NetTotal;
				$this->PageData['freegiftcombo'] = $freegiftcombo;
				$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
				$this->PageData['CreditDiscount'] = $CreditDiscount;
				$CreditData = $this->GetCreditLimitAmount();
				$this->PageData['CartAttr'] = $CreditData ;
			}
			$ship_country = Session::get('ShoppingCart.ShippingAddress.country');
			$ship_state = Session::get('ShoppingCart.ShippingAddress.state');
			$ship_zip = Session::get('ShoppingCart.ShippingAddress.zip');
			$ship_city = Session::get('ShoppingCart.ShippingAddress.city');
			$onlyGCPurchased = $request->onlyGCPurchased;
			$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);

			if($request->ShipInsCharge == 'yes')
			{
				$this->SetShippingInsuranceCharge('remove');
				$this->SetShippingInsuranceCharge('add');
			}

			$this->SetupCart();
			//$Views['CheckoutBoxHTML'] = view('checkout.subtotalbox')->with($this->PageData)->render();
			$Views['ShipInfo'] = view('checkout.shippinginfo')->render();
			$this->PageData['OnlyHead'] = $request->OnlyHead;
			//$Views['PayMethods'] = view('checkout.payment-methods')->with($this->PageData)->render();
            $this->PageData['Shipping'] = Session::get('ShoppingCart.ShippingAddress');
            $this->SetAmazonConfig('billing');

			########set afterpay shipping details start#######
				// $ShippingModeRS = ShippingMode::where('status','=','1')->where('shipping_mode_id','=',$OrderRs[0]->phoneorder_shipping_method_id)->get();
				// echo "<pre>";print_r($ShippingModeRS);exit;
				$ShippingInfo = Session::get('ShoppingCart.Shipping');
				//echo "<pre>"; print_r($ShippingInfo); exit;
				$currency = "USD";
				$shipping_arr_app = array();
				if(isset($ShippingInfo['ShippingMethodID']) && $ShippingInfo['ShippingMethodID']!='')
				{
					$shipping_arr_app[0]["id"] = (string)$ShippingInfo['ShippingMethodID'];
				}
				if(isset($ShippingInfo['ShippingMethodName']) && $ShippingInfo['ShippingMethodName']!='')
				{
					$shipping_arr_app[0]["name"] = strip_tags($ShippingInfo['ShippingMethodName']);
					$shipping_arr_app[0]["description"] = strip_tags($ShippingInfo['ShippingMethodName']);
				}

				if(!empty($ShippingInfo['ShippingCharge']) && $ShippingInfo['ShippingCharge'] > 0)
				{
					$shipping_arr_app[0]["shippingAmount"]["amount"] = (string)number_format($ShippingInfo['ShippingCharge'],2);
					$shipping_arr_app[0]["shippingAmount"]["currency"] = $currency;
				}
				if(!empty(Session::get('ShoppingCart.Tax')) && Session::get('ShoppingCart.Tax') > 0)
				{
					$shipping_arr_app[0]["taxAmount"]["amount"] = (string)number_format(Session::get('ShoppingCart.Tax'),2);
					$shipping_arr_app[0]["taxAmount"]["currency"] = $currency;
				}
				if(!empty($this->GetNetTotal()) && $this->GetNetTotal() > 0)
				{
					$shipping_arr_app[0]["orderAmount"]["amount"] = (string)number_format($this->GetNetTotal(),2);
					$shipping_arr_app[0]["orderAmount"]["currency"] = $currency;
				}
				$this->PageData["Shipping_Arr_AP"] = json_encode($shipping_arr_app);
			########set afterpay shipping details end#######

			$this->PageData["onlyGCPurchasedVal"] = $request->onlyGCPurchasedVal;

			 $PaymentMethodsValE = "";
		     if(isset($PaymentMethodList) && isset($PaymentMethodList[0]->pm_name) && $PaymentMethodList[0]->pm_name!='')
		     {
				 $PaymentMethodsValE = $PaymentMethodList[0]->pm_name;
			 }
			 else if(isset($PaymentMethodList) && isset($PaymentMethodList[0]["pm_name"]) && $PaymentMethodList[0]["pm_name"]!='')
			 {
				 $PaymentMethodsValE = $PaymentMethodList[0]["pm_name"];
			 }

			 if(isset($PaymentMethodsValE) && $PaymentMethodsValE!='')
			 {
				$GAPayment 	= googleAnalyticsGA4("PaymentMethods",Session::get('ShoppingCart.Cart'), $this->GetNetTotal(),$this->GetAllCoupons('CouponCode'),'',$PaymentMethodsValE);
				$this->PageData["GA4"] =  $GAPayment;
			}
			$log['PaymentMethodsEnd'] = "PaymentMethodsEnd";
			addLog('PaymentMethods',$log);
            return view('checkout.payment-methods-page')->with($this->PageData);
			//return response()->json($Views);
		}
	}

	public function SetBilling(Request $request)
	{

		Session::forget("BillingSkipVariable");

		if(isset($request->BillingSkipVariable) && $request->BillingSkipVariable=="Yes")
		{
			Session::put('BillingSkipVariable',$request->BillingSkipVariable);
		}
		else if($request->BillingSkipVariableFromBill &&  $request->BillingSkipVariableFromBill=="Yes")
		{
			Session::put('BillingSkipVariable',$request->BillingSkipVariableFromBill);
		}
		Session::forget("BillingSkipEmail");
		if(isset($request->BillingSkipEmail) && $request->BillingSkipEmail=="Yes")
		{
			Session::put('BillingSkipEmail',$request->BillingSkipEmail);
		}
		else if($request->BillingSkipEmailFromBill && $request->BillingSkipEmailFromBill=="Yes")
		{
			Session::put('BillingSkipEmail',$request->BillingSkipEmailFromBill);
		}

		if(isset($request->AfterPayAP) && strtolower($request->AfterPayAP) == 'ap')
		{

			if(Session::has('ShoppingCart.AfterPay.Customer_Details')){
				$Customer_Details = Session::get('ShoppingCart.AfterPay.Customer_Details');

				$Billing['bill_fname'] 		= (isset($Customer_Details['ship_fname'])?$Customer_Details['ship_fname']:'');
				$Billing['bill_lname']  		= (isset($Customer_Details['ship_lname'])?$Customer_Details['ship_lname']:'');
				$Billing['bill_company']    		= "";
				$Billing['bill_address1']   		= (isset($Customer_Details['ship_address1'])?$Customer_Details['ship_address1']:'');
				$Billing['bill_address2']   		= (isset($Customer_Details['ship_address2'])?$Customer_Details['ship_address2']:'');
				$Billing['bill_city'] 	   		= (isset($Customer_Details['ship_city'])?$Customer_Details['ship_city']:'');
				$Billing['bill_zip'] 	   		= (isset($Customer_Details['ship_zip'])?$Customer_Details['ship_zip']:'');
				$Billing['bill_state'] 	   		= (isset($Customer_Details['ship_state'])?$Customer_Details['ship_state']:'');
				$Billing['bill_country']    		= (isset($Customer_Details['ship_country'])?$Customer_Details['ship_country']:'');
				$Billing['bill_phone'] 	   		= (isset($Customer_Details['ship_phone'])?$Customer_Details['ship_phone']:'');
				$Billing['bill_email'] 	   		= (isset($Customer_Details['email'])?$Customer_Details['email']:'');
				$Billing['confirm_email'] 	= '';

				if(isset($Billing['bill_country']) && $Billing['bill_country']!='US')
				{
					$Billing["bill_other_state"] = (isset($Customer_Details['ship_state'])?$Customer_Details['ship_state']:'');
				}

				$request->merge($Billing);
			}
		}
		$this->SetBillingAddress($request);
		$this->SetShippingAddress($request);
		$this->PageData['Billing'] = Session::get('ShoppingCart.BillingAddress');
		$this->PageData['Shipping'] = Session::get('ShoppingCart.ShippingAddress');
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		if(!$normaluser && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		{
			$this->SetGuestCustomer($request);
		} else {
			$this->CustomerInfoUpdate($request);
		}
		return view('checkout.shipbillinfo')->with($this->PageData);
	}

	function enteredFormattedAddress($address){
		$address1ToCompare = str_replace(" rd "," ",strtolower($address));
		$address1ToCompare = str_replace(" road "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" st. "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" st "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" street "," ",strtolower($address1ToCompare));
		//$shipping['address1ToCompare'] = str_replace(" ste "," suite ",strtolower($shipping['address1ToCompare']));
		$address1ToCompare = str_replace(" ste "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" suite "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" dr. "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" dr "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("#","",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(",","",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" ln "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" lane "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" ln","",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" lane","",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" drive","",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" dr.","",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" dr","",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("1st","1",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("2nd","2",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("2th","2",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("3rd","3",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("4th","4",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("5th","5",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("6th","6",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("7th","7",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("8th","8",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("9th","9",strtolower($address1ToCompare));
		$address1ToCompare = str_replace("0th","0",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" w "," west ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" s "," south ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" apartment "," apt ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(" road","",strtolower($address1ToCompare));
		if($address1ToCompare != ''){
			if(strpos($address1ToCompare,"ave")!=''){
				$arr = explode(" ",$address1ToCompare);
				if(count($arr) > 1){
					if($arr[count($arr)-1] == "ave"){
						$address1ToCompare = str_replace(" ave"," avenue",strtolower($address1ToCompare));
					} else {
						$address1ToCompare = str_replace(" ave "," avenue ",strtolower($address1ToCompare));
					}
				}
			}
		}
		$address1ToCompare = str_replace("  "," ",strtolower($address1ToCompare));
		$address1ToCompare = str_replace(".","",strtolower($address1ToCompare));
		return $address1ToCompare;
	}

	function respondedFormattedAddress($address){
		$firstAddressLineToCompare = str_replace(" rd "," ",strtolower($address));
		$firstAddressLineToCompare = str_replace(" road "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" st. "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" st "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" street "," ",strtolower($firstAddressLineToCompare));
		//$uspsAddressArr['firstAddressLineToCompare'] = str_replace(" ste "," suite ",strtolower($uspsAddressArr['firstAddressLineToCompare']));
		$firstAddressLineToCompare = str_replace(" ste "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" dr. "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" dr "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" # "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" ln "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" lane "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" ln","",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" lane","",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" drive","",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" dr.","",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" dr","",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("1st","1",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("2nd","2",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("2th","2",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("3rd","3",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("4th","4",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("5th","5",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("6th","6",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("7th","7",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("8th","8",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("9th","9",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("0th","0",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" w "," west ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" s "," south ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" e "," east ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(" rd","",strtolower($firstAddressLineToCompare));
		if($firstAddressLineToCompare != ''){
			if(strpos($firstAddressLineToCompare,"ave")!=''){
				$arr = explode(" ",$firstAddressLineToCompare);
				if(count($arr) > 1){
					if($arr[count($arr)-1] == "ave"){
						$firstAddressLineToCompare = str_replace(" ave"," avenue",strtolower($firstAddressLineToCompare));
					}else {
						$firstAddressLineToCompare = str_replace(" ave "," avenue ",strtolower($firstAddressLineToCompare));
					}
				}
			}
		}
		//$firstAddressLineToCompare = str_replace(" ave"," avenue",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace("  "," ",strtolower($firstAddressLineToCompare));
		$firstAddressLineToCompare = str_replace(".","",strtolower($firstAddressLineToCompare));
		return $firstAddressLineToCompare;
	}

	function GoogleAddressValidate($shipping = array()){
		$returnArr = array();
		$addressToCheck = ",";
		$msg = "";
		if(isset($shipping['address1']) && $shipping['address1']!=''){
			$addressToCheck .= $shipping['address1'];
			$shipping['add1'] = $shipping['address1'];
		}

		if(isset($shipping['address2']) && $shipping['address2']!=''){
			if($addressToCheck!=''){
				$addressToCheck .= ", ";
			}
			$addressToCheck .= $shipping['address2'];
			$shipping['add2'] = $shipping['address2'];
			//$shipping['address1'] = $shipping['address1'].", ".$shipping['address2'];
			$shipping['address1'] = trim($shipping['address1'])." ".trim($shipping['address2']);
		}

		if(isset($shipping['city']) && $shipping['city']!=''){
			if($addressToCheck!=''){
				$addressToCheck .= ", ";
			}
			$addressToCheck .= $shipping['city'];
		}

		if(isset($shipping['state']) && $shipping['state']!=''){
			if($addressToCheck!=''){
				$addressToCheck .= ", ";
			}
			$addressToCheck .= $shipping['state'];
		}

		if(isset($shipping['zip']) && $shipping['zip']!=''){
			if($addressToCheck!=''){
				$addressToCheck .= " ";
			}
			$addressToCheck .= $shipping['zip'];
		}

		if(isset($shipping['country']) && $shipping['country']!=''){
			if($addressToCheck!=''){
				$addressToCheck .= ", ";
			}
			if($shipping['country'] == 'US'){
				$shipping['country'] = 'USA';
			}
			$addressToCheck .= $shipping['country'];
		}
		$addressToCheck = substr($addressToCheck,1);
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://addressvalidation.googleapis.com/v1:validateAddress?key=AIzaSyCcdWTGp2vy5_cEjzW6VdBadPer4CUKM3Q',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>'{
				"address": {
					"addressLines": ["'.$addressToCheck.'"]
				}
			}',
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
		));
		$response = curl_exec($curl);
		curl_close($curl);
		$res_arr = json_decode($response,true);
		//if(isset($res_arr['error']) && isset($res_arr['error']['code']) && $res_arr['error']['code'] == '400')
		if(isset($res_arr['error'])){
			$returnArr['validate'] = true;
			$returnArr['validaePopup'] = '';
			return $returnArr;
				//$shipping['trace'] = "7";
		}
		if(isset($res_arr['result']['address']['missingComponentTypes']) && count($res_arr['result']['address']['missingComponentTypes']) > 0){
			$checkMissingComponentTypes = array('locality', 'postal_code', 'country');
			$missing = array_intersect($checkMissingComponentTypes,$res_arr['result']['address']['missingComponentTypes']);

			if(count($missing) > 0){
				$missing = array_values($missing);
				$arr['locality'] = 'City';
				$arr['postal_code'] = 'Postal Code';
				$arr['country'] = 'Country';

				for($m = 0; $m < count($missing); $m++){
					if(isset($arr[$missing[$m]])){
						$msg .= ", ".$arr[$missing[$m]];
					}
				}
				if($msg != ''){
					$msg = substr($msg,1);
					$msg = "Missing Address : ".$msg.", please update the address before placing an order.";
				}
			}
		}
		$returnArr['msg'] = $msg;
		$returnArr['formattedAddress'] = "";
		$returnArr['suggestedAddress'] = "";
		$returnArr['enteredAddress'] = "";
		$returnArr['cntUnConfirmedComponentTypes'] = "";
		$returnArr['missingComponentsAddress'] = "";
		$returnArr['unConfirmedComponentsAddress'] = "";
		$missingComponentsArray = array();
		$unConfirmedComponentsArray = array();
		if(isset($res_arr['result']['address']['formattedAddress']) && $res_arr['result']['address']['formattedAddress']!=''){
			$returnArr['formattedAddress'] = "Suggested Address : ".$res_arr['result']['address']['formattedAddress'];
			$returnArr['suggestedAddress'] = $res_arr['result']['address']['formattedAddress'];
			$returnArr['enteredAddress'] = $addressToCheck;
		}

		if(isset($res_arr['result']['address']['missingComponentTypes']) && count($res_arr['result']['address']['missingComponentTypes']) > 0){
			for($m = 0; $m < count($res_arr['result']['address']['missingComponentTypes']); $m++){
				if(isset($res_arr['result']['address']['missingComponentTypes'][$m]) && $res_arr['result']['address']['missingComponentTypes'][$m] == 'subpremise'){
					array_push($missingComponentsArray, "Apartment");
				}
				if(isset($res_arr['result']['address']['missingComponentTypes'][$m]) && $res_arr['result']['address']['missingComponentTypes'][$m] == 'street_number'){
					array_push($missingComponentsArray, "Street Number");
				}
			}
		}
		if(isset($res_arr['result']['address']['unconfirmedComponentTypes']) && count($res_arr['result']['address']['unconfirmedComponentTypes']) > 0){
			for($u = 0; $u < count($res_arr['result']['address']['unconfirmedComponentTypes']); $u++){
				/*if(isset($res_arr['result']['address']['unconfirmedComponentTypes'][$u]) && $res_arr['result']['address']['unconfirmedComponentTypes'][$u] == 'subpremise'){
					array_push($unConfirmedComponentsArray, "Apartment");
				}*/
				if(isset($res_arr['result']['address']['unconfirmedComponentTypes'][$u]) && $res_arr['result']['address']['unconfirmedComponentTypes'][$u] == 'street_number'){
					array_push($unConfirmedComponentsArray, "Street Number");
				}
				if(isset($res_arr['result']['address']['unconfirmedComponentTypes'][$u]) && $res_arr['result']['address']['unconfirmedComponentTypes'][$u] == 'route'){
					array_push($unConfirmedComponentsArray, "Route");
				}
				if(isset($res_arr['result']['address']['unconfirmedComponentTypes'][$u]) && $res_arr['result']['address']['unconfirmedComponentTypes'][$u] == 'locality'){
					array_push($unConfirmedComponentsArray, "Locality");
				}
				if(isset($res_arr['result']['address']['unconfirmedComponentTypes'][$u]) && $res_arr['result']['address']['unconfirmedComponentTypes'][$u] == 'premise'){
					array_push($unConfirmedComponentsArray, "Building");
				}
				if(isset($res_arr['result']['address']['unconfirmedComponentTypes'][$u]) && $res_arr['result']['address']['unconfirmedComponentTypes'][$u] == 'postal_code'){
					array_push($unConfirmedComponentsArray, "Zip Code");
				}

			}
		}
		//$returnArr['missingComponentsAddress'] = implode(",",$missingComponentsArray);

		$this->ValidatePageData['suggestedAddress'] = $returnArr['suggestedAddress'];
		$this->ValidatePageData['enteredAddress'] = $returnArr['enteredAddress'];
		//$this->ValidatePageData['missingComponentsAddress'] = $returnArr['missingComponentsAddress'];
		$this->ValidatePageData['cntUnConfirmedComponentTypes'] = 0;

		$uspsAddressArr = array();
		if(isset($res_arr['result']['uspsData']['standardizedAddress'])){
			$uspsAddressArr = $res_arr['result']['uspsData']['standardizedAddress'];
			$uspsAddressArr['firstAddressLineToCompare'] = "";
			if(isset($uspsAddressArr['cityStateZipAddressLine'])){
				unset($uspsAddressArr['cityStateZipAddressLine']);
			}
			if(isset($uspsAddressArr['zipCodeExtension'])){
				unset($uspsAddressArr['zipCodeExtension']);
			}
			if(isset($uspsAddressArr['firstAddressLine'])){
				$uspsAddressArr['firstAddressLineToCompare'] = $this->respondedFormattedAddress($uspsAddressArr['firstAddressLine']);

				$uspsAddressArr['firstAddressLine'] = ucwords(strtolower($uspsAddressArr['firstAddressLine']));
			}
			if(isset($uspsAddressArr['city'])){
				$uspsAddressArr['city'] = ucwords(strtolower($uspsAddressArr['city']));
			}
			$uspsAddressArr['country'] = 'USA';
		}
		$shipping['address1ToCompare'] = "";
		if(isset($shipping['address1']) && $shipping['address1']!=''){
			$shipping['address1ToCompare'] = $this->enteredFormattedAddress($shipping['address1']);

		}
		if((isset($res_arr['result']['address']['missingComponentTypes']) && count($res_arr['result']['address']['missingComponentTypes']) > 0) || (isset($res_arr['result']['address']['unconfirmedComponentTypes']) && count($res_arr['result']['address']['unconfirmedComponentTypes']) > 0) || (isset($shipping['city']) && trim($shipping['city']) !='' && isset($uspsAddressArr['city']) && trim($uspsAddressArr['city']) && strtolower(trim($shipping['city'])) != strtolower(trim($uspsAddressArr['city'])) && isset($res_arr['result']['address']['postalAddress']['locality']) && strtolower(trim($shipping['city'])) != strtolower(trim($res_arr['result']['address']['postalAddress']['locality']))) || (isset($shipping['state']) && trim($shipping['state']) !='' && isset($uspsAddressArr['state']) && trim($uspsAddressArr['state']) && strtolower(trim($shipping['state'])) != strtolower(trim($uspsAddressArr['state']))) || (isset($shipping['zip']) && trim($shipping['zip']) !='' && isset($uspsAddressArr['zipCode']) && trim($uspsAddressArr['zipCode']) && strtolower(trim($shipping['zip'])) != strtolower(trim($uspsAddressArr['zipCode'])) && !in_array(strtolower(trim($uspsAddressArr['zipCode'])),explode("-",strtolower(trim($shipping['zip'])))) )){
			if(isset($res_arr['result']['address']['unconfirmedComponentTypes']) && count($res_arr['result']['address']['unconfirmedComponentTypes']) > 2){
			//if(isset($res_arr['result']['address']['unconfirmedComponentTypes']) && count($res_arr['result']['address']['unconfirmedComponentTypes']) >= 2){
				Session::put('ShoppingCart.GoogleSuggestedAddress', $res_arr['result']);
				$returnArr['cntUnConfirmedComponentTypes'] = count($res_arr['result']['address']['unconfirmedComponentTypes']);
				$this->ValidatePageData['cntUnConfirmedComponentTypes'] = count($res_arr['result']['address']['unconfirmedComponentTypes']);
				$returnArr['missingComponentsAddress'] = "";
				$this->ValidatePageData['missingComponentsAddress'] = $returnArr['missingComponentsAddress'];
				$returnArr['unConfirmedComponentsAddress'] = implode(",",$unConfirmedComponentsArray);
				$this->ValidatePageData['unConfirmedComponentsAddress'] = $returnArr['unConfirmedComponentsAddress'];
				$this->ValidatePageData['ship_country'] = $shipping['country'];
				$returnArr['validate'] = false;
				$returnArr['validaePopup'] = view('popup.google-address-validate-popup')->with($this->ValidatePageData)->render();
				$shipping['trace'] = "1";
			}

			else if(isset($shipping['zip']) && trim($shipping['zip']) !='' && isset($uspsAddressArr['zipCode']) && trim($uspsAddressArr['zipCode']) && strtolower(trim($shipping['zip'])) != strtolower(trim($uspsAddressArr['zipCode'])) && !in_array(strtolower(trim($uspsAddressArr['zipCode'])),explode("-",strtolower(trim($shipping['zip'])))) && isset($shipping['state']) && trim($shipping['state']) !='' && isset($uspsAddressArr['state']) && trim($uspsAddressArr['state']) && strtolower(trim($shipping['state'])) == strtolower(trim($uspsAddressArr['state'])) ){
				Session::put('ShoppingCart.GoogleSuggestedAddress', $res_arr['result']);
				$shipping['firstAddressLineToCompare'] = $uspsAddressArr['firstAddressLineToCompare'];
				unset($uspsAddressArr['firstAddressLineToCompare']);
				$returnArr['suggestedAddress'] = implode(", ",$uspsAddressArr);
				Session::put('ShoppingCart.GoogleSuggestedAddress.USPS', $uspsAddressArr);
				$returnArr['cntUnConfirmedComponentTypes'] = 0;
				$this->ValidatePageData['cntUnConfirmedComponentTypes'] = 0;
				$returnArr['missingComponentsAddress'] = "";
				$this->ValidatePageData['missingComponentsAddress'] = $returnArr['missingComponentsAddress'];
				$returnArr['unConfirmedComponentsAddress'] = "";
				$this->ValidatePageData['unConfirmedComponentsAddress'] = $returnArr['unConfirmedComponentsAddress'];
				$this->ValidatePageData['ship_country'] = $shipping['country'];
				$returnArr['validate'] = false;
				$returnArr['validaePopup'] = view('popup.google-address-validate-popup')->with($this->ValidatePageData)->render();
				$shipping['trace'] = "6";
			}

			//else if(count($missingComponentsArray) > 1 || (count($missingComponentsArray) == 1 && isset($res_arr['result']['address']['unconfirmedComponentTypes'])) ){
			else if(isset($res_arr['result']['address']['unconfirmedComponentTypes']) || count($missingComponentsArray) >= 1){
				$trace = '44';
				$nUnconfirmedComponentTypesArray = array(); //$res_arr['result']['address']['unconfirmedComponentTypes'];
				if(isset($res_arr['result']['address']['unconfirmedComponentTypes'])){
					$nUnconfirmedComponentTypesArray = $res_arr['result']['address']['unconfirmedComponentTypes'];
					//if(in_array('subpremise',$res_arr['result']['address']['unconfirmedComponentTypes'])){
					if(($key = array_search('subpremise', $nUnconfirmedComponentTypesArray)) !== false) {
						unset($nUnconfirmedComponentTypesArray[$key]);
						//$trace = '4455'.json_encode($nUnconfirmedComponentTypesArray);
						$trace = '4455';
					}
				}
				$unionTerritories = array("AS","GU","MP","PR","VI");
				$chkunconfirmedtype = array("country");
				if(count($nUnconfirmedComponentTypesArray) > 0 || count($missingComponentsArray) >= 1){
					if($shipping['country']!='USA' && !in_array($shipping['state'],$unionTerritories) && $chkunconfirmedtype != $nUnconfirmedComponentTypesArray){
						Session::put('ShoppingCart.GoogleSuggestedAddress', $res_arr['result']);
						$shipping['firstAddressLineToCompare'] = "";
						if(isset($uspsAddressArr['firstAddressLineToCompare'])){
							$shipping['firstAddressLineToCompare'] = $uspsAddressArr['firstAddressLineToCompare'];
							unset($uspsAddressArr['firstAddressLineToCompare']);
						}
						$returnArr['suggestedAddress'] = implode(", ",$uspsAddressArr);
						Session::put('ShoppingCart.GoogleSuggestedAddress.USPS', $uspsAddressArr);
						$returnArr['missingComponentsAddress'] = implode(",",$missingComponentsArray);
						$this->ValidatePageData['missingComponentsAddress'] = $returnArr['missingComponentsAddress'];
						$returnArr['unConfirmedComponentsAddress'] = implode(",",$unConfirmedComponentsArray);
						$this->ValidatePageData['unConfirmedComponentsAddress'] = $returnArr['unConfirmedComponentsAddress'];
						$this->ValidatePageData['ship_country'] = $shipping['country'];
							//$returnArr['cntUnConfirmedComponentTypes'] = 0;	//062024
							//$this->ValidatePageData['cntUnConfirmedComponentTypes'] = 0;	//062024
						$returnArr['validate'] = false;
						$returnArr['validaePopup'] = view('popup.google-address-validate-popup')->with($this->ValidatePageData)->render();
						$shipping['trace'] = $trace;// "44";
					}
				}

			}
			else if(isset($shipping['address1ToCompare']) && trim($shipping['address1ToCompare']) !='' && isset($uspsAddressArr['firstAddressLineToCompare']) && trim($uspsAddressArr['firstAddressLineToCompare']) != '' && strtolower(trim($shipping['address1ToCompare'])) != strtolower(trim($uspsAddressArr['firstAddressLineToCompare'])) && isset($shipping['add1']) && trim($shipping['add1']) !='' && $this->enteredFormattedAddress(strtolower(trim($shipping['add1']))) != strtolower(trim($uspsAddressArr['firstAddressLineToCompare'])) ){
				Session::put('ShoppingCart.GoogleSuggestedAddress', $res_arr['result']);
				$shipping['firstAddressLineToCompare'] = $uspsAddressArr['firstAddressLineToCompare'];
				$shipping['add1tocompare'] = $this->enteredFormattedAddress(strtolower(trim($shipping['add1'])));

				unset($uspsAddressArr['firstAddressLineToCompare']);
				$returnArr['suggestedAddress'] = implode(", ",$uspsAddressArr);
				Session::put('ShoppingCart.GoogleSuggestedAddress.USPS', $uspsAddressArr);
				$returnArr['cntUnConfirmedComponentTypes'] = 0;
				$this->ValidatePageData['cntUnConfirmedComponentTypes'] = 0;
				$returnArr['missingComponentsAddress'] = "";
				$this->ValidatePageData['missingComponentsAddress'] = $returnArr['missingComponentsAddress'];
				$returnArr['unConfirmedComponentsAddress'] = "";
				$this->ValidatePageData['unConfirmedComponentsAddress'] = $returnArr['unConfirmedComponentsAddress'];
				$this->ValidatePageData['ship_country'] = $shipping['country'];
				$returnArr['validate'] = false;
				$returnArr['validaePopup'] = view('popup.google-address-validate-popup')->with($this->ValidatePageData)->render();
				$shipping['trace'] = "3";
			}
			else if(isset($shipping['city']) && trim($shipping['city']) !='' && isset($uspsAddressArr['city']) && trim($uspsAddressArr['city']) && strtolower(trim($shipping['city'])) != strtolower(trim($uspsAddressArr['city'])) && isset($res_arr['result']['address']['postalAddress']['locality']) && strtolower(trim($shipping['city'])) != strtolower(trim($res_arr['result']['address']['postalAddress']['locality']))){
				Session::put('ShoppingCart.GoogleSuggestedAddress', $res_arr['result']);
				$shipping['firstAddressLineToCompare'] = $uspsAddressArr['firstAddressLineToCompare'];
				unset($uspsAddressArr['firstAddressLineToCompare']);
				$returnArr['suggestedAddress'] = implode(", ",$uspsAddressArr);
				Session::put('ShoppingCart.GoogleSuggestedAddress.USPS', $uspsAddressArr);
				$returnArr['cntUnConfirmedComponentTypes'] = 0;
				$this->ValidatePageData['cntUnConfirmedComponentTypes'] = 0;
				$returnArr['missingComponentsAddress'] = "";
				$this->ValidatePageData['missingComponentsAddress'] = $returnArr['missingComponentsAddress'];
				$returnArr['unConfirmedComponentsAddress'] = "";
				$this->ValidatePageData['unConfirmedComponentsAddress'] = $returnArr['unConfirmedComponentsAddress'];
				$this->ValidatePageData['ship_country'] = $shipping['country'];
				$returnArr['validate'] = false;
				$returnArr['validaePopup'] = view('popup.google-address-validate-popup')->with($this->ValidatePageData)->render();
				$shipping['trace'] = "4";
			}
			else if(isset($shipping['state']) && trim($shipping['state']) !='' && isset($uspsAddressArr['state']) && trim($uspsAddressArr['state']) && strtolower(trim($shipping['state'])) != strtolower(trim($uspsAddressArr['state']))){
				Session::put('ShoppingCart.GoogleSuggestedAddress', $res_arr['result']);
				$shipping['firstAddressLineToCompare'] = $uspsAddressArr['firstAddressLineToCompare'];
				unset($uspsAddressArr['firstAddressLineToCompare']);
				$returnArr['suggestedAddress'] = implode(", ",$uspsAddressArr);
				Session::put('ShoppingCart.GoogleSuggestedAddress.USPS', $uspsAddressArr);
				$returnArr['cntUnConfirmedComponentTypes'] = 0;
				$this->ValidatePageData['cntUnConfirmedComponentTypes'] = 0;
				$returnArr['missingComponentsAddress'] = "";
				$this->ValidatePageData['missingComponentsAddress'] = $returnArr['missingComponentsAddress'];
				$returnArr['unConfirmedComponentsAddress'] = "";
				$this->ValidatePageData['unConfirmedComponentsAddress'] = $returnArr['unConfirmedComponentsAddress'];
				$this->ValidatePageData['ship_country'] = $shipping['country'];
				$returnArr['validate'] = false;
				$returnArr['validaePopup'] = view('popup.google-address-validate-popup')->with($this->ValidatePageData)->render();
				$shipping['trace'] = "5";
			}
			else if(isset($shipping['zip']) && trim($shipping['zip']) !='' && isset($uspsAddressArr['zipCode']) && trim($uspsAddressArr['zipCode']) && strtolower(trim($shipping['zip'])) != strtolower(trim($uspsAddressArr['zipCode'])) && !in_array(strtolower(trim($uspsAddressArr['zipCode'])),explode("-",strtolower(trim($shipping['zip'])))) ){
				Session::put('ShoppingCart.GoogleSuggestedAddress', $res_arr['result']);
				$shipping['firstAddressLineToCompare'] = $uspsAddressArr['firstAddressLineToCompare'];
				unset($uspsAddressArr['firstAddressLineToCompare']);
				$returnArr['suggestedAddress'] = implode(", ",$uspsAddressArr);
				Session::put('ShoppingCart.GoogleSuggestedAddress.USPS', $uspsAddressArr);
				$returnArr['cntUnConfirmedComponentTypes'] = 0;
				$this->ValidatePageData['cntUnConfirmedComponentTypes'] = 0;
				$returnArr['missingComponentsAddress'] = "";
				$this->ValidatePageData['missingComponentsAddress'] = $returnArr['missingComponentsAddress'];
				$returnArr['unConfirmedComponentsAddress'] = "";
				$this->ValidatePageData['unConfirmedComponentsAddress'] = $returnArr['unConfirmedComponentsAddress'];
				$this->ValidatePageData['ship_country'] = $shipping['country'];
				$returnArr['validate'] = false;
				$returnArr['validaePopup'] = view('popup.google-address-validate-popup')->with($this->ValidatePageData)->render();
				$shipping['trace'] = "6";
			}
			else
			{
				$shipping['firstAddressLineToCompare'] = "";
				if(isset($uspsAddressArr['firstAddressLineToCompare'])){
					$shipping['firstAddressLineToCompare'] = $uspsAddressArr['firstAddressLineToCompare'];
				}
				$returnArr['validate'] = true;
				$returnArr['validaePopup'] = '';
				$shipping['trace'] = "7";
			}
		} else {
			$shipping['firstAddressLineToCompare'] = "";
			if(isset($uspsAddressArr['firstAddressLineToCompare'])){
				$shipping['firstAddressLineToCompare'] = $uspsAddressArr['firstAddressLineToCompare'];
			}
			$returnArr['validate'] = true;
			$returnArr['validaePopup'] = '';
			$shipping['trace'] = "77";
		}
		$returnArr['uspsAddressArr'] = $uspsAddressArr;
		$returnArr['shippingsArr'] = $shipping;
		return $returnArr;
	}

	public function CustomerInfoUpdate($request)
	{
		$allow_update_details = "Yes";
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(Auth::user() && $allow_update_details == "Yes" )
		if($normaluser && $allow_update_details == "Yes" )
		{
			if($request['bill_country'] != 'US'){
				$state = isset($request['bill_other_state']) ? $request['bill_other_state'] : "";
			}else{
				$state = $request['bill_state'];
			}
			$CustomerAddNew = array (
			'first_name'		=> stripslashes($request['bill_fname']),
			'last_name' 		=> stripslashes($request['bill_lname']),
			'address1' 			=> stripslashes($request['bill_address1']),
			'city' 				=> stripslashes($request['bill_city']),
			'state' 			=> $state,
			'country' 			=> $request['bill_country'],
			'zip' 				=> $request['bill_zip'],
			'phone' 			=> $request['bill_phone']
			);
			if(isset($request['bill_company']) && $request['bill_company'] != ""){
				$CustomerAddNew['company_name'] = stripslashes($request['bill_company']);
			}
			if(isset($request['bill_address2']) && $request['bill_address2'] != ""){
				$CustomerAddNew['address2'] = stripslashes($request['bill_address2']);
			}
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			$cust_upd = Customer::where('customer_id','=',$normaluser->customer_id)->update($CustomerAddNew);
			//$cust_upd = Customer::where('customer_id','=',Auth::user()->customer_id)->update($CustomerAddNew);

			//merge guest accounts
			$user_email = Session::has('sess_useremail') ? Session::get('sess_useremail') : "";
			if($user_email == ""){
				$user_email = $request['bill_email'];
			}
			$this->Merge_Guest_Register($user_email,Session::get('sess_icustomerid'));
			//merge guest accounts
		}

		if(isset($request['newsletter']) && $request['newsletter'] == 'Yes' && trim($request['bill_email'])!='')
		{
			$check_news = NewsLetter::where('email','=',trim($request['bill_email']))->get();
			if($check_news && $check_news->count() <=0)
			{
				$arrInsert = array(
				'first_name' => trim($request['bill_fname']),
				'last_name'  => trim($request['bill_lname']),
				'email' 	 => trim($request['bill_email']),
				'phone_no' => trim($request['bill_phone']),
				'status'	 => '1'
				);
				$News = NewsLetter::create($arrInsert);
				$NewsId = $News->news_letter_id;
				if($NewsId)
				{
					$data["phone"] = trim($request['bill_phone']); //"+12679018713";
					$data["email"] = trim($request['bill_email']);//"test@gmail.com";
					$data["first_name"] = trim($request['bill_fname']);
					$data["visitorId"] = $NewsId; //"762bb2a97d604f958e3071fef83dfd5a";
					if(trim($data["phone"])!="" && config('global.SITE_MODE') == 'Live' ){
						AddAttentiveSubscriber($data);
					}
				}
			}
		}
	}

	public function SetGuestCustomer($request,$isPaypal="No")
	{
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		//if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		if(!$normaluser && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		{

			if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid')!='')
			{
				Session::forget('sess_icustomerid');
				Session::forget('etype');
				Session::forget('eusertype');
				Session::forget('sess_useremail');
			}

			$check_cust_email = Customer::select('customer_id','email','status','eusertype','first_name','is_dropshipper')
								->where('email','=',trim($request['bill_email']))
								->where('registration_type','=','M')->get();
			$registration_type = "Member";
			$allow_update_details = "Yes";
			if($check_cust_email->count() <= 0 )
			{

				$check_cust_email = Customer::select('customer_id','email','status','eusertype','first_name','is_dropshipper')
								->where('email','=',trim($request['bill_email']))
								->where('registration_type','=','G')
								->where('is_deleted','=','No')->get();
				$registration_type = "Guest";
			}
			if($check_cust_email && $check_cust_email->count() > 0 )
			{
				Session::put('sess_icustomerid',$check_cust_email[0]["customer_id"]);
				Session::put('etype','G');
				Session::put('eusertype',$check_cust_email[0]["eusertype"]);
				Session::put('sess_useremail',$check_cust_email[0]["email"]);
				$allow_update_details = "No";

				if($check_cust_email[0]['status'] == "0"){
					$CustomerArr = array (
						'upd_datetime' 		=> date('Y-m-d H:i:s'),
						'merge_log' 		=> "Auto updated to Active from billing page",
						'status' 			=> '1'
					);
					$cust_upd = Customer::where('customer_id',Session::get('sess_icustomerid'))->update($CustomerArr);
				}

				if(isset($registration_type)  && $registration_type=="Guest"  && isset($isPaypal) && $isPaypal=="Yes")
				{

					if($request['bill_country'] != 'US' && isset($request['bill_other_state']) && $request['bill_other_state']!=''){
						$state = $request['bill_other_state'];
					}
					else
					{
						$state = $request['bill_state'];
					}
					$bill_phone = '';
					if(isset($request['bill_phone']) && $request['bill_phone'])
					{
						$bill_phone = $request['bill_phone'];
					}

					if(isset($request['bill_fname']) && $request['bill_fname'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_fname']))
					{
						$request['bill_fname'] = $this->transliterate($request['bill_fname']);
					}
					if(isset($request['bill_lname']) && $request['bill_lname'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_lname']))
					{
						$request['bill_lname'] = $this->transliterate($request['bill_lname']);
					}
					if(isset($request['bill_address1']) && $request['bill_address1'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_address1']))
					{
						$request['bill_address1'] = $this->transliterate($request['bill_address1']);
					}
					if(isset($request['bill_address2']) && $request['bill_address2'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_address2']))
					{
						$request['bill_address2'] = $this->transliterate($request['bill_address2']);
					}
					if(isset($request['bill_city']) && $request['bill_city'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_city']))
					{
						$request['bill_city'] = $this->transliterate($request['bill_city']);
					}

					$CustomerAddNew = array (
						'first_name'		=> stripslashes($request['bill_fname']),
						'last_name' 		=> stripslashes($request['bill_lname']),
						'address1' 			=> stripslashes($request['bill_address1']),
						'address2' 			=> stripslashes($request['bill_address2']),
						'city' 				=> stripslashes($request['bill_city']),
						'state' 			=> $state,
						'country' 			=> $request['bill_country'],
						'zip' 				=> $request['bill_zip'],
						'phone' 			=> $bill_phone
					);
					$cust_upd = Customer::where('customer_id',Session::get('sess_icustomerid'))->update($CustomerAddNew);

				}

				Session::put('ShoppingCart.merge_note',"Merge with ".$check_cust_email[0]['eusertype']." (".$registration_type.") customer id: ".$check_cust_email[0]['customer_id']);
				Session::put('ShoppingCart.is_registered_guest',"Yes");
			}else{
				if($request['bill_country'] != 'US' && isset($request['bill_other_state']) && $request['bill_other_state']!=''){
					$state = $request['bill_other_state'];
				}else{
					$state = $request['bill_state'];
				}
				$bill_phone = '';
				if(isset($request['bill_phone']) && $request['bill_phone'])
				{
					$bill_phone = $request['bill_phone'];
				}

				if(isset($request['bill_fname']) && $request['bill_fname'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_fname']))
				{
					$request['bill_fname'] = $this->transliterate($request['bill_fname']);
				}
				if(isset($request['bill_lname']) && $request['bill_lname'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_lname']))
				{
					$request['bill_lname'] = $this->transliterate($request['bill_lname']);
				}
				if(isset($request['bill_address1']) && $request['bill_address1'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_address1']))
				{
					$request['bill_address1'] = $this->transliterate($request['bill_address1']);
				}
				if(isset($request['bill_city']) && $request['bill_city'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_city']))
				{
					$request['bill_city'] = $this->transliterate($request['bill_city']);
				}

				$CustomerAddNew = array (
					'first_name'		=> stripslashes($request['bill_fname']),
					'last_name' 		=> stripslashes($request['bill_lname']),
					'address1' 			=> stripslashes($request['bill_address1']),
					'city' 				=> stripslashes($request['bill_city']),
					'state' 			=> $state,
					'country' 			=> $request['bill_country'],
					'zip' 				=> $request['bill_zip'],
					'phone' 			=> $bill_phone,
					'email' 			=> $request['bill_email'],
					'registration_type' => 'G',
					'status' 			=> '1',
					'eusertype'			=> 'Retailer',
					'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
					'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT']
				);
				$customer_id = (int)Session::get('sess_icustomerid');
				if(!Session::has('sess_icustomerid'))
				{
					$NewCustomer = Customer::create($CustomerAddNew);
					$customer_id = $NewCustomer->customer_id;
					Session::put('sess_icustomerid',$customer_id) ;
					Session::put('etype','G');
					Session::put('eusertype','Retailer');
					Session::put('sess_useremail',$request['bill_email']);
				}else{
					Customer::where('customer_id','=',$customer_id)->update($CustomerAddNew);
					//$customer_id = Customer::where('customer_id','=',$customer_id)->update($CustomerAddNew);
				}
			}

			if($allow_update_details == "No"){
				$user_email = $request['bill_email'];
				$this->Merge_Guest_Register($user_email,Session::get('sess_icustomerid'));
			}
			$this->SetBillingAddress($request);
			$Billing = Session::get('ShoppingCart.BillingAddress');
			if(isset($request['newsletter']) && $request['newsletter'] == 'Yes' && trim($request['bill_email'])!='')
			{
				$check_news = NewsLetter::where('email','=',trim($request['bill_email']))->get();
				if($check_news && $check_news->count() <=0)
				{
					$arrInsert = array(
						'first_name' => trim($request['bill_fname']),
						'last_name'  => trim($request['bill_lname']),
						'email' 	 => trim($request['bill_email']),
						'phone_no' => trim($request['bill_phone']),
						'status'	 => '1'
					);
					$News = NewsLetter::create($arrInsert);
					$NewsId = $News->news_letter_id;
					if($NewsId)
					{
						$data["phone"] = trim($request['bill_phone']); //"+12679018713";
						$data["email"] = trim($request['bill_email']);//"test@gmail.com";
						$data["first_name"] = trim($request['bill_fname']);
						$data["visitorId"] = $NewsId;
						if(trim($data["phone"])!="" && config('global.SITE_MODE') == 'Live'){
							AddAttentiveSubscriber($data);
						}
					}
				}
			}
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//if(!Auth::user() && Session::get('sess_icustomerid') != '')
			if(!$normaluser && Session::get('sess_icustomerid') != '')
			{
				$customer_password = '';
				if(isset($request['guest_password']) && $request['guest_password']!='')
				{
					$customer_password = trim($request['guest_password']);
				}
				if(isset($customer_password) && $customer_password!='' && $registration_type == "Guest")
				{
					$check_cust_email = Customer::where('customer_id','=',(int)Session::get('sess_icustomerid'))
										->where('email','=',trim($request['bill_email']))
										->where('registration_type','=','M')->get();
					if($check_cust_email->count()>0)
					{
						$msg = "To become a registered customer, please change your email address, as its already in use.";
						return response()->json(array('error' => 1,'Message' => $msg));
					}
					else
					{
						$check_cust_email = Customer::where('customer_ip','=',$_SERVER['REMOTE_ADDR'])
											->where('registration_type','=','M')
											->where('customer_id','!=',(int)Session::get('sess_icustomerid'))->get();
						if($check_cust_email->count()>=5)
						{
							$msg = "Oops .. Your IP has reached the maximum count of user registered with maxaroma.There are 5 different users already registered from this IP.";
							return response()->json(array('error' => 1,'Message' => $msg));
						}
						else
						{
							if ($request['bl_country'] != 'US'){
								$state = $request['bill_other_state'];
							}else{
								$state = $request['bill_state'];
							}

							if(isset($request['bill_fname']) && $request['bill_fname'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_fname']))
							{
								$request['bill_fname'] = $this->transliterate($request['bill_fname']);
							}
							if(isset($request['bill_lname']) && $request['bill_lname'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_lname']))
							{
								$request['bill_lname'] = $this->transliterate($request['bill_lname']);
							}
							if(isset($request['bill_address1']) && $request['bill_address1'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_address1']))
							{
								$request['bill_address1'] = $this->transliterate($request['bill_address1']);
							}
							if(isset($request['bill_address2']) && $request['bill_address2'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_address2']))
							{
								$request['bill_address2'] = $this->transliterate($request['bill_address2']);
							}
							if(isset($request['bill_city']) && $request['bill_city'] != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $request['bill_city']))
							{
								$request['bill_city'] = $this->transliterate($request['bill_city']);
							}

							$CustomerUPDArr = array (
							'first_name'		=> stripslashes($request['bill_fname']),
							'last_name' 		=> stripslashes($request['bill_lname']),
							'address1' 			=> stripslashes($request['bill_address1']),
							'address2' 			=> stripslashes($request['bill_address2']),
							'city' 				=> stripslashes($request['bill_city']),
							'state' 			=> $state,
							'country' 			=> $request['bill_country'],
							'zip' 				=> $request['bill_zip'],
							'phone' 			=> $request['bill_phone'],
							'email' 			=> $request['bill_email'],
							'status' 			=> '1',
							'eusertype'			=> 'Retailer',
							'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
							'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
							'password'			=> $customer_password,
							'registration_type' => 'M',
							//'reg_datetime' 		=> date('Y-m-d H:i:s'),
							'upd_datetime' 		=> date('Y-m-d H:i:s'),
							//'iRewardpoint'		=> '150'
							);

                            if(config('global.YOTPO_PROG') == false)
                            {
                                $CustomerUPDArr['iRewardpoint'] = '150';
                            }
							$where = "customer_id= '".(int)Session::get('sess_icustomerid')."' ";
							$cust_upd = Customer::where('customer_id','=',(int)Session::get('sess_icustomerid'))->update($CustomerUPDArr);
							if($cust_upd)
							{
                                if(config('global.YOTPO_PROG') == false)
                                {
                                    $RewardPointVal["customer_id"] = Session::get('sess_icustomerid');
                                    $RewardPointVal["note"] = "Reward Point Added By Checkout Register";
                                    $RewardPointVal["iRewardpoint"] = 150;
                                    $RewardDiscountPoint = RewardPoint::create($RewardPointVal);
                                }
								 $CustomerQry = Customer::where('email', $request['bill_email'])
										->where('status','1')
										->where('registration_type','M');

								$CustomerQry->where('password',$customer_password);

                                $Customer = $CustomerQry->first();

								if(empty($Customer->email)) {
									if(isset($Customer->customer_id) &&	$Customer->customer_id!='' && $Customer->customer_id > 0){
										$str = "Email not found and customer Id ". $Customer->customer_id;
									} else {
										$str = "Email not found and customer details ".json_encode($Customer);
									}
									$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
									if(fopen($myFile, 'a+'))
									{
										$fh = fopen($myFile, 'a+');
										$stringData = date("m/d/Y H:i:s")." ".$str;
										fwrite($fh, $stringData);
										fclose($fh);
									}
								}

								Session::put('sess_useremail',$Customer->email);
								Session::put('sess_username',$Customer->first_name);
								Session::put('sess_icustomerid',$Customer->customer_id);
								Session::put('eusertype',$Customer->eusertype);
								Session::put('is_dropshipper',$Customer->is_dropshipper);
								Session::put('SpecialCustomerFlag',$Customer->DownloadSpecialPricelist);
								Session::put('etype','M');
								Session::put('payment_amount',$Customer->payment_amount);

                                $custFname = '';
								if(isset($Customer->first_name) &&  $Customer->first_name!='' )
								{
									$custFname = $Customer->first_name;
								}
								$custlname = '';
								if(isset($Customer->last_name) && $Customer->last_name!='')
								{
									$custlname = $Customer->last_name;
								}
								Session::put('sess_custname',$custFname."|".$custlname);
								$CityVal = '';
								if(isset($Customer->city) &&  $Customer->city!='')
								{
									$CityVal = $Customer->city;
								}
								$StateVal = '';
								if(isset($Customer->state) &&  $Customer->state!='')
								{
									$StateVal = $Customer->state;
								}
								$CountryVal = '';
								if(isset($Customer->country) &&  $Customer->country!='')
								{
									$CountryVal = $Customer->country;
								}
								$ZipVal = '';
								if(isset($Customer->zip) &&  $Customer->zip!='')
								{
									$ZipVal = $Customer->zip;
								}
								$PhoneVal = '';
								if(isset($Customer->phone) &&  $Customer->phone!='')
								{
									$PhoneVal = $Customer->phone;
								}
								Session::put('sess_useraddress',$CityVal."|".$StateVal."|".$CountryVal."|".$ZipVal."|".$PhoneVal);

                                Auth::login($Customer, false);

                                Session::put("GARegsiter","Yes");

								Cookie::make('omnisendContactID',$Customer->omnisend_accountid,time()+60*60*24*15);
								$this->GenerateShopCartFromCookieAfterLogin();
								$this->StoreShopCartInCookie();

								YotpoRequest('create_customer',$Customer);

								$Template = GetMailTemplate("CUSTOMER_REGISTER");
								$EmailBody = str_replace('{$vFirstName}',$request['bill_fname'],$Template[0]->mail_body);
								$EmailBody = str_replace('{$vLastName}',$request['bill_lname'],$EmailBody);
								$EmailBody = str_replace('{$vemail}',$request['bill_email'],$EmailBody);
								$EmailBody = str_replace('{$password}',$customer_password,$EmailBody);
								$EmailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$EmailBody);
								$EmailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$EmailBody);
								$EmailBody = str_replace('{$TOLL_FREE_NO}',config('Settings.CONTACT_PHONE_NO'),$EmailBody);
								$EmailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$EmailBody);
								$FreeShipping = "";
								if(config('Settings.FREESHIPPING_VALUE') && config('Settings.FREESHIPPING_VALUE') > 0) {
									$FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
								}
								$EmailBody = str_replace('{$freeshippinginfo}',$FreeShipping,$EmailBody);

								$To = $request['bill_email'];
								$Subject = $Template[0]->subject;
								$EmailBody = $Template[0]->mail_body;
								$From = config('Settings.CONTACT_MAIL');
								//SendMail($Subject,$EmailBody,$To,$From);

								$To = config('Settings.ADMIN_MAIL');
								//SendMail($Subject,$EmailBody,$To,$From);

								//merge guest accounts
								$user_email = $request['bill_email'];
								$this->Merge_Guest_Register($user_email,Session::get('sess_icustomerid'));
								//merge guest accounts

								$show_login_box    = 'No';
								$show_password_box = 'No';
								$normaluser = Auth::user();
								if (Auth::guard('store')->check()) {
									$normaluser = Auth::guard('web')->user();
								}
								//if(!Auth::user())
								if(!$normaluser)
								{
									$show_password_box 	= 'Yes';
									$show_login_box 	= 'Yes';
								}
								else
								{
									if(config('global.OMNISEND_PROG') == false)
									{
									   SendMail($Subject,$EmailBody,$To,$From);
									}
									else {
										OmanisendRequest('create_customer',$Customer);
									$To = $Customer->email;
									$EventData = ['email' => $To, //'qualdev.devs@gmail.com',
													'fields' => [
														'first_name' => $Customer->first_name,
														'last_name' => $Customer->last_name,
														'password' => $Customer->password,
														'SITE_NAME' => config('Settings.SITE_TITLE'),
														'COUPON_CODE_VALUE' => config('Settings.COUPON_CODE_VALUE'),
														'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
														'Site_URL' => config('global.SITE_URL'),
														'freeshippinginfo' => $FreeShipping
													]
												];
									OmanisendRequest('61e55276af90600022058216',$EventData);
									}
								}
								$this->SetBillingAddress($request);
								//$this->SetShippingAddress($request);
								$Billing  = Session::get('ShoppingCart.BillingAddress');
								//echo $ajaxTemplate."###1";
								return true;
							}
							else
							{
								$msg = "Error###You have not been registered, please try again to become a registered customer.";
								return response()->json(array('error' => 1,'Message' => $msg));
								exit;
							}
						}
					}
				}
			}
		}
	}

	public function CheckAvailableShippingMethod($shipping_mode_id = NULL, $ship_country,$ship_state,$ship_zip)
	{
		$shipping_mode_id = (int)$shipping_mode_id;

		$ShippingMethodRS = ShippingMode::where('status','=','1')->where('shipping_mode_id','=',$shipping_mode_id)->get();

		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('country','like','%'.$ship_country.'%')->get();

				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}

			if ($rid && $rid->count() > 0 )
			{
				if($rid[0]["normal_charge"]=="")
				{
					$normal_chrage = 0;
				}
				else
				{
					$normal_chrage = $rid[0]["normal_charge"];
				}
				if($rid[0]["light_charge"]=="")
				{
					$light_charge = 0;
				}
				else
				{
					$light_charge = $rid[0]["light_charge"];
				}
				if($rid[0]["heavy_charge"]=="")
				{
					$heavy_charge = 0;
				}
				else
				{
					$heavy_charge = $rid[0]["heavy_charge"];
				}
				$shipping_mode_id = (int)$ShippingMethodRS[0]['shipping_mode_id'];
				$deusertype = '';
				if(Session::get('is_dropshipper')=='Yes')
					$deusertype = 'Dropshipper';

				if($ShippingMethodRS[0]["eusertype"] == $deusertype && Session::get('sess_icustomerid')!="" && Session::get('eusertype')=="Wholesaler")
				{
				   return (int)$shipping_mode_id."###".$normal_chrage."###".$light_charge."###".$heavy_charge;
				}
				else if($ShippingMethodRS[0]["eusertype"]==Session::get('eusertype') && Session::get('is_dropshipper')!='Yes')
				{
					return (int)$shipping_mode_id."###".$normal_chrage."###".$light_charge."###".$heavy_charge;
				}
				else
				{
					if(Session::get('sess_icustomerid')=="" && $ShippingMethodRS[0]["eusertype"]=="Retailer")
					{
						return (int)$shipping_mode_id."###".$normal_chrage."###".$light_charge."###".$heavy_charge;
					}
					if(Session::get('sess_icustomerid')!="" && $ShippingMethodRS[0]["eusertype"]=="Retailer" && Session::get('eusertype')=="")
					{
						return (int)$shipping_mode_id."###".$normal_chrage."###".$light_charge."###".$heavy_charge;
					}
				}
			}
			else
			{
				return false;
			}
		}else{
			return false;
		}
	}
	public function CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id,$paypal_subtotal = "",$paypal_totalitem = 0)
	{
		if($paypal_subtotal != ''){
			$subTotal = $paypal_subtotal;
		} else {
			$AllDiscount = $this->GetAllDiscounts();
			$subTotal = Session::get('ShoppingCart.SubTotal') - $AllDiscount['TotalDiscount'];
		}

		$ship_country  = substr($ship_country, 0, 2);
		$shipping_mode_id = (int)$shipping_mode_id;
		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('country','like','%'.$ship_country.'%')->get();

				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}
		}
		if($rid && $rid->count() > 0 )
		{
			$shipping_rule_id 	= $rid[0]["shipping_rule_id"];
			$rule_type  		= $rid[0]["rule_type"];
			$days				= $rid[0]["days"];

			if ($shipping_rule_id != "" && $rule_type == 1 )
			{
				$rowrate = ShippingRate::where('shipping_rule_id','=',$shipping_rule_id)
											->where('order_amount','<=',$subTotal)
											->orderBy('order_amount','desc')->limit(1)->get();
			}
			else if($shipping_rule_id != "" && ($rule_type==0 || $rule_type==2))
			{
				//$totalitem = $Cart->getTotalItemInCart() - $Cart->getGiftCertiCount();
				//$totalitem = Session::get('ShoppingCart.TotalItemInCart') ;
				if($paypal_subtotal != ''){
					$totalitem = $paypal_totalitem;
				} else {
					$totalitem = Session::get('ShoppingCart.TotalItemInCart');
				}

				$rowrate = ShippingRate::where('shipping_rule_id','=',$shipping_rule_id)
											->where('order_amount','<=',$totalitem)
											->orderBy('order_amount','desc')->limit(1)->get();
				############ FOR FREE SHIPPING FOR ITEM COUNT ##########
					if($rid[0]["is_free_ship"]=="Yes")
					{
						if($rid[0]["free_ship_amt"]<=$subTotal)
						{
							$temp_ShippingCharge=0;
							//return $temp_ShippingCharge;
						}
					}
				############## FOR FREE SHIPPING FOR ITEM COUNT ##############
			}
			$charge = 0;
			if($rowrate && $rowrate->count() > 0)
			{
				$charge = $rowrate[0]["charge"];
				if($rid[0]["is_free_ship"]=="Yes")
				{
					if($rid[0]["free_ship_amt"]<=$subTotal)
					{
						$charge=0;
					}
				}
			}
			if ($charge > 0)
				$temp_ShippingCharge = $charge;
			else
				$temp_ShippingCharge = 0;

			########### START CODE FOR CALCULATE PROP SHIP CHARGE###########
			if($rid[0]["prop_item"] > 0)
			{
				if($rid[0]["prop_charge"] > 0)
				{
					if($totalitem >= $rid[0]["prop_item"])
					{
						$extraitem = ($totalitem-$rid[0]["prop_item"]) + 1;
						$propshippingcharge  = ($rid[0]["prop_charge"]*$extraitem);
						$temp_ShippingCharge = $temp_ShippingCharge+$propshippingcharge;
					}
				}
			}
			if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($shipping_mode_id,Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
			{
				$temp_ShippingCharge = 0;
			}
			if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $shipping_mode_id == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
			{
				$temp_ShippingCharge = 0;
			}
			########### END CODE FOR CALCULATE PROP SHIP CHARGE###########
			return $temp_ShippingCharge."###".$days;
		}
	}

	public function countWeekendDays($start, $end)
	{
		$iter = 24*60*60; // whole day in seconds
		$count = 0; // keep a count of Sats & Suns
		$start = strtotime($start);
		$end   = strtotime($end);
		for($i = $start; $i <= $end; $i=$i+$iter)
		{
		   if(Date('D',$i) == 'Sat' || Date('D',$i) == 'Sun')
		   {
				$count++;
		   }
		}
		return $count;
	}
	public function checkday($date)
	{
		$timestamp = strtotime($date);
		$weekday= date("l", $timestamp );
		$normalized_weekday = strtolower($weekday ?? '');
		if (($normalized_weekday == "saturday") || ($normalized_weekday == "sunday")) {
			return $normalized_weekday;
		}
	}

	public function TaxCalculation($ship_country, $ship_state, $ship_zip, $onlyGCPurchased, $isPayPalSubTotal = 0, $ship_city = '', $shipping_charge_paypal_product_page = 0)
	{

		Session::put('ShoppingCart.TaxableShipping', "No");
		Session::put('ShoppingCart.TaxableShippingInsurance', "No");
		Session::put('ShoppingCart.TaxableShippingSignature', "No");
		//if(Session::get('eusertype') == "Wholesaler" || $onlyGCPurchased == 1) {

		// if($onlyGCPurchased == 1) {
		// 	Session::put('ShoppingCart.Tax', 0);
		// 	return null;
		// }
		$wh_log = '';
		if(Session::has('eusertype') && Session::get('eusertype') == "Wholesaler"){
			if(Session::has('sess_useremail') && Session::get('sess_useremail') != ""){
				$wh_log .= "Wholesale User Email : ".Session::get('sess_useremail');
			}
			if(Session::has('resale_certificate_status') && Session::get('resale_certificate_status') != ""){
				$wh_log .= "--Wholesale Resale Certificate Status : ".Session::get('resale_certificate_status');
			}
			if($wh_log != ''){
				Log::info('Wholesaler Tax ', [
					'Wholesaler Tax Calculations ' => $wh_log
				]);
			}

		  if(!Session::has('resale_flag'))
		  {
		  $Customer = Customer::where('email', Session::get('sess_useremail'))
				->where('status', '1')
				->where('registration_type', 'M')
				->first();

			if ($Customer && !Session::has('resale_flag') && (!Session::has('resale_certificate_status') || Session::get('resale_certificate_status') == "Pending" || Session::get('resale_certificate_status') == "Rejected"))
			{
				Session::put('resale_certificate_status', $Customer->resale_certificate_status);
				Session::put('resale_flag', 'Yes');
			}
		  }
		}

		if((Session::has('eusertype') && Session::get('eusertype') == "Wholesaler" && Session::has('resale_certificate_status') && Session::get('resale_certificate_status') == "Approved") || $onlyGCPurchased == 1){
            Session::put('ShoppingCart.Tax', 0);
            return null;
        }

		// Log::info('Shipping Infor for Tax ', [
		// 	'Shipping Infor for Tax ' => Session::get('eusertype')."---".$ship_country."--".$ship_state."--".$ship_zip."--".$isPayPalSubTotal."--".$ship_city
		// ]);

		// Log::warning('User Type Info', [
		// 	'User Type Info' => Session::get('eusertype')
		// ]);

		 $ship_city = trim($ship_city);
		// -------------------------------
		// Subtotal Calculation
		// -------------------------------
		$AllDiscount = $this->GetAllDiscounts();

		$GiftCertiTotal = Session::has('ShoppingCart.GiftCertiTotal')
			? NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'))
			: 0;

		/*$ShippingChargeTotal = (Session::has('ShoppingCart.Shipping.ShippingCharge') && Session::get('ShoppingCart.Shipping.ShippingCharge') > 0)
			? Session::get('ShoppingCart.Shipping.ShippingCharge')
			: 0;*/

		$AllDiscount["TotalDiscount"] -= Session::get('ShoppingCart.credit_limit_discount');

		if (Session::has('ShoppingCart.phoneorder_manual_discount')) {
			$AllDiscount["TotalDiscount"] += Session::get('ShoppingCart.phoneorder_manual_discount');
		}

		/*$subTotal = (Session::get('ShoppingCart.SubTotal') + $ShippingChargeTotal)
			- ($GiftCertiTotal + $AllDiscount["TotalDiscount"]);*/

		$subTotal = (Session::get('ShoppingCart.SubTotal') - ($GiftCertiTotal + $AllDiscount["TotalDiscount"]));

		$isFromPayPalProductPage = !empty($isPayPalSubTotal) ? "Yes" : "No";

		if (!empty($isPayPalSubTotal)) {
			$subTotal = $isPayPalSubTotal;
		}

		$subTotal = max(0, NumberFormat($subTotal));

		$ship_zip = $ship_zip ?: '0';

		$calculateTax = function ($area) use ($subTotal, $isFromPayPalProductPage,$shipping_charge_paypal_product_page) {
		//echo $area->tax_areas_id; exit;
		//Log::info('CalculateTaxArea: '.json_encode($area));
		$ShippingChargeTotal = (Session::has('ShoppingCart.Shipping.ShippingCharge') && Session::get('ShoppingCart.Shipping.ShippingCharge') > 0)
			? Session::get('ShoppingCart.Shipping.ShippingCharge')
			: 0;

		if($isFromPayPalProductPage == "Yes" && isset($shipping_charge_paypal_product_page) && $shipping_charge_paypal_product_page != 0 && $shipping_charge_paypal_product_page != '0.00'){
			$ShippingChargeTotal = (float)$shipping_charge_paypal_product_page;
		}
			if(isset($area->ShippingTaxable) && $area->ShippingTaxable == "Yes")
			{
				Session::put('ShoppingCart.TaxableShipping', "Yes");
				$subTotal = $subTotal + $ShippingChargeTotal;
				//$subTotal = max(0, NumberFormat($subTotal));
			}
			if(isset($area->insurance) && $area->insurance == "Yes"){
				Session::put('ShoppingCart.TaxableShippingInsurance', "Yes");
				$subTotal = $subTotal + $this->GetAllCharges('ShippingInsurance');
			}
			if(isset($area->signature) && $area->signature == "Yes"){
				Session::put('ShoppingCart.TaxableShippingSignature', "Yes");
				$subTotal = $subTotal + $this->GetAllCharges('ShippingSignature');
			}

			$subTotal = max(0, (float) NumberFormat($subTotal));

			$rate = TaxRates::where('tax_areas_id', $area->tax_areas_id)
				->where('amount_from', '<=', $subTotal)
				->orderBy('amount_from', 'desc')
				->first();

			if (!$rate) return null;
			//Log::info('CalculateTaxRate: '.json_encode($rate));
			Session::put('ShoppingCart.TaxableSubTotal', $subTotal);
			$tax = ($rate->amount_in_percent == 'Y')
				? ($subTotal * $rate->charge_amount) / 100
				: $rate->charge_amount;

			if ($isFromPayPalProductPage == "Yes") {
				return $tax;
			} else {
				Session::put('ShoppingCart.Tax', $tax);
				return null;
			}
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
				->where('status', '1')
				->orderByRaw('(zip_to - zip_from) ASC')->first();

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
			->orderByRaw('(zip_to - zip_from) ASC')->first();

		if ($area) return $calculateTax($area);

		// =========================================================
		// PRIORITY 3: ZIP And country only
		// =========================================================
		$area = TaxAreas::where('country', $ship_country)
			->where('states', '')
			->where('zip_from', '<=', (int)$ship_zip)
			->where('zip_to', '>=', (int)$ship_zip)
			->where('status', '1')
			->orderByRaw('(zip_to - zip_from) ASC')->first();

		if ($area) return $calculateTax($area);

		// =========================================================
		// PRIORITY 4: Country And State
		// =========================================================
			$area = TaxAreas::where('country', $ship_country)
				->where('states', $ship_state)
				->where('zip_from','=','')->where('zip_to','=','')
				->where('status', '1')
				->orderByRaw('(zip_to - zip_from) ASC')->first();

			if ($area) return $calculateTax($area);

		// =========================================================
		// PRIORITY 5: City + State + Country
		// =========================================================
		if (!empty($ship_city)) {
			$area = TaxAreas::where('country', $ship_country)
				->where('states', $ship_state)
				->whereRaw('LOWER(county) = ?', [strtolower($ship_city)])
				->where('status', '1')
				->orderByRaw('(zip_to - zip_from) ASC')->first();

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
			->orderByRaw('(zip_to - zip_from) ASC')->first();

		if ($area) return $calculateTax($area);

		$TaxareaState = TaxAreaState::where('state', $ship_state)
				->orderBy('taxt_areas_state_id', 'DESC')
				->first();

		if ($TaxareaState) {

			//echo "<pre>"; print_r($TaxareaState); exit;
			$taxRate      = (float) ($TaxareaState->tax_rate ?? 0);
			$taxshipping  = $TaxareaState->shipping ?? 'No';
			$taxinsurance = $TaxareaState->insurance ?? 'No';
			$taxsignature = $TaxareaState->signature ?? 'No';

			$taxableSubTotal =  $subTotal;

			if ($taxshipping === "Yes") {
				Session::put('ShoppingCart.TaxableShipping', "Yes");
				$taxableSubTotal +=  $this->GetAllCharges('ShippingCharge');
			}

			if ($taxinsurance === "Yes") {
				Session::put('ShoppingCart.TaxableShippingInsurance', "Yes");
				$taxableSubTotal +=  $this->GetAllCharges('ShippingInsurance');
			}

			if ($taxsignature === "Yes") {
				Session::put('ShoppingCart.TaxableShippingSignature', "Yes");
				$taxableSubTotal +=  $this->GetAllCharges('ShippingSignature');
			}
			$taxableSubTotal = max(0, (float) NumberFormat($taxableSubTotal));
			$tax = ($taxableSubTotal * $taxRate) / 100;
			Session::put('ShoppingCart.TaxableSubTotal', $taxableSubTotal);
			Session::put('ShoppingCart.Tax', $tax);
			return true;
		}
		else
		{

		 $area = TaxAreas::where('country', $ship_country)
			->where('states', $ship_state)
			->where('status', '1')
			->orderByRaw('(zip_to - zip_from) ASC')
			->first();

			if ($area) return $calculateTax($area);
		}

		// Default = 0
		if ($isFromPayPalProductPage == "Yes") {
			return 0;
		} else {
			Session::put('ShoppingCart.Tax', 0);
			return null;
		}
	}

    public function SetShippingMethod(Request $request)
	{

		$IsGiftCertificateItem = '';

		if($request->ajax())
		{
			$log['ajax_request'] = json_encode($request->all());
		  	addLog('SetShippingMethodStart', $log);
			if(isset($request->ShipMethodID) && $request->ShipMethodID != '')
			{
				Session::put('ShoppingCart.Shipping.ShippingMethodID',$request->ShipMethodID);
				$this->PageData = [];
				$ship_country = Session::get('ShoppingCart.ShippingAddress.country');
				$ship_state = Session::get('ShoppingCart.ShippingAddress.state');
				$ship_zip = Session::get('ShoppingCart.ShippingAddress.zip');
				$ship_city = Session::get('ShoppingCart.ShippingAddress.city');
				$IsCosmo = $request->IsCosmo;
				$IsNandansons = $request->IsNandansons;
				$IsPerfumePW = $request->IsPerfumePW;
				$IsPCA = $request->IsPCA;
				$IsND = $request->IsND;
				$IsVenderItem = $request->IsVenderItem;
				$shipping_mode_id = $request->ShipMethodID;
				$onlyGCPurchased = $request->onlyGCPurchased;
				$isStripeCart = "No";
				if(isset($request->action) && ($request->action == 'stripecart' || $request->action == 'paypalcart'))
				{
					$isStripeCart = "Yes";
					if(isset($request->country) && $request->country!='')
					{
						$ship_country = $request->country;
						Session::put('ShoppingCart.ShippingAddress.country', $ship_country);
					}
					if(isset($request->state) && $request->state!='')
					{
						$ship_state = $request->state;
						Session::put('ShoppingCart.ShippingAddress.state', $ship_state);
					}
					if(isset($request->zip) && $request->zip!='')
					{
						$ship_zip = $request->zip;
						Session::put('ShoppingCart.ShippingAddress.zip',$ship_zip);
					}
					$IsVenderItem = "No";
					$IsCosmo = "No";
					$IsNandansons = "No";
					$IsPerfumePW = "No";
					$IsPCA = "No";
					$IsND = "No";
					$IsMaxaromaTwoDelivery = "No";
					$ShopCartItems = Session::get('ShoppingCart.Cart');
					foreach($ShopCartItems as $ShopItem)
					{
						if((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") || (isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=='Yes') || (isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=='Yes') || (isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=='Yes') && (isset($ShopItem['IsND']) && $ShopItem['IsND']=='Yes') && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
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
						}else{
							$IsMaxaromaTwoDelivery = "No";
						}

						$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$ShopItem);

						//if($ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU1'))
						if($IsGiftCertificateItem == 'No'){
							$onlyGCPurchased = 0;
						}
					}
				}
				$ShipMethod = $this->CheckShippingMethod($shipping_mode_id,$ship_country,$ship_state,$ship_zip,$IsCosmo,$IsNandansons,$IsPerfumePW,$IsPCA,$IsND,$IsVenderItem);

				$VendorPopup = '';
				if($IsVenderItem == 'Yes')
				{
					$vendorDays = Session::get('ShoppingCart.VendorShippingDateVal.setVendorshipDay');
					$vendorName = Session::get('ShoppingCart.VendorShippingDateVal.setVendorNameVal');
					if($vendorName=="IsCosmo" || $vendorName=="IsPCA" || $vendorName=="ISNandansons" || $vendorName=="IsND" || $vendorName=="IsNandansons" || $vendorName=="IsPerfumePW")
					{
						$daysnew = 3;
					}
					if($vendorName=="IsPWW" )
					{
						$daysnew = 3;
					}
					$dt_date =  date('m/d', strtotime("+".$vendorDays. "days"));
					$VendorPopup = str_replace('{$daysval}',$dt_date,config('Settings.VENDORITEM_POPUP_WINDOW'));
					$VendorPopup = str_replace('{$days}',$vendorDays,$VendorPopup);
				}
				if($ShipMethod['status'] == 'success')
				{

					$this->CalculateShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id);
					$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased,'',$ship_city);
					/*if(Session::has('shipping_insurance_charge') && Session::get('shipping_insurance_charge') > 0)
					{
						$this->SetShippingInsuranceCharge('add');
					}*/
					if(isset($isStripeCart) && $isStripeCart=="Yes")
					{
						if($request->ShipMethodID==46)
						{
							$this->SetShippingInsuranceCharge('remove');
						}
					}
					else if($request->ShipMethodID!=46)
					{
						//$this->SetShippingInsuranceCharge('add');
						if(Session::has('shipping_insurance_charge') && Session::get('shipping_insurance_charge') > 0)
						{
							$this->SetShippingInsuranceCharge('remove');
							$this->SetShippingInsuranceCharge('add');
						} else {
							$this->SetShippingInsuranceCharge('remove');
						}
					}
					else if($request->ShipMethodID==46)
					{
						$this->SetShippingInsuranceCharge('remove');
					}

					Session::put('ShoppingCart.EstimatedDeliveryDate',$request->EstDate);
					Session::put('ShoppingCart.Shipping.ShippingDays',$request->EstDate);
					$this->SetupCart();
				} else {
					$this->PageData['ShipMethodError'] = $ShipMethod['error'];
				}
				if(Session::has('shipping_insurance_charge') && Session::get('shipping_insurance_charge') > 0)
				{
					$this->SetShippingInsuranceCharge('remove');
					$this->SetShippingInsuranceCharge('add');
				}
				$this->SetupCart();

				$ShipInsCharge = 0;
				if(Session::has('shipping_insurance_charge') && Session::get('shipping_insurance_charge') > 0)
					$ShipInsCharge = Session::get('shipping_insurance_charge');
				if(!isset($request->action))
				{
					$GA4 = "";
					$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');
					if($onlyGCPurchased==0)
					{
						$GA4 = googleAnalyticsGA4("ShippingMethods",Session::get('ShoppingCart.Cart'),$this->GetNetTotal(), $this->GetAllCoupons('CouponCode'));
					}
					$this->PageData["GAShippingTag"] = $GA4;
					$SubTotalBox =  view('checkout.subtotalbox')->with($this->PageData)->render();
					return response()->json([ 'SubTotalBox' => $SubTotalBox, 'ShipInsCharge' => Price($ShipInsCharge), 'VendorPopup' => $VendorPopup]);
				} else {
					return $ShipMethod['status'];
				}
			}
		}
	}

	public function GetDropshipperAccountDetails($action='',$order_amount=0)
	{
		$DropshipperAccountDetails = array();
		$ds_res = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
		if($ds_res && $ds_res->count() > 0)
		{
			$available_funds = $ds_res[0]['available_funds'];
			if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') == 'Wholesaler')
			{
				if($action == 'dropshipdetails')
					$NetTotal = $order_amount;
				else
					$NetTotal = $this->GetNetTotal();
				if($available_funds >= $NetTotal)
				{
					$DropshipperAccountDetails['fund_available'] = 'Yes';
					$DropshipperAccountDetails['fund_msg'] = "";
					$DropshipperAccountDetails['total_fund'] = $available_funds;
					$DropshipperAccountDetails['total_payment'] = $NetTotal;
					$DropshipperAccountDetails['remaining_fund'] = $available_funds - $NetTotal;
					$DropshipperAccountDetails['required_fund'] = "";
				}
				else
				{
					$DropshipperAccountDetails['fund_available'] = 'No';
					$DropshipperAccountDetails['fund_msg'] = "Your dropshipper account does not have sufficient balance";
					$DropshipperAccountDetails['total_fund'] = $available_funds;
					$DropshipperAccountDetails['total_payment'] = $NetTotal;
					$DropshipperAccountDetails['remaining_fund'] = "";
					$DropshipperAccountDetails['required_fund'] = $NetTotal - $available_funds;
				}
			}
		}
		return $DropshipperAccountDetails;
	}

	public function GetDropshipperFundDetails(Request $request)
	{
		if(isset($request->action) && $request->action == 'dropshipdetails')
		{
			$this->PageData['order_amount'] = $request->order_amount;
			$this->PageData['DropshipperAccountDetails'] = $this->GetDropshipperAccountDetails('dropshipdetails',$request->order_amount);
			return view('myaccount.dropshipper-fund')->with($this->PageData)->render();
		}
	}

	public function CheckMember(Request $request)
	{
		if($request->ajax())
		{
			if(isset($request->action) && $request->action == 'chkmember')
			{
				$Email = $request->email;

				if(checkBlockedUser($Email)==true){
					return false;
				}

				$Result = Customer::where(db::raw('trim(email)'),'=',$Email)->where('registration_type','=','M')->get();
				if($Result && $Result->count()>0)
					return true;
				else
					return false;
			} else if(isset($request->action) && $request->action == 'chkmember_billing')
			{
				$Email = $request->email;

				if(checkBlockedUser($Email,0,"checkout_wo_login")==true){
					return "4~Your account has been blocked, please contact us.";
					exit;
				}

				$Result = Customer::where(db::raw('trim(email)'),'=',$Email)->where('registration_type','=','M')->get();
				$ChkIP = Customer::where('customer_ip','=',$_SERVER['REMOTE_ADDR'])->where('registration_type','=','M')->get();
				if($Result && $Result->count()>0 && $ChkIP && $ChkIP->count() >= 5){
					$link_forgot = config('global.SITE_URL')."forgot-password.html";
					$forgot_password_link = '<a class="btn_link" href="'.$link_forgot.'" style="color:red;">click here</a>';
					$login_link = '<a class="btn_link signinsignup" data-action="sign_in" href="javascript:void(0);" style="color:red;">login</a>';
					$duplicate_ip_msg = str_replace("{click_here}",$forgot_password_link,config('message.Register.DuplicateIP'));
					$duplicate_ip_msg = str_replace("{login}",$login_link,$duplicate_ip_msg);
					return "0~".$duplicate_ip_msg;
				}
				if($Result && $Result->count()>0){
					return "1";
				} else if($ChkIP && $ChkIP->count() >= 5){
					$link_forgot = config('global.SITE_URL')."forgot-password.html";
					$forgot_password_link = '<a class="btn_link" href="'.$link_forgot.'" style="color:red;">click here</a>';
					$login_link = '<a class="btn_link signinsignup" data-action="sign_in" href="javascript:void(0);" style="color:red;">login</a>';
					$duplicate_ip_msg = str_replace("{click_here}",$forgot_password_link,config('message.Register.DuplicateIP'));
					$duplicate_ip_msg = str_replace("{login}",$login_link,$duplicate_ip_msg);
					return "2~".$duplicate_ip_msg;
					//return "2";
				} else {
					return "3";
				}
				/*if($Result && $Result->count()>0)
					return true;
				else
					return false;*/
			}
		}
	}

	public function CheckShippingMethod($shipping_mode_id = NULL, $ship_country,$ship_state,$ship_zip,$IsCosmo='No',$IsNandansons='No',$IsPerfumePW='No',$IsPCA='No',$IsND='No',$IsVenderItem='No')
	{
		$shipping_mode_id = (int)$shipping_mode_id;
		$ShippingMethodRS = ShippingMode::where('shipping_mode_id','=',$shipping_mode_id)->get();

		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('country','like','%'.$ship_country.'%')->get();

				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}

			if($rid && $rid->count() > 0 )
			{
				Session::put('ShoppingCart.Shipping.ShippingMethodName', $ShippingMethodRS[0]['type']);
				Session::put('ShoppingCart.Shipping.ShippingMethodID', $ShippingMethodRS[0]['shipping_mode_id']);

				$days = 0;
				if(($IsVenderItem=="Yes" && $IsPerfumePW=="Yes"))
				{
					$days = $this->GetShippingChargeDays($ship_zip,$ship_state,$ship_country,$shipping_mode_id);
					$days = $days + 3;
				}
				else if(($IsVenderItem=="Yes" && $IsCosmo=="Yes") || ($IsVenderItem=="Yes" && $IsPCA=="Yes") || ($IsVenderItem=="Yes" && $IsNandansons=="Yes") || ($IsVenderItem=="Yes" && $IsPerfumePW=="Yes") || ($IsVenderItem=="Yes" && $IsND=="Yes"))
				{
					$days = $this->GetShippingChargeDays($ship_zip,$ship_state,$ship_country,$shipping_mode_id);
					$days = $days + 3;
				}

				$ShippingMethodRS[0]['days']	= $days;
				///////// added on 20-feb-2019 for vendoritempopup
				if(isset($IsVenderItem) && $IsVenderItem=="Yes" && isset($IsCosmo) && $IsCosmo=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','IsCosmo');
				}
				if(isset($IsVenderItem) && $IsVenderItem=="Yes" && isset($IsNandansons) && $IsNandansons=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','ISNandansons');
				}
				if(isset($IsVenderItem) && $IsVenderItem=="Yes" && isset($IsPCA) && $IsPCA=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','IsPCA');
				}
				if(isset($IsVenderItem) && $IsVenderItem=="Yes" && isset($IsPerfumePW) && $IsPerfumePW=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','IsPWW');
				}
				if(isset($IsVenderItem) && $IsVenderItem=="Yes" && isset($IsND) && $IsND=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','IsND');
				}
				/////////added on 20-feb-2019 for vendoritempopup
				//Session::put('ShoppingCart.Shipping.ShippingDays','');
				if($ShippingMethodRS[0]['days']!='')
				{
					if($ShippingMethodRS[0]['days']==0)
					{
						$estimateShipDate='';
					} else {
						$sdate = date('Y-m-d');
						$edate = date('Y-m-d', strtotime("+" . $ShippingMethodRS[0]['days'] . "days"));
						$satsun_cnt = $this->countWeekendDays($sdate, $edate);
						$holiday_day_arr = ShippingHoliday::whereBetween('holiday_date',[$sdate,$edate])->where('holiday_status','=','1')->where('holiday_date','!=',date("Y-m-d"))->get();
						$holiday_day = $holiday_day_arr->count();
						$exact_shipday = $ShippingMethodRS[0]['days'] + $satsun_cnt + $holiday_day;
						$approx_shipdate = date('Y-m-d', strtotime("+" . $exact_shipday . "days"));
						$extradays = '0';
						$daynew = $this->checkday($approx_shipdate);
						if ($daynew == 'saturday')
						{
							$extradays = '2';
						} else if ($daynew == 'sunday'){
							$extradays = '1';
						}
						$ShippingMethodRS[0]['days'] = $exact_shipday + $extradays;
						Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ShippingMethodRS[0]['days']);
						$dt_date =  date('M d', strtotime("+".$ShippingMethodRS[0]['days']. "days"));
						Session::put('ShoppingCart.Shipping.ShippingDays', 'Estimated Delivery on or before <b>'.$dt_date.'</b>');
					}
				}
				return ['status' => 'success', 'ShipMethodID' => (int)$ShippingMethodRS[0]['shipping_mode_id']];
			}else{
				$errMsg = "The shipping method you selected is not available to your destination. Please select a different method.";
				return ['status' => 'fail','error' => $errMsg];
			}
		}else{
			$errMsg = "The shipping method you selected is not available to your destination. Please select a different method";
			return ['status' => 'fail','error' => $errMsg];
		}
	}

	public function CalculateShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id)
	{
		$TotalDiscount = $this->GetAllDiscounts();
		$subTotal = Session::get('ShoppingCart.SubTotal') - $TotalDiscount['TotalDiscount'];
		$ship_country  = substr($ship_country, 0, 2);
		$shipping_mode_id = (int)$shipping_mode_id;

		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('country','like','%'.$ship_country.'%')->get();

				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}
		}
		if($rid && $rid->count() > 0 )
		{
			$shipping_rule_id 	= $rid[0]["shipping_rule_id"];
			$rule_type  		= $rid[0]["rule_type"];
			$NewdaysVal			= $rid[0]["days"];
			if ($shipping_rule_id != "" && $rule_type == 1 )
			{
				$resultrate = ShippingRate::where('shipping_rule_id','=',$shipping_rule_id)
								->where('order_amount','<=',$subTotal)->orderBy('order_amount','desc')->limit(1);
			}
			else if($shipping_rule_id != "" && ($rule_type==0 || $rule_type==2))
			{
				##$totalitem = $this->getTotalItemInCart() - $this->getGiftCertiCount();
				$totalitem = Session::get('ShoppingCart.TotalItemInCart');
				$resultrate = ShippingRate::where('shipping_rule_id','=',$shipping_rule_id)
								->where('order_amount','<=',$totalitem)->orderBy('order_amount','desc')->limit(1);
				##	FOR FREE SHIPPING FOR ITEM COUNT ##
				if($rid[0]["is_free_ship"]=="Yes")
				{
					if($rid[0]["free_ship_amt"]<=$subTotal)
					{
						$temp_ShippingCharge=0;
						Session::put('ShoppingCart.Shipping.ShippingCharge',$temp_ShippingCharge);
						//return NULL;
					}
				}
				## FOR FREE SHIPPING FOR ITEM COUNT ##
			}

			$rowrate = $resultrate->get();
			$charge=0;
			if(isset($rowrate[0]["charge"]) && $rowrate[0]["charge"] > 0)
			{
				$charge = $rowrate[0]["charge"];
			}
			if($rid[0]["is_free_ship"]=="Yes")
			{
				if($rid[0]["free_ship_amt"]<=$subTotal)
				{
					$charge=0;
					//return $temp_ShippingCharge;
				}
			}
			if ($charge > 0)
				$temp_ShippingCharge = $charge;
			else
				$temp_ShippingCharge = 0;

			########### START CODE FOR CALCULATE PROP SHIP CHARGE###########
			if($rid[0]["prop_item"] > 0)
			{
				if($rid[0]["prop_charge"] > 0)
				{
					if($totalitem >= $rid[0]["prop_item"])
					{
						$extraitem = ($totalitem-$rid[0]["prop_item"]) + 1;
						$propshippingcharge  = ($rid[0]["prop_charge"]*$extraitem);
						$temp_ShippingCharge = $temp_ShippingCharge+$propshippingcharge;
					}
				}
			}

			if($rid[0]["normal_charge"]==""){
				$normal_chrage = 0;
			}else{
				$normal_chrage = $rid[0]["normal_charge"];
			}
			if($rid[0]["light_charge"]==""){
				$light_charge = 0;
			}else{
				$light_charge = $rid[0]["light_charge"];
			}
			if($rid[0]["heavy_charge"]==""){
				$heavy_charge = 0;
			}else{
				$heavy_charge = $rid[0]["heavy_charge"];
			}

			$normalPWeight = 0;
			$lightPWeight = 0;
			$heavyPWeight = 0;

			$CartArr = Session::get('ShoppingCart.Cart');

			for($t=0;$t<count($CartArr);$t++)
			{
				if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Normal" && $normal_chrage > 0)
				{
					$normalPWeight = $normalPWeight + ($normal_chrage * $CartArr[$t]["Qty"]);
				}
				if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Light" && $light_charge > 0)
				{
					$lightPWeight = $lightPWeight + ($light_charge * $CartArr[$t]["Qty"]);
				}
				if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Heavy" && $heavy_charge > 0)
				{
					$heavyPWeight = $heavyPWeight + ($heavy_charge * $CartArr[$t]["Qty"]);
				}
			}

			$temp_ShippingCharge = $temp_ShippingCharge + $normalPWeight + $lightPWeight + $heavyPWeight;

			if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($shipping_mode_id,Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
			{
				$temp_ShippingCharge = 0;
			}
			if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $shipping_mode_id == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
			{
				$temp_ShippingCharge = 0;
			}

			//echo $temp_ShippingCharge;
			Session::put('ShoppingCart.Shipping.ShippingCharge',$temp_ShippingCharge);
		}
		return NULL;
	}

	public function OutOfStockItemsRemove()
	{

		$strSku = array();

		if (!Session::has('ShoppingCart.Cart')) {
			return $strSku;
		}

		$tempCart = Session::get('ShoppingCart.Cart');
		$cnt_row  = count($tempCart);

		if ($cnt_row === 0) {
			return $strSku;
		}

		$allSkus = $this->extractUniqueSkus($tempCart);

		$productRows   = $this->getSellableProductStock($allSkus);

		$hasStoreItems = $this->cartHasStoreItems($tempCart);
		$storeStockMap = $hasStoreItems ? $this->getStoreStockForCart($allSkus) : collect();

		$StockLeftArray = [];

		for ($i = 0; $i < $cnt_row; $i++) {
			$sku = $tempCart[$i]["SKU"];

			$ProductRow = $productRows->get($sku);

			if (!$ProductRow) {
				continue;
			}

			[$isOutOfStock, $stockLeft] = $this->evaluateStockForItem($tempCart[$i], $ProductRow, $storeStockMap);

			if ($isOutOfStock) {
				$strSku[] = $sku;
			}

			$StockLeftArray[] = $stockLeft;

		}
		Session::put('ShowStockLeftMessage', 'No');
		for ($s = 0; $s < count($StockLeftArray); $s++) {
			if ($StockLeftArray[$s] > 0 && $StockLeftArray[$s] < 10) {
				Session::put('ShowStockLeftMessage', 'Yes');
				break;
			}
		}

		return $strSku;
	}

	private function extractUniqueSkus(array $tempCart): array
	{
		$skus = [];
		foreach ($tempCart as $item) {
			$skus[$item["SKU"]] = true;
		}
		return array_keys($skus);
	}

	private function cartHasStoreItems(array $tempCart): bool
	{
		foreach ($tempCart as $item) {
			if (isset($item["OrderType"]) && $item["OrderType"] == "Store") {
				return true;
			}
		}
		return false;
	}

	private function getSellableProductStock(array $skus)
	{
		$columns = [
			'pu_products.sku',
			'pu_products.cosmo_current_stock',
			'pu_products.nandansons_current_stock',
			'pu_products.nd_current_stock',
			'pu_products.perfumeworldwide_currentstock',
			'pu_products.pca_current_stock',
			'pu_products.current_stock',
			'pu_products.minimum_stock',
		];

		$active = Products::whereIn('sku', $skus)
			->where('status', '1')
			->select($columns)
			->get();

		$private = Products::query()
			->join('pu_products_one as po', 'pu_products.products_id', '=', 'po.products_id')
			->whereIn('pu_products.sku', $skus)
			->where('pu_products.status', '2')
			->where('po.is_private', 'Yes')
			->where('po.private_code', '!=', '')
			->select($columns)
			->get();
		//echo "<pre>"; print_r($active); exit;
		return $active->concat($private)->keyBy('sku');
	}

	private function getStoreStockForCart(array $skus)
	{
		if (!Auth::guard('store')->check()) {
			return collect();
		}

		$store = Auth::guard('store')->user();

		return DB::table('pu_store_inventory as ps')
			->join('pu_products', 'pu_products.products_id', '=', 'ps.products_id')
			->where('ps.store_id', $store->store_id)
			->whereIn('pu_products.sku', $skus)
			->select('pu_products.sku', 'ps.current_stock as store_currentStock')
			->get()
			->keyBy('sku');
	}

	private function evaluateStockForItem(array $item, $ProductRow, $storeStockMap): array
	{
		$isStoreOrder = isset($item["OrderType"]) && $item["OrderType"] == "Store";
		$storeRow = $isStoreOrder ? $storeStockMap->get($item["SKU"]) : null;
		$store_currentStock = $storeRow->store_currentStock ?? null;

		if ($isStoreOrder && $store_currentStock <= 0) {
			return [true, $store_currentStock];
		}

		$vendorChecks = [
			'IsCosmo'      => 'cosmo_current_stock',
			'IsPCA'        => 'pca_current_stock',
			'IsNandansons' => 'nandansons_current_stock',
			'IsPerfumePW'  => 'perfumeworldwide_currentstock',
			'IsND'         => 'nd_current_stock',
		];

		foreach ($vendorChecks as $flag => $field) {
			if (($item[$flag] ?? 'No') == "Yes" && $item["VendorSKU"] != '' && $item["OrderType"] == "Website") {
				$vendorStock = $ProductRow->$field;
				$stockLeft = $vendorStock - $ProductRow->minimum_stock;
				$outOfStock = ($vendorStock <= 0 || $item["Qty"] > $vendorStock);
				return [$outOfStock, $stockLeft];
			}
		}

		if (($ProductRow->current_stock <= $ProductRow->minimum_stock || $item["Qty"] > $ProductRow->current_stock)
			&& $item["VendorSKU"] == '' && $item["OrderType"] == "Website") {
			return [true, $ProductRow->current_stock - $ProductRow->minimum_stock];
		}

		// Not out of stock — compute stock-left for the low-stock UI message
		if ($isStoreOrder) {
			return [false, $store_currentStock];
		}
		foreach ($vendorChecks as $flag => $field) {
			if (($item[$flag] ?? 'No') == "Yes" && $item["VendorSKU"] != '' && $item["OrderType"] == 'Website') {
				return [false, $ProductRow->$field - $ProductRow->minimum_stock];
			}
		}
		if ($item["OrderType"] == 'Website') {
			return [false, $ProductRow->current_stock - $ProductRow->minimum_stock];
		}

		return [false, 0];
	}

	public function paypalBuynowOrder_bk_1(Request $request){

		// $product_id = $request->product_id;
		// $product_sku = $request->product_sku;
		// $product_qty = $request->product_qty;
		$checkout_type = 'G';
		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." Before PayPal Buy Now Insert Order :".json_encode($request->all())."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}
		$SubTotal = NumberFormat($request->total_price);

		if(isset($request->payer_email) && $request->payer_email!=''){
			if(checkBlockedUser($request->payer_email,0,'PayPalGuest')==true)
			{
				Session::flash('PlaceOrderError',config('message.Register.Blocked'));
				return "Blocked";
			}
		}
		//if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler'){
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(Auth::user() && Auth::user()->is_dropshipper != 'Yes' && strtolower(Auth::user()->eusertype ?? '') == 'wholesaler'){
		if($normaluser && $normaluser->is_dropshipper != 'Yes' && strtolower($normaluser->eusertype ?? '') == 'wholesaler'){
			$w_min_order_amt  = NumberFormat(config('Settings.WHOLESALER_MIN_ORDER_AMOUNT'));
			if($SubTotal < $w_min_order_amt){
				$msg = "For wholesaler minimum order amount should be ".$this->Make_Price($w_min_order_amt,true);
				Session::flash('PlaceOrderError',$msg);
				return "WholeSaleMinOrder";
			}
		}

		if(Session::has('etype') && Session::get('etype') == 'M')
			$checkout_type = 'M';
		else
			$checkout_type = 'G';

		$OrderInsert = array (
			'customer_id'		=> 0,//$customer_id,
			'sub_total' 		=> $SubTotal,
			'shipping_amt' 		=> '0.00', //$ShipMethodCharge,
			'tax' 				=> '0.00', //$this->GetAllCharges('Tax'),
			'gift_charge' 		=> '0.00',//$this->GetAllCharges('GiftWrappingCharge'),
			'gift_message' 		=> '',
			'is_gift_order'		=> 'No',
			'handling_charge' 	=> '0.00',
			'wire_discount' 	=> '0.00',
			'auto_discount' 	=> '0.00',//$this->GetAllDiscounts('AutoDiscount'),
			'quantity_discount'	=> '0.00',//$this->GetAllDiscounts('QuantityDiscount'),
			'reward_discount'	=> '0.00',//$this->GetAllDiscounts('YotpoRewardDiscount'),
			'coupon_amount' 	=> '0.00',//$this->GetAllDiscounts('CouponDiscount'),
			'coupon_id' 		=> '',//$coupon_id,
			'Second_coupon_id'	=> '',//$yotporewardcode,
			'coupon_code' 		=> '',//$OneCouponCode,
			'gc_amount' 		=> '0.00',//$this->GetAllDiscounts('GiftCoupon'),
			'gc_code' 			=> '',//$GCCode,
			'refer_id'			=> '',//$referDiscountId,
			'refer_amount' 		=> '0.00',//$this->GetAllDiscounts('AutoReferDiscount'),
			'order_total' 		=> '0.00',//$this->GetNetTotal(),
			'shipinfo' 			=> '',//$ShipMethodName,
			'payment_type' 		=> '',//$Payment_Type,
			'payment_method' 	=> '',//$Payment_Method,
			'pay_status' 		=> 'Unpaid',
			'customer_comment' 	=> '',//$customer_comment,
			'status'			=> 'Pending',
			'currency_info'		=> '',//$currency_info,
			'checkout_type' 	=> $checkout_type, //'',//$checkout_type,
			'user_type' 		=> '',//$w_user_type,
			'ilevelid' 			=> '',//$w_ilevelid,
			'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
			'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
			'is_only_gc'		=> '0',//(string)$onlyGCPurchased,
			'free_gift'			=> '',//$free_gift,
			'ship_first_name' 	=> '',//isset($Shipping['first_name']) ? $Shipping['first_name'] : '',
			'ship_last_name' 	=> '',//isset($Shipping['last_name']) ? $Shipping['last_name'] : '',
			'ship_company' 		=> '',//isset($Shipping['company']) ? $Shipping['company'] : '',
			'ship_email' 		=> '',//isset($Shipping['email']) ? $Shipping['email'] : '',
			'ship_address1' 	=> '',//isset($Shipping['address1']) ? $Shipping['address1'] : '',
			'ship_address2' 	=> '',//isset($Shipping['address2']) ? $Shipping['address2'] : '',
			'ship_city' 		=> '',//isset($Shipping['city']) ? $Shipping['city'] : '',
			'ship_zip' 			=> '',//isset($Shipping['zip']) ? $Shipping['zip'] : '',
			'ship_state' 		=> '',//isset($Shipping['state']) ? $Shipping['state'] : '',
			'ship_country' 		=> '',//isset($Shipping['country']) ? $Shipping['country'] : '',
			'ship_phone' 		=> '',//isset($Shipping['phone']) ? $Shipping['phone'] : '',
			'bill_first_name' 	=> '',//isset($Billing['first_name']) ? $Billing['first_name'] : '',
			'bill_last_name' 	=> '',//isset($Billing['last_name']) ? $Billing['last_name'] : '',
			'bill_company' 		=> '',//isset($Billing['company']) ? $Billing['company'] : '',
			'bill_email' 		=> '',//isset($Billing['email']) ? $Billing['email'] : '',
			'bill_address1' 	=> '',//isset($Billing['address1']) ? $Billing['address1'] : '',
			'bill_address2' 	=> '',//isset($Billing['address2']) ? $Billing['address2'] : '',
			'bill_city' 		=> '',//isset($Billing['city']) ? $Billing['city'] : '',
			'bill_zip' 			=> '',//isset($Billing['zip']) ? $Billing['zip'] : '',
			'bill_state' 		=> '',//isset($Billing['state']) ? $Billing['state'] : '',
			'bill_country' 		=> '',//isset($Billing['country']) ? $Billing['country'] : '',
			'bill_phone' 		=> '',//isset($Billing['phone']) ? $Billing['phone'] : '',
			'gift_from'				=> '',//$gift_from,
			'gift_to'				=> '',//$gift_to,
			'gift_message_customer'	=> '',//$gift_message_customer,
			'cust_current_credit_limit' => '',//$cust_current_credit_limit,
			'apply_credit'          => '',//$apply_credit,
			'remaining_credit'      => '',//$remaining_credit,
			'use_credit_limit'      => '',//$use_credit_limit,
			'is_dropship_order'     => '',//$is_dropship_order,
			'shipping_signature'	 => '',//$OrdShipSignature,
			'is_shipping_signature' => '',//$ShippingSignatureFlag,
			'Is_GiftCertificatPurchase' => '',//$this->GetCartAttribute('CheckGCPurchasedVal'),
			'EstimatedDeliveryDate' 	=> '',//$EstimatedDeliveryDate,
			'fullshipping_info'		=> 	'',//$fullShippingname,
			'merge_note'		=> 	'',//$merge_note,
			'bogo_discount'	=> '',//$this->GetAllDiscounts('DogoDiscount'),
			'is_maxtwoday'	=> '',//$is_maxtwoday,
			'route_shipping_insurance_charge' => '',//$OrdShipInsurance,
			'vLang_flag' => '',//Session::get('ShoppingCart.YotpoFreeGiftCoupon'),
			'paymentintentid' => '',//$paymentintentid,
			'transaction_info' 		=>	'',//Session::get("PayMethodRes")?Session::get("PayMethodRes"):'',
			'payment_gateway_response' => '',//Session::get("PayMethodRes")?Session::get("PayMethodRes"):''
		);
		$PlaceOrder = Order::create($OrderInsert);

		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." After PayPal Buy Now Insert Order :".json_encode($request->all())."----".json_encode($PlaceOrder)."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		return $OrderID = $PlaceOrder->orders_id;
	}

	public function paypalBuynowOrder(Request $request){

		// $product_id = $request->product_id;
		// $product_sku = $request->product_sku;
		// $product_qty = $request->product_qty;
		$checkout_type = 'G';
		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." Before PayPal Buy Now Insert Order :".json_encode($request->all())."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}
		$SubTotal = NumberFormat($request->total_price);

		if(isset($request->payer_email) && $request->payer_email!=''){
			if(checkBlockedUser($request->payer_email,0,'PayPalGuest')==true)
			{
				Session::flash('PlaceOrderError',config('message.Register.Blocked'));
				return "Blocked";
			}
		}
		//if(Session::has('eusertype') && strtolower(Session::get('eusertype') ?? '')=='wholesaler'){
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(Auth::user() && Auth::user()->is_dropshipper != 'Yes' && strtolower(Auth::user()->eusertype ?? '') == 'wholesaler'){
		if($normaluser && $normaluser->is_dropshipper != 'Yes' && strtolower($normaluser->eusertype ?? '') == 'wholesaler'){
			$w_min_order_amt  = NumberFormat(config('Settings.WHOLESALER_MIN_ORDER_AMOUNT'));
			if($SubTotal < $w_min_order_amt){
				$msg = "For wholesaler minimum order amount should be ".$this->Make_Price($w_min_order_amt,true);
				Session::flash('PlaceOrderError',$msg);
				return "WholeSaleMinOrder";
			}
		}

		if(Session::has('etype') && Session::get('etype') == 'M')
			$checkout_type = 'M';
		else
			$checkout_type = 'G';

		$payer_email = '';
		$payer_address1 = '';
		$payer_state = '';
		$payer_city = '';
		$payer_country = '';
		$payer_postcode = '';
		$order_details = '';

		$product_id = 0;// $request->product_id;
		$product_sku = ""; //$request->product_sku;

		$product_price = "0.00";//$request->prod_price;
		$product_qty = "0"; //$request->prod_qty;

		if(isset($request->product_id) && $request->product_id!=''){
			$product_id = $request->product_id;
		}
		if(isset($request->product_sku) && $request->product_sku!=''){
			$product_sku = $request->product_sku;
		}
		if(isset($request->prod_price) && $request->prod_price!=''){
			$product_price = $request->prod_price;
		}
		if(isset($request->prod_qty) && $request->prod_qty!=''){
			$product_qty = $request->prod_qty;
		}

		if(isset($request->payer_email) && $request->payer_email != ''){
			$payer_email = $request->payer_email;
		}
		if(isset($request->payer_address1) && $request->payer_address1 != ''){
			$payer_address1 = $request->payer_address1;
		}
		if(isset($request->payer_state) && $request->payer_state != ''){
			$payer_state = $request->payer_state;
		}
		if(isset($request->payer_city) && $request->payer_city != ''){
			$payer_city = $request->payer_city;
		}
		if(isset($request->payer_country) && $request->payer_country != ''){
			$payer_country = $request->payer_country;
		}
		if(isset($request->payer_postcode) && $request->payer_postcode != ''){
			$payer_postcode = $request->payer_postcode;
		}
		if(isset($request->order_details) && $request->order_details != ''){
			$order_details = $request->order_details;
		}

		$paypal_order_details = array();
		$order_total = "0.00";
		$shipping_total = "0.00";
		$tax_total = "0.00";
		$payer_fname = "";
		$payer_lname = "";
		$ship_name = "";

		$shipping_option_nm = "";
		$shipping_option_id = "";
		if($order_details != ''){
			$paypal_order_details = json_decode($order_details,true);
			if(isset($paypal_order_details['purchase_units'][0]['amount']['value']) && $paypal_order_details['purchase_units'][0]['amount']['value']!=''){
				$order_total = (float)$paypal_order_details['purchase_units'][0]['amount']['value'];
			}
			if(isset($paypal_order_details['purchase_units'][0]['amount']['breakdown']['shipping']['value']) && $paypal_order_details['purchase_units'][0]['amount']['breakdown']['shipping']['value']!=''){
				$shipping_total = (float)$paypal_order_details['purchase_units'][0]['amount']['breakdown']['shipping']['value'];
			}
			if(isset($paypal_order_details['purchase_units'][0]['amount']['breakdown']['tax_total']['value']) && $paypal_order_details['purchase_units'][0]['amount']['breakdown']['tax_total']['value']!=''){
				$tax_total = (float)$paypal_order_details['purchase_units'][0]['amount']['breakdown']['tax_total']['value'];
				$tax_total = number_format((float)$tax_total, 2, '.', '');
			}
			if(isset($paypal_order_details['payer']['name']['given_name']) && $paypal_order_details['payer']['name']['given_name']!=''){
				$payer_fname = $paypal_order_details['payer']['name']['given_name'];
			}
			if(isset($paypal_order_details['payer']['name']['surname']) && $paypal_order_details['payer']['name']['surname']!=''){
				$payer_lname = $paypal_order_details['payer']['name']['surname'];
			}
			if(isset($paypal_order_details['purchase_units'][0]['shipping']['name']['full_name']) && $paypal_order_details['purchase_units'][0]['shipping']['name']['full_name']!=''){
				$ship_name = $paypal_order_details['purchase_units'][0]['shipping']['name']['full_name'];
			}
			if(isset($paypal_order_details['purchase_units'][0]['shipping']['options'][0]['id']) && $paypal_order_details['purchase_units'][0]['shipping']['options'][0]['id']!=''){
				$shipping_option_id = $paypal_order_details['purchase_units'][0]['shipping']['options'][0]['id'];
			}
			if(isset($paypal_order_details['purchase_units'][0]['shipping']['options'][0]['label']) && $paypal_order_details['purchase_units'][0]['shipping']['options'][0]['label']!=''){
				$shipping_option_nm = $paypal_order_details['purchase_units'][0]['shipping']['options'][0]['label'];
			}

			// $ret['res_pu_shipping_option_id'] = isset($orderData['purchase_units'][0]['shipping']['options'][0]['id']) ? $orderData['purchase_units'][0]['shipping']['options'][0]['id'] : '';
			// $ret['res_pu_shipping_option_name'] = isset($orderData['purchase_units'][0]['shipping']['options'][0]['label']) ? $orderData['purchase_units'][0]['shipping']['options'][0]['label'] : '';
		}

		$newrequest = [
			'bill_country' 			=> $payer_country, //((Session::has('Paypalcountry')) ? Session::get('Paypalcountry') : ''),
			'bill_fname' 			=> '',
			'bill_lname'				=> '',
			'bill_address1' 		=> $payer_address1, //'',
			'bill_address2' 		=> '',
			'bill_city' 				=> $payer_city,  //((Session::has('Paypalcity')) ? Session::get('Paypalcity') : ''),
			'bill_state' 				=> $payer_state, //((Session::has('Paypalstate')) ? Session::get('Paypalstate') : ''),
			'bill_zip' 				=> $payer_postcode, //((Session::has('Paypalzipcode')) ? Session::get('Paypalzipcode') : ''),
			'bill_phone' 			=> '',
			'bill_email' 				=> $payer_email, //$CustomerEmail,
			'bill_cemail' 			=> $payer_email, //$CustomerEmail,
			'sameasbill' 			=> 'No',
			'bill_other_state'		=> $payer_country != 'US' ? $payer_state : ''
		];

		$this->SetBillingAddress($newrequest);

		$newrequestShip = [
			'ship_country' 				=> $payer_country, //((Session::has('Paypalcountry')) ? Session::get('Paypalcountry') : ''),
			'ship_fname' 				=> '',
			'ship_lname'				=> '',
			'ship_company'				=> '',
			'ship_address1' 			=> $payer_address1,//'',
			'ship_address2' 			=> '',
			'ship_city' 				=> $payer_city,  //((Session::has('Paypalcity')) ? Session::get('Paypalcity') : ''),
			'ship_state' 				=> $payer_state, //((Session::has('Paypalstate')) ? Session::get('Paypalstate') : ''),
			'ship_zip' 					=> $payer_postcode, //((Session::has('Paypalzipcode')) ? Session::get('Paypalzipcode') : ''),
			'ship_phone' 				=> '',
			'ship_email' 				=> $payer_email, //$CustomerEmail,
			'sameasbill' 				=> 'No',
			'ship_other_state'			=> $payer_country != 'US' ? $payer_state : ''
		];

		$this->SetShippingAddress($newrequestShip);
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		if(!$normaluser && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		{
			$this->SetGuestCustomer($newrequest);
		} else {
			$this->CustomerInfoUpdate($newrequest);
		}

		if($shipping_option_nm != '')	{
			$method_arr = explode("-",$shipping_option_nm);
			if(count($method_arr) > 0){
				$max_key = max(array_keys($method_arr));
				if(isset($method_arr[0]) && $method_arr[0]!=''){
					$ShipMethodName = $method_arr[0];

					$ShippingMethodRS = ShippingMode::where('status','=','1')->where('shipping_mode_id','=',$shipping_option_id)->get();
					if($ShippingMethodRS[0]['type'] !=''){
						$ShipMethodName = strip_tags($ShippingMethodRS[0]['type'] ?? '');
					}
					//Session::put('ShoppingCart.Shipping.ShippingMethodName',$method_arr[0]);
					//Session::put('ShoppingCart.Shipping.ShippingMethodName',$ShipMethodName);
				}

				if(isset($method_arr[$max_key]) && $method_arr[$max_key]!=''){
					$EstimatedDeliveryDate = date("Y-m-d",strtotime($method_arr[$max_key]));
					//Session::put("ShoppingCart.EstimatedDeliveryDate",$method_arr[$max_key]);
				}
			}

		}

		$currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');

		$fullShippingname =  $ShipMethodName. " <b>(".Session::get('currency_symbol').$shipping_total.")</b> ";

		$cur_date = date("Y-m-d");
		if($EstimatedDeliveryDate == "" && strtotime($EstimatedDeliveryDate) < strtotime($cur_date)){
			$EstimatedDeliveryDate = "0000-00-00 00:00:00";
		}

		if($product_id > 0){
			$ProdInfo = DB::table('pu_products as p')
					->join('pu_products_one as po','p.products_id','=','po.products_id')
					->where(function($query){
						$query->orWhere('p.status','=','1');
						$query->OrWhere(function($qry){
							$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');
						});
					})
					->where('p.products_id','=',$product_id)->get();

			$ProductRs = $this->SetProduct($ProdInfo[0]);

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
			if($ProductRs->WebsiteStock == "Out")
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

			$IsMaxaromaTwoDelivery = 'No';
			if($ProductRs->WebsiteStock == "In")
			{
				$IsMaxaromaTwoDelivery = $ProductRs->maxtwodaydelivery;
			}
			if(isset($ProductRs->IsDealProducts) && $ProductRs->IsDealProducts == "Yes")
			{
				$IsMaxaromaTwoDelivery = 'No';
			}
		}

		$OrderInsert = array (
			'customer_id'		=> Session::has('sess_icustomerid') ? Session::get('sess_icustomerid') : 0,//0,//$customer_id,
			'sub_total' 		=> $SubTotal,
			'shipping_amt' 		=> $shipping_total,//'0.00', //$ShipMethodCharge,
			'tax' 				=> $tax_total,//'0.00', //$this->GetAllCharges('Tax'),
			'gift_charge' 		=> '0.00',//$this->GetAllCharges('GiftWrappingCharge'),
			'gift_message' 		=> '',
			'is_gift_order'		=> 'No',
			'handling_charge' 	=> '0.00',
			'wire_discount' 	=> '0.00',
			'auto_discount' 	=> '0.00',//$this->GetAllDiscounts('AutoDiscount'),
			'quantity_discount'	=> '0.00',//$this->GetAllDiscounts('QuantityDiscount'),
			'reward_discount'	=> '0.00',//$this->GetAllDiscounts('YotpoRewardDiscount'),
			'coupon_amount' 	=> '0.00',//$this->GetAllDiscounts('CouponDiscount'),
			'coupon_id' 		=> '',//$coupon_id,
			'Second_coupon_id'	=> '',//$yotporewardcode,
			'coupon_code' 		=> '',//$OneCouponCode,
			'gc_amount' 		=> '0.00',//$this->GetAllDiscounts('GiftCoupon'),
			'gc_code' 			=> '',//$GCCode,
			'refer_id'			=> '',//$referDiscountId,
			'refer_amount' 		=> '0.00',//$this->GetAllDiscounts('AutoReferDiscount'),
			'order_total' 		=> $order_total,//'0.00',//$this->GetNetTotal(),
			'shipinfo' 			=> $ShipMethodName,//'',//$ShipMethodName,
			'payment_type' 		=> 'PAYMENT_PAYPALEC',//'',//$Payment_Type,
			'payment_method' 	=> 'Paypal Express Checkout',//'',//$Payment_Method,
			'pay_status' 		=> 'Unpaid',
			'customer_comment' 	=> '',//$customer_comment,
			'status'			=> 'Pending',
			'currency_info'		=> $currency_info,//'',//$currency_info,
			'checkout_type' 	=> $checkout_type, //'',//$checkout_type,
			'user_type' 		=> '',//$w_user_type,
			'ilevelid' 			=> '',//$w_ilevelid,
			'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
			'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
			'is_only_gc'		=> '0',//(string)$onlyGCPurchased,
			'free_gift'			=> '',//$free_gift,
			'ship_first_name' 	=> $ship_name,//'',//isset($Shipping['first_name']) ? $Shipping['first_name'] : '',
			'ship_last_name' 	=> '',//isset($Shipping['last_name']) ? $Shipping['last_name'] : '',
			'ship_company' 		=> '',//isset($Shipping['company']) ? $Shipping['company'] : '',
			'ship_email' 		=> $payer_email,//'',//isset($Shipping['email']) ? $Shipping['email'] : '',
			'ship_address1' 	=> $payer_address1, //'',//isset($Shipping['address1']) ? $Shipping['address1'] : '',
			'ship_address2' 	=> '',//isset($Shipping['address2']) ? $Shipping['address2'] : '',
			'ship_city' 		=> $payer_city,//'',//isset($Shipping['city']) ? $Shipping['city'] : '',
			'ship_zip' 			=> $payer_postcode,//'',//isset($Shipping['zip']) ? $Shipping['zip'] : '',
			'ship_state' 		=> $payer_state,//'',//isset($Shipping['state']) ? $Shipping['state'] : '',
			'ship_country' 		=> $payer_country, //'',//isset($Shipping['country']) ? $Shipping['country'] : '',
			'ship_phone' 		=> '',//isset($Shipping['phone']) ? $Shipping['phone'] : '',
			'bill_first_name' 	=> $payer_fname,//'',//isset($Billing['first_name']) ? $Billing['first_name'] : '',
			'bill_last_name' 	=> $payer_lname,//'',//isset($Billing['last_name']) ? $Billing['last_name'] : '',
			'bill_company' 		=> '',//isset($Billing['company']) ? $Billing['company'] : '',
			'bill_email' 		=> $payer_email,//'',//isset($Billing['email']) ? $Billing['email'] : '',
			'bill_address1' 	=> $payer_address1,//'',//isset($Billing['address1']) ? $Billing['address1'] : '',
			'bill_address2' 	=> '',//isset($Billing['address2']) ? $Billing['address2'] : '',
			'bill_city' 		=> $payer_city,//'',//isset($Billing['city']) ? $Billing['city'] : '',
			'bill_zip' 			=> $payer_postcode,//'',//isset($Billing['zip']) ? $Billing['zip'] : '',
			'bill_state' 		=> $payer_state, //'',//isset($Billing['state']) ? $Billing['state'] : '',
			'bill_country' 		=> $payer_country,//'',//isset($Billing['country']) ? $Billing['country'] : '',
			'bill_phone' 		=> '',//isset($Billing['phone']) ? $Billing['phone'] : '',
			'gift_from'				=> '',//$gift_from,
			'gift_to'				=> '',//$gift_to,
			'gift_message_customer'	=> '',//$gift_message_customer,
			'cust_current_credit_limit' => '',//$cust_current_credit_limit,
			'apply_credit'          => '',//$apply_credit,
			'remaining_credit'      => '',//$remaining_credit,
			'use_credit_limit'      => '',//$use_credit_limit,
			'is_dropship_order'     => '',//$is_dropship_order,
			'shipping_signature'	 => '',//$OrdShipSignature,
			'is_shipping_signature' => '',//$ShippingSignatureFlag,
			'Is_GiftCertificatPurchase' => '',//$this->GetCartAttribute('CheckGCPurchasedVal'),
			'EstimatedDeliveryDate' 	=> $EstimatedDeliveryDate, //'',//$EstimatedDeliveryDate,
			'fullshipping_info'		=> 	$fullShippingname,//'',//$fullShippingname,
			'merge_note'		=> 	'',//$merge_note,
			'bogo_discount'	=> '',//$this->GetAllDiscounts('DogoDiscount'),
			'is_maxtwoday'	=> $IsMaxaromaTwoDelivery,//'',//$is_maxtwoday,
			'route_shipping_insurance_charge' => '',//$OrdShipInsurance,
			'vLang_flag' => '',//Session::get('ShoppingCart.YotpoFreeGiftCoupon'),
			'paymentintentid' => '',//$paymentintentid,
			'transaction_info' 		=>	'',//Session::get("PayMethodRes")?Session::get("PayMethodRes"):'',
			'payment_gateway_response' => $order_details //'',//Session::get("PayMethodRes")?Session::get("PayMethodRes"):''
		);
		$PlaceOrder = Order::create($OrderInsert);

		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." After PayPal Buy Now Insert Order :".json_encode($request->all())."----".json_encode($PlaceOrder)."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		$OrderID = $PlaceOrder->orders_id;

		$OrderUpdate = array (
			'orders_no' 		=> 'OR'.$OrderID
		);

		$CurrOrder = Order::find($OrderID);
		$udpRefer = $CurrOrder->update($OrderUpdate);

		$prores = Products::select('product_name','short_description')->where('products_id','=',$product_id)->get();
		$prodnm = $prores[0]["product_name"].'<br>'.$prores[0]["short_description"];

		$OrderDetailInsert = array (
			'orders_id'					=> $OrderID,
			'orders_no'					=> "OR".$OrderID, // To add 'OR' Change on :: 06-10-2015
			'products_id'				=> $product_id,
			'sku' 						=> $product_sku, //$PayPalResponse['product_sku'],
			'product_name'				=> $prodnm, //$PayPalResponse['res_pu_items_name'],
			'quantity' 					=> $product_qty,
			'price' 					=> (float)$product_price,
			'total' 					=> ((float)$product_price * (float)$product_qty),
			'status' 					=> '1',
			'item_price' 				=> (float)$product_price,
			'excluded_flag'  			=> '',
			'is_gift_wrap'				=> '',
			'is_free_gift_products' 	=> 'No',
			'VendorSKU'					=> '',
			'IsCosmo'					=> $IsCosmo,
			'IsNandansons'  			=> $IsNandansons,
			'IsPerfumePW'				=> $IsPerfumePW,
			'IsPCA'						=> '',
			'IsND'						=> $IsND,
			'coupon_itemwise_discount'	=> '',
			'handling_time_str'			=> '',
			'attribute_info'        	=> 'No',
			'actual_price'				=> '',
			'excluded_flag'				=> '',
			'item_tax_amount'			=> $tax_total
		);
		$OrdDetail = OrderDetail::create($OrderDetailInsert);

		return $OrderID;// = $PlaceOrder->orders_id;
	}

	public function PlacePayPalOrder(Request $request)
	{
		//return json_encode($request->orderData);
		$orderData = $request->orderData;
		$product_id = $request->product_id;
		$product_sku = $request->product_sku;

		$product_price = $request->prod_price;
		$product_qty = $request->prod_qty;
		$order_ref_id = $request->ref_id;
		//----------------------------------

		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." Before PayPal Buy Now Approval Order :".json_encode($request->all())."\n";
			$stringData .= date("m/d/Y H:i:s").json_encode($orderData)."---".$product_id."---".$product_sku."---".$product_price."---".$product_qty."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		$prod_total = 0;
		if($product_qty > 0 && $product_price > 0){
			$prod_total = (float)$product_qty * (float)$product_price;
			$prod_total = NumberFormat($prod_total);
		}
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		$ret['res_id'] = isset($orderData['id']) ? $orderData['id'] : '';
		$ret['res_intent'] = isset($orderData['intent']) ? $orderData['intent'] : '';
		$ret['res_createtime'] = isset($orderData['create_time']) ? $orderData['create_time'] : '';

		$ret['res_links'] = isset($orderData['links'][0]['href']) ? $orderData['links'][0]['href'] : '';
		$ret['res_links_method'] = isset($orderData['links'][0]['method']) ? $orderData['links'][0]['method'] : '';

		//$ret['res_payer_fname'] = Auth::user() ? Auth::user()->first_name : (isset($orderData['payer']['name']['given_name']) ? $orderData['payer']['name']['given_name'] : '');
		$ret['res_payer_fname'] = $normaluser ? $normaluser->first_name : (isset($orderData['payer']['name']['given_name']) ? $orderData['payer']['name']['given_name'] : '');
		//$ret['res_payer_lname'] = Auth::user() ? Auth::user()->last_name : (isset($orderData['payer']['name']['surname']) ? $orderData['payer']['name']['surname'] : '');
		$ret['res_payer_lname'] = $normaluser ? $normaluser->last_name : (isset($orderData['payer']['name']['surname']) ? $orderData['payer']['name']['surname'] : '');
		$ret['res_payer_country_code'] = isset($orderData['payer']['address']['country_code']) ? $orderData['payer']['address']['country_code'] : '';
		//$ret['res_payer_email'] = Auth::user() ? Auth::user()->email : (isset($orderData['payer']['email_address']) ? $orderData['payer']['email_address'] : '');
		$ret['res_payer_email'] = $normaluser ? $normaluser->email : (isset($orderData['payer']['email_address']) ? $orderData['payer']['email_address'] : '');
		$ret['res_payer_id'] = isset($orderData['payer']['payer_id']) ? $orderData['payer']['payer_id'] : '';

		$ret['res_pu_amount_currency_code'] = isset($orderData['purchase_units'][0]['amount']['currency_code']) ? $orderData['purchase_units'][0]['amount']['currency_code'] : '';
		$ret['res_pu_amount_value'] = isset($orderData['purchase_units'][0]['amount']['value']) ? $orderData['purchase_units'][0]['amount']['value'] : '';

		$ret['res_pu_amount_breakdown_handling_currency_code'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['handling']['currency_code']) ? $orderData['purchase_units'][0]['amount']['breakdown']['handling']['currency_code'] : '';
		$ret['res_pu_amount_breakdown_handling_value'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['handling']['value']) ? $orderData['purchase_units'][0]['amount']['breakdown']['handling']['value'] : '';

		$ret['res_pu_amount_breakdown_insurance_currency_code'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['insurance']['currency_code']) ? $orderData['purchase_units'][0]['amount']['breakdown']['insurance']['currency_code'] : '';
		$ret['res_pu_amount_breakdown_insurance_value'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['insurance']['value']) ? $orderData['purchase_units'][0]['amount']['breakdown']['insurance']['value'] : '';

		$ret['res_pu_amount_breakdown_item_total_currency_code'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['item_total']['currency_code']) ? $orderData['purchase_units'][0]['amount']['breakdown']['item_total']['currency_code'] : '';
		$ret['res_pu_amount_breakdown_item_total_value'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['item_total']['value']) ? $orderData['purchase_units'][0]['amount']['breakdown']['item_total']['value'] : '';

		$ret['res_pu_amount_breakdown_shipping_currency_code'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code']) ? $orderData['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code'] : '';
		$ret['res_pu_amount_breakdown_shipping_value'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['shipping']['value']) ? $orderData['purchase_units'][0]['amount']['breakdown']['shipping']['value'] : '';

		$ret['res_pu_amount_breakdown_shipping_discount_currency_code'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['shipping_discount']['currency_code']) ? $orderData['purchase_units'][0]['amount']['breakdown']['shipping_discount']['currency_code'] : '';
		$ret['res_pu_amount_breakdown_shipping_discount_value'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['shipping_discount']['value']) ? $orderData['purchase_units'][0]['amount']['breakdown']['shipping_discount']['value'] : '';

		$ret['res_pu_description'] = isset($orderData['purchase_units'][0]['description']) ? $orderData['purchase_units'][0]['description'] : '';

		$ret['res_pu_items_cat'] = isset($orderData['purchase_units'][0]['items'][0]['category']) ? $orderData['purchase_units'][0]['items'][0]['category'] : '';
		$ret['res_pu_items_name'] = isset($orderData['purchase_units'][0]['items'][0]['name']) ? $orderData['purchase_units'][0]['items'][0]['name'] : '';
		$ret['res_pu_items_qty'] = $product_qty; //isset($orderData['purchase_units'][0]['items'][0]['quantity']) ? $orderData['purchase_units'][0]['items'][0]['quantity'] : '';

		$ret['res_pu_items_tax_currency_code'] = isset($orderData['purchase_units'][0]['items'][0]['tax']['currency_code']) ? $orderData['purchase_units'][0]['items'][0]['tax']['currency_code'] : '';
		//$ret['res_pu_items_tax_value'] = isset($orderData['purchase_units'][0]['items'][0]['tax']['value']) ? $orderData['purchase_units'][0]['items'][0]['tax']['value'] : '';
		$ret['res_pu_items_tax_value'] = isset($orderData['purchase_units'][0]['amount']['breakdown']['tax_total']['value']) ? $orderData['purchase_units'][0]['amount']['breakdown']['tax_total']['value'] : '0';

		$ret['res_pu_items_unit_amount_currency_code'] = isset($orderData['purchase_units'][0]['items'][0]['unit_amount']['currency_code']) ? $orderData['purchase_units'][0]['items'][0]['unit_amount']['currency_code'] : '';
		//$ret['res_pu_items_unit_amount_value'] = isset($orderData['purchase_units'][0]['items'][0]['unit_amount']['value']) ? $orderData['purchase_units'][0]['items'][0]['unit_amount']['value'] : number_format("0",2);
		$ret['res_pu_items_unit_amount_value'] =  $product_price;

		//$ret['res_pu_payee_email_address'] = Auth::user() ? Auth::user()->email : (isset($orderData['purchase_units'][0]['payee']['email_address']) ? $orderData['purchase_units'][0]['payee']['email_address'] : '');
		$ret['res_pu_payee_email_address'] = $normaluser ? $normaluser->email : (isset($orderData['purchase_units'][0]['payee']['email_address']) ? $orderData['purchase_units'][0]['payee']['email_address'] : '');

		$ret['res_pu_payee_merchant_id'] = isset($orderData['purchase_units'][0]['payee']['merchant_id']) ? $orderData['purchase_units'][0]['payee']['merchant_id'] : '';

		$ret['res_pu_payments_capture_id'] = isset($orderData['purchase_units'][0]['payments']['captures'][0]['id']) ? $orderData['purchase_units'][0]['payments']['captures'][0]['id'] : '';
		$ret['res_pu_payments_capture_final_capture'] = isset($orderData['purchase_units'][0]['payments']['captures'][0]['final_capture']) ? $orderData['purchase_units'][0]['payments']['captures'][0]['final_capture'] : '';
		$ret['res_pu_payments_capture_amount_currency_code'] = isset($orderData['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code']) ? $orderData['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] : '';
		$ret['res_pu_payments_capture_amount_value'] = isset($orderData['purchase_units'][0]['payments']['captures'][0]['amount']['value']) ? $orderData['purchase_units'][0]['payments']['captures'][0]['amount']['value'] : '';

		/////////shipping data

		$ret['res_pu_shipping_fullname'] = isset($orderData['purchase_units'][0]['shipping']['name']['full_name']) ? $orderData['purchase_units'][0]['shipping']['name']['full_name'] : '';
		$ret['res_pu_shipping_address_1'] = isset($orderData['purchase_units'][0]['shipping']['address']['address_line_1']) ? $orderData['purchase_units'][0]['shipping']['address']['address_line_1'] : '';
		$ret['res_pu_shipping_address_2'] = isset($orderData['purchase_units'][0]['shipping']['address']['address_line_2']) ? $orderData['purchase_units'][0]['shipping']['address']['address_line_2'] : '';
		$ret['res_pu_shipping_state'] = isset($orderData['purchase_units'][0]['shipping']['address']['admin_area_1']) ? $orderData['purchase_units'][0]['shipping']['address']['admin_area_1'] : '';
		$ret['res_pu_shipping_city'] = isset($orderData['purchase_units'][0]['shipping']['address']['admin_area_2']) ? $orderData['purchase_units'][0]['shipping']['address']['admin_area_2'] : '';
		$ret['res_pu_shipping_country'] = isset($orderData['purchase_units'][0]['shipping']['address']['country_code']) ? $orderData['purchase_units'][0]['shipping']['address']['country_code'] : '';
		$ret['res_pu_shipping_postcode'] = isset($orderData['purchase_units'][0]['shipping']['address']['postal_code']) ? $orderData['purchase_units'][0]['shipping']['address']['postal_code'] : '';

		$ret['res_pu_shipping_option_id'] = isset($orderData['purchase_units'][0]['shipping']['options'][0]['id']) ? $orderData['purchase_units'][0]['shipping']['options'][0]['id'] : '';
		$ret['res_pu_shipping_option_name'] = isset($orderData['purchase_units'][0]['shipping']['options'][0]['label']) ? $orderData['purchase_units'][0]['shipping']['options'][0]['label'] : '';
		$ret['res_pu_shipping_type'] = isset($orderData['purchase_units'][0]['shipping']['options'][0]['type']) ? $orderData['purchase_units'][0]['shipping']['options'][0]['type'] : '';
		$ret['res_pu_shipping_option_amount_currency_code'] = isset($orderData['purchase_units'][0]['shipping']['options'][0]['amount']['currency']) ? $orderData['purchase_units'][0]['shipping']['options'][0]['amount']['currency'] : '';
		$ret['res_pu_shipping_option_amount_value'] = isset($orderData['purchase_units'][0]['shipping']['options'][0]['amount']['value']) ? $orderData['purchase_units'][0]['shipping']['options'][0]['amount']['value'] : '';

		$ret['res_status'] = isset($orderData['status']) ? $orderData['status'] : '';

		$ret['res_pu_shipping_fname'] = '';
		$ret['res_pu_shipping_lname'] = '';

		if($ret['res_pu_shipping_fullname']!=''){
			$res_pu_shipping_fullname_arr = explode(" ",$ret['res_pu_shipping_fullname']);
			$ret['res_pu_shipping_fname'] = isset($res_pu_shipping_fullname_arr[0]) ? $res_pu_shipping_fullname_arr[0] : '';
			$ret['res_pu_shipping_lname'] = isset($res_pu_shipping_fullname_arr[1]) ? $res_pu_shipping_fullname_arr[1] : '';
		}

		$Billing['bill_fname'] = $ret['res_pu_shipping_fname']; //$ret['res_payer_fname'];
		$Billing['bill_lname'] = $ret['res_pu_shipping_lname']; //$ret['res_payer_lname'];
		$Billing['bill_country'] = "";
		$Billing['bill_other_state'] = "";
		$Billing['bill_state'] = "";
		$Billing['bill_company'] = "";
		$Billing['bill_address1'] = "";
		$Billing['bill_address2'] = "";
		$Billing['bill_city'] = "";
		$Billing['bill_country'] = "";
		$Billing['bill_zip'] = "";
		$Billing['bill_phone'] = "";
		$Billing['bill_email'] = "";
		$Billing['bill_cemail'] = "";

		$Billing['sameasbill'] = "N";

		$Billing['ship_country'] = $ret['res_pu_shipping_country'];
		$Billing['ship_fname'] = $ret['res_pu_shipping_fname'];
		$Billing['ship_lname'] = $ret['res_pu_shipping_lname'];
		$Billing['ship_company'] = "";
		$Billing['ship_address1'] = $ret['res_pu_shipping_address_1'];
		$Billing['ship_address2'] = $ret['res_pu_shipping_address_2'];
		$Billing['ship_city'] = $ret['res_pu_shipping_city'];
		$Billing['ship_state'] = $ret['res_pu_shipping_state'];
		$Billing['ship_country'] = $ret['res_pu_shipping_country'];
		$Billing['ship_zip'] = $ret['res_pu_shipping_postcode'];
		$Billing['ship_phone'] =  "";
		$Billing['ship_email'] = $ret['res_payer_email']; //$ret['res_pu_payee_email_address'];
		$Billing['ship_other_state'] = $ret['res_pu_shipping_country'] != 'US' ? $ret['res_pu_shipping_state'] : '';

		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		//if(Auth::user()){
		if($normaluser){
			$CustomerID = Session::has('sess_icustomerid') ? Session::get('sess_icustomerid') : 0;
			$cust_res 	= Customer::where('customer_id','=',$CustomerID)->get();
			if(count($cust_res) > 0){
				$customerInfo = $cust_res[0];

				$Billing['bill_fname'] = $Billing['ship_fname']; //$customerInfo['first_name'];
				$Billing['bill_lname'] = $Billing['ship_lname']; //$customerInfo['last_name'];
				$Billing['bill_country'] = $Billing['ship_country']; //$customerInfo['country'];
				$Billing['bill_other_state'] = $Billing['ship_country'] != 'US' ? $Billing['ship_state'] : ""; //$customerInfo['country'] != 'US' ? $customerInfo['state'] : "";
				$Billing['bill_state'] = $Billing['ship_state']; //$customerInfo['state'];
				$Billing['bill_company'] = $Billing['ship_company']; //$customerInfo['company_name'];
				$Billing['bill_address1'] = $Billing['ship_address1']; //$customerInfo['address1'];
				$Billing['bill_address2'] = $Billing['ship_address2']; //$customerInfo['address2'];
				$Billing['bill_city'] = $Billing['ship_city']; //$customerInfo['city'];
				$Billing['bill_zip'] = $Billing['ship_zip']; //$customerInfo['zip'];
				$Billing['bill_phone'] = $Billing['ship_phone']; //$customerInfo['phone'];
				$Billing['bill_email'] = $customerInfo['email'];
				$Billing['bill_cemail'] = $customerInfo['email'];

				// $Billing['sameasbill'] = "N";

				// $Billing['ship_country'] = $customerInfo['email'];
				// $Billing['ship_fname'] = $ret['res_pu_shipping_fname'];
				// $Billing['ship_lname'] = $ret['res_pu_shipping_lname'];
				// $Billing['ship_company'] = "";
				// $Billing['ship_address1'] = $ret['res_pu_shipping_address_1'];
				// $Billing['ship_address2'] = "";
				// $Billing['ship_city'] = $ret['res_pu_shipping_city'];
				// $Billing['ship_state'] = $ret['res_pu_shipping_state'];
				// $Billing['ship_country'] = $ret['res_pu_shipping_country'];
				// $Billing['ship_zip'] = $ret['res_pu_shipping_postcode'];
				// $Billing['ship_phone'] =  "";
				// $Billing['ship_email'] = $ret['res_pu_payee_email_address'];

			}
		}

		$this->SetBillingAddress($Billing);
		$this->SetShippingAddress($Billing);

		$EstimatedDeliveryDate = "0000-00-00 00:00:00";
		$ShipMethodName = '';
		Session::put('ShoppingCart.SubTotal',$prod_total);
		Session::put('ShoppingCart.Tax',$ret['res_pu_items_tax_value']);
		Session::put('ShoppingCart.Shipping.ShippingCharge',$ret['res_pu_shipping_option_amount_value']);
		if(isset($ret['res_pu_shipping_option_name']) && $ret['res_pu_shipping_option_name']!=''){
			$method_arr = explode("-",$ret['res_pu_shipping_option_name']);
			if(count($method_arr) > 0){
				$max_key = max(array_keys($method_arr));
				if(isset($method_arr[0]) && $method_arr[0]!=''){
					$ShipMethodName = $method_arr[0];

					if(isset($ret['res_pu_shipping_option_id']) && $ret['res_pu_shipping_option_id']!=''){
						$ShippingMethodRS = ShippingMode::where('status','=','1')->where('shipping_mode_id','=',$ret['res_pu_shipping_option_id'])->get();
						if($ShippingMethodRS[0]['type'] !=''){
							$ShipMethodName = strip_tags($ShippingMethodRS[0]['type'] ?? '');
						}
					}

					//Session::put('ShoppingCart.Shipping.ShippingMethodName',$method_arr[0]);
					Session::put('ShoppingCart.Shipping.ShippingMethodName',$ShipMethodName);
				}
				if(isset($method_arr[$max_key]) && $method_arr[$max_key]!=''){
					$EstimatedDeliveryDate = date("Y-m-d",strtotime($method_arr[$max_key]));
					Session::put("ShoppingCart.EstimatedDeliveryDate",$method_arr[$max_key]);
				}
				/*if(isset($method_arr[1]) && $method_arr[1]!=''){
					$EstimatedDeliveryDate = date("Y-m-d",strtotime($method_arr[1]));
					Session::put("ShoppingCart.EstimatedDeliveryDate",$method_arr[1]);
				}*/

			}

		}
		//Session::put('ShoppingCart.Shipping.ShippingMethodName',$ret['res_pu_shipping_option_name']);
		Session::put('ShoppingCart.Shipping.ShippingDays','');

		$ret['product_id'] = $product_id;
		$ret['product_sku'] = $product_sku;
		$ret['PayPalResponse'] = $orderData;

		//Session::put('ShoppingCart.Payment_Detail.Payment_Type','PAYMENT_PAYPALPROD');
		//Session::put('ShoppingCart.Payment_Detail.Payment_Method','PayPal Buy Now');
		$Payment_Type = 'PAYMENT_PAYPALEC'; //'PAYMENT_PAYPALPROD';
		$Payment_Method = 'Paypal Express Checkout'; //'PayPal Buy Now';

		Session::put("PayMethodRes",json_encode($orderData));

		$request->isPayPalProduct = 'Y';
		$request->PayPalResponse = $ret;
		Log::info('PlacePayPalOrderError: Before guest checkout');
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		//if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes'){
		if(!$normaluser && config('global.IS_GUEST_CHECKOUT') == 'Yes'){
			Log::info('PlacePayPalOrderError: After checkout condition');
			$newrequest['bill_email'] = $ret['res_payer_email']; //$ret['res_pu_payee_email_address'];
			$newrequest['bill_cemail'] = $ret['res_payer_email']; //$ret['res_pu_payee_email_address'];
			$newrequest['bill_country'] = $ret['res_payer_country_code'];
			$newrequest['bill_state'] = $ret['res_pu_shipping_state'];
			$newrequest['bill_fname'] = $ret['res_pu_shipping_fname']; //$ret['res_payer_fname'];
			$newrequest['bill_lname'] = $ret['res_pu_shipping_lname']; //$ret['res_payer_lname'];
			$newrequest['bill_address1'] = $ret['res_pu_shipping_address_1'];
			$newrequest['bill_address2'] = $ret['res_pu_shipping_address_2'];
			$newrequest['bill_city'] = $ret['res_pu_shipping_city'];
			$newrequest['bill_zip'] = $ret['res_pu_shipping_postcode'];
			$newrequest['bill_phone'] = '';
			$newrequest['bill_other_state'] = $ret['res_payer_country_code'] != 'US' ? $ret['res_pu_shipping_state'] : '';
			$this->SetGuestCustomer($newrequest,"Yes");
			Log::info('PlacePayPalOrderError: After set Guest Customer');
		}  else {
			$nrequest['bill_fname'] = "";
			$nrequest['bill_lname'] = "";
			$nrequest['bill_address1'] = "";
			$nrequest['bill_city'] = "";
			$nrequest['bill_state'] = "";
			$nrequest['bill_zip'] = "";
			$nrequest['bill_country'] = "";
			$nrequest['bill_other_state'] = "";
			$nrequest['bill_phone'] = "";

			// if(isset($ret['res_payer_fname']) && $ret['res_payer_fname'] !=''){
			// 	$nrequest['bill_fname'] = $ret['res_payer_fname'];
			// }
			// if(isset($ret['res_payer_lname']) && $ret['res_payer_lname'] !=''){
			// 	$nrequest['bill_lname'] = $ret['res_payer_lname'];
			// }

			if(isset($ret['res_pu_shipping_fname']) && $ret['res_pu_shipping_fname'] !=''){
				$nrequest['bill_fname'] = $ret['res_pu_shipping_fname'];
			}
			if(isset($ret['res_pu_shipping_lname']) && $ret['res_pu_shipping_lname'] !=''){
				$nrequest['bill_lname'] = $ret['res_pu_shipping_lname'];
			}

			if(isset($ret['res_pu_shipping_address_1']) && $ret['res_pu_shipping_address_1'] !=''){
				$nrequest['bill_address1'] = $ret['res_pu_shipping_address_1'];
			}
			if(isset($ret['res_pu_shipping_address_2']) && $ret['res_pu_shipping_address_2'] !=''){
				$nrequest['bill_address2'] = $ret['res_pu_shipping_address_2'];
			}

			if(isset($ret['res_pu_shipping_city']) && $ret['res_pu_shipping_city'] !=''){
				$nrequest['bill_city'] = $ret['res_pu_shipping_city'];
			}
			if(isset($ret['res_pu_shipping_state']) && $ret['res_pu_shipping_state'] !=''){
				$nrequest['bill_state'] = $ret['res_pu_shipping_state'];
			}
			if(isset($ret['res_pu_shipping_postcode']) && $ret['res_pu_shipping_postcode'] !=''){
				$nrequest['bill_zip'] = $ret['res_pu_shipping_postcode'];
			}
			if(isset($ret['res_payer_country_code']) && $ret['res_payer_country_code'] !=''){
				$nrequest['bill_country'] = $ret['res_payer_country_code'];
			}

			if($nrequest['bill_country'] != 'US'){
				if(isset($ret['res_pu_shipping_state']) && $ret['res_pu_shipping_state']!=''){
					$nrequest['bill_other_state'] = $ret['res_pu_shipping_state'];
				}
			} else {
				$nrequest['bill_other_state'] = "";
			}
			$nrequest['bill_phone'] = "";
			$this->CustomerInfoUpdate($nrequest);
		}
		//return json_encode($request->all());
		//return $this->PlaceOrder($request);

		$isPayPalProduct = 'Y';
		$request->PaymentMethod = 'PAYMENT_PAYPALEC';
		$PayPalResponse = $request->PayPalResponse;

		$ShippingInfo = Session::get('ShoppingCart.Shipping');

		//$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(".Session::get('currency_symbol').$ShippingInfo["ShippingCharge"].")</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
		//$ShipMethodName = $ShippingInfo['ShippingMethodName'];
		$ShipMethodCharge = $this->GetShippingCharge();
		// $arrPaymentDetail = Session::get('ShoppingCart.Payment_Detail');

		// $merge_note = (Session::has('ShoppingCart.merge_note')) ? Session::get('ShoppingCart.merge_note') : "";
    	// $Payment_Type = (isset($arrPaymentDetail['Payment_Type']))?$arrPaymentDetail['Payment_Type']:'';
    	// $Payment_Method = (isset($arrPaymentDetail['Payment_Method']))?$arrPaymentDetail['Payment_Method']:'';

		$currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');
		//$fullShippingname =  $ShipMethodName. " <b>(".Session::get('currency_symbol').$ret['res_pu_shipping_option_amount_value'].")</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
		$fullShippingname =  $ShipMethodName. " <b>(".Session::get('currency_symbol').$ret['res_pu_shipping_option_amount_value'].")</b> ";

		if(Session::has('etype') && Session::get('etype') == 'M')
			$checkout_type = 'M';
		else
			$checkout_type = 'G';

		$w_user_type = Session::get('eusertype');
		$w_ilevelid  = (Session::has('ilevelid'))?Session::get('ilevelid'):0;

		$Shipping = Session::get('ShoppingCart.ShippingAddress');
		$Billing  = Session::get('ShoppingCart.BillingAddress');

		$onlyGCPurchased = '0'; //$this->GetCartAttribute('onlyGCPurchased');

		$CreditData = $this->GetCreditLimitAmount();
    	$CreditAmt = $CreditData['CreditLimit'];
    	$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');

		$cust_current_credit_limit = $CreditAmt;
		$apply_credit = $CreditDiscount;
		$remaining_credit = $CreditData['RemainCreditLimit'];

		if($CreditDiscount>0){
			$use_credit_limit = 'Yes';
		}else{
			$use_credit_limit = 'No';
		}

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_DS')
			$is_dropship_order = 'Yes';
		else
			$is_dropship_order = 'No';

		//$EstimatedDeliveryDate = "0000-00-00 00:00:00";

		$cur_date = date("Y-m-d");
		if($EstimatedDeliveryDate == "" && strtotime($EstimatedDeliveryDate) < strtotime($cur_date)){
			$EstimatedDeliveryDate = "0000-00-00 00:00:00";
		}

		$ProdInfo = DB::table('pu_products as p')
					->join('pu_products_one as po','p.products_id','=','po.products_id')
					->where(function($query){
						$query->orWhere('p.status','=','1');
						$query->OrWhere(function($qry){
							$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');
						});
					})
					->where('p.products_id','=',$PayPalResponse['product_id'])->get();

		$ProductRs = $this->SetProduct($ProdInfo[0]);

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
		if($ProductRs->WebsiteStock == "Out")
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
			else if($ProductRs->perfumeworldwide_sku!='' &&  $ProductRs->nandansons_current_stock > 0 && $perfumeworldwide_our_price > 0)
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

		$IsMaxaromaTwoDelivery = 'No';
		if($ProductRs->WebsiteStock == "In")
		{
			$IsMaxaromaTwoDelivery = $ProductRs->maxtwodaydelivery;
		}
		if(isset($ProductRs->IsDealProducts) && $ProductRs->IsDealProducts == "Yes")
		{
			$IsMaxaromaTwoDelivery = 'No';
		}

		$return_status = "success";
		if(isset($ret['res_status']) && ($ret['res_status']=="COMPLETED" || $ret['res_status']=="APPROVED"))
        {
			$status = "Pending";
			$pay_status = 'Unpaid';
			if($ret['res_status']=="COMPLETED"){
				$pay_status = 'Paid';
			}
			$transaction_info = "This transaction has been approved.";
		}
		else
		{
			$return_status = "declined";
			$status = "Declined";
			$pay_status = 'Unpaid';
			$transaction_info = "This transaction has been Declined.";
		}

		if(isset($Billing['first_name']) && $Billing['first_name']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Billing['first_name']))
		{
			$Billing['first_name'] = $this->transliterate($Billing['first_name']);
		}
		if(isset($Billing['last_name']) && $Billing['last_name']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Billing['last_name']))
		{
			$Billing['last_name'] = $this->transliterate($Billing['last_name']);
		}
		if(isset($Billing['address1']) && $Billing['address1']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Billing['address1']))
		{
			$Billing['address1'] = $this->transliterate($Billing['address1']);
		}
		if(isset($Billing['address2']) && $Billing['address2']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Billing['address2']))
		{
			$Billing['address2'] = $this->transliterate($Billing['address2']);
		}
		if(isset($Billing['city']) && $Billing['city']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Billing['city']))
		{
			$Billing['city'] = $this->transliterate($Billing['city']);
		}

		if(isset($Shipping['first_name']) && $Shipping['first_name']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Shipping['first_name']))
		{
			$Shipping['first_name'] = $this->transliterate($Shipping['first_name']);
		}
		if(isset($Shipping['last_name']) && $Shipping['last_name']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Shipping['last_name']))
		{
			$Shipping['last_name'] = $this->transliterate($Shipping['last_name']);
		}
		if(isset($Shipping['address1']) && $Shipping['address1']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Shipping['address1']))
		{
			$Shipping['address1'] = $this->transliterate($Shipping['address1']);
		}
		if(isset($Shipping['address2']) && $Shipping['address2']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Shipping['address2']))
		{
			$Shipping['address2'] = $this->transliterate($Shipping['address2']);
		}
		if(isset($Shipping['city']) && $Shipping['city']!='' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $Shipping['city']))
		{
			$Shipping['city'] = $this->transliterate($Shipping['city']);
		}

		$OrderInsert = array (
			'orders_no' 		=> 'OR'.$order_ref_id,
			'customer_id'		=> Session::has('sess_icustomerid') ? Session::get('sess_icustomerid') : 0,
			'sub_total' 		=> (float)$prod_total,//Session::has('ShoppingCart.SubTotal') ? (float)Session::get('ShoppingCart.SubTotal') : (float)0,
			'shipping_amt' 		=> (float)$ret['res_pu_shipping_option_amount_value'],//$ShipMethodCharge,
			//'tax' 				=> (float)$ret['res_pu_items_tax_value'],//$this->GetAllCharges('Tax'),
			'gift_charge' 		=> '0.00',//$this->GetAllCharges('GiftWrappingCharge'),
			'gift_message' 		=> '',
			'is_gift_order'		=> 'No',
			'handling_charge' 	=> '0.00',
			'wire_discount' 	=> '0.00',
			'auto_discount' 	=> '0.00',//$this->GetAllDiscounts('AutoDiscount'),
			'quantity_discount'	=> '0.00',//$this->GetAllDiscounts('QuantityDiscount'),
			'reward_discount'	=> '0.00',//$this->GetAllDiscounts('YotpoRewardDiscount'),
			'coupon_amount' 	=> '0.00',//$this->GetAllDiscounts('CouponDiscount'),
			'coupon_id' 		=> '',
			'Second_coupon_id'	=> '',
			'coupon_code' 		=> '',
			'gc_amount' 		=> '',//$this->GetAllDiscounts('GiftCoupon'),
			'gc_code' 			=> '',
			'refer_id'			=> 0,
			'refer_amount' 		=> '',//$this->GetAllDiscounts('AutoReferDiscount'),
			'order_total' 		=> $ret['res_pu_payments_capture_amount_value'],//$this->GetNetTotal(),
			'shipinfo' 			=> $ShipMethodName,
			'payment_type' 		=> $Payment_Type,
			'payment_method' 	=> $Payment_Method,
			'pay_status' 		=> $pay_status,//'Paid',
			'ccinfo' 			=> "",
			'customer_comment' 	=> '',
			'status'			=> $status, //'Pending',
			'currency_info'		=> $currency_info,
			'checkout_type' 	=> $checkout_type,
			'user_type' 		=> $w_user_type,
			'ilevelid' 			=> $w_ilevelid,
			//'level_price' 		=> $w_level_price,
			'ship_first_name' 	=> isset($Shipping['first_name']) ? $Shipping['first_name'] : '', //$Shipping['first_name'],
			'ship_last_name' 	=> isset($Shipping['last_name']) ? $Shipping['last_name'] : '',//$Shipping['last_name'],
			'ship_company' 		=> isset($Shipping['company']) ? $Shipping['company'] : '',//$Shipping['company'],
			'ship_email' 		=> isset($Shipping['email']) ? $Shipping['email'] : '',//$Shipping['email'],
			'ship_address1' 	=> isset($Shipping['address1']) ? $Shipping['address1'] : '',//$Shipping['address1'],
			'ship_address2' 	=> isset($Shipping['address2']) ? $Shipping['address2'] : '',//$Shipping['address2'],
			'ship_city' 		=> isset($Shipping['city']) ? $Shipping['city'] : '',//$Shipping['city'],
			'ship_zip' 			=> isset($Shipping['zip']) ? $Shipping['zip'] : '',//$Shipping['zip'],
			'ship_state' 		=> isset($Shipping['state']) ? $Shipping['state'] : '',//$Shipping['state'],
			'ship_country' 		=> isset($Shipping['country']) ? $Shipping['country'] : '',//$Shipping['country'],
			'ship_phone' 		=> isset($Shipping['phone']) ? $Shipping['phone'] : '',//$Shipping['phone'],
			'bill_first_name' 	=> isset($Billing['first_name']) ? $Billing['first_name'] : '',//$Billing['first_name'],
			'bill_last_name' 	=> isset($Billing['last_name']) ? $Billing['last_name'] : '',//$Billing['last_name'],
			'bill_company' 		=> isset($Billing['company']) ? $Billing['company'] : '',//$Billing['company'],
			'bill_email' 		=> isset($Billing['email']) ? $Billing['email'] : '',//$Billing['email'],
			'bill_address1' 	=> isset($Billing['address1']) ? $Billing['address1'] : '',//$Billing['address1'],
			'bill_address2' 	=> isset($Billing['address2']) ? $Billing['address2'] : '',//$Billing['address2'],
			'bill_city' 		=> isset($Billing['city']) ? $Billing['city'] : '',//$Billing['city'],
			'bill_zip' 			=> isset($Billing['zip']) ? $Billing['zip'] : '',//$Billing['zip'],
			'bill_state' 		=> isset($Billing['state']) ? $Billing['state'] : '',//$Billing['state'],
			'bill_country' 		=> isset($Billing['country']) ? $Billing['country'] : '',//$Billing['country'],
			'bill_phone' 		=> isset($Billing['phone']) ? $Billing['phone'] : '',////$Billing['phone'],
			'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
			'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
			'is_only_gc'		=> (string)$onlyGCPurchased,
			'free_gift'			=> '',
			//======= Added Code Date 09/02/2015 Start Here ===//
			'gift_from'				=> '',
			'gift_to'				=> '',
			'gift_message_customer'	=> '',
			//======= Added Code Date 09/02/2015 End Here ===//
										//Credit Limit Code Start
		   'cust_current_credit_limit' => $cust_current_credit_limit,
		   'apply_credit'          => $apply_credit,
		   'remaining_credit'      => $remaining_credit,
		   'use_credit_limit'      => $use_credit_limit,
										//Credit Limit Code End
		   'is_dropship_order'     => $is_dropship_order,
		   'shipping_signature'	 => 0,
		   'is_shipping_signature' => 'No',
		   'Is_GiftCertificatPurchase' => $this->GetCartAttribute('CheckGCPurchasedVal'),
		   'EstimatedDeliveryDate' 	=> $EstimatedDeliveryDate,
		   'fullshipping_info'		=> 	$fullShippingname,
		   'merge_note'		=>  '',//$merge_note,
		   'bogo_discount'	=> $this->GetAllDiscounts('DogoDiscount'),
		   'is_maxtwoday'	=> $IsMaxaromaTwoDelivery,//"No", //$is_maxtwoday,
		   'route_shipping_insurance_charge' => 0, //$OrdShipInsurance,
		   'vLang_flag' => Session::get('ShoppingCart.YotpoFreeGiftCoupon'),
		   'paymentintentid' => "",
		   'payment_gateway_response' => Session::get("PayMethodRes")?Session::get("PayMethodRes")."---pbn":'',
		   'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):'',
		   'transaction_info' =>	$transaction_info
		);

		$CurrOrder = Order::find($order_ref_id);
		$udpRefer = $CurrOrder->update($OrderInsert);

		$prores = Products::select('product_name','short_description')->where('products_id','=',$PayPalResponse['product_id'])->get();
		$prodnm = $prores[0]["product_name"].'<br>'.$prores[0]["short_description"];

		/*
		$OrderDetailInsert = array (
			'orders_id'					=> $order_ref_id,
			'orders_no'					=> "OR".$order_ref_id, // To add 'OR' Change on :: 06-10-2015
			'products_id'				=> $PayPalResponse['product_id'],
			'sku' 						=> $PayPalResponse['product_sku'],
			'product_name'				=> $prodnm, //$PayPalResponse['res_pu_items_name'],
			'quantity' 					=> $PayPalResponse['res_pu_items_qty'],
			'price' 					=> (float)$PayPalResponse['res_pu_items_unit_amount_value'],
			'total' 					=> ((float)$PayPalResponse['res_pu_items_unit_amount_value'] * (float)$PayPalResponse['res_pu_items_qty']),
			'status' 					=> '1',
			'item_price' 				=> (float)$PayPalResponse['res_pu_items_unit_amount_value'],
			'excluded_flag'  			=> '',
			'is_gift_wrap'				=> '',
			'is_free_gift_products' 	=> 'No',
			'VendorSKU'					=> '',
			'IsCosmo'					=> $IsCosmo,
			'IsNandansons'  			=> $IsNandansons,
			'IsPerfumePW'				=> $IsPerfumePW,
			'IsPCA'						=> '',
			'coupon_itemwise_discount'	=> '',
			'handling_time_str'			=> '',
			'attribute_info'        	=> 'No',
			'actual_price'				=> '',
			'excluded_flag'				=> '',
		);
		$OrdDetail = OrderDetail::create($OrderDetailInsert); */

		Session::put('ShoppingCart.OrderID',$order_ref_id);

		$myFile = env('LOG_BASE_PATH') .'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." After PayPal Buy Now Approval Order :".$order_ref_id."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		// $ret['status'] = 'success';
		// $ret['oid'] = base64_encode($order_ref_id);
		// return json_encode($ret);
		// exit;

		if($return_status == "success"){
			$ret['status'] = 'success';
			$ret['oid'] = base64_encode($order_ref_id);
		} else {
			$ret['status'] = 'declined';
			$msg = "Your order has been declined.";
			Session::flash('PlaceOrderError',$msg);
		}
		return json_encode($ret);
		exit;

			// if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			// {
			// 	$this->SetGuestCustomer($newrequest);
			// } else {
			// 	$this->CustomerInfoUpdate($newrequest);
			// }
			// return $res_pu_shipping_fullname."---".$res_pu_shipping_address_1."---".$res_pu_shipping_state."---".$res_pu_shipping_city."---".$res_pu_shipping_country."---".$res_pu_shipping_postcode;
			// return $res_payer_fname."---".$res_payer_lname;

			// return $res_links;// $orderData['id'];
			// return $orderData;
			// $order_id = $orderData->id;
			// $order_intent =  $orderData->intent;
			// $order_link =  $orderData->links[0]->href;

			// $payer_name = $orderData->name->given_name;
			// return $payer_name;
			//return console.log($request);
	}

	public function PlaceOrder(Request $request)
	{
		addLog('PlaceOrderStart');
		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$stringData = '';
			$fh = fopen($myFile, 'a+');
			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Place order start Session Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
			}
			if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Place order start Session User Email : ".Session::get('sess_useremail')." :\n";
			}
			fwrite($fh, $stringData);
			fclose($fh);
		}
		$this->SetupCart();

		$skrSKU= $this->OutOfStockItemsRemove();

		if(count($skrSKU) > 0)
		{
			Session::put('SKUListArrayVal',$skrSKU);
			$err_msg = "Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			$log['err_msg'] = $err_msg;
			addLog('PlaceOrder',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/shoppingcart');
		}
		$IsGiftCertificateItem = '';

		if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
		{
			config(['app.debug' => true]);
		}
		if(config('global.SHOPP_STATUS') == 'Close')
		{
			Session::forget('ShoppingCart');
			$err_msg = "Shop status is close.";
			$log['err_msg'] = $err_msg;
			addLog('PlaceOrder',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/');
		}
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
		{
			Session::forget('ShoppingCart');
			$err_msg = "Shoping cart total is not valid.";
			$log['err_msg'] = $err_msg;
			addLog('PlaceOrder',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/shoppingcart');
		}
		if($this->Is_WholeSaler_Allow() == false)
		{
			$err_msg = "Wholesaler is not allowed.";
			$log['err_msg'] = $err_msg;
			addLog('PlaceOrder',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/shoppingcart');
		}

		if (Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0 && Auth::guard('store')->check()) {
			$CartItem = Session::get('ShoppingCart.Cart');
			$grouped = collect($CartItem)->groupBy('OrderType');
			$storeCount = $grouped->get('Store', collect())->count();
			$websiteCount = $grouped->get('Website', collect())->count();

			if ($storeCount > 0 && $websiteCount > 0) {
				$groupedArray = $grouped->map(function ($items) {
					return $items->map(function ($item) {
						return (array) $item;
					})->values()->toArray();
				})->toArray();
				$posController = app()->make(POSController::class);
				$keys = array_keys($groupedArray);

				return $posController->splitOrderPlace($request, $groupedArray);
			} else if ($storeCount > 0 ||  $websiteCount > 0) {
				$WebsiteOrder = "No";

				if($websiteCount > 0)
				{
					$WebsiteOrder = "Yes";
				}

				$posController = app()->make(POSController::class);
				return $posController->StoreOrderPlace($request,$WebsiteOrder);
				echo "test123".rand();
				exit;
			}
		}

		if(isset($request->is_stripe_wallet) && $request->is_stripe_wallet == "google_pay")
		{
			$log['is_stripe_wallet'] = $request->is_stripe_wallet;
			addLog('PlaceOrder',$log);
			$request->PaymentMethod = 'STRIPE_GOOGLEPAY';
			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$log['is_stripe_applepay'] = $request->is_stripe_applepay;
			addLog('PlaceOrder',$log);
			$request->PaymentMethod = 'STRIPE_APPLEPAY';
			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");
		}

		if(isset($request->is_btm_ap_chkout) && $request->is_btm_ap_chkout == "Yes"){	//bottom afterpay checkout
			$request->PaymentMethod = "PAYMENT_PAYWITHAFTERPAY";
		}

		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		$is_amazon = 0;
		if($this->GetNetTotal() <=0 && $CreditDiscount > 0 && config('Settings.WHOLESALE_CREDIT_LIMIT')=='Yes' && Session::get("etype") == "M" && Session::get("sess_icustomerid") > 0 && Session::get("is_dropshipper") !="Yes")
		{
			if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
				$is_amazon = 1;
			$request->PaymentMethod  = "PAYMENT_CL";
		}

		if($this->GetNetTotal() <= 0 && $CreditDiscount <= 0)
		{
			if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
				$is_amazon = 1;
			$request->PaymentMethod = 'PAYMENT_GIFT_CERTIFICATE';
		}

		if($request->PaymentMethod != 'PAYMENT_PAYPALEC' && (!isset($request->is_paypal) || $request->is_paypal !="yes"))
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'PAYMENT_PAYPALEC' && isset($request->is_paypal) && $request->is_paypal =="yes")
		{
			$this->setPaymentDetail($request);
		}
		if($request->PaymentMethod == 'PAYMENT_PAYWITHAFTERPAY')
		{
			$this->setPaymentDetail($request);
		}
		if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'PAYMENT_STRIPE_BUTTON')
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'STRIPE_GOOGLEPAY')
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'STRIPE_APPLEPAY')
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'PAYMENT_AUTHORIZENETCC' || $request->PaymentMethod == 'PAYMENT_PAYPALCC'  || $request->PaymentMethod == 'PAYMENT_BRAINTREECC')
		{
			if($request->CCType =='' || $request->CCNumber =='' ||  $request->CCMonth =='' || $request->CCYear == '' || $request->CCholdername =='')
			{
				$err_msg = "Error while processing your order, Please try again.";
				$log['err_msg'] = $err_msg;
				addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError:'.$request->PaymentMethod.' - Credit Card issue.');
				return redirect()->back();
			}
		}

		if($this->GetNetTotal() <=0)
		{
			$GiftCoupon = $this->GetAllCoupons('GiftCoupon');
			$GCAmount = 0;
			$GCCode = "";
			$arrPaymentDetail = Session::get('ShoppingCart.Payment_Detail');
			if($GiftCoupon)
			{
				$GCAmount = $GiftCoupon['Value'];
				$GCCode = $GiftCoupon['Code'];
			}
			$log['GiftCoupon'] = json_encode($GiftCoupon);
			$log['arrPaymentDetail'] = json_encode($arrPaymentDetail);
			if(empty($GCCode) && $GCCode=='' && isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type']!='' && $arrPaymentDetail['Payment_Type']!='PAYMENT_CL')
			{
				$err_msg = "Order total is invalid, please try again.";
				$log['err_msg'] = $err_msg;
				addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				return redirect('/shoppingcart');
			}
		}

		$customer_id = (int)Session::get('sess_icustomerid');

		if(!Session::has('sess_icustomerid') || empty($customer_id))
		{
			$err_msg = "Error while processing your order, Please try again.";
			$log['err_msg'] = $err_msg;
			addLog("PlaceOrder",$log);
			Session::flash('PlaceOrderError',$err_msg);
			Log::info('PlaceOrderError: Customer Not Found');
			return redirect()->back();
		}

		$Billing  = Session::get('ShoppingCart.BillingAddress');

		if(($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON') && (trim($Billing['first_name'])== '' || trim($Billing['address1'])== '' || trim($Billing['city'])== '' ||
			trim($Billing['zip'])== '' || trim($Billing['state'])== '' || trim($Billing['country'])== '' ||
			trim($Billing['email'])== '')
			)
			{
					$err_msg = "Please fill the required fields for billing shipping information.";
					$log['Billing'] = json_encode($Billing);
					$log['err_msg'] = $err_msg;
					addLog("PlaceOrder",$log);
					Session::flash('PlaceOrderError',$err_msg);
					Log::info('PlaceOrderError: BillingAddress Validation');
					return redirect()->back();
			}

		if(!isset($_REQUEST['AppleGPay'])){
			if(($request->PaymentMethod != 'PAYMENT_STRIPE_BUTTON' && $request->PaymentMethod != 'STRIPE_GOOGLEPAY' && $request->PaymentMethod != 'STRIPE_APPLEPAY' && $request->PaymentMethod != 'PAYMENT_PAYWITHAMAZON' && $is_amazon !=1) && (trim($Billing['first_name'])== '' || trim($Billing['last_name'])== '' || trim($Billing['address1'])== '' || trim($Billing['city'])== '' ||
			trim($Billing['zip'])== '' || trim($Billing['state'])== '' || trim($Billing['country'])== '' || trim($Billing['phone'])== '' ||
			trim($Billing['email'])== ''))
			{
					$err_msg = "Please fill the required fields for billing information. ";
					$log['Billing'] = json_encode($Billing);
					$log['err_msg'] = $err_msg;
					addLog("PlaceOrder",$log);
					Session::flash('PlaceOrderError',$err_msg);
					Log::info('PlaceOrderError: BillingAddress Validation');
					return redirect()->back();
			}
		}

		$Shipping = Session::get('ShoppingCart.ShippingAddress');
		$log['Shipping'] = $Shipping;
		addLog("PlaceOrder",$log);
		$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');
		//$onlyGCPurchased = $request->onlyGCPurchased;
		/*if(!$onlyGCPurchased || $onlyGCPurchased == "")
			$onlyGCPurchased = 0;*/
		if($onlyGCPurchased == 0)
		{
			//if($request->PaymentMethod != 'PAYMENT_PAYWITHAMAZON' && $request->PaymentMethod != 'STRIPE_GOOGLEPAY' && $request->PaymentMethod != 'STRIPE_APPLEPAY' && (trim($Shipping['first_name']) == '' || trim($Shipping['last_name']) == '' || trim($Shipping['address1']) == '' || trim($Shipping['city']) == '' || trim($Shipping['zip'])== '' || trim($Shipping['state'])== '' || trim($Shipping['country'])== '' ))
			if($request->PaymentMethod != 'PAYMENT_PAYWITHAMAZON' && $request->PaymentMethod != 'STRIPE_GOOGLEPAY' && $request->PaymentMethod != 'STRIPE_APPLEPAY' && ((isset($Shipping['first_name']) && trim($Shipping['first_name']) == '') || (isset($Shipping['last_name']) &&	trim($Shipping['last_name']) == '') || (isset($Shipping['address1']) && trim($Shipping['address1']) == '') || (isset($Shipping['city']) && trim($Shipping['city']) == '') || (isset($Shipping['zip']) && trim($Shipping['zip']) == '') || (isset($Shipping['state']) && trim($Shipping['state']) == '') || (isset($Shipping['country']) && trim($Shipping['country']) == '') ))
			{
				$err_msg = "Please fill the required fields for shipping information. ";
				$log['err_msg'] = $err_msg;
				addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: ShippingAddress Validation');
				return redirect()->back();
			}
		}

		if($onlyGCPurchased == 1)
		{
			Session::put('ShoppingCart.BillingAsShipping','Yes');
			$Billing['sameasbill'] = 'Yes';
			$this->SetShippingAddress($Billing);
			$Shipping = Session::get('ShoppingCart.ShippingAddress');
		}

		$arrPaymentDetail = Session::get('ShoppingCart.Payment_Detail');
		if(Session::has('ShoppingCart.Payment_Detail'))
		{
			//if((trim($arrPaymentDetail['Payment_Type']) == '' || $arrPaymentDetail['Payment_Type'] =='PAYMENT_PAYPALEC') && (!isset($request->is_paypal)))
			if(((isset($arrPaymentDetail['Payment_Type']) && trim($arrPaymentDetail['Payment_Type']) == '') || (isset($arrPaymentDetail['Payment_Type']) &&	$arrPaymentDetail['Payment_Type'] =='PAYMENT_PAYPALEC')) && (!isset($request->is_paypal)))
			{
				$err_msg =  "Please choose payment method.";
				$log['err_msg'] = $err_msg;
				addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Payment Method Not Selected');
				return redirect()->back();
			}

			//if(trim($arrPaymentDetail['Payment_Type']) == '' && isset($request->is_paypal) && $request->is_paypal == "yes")
			if(isset($arrPaymentDetail['Payment_Type']) && trim($arrPaymentDetail['Payment_Type']) == '' && isset($request->is_paypal) && $request->is_paypal == "yes")
			{
				$err_msg =  "Please choose payment method.";
				$log['err_msg'] = $err_msg;
				addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Payment Method Not Selected');
				return redirect()->back();
			}

			if($arrPaymentDetail['Payment_Type']=='PAYMENT_AUTHORIZENETCC' || $arrPaymentDetail['Payment_Type']=='PAYMENT_PAYPALCC' )
			{
				if($arrPaymentDetail['CCType']=='' || $arrPaymentDetail['CCNumber']=='' || $arrPaymentDetail['CCMonth']=='' || $arrPaymentDetail['CCYear']=='' || $arrPaymentDetail['CCName']=='')
				{
					$msg = "Please fill the credit card information.";
					$log['err_msg'] = $err_msg;
					addLog("PlaceOrder",$log);
					Session::flash('PlaceOrderError',$msg);
					Log::info('PlaceOrderError: '.$arrPaymentDetail['Payment_Type'].' Credit Card issue.');
					return redirect()->back();
				}
			}
		}
		$ShippingInfo = Session::get('ShoppingCart.Shipping');
		if($onlyGCPurchased == 0)
		{
			if(empty($ShippingInfo['ShippingMethodID']) || (int)$ShippingInfo['ShippingMethodID'] <=0 )
			{
				$err_msg =  "Please choose shipping method.";
				$log['err_msg'] = $err_msg;
					addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Shipping Method Not Selected');
				return redirect()->back();
			}
		}

		$currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');
		if(Session::has('etype') && Session::get('etype') == 'M')
			$checkout_type = 'M';
		else
			$checkout_type = 'G';

		$cc_info ="";
		if(Session::has('ShoppingCart.Payment_Detail'))
		{
			if($arrPaymentDetail['Payment_Type'] == 'PAYMENT_AUTHORIZENETCC' || $arrPaymentDetail['Payment_Type'] =='PAYMENT_PAYPALCC')
			{
				$cc_info = $arrPaymentDetail['CCType'].",".substr(trim($arrPaymentDetail['CCNumber']),-4).",Exp.".$arrPaymentDetail['CCMonth']."/".$arrPaymentDetail['CCYear'].",CSC.".$arrPaymentDetail['CSC'];
			}
		}

		$ShippingInfo	 = Session::get('ShoppingCart.Shipping');
		$GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
		$CouponCode = $this->GetAllCoupons('CouponCode');
		$couponRS = array();
		$OneCouponCode = "";
		$coupon_id = '';
		$SecondCouponCode = "";
		$Second_coupon_id = "";

		$yotporewardcode = '';

		if(Session::has('ShoppingCart.YotpoRewardCode') && Session::get('ShoppingCart.YotpoRewardCode') != '')
		{
			$yotporewardcode = Session::get('ShoppingCart.YotpoRewardCode');
		}

		if(!empty($CouponCode))
		{
			$couponRS = Coupon::where('coupon_number','=',$CouponCode)->limit(1)->get();
			if($couponRS && $couponRS->count() > 0)
			{
				$OneCouponCode = $couponRS[0]->coupon_number;
				$coupon_id = $couponRS[0]->coupon_id;
			}

			$Temp_Second_Coupon_Code =  $this->GetAllCoupons('SecondCouponCode');
			if($Temp_Second_Coupon_Code!='')
			{
				$couponRS = Coupon::where('coupon_number','=',$Temp_Second_Coupon_Code)->limit(1)->get();

				if(count($couponRS) > 0)
				{
					$SecondCouponCode = $couponRS[0]->coupon_number;
					$Second_coupon_id = $couponRS[0]->coupon_id;
				}
				if($SecondCouponCode!='')
				{
					$OneCouponCode = $OneCouponCode."#". Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount').":".$SecondCouponCode."#".Session::get('ShoppingCart.PromoCoupon.SecondCouponDiscount');
				}
			}
		}

		## Wholesaler Field Start ###
		$w_user_type = Session::get('eusertype');
		$w_ilevelid  = (Session::has('ilevelid'))?Session::get('ilevelid'):0;
		## Wholesaler Field End ###

		$GiftValue = $this->FreeGiftValue($this->GetNetTotal());

		if(count($GiftValue) > 0) {
			$free_gift = Session::get('ShoppingCart.FreeGift');
			$gift_from = Session::get('ShoppingCart.GiftFrom');
			$gift_to   = Session::get('ShoppingCart.GiftTo');
			$gift_message_customer = Session::get('ShoppingCart.GiftMessageCustomer');
		} else {
			$free_gift = '';
			$gift_from = '';
			$gift_to   = '';
			$gift_message_customer = (isset($request->gift_message_customer))?$request->gift_message_customer:'';
			if(strtolower(trim($gift_message_customer ?? '')) == " *gift message")
			{
				$gift_message_customer = '';
			}
			Session::forget('ShoppingCart.FreeGift');
			Session::forget('ShoppingCart.GiftFrom');
			Session::forget('ShoppingCart.GiftTo');
			Session::forget('ShoppingCart.GiftMessageCustomer');
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Eight Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$CreditData = $this->GetCreditLimitAmount();
		$CreditAmt = $CreditData['CreditLimit'];
		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		$cust_current_credit_limit = $CreditAmt;
		$apply_credit = $CreditDiscount;
		$remaining_credit = $CreditData['RemainCreditLimit'];

		if($CreditDiscount>0){
			$use_credit_limit = 'Yes';
		}else{
			$use_credit_limit = 'No';
		}
		//Credit Limit Code End

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_DS')
			$is_dropship_order = 'Yes';
		else
			$is_dropship_order = 'No';

		if(isset($request->customer_comment) && strtolower(trim($request->customer_comment)) == "special request")
		{
			$request->customer_comment = "";
		}

		$fullShippingname = '';
		$is_maxtwoday = "No";
		$EstimatedDeliveryDate = '';
		$ShipMethodName = '';
		$ShipMethodCharge = 0;
		$OrdShipSignature = 0;
		$OrdShipInsurance = 0;
		if($onlyGCPurchased == 0)
		{
			if($ShippingInfo["ShippingCharge"] > 0)
			{
				$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(".Session::get('currency_symbol').$ShippingInfo["ShippingCharge"].")</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
			}
			else
			{
				$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(Free)</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
			}
			if(strtolower($ShippingInfo['ShippingMethodName'] ?? '')=='max2days')
			{
				$is_maxtwoday = "Yes";
			}
			$EstimatedDeliveryDate = Session::get("ShoppingCart.EstimatedDeliveryDate");
			$ShipMethodName = $ShippingInfo['ShippingMethodName'];
			$ShipMethodCharge = $this->GetShippingCharge();
			$OrdShipSignature = $this->GetAllCharges('ShippingSignature');
			$OrdShipInsurance = $this->GetAllCharges('ShippingInsurance');
		}

		$cur_date = date("Y-m-d");
		if($EstimatedDeliveryDate == "" && strtotime($EstimatedDeliveryDate) < strtotime($cur_date)){
			$EstimatedDeliveryDate = "0000-00-00 00:00:00";
		}

		$referDiscountId = (Session::has('ShoppingCart.ReferDiscountId'))?Session::get('ShoppingCart.ReferDiscountId'):0;

		$merge_note = (Session::has('ShoppingCart.merge_note')) ? Session::get('ShoppingCart.merge_note') : "";
		$Payment_Type = (isset($arrPaymentDetail['Payment_Type']))?$arrPaymentDetail['Payment_Type']:'';
		$Payment_Method = (isset($arrPaymentDetail['Payment_Method']))?$arrPaymentDetail['Payment_Method']:'';

		$PaymentResponse = "";
		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == "PAYMENT_STRIPE_BUTTON")
		{
			$Payment_Type = "PAYMENT_STRIPE";
			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");
		}

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == "STRIPE_GOOGLEPAY")
		{
			$PaymentResponse = Session::get("PayMethodRes");
		}

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == "STRIPE_APPLEPAY")
		{
			$PaymentResponse = Session::get("PayMethodRes");
		}

		$customer_comment = (isset($request->customer_comment))?$request->customer_comment:'';
		$SubTotal = (float)Session::get('ShoppingCart.SubTotal');
		$GiftCoupon = $this->GetAllCoupons('GiftCoupon');
		$GCAmount = 0;
		$GCCode = "";
		if($GiftCoupon)
		{
			$GCAmount = $GiftCoupon['Value'];
			$GCCode = $GiftCoupon['Code'];
		}

		$paymentintentid = "";
		if(Session::get("StripePaymentType")=="Google Pay" && isset($request->is_stripe_wallet))
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_GOOGLEPAY";
			$paymentintentid = Session::has("ShoppingCart.apple_google_paymentintentid")?Session::get("ShoppingCart.apple_google_paymentintentid"):'';
		}

		if(Session::get("StripePaymentType")=="Apple Pay" && isset($request->is_stripe_wallet))
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_APPLEPAY";
			$paymentintentid = Session::has("ShoppingCart.apple_google_paymentintentid")?Session::get("ShoppingCart.apple_google_paymentintentid"):'';
		}
		/*if($request->is_stripe_wallet == "google_pay")
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_GOOGLEPAY";
		}

		if($request->is_stripe_wallet == "apple_pay")
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_APPLEPAY";
		}*/

		$ShippingSignatureFlag = 'No';
		if($onlyGCPurchased == 0 && isset($request->shipsignatureflag) && $request->shipsignatureflag == 'Yes')
			$ShippingSignatureFlag = 'Yes';

		if($onlyGCPurchased == 0 && isset($request->shipping_signature) && $request->shipping_signature == 'Yes')
			$ShippingSignatureFlag = 'Yes';

		$OrderInsert = array (
				'customer_id'		=> $customer_id,
				'sub_total' 		=> $SubTotal,
				'shipping_amt' 		=> $ShipMethodCharge,
				'tax' 				=> $this->GetAllCharges('Tax'),
				'gift_charge' 		=> $this->GetAllCharges('GiftWrappingCharge'),
				'gift_message' 		=> '',
				'is_gift_order'		=> 'No',
				'handling_charge' 	=> '0.00',
				'wire_discount' 	=> '0.00',
				'auto_discount' 	=> $this->GetAllDiscounts('AutoDiscount'),
				'quantity_discount'	=> $this->GetAllDiscounts('QuantityDiscount'),
				'reward_discount'	=> $this->GetAllDiscounts('YotpoRewardDiscount'),
				'coupon_amount' 	=> $this->GetAllDiscounts('CouponDiscount'),
				'coupon_id' 		=> $coupon_id,
				'Second_coupon_id'	=> $yotporewardcode,
				'coupon_code' 		=> $OneCouponCode,
				'gc_amount' 		=> $this->GetAllDiscounts('GiftCoupon'),
				'gc_code' 			=> $GCCode,
				'refer_id'			=> $referDiscountId,
				'refer_amount' 		=> $this->GetAllDiscounts('AutoReferDiscount'),
				'order_total' 		=> $this->GetNetTotal(),
				'shipinfo' 			=> $ShipMethodName,
				'payment_type' 		=> $Payment_Type,
				'payment_method' 	=> $Payment_Method,
				'pay_status' 		=> 'Unpaid',
				'ccinfo' 			=> $cc_info,
				'customer_comment' 	=> $customer_comment,
				'status'			=> 'Pending',
				'currency_info'		=> $currency_info,
				'checkout_type' 	=> $checkout_type,
				'user_type' 		=> $w_user_type,
				'ilevelid' 			=> $w_ilevelid,
				//'level_price' 		=> $w_level_price,
				'ship_first_name' 	=> isset($Shipping['first_name']) ? $Shipping['first_name'] : '', //$Shipping['first_name'],
				'ship_last_name' 	=> isset($Shipping['last_name']) ? $Shipping['last_name'] : '',//$Shipping['last_name'],
				'ship_company' 		=> isset($Shipping['company']) ? $Shipping['company'] : '',//$Shipping['company'],
				'ship_email' 		=> isset($Shipping['email']) ? $Shipping['email'] : '',//$Shipping['email'],
				'ship_address1' 	=> isset($Shipping['address1']) ? $Shipping['address1'] : '',//$Shipping['address1'],
				'ship_address2' 	=> isset($Shipping['address2']) ? $Shipping['address2'] : '',//$Shipping['address2'],
				'ship_city' 		=> isset($Shipping['city']) ? $Shipping['city'] : '',//$Shipping['city'],
				'ship_zip' 			=> isset($Shipping['zip']) ? $Shipping['zip'] : '',//$Shipping['zip'],
				'ship_state' 		=> isset($Shipping['state']) ? $Shipping['state'] : '',//$Shipping['state'],
				'ship_country' 		=> isset($Shipping['country']) ? $Shipping['country'] : '',//$Shipping['country'],
				'ship_phone' 		=> isset($Shipping['phone']) ? $Shipping['phone'] : '',//$Shipping['phone'],
				'bill_first_name' 	=> isset($Billing['first_name']) ? $Billing['first_name'] : '',//$Billing['first_name'],
				'bill_last_name' 	=> isset($Billing['last_name']) ? $Billing['last_name'] : '',//$Billing['last_name'],
				'bill_company' 		=> isset($Billing['company']) ? $Billing['company'] : '',//$Billing['company'],
				'bill_email' 		=> isset($Billing['email']) ? $Billing['email'] : '',//$Billing['email'],
				'bill_address1' 	=> isset($Billing['address1']) ? $Billing['address1'] : '',//$Billing['address1'],
				'bill_address2' 	=> isset($Billing['address2']) ? $Billing['address2'] : '',//$Billing['address2'],
				'bill_city' 		=> isset($Billing['city']) ? $Billing['city'] : '',//$Billing['city'],
				'bill_zip' 			=> isset($Billing['zip']) ? $Billing['zip'] : '',//$Billing['zip'],
				'bill_state' 		=> isset($Billing['state']) ? $Billing['state'] : '',//$Billing['state'],
				'bill_country' 		=> isset($Billing['country']) ? $Billing['country'] : '',//$Billing['country'],
				'bill_phone' 		=> isset($Billing['phone']) ? $Billing['phone'] : '',////$Billing['phone'],
				'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
				'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
				'is_only_gc'		=> (string)$onlyGCPurchased,
				'free_gift'			=> $free_gift,
				//======= Added Code Date 09/02/2015 Start Here ===//
				'gift_from'				=> $gift_from,
				'gift_to'				=> $gift_to,
				'gift_message_customer'	=> $gift_message_customer,
				//======= Added Code Date 09/02/2015 End Here ===//
											//Credit Limit Code Start
			   'cust_current_credit_limit' => $cust_current_credit_limit,
			   'apply_credit'          => $apply_credit,
			   'remaining_credit'      => $remaining_credit,
			   'use_credit_limit'      => $use_credit_limit,
											//Credit Limit Code End
			   'is_dropship_order'     => $is_dropship_order,
			   'shipping_signature'	 => $OrdShipSignature,
			   'is_shipping_signature' => $ShippingSignatureFlag,
			   'Is_GiftCertificatPurchase' => $this->GetCartAttribute('CheckGCPurchasedVal'),
			   'EstimatedDeliveryDate' 	=> $EstimatedDeliveryDate,
			   'fullshipping_info'		=> 	$fullShippingname,
			   'merge_note'		=> 	$merge_note,
			   'bogo_discount'	=> $this->GetAllDiscounts('DogoDiscount'),
			   'is_maxtwoday'	=> $is_maxtwoday,
			   'route_shipping_insurance_charge' => $OrdShipInsurance,
               'vLang_flag' => Session::get('ShoppingCart.YotpoFreeGiftCoupon'),
               'paymentintentid' => $paymentintentid,
			   'payment_gateway_response' => Session::get("PayMethodRes")?Session::get("PayMethodRes"):'',
			   //'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):'',
		);
		//echo "<pre>"; print_r($OrderInsert); exit;
		// $PlaceOrder = Order::create($OrderInsert);
		// $OrderID = $PlaceOrder->orders_id;
		// $aa = Session::put('ShoppingCart.OrderID',$OrderID); // set order id in cart

		//if(isset($_REQUEST['AppleGPay']) && Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID')!=''){
		$is_order_update = 'N';
		/*if(isset($request->is_step_gpay) && $request->is_step_gpay!='' && Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID')!=''){
			$OrderID = Session::get('ShoppingCart.OrderID');
			//Log::info('Order Table Order Id '.$_REQUEST['AppleGPay']."--".$OrderID);
			$CurrOrder = Order::find($OrderID);
			$udpRefer = $CurrOrder->update($OrderInsert);
			$is_order_update = 'Y';
		} else {
			$PlaceOrder = Order::create($OrderInsert);
			$OrderID = $PlaceOrder->orders_id;
			$aa = Session::put('ShoppingCart.OrderID',$OrderID); // set order id in cart
		}*/
		$log['PlaceOrderInsert'] = json_encode($OrderInsert);
		addLog("PlaceOrder",$log);
		$PlaceOrder = Order::create($OrderInsert);
		$OrderID = $PlaceOrder->orders_id;
		$aa = Session::put('ShoppingCart.OrderID',$OrderID); // set order id in cart

		if(isset($request->PaymentMethod) && $request->PaymentMethod=='PAYMENT_PAYPALEC'){
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." PayPal Order Insert :".json_encode($OrderInsert)."\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$stringData = '';
			$fh = fopen($myFile, 'a+');
			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Place order after order insert Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
			}
			if(isset($OrderInsert) && !empty($OrderInsert)){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Place order after order insert Order Data : ".json_encode($OrderInsert)." :\n";
			}
			if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
				$stringData .= date("m/d/Y H:i:s")." Regarding Gift Certificate Place order after order insert Session User Email : ".Session::get('sess_useremail')." :\n";
			}
			fwrite($fh, $stringData);
			fclose($fh);
		}

		if($OrderID != "")
		{
			// To add 'OR' Change on :: 06-10-2015
			$CurrOrder = Order::find($OrderID);
			$updateOrder = array ('orders_no'	 => "OR".$OrderID );
			$udpRefer = $CurrOrder->update($updateOrder);
		}

		$tempCart = Session::get('ShoppingCart.Cart');
		$cnt_row  = count($tempCart);

		$IsVender = "No";
		$IsAmazOR = 'No';
		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAMAZON')
		{
			$IsAmazOR = 'Yes';
		}

		$IsPerfumePWVendor = "No";

		//echo "<pre>"; print_r($tempCart); exit;

		//if(Session::has('sess_useremail') && Session::get('sess_useremail') == 'gequaldev@gmail.com'){
		$TaxValueNew = $this->GetAllCharges('TaxValue');
			if($is_order_update == 'N'){

				$couponCode = $this->GetAllCoupons('CouponCode');
			    $CouponDiscount = $this->GetAllDiscounts('CouponDiscount');

				$TotalTxShipping = 0;
				$couponPercentage = 0;
				if (isset($couponCode) && $couponCode != '' && $CouponDiscount > 0  && Session::has('ShoppingCart.CouponPercentage'))
				{
					$couponPercentage = session::get('ShoppingCart.CouponPercentage');

				}

				for($i=0; $i<$cnt_row; $i++)
				{

					$ItemWiseTaxVal = 0;
					if($TaxValueNew > 0)
					{
						$ItemWiseTaxVal = $this->ItemWiseTax($tempCart[$i]['TotPrice'],$i);

						$ItemWiseTaxVal = NumberFormat($ItemWiseTaxVal);
					}

					$allocatedDiscount  = 0;
					if (isset($couponCode) && $couponCode != '' && $CouponDiscount > 0 && Session::has('ShoppingCart.CountShipTax') &&  Session::get('ShoppingCart.CountShipTax')=='1' && Session::has('ShoppingCart.CouponPercentage'))
					{

					$ShippingChargeItemWise = ($this->GetShippingCharge() > 0  && $SubTotal > 0)
						? NumberFormat(($tempCart[$i]["TotPrice"] * $this->GetShippingCharge()) / $SubTotal)
						: 0;
					$ItemWiseTaxShipping = NumberFormat($ItemWiseTaxVal + $ShippingChargeItemWise);
					$allocatedDiscount      = $ItemWiseTaxShipping * ($couponPercentage / 100);

					$allocatedDiscount      = NumberFormat($allocatedDiscount);
					}
					$CouponItemWiseVal = 0;
					if(!empty($tempCart[$i]['CouponDisItemWiseDiscout']) && $tempCart[$i]['CouponDisItemWiseDiscout'] > 0 && !empty($couponPercentage) && $couponPercentage==100)
					{
						$allocatedDiscount = $allocatedDiscount."###".$tempCart[$i]['CouponDisItemWiseDiscout'];
					}

					$tempCart[$i]['TaxShippingItemWiseDiscount'] = $allocatedDiscount;

					if(empty($this->GetAllDiscounts('CouponDiscount')) || $this->GetAllDiscounts('CouponDiscount') <= 0)
					{
						$tempCart[$i]['CouponDisItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('AutoDiscount')) || $this->GetAllDiscounts('AutoDiscount') <= 0)
					{
						$tempCart[$i]['AutoItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('QuantityDiscount')) || $this->GetAllDiscounts('QuantityDiscount') <= 0)
					{
						$tempCart[$i]['QuantityItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('YotpoRewardDiscount')) || $this->GetAllDiscounts('YotpoRewardDiscount') <= 0)
					{
						$tempCart[$i]['RewardItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('DogoDiscount')) || $this->GetAllDiscounts('DogoDiscount') <= 0)
					{
						$tempCart[$i]['BogoItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('GiftCoupon')) || $this->GetAllDiscounts('GiftCoupon') <= 0)
					{
						$tempCart[$i]['GiftCertificateItemWiseDiscout'] = 0;
					}
					if(empty($apply_credit) && $apply_credit<=0)
					{
						$tempCart[$i]['CreditLimitItemWiseDiscout'] = 0;
					}

					$ActualPrice = 0;
					$CouponDisItemWiseDiscout = 0;
					$AutoItemWiseDiscout = 0;
					$QuantityItemWiseDiscout = 0;
					$RewardItemWiseDiscout = 0;
					$BogoItemWiseDiscout = 0;
					$GiftCertificateItemWiseDiscout = 0;
					$CreditLimitItemWiseDiscout = 0;

					if(!empty($tempCart[$i]['CouponDisItemWiseDiscout']) && $tempCart[$i]['CouponDisItemWiseDiscout'] > 0)
					{
						$CouponDisItemWiseDiscout = $tempCart[$i]['CouponDisItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['AutoItemWiseDiscout']) && $tempCart[$i]['AutoItemWiseDiscout'] > 0)
					{
						$AutoItemWiseDiscout = $tempCart[$i]['AutoItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['QuantityItemWiseDiscout']) && $tempCart[$i]['QuantityItemWiseDiscout'] > 0)
					{
						$QuantityItemWiseDiscout = $tempCart[$i]['QuantityItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['RewardItemWiseDiscout']) && $tempCart[$i]['RewardItemWiseDiscout'] > 0)
					{
						$RewardItemWiseDiscout = $tempCart[$i]['RewardItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['BogoItemWiseDiscout']) && $tempCart[$i]['BogoItemWiseDiscout'] > 0)
					{
						$BogoItemWiseDiscout = $tempCart[$i]['BogoItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['GiftCertificateItemWiseDiscout']) && $tempCart[$i]['GiftCertificateItemWiseDiscout'] > 0)
					{
						$GiftCertificateItemWiseDiscout = $tempCart[$i]['GiftCertificateItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['CreditLimitItemWiseDiscout']) && $tempCart[$i]['CreditLimitItemWiseDiscout'] > 0)
					{
						$CreditLimitItemWiseDiscout = $tempCart[$i]['CreditLimitItemWiseDiscout'];
					}

					$ActualPrice = $CouponDisItemWiseDiscout + $AutoItemWiseDiscout + $QuantityItemWiseDiscout + $RewardItemWiseDiscout + $BogoItemWiseDiscout+ $GiftCertificateItemWiseDiscout + $CreditLimitItemWiseDiscout;

					if(!empty($ActualPrice) && $ActualPrice > 0)
					{
						$ActualPrice = $tempCart[$i]['TotPrice'] - $ActualPrice;
						$ActualPrice = NumberFormat($ActualPrice);
					}
					else
					{
						$ActualPrice = 0;
					}

					//$TotalItemTaxAmount = $this->itemTaxAmount($ActualPrice,$tempCart[$i]['TotPrice']);

					if(!isset($tempCart[$i]['YotpoFreeGift']))
						$tempCart[$i]['YotpoFreeGift'] = '';

					$OrderDetailInsert = array (
						'orders_id'				=> $OrderID,
						'orders_no'				=> "OR".$OrderID, // To add 'OR' Change on :: 06-10-2015
						'products_id'			=> $tempCart[$i]['ProductID'],
						'sku' 					=> $tempCart[$i]['SKU'],
						'product_name'			=> $tempCart[$i]['ProductName'].'<br>'.$tempCart[$i]['short_description'],
						'quantity' 				=> $tempCart[$i]['Qty'],
						'price' 				=> $tempCart[$i]['Price'],
						'total' 				=> $tempCart[$i]['TotPrice'],
						'status' 				=> '1',
						'item_price' 			=> (isset($tempCart[$i]['ItemPrice']))?$tempCart[$i]['ItemPrice']:0,
						'excluded_flag'  		=> (isset($tempCart[$i]['FinalSale']))?$tempCart[$i]['FinalSale']:'',
						'is_gift_wrap'			=> (isset($tempCart[$i]['gift_wrap']))?$tempCart[$i]['gift_wrap']:'',
						'is_free_gift_products' => (isset($tempCart[$i]['IS_Free_Gift']))?$tempCart[$i]['IS_Free_Gift']:'No',
						'VendorSKU'				=> $tempCart[$i]['VendorSKU'],
						'IsCosmo'				=> $tempCart[$i]['IsCosmo'],
						'IsNandansons'  		=> $tempCart[$i]['IsNandansons'],
						'IsPerfumePW'			=> $tempCart[$i]['IsPerfumePW'],
						'IsPCA'					=> $tempCart[$i]['IsPCA'],
						'IsND'					=> $tempCart[$i]['IsND'] ?? 'No',
						'coupon_itemwise_discount' => $tempCart[$i]['ItemWiseCouponDiscount'],
						'handling_time_str'		=> 	(isset($tempCart[$i]['HandlingTimeStr']))?$tempCart[$i]['HandlingTimeStr']:'',
						'attribute_info'        => (isset($tempCart[$i]['IsYotpoFreeProduct']))?$tempCart[$i]['IsYotpoFreeProduct']:'No',
						'actual_price'			=> $ActualPrice,
						'item_tax_amount'		=> $ItemWiseTaxVal,
						'sf_orderitemid'		=> $tempCart[$i]['TaxShippingItemWiseDiscount'] ?? 0
					);
					//Log::info('OrderDetailInsert -- '.json_encode($OrderDetailInsert));
					$log['OrderDetailInsert'] = json_encode($OrderDetailInsert);
					addLog("PlaceOrder",$log);
					$OrdDetail = OrderDetail::create($OrderDetailInsert);
					//Log::info('OrdDetail -- '.json_encode($OrdDetail));
					$OrderDetailID = $OrdDetail->orders_detail_id;
					if(($tempCart[$i]['IsCosmo']=="Yes" || $tempCart[$i]['IsNandansons']=='Yes' || $tempCart[$i]['IsPerfumePW']=='Yes' || $tempCart[$i]['IsPCA']=="Yes" || $tempCart[$i]['IsND']=="Yes") && $tempCart[$i]['VendorSKU']!='' )
					{
						$IsVender = "Yes";
					}
					if($tempCart[$i]['IsPerfumePW']=='Yes' )
					{
						$IsPerfumePWVendor = "Yes";
					}

					## Insert purchased GC

					$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$tempCart[$i]);

					//if($tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
					if($IsGiftCertificateItem == 'Yes')
					{
						//$AddGC = $this->InsertGiftCertificateDB($tempCart[$i], $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
						$AddGC = $this->checkGiftCertificateItem('InsertGiftCertificateInDB', $tempCart[$i], 'Yes', $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
					}
				}
			}
		/*} else {
			for($i=0; $i<$cnt_row; $i++)
			{
				if(empty($this->GetAllDiscounts('CouponDiscount')) || $this->GetAllDiscounts('CouponDiscount') <= 0)
				{
					$tempCart[$i]['CouponDisItemWiseDiscout'] = 0;
				}
				if(empty($this->GetAllDiscounts('AutoDiscount')) || $this->GetAllDiscounts('AutoDiscount') <= 0)
				{
					$tempCart[$i]['AutoItemWiseDiscout'] = 0;
				}
				if(empty($this->GetAllDiscounts('QuantityDiscount')) || $this->GetAllDiscounts('QuantityDiscount') <= 0)
				{
					$tempCart[$i]['QuantityItemWiseDiscout'] = 0;
				}
				if(empty($this->GetAllDiscounts('YotpoRewardDiscount')) || $this->GetAllDiscounts('YotpoRewardDiscount') <= 0)
				{
					$tempCart[$i]['RewardItemWiseDiscout'] = 0;
				}
				if(empty($this->GetAllDiscounts('DogoDiscount')) || $this->GetAllDiscounts('DogoDiscount') <= 0)
				{
					$tempCart[$i]['BogoItemWiseDiscout'] = 0;
				}
				if(empty($this->GetAllDiscounts('GiftCoupon')) || $this->GetAllDiscounts('GiftCoupon') <= 0)
				{
					$tempCart[$i]['GiftCertificateItemWiseDiscout'] = 0;
				}
				if(empty($apply_credit) && $apply_credit<=0)
				{
					$tempCart[$i]['CreditLimitItemWiseDiscout'] = 0;
				}

				$ActualPrice = 0;
				$CouponDisItemWiseDiscout = 0;
				$AutoItemWiseDiscout = 0;
				$QuantityItemWiseDiscout = 0;
				$RewardItemWiseDiscout = 0;
				$BogoItemWiseDiscout = 0;
				$GiftCertificateItemWiseDiscout = 0;
				$CreditLimitItemWiseDiscout = 0;

				if(!empty($tempCart[$i]['CouponDisItemWiseDiscout']) && $tempCart[$i]['CouponDisItemWiseDiscout'] > 0)
				{
					$CouponDisItemWiseDiscout = $tempCart[$i]['CouponDisItemWiseDiscout'];
				}
				if(!empty($tempCart[$i]['AutoItemWiseDiscout']) && $tempCart[$i]['AutoItemWiseDiscout'] > 0)
				{
					$AutoItemWiseDiscout = $tempCart[$i]['AutoItemWiseDiscout'];
				}
				if(!empty($tempCart[$i]['QuantityItemWiseDiscout']) && $tempCart[$i]['QuantityItemWiseDiscout'] > 0)
				{
					$QuantityItemWiseDiscout = $tempCart[$i]['QuantityItemWiseDiscout'];
				}
				if(!empty($tempCart[$i]['RewardItemWiseDiscout']) && $tempCart[$i]['RewardItemWiseDiscout'] > 0)
				{
					$RewardItemWiseDiscout = $tempCart[$i]['RewardItemWiseDiscout'];
				}
				if(!empty($tempCart[$i]['BogoItemWiseDiscout']) && $tempCart[$i]['BogoItemWiseDiscout'] > 0)
				{
					$BogoItemWiseDiscout = $tempCart[$i]['BogoItemWiseDiscout'];
				}
				if(!empty($tempCart[$i]['GiftCertificateItemWiseDiscout']) && $tempCart[$i]['GiftCertificateItemWiseDiscout'] > 0)
				{
					$GiftCertificateItemWiseDiscout = $tempCart[$i]['GiftCertificateItemWiseDiscout'];
				}
				if(!empty($tempCart[$i]['CreditLimitItemWiseDiscout']) && $tempCart[$i]['CreditLimitItemWiseDiscout'] > 0)
				{
					$CreditLimitItemWiseDiscout = $tempCart[$i]['CreditLimitItemWiseDiscout'];
				}

				$ActualPrice = $CouponDisItemWiseDiscout + $AutoItemWiseDiscout + $QuantityItemWiseDiscout + $RewardItemWiseDiscout + $BogoItemWiseDiscout+ $GiftCertificateItemWiseDiscout + $CreditLimitItemWiseDiscout;

				if(!empty($ActualPrice) && $ActualPrice > 0)
				{
					$ActualPrice = $tempCart[$i]['TotPrice'] - $ActualPrice;
					$ActualPrice = NumberFormat($ActualPrice);
				}
				else
				{
					$ActualPrice = 0;
				}

				if(!isset($tempCart[$i]['YotpoFreeGift']))
					$tempCart[$i]['YotpoFreeGift'] = '';

				$OrderDetailInsert = array (
					'orders_id'				=> $OrderID,
					'orders_no'				=> "OR".$OrderID, // To add 'OR' Change on :: 06-10-2015
					'products_id'			=> $tempCart[$i]['ProductID'],
					'sku' 					=> $tempCart[$i]['SKU'],
					'product_name'			=> $tempCart[$i]['ProductName'].'<br>'.$tempCart[$i]['short_description'],
					'quantity' 				=> $tempCart[$i]['Qty'],
					'price' 				=> $tempCart[$i]['Price'],
					'total' 				=> $tempCart[$i]['TotPrice'],
					'status' 				=> '1',
					'item_price' 			=> (isset($tempCart[$i]['ItemPrice']))?$tempCart[$i]['ItemPrice']:0,
					'excluded_flag'  		=> (isset($tempCart[$i]['FinalSale']))?$tempCart[$i]['FinalSale']:'',
					'is_gift_wrap'			=> (isset($tempCart[$i]['gift_wrap']))?$tempCart[$i]['gift_wrap']:'',
					'is_free_gift_products' => (isset($tempCart[$i]['IS_Free_Gift']))?$tempCart[$i]['IS_Free_Gift']:'No',
					'VendorSKU'				=> $tempCart[$i]['VendorSKU'],
					'IsCosmo'				=> $tempCart[$i]['IsCosmo'],
					'IsNandansons'  		=> $tempCart[$i]['IsNandansons'],
					'IsPerfumePW'			=> $tempCart[$i]['IsPerfumePW'],
					'IsPCA'					=> $tempCart[$i]['IsPCA'],
					'coupon_itemwise_discount' => $tempCart[$i]['ItemWiseCouponDiscount'],
					'handling_time_str'		=> 	(isset($tempCart[$i]['HandlingTimeStr']))?$tempCart[$i]['HandlingTimeStr']:'',
					'attribute_info'        => (isset($tempCart[$i]['IsYotpoFreeProduct']))?$tempCart[$i]['IsYotpoFreeProduct']:'No',
					'actual_price'			=> $ActualPrice
				);

				$OrdDetail = OrderDetail::create($OrderDetailInsert);
				$OrderDetailID = $OrdDetail->orders_detail_id;
				if(($tempCart[$i]['IsCosmo']=="Yes" || $tempCart[$i]['IsNandansons']=='Yes' || $tempCart[$i]['IsPerfumePW']=='Yes' || $tempCart[$i]['IsPCA']=="Yes") && $tempCart[$i]['VendorSKU']!='' )
				{
					$IsVender = "Yes";
				}
				if($tempCart[$i]['IsPerfumePW']=='Yes' )
				{
					$IsPerfumePWVendor = "Yes";
				}

				## Insert purchased GC

				$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$tempCart[$i]);

				//if($tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
				if($IsGiftCertificateItem == 'Yes')
				{
					//$AddGC = $this->InsertGiftCertificateDB($tempCart[$i], $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
					$AddGC = $this->checkGiftCertificateItem('InsertGiftCertificateInDB', $tempCart[$i], 'Yes', $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
				}
			}
		}*/

		$EstimateNewDate = '';

		if($onlyGCPurchased == 0)
		{
			$EstimateNewDate = $this->SetEstimateDate($ShippingInfo['ShippingMethodID'],$IsVender,$IsPerfumePWVendor,$Shipping['zip'],$Shipping['state'],$Shipping['country']);
			//echo $EstimateNewDate; exit;
		}
		$cur_date = date("Y-m-d");
		if($EstimateNewDate == "" && strtotime($EstimateNewDate) < strtotime($cur_date)){
			$EstimateNewDate = "0000-00-00 00:00:00";
		}

		$updArayVal = array ('EstimatedDeliveryDate' => $EstimateNewDate);
		$updOrder22 = $CurrOrder->update($updArayVal);

		if($OrderID!="" && $IsVender=="Yes")
		{
			// To add 'OR' Change on :: 06-10-2015
			$updateOrder1 = array('IsVender' => $IsVender);
			$udpRefer1 = $CurrOrder->update($updateOrder1);
		}

		$customerEmail = '';
		if(isset($Billing['email']) && $Billing['email']!='')
		{
			//$customerEmail	= "naresh.qualdev@gmail.com";
			$customerEmail	= $Billing['email'];
		}

		$orderNo		= 'OR'.$OrderID;
		$customerName = '';
		if((isset($Billing['first_name']) && $Billing['first_name']!=''))
		{
			$customerName	= $Billing['first_name']." ";
		}

		if(isset($Billing['last_name']) && $Billing['last_name'])
		{
			$customerName = $customerName.$Billing['last_name'];
		}

		$gc_remaining_value = 0;
		if($GiftCouponInfo && count($GiftCouponInfo) > 0 && isset($GiftCouponInfo["Code"]) && $GiftCouponInfo["Code"]!='')
		{
			$GiftCouponInfo['Value'] = ($GiftCouponInfo['Value']!='')?$GiftCouponInfo['Value']:0.00;
			$new_total = $this->GetNetTotal() + $GiftCouponInfo['Value'];
			if($new_total <= $GiftCouponInfo['Applicable_Value'])
			{
				$gc_remaining_value = NumberFormat(($GiftCouponInfo['Applicable_Value']-$new_total));
			}

			if($GiftCouponInfo['Code'] != '' && $new_total <= $GiftCouponInfo['Applicable_Value'])
			{
				$str_info  = 'Gift Certificate discount value is greater than order total amount. \n\n';
				$str_info .= 'So net $'.$new_total.' is deduct from gift certifiacte value. \n\n';
				$str_info .= 'Used Gift Certificate code is ('.$GiftCouponInfo['Code'].')';

				$updAray = array ('pay_status' 	   => 'Paid','transaction_info' => $str_info);
				$updOrder = $CurrOrder->update($updAray);
				return redirect(config('global.SITE_URL')."order-receipt");
			}
		} else if($this->GetNetTotal() == 0){
			$updAray = array ('pay_status' => 'Paid');
			$updOrder = $CurrOrder->update($updAray);
			return redirect(config('global.SITE_URL')."order-receipt");
		}

		if($request->PaymentMethod == 'STRIPE_GOOGLEPAY')
		{
			$myFile = env('LOG_BASE_PATH').'Logs/Walmart/ApplePayLog.txt';

			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".json_encode($OrderInsert)." : In Shoppingcart Controller.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$StepValue = '';
            if(isset($request->is_step_gpay) && $request->is_step_gpay!='')
            {
				$StepValue = $request->is_step_gpay;
			}

			$updAray = array (
			'pay_status'   => 'Paid',
			'transaction_info' => serialize($PaymentResponse)." ".$StepValue
			);

		  $updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
		  return redirect(config('global.SITE_URL').'order-receipt');
		}

		if($request->PaymentMethod == 'STRIPE_APPLEPAY')
		{
			$myFile =env('LOG_BASE_PATH').'Logs/Walmart/ApplePayLog.txt';

			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".json_encode($OrderInsert)." : In Shoppingcart Controller.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$StepValue = '';
            if(isset($request->is_step_gpay) && $request->is_step_gpay!='')
            {
				$StepValue = $request->is_step_gpay;
			}

			$updAray = array (
			'pay_status'   => 'Paid',
			'transaction_info' => serialize($PaymentResponse)." ".$StepValue
			);

		  $updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
		  return redirect(config('global.SITE_URL').'order-receipt');
		}

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type']=='PAYMENT_PAYPALEC') ## Paypal Express payment gateway condition
		{
			$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';

			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$OrderID." : Paypal DoPayment Function In Shoppingcart Controller.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

			if(isset($OrderID) && $OrderID > 0)
			{
				return redirect(config('global.SITE_URL').'paypal/dopayment/'.$OrderID);
			}
			else
			{
				return redirect(config('global.SITE_URL').'paypal/dopayment');
			}
		}
		elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYPALCC') ## Paypal Do direct payment gateway condition
		{
			//header("location:".$SECURED_PATH."paypal_checkout/paypal_dodirect_payment.php");
			//exit;
		}
		elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_STRIPE') ## Braintree payment gateway condition
		{
			// echo config('global.PHYSICAL_PATH');
			// exit;
			//$myFile = '/home/maxaroma/public_html/Logs/Walmart/StripeLog.txt';
			$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/StripeLog.txt';

			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$OrderID." : Stripe Payment In Shoppingcart Controller.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			return redirect(config('global.SITE_URL').'stripe/placeorder/'.$OrderID);
			exit;
		}
		elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_AUTHORIZENETCC') ## AUTHORIZE payment gateway condition
		{
			//header("location:".$SECURED_PATH."authorize_checkout/payment_authorize.php");
			//exit;
		}
		elseif(isset($arrPaymentDetail['Payment_Type']) && ($arrPaymentDetail['Payment_Type'] == 'PAYMENT_MOC' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_WT' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_CL' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_DS' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_GIFT_CERTIFICATE')) ## Other payment gateway condition
		{
			addLog("PlaceOrder",$log);
			return redirect(config('global.SITE_URL').'order-receipt');
			exit;
		}else if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAFTERPAY') { ## Afterpay payment gateway condition
			if(isset($request->is_btm_ap_chkout) && $request->is_btm_ap_chkout == "Yes"){	//from the bottom express checkout button
				addLog("PlaceOrder",$log);
				return redirect(config('global.SITE_URL').'afterpay/dopayment_express_btm/'.$OrderID.'~'.$customer_id);
				exit;
			}else{
				$ap_psChecksum = $request->ap_psChecksum;

				if(empty($ap_psChecksum) && $ap_psChecksum=='')
				{
					Session::flash('PlaceOrderError','Error in Processing Request. Please try again.');
					$log['msg'] = 'Error in Processing Request. Please try again.';
					addLog("PlaceOrder",$log);

					$transaction_info = "This transaction has been Declined.";
					$updAray = array (
										'status' 	   				=> 'Declined',
										'transaction_info' 			=> $transaction_info
									  );

					$uporderres = Order::Where("orders_id","=",$OrderID)
										->update($updAray);

					$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);

					/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);

					$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

					Session::forget('ShoppingCart.AfterPay.Checkout_Token');
					return redirect('checkout');
					exit;
				}
				else
				{
				return redirect(config('global.SITE_URL').'afterpay/success_express/'.$ap_psChecksum.'/'.$OrderID.'~'.$customer_id);
				exit;
				}
				//header("Location:" . $SECURED_PATH . "PayWithAfterpay/afterpay_checkout.php");
				//exit();
			}
		}elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAMAZON'){
			return redirect(config('global.SITE_URL').'amazon/placeorder');
			exit;
		}elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_STRIPE_BUTTON'){
			return redirect(config('global.SITE_URL').'order-receipt');
			exit;
		}
		#### Here check payment type and do processing end ####
		if($OrderID > 0)
		{
			$updArayVAL = array ('pay_status' => 'Unpaid', 'status' => 'Declined');
			$uporderres11 = $CurrOrder->update($updArayVAL);

			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);

			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);

			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
		}
		return redirect('/');
	}

	public function itemTaxAmount($ActualPrice = 0, $totPrice = 0)
	{
		if ($ActualPrice <= 0) {
			$ActualPrice = $totPrice;
		}

		$TotalItemTaxAmount = 0;

		if (Session::has('ShoppingCart.Tax') && Session::get('ShoppingCart.Tax') > 0 && $ActualPrice > 0 && Session::has('ShoppingCart.TaxableSubTotal') && Session::get('ShoppingCart.TaxableSubTotal') > 0) {
			$TaxableSubTotal = (float) Session::get('ShoppingCart.TaxableSubTotal');
			$ActualTotalTaxAmount = (float) Session::get('ShoppingCart.Tax');

			$TaxableShippingCharge = 0;
			$TaxableShippingInsurance = 0;
			$TaxableShippingSignature = 0;

			if (Session::has('ShoppingCart.TaxableShipping') &&	Session::get('ShoppingCart.TaxableShipping') === "Yes") {
				$TaxableShippingCharge = (float) $this->GetAllCharges('ShippingCharge');
			}

			if (Session::has('ShoppingCart.TaxableShippingInsurance') && Session::get('ShoppingCart.TaxableShippingInsurance') === "Yes") {
				$TaxableShippingInsurance = (float) $this->GetAllCharges('ShippingInsurance');
			}

			if (Session::has('ShoppingCart.TaxableShippingSignature') && Session::get('ShoppingCart.TaxableShippingSignature') === "Yes") {
				$TaxableShippingSignature = (float) $this->GetAllCharges('ShippingSignature');
			}

			$TotalTaxableCharges = $TaxableShippingCharge + $TaxableShippingInsurance + $TaxableShippingSignature;

			$ItemTaxableSubTotal = $TaxableSubTotal - $TotalTaxableCharges;

			if ($ItemTaxableSubTotal > 0) {
				$ItemChargeShare = ($ActualPrice / $ItemTaxableSubTotal) * $TotalTaxableCharges;
				$TaxableActualPrice = $ActualPrice + $ItemChargeShare;
				$TotalItemTaxAmount = ($TaxableActualPrice / $TaxableSubTotal) * $ActualTotalTaxAmount;
				$TotalItemTaxAmount = max(0, (float) NumberFormat($TotalItemTaxAmount));
			}
		}

		return $TotalItemTaxAmount;
	}

	public function ApplePayPlaceOrder(Request $request)
	{
		$this->SetupCart();
		//if($this->GetNetTotal() <=0)
		if($this->GetNetTotal() <=0 && (!isset($request->order_invalid) || (isset($request->order_invalid) && $request->order_invalid == "")))
		{
			$err_msg = 'Please change payment type';
			Session::flash('PlaceOrderError',$err_msg);
			return "Zero";
		}

		if(isset($request->PaymentMethod) && $request->PaymentMethod=='PAYMENT_PAYPALEC'){
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." PayPal Place Order Start :";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$skrSKU= $this->OutOfStockItemsRemove();

		//if(count($skrSKU) > 0)
		if(count($skrSKU) > 0 && (!isset($request->order_invalid) || (isset($request->order_invalid) && $request->order_invalid == "")))
		{
			Session::put('SKUListArrayVal',$skrSKU);
			$err_msg = "Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			$log['err_msg'] = $err_msg;
			addLog('PlaceOrder',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return "OutOfStock";
		}
		$IsGiftCertificateItem = '';

		//if(config('global.SHOPP_STATUS') == 'Close')
		if(config('global.SHOPP_STATUS') == 'Close' && (!isset($request->order_invalid) || (isset($request->order_invalid) && $request->order_invalid == "")))
		{
			$err_msg = 'Sorry please try again';
			$log['err_msg'] = $err_msg;
			addLog('PlaceOrder',$log);
			Session::flash('PlaceOrderError',$err_msg);
			Session::forget('ShoppingCart');
			return "Close";
		}

		//if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0 && (!isset($request->order_invalid) || (isset($request->order_invalid) && $request->order_invalid == "")))
		{
			$err_msg = 'Sorry please try again';
			$log['err_msg'] = $err_msg;
			addLog('PlaceOrder',$log);
			Session::flash('PlaceOrderError',$err_msg);
			Session::forget('ShoppingCart');
			return "Close";
		}

		//if($this->Is_WholeSaler_Allow() == false)
		if($this->Is_WholeSaler_Allow() == false && count(Session::get('ShoppingCart.Cart')) <= 0 && (!isset($request->order_invalid) || (isset($request->order_invalid) && $request->order_invalid == "")))
		{
			$err_msg = 'Sorry please try again';
			$log['err_msg'] = $err_msg;
			addLog('PlaceOrder',$log);
			Session::flash('PlaceOrderError',$err_msg);
			return "Close";
		}

		if(isset($request->AppleGPay) && $request->AppleGPay == "G")
		{
			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");

			$log['AppleGPay'] = $request->AppleGPay;
			$log['Payment_Method'] = $Payment_Method;
			$log['PaymentResponse'] = $PaymentResponse;
		}

		if(isset($request->AppleGPay) && $request->AppleGPay == "A")
		{

			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");

			$log['AppleGPay'] = $request->AppleGPay;
			$log['Payment_Method'] = $Payment_Method;
			$log['PaymentResponse'] = $PaymentResponse;
		}

		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		$is_amazon = 0;

		$customer_id = (int)Session::get('sess_icustomerid');

		if(!Session::has('sess_icustomerid') || empty($customer_id))
		{
			$err_msg = "Error while processing your order, Please try again.";
			$log['err_msg'] = $err_msg;
			addLog("PlaceOrder",$log);
			Session::flash('PlaceOrderError',$err_msg);
			Log::info('PlaceOrderError: Customer Not Found');
			return "Guest";
		}

		$Billing  = Session::get('ShoppingCart.BillingAddress');
		$Shipping = Session::get('ShoppingCart.ShippingAddress');
		$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');

		if($onlyGCPurchased == 1)
		{
			Session::put('ShoppingCart.BillingAsShipping','Yes');
			$Billing['sameasbill'] = 'Yes';
			$this->SetShippingAddress($Billing);
			$Shipping = Session::get('ShoppingCart.ShippingAddress');
		}

		$arrPaymentDetail = array();
		if(Session::has('ShoppingCart.Payment_Detail'))
		{
			$arrPaymentDetail = Session::get('ShoppingCart.Payment_Detail');
		}
		$ShippingInfo = Session::get('ShoppingCart.Shipping');
		if($onlyGCPurchased == 0)
		{
			if(empty($ShippingInfo['ShippingMethodID']) || (int)$ShippingInfo['ShippingMethodID'] <=0 )
			{
				$err_msg =  "Please choose shipping method.";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Shipping Method Not Selected');
				return "SHMethod";
			}
		}

		$currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');
		if(Session::has('etype') && Session::get('etype') == 'M')
			$checkout_type = 'M';
		else
			$checkout_type = 'G';

		$cc_info ="";

		$ShippingInfo	 = Session::get('ShoppingCart.Shipping');
		$GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
		$CouponCode = $this->GetAllCoupons('CouponCode');
		$couponRS = array();
		$OneCouponCode = "";
		$coupon_id = '';
		$SecondCouponCode = "";
		$Second_coupon_id = "";

		$yotporewardcode = '';

		if(Session::has('ShoppingCart.YotpoRewardCode') && Session::get('ShoppingCart.YotpoRewardCode') != '')
		{
			$yotporewardcode = Session::get('ShoppingCart.YotpoRewardCode');
		}

		if(!empty($CouponCode))
		{
			$couponRS = Coupon::where('coupon_number','=',$CouponCode)->limit(1)->get();
			if($couponRS && $couponRS->count() > 0)
			{
				$OneCouponCode = $couponRS[0]->coupon_number;
				$coupon_id = $couponRS[0]->coupon_id;
			}

			$Temp_Second_Coupon_Code =  $this->GetAllCoupons('SecondCouponCode');
			if($Temp_Second_Coupon_Code!='')
			{
				$couponRS = Coupon::where('coupon_number','=',$Temp_Second_Coupon_Code)->limit(1)->get();

				if(count($couponRS) > 0)
				{
					$SecondCouponCode = $couponRS[0]->coupon_number;
					$Second_coupon_id = $couponRS[0]->coupon_id;
				}
				if($SecondCouponCode!='')
				{
					$OneCouponCode = $OneCouponCode."#". Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount').":".$SecondCouponCode."#".Session::get('ShoppingCart.PromoCoupon.SecondCouponDiscount');
				}
			}
		}

		## Wholesaler Field Start ###
		$w_user_type = Session::get('eusertype');
		$w_ilevelid  = (Session::has('ilevelid'))?Session::get('ilevelid'):0;
		## Wholesaler Field End ###

		$GiftValue = $this->FreeGiftValue($this->GetNetTotal());

		if(count($GiftValue) > 0) {
			$free_gift = Session::get('ShoppingCart.FreeGift');
			$gift_from = Session::get('ShoppingCart.GiftFrom');
			$gift_to   = Session::get('ShoppingCart.GiftTo');
			$gift_message_customer = Session::get('ShoppingCart.GiftMessageCustomer');
		} else {
			$free_gift = '';
			$gift_from = '';
			$gift_to   = '';
			$gift_message_customer = (isset($request->gift_message_customer))?$request->gift_message_customer:'';
			if(strtolower(trim($gift_message_customer ?? '')) == " *gift message")
			{
				$gift_message_customer = '';
			}
			Session::forget('ShoppingCart.FreeGift');
			Session::forget('ShoppingCart.GiftFrom');
			Session::forget('ShoppingCart.GiftTo');
			Session::forget('ShoppingCart.GiftMessageCustomer');
		}

		$CreditData = $this->GetCreditLimitAmount();
		$CreditAmt = $CreditData['CreditLimit'];
		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		$cust_current_credit_limit = $CreditAmt;
		$apply_credit = $CreditDiscount;
		$remaining_credit = $CreditData['RemainCreditLimit'];

		if($CreditDiscount>0){
			$use_credit_limit = 'Yes';
		}else{
			$use_credit_limit = 'No';
		}
		//Credit Limit Code End

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_DS')
			$is_dropship_order = 'Yes';
		else
			$is_dropship_order = 'No';

		if(isset($request->customer_comment) && strtolower(trim($request->customer_comment)) == "special request")
		{
			$request->customer_comment = "";
		}

		$fullShippingname = '';
		$is_maxtwoday = "No";
		$EstimatedDeliveryDate = '';
		$ShipMethodName = '';
		$ShipMethodCharge = 0;
		$OrdShipSignature = 0;
		$OrdShipInsurance = 0;
		if($onlyGCPurchased == 0)
		{
			if($ShippingInfo["ShippingCharge"] > 0)
			{
				$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(".Session::get('currency_symbol').$ShippingInfo["ShippingCharge"].")</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
			}
			else
			{
				$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(Free)</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
			}
			if(strtolower($ShippingInfo['ShippingMethodName'] ?? '')=='max2days')
			{
				$is_maxtwoday = "Yes";
			}
			$EstimatedDeliveryDate = Session::get("ShoppingCart.EstimatedDeliveryDate");
			$ShipMethodName = $ShippingInfo['ShippingMethodName'];
			$ShipMethodCharge = $this->GetShippingCharge();
			$OrdShipSignature = $this->GetAllCharges('ShippingSignature');
			$OrdShipInsurance = $this->GetAllCharges('ShippingInsurance');
		}

		$cur_date = date("Y-m-d");
		if($EstimatedDeliveryDate == "" && strtotime($EstimatedDeliveryDate) < strtotime($cur_date)){
			$EstimatedDeliveryDate = "0000-00-00 00:00:00";
		}

		$referDiscountId = (Session::has('ShoppingCart.ReferDiscountId'))?Session::get('ShoppingCart.ReferDiscountId'):0;

		$merge_note = (Session::has('ShoppingCart.merge_note')) ? Session::get('ShoppingCart.merge_note') : "";
		//$Payment_Type = (isset($arrPaymentDetail['Payment_Type']))?$arrPaymentDetail['Payment_Type']:'';
		//$Payment_Method = (isset($arrPaymentDetail['Payment_Method']))?$arrPaymentDetail['Payment_Method']:'';

		$customer_comment = (isset($request->customer_comment))?$request->customer_comment:'';
		$SubTotal = (float)Session::get('ShoppingCart.SubTotal');
		$GiftCoupon = $this->GetAllCoupons('GiftCoupon');
		$GCAmount = 0;
		$GCCode = "";
		if($GiftCoupon)
		{
			$GCAmount = $GiftCoupon['Value'];
			$GCCode = $GiftCoupon['Code'];
		}

		$paymentintentid = "";
		if(Session::get("StripePaymentType")=="Google Pay" && isset($request->AppleGPay))
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_GOOGLEPAY";
			$paymentintentid = Session::has("ShoppingCart.apple_google_paymentintentid")?Session::get("ShoppingCart.apple_google_paymentintentid"):'';
		}

		if(Session::get("StripePaymentType")=="Apple Pay" && isset($request->AppleGPay))
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_APPLEPAY";
			$paymentintentid = Session::has("ShoppingCart.apple_google_paymentintentid")?Session::get("ShoppingCart.apple_google_paymentintentid"):'';
		}

		if(isset($request->PaymentMethod) && $request->PaymentMethod=='PAYMENT_PAYPALEC'){
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Before Order Insert for PayPal : ";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$order_response_details = '';
		if(Session::has("PayMethodRes") && Session::get("PayMethodRes")!=''){
			$order_response_details = Session::get("PayMethodRes");
		}
		if(isset($request->PaymentMethod) && $request->PaymentMethod=='PAYMENT_PAYPALEC')
		{
			$Payment_Method = $arrPaymentDetail['Payment_Method'];
			$Payment_Type = $arrPaymentDetail['Payment_Type'];
			if(isset($request->order_details) && $request->order_details!=''){
				$order_response_details = $request->order_details;
			}
			Session::forget("PayMethodRes");
		}

		$ShippingSignatureFlag = 'No';
		if($onlyGCPurchased == 0 && isset($request->shipsignatureflag) && $request->shipsignatureflag == 'Yes')
			$ShippingSignatureFlag = 'Yes';

		if($onlyGCPurchased == 0 && isset($request->shipping_signature) && $request->shipping_signature == 'Yes')
			$ShippingSignatureFlag = 'Yes';

		if(empty($w_user_type) && $w_user_type=="")
		{
			$w_user_type = "Retailer";
		}

				$OrderInsert = array (
				'customer_id'		=> $customer_id,
				'sub_total' 		=> $SubTotal,
				'shipping_amt' 		=> $ShipMethodCharge,
				'tax' 				=> $this->GetAllCharges('Tax'),
				'gift_charge' 		=> $this->GetAllCharges('GiftWrappingCharge'),
				'gift_message' 		=> '',
				'is_gift_order'		=> 'No',
				'handling_charge' 	=> '0.00',
				'wire_discount' 	=> '0.00',
				'auto_discount' 	=> $this->GetAllDiscounts('AutoDiscount'),
				'quantity_discount'	=> $this->GetAllDiscounts('QuantityDiscount'),
				'reward_discount'	=> $this->GetAllDiscounts('YotpoRewardDiscount'),
				'coupon_amount' 	=> $this->GetAllDiscounts('CouponDiscount'),
				'coupon_id' 		=> $coupon_id,
				'Second_coupon_id'	=> $yotporewardcode,
				'coupon_code' 		=> $OneCouponCode,
				'gc_amount' 		=> $this->GetAllDiscounts('GiftCoupon'),
				'gc_code' 			=> $GCCode,
				'refer_id'			=> $referDiscountId,
				'refer_amount' 		=> $this->GetAllDiscounts('AutoReferDiscount'),
				'order_total' 		=> $this->GetNetTotal(),
				'shipinfo' 			=> $ShipMethodName,
				'payment_type' 		=> $Payment_Type,
				'payment_method' 	=> $Payment_Method,
				'pay_status' 		=> 'Unpaid',
				'ccinfo' 			=> $cc_info,
				'customer_comment' 	=> $customer_comment,
				'status'			=> 'Pending',
				'currency_info'		=> $currency_info,
				'checkout_type' 	=> $checkout_type,
				'user_type' 		=> $w_user_type,
				'ilevelid' 			=> $w_ilevelid,
				'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
				'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
				'is_only_gc'		=> (string)$onlyGCPurchased,
				'free_gift'			=> $free_gift,
				'ship_first_name' 	=> isset($Shipping['first_name']) ? $Shipping['first_name'] : '',
				'ship_last_name' 	=> isset($Shipping['last_name']) ? $Shipping['last_name'] : '',
				'ship_company' 		=> isset($Shipping['company']) ? $Shipping['company'] : '',
				'ship_email' 		=> isset($Shipping['email']) ? $Shipping['email'] : '',
				'ship_address1' 	=> isset($Shipping['address1']) ? $Shipping['address1'] : '',
				'ship_address2' 	=> isset($Shipping['address2']) ? $Shipping['address2'] : '',
				'ship_city' 		=> isset($Shipping['city']) ? $Shipping['city'] : '',
				'ship_zip' 			=> isset($Shipping['zip']) ? $Shipping['zip'] : '',
				'ship_state' 		=> isset($Shipping['state']) ? $Shipping['state'] : '',
				'ship_country' 		=> isset($Shipping['country']) ? $Shipping['country'] : '',
				'ship_phone' 		=> isset($Shipping['phone']) ? $Shipping['phone'] : '',
				'bill_first_name' 	=> isset($Billing['first_name']) ? $Billing['first_name'] : '',
				'bill_last_name' 	=> isset($Billing['last_name']) ? $Billing['last_name'] : '',
				'bill_company' 		=> isset($Billing['company']) ? $Billing['company'] : '',
				'bill_email' 		=> isset($Billing['email']) ? $Billing['email'] : '',
				'bill_address1' 	=> isset($Billing['address1']) ? $Billing['address1'] : '',
				'bill_address2' 	=> isset($Billing['address2']) ? $Billing['address2'] : '',
				'bill_city' 		=> isset($Billing['city']) ? $Billing['city'] : '',
				'bill_zip' 			=> isset($Billing['zip']) ? $Billing['zip'] : '',
				'bill_state' 		=> isset($Billing['state']) ? $Billing['state'] : '',
				'bill_country' 		=> isset($Billing['country']) ? $Billing['country'] : '',
				'bill_phone' 		=> isset($Billing['phone']) ? $Billing['phone'] : '',
				'gift_from'				=> $gift_from,
				'gift_to'				=> $gift_to,
				'gift_message_customer'	=> $gift_message_customer,
			    'cust_current_credit_limit' => $cust_current_credit_limit,
			    'apply_credit'          => $apply_credit,
			    'remaining_credit'      => $remaining_credit,
			    'use_credit_limit'      => $use_credit_limit,
			    'is_dropship_order'     => $is_dropship_order,
			    'shipping_signature'	 => $OrdShipSignature,
			    'is_shipping_signature' => $ShippingSignatureFlag,
			    'Is_GiftCertificatPurchase' => $this->GetCartAttribute('CheckGCPurchasedVal'),
			    'EstimatedDeliveryDate' 	=> $EstimatedDeliveryDate,
			    'fullshipping_info'		=> 	$fullShippingname,
			    'merge_note'		=> 	$merge_note,
			    'bogo_discount'	=> $this->GetAllDiscounts('DogoDiscount'),
			    'is_maxtwoday'	=> $is_maxtwoday,
			    'route_shipping_insurance_charge' => $OrdShipInsurance,
                'vLang_flag' => Session::get('ShoppingCart.YotpoFreeGiftCoupon'),
                'paymentintentid' => $paymentintentid,
                'transaction_info' 		=>	Session::get("PayMethodRes")?Session::get("PayMethodRes"):'',
				'payment_gateway_response' => $order_response_details //Session::get("PayMethodRes")?Session::get("PayMethodRes"):''
		);

		if(isset($request->PaymentMethod) && $request->PaymentMethod=='PAYMENT_PAYPALEC'){
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")."Before PayPal Order Insert :".json_encode($OrderInsert)."\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$PlaceOrder = Order::create($OrderInsert);
		$OrderID = $PlaceOrder->orders_id;
		$aa = Session::put('ShoppingCart.OrderID',$OrderID); // set order id in cart

		if($OrderID != "")
		{

			if(isset($request->PaymentMethod) && $request->PaymentMethod=='PAYMENT_PAYPALEC'){
				$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');
					$stringData = date("m/d/Y H:i:s")." PayPal Order Id :".$OrderID."\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}
			}

			// To add 'OR' Change on :: 06-10-2015
			$CurrOrder = Order::find($OrderID);
			$updateOrder = array ('orders_no'	 => "OR".$OrderID );
			$udpRefer = $CurrOrder->update($updateOrder);
		}else {
			if(isset($request->PaymentMethod) && $request->PaymentMethod=='PAYMENT_PAYPALEC'){
				$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');
					$stringData = date("m/d/Y H:i:s")." PayPal Order Id is blank";
					fwrite($fh, $stringData);
					fclose($fh);
				}
			}
		}

		$tempCart = Session::get('ShoppingCart.Cart');
		$cnt_row  = count($tempCart);

		$IsVender = "No";
		$IsAmazOR = 'No';

		$IsPerfumePWVendor = "No";
		$TaxValueNew = $this->GetAllCharges('TaxValue');

				$couponCode = $this->GetAllCoupons('CouponCode');
			    $CouponDiscount = $this->GetAllDiscounts('CouponDiscount');

				$TotalTxShipping = 0;
				$couponPercentage = 0;
				if (isset($couponCode) && $couponCode != '' && $CouponDiscount > 0  && Session::has('ShoppingCart.CouponPercentage'))
				{
					$couponPercentage = session::get('ShoppingCart.CouponPercentage');

				}

				for($i=0; $i<$cnt_row; $i++)
				{
					$ItemWiseTaxVal = 0;
					if($TaxValueNew > 0)
					{
						$ItemWiseTaxVal = $this->ItemWiseTax($tempCart[$i]['TotPrice'],$i);
					}

					$allocatedDiscount  = 0;
					if (isset($couponCode) && $couponCode != '' && $CouponDiscount > 0 && Session::has('ShoppingCart.CountShipTax') &&  Session::get('ShoppingCart.CountShipTax')=='1' && Session::has('ShoppingCart.CouponPercentage'))
					{

					$ShippingChargeItemWise = ($this->GetShippingCharge() > 0  && $SubTotal > 0)
						? NumberFormat(($tempCart[$i]["TotPrice"] * $this->GetShippingCharge()) / $SubTotal)
						: 0;
					$ItemWiseTaxShipping = NumberFormat($ItemWiseTaxVal + $ShippingChargeItemWise);
					$allocatedDiscount      = $ItemWiseTaxShipping * ($couponPercentage / 100);

					$allocatedDiscount      = NumberFormat($allocatedDiscount);
					}
					$CouponItemWiseVal = 0;
					if(!empty($tempCart[$i]['CouponDisItemWiseDiscout']) && $tempCart[$i]['CouponDisItemWiseDiscout'] > 0 && !empty($couponPercentage) && $couponPercentage==100)
					{
						$allocatedDiscount = $allocatedDiscount."###".$tempCart[$i]['CouponDisItemWiseDiscout'];
					}

					$tempCart[$i]['TaxShippingItemWiseDiscount'] = $allocatedDiscount;

					if(empty($this->GetAllDiscounts('CouponDiscount')) || $this->GetAllDiscounts('CouponDiscount') <= 0)
					{
						$tempCart[$i]['CouponDisItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('AutoDiscount')) || $this->GetAllDiscounts('AutoDiscount') <= 0)
					{
						$tempCart[$i]['AutoItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('QuantityDiscount')) || $this->GetAllDiscounts('QuantityDiscount') <= 0)
					{
						$tempCart[$i]['QuantityItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('YotpoRewardDiscount')) || $this->GetAllDiscounts('YotpoRewardDiscount') <= 0)
					{
						$tempCart[$i]['RewardItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('DogoDiscount')) || $this->GetAllDiscounts('DogoDiscount') <= 0)
					{
						$tempCart[$i]['BogoItemWiseDiscout'] = 0;
					}
					if(empty($this->GetAllDiscounts('GiftCoupon')) || $this->GetAllDiscounts('GiftCoupon') <= 0)
					{
						$tempCart[$i]['GiftCertificateItemWiseDiscout'] = 0;
					}
					if(empty($apply_credit) && $apply_credit<=0)
					{
						$tempCart[$i]['CreditLimitItemWiseDiscout'] = 0;
					}

					$ActualPrice = 0;
					$CouponDisItemWiseDiscout = 0;
					$AutoItemWiseDiscout = 0;
					$QuantityItemWiseDiscout = 0;
					$RewardItemWiseDiscout = 0;
					$BogoItemWiseDiscout = 0;
					$GiftCertificateItemWiseDiscout = 0;
					$CreditLimitItemWiseDiscout = 0;

					if(!empty($tempCart[$i]['CouponDisItemWiseDiscout']) && $tempCart[$i]['CouponDisItemWiseDiscout'] > 0)
					{
						$CouponDisItemWiseDiscout = $tempCart[$i]['CouponDisItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['AutoItemWiseDiscout']) && $tempCart[$i]['AutoItemWiseDiscout'] > 0)
					{
						$AutoItemWiseDiscout = $tempCart[$i]['AutoItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['QuantityItemWiseDiscout']) && $tempCart[$i]['QuantityItemWiseDiscout'] > 0)
					{
						$QuantityItemWiseDiscout = $tempCart[$i]['QuantityItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['RewardItemWiseDiscout']) && $tempCart[$i]['RewardItemWiseDiscout'] > 0)
					{
						$RewardItemWiseDiscout = $tempCart[$i]['RewardItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['BogoItemWiseDiscout']) && $tempCart[$i]['BogoItemWiseDiscout'] > 0)
					{
						$BogoItemWiseDiscout = $tempCart[$i]['BogoItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['GiftCertificateItemWiseDiscout']) && $tempCart[$i]['GiftCertificateItemWiseDiscout'] > 0)
					{
						$GiftCertificateItemWiseDiscout = $tempCart[$i]['GiftCertificateItemWiseDiscout'];
					}
					if(!empty($tempCart[$i]['CreditLimitItemWiseDiscout']) && $tempCart[$i]['CreditLimitItemWiseDiscout'] > 0)
					{
						$CreditLimitItemWiseDiscout = $tempCart[$i]['CreditLimitItemWiseDiscout'];
					}

					$ActualPrice = $CouponDisItemWiseDiscout + $AutoItemWiseDiscout + $QuantityItemWiseDiscout + $RewardItemWiseDiscout + $BogoItemWiseDiscout+ $GiftCertificateItemWiseDiscout + $CreditLimitItemWiseDiscout;

					if(!empty($ActualPrice) && $ActualPrice > 0)
					{
						$ActualPrice = $tempCart[$i]['TotPrice'] - $ActualPrice;
						$ActualPrice = NumberFormat($ActualPrice);
					}
					else
					{
						$ActualPrice = 0;
					}

					//$TotalItemTaxAmount = $this->itemTaxAmount($ActualPrice,$tempCart[$i]['TotPrice']);

					if(!isset($tempCart[$i]['YotpoFreeGift']))
						$tempCart[$i]['YotpoFreeGift'] = '';

					$OrderDetailInsert = array (
						'orders_id'				=> $OrderID,
						'orders_no'				=> "OR".$OrderID, // To add 'OR' Change on :: 06-10-2015
						'products_id'			=> $tempCart[$i]['ProductID'],
						'sku' 					=> $tempCart[$i]['SKU'],
						'product_name'			=> $tempCart[$i]['ProductName'].'<br>'.$tempCart[$i]['short_description'],
						'quantity' 				=> $tempCart[$i]['Qty'],
						'price' 				=> $tempCart[$i]['Price'],
						'total' 				=> $tempCart[$i]['TotPrice'],
						'status' 				=> '1',
						'item_price' 			=> (isset($tempCart[$i]['ItemPrice']))?$tempCart[$i]['ItemPrice']:0,
						'excluded_flag'  		=> (isset($tempCart[$i]['FinalSale']))?$tempCart[$i]['FinalSale']:'',
						'is_gift_wrap'			=> (isset($tempCart[$i]['gift_wrap']))?$tempCart[$i]['gift_wrap']:'',
						'is_free_gift_products' => (isset($tempCart[$i]['IS_Free_Gift']))?$tempCart[$i]['IS_Free_Gift']:'No',
						'VendorSKU'				=> $tempCart[$i]['VendorSKU'],
						'IsCosmo'				=> $tempCart[$i]['IsCosmo'],
						'IsNandansons'  		=> $tempCart[$i]['IsNandansons'],
						'IsPerfumePW'			=> $tempCart[$i]['IsPerfumePW'],
						'IsPCA'					=> $tempCart[$i]['IsPCA'],
						'IsND'					=> $tempCart[$i]['IsND'],
						'coupon_itemwise_discount' => $tempCart[$i]['ItemWiseCouponDiscount'],
						'handling_time_str'		=> 	(isset($tempCart[$i]['HandlingTimeStr']))?$tempCart[$i]['HandlingTimeStr']:'',
						'attribute_info'        => (isset($tempCart[$i]['IsYotpoFreeProduct']))?$tempCart[$i]['IsYotpoFreeProduct']:'No',
						'actual_price'			=> $ActualPrice,
						'item_tax_amount'		=> $ItemWiseTaxVal,
						'sf_orderitemid'		=> $tempCart[$i]['TaxShippingItemWiseDiscount'] ?? 0
					);

					$OrdDetail = OrderDetail::create($OrderDetailInsert);
					$OrderDetailID = $OrdDetail->orders_detail_id;
					if(($tempCart[$i]['IsCosmo']=="Yes" || $tempCart[$i]['IsNandansons']=='Yes' || $tempCart[$i]['IsPerfumePW']=='Yes' || $tempCart[$i]['IsPCA']=="Yes" || $tempCart[$i]['IsND']=="Yes") && $tempCart[$i]['VendorSKU']!='' )
					{
						$IsVender = "Yes";
					}
					if($tempCart[$i]['IsPerfumePW']=='Yes' )
					{
						$IsPerfumePWVendor = "Yes";
					}

					## Insert purchased GC

					$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$tempCart[$i]);

					//if($tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
					if($IsGiftCertificateItem == 'Yes')
					{
						//$AddGC = $this->InsertGiftCertificateDB($tempCart[$i], $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
						$AddGC = $this->checkGiftCertificateItem('InsertGiftCertificateInDB', $tempCart[$i], 'Yes', $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
					}
				}

		  $myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." OrderPlace Insert ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

		$EstimateNewDate = '';

		if($onlyGCPurchased == 0)
		{
			$EstimateNewDate = $this->SetEstimateDate($ShippingInfo['ShippingMethodID'],$IsVender,$IsPerfumePWVendor,$Shipping['zip'],$Shipping['state'],$Shipping['country']);
			//echo $EstimateNewDate; exit;
		}
		$cur_date = date("Y-m-d");
		if($EstimateNewDate == "" && strtotime($EstimateNewDate) < strtotime($cur_date)){
			$EstimateNewDate = "0000-00-00 00:00:00";
		}

		$updArayVal = array ('EstimatedDeliveryDate' => $EstimateNewDate);
		$updOrder22 = $CurrOrder->update($updArayVal);

		if($OrderID!="" && $IsVender=="Yes")
		{
			$updateOrder1 = array('IsVender' => $IsVender);
			$udpRefer1 = $CurrOrder->update($updateOrder1);
		}
		if(isset($request->PaymentMethod) && $request->PaymentMethod=='PAYMENT_PAYPALEC')
		{
			return $OrderID;
		}
		else
		{
			return "OK";
		}
		//return "OK";
	}

	public function PlaceOrder_bk_30092024(Request $request)
	{
		if($this->GetNetTotal() <=0)
		{
			$GiftCoupon = $this->GetAllCoupons('GiftCoupon');
			$GCAmount = 0;
			$GCCode = "";
			$arrPaymentDetail = Session::get('ShoppingCart.Payment_Detail');
			if($GiftCoupon)
			{
				$GCAmount = $GiftCoupon['Value'];
				$GCCode = $GiftCoupon['Code'];
			}

			if(empty($GCCode) && $GCCode=='' && isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type']!='' && $arrPaymentDetail['Payment_Type']!='PAYMENT_CL')
			{
				return redirect('/shoppingcart');
			}
		}
		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." First Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$skrSKU= $this->OutOfStockItemsRemove();

		if(count($skrSKU) > 0)
		{
			Session::put('SKUListArrayVal',$skrSKU);
			$err_msg = "Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			Session::flash('PlaceOrderError',$err_msg);
			return redirect('/shoppingcart');
		}
		$IsGiftCertificateItem = '';

		if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
		{
			config(['app.debug' => true]);
		}
		if(config('global.SHOPP_STATUS') == 'Close')
		{
			Session::forget('ShoppingCart');
			return redirect('/');
		}
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
		{
			Session::forget('ShoppingCart');
			return redirect('/shoppingcart');
		}
		if($this->Is_WholeSaler_Allow() == false)
		{
			return redirect('/shoppingcart');
		}

		if(isset($request->is_stripe_wallet) && $request->is_stripe_wallet == "google_pay")
		{
			$request->PaymentMethod = 'STRIPE_GOOGLEPAY';
			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$request->PaymentMethod = 'STRIPE_APPLEPAY';
			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");
		}

		if(isset($request->is_btm_ap_chkout) && $request->is_btm_ap_chkout == "Yes"){	//bottom afterpay checkout
			$request->PaymentMethod = "PAYMENT_PAYWITHAFTERPAY";
		}

		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		$is_amazon = 0;
		if($this->GetNetTotal() <=0 && $CreditDiscount > 0 && config('Settings.WHOLESALE_CREDIT_LIMIT')=='Yes' && Session::get("etype") == "M" && Session::get("sess_icustomerid") > 0 && Session::get("is_dropshipper") !="Yes")
		{
			if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
				$is_amazon = 1;
			$request->PaymentMethod  = "PAYMENT_CL";
		}

		if($this->GetNetTotal() <= 0 && $CreditDiscount <= 0)
		{
			if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
				$is_amazon = 1;
			$request->PaymentMethod = 'PAYMENT_GIFT_CERTIFICATE';
			$log['Payment_Method'] = $request->PaymentMethod;
		}

		if($request->PaymentMethod != 'PAYMENT_PAYPALEC' && (!isset($request->is_paypal) || $request->is_paypal !="yes"))
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'PAYMENT_PAYPALEC' && isset($request->is_paypal) && $request->is_paypal =="yes")
		{
			$this->setPaymentDetail($request);
		}
		if($request->PaymentMethod == 'PAYMENT_PAYWITHAFTERPAY')
		{
			$this->setPaymentDetail($request);
		}
		if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'PAYMENT_STRIPE_BUTTON')
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'STRIPE_GOOGLEPAY')
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'STRIPE_APPLEPAY')
		{
			$this->setPaymentDetail($request);
		}

		if($request->PaymentMethod == 'PAYMENT_AUTHORIZENETCC' || $request->PaymentMethod == 'PAYMENT_PAYPALCC'  || $request->PaymentMethod == 'PAYMENT_BRAINTREECC')
		{
			if($request->CCType =='' || $request->CCNumber =='' ||  $request->CCMonth =='' || $request->CCYear == '' || $request->CCholdername =='')
			{
				$err_msg = "Error while processing your order, Please try again.";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError:'.$request->PaymentMethod.' - Credit Card issue.');
				return redirect()->back();
			}
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Second Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$customer_id = (int)Session::get('sess_icustomerid');

		if(!Session::has('sess_icustomerid') || empty($customer_id))
		{
			$err_msg = "Error while processing your order, Please try again.";
			Session::flash('PlaceOrderError',$err_msg);
			Log::info('PlaceOrderError: Customer Not Found');
			return redirect()->back();
		}

		$Billing  = Session::get('ShoppingCart.BillingAddress');

		if(($request->PaymentMethod != 'PAYMENT_STRIPE_BUTTON' && $request->PaymentMethod != 'STRIPE_GOOGLEPAY' && $request->PaymentMethod != 'STRIPE_APPLEPAY' && $request->PaymentMethod != 'PAYMENT_PAYWITHAMAZON' && $is_amazon !=1) && (trim($Billing['first_name'])== '' || trim($Billing['last_name'])== '' || trim($Billing['address1'])== '' || trim($Billing['city'])== '' ||
		   trim($Billing['zip'])== '' || trim($Billing['state'])== '' || trim($Billing['country'])== '' || trim($Billing['phone'])== '' ||
		   trim($Billing['email'])== ''))
		{
				$err_msg = "Please fill the required fields for billing information. ";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: BillingAddress Validation');
				return redirect()->back();
		}

		$Shipping = Session::get('ShoppingCart.ShippingAddress');
		$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');
		//$onlyGCPurchased = $request->onlyGCPurchased;
		/*if(!$onlyGCPurchased || $onlyGCPurchased == "")
			$onlyGCPurchased = 0;*/
		if($onlyGCPurchased == 0)
		{
			if($request->PaymentMethod != 'PAYMENT_PAYWITHAMAZON' && $request->PaymentMethod != 'STRIPE_GOOGLEPAY' && $request->PaymentMethod != 'STRIPE_APPLEPAY' && (trim($Shipping['first_name']) == '' || trim($Shipping['last_name']) == '' || trim($Shipping['address1']) == '' ||
			trim($Shipping['city']) == '' || trim($Shipping['zip'])== '' || trim($Shipping['state'])== '' || trim($Shipping['country'])== '' ))
			{
				$err_msg = "Please fill the required fields for shipping information. ";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: ShippingAddress Validation');
				return redirect()->back();
			}
		}

		if($onlyGCPurchased == 1)
		{
			Session::put('ShoppingCart.BillingAsShipping','Yes');
			$Billing['sameasbill'] = 'Yes';
			$this->SetShippingAddress($Billing);
			$Shipping = Session::get('ShoppingCart.ShippingAddress');
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Third Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$arrPaymentDetail = Session::get('ShoppingCart.Payment_Detail');
		if(Session::has('ShoppingCart.Payment_Detail'))
		{
			if((trim($arrPaymentDetail['Payment_Type']) == '' || $arrPaymentDetail['Payment_Type'] =='PAYMENT_PAYPALEC') && (!isset($request->is_paypal)))
			{
				$err_msg =  "Please choose payment method.";
				$log['err_msg'] = $err_msg;
				addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Payment Method Not Selected');
				return redirect()->back();
			}

			if(trim($arrPaymentDetail['Payment_Type']) == '' && isset($request->is_paypal) && $request->is_paypal == "yes")
			{
				$err_msg =  "Please choose payment method.";
				$log['err_msg'] = $err_msg;
				addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Payment Method Not Selected');
				return redirect()->back();
			}

			if($arrPaymentDetail['Payment_Type']=='PAYMENT_AUTHORIZENETCC' || $arrPaymentDetail['Payment_Type']=='PAYMENT_PAYPALCC' )
			{
				if($arrPaymentDetail['CCType']=='' || $arrPaymentDetail['CCNumber']=='' || $arrPaymentDetail['CCMonth']=='' || $arrPaymentDetail['CCYear']=='' || $arrPaymentDetail['CCName']=='')
				{
					$msg = "Please fill the credit card information.";
					Session::flash('PlaceOrderError',$msg);
					Log::info('PlaceOrderError: '.$arrPaymentDetail['Payment_Type'].' Credit Card issue.');
					return redirect()->back();
				}
			}
		}
		$ShippingInfo = Session::get('ShoppingCart.Shipping');
		$log['ShippingInfo'] = json_encode($ShippingInfo);
		addLog("PlaceOrder",$log);
		if($onlyGCPurchased == 0)
		{
			if(empty($ShippingInfo['ShippingMethodID']) || (int)$ShippingInfo['ShippingMethodID'] <=0 )
			{
				$err_msg =  "Please choose shipping method.";
				$log['err_msg'] = $err_msg;
				addLog("PlaceOrder",$log);
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Shipping Method Not Selected');
				return redirect()->back();
			}
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Fourth Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');
		$log['currency_info'] = $currency_info;
		if(Session::has('etype') && Session::get('etype') == 'M')
			$checkout_type = 'M';
		else
			$checkout_type = 'G';

		$cc_info ="";
		if(Session::has('ShoppingCart.Payment_Detail'))
		{
			if($arrPaymentDetail['Payment_Type'] == 'PAYMENT_AUTHORIZENETCC' || $arrPaymentDetail['Payment_Type'] =='PAYMENT_PAYPALCC')
			{
				$cc_info = $arrPaymentDetail['CCType'].",".substr(trim($arrPaymentDetail['CCNumber']),-4).",Exp.".$arrPaymentDetail['CCMonth']."/".$arrPaymentDetail['CCYear'].",CSC.".$arrPaymentDetail['CSC'];
			}
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Fifth Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$ShippingInfo	 = Session::get('ShoppingCart.Shipping');
		$GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
		$CouponCode = $this->GetAllCoupons('CouponCode');
		$couponRS = array();
		$OneCouponCode = "";
		$coupon_id = '';
		$SecondCouponCode = "";
		$Second_coupon_id = "";

		$yotporewardcode = '';

		if(Session::has('ShoppingCart.YotpoRewardCode') && Session::get('ShoppingCart.YotpoRewardCode') != '')
		{
			$yotporewardcode = Session::get('ShoppingCart.YotpoRewardCode');
		}
		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Sixth Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}
		if(!empty($CouponCode))
		{
			$couponRS = Coupon::where('coupon_number','=',$CouponCode)->limit(1)->get();
			$log['couponRS'] = json_encode($couponRS);
			if($couponRS && $couponRS->count() > 0)
			{
				$OneCouponCode = $couponRS[0]->coupon_number;
				$coupon_id = $couponRS[0]->coupon_id;
			}

			$Temp_Second_Coupon_Code =  $this->GetAllCoupons('SecondCouponCode');
			if($Temp_Second_Coupon_Code!='')
			{
				$couponRS = Coupon::where('coupon_number','=',$Temp_Second_Coupon_Code)->limit(1)->get();
				$log['secondcouponRS'] = json_encode($couponRS);

				if(count($couponRS) > 0)
				{
					$SecondCouponCode = $couponRS[0]->coupon_number;
					$Second_coupon_id = $couponRS[0]->coupon_id;
				}
				if($SecondCouponCode!='')
				{
					$OneCouponCode = $OneCouponCode."#". Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount').":".$SecondCouponCode."#".Session::get('ShoppingCart.PromoCoupon.SecondCouponDiscount');
				}
			}
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Sevanth Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		## Wholesaler Field Start ###
		$w_user_type = Session::get('eusertype');
		$w_ilevelid  = (Session::has('ilevelid'))?Session::get('ilevelid'):0;
		## Wholesaler Field End ###

		$GiftValue = $this->FreeGiftValue($this->GetNetTotal());
		$log['GiftValue'] = $GiftValue;
		if(count($GiftValue) > 0) {
			$free_gift = Session::get('ShoppingCart.FreeGift');
			$gift_from = Session::get('ShoppingCart.GiftFrom');
			$gift_to   = Session::get('ShoppingCart.GiftTo');
			$gift_message_customer = Session::get('ShoppingCart.GiftMessageCustomer');
		} else {
			$free_gift = '';
			$gift_from = '';
			$gift_to   = '';
			$gift_message_customer = (isset($request->gift_message_customer))?$request->gift_message_customer:'';
			if(strtolower(trim($gift_message_customer ?? '')) == " *gift message")
			{
				$gift_message_customer = '';
			}
			Session::forget('ShoppingCart.FreeGift');
			Session::forget('ShoppingCart.GiftFrom');
			Session::forget('ShoppingCart.GiftTo');
			Session::forget('ShoppingCart.GiftMessageCustomer');
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Eight Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$CreditData = $this->GetCreditLimitAmount();
		$CreditAmt = $CreditData['CreditLimit'];
		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		$cust_current_credit_limit = $CreditAmt;
		$apply_credit = $CreditDiscount;
		$remaining_credit = $CreditData['RemainCreditLimit'];

		if($CreditDiscount>0){
			$use_credit_limit = 'Yes';
		}else{
			$use_credit_limit = 'No';
		}
		//Credit Limit Code End

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_DS')
			$is_dropship_order = 'Yes';
		else
			$is_dropship_order = 'No';

		if(isset($request->customer_comment) && strtolower(trim($request->customer_comment)) == "special request")
		{
			$request->customer_comment = "";
		}

		$fullShippingname = '';
		$is_maxtwoday = "No";
		$EstimatedDeliveryDate = '';
		$ShipMethodName = '';
		$ShipMethodCharge = 0;
		$OrdShipSignature = 0;
		$OrdShipInsurance = 0;
		if($onlyGCPurchased == 0)
		{
			if($ShippingInfo["ShippingCharge"] > 0)
			{
				$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(".Session::get('currency_symbol').$ShippingInfo["ShippingCharge"].")</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
			}
			else
			{
				$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(Free)</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
			}
			if(strtolower($ShippingInfo['ShippingMethodName'] ?? '')=='max2days')
			{
				$is_maxtwoday = "Yes";
			}
			$EstimatedDeliveryDate = Session::get("ShoppingCart.EstimatedDeliveryDate");
			$ShipMethodName = $ShippingInfo['ShippingMethodName'];
			$ShipMethodCharge = $this->GetShippingCharge();
			$OrdShipSignature = $this->GetAllCharges('ShippingSignature');
			$OrdShipInsurance = $this->GetAllCharges('ShippingInsurance');
			$log['fullShippingname'] = $fullShippingname;
		}

		$cur_date = date("Y-m-d");
		if($EstimatedDeliveryDate == "" && strtotime($EstimatedDeliveryDate) < strtotime($cur_date)){
			$EstimatedDeliveryDate = "0000-00-00 00:00:00";
		}

		$referDiscountId = (Session::has('ShoppingCart.ReferDiscountId'))?Session::get('ShoppingCart.ReferDiscountId'):0;

		$merge_note = (Session::has('ShoppingCart.merge_note')) ? Session::get('ShoppingCart.merge_note') : "";
		$Payment_Type = (isset($arrPaymentDetail['Payment_Type']))?$arrPaymentDetail['Payment_Type']:'';
		$Payment_Method = (isset($arrPaymentDetail['Payment_Method']))?$arrPaymentDetail['Payment_Method']:'';

		$PaymentResponse = "";
		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == "PAYMENT_STRIPE_BUTTON")
		{
			$Payment_Type = "PAYMENT_STRIPE";
			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");
		}

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == "STRIPE_GOOGLEPAY")
		{
			$PaymentResponse = Session::get("PayMethodRes");
		}

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == "STRIPE_APPLEPAY")
		{
			$PaymentResponse = Session::get("PayMethodRes");
		}

		$customer_comment = (isset($request->customer_comment))?$request->customer_comment:'';
		$SubTotal = (float)Session::get('ShoppingCart.SubTotal');
		$GiftCoupon = $this->GetAllCoupons('GiftCoupon');
		$GCAmount = 0;
		$GCCode = "";
		if($GiftCoupon)
		{
			$GCAmount = $GiftCoupon['Value'];
			$GCCode = $GiftCoupon['Code'];
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Nine Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$paymentintentid = "";
		if(Session::get("StripePaymentType")=="Google Pay" && isset($request->is_stripe_wallet))
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_GOOGLEPAY";
			$paymentintentid = Session::has("ShoppingCart.apple_google_paymentintentid")?Session::get("ShoppingCart.apple_google_paymentintentid"):'';
		}

		if(Session::get("StripePaymentType")=="Apple Pay" && isset($request->is_stripe_wallet))
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_APPLEPAY";
			$paymentintentid = Session::has("ShoppingCart.apple_google_paymentintentid")?Session::get("ShoppingCart.apple_google_paymentintentid"):'';
		}
		/*if($request->is_stripe_wallet == "google_pay")
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_GOOGLEPAY";
		}

		if($request->is_stripe_wallet == "apple_pay")
		{
			$Payment_Method = Session::get("StripePaymentType");
			$Payment_Type = "STRIPE_APPLEPAY";
		}*/

		$ShippingSignatureFlag = 'No';
		if($onlyGCPurchased == 0 && isset($request->shipsignatureflag) && $request->shipsignatureflag == 'Yes')
			$ShippingSignatureFlag = 'Yes';

		if($onlyGCPurchased == 0 && isset($request->shipping_signature) && $request->shipping_signature == 'Yes')
			$ShippingSignatureFlag = 'Yes';

		$OrderInsert = array (
				'customer_id'		=> $customer_id,
				'sub_total' 		=> $SubTotal,
				'shipping_amt' 		=> $ShipMethodCharge,
				'tax' 				=> $this->GetAllCharges('Tax'),
				'gift_charge' 		=> $this->GetAllCharges('GiftWrappingCharge'),
				'gift_message' 		=> '',
				'is_gift_order'		=> 'No',
				'handling_charge' 	=> '0.00',
				'wire_discount' 	=> '0.00',
				'auto_discount' 	=> $this->GetAllDiscounts('AutoDiscount'),
				'quantity_discount'	=> $this->GetAllDiscounts('QuantityDiscount'),
				'reward_discount'	=> $this->GetAllDiscounts('YotpoRewardDiscount'),
				'coupon_amount' 	=> $this->GetAllDiscounts('CouponDiscount'),
				'coupon_id' 		=> $coupon_id,
				'Second_coupon_id'	=> $yotporewardcode,
				'coupon_code' 		=> $OneCouponCode,
				'gc_amount' 		=> $this->GetAllDiscounts('GiftCoupon'),
				'gc_code' 			=> $GCCode,
				'refer_id'			=> $referDiscountId,
				'refer_amount' 		=> $this->GetAllDiscounts('AutoReferDiscount'),
				'order_total' 		=> $this->GetNetTotal(),
				'shipinfo' 			=> $ShipMethodName,
				'payment_type' 		=> $Payment_Type,
				'payment_method' 	=> $Payment_Method,
				'pay_status' 		=> 'Unpaid',
				'ccinfo' 			=> $cc_info,
				'customer_comment' 	=> $customer_comment,
				'status'			=> 'Pending',
				'currency_info'		=> $currency_info,
				'checkout_type' 	=> $checkout_type,
				'user_type' 		=> $w_user_type,
				'ilevelid' 			=> $w_ilevelid,
				//'level_price' 		=> $w_level_price,
				'ship_first_name' 	=> $Shipping['first_name'],
				'ship_last_name' 	=> $Shipping['last_name'],
				'ship_company' 		=> $Shipping['company'],
				'ship_email' 		=> $Shipping['email'],
				'ship_address1' 	=> $Shipping['address1'],
				'ship_address2' 	=> $Shipping['address2'],
				'ship_city' 		=> $Shipping['city'],
				'ship_zip' 			=> $Shipping['zip'],
				'ship_state' 		=> $Shipping['state'],
				'ship_country' 		=> $Shipping['country'],
				'ship_phone' 		=> $Shipping['phone'],
				'bill_first_name' 	=> $Billing['first_name'],
				'bill_last_name' 	=> $Billing['last_name'],
				'bill_company' 		=> $Billing['company'],
				'bill_email' 		=> $Billing['email'],
				'bill_address1' 	=> $Billing['address1'],
				'bill_address2' 	=> $Billing['address2'],
				'bill_city' 		=> $Billing['city'],
				'bill_zip' 			=> $Billing['zip'],
				'bill_state' 		=> $Billing['state'],
				'bill_country' 		=> $Billing['country'],
				'bill_phone' 		=> $Billing['phone'],
				'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
				'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
				'is_only_gc'		=> (string)$onlyGCPurchased,
				'free_gift'			=> $free_gift,
				//======= Added Code Date 09/02/2015 Start Here ===//
				'gift_from'				=> $gift_from,
				'gift_to'				=> $gift_to,
				'gift_message_customer'	=> $gift_message_customer,
				//======= Added Code Date 09/02/2015 End Here ===//
											//Credit Limit Code Start
			   'cust_current_credit_limit' => $cust_current_credit_limit,
			   'apply_credit'          => $apply_credit,
			   'remaining_credit'      => $remaining_credit,
			   'use_credit_limit'      => $use_credit_limit,
											//Credit Limit Code End
			   'is_dropship_order'     => $is_dropship_order,
			   'shipping_signature'	 => $OrdShipSignature,
			   'is_shipping_signature' => $ShippingSignatureFlag,
			   'Is_GiftCertificatPurchase' => $this->GetCartAttribute('CheckGCPurchasedVal'),
			   'EstimatedDeliveryDate' 	=> $EstimatedDeliveryDate,
			   'fullshipping_info'		=> 	$fullShippingname,
			   'merge_note'		=> 	$merge_note,
			   'bogo_discount'	=> $this->GetAllDiscounts('DogoDiscount'),
			   'is_maxtwoday'	=> $is_maxtwoday,
			   'route_shipping_insurance_charge' => $OrdShipInsurance,
               'vLang_flag' => Session::get('ShoppingCart.YotpoFreeGiftCoupon'),
               'paymentintentid' => $paymentintentid,
			   'payment_gateway_response' => Session::get("PayMethodRes")?Session::get("PayMethodRes"):'',
			   'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):'',
		);
		//echo "<pre>"; print_r($OrderInsert); exit;
		$PlaceOrder = Order::create($OrderInsert);
		$OrderID = $PlaceOrder->orders_id;
		$aa = Session::put('ShoppingCart.OrderID',$OrderID); // set order id in cart
		if($OrderID != "")
		{
			// To add 'OR' Change on :: 06-10-2015
			$CurrOrder = Order::find($OrderID);
			$updateOrder = array ('orders_no'	 => "OR".$OrderID );
			$udpRefer = $CurrOrder->update($updateOrder);
		}

		$tempCart = Session::get('ShoppingCart.Cart');
		$cnt_row  = count($tempCart);

		$IsVender = "No";
		$IsAmazOR = 'No';
		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAMAZON')
		{
			$IsAmazOR = 'Yes';
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Ten Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$IsPerfumePWVendor = "No";

		//echo "<pre>"; print_r($tempCart); exit;
		for($i=0; $i<$cnt_row; $i++)
		{
			if(empty($this->GetAllDiscounts('CouponDiscount')) || $this->GetAllDiscounts('CouponDiscount') <= 0)
			{
				$tempCart[$i]['CouponDisItemWiseDiscout'] = 0;
			}
			if(empty($this->GetAllDiscounts('AutoDiscount')) || $this->GetAllDiscounts('AutoDiscount') <= 0)
			{
				$tempCart[$i]['AutoItemWiseDiscout'] = 0;
			}
			if(empty($this->GetAllDiscounts('QuantityDiscount')) || $this->GetAllDiscounts('QuantityDiscount') <= 0)
			{
				$tempCart[$i]['QuantityItemWiseDiscout'] = 0;
			}
			if(empty($this->GetAllDiscounts('YotpoRewardDiscount')) || $this->GetAllDiscounts('YotpoRewardDiscount') <= 0)
			{
				$tempCart[$i]['RewardItemWiseDiscout'] = 0;
			}
			if(empty($this->GetAllDiscounts('DogoDiscount')) || $this->GetAllDiscounts('DogoDiscount') <= 0)
			{
				$tempCart[$i]['BogoItemWiseDiscout'] = 0;
			}
			if(empty($this->GetAllDiscounts('GiftCoupon')) || $this->GetAllDiscounts('GiftCoupon') <= 0)
			{
				$tempCart[$i]['GiftCertificateItemWiseDiscout'] = 0;
			}
			if(empty($apply_credit) && $apply_credit<=0)
			{
				$tempCart[$i]['CreditLimitItemWiseDiscout'] = 0;
			}

			$ActualPrice = 0;
			$CouponDisItemWiseDiscout = 0;
			$AutoItemWiseDiscout = 0;
			$QuantityItemWiseDiscout = 0;
			$RewardItemWiseDiscout = 0;
			$BogoItemWiseDiscout = 0;
			$GiftCertificateItemWiseDiscout = 0;
			$CreditLimitItemWiseDiscout = 0;

			if(!empty($tempCart[$i]['CouponDisItemWiseDiscout']) && $tempCart[$i]['CouponDisItemWiseDiscout'] > 0)
			{
				$CouponDisItemWiseDiscout = $tempCart[$i]['CouponDisItemWiseDiscout'];
			}
			if(!empty($tempCart[$i]['AutoItemWiseDiscout']) && $tempCart[$i]['AutoItemWiseDiscout'] > 0)
			{
				$AutoItemWiseDiscout = $tempCart[$i]['AutoItemWiseDiscout'];
			}
			if(!empty($tempCart[$i]['QuantityItemWiseDiscout']) && $tempCart[$i]['QuantityItemWiseDiscout'] > 0)
			{
				$QuantityItemWiseDiscout = $tempCart[$i]['QuantityItemWiseDiscout'];
			}
			if(!empty($tempCart[$i]['RewardItemWiseDiscout']) && $tempCart[$i]['RewardItemWiseDiscout'] > 0)
			{
				$RewardItemWiseDiscout = $tempCart[$i]['RewardItemWiseDiscout'];
			}
			if(!empty($tempCart[$i]['BogoItemWiseDiscout']) && $tempCart[$i]['BogoItemWiseDiscout'] > 0)
			{
				$BogoItemWiseDiscout = $tempCart[$i]['BogoItemWiseDiscout'];
			}
			if(!empty($tempCart[$i]['GiftCertificateItemWiseDiscout']) && $tempCart[$i]['GiftCertificateItemWiseDiscout'] > 0)
			{
				$GiftCertificateItemWiseDiscout = $tempCart[$i]['GiftCertificateItemWiseDiscout'];
			}
			if(!empty($tempCart[$i]['CreditLimitItemWiseDiscout']) && $tempCart[$i]['CreditLimitItemWiseDiscout'] > 0)
			{
				$CreditLimitItemWiseDiscout = $tempCart[$i]['CreditLimitItemWiseDiscout'];
			}

			$ActualPrice = $CouponDisItemWiseDiscout + $AutoItemWiseDiscout + $QuantityItemWiseDiscout + $RewardItemWiseDiscout + $BogoItemWiseDiscout+ $GiftCertificateItemWiseDiscout + $CreditLimitItemWiseDiscout;

		     if(!empty($ActualPrice) && $ActualPrice > 0)
		     {
				 $ActualPrice = $tempCart[$i]['TotPrice'] - $ActualPrice;
				  $ActualPrice = NumberFormat($ActualPrice);
			 }
			 else
			 {
				 $ActualPrice = 0;
			 }

			// $TotalItemTaxAmount = $this->itemTaxAmount($ActualPrice,$tempCart[$i]['TotPrice']);

            if(!isset($tempCart[$i]['YotpoFreeGift']))
                $tempCart[$i]['YotpoFreeGift'] = '';
			$OrderDetailInsert = array (
				'orders_id'				=> $OrderID,
				'orders_no'				=> "OR".$OrderID, // To add 'OR' Change on :: 06-10-2015
				'products_id'			=> $tempCart[$i]['ProductID'],
				'sku' 					=> $tempCart[$i]['SKU'],
				'product_name'			=> $tempCart[$i]['ProductName'].'<br>'.$tempCart[$i]['short_description'],
				'quantity' 				=> $tempCart[$i]['Qty'],
				'price' 				=> $tempCart[$i]['Price'],
				'total' 				=> $tempCart[$i]['TotPrice'],
				'status' 				=> '1',
				'item_price' 			=> (isset($tempCart[$i]['ItemPrice']))?$tempCart[$i]['ItemPrice']:0,
				'excluded_flag'  		=> (isset($tempCart[$i]['FinalSale']))?$tempCart[$i]['FinalSale']:'',
				'is_gift_wrap'			=> (isset($tempCart[$i]['gift_wrap']))?$tempCart[$i]['gift_wrap']:'',
				'is_free_gift_products' => (isset($tempCart[$i]['IS_Free_Gift']))?$tempCart[$i]['IS_Free_Gift']:'No',
				'VendorSKU'				=> $tempCart[$i]['VendorSKU'],
				'IsCosmo'				=> $tempCart[$i]['IsCosmo'],
				'IsNandansons'  		=> $tempCart[$i]['IsNandansons'],
				'IsPerfumePW'			=> $tempCart[$i]['IsPerfumePW'],
				'IsPCA'					=> $tempCart[$i]['IsPCA'],
				'IsND'					=> $tempCart[$i]['IsND'],
				'coupon_itemwise_discount' => $tempCart[$i]['ItemWiseCouponDiscount'],
				'handling_time_str'		=> 	(isset($tempCart[$i]['HandlingTimeStr']))?$tempCart[$i]['HandlingTimeStr']:'',
                'attribute_info'        => (isset($tempCart[$i]['IsYotpoFreeProduct']))?$tempCart[$i]['IsYotpoFreeProduct']:'No',
                'actual_price'			=> $ActualPrice,
				'item_tax_amount'		=> $TotalItemTaxAmount
			);

			$OrdDetail = OrderDetail::create($OrderDetailInsert);
			$OrderDetailID = $OrdDetail->orders_detail_id;
			if(($tempCart[$i]['IsCosmo']=="Yes" || $tempCart[$i]['IsNandansons']=='Yes' || $tempCart[$i]['IsPerfumePW']=='Yes' || $tempCart[$i]['IsPCA']=="Yes" || $tempCart[$i]['IsND']=="Yes") && $tempCart[$i]['VendorSKU']!='' )
			{
				$IsVender = "Yes";
			}
			if($tempCart[$i]['IsPerfumePW']=='Yes' )
			{
				$IsPerfumePWVendor = "Yes";
			}

			## Insert purchased GC

			$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$tempCart[$i]);

			//if($tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
			if($IsGiftCertificateItem == 'Yes')
			{
				//$AddGC = $this->InsertGiftCertificateDB($tempCart[$i], $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
				$AddGC = $this->checkGiftCertificateItem('InsertGiftCertificateInDB', $tempCart[$i], 'Yes', $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
			}
		}

		if(isset($request->is_stripe_applepay) && $request->is_stripe_applepay == "apple_pay")
		{
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Elevan Place Order ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		}

		$EstimateNewDate = '';

		if($onlyGCPurchased == 0)
		{
			$EstimateNewDate = $this->SetEstimateDate($ShippingInfo['ShippingMethodID'],$IsVender,$IsPerfumePWVendor,$Shipping['zip'],$Shipping['state'],$Shipping['country']);
			//echo $EstimateNewDate; exit;
		}
		$cur_date = date("Y-m-d");
		if($EstimateNewDate == "" && strtotime($EstimateNewDate) < strtotime($cur_date)){
			$EstimateNewDate = "0000-00-00 00:00:00";
		}

		$updArayVal = array ('EstimatedDeliveryDate' => $EstimateNewDate);
		$updOrder22 = $CurrOrder->update($updArayVal);

		if($OrderID!="" && $IsVender=="Yes")
		{
			// To add 'OR' Change on :: 06-10-2015
			$updateOrder1 = array('IsVender' => $IsVender);
			$udpRefer1 = $CurrOrder->update($updateOrder1);
		}

		$customerEmail = '';
		if(isset($Billing['email']) && $Billing['email']!='')
		{
			//$customerEmail	= "naresh.qualdev@gmail.com";
			$customerEmail	= $Billing['email'];
		}

		$orderNo		= 'OR'.$OrderID;
		$customerName = '';
		if((isset($Billing['first_name']) && $Billing['first_name']!=''))
		{
			$customerName	= $Billing['first_name']." ";
		}

		if(isset($Billing['last_name']) && $Billing['last_name'])
		{
			$customerName = $customerName.$Billing['last_name'];
		}

		$gc_remaining_value = 0;
		if($GiftCouponInfo && count($GiftCouponInfo) > 0 && isset($GiftCouponInfo["Code"]) && $GiftCouponInfo["Code"]!='')
		{
			$GiftCouponInfo['Value'] = ($GiftCouponInfo['Value']!='')?$GiftCouponInfo['Value']:0.00;
			$new_total = $this->GetNetTotal() + $GiftCouponInfo['Value'];
			if($new_total <= $GiftCouponInfo['Applicable_Value'])
			{
				$gc_remaining_value = NumberFormat(($GiftCouponInfo['Applicable_Value']-$new_total));
			}

			if($GiftCouponInfo['Code'] != '' && $new_total <= $GiftCouponInfo['Applicable_Value'])
			{
				$str_info  = 'Gift Certificate discount value is greater than order total amount. \n\n';
				$str_info .= 'So net $'.$new_total.' is deduct from gift certifiacte value. \n\n';
				$str_info .= 'Used Gift Certificate code is ('.$GiftCouponInfo['Code'].')';

				$updAray = array ('pay_status' 	   => 'Paid','transaction_info' => $str_info);
				$updOrder = $CurrOrder->update($updAray);
				return redirect(config('global.SITE_URL')."order-receipt");
			}
		} else if($this->GetNetTotal() == 0){
			$updAray = array ('pay_status' => 'Paid');
			$updOrder = $CurrOrder->update($updAray);
			return redirect(config('global.SITE_URL')."order-receipt");
		}

		if($request->PaymentMethod == 'STRIPE_GOOGLEPAY')
		{
			$myFile = env('LOG_BASE_PATH').'Logs/Walmart/ApplePayLog.txt';

			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".json_encode($OrderInsert)." : In Shoppingcart Controller.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$StepValue = '';
            if(isset($request->is_step_gpay) && $request->is_step_gpay!='')
            {
				$StepValue = $request->is_step_gpay;
			}

			$updAray = array (
			'pay_status'   => 'Paid',
			'transaction_info' => serialize($PaymentResponse)." ".$StepValue
			);

		  $updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
		  return redirect(config('global.SITE_URL').'order-receipt');
		}

		if($request->PaymentMethod == 'STRIPE_APPLEPAY')
		{
			$myFile = env('LOG_BASE_PATH').'Logs/Walmart/ApplePayLog.txt';

			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".json_encode($OrderInsert)." : In Shoppingcart Controller.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$StepValue = '';
            if(isset($request->is_step_gpay) && $request->is_step_gpay!='')
            {
				$StepValue = $request->is_step_gpay;
			}

			$updAray = array (
			'pay_status'   => 'Paid',
			'transaction_info' => serialize($PaymentResponse)." ".$StepValue
			);

		  $updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
		  return redirect(config('global.SITE_URL').'order-receipt');
		}

		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type']=='PAYMENT_PAYPALEC') ## Paypal Express payment gateway condition
		{
			$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';

			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$OrderID." : Paypal DoPayment Function In Shoppingcart Controller.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

			if(isset($OrderID) && $OrderID > 0)
			{
				return redirect(config('global.SITE_URL').'paypal/dopayment/'.$OrderID);
			}
			else
			{
				return redirect(config('global.SITE_URL').'paypal/dopayment');
			}
		}
		elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYPALCC') ## Paypal Do direct payment gateway condition
		{
			//header("location:".$SECURED_PATH."paypal_checkout/paypal_dodirect_payment.php");
			//exit;
		}
		elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_STRIPE') ## Braintree payment gateway condition
		{
			// echo config('global.PHYSICAL_PATH');
			// exit;
			//$myFile = '/home/maxaroma/public_html/Logs/Walmart/StripeLog.txt';
			$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/StripeLog.txt';

			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$OrderID." : Stripe Payment In Shoppingcart Controller.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			return redirect(config('global.SITE_URL').'stripe/placeorder/'.$OrderID);
			exit;
		}
		elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_AUTHORIZENETCC') ## AUTHORIZE payment gateway condition
		{
			//header("location:".$SECURED_PATH."authorize_checkout/payment_authorize.php");
			//exit;
		}
		elseif(isset($arrPaymentDetail['Payment_Type']) && ($arrPaymentDetail['Payment_Type'] == 'PAYMENT_MOC' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_WT' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_CL' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_DS' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_GIFT_CERTIFICATE')) ## Other payment gateway condition
		{
			return redirect(config('global.SITE_URL').'order-receipt');
			exit;
		}else if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAFTERPAY') { ## Afterpay payment gateway condition
			if(isset($request->is_btm_ap_chkout) && $request->is_btm_ap_chkout == "Yes"){	//from the bottom express checkout button
				return redirect(config('global.SITE_URL').'afterpay/dopayment_express_btm/'.$OrderID.'~'.$customer_id);
				exit;
			}else{
				$ap_psChecksum = $request->ap_psChecksum;

				if(empty($ap_psChecksum) && $ap_psChecksum=='')
				{
					Session::flash('PlaceOrderError','Error in Processing Request. Please try again.');

					$transaction_info = "This transaction has been Declined.";
					$updAray = array (
										'status' 	   				=> 'Declined',
										'transaction_info' 			=> $transaction_info
									  );

					$uporderres = Order::Where("orders_id","=",$OrderID)
										->update($updAray);

					$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);

					/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);

					$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

					Session::forget('ShoppingCart.AfterPay.Checkout_Token');
					return redirect('checkout');
					exit;
				}
				else
				{
				return redirect(config('global.SITE_URL').'afterpay/success_express/'.$ap_psChecksum.'/'.$OrderID.'~'.$customer_id);
				exit;
				}
				//header("Location:" . $SECURED_PATH . "PayWithAfterpay/afterpay_checkout.php");
				//exit();
			}
		}elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAMAZON'){
			return redirect(config('global.SITE_URL').'amazon/placeorder');
			exit;
		}elseif(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_STRIPE_BUTTON'){
			return redirect(config('global.SITE_URL').'order-receipt');
			exit;
		}
		#### Here check payment type and do processing end ####
		if($OrderID > 0)
		{
			$updArayVAL = array ('pay_status' => 'Unpaid', 'status' => 'Declined');
			$uporderres11 = $CurrOrder->update($updArayVAL);

			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);

			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);

			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
		}
		return redirect('/');
	}

	public function ItemWiseTax($TotalNewPrice,$a=0)
	{
		$Subtotal = Session::get('ShoppingCart.SubTotal');
		$TaxValue = $this->GetAllCharges('TaxValue');
		$TaxItemWise =0;
		if(!empty($TaxValue))
		{
			$TaxItemWise = (($TotalNewPrice * $TaxValue) / $Subtotal);
		}
		return $TaxItemWise;

	}

	public function OrderReceipt(Request $request)
	{
		$isPayPalProduct = 'N';
		$OrderID = 0;
		$setOrderStr = "";

		// if(isset($_GET['order']) && $_GET['order']!=''){
		// 	$OrderID = base64_decode($_GET['order']);
		// 	$isPayPalProduct = 'Y';
		// }

		if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID')!='' && !empty(Session::get('ShoppingCart.OrderID')))
		{
			$OrderID = Session::get('ShoppingCart.OrderID');
		}
		else if(isset($_GET['order']) && $_GET['order']!=''){
			$OrderID = base64_decode($_GET['order']);
			$isPayPalProduct = 'Y';
		} else {
			$OrderID = "";
		}

		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];
		//$this->PageData['JSFILES'] = ['billing.js','login.js','login_validate.js'];
		$this->PageData['JSFILES'] = ['bootstrap.min.js','jquery.mobile-1.0rc2.min.js','StarWebPrintBuilder.js','StarBarcodeEncoder.js','StarWebPrintExtManager.js','StarWebPrintDisplayBuilder.js','StarWebPrintTrader.js','pos_cashdrawer.js','order_receipt.js'];
		$wholesale_terms = "";
		$chkPaymentType = "";
		$chkPaidStatus = "";

		$IsGiftCertificateItem = $IsGiftCertificateItem1 = '';
		$hasCashPayment = 0;

		$order_receipt_image_path = config('global.SITE_URL').'images/';
		$order_receipt_image = generalsetting('ORDER_RECEIPT_IMAGE',1);
		$order_receipt_image = $order_receipt_image_path.$order_receipt_image;
		$this->PageData['order_receipt_image'] = $order_receipt_image;

		if(Session::get('eusertype') == 'Wholesaler')
			$wholesale_terms = "<br><strong style='font-size:14px'>Read our MaxAroma Wholesale <a class='viewlink' data-target='#myModalPopUp' data-toggle='modal' href='javascript:void(0);' onclick='DisplayWholesalerTerms();' class='normallink'>Policy</a></strong>";
		$this->PageData['wholesale_terms'] = $wholesale_terms;

		$IS_WEB_SITE_ORDER 	= 'Yes';
		$IS_GOOGLE_ORDER 	= 'No';
		$IS_AMAZON_ORDER    = 'No';

		$Template = 'order-receipt';

		$merchant_order_no = (isset($request->merchant_order_no)?$request->merchant_order_no:'');
		$amazon_order_no = (isset($request->amznPmtsOrderIds)?$request->amznPmtsOrderIds:'');

		//$amazon_order_no = '104-5474895-5200227';

		if(trim($merchant_order_no) !='')
		{
			$IS_WEB_SITE_ORDER 	= 'No';
			$IS_GOOGLE_ORDER    = 'Yes';
		}

		if(trim($amazon_order_no) !='')
		{
			$IS_WEB_SITE_ORDER 	= 'No';
			$IS_AMAZON_ORDER    = 'Yes';
			$Template = 'order-receipt-amazon';
		}
		$this->PageData['IS_WEB_SITE_ORDER'] = $IS_WEB_SITE_ORDER;
		$this->PageData['IS_GOOGLE_ORDER'] = $IS_GOOGLE_ORDER;
		$this->PageData['IS_AMAZON_ORDER'] = $IS_AMAZON_ORDER;

		if(Session::has('ShoppingCart.Payment_Detail'))
		{

		$arrPaymentDetail11 = (Session::has('ShoppingCart.Payment_Detail'))?Session::get('ShoppingCart.Payment_Detail'):array();
		if(isset($arrPaymentDetail11) && isset($arrPaymentDetail11["Payment_Type"])){
			$arrPaymentDetail11["Payment_Type"] = $arrPaymentDetail11["Payment_Type"];
		}else{
			$arrPaymentDetail11["Payment_Type"] = '';
		}

		if (isset($arrPaymentDetail11) && isset($arrPaymentDetail11["Payment_Type"])) {
			if ($arrPaymentDetail11["Payment_Type"] == 'PAYMENT_CASH' || $arrPaymentDetail11["Payment_Type"] == 'PAYMENT_SPLIT') {
				$hasCashPayment = 1;
			}
		}

		$this->PageData['hasCashPayment'] = $hasCashPayment;
		$this->PageData['orderCashAmount'] = $arrPaymentDetail11["orderCashAmount"];

		//$arrPaymentDetail11 = Session::get('ShoppingCart.Payment_Detail');

		$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');

		$GAShipping = "";
		$GAPayment 	=  "";
		if(isset($arrPaymentDetail11) && isset($arrPaymentDetail11["Payment_Type"]) && ($arrPaymentDetail11["Payment_Type"]=="PAYMENT_PAYWITHAFTERPAY" || $arrPaymentDetail11["Payment_Type"]=="STRIPE_GOOGLEPAY") || $arrPaymentDetail11["Payment_Type"]=="STRIPE_APPLEPAY" || $arrPaymentDetail11["Payment_Type"]=="PAYMENT_CL" )
		{

			if(isset($arrPaymentDetail11) && $arrPaymentDetail11["Payment_Type"]=="STRIPE_GOOGLEPAY")
			{
				if($onlyGCPurchased==0)
				{
					$GAShipping = googleAnalyticsGA4("ShippingMethods",Session::get('ShoppingCart.Cart'),$this->GetNetTotal(), $this->GetAllCoupons('CouponCode'));
				}
				$GAPayment 	= googleAnalyticsGA4("PaymentMethods",Session::get('ShoppingCart.Cart'), $this->GetNetTotal(),$this->GetAllCoupons('CouponCode'),'','Google Pay');

			}
			else if(isset($arrPaymentDetail11) && $arrPaymentDetail11["Payment_Type"]=="STRIPE_APPLEPAY")
			{
				if($onlyGCPurchased==0)
				{
					$GAShipping = googleAnalyticsGA4("ShippingMethods",Session::get('ShoppingCart.Cart'),$this->GetNetTotal(), $this->GetAllCoupons('CouponCode'));
				}
				$GAPayment 	= googleAnalyticsGA4("PaymentMethods",Session::get('ShoppingCart.Cart'), $this->GetNetTotal(),$this->GetAllCoupons('CouponCode'),'','Apple Pay');
			}
			else
			{
				if(isset($arrPaymentDetail11["Payment_Method"]) && $arrPaymentDetail11["Payment_Method"]!='')
				{
					$GAPayment 	= googleAnalyticsGA4("PaymentMethods",Session::get('ShoppingCart.Cart'), $this->GetNetTotal(),$this->GetAllCoupons('CouponCode'),'',$arrPaymentDetail11["Payment_Method"]);

				}
			}

			$this->PageData['GAShipping']  = $GAShipping;
			$this->PageData['GAPayment']  = $GAPayment;
			}

		}

		$topmenubar = '<a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'fragrances/cid/1" target="_blank">Fragrances</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'skincare/cid/18" target="_blank">Skincare</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'pocket-perfume/cid/68" target="_blank">Pocket Perfume</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'bath-body/cid/12" target="_blank">Bath &amp; Body</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'candles/cid/208" target="_blank">Candles</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#ff0000; text-decoration:none;" href="'.config('global.SITE_URL').'offers.html" target="_blank">SALES & OFFERS</a>
						   ';

		$CustomerID = Session::get('sess_icustomerid');

        //if($OrderID == 0 &&	($CustomerID == null || empty($CustomerID)))
		if(($OrderID == 0 || empty($OrderID)) && ($CustomerID == null || empty($CustomerID)))
        {
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Order Receipt OrderID is zero and customer Id not found :\n";
				if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
					$stringData .= date("m/d/Y H:i:s")." Session Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
				} else {
					$stringData .= date("m/d/Y H:i:s")." Session Data is blank :\n";
				}
				if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid')!=''){
					$stringData .= date("m/d/Y H:i:s")." User Id is ".Session::get('sess_icustomerid')." :\n";
				}
				if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
					$stringData .= date("m/d/Y H:i:s")." User Email is ".Session::get('sess_useremail')." :\n";
				}
				if(Session::getId() != ''){
					$stringData .= date("m/d/Y H:i:s")." Session Id is ".Session::getId()." :\n";
				}
				fwrite($fh, $stringData);
				fclose($fh);
			}
            return redirect(config('global.SITE_URL'));
            exit;
        }
		//if($OrderID == 0){
		if($OrderID == 0 || empty($OrderID)){
        	$OrderID = Session::get('ShoppingCart.OrderID');
		}

        //$OrderID = '77799';
        if($OrderID == '' || empty($OrderID))
        {
			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Order Receipt OrderID not found :\n";
				if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
					$stringData .= date("m/d/Y H:i:s")." Session Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
				} else {
					$stringData .= date("m/d/Y H:i:s")." Session Data is blank :\n";
				}
				if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid')!=''){
					$stringData .= date("m/d/Y H:i:s")." User Id is ".Session::get('sess_icustomerid')." :\n";
				}
				if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
					$stringData .= date("m/d/Y H:i:s")." User Email is ".Session::get('sess_useremail')." :\n";
				}
				if(Session::getId() != ''){
					$stringData .= date("m/d/Y H:i:s")." Session Id is ".Session::getId()." :\n";
				}
				fwrite($fh, $stringData);
				fclose($fh);
			}
            return redirect(config('global.SITE_URL'));
            exit;
        }

        Log::channel('order_receipt')->info('Order Receipt Started for Order - '.$OrderID);

        //$OrderRs = Order::where('orders_id','=',$OrderID)->where('customer_id','=',$CustomerID)->get();

		if($isPayPalProduct == 'Y'){
			$OrderRs = Order::where('orders_id','=',$OrderID)->get();
			$CustomerID = $OrderRs[0]['customer_id'];
		} else {
			//$OrderRs = Order::where('orders_id','=',$OrderID)->where('customer_id','=',$CustomerID)->get();
			$OrderRs = Order::where('orders_id','=',$OrderID)->get();
		}
		$value = $OrderRs[0]['is_skip_addresspart'] ?? '';
		$this->PageData['SkipAddressPart'] = $value !== '' ? explode('###', $value)[0] : '';
		if(empty($this->PageData['SkipAddressPart']))
		{
			$this->PageData['SkipAddressPart'] = $value !== '' ? explode('###', $value)[1] : '';
		}

		Session::forget('BillingSkipVariable');
		Session::forget('BillingSkipEmail');

		if($hasCashPayment == 1){
			$newrequest = new \Illuminate\Http\Request();
			$newrequest->setMethod('POST');

			$newrequest11 = new \Illuminate\Http\Request();
			$newrequest11->setMethod('POST');

			//$setOrderStr = "OR".$OrderRs[0]['orders_id']."(Store)";

			//if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID')!='' && !empty(Session::get('ShoppingCart.OrderID')))
			//{
				if(isset($OrderRs[0]['order_type']) && $OrderRs[0]['order_type'] == "Store"){
					$setOrderStr = $OrderRs[0]['orders_no'] . " (Store)";
				}
				else{
					$setOrderStr = $OrderRs[0]['orders_no'] . " (Online)";
				}
			//}

			$getDailyOpenForDay = DB::table('pu_store_cash_drawer')->select('*')->where('store_id', Auth::guard('store')->user()->store_id)->where(DB::raw("(DATE_FORMAT(added_datetime,'%m-%d-%Y'))"), date("m-d-Y"))->orderBy('added_datetime', 'desc')->first();

			$action_notes = array(
				config('global.STORE_LOGS_ORDER_ID') => ($setOrderStr ?? ''),
				config('global.STORE_LOGS_PREVIOUS_AMOUNT') => $getDailyOpenForDay->current_balance,
				config('global.STORE_LOGS_ORDER_AMOUNT') => ($OrderRs[0]['order_total'] ?? 0.00),
				config('global.STORE_LOGS_CD_AMOUNT') => (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0),
				config('global.STORE_LOGS_UPDATED_AMOUNT') => ((float)$getDailyOpenForDay->current_balance + (float)(Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0)),
				config('global.STORE_LOGS_ADJUSTMENT_TYPE') => 'increase',
			);

			//$newrequest->request->add(['action' => 'Order', 'action_type' => 'Open', 'actionNotes' => '{"orderId":' . ($OrderRs[0]['orders_id'] ?? 0) . ', "orderAmount":' . ($OrderRs[0]['order_total'] ?? 0.00) . ', "cd_amount":' . (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0) . '}', 'cd_amount' => (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0), 'is_device_event' => 0]);

			//$newrequest->request->add(['action' => 'Order', 'action_type' => 'Open', 'actionNotes' => '{"orderId":"' . ($setOrderStr ?? "") . '", "orderAmount":' . ($OrderRs[0]['order_total'] ?? 0.00) . ', "cd_amount":' . (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0) . '}', 'cd_amount' => (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0), 'is_device_event' => 0]);
			$newrequest->request->add(['action' => 'Order', 'action_type' => 'Open', 'actionNotes' => json_encode($action_notes), 'cd_amount' => (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0), 'is_device_event' => 0]);

			//$newrequest11->request->add(['action' => 'Order', 'action_type' => 'Close', 'actionNotes' => '{"orderId":"' . ($setOrderStr ?? "") . '", "orderAmount":' . ($OrderRs[0]['order_total'] ?? 0.00) . ', "cd_amount":' . (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0) . '}', 'cd_amount' => (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0), 'is_device_event' => 0]);
			$newrequest11->request->add(['action' => 'Order', 'action_type' => 'Close', 'actionNotes' => json_encode($action_notes), 'cd_amount' => (Session::get('ShoppingCart.Payment_Detail.orderCashAmount') ?? 0), 'is_device_event' => 0]);
			//dd(Session::get('ShoppingCart'));
			//dd($newrequest);

			$posController = new POSController();
			$posController->cashDrawerLog($newrequest);
			$posController->cashDrawerLog($newrequest11);
		}

        if(isset($OrderRs[0]["Is_GiftCertificatPurchase"]) && $OrderRs[0]["Is_GiftCertificatPurchase"]=='1')
        {
            $mailTemplate = GetMailTemplate("GC_SEND_CODE");
        }

        $OrderDetailRs = OrderDetail::where('orders_id','=',$OrderID)->orderBy('orders_detail_id')->get();
		$this->CJCall($OrderRs[0],$OrderDetailRs);
        $GOOGLE_ORDER_TRACKING = '';
        $GOOGLE_ORDER_TRACKING_GTM = '';
        $gtm_purchase_prod_str = "";

        $RIO_EBAY_COMMERCE_CODE  = '';
        $RIO_EBAY_COMMERCE_CODE  .= "var _roi = _roi || [];
        _roi.push(['_setMerchantId', '532042']);
        _roi.push(['_setOrderId', '".$OrderRs[0]['orders_no']."']);
        _roi.push(['_setOrderAmount', '".$OrderRs[0]['sub_total']."']);
        _roi.push(['_setOrderNotes',  '".$OrderRs[0]['customer_comment']."']);";

        $trusted_code = '';
        $Bizrate_POS_Code = "var orderId  ='".$OrderRs[0]["orders_no"]."';
                             var cartTotal='".$OrderRs[0]['order_total']."';
                             var billingZipCode='".$OrderRs[0]["bill_zip"]."'; ";

        $Bizrate_POS_Code_NEW = "";
        $Bizrate_POS_Code_NEW.= "var productsPurchased='";

        $freeshippinginfo = '';
        if(config('Settings.FREESHIPPING_VALUE') !="" && config('Settings.FREESHIPPING_VALUE') > 0)
        {
            $freeshippinginfo.= '<p class="siteoffer" style="margin:0;padding:5px 0px; font-size:16px; font-weight:normal; font-family:Arial,  sans-serif; color:#666666;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</p>';
        }

        $critieostr = '';
        $ProdDetails = [];
        $roktstr = '';
        $couponCodeNo = "";
        $GA4_purchase_prod_str = "";
        $FBContent_arr =  array();
        if($OrderRs[0]['coupon_code']!='')
        {
			 $couponCodeNo .="coupon: '".$OrderRs[0]['coupon_code']."',";
		}
		if (isset($OrderRs[0]['order_type']) && $OrderRs[0]['order_type'] == "Store") {
			$posController = app()->make(POSController::class);
			$OrderArrValT = $OrderRs[0];
			$posController->skuvault($OrderRs[0]->toArray(),$OrderDetailRs->toArray());
		}

		$Content_id_str = "";
		$Content_id_Brcket_str = "";
        for($p=0;$p<$OrderDetailRs->count();$p++)
        {
             if($OrderRs[0]["payment_type"]!="PAYMENT_STRIPE" && ($OrderRs[0]["pay_status"]=="Paid" || $OrderRs[0]["payment_type"]=="PAYMENT_DS"))
				{
					$GiftCardRes = GiftCertificate::where('status','=','0')->where('orders_detail_id','=',$OrderDetailRs[$p]["orders_detail_id"])->get();

					if($GiftCardRes && $GiftCardRes->count() > 0)
					{
						$CurrentDate =  date("Y-m-d");

						if($GiftCardRes[0]['deliverydate'] == $CurrentDate)
						{
						$updGiftAray = array ('status' => '1');
						$uporderresgift = GiftCertificate::where('gc_id','=',$GiftCardRes[0]['gc_id'])->update($updGiftAray);
						}

					}

				}
            $critieostr .=  '{ id: "'.$OrderDetailRs[$p]['sku'].'", price: "'.$OrderDetailRs[$p]['price'].'", quantity: "'.$OrderDetailRs[$p]['quantity'].'" } ,';
            $roktstr.= "{sku:'".$OrderDetailRs[$p]['sku']."',quantity:'".$OrderDetailRs[$p]['quantity']."',productname:'".addslashes($OrderDetailRs[$p]['product_name'])."',price:'".$OrderDetailRs[$p]['price']."',majorcat:'',minorcat:'',currency:'USD'},";
            $order_items_pixel[] = array(
                                    'id'=> $OrderDetailRs[$p]["sku"],
                                    'quantity' => $OrderDetailRs[$p]["quantity"],
                                    'price' => $OrderDetailRs[$p]["price"]
                                    );

            $giftcertificateItem = "No";

            $IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$OrderDetailRs[$p],'No');

           // if($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU') || $OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU1'))
            if($IsGiftCertificateItem == 'Yes')
            {
                $GC_IMAGE_URL = "";
                /*if($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU'))
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL');
                else if($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU1'))
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL1');
                if($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU2'))
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL2');*/

				$GC_IMAGE_URL = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$OrderDetailRs[$p],'No');

                $GCRs = GiftCertificate::where('orders_detail_id','=',$OrderDetailRs[$p]['orders_detail_id'])->where('customer_id','=',$CustomerID)->get();
                if($GCRs && $GCRs->count($GCRs) > 0)
                {
                    $OrderDetailRs[$p]['RecipientName']  = $GCRs[0]['recipient_name'];
                    $OrderDetailRs[$p]['RecipientEmail'] = $GCRs[0]['recipient_email'];
                    $OrderDetailRs[$p]['SenderName']  	 = $GCRs[0]['your_name'];
                    $OrderDetailRs[$p]['SenderEmail'] 	 = $GCRs[0]['your_email'];
                    $OrderDetailRs[$p]['Image']			 = $GC_IMAGE_URL;
                    $OrderDetailRs[$p]['DeliveryDate']	 = $GCRs[0]['deliverydate'];
                    $giftcertificateItem = "Yes";
                    $GA4_purchase_prod_str .= "{
                                    item_id : '".$OrderDetailRs[$p]["sku"]."',
                                    item_name : '".stripcslashes($OrderDetailRs[$p]["product_name"])."',
                                    affiliation : 'Google Merchandise Store',";
					$GA4_purchase_prod_str .= "
                                     index: ".$p.",
                                     item_list_id: 'related_products',
									 item_list_name: 'Related Products',
									 price: ".$OrderDetailRs[$p]['price'].",
									 quantity: ".$OrderDetailRs[$p]["quantity"]."
                                   },";

                }
            }else{
                $FreeGiftSku = '';
				$FreeSampleSKU = '';
                if($OrderDetailRs[$p]["is_free_gift_products"] == "Yes")
                {
                    $FreeGiftSku = 	$OrderDetailRs[$p]['sku'];
                    $OrderDetailRs[$p]['sku'] = str_replace("GIFT-","",$OrderDetailRs[$p]['sku']);
                } else if (str_contains($OrderDetailRs[$p]['sku'], "SAMPLE-")) {
					$FreeSampleSKU = 	$OrderDetailRs[$p]['sku'];
                    $OrderDetailRs[$p]['sku'] = str_replace("SAMPLE-","",$OrderDetailRs[$p]['sku']);
				}
                $prod_res = DB::table('pu_products as p')
                            ->join('pu_products_category as pc','p.products_id','=','pc.products_id')
                            ->select('p.products_id','p.UPC','p.image','p.product_name','p.vtype','p.short_description','p.product_description','p.sku','p.current_stock','p.cosmo_current_stock','p.nandansons_current_stock','p.perfumeworldwide_currentstock','p.pca_current_stock','p.nd_current_stock','pc.category_id')
                            ->where(DB::raw('LOWER(TRIM(sku))'),'=',strtolower(trim($OrderDetailRs[$p]['sku'])))
                            ->limit(1)->get();

                  if($IsGiftCertificateItem == 'Yes'){
					  $thumb_image = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$OrderDetailRs[$p],'No');
				  }
				  else{
					if(file_exists(config('global.PRD_LARGE_IMG_PATH').$prod_res[0]->image) && !empty($prod_res[0]->image)){
						$thumb_image = config('global.PRD_LARGE_IMG_URL').$prod_res[0]->image;
					}
					else{
						$thumb_image = config('global.NO_IMAGE_LARGE');
					}
				}

                $OrderDetailRs[$p]['Image'] =$thumb_image;

                $vlink_name = SetProductURL($prod_res[0]->products_id,$prod_res[0]->product_name,$prod_res[0]->category_id);
                $OrderDetailRs[$p]['ProdLink'] = $vlink_name;
                 if($p < 5)
                 {
                     $Bizrate_POS_Code_NEW.= "".$vlink_name."=^".addslashes($prod_res[0]->sku)."=^".addslashes($prod_res[0]->UPC)."=^".$OrderDetailRs[$p]['total']."=|";
                 }
            }
            $GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');

			if($giftcertificateItem == "Yes" && $GCRs->count() > 0 && $OrderDetailRs[$p]['DeliveryDate'] == date("Y-m-d") && ($OrderRs[0]["pay_status"]=="Paid" || $OrderRs[0]["payment_type"]=="PAYMENT_DS" ) && $OrderRs[0]["payment_type"]!="PAYMENT_STRIPE")
            {

                $message_back = $mailTemplate[0]['mail_body'];
                $subject_back = $mailTemplate[0]['subject'];
                $subject_back = str_replace('{$SITE_NAME}', config('Settings.SITE_TITLE'),$subject_back);
                $subject_back = str_replace('{$recipient_name}', $GCRs[0]['recipient_name'],$subject_back);
                $subject_back = str_replace('{$sender_name}', $GCRs[0]['your_name'],$subject_back);

                $this->PageData["CONTACT_MAIL"] = config('Settings.CONTACT_MAIL');
                $this->PageData["topmenubar"] = $topmenubar;
                $this->PageData["freeshippinginfo"] = $freeshippinginfo;
                $this->PageData["recipient_name"] = $GCRs[0]['recipient_name'];
                $this->PageData["sender_name"] = $GCRs[0]['your_name'];
                $this->PageData["SITE_NAME"] = config('global.SITE_TITLE');
                $this->PageData["Site_URL"] = config('global.SITE_URL');
                $this->PageData["remaining_value"] = $GCRs[0]["remaining_value"];
                $this->PageData["gc_code"] = $GCRs[0]["gc_code"];
                $this->PageData["freeshippinginfo"] = $freeshippinginfo;
                $this->PageData["message"] = $GCRs[0]["messae"];
                $this->PageData["TOLL_FREE_NO"]= config('global.TOLL_FREE_NO');
                $this->PageData["GiftCard"] = $GCRs[0]['giftimage'];

				$GCRs[0]["gc_card"] = $GC_IMAGE_URL;
				$this->PageData["gc_card"] = $GCRs[0]["gc_card"];

                $message_back = str_replace('{$freeshippinginfo}',$freeshippinginfo,$message_back);
                $message_back = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$message_back);
                $message_back = str_replace('{$recipient_name}',$GCRs[0]['recipient_name'],$message_back);
                $message_back = str_replace('{$remaining_value}',$GCRs[0]['remaining_value'],$message_back);
                $message_back = str_replace('{$sender_name}',$GCRs[0]['your_name'],$message_back);
                $message_back = str_replace('{$gc_code}',$GCRs[0]["gc_code"],$message_back);
                $message_back = str_replace('{$gc_card}',$GCRs[0]["gc_card"],$message_back);
                if(isset($GiftCouponInfo['Value']))
                    $message_back = str_replace('{$gc_amount}',$GiftCouponInfo['Value'],$message_back);
                $message_back = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_PHONE_NO'),$message_back);

                $vtoemail   = $GCRs[0]["recipient_email"];
                $vfromemail = $GCRs[0]["your_email"];

                if(config('global.OMNISEND_PROG') == false)
                {
                    SendMail($subject_back,$message_back,$vtoemail,$vfromemail);
                } else {
                    /** OMANISEND **/
                    //OmanisendRequest('6209ffc44fa101001e950228',$GCRs[0]);
                    OmanisendRequest('New_Gift_Cards_Email',$GCRs[0]);
                    /** OMANISEND **/
                }

                //$cust_res 	= Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
				$cust_res 	= Customer::where('customer_id','=',$CustomerID)->get();

                $reward_point = 0;
                if(config('global.YOTPO_PROG') == false)
                {
                    $reward_point = $cust_res[0]['iRewardpoint']+100;
                }
                $giftdata = array();
                $giftdata['is_email'] = "Yes";
                GiftCertificate::where("gc_id","=",$GCRs[0]['gc_id'])->update($giftdata);
            }
            //========GOOGLE ITEM TRACK CODE For Google Trusted on 20June2014=======
            if($OrderDetailRs[$p]["products_id"] > 0)
            {
            $iproductid = $OrderDetailRs[$p]["products_id"];
            $prores = Products::select('our_price','products_id', 'product_name','short_description','sale_price','sku','product_description','vtype','gender','UPC','imanufactureid')
                        ->where('products_id','=',$iproductid)->get();

            $g_unit_price = $OrderDetailRs[$p]["price"] / $OrderDetailRs[$p]["quantity"];

            $trusted_code.= '{"gtin":"'.$prores[0]["UPC"].'"},';
            //========GOOGLE ITEM TRACK CODE For Google Trusted on 20June2014=======

            //=======GOOGLE ITEM TRACK CODE START HERE===========//
             $fetch_brand = DB::table('pu_manufacture')
                                ->where('imanufactureid','=',$prores[0]["imanufactureid"])->get();

            $fetch_category = array();
            $gcat = '';
            $fetch_category = DB::table('pu_category as c')
                                ->join('pu_products_category as pc','c.category_id','=','pc.category_id')
                                ->join('pu_products as p', 'pc.products_id','=','p.products_id')
                                ->where('p.products_id','=',$OrderDetailRs[$p]['products_id'])->get();
           $StrCatValue = "";
            if($fetch_category && $fetch_category->count() > 0)
            {
                $gcat = stripcslashes($fetch_category[0]->category_name);
                $CatInfo = config('CATEGORY_INFO');
                $breadcrumbs = $CatInfo['CatForProd'][$fetch_category[0]->category_id]['subcatbredcrum'];
                $CategoryArray = explode("-",$breadcrumbs);

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							$StrCatValue .= "item_category :'".$CategoryArray[$d]."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".$CategoryArray[$d]."',";
						}
					}
				}
                $gcatid = $fetch_category[0]->category_id;
            }
            $RIO_EBAY_COMMERCE_CODE  .= "_roi.push(['_addItem',
                                                    '".$prores[0]["sku"]."',
                                                    '".$prores[0]["product_name"]."',
                                                    '".$gcatid."',
                                                    '".$gcat."',
                                                    '".$OrderDetailRs[$p]["price"]."',
                                                    '".$OrderDetailRs[$p]["quantity"]."'
                                                    ]);";
            // Enanced Google Purchase tracking						* 				*/
            $gtm_purchase_prod_str .= "{
                                    'id': '".$prores[0]["sku"]."',
                                    'name': '".$prores[0]["product_name"]."',
                                    'category': '".$gcat."',
                                    'price': '".$OrderDetailRs[$p]['price']."',
                                    'quantity': ".$OrderDetailRs[$p]['quantity']."
                                   },";

			$GA4_purchase_prod_str .= "{
                                    item_id : '".$prores[0]["sku"]."',
                                    item_name : '".$prores[0]["product_name"]."',
                                    affiliation : 'Maxaroma',";
              $GA4_purchase_prod_str .= "
                                     index: ".$p.",
                                     item_brand: '".$fetch_brand[0]->vmanufacture."',
                                     ".$StrCatValue."
                                     item_list_id: 'related_products',
									 item_list_name: 'Related Products',
									 price: ".$OrderDetailRs[$p]['price'].",
									 quantity: ".$OrderDetailRs[$p]["quantity"]."
                                   },";

            $GOOGLE_ORDER_TRACKING .="ga('ec:addProduct', {
                                         id: '".$prores[0]["sku"]."',         // Product SKU
                                        name: '".$prores[0]["product_name"]."',   // Product Name*
                                    category: '".$gcat."',      // Product Category
                                       price: '".$OrderDetailRs[$p]['price']."',              // Price
                                    quantity: '".$OrderDetailRs[$p]['quantity']."'                   // Quantity
                                });";

            //=======GOOGLE ITEM TRACK CODE END HERE===========//

              if($OrderDetailRs->count() > 1)
				{
					$Content_id_str .= "'" .$OrderDetailRs[$p]["sku"] . "',";
					$FBContent_arr[$p]["id"]= $OrderDetailRs[$p]["sku"];
					$FBContent_arr[$p]["quantity"]= $OrderDetailRs[$p]['quantity'];
				}
				else
				{
					$Content_id_str .= "'" .$OrderDetailRs[$p]["sku"] . "'";
					$FBContent_arr["id"] = $OrderDetailRs[$p]["sku"];
					$FBContent_arr["quantity"]= $OrderDetailRs[$p]['quantity'];
				}

            }

            $ProdDetails[] = $OrderDetailRs[$p];
        }

		if(isset($Content_id_str) && $Content_id_str!='')
		{
			 if($OrderDetailRs->count() > 1)
			{
				$Content_id_str = substr($Content_id_str,0,-1);
			}
			$Content_id_Brcket_str = "[".$Content_id_str."]";
		}

        if($critieostr!='')
        {
            $critieostr = substr($critieostr,0,-1);
        }
        $this->PageData["critieostr"] = $critieostr;

        if($roktstr != '')
        {
            $roktstr = substr($roktstr,0,-1);
        }

        $this->PageData["roktstr"] = $roktstr;

		$cj_coupons = $OrderRs[0]["coupon_code"];
        $cj_couponsArr = explode(":",$cj_coupons);
		$cj_TotalCouponsArr = count($cj_couponsArr);
		if(count($cj_couponsArr) > 0)
		{
			if($cj_TotalCouponsArr == 2)
			{
				$OtherCouponArr1 = explode("#",$cj_couponsArr[0]);
				$OtherCouponArr2 = explode("#",$cj_couponsArr[1]);
				$cj_coupons = $OtherCouponArr1[0].','.$OtherCouponArr2[0];
			}else{
				$cj_coupons = $OrderRs[0]["coupon_code"];
			}
		}

        if(Cookie::has("PEPPERJAM") && Cookie::get("PEPPERJAM") == "YES" && Session::get('eusertype') != "Wholesaler")
        {
            $customArr = Order::where('customer_id','=',$OrderRs[0]["customer_id"])->get();
            $new_to_file = 1;
            if($customArr->count() > 1)
            {
                $new_to_file = 0;
            }

            $order_id = $OrderRs[0]["orders_no"];
            $integration = 'DYNAMIC';
            $program_id = '8716';
            $coupons = $OrderRs[0]["coupon_code"];
            $couponsArr = explode(":",$coupons);
            $TotalCouponsArr = count($couponsArr);
            if(count($couponsArr) > 0)
            {
                if($TotalCouponsArr == 2)
                {
                    $OtherCouponArr1 = explode("#",$couponsArr[0]);
                    $OtherCouponArr2 = explode("#",$couponsArr[1]);
                    $coupons = $OtherCouponArr1[0].','.$OtherCouponArr2[0];
                }else{
                    $coupons = $OrderRs[0]["coupon_code"];
                }
            }
            $pixel_html = '';

            if(count($order_items_pixel) > 0)
            {
                $AllDiscounts = $this->GetAllDiscounts();
                $TotalDiscount = $AllDiscounts['TotalDiscount'];
                $SubTotal = NumberFormat(Session::get('ShoppingCart.SubTotal'));
                $affiliateDiscount = 0;
                if($TotalDiscount > 0)
                {
                    $affiliateDiscount = 1 -($TotalDiscount / $SubTotal);
                    $affiliateDiscount =  number_format($affiliateDiscount , 2, '.','');
                }
                $x = 1;
                foreach ($order_items_pixel as $order_item)
                {
                    if($affiliateDiscount > 0 && $affiliateDiscount!='')
                    {
                        $affiliateItemAmount = $order_item['price'] * $affiliateDiscount;
                        $affiliateItemAmount =  number_format($affiliateItemAmount , 2, '.','');
                    }else{
                        $affiliateItemAmount = $order_item['price'];
                    }

                    $pixel_html .=
                    '&' . 'ITEM_ID' . $x . '=' . $order_item['id'] .
                    '&' . 'ITEM_PRICE' . $x . '=' . $affiliateItemAmount .
                    '&' . 'QUANTITY' . $x . '=' . $order_item['quantity'];
                    $x++;
                }
                if($pixel_html!='' && $coupons!='')
                {
                    $pixel_html ='<iframe src="https://t.pepperjamnetwork.com/track?'.'INT='.$integration.'&PROGRAM_ID='.$program_id.'&ORDER_ID='.$order_id.'&COUPON='.$coupons.'&NEW_TO_FILE='.$new_to_file.$pixel_html.'" width="1" height="1" frameborder="0"></iframe>';
                }else{
                    $pixel_html ='<iframe src="https://t.pepperjamnetwork.com/track?'.'INT='.$integration.'&PROGRAM_ID='.$program_id.'&ORDER_ID='.$order_id.'&NEW_TO_FILE='.$new_to_file.$pixel_html.'" width="1" height="1" frameborder="0"></iframe>';
                }
                $updateOrdernewArr = array ('is_pepperjam'	 => "Yes" );
                //Order::where('orders_id','=',$OrderID)->update($updateOrdernewArr);
                $this->PageData["pixel_html"] = $pixel_html;
            }
        }

		$FBContent_str = '';
        if(is_array($FBContent_arr))
        {
			$FBContent_str = json_encode($FBContent_arr);
		}

		//for cj start
		$cj_pid_fstr = '';
		$cj_tot_qty = 0;
		$cj_Coupon_DiscLevel = "";
		$cj_coupon_order = "";
		$cj_coupon_code = $this->GetAllCoupons('CouponCode');

		if($cj_coupon_code != "" && Session::has('ShoppingCart.Coupon_Detail_CJ.orders'))
		{
			$cj_coupon_order = Session::get('ShoppingCart.Coupon_Detail_CJ.orders');
			if($cj_coupon_order != ""){
				if($cj_coupon_order == 0){
					//on order amount so whole order discount
					$cj_Coupon_DiscLevel = "Order";
				}else if($cj_coupon_order == 4){
					//free shipping so no discount
					$cj_Coupon_DiscLevel = "None";
				}else{
					//category,sku,brand etc.
					$cj_Coupon_DiscLevel = "Item";
				}
			}
		}

		$cj_tempCart = (Session::has('ShoppingCart.Cart'))?Session::get('ShoppingCart.Cart'):[];
		$cj_item_array = array();
		$sf_item_array = array();
		$cj_cnt_row = count($cj_tempCart);
		if($cj_cnt_row > 1)
		{
			for($ir=0;$ir<$cj_cnt_row;$ir++)
			{
				if($cj_tempCart[$ir]['SKU'] != "")
				{
					$cj_product_quantity = $cj_tempCart[$ir]['Qty'];
					$cj_tot_qty += $cj_product_quantity;

					$cj_product_name = $cj_tempCart[$ir]['ProductName'];
					$cj_product_name = str_replace("\"","'",$cj_product_name);

					$cj_item_array[$ir]['product_name'] = $cj_product_name;
					$cj_item_array[$ir]['product_id'] = $cj_tempCart[$ir]['SKU'];
					$cj_item_array[$ir]['product_price'] = $cj_tempCart[$ir]['Price'];
					$cj_item_array[$ir]['product_quantity'] = $cj_product_quantity;

					$cj_ItemWiseDiscount = 0;

					if(((isset($cj_tempCart[$ir]['ItemWiseCouponDiscount']) && $cj_tempCart[$ir]['ItemWiseCouponDiscount'] > 0) || (isset($cj_tempCart[$ir]['ItemWiseCouponDiscount_CJ']) && $cj_tempCart[$ir]['ItemWiseCouponDiscount_CJ'] > 0)) && $cj_Coupon_DiscLevel == "Item"){
						if($cj_coupon_order == "7"){
							$cj_ItemWiseDiscount = $cj_tempCart[$ir]['ItemWiseCouponDiscount_CJ'];
						}else{
							$cj_ItemWiseDiscount = $cj_tempCart[$ir]['TotPrice'] - $cj_tempCart[$ir]['ItemWiseCouponDiscount'];
						}
					}
					$cj_item_array[$ir]['ItemWiseDiscount'] = number_format($cj_ItemWiseDiscount,2);

					$cj_pid_fstr .= $cj_tempCart[$ir]['SKU'].",";
					//sf track cart start
					$sf_item_array[$ir]['item'] = $cj_tempCart[$ir]['SKU'];
					$sf_item_array[$ir]['quantity'] = $cj_product_quantity;
					$sf_item_array[$ir]['price'] = $cj_tempCart[$ir]['Price'];
					$sf_item_array[$ir]['unique_id'] = $cj_tempCart[$ir]['SKU'];
						//sf track cart end
				}
			}
			$cj_pid_fstr = substr($cj_pid_fstr,0,-1);
			// if($cj_pid_fstr != "")
			// {
			// 	$Data['RemarketingprodID'] = explode(",",$cj_pid_fstr);
			// }
		}
		else
		{
			for($ir=0;$ir<$cj_cnt_row;$ir++)
			{
				if($cj_tempCart[$ir]['SKU'] != "")
				{
					$cj_product_quantity = $cj_tempCart[$ir]['Qty'];
					$cj_tot_qty += $cj_product_quantity;

					$cj_product_name = $cj_tempCart[$ir]['ProductName'];
					$cj_product_name = str_replace("\"","'",$cj_product_name);

					$cj_item_array[$ir]['product_name'] = $cj_product_name;
					$cj_item_array[$ir]['product_id'] = $cj_tempCart[$ir]['SKU'];
					$cj_item_array[$ir]['product_price'] = $cj_tempCart[$ir]['Price'];
					$cj_item_array[$ir]['product_quantity'] = $cj_product_quantity;

					$cj_ItemWiseDiscount = 0;
					$cj_tempCart[$ir]['ItemWiseCouponDiscount'] = (isset($cj_tempCart[$ir]['ItemWiseCouponDiscount'])?$cj_tempCart[$ir]['ItemWiseCouponDiscount']:0);
					$cj_tempCart[$ir]['ItemWiseCouponDiscount_CJ'] = (isset($cj_tempCart[$ir]['ItemWiseCouponDiscount_CJ'])?$cj_tempCart[$ir]['ItemWiseCouponDiscount_CJ']:0);

					if(($cj_tempCart[$ir]['ItemWiseCouponDiscount'] > 0 || $cj_tempCart[$ir]['ItemWiseCouponDiscount_CJ'] > 0) && $cj_Coupon_DiscLevel == "Item"){
						if($cj_coupon_order == "7"){
							$cj_ItemWiseDiscount = $cj_tempCart[$ir]['ItemWiseCouponDiscount_CJ'];
						}else{
							$cj_ItemWiseDiscount = $cj_tempCart[$ir]['TotPrice'] - $cj_tempCart[$ir]['ItemWiseCouponDiscount'];
						}
					}
					$cj_item_array[$ir]['ItemWiseDiscount'] = number_format($cj_ItemWiseDiscount,2);

					$cj_pid_fstr .= $cj_tempCart[$ir]['SKU'];

					$sf_item_array[$ir]['item'] = $cj_tempCart[$ir]['SKU'];
					$sf_item_array[$ir]['quantity'] = $cj_product_quantity;
					$sf_item_array[$ir]['price'] = $cj_tempCart[$ir]['Price'];
					$sf_item_array[$ir]['unique_id'] = $cj_tempCart[$ir]['SKU'];
				}
			}
			// if($cj_pid_fstr != "")
			// {
			// 	$Data['RemarketingprodID'] = $cj_pid_fstr;
			// }
		}

		//for cj end

        // Enanced Google Purchase tracking
        $GOOGLE_ORDER_TRACKING .="ga('ec:setAction', 'purchase', {
                                    id: '".$OrderRs[0]['orders_id']."',     		// Transaction ID*
                                    affiliation: 'MaxAroma', 						// Store Name
                                    revenue: '".$OrderRs[0]['order_total']."',    // Total
                                    tax: '".$OrderRs[0]['tax']."',         		// Tax
                                    shipping: '".$OrderRs[0]['shipping_amt']."' // Shipping
                                });";

        $GOOGLE_ORDER_TRACKING_GTM .="dataLayer.push(
                                         {
                                          'ecommerce': {
                                            'purchase': {
                                              'actionField': {
                                                'id': '".$OrderRs[0]['orders_id']."',
                                                'affiliation': 'MaxAroma',
                                                'revenue': '".$OrderRs[0]['order_total']."',
                                                'tax':'".$OrderRs[0]['tax']."',
                                                'shipping': '".$OrderRs[0]['shipping_amt']."'
                                              },
                                              'products': [".rtrim($gtm_purchase_prod_str, ',')."]
                                            }
                                          }
                                        });";
       $CurrencySymbol = 'USD';
		if(Session::has('currency_code') && Session::get('currency_code') != '')
			$CurrencySymbol = Session::get('currency_code');
        $bill_phone = "";
        if(isset($OrderRs[0]['bill_phone']) && $OrderRs[0]['bill_phone']!='')
        {
			 $bill_phone = $OrderRs[0]['bill_phone'];
		}
		$RequestURLGTM = "";
		if(Session::has('RequestURLGTM') && Session::get('RequestURLGTM') != '')
		{
			$RequestURLGTM = Session::get('RequestURLGTM');
		}

		$discountInfo_n = $this->GetAllDiscounts();
		$discount_n = $discountInfo_n['TotalDiscount'];

        $GA4_purchase_prod_str = substr($GA4_purchase_prod_str,0,-1);
        $GA4 ="";
        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'purchase',
								  RemarketingprodID:".json_encode(explode(",", $cj_pid_fstr)).",
								  RemarketingpageType:'purchase',
								  RemarketingtotalValue:'".$OrderRs[0]['sub_total']."',
								  RemarketingConversionLanguage : 'en',
								  RemarketingConversionFormat : '3',
								  RemarketingConversionColor : 'ffffff',
								  RemarketingConversionLabel : 'O5inCKztgGsQkqLpuQM',
								  RemarketingOnly:'false',
								  RemarketingCouponCode:'".$cj_coupons."',
								  RemarketingDiscount:'".$discount_n."',
								  RemarketingDiscountCJ:'".$discount_n."',
								  RemarketingOrderId:".$OrderRs[0]['orders_id'].",
								  line_items_array:".json_encode($cj_item_array).",
								  line_items:".json_encode($cj_item_array).",
								  order_quantity:$cj_tot_qty,
								  currency:'".$CurrencySymbol."',
								  SF_TrackPurchase:".json_encode($sf_item_array).",
								  page_location : '".$RequestURLGTM."',
								  user_data :{
										address :
											{
												first_name : '".$OrderRs[0]['bill_first_name']."',
												last_name : '".$OrderRs[0]['bill_last_name']."',
												email_address: '".$OrderRs[0]['bill_email']."',
												phone_number : '".$bill_phone."',
											}
									},
								  contents : ".$FBContent_str.",
								  content_ids : ".$Content_id_Brcket_str.",
								  content_type: 'product',
								  ecommerce:{
								  transaction_id: '".$OrderRs[0]['orders_no']."',
								  subtotal : '".$OrderRs[0]['sub_total']."',
								  value: '".$OrderRs[0]['order_total']."',
								  tax: ".$OrderRs[0]['tax'].",
								  shipping: ".$OrderRs[0]['shipping_amt'].",
								  currency: '".$CurrencySymbol."',
								  ".$couponCodeNo."
								  items: [
											".$GA4_purchase_prod_str."
										 ]
										}
									});
								";

        /*$GA4FacebookConversion = "gtag('event', 'purchase',
		  {
			'event_id': 1221211221,
			'transaction_id': 'OR255555',
			  user_data: {
			  address: {
				first_name: 'Qualdev Test',
			  },
			}
		  });"; */

        $this->PageData['GA4'] = $GA4;

        $Bizrate_POS_Code_NEW = substr($Bizrate_POS_Code_NEW,0,-1);
        $Bizrate_POS_Code_NEW.= "'";
        $Bizrate_POS_Code =$Bizrate_POS_Code.$Bizrate_POS_Code_NEW;

        $this->PageData['Bizrate_POS_Code'] = '<script type="text/javascript">'.$Bizrate_POS_Code.'</script>';
        $this->PageData['trusted_code'] = trim($trusted_code,",");

        $ShippingInsurance = $OrderRs[0]['route_shipping_insurance_charge'];
        /*if($ShippingInsurance > 0){
            $this->RouteShippingInsuranceOrderProcess($OrderRs[0],$OrderDetailRs);

        }*/
		$chkPaymentType = $OrderRs[0]['payment_type'];
		$chkPaidStatus = $OrderRs[0]['pay_status'];
        #### Deduct product stock Start #####
        if ($OrderRs[0]['pay_status'] == 'Paid') {
			for ($n = 0; $n < count($OrderDetailRs); $n++) {
				$this->ProductDeductStock($OrderDetailRs[$n]["sku"], $OrderDetailRs[$n]["quantity"], $OrderDetailRs[$n]["IsCosmo"], $OrderDetailRs[$n]["IsNandansons"], $OrderDetailRs[$n]["IsPerfumePW"], $OrderDetailRs[$n]["IsPCA"], $OrderDetailRs[$n]["IsND"], $OrderDetailRs[$n]["VendorSKU"], $OrderDetailRs[$n]["item_type"],$OrderDetailRs[$n]["products_id"]);
			}
		} else if ($OrderRs[0]['payment_type'] == "PAYMENT_STRIPE"  || $OrderRs[0]['payment_type'] == "PAYMENT_WT" || $OrderRs[0]['payment_type'] == "PAYMENT_DS" ||  $OrderRs[0]['payment_type'] == "PAYMENT_CL" || $OrderRs[0]['payment_type'] == "PAYMENT_GIFT_CERTIFICATE") {
			for ($n = 0; $n < count($OrderDetailRs); $n++) {
				$this->ProductDeductStock($OrderDetailRs[$n]["sku"], $OrderDetailRs[$n]["quantity"], $OrderDetailRs[$n]["IsCosmo"], $OrderDetailRs[$n]["IsNandansons"], $OrderDetailRs[$n]["IsPerfumePW"], $OrderDetailRs[$n]["IsPCA"], $OrderDetailRs[$n]["IsND"], $OrderDetailRs[$n]["VendorSKU"], $OrderDetailRs[$n]["item_type"],$OrderDetailRs[$n]["products_id"]);
			}
		}
        #### Deduct product stock End #####

        ### Code for payment message Start #######

        $PaymentRs = PaymentMethod::where('pm_group_name','=',$OrderRs[0]['payment_type'])->limit(1)->get();

        $Payment_Method_Message = '';
        if($PaymentRs && $PaymentRs->count() > 0 && trim($PaymentRs[0]['pm_short_desc'])!='' )
        {
            $Payment_Method_Message = stripslashes(trim($PaymentRs[0]['pm_short_desc']));
        }

        ## Msg for status "Pending Review" of AuthNet Start ##
        if(($OrderRs[0]['payment_type']=='PAYMENT_AUTHORIZENETCC' || $OrderRs[0]['payment_type']=='PAYMENT_PAYPALCC') && $OrderRs[0]['payment_gateway_response']!='')
        {
            $arr_gateway_response = explode(",",$OrderRs[0]['payment_gateway_response']);
            if ($arr_gateway_response[0] == 4)
            {
                $Payment_Method_Message = "<h5>Thank you! Your order will be processed pending a standard transaction review.</h5>
                <p>We hope you enjoyed shopping with us. Your order will be processed as soon as possible. We will contact you with updates. <br />Please allow 24hrs to process the payment. An E-mail Confirmation will be sent upon payment received.</p>";
            }
        }
        $this->PageData['Payment_Method_Message'] = str_replace('>rn<','><',$Payment_Method_Message);

        $STR_EMAIL_ITEM = '';
        /*$STR_EMAIL_ITEM .= '<table cellpadding="0" cellspacing="0" width="100%" border="0">
                <tr align="center" valign="top">
                    <td style="background-color:#e5e5e5; padding:5px;"><strong>Gift Wrap</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px;"><strong>Images</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px;" align="left"><strong>Your Order Summary</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px; width:65px;"><strong>Quantity</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px; width:87px;" align="right"><strong>Price</strong></td>
                </tr>';*/

		$STR_EMAIL_ITEM .= '<table cellpadding="0" cellspacing="0" width="100%" border="0">
                <tr><td style="width:100%; overflow-x:auto;">
                <table cellpadding="0" cellspacing="0" width="100%" border="0" style="border-collapse:collapse; table-layout:fixed; min-width:620px;">
                <colgroup>
                    <col style="width:75px;">
                    <col style="width:130px;">
                    <col style="width:260px;">
                    <col style="width:80px;">
                    <col style="width:75px;">
                </colgroup>
                <tr align="center" valign="top">
                    <td style="background-color:#e5e5e5; padding:5px;"><strong>Gift Wrap</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px;"><strong>Images</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px;" align="left"><strong>Your Order Summary</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px; width:67px;"><strong>Quantity</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px; width:75px;" align="right"><strong>Price</strong></td>
                </tr>';

		$STR_EMAIL_ITEM_STORE = '<div style="padding:28px 20px;">';
        $STR_EMAIL_ITEM_STORE .= '<div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:2px;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid #eee;">Your Selections</div>';

        $TotalProducts = 0;
        for($n=0;$n<count($OrderDetailRs);$n++)
        {
            $checked = $iimage = '';
            if($OrderDetailRs[$n]['is_gift_wrap']=='Yes')
            { $checked = 'checked="checked" '; }

			$IsGiftCertificateItem1 = $this->checkGiftCertificateItem('IsGiftCertificateItem',$OrderDetailRs[$n],'No');

			if($IsGiftCertificateItem1 == 'Yes'){
				$iimage = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$OrderDetailRs[$n],'No');
				$OrderDetailRs[$n]['Image'] = $iimage;
			}else{
				$OrderDetailRs[$n]['Image'] = $OrderDetailRs[$n]['Image'];
			}

			$productName_store = $OrderDetailRs[$n]['product_name'];
            $sku_store         = $OrderDetailRs[$n]['sku'];
            $price_store       = Price($OrderDetailRs[$n]['price']);
            $quantity_store    = $OrderDetailRs[$n]['quantity'];

            $STR_EMAIL_ITEM_STORE .= '<div style="padding:12px 0;border-bottom:1px solid #f5f5f3;">';

			$STR_EMAIL_ITEM_STORE .= '<table width="100%" cellpadding="0" cellspacing="0">';
			$STR_EMAIL_ITEM_STORE .= '<tr>';

			$STR_EMAIL_ITEM_STORE .= '<td style="font-size:14px;color:#1a1a2e;">';
			$STR_EMAIL_ITEM_STORE .= '<div style="font-weight:500;">'.$productName_store.'</div>';
			$STR_EMAIL_ITEM_STORE .= '<div style="font-size:12px;color:#999;margin-top:2px;">'.$sku_store.'</div>';
			$STR_EMAIL_ITEM_STORE .= '</td>';

			$STR_EMAIL_ITEM_STORE .= '<td align="right" style="min-width:80px;">';
			$STR_EMAIL_ITEM_STORE .= '<div style="font-size:14px;font-weight:600;color:#1a1a2e;">'.$price_store.'</div>';
			$STR_EMAIL_ITEM_STORE .= '<div style="font-size:12px;color:#999;">Qty: '.$quantity_store.'</div>';
			$STR_EMAIL_ITEM_STORE .= '</td>';

			$STR_EMAIL_ITEM_STORE .= '</tr>';
			$STR_EMAIL_ITEM_STORE .= '</table>';

            $STR_EMAIL_ITEM_STORE .= '</div>';

            //$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td valign="middle" style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><input type="checkbox"  disabled="disabled" '.$checked.' /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><img src="'.$OrderDetailRs[$n]['Image'].'" border="0" width="125" border="0" class="img-resp-75" /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="left"><p style="color:#000; margin:0px;"><strong>'.$OrderDetailRs[$n]['product_name'].'</strong></p><p>SKU: '.$OrderDetailRs[$n]['sku'].'</p>';

			$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td valign="middle" style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><input type="checkbox"  disabled="disabled" '.$checked.' /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><img src="'.$OrderDetailRs[$n]['Image'].'" border="0" width="125" border="0" class="img-resp-75" /></td><td style="padding:10px 8px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8; width:260px; word-break:break-word; overflow-wrap:break-word; white-space:normal;" align="left"><p style="color:#000; margin:0px;"><strong>'.$OrderDetailRs[$n]['product_name'].'</strong></p><p>SKU: '.$OrderDetailRs[$n]['sku'].'</p>';

            //if($OrderDetailRs[$n]["sku"] == config('global.GIFT_CERTIFICATE_SKU') || $OrderDetailRs[$n]["sku"] == config('global.GIFT_CERTIFICATE_SKU1') || $OrderDetailRs[$n]["sku"] == config('global.GIFT_CERTIFICATE_SKU2'))
            if($IsGiftCertificateItem1 == 'Yes')
            {
                $STR_EMAIL_ITEM .='<p><strong>Sender Name : </strong> '.$OrderDetailRs[$n]['SenderName'].'</p>
                                   <p><strong>Sender Email : </strong> '.$OrderDetailRs[$n]['SenderEmail'].'</p>';
                $STR_EMAIL_ITEM .='<p><strong>Recipient Name : </strong> '.$OrderDetailRs[$n]['RecipientName'].'</p>
                                   <p><strong>Recipient Email : </strong> '.$OrderDetailRs[$n]['RecipientEmail'].'</p>';
            }
            if($OrderDetailRs[$n]["handling_time_str"]!='')
            {
                $STR_EMAIL_ITEM .='<p>'.$OrderDetailRs[$n]['handling_time_str'].'</p>';
            }
            $STR_EMAIL_ITEM .= '</td>';
            $STR_EMAIL_ITEM .= '<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><strong>'.$OrderDetailRs[$n]['quantity'].'</strong></td>
            <td style="padding:10px 5px; border-bottom:1px solid #e8e8e8;" align="right"><strong>'.Price($OrderDetailRs[$n]['price']).'</strong></td>
            </tr>';

            $TotalProducts = (int)$TotalProducts + (int)$OrderDetailRs[$n]['quantity'];
        }
		$STR_EMAIL_ITEM_STORE .= '</div>';
        if(isset($OrderDetailRs[$n]['is_gift_wrap']) && $OrderDetailRs[$n]['is_gift_wrap']=='Yes')
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Gift Wrap:</strong></td><td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">Yes</td></tr>';
        }

        $STR_EMAIL_ITEM .= '<tr align="center" valign="top">
            <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong> Total item purchased:</strong></td>
            <td align="right" style="padding:5px;border-bottom:1px solid #e8e8e8;">'.$TotalProducts.'</td>
        </tr>';

        $STR_EMAIL_ITEM .= '<tr align="center" valign="top">
            <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Subtotal:</td>
            <td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['sub_total']).'</td>
        </tr>';

        if(!empty($OrderRs[0]["shipping_amt"]) && $OrderRs[0]["shipping_amt"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Charge:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['shipping_amt']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["tax"]) && $OrderRs[0]["tax"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Sales Tax:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['tax']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["gift_charge"]) && $OrderRs[0]["gift_charge"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Wrap Charge :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['gift_charge']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["auto_discount"]) && $OrderRs[0]["auto_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Auto Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['auto_discount']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["quantity_discount"]) && $OrderRs[0]["quantity_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Quantity Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['quantity_discount']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["reward_discount"]) && $OrderRs[0]["reward_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Yotpo Reward Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['reward_discount']).'</td></tr>';
        }

		//if(!empty($OrderRs[0]["shipping_signature"]) && $OrderRs[0]["shipping_signature"]>0)
		//if(!empty($OrderRs[0]["is_shipping_signature"]) && $OrderRs[0]["is_shipping_signature"]>0)
		$shipping_signature = '';
		$shipping_sign = '';
		if(isset($OrderRs[0]["is_shipping_signature"]) && $OrderRs[0]["is_shipping_signature"] != '')
        {
			$shipping_signature = 'ON';
            $shipping_sign = 'Y';
            if($OrderRs[0]["is_shipping_signature"] == 'No'){
                $shipping_signature = 'OFF';
                $shipping_sign = 'N';
            }
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Signature ('.$shipping_signature.') :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['shipping_signature']).'</td></tr>';
        }

        /*if(!empty($OrderRs[0]["shipping_signature"]) && $OrderRs[0]["shipping_signature"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Signature :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['shipping_signature']).'</td></tr>';
        }*/
		$shipping_insurance = 'N';
        if(!empty($OrderRs[0]["route_shipping_insurance_charge"]) && $OrderRs[0]["route_shipping_insurance_charge"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Insurance* :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['route_shipping_insurance_charge']).'</td></tr>';
			$shipping_insurance = 'Y';
        }

        if(!empty($OrderRs[0]["coupon_amount"]) && $OrderRs[0]["coupon_amount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Coupon Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['coupon_amount']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["gc_amount"]) && $OrderRs[0]["gc_amount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Certificate Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['gc_amount']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["bogo_discount"]) && $OrderRs[0]["bogo_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Bogo Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['bogo_discount']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["reward_discount"]) && $OrderRs[0]["reward_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Reward Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['reward_discount']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["refer_amount"]) && $OrderRs[0]["refer_amount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Refer Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['refer_amount']).'</td></tr>';
        }

        if(!empty($OrderRs[0]["apply_credit"]) && $OrderRs[0]["apply_credit"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Credit Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['apply_credit']).'</td></tr>';
        }

        $STR_EMAIL_ITEM .= '<tr align="center" valign="top">
            <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Order Total:</strong></td>
            <td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right"><strong>'.Price($OrderRs[0]['order_total']).'</strong></td>
        </tr>';

        $STR_EMAIL_ITEM .= '</table>';

        if(isset($OrderRs[0]["free_gift"]) && $OrderRs[0]["free_gift"]!="")
        {
            $OrderRs[0]["free_gift"] = "Got a Free Gift Of ".$OrderRs[0]["free_gift"];
        }
        $OrdDate = date('Y-m-d h:i:s',$OrderRs[0]["order_datetime"]);
        $estimated_ship_date = date('Y-m-d', strtotime($OrdDate . ' + 23 day'));
        $estimated_date = date('m-d-Y', strtotime($OrderRs[0]["EstimatedDeliveryDate"]));
        $this->PageData["Estimated_Ship_Date"] = $estimated_ship_date;
       // $this->PageData["GOOGLE_ORDER_TRACKING"] = $GOOGLE_ORDER_TRACKING;
		$this->PageData["GOOGLE_ORDER_TRACKING"] = "";
        $mailTemplate = GetMailTemplate("ORDER_RECEIPT_NEW");
        $this->PageData["topmenubar"] = $topmenubar;
        $this->PageData["freeshippinginfo"] = $freeshippinginfo;

        $MailBanners = MailBanner::where('status','=','1');
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
        $this->PageData["Addblock"] = $Addblock;

        ##Send Email TO Customer
        $to_email =  $OrderRs[0]["bill_email"];
        $bcc= 'b1ff7c3c82@invite.trustpilot.com';
        $ReceiptMailBody = $mailTemplate[0]['mail_body'];
        $ReceiptMailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$TOPMENUBAR}',$topmenubar,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$topmenubar}',$topmenubar,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$Addblock}',$Addblock,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$orders_no}',$OrderRs[0]['orders_no'],$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$order_total}',Price($OrderRs[0]['order_total']),$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$order_datetime}',date('M d, Y',$OrderRs[0]['order_datetime']),$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$shipinfo}',$OrderRs[0]['shipinfo'],$ReceiptMailBody);

        $BillAddress = $OrderRs[0]['bill_first_name'].' '.$OrderRs[0]['bill_last_name']."<br>";
        if($OrderRs[0]['bill_address2'] != '')
            $BillAddress.= $OrderRs[0]['bill_address1'].', '.$OrderRs[0]['bill_address2']."<br>";
        else
            $BillAddress.= $OrderRs[0]['bill_address1'].',<br>';
        $BillAddress.=$OrderRs[0]['bill_city'].', '.$OrderRs[0]['bill_state']."<br>";
        $BillAddress.=$OrderRs[0]['bill_zip'].' - '.$OrderRs[0]['bill_country'];

        $ReceiptMailBody = str_replace('{$bill_address}',$BillAddress,$ReceiptMailBody);

        $ShipAddress = $OrderRs[0]['ship_first_name'].' '.$OrderRs[0]['ship_last_name']."<br>";
        if($OrderRs[0]['ship_address2'] != '')
            $ShipAddress.= $OrderRs[0]['ship_address1'].', '.$OrderRs[0]['ship_address2']."<br>";
        else
            $ShipAddress.= $OrderRs[0]['ship_address1'].',<br>';
        $ShipAddress.=$OrderRs[0]['ship_city'].', '.$OrderRs[0]['ship_state']."<br>";
        $ShipAddress.=$OrderRs[0]['ship_zip'].' - '.$OrderRs[0]['ship_country'];

		if(isset($OrderRs[0]['store_id']) && $OrderRs[0]['store_id'] > 0 && isset($OrderRs[0]['is_skip_addresspart']) && $OrderRs[0]['is_skip_addresspart']!=''  && $OrderRs[0]['is_skip_addresspart'] != 'No'){

			$address_part = explode("###",$OrderRs[0]['is_skip_addresspart']);
			if(isset($address_part[0]) && $address_part[0] == 'Yes' ){
				$BillAddress = "";
				$ShipAddress = "";//$OrderRs[0]['ship_first_name'].' '.$OrderRs[0]['ship_last_name'];
				//$to_email = "tempchecknew@gmail.com";
			}
		}
		if(isset($OrderRs[0]['store_id']) && $OrderRs[0]['store_id'] > 0){
			$estimated_date = "";
		}

        $ReceiptMailBody = str_replace('{$ship_address}',$ShipAddress,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$STR_EMAIL_ITEM}',$STR_EMAIL_ITEM,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$ReceiptMailBody);

        $MailSubject = $mailTemplate[0]['subject'];
        $MailSubject = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$MailSubject);
        $MailSubject = str_replace('{$OrderRs.orders_no}',$OrderRs[0]['orders_no'],$MailSubject);
		$customer_comment = (isset($OrderRs[0]['customer_comment']))?$OrderRs[0]['customer_comment']:'';
        /*if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
        {
            echo $ReceiptMailBody;
            exit;
        }*/

		if(Session::has('ShoppingCart.Shipping.ShippingMethodID') && Session::get('ShoppingCart.Shipping.ShippingMethodID') == '46'){
			StorePickup::create([
				'order_id' => $OrderID,
				'status'   => 'Store Pickup',
			]);
		}

	/*
	   if(Session::has('ShoppingCart.Shipping.ShippingMethodID') && Session::get('ShoppingCart.Shipping.ShippingMethodID') == '46'){
			StorePickup::create([
				'order_id' => $OrderID,
				'status'   => 'Store Pickup',
			]);
			$customer_nm = $OrderRs[0]['bill_first_name'].' '.$OrderRs[0]['bill_last_name'];
			$Addblock = 'Store Pickup';
			//send email to staff ronald@maxaroma.com + hector@maxaroma.com
			$staff_email = "ronald@maxaroma.com"; //"tempchecknew@gmail.com";
			$OtherData = ['toMail' => $staff_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign, 'pickup_status' => 'Store Pickup','customer_name'=>$customer_nm];
			OmanisendRequest('6936ffd745fd2286e1ae63a1',$OrderRs[0],$OtherData);

			$staff2_email = "hector@maxaroma.com"; //"tempchecknew@gmail.com";
			$OtherData = ['toMail' => $staff1_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign, 'pickup_status' => 'Store Pickup','customer_name'=>$customer_nm];
			OmanisendRequest('6936ffd745fd2286e1ae63a1',$OrderRs[0],$OtherData);

			//send email to customer
			$to_email = "tempchecknew@gmail.com";
			$Addblock = 'Store Pickup';
			$bill_phone = $OrderRs[0]["bill_phone"];
			$OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign, 'pickup_status' => 'Store Pickup','customer_name'=>$customer_nm,'phone'=>$bill_phone];
			OmanisendRequest('6936f81cc72739b72a50951a',$OrderRs[0],$OtherData);
			//exit;
		}	*/
       if(config('global.OMNISEND_PROG') == false)
        {
            //SendMail($MailSubject,$ReceiptMailBody,$to_email,config('Settings.ADMIN_MAIL'),'',$bcc);
            ##Send Email TO Admin
            SendMail($MailSubject,$ReceiptMailBody,config('Settings.ADMIN_MAIL'),config('Settings.CONTACT_MAIL'));
        } else {
            /** OMANISEND **/
			if($chkPaymentType=="PAYMENT_WT" || $chkPaymentType=="PAYMENT_DS" ||  $chkPaymentType=="PAYMENT_CL" || $chkPaymentType=="PAYMENT_GIFT_CERTIFICATE")
			{
				//$OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date];
				$OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign];
				OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRs[0],$OtherData);

				//$OtherData = ['toMail' => config('Settings.ADMIN_MAIL'), 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment];
				$OtherData = ['toMail' => 'orders@maxaroma.com', 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign];
				OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRs[0],$OtherData);

				/*$OthData = ['toMail' => 'qualdev.devs@gmail.com', 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date];
				OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRs[0],$OthData);	*/

			} else if($chkPaidStatus == 'Paid' && $chkPaymentType != "PAYMENT_STRIPE"){

				if(Session::has('ShoppingCart.Shipping.ShippingMethodID') && Session::get('ShoppingCart.Shipping.ShippingMethodID') == '46'){
					// StorePickup::create([
					// 	'order_id' => $OrderID,
					// 	'status'   => 'Store Pickup',
					// ]);
					$customer_nm = $OrderRs[0]['bill_first_name'].' '.$OrderRs[0]['bill_last_name'];
					$Addblock = 'Store Pickup';
					//send email to staff ronald@maxaroma.com + hector@maxaroma.com

					$staff_email = "ronald@maxaroma.com"; //"tempchecknew@gmail.com";
					$OtherData = ['toMail' => $staff_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign, 'pickup_status' => 'Store Pickup','customer_name'=>$customer_nm];
					OmanisendRequest('6936ffd745fd2286e1ae63a1',$OrderRs[0],$OtherData);

					$staff1_email = "hector@maxaroma.com"; //"tempchecknew@gmail.com";
					$OtherData = ['toMail' => $staff1_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign, 'pickup_status' => 'Store Pickup','customer_name'=>$customer_nm];
					OmanisendRequest('6936ffd745fd2286e1ae63a1',$OrderRs[0],$OtherData);

					// $staff2_email = "tempchecknew@gmail.com"; //"tempchecknew@gmail.com";
					// $OtherData = ['toMail' => $staff2_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign, 'pickup_status' => 'Store Pickup','customer_name'=>$customer_nm];
					// OmanisendRequest('6936ffd745fd2286e1ae63a1',$OrderRs[0],$OtherData);

					//send email to customer
					//$to_email = "tempchecknew@gmail.com";
					//$Addblock = 'Store Pickup';
					$bill_phone = $OrderRs[0]["bill_phone"];
					$OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign, 'pickup_status' => 'Store Pickup','customer_name'=>$customer_nm,'phone'=>$bill_phone];
					OmanisendRequest('6936f81cc72739b72a50951a',$OrderRs[0],$OtherData);

					// $bill_phone = $OrderRs[0]["bill_phone"];
					// $OtherData = ['toMail' => "tempchecknew@gmail.com", 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign, 'pickup_status' => 'Store Pickup','customer_name'=>$customer_nm,'phone'=>$bill_phone];
					// OmanisendRequest('6936f81cc72739b72a50951a',$OrderRs[0],$OtherData);
					//exit;
				}

				if(isset($OrderRs[0]['store_id']) && $OrderRs[0]['store_id'] > 0){
					$storeData = DB::table('pu_stores as s')->where('s.store_id','=',$OrderRs[0]['store_id'])->select('store_city','store_state')->first();
					$store_address = $storeData->store_city." ".$storeData->store_state;
					$bill_phone = $OrderRs[0]["bill_phone"];
					$OtherData = ['toMail' => $to_email, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM_STORE,'phone'=>$bill_phone,'OrdDate'=>$OrdDate,'store_address'=>$store_address];
					OmanisendRequest('69f8b45dbe5da69308eeb38d',$OrderRs[0],$OtherData);

					// $bill_phone = $OrderRs[0]["bill_phone"];
					// $OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign,'phone'=>$bill_phone];
					// OmanisendRequest('6942bff04b2a3e1daf65c63d',$OrderRs[0],$OtherData);
				} else {
					//echo $to_email;exit;
					//$OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date];
					$OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign];
					OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRs[0],$OtherData);

					//$OtherData = ['toMail' => config('Settings.ADMIN_MAIL'), 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment];
					$OtherData = ['toMail' => 'orders@maxaroma.com', 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign];
					OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRs[0],$OtherData);

					/*$OthData = ['toMail' => 'qualdev.devs@gmail.com', 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date];
					OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRs[0],$OthData);	*/
				}
			} else if($chkPaidStatus == 'Paid' && ($chkPaymentType == "PAYMENT_STRIPE" || $chkPaymentType == "PAYMENT_SPLIT" || $chkPaymentType == "PAYMENT_STRIPE_NORMAL")  && isset($OrderRs[0]['store_id']) && $OrderRs[0]['store_id'] > 0){
				//if(isset($OrderRs[0]['store_id']) && $OrderRs[0]['store_id'] > 0){
					//$to_email = "tempchecknew@gmail.com";
					$storeData = DB::table('pu_stores as s')->where('s.store_id','=',$OrderRs[0]['store_id'])->select('store_city','store_state')->first();
					$store_address = $storeData->store_city." ".$storeData->store_state;
					$bill_phone = $OrderRs[0]["bill_phone"];
					$OtherData = ['toMail' => $to_email, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM_STORE,'phone'=>$bill_phone,'OrdDate'=>$OrdDate,'store_address'=>$store_address];
					OmanisendRequest('69f8b45dbe5da69308eeb38d',$OrderRs[0],$OtherData);

					// $bill_phone = $OrderRs[0]["bill_phone"];
					// $OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM, 'customernote' => $customer_comment, 'estimated_ship_date' => $estimated_date, 'customer_ip' => $OrderRs[0]["customer_ip"], 'shipping_insurance' => $shipping_insurance, 'shipping_signature' => $shipping_sign,'phone'=>$bill_phone];
					// OmanisendRequest('6942bff04b2a3e1daf65c63d',$OrderRs[0],$OtherData);
				//}
			}
			//SendMail($MailSubject,$ReceiptMailBody,"nishits.qualdev@gmail.com",config('Settings.ADMIN_MAIL'),'',"");

			//31072023$scr = '<script type="application/json+trustpilot">{"recipientName": "'.$OrderRs[0]['bill_first_name'].'","recipientEmail": "'.$to_email.'","referenceId": "'.$OrderRs[0]['orders_no'].'"}</script>';
			//31072023$ReceiptMailBody .= $scr;
			//SendMail($MailSubject,$ReceiptMailBody,"maxaroma.com+b1ff7c3c82@invite.trustpilot.com",config('Settings.ADMIN_MAIL'),'',"nishits.qualdev@gmail.com,ravi.qualdev@gmail.com");
			//31072023SendMail($MailSubject,$ReceiptMailBody,"maxaroma.com+b1ff7c3c82@invite.trustpilot.com",config('Settings.ADMIN_MAIL'),'','');
            /** OMANISEND **/
        }

        Log::channel('order_receipt')->info('Order Receipt Email Sent for Order - '.$OrderID);

      //  $this->PageData["GOOGLE_ORDER_TRACKING"] = $GOOGLE_ORDER_TRACKING;
       // $this->PageData["GOOGLE_ORDER_TRACKING_GTM"] = '<script type="text/javascript">'.$GOOGLE_ORDER_TRACKING_GTM.'</script>';
		$this->PageData["GOOGLE_ORDER_TRACKING"] = "";
		 $this->PageData["GOOGLE_ORDER_TRACKING_GTM"] = "";
        $GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
        if($GiftCouponInfo && count($GiftCouponInfo) > 0 && $GiftCouponInfo['Code'] != '' && $GiftCouponInfo['Value'] > 0 )
        {
            $gcRES = GiftCertificate::where('gc_code','=',$GiftCouponInfo['Code'])->where('status','=','1')->get();
            if($gcRES && $gcRES->count() > 0)
            {
                $gc_remaining_value = NumberFormat($GiftCouponInfo["Remaining_Value"]);

                if($GiftCouponInfo['Code'] != '' && $GiftCouponInfo['Value'] > 0 )
                {
                    $upgGif = array (
                                    'remaining_value' => $gc_remaining_value,
                                    'last_used_date'  => date('Y-m-d H:i:s')
                                );
                    $udpGift = GiftCertificate::where('gc_code','=',$GiftCouponInfo['Code'])->update($upgGif);
                }
                $freeshippinginfo = '';
                if(config('Settings.FREESHIPPING_VALUE')!="" && config('Settings.FREESHIPPING_VALUE') > 0)
                {
                    $freeshippinginfo .= '<strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders';
                }
                $gcRESNew = GiftCertificate::where('gc_code','=',$GiftCouponInfo['Code'])->where('status','=','1')->get();

                $res_mail = GetMailTemplate("GC_USAGE");
                $to_recipient = $gcRES[0]['recipient_email'];
                $GC_Subject = $res_mail[0]['subject'];
                $GC_Subject = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$GC_Subject);

                $GCMailBody = $res_mail[0]['mail_body'];
                $GCMailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$GCMailBody);
                $GCMailBody = str_replace('{$recipient_name}',$gcRES[0]['recipient_name'],$GCMailBody);
                $GCMailBody = str_replace('{$gc_code}',$GiftCouponInfo['Code'],$GCMailBody);
                $GCMailBody = str_replace('{$gc_amount}',$GiftCouponInfo['Value'],$GCMailBody);
                $GCMailBody = str_replace('{$remaining_value}',$gcRESNew[0]['remaining_value'],$GCMailBody);
                $GCMailBody = str_replace('{$TOLL_FREE_NO}',config('Settings.CONTACT_PHONE_NO'),$GCMailBody);
                $GCMailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$GCMailBody);
                $GCMailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$GCMailBody);
                $GCMailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$GCMailBody);

                $this->PageData["recipient_name"] = $gcRES[0]['recipient_name'];
                $this->PageData["gc_code"] = $GiftCouponInfo['Code'];
                $this->PageData["gc_amount"] = $GiftCouponInfo['Value'];
                $this->PageData["remaining_value"] = $gcRESNew[0]['remaining_value'];
                $this->PageData["TOLL_FREE_NO"] = config('global.CONTACT_PHONE_NO');
                $this->PageData['Site_URL'] = config('global.SITE_URL');
                $this->PageData["freeshippinginfo"] = $freeshippinginfo;
                if(config('global.OMNISEND_PROG') == false)
                {
                    SendMail($GC_Subject,  $GCMailBody, $to_recipient, config('Settings.ADMIN_MAIL'));
                } else {
                   /** OMANISEND **/
                    $OtherData = ['gc_code' => $GiftCouponInfo['Code'], 'gc_amount' => $GiftCouponInfo['Value'], 'remaining_value' => $gcRESNew[0]['remaining_value']];
                    OmanisendRequest('61fbcf88bf58ef001efc0243',$gcRES[0],$OtherData);
                    /** OMANISEND **/
                }
            }
        }

		$RIO_EBAY_COMMERCE_CODE .= "_roi.push(['_trackTrans']);";
		$this->PageData['RIO_EBAY_COMMERCE_CODE'] = '<script type="text/javascript">'.$RIO_EBAY_COMMERCE_CODE.'</script>';

		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		if(config('Settings.WHOLESALE_CREDIT_LIMIT') == 'Yes' && $CreditDiscount>0 && Session::get('etype')=="M" && Session::get('is_dropshipper')!='Yes' && Session::has('sess_icustomerid')){
			$CustomerRemainingCreditAmount = Session::get('ShoppingCart.customer_remaining_credit_amount');
			$upgCustomer = array ('credit_limit' => $CustomerRemainingCreditAmount);
			$udpCL = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);

			$cpay_status = 'Paid';
			if($OrderRs[0]['payment_type'] == 'PAYMENT_MOC' || $OrderRs[0]['payment_type'] == 'PAYMENT_WT' || $OrderRs[0]['payment_type'] =='PAYMENT_PAYWITHAMAZON'){
				$cpay_status = 'Unpaid';
			}

			$upgOrder = array ('pay_status' => $cpay_status);
			Order::where('orders_id','=',$OrderRs[0]['orders_id'])->update($upgOrder);

			$log_insert = array (
				'orderid' => $OrderRs[0]['orders_id'],
				'custid' => Session::get('sess_icustomerid'),
				'current_credit_limit' => $OrderRs[0]['cust_current_credit_limit'],
				'apply_credit' => $OrderRs[0]['apply_credit'],
				'remaining_credit' => $OrderRs[0]['remaining_credit']
			);
			CustomerCreditLimitLogs::create($log_insert);
			Session::put('ShoppingCart.credit_limit_discount', 0.00);
            Session::put('ShoppingCart.customer_remaining_credit_amount', 0.00);
		}

		if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') == 'Wholesaler')
		{
			$ds_res = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();

			$DropshipperAccountDetails = $this->GetDropshipperAccountDetails();
			if($ds_res[0]['available_funds']>0 && $DropshipperAccountDetails['fund_available'] == 'Yes')
			{
				if($OrderRs[0]['payment_type'] == 'PAYMENT_DS')
				{
					$remaining_fund = $DropshipperAccountDetails['remaining_fund'];
					$upgCustomer = array ('available_funds' => $remaining_fund);
					$udpDS = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);
				}
				$fpay_status = 'Paid';
				if($OrderRs[0]['payment_type'] == 'PAYMENT_MOC' || $OrderRs[0]['payment_type'] == 'PAYMENT_WT' ||  $OrderRs[0]['payment_type'] =='PAYMENT_PAYWITHAMAZON'){
					$fpay_status = 'Unpaid';
				}
				$upgOrder2 = array ('pay_status' => $fpay_status);
				Order::where('orders_id', '=',$OrderRs[0]['orders_id'])->update($upgOrder2);
			}
		}

        if(config('global.YOTPO_PROG') == false)
        {
            if(Session::has('ShoppingCart.Reward_array'))
            {
                if(strtolower(Session::get('eusertype') ?? '')=='retailer') {
                    $rewardarray_use = array();
                    $rewardarray_use = Session::get('ShoppingCart.Reward_array');

                    if(is_array($rewardarray_use) && !empty($rewardarray_use)) {
                        //$res_client = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
						$res_client 	= Customer::where('customer_id','=',$CustomerID)->get();
                        $FinalReaminRewardpoint = 0;
                        if((int)$rewardarray_use['RemainRewardPoint']>0  && $rewardarray_use['RewardDiscount']>0) {
                             $FinalReaminRewardpoint = (int)$rewardarray_use['RemainRewardPoint'];
                        }else {
                             $FinalReaminRewardpoint = $res_client[0]['iRewardpoint'];
                        }
                        $upgCustomer = array ('iRewardpoint' => $FinalReaminRewardpoint);
                        //Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);
						Customer::where('customer_id','=',$CustomerID)->update($upgCustomer);

                        if($rewardarray_use['AppliedRewardPoint'] > 0)
                        {
                            $InsertCustomer = array (
                                                'customer_id' 	=> Session::get('sess_icustomerid'),
                                                'note'		  	=> "Deduct Reward Point By Order",
                                                'iRewardpoint'	=> $rewardarray_use['AppliedRewardPoint'],
                                                'Order_No'		=> $OrderRs[0]["orders_no"]
                                               );
                            RewardPoint::create($InsertCustomer);
                        }
                    }
                }
            }
        }

		$cnt_row = 0;
		$tempCart = array();
		//if($isPayPalProduct == 'N'){
		if($isPayPalProduct == 'N' && Session::has('ShoppingCart.Cart')){
			$tempCart = Session::get('ShoppingCart.Cart');
			$cnt_row  = count($tempCart);
		}
		$Rewardchk_arr = array();

		if($cnt_row > 0) {
			$DealTotalprice = 0;
			for($dl=0; $dl<$cnt_row; $dl++) {
				$dealofdayRS= Dealofweek::where('status','=','1')
								->where('start_date','<=',date('Y-m-d H:i'))->where('end_date','>=',date('Y-m-d H:i'))
								->where('product_sku','=',$tempCart[$dl]['SKU'])
								->limit(1)->get();

				if($dealofdayRS && $dealofdayRS->count()>0) {
					$DealTotalprice = $DealTotalprice+$tempCart[$dl]['TotPrice'];
				}else {
					$Rewardchk_arr[] = $tempCart[$dl]['SKU'];
				}
			}
		}

        if(config('global.YOTPO_PROG') == false)
        {
            if(strtolower(Session::get('eusertype') ?? '')=='retailer' && Session::get('etype') == 'M' && Session::has('ShoppingCart.RewardPointItemWiseTotal'))
            {
                $Rewardpoint = Session::get('ShoppingCart.RewardPointItemWiseTotal');
                if($Rewardpoint>0)
                {
                    //$res_client = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
					$res_client = Customer::where('customer_id','=',$CustomerID)->get();
                    $FinalRewardpoint = $Rewardpoint + $res_client[0]['iRewardpoint'];
                    $upgCustomer = array ('iRewardpoint' => $FinalRewardpoint);
                    //Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);
					Customer::where('customer_id','=',$CustomerID)->update($upgCustomer);
                    $InsertCustomer = array (
                                            'customer_id' 	=> $CustomerID, //Session::get('sess_icustomerid'),
                                            'note'		  	=> "Reward Point Added By Order",
                                            'iRewardpoint'	=> $Rewardpoint,
                                            'Order_No'		=> $OrderRs[0]["orders_no"]
                                   );
                    RewardPoint::create($InsertCustomer);
                }
            }
        }

		//$cust_res1 	= Order::where('customer_id','=',Session::get('sess_icustomerid'))->get();
		$cust_res1 	= Order::where('customer_id','=',$CustomerID)->get();

		if($cust_res1 && $cust_res1->count()<=0)
		{
			//$cust_res = Customer::where('customer_id','=',Session::get('sess_icustomerid'))
			//			->where('registration_type','=','M')->where('status','=','1')->get();
			$cust_res = Customer::where('customer_id','=',$CustomerID)
						->where('registration_type','=','M')->where('status','=','1')->get();

			$referenced_by = "";
			if($cust_res && $cust_res->count()>0 )
			{
				$referenced_by = $cust_res[0]['referenced_by'];
				$new_str_arr = explode('#', $referenced_by);
				$id = $new_str_arr[0];
				$Remail =  $new_str_arr[1];
			}
			if($referenced_by!='')
			{
				$referralRes = ReferFriend::where('customer_id','=',$id)->where('receiver','=',$Remail)->limit(1)->get();
				$datetime = date('Y-m-d H:i:s');
				if($referralRes && $referralRes->count()>0)
				{
					//Condition For Adding Referral Point First Time When Refferal Client Clicks in Link and Updating Referrel Customer Status//
					if($referralRes[0]['is_sender_notified']=='N')
					{
						$saveData['is_sender_notified'] = 'Y';
						$saveData['refer_datetime']	 	= $datetime;
						$where = "customer_id= '".$id."' AND receiver = '".$Remail."'";
						ReferFriend::where('customer_id','=',$id)->where('receiver','=',$Remail)->update($saveData);

                        if(config('global.YOTPO_PROG') == false)
                        {
                            // Query For Updating Reward Point in Customer Table //
                            $cust_res = Customer::where('customer_id','=',$id)->get();
                            $reward_point = $cust_res[0]['iRewardpoint']+100;
                            $custdata['iRewardpoint'] = $reward_point;

                            $where = "customer_id= '".$id."'";
                            Customer::where('customer_id','=',$id)->update($custdata);

                            $InsertCustomer = array (
                                                'customer_id' 	=> $id,
                                                'note'		  	=> "Reward Point For Adding Referral Point First Time",
                                                'iRewardpoint'	=> 100,
                                                'Order_No'		=> $OrderRs[0]["orders_no"]
                                            );
                            RewardPoint::create($InsertCustomer);
                        }
					}
				}
			}
		}

		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			Shoppingcart::where('customer_id','=',Session::get('sess_icustomerid'))->delete();
		}

		$GTMDATA = ['page' => 'order_receipt', 'pagetype' => 'purchase'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);

		// if(Auth::user() && Session::has('ShoppingCart.YotpoRewardRedeemDiscount') && trim(Auth::user()->email)!='' && trim(Session::get('ShoppingCart.YotpoRewardCode'))!=''){
		// 	//this session creates when redeem action done using dropdown on checkout page
		// 	if(Session::has('ShoppingCart.YotpoRewardCode') && trim(Session::get('ShoppingCart.YotpoRewardCode'))!=''){
		// 		//Here coupon get deactive or cancel
		// 		$yotpo_coupon = trim(Session::get('ShoppingCart.YotpoRewardCode'));
		// 		$coupon_status['status'] = '0';
		// 		$coupon_customer_email = trim(Auth::user()->email); //'qqualdev@gmail.com';
		// 		$updateCoupon = Coupon::where('coupon_number','=',$yotpo_coupon)->where('customer_email','=',$coupon_customer_email)->where('start_date','=',DB::raw('curdate()'))->update($coupon_status);
		// 	}
		// 	Session::forget('ShoppingCart.YotpoRewardRedeemDiscount');
		// 	Session::forget('ShoppingCart.YotpoRewardCode');
		// 	Session::forget('CartSuccess');
		// }

		if($isPayPalProduct == 'Y'){
			Session::forget('TOKEN');
			Session::forget('PAYPAL_PAYER_ID');
			Session::forget('PayPalToken');
			Session::forget('token');
			Session::forget('nvpReqArray');
			Session::forget('shipping_insurance');
			Session::forget('shipping_insurance_charge');
			//Session::forget('ShoppingCart');
			Session::forget('AMAZON_ACCESS_TOKEN');
			Session::forget('StripePaymentType');

			Session::forget('ShoppingCart.EstimatedDeliveryDate');
			Session::forget('ShoppingCart.VendorShippingDateVal');
			Session::forget('ShoppingCart.YotpoFreeGiftCoupon');
			Session::forget('ShoppingCart.SubTotal');
			Session::forget('ShoppingCart.TotalItemInCart');
			Session::forget('ShoppingCart.RewardPointItemWiseTotal');
			Session::forget('ShoppingCart.GiftWrapping');
			Session::forget('ShoppingCart.BillingAddress');
			Session::forget('ShoppingCart.BillingAsShipping');
			Session::forget('ShoppingCart.ShippingAddress');
			Session::forget('ShoppingCart.Tax');
			Session::forget('ShoppingCart.IsMaxaromaTwoDelivery');
			Session::forget('ShoppingCart.ISMaxTwoItem');
			Session::forget('ShoppingCart.IsVenderItem');
			Session::forget('ShoppingCart.IsCosmo');
			Session::forget('ShoppingCart.IsNandansons');
			Session::forget('ShoppingCart.IsPerfumePW');
			Session::forget('ShoppingCart.IsPCA');
			Session::forget('ShoppingCart.IsND');
			Session::forget('ShoppingCart.ISMax2dayVal');
			Session::forget('ShoppingCart.onlyGCPurchased');
			Session::forget('ShoppingCart.action');
			Session::forget('ShoppingCart.OnlyHead');
			Session::forget('ShoppingCart.Payment_Detail');
			Session::forget('ShoppingCart.OrderID');

			Cookie::forget('MY_SHOP_CART_COOKIE');
		} else {
			Session::forget('TOKEN');
			Session::forget('PAYPAL_PAYER_ID');
			Session::forget('PayPalToken');
			Session::forget('token');
			Session::forget('nvpReqArray');
			Session::forget('shipping_insurance');
			Session::forget('shipping_insurance_charge');
			Session::forget('ShoppingCart');
			Session::forget('AMAZON_ACCESS_TOKEN');
			Session::forget('StripePaymentType');
			Session::forget('PaypalEmail');
			Session::forget('Paypalcity');
			Session::forget('Paypalcountry');
			Session::forget('Paypalstate');
			Session::forget('Paypalzipcode');
			Cookie::forget('MY_SHOP_CART_COOKIE');
			Session::forget('ShoppingCart.OrderID');
			Session::forget('ShoppingCart.StoreOrderID');
			Session::forget('ShoppingCart.StoreCashSplitPaymentId');
			Session::forget('ShoppingCart.WebsiteCashSplitPaymentId');
			Session::forget('ShoppingCart.StoreCashPaymentId');
		}
        /** OMANISEND **/
        OmanisendRequest('removeCart');
        /** OMANISEND **/

        Log::channel('order_receipt')->info('Session data expired for Order - '.$OrderID);

        $this->PageData['MainOrder'] = $OrderRs[0];
		$Charges = [];
		$Charges['shipping'] = ['field' => 'shipping_amt','label' => 'Shipping Charge'];
		$Charges['amazon_shipping'] = ['field' => 'OrderShippingCharge','label' => 'Shipping Charge'];
		$Charges['tax'] = ['field' => 'tax','label' => 'Sales Tax'];
		$Charges['amazon_tax'] = ['field' => 'OrderSalesTax','label' => 'Sales Tax'];
		$Charges['gift_charge'] = ['field' => 'gift_charge','label' => 'Gift Wrapping Charge'];
		$Charges['amazon_gift_charge'] = ['field' => 'GiftWrappingCharge','label' => 'Gift Wrapping Charge'];
		$Charges['shipping_insurance'] = ['field' => 'route_shipping_insurance_charge','label' => 'Shipping Insurance Charge'];
		$Charges['shipping_signature'] = ['field' => 'shipping_signature','label' => 'Shipping Signature'];

		$Discounts = [];
		$AutoDiscountLabel = (config('Settings.AUTO_DISCOUNT_FLAG') != '' ? config('Settings.AUTO_DISCOUNT_FLAG') : 'Auto Discount');
		$QuantityDiscountLabel = (config('Settings.QUANTITY_DISCOUNT_FLAG') != '' ? config('Settings.QUANTITY_DISCOUNT_FLAG') : 'Quantity Discount');
		$BogoDiscountLabel = (config('Settings.BOGO_DISCOUNT_FLAG') != '' ? config('Settings.BOGO_DISCOUNT_FLAG') : 'Bogo Discount');
		$Discounts['auto_discount'] = ['field' => 'auto_discount','label' => $AutoDiscountLabel];
		$Discounts['quantity_discount'] = ['field' => 'quantity_discount','label' => $QuantityDiscountLabel];
		$Discounts['coupon_amount'] = ['field' => 'coupon_amount','label' => 'Coupon Discount'];
		$Discounts['gc_amount'] = ['field' => 'gc_amount','label' => 'Gift Certificate Discount'];
		$Discounts['refer_amount'] = ['field' => 'refer_amount','label' => 'Refer Discount'];
		$Discounts['bogo_discount'] = ['field' => 'bogo_discount','label' => $BogoDiscountLabel];
		$Discounts['apply_credit'] = ['field' => 'apply_credit','label' => 'Credit Discount'];
		$Discounts['reward_discount'] = ['field' => 'reward_discount','label' => 'Yotpo Reward Discount'];

		$this->PageData['AllCharges'] = $Charges;
		$this->PageData['AllDiscounts'] = $Discounts;
		$this->PageData['OrderDetails'] = $ProdDetails;

        Log::channel('order_receipt')->info('Order Receipt Ended for Order - '.$OrderID);
        Log::channel('order_receipt')->info('--------------------------------');
		//return view('checkout.'.$Template)->with($this->PageData);
        return view('checkout.order-receipt')->with($this->PageData);
	}

	private function CJCall($OrderRs,$OrderDetailRs)
	{

		// Log::info('CJ cookie debug before read', [
		// 	'order_id' => $OrderRs['orders_id'],
		// 	'request_url' => request()->fullUrl(),
		// 	'request_cookie_cje' => request()->cookie('cje'),
		// 	'php_cookie_cje' => $_COOKIE['cje'] ?? null,
		// 	'all_cookies' => request()->cookies->all(),
		// ]);

		//$cjevent = $_COOKIE['cje'] ?? request()->cookie('cje'); //request()->cookie('cje');
		$cjevent = $_COOKIE['cje'] ?? request()->cookie('cje') ?? '';

		if (empty($cjevent)) {
			Log::warning('CJ S2S skipped: cjevent missing', [
				'order_id' => $OrderRs['orders_id']
			]);
			return;
		}
		//$cjevent = '656e8fa049ec11ea8237023d0a240612';

		$AllDiscounts = $this->GetAllDiscounts();
        $TotalDiscount = $AllDiscounts['TotalDiscount'];

		// $critieostr .=  '{ id: "'.$OrderDetailRs[$p]['sku'].'", price: "'.$OrderDetailRs[$p]['price'].'", quantity: "'.$OrderDetailRs[$p]['quantity'].'" } ,';
        //     $roktstr.= "{sku:'".$OrderDetailRs[$p]['sku']."',quantity:'".$OrderDetailRs[$p]['quantity']."',productname:'".addslashes($OrderDetailRs[$p]['product_name'])."',price:'".$OrderDetailRs[$p]['price']."',majorcat:'',minorcat:'',currency:'USD'},";

		$params = [
			'CID'      => '1563116',//'1563116',//'1564227',
			'TYPE'     => '426683',//'426683',//'431952',
			'METHOD'   => 'S2S',
			'CJEVENT'  => $cjevent,
			'OID'      => $OrderRs['orders_no'],
			'CURRENCY' => 'USD',
			'DISCOUNT' => number_format($TotalDiscount ?? 0, 2, '.', ''),
		];

		$i = 1;
		for ($p = 0; $p < $OrderDetailRs->count(); $p++) {

			$sku      = $OrderDetailRs[$p]['sku'];
			$price    = $OrderDetailRs[$p]['price'];
			$quantity = $OrderDetailRs[$p]['quantity'];
			$itemwise_total =  $OrderDetailRs[$p]['total'];
			$itemwise_actual_price = $OrderDetailRs[$p]['actual_price'];

			if($itemwise_total > 0 && $itemwise_actual_price > 0){
				$itemwise_discount = $itemwise_total - $itemwise_actual_price;
			} else {
				$itemwise_discount = 0;
			}

			$params["ITEM{$i}"] = $sku;
			$params["AMT{$i}"] = number_format((float) $price, 2, '.', '');
			$params["QTY{$i}"] = (int) $quantity;
			$params["DCNT{$i}"] = number_format((float) $itemwise_discount, 2, '.', '');

			$i++;
		}

		if (!empty($OrderRs['Second_coupon_id'])) {
			$params['COUPON'] = $OrderRs['Second_coupon_id'];
		}

		// if (!empty($OrderRs['customer_status'])) {
		// 	$params['CUST_STATUS'] = $OrderRs['customer_status'];
		// }

		$params['eventTime'] = now()->utc()->format('Y-m-d\TH:i:s\Z');

		try {
			//Http::timeout(2)->get('https://www.emjcd.com/u', $params);
			$response = Http::timeout(10)->get('https://www.emjcd.com/u',$params);

			Log::info('CJ S2S fired successfully', [
				'order_id' => $OrderRs['orders_id'],
				'payload'  => $params,
				'http_code'  => $response->status(),
    			'response'   => $response->body()
			]);

		} catch (\Throwable $e) {
			Log::error('CJ S2S failed', [
				'order_id' => $OrderRs['orders_id'],
				'error'    => $e->getMessage()
			]);
		}
	}

	public function SetGuestToMember(Request $request)
	{
		if($request->ajax())
		{
			$action = $request->action;
			$custEmail = Session::get('sess_useremail');
			if($action == 'guest_to_member')
			{
				$custdata = array(
					"password"=>$request->password,
					"registration_type" => 'M'
				);
				$CustInfo = Customer::where('email','=',$custEmail)->first();
				if($CustInfo)
				{
					if($request->password == $CustInfo->password)
					{
						return response()->json(['success' => '0', 'message' => 'This password has been already set. Please try new password.']);
					}
					$CustInfo->password = $request->password;
					$CustInfo->registration_type = 'M';
					if(config('global.YOTPO_PROG') == false)
					{
						$CustInfo->iRewardpoint = '150';
					}
					$CustInfo->save();
					Session::put('etype','M');
					if(config('global.YOTPO_PROG') == false)
					{
						$RewardPoints = array();
						$RewardPoints["customer_id"] = $CustInfo->customer_id;
						$RewardPoints["note"] = "Reward Point Added By Register";
						$RewardPoints["iRewardpoint"] = 150;
						$UserRewardPoints = RewardPoint::create($RewardPoints);
					}
					return response()->json(['success' => '1', 'message' => 'You are become a member successfully. <a href="'.url("/").'"><strong>Continue Shopping</strong></a>']);
				} else {
					return response()->json(['success' => '0', 'message' => 'Customer not found. Please try again.']);
				}
			}
			return response()->json(['success' => '0','message' => 'Something went wrong. Please try again.']);
		}
	}

	public function CustomerAddFund(Request $request)
	{
		$Page = '';
		if(isset($request->page_from) && $request->page_from != '')
		{
			$Page = $request->page_from;
		}
		if(isset($request->fund_type) && $request->fund_type != '')
		{
			if($request->fund_type == 'card')
			{
				return redirect('stripe/addfund/'.$Page);
				exit;
			}
		}
	}

	public function PaypalFundResponse(Request $request)
	{
		if(isset($request->status) && $request->status == 'Completed'){
			$msg = "Fund added to your account successfully.";
			Session::flash('fund_msg',$msg);
		}else{
			$msg = "Unable to process payment.";
			Session::flash('fund_msg',$msg);
		}

		if(isset($request->pagefrom) && $request->pagefrom == 'dropshipfund'){
			return redirect('/dropshipper-fund-summary.html');
		}elseif(isset($request->pagefrom) && $request->pagefrom == 'billing'){
			return redirect('/checkout');
		} else {
			return redirect('/shoppingcart');
		}
	}

	public function PaypalFundProcess(Request $request)
	{
		$fp = fopen(config('global.PHYSICAL_PATH').'paypal_response.txt', 'a');
		fwrite($fp, 'CustomerID : '.$request->uid.'\n');
		fwrite($fp, 'Payment Status : '.$request->payment_status.'\n');
		fwrite($fp, 'Payment Gross : '.$request->payment_gross.'\n');
		fwrite($fp, 'TXNID : '.$request->txn_id.'\n');
		fwrite($fp, '--------------------------------\n');
		fclose($fp);
		echo $request->uid ."<br/>";

		if(isset($request->uid) && $request->uid != '')
		{
			if(isset($request->payment_status) && $request->payment_status == 'Completed')
			{
				$fundsres = Customer::where('customer_id', '=', $request->uid)->get();
				$insertArray = array(
					"customer_id" => $request->uid,
					"cust_available_fund" => $fundsres[0]['available_funds'],
					"cust_requested_fund" => $request->payment_gross,
					"paypal_ipn_response" => serialize($request->all()),
					"payment_status"      => $request->payment_status,
					"txn_id"              => $request->txn_id
				);

				$result = PaypalIpnLog::create($insertArray);

				if($result){
					$total_available_funds = $fundsres[0]['available_funds'] + $request->payment_gross;
					$custdata = array(
						"available_funds"=>$total_available_funds
					);
					Customer::where('customer_id','=',$request->uid)->update($custdata);
				}
			}
		}
	}

	public function AmazonCheckout(Request $request)
	{
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];
		$this->PageData['JSFILES'] = ['billing.js'];

		if(!Session::has('ShoppingCart.Cart') || count(Session::get('ShoppingCart.Cart')) == 0)
			return redirect('/shoppingcart');
		$this->SetCheckoutCommonDetails($request);
		$this->PageData['meta_title'] = "Billing and Shipping Information :: ".config('Settings.SITE_TITLE');
		$this->SetupCart();
		$GTMDATA = ['page' => 'billing_amazon', 'pagetype' => 'cart'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		$GA4 = googleAnalyticsGA4("BeginCheckout",Session::get('ShoppingCart.Cart'),$this->GetNetTotal(),$this->GetAllCoupons('CouponCode'));
		$this->PageData['GA4'] = $GA4;
		$GAPayment 	= googleAnalyticsGA4("PaymentMethods",Session::get('ShoppingCart.Cart'), $this->GetNetTotal(),$this->GetAllCoupons('CouponCode'),'','Pay With Amazon');
		$this->PageData["GA4Amazon"] =  $GAPayment;
		return view('checkout.amazon-checkout')->with($this->PageData);
	}

	public function AmazonFundCheckout(Request $request)
	{
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];
		$this->PageData['JSFILES'] = ['billing.js'];

		$this->PageData['meta_title'] = "Billing and Shipping Information :: ".config('Settings.SITE_TITLE');
		$this->SetAmazonConfig('fund');
		return view('checkout.amazon-fund-checkout')->with($this->PageData);
	}

	public function ApplyFreeGift($GiftValue,$GiftFrom,$GiftTo,$GiftMessage)
	{
		$log['GiftValue'] = $GiftValue;
		$log['GiftFrom'] = $GiftFrom;
		$log['GiftTo'] = $GiftTo;
		$log['GiftMessage'] = $GiftMessage;
		addLog("ApplyFreeGiftStart",$log);
		Session::put('ShoppingCart.FreeGift',$GiftValue);
		//============Added Code Date 7-Feb-2015 Start Here ==============//
		Session::put('ShoppingCart.GiftFrom', $GiftFrom);
		Session::put('ShoppingCart.GiftTo', $GiftTo);
		Session::put('ShoppingCart.GiftMessageCustomer', $GiftMessage);
		//============Added Code Date 7-Feb-2015 End Here ==============//
		$Msg =  "Free Gift is applied successfully";
		$log['message'] = $Msg;
		addLog("FreeGiftMessage",$log);
		return response()->json(['success' => '1', 'message' => $Msg]);
	}

	public function GetShippingOptionsJson(Request $request)
	{
		if($request->zip != ""){
			$ship_zip = $request->zip;
			$ship_state = $request->state;
			$ship_country = $request->country;
		} else {
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			//if(Auth::user())
			if($normaluser)
			{
				$ship_zip = $normaluser->zip; //Auth::user()->zip;
				$ship_state = $normaluser->state; //Auth::user()->state;
				$ship_country = $normaluser->country; //Auth::user()->country;
			} else {
				$ship_zip = "";
				if(isset($request->zip) && $request->zip!='')
				{
					$ship_zip = $request->zip;
				}
				$ship_state = "";
				if(isset($request->state) && $request->state!='')
				{
					$ship_state = trim($request->state);
				}

				$ship_country = "";
				if(isset($request->country) && $request->country!='')
				{
					$ship_country = trim($request->country);
				}
			}
		}
		$shipping_modes = ShippingMode::where('status','=','1')->orderBy('display_position')->get();
		$show_shipping_modes = 0;
		$shipping_mode_tmp_arr = [];
		if($shipping_modes && $shipping_modes->count($shipping_modes) > 0)
		{
			foreach($shipping_modes as $shipping_mode)
			{
				$shipping_charge = $this->CalculateShippingCharge($ship_zip,$ship_state,"US",$shipping_mode->shipping_mode_id,"Yes");
				if($shipping_charge > 0 )
				{
					$shipping_mode_tmp_arr[] = array(
						'id'=>(string)$shipping_mode->shipping_mode_id,
						'label'=>strip_tags($shipping_mode->type),
						'detail'=>strip_tags($shipping_mode->type),
						'amount'=>round($shipping_charge*100)
					);
				}
			}
		}
		return response()->json(['shippingmodes' => $shipping_mode_tmp_arr]);
	}
		public function StripButtonResponse(Request $request)
	{
		//echo "<pre>"; print_r($request['shippingAddress']);
		$log['stripe_button_response_request'] = json_encode($request->all());
		addLog('StripeButtonResponseStart', $log);
		$Payment_Type = '';
		$phoneval = '';
		if(isset($request->paymentMethod))
		{
				Stripe::setApiKey(env('STRIPE_SECRET'));
				$MethodRes = \Stripe\PaymentMethod::retrieve($request->paymentMethod);

				$log['stripe_button_response_method_res'] = json_encode($MethodRes);
				addLog('StripeButtonResponseMethodRes', $log);

				$Wallet = "Credit Card";
				if(isset($MethodRes->card['wallet']['type']))
				{
					if($MethodRes->card['wallet']['type'] == 'google_pay'){
						$Wallet = "Google Pay";
						$Payment_Type = "STRIPE_GOOGLEPAY";
					}
					if($MethodRes->card['wallet']['type'] == 'apple_pay'){
						$Wallet = "Apple Pay";
						$Payment_Type = "STRIPE_APPLEPAY";
					}
				}
				Session::put('StripePaymentType',$Wallet);
				Session::put('PayMethodRes',json_encode($MethodRes));

		}

		$data = $request->all();
		$payerPhone = $data['payerPhone'] ?? '';

		$Status = "fail";
		if(isset($Wallet) && $Wallet=="Credit Card")
		{
			$Status = "fail";
		}
		else
		{
			$CustomerEmail = '';
			if(isset($request->payerEmail) && $request->payerEmail!='')
			{
				$CustomerEmail = $request->payerEmail;
			}
		if(isset($request->stepfrom) && $request->stepfrom=='laststep' && isset($MethodRes['billing_details']['address']))
		{
			$Status = 'success';
		}
		else
		{
			if(isset($MethodRes['billing_details']['address']))
			{

			$Billing = $request['shippingAddress'];
			$frsname = '';
			$lrsname = '';
			if(isset($Billing["recipient"]) && $Billing["recipient"]!='')
			{
				$fnameArr = explode(" ",$Billing["recipient"]);

				if(isset($fnameArr[0]) && $fnameArr[0]!='')
					$frsname = $fnameArr[0];

				if(isset($fnameArr[1]) && $fnameArr[1]!='')
					$lrsname = $fnameArr[1];
			}
			$AddressLine1 = '';
			if(isset($Billing['addressLine'][0]) && $Billing['addressLine'][0]!='')
			{
				$AddressLine1 = $Billing['addressLine'][0];
			}
			$AddressLine2 = '';
			if(isset($Billing['addressLine'][1]) && $Billing['addressLine'][1]!='')
			{
				$AddressLine2 = $Billing['addressLine'][1];
			}
			$phoneval = (isset($Billing['phone']) ? $Billing['phone'] : '');
			if(empty($phoneval))
			{
				$phoneval = $payerPhone;
			}

			$newrequest = [
				'bill_country' => (isset($Billing['country']) ? $Billing['country'] : ''),
				'bill_fname' => $frsname,
				'bill_lname' => $lrsname,
				'bill_company' => (isset($Billing['organization']) ? $Billing['organization'] : ''),
				'bill_address1' => $AddressLine1,
				'bill_address2' => $AddressLine2,
				'bill_city' => (isset($Billing['city']) ? $Billing['city'] : ''),
				'bill_state' => (isset($Billing['region']) ? $Billing['region'] : ''),
				'bill_zip' => (isset($Billing['postalCode']) ? $Billing['postalCode'] : ''),
				'bill_phone' => $phoneval, //(isset($Billing['phone']) ? $Billing['phone'] : ''),
				'bill_email' => $CustomerEmail,
				'bill_cemail' => $CustomerEmail,
				'sameasbill' => 'Yes'
			];

			$this->SetBillingAddress($newrequest);
			$this->SetShippingAddress($newrequest);

			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}

			//if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			if(!$normaluser && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			{
				$this->SetGuestCustomer($newrequest);
			} else {
				$this->CustomerInfoUpdate($newrequest);
			}

			$Status = 'success';
			}
		}
			$paymentMethods = $request->paymentMethod;

		}

		if(Session::has("PayMethodRes") && Session::get("PayMethodRes") != '' && Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID')!=''){
			$respShipping = Session::get('ShoppingCart.ShippingAddress');
			$respBilling  = Session::get('ShoppingCart.BillingAddress');
			$OrderID = Session::get('ShoppingCart.OrderID');
			$PaymentResponse = Session::get("PayMethodRes");
			$stepfrom = '';
			if(isset($request->stepfrom) && $request->stepfrom!=''){
				$stepfrom = $request->stepfrom;
			}

			if(isset($respShipping['first_name']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['first_name']))
			{
				$respShipping['first_name'] = $this->transliterate($respShipping['first_name']);
			}
			if(isset($respShipping['last_name']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['last_name']))
			{
				$respShipping['last_name'] = $this->transliterate($respShipping['last_name']);
			}
			if(isset($respShipping['company']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['company']))
			{
				$respShipping['company'] = $this->transliterate($respShipping['company']);
			}
			if(isset($respShipping['email']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['email']))
			{
				$respShipping['email'] = $this->transliterate($respShipping['email']);
			}
			if(isset($respShipping['address1']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['address1']))
			{
				$respShipping['address1'] = $this->transliterate($respShipping['address1']);
			}
			if(isset($respShipping['address2']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['address2']))
			{
				$respShipping['address2'] = $this->transliterate($respShipping['address2']);
			}
			if(isset($respShipping['city']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['city']))
			{
				$respShipping['city'] = $this->transliterate($respShipping['city']);
			}
			if(isset($respShipping['zip']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['zip']))
			{
				$respShipping['zip'] = $this->transliterate($respShipping['zip']);
			}
			if(isset($respShipping['state']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['state']))
			{
				$respShipping['state'] = $this->transliterate($respShipping['state']);
			}
			if(isset($respShipping['country']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['country']))
			{
				$respShipping['country'] = $this->transliterate($respShipping['country']);
			}
			if(isset($respShipping['phone']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respShipping['phone']))
			{
				$respShipping['phone'] = $this->transliterate($respShipping['phone']);
			}
			if(isset($respBilling['first_name']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['first_name']))
			{
				$respBilling['first_name'] = $this->transliterate($respBilling['first_name']);
			}
			if(isset($respBilling['last_name']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['last_name']))
			{
				$respBilling['last_name'] = $this->transliterate($respBilling['last_name']);
			}
			if(isset($respBilling['company']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['company']))
			{
				$respBilling['company'] = $this->transliterate($respBilling['company']);
			}
			if(isset($respBilling['email']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['email']))
			{
				$respBilling['email'] = $this->transliterate($respBilling['email']);
			}
			if(isset($respBilling['address1']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['address1']))
			{
				$respBilling['address1'] = $this->transliterate($respBilling['address1']);
			}
			if(isset($respBilling['address2']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['address2']))
			{
				$respBilling['address2'] = $this->transliterate($respBilling['address2']);
			}
			if(isset($respBilling['city']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['city']))
			{
				$respBilling['city'] = $this->transliterate($respBilling['city']);
			}
			if(isset($respBilling['zip']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['zip']))
			{
				$respBilling['zip'] = $this->transliterate($respBilling['zip']);
			}
			if(isset($respBilling['state']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['state']))
			{
				$respBilling['state'] = $this->transliterate($respBilling['state']);
			}
			if(isset($respBilling['country']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['country']))
			{
				$respBilling['country'] = $this->transliterate($respBilling['country']);
			}
			if(isset($respBilling['phone']) && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $respBilling['phone']))
			{
				$respBilling['phone'] = $this->transliterate($respBilling['phone']);
			}
			if($phoneval != ''){
				$respShipping['phone'] = $respBilling['phone'] = $phoneval;
			}

			$updAray = array (
				'customer_id'	  	=> Session::get('sess_icustomerid'),
				'ship_first_name' 	=> isset($respShipping['first_name']) ? $respShipping['first_name'] : '',
				'ship_last_name' 	=> isset($respShipping['last_name']) ? $respShipping['last_name'] : '',
				'ship_company' 		=> isset($respShipping['company']) ? $respShipping['company'] : '',//$Shipping['company'],
				'ship_email' 		=> isset($respShipping['email']) ? $respShipping['email'] : '',//$Shipping['email'],
				'ship_address1' 	=> isset($respShipping['address1']) ? $respShipping['address1'] : '',
				'ship_address2' 	=> isset($respShipping['address2']) ? $respShipping['address2'] : '',
				'ship_city' 		=> isset($respShipping['city']) ? $respShipping['city'] : '',//$Shipping['city'],
				'ship_zip' 			=> isset($respShipping['zip']) ? $respShipping['zip'] : '',//$Shipping['zip'],
				'ship_state' 		=> isset($respShipping['state']) ? $respShipping['state'] : '',//$Shipping['state'],
				'ship_country' 		=> isset($respShipping['country']) ? $respShipping['country'] : '',//$Shipping['country'],
				'ship_phone' 		=> isset($respShipping['phone']) ? $respShipping['phone'] : '',//$Shipping['phone'],
				'bill_first_name' 	=> isset($respBilling['first_name']) ? $respBilling['first_name'] : '',
				'bill_last_name' 	=> isset($respBilling['last_name']) ? $respBilling['last_name'] : '',
				'bill_company' 		=> isset($respBilling['company']) ? $respBilling['company'] : '',//$Billing['company'],
				'bill_email' 		=> isset($respBilling['email']) ? $respBilling['email'] : '',//$Billing['email'],
				'bill_address1' 	=> isset($respBilling['address1']) ? $respBilling['address1'] : '',//$Billing['address1'],
				'bill_address2' 	=> isset($respBilling['address2']) ? $respBilling['address2'] : '',//$Billing['address2'],
				'bill_city' 		=> isset($respBilling['city']) ? $respBilling['city'] : '',//$Billing['city'],
				'bill_zip' 			=> isset($respBilling['zip']) ? $respBilling['zip'] : '',//$Billing['zip'],
				'bill_state' 		=> isset($respBilling['state']) ? $respBilling['state'] : '',//$Billing['state'],
				'bill_country' 		=> isset($respBilling['country']) ? $respBilling['country'] : '',//$Billing['country'],
				'bill_phone' 		=> isset($respBilling['phone']) ? $respBilling['phone'] : '',
				'payment_type' 		=> $Payment_Type,
				'payment_method' 	=> Session::get("StripePaymentType"),
				'transaction_info' 	=> serialize($PaymentResponse)." ".$stepfrom,
				'payment_gateway_response' => Session::get("PayMethodRes")?Session::get("PayMethodRes"):'',
				'paymentintentid' => Session::has("ShoppingCart.apple_google_paymentintentid") ? Session::get("ShoppingCart.apple_google_paymentintentid") : ''
			);

			$updAray["pay_status"] = "Paid";
			$updAray["status"] 		= "Pending";

			if(isset($request->isAbort) && $request->isAbort=="Yes")
			{

				$Status="fail";
				if(Session::has("ShoppingCart.apple_google_paymentintentid"))
				{
					$IntentIdVal =Session::get("ShoppingCart.apple_google_paymentintentid");
					$intent = \Stripe\PaymentIntent::retrieve($IntentIdVal);
					if(isset($intent->status) && $intent->status=="succeeded")
					{
						$Status="success";
						$updAray["payment_gateway_response"] = json_encode($intent);
					}

				}

			}

			if($Status=="fail")
			{
				$updAray["pay_status"] = "Unpaid";
				$updAray["status"] = "Declined";
			}

			$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
			$log['stripe_button_response_update_order'] = json_encode($updAray);
			addLog('StripeButtonResponseMethodRes', $log);

			if($Status=="fail")
			{
				Session::forget('StripePaymentType');
				Session::forget('PayMethodRes');
			}

			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." OrderPlace Respnse ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

		}

		return response()->json(['status' => $Status]);
	}

	public function updateApplePayLog(Request $request){
		$log['logaction'] = "";
		$log['log_data'] = "";
		$log['log_actions'] = "";
		$log['rtnm'] = "";

		if(isset($request->logaction) && $request->logaction!=''){
			$log['logaction'] = $request->logaction;
		}
		if(isset($request->log_data) && $request->log_data!=''){
			$log['log_data'] = $request->log_data;
		}
		if(isset($request->log_actions) && $request->log_actions!=''){
			$log['log_actions'] = $request->log_actions;
		}
		if(isset($request->rtnm) && $request->rtnm!=''){
			$log['rtnm'] = $request->rtnm;
		}
		addLog("UpdateApplePayLog",$log);
	}

	public function UpdatePaypalLog(Request $request){
		$logaction = '';
        $log_data = '';
        $log_actions = '';
        $rtnm = '';
		if(isset($request->logaction) && $request->logaction!=''){
			$logaction = $request->logaction;
		}
		if(isset($request->log_data) && $request->log_data!=''){
			$log_data = $request->log_data;
		}
		if(isset($request->log_actions) && $request->log_actions!=''){
			$log_actions = $request->log_actions;
		}
		if(isset($request->rtnm) && $request->rtnm!=''){
			$rtnm = $request->rtnm;
		}

		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." UpdatePaypalLog Start ".$logaction."--".$rtnm." :\n";
			$stringData .= date("m/d/Y H:i:s")." Data : ".$log_data." -- Actions :".$log_actions." :\n";
			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
				$stringData .= date("m/d/Y H:i:s")." Session Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
			}
			$stringData .= date("m/d/Y H:i:s")." UpdatePaypalLog End ".$logaction."--".$rtnm." :\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		if($logaction == "NoOrderId"){
			$test3 = @mail("nishits.qualdev@gmail.com","Paypal No Order Id - ".$rtnm,$log_data);
			$test3 = @mail("naresh.qualdev@gmail.com","Paypal No Order Id - ".$rtnm,$log_data);
			$err_msg ="Sorry, something went wrong, Please try again.";
			Session::flash('PlaceOrderError',$err_msg);
		}

	}

	public function PaypalOrderCollect(Request $request)
	{

		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." Before PayPal Order Insert :".json_encode($request->rtnm)."\n";
			$stringData .= date("m/d/Y H:i:s")." Before PayPal Order Insert :".json_encode($request->all())."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		$payer_email = "";
		$payer_address1 = "";
		$payer_state = "";
		$payer_city = "";
		$payer_country = "";
		$payer_postcode = "";
		$order_details = "";

		$order_invalid = "";

		if(isset($request->payer_address1) && $request->payer_address1!=''){
			$payer_address1 = $request->payer_address1;
		}

		if(isset($request->payer_state) && $request->payer_state!=''){
			$payer_state = $request->payer_state;
		}
		if(isset($request->payer_city) && $request->payer_city!=''){
			$payer_city = $request->payer_city;
		}
		if(isset($request->payer_country) && $request->payer_country!=''){
			$payer_country = $request->payer_country;
		}
		if(isset($request->payer_postcode) && $request->payer_postcode!=''){
			$payer_postcode = $request->payer_postcode;
		}
		if(isset($request->order_details) && $request->order_details!=''){
			$order_details = $request->order_details;
		}
		if(isset($request->order_invalid) && $request->order_invalid!=""){
			$order_invalid = $request->order_invalid;
		}

		$fname = "";
		$lname = "";
		$ship_fname = "";
		$ship_lname = "";
		if($order_details != ''){
			if(strpos($order_details,"---") !== ""){
				$ord_data = explode("---",$order_details);
				$ord = json_decode($ord_data[0],true);
				if(isset($ord['payer']['name']['given_name']) && $ord['payer']['name']['given_name']!=''){
					$fname = $ord['payer']['name']['given_name'];
				}
				if(isset($ord['payer']['name']['surname']) && $ord['payer']['name']['surname']!=''){
					$lname = $ord['payer']['name']['surname'];
				}
			} else {
				$ord = json_decode($order_details,true);
				if(isset($ord['payer']['name']['given_name']) && $ord['payer']['name']['given_name']!=''){
					$fname = $ord['payer']['name']['given_name'];
				}
				if(isset($ord['payer']['name']['surname']) && $ord['payer']['name']['surname']!=''){
					$lname = $ord['payer']['name']['surname'];
				}
				if(isset($ord['purchase_units'][0]['shipping']['name']) && $ord['purchase_units'][0]['shipping']['name']!=''){
					$ship_name = $ord['purchase_units'][0]['shipping']['name'];
					$ship_name_arr = explode(" ",$ship_name);
					if(isset($ship_name_arr[0]) && $ship_name_arr[0]!=''){
						$ship_fname =  $ship_name_arr[0];
					}
					if(isset($ship_name_arr[1]) && $ship_name_arr[1]!=''){
						$ship_lname = $ship_name_arr[1];
					}
				}
			}
		}

		if(Session::has('Paypalcountry') && Session::get('Paypalcountry')!=''){
			$payer_country = Session::get('Paypalcountry');
		}

		if(Session::has('Paypalcity') && Session::get('Paypalcity') != ''){
			$payer_city = Session::get('Paypalcity');
		}

		if(Session::has('Paypalstate') && Session::get('Paypalstate') != ''){
			$payer_state = Session::get('Paypalstate');
		}

		if((Session::has('Paypalzipcode')) && Session::get('Paypalzipcode') != ''){
			$payer_postcode = Session::get('Paypalzipcode');
		}

		//if(isset($request->rtnm) && $request->rtnm == 'billing' && Session::has('PaypalEmail') && Session::get('PaypalEmail')!='')
		if(isset($request->rtnm) && $request->rtnm == 'billing')
		{
			$chk_paypal_email_data = '';
			if(Session::has('PaypalEmail') && Session::get('PaypalEmail')!=''){
				$CustomerEmail  =  Session::get('PaypalEmail');
				$chk_paypal_email_data .= date("m/d/Y H:i:s")." PayPal Session Email :".$CustomerEmail."\n";
			} else {
				$CustomerEmail  =  isset($request->payer_email) ? $request->payer_email : '';
				$chk_paypal_email_data .= date("m/d/Y H:i:s")." PayPal Payer Email :".$CustomerEmail."\n";
			}

			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = $chk_paypal_email_data;
				fwrite($fh, $stringData);
				fclose($fh);
			}

			//if(isset($request->payer_email) && $request->payer_email!=''){
			if(isset($CustomerEmail) && $CustomerEmail!=''){
				if(checkBlockedUser($CustomerEmail,0,'PayPalGuest')==true)
				{
					Session::flash('PlaceOrderError',config('message.Register.Blocked'));
					return "Blocked";
				}
			}

			if(isset($CustomerEmail) && $CustomerEmail!=''){
				$payer_email = $CustomerEmail;
			}

			$newrequest = [
				'bill_country' 			=> $payer_country, //((Session::has('Paypalcountry')) ? Session::get('Paypalcountry') : ''),
				'bill_fname' 			=> $fname,//'',
				'bill_lname'			=> $lname,//'',
				'bill_address1' 		=> $payer_address1, //'',
				'bill_address2' 		=> '',
				'bill_city' 				=>  $payer_city,  //((Session::has('Paypalcity')) ? Session::get('Paypalcity') : ''),
				'bill_state' 				=> $payer_state, //((Session::has('Paypalstate')) ? Session::get('Paypalstate') : ''),
				'bill_zip' 				=> $payer_postcode, //((Session::has('Paypalzipcode')) ? Session::get('Paypalzipcode') : ''),
				'bill_phone' 			=> '',
				'bill_email' 				=> $payer_email, //$CustomerEmail,
				'bill_cemail' 			=> $payer_email, //$CustomerEmail,
				'sameasbill' 			=> 'No',
				'bill_other_state'		=> $payer_country != 'US' ? $payer_state : ''
			];

			$this->SetBillingAddress($newrequest);

			$newrequestShip = [
				'ship_country' 			=> $payer_country, //((Session::has('Paypalcountry')) ? Session::get('Paypalcountry') : ''),
				'ship_fname' 			=> $ship_fname, //'',
				'ship_lname'			=> $ship_lname,//'',
				'ship_company'			=> '',
				'ship_address1' 		=> $payer_address1,//'',
				'ship_address2' 		=> '',
				'ship_city' 			=> $payer_city,  //((Session::has('Paypalcity')) ? Session::get('Paypalcity') : ''),
				'ship_state' 			=> $payer_state, //((Session::has('Paypalstate')) ? Session::get('Paypalstate') : ''),
				'ship_zip' 				=> $payer_postcode, //((Session::has('Paypalzipcode')) ? Session::get('Paypalzipcode') : ''),
				'ship_phone' 			=> '',
				'ship_email' 			=> $payer_email, //$CustomerEmail,
				'sameasbill' 			=> 'No',
				'ship_other_state'			=> $payer_country != 'US' ? $payer_state : ''
			];

			$this->SetShippingAddress($newrequestShip);

			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}

			//if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			if(!$normaluser && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			{
				$this->SetGuestCustomer($newrequest);
			} else {
				$this->CustomerInfoUpdate($newrequest);
			}

			$request->PaymentMethod = 'PAYMENT_PAYPALEC';
			$this->setPaymentDetail($request);

			$OrderVal = $this->ApplePayPlaceOrder($request);

			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." After PayPal Order Insert :".json_encode($request->rtnm)."\n";
				$stringData .= date("m/d/Y H:i:s")." After PayPal Order Insert :".json_encode($OrderVal)."\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

			if(isset($OrderVal) && ($OrderVal=='OutOfStock' || $OrderVal=='Close' || $OrderVal=='Guest' || $OrderVal=='SHMethod' || $OrderVal=='Zero'))
			{
				Session::forget("StripePaymentType");
				Session::forget("PayMethodRes");
				Session::forget("ShoppingCart.Payment_Detail");
			}
			if($OrderVal=='OutOfStock')
			{
				return "OutOfStock";
			}
			else if($OrderVal=='Close')
			{
				return "Close";
			}
			else if($OrderVal=='Guest')
			{
				return "Guest";
			}
			else if($OrderVal=='SHMethod')
			{
				return "SHMethod";
			}
			else if($OrderVal=='Zero')
			{
				return "Zero";
			}
			else
			{
				return $OrderVal;
			}
		} else if(isset($request->rtnm) && $request->rtnm == 'billing-payment'){
			$request->PaymentMethod = 'PAYMENT_PAYPALEC';
			$this->setPaymentDetail($request);

			$OrderVal = $this->ApplePayPlaceOrder($request);

			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." After PayPal Order Insert :".json_encode($request->rtnm)."\n";
				$stringData .= date("m/d/Y H:i:s")." After PayPal Order Insert :".json_encode($OrderVal)."\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

			if(isset($OrderVal) && ($OrderVal=='OutOfStock' || $OrderVal=='Close' || $OrderVal=='Guest' || $OrderVal=='SHMethod' || $OrderVal=='Zero'))
			{
				Session::forget("StripePaymentType");
				Session::forget("PayMethodRes");
				Session::forget("ShoppingCart.Payment_Detail");
			}
			if($OrderVal=='OutOfStock')
			{
				return "OutOfStock";
			}
			else if($OrderVal=='Close')
			{
				return "Close";
			}
			else if($OrderVal=='Guest')
			{
				return "Guest";
			}
			else if($OrderVal=='SHMethod')
			{
				return "SHMethod";
			}
			else if($OrderVal=='Zero')
			{
				return "Zero";
			}
			else
			{
				return $OrderVal;
			}
		}
		else
		{
			$err_msg = 'Please change payment type';
			Session::flash('PlaceOrderError',$err_msg);
			return "Zero";
		}
	}

	public function transliterate($string)
	{
		// ICU rule: Any-Latin → Latin ASCII
		$latin = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);

		 $map = [
        "ʿ" => "a",   // ʿAyn → A
        "`" => "",    // Remove backticks if any
    ];

    return strtr($latin, $map);
	}

	public function SetCartForPaypal(Request $request)
	{

		$this->SetupCart();

		$skrSKU= $this->OutOfStockItemsRemove();

		if(count($skrSKU) > 0)
		{
			$err_msg ="Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			Session::flash('PlaceOrderError',$err_msg);
			return "OutOfStock";
		}

		if($this->GetNetTotal()<= 0)
		{
			$err_msg ="Please try with different payment method.";
			Session::flash('PlaceOrderError',$err_msg);
			return "Zero";
		}

		if($this->Is_WholeSaler_Allow() == false)
		{
			return "MinimumAmountError";
		}
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
		{
			$err_msg ="Please try with different payment method.";
			Session::flash('PlaceOrderError',$err_msg);
			return "Zero";
		}

		$OrderSubTotal 	 =  Session::get('ShoppingCart.SubTotal');
		$GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
		$GiftValue = 0;
		if($GiftCouponInfo && count($GiftCouponInfo) > 0)
		{
			$GiftValue = $GiftCouponInfo['Value'];
		}
		$NetDiscount 	 = $this->GetAllDiscounts();
		$TotalDiscount 	 = NumberFormat($NetDiscount['TotalDiscount']);
		$OrderSubTotal 	 = $OrderSubTotal;

		$ShopCart = Session::get('ShoppingCart.Cart');
		$ItemsArr = array();
		$data = array();
		if(is_array($ShopCart) && $ShopCart)
		{
			foreach($ShopCart as $key => $CartItem)
			{
				$unit_amountInfo = array();
				$unit_amountInfo["currency_code"] = "USD";
				$unit_amountInfo["value"] = $CartItem['Price'];

				$ItemsArr[] = array(
							'name' 			=> $CartItem['ProductName'],
							'sku'			=> $CartItem['SKU'],
							'unit_amount'	=> $unit_amountInfo,
							'quantity' 		=> $CartItem['Qty']
							);

			}

		$AmountArr = array();
		if($OrderSubTotal > 0)
		{
			$AmountArr["currency_code"] = "USD";
			$AmountArr["value"] = NumberFormat($this->GetNetTotal());
			$AmountArr["breakdown"]["item_total"]["currency_code"] = "USD";
			$AmountArr["breakdown"]["item_total"]["value"] = $OrderSubTotal;

			$ShippingSignature = 0;
			if(!empty($this->GetAllCharges('GiftWrappingCharge')) && $this->GetAllCharges('GiftWrappingCharge') > 0)
			{
				$ShippingSignature = $this->GetAllCharges('GiftWrappingCharge');
			}

			if(!empty($this->GetAllCharges('ShippingSignature')) && $this->GetAllCharges('ShippingSignature') > 0)
			{
				$ShippingSignature = $ShippingSignature + $this->GetAllCharges('ShippingSignature');
			}

			if(!empty($this->GetAllCharges('ShippingCharge')) &&  $this->GetAllCharges('ShippingCharge')>0)
			{
				$AmountArr['breakdown']['shipping']['currency_code'] = "USD";
				$AmountArr['breakdown']['shipping']['value'] = $this->GetAllCharges('ShippingCharge');
			}
			if(!empty($this->GetAllCharges('ShippingInsurance')) &&  $this->GetAllCharges('ShippingInsurance')>0)
			{
				$AmountArr['breakdown']['insurance']['currency_code'] = "USD";
				$AmountArr['breakdown']['insurance']['value'] = $this->GetAllCharges('ShippingInsurance');
			}
			if(!empty($ShippingSignature) &&  $ShippingSignature > 0)
			{
				$AmountArr['breakdown']['handling']['currency_code'] = "USD";
				$AmountArr['breakdown']['handling']['value'] = NumberFormat($ShippingSignature);
			}
			if(!empty($this->GetAllCharges('Tax')) &&  $this->GetAllCharges('Tax')>0)
			{
				$AmountArr['breakdown']['tax_total']['currency_code'] = "USD";
				$AmountArr['breakdown']['tax_total']['value'] = $this->GetAllCharges('Tax');
			}

			if(!empty($TotalDiscount) &&  $TotalDiscount>0)
			{
				$AmountArr["breakdown"]['discount']['currency_code'] = "USD";
				$AmountArr["breakdown"]['discount']['value'] = NumberFormat($TotalDiscount);
			}

		}

			$data["purchase_units"][0]["invoice_id"] = "INV".time(). '-' . mt_rand(1000, 9999);
			$data["purchase_units"][0]["amount"] = $AmountArr;
			$data["purchase_units"][0]["items"] = $ItemsArr;

		if(isset($request->rtnm) && $request->rtnm == 'billing-payment'){ // added condition for last step payment where no need of shipping options
			$data["application_context"]["shipping_preference"] = "SET_PROVIDED_ADDRESS";
			$shippingAddressArr['name']['full_name'] = '';
			$shippingAddressArr['address']['address_line_1'] = '';
			$shippingAddressArr['address']['address_line_2'] = '';
			$shippingAddressArr['address']['admin_area_2'] = ''; //city
			$shippingAddressArr['address']['admin_area_1'] = ''; //state
			$shippingAddressArr['address']['postal_code'] = '';
			$shippingAddressArr['address']['country_code'] = '';
			$shipping_nm = '';
			if(Session::has('ShoppingCart.BillingAsShipping') && Session::get('ShoppingCart.BillingAsShipping') == "Yes"){
				if(Session::has('ShoppingCart.BillingAddress.city') && Session::get('ShoppingCart.BillingAddress.city')!=''){
					$shippingAddressArr['address']['admin_area_2'] = Session::get('ShoppingCart.BillingAddress.city');
				}
				if(Session::has('ShoppingCart.BillingAddress.state') && Session::get('ShoppingCart.BillingAddress.state')!=''){
					$shippingAddressArr['address']['admin_area_1'] = Session::get('ShoppingCart.BillingAddress.state');
				}
				if(Session::has('ShoppingCart.BillingAddress.zip') && Session::get('ShoppingCart.BillingAddress.zip')!=''){
					$shippingAddressArr['address']['postal_code'] = Session::get('ShoppingCart.BillingAddress.zip');
				}
				if(Session::has('ShoppingCart.BillingAddress.country') && Session::get('ShoppingCart.BillingAddress.country')!=''){
					$shippingAddressArr['address']['country_code'] = Session::get('ShoppingCart.BillingAddress.country');
				}
				if(Session::has('ShoppingCart.BillingAddress.address1') && Session::get('ShoppingCart.BillingAddress.address1')!=''){
					$shippingAddressArr['address']['address_line_1'] = Session::get('ShoppingCart.BillingAddress.address1');
				}
				if(Session::has('ShoppingCart.BillingAddress.address2') && Session::get('ShoppingCart.BillingAddress.address2')!=''){
					$shippingAddressArr['address']['address_line_2'] = Session::get('ShoppingCart.BillingAddress.address2');
				}
				if(Session::has('ShoppingCart.BillingAddress.first_name') && Session::get('ShoppingCart.BillingAddress.first_name')!=''){
					$shipping_nm = Session::get('ShoppingCart.BillingAddress.first_name');
				}
				if(Session::has('ShoppingCart.BillingAddress.last_name') && Session::get('ShoppingCart.BillingAddress.last_name')!=''){
					if($shipping_nm!=''){
						$shipping_nm .= " ";
					}
					$shipping_nm .= Session::get('ShoppingCart.BillingAddress.last_name');
				}
				$shippingAddressArr['name']['full_name'] = $shipping_nm;
			} else {
				if(Session::has('ShoppingCart.ShippingAddress.city') && Session::get('ShoppingCart.ShippingAddress.city')!=''){
					$shippingAddressArr['address']['admin_area_2'] = Session::get('ShoppingCart.ShippingAddress.city');
				}
				if(Session::has('ShoppingCart.ShippingAddress.state') && Session::get('ShoppingCart.ShippingAddress.state')!=''){
					$shippingAddressArr['address']['admin_area_1'] = Session::get('ShoppingCart.ShippingAddress.state');
				}
				if(Session::has('ShoppingCart.ShippingAddress.zip') && Session::get('ShoppingCart.ShippingAddress.zip')!=''){
					$shippingAddressArr['address']['postal_code'] = Session::get('ShoppingCart.ShippingAddress.zip');
				}
				if(Session::has('ShoppingCart.ShippingAddress.country') && Session::get('ShoppingCart.ShippingAddress.country')!=''){
					$shippingAddressArr['address']['country_code'] = Session::get('ShoppingCart.ShippingAddress.country');
				}
				if(Session::has('ShoppingCart.ShippingAddress.address1') && Session::get('ShoppingCart.ShippingAddress.address1')!=''){
					$shippingAddressArr['address']['address_line_1'] = Session::get('ShoppingCart.ShippingAddress.address1');
				}
				if(Session::has('ShoppingCart.ShippingAddress.address2') && Session::get('ShoppingCart.ShippingAddress.address2')!=''){
					$shippingAddressArr['address']['address_line_2'] = Session::get('ShoppingCart.ShippingAddress.address2');
				}
				if(Session::has('ShoppingCart.ShippingAddress.first_name') && Session::get('ShoppingCart.ShippingAddress.first_name')!=''){
					$shipping_nm = Session::get('ShoppingCart.ShippingAddress.first_name');
				}
				if(Session::has('ShoppingCart.ShippingAddress.last_name') && Session::get('ShoppingCart.ShippingAddress.last_name')!=''){
					if($shipping_nm!=''){
						$shipping_nm .= " ";
					}
					$shipping_nm .= Session::get('ShoppingCart.ShippingAddress.last_name');
				}
				$shippingAddressArr['name']['full_name'] = $shipping_nm;
			}

			$data["purchase_units"][0]["shipping"] = $shippingAddressArr;
		}

		}
		return response()->json($data);

	}

	public function SetCartForStripe(Request $request)
	{
		//$this->SetShippingInsuranceCharge('remove');
		$this->SetupCart();
		$skrSKU= $this->OutOfStockItemsRemove();

		if(count($skrSKU) > 0)
		{
			$err_msg ="Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			Session::flash('PlaceOrderError',$err_msg);
			return "OutOfStock";
		}

		if($this->GetNetTotal()<= 0)
		{
			$err_msg ="Please try with different payment method.";
			Session::flash('PlaceOrderError',$err_msg);
			return "Zero";
		}
		if(isset($request->isLast) && $request->isLast=='Yes')
		{
			//$this->SetShippingInsuranceCharge('add');
		}
		$CartItems = $this->GetCartForStripe();
		$NetTotal = $this->GetNetTotal();

		$log['CartItems'] = json_encode($CartItems);
		$log['NetTotal'] = json_encode($NetTotal);
		addLog('SetCartForStripe', $log);

		$myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." CartItem :".json_encode($CartItems)." : Total :".round($NetTotal*100)."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		return response()->json(['items' => $CartItems, 'NetTotal' => round($NetTotal*100)]);
	}

	public function GetClientSecret(Request $request)
	{
		$log['ajax_request'] = json_encode($request->all());
		addLog('GetClientSecretStart', $log);
		//if(isset($request->emailAddress) && $request->emailAddress!='')
		if(isset($request->allDetailsVal) && $request->allDetailsVal != '')
		{
			$payerEmail = "";
			$payerPhone = "";
			$allDetailsVal = $request->allDetailsVal;
			if (is_string($allDetailsVal)) {
    			$allDetailsVal = json_decode($allDetailsVal, true);
				$payerEmail = $allDetailsVal['payerEmail'] ?? '';
				$payerPhone = $allDetailsVal['payerPhone'] ?? '';
			}

			if($payerPhone==''){
				Session::flash('PlaceOrderError',"Please enter phone no.");
				return false;
			}

			if($payerEmail!=''){
				//if(checkBlockedUser($request->emailAddress,0,'ClientSecret')==true)
				if(checkBlockedUser($payerEmail,0,'ClientSecret')==true)
				{
					Session::flash('PlaceOrderError',config('message.Register.Blocked'));
					return false;
				}
			}
		}
		if(isset($request->stepfrom) && $request->stepfrom=="firststep")
		{
			if(isset($request->allDetailsVal) && $request->allDetailsVal!='')
			{
				$Billing = json_decode($request->allDetailsVal,true);

				$log['billing_details'] = json_encode($Billing);
				addLog('GetClientSecretFirstStepGetBilling', $log);

				$frsname = '';
				$lrsname = '';
				if(isset($Billing["payerName"]) && $Billing["payerName"]!='')
				{
					$fnameArr = explode(" ",$Billing["payerName"]);

					if(isset($fnameArr[0]) && $fnameArr[0]!='')
						$frsname = $fnameArr[0];

					if(isset($fnameArr[1]) && $fnameArr[1]!='')
						$lrsname = $fnameArr[1];
				}
				$AddressLine1 = '';
				if(isset($Billing['shippingAddress']['addressLine'][0]) && $Billing['shippingAddress']['addressLine'][0]!='')
				{
					$AddressLine1 = $Billing['shippingAddress']['addressLine'][0];
				}
				$AddressLine2 = '';
				if(isset($Billing['shippingAddress']['addressLine'][1]) && $Billing['shippingAddress']['addressLine'][1]!='')
				{
					$AddressLine2 = $Billing['shippingAddress']['addressLine'][1];
				}

				$CustomerEmail = '';

				if(isset($Billing['payerEmail']) && $Billing['payerEmail']!='')
				{
					$CustomerEmail =  $Billing['payerEmail'];
				}
				else if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0)
				{
					$CustomerEmail =  Session::get('sess_useremail');
				}

				$newrequest = [
					'bill_country' 		=> (isset($Billing['paymentMethod']['billing_details']['address']['country']) ? $Billing['paymentMethod']['billing_details']['address']['country'] : ''),
					'bill_fname' 		=> $frsname,
					'bill_lname'		=> $lrsname,
					'bill_address1' 	=>  (isset($Billing['paymentMethod']['billing_details']['address']['line1']) ? $Billing['paymentMethod']['billing_details']['address']['line1'] : ''),
					'bill_address2' 	=> (isset($Billing['paymentMethod']['billing_details']['address']['line2']) ? $Billing['paymentMethod']['billing_details']['address']['line2'] : ''),
					'bill_city' 		=>  (isset($Billing['paymentMethod']['billing_details']['address']['city']) ? $Billing['paymentMethod']['billing_details']['address']['city'] : ''),
					'bill_state' 		=> (isset($Billing['paymentMethod']['billing_details']['address']['state']) ? $Billing['paymentMethod']['billing_details']['address']['state'] : ''),
					'bill_zip' 			=> (isset($Billing['paymentMethod']['billing_details']['address']['postal_code']) ? $Billing['paymentMethod']['billing_details']['address']['postal_code'] : ''),
					'bill_phone' 		=> (isset($Billing['paymentMethod']['billing_details']['address']['phone']) ? $Billing['paymentMethod']['billing_details']['address']['phone'] : ''),
					'bill_email' 		=> $CustomerEmail,
					'bill_cemail' 		=> $CustomerEmail,
					'sameasbill' 		=> 'No'
				];
				$log['newrequest'] = json_encode($newrequest);
				addLog('GetClientSecretFirstStepSetBilling', $log);
				$this->SetBillingAddress($newrequest);

				$newrequestShip = [
					'ship_country' 			=> (isset($Billing['shippingAddress']['country']) ? $Billing['shippingAddress']['country'] : ''),
					'ship_fname' 			=> $frsname,
					'ship_lname'				=> $lrsname,
					'ship_company'			=> '',
					'ship_address1' 			=> $AddressLine1,
					'ship_address2' 			=> $AddressLine2,
					'ship_city' 				=> (isset($Billing['shippingAddress']['city']) ? $Billing['shippingAddress']['city'] : ''),
					'ship_state' 			=> (isset($Billing['shippingAddress']['region']) ? $Billing['shippingAddress']['region'] : ''),
					'ship_zip' 				=> (isset($Billing['shippingAddress']['postalCode']) ? $Billing['shippingAddress']['postalCode'] : ''),
					'ship_phone' 			=> (isset($Billing['shippingAddress']['phone']) ? $Billing['shippingAddress']['phone'] : ''),
					'ship_email' 			=> $CustomerEmail,
					'sameasbill' 		=> 'No'
				];

				$Wallet = '';
				if(isset($Billing['paymentMethod']['card']['wallet']['type']) && $Billing['paymentMethod']['card']['wallet']['type']!='')
				{
					$Wallet =$Billing['paymentMethod']['card']['wallet']['type'];

					if($Wallet=='google_pay')
					{
						$Wallet = 'Google Pay';
					}
					if($Wallet=='apple_pay')
					{
						$Wallet = 'Apple Pay';
					}

				}

				Session::put('StripePaymentType',$Wallet);
				Session::put('PayMethodRes',serialize(json_encode($Billing)));

				$log['newrequestShip'] = json_encode($newrequestShip);
				addLog('GetClientSecretFirstStepSetShipping', $log);
				$this->SetShippingAddress($newrequestShip);

				$normaluser = Auth::user();
				if (Auth::guard('store')->check()) {
					$normaluser = Auth::guard('web')->user();
				}

				//if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
				if(!$normaluser && config('global.IS_GUEST_CHECKOUT') == 'Yes')
				{
					$this->SetGuestCustomer($newrequest);
				} else {
					$this->CustomerInfoUpdate($newrequest);
				}

			}

		}

		if(isset($request->isLastNew) && $request->isLastNew=="Yes")
		{
			if(isset($request->allDetailsVal) && $request->allDetailsVal!='')
			{
				$Billing = json_decode($request->allDetailsVal,true);
				$log['newrequestShip'] = "";
				if(isset($newrequestShip) && !empty($newrequestShip)){
					$log['newrequestShip'] = json_encode($newrequestShip);
				}

				addLog('GetClientSecretLastStepGetShipping', $log);
				$Wallet = '';
				if(isset($Billing['paymentMethod']['card']['wallet']['type']) && $Billing['paymentMethod']['card']['wallet']['type']!='')
				{
					$Wallet =$Billing['paymentMethod']['card']['wallet']['type'];

					if($Wallet=='google_pay')
					{
						$Wallet = 'Google Pay';
					}
					if($Wallet=='apple_pay')
					{
						$Wallet = 'Apple Pay';
					}

				}

				Session::put('StripePaymentType',$Wallet);
				Session::put('PayMethodRes',serialize(json_encode($Billing)));
			}
		}

		$clientSecret = "";
		$payment_intent_id = "";
		if($this->GetNetTotal() > 0 )
		{

			$NetTotal = $this->GetNetTotal();

			/*if(Session::has('sess_useremail') && Session::get('sess_useremail') == 'gequaldev@gmail.com'){
				$intent = \Stripe\PaymentIntent::create([ 
					'amount' => round($NetTotal * 100),
					'currency' => 'usd',
				  ]);
				  if($intent && isset($intent->client_secret))
					  $clientSecret = $intent->client_secret;

				  if($intent && isset($intent->id))
					  $payment_intent_id = $intent->id;
			} else {*/
				Stripe::setApiKey(env('STRIPE_SECRET'));
				$intent = \Stripe\PaymentIntent::create([
					'amount' => round($NetTotal * 100),
					'currency' => 'usd',
				  ]);
				  if($intent && isset($intent->client_secret))
					  $clientSecret = $intent->client_secret;

				  if($intent && isset($intent->id))
					  $payment_intent_id = $intent->id;
			//}
				  $log['clientsecretintent'] = json_encode($intent);
				  addLog('GetClientSecretIntent', $log);

		}

			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayStripOrderPlaceLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." OrderPlace ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

		if(isset($_REQUEST['AppleGPay']) && ($_REQUEST['AppleGPay'] == 'G' || $_REQUEST['AppleGPay'] == 'A')){

			$OrderVal = $this->ApplePayPlaceOrder($request);
			if($OrderVal!='OK')
			{
				Session::forget("StripePaymentType");
				Session::forget("PayMethodRes");
			}
			$log['clientsecret_status'] = json_encode($OrderVal);
			addLog('GetClientSecretStatus', $log);
		}

		if(isset($OrderVal) && $OrderVal=='OK')
		{
			$NetTotal = $this->GetNetTotal();
			\Stripe\PaymentIntent::update(
			  $payment_intent_id,
			  [
				'amount' => round($NetTotal * 100),
				'currency' => 'usd',
			  ]
			);
			$log['clientsecretpayment_intent_id'] = json_encode($payment_intent_id);
			addLog('GetClientSecretIntent', $log);
			Session::put('ShoppingCart.apple_google_paymentintentid',$payment_intent_id);
			return response()->json(['clientSecret' => $clientSecret]);
		}
		else if($OrderVal=='OutOfStock')
		{
			return "OutOfStock";
		}
		else if($OrderVal=='Close')
		{
			return "Close";
		}
		else if($OrderVal=='Guest')
		{
			return "Guest";
		}
		else if($OrderVal=='SHMethod')
		{
			return "SHMethod";
		}
		else if($OrderVal=='Zero')
		{
			return "Zero";
		}

	}

	public function GetClientSecret_bk_30092024(Request $request)
	{
		$clientSecret = "";
		$payment_intent_id = "";
		if($this->GetNetTotal() > 0 )
		{

			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayShopLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Before Shoppingcart ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

			$NetTotal = $this->GetNetTotal();
			Stripe::setApiKey(env('STRIPE_SECRET'));
			$intent = \Stripe\PaymentIntent::create([
			  'amount' => round($NetTotal * 100),
			  'currency' => 'usd',
			]);
			if($intent && isset($intent->client_secret))
				$clientSecret = $intent->client_secret;

			if($intent && isset($intent->id))
				$payment_intent_id = $intent->id;

			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." Intent json ".json_encode($intent)." : Gpay/ApplePay.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

			$myFile = env('LOG_BASE_PATH').'Logs/ApplePayShopLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." After Shoppingcart ".json_encode(Session::get('ShoppingCart'))." \n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

		}

		Session::put('ShoppingCart.apple_google_paymentintentid',$payment_intent_id);
		return response()->json(['clientSecret' => $clientSecret]);
	}

	public function CheckMailTemplate()
	{
		$topmenubar = '<a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'fragrances/cid/1" target="_blank">Fragrances</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'skincare/cid/18" target="_blank">Skincare</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'makeup/cid/30" target="_blank">Makeup</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'bath-body/cid/12" target="_blank">Bath &amp; Body</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'at-home/cid/15" target="_blank">At Home</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'sunglasses/cid/68" target="_blank">Sunglasses</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'perfumesale/p4u/special-sl/view" target="_blank">Sale</a>';

		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		$Template = GetMailTemplate("CUSTOMER_REGISTER");
        $EmailBody = $Template[0]->mail_body;
        $EmailBody = str_replace('{$vFirstName}',$normaluser->first_name,$EmailBody);//Auth::user()->first_name,$EmailBody);
        $EmailBody = str_replace('{$vLastName}',$normaluser->last_name,$EmailBody);//Auth::user()->last_name,$EmailBody);
        $EmailBody = str_replace('{$vemail}',$normaluser->email,$EmailBody);//Auth::user()->email,$EmailBody);
        $EmailBody = str_replace('{$password}',$normaluser->password,$EmailBody);//Auth::user()->password,$EmailBody);
        $EmailBody = str_replace('{$COUPON_CODE_VALUE}',config('Settings.COUPON_CODE_VALUE'),$EmailBody);
        $EmailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$EmailBody);
        $FreeShipping = "";
        if(config('Settings.FREESHIPPING_VALUE')) {
            $FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
        }
        $EmailBody = str_replace('{$freeshippinginfo}',$FreeShipping,$EmailBody);
		dd($EmailBody);
	}

    public function SetOmnisendCart(Request $request)
    {

		$IsGiftCertificateItem = '';

        if(isset($request->omnisendContactID) && $request->omnisendContactID != '')
        {
            $CartData = OmanisendRequest('getCart',['omnisend_accountid' => $request->omnisendContactID]);
            if(isset($CartData['products']) && count($CartData['products']) > 0)
            {
                $ArrMyShopCart = $CartData['products'];

                Session::put("RemoveItem",'');
                $RemoveItem = '';
                for ($p = 0; $p < count($ArrMyShopCart); $p++) {
                    $prod_sku = strtolower(trim($ArrMyShopCart[$p]['sku'] ?? ''));
                    $quantity = (int) $ArrMyShopCart[$p]['quantity'];

                    $IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$ArrMyShopCart[$p],'No');

                    //if($ArrMyShopCart[$p]['sku'] == config('global.GIFT_CERTIFICATE_SKU') || $ArrMyShopCart[$p]['sku'] == config('global.GIFT_CERTIFICATE_SKU1'))
                    if($IsGiftCertificateItem == 'Yes')
                    {
                        /*
                        $data['gc_value']     	= $ArrMyShopCart[$p]['price'];
                        $data['recipient_name']	= $ArrMyShopCart[$p]['RecipientName'];
                        $data['recipient_email']	= $ArrMyShopCart[$p]['RecipientEmail'];
                        $data['subject']			= $ArrMyShopCart[$p]['Subject'];
                        $data['message']			= $ArrMyShopCart[$p]['Message'];
                        $data['signature']		= $ArrMyShopCart[$p]['Signature'];
                        $data['deliverydate']		= $ArrMyShopCart[$p]['DeliveryDate'];
                        $data['yourname']			= $ArrMyShopCart[$p]['YourName'];
                        $data['youremail']		= $ArrMyShopCart[$p]['YourEmail'];
                        $data["GiftImage"]		= $ArrMyShopCart[$p]['GiftImage'];

                        if($data['gc_value'] >= config('Settings.MINIMUM_GIFTCERTIFICATE_AMOUNT') && $data['gc_value'] <= config('Settings.MAXIMUM_GIFTCERTIFICATE_AMOUNT'))
                        {
                            $CartRequest->merge($data);
                            $this->insertGiftCertificate($CartRequest);
                        }
                        */
                    }else{
                        $ProductRs = Products::where('status','=','1')->where(DB::raw('lower(sku)'),'=',$prod_sku)->get();
                        if($ProductRs && $ProductRs->count() > 0)
                        {
                            $ProductRs = $this->SetProduct($ProductRs[0]);

                             if($ProductRs->product_price > 0 && ($ProductRs->current_stock > 0 || ($ProductRs->cosmo_current_stock > 0 && $ProductRs->cosmo_sku!='') || ($ProductRs->nandansons_current_stock > 0 && $ProductRs->nandansons_sku!='') || ($ProductRs->perfumeworldwide_currentstock > 0 && $ProductRs->perfumeworldwide_sku!='') || ($ProductRs->nd_current_stock > 0 && $ProductRs->nd_sku!='')))
                            {
                                $RemoveItem.= $prod_sku.",";
                                $products_id = $ProductRs->products_id;
                                $this->AddToCart($products_id,$quantity,'Yes');
                            }
                        } else {
                            continue;
                        }
                    }
                }
                //$this->StoreShopCartInCookie();
                Session::put("RemoveItem",substr($RemoveItem,0,-1));
            }
        }
        return redirect('/shoppingcart');
    }
    function SetEstimateDate($shipModeId,$IsVender,$IsPerfumePWVendor,$zip,$state,$country)
    {
		$tempChargeStr = $this->CalculateAvailableShippingCharge($zip,$state,$country,$shipModeId);
		$tempChargeArr = explode("###",$tempChargeStr);

		$tempCharge = $tempChargeArr[0];
		$days		= $tempChargeArr[1];

		if($IsVender=="Yes" && $IsPerfumePWVendor=="Yes")
		{
			$days = $days + 3;
		}
		else if($IsVender=="Yes")
		{
			$days = $days + 3;
		}

		$DayVal = date("H@@a");
		$DayValArr = explode("@@",$DayVal);

		$DaynameVal = date("l");
		if($DayValArr[1] == "pm" && isset($DaynameVal) && $DaynameVal!='Sunday' && $DaynameVal!='Saturday')
		{
		   if($DayValArr[0] >=14)
		   {
			   $days = $days + 1;
		   }
		}

		if(isset($shipModeId) && ($shipModeId == 33 || $shipModeId == 34 || $shipModeId == 29) && ($DaynameVal=="Saturday" || $DaynameVal=="Sunday"))
		{
			if($DaynameVal=="Saturday")
			{
				$days = $days + 2;
			}
			else if($DaynameVal=="Sunday")
			{
				$days = $days + 1;
			}

		}

		$EstimatedDeliveryDate = '';
		if($days<=0 || $days=='')
		{
			return $EstimatedDeliveryDate = '';
		}
		else
		{
			$holiday_day_arr = ShippingHoliday::where('holiday_status','=','1')->where('holiday_date','>',date("Y-m-d"))->get();

			$holiday_day = $holiday_day_arr->count();
			$HolidayArrVal = array();

			foreach($holiday_day_arr as $HolidayVal)
			{

				$HolidayArrVal[] = $edate = date('Y-m-d', strtotime($HolidayVal->holiday_date));

			}
			$k=$days;

			for($d=1;$d<=$k;$d++)
			{

				$edate = date('Y-m-d', strtotime("+" . $d . "days"));

				$daynew = $this->checkday($edate);
				if ($daynew == 'saturday' || $daynew == 'sunday')
				{
					$k++;
				}
				else if(in_array($edate,$HolidayArrVal))
				{
					$k++;
				}

			}

			$dt_date =  date('M d', strtotime($edate));

			$estimateShipDate='Estimated Delivery on or before <b>'.$dt_date.'</b>';
			$EstimatedDeliveryDate =  $edate;

			return $EstimatedDeliveryDate;

		}

	}
	function GetSlideCartDataPage()
	{
		$GA4 = "";
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$CartInfo = Session::get('ShoppingCart.Cart');
			$GA4 = googleAnalyticsGA4("ViewCartPage",$CartInfo,$this->GetNetTotal());
		    $GANewCode = "<script type='text/javascript'>".$GA4."</script>";
		    $GA4 = $GANewCode;
		}
		echo $GA4; exit;
	}

	function GetRecommendedProducts()
	{

		$CommonValRoute= \Route::getCurrentRoute();
		if($CommonValRoute->getName() == "billing")
		{
			return true;
		}
		$slider_products = $allSectionRecommendedPrds = array();

		$date = date('Y-m-d');

		if (!Cache::has('RecommendedProductCache')){
		$RPSections = RecommendedProduct::select('recommendedproductid','title','products','categoryOfBrand','show_products_by','start_date','end_date','status')
								->where('status','=','1')
								->where('show_products_by','!=','')
								->where('products','!=','')
								->where('products','!=','0')
								->where('end_date','>=',$date)
								->orderBy('end_date','desc')
								->limit(1)->get();
		Cache::put('RecommendedProductCache',$RPSections);
		Cache::forget('RecommendedLoopProducts');
		}
		else
		{
			$RPSections = Cache::get('RecommendedProductCache');

		}
		$RPSections = json_decode(json_encode($RPSections), true);

		if(count($RPSections) > 0)
		{
			if (!Cache::has('RecommendedLoopProducts')){
				foreach($RPSections as $section)
				{

					if(isset($section['products']))
					{
						$section['title'] = stripslashes($section['title']);
						$section['show_products_by'] = $section['show_products_by'];
						$section['products'] = $section['products'];
						$section['categoryOfBrand'] = $section['categoryOfBrand'];
						$section['start_date'] = $section['start_date'];
						$section['end_date'] = $section['end_date'];
						$section['end_date'] = $section['end_date'];

						if(isset($section['show_products_by']) && $section['show_products_by'] != ''){
							if($section['show_products_by'] == 3){
								$slider_products = $this->GetSliderProducts($section['products']);
							}
							else if($section['show_products_by'] == 1){
								if($section['categoryOfBrand'] != '' && $section['categoryOfBrand'] > 0){
									$slider_products = $this->GetProductsWithParms('',$section['products'],$section['categoryOfBrand'],'','',15);
								}else{
									$slider_products = $this->GetProductsWithParms('',$section['products'],'','','',15);
								}

								if(isset($slider_products['Products']) && count($slider_products['Products']) > 0){
									$slider_products = $slider_products['Products'];
								}else{
									$slider_products = array();
								}
							}
							else if($section['show_products_by'] == 2){
								$slider_products = $this->GetProductsWithParms('','',$section['products'],'','',15);
								if(isset($slider_products['Products']) && count($slider_products['Products']) > 0){
									$slider_products = $slider_products['Products'];
								}else{
									$slider_products = array();
								}
							}
							else{
								$slider_products = array();
							}
						}else{
							$slider_products = array();
						}
						//($ProductString='',$ManufactureID='',$CategoryID='',$ExcludeProductString='',$Flag='',$limit=10,$Filters=[],$isInStock='No',$CategoryString='')

						if(count($slider_products) > 0){
							$section['slider_products'] = $slider_products;
						}else{
							$section['slider_products'] = array();
						}

						$allSectionRecommendedPrds[] = $section;
						Cache::put('RecommendedLoopProducts',$allSectionRecommendedPrds);
					}
				}
			}
			else{
				$allSectionRecommendedPrds = Cache::get('RecommendedLoopProducts');
			}

		}
		else{
			$section = [];
			$allCartCatIds = $allCartSKUs = $allCartManufactureIds = '';

			if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
			{
				$CartInfo = Session::get('ShoppingCart.Cart');
				$cntCart = count(Session::get('ShoppingCart.Cart'));
				//echo "<pre>";print_r($CartInfo);exit;

				foreach($CartInfo as $Cart){

					if(isset($Cart['ImanufactureID']) && $Cart['ImanufactureID'] > 0){
						if(isset($Cart["IS_Free_Gift"]) &&  $Cart["IS_Free_Gift"] == "Yes"){
							continue;
						}
						else if(isset($Cart["IsGiftCertificateItem"]) &&  $Cart["IsGiftCertificateItem"] == "Yes"){
							continue;
						}
						else{
							$allCartManufactureIds .= $Cart['ImanufactureID'].",";
							$allCartSKUs .= $Cart['SKU'].",";

							if(isset($Cart['CategoryID']) && $Cart['CategoryID'] > 0){
								$allCartCatIds .= $Cart['CategoryID'].",";
							}
						}
					}
				}

				$slider_products = array();

				if(isset($allCartManufactureIds) && $allCartManufactureIds != '')
				{
					if(isset($allCartSKUs) && $allCartSKUs != ''){
						$slider_products = $this->GetProductsWithParms('','','',$allCartSKUs,'',15,array(),'Yes','',$allCartManufactureIds,'Yes');
					}
					else{
						$slider_products = $this->GetProductsWithParms('','','','','',15,array(),'Yes','',$allCartManufactureIds,'Yes');
					}

					if(!isset($slider_products['Products']) || count($slider_products['Products']) <= 0){

						if(isset($allCartSKUs) && $allCartSKUs != ''){
							$slider_products = $this->GetProductsWithParms('','','',$allCartSKUs,'',15,array(),'Yes','',$allCartManufactureIds,'No');
						}
						else{
							$slider_products = $this->GetProductsWithParms('','','','','',15,array(),'Yes','',$allCartManufactureIds,'No');
						}

						if(!isset($slider_products['Products']) || count($slider_products['Products']) <= 0){
							if(isset($allCartSKUs) && $allCartSKUs != ''){
								$slider_products = $this->GetProductsWithParms('','','',$allCartSKUs,'',15,array(),'Yes',$allCartCatIds);
							}
							else{
								$slider_products = $this->GetProductsWithParms('','','','','',15,array(),'Yes',$allCartCatIds);
							}
						}
					}
				}

				if(isset($slider_products['Products']) && count($slider_products['Products']) > 0){

					$section['title'] = stripslashes('You May Also Like');
					//$section['products'] = $section['products'];
					$section['start_date'] = '';
					$section['end_date'] = '';

					for($p=0;$p<count($slider_products['Products']);$p++)
					{
						//echo count($slider_products['Products']);exit;
						//echo "<pre>";print_r($slider_products['Products'][$p]);exit;

						$sStock = $slider_products['Products'][$p]->current_stock;

						if($sStock > 0){
							//$section['slider_products'] = $slider_products['Products'];
							$section['slider_products'][] = $slider_products['Products'][$p];
						}
					}
					//echo "<pre>";print_r($section);echo "</pre>";exit;
					$allSectionRecommendedPrds[] = $section;
				}
			}
		}

		if(isset($allSectionRecommendedPrds) && count($allSectionRecommendedPrds) > 0){
			$allSectionRecommendedPrds = $allSectionRecommendedPrds;
		}else{
			$allSectionRecommendedPrds = array();
		}

		$this->PageData['allSectionRecommendedPrds'] = $allSectionRecommendedPrds;
		return $allSectionRecommendedPrds;
	}

	/*public function setIntelliSuggestTrackingCart(Request $request)
	{
		if($request->ajax()){

			$cart_arr = array();
			$setSkuArr = $setIDArr = $setPriceArr = $setQtyArr = array();
			$nullVar = '';

			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!='' && count(Session::get('ShoppingCart.Cart')) > 0){
				$cart_arr = Session::get('ShoppingCart.Cart');
				$allCartItems = '';

				if (!empty($cart_arr)) {
					foreach($cart_arr as $Cart){
						$setSkuArr[] = $Cart['SKU'];
						$setIDArr[] = $Cart['ProductID'];
						$setPriceArr[] = $Cart['ItemPrice'] ?? 0; ;
						$setQtyArr[] = $Cart['Qty'];

						$allCartItems .= $Cart['SKU'].",";
					}
					$allCartItems = rtrim($allCartItems,",");
					$allCartItems = "'".str_replace(",","','",$allCartItems)."'";
				}
				return json_encode($setSkuArr)."@@@".json_encode($setIDArr)."@@@".json_encode($setPriceArr)."@@@".json_encode($setQtyArr)."@@@".$allCartItems;
			}else{
				//return json_encode($setSkuArr)."@@@".json_encode($setIDArr)."@@@".json_encode($setPriceArr)."@@@".json_encode($setQtyArr)."@@@".$allCartItems;
				return $nullVar;
			}
		}
	}*/

	public function StorePaymentMethods(Request $request)
	{
		Session::forget("BillingSkipVariable");

		Session::forget("PaymentMessagePOSF");
		$this->PageData = [];
		$this->PageData['ShipToStoreVal'] = 'No';
		if ($request->has('ShipToStoreVal') && $request->input('ShipToStoreVal') == "Yes")
		{
			$this->PageData['ShipToStoreVal'] = 'Yes';
			 $request->merge([
				'shipping_signature' => "No"
			]);
			Session::forget("ShoppingCart.ShippingSignature");

			$EstimateDate = now()->addDays(2)->format("m/d/Y");
			Session::put("ShoppingCart.EstimatedDeliveryDate",$EstimateDate);

			$EstimateDate = $this->getEstimateDateVal();

			Session::put("ShoppingCart.Shipping", [
						"ShippingMethodName" => "Ship To Store",
						"ShippingDays"       => $EstimateDate,
						"ShippingCharge"     => 0,
						"ShippingMethodID" 	 => 99999
						]);

			$this->SetShippingInsuranceCharge('remove');
			Session::forget('shipping_insurance_charge');
		}

		//echo "<pre>"; print_r(Session::get("ShoppingCart")); exit;

		// if(!Auth::guard('store')->check() && $request->cookie('pos_maxaroma_token') && !empty($request->cookie('pos_maxaroma_token')))
        // {
        //     return redirect('/store/login-from-cookiee');
        // }

		if(isset($request->BillingSkipVariable) && $request->BillingSkipVariable=="Yes")
		{
			Session::put('BillingSkipVariable',$request->BillingSkipVariable);
		}
		else if($request->BillingSkipVariableFromBill &&  $request->BillingSkipVariableFromBill=="Yes")
		{
			Session::put('BillingSkipVariable',$request->BillingSkipVariableFromBill);
		}
		Session::forget("BillingSkipEmail");
		if(isset($request->BillingSkipEmail) && $request->BillingSkipEmail=="Yes")
		{
			Session::put('BillingSkipEmail',$request->BillingSkipEmail);
		}
		else if($request->BillingSkipEmailFromBill && $request->BillingSkipEmailFromBill=="Yes")
		{
			Session::put('BillingSkipEmail',$request->BillingSkipEmailFromBill);
		}

		if (Auth::guard('store')->check()) {
			$storeUser = Auth::guard('store')->user();
			$store = DB::table('pu_stores')->where('store_id', $storeUser->store_id)->first();
			$sessionEmail = Session::get('ShoppingCart.Store.Email');

			if (!empty($sessionEmail) && !empty($store->store_email ?? '') && $sessionEmail != $store->store_email) {
				$customer = Customer::where('email', $sessionEmail)
							->where('registration_type', 'G')
							->where('is_deleted', 'No')
							->first();
				if ($customer) {
					$Billing = Session::get('ShoppingCart.BillingAddress', []);
					$updateCustomer = [];

					if (empty($customer->first_name) && !empty(trim($Billing['first_name'] ?? ''))) {
						$updateCustomer['first_name'] = trim($Billing['first_name']);
					}

					if (empty($customer->last_name) && !empty(trim($Billing['last_name'] ?? ''))) {
						$updateCustomer['last_name'] = trim($Billing['last_name']);
					}

					if (empty($customer->phone) && !empty(trim($Billing['phone'] ?? ''))) {
						$updateCustomer['phone'] = trim($Billing['phone']);
					}

					if (!empty($updateCustomer)) {
						$updateCustomer['country'] = 'US';
						$customer->update($updateCustomer);
						$customer->refresh();
						//Log::info('Stroe Create Customer: '.json_encode($customer));
						OmanisendRequest('create_customer',$customer);
					}

				}
			}
		}

		if (Session::has('ShoppingCart.SalesAgent')) {
			$orderSalesAgents = Session::get('ShoppingCart.SalesAgent');
			//echo json_encode($orderSalesAgents);
		}
		$myFile = env('LOG_BASE_PATH') .'Logs/ApplePayOtherLog.txt';
		if (fopen($myFile, 'a+')) {
			$stringData = '';
			$fh = fopen($myFile, 'a+');
			if (Session::has('ShoppingCart') && Session::get('ShoppingCart') != '') {
				$stringData .= date("m/d/Y H:i:s") . " Regarding Gift Certificate PaymentMethod Session Data : " . json_encode(Session::get('ShoppingCart')) . " :\n";
			}
			if (Session::has('sess_useremail') && Session::get('sess_useremail') != '') {
				$stringData .= date("m/d/Y H:i:s") . " Regarding Gift Certificate Place order start Session User Email : " . Session::get('sess_useremail') . " :\n";
			}
			fwrite($fh, $stringData);
			fclose($fh);
		}

		$skrSKU = $this->OutOfStockItemsRemove();

		if (count($skrSKU) > 0) {
			$err_msg = "Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment.";
			$log['err_msg'] = $err_msg;
			addLog('PaymentMethods', $log);
			Session::flash('PlaceOrderError', $err_msg);
			return redirect('/shoppingcart');
		}

		if ($request->isMethod('GET') && $request->onlyGCPurchasedVal == 0)
		{
			return redirect('checkout');
		}

		$this->PageData['CSSFILES'] = ['shoppingcart.css', 'checkout.css', 'pos-style.css'];
		$this->PageData['JSFILES'] = ['billing.js', 'poscheckout.js'];
		//$this->PageData['JSFILES'] = ['jquery.mobile-1.0rc2.min.js','StarWebPrintBuilder.js','StarBarcodeEncoder.js','StarWebPrintExtManager.js','StarWebPrintDisplayBuilder.js','StarWebPrintTrader.js','store_device_status.js','billing.js', 'poscheckout.js'];
		$Views = [];
		//echo Session::get('ShoppingCart.Shipping.ShippingDays'); exit;

		/*$isshow_dailyopen_pending = 2;
		$cashd_res = DB::table('pu_store_cash_drawer')->select('*')->where('store_id', auth('store')->user()->store_id)->where(DB::raw("(DATE_FORMAT(added_datetime,'%m-%d-%Y'))"), date("m-d-Y"))->first();
		//dd($cashd_res);
		if(empty($cashd_res))
		{
			$isshow_dailyopen_pending = 1;
		}*/

		$this->PageData['BillingSkipVariable'] = '';
		if(isset($request->BillingSkipVariable) && $request->BillingSkipVariable=="Yes")
		{
			$this->PageData['BillingSkipVariable'] = $request->BillingSkipVariable;
		}

		$this->PageData['BillingSkipEmail'] = '';
		if(isset($request->BillingSkipEmail) && $request->BillingSkipEmail=="Yes")
		{
			$this->PageData['BillingSkipEmail'] = $request->BillingSkipEmail;
		}

		//$this->PageData['isshow_dailyopen_pending'] =  $isshow_dailyopen_pending;

		$this->SetCheckoutCommonDetails($request);

		$this->PageData['shipping_signature']  = "No";
		if (isset($request->shipping_signature)) {
			$this->PageData['shipping_signature'] = $request->shipping_signature;
			$log['shipping_signature'] = $request->shipping_signature;
		}

		if ((isset($request->action) && $request->action == 'paymentinfo') || (isset($request->takeaction) && $request->takeaction == "TakeAction") || (isset($request->takeactiong) && $request->takeactiong == "TakeAction")) {

			$log['ajax_request'] = $request->action;
			addLog('PaymentMethods', $log);
			$this->PageData['SelMethod'] = '';
			$this->PageData['is_paypal'] = 'no';
			$this->PageData['is_afterpay'] = 'no';
			$this->PageData['SelPayMethod'] = (isset($request->SelPayMethod) ? $request->SelPayMethod : '');

			if ($request->OnlyHead == '0' || $request->OnlyHeadval == '0') {
				$Billing  = Session::get('ShoppingCart.BillingAddress');

				$this->PageData['Billing'] = $Billing;

				$allowpaymentoption = ['PAYMENT_STRIPE', 'PAYMENT_CASH', 'PAYMENT_SPLIT','PAYMENT_STRIPE_NORMAL'];

				$NetTotal = $this->GetNetTotal();
				$this->PageData['ShowCardReader'] = 'Yes';
				if(isset($NetTotal) && (float)$NetTotal <= 0)
				{
					$this->PageData['ShowCardReader'] = 'No';
					$allowpaymentoption = ['PAYMENT_FREEITEM'];
				}

				$OnlyGiftCert = 0;
				$CreditDiscount = 0;

				$PaymentMethodList = PaymentMethod::where('pm_status', '=', 'Active')->whereIn('pm_group_name', $allowpaymentoption)->orderBy('pm_position', 'asc')->get();

				$this->PageData['PaymentMethodList'] = $PaymentMethodList;

				$GiftValue = $this->FreeGiftValue($this->GetNetTotal());
				$giftflag = 0;
				$freegiftcombo = '';
				if (count($GiftValue) > 0) {
					$giftflag = 1;
					$freegiftcombo = "<select name='freegiftvalue' id='freegiftvalue' class='form-control'>";
					$freegiftcombo .= "<option value=''>Select Gift</option>";

					for ($i = 0; $i < count($GiftValue); $i++) {
						$selected = "";
						if (Session::get('ShoppingCart.FreeGift') == $GiftValue[$i]) {
							$selected = "selected=selected";
						}
						$freegiftcombo .= "<option value=\"" . $GiftValue[$i] . "\" $selected >" . $GiftValue[$i] . "</option>";
					}
					$freegiftcombo .= "</select>";
				}
				$this->PageData['giftflag'] = $giftflag;
				$this->PageData['freegiftcombo'] = $freegiftcombo;
				$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
				$this->PageData['CreditDiscount'] = $CreditDiscount;
				$CreditData = $this->GetCreditLimitAmount();
				$this->PageData['CartAttr'] = $CreditData ;
				//$this->PageData['CreditDiscount'] = 0;
			}

			$ship_country = Session::get('ShoppingCart.ShippingAddress.country');
			$ship_state = Session::get('ShoppingCart.ShippingAddress.state');
			$ship_zip = Session::get('ShoppingCart.ShippingAddress.zip');
			$ship_city = Session::get('ShoppingCart.ShippingAddress.city');
			$onlyGCPurchased = $request->onlyGCPurchased;

			if (isset($request->OrderType) && $request->OrderType != 'Store') {
				$this->TaxCalculation($ship_country, $ship_state, $ship_zip, $onlyGCPurchased,'',$ship_city);
			}
			if (isset($request->OrderType) && $request->OrderType == 'Store') {
				$this->SetShippingInsuranceCharge('remove');
			}

			if ($request->ShipInsCharge == 'yes' && isset($request->OrderType) && $request->OrderType != 'Store') {
				$this->SetShippingInsuranceCharge('remove');
				$this->SetShippingInsuranceCharge('add');
			}

			$this->SetupCart();

			$Views['ShipInfo'] = view('checkout.shippinginfo')->render();
			$this->PageData['OnlyHead'] = $request->OnlyHead;
			$this->PageData['Shipping'] = Session::get('ShoppingCart.ShippingAddress');
			$ShippingInfo = Session::get('ShoppingCart.Shipping');
			$currency = "USD";

			$this->PageData["onlyGCPurchasedVal"] = $request->onlyGCPurchasedVal;

			$PaymentMethodsValE = "";
			if (isset($PaymentMethodList) && isset($PaymentMethodList[0]->pm_name) && $PaymentMethodList[0]->pm_name != '') {
				$PaymentMethodsValE = $PaymentMethodList[0]->pm_name;
			} else if (isset($PaymentMethodList) && isset($PaymentMethodList[0]["pm_name"]) && $PaymentMethodList[0]["pm_name"] != '') {
				$PaymentMethodsValE = $PaymentMethodList[0]["pm_name"];
			}

			if (isset($PaymentMethodsValE) && $PaymentMethodsValE != '') {
				$GAPayment 	= googleAnalyticsGA4("PaymentMethods", Session::get('ShoppingCart.Cart'), $this->GetNetTotal(), $this->GetAllCoupons('CouponCode'), '', $PaymentMethodsValE);
				$this->PageData["GA4"] =  $GAPayment;
			}

			$this->PageData['NetTotal'] = $this->GetNetTotal();
			$log['PaymentMethodsEnd'] = "PaymentMethodsEnd";
			addLog('PaymentMethods', $log);
			$OrderMsgVal = "";

			if (Auth::guard('store')->check()) {

				$CartItem = Session::get('ShoppingCart.Cart');
				$grouped = collect($CartItem)->groupBy('OrderType');
				$storeCount = $grouped->get('Store', collect())->count();
				$websiteCount = $grouped->get('Website', collect())->count();

				if ($storeCount > 0 && $websiteCount > 0) {
					$OrderStoreTypeValue = "Both";
					if (!Session::has('ShoppingCart.StoreOrderID') && empty(Session::get('ShoppingCart.StoreOrderID'))) {
						$this->PlaceOrder($request);
					}
					Session::put('ShoppingCart.OrderStoreTypeValue', $OrderStoreTypeValue);
					if (Session::has('ShoppingCart.StoreOrderID')) {
						$OrderNumber = "#". GetStorePrefix() . Session::get('ShoppingCart.StoreOrderID');
						$OrderMsgVal = "Your Order has been Generated.<br/>Please proceed with the Payment.";
					}
				} else if ($storeCount > 0 || $websiteCount  > 0) {

					$OrderStoreTypeValue = "Website";
					if($storeCount > 0)
					{
						$OrderStoreTypeValue = "Store";
					}
					Session::put('ShoppingCart.OrderStoreTypeValue', $OrderStoreTypeValue);
					if (!Session::has('ShoppingCart.OrderID') && empty(Session::get('ShoppingCart.OrderID'))) {
						$this->PlaceOrder($request);
					}

					if (Session::has('ShoppingCart.OrderID'))
					{
						if($OrderStoreTypeValue == 'Store')
						{
							$OrderNumber = "#". GetStorePrefix() . Session::get('ShoppingCart.OrderID');
						} else {
							$OrderNumber = "#OR" . Session::get('ShoppingCart.OrderID');
						}
						//$OrderNumber = "#OR" . Session::get('ShoppingCart.OrderID');
						$OrderMsgVal = "Your Order " . $OrderNumber . " has been Generated. <br/>Please proceed with the Payment.";
					}
				}

			}

			$this->PageData["OrderMsgVal"] =  $OrderMsgVal;

			//Check Card Reader Status
			$StripeDetails = $this->GetStripeKey();

			$this->PageData['PublicKey'] = $StripeDetails['PUBLISH_KEY'] ?? '';
			$CardReaderStatus = $this->GetCardReaderStatus(1);
			$this->PageData['CardReaderStatus'] = $CardReaderStatus['status'];
			$this->PageData['CardReaderName'] = $CardReaderStatus['reader_name']??'';
			//$CardReaderStatus = ['status'=> "ONLINE"];
			$CardReaders = StoreCardReader::where('store_id',auth('store')->user()->store_id)->where('status','1')->get();
			$this->PageData['CardReaders'] = $CardReaders;
			//Check Card Reader Status

			return view('pos.payment-methods-page')->with($this->PageData);
		}
	}
	public function getEstimateDateVal()
	{
		$DayVal = date("H@@a");
		$DayValArr = explode("@@",$DayVal);
		$days = 2;
		$edate = date('Y-m-d', strtotime($days));
		$DaynameVal = date("l");
		if($DayValArr[1] == "pm" && isset($DaynameVal) && $DaynameVal!='Saturday' && $DaynameVal!="Sunday")
		{
		   if($DayValArr[0] >=14)
		   {
			   $days = $days + 1;
		   }
		}

		if($DaynameVal=="Saturday" || $DaynameVal=="Sunday")
		{
			if($DaynameVal=="Saturday")
			{
				$days = $days + 2;
			}
			else if($DaynameVal=="Sunday")
			{
				$days = $days + 1;
			}
		}

		$holiday_day_arr = ShippingHoliday::where('holiday_status','=','1')->where('holiday_date','>',date("Y-m-d"))->get();

			$holiday_day = $holiday_day_arr->count();
			$HolidayArrVal = array();

			foreach($holiday_day_arr as $HolidayVal)
			{

				$HolidayArrVal[] = $edate = date('Y-m-d', strtotime($HolidayVal->holiday_date));

			}
			$k=$days;

			for($d=1;$d<=$k;$d++)
			{

				$edate = date('Y-m-d', strtotime("+" . $d . "days"));

				$daynew = $this->checkday($edate);
				if ($daynew == 'saturday' || $daynew == 'sunday')
				{
					$k++;
				}
				else if(in_array($edate,$HolidayArrVal))
				{
					$k++;

				}

			}

			$dt_date =  date('M d', strtotime($edate));

			$estimateShipDate='Estimated Delivery on or before <b>'.$dt_date.'</b>';
			$EstimatedDeliveryDate =  $edate;
			return $estimateShipDate;

		}

}
