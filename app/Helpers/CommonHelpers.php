<?php
//use DB;
//use Mail;
//use Cache;
//use URL;

use function PHPUnit\Framework\isEmpty;
use App\Models\Products;
use App\Models\Manufacture;
use App\Models\BlockedCustomerlog;
use App\Models\TopBar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

if(isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

function checkLogin(){
	if(Auth::user()){
			return redirect('/myaccount.html');
	}
}
function PrintObj($Obj)
{
	if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
	{
		dd($Obj);
	}
}

function getMetaTitleDescription($str = ""){
	if($str!=""){
		return htmlspecialchars(html_entity_decode(stripslashes(str_replace(["\r\n","\n"], '', trim($str))),ENT_QUOTES|ENT_HTML5,'UTF-8'), ENT_QUOTES,'UTF-8');
	}
}

function generateUUIDv4() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0x4000, 0x4fff),
        mt_rand(0x8000, 0xbfff),
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function edeskPost($url, $data, $apiToken) {
	addLog("edesk-message-start");
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_POST           => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => [
			'Accept: application/json',
			'Authorization: Bearer ' . $apiToken,
			'Content-Type: application/json',
		],
		CURLOPT_POSTFIELDS     => json_encode($data),
	]);
	$resp = curl_exec($ch);
	$log['req'] = json_encode($data);
	$log['resp'] = $resp;
	addLog("edesk-message-success",$log);
	// if (curl_errno($ch)) {
	// 	throw new Exception('cURL Error: ' . curl_error($ch));
	// }
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	// if ($code < 200 || $code >= 300) {
	// 	throw new Exception("HTTP $code Error Response: $resp");
	// }
	//return json_decode($resp, true);
}

function addLog($action_nm = "", $arr = array()){
	//$myFile = '/home/maxaroma/public_html/Logs/Walmart/'.date("Y-m-d").'CartLog.txt';

	$dir_path = env('LOG_BASE_PATH') . 'Logs/Walmart/' . date('Y-m-d');

	if (!is_dir($dir_path)) {
		mkdir($dir_path, 0777, true);
	}

	if(Session::getId() != '' && Session::has('sess_icustomerid') && Session::get('sess_icustomerid')!=''){
		//$myFile = '/home/maxaroma/public_html/Logs/Walmart/'.date("Y-m-d")."_".Session::get('sess_icustomerid')."_".Session::getId().'CartLog.txt';
		$myFile = $dir_path.'/'.date("Y-m-d")."_".Session::get('sess_icustomerid')."_".Session::getId().'CartLog.txt';
    }
	else if(Session::getId() != '') {
		//$myFile = '/home/maxaroma/public_html/Logs/Walmart/'.date("Y-m-d").'_'.Session::getId().'CartLog.txt';
		$myFile = $dir_path.'/'.date("Y-m-d").'_'.Session::getId().'CartLog.txt';
	}

	if(fopen($myFile, 'a+'))
	{
		$stringData = '';
		$fh = fopen($myFile, 'a+');

		// if (Session::getId() != '') {
		// 	$stringData .= date("m/d/Y H:i:s") . " User Session Id : " . Session::getId() . " :\n";
		// }
		if($action_nm != ""){
			$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Start :\n";
		}
		if($action_nm == "AddToCart" || $action_nm == "UpdateCart" || $action_nm == "RemoveFromCartStart" || $action_nm == "RemoveFromCart" || $action_nm == "ApplyCouponDiscount" || $action_nm == "ApplyCreditDiscount" || $action_nm == "remove_credit_limit" || $action_nm == "ResetGiftCoupon" || $action_nm == "SetGiftCouponRemainingValue" || $action_nm == "ResetGiftCoupon_2" || $action_nm == "ResetGiftCoupon_3" || $action_nm == "SetGiftCoupon" || $action_nm == "ResetGiftCoupon_4" || $action_nm == "GiftCouponInfo" || $action_nm == "RemoveYotpoReward" || $action_nm == "RemoveGiftCoupon" || $action_nm == "RemoveCoupon" || $action_nm == "ClearBag" || $action_nm == "SameFreeGift" || $action_nm == "FreeGiftNull" || $action_nm == "FreeGiftUnset" || $action_nm == "FreeGiftInsertProductValue" || $action_nm == "FreeGiftMessage" || $action_nm == "ApplyFreeGiftErrorMessage" || $action_nm == "ApplyCouponDiscountStart" || $action_nm == "ApplyCouponRecordSet" || $action_nm == "ApplyCouponOrderAmount" || $action_nm == "ApplyCouponProductSKU" || $action_nm == "ApplyCouponCase7" || $action_nm == "ApplyDiscountProductCategory" || $action_nm == "ApplyDiscountProductBrand" || $action_nm == "ApplyDiscountFreeShipping" || $action_nm == "ApplyDiscountInvalidCoupon" || $action_nm == "ApplyDiscountInvalidYotpoCoupon" || $action_nm == "GiftCouponRecordset" || $action_nm == "cantRemoveCoupon" || $action_nm == "FreeGiftInsertProductValueStart" || $action_nm == "FreeGiftInsertProductValueOutofStock" || $action_nm == "ApplyFreeGiftStart" || $action_nm == "ShoppingcartPageStart" || $action_nm == "ShoppingcartPage" || $action_nm == "ShoppingcartPageView" || $action_nm == "SetShippingInsuranceChargeStart" || $action_nm == "SetupCartStart" || $action_nm == "ApplyAutoDiscountStart" || $action_nm == "ApplyAutoDiscount" || $action_nm == "ApplyAutoDiscountEnd" || $action_nm == "ApplyQuantityDiscountStart" || $action_nm == "ApplyQuantityDiscount" || $action_nm == "ApplyDogoDiscountStart" || $action_nm == "ApplyDogoDiscount" || $action_nm == "ApplyGiftWrappingStart" || $action_nm == "ApplyGiftWrapping" || $action_nm == "TaxCalculationStart" || $action_nm == "TaxCalculation" || $action_nm == "GetAllChargesStart" || $action_nm == "GetAllCharges" || $action_nm == "GetAllDiscountStart" || $action_nm == "GetAllDiscount" || $action_nm == "SetupCartEnd" || $action_nm == "CheckoutPageStart" || $action_nm == "CheckoutPage" || $action_nm == "SetCheckoutCommonDetailsStart" || $action_nm == "SetCheckoutCommonDetails" || $action_nm == "ShippingMethodStart" || $action_nm == "ShippingMethod" || $action_nm == "PaymentMethodsStart" || $action_nm == "PaymentMethods" || $action_nm == "PlaceOrderStart" || $action_nm == "PlaceOrder" || $action_nm == "setPaymentDetailStart" || $action_nm == "setPaymentDetail" || $action_nm == "ApplyGiftCouponsStart"){
			if(Session::has('sess_useremail') && Session::get('sess_useremail')!=''){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Session User Email : ".Session::get('sess_useremail')." :\n";
			}
			if(isset($arr['ProductRs']) && !empty($arr['ProductRs'])){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Product Data ProductRs : ".json_encode($arr['ProductRs'])." :\n";
			}
			if($action_nm == "ApplyCouponDiscount"){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Discount : ".json_encode($arr)." :\n";
			}
			else if($action_nm == "GiftCouponInfo"){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Gift Coupon Info : ".json_encode($arr)." :\n";
			}
			else if($action_nm == "RemoveYotpoReward"){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Remove Yotpo Reward : ".json_encode($arr)." :\n";
			}
			else if($action_nm == "RemoveGiftCoupon"){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Remove Gift Coupon : ".json_encode($arr)." :\n";
			}
			else if($action_nm == "RemoveFromCartStart"){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Remove Coupon Start : ".json_encode($arr)." :\n";
			}
			else if($action_nm == "RemoveCoupon"){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Remove Coupon : ".json_encode($arr)." :\n";
			}
			else if($action_nm == "SameFreeGift"){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Same Free Gift : ".json_encode($arr)." :\n";
			}
			else if($action_nm == "FreeGiftMessage" || $action_nm == "ApplyFreeGiftErrorMessage"){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Free Gift Message : ".json_encode($arr)." :\n";
			} else {
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." ***** : ".json_encode($arr)." :\n";
			}

			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Session Cart Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
			}
		}else {
			$stringData .= date("m/d/Y H:i:s")." ".$action_nm." ***** : ".json_encode($arr)." :\n";
			if(Session::has('ShoppingCart') && Session::get('ShoppingCart')!=''){
				$stringData .= date("m/d/Y H:i:s")." ".$action_nm." Session Cart Data : ".json_encode(Session::get('ShoppingCart'))." :\n";
			}
		}
		if($action_nm != ""){
			$stringData .= date("m/d/Y H:i:s")." ".$action_nm." End :\n";
		}
		$stringData .= "======================================================================================";
		fwrite($fh, $stringData);
		fclose($fh);
	}
}

function getTopPopupText(){
	$popupArr = array();
	if (!Cache::has('topHeaderPopupText')) {
		$topHeaderPopup = \App\Models\TopBar::where('bar_type', 3)->orderBy('BarId')->get()->toArray();
		Cache::put('topHeaderPopupText', $topHeaderPopup);
		$popupArr = $topHeaderPopup;
	}else{
		$popupArr = Cache::get('topHeaderPopupText');
	}
	return $popupArr;
}

function setTopHeaderBar(){
	$headerBars = array();
	if (!Cache::has('TopFirstHeaderBar')) {
		$topFirstHeaderBar = \App\Models\TopBar::where('bar_type', 1)->orderBy('BarId')->get()->toArray();
		Cache::put('TopFirstHeaderBar', $topFirstHeaderBar);
		$headerBars['first'] = $topFirstHeaderBar;
	}else{
		$headerBars['first'] = Cache::get('TopFirstHeaderBar');
	}

	if (!Cache::has('TopSecondHeaderBar')) {
		$topSecondHeaderBar = \App\Models\TopBar::where('bar_type', 2)->orderBy('BarId')->get()->toArray();
		Cache::put('TopSecondHeaderBar', $topSecondHeaderBar);
		$headerBars['second'] = $topSecondHeaderBar;
	}else{
		$headerBars['second'] = Cache::get('TopSecondHeaderBar');
	}
	return $headerBars;
}

function checkBlockedUser($email, $customer_id = 0, $from_action = "", $is_checked = 'N'){
	if($is_checked == 'N'){
		$check_blocked_email = \App\Models\Customer::where('email','=',trim($email))
											->where('block_customer_flag','=','Yes')->get();
		if($check_blocked_email->count()>0)
		{
			$blockArray = array(
				'customer_id' => $customer_id,
				'customer_email' => $email,
				'from_action' => $from_action,
				'from_ip' => $_SERVER['REMOTE_ADDR'],
				'from_browser' => $_SERVER['HTTP_USER_AGENT']
			);
			\App\Models\BlockedCustomerlog::create($blockArray);
			return true;
		} else {
			return false;
		}
	} else {
		$blockArray = array(
			'customer_id' => $customer_id,
			'customer_email' => $email,
			'from_action' => $from_action,
			'from_ip' => $_SERVER['REMOTE_ADDR'],
			'from_browser' => $_SERVER['HTTP_USER_AGENT']
		);
		\App\Models\BlockedCustomerlog::create($blockArray);
		return true;
	}

}

function setURLValue($url)
{
	$SpecialCharacters = array("(",")","'","#","%","]","[",":",".","/");
	$url = str_replace(" ","-",$url);
	$url = str_replace($SpecialCharacters, "", $url);
	return strtolower($url);
}

function GetYouSaveAmount($Price,$Percentage)
{
    $Amount = $Price * (round($Percentage)/100);
    return round($Amount);
}

function Price($Amount)
{
	$Amount = ($Amount == ''?0.00:(float)$Amount);
	$CurrencySymbol = '$';
	if(Session::has('currency_symbol') && Session::get('currency_symbol') != '')
		$CurrencySymbol = Session::get('currency_symbol');
    $conversion_rate = Session::get('currency_rate');
    $Amount = $Amount * $conversion_rate;
	$FormatAmount = $CurrencySymbol.floatval(NumberFormat($Amount));
	return $FormatAmount;
}

function GetCountries()
{
	return Cache::rememberForever('country_list_ca', function () {

        return \App\Models\Countries::where('status', '1')
            ->orderBy('countries_name', 'asc')
            ->get(['countries_iso_code_2', 'countries_name'])
            ->mapWithKeys(function ($country) {
                return [
                    $country->countries_iso_code_2 =>
                        $country->countries_iso_code_2 . ' ' . $country->countries_name
                ];
            })
            ->toArray();
    });
}

function GetStates()
{
    return Cache::rememberForever('state_list_ca', function () {

        return ['' => 'Select State'] +

            App\Models\State::where('status', '1')
                ->orderBy('name')
                ->get(['code', 'name'])
                ->mapWithKeys(function ($state) {
                    return [
                        $state->code => $state->code . ' ' . $state->name
                    ];
                })
                ->toArray();
    });
}
if(!function_exists('generalsetting')){
	function generalsetting($variable="",$section=1) {
		if($variable=="") {
			$Setting = \App\Models\SiteSettings::where('section','=',$section)->orderBy('display_order')->get();
			return $Setting;
		}else {
			$Setting = \App\Models\SiteSettings::where('section','=',$section)
						->where('var_name','=',$variable)
						->orderBy('site_settings_id')->get();
			return $Setting[0]->setting;
		}
	}
}
function GetCustomerAttribute($Attr='')
{
	if($Attr!='')
	{
		$CustomerAttributes = \App\Models\CustomerAttribute::where('attributename','=',$Attr)->get();
		if($CustomerAttributes->count() > 0)
			return $CustomerAttributes;
	}
}
if(!function_exists('isMobile')){
	function isMobile() {
		// return false;
		return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", Request::header('user-agent'));
	}
}

function GetBottomHtml()
{
	$BottomHtmlText = Cache::remember('BottomHtmlText', 3600, function() {
		$BottomHtml = \App\Models\BottomHtml::all();
		$shipping_policy = '<a href="'.config('global.SITE_URL').'shipping-policy.html">Shipping Policy</a>';
		$BottomHtml = stripcslashes($BottomHtml[0]->html_text);
		$BottomHtmlText = str_replace('{$Site_URL}',config('global.SITE_URL'),$BottomHtml);
		$BottomHtmlText = str_replace('{$shipping_policy}',$shipping_policy,$BottomHtmlText);
		return $BottomHtmlText;
	});
}

function GetMetaInfo()
{
	$MetaInfo = Cache::remember('DefaultMetaInfo', 3600, function() {
		$PageType = 'NR';
        $MetaInfo = \App\Models\MetaInfo::where('type','=',$PageType)->get();
        if($MetaInfo->count() > 0 )
        {
            return $MetaInfo[0];
        }
		return false;
	});
}

function getEloquentSqlWithBindings($queries,$qryflag='')
{
    $AllQueries = [];
    foreach($queries as $qry)
    {
        if($qryflag == 'other')
        {
            $AllQueries[] = vsprintf(str_replace('?', '%s', $qry['query']), collect($qry['bindings'])->map(function ($binding) {
                $binding = addslashes($binding);
                return is_numeric($binding) ? $binding : "'{$binding}'";
            })->toArray());
        } else {
            $AllQueries[] = vsprintf(str_replace('?', '%s', $qry->toSql()), collect($qry->getBindings())->map(function ($binding) {
                $binding = addslashes($binding);
                return is_numeric($binding) ? $binding : "'{$binding}'";
            })->toArray());
        }
    }
    return $AllQueries;
}

function BrandsList($BrandChar='', $BrandIdArr = [])
{
	$BrandList = [];
	if($BrandChar != '')
	{
		$BrandQry = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1');
		if($BrandChar == '#')
			$BrandQry->where('m.vmanufacture','regexp','^[0-9]+');
		else
			$BrandQry->where('m.vmanufacture','like',$BrandChar.'%');
		$Brands = $BrandQry->groupBy('m.imanufactureid')->orderBy('m.vmanufacture')->get();

		if($Brands && $Brands->count() > 0)
		{
			foreach($Brands as $Brand)
			{
				$Name = remove_special_chars($Brand->vmanufacture);
				$BrandList[]=[
                    'ID' => $Brand->imanufactureid,
					'Name' => $Brand->vmanufacture,
					'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid,
				];
			}
		}
		return $BrandList;
	}

	if (!Cache::has('AllTheBrands')) {
		$BrandQry = DB::table('pu_products as po')
                ->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
                ->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
                ->where('po.status','=','1')
                ->where('m.status','=','1');
                //->where('m.vmanufacture','like',$char.'%')
                ////->groupBy('m.imanufactureid')
                ////->orderBy('m.vmanufacture')
                ////->get();
		if(!empty($BrandIdArr)){
			$BrandQry->WhereIn('m.imanufactureid',$BrandIdArr);
		}
		$Brands = $BrandQry->groupBy('m.imanufactureid')->orderBy('m.vmanufacture')->get();
		//Log::info('ClearCacheBrands : '.json_encode($Brands));
		Cache::put('AllTheBrands', $Brands);
	}else{
		$Brands = Cache::get('AllTheBrands');
		if(!empty($BrandIdArr)){
			$bBrands = json_decode(json_encode($Brands),true);

			$newBrands = array();
			for($b = 0; $b < count($BrandIdArr); $b++){
				$key = array_search($BrandIdArr[$b], array_column($bBrands, 'imanufactureid'));
				$newBrands[] = (object)$bBrands[$key];
			}
			$Brands = collect($newBrands);
		}
		//Log::info('Cached_Brands : '.json_encode($Brands));
	}
    if($Brands && $Brands->count() > 0)
    {
        $Char = "";
        foreach($Brands as $Brand)
        {
            $BrandChar = substr($Brand->vmanufacture,0,1);
            if(is_numeric($BrandChar))
            {
                $char = "#";
            } else {
                $char = strtoupper($BrandChar);
            }
            $Name = stripcslashes(remove_special_chars($Brand->vmanufacture));
            $BrandList[$char][]=[
                'ID' => $Brand->imanufactureid,
                'Name' => stripcslashes($Brand->vmanufacture),
                'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid,
            ];
        }
    }

    /*
	foreach (range('A', 'Z') as $char){
		$Brands = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1')
					->where('m.vmanufacture','like',$char.'%')
					->groupBy('m.imanufactureid')
					->orderBy('m.vmanufacture')
					->get();

		if($Brands && $Brands->count() > 0)
		{
			foreach($Brands as $Brand)
			{
				$Name = stripcslashes(remove_special_chars($Brand->vmanufacture));
				$BrandList[$char][]=[
                    'ID' => $Brand->imanufactureid,
					'Name' => stripcslashes($Brand->vmanufacture),
					'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid,
				];
			}
		}
	}

	$Brands = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1')
					->where('m.vmanufacture','regexp','^[0-9]+')
					->groupBy('m.imanufactureid')
					->orderBy('m.vmanufacture')
					->get();
	foreach($Brands as $Brand)
	{
		$Name = remove_special_chars($Brand->vmanufacture);
		$BrandList['#'][]=[
			'Name' => stripcslashes($Brand->vmanufacture),
			'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid,
		];
	}*/
	return $BrandList;
}

function BrandsList_bk_26112022($BrandChar='')
{
	$BrandList = [];
	if($BrandChar != '')
	{
		$BrandQry = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1');
		if($BrandChar == '#')
			$BrandQry->where('m.vmanufacture','regexp','^[0-9]+');
		else
			$BrandQry->where('m.vmanufacture','like',$BrandChar.'%');
		$Brands = $BrandQry->groupBy('m.imanufactureid')->orderBy('m.vmanufacture')->get();

		if($Brands && $Brands->count() > 0)
		{
			foreach($Brands as $Brand)
			{
				$Name = remove_special_chars($Brand->vmanufacture);
				$BrandList[]=[
					'Name' => $Brand->vmanufacture,
					'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid,
				];
			}
		}
		return $BrandList;
	}

	foreach (range('A', 'Z') as $char){
		$Brands = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1')
					->where('m.vmanufacture','like',$char.'%')
					->groupBy('m.imanufactureid')
					->orderBy('m.vmanufacture')
					->get();

		if($Brands && $Brands->count() > 0)
		{
			foreach($Brands as $Brand)
			{
				$Name = stripcslashes(remove_special_chars($Brand->vmanufacture));
				$BrandList[$char][]=[
					'Name' => stripcslashes($Brand->vmanufacture),
					'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid,
				];
			}
		}
	}

	$Brands = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1')
					->where('m.vmanufacture','regexp','^[0-9]+')
					->groupBy('m.imanufactureid')
					->orderBy('m.vmanufacture')
					->get();
	foreach($Brands as $Brand)
	{
		$Name = remove_special_chars($Brand->vmanufacture);
		$BrandList['#'][]=[
			'Name' => stripcslashes($Brand->vmanufacture),
			'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid,
		];
	}
	return $BrandList;
}

function remove_special_chars($str) {

	$str = preg_replace("/[,^!<>@\/()\"&#$*~`{}'?:;.?%]*/","", trim($str));
	$str = str_replace("  ", " ", strtolower($str));
	$str = str_replace(" ", "-", strtolower($str));
	$str = str_replace("--", "-", strtolower($str));
	$str = str_replace("--", "-", strtolower($str));
	return $str;
}

function remove_html_entities($str)
{
    $str = str_ireplace(array("\r","\n",'\r','\n'),'',$str);
    $str = str_replace('\\r\n','',$str);
    $str = stripslashes($str);
    $str = html_entity_decode($str);
    return $str;
}

function SetCatTree($CatArray=0)
{
	$ProdCats = [];
	//$ProdCatsData = Cache::remember('AllCategoriesInfo', 3600, function(){
		if (!Cache::has('AllTheCategory')) {
			$Categories = \App\Models\Category::where('status','=','1')->orderBy('category_id')->get();
			Cache::put('AllTheCategory', $Categories);
		}else{
			$Categories = Cache::get('AllTheCategory');
		}
		$AllCats = NewCatTree($Categories);
		$HomeLink = config('global.SITE_URL');
		$BredCrum = [];$SubCatsTree=[];$SubCats=[];
		$key = 0;
		foreach($AllCats as $MainCat)
		{
			if($MainCat->category_id == $CatArray || $CatArray == 0)
			{
				unset($BredCrum);
				$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'Level' => 0];
				$SubCatBredcrum = ucwords($MainCat->category_name);
				$BredCrum[0]['id'] = 0;
				$BredCrum[0]['title'] = 'Home';
				$BredCrum[0]['link'] = $HomeLink;
				$BredCrum[1]['id'] = $MainCat->category_id;
				$BredCrum[1]['title'] = ucwords($MainCat->category_name);
				$BredCrum[1]['link'] = $HomeLink.remove_special_chars(trim($MainCat->category_name)) . '/cid/' . $MainCat->category_id;

				$ProdCats[$MainCat->category_id] = [
					'slug' => remove_special_chars($MainCat->category_name).'/',
					'category_name' => $MainCat->category_name,
					'bredcrum' => $BredCrum,
					'subcatbredcrum' => $SubCatBredcrum,
					'parent_id' => 0,
					'root_parent_id' => 0,
				];
				if(isset($MainCat->childs) && count($MainCat->childs) > 0 ){
					//$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'Level' => 0];
					foreach($MainCat->childs as $SubLevel1){
						$SubAllCats = isset($SubLevel1->childs)?$SubLevel1->childs:[];
						$SubCatBredcrum1 = $SubCatBredcrum.' - '.ucwords($SubLevel1->category_name);
						$BredCrum[2]['id'] = $SubLevel1->category_id;
						$BredCrum[2]['title'] = ucwords($SubLevel1->category_name);
						$BredCrum[2]['link'] = $HomeLink.remove_special_chars(trim($SubLevel1->category_name)) . '/cid/' . $SubLevel1->category_id;
						$ProdCats[$SubLevel1->category_id] = [
							'slug' => remove_special_chars($MainCat->category_name).'/'.remove_special_chars($SubLevel1->category_name).'/',
							'category_name' => $SubLevel1->category_name,
							'bredcrum' => $BredCrum,
							'subcatbredcrum' => $SubCatBredcrum1,
							'parent_id' => $SubLevel1->category_id,
							'root_parent_id' => $MainCat->category_id,
						];
						$SubCatsTree[$key][]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name,'hasChild' => ($SubAllCats != null && count($SubAllCats) > 0) ? 'Yes':'No', 'Level' => 1];

						if($SubAllCats){
							foreach($SubAllCats as $SubLevel2){
								$SubCatBredcrum2= $SubCatBredcrum.' - '.ucwords($SubLevel1->category_name).' - '.ucwords($SubLevel2->category_name);
								$BredCrum[3]['id'] = $SubLevel2->category_id;
								$BredCrum[3]['title'] = ucwords($SubLevel2->category_name);
								$BredCrum[3]['link'] = $HomeLink.remove_special_chars(trim($SubLevel2->category_name)) . '/cid/' . $SubLevel2->category_id;
								$ProdCats[$SubLevel2->category_id] = [
									'slug' => remove_special_chars($SubLevel1->category_name).'/'.remove_special_chars($SubLevel2->category_name).'/',
									'category_name' => $SubLevel2->category_name,
									'bredcrum' => $BredCrum,
									'subcatbredcrum' => $SubCatBredcrum2,
									'parent_id' => $SubLevel2->category_id,
									'root_parent_id' => $MainCat->category_id,
								];
								$SubCatsTree[$key][]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name, 'Level' => 2];
								$key++;
							}
						}
						$key++;
					}
				}
			}
		}
	return ['CatForProd' => $ProdCats, 'CatTree' => $SubCatsTree];
}

function GetMainCatsTree($CatArray)
{
	if (!Cache::has('AllTheCategory')) {
		$Categories = \App\Models\Category::where('status','=','1')->orderBy('category_id')->get();
		Cache::put('AllTheCategory', $Categories);
	}else{
		$Categories = Cache::get('AllTheCategory');
	}

	$AllCats = NewCatTree($Categories);
	$SubCatsTree=[];$key=0;$SubCats=[];
	foreach($AllCats as $MainCat)
	{
		$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'link' => config('global.SITE_URL').remove_special_chars($MainCat->category_name).'/cid/'.$MainCat->category_id,'Level' => 0];
		if(in_array($MainCat->category_id,$CatArray) || $CatArray[0] == 0)
		{
			$SubCats[]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'link' => config('global.SITE_URL').remove_special_chars($MainCat->category_name).'/cid/'.$MainCat->category_id];
			if(isset($MainCat->childs) && count($MainCat->childs) > 0 ){
				$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'link' => config('global.SITE_URL').remove_special_chars($MainCat->category_name).'/cid/'.$MainCat->category_id, 'Level' => 0];

				foreach($MainCat->childs as $SubLevel1){
					$SubCats[]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name, 'link' => config('global.SITE_URL').remove_special_chars($SubLevel1->category_name).'/scid/'.$SubLevel1->category_id];
					$SubAllCats = isset($SubLevel1->childs)?$SubLevel1->childs:[];
					$SubCatsTree[$key][]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name,'hasChild' => ($SubAllCats != null && count($SubAllCats) > 0) ? 'Yes':'No', 'link' => config('global.SITE_URL').remove_special_chars($SubLevel1->category_name).'/scid/'.$SubLevel1->category_id,'Level' => 1];

					if($SubAllCats){
						foreach($SubAllCats as $SubLevel2){
							$SubCats[]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name, 'link' => config('global.SITE_URL').remove_special_chars($SubLevel2->category_name).'/scid/'.$SubLevel2->category_id];
							$SubCatsTree[$key][]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name,  'link' => config('global.SITE_URL').remove_special_chars($SubLevel2->category_name).'/scid/'.$SubLevel2->category_id, 'Level' => 2];
							$key++;
						}
					}
					$key++;
				}
			}
		}
	}
	return ['CatList' => $SubCats, 'CatTree' => $SubCatsTree];
}

function NewCatTree($Cats)
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

function SetProductURL($ProdID,$ProdName,$CategoryID='')
{
	$ProdLink = config('global.SITE_URL');
	$ProdName = remove_special_chars($ProdName);
	$AllCategoriesInfo = config('CATEGORY_INFO');
	$CatInfo = $AllCategoriesInfo['CatForProd'];
	if(!isset($CatInfo[$CategoryID])  && isset($CategoryID))
	{
		$CatId = DB::table('pu_products_category as pc')
			->select('c.category_id')
			->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
            		->where('pc.products_id', '=', $ProdID)
            		->where('c.status', '=', '1')
			->orderBy('c.display_position')
			->orderBy('c.category_name')
			->offset(0)->limit(1)->get();

		if(count($CatId) > 0)
		{
			$CategoryID = $CatId[0]->category_id;
		}

	}
	$CatLink = "";
	if(isset($CatInfo[$CategoryID]['slug']) && $CatInfo[$CategoryID]['slug']!='')
	{
		$CatLink = $CatInfo[$CategoryID]['slug'];
	}
	$ProdName = str_replace("'", "", stripslashes($ProdName));
	$ProdLink.=$CatLink.$ProdName.'/pid/'.$ProdID.'/'.$CategoryID;
	return $ProdLink;
}
function GetCategoryMenu()
{
	$Menu = [];
	$Menu = Cache::remember('Menu', 3600, function() {
		$MainBrands = \App\Models\MainbrandLanding::where('title','!=','')->where('is_show','=','Yes')->get();
		$i=0;
		if($MainBrands && $MainBrands->count() > 0){
			$MainBrandSubCats = \App\Models\BrandLandling::where('title','!=','')->where('sku','!=','')->where('status','=','1')->orderBy('position')->get();
			if($MainBrandSubCats && $MainBrandSubCats->count() > 0)
			{
				$MainBanner = '';
				if(file_exists(config('global.CAT_IMAGE_PATH').$MainBrands[0]->mega_menu_image) && $MainBrands[0]->mega_menu_image !='')
				{
					$MainBanner = config('global.CAT_IMAGE_URL').$MainBrands[0]->mega_menu_image;
				}
				$Menu[$i] = [
					'category_id' => '',
					'category_name' => $MainBrands[0]->title,
					'link' => 'javascript:void(0);',
					'banner' => $MainBanner,
				];
				foreach($MainBrandSubCats as $MainBrandSubCat)
				{
					$Menu[$i]['subcats'][] = [
						'category_id' => '',
						'category_name' => $MainBrandSubCat->title,
						'link' => config('global.SITE_URL').remove_special_chars($MainBrandSubCat->title).'/tpid/'.$MainBrandSubCat->id,
					];
				}
			}
			$i++;
		}
		$Categories = \App\Models\Category::where('status','=','1')->where('display_top','=','Yes')->orderBy('display_position')->limit(6)->get();
		foreach($Categories as $Key => $Category)
		{
			$CatBanner = '';
			if(file_exists(config('global.CAT_IMAGE_PATH').$Category->mega_menu_image) && $Category->mega_menu_image !='')
			{
				$CatBanner = config('global.CAT_IMAGE_URL').$Category->mega_menu_image;
			}
			if($Category->category_id == '12')
				$CatBanner = config('global.SITE_IMAGES').'product_newa.jpg';
			$Menu[$i]=[
				'category_id' => $Category->category_id,
				'category_name' => $Category->category_name,
				'subcats' => GetSubCategories($Category->category_id,1),
				'link' => config('global.SITE_URL').remove_special_chars($Category->category_name).'/cid/'.$Category->category_id,
				'banner' => $CatBanner,
			];
			$i++;
		}
		$Menu[$i]=[
			'category_id' => '',
			'category_name' => 'Brands',
			'subcats' => [],
			'link' => config('global.SITE_URL').'brand-name-perfumes.html',
		];
		return $Menu;
	});
}

function GetSubCategories($Parent=0,$Level=1)
{
	$Cats = [];
	$Categories = \App\Models\Category::where('status','=','1')
					->where('parent_id','=',$Parent)
					->orderBy('display_position')
					->orderBy('category_name')
					->limit(10)
					->get();
	foreach($Categories as $Key => $Category)
	{
		$Cats[] = [
			'category_id' => $Category->category_id,
			'category_name' => $Category->category_name,
			'subcats' => GetSubCategories($Category->category_id,2),
			'link' => config('global.SITE_URL').remove_special_chars($Category->category_name).'/scid/'.$Category->category_id,
		];
	}
	if($Level == 1)
	{
		$Cats[] = [
			'category_id' => '',
			'category_name' => 'Sale',
			'subcats' => [],
			'link' => config('global.SITE_URL').'perfumesale/p4u/special-sl/view',
		];
		if(getDealofweekCount())
		{
			$Cats[] = [
				'category_id' => '',
				'category_name' => 'Weekly Deals',
				'subcats' => [],
				'link' => config('global.SITE_URL').'dealofweek.html',
			];
		}
		$Cats[] = [
			'category_id' => '',
			'category_name' => 'Coupons',
			'subcats' => [],
			'link' => config('global.SITE_URL').'coupons-promotional.html',
		];
		if($Parent == '1')
		{
			$UnboxCat = \App\Models\Category::find(198);
			if($UnboxCat && $UnboxCat->count() > 0)
			{
				$Cats[] = [
					'category_id' => $UnboxCat->category_id,
					'category_name' => 'Unboxed Items',
					'subcats' => [],
					'link' => config('global.SITE_URL').remove_special_chars($UnboxCat->category_name).'/cid/'.$UnboxCat->category_id,
				];
			}
		}
	}
	return $Cats;
}

function getDealofweekCount() {
	$Dealofweek  = \App\Models\Dealofweek::where('start_date','<=',date('Y-m-d H:i'))->where('end_date','>=',date('Y-m-d H:i'))->where('status','=','1')->where('deal_type','=','Weekly')->get();
	if($Dealofweek && $Dealofweek->count() > 0){
		return true;
	} else {
		return false;
	}
}

function GetReviewOfProducts()
{
	$ProductReviews = DB::table('pu_products_review as pr')
						->join('pu_products as p','pr.sku','=','p.sku')
						->join('pu_products_category as pc','p.products_id','=','pc.products_id')
						->join('pu_category as c','pc.category_id','=','c.category_id')
						->join('pu_manufacture as m','p.imanufactureid','=','m.imanufactureid')
						->select('pr.first_name','pr.city','pr.state','pr.country','pr.star_rate','pr.user_review','p.products_id','p.sku', 'p.product_name','p.brand_id','p.imanufactureid','m.vmanufacture','p.gender','pc.category_id')
						->where('pr.approved','=','Yes')
						->where('pr.star_rate','>','3')
						->where('p.status','=','1')
						->orderBy('pr.review_id','desc')
						->groupBy('p.products_id')
						->limit(10)
						->get();
	$AllReviewes = [];
	if($ProductReviews && $ProductReviews->count() > 0)
	{
		foreach($ProductReviews as $Review)
		{
			$Review->referencedName = $Review->product_name;
			$AllReviewes[]=$Review;
		}
	}
	return $AllReviewes;
}

function GetMailTemplate($Template)
{
	if($Template != "")
	{
		$TemplateDetails = \App\Models\EmailTemplates::select('subject','mail_body')
							->where('template_var_name','=',$Template)
							->where('status','=','1')
							->get();
		if($TemplateDetails && $TemplateDetails->count() > 0)
			return $TemplateDetails;
		else
			return false;
	} else {
		return false;
	}
}
function SendMail($Subject,$EmailBody,$To,$From,$CC='',$BCC='')
{
	$SendMail = $MailSend = Mail::send(array(), array(), function ($message) use ($To,$Subject,$EmailBody,$From,$CC,$BCC) {
		//$message->from($From,"Maxaroma");
		$message->to($To)
			->subject($Subject)
			->setBody($EmailBody, 'text/html');
		if($CC != '')
			$message->cc($CC);
		if($BCC != '')
			$message->bcc($BCC);
	});
}

if(!function_exists('FreeGiftValue')){
	function FreeGiftValue($subtotal) {
		$free_gift_array = array();
		if($subtotal >= config('Settings')->FREEGIFT_VALUE) {
			if(config('Settings')->BEAUTY_SAMPLE == "Yes") {
				$free_gift_array[] = "Beauty & Accessories Sample";
			}
			if(config('Settings')->PERFUME_SAMPLE == "Yes") {
				$free_gift_array[] = "Perfume Sample";
			}
		} else {
			return $free_gift_array;
		}
		return $free_gift_array;
	}
}
if(!function_exists('dateDiffInDays')){
	function dateDiffInDays($date1, $date2)
	{
		$diff = strtotime($date2) - strtotime($date1);
		return abs(round($diff / 86400));
	}
}
/** DROPSHIPPER FUNCTIONS START **/

function CheckAvailableShippingMethod($shipping_mode_id = NULL, $ship_country="",$ship_state="",$ship_zip="",$subTotal=0,$TotalQuantity=0)
{
	$shipping_mode_id = (int)$shipping_mode_id;

	$ShippingMethodRS = \App\Models\ShippingMode::where('shipping_mode_id','=',$shipping_mode_id)
						->where('status','=','1')
						->get();

	/*if ($ship_country != "")
	{
		## this condition is for Z + S + C
		$sql = "SELECT * FROM `".TABLE_PREFIX."shipping_rule` WHERE shipping_mode_id = '".$shipping_mode_id."'
				AND zipcode_to >= '".$ship_zip."' AND zipcode_from <= '".$ship_zip."'
				AND state like '%".$ship_state."%' AND country like '%".$ship_country."%'";
		$rid = $obj->select($sql);

		## this condition is for Z + C
		if (count($rid) <= 0)
		{
			$sql = "SELECT * FROM `".TABLE_PREFIX."shipping_rule`
					WHERE shipping_mode_id = '".$shipping_mode_id."'
					AND zipcode_to >='".$ship_zip."' AND zipcode_from <= '".$ship_zip."'
					AND country LIKE '%".$ship_country."%'";
			$rid = $obj->select($sql);

			## this condition is for S + C
			if (count($rid) <= 0)
			{
				$sql = "SELECT * FROM `".TABLE_PREFIX."shipping_rule`
						WHERE shipping_mode_id = '".$shipping_mode_id."' AND state LIKE '%".$ship_state."%'
						AND country LIKE '%".$ship_country."%'";
				$rid = $obj->select($sql);

				## this condition is for only C
				if (count($rid) <= 0)
				{
					$sql = "SELECT * FROM `".TABLE_PREFIX."shipping_rule`
							WHERE shipping_mode_id = '".$shipping_mode_id."'
							AND country like '%".$ship_country."%'
							AND state = '' AND zipcode_to = '' AND zipcode_from = ''";
					$rid = $obj->select($sql);

				}
			}
		}

		if (count($rid) > 0 )
		{
             return (int)$ShippingMethodRS[0]['shipping_mode_id'];

		}
		else
		{
			return false;
		}
	}
	else
	{
		return false;
	}*/
}
function GetBanner($bannertype,$imanufactureid='',$sku='')
{
	/*
	$bannertype = explode(",",$bannertype);
	$condition = '';
			$bannercondition = '';
			$bannerShow = "No";
			for($m=0;$m<count($bannertype);$m++) {
					$condition.=" (((start_date <= '".date('Y-m-d')."' AND end_date >= '".date('Y-m-d')."') OR (start_date = '0000-00-00' AND end_date >= '0000-00-00')) AND section = ".$bannertype[$m].") OR ";
					if($bannertype[$m]=="'PRODUCT BANNER'")
					{
						$bannercondition = 'PRODUCT BANNER';
					}
				}
			$condition = substr($condition,0,-3);

			$BrandDetail = HomeImage::where('imanufactureid','=',$imanufactureid)
									->where('start_date','<=',date("Y-m-d")
									->where('end_date','<=',date("Y-m-d")
									->where('start_date','<=',date("Y-m-d")
									->where('start_date','<=',date("Y-m-d"))->get();
	*/
}

/** DROPSHIPPER FUNCTIONS END **/

function GetOrderStatusClass($status) {
	if($status == 'Pending') {
		$status_class = 'text_red';
	} elseif($status == 'Completed') {
		$status_class = 'text_green';
	} elseif($status == 'Canceled') {
		$status_class = 'text_red';
	} elseif($status == 'Declined') {
		$status_class = 'text_red';
	} elseif($status == 'Pending - PhoneOrder') {
		$status_class = 'text_red';
	} else {
		$status_class = '';
	}
	return $status_class;
}

function GetProductAverageRating($TotalReview,$TotalRate)
{
	$average_bottom = (int)@($TotalRate/$TotalReview);
	$average_real = @($TotalRate/$TotalReview);
	if(($average_real-$average_bottom)>=0.5)
		$average_rate = ceil($TotalRate/$TotalReview);
	else
		$average_rate =  $average_bottom;
	if($average_rate>5)
		$average_rate=5;
	return $average_rate;
}

function GetDealOfWeek($SKU='',$DealType='Weekly',$ForPage='',$ismultipleSku = array())
{
	$DealDetails =[];
	$FlagCache= "Yes";
	$DealQuery = DB::table('pu_dealofweek as dw')
					->select('dw.dealofweek_id','dw.description','dw.deal_type','dw.discount_coupon_flag','dw.product_sku','dw.start_date','dw.end_date','dw.deal_price','p.retail_price','p.product_name','p.image','p.imanufactureid','p.short_description')
					->join('pu_products as p','dw.product_sku','=','p.sku')
					->where('dw.status','=','1')
					->where('dw.start_date','<=',date('Y-m-d H:i'))->where('dw.end_date','>=',date('Y-m-d H:i'))
					->where('dw.deal_type','=',$DealType)->where('p.current_stock', '>', 0);
	if(!empty($SKU) && empty($ismultipleSku))
	{
		$FlagCache= "No";
		$DealQuery->where('dw.product_sku','=',$SKU);
		$DealQuery->where('p.sku','=',$SKU);
	}
	if(empty($SKU) && !empty($ismultipleSku))
	{
		$FlagCache= "No";
		$DealQuery->whereIn('dw.product_sku',$ismultipleSku);
		$DealQuery->whereIn('p.sku',$ismultipleSku);
	}
	if($ForPage == 'Cart' || $ForPage == 'ForProductDetail')
	{
		$FlagCache= "No";
		$DealQuery->join('pu_products_one as po','p.products_id','=','po.products_id');
		$DealQuery->where(function($query){
			$query->orWhere('p.status','=','1');
			$query->OrWhere(function($qry){
				$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');
			});
		});
		$DealQuery->orderBy('dw.dealofweek_id','desc')->limit(1);
	} else {
		$DealQuery->where('p.status','=','1');
	}

	if($FlagCache=="Yes")
	{
		$CacheName = "DealProd".date('Y-m-d');
		//Cache::forget($CacheName);
		if(!Cache::has($CacheName)) {
			$Dealofweek = $DealQuery->get();
			Cache::put($CacheName, $Dealofweek);
			$currentDateVal = date('Y-m-d');
			$dddateVal = date('Y-m-d', strtotime('-1 day', strtotime($currentDateVal)));
			Cache::forget('DealProd'.$dddateVal);
		}
		else
		{
			$Dealofweek = Cache::get($CacheName);

		}
	}
	else
	{
		$Dealofweek = $DealQuery->get();
	}
	if($Dealofweek && $Dealofweek->count() > 0)
	{
		foreach($Dealofweek as $Deal)
		{
			if(count($ismultipleSku) == 0  && $SKU!='')
			{
				$Deal->product_sku = $SKU;
			}
			// $Deal->product_sku = strtoupper($Deal->product_sku);
			$DealDetails[$Deal->product_sku]['deal_price'] = $Deal->deal_price;
			$DealDetails[$Deal->product_sku]['start_date'] = $Deal->start_date;
			$DealDetails[$Deal->product_sku]['end_date'] = $Deal->end_date;
			$DealDetails[$Deal->product_sku]['deal_type'] = $DealType;
			$YouSave=0;
			$YouSavePrice = 0;
			if($Deal->retail_price > 0 && $Deal->retail_price > $Deal->deal_price)
			{
				$YouSave = ($Deal->retail_price - $Deal->deal_price) / $Deal->retail_price;
				$YouSave = $YouSave * 100;
				$YouSave = number_format($YouSave, 0);
				$YouSavePrice = $Deal->retail_price - $Deal->deal_price;
			}
			$DealDetails[$Deal->product_sku]['yousave'] = $YouSave;
			$DealDetails[$Deal->product_sku]['yousaveprice'] = $YouSavePrice;

			$imgname = stripslashes($Deal->image);
			if(file_exists(config('global.PRD_THUMB_IMG_PATH').$imgname) and !empty($imgname))
			{
				$newimageVal = config('global.PRD_THUMB_IMG_PATH').$imgname;
				$verP =filemtime($newimageVal);
				$thumb_image = config('global.PRD_THUMB_IMG_URL').$imgname."?ver=".$verP;
			}else{
				$thumb_image = config('global.NO_IMAGE_THUMB');
			}
			$Deal->image = $thumb_image;
			$DealDetails[$Deal->product_sku]['description'] = $Deal->description;
			$DealDetails[$Deal->product_sku]['discount_coupon_flag'] = $Deal->discount_coupon_flag;
		}
	}
	return $DealDetails;
}

function GetCancelReasons() {
	$CancelReason = array("Item no longer needed","Better price elsewhere","Purchased item by mistake","Changed my mind","Other");
	return $CancelReason;
}

function GetReturnReasons() {
	$ReturnReason = array("Item no longer needed","Quality not as expected","Not as described/pictured","Item Damaged","Wrong item received","Better price elsewhere","Purchased item by mistake","Changed my mind","Other");
	return $ReturnReason;
}

function GetRefundReasons() {
	$RefundReason = array("Customer order item by mistake","Item did not come back in its original condition","customer changed mind or no longer needed","Other");
	return $RefundReason;
}

function CanonicalURL()
{
	return url()->current();
	/*
	$CurrentURL = config('app.url') . request()->path();
	$CanonicalURL = '';

	//Home Page
	$CheckHomeURLForSlash = substr(config('global.SITE_URL'),strlen(config('global.SITE_URL'))-1,strlen(config('global.SITE_URL')));
	if($CheckHomeURLForSlash == '/')
	{
		$HomeURL = substr(config('global.SITE_URL'),0,strlen(config('global.SITE_URL'))-1);
		if($CurrentURL == $HomeURL)
			$CanonicalURL = $HomeURL;
	}
	//Sub Category Pages
	if(strstr($CurrentURL,'scid'))
	{
		$URLV = explode("/",$CurrentURL);
		$CanonicalURL = $CurrentURL;
		if($URLV[count($URLV)-1] == '2')
		{
			$CanonicalURL = config('global.SITE_URL')."fragrances/niche-perfumes/p4u/cid-2/page-all/view";
		}
	}

	//Product Listing Pages
	if(strstr($CurrentURL,'p4u'))
	{
		$URLV 	  = explode("/p4u/",$CurrentURL);
		$urlValue = str_replace("/peraromares/","",$URLV[0]);
		$urlValue = str_replace("/peraromares","",$urlValue);

		$NewCanURL = str_replace("/peraromares/","",$CurrentURL);
		$NewCanURL = str_replace("/peraromares","",$CurrentURL);
		$NewCanURL = str_replace("pp-all/","",$CurrentURL."/");
		$NewCanURL = str_replace("/view/","",$CurrentURL."/");
		$NewCanURL = str_replace("/view","",$CurrentURL."/");
		$NewCanURL = $NewCanURL."pp-all/view";

		$CanonicalURL = $NewCanURL;
		if(isset($URLV[1]))
        {
            if($URLV[1] == 'cid-1/view' || $URLV[1] == 'cid-1/pp-all/view' || $URLV[1] == 'cid-1/page-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."fragrances/p4u/cid-1/page-all/view";
            }
            else if($URLV[1] == 'cid-3/special-fe/view' || $URLV[1] == 'cid-3/special-na/view' || $URLV[1] == 'cid-3/special-ts/view' || $URLV[1] == 'cid-3/special-cl/view' || $URLV[1] == 'cid-3/view' || $URLV[1] == 'cid-3/page-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."fragrances-for-men/p4u/cid-3/page-all/view";
            }
            else if($URLV[1] == 'cid-5/view' || $URLV[1] == 'cid-5/pp-all/view' || $URLV[1] == 'cid-5/page-all/view' || $URLV[1] == 'cid-5/page-all/pp-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."fragrances-for-women/p4u/cid-5/page-all/view";
            }
            else if($URLV[1] == 'cid-2/special-fe/view' || $URLV[1] == 'cid-2/special-na/view' || $URLV[1] == 'cid-2/special-ts/view' || $URLV[1] == 'cid-2/special-cl/view' || $URLV[1] == 'cid-2/view' || $URLV[1] == 'cid-2/page-all/view' || $URLV[1] == 'cid-2/page-all/pp-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."fragrances/niche-perfumes/p4u/cid-2/page-all/view";
            }
            else if($URLV[1] == 'cid-1/special-cp/view' || $URLV[1] == 'cid-1/special-cp/pp-all/view' || $URLV[1] == 'cid-1/special-cp/page-all/view' || $URLV[1] == 'cid-1/special-cp/page-all/pp-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."fragrances/celebrity-perfumes/p4u/cid-1/special-cp/page-all/view";
            }
            else if($URLV[1] == 'cid-4/special-fe/view' || $URLV[1] == 'cid-4/special-na/view' || $URLV[1] == 'cid-4/special-ts/view' || $URLV[1] == 'cid-4/special-cl/view' || $URLV[1] == 'cid-4/view' || $URLV[1] == 'cid-4/pp-all/view' || $URLV[1] == 'cid-4/page-all/view' || $URLV[1] == 'cid-4/page-all/pp-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."fragrances/unisex-perfumes/p4u/cid-4/page-all/view";
            }
            else if($URLV[1] == 'mid-43/view' || $URLV[1] == 'mid-43/pp-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."tom-ford-fragrances/p4u/mid-43/pp-all/view";
            }
            else if($URLV[1] == 'mid-14/view' || $URLV[1] == 'mid-14/pp-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."creed-fragrances/p4u/mid-14/pp-all/view";
            }
            else if($URLV[1] == 'mid-312/view' || $URLV[1] == 'mid-312/pp-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."amouage-fragrances/p4u/mid-312/pp-all/view";
            }
            else if($URLV[1] == 'mid-282/view' || $URLV[1] == 'mid-282/pp-all/view')
            {
                $CanonicalURL = config('global.SITE_URL')."parfums-de-marly-fragrances/p4u/mid-282/pp-all/view";
            }
        }
	}
	return $CanonicalURL;
	*/
}
function getstaticpages($flag)
{

	if(is_array($flag))
	{
		$static_res = array();
		if(in_array('shipping_information',$flag) && in_array('return_exchange_policy',$flag))
		{
			if(!Cache::has('ShippingStaticCache'))
			{
				$static_res  =$static_res = \App\Models\StaticPages::whereIn('name',$flag)->get();
				$pageCnt = count($static_res);
				Cache::put('ShippingStaticCache', $static_res);
			}
			else
			{
				$static_res = Cache::get('ShippingStaticCache');
			}
		}

		$pageCnt = count($static_res);

		for($i=0;$i<$pageCnt;$i++){
			if (str_contains($static_res[$i]->content, '{$Site_URL}')) {
				$static_res[$i]->content = str_replace('{$Site_URL}', config('global.SITE_URL'), $static_res[$i]->content);
			}
		}
	}
	else
	{
		$static_res  =$static_res = \App\Models\StaticPages::where('name','=',$flag)->get();
	}
    return $static_res;
}

function GenRequiredFields()
{
	return array(
			    'orders_no',
			    'sku',
			    'qty',
			    'first_name',
			    'last_name',
			    'address1',
			    'city',
			    'state',
			    'zip',
			    'country',
			    'email'
			);
}

function GenCSVFieldsArr()
{
	return array(
			    'orders_no' => array(
			        'import_field' => 'orders_no',
			        'export_field' => 'orders_no',
			        'import_header_val' => 'Orders No',
			        'export_header_val' => 'Orders No'
			    ),
			    'sku' => array(
			        'import_field' => 'sku',
			        'export_field' => 'sku',
			        'import_header_val' => 'SKU',
			        'export_header_val' => 'SKU'
			    ),
			    'qty' => array(
			        'import_field' => 'quantity',
			        'export_field' => 'quantity',
			        'import_header_val' => 'Qty',
			        'export_header_val' => 'Qty'
			    ),
			    'first_name' => array(
			        'import_field' => 'ship_first_name',
			        'export_field' => 'ship_first_name',
			        'import_header_val' => 'First Name',
			        'export_header_val' => 'First Name'
			    ),
			    'last_name' => array(
			        'import_field' => 'ship_last_name',
			        'export_field' => 'ship_last_name',
			        'product_field' => 'ship_last_name',
			        'import_header_val' => 'Last Name',
			        'export_header_val' => 'Last Name'
			    ),
			    'address1' => array(
			        'import_field' => 'ship_address1',
			        'export_field' => 'ship_address1',
			        'product_field' => 'ship_address1',
			        'import_header_val' => 'Address1',
			        'export_header_val' => 'Address1'
			    ),
			    'address2' => array(
			        'import_field' => 'ship_address2',
			        'export_field' => 'ship_address2',
			        'product_field' => 'ship_address2',
			        'import_header_val' => 'Address2',
			        'export_header_val' => 'Address2'
			    ),
			    'city' => array(
			        'import_field' => 'ship_city',
			        'export_field' => 'ship_city',
			        'product_field' => 'ship_city',
			        'import_header_val' => 'City',
			        'export_header_val' => 'City'
			    ),
			    'state' => array(
			        'import_field' => 'ship_state',
			        'export_field' => 'ship_state',
			        'product_field' => 'ship_state',
			        'import_header_val' => 'State',
			        'export_header_val' => 'State'
			    ),
			    'country' => array(
			        'import_field' => 'ship_country',
			        'export_field' => 'ship_country',
			        'product_field' => 'ship_country',
			        'import_header_val' => 'Country',
			        'export_header_val' => 'Country'
			    ),
			    'zip' => array(
			        'import_field' => 'ship_zip',
			        'export_field' => 'ship_zip',
			        'product_field' => 'ship_zip',
			        'import_header_val' => 'Zip',
			        'export_header_val' => 'Zip'
			    ),
			    'phone' => array(
			        'import_field' => 'ship_phone',
			        'export_field' => 'ship_phone',
			        'product_field' => 'ship_phone',
			        'import_header_val' => 'Phone',
			        'export_header_val' => 'Phone'
			    ),
			    'email' => array(
			        'import_field' => 'ship_email',
			        'export_field' => 'ship_email',
			        'product_field' => 'ship_email',
			        'import_header_val' => 'Email',
			        'export_header_val' => 'Email'
			    )

			);
}

function CSVFieldsDropshipperOrder()
{
	return array(
			    'orders_no' => array(
			        'import_field' => 'orders_no',
			        'export_field' => 'orders_no',
			        'import_header_val' => 'Orders No',
			        'export_header_val' => 'Orders No'
			    ),
			    'first_name' => array(
			        'import_field' => 'ship_first_name',
			        'export_field' => 'ship_first_name',
			        'import_header_val' => 'First Name',
			        'export_header_val' => 'First Name'
			    ),
			    'last_name' => array(
			        'import_field' => 'ship_last_name',
			        'export_field' => 'ship_last_name',
			        'product_field' => 'ship_last_name',
			        'import_header_val' => 'Last Name',
			        'export_header_val' => 'Last Name'
			    ),
			    'address1' => array(
			        'import_field' => 'ship_address1',
			        'export_field' => 'ship_address1',
			        'product_field' => 'ship_address1',
			        'import_header_val' => 'Address1',
			        'export_header_val' => 'Address1'
			    ),
			    'address2' => array(
			        'import_field' => 'ship_address2',
			        'export_field' => 'ship_address2',
			        'product_field' => 'ship_address2',
			        'import_header_val' => 'Address2',
			        'export_header_val' => 'Address2'
			    ),
			    'city' => array(
			        'import_field' => 'ship_city',
			        'export_field' => 'ship_city',
			        'product_field' => 'ship_city',
			        'import_header_val' => 'City',
			        'export_header_val' => 'City'
			    ),
			    'state' => array(
			        'import_field' => 'ship_state',
			        'export_field' => 'ship_state',
			        'product_field' => 'ship_state',
			        'import_header_val' => 'State',
			        'export_header_val' => 'State'
			    ),
			    'country' => array(
			        'import_field' => 'ship_country',
			        'export_field' => 'ship_country',
			        'product_field' => 'ship_country',
			        'import_header_val' => 'Country',
			        'export_header_val' => 'Country'
			    ),
			    'zip' => array(
			        'import_field' => 'ship_zip',
			        'export_field' => 'ship_zip',
			        'product_field' => 'ship_zip',
			        'import_header_val' => 'Zip',
			        'export_header_val' => 'Zip'
			    ),
			    'phone' => array(
			        'import_field' => 'ship_phone',
			        'export_field' => 'ship_phone',
			        'product_field' => 'ship_phone',
			        'import_header_val' => 'Phone',
			        'export_header_val' => 'Phone'
			    ),
			    'email' => array(
			        'import_field' => 'ship_email',
			        'export_field' => 'ship_email',
			        'product_field' => 'ship_email',
			        'import_header_val' => 'Email',
			        'export_header_val' => 'Email'
			    )

			);
}

function CSVFieldsDropshipperOrderDetail()
{
	return array(
			    'orders_no' => array(
			        'import_field' => 'orders_no',
			        'export_field' => 'orders_no',
			        'import_header_val' => 'Orders No',
			        'export_header_val' => 'Orders No'
			    ),
			    'sku' => array(
			        'import_field' => 'sku',
			        'export_field' => 'sku',
			        'import_header_val' => 'SKU',
			        'export_header_val' => 'SKU'
			    ),
			    'qty' => array(
			        'import_field' => 'quantity',
			        'export_field' => 'quantity',
			        'import_header_val' => 'Qty',
			        'export_header_val' => 'Qty'
			    ),

			);
}

function GetSpecialPricePercentandValue($qty)
{
	$per = 0;
	$val = 0;
	$db_recs = \App\Models\MarkupPrices::all();
	// if(!empty($db_recs)) {
	if($db_recs && $db_recs->count() > 0) {
		foreach ($db_recs as $markup_price_key => $markup_price_value) {
			if($markup_price_value->markup_value !="" && $markup_price_value->markup_value != "0" && $markup_price_value->markup_percent !="" && $markup_price_value->markup_percent !="0") {
				$mvalu = explode("-",$markup_price_value->markup_value);
				$mvalcount = count($mvalu);
				if($mvalcount>1) {
					if($qty >= $mvalu[0] && $qty <= $mvalu[1]) {
						$per = $markup_price_value->markup_percent;
						$val = $markup_price_value->markup_value;
					}
				} else {
					if($qty > $mvalu[0]) {
						$per = $markup_price_value->markup_percent;
						$val = $markup_price_value->markup_value;
					}
				}
			}
			//if($per != '') {
			if($per != '' && $per != 0 ) {
				break;
			}
		}
	}
	return $per."#".$val;
}

function getWholesalerSpecialPricesDetails($product_price){

	if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
	{
		$is_special_price_enable = 1;
	}
	else
	{
		$is_special_price_enable = 0;
	}
	$quty_detail = '';
	$dis_detail = '';
	$SpecialPriceDetails = "";
	if($is_special_price_enable == 1)
	{
		$per = '';
		$db_recs = \App\Models\MarkupPrices::all();
		if($db_recs && $db_recs->count() > 0) {
			$quty_detail .= '<table> <tr><th class="fsbold">Quantity</th>
						 <th class="fsbold">Discount Offer Price</th></tr>';
			$quty_detail .= '<tr>';
			foreach ($db_recs as $markup_price_key => $markup) {
				if($markup->markup_lable != "" && $markup->markup_lable != '0' && $markup->markup_percent != "" && $markup->markup_percent != '0'){
					$NewPrice = $product_price - ($product_price*$markup->markup_percent)/100;
					$quty_detail .= ' <tr><td>'.$markup->markup_lable.'</td><td>'.NumberFormat($NewPrice).'</td></tr>';
				}
			}
			$quty_detail.= '</tr></table>';
		}
		$SpecialPriceDetails = $quty_detail;
	}
	return $SpecialPriceDetails;
}
function NumberFormat($val)
{
	if($val == '')
		$val = 0;
	$val = (float)$val;
	return number_format( $val , 2, '.','');
}

// function added in global service provider
function GetDealCheckProduct()
{
	$deal_product =  DB::table('pu_dealofweek as dw')
		->join('pu_dealofweektitle as dwt', 'dw.did', '=', 'dwt.did')
		->join('pu_products as p', 'dw.product_sku', '=', 'p.sku')
		->select('dw.dealofweek_id', 'dw.product_sku', 'dw.start_date', 'dw.end_date', 'dw.deal_price')
		->where('p.status', '=', '1')
		->where('dw.status', '=', '1')
		->where('start_date', '<=', date('Y-m-d H:i'))
		->where('end_date', '>=', date('Y-m-d H:i'))
		->get();
	$dealcheck_array = array();
	$dealcompare_array = array();
	if (count($deal_product) > 0) {
		for ($i = 0; $i < count($deal_product); $i++) {
			$dealcheck_array[] .= trim($deal_product[$i]->product_sku);
			$deal_product[$i]->deal_end = date("Y-m-d");
			$dealcompare_array[trim($deal_product[$i]->product_sku)] = $deal_product[$i];
		}
	}

	return array('dealcheck_array'=> $dealcheck_array,'dealcompare_array' => $dealcompare_array);
}

// function added in global service provider
function GetDayDealCheckProduct()
{
	$ddeal_product =  DB::table('pu_dealofweek as dw')
			->join('pu_products as p', 'dw.product_sku', '=', 'p.sku')
			->select('dw.dealofweek_id', 'dw.product_sku', 'dw.start_date', 'dw.end_date', 'dw.deal_price', 'p.product_name', 'p.image', 'p.imanufactureid', 'p.short_description')
			->where('p.status', '=', '1')
			->where('dw.status', '=', '1')
			->where('start_date', '<=', date('Y-m-d H:i'))
			->where('end_date', '>=', date('Y-m-d H:i'))
			->where('deal_type', '=', 'Daily')
			->offset(0)->limit(1)->get();
		$ddealcheck_array = array();
		$ddealcompare_array = array();
		$aroma_popup_flg = 0;
		if (count($ddeal_product) > 0) {
			for ($i = 0; $i < count($ddeal_product); $i++) {
				$ddealcheck_array[] = trim($ddeal_product[$i]->product_sku);
				$ddeal_product[$i]->deal_end = date("Y-m-d");
				$ddealcompare_array[trim($ddeal_product[$i]->product_sku)] = $ddeal_product[$i];
				$ddealcheck_array['product_name'] = trim($ddeal_product[$i]->product_name);
				$ddealcheck_array['image'] = '$brnlogo';
			}
		}

	return array('ddealcheck_array'=> $ddealcheck_array,'ddealcompare_array' => $ddealcompare_array);
}

function GetFrontMegaMenu()
{
	$menu_array = Cache::remember('menu_array', 3600, function() {
		$parentCategories =  DB::table('pu_menu_front')
							->select('menu_title', 'menu_id', 'menu_link', 'rank', 'status','parent_id')
							->where('parent_id', '=', 0)
							->where('status', '=', '1')
							->orderBy('rank', 'ASC')
							->get()->toArray();
		// echo $_SERVER['REMOTE_ADDR'];
		// 49.34.169.128
		$mainArray = [];
		$level = 1;
		if(count($parentCategories) > 0) {
			foreach($parentCategories as $pcKey => $pcValue) {
				$mainArray[$pcKey]['menu_id'] = $pcValue->menu_id;
				$mainArray[$pcKey]['menu_title'] = $pcValue->menu_title;
				$mainArray[$pcKey]['menu_link'] = $pcValue->menu_link;
				$mainArray[$pcKey]['rank'] = $pcValue->rank;
				$mainArray[$pcKey]['status'] = $pcValue->status;
				$mainArray[$pcKey]['parent_id'] = $pcValue->parent_id;
				$parentCategories[$pcKey]->level = $level;
				$labels =  DB::table('pu_menu_front')
							->select('menu_title', 'menu_id', 'menu_link', 'rank', 'status','parent_id')
							->where('parent_id', '=', $pcValue->menu_id)
							->where('is_label', '=', '1')
							->where('status', '=', '1')
							->orderBy('rank', 'ASC')
							->get()->toArray();
				$cat_labels_count =  DB::table('pu_menu_front')
							->select('menu_title', 'menu_id', 'menu_link', 'rank', 'status')
							->where('parent_id', '=', $pcValue->menu_id)
							->where('is_label', '=', '1')
							->where('menu_title', '!=', 'Custom Tag Link - Banner Section')
							->where('status', '=', '1')
							->count();
				$parentCategories[$pcKey]->label_count = count($labels);
				$mainArray[$pcKey]['label_count'] = count($labels);
				$total_columns = 5;
				$display_banners_count = $total_columns - $cat_labels_count;
				$mainArray[$pcKey]['display_banners_count'] = $display_banners_count;
				$labelArray = [];
				if(count($labels) > 0) {
					foreach($labels as $labelKey => $labelVaue) {
						$labelArray[$labelKey]['menu_id'] = $labelVaue->menu_id;
						$labelArray[$labelKey]['menu_title'] = $labelVaue->menu_title;
						$labelArray[$labelKey]['menu_link'] = $labelVaue->menu_link;
						// $labelArray[$labelKey]['is_below'] = $labelVaue->is_below;
						$labelArray[$labelKey]['rank'] = $labelVaue->rank;
						$labelArray[$labelKey]['status'] = $labelVaue->status;
						$labelArray[$labelKey]['parent_id'] = $labelVaue->parent_id;
			        	$labelArray[$labelKey]['childs'] = array();
				        getSubCats($labelVaue->menu_id, $labelArray[$labelKey]['childs'],$level+1);
					}
				}
				$mainArray[$pcKey]['label'] = $labelArray;
			}
		}
		// dd($mainArray);
		$menu_array = $mainArray;
		return $menu_array;
	});
}

function getSubCats($parent_id = 0, &$categoriesArray = array(),$level=0) {
	$allSubCategories =  DB::table('pu_menu_front')
				->select('menu_title', 'menu_id', 'menu_link', 'rank', 'status', 'menu_image', 'menu_image1', 'menu_image2', 'menu_label', 'menu_label1', 'menu_label2', 'menu_custom_link', 'menu_custom_link1', 'menu_custom_link2')
				->where('parent_id', '=', (int)$parent_id)
				->where('is_label', '=', '0')
				->where('status', '=', '1')
				->orderBy('rank', 'ASC')
				->get()->toArray();

    foreach($allSubCategories as $k => $category) {
		$categoriesArray[$k]['menu_id'] = $category->menu_id;
		$categoriesArray[$k]['menu_title'] = $category->menu_title;
		$categoriesArray[$k]['menu_link'] = $category->menu_link;

		if (file_exists(config('global.FRONT_MENU_IMAGE_PATH') . $category->menu_image) && $category->menu_image != '') {
			$newimageVal = config('global.FRONT_MENU_IMAGE_PATH')  . stripslashes($category->menu_image);
			$verP = filemtime($newimageVal);
			$categoriesArray[$k]['menu_image'] = config('global.FRONT_MENU_IMAGE_URL') . $category->menu_image . "?ver=" . $verP;
		}else{
			$categoriesArray[$k]['menu_image'] = $category->menu_image;
		}

		if (file_exists(config('global.FRONT_MENU_IMAGE_PATH') . $category->menu_image1) && $category->menu_image1 != '') {
			$newimageVal = config('global.FRONT_MENU_IMAGE_PATH')  . stripslashes($category->menu_image1);
			$verP = filemtime($newimageVal);
			$categoriesArray[$k]['menu_image1'] = config('global.FRONT_MENU_IMAGE_URL') . $category->menu_image1 . "?ver=" . $verP;
		}else{
			$categoriesArray[$k]['menu_image1'] = $category->menu_image1;
		}

		if (file_exists(config('global.FRONT_MENU_IMAGE_PATH') . $category->menu_image2) && $category->menu_image2 != '') {
			$newimageVal = config('global.FRONT_MENU_IMAGE_PATH')  . stripslashes($category->menu_image2);
			$verP = filemtime($newimageVal);
			$categoriesArray[$k]['menu_image2']  = config('global.FRONT_MENU_IMAGE_URL') . $category->menu_image2 . "?ver=" . $verP;
		}else{
			$categoriesArray[$k]['menu_image2'] = $category->menu_image2;
		}

		$categoriesArray[$k]['menu_label'] = $category->menu_label;
		$categoriesArray[$k]['menu_label1'] = $category->menu_label1;
		$categoriesArray[$k]['menu_label2'] = $category->menu_label2;
		$categoriesArray[$k]['menu_custom_link'] = $category->menu_custom_link;
		$categoriesArray[$k]['menu_custom_link1'] = $category->menu_custom_link1;
		$categoriesArray[$k]['menu_custom_link2'] = $category->menu_custom_link2;

		$categoriesArray[$k]['rank'] = $category->rank;
		$categoriesArray[$k]['status'] = $category->status;
		$categoriesArray[$k]['level'] = $level;
    	$categoriesArray[$k]['childs'] = array();
    	getSubCats($category->menu_id,$categoriesArray[$k]['childs'],$level+1);
    }
}

function getCategoriesHTML($category)
{
    $html = "";
    foreach ($category as $cat_id)
    {
        if (count($cat_id['childs']) > 0) {
			// $html .='<li><a href="'.$cat_id['menu_link'].'" class="mm-sub-link" aria-label="'.$cat_id['menu_title'].'">'.$cat_id['menu_title'].'</a></li>';
          	$html .= getCategoriesHTML($cat_id['childs']);
        } else {
			if($cat_id['menu_title'] != 'Kids' || config('typevalofcriteo') == 'd'){
				$html .='<li role="listitem"><a href="'.$cat_id['menu_link'].'" class="ga4c" role="link" aria-label="'.$cat_id['menu_title'].'">'.$cat_id['menu_title'].'</a></li>';
			}
        }
    }

    return $html;
}

function getPopularBrands()
{
	$popular_brands = Cache::remember('popular_brands', 3600, function() {
		$popularBrands = [];

		$BrandList = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.is_popular','=','Yes')
					->where('m.status','=','1')
					->groupBy('m.imanufactureid')
					->orderBy('m.vmanufacture', 'ASC')
					->limit(24)
					->get();

		if($BrandList && $BrandList->count() > 0)
		{
			foreach($BrandList as $Brand)
			{
				$Name = remove_special_chars($Brand->vmanufacture);
				if(file_exists(config('global.MANUFACTUR_IMAGE_PATH').$Brand->imglogo) && $Brand->imglogo !='')
				{
					$imgLogo = config('global.MANUFACTUR_IMAGE_URL').$Brand->imglogo;
				} else {
					$imgLogo = config('global.MANUFACTUR_IMAGE_URL').'popular_brand_no_image.png';
				}
				$popularBrands[]=[
					'Name' => $Brand->vmanufacture,
					'ImageLogo' => $imgLogo,
					'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid,
				];
			}
		}
		$popular_brands = $popularBrands;
		return $popular_brands;

	});
}

function GCGenerateCode( $seed=false)
{
	$length = 25;
	$GIFT_CERT_CODE_CHARACTERS = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$newcode = "";
	if(false !== $seed) mt_srand($seed);
	for($i=0; $i<$length; $i++):
		$idx = mt_rand(1, strlen($GIFT_CERT_CODE_CHARACTERS)) - 1;
		$newcode .= $GIFT_CERT_CODE_CHARACTERS[$idx];
	endfor;
	return $newcode;
}

function GetPages()
{
	$Pages = [];
	Cache::remember('StaticPagesCache', 3600, function() {
		$StaticPages = \App\Models\StaticPages::where('status','=','1')->get();
		$DirectPages = ['faq','about_us','site_map','free_sample','coupons_promotional','reward_point_program','security_policy','privacy_policy','shipping_policy','return_exchange_policy','terms_and_conditions'];
		$PageWithUnderscore = ['Redemption_policy','store_credit'];
        $Pkey = 0;
		foreach($StaticPages as $key => $Page)
		{
			if($Page->name == 'contactus')
				continue;
			$Pages[$Pkey]['slug'] = $Page->name;
			if($Page->name == 'FAQS')
			{
				$Page->name = 'faq';
			}
			$Page->name = strtolower($Page->name);

			if(!in_array($Page->name,$DirectPages)){
				if(in_array($Page->name,$PageWithUnderscore)){
					$StaticPage = '/site-page/'.$Page->name.'.html';
				} else {
					$StaticPage = str_replace('_','-',$Page->name);
					$StaticPage = '/site-page/'.$StaticPage.'.html';
				}
			}else{
				$StaticPage = str_replace('_','-',$Page->name);
				$StaticPage = '/'.$StaticPage.'.html';
			}
			$Pages[$Pkey]['link'] = $StaticPage;
            $Pkey++;
		}
		return $Pages;
	});
}
if(!function_exists('AddAttentiveSubscriber')){
	function AddAttentiveSubscriber($data){
		global $obj;
		$url = "https://api.attentivemobile.com/1/add-subscribers";
		$bearer_token = "SLaZI9AyM-aDRze0L4jggSD8qEsV3k9YMGyue2yRkzI";
		$ch = curl_init();
		curl_setopt_array($ch, array(
		// Replace with your offline_event_set_id
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS =>  json_encode($data),
		CURLOPT_HTTPHEADER => array(
			"cache-control: no-cache",
			"Content-Type: application/json",
			"Authorization: Bearer ".$bearer_token."",
			//"Authorization: Basic ".$basic_auth."",
			"Accept: application/json"),
		));
		$result = curl_exec($ch);

		$arrUpdate = array('attentive_response' => $result);
		\App\Models\NewsLetter::where('news_letter_id','=',$data["visitorId"])->update($arrUpdate);
		return true;
	}
}
function getImageWidth($imagpath){
	if(!file_exists($imagpath)){
		$imagpath = config('global.PHYSICAL_PATH').'images/noimage-th.jpg';
	}
	list($width, $height) =  @getimagesize($imagpath);
	return $width;
}

function getImageHeight($imagpath){
	if(!file_exists($imagpath)){
		$imagpath = config('global.PHYSICAL_PATH').'images/noimage-th.jpg';
	}
	list($width, $height) =  @getimagesize($imagpath);
	return $height;
}

function getProductImage($image,$size='thumb')
{
    if($size == 'medium'){
        $ImgPath = config('global.PRD_MEDIUM_IMG_PATH');
        $ImgURL = config('global.PRD_MEDIUM_IMG_URL');
    } elseif($size == 'large'){
        $ImgPath = config('global.PRD_LARGE_IMG_PATH');
        $ImgURL = config('global.PRD_LARGE_IMG_URL');
    } else {
        $ImgPath = config('global.PRD_THUMB_IMG_PATH');
        $ImgURL = config('global.PRD_THUMB_IMG_URL');
    }

    if (file_exists($ImgPath . $image) && trim($image) != '') {
        $newimageVal = $ImgPath  . stripslashes($image);
        $verP = filemtime($newimageVal);
        $prodImage  = $ImgURL . $image . "?ver=" . $verP;
    } else {
        $prodImage = $ImgURL;
    }
    return $prodImage;
}

function SetPageview($Route='')
{
	$PageView = "Other";
	if($Route == "home"){
		$PageView = "Homepage";
	}elseif($Route == "retailer-registration"){
		$PageView = "Retail Registration";
	}elseif($Route == "wholesaler-registration"){
		$PageView = "Wholesale Registration";
	}elseif($Route == "login"){
		$PageView = "Log In";
	}elseif($Route == "contactus"){
		$PageView = "Contact Us";
	}elseif($Route == "fragrance"){
		$PageView = "Fragrance";
	}elseif($Route == "CategoryPage1" || $Route == "CategoryPage2" || $Route == "CategoryPage3" || $Route == "CategoryPage4"){
		$PageView = "Category";
	}elseif($Route == "product-list1" || $Route == "product-list2" || $Route == "product-list3" || $Route == "product-list4" || $Route == "product-list5" || $Route == "product-list6"){
		$PageView = "Product List";
	}elseif($Route == "proddetails" || $Route == "proddetails_size" || $Route == "proddetails_code"){
		$PageView = "Product View";
	}elseif($Route == "shoppingcart"){
		$PageView = "Shopping Cart";
	}elseif($Route == "order_history"){
		$PageView = "Ordered Product";
	}elseif($Route == "cancel_orders"){
		$PageView = "Cancelled Order";
	}elseif($Route == "return_orders"){
		$PageView = "Refunded Order";
	}elseif($Route == "order-receipt"){
		$PageView = "Order Placed";
	}elseif($Route == "billing" || $Route == "AmazonBilling" || $Route == "billing"){
		$PageView = "Checkout Process - Address";
	}
	return $PageView;
}
function SetOrderStatus($type,$pay_status,$order_status,$ship_status)
{
    $OrderPaymentStatus = ['Paid' => 'paid', 'Unpaid' => 'awaitingPayment', 'Inprocess' => 'awaitingPayment'];
    if($type == 'pay_status')
    {
        $OrdStatus = ['Declined', 'Canceled', 'Back Ordered', 'Refund'];
        if(in_array($order_status,$OrdStatus))
            $PayStatus = 'voided';
        else
            $PayStatus = $OrderPaymentStatus[$pay_status];
        return $PayStatus;
    }
    if($type == 'status')
    {
        $Status = ['Declined', 'Canceled', 'Back Ordered', 'Refund'];
        $Unfulfilled = ['Sent To Stripe', 'Pending By Amazon','Pending - PhoneOrder','Sent To AfterPay'];
        $Delivered = ['Completed', 'Closed'];
        $Restocked = ['Refund','Return Requested','Return Approved','Return Rejected','Item Received','Request Cancellation','Cancellation Approved','Resolved'];

        if($ship_status == 'Pending')
            $OrderStatus = 'inProgress';
        else if($ship_status == 'Shipped')
            $OrderStatus = 'fulfilled';
        else if(in_array($order_status,$Unfulfilled))
            $OrderStatus = 'unfulfilled';
        else if(in_array($order_status,$Delivered))
            $OrderStatus = 'delivered';
        else if(in_array($order_status,$Restocked))
            $OrderStatus = 'restocked';
        return $OrderStatus;
    }
}
function GetTrackingLink($TrackingNo='',$ShipMethod='')
{
    $TrackingLink = "";
    if($TrackingNo !='' && $ShipMethod !='')
    {
        if($ShipMethod == 'USPS')
            $TrackingLink = "https://tools.usps.com/go/TrackConfirmAction.action?tLabels=".$TrackingNo;
        if($ShipMethod == 'UPS')
            $TrackingLink = "https://wwwapps.ups.com/WebTracking/processRequest?HTMLVersion=5.0&Requester=NES&AgreeToTermsAndConditions=yes&loc=en_US&tracknum=".$TrackingNo;
        if($ShipMethod == 'FedEx')
            $TrackingLink = "https://www.fedex.com/fedextrack/?trknbr=".$TrackingNo;
    }
    return $TrackingLink;
}
function OmanisendRequest($RequestType='',$Data=[],$OtherData=[])
{
	$ApiURL = "https://api.omnisend.com/v3/";
	$ApiType = "POST";
	$RequestData = [];
	$NoAction = 0;
    $omnisend_accountid = "";
    if(Auth::user() && Auth::user()->omnisend_accountid != '')
    {
        $omnisend_accountid = Auth::user()->omnisend_accountid;
    } else if(Cookie::has("omnisendContactID") && Cookie::get("omnisendContactID") != "" )
    {
        $omnisend_accountid = Cookie::get("omnisendContactID");
    }
	if(isset($OtherData['toMail']) && $OtherData['toMail'] == 'gequaldev@gmail.com'){
		$OtherData['toMail'] = 'tempchecknew@gmail.com
';
	}
    //dd($RequestType,$omnisend_accountid);
	//echo $RequestType."<br>";
	switch($RequestType){
		case "6936f81cc72739b72a50951a": // In Store Pickup - Send customer email
			$ApiURL.="events/".$RequestType;
			$customernote	= $estimatedDate = '';
			if(isset($OtherData['customernote'])){
				$customernote	= $OtherData['customernote'];
			}
			if(isset($OtherData['estimated_ship_date'])){
				$estimatedDate	= $OtherData['estimated_ship_date'];
			}
			$RequestData = [];
			$RequestData = ['email' => $OtherData['toMail'],'phone' => $OtherData['phone'] ?? '',// '+16312741060',//
				'fields' => [
					'customer_ip' => $OtherData['customer_ip'],
                    'shipping_insurance' => $OtherData['shipping_insurance'],
                    'shipping_signature' => $OtherData['shipping_signature'],
					'addblock' => $OtherData['addblock'],
					'orders_no' => $Data->orders_no,
					'order_datetime' => date("Y-m-d\TH:i:s\Z",$Data->order_datetime),
					'order_total' => Price($Data->order_total),
					'shipinfo' => $Data->shipinfo,
					'bill_address' => $OtherData['BillAddress'],
					'ship_address' => $OtherData['ShipAddress'],
					'customernote' => str_replace('\r\n','<br>', str_replace("\'", "'", $customernote)),
					'estimated_ship_date' => $estimatedDate,
					'STR_EMAIL_ITEM' => $OtherData['STR_EMAIL_ITEM'],
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'pickup_status' => $OtherData['pickup_status'],
					'customer_name' => $OtherData['customer_name']
				]
			];
			break;
		case "6936ffd745fd2286e1ae63a1": // In Store Pickup - Internal Admin Notification
			$ApiURL.="events/".$RequestType;
			$customernote	= $estimatedDate = '';
			if(isset($OtherData['customernote'])){
				$customernote	= $OtherData['customernote'];
			}
			if(isset($OtherData['estimated_ship_date'])){
				$estimatedDate	= $OtherData['estimated_ship_date'];
			}
			$RequestData = [];
			$RequestData = ['email' => $OtherData['toMail'],
				'fields' => [
					'customer_ip' => $OtherData['customer_ip'],
                    'shipping_insurance' => $OtherData['shipping_insurance'],
                    'shipping_signature' => $OtherData['shipping_signature'],
					'addblock' => $OtherData['addblock'],
					'orders_no' => $Data->orders_no,
					'order_datetime' => date("Y-m-d\TH:i:s\Z",$Data->order_datetime),
					'order_total' => Price($Data->order_total),
					'shipinfo' => $Data->shipinfo,
					'bill_address' => $OtherData['BillAddress'],
					'ship_address' => $OtherData['ShipAddress'],
					'customernote' => str_replace('\r\n','<br>', str_replace("\'", "'", $customernote)),
					'estimated_ship_date' => $estimatedDate,
					'STR_EMAIL_ITEM' => $OtherData['STR_EMAIL_ITEM'],
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'pickup_status' => $OtherData['pickup_status'],
					'customer_name' => $OtherData['customer_name']
				]
			];
			break;
		case 'create_customer':
			$ApiURL.="contacts";
			if($Data->omnisend_accountid != '')
			{
				$CustomerInfo = OmanisendRequest('checkCustomer',['omnisend_accountid' => $Data->omnisend_accountid]);
				if(isset($CustomerInfo['contactID']) && $CustomerInfo['status'] != 'subscribed' && isset($OtherData['newsletter']) && $OtherData['newsletter'] == 'Yes')
				{
					OmanisendRequest('update_customer',$Data,$OtherData);
					break;
				}
			}
			$Status = "nonSubscribed";
			if(isset($OtherData['newsletter']) && $OtherData['newsletter'] == 'Yes')
			{
				$Status = "subscribed";
			}
			$RequestData = [];
			$RequestData['identifiers']=[
				[
					'channels' => [
						'email' => ['status' => $Status, 'statusDate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))))]
					],
					'type' => 'email',
					'id' => $Data->email
				]
			];
			if($Data->phone != '')
			{
				$RequestData['identifiers'][]=[
					'channels' => [
						'sms' => ['status' => $Status, 'statusDate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))))]
					],
					'type' => 'phone',
					'id' => stripslashes(utf8_encode($Data->phone))
				];
			}
			$Countryres = \App\Models\Countries::where('countries_iso_code_2','=',trim($Data->country))->get();
			$RequestData['firstName'] = stripslashes(utf8_encode($Data->first_name));
			$RequestData['lastName'] = stripslashes(utf8_encode($Data->last_name));

			if($Data->country == "UK")
			{
				$RequestData['country'] = "GB";
			}else if($Data->country == "FX")
			{
				$RequestData['country'] = "FR";
			}else if($Data->country =="AN")
			{
				$RequestData['country'] = "NL";
			} else {
				$RequestData['country'] = stripslashes(utf8_encode($Countryres[0]['countries_name']));
			}
			$RequestData['countryCode'] = $Data->country;
			$RequestData['state'] = stripslashes(utf8_encode($Data->state));
			$RequestData['city'] = stripslashes(utf8_encode($Data->city));
			$address = $Data->address1;
			if($Data->address2 != '')
				$address.=", ".$Data->address2;
			$RequestData['address'] = stripslashes(utf8_encode($address));
			$RequestData['postalCode'] = stripslashes(utf8_encode($Data->zip));
			if($Data->gender != null)
			{
				if($Data->gender == 'Female')
					$RequestData['gender'] = 'f';
				if($Data->gender == 'Male')
					$RequestData['gender'] = 'm';
			}
			if(trim($Data->birthday) != null && trim($Data->birthday) != '0000-00-00')
				$RequestData['birthday'] = $Data->birthday;

			if($Data->registration_type == 'M')
				$RequestData['customProperties']['RegistrationType'] = 'Member';
			if($Data->registration_type == 'G')
				$RequestData['customProperties']['RegistrationType'] = 'Guest';

			$RequestData['customProperties']['ContactNo'] = stripslashes(utf8_encode($Data->phone));
			$RequestData['customProperties']['ContactNo'] = stripslashes(utf8_encode($Data->phone));
			$RequestData['customProperties']['Company'] = stripslashes(utf8_encode($Data->company_name));
			$RequestData['customProperties']['UserType'] = $Data->eusertype;
			$RequestData['customProperties']['Dropshipper'] = $Data->is_dropshipper;
			$RequestData['customProperties']['RegisterDate'] = $Data->reg_datetime;
			$RequestData['customProperties']['Status'] = ($Data->status == '1' || (isset($OtherData['status']) && $OtherData['status'] == '1'))?'Active':'Inactive';
			$RequestData['customProperties']['RewardPoints'] = $Data->iRewardpoint;
			if($Data->wholesale_approve_date != '0000-00-00')
				$RequestData['customProperties']['ApproveDate'] = $Data->wholesale_approve_date;
			break;
		case 'newletter_create_customer':
			$ApiURL.="contacts";
			$RequestData = [];
			$RequestData['identifiers']=[
				[
					'channels' => [
						'email' => ['status' => 'subscribed', 'statusDate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))))]
					],
					'type' => 'email',
					'id' => trim($Data['news_email1'])
				]
			];
			if(trim($Data['news_contactno']) != '')
			{
				$RequestData['identifiers'][]=[
					'channels' => [
						'sms' => ['status' => 'subscribed', 'statusDate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))))]
					],
					'type' => 'phone',
					'id' => stripslashes(utf8_encode(trim($Data['news_contactno'])))
				];
			}
			$RequestData['firstName'] = stripslashes(utf8_encode(trim($Data['news_firstname'])));
			$RequestData['lastName'] = stripslashes(utf8_encode(trim($Data['news_lastname'])));
			break;
		case 'update_customer':
			$ApiURL.="contacts/".$Data->omnisend_accountid;
			$ApiType = "PATCH";
			$RequestData = [];
			$RequestData['identifiers']=[
				[
					'channels' => [
						'email' => ['status' => 'subscribed', 'statusDate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))))]
					],
					'type' => 'email',
					'id' => $Data->email
				]
			];
			/*
			if($Data->phone != '')
			{
				$RequestData['identifiers'][]=[
					'channels' => [
						'sms' => ['status' => 'subscribed', 'statusDate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))))]
					],
					'type' => 'phone',
					'id' => stripslashes(utf8_encode($Data->phone))
				];
			}*/
			break;
		case 'checkCustomer':
			$ApiURL.="contacts/".$Data['omnisend_accountid'];
			$ApiType = "GET";
			$RequestData = [];
			break;
		case "61e55276af90600022058216": // CUSTOMER_REGISTER - Retailer Signup Email
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			/*$RequestData = ['email' => $Data->email,
				'fields' => [
					'first_name' => $Data->first_name,
					'last_name' => $Data->last_name,
					'password' => $Data->password,
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'COUPON_CODE_VALUE' => config('Settings.COUPON_CODE_VALUE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'Site_URL' => config('global.SITE_URL'),
					'freeshippinginfo' => ''
				]
			];*/
			$RequestData = ['email' => $Data['email'],
				'fields' => [
					'first_name' => $Data['fields']['first_name'],
					'last_name' => $Data['fields']['last_name'],
					'password' => $Data['fields']['password'],
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'COUPON_CODE_VALUE' => config('Settings.COUPON_CODE_VALUE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'Site_URL' => config('global.SITE_URL'),
					'freeshippinginfo' => ''
				]
			];
			break;
		case "6555d886012f9131a37ae1d2"	: // Police Report Notification to Customer Care of Maxaroma
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data->email,
				'fields' => [
					'order_number' => $Data->order_number,
					'police_report_date' => $Data->police_report_date,
					'Site_URL' => $Data->Site_URL
				]
			];
			break;
		case "656068f0e928245593627ed2"	:
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data->email,
				'fields' => [
					'order_number' => $Data->order_number,
					'upload_date' => $Data->upload_date,
					'Site_URL' => $Data->Site_URL
				]
			];
			break;
		case "61e6a448c01934001be85479": // WHOLESALER - Wholesaler Signup Email
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data['email'],
				'fields' => [
					'first_name' => $Data['fields']['first_name'],
					'last_name' => $Data['fields']['last_name'],
					'password' => $Data['fields']['password'],
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL')
				]
			];
			/*$RequestData = ['email' => $Data->email,
				'fields' => [
					'first_name' => $Data->first_name,
					'last_name' => $Data->last_name,
					'password' => $Data->password,
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL')
				]
			];*/
			break;
		case "61e6b6706adf87002036fa00": // DROPSHIPPER_CUSTOMER - Dropshipper Signup Email
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => config('Settings.ADMIN_MAIL'),
				'fields' => [
					'customer_id' => $Data->customer_id,
					'first_name' => $Data->first_name,
					'last_name' => $Data->last_name,
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL')
				]
			];
			break;
		case "61e048930e8680001cd923aa": // FORGOT_PASSWORD - Forgot Password Email
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data->email,
				'fields' => [
					'password' => $Data->password,
					'TOLL_FREE_NO' => config('Settings.TOLL_FREE_NO'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'Site_URL' => config('global.SITE_URL'),
					'SITE_NAME' => config('Settings.SITE_TITLE')
				]
			];
			break;
		case "68e540bae6d736b091e25f02": // POS - OTP for Customer Notification
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data['toMail'],
				'fields' => [
					'login_otp' => $Data['login_otp'],
					'TOLL_FREE_NO' => config('Settings.TOLL_FREE_NO'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'Site_URL' => config('global.SITE_URL'),
					'SITE_NAME' => config('Settings.SITE_TITLE')
				]
			];
			break;
		case "68e62a6430300b38d35687a5": // POS - Store User Login 2FA Security Authentication Code
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data['toMail'],
				'fields' => [
					'twofa_code' => (string) $Data['2fa_code'],
					'TOLL_FREE_NO' => config('Settings.TOLL_FREE_NO'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'Site_URL' => config('global.SITE_URL'),
					'SITE_NAME' => config('Settings.SITE_TITLE')
				]
			];
			break;
		case "61e6ba7faf9060002205881d": // SEND_TO_FRIEND - Send To Friend Email
			$ApiURL.="events/".$RequestType;
			if (file_exists(config('global.PRD_MEDIUM_IMG_PATH') . $Data->image) && $Data->image != '') {
				$newimageVal = config('global.PRD_MEDIUM_IMG_PATH') . stripslashes($Data->image);
				$verP = filemtime($newimageVal);
				$Data->mainImage  = config('global.PRD_MEDIUM_IMG_URL') . $Data->image . "?ver=" . $verP;
			} else if (file_exists(config('global.PRD_LARGE_IMG_PATH') . $Data->image) && $Data->image != '') {
				$newimageVal = config('global.PRD_LARGE_IMG_PATH') . stripslashes($Data->image);
				$verP = filemtime($newimageVal);
				$Data->mainImage  = config('global.PRD_LARGE_IMG_URL') . $Data->image . "?ver=" . $verP;
			}else {
				$Data->mainImage =  config('global.SITE_URL') . config('global.NO_IMAGE_MEDIUM');
			}
            $ProdLink = SetProductURL($Data->products_id, $Data->product_name, $Data->category_id);
			$RequestData = [];
			$RequestData = ['email' => $OtherData['toMail'],
				'fields' => [
					'product_image' => $Data->mainImage,
					'product_name' => $Data->product_name,
					'short_desc' => $Data->short_description,
					'sale_price' => (float)$Data->product_price,
					'message' => $OtherData['message'],
					'product_page_link' => $ProdLink,
                    'productImage' => '<img src="'.$Data->mainImage.'" alt=""/>',
					'SITE_NAME' => config('Settings.SITE_TITLE')
				]
			];
			break;
		case "69f8b45dbe5da69308eeb38d": // POS ORDER_RECEIPT_NEW - Order Receipt Email
			$ApiURL.="events/".$RequestType;
			$customernote	= $estimatedDate = '';

			if(isset($OtherData['customernote'])){
				$customernote	= $OtherData['customernote'];
			}
			if(isset($OtherData['estimated_ship_date'])){
				$estimatedDate	= $OtherData['estimated_ship_date'];
			}

			if(isset($OtherData['store_address']) && $OtherData['store_address'] != ''){
				$OtherData['store_address'] = $OtherData['store_address'];
			}else{
				$OtherData['store_address'] = '';
			}

			$order_datetimeval = '';
			$STR_SOCIAL_MEDIA = '';

				$STR_SOCIAL_MEDIA .= '<div style="padding:28px 40px;text-align:center;background:#ffffff;border-top:1px solid #f0efeb;">';

					$STR_SOCIAL_MEDIA .= '<div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:2px;margin-bottom:14px;">';
						$STR_SOCIAL_MEDIA .= 'Follow the Journey';
					$STR_SOCIAL_MEDIA .= '</div>';

					$STR_SOCIAL_MEDIA .= '<table cellpadding="0" cellspacing="0" border="0" align="center">';
						$STR_SOCIAL_MEDIA .= '<tr>';

							$STR_SOCIAL_MEDIA .= '<td style="padding:0 10px;">';
								$STR_SOCIAL_MEDIA .= '<a href="#" title="Instagram" style="display:block;width:40px;height:40px;line-height:40px;background:#1a1a2e;color:#ffffff;text-decoration:none;font-size:14px;border-radius:50%;text-align:center;font-family:Arial, sans-serif;">IG</a>';
							$STR_SOCIAL_MEDIA .= '</td>';

							$STR_SOCIAL_MEDIA .= '<td style="padding:0 10px;">';
								$STR_SOCIAL_MEDIA .= '<a href="#" title="TikTok" style="display:block;width:40px;height:40px;line-height:40px;background:#1a1a2e;color:#ffffff;text-decoration:none;font-size:14px;border-radius:50%;text-align:center;font-family:Arial, sans-serif;">TT</a>';
							$STR_SOCIAL_MEDIA .= '</td>';

							$STR_SOCIAL_MEDIA .= '<td style="padding:0 10px;">';
								$STR_SOCIAL_MEDIA .= '<a href="#" title="Facebook" style="display:block;width:40px;height:40px;line-height:40px;background:#1a1a2e;color:#ffffff;text-decoration:none;font-size:14px;border-radius:50%;text-align:center;font-family:Arial, sans-serif;">FB</a>';
							$STR_SOCIAL_MEDIA .= '</td>';

						$STR_SOCIAL_MEDIA .= '</tr>';
					$STR_SOCIAL_MEDIA .= '</table>';

				$STR_SOCIAL_MEDIA .= '</div>';

			// if(isset($Data['order_datetime'])){
            //     $order_datetimeval	= date("m/d/Y H:i:s", strtotime($Data['order_datetime'])); //$Data['order_datetime'];
			// }
			if(isset($OtherData['OrdDate']) && $OtherData['OrdDate']!=''){
				$order_datetimeval	= date("m/d/Y H:i:s", strtotime($OtherData['OrdDate']));
			}
			$totaldiscount = $Data->auto_discount + $Data->quantity_discount + $Data->reward_discount + $Data->bogo_discount + $Data->coupon_amount + $Data->gc_amount + $Data->manual_discount;
			$totaldiscount = number_format($totaldiscount,2);
			$RequestData = [];

			$oth_phone = trim($OtherData['phone'] ?? '');
			$oth_phone = preg_replace('/[^0-9+]/', '', $oth_phone);
			if($oth_phone != '' && substr($oth_phone, 0, 1) != '+')
			{
				if(strlen($oth_phone) == 10){
					$oth_phone = '+1'.$oth_phone;
				}
			}

			//$OtherData['toMail'] = "qualdev.devs@gmail.com";
			//$RequestData = ['email' => $OtherData['toMail'],
			$RequestData = ['email' => trim($OtherData['toMail']),'phone' => $oth_phone, //$OtherData['phone'] ?? '',// '+16312741060',//
				'fields' => [
					'order_tax' => Price($Data['tax']),
                    'first_name' => $Data->bill_first_name,
                    'last_name' => $Data->bill_last_name,
                    'order_no' => $Data->orders_no,
                    'order_date' => $order_datetimeval,
                    'order_total' => Price(number_format($Data->order_total,2)),
                    'order_subtotal' => Price(number_format($Data->sub_total,2)),
                    'order_discount' => Price($totaldiscount),
                    'TOLL_FREE_NO' => '',
                    'Site_URL' => 'https://www.maxaroma.com',
                    'CONTACT_MAIL' => 'CustomerCare@maxaroma.com',
                    'STR_EMAIL_ITEM' => $OtherData['STR_EMAIL_ITEM'],
					'SOCIAL_MEDIA' => $STR_SOCIAL_MEDIA,
					'store_address' => $OtherData['store_address']
				]
			];
			break;
		case "6942bff04b2a3e1daf65c63d": // ORDER_RECEIPT_NEW - Order Receipt Email
			$ApiURL.="events/".$RequestType;
			$customernote	= $estimatedDate = '';
			if(isset($OtherData['customernote'])){
				$customernote	= $OtherData['customernote'];
			}
			if(isset($OtherData['estimated_ship_date'])){
				$estimatedDate	= $OtherData['estimated_ship_date'];
			}
			$RequestData = [];
			$RequestData = ['email' => $OtherData['toMail'],'phone' => $OtherData['phone'] ?? '',// '+16312741060',//
				'fields' => [
					'customer_ip' => $OtherData['customer_ip'],
                    'shipping_insurance' => $OtherData['shipping_insurance'],
                    'shipping_signature' => $OtherData['shipping_signature'],
					'addblock' => $OtherData['addblock'],
					'orders_no' => $Data->orders_no,
					'order_datetime' => date("Y-m-d\TH:i:s\Z",$Data->order_datetime),
					'order_total' => Price($Data->order_total),
					'shipinfo' => $Data->shipinfo,
					'bill_address' => $OtherData['BillAddress'],
					'ship_address' => $OtherData['ShipAddress'],
					'customernote' => str_replace('\r\n','<br>', str_replace("\'", "'", $customernote)),
					'estimated_ship_date' => $estimatedDate,
					'STR_EMAIL_ITEM' => $OtherData['STR_EMAIL_ITEM'],
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'SITE_NAME' => config('Settings.SITE_TITLE')
				]
			];
			break;
		case "61fb93a4b86552001e976b3c": // ORDER_RECEIPT_NEW - Order Receipt Email
			$ApiURL.="events/".$RequestType;
			$customernote	= $estimatedDate = '';
			if(isset($OtherData['customernote'])){
				$customernote	= $OtherData['customernote'];
			}
			if(isset($OtherData['estimated_ship_date'])){
				$estimatedDate	= $OtherData['estimated_ship_date'];
			}
			$RequestData = [];
			$RequestData = ['email' => trim($OtherData['toMail']),
				'fields' => [
					'customer_ip' => $OtherData['customer_ip'],
                    'shipping_insurance' => $OtherData['shipping_insurance'],
                    'shipping_signature' => $OtherData['shipping_signature'],
					'addblock' => $OtherData['addblock'],
					'orders_no' => $Data->orders_no,
					'order_datetime' => date("Y-m-d\TH:i:s\Z",$Data->order_datetime),
					'order_total' => Price($Data->order_total),
					'shipinfo' => $Data->shipinfo,
					'bill_address' => $OtherData['BillAddress'],
					'ship_address' => $OtherData['ShipAddress'],
					'customernote' => str_replace('\r\n','<br>', str_replace("\'", "'", $customernote)),
					'estimated_ship_date' => $estimatedDate,
					'STR_EMAIL_ITEM' => $OtherData['STR_EMAIL_ITEM'],
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'SITE_NAME' => config('Settings.SITE_TITLE')
				]
			];
			break;
		case "61fbcf88bf58ef001efc0243": // GC_USAGE - GC USAGE Email
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data->recipient_email,
				'fields' => [
					'recipient_name' => $Data->recipient_name,
					'gc_code' => $OtherData['gc_code'],
					'gc_amount' => (float)$OtherData['gc_amount'],
					'remaining_value' => (float)$OtherData['remaining_value'],
					'TOLL_FREE_NO' => config('Settings.TOLL_FREE_NO'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'SITE_NAME' => config('Settings.SITE_TITLE')
				]
			];
			break;
		case "6209ffc44fa101001e950228": // GC_SEND_CODE - GC SEND CODE Email
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data->recipient_email,
				'fields' => [
					'recipient_name' => $Data['recipient_name'],
					'sender_name' => $Data['your_name'],
					'gc_code' => $Data['gc_code'],
					'remaining_value' => (float)$Data['remaining_value'],
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'SITE_NAME' => config('Settings.SITE_TITLE')
				]
			];
			break;
		case "New_Gift_Cards_Email": // MOM - GC SEND CODE/MAXAROMA GENERAL - GC SEND CODE/BIRTHDAY - GC SEND CODE/DAD - GC SEND CODE -> New Gift Cards Email

			if($Data['gc_card'] == config('global.GC_IMAGE_URL')){
				$ApiURL.="events/647610e5629f7f992be20783";
			}else if($Data['gc_card'] == config('global.GC_IMAGE_URL1')){
				$ApiURL.="events/6476111d629f7f992be20784";
			}else if($Data['gc_card'] == config('global.GC_IMAGE_URL2')){
				$ApiURL.="events/64761154117ea647ecdb9d26";
			}else if($Data['gc_card'] == config('global.GC_IMAGE_URL3')){
				$ApiURL.="events/6476115befaea2bef1efd4a5";
			}
			//echo "<pre>";print_r($Data);exit;
			$RequestData = [];
			$RequestData = ['email' => $Data->recipient_email,
				'fields' => [
					'recipient_name' => $Data['recipient_name'],
					'sender_name' => $Data['your_name'],
					'gc_code' => $Data['gc_code'],
					'gc_card' => $Data['gc_card'],
					'remaining_value' => (float)$Data['remaining_value'],
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'Site_URL' => config('global.SITE_URL')
				]
			];
			break;
		case "6201175ab86552001e977a60": // DONOT_SEE_REQUEST - Don't See Request Email
			$ApiURL.="events/".$RequestType;
			$comments  = stripslashes(nl2br(strtr(trim($Data['comments']), array('\r' => chr(13), '\n' => chr(10)))));
			$comments  = str_replace("<br />","",strip_tags($comments));
			$RequestData = [];
			$RequestData = ['email' => config('Settings.CONTACT_MAIL'),
				'fields' => [
					'CUST_NAME' => $Data['custname'],
					'comments' => $comments,
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
				]
			];
			break;
		case "62011d76bf58ef001efc0fae": // INSTANT_COUPON - Instant Coupon Email
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $OtherData['toMail'],
				'fields' => [
					'COUPON_CODE_VALUE' => config('Settings.COUPON_CODE_VALUE'),
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
				]
			];
			break;
		case "620121738a8d4100249b3b18": // NICHE_FRAGRANCES - Niche Fragrances Membership
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $OtherData['toMail'],
				'fields' => [
					'coupon_code' => $Data['coupon_code'],
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
				]
			];
			break;
		case "6201253fb86552001e977a7b": // CONTACT_US - Contact US Mail
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => config('Settings.CONTACT_MAIL'),
				'fields' => [
					'your_name' => $Data['your_name'],
					'your_email' => $Data['your_email'],
					'your_comment' => $Data['your_comment'],
                    'your_subject' => $Data['your_subject'],
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
                    'SITE_NAME' => config('Settings.SITE_TITLE'),
				]
			];
			break;
		case "6201293db86552001e977a84": // ORDER_RETURN_NOTIFICATION
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => config('Settings.ADMIN_MAIL'),
				'fields' => [
                    'returnLink' => $OtherData['returnLink'],
					'order_no' => $Data->orders_no,
                    'return_info' => $OtherData['return_info'],
                    'reason' => $OtherData['reason'],
                    'sku' => $OtherData['sku'],
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
				]
			];
			break;
		case "62012eb2b86552001e977a87": // ORDER_CANCEL_NOTIFICATION
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => config('Settings.ADMIN_MAIL'),
				'fields' => [
					'reason' => $Data->reason,
					'order_no' => $Data->orders_no,
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
				]
			];
			break;
		case "62b9d52aa67887001578773a": // Insurance Claim Request
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data['customer_email'],
				'fields' => [
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'order_number' => $Data['orders_no'],
					'customer_name' => $Data['customer_name'],
					'claim_reason' => $Data['claim_reason'],
					'attachment' => $Data['attachment_url'],
					'comment' => $Data['cust_comment']
				]
			];
			break;
		case "62bb11d8842bc8001cb182c8": // Admin Insurance Claim Request
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => config('Settings.ADMIN_MAIL'),
				'fields' => [
					'order_number' => $Data['orders_no'],
					'customer_name' => $Data['customer_name'],
					'claim_reason' => $Data['claim_reason'],
					'attachment' => $Data['attachment_url'],
					'comment' => $Data['cust_comment']
				]
			];
			break;
		case "63c113f03d897d0020e8b31e": // Decline Order
			$ApiURL.="events/".$RequestType;
			$RequestData = [];
			$RequestData = ['email' => $Data['toMail'], //'qualdev.devs@gmail.com'
				'fields' => [
					'orders_no' => $Data['orderno'],
					'customer_name' => $Data['customer_name'],
					'SITE_NAME' => config('Settings.SITE_TITLE'),
					'CONTACT_MAIL' => config('Settings.CONTACT_MAIL')
				]
			];
			break;
		case "setCart": // CREATE CART IF NOT CREATED
            if(config('global.OMNISEND_CART'))
            {
                if($omnisend_accountid != '')
                {
                    $ChkCart = OmanisendRequest('getCart',['omnisend_accountid' => $omnisend_accountid]);
                    if(isset($ChkCart['cartID']) && $ChkCart['cartID'] != '')
                    {
                        if(isset($ChkCart['products']) && count($ChkCart['products']) > 0)
                        {
                            OmanisendRequest('updateCart',$Data,$OtherData);
                            break;
                        } else {
                            OmanisendRequest('removeCart',['omnisend_accountid' => $omnisend_accountid]);
                        }
                    }
                    $ApiURL.= "carts";

                    if(isset($Data['CartData']['Cart']) && count($Data['CartData']['Cart']) > 0)
                    {

						$RequestData = [];
						$RequestData['cartID'] = $omnisend_accountid;
						$RequestData['contactID'] = $omnisend_accountid;
						$RequestData['createdAt'] = str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))));
						$RequestData['currency'] = 'USD';
						$RequestData['cartRecoveryUrl'] = config('global.SITE_URL').'sitecart';

                        $CartData = $Data['CartData']['Cart'];

                        $RequestData['cartSum'] = (int)($Data['CartData']['SubTotal'] * 100);
                        $Products = array();
                        $u=0;
                        foreach($CartData as $i => $CartProduct)
                        {
							if(!empty($CartProduct['ProductID']) && $CartProduct['ProductID'] > 0)
							{
								$Products[$u]['cartProductID'] = (string)$CartProduct['ProductID'];
								$Products[$u]['productID'] = (string)$CartProduct['ProductID'];
								$Products[$u]['variantID'] = (string)$CartProduct['ProductID'];
								$Products[$u]['sku'] = $CartProduct['SKU'];
								$Products[$u]['title'] = $CartProduct['ProductName'];
								$Products[$u]['description'] = $CartProduct['ProductName'];
								$Products[$u]['quantity'] = (int)$CartProduct['Qty'];
								$Products[$u]['price'] = (int)($CartProduct['Price']*100);
								$CartProd = \App\Models\Products::where('products_id','=',$CartProduct['ProductID'])->get();

								if(file_exists(config('global.PRD_THUMB_IMG_PATH').stripslashes($CartProd[0]->image)) && !empty($CartProd[0]->image))
									$thumb_image = config('global.PRD_THUMB_IMG_URL').rawurlencode($CartProd[0]->image);
								else
									$thumb_image = config('global.NO_IMAGE_THUMB');

								$Products[$u]['imageUrl'] = $thumb_image;
								if(isset($CartProduct['Prod_URL']) && $CartProduct['Prod_URL']!=''){
									$Products[$u]['productUrl'] = $CartProduct['Prod_URL'];
								}
								$u++;
							}
                        }
                        $RequestData['products'] = $Products;
                    } else {
						$NoAction = 1;
					}
                } else  {
                    $NoAction = 1;
                }
            }
			break;
		case "getCart": // GET CART
            if(config('global.OMNISEND_CART'))
            {
                $ApiType = "GET";
                $ApiURL.= "carts/".$Data['omnisend_accountid'];
            }
            break;
        case "removeCart": // REMOVE CART
            if($omnisend_accountid !='')
            {
                $ApiType = "DELETE";
                $ApiURL.= "carts/".$omnisend_accountid;
            }
            break;
        case "updateCart": // UPDATE CART
            if(config('global.OMNISEND_CART'))
            {
                $ApiType = "PUT";
                $ApiURL.= "carts/".$omnisend_accountid;
                $RequestData=[];
                $RequestData['currency'] = 'USD';
                $RequestData['updatedAt'] = str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))));
                $SubTotal = 0;
                if(isset($Data['CartData']['SubTotal']))
                    $SubTotal = $Data['CartData']['SubTotal'];
                $RequestData['cartSum'] = (int)($SubTotal * 100);
                $RequestData['cartRecoveryUrl'] = config('global.SITE_URL').'sitecart';
                if(isset($Data['CartData']) && isset($Data['CartData']['Cart']) && count($Data['CartData']['Cart']) > 0)
                {
                    $CartData = $Data['CartData']['Cart'];
                    $RequestData['cartSum'] = (int)($Data['CartData']['SubTotal'] * 100);
                    $Products = array();
                    foreach($CartData as $i => $CartProduct)
                    {
                        $Products[$i]['cartProductID'] = (string)$CartProduct['ProductID'];
                        $Products[$i]['productID'] = (string)$CartProduct['ProductID'];
                        $Products[$i]['variantID'] = (string)$CartProduct['ProductID'];
                        $Products[$i]['sku'] = $CartProduct['SKU'];
                        $Products[$i]['title'] = $CartProduct['ProductName'];
                        $Products[$i]['description'] = $CartProduct['ProductName'];
                        $Products[$i]['quantity'] = (int)$CartProduct['Qty'];
                        $Products[$i]['price'] = (int)($CartProduct['Price']*100);
                        $CartProd = \App\Models\Products::where('products_id','=',$CartProduct['ProductID'])->get();
                        if(isset($CartProd[0]->image) && file_exists(config('global.PRD_THUMB_IMG_PATH').stripslashes($CartProd[0]->image)) && !empty($CartProd[0]->image))
                            $thumb_image = config('global.PRD_THUMB_IMG_URL').rawurlencode($CartProd[0]->image);
                        else
                            $thumb_image = config('global.NO_IMAGE_THUMB');
                        $Products[$i]['imageUrl'] = $thumb_image;
						if(isset($CartProduct['Prod_URL']) && $CartProduct['Prod_URL']!=''){
                        	$Products[$i]['productUrl'] = $CartProduct['Prod_URL'];
						}
                    }
                    $RequestData['products'] = $Products;
                }
            }
            break;
		case "updateCartProduct": // UPDATE CART PRODUCT
            if(config('global.OMNISEND_CART'))
            {
                $ApiType = "PATCH";
                $ApiURL.= "carts/".$omnisend_accountid.'/products/'.$Data->products_id;
                $RequestData=[];
                $RequestData['currency'] = 'USD';
                $RequestData['productID'] = (string)$Data->products_id;
                $RequestData['variantID'] = (string)$Data->products_id;
                $RequestData['sku'] = $Data->sku;
                $RequestData['title'] = stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$Data->product_name));
                $RequestData['description'] = strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$Data->product_name)));
                $RequestData['quantity'] = (int)($OtherData['CartProduct']['quantity'] + $OtherData['quantity']);
                $RequestData['price'] = (int)($Data->sale_price*100);
                $RequestData['imageUrl'] = $OtherData['imageUrl'];
                $RequestData['productUrl'] = $OtherData['prodLink'];
            }
            break;
        case "update_order_status":
            $ApiType = "PATCH";
            $OrderData = \App\Models\Order::where('orders_id', '=', $Data['orders_id'])->get();
            $ApiURL.= "orders/".$OrderData[0]['orders_no'];
            $RequestData=[];
            $RequestData = [
                'paymentStatus' => SetOrderStatus('pay_status',$OrderData[0]['pay_status'],$OrderData[0]['status'],$OrderData[0]['ship_status']),
                'fulfillmentStatus' => SetOrderStatus('status',$OrderData[0]['pay_status'],$OrderData[0]['status'],$OrderData[0]['ship_status'])
            ];
            if(trim($OrderData[0]['tracking_no']) != '')
            {
                $RequestData['trackingCode'] = trim($OrderData[0]['tracking_no']);
                $RequestData['courierTitle'] = $OrderData[0]['ship_method'];
                $RequestData['courierUrl'] = GetTrackingLink(trim($OrderData[0]['tracking_no']),$OrderData[0]['ship_method']);
            }
            if($OrderData[0]['status'] == 'Canceled')
            {
                if($OrderData[0]['CancelApproveDate']!="0000-00-00 00:00:00")
                    $RequestData['canceledDate'] =  date("Y-m-d\TH:i:s\Z",strtotime($OrderData[0]['CancelApproveDate']));

                if(trim($OrderData[0]['cancellation_reasons']) != '')
                    $RequestData['cancelReason'] =  $OrderData[0]['cancellation_reasons'];
            }
            break;
		default:
			$RequestData=[];
			break;
	}
	if(config('global.OMNISEND_PROG') == true && $NoAction == 0)
	{
		$CurlSetup = array(
			CURLOPT_URL => $ApiURL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $ApiType,
			CURLOPT_HTTPHEADER => array(
			"X-API-KEY: 61a57424f7860b001f9ed49f-7g0VYzQJLyDNsljKTSUIRTtDu5e44XZToAeB4WMGNmN3c4cv5q",
			"cache-control: no-cache",
			"Content-Type: application/json",
			"Accept: application/json"),
		);
		if($RequestType != 'checkCustomer' && $RequestType != 'getCart' && $RequestType != 'removeCart')
		{
			$CurlSetup[CURLOPT_POSTFIELDS] = json_encode($RequestData);
		}
		$ch = curl_init();
		curl_setopt_array($ch,$CurlSetup);
		$result = curl_exec($ch);
		$OmaniRes = json_decode($result,true);
		if(isset($OmaniRes['contactID']) && $RequestType == 'create_customer')
		{
			$UpdateCustomer = \App\Models\Customer::where('email','=',$Data->email)->update(['omnisend_accountid' => $OmaniRes['contactID']]);
		}
		if($RequestType == "create_customer"){
			Log::info('Omnisend Create Customer: Omnisend Response', [
				'Data'     => json_encode($Data ?? []),
				'Request'  => json_encode($RequestData ?? []),
				'Response' => json_encode($OmaniRes ?? [])
			]);
		}
		if($RequestType == '69f8b45dbe5da69308eeb38d'){
			Log::warning('OmnisendResponse: Omnisend Response', [
				'Request' => json_encode($RequestData),'Response' => json_encode($OmaniRes)
			]);

			if(isset($RequestData['phone']) && $RequestData['phone']!=''){
				$requestDataPhone = [
					"identifiers" => [
						[
							"type" => "phone",
							"id" => trim($RequestData['phone']),
							"channels" => [
								"sms" => [
									"status" => "subscribed",
									"statusDate" => gmdate("Y-m-d\TH:i:s\Z")
								]
							]
						]
					]
				];

				$chPhone = curl_init();

				curl_setopt_array($chPhone, [
					CURLOPT_URL => "https://api.omnisend.com/v3/contacts",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_HTTPHEADER => [
						"X-API-KEY: 61a57424f7860b001f9ed49f-7g0VYzQJLyDNsljKTSUIRTtDu5e44XZToAeB4WMGNmN3c4cv5q",
						"Content-Type: application/json",
						"Accept: application/json"
					],
					CURLOPT_POSTFIELDS => json_encode($requestDataPhone),
				]);

				$responsePhone = curl_exec($chPhone);
				curl_close($chPhone);
				Log::warning('OmnisendSubscribeResponse: Omnisend Subscribe Response', [
					'Request' => json_encode($requestDataPhone),'Response' => json_encode(json_decode($responsePhone,true))
				]);
			}
		}

		if(isset($OmaniRes['error']))
		{
            $fp = fopen(config('global.PHYSICAL_PATH').'omnisend/omnisend.txt', 'a');
			fwrite($fp, $RequestType.'\n');
            fwrite($fp, json_encode($RequestData).'\n');
            fwrite($fp, $result.'\n');
            fwrite($fp, '--------------------------------\n');
            fclose($fp);
		}

		return $OmaniRes;
		//dd(json_decode($result));
	}
}

/*function OmanisendRequest_old($EventID,$EventData=[]) // function name changed to check from OmanisendRequest to OmanisendRequest_old
{
	if($EventID != '' && config('global.OMNISEND_PROG') == true)
	{
		$url = "https://api.omnisend.com/v3/events/".$EventID;
		$ch = curl_init();
		curl_setopt_array($ch, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS =>  json_encode($EventData),
		  CURLOPT_HTTPHEADER => array(
			"X-API-KEY: 61a57424f7860b001f9ed49f-7g0VYzQJLyDNsljKTSUIRTtDu5e44XZToAeB4WMGNmN3c4cv5q",
			"cache-control: no-cache",
			"Content-Type: application/json",
			"Accept: application/json"),
		));
		$result = curl_exec($ch);

	}
}*/

function OmanisendContactRequest($EventID,$EventData=[])
{
	if($EventID != '' && config('global.OMNISEND_PROG') == true)
	{
		$url = "https://api.omnisend.com/v3/contacts";
		$ch = curl_init();
		curl_setopt_array($ch, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS =>  json_encode($EventData),
		  CURLOPT_HTTPHEADER => array(
			"X-API-KEY: 61a57424f7860b001f9ed49f-7g0VYzQJLyDNsljKTSUIRTtDu5e44XZToAeB4WMGNmN3c4cv5q",
			"cache-control: no-cache",
			"Content-Type: application/json",
			"Accept: application/json"),
		));
		$result = curl_exec($ch);
	}
}

function YotpoRequest($RequestType='',$Data=[],$OtherData=[])
{
	$ApiURL = "https://loyalty.yotpo.com/api/v2/";
	$ApiType = "POST";
	$Environment = 'Live'; //Sandbox
    if($Environment == 'sandbox')
    {
        $API_KEY = 'kfoVMOxbSTz6LfciYXZv0Att';
        $GUID_KEY = 'ovP-FrBwbhaFtx8vQlYT6g';
    } else {
        $API_KEY = 'uP0DefYrL1Dc77lwxlIq2gtt';
        $GUID_KEY = 'uIl5V6C_LVeCr5BT4bhDLQ';
    }
	switch($RequestType){
		case 'create_customer':
			$ApiURL.="customers";
			$RequestData = [];
            $RequestData['id'] = $Data->customer_id;
			$RequestData['email'] = $Data->email;
			$RequestData['first_name'] = $Data->first_name;
			$RequestData['last_name'] = $Data->last_name;
			/*
			$RequestData['email'] = "qqualdev@gmail.com";
			$RequestData['first_name'] = "Qualdev1";
			$RequestData['last_name'] = "Qualdev2";
			*/
			break;
		case 'customAction':
			$ApiURL.= "actions";
			$RequestData = [];
			$RequestData['type'] = "CustomAction";
			$RequestData['customer_email'] = $Data['email'];
			$RequestData['action_name'] = $OtherData["action"];
			break;
		case 'customer_detail':
			$ApiURL.= "customers?country_iso_code=null&with_referral_code=false&with_history=true";
			$ApiType = "GET";
			$RequestData=[
			'customer_email' => $Data['email'],
			'customer_id' => Session::has('sess_icustomerid'),
			];
			break;
		/*
		case 'create_order':
			$ApiURL = "https://loyalty.yotpo.com/api/v2/orders";
			$RequestData=[
				"customer_email"=>"gequaldev@gmail.com",
				"total_amount_cents"=>"7500",
				"currency_code"=>"USD",
				"order_id"=>"12346",
				"status" => "paid",
				"created_at"=>date('Y-m-d H:i:s'),
				"ip_address"=>$_SERVER['REMOTE_ADDR'],
				"user_agent"=>$_SERVER['HTTP_USER_AGENT'],
				"discount_amount_cents"=>"500",
				"coupon_code"=>"Test123",
				"items" => [
					[
						"name" => "Francesca Bianchi Sticky Fingers",
						"price_cents" => "13500",
						"id" => "26568",
						"quantity" => 2,
					]
				]
			];
			break;
		*/
		default:
			$RequestData=[];
			break;
	}

	if(config('global.YOTPO_PROG') == true && count($RequestData) > 0)
	{
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $ApiURL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $ApiType,
			CURLOPT_POSTFIELDS =>  json_encode($RequestData),
			CURLOPT_HTTPHEADER => array(
				"x-guid: ".$GUID_KEY,
				"x-api-key: ".$API_KEY,
				"cache-control: no-cache",
				"Content-Type: application/json",
				"Accept: application/json"),
		));
		$result = curl_exec($ch);
		//echo "<pre>";print_r(json_decode($result));exit;
		return json_decode($result);
		//dd(json_decode($result));
	}
}
function googleAnalyticsGA4($pageType="",$temp_ary=array(),$NetTotal=0, $couponCode='',$keyword='',$PaymentType='',$searchResultCount = 0)
{
		$GA4 ="";
		$CurrencySymbol = 'USD';
		$strToVAl = "";
		$CategoryArray = array();
		$ItemPrice = 0;

		$UserDataInfo="";
		$firstname = "";
		$lastname = "";
		$city = "";
		$state = "";
		$country ="";
		$zip ="";
		$phone = "";
		$CustInfoArr = "";
		$emailAddress = null;

		$UserDataInfoVal = "user_data :{
			address : {
				email_address: ".($emailAddress ? "'".$emailAddress."'" : "null")."
			}
		},";

		if(Session::has('ShoppingCart.ShippingAddress'))
		{
			$Shipping = Session::get('ShoppingCart.ShippingAddress');
			$firstname 	= (isset($Shipping["first_name"]) ? $Shipping["first_name"] : '');
			$lastname 	= (isset($Shipping["first_name"]) ? $Shipping["last_name"] : '');
			$country 	= (isset($Shipping["country"]) ? $Shipping["country"] : '');
			$zip 	  	= (isset($Shipping["zip"]) ? $Shipping["zip"] : '');
			$state	  	= (isset($Shipping["state"]) ? $Shipping["state"] : '');
			$phone 		= (isset($Shipping["phone"]) ? $Shipping["phone"] : '');
			$city 		= (isset($Shipping["city"]) ? $Shipping["city"] : '');
			$emailAddress= (isset($Shipping["email"]) ? $Shipping["email"] : '');
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
		$UserDataInfo = "";
		if(Session::has('ShoppingCart.ShippingAddress'))
		{
		$UserDataInfo="user_data :{
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
						";
		}
		else if(Session::has('etype') && Session::get('etype')=="M" && Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0)
		{
		 $UserDataInfo="user_data :{
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
			 $UserDataInfo="user_data :{
										address :
											{
												first_name : '".$firstname."',
												last_name : '".$lastname."',
												email_address: '".$emailAddress."',
												phone_number : '".$phone."'
											}
									},";

		}

		if(Session::has('currency_code') && Session::get('currency_code') != '')
			$CurrencySymbol = Session::get('currency_code');

		$CouponStr = 'coupon: "",';
		if($couponCode!='')
		{
			$CouponStr = 'coupon: "'.$couponCode.'",';
		}
		if($pageType=="WishlistAddPage")
	    {
			$GA4 .="dataLayer.push({ ecommerce: null });";
			$GA4 .="dataLayer.push({
									  event:'add_to_wishlist',".$UserDataInfo." ecommerce:{currency:'".$CurrencySymbol."',value:".$temp_ary["our_price"].",items:[{item_id:'".$temp_ary['sku']."',
												item_name:'".$temp_ary['product_name']."',affiliation:'Maxaroma',index:0,item_list_id:'related_products',item_list_name:'Related Products',
											 }]
											}
										});";
		}
		else if($pageType=="ProdcutQuickView11")
	    {
				$StrCatValue = "";
				$temp_ary = (array)$temp_ary;

                if(isset($temp_ary['breadcrumbsVal']) &&  $temp_ary['breadcrumbsVal']!='')
                {
					$CategoryArray = $temp_ary['breadcrumbsVal'];
				}

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							continue;
						}
						if($d==1)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d]["title"])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d]["title"])."',";
							$q++;
						}
					}
				}

                if($temp_ary['isDealProduct'] == "Yes")
                {
					if(isset($temp_ary["dealprice"]) && $temp_ary["dealprice"] > 0)
					{
						$ItemPrice = $temp_ary["dealprice"];
					}
					else
					{
						$ItemPrice = $temp_ary["retail_price"];
					}
				}
				else
				{
					$ItemPrice =  $temp_ary["product_price"];
				}

        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'select_item',
								   ".$UserDataInfo."
								  ecommerce: {
								  item_list_id: 'related_products',
								  item_list_name: 'Related products',
								  items: [{
											item_id:'".$temp_ary['sku']."',
											item_name:'".addslashes($temp_ary['product_name'])."',
											affiliation: 'Maxaroma',
											index: 0,
											item_brand: '".addslashes($temp_ary['vmanufacture'])."',
											 ".$StrCatValue."
											item_list_id: 'related_products',
											item_list_name: 'Related Products',
											price: ".$ItemPrice.",
											quantity: 1
										 }]
										}
									});";
		}
		else if($pageType=="ViewProductDetail" || $pageType=="ProdcutQuickView")
	    {
				$StrCatValue = "";
				$temp_ary = (array)$temp_ary;

                if(isset($temp_ary['breadcrumbsGA']) &&  $temp_ary['breadcrumbsGA']!='')
                {
					if($pageType=="ProdcutQuickView")
					{
					$CategoryArray =$temp_ary['breadcrumbsGA'];
					}
					else
					{
					$CategoryArray = explode("-",$temp_ary['breadcrumbsGA']);
					}
				}

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					if($pageType=="ViewProductDetail")
					{
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d])."',";
							$q++;
						}
					}
					}
					else
					{
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							continue;
						}
						if($d==1)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d]["title"])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d]["title"])."',";
							$q++;
						}
					}
					}

				}
                if($temp_ary['isDealProduct'] == "Yes")
                {
					if(isset($temp_ary["dealprice"]) && $temp_ary["dealprice"] > 0)
					{
						$ItemPrice = $temp_ary["dealprice"];
					}
					else
					{
						$ItemPrice = $temp_ary["retail_price"];
					}
				}
				else
				{
					$ItemPrice =  $temp_ary["product_price"];
				}

        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'view_item',
								  ".$UserDataInfo."
								  sku:'".$temp_ary['sku']."',
								  productname:'".$temp_ary['product_name']."',
								  productbrand: '".addslashes($temp_ary['vmanufacture'])."',
								  productprice: ".$ItemPrice.",
								  productquantity: 1,
								  ecommerce: {
								  currency: '".$CurrencySymbol."',
								  value: ".$ItemPrice.",
								  items: [{
											item_id:'".$temp_ary['sku']."',
											item_name:'".$temp_ary['product_name']."',
											affiliation: 'Maxaroma',
											index: 0,
											item_brand: '".addslashes($temp_ary['vmanufacture'])."',
											 ".$StrCatValue."
											item_list_id: 'related_products',
											item_list_name: 'Related Products',
											price: ".$ItemPrice.",
											quantity: 1
										 }]
										}
									});";
		}
		else if($pageType=="Shoppingcart")
	    {

				$StrCatValue = "";

                if(isset($temp_ary['CategoryName']) &&  $temp_ary['CategoryName']!='')
                {
					$CategoryArray = explode("-",$temp_ary['CategoryName']);
				}

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d])."',";
							$q++;
						}
					}
				}
				$manufactureName ="";
                if(isset($temp_ary['manufactureName']) && $temp_ary['manufactureName']!='')
                {
					$manufactureName="item_brand: '".addslashes($temp_ary['manufactureName'])."',";
				}
            	$PriceVal = 0;
				if(!empty($temp_ary['ItemPrice']) &&  $temp_ary['ItemPrice'] > 0)
				{
					$PriceVal = $temp_ary['ItemPrice'];
				}
				/** Get Total Cart Quantity */
				$TotalQty = 0;
				if(Session::has('ShoppingCart.Cart'))
				{
					$FullCart = Session::get('ShoppingCart.Cart');
					if(count($FullCart) > 0)
					{
						foreach($FullCart as $CartProduct)
						{
							$TotalQty+=(int)$CartProduct['Qty'];
						}
					}
				}
				if(empty($UserDataInfo) && $UserDataInfo=='')
				{
					$UserDataInfo = $UserDataInfoVal;
				}
				/** Get Total Cart Quantity*/
        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'add_to_cart',
								 ".$UserDataInfo."
								  ecommerce: {
								  currency: '".$CurrencySymbol."',
								  value:".$temp_ary["TotPrice"].",
								  nettotal:".$NetTotal.",
								  totalqty:".$TotalQty.",
								  items: [{
											item_id:'".$temp_ary['SKU']."',
											item_name:'".addslashes($temp_ary['ProductName'])."',
											affiliation: 'Maxaroma',
											index: 0,
											".$manufactureName."
											 ".$StrCatValue."
											item_list_id: 'related_products',
											item_list_name: 'Related Products',
											price: ".$PriceVal.",
											quantity: ".$temp_ary['Qty']."
										 }]
										}
									});";
		}
		else if($pageType=="ViewCartPage")
	    {
        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'view_cart',
								   ".$UserDataInfo."
								  ecommerce: {
								  currency: '".$CurrencySymbol."',
								  value:".$NetTotal.",";
				for($i=0;$i<count($temp_ary);$i++)
				{

				$StrCatValue = "";
				$CategoryArray  = array();
                if(isset($temp_ary[$i]['CategoryName']) &&  $temp_ary[$i]['CategoryName']!='')
                {
					$CategoryArray = explode("-",$temp_ary[$i]['CategoryName']);
				}

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d])."',";
							$q++;
						}
					}
				}

				 $manufactureName ="";
                if(isset($temp_ary[$i]['manufactureName']) && $temp_ary[$i]['manufactureName']!='')
                {
					$manufactureName="item_brand: '".addslashes($temp_ary[$i]['manufactureName'])."',";
				}
				$PriceVal = 0;
				if(!empty($temp_ary[$i]['ItemPrice']) &&  $temp_ary[$i]['ItemPrice'] > 0)
				{
					$PriceVal = $temp_ary[$i]['ItemPrice'];
				}
				$strToVAl .="{
								item_id:'".$temp_ary[$i]['SKU']."',
								item_name:'".addslashes($temp_ary[$i]['ProductName'])."',
								affiliation: 'Maxaroma',
								index: ".$i.",
								".$manufactureName."
								 ".$StrCatValue."
								item_list_id: 'related_products',
								item_list_name: 'Related Products',
								price: ".$PriceVal.",
								quantity: ".$temp_ary[$i]['Qty']."
							},";
				  }
				  $strToVAl = substr($strToVAl,0,-1);

				  $GA4 .="items: [
								".$strToVAl."
							   ]}
									});";

		}
		else if($pageType=="BeginCheckout")
	    {

        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'begin_checkout',
								  ".$UserDataInfo."
								  ecommerce: {
								  currency: '".$CurrencySymbol."',
								  value:".$NetTotal.",
								  ".$CouponStr."";
				for($i=0;$i<count($temp_ary);$i++)
				{

				$StrCatValue = "";
				$CategoryArray  = array();
                if(isset($temp_ary[$i]['CategoryName']) &&  $temp_ary[$i]['CategoryName']!='')
                {
					$CategoryArray = explode("-",$temp_ary[$i]['CategoryName']);
				}

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d])."',";
							$q++;
						}
					}
				}

				 $manufactureName ="";
                if(isset($temp_ary[$i]['manufactureName']) && $temp_ary[$i]['manufactureName']!='')
                {
					$manufactureName="item_brand: '".addslashes($temp_ary[$i]['manufactureName'])."',";
				}
				$priceVal = 0;
				if(!empty($temp_ary[$i]['ItemPrice']) && $temp_ary[$i]['ItemPrice'] > 0)
				{
					$priceVal = $temp_ary[$i]['ItemPrice'];
				}

				$strToVAl .="{
								item_id:'".$temp_ary[$i]['SKU']."',
								item_name:'".addslashes($temp_ary[$i]['ProductName'])."',
								affiliation: 'Maxaroma',
								index: ".$i.",
								".$manufactureName."
								 ".$StrCatValue."
								item_list_id: 'related_products',
								item_list_name: 'Related Products',
								price: ".$priceVal.",
								quantity: ".$temp_ary[$i]['Qty']."
							},";
				  }
				  $strToVAl = substr($strToVAl,0,-1);

				  $GA4 .="items: [
								".$strToVAl."
							   ]}
							   });";

		}
		else if($pageType=="SearchPage" || $pageType=="SearchAutoComplete")
	    {

			$GA4 .="dataLayer.push({
									event: 'view_search_results',
									".$UserDataInfo."
									ecommerce: {
									'search_term': '".addslashes($keyword)."',
									'results' : ".$searchResultCount.",
									}});";

			$GA4 .="dataLayer.push({
									event: 'search',
									".$UserDataInfo."
									ecommerce: {
									'search_term': '".addslashes($keyword)."',
									'results' : ".$searchResultCount.",
									}});";

		}
		else if($pageType=="LoginPopup" || $pageType=="LoginPage")
	    {
			$GA4 .="dataLayer.push({
									event: 'login',
									ecommerce: {
									'method': 'Maxaroma',
									}});";

		}
		else if($pageType=="RegisterPage")
	    {
			$Externald = setExternalIDHash();
			$GA4 .="dataLayer.push({
									event: 'sign_up',
									'userId': '".Session::get('sess_icustomerid')."',
								    'external_id': '".$Externald."',
									ecommerce: {
									'method': 'Maxaroma',
									'email'  : '".Session::get('sess_useremail')."'
									}});";
		}
		else if($pageType=="ShippingMethods")
	    {
        $ShippingInfo = array();
        $ShippingStr = "";
        if(Session::has('ShoppingCart.Shipping') && count(Session::get('ShoppingCart.Shipping')) > 0)
		{
			$ShippingInfo = Session::get('ShoppingCart.Shipping');
			if(isset($ShippingInfo["ShippingMethodName"]) && $ShippingInfo["ShippingMethodName"]!='')
			{
				$ShippingStr = "shipping_tier:'".strip_tags($ShippingInfo["ShippingMethodName"])."',";
			}
		}
        if(isset($couponCode) && $couponCode!='')
        {
			 $couponCode="coupon: '".$couponCode."',";
		}

        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'add_shipping_info',
								   ".$UserDataInfo."
								  ecommerce: {
								  currency: '".$CurrencySymbol."',
								  value:".$NetTotal.",
								  ".$couponCode."
								  ".$ShippingStr."";

				if(isset($temp_ary))
				{
				for($i=0;$i<count($temp_ary);$i++)
				{

				$StrCatValue = "";

                $CategoryArray = explode("-",$temp_ary[$i]['CategoryName']);

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d])."',";
							$q++;
						}
					}
				}
				$PriceVal = 0;
				if(!empty($temp_ary[$i]['ItemPrice']) &&  $temp_ary[$i]['ItemPrice'] > 0)
				{
					$PriceVal = $temp_ary[$i]['ItemPrice'];
				}

				$strToVAl .="{
								item_id:'".$temp_ary[$i]['SKU']."',
								item_name:'".addslashes($temp_ary[$i]['ProductName'])."',
								affiliation: 'Maxaroma',
								index: ".$i.",
								item_brand: '".addslashes($temp_ary[$i]['manufactureName'])."',
								 ".$StrCatValue."
								item_list_id: 'related_products',
								item_list_name: 'Related Products',
								price: ".$PriceVal.",
								quantity: ".$temp_ary[$i]['Qty']."
							},";
				  }
				  $strToVAl = substr($strToVAl,0,-1);
				  $GA4 .="items: [
								".$strToVAl."
							   ]}
							});";
				}

		}
		else if($pageType=="PaymentMethods")
	    {

        if(isset($couponCode) && $couponCode!='')
        {
			 $couponCode="coupon: '".$couponCode."',";
		}
        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'add_payment_info',
								   ".$UserDataInfo."
								  ecommerce: {
								  currency: '".$CurrencySymbol."',
								  value:".$NetTotal.",
								 ".$couponCode."
								  payment_type:'".$PaymentType."',";
				if(isset($temp_ary))
				{
				for($i=0;$i<count($temp_ary);$i++)
				{

				$StrCatValue = "";

                $CategoryArray = explode("-",$temp_ary[$i]['CategoryName']);

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d])."',";
							$q++;
						}
					}
				}
				$PriceVal = 0;
				if(!empty($temp_ary[$i]['ItemPrice']) &&  $temp_ary[$i]['ItemPrice'] > 0)
				{
					$PriceVal = $temp_ary[$i]['ItemPrice'];
				}

				$strToVAl .="{
								item_id:'".$temp_ary[$i]['SKU']."',
								item_name:'".addslashes($temp_ary[$i]['ProductName'])."',
								affiliation: 'Maxaroma',
								index: ".$i.",
								item_brand: '".addslashes($temp_ary[$i]['manufactureName'])."',
								 ".$StrCatValue."
								item_list_id: 'related_products',
								item_list_name: 'Related Products',
								price: ".$PriceVal .",
								quantity: ".$temp_ary[$i]['Qty']."
							},";
				  }
				  $strToVAl = substr($strToVAl,0,-1);
				  $GA4 .="items: [
								".$strToVAl."
							   ]}
							});";
					}
	    }
	   else if($pageType=="RemoveFromShoppingcart")
		{

		$StrCatValue = "";
           $CategoryArray  = array();
                if(isset($temp_ary['CategoryName']) &&  $temp_ary['CategoryName']!='')
                {
					$CategoryArray = explode("-",$temp_ary['CategoryName']);
				}

                if(count($CategoryArray) > 0)
                {
					$q = 2;
					for($d=0;$d<count($CategoryArray);$d++)
					{
						if($d==0)
						{
							$StrCatValue .= "item_category :'".addslashes($CategoryArray[$d])."',";
						}
						else
						{
							$StrCatValue .= "item_category".$q." :'".addslashes($CategoryArray[$d])."',";
							$q++;
						}
					}
				}

       $manufactureName ="";
                if(isset($temp_ary['manufactureName']) && $temp_ary['manufactureName']!='')
                {
					$manufactureName="item_brand: '".addslashes($temp_ary['manufactureName'])."',";
				}
				$PriceVal = 0;
				if(!empty($temp_ary['ItemPrice']) &&  $temp_ary['ItemPrice'] > 0)
				{
					$PriceVal = $temp_ary['ItemPrice'];
				}
        $GA4 .="dataLayer.push({ ecommerce: null });";
        $GA4 .="dataLayer.push({
								  event: 'remove_from_cart',
								  ".$UserDataInfo."
								  ecommerce: {
								  currency: '".$CurrencySymbol."',
								  value:".$temp_ary["TotPrice"].",
								  items: [{
											item_id:'".$temp_ary['SKU']."',
											item_name:'".addslashes($temp_ary['ProductName'])."',
											affiliation: 'Maxraoma',
											index: 0,
											".$manufactureName."
											 ".$StrCatValue."
											item_list_id: 'related_products',
											item_list_name: 'Related Products',
											price: ".$PriceVal .",
											quantity: ".$temp_ary['Qty']."
										 }]
										}
									});";

		}

		else if($pageType=="CategoryPageView")
		{
			$GA4 .="dataLayer.push({
			  event: 'category_view'
			});";

		}

		return html_entity_decode($GA4);
}
function GetDetailPageVideoSchema($ytthumb="",$embedUrl="",$contentUrl="",$product_descriptionVal="",$product_nameVal="",$video_upload_date="")
{
	$SchemaStr = '<script type="application/ld+json">
					{
					  "@context": "https://schema.org",
					  "@type": "VideoObject",
					  "name": "'.$product_nameVal.'",
					  "description": "'.$product_descriptionVal.'",
					  "thumbnailUrl": "'.$ytthumb.'",
					  "embedUrl": "'.$embedUrl.'",
					  "contentUrl": "'.$contentUrl.'",
					  "uploadDate": "'.$video_upload_date.'"
					}
					</script>';

	return $SchemaStr;
}
function setExternalID()
{

	$cookieKey = 'customer_idvals';
	$cookieValue = Cookie::get($cookieKey);

	if ($cookieValue) {
		return $cookieValue;
	}

	$sessionId = Session::getId();

	Cookie::queue($cookieKey, $sessionId, 60 * 24);

	return $sessionId;
}
function setExternalIDHash()
{
	if (Auth::check()) {
		$email = Auth::user()->email;
		return hash('sha256', $email);
	}

	$cookieKey = 'external_id';
	$cookieValue = Cookie::get($cookieKey);

	if ($cookieValue) {
		return $cookieValue;
	}

	$sessionId = Session::getId();
	$externalId = hash('sha256', $sessionId);

	// Set cookie for 24 hours (1440 minutes)
	setcookie($cookieKey, $externalId, time() + (86400 * 30), "/");

	return $externalId;
}

function SetCustomerLoyaltyTier($Email)
{
	Session::forget('LoyaltyTier');
	Session::forget('YotpoRewardPoints');
	if(Session::has('eusertype') && strtolower(Session::get('eusertype')) != 'wholesaler' && !empty($Email))
	{
		$CheckLoyaltyTier = YotpoRequest('customer_detail',['email' => $Email]);

		if(isset($CheckLoyaltyTier->vip_tier_name))
		{
			Session::put('LoyaltyTier',strtolower($CheckLoyaltyTier->vip_tier_name));
		}

		if(isset($CheckLoyaltyTier->points_balance))
		{
			Session::put('YotpoRewardPoints',strtolower($CheckLoyaltyTier->points_balance));
		}
	}
}

/** Schema Functions */
function ProductSchema($Product)
{
	$ProductRatings = [];
	if(Cache::has('ProdctsRatings'))
	{
		$ProductRatings = Cache::get('ProdctsRatings');
	}
	$description = $Product->product_name;
	if(!empty(trim($Product->short_description)))
	{
		$description = strip_tags($Product->short_description);
	}
	$ProdName = stripslashes($Product->product_name);
	$SchemaProduct = [
		"@type" => "Product",
		"image" => $Product->prod_image,
		"url" =>  $Product->product_url,
		"name" => html_entity_decode($ProdName, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
		"description" => $description,
		"offers" => [
			"@type" => "Offer",
			"price" => $Product->product_price,
			"priceCurrency" =>  Session::get('currency_code'),
			"availability" => ($Product->stock=='In' ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut'),
			"hasMerchantReturnPolicy" => [
				"@type" => "MerchantReturnPolicy",
				"applicableCountry" => "US",
				"returnPolicyCategory" => "https://schema.org/MerchantReturnFiniteReturnWindow",
				"merchantReturnDays" => 30,
				"returnMethod" => "https://schema.org/ReturnByMail",
				"returnFees" => "https://schema.org/FreeReturn"
			]
		]
	];
	if(isset($ProductRatings[$Product->products_id]) && isset($ProductRatings[$Product->products_id]['averageScore']) && isset($ProductRatings[$Product->products_id]['totalReviews']) && $ProductRatings[$Product->products_id]['averageScore'] > 0 && $ProductRatings[$Product->products_id]['totalReviews'] > 0 )
	{
		$SchemaProduct["aggregateRating"] = [
			"@type" => "AggregateRating",
			"ratingValue" => $ProductRatings[$Product->products_id]['averageScore'],
			"reviewCount" => $ProductRatings[$Product->products_id]['totalReviews']
		];
	}
	return $SchemaProduct;
}
function PageSchema($PageRoute="",$SchemaData=[])
{
	$ProductRatings = [];
	if(Cache::has('ProdctsRatings'))
	{
		$ProductRatings = Cache::get('ProdctsRatings');
	} else {
		$Ratings = DB::table('pu_products_rating')->select('products_id','rating','total_reviews')->get();
		foreach($Ratings as $Rating)
		{
			$ProductRatings[$Rating->products_id] = ['averageScore' => $Rating->rating,'totalReviews' => $Rating->total_reviews];
		}
		Cache::put('ProdctsRatings', $ProductRatings);
	}

	$SiteAverageRating = 0;
	$SiteTotalReviews = 0;
	if(count($ProductRatings) > 0 )
	{
		$Reviews = array_column($ProductRatings,'totalReviews');
		$SiteTotalReviews = array_sum($Reviews);
		$Scores = array_column($ProductRatings,'averageScore');
		$SiteAverageRating = array_sum($Scores)/count($Scores);
	}

	$organization = [
		"@context" => "https://schema.org",
		"@type" => "Organization",
		"url" => config('global.SITE_URL'),
		"logo" => config('global.SITE_IMAGES').'logo.jpg',
		"name" => "Maxaroma",
		"contactPoint" => [
			[
				"@type" => "ContactPoint",
				"telephone" => config('Settings.CONTACT_PHONE_NO'),
				"email" => "customercare@maxaroma.com",
				"contactType" => "Customer Service",
				"areaServed" => "US"
			]
		],
		"sameAs" => [
			"https://www.facebook.com/yousmellawesome/",
			"https://twitter.com/Max_Aroma",
			"https://www.pinterest.com/MaxAroma/",
			"https://www.youtube.com/channel/UC1iGTiUYtYxOpqAZkur0yag",
			"https://www.instagram.com/maxaroma/",
			"https://www.tiktok.com/@maxaroma/",
			"https://blog.maxaroma.com/"
		]
	];

	if($SiteTotalReviews > 0 && $SiteAverageRating > 2.5)
	{
		$organization['aggregateRating'] = [
			"@type" => "AggregateRating",
			"ratingValue" => number_format($SiteAverageRating,2),
			"reviewCount" => $SiteTotalReviews,
			"ratingCount" => $SiteTotalReviews
		];
	}
	/*
	$ProdReview = DB::table('pu_products_review')
		->select(DB::raw('COUNT(*) as total_count'), DB::raw('AVG(star_rate) as average'))
		->where("approved","Yes")->first();
	if($ProdReview)
	{
		if($ProdReview->total_count > 0 && $ProdReview->average >2.5)
		{
			$organization['aggregateRating'] = [
				"@type" => "AggregateRating",
				"ratingValue" => number_format($ProdReview->average,2),
				"reviewCount" => $ProdReview->total_count,
				"ratingCount" => $ProdReview->total_count
			];
		}
	}
	*/
	$website = [
		"@context" => "https://schema.org",
		"@type" => "WebSite",
		"name" => "Maxaroma",
		"url" => config('global.SITE_URL'),
		"potentialAction" => [
				"@type" => "SearchAction",
				"target" => config('global.SITE_URL').'p4u/key-{keyword}/view',
				"query-input" => "required name=keyword"
		]
	];
	$CommonSchema='<script type="application/ld+json">';
	$CommonSchema.=json_encode($organization, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
	$CommonSchema.='</script>';

	$CommonSchema.='<script type="application/ld+json">';
	$CommonSchema.=json_encode($website, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
	$CommonSchema.='</script>';

	if(isset($SchemaData['page_title']))
	{
		$BreadCrumbList = [
			"@context" => "https://schema.org",
			"@type" => "BreadcrumbList",
			"name" => "Breadcrumbs",
		];
		$position = 1;
		if(is_array($SchemaData['page_title']))
		{
			foreach($SchemaData['page_title'] as $BreadCrumb)
			{
				$BreadCrumbList['itemListElement'][]=[
					"@type" => "ListItem",
					"position" => $position,
					"name" => $BreadCrumb['title'],
					"item" => $BreadCrumb['link']
				];
				$position++;
			}
		} else {
			$BreadCrumbList['itemListElement'][]=[
				"@type" => "ListItem",
				"position" => $position,
				"name" => "Home",
				"item" => config('global.SITE_URL')
			];
			$position++;
			$BreadCrumbList['itemListElement'][]=[
				"@type" => "ListItem",
				"position" => $position,
				"name" => $SchemaData['page_title'],
				"item" => $SchemaData['url']
			];
		}

		$CommonSchema.='<script type="application/ld+json">';
		$CommonSchema.=json_encode($BreadCrumbList, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
		$CommonSchema.='</script>';
	}
	$ProductListSchema = [];
	if($PageRoute == 'home')
	{
		$CommonSchema.='<script type="application/ld+json">';
		$ProductListSchema = [
			"@context" => "https://schema.org",
			"@type" => "ItemList",
			"url" => url()->current()
		];
		$Products = [];
		$position = 1;
		if(isset($SchemaData['NewArrivals']) && count($SchemaData['NewArrivals']) > 0)
		{
			foreach($SchemaData['NewArrivals'] as $NewArrival)
			{
				$Products[] = ProductSchema($NewArrival);
				$position++;
			}
		}
		if(isset($SchemaData['BestSellers']) && count($SchemaData['BestSellers']) > 0)
		{
			foreach($SchemaData['BestSellers'] as $BestSellers)
			{
				$Products[] = ProductSchema($BestSellers);
				$position++;
			}
		}
		$ProductListSchema["itemListElement"] = $Products;
		$CommonSchema.=json_encode($ProductListSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
		$CommonSchema.='</script>';
	}
	if($PageRoute == 'category')
	{
		$Category = [
			"@context" => "https://schema.org",
			"@type" => "ItemList",
			"name" => "Sub Category"
		];
		$position = 1;
		if(isset($SchemaData['pageBanners']['MainBanner']))
		{
			$Category['itemListElement'][] = [
				"@type" => "ListItem",
				"position" => $position,
				"image" => $SchemaData['pageBanners']['MainBanner']['banner'],
				"url" => $SchemaData['pageBanners']['MainBanner']['banner_link'],
				"name" => $SchemaData['pageBanners']['MainBanner']['banner_title']
			];
			$position++;
		}
		if(isset($SchemaData['pageBanners']['RightBanners']) && count($SchemaData['pageBanners']['RightBanners']) > 0)
		{
			foreach($SchemaData['pageBanners']['RightBanners'] as $RightBanner)
			{
				$Category['itemListElement'][] = [
					"@type" => "ListItem",
					"position" => $position,
					"image" => $RightBanner['banner'],
					"url" => $RightBanner['banner_link'],
					"name" => $RightBanner['banner_title']
				];
				$position++;
			}
		}
		if(isset($Category['itemListElement']) && count($Category['itemListElement'])>0)
		{
			$CommonSchema.='<script type="application/ld+json">';
			$CommonSchema.=json_encode($Category, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
			$CommonSchema.='</script>';
		}
		if(isset($SchemaData['Products']) && count($SchemaData['Products']) > 0)
		{
			$CommonSchema.='<script type="application/ld+json">';
			$CatProducts = [
				"@context" => "https://schema.org",
				'@type' => 'ItemList',
				"url" => url()->current(),
			];
			foreach($SchemaData['Products']['NewArrivals'] as $Product)
			{
				$CatProducts['itemListElement'][] = ProductSchema($Product);
			}
			foreach($SchemaData['Products']['BestSellers'] as $Product)
			{
				$CatProducts['itemListElement'][] = ProductSchema($Product);
			}
			$CommonSchema.=json_encode($CatProducts, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
			$CommonSchema.='</script>';
		}
	}
	if($PageRoute == 'productlist')
	{
		if(isset($SchemaData['Products']) && count($SchemaData['Products']) > 0)
		{
			$CommonSchema.='<script type="application/ld+json">';
			$ListProducts = [
				"@context" => "https://schema.org",
				"@type" => "ItemList",
				"url" => url()->current()
			];
			foreach($SchemaData['Products'] as $Product)
			{
				$ListProducts['itemListElement'][] = ProductSchema($Product);
			}
			$CommonSchema.=json_encode($ListProducts, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
			$CommonSchema.='</script>';
		}
	}
	if($PageRoute == 'productdetails')
	{
		if(isset($SchemaData['ProductDetails']))
		{
			$Detail = $SchemaData['ProductDetails'];
			$description = $Detail->product_name;
			if(!empty($Detail->short_description))
			{
				$description = strip_tags($Detail->short_description);
			}
			$ProdName = stripslashes($Detail->product_name);
			$ProdDetail = [
				"@context" => "https://schema.org",
				"@type" => "Product",
				"name" => html_entity_decode($ProdName, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				"image" => $Detail->mainImage,
				"url" => url()->current(),
				"description" => $description,
				"offers" => [
					"@type" => "Offer",
					"price" => $Detail->product_price,
					"priceCurrency" => Session::get('currency_code'),
					"availability" => ($Detail->stock=='In' ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut'),
					"hasMerchantReturnPolicy" => [
						"@type" => "MerchantReturnPolicy",
						"applicableCountry" => "US",
						"returnPolicyCategory" => "https://schema.org/MerchantReturnFiniteReturnWindow",
						"merchantReturnDays" => 30,
						"returnMethod" => "https://schema.org/ReturnByMail",
						"returnFees" => "https://schema.org/FreeReturn"
					]
				]
			];
			if(isset($ProductRatings[$Detail->products_id]) && isset($ProductRatings[$Detail->products_id]['averageScore']) && isset($ProductRatings[$Detail->products_id]['totalReviews']) && $ProductRatings[$Detail->products_id]['averageScore'] > 0 && $ProductRatings[$Detail->products_id]['totalReviews'] > 0 )
			{
				$ProdDetail["aggregateRating"] = [
					"@type" => "AggregateRating",
					"ratingValue" => $ProductRatings[$Detail->products_id]['averageScore'],
					"reviewCount" => $ProductRatings[$Detail->products_id]['totalReviews']
				];
			}
			$CommonSchema.='<script type="application/ld+json">';
			$CommonSchema.=json_encode($ProdDetail, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
			$CommonSchema.='</script>';
		}
	}
	if($PageRoute == 'brand')
	{
		$BrandDetail = $SchemaData['BrandDetail'];
		$BrandProducts = [
			"@context" => "https://schema.org",
			"@type" => "ItemList",
			"url" => url()->current(),
			"name" => str_replace("'","",stripcslashes($BrandDetail[0]->vmanufacture))
		];
		if(isset($SchemaData['Products']) && count($SchemaData['Products']) > 0)
		{
			$CommonSchema.='<script type="application/ld+json">';
			foreach($SchemaData['Products'] as $Product)
			{
				$BrandProducts['itemListElement'][] = ProductSchema($Product);
			}
			$CommonSchema.=json_encode($BrandProducts, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
			$CommonSchema.='</script>';
		}
	}
	if($PageRoute == 'dealofweek')
	{
		$DealOfWeekProducts = [
			"@context" => "https://schema.org",
			"@type" => "ItemList",
			"url" => url()->current(),
			"name" => $SchemaData['page_title']
		];
		if(isset($SchemaData['Products']) && count($SchemaData['Products']) > 0)
		{
			$CommonSchema.='<script type="application/ld+json">';
			foreach($SchemaData['Products'] as $Product)
			{
				$DealOfWeekProducts['itemListElement'][] = ProductSchema($Product);
			}
			$CommonSchema.=json_encode($DealOfWeekProducts, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
			$CommonSchema.='</script>';
		}
	}
	if($PageRoute == 'faq')
	{
		$faqSchema = [
			"@context" => "https://schema.org",
			"@type" => "FAQPage",
			"url" => url()->current(),
			"name" => $SchemaData['page_title']
		];
		if(isset($SchemaData['faq']) && count($SchemaData['faq']) > 0)
		{
			$CommonSchema.='<script type="application/ld+json">';
			foreach($SchemaData['faq'] as $faq)
			{
				$faqSchema["mainEntity"][]= [
					"@type" => "Question",
					"name" => strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$faq->question))),
					"acceptedAnswer" => [
						"@type" => "Answer",
						"text" => strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$faq->answer)))
					]
				];
			}
			$CommonSchema.=json_encode($faqSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
			$CommonSchema.='</script>';
		}
	}
	return $CommonSchema;
}
/** Schema Functions */
function addOrdinalSuffix($number)
{
    if (!in_array(($number % 100), [11, 12, 13])) {
        switch ($number % 10) {
            case 1:  return $number . 'st';
            case 2:  return $number . 'nd';
            case 3:  return $number . 'rd';
        }
    }
    return $number . 'th';
}

function ArraySum($array=[],$key='')
{
    if(count($array) > 0 && !empty($key))
	{
		return array_sum(
			array_map(
				'floatval',
				array_filter(
					array_column($array, $key),
					fn($v) => $v !== null && $v !== ''
				)
			)
		);
	}
}

function setImageName($image, $imgpath, $imgURL)
{
	$imageName="";
	if (!empty($image) && file_exists($imgpath . stripslashes($image))) {
		$ver = filemtime($imgpath . stripslashes($image));
		$imageName = $imgURL . $image . '?ver=' . $ver;
	}
	return $imageName;
}

function GetStorePrefix()
{
	$StorePrefix = "OR";
	if(Session::has("sess_store_prefix") && !empty(Session::get("sess_store_prefix")))
	{
		$StorePrefix = Session::get("sess_store_prefix");
	}
	return $StorePrefix;
}
