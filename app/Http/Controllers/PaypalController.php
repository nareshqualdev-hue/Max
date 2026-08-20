<?php
namespace App\Http\Controllers;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Traits\CartTrait;
use App\Http\Controllers\Traits\EncryptTrait;

use App\Models\Order;
use App\Models\Customer;
use App\Models\PaymentMethod;

use App\Models\DropshipperOrder;
use App\Models\DropshipperOrderDetail;
use App\Models\OrderDetail;
use App\Models\ShippingMode;
use App\Models\Products;

use Session;
use URL;

class PaypalController extends Controller
{
	use CartTrait;
	use EncryptTrait;
	
	public function __construct()
	{
		
		$this->provider = new PayPalClient;

		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_PAYPALEC')
							->where('pm_status', '=', 'Active')
							->get();
		if($db_res->count() > 0)
		{
			$arrPEVar		= unserialize($db_res[0]->pm_details);
			$Mode = trim(strtolower($arrPEVar['paypalec_Transaction_Mode']));
			$client_id = $this->decrypt($arrPEVar['paypalec_Username']);
			$client_secret = $this->decrypt($arrPEVar['paypalec_Password']);
			// echo $client_id."<br>"; 
			// echo $client_secret."<br>";
			// echo "<pre>"; print_r($arrPEVar);
			// exit;
			
				// $PaypalConfig = [
				// 	'mode'    => 'sandbox', 
				// 	'sandbox' => [
				// 		'client_id'    	=> 'AQymJLkSRgzhHf0AjiYOGL_OHQZ60bCggeySkd8F31n_2ery6HK7HXYQGeeBCfszGgAin8XfJbvZuByn',
				// 		'client_secret' => 'ECHswxs-ygvtI9JSZ_3uOTWxyKyrQCK2w7mGq-MXs6a1r8zZSHZt0TuHiJbKAkkjx0_Y06Ma8iE1TGJh',
				// 		'app_id'      	=> ''
				// 	],
				// 	'payment_action' => 'Sale', // Can only be 'Sale', 'Authorization' or 'Order'
				// 	'currency'       => 'USD',
				// 	'billing_type'   => 'MerchantInitiatedBilling',
				// 	'notify_url'     => '', // Change this accordingly for your application.
				// 	'locale'         => '', // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
				// 	'validate_ssl'   => true, // Validate SSL when creating api client.
				// 	'Content-Type'	 => 'application/json'
				// ];

				$PaypalConfig = [
					'mode'    => 'live', 
					'live' => [
						'client_id'    	=> $client_id,
						'client_secret' => $client_secret,
						'app_id'      	=> ''
					],
					'payment_action' => 'Sale', // Can only be 'Sale', 'Authorization' or 'Order'
					'currency'       => 'USD',
					'billing_type'   => 'MerchantInitiatedBilling',
					'notify_url'     => '', // Change this accordingly for your application.
					'locale'         => '', // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
					'validate_ssl'   => true, // Validate SSL when creating api client.
					'Content-Type'	 => 'application/json'
				];
			
						
			$this->provider->setApiCredentials($PaypalConfig);
			
			$this->provider->getAccessToken();
		}
		
	}
	

  	public function SetPaypal(Request $request)
	{
		
		
		if(isset($request->dropsipflag) && $request->dropsipflag == 'dropship')
		{
			
			$data = [];
			
			$OrderTotal  = Session::get('DropShipperOrderAmount');
			
			
			
			$data['intent'] = 'CAPTURE';
			$data["application_context"]['return_url'] = url('/paypal/success/dropship');
			$data["application_context"]['cancel_url'] = url('/imported-order-list.html');
			
			$AmountArr["currency_code"] = "USD";
			$AmountArr["value"] = NumberFormat($OrderTotal);
			$AmountArr["breakdown"]["item_total"]["currency_code"] = "USD";
			$AmountArr["breakdown"]["item_total"]["value"] = NumberFormat($OrderTotal);
			
			$unit_amountInfo = array();
			$unit_amountInfo["currency_code"] = "USD";
			$unit_amountInfo["value"] = NumberFormat($OrderTotal);;
					
					
			
			
			$ItemsArr[] = array(
								'name' 			=> 'Dropshipper Order',
								'sku'			=> 'Dropshiper Order',
								'unit_amount'	=> $unit_amountInfo,
								'quantity' 		=> 1
								);
		
			$data["purchase_units"][0]["amount"] = $AmountArr;
			$data["purchase_units"][0]["items"] = $ItemsArr;
			
			
			
			$response = $this->provider->createOrder($data);
			
		
		    if(isset($response["id"]) && $response["id"]!='')
		    {
				foreach($response["links"] as $link)
				{
					if($link['rel']=="approve")
					{
						return redirect()->away($link['href']);
					}	
				}
			}
			else
			{
				$ErrorMsg = $response["error"]["message"];
				Session::flash('CartError',$ErrorMsg);								
				return redirect('imported-order-list.html');
			}
		}
		 else 
		 {	
			if($this->Is_WholeSaler_Allow() == false)
			{
				return redirect('/shoppingcart/view');
			}
			
			if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
			{
				Session::forget('ShoppingCart');
				return redirect('/shoppingcart/view');	
			}	
			$tempBillingAdd  = Session::get('ShoppingCart.BillingAddress');
			$tempShippingAdd = Session::get('ShoppingCart.ShippingAddress');
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

			$data = [];
			
			
			$ShopCart = Session::get('ShoppingCart.Cart');
			$ItemsArr = array();
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

			
			$data['intent'] = 'CAPTURE';
			$data["application_context"]['return_url'] = url('/paypal/success');
			$data["application_context"]['cancel_url'] = url('/checkout');
			
		
			
			$AmountArr = array();
			if( $OrderSubTotal > 0)
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
			
			$data["purchase_units"][0]["amount"] = $AmountArr;
			$data["purchase_units"][0]["items"] = $ItemsArr;
				
			//echo "<pre>"; print_r($data); exit;	
			$response = $this->provider->createOrder($data);
					

		    if(isset($response["id"]) && $response["id"]!='')
		    {
				foreach($response["links"] as $link)
				{
					if($link['rel']=="approve")
					{
						return redirect()->away($link['href']);
					}	
				}
			}
			else
			{
				$ErrorMsg = $response["error"]["message"];
			
				Session::flash('CartError',$ErrorMsg);								
				return redirect('shoppingcart');
			}
		    

		}		 
	}
   }
	
   public function CheckoutPaypalUpdate(Request $request)
	{
		
		$tokenArr = $this->provider->getAccessToken();		
	
		$accessToken = $tokenArr['access_token'];	
		
		$myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." Before Checkout PayPal Update :".json_encode($request->rtnm)."\n";	
			$stringData .= date("m/d/Y H:i:s")." Before Checkout PayPal Update :".json_encode($request)."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}
		
		$PaypalOrderId = $request["orderID"];
		
		if (empty($accessToken))	
		{	
			Session::forget('PayPalToken');	
			$err_msg = 'Please try agian token expired';
			Session::flash('PlaceOrderError',$err_msg);		
			$Res['status'] = 'Error';
			return json_encode($Res);
		}
		
		if (empty($request["orderID"]))	
		{	
			Session::forget('PayPalToken');	
			$err_msg = 'Please try agian token expired';
			Session::flash('PlaceOrderError',$err_msg);			
			$Res['status'] = 'Error';
			return json_encode($Res);
		}
		$shoppingController = new ShoppingcartController();
		$ShippingInfo = $shoppingController->ShippingMethods($request);
	   $shipModeId = '';
		
			
		
	   if(isset($ShippingInfo) && count($ShippingInfo) > 0){
		    
			foreach($ShippingInfo as $shipv){
				
				if($shipv['selected'] == true){
					$shipModeId = $shipv['id'];
					break; 
				}
			}
		}
		else
		{
			$err_msg = 'Please try agian.shipping method not found for selected shipping address';
			Session::flash('PlaceOrderError',$err_msg);	
			$Res['status'] = 'Error';
			return json_encode($Res);
		}
	
		
		
	    if(empty($shipModeId))    	
		{
			$shipModeId = Session::get('ShoppingCart.Shipping.ShippingMethodID');
		}
		
		
		if(empty($shipModeId))
		{
			$err_msg = 'Please try agian.shipping method not found for selected shipping address';
			Session::flash('PlaceOrderError',$err_msg);	
			$Res['status'] = 'Error';
			return json_encode($Res);
		}
	
            $shoppingController->SetupCart();
       
			
			$OrderSubTotal 	 =  Session::get('ShoppingCart.SubTotal');
			$GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
			$GiftValue = 0;
			if($GiftCouponInfo && count($GiftCouponInfo) > 0)
			{
				$GiftValue = $GiftCouponInfo['Value'];
			}
			## For the All Type Of Discounts Start Here
			$NetDiscount 	 = $this->GetAllDiscounts();
			
			$TotalDiscount 	 = NumberFormat($NetDiscount['TotalDiscount']);
			## For the all types of discounts end here
			$OrderSubTotal 	 = NumberFormat($OrderSubTotal);

			$total_charges = $this->GetAllCharges();
			
			$ShopCart = Session::get('ShoppingCart.Cart');
			$ItemsArr = array();
			$ItemsArr['op'] = 'replace';
			$ItemsArr['path'] = "/purchase_units/@reference_id=='default'/items";
			foreach($ShopCart as $key => $CartItem)
			{
				if(isset($CartItem['IS_Free_Gift']) && $CartItem['IS_Free_Gift'] == 'Yes')
				{	
					$ItemPrice = $CartItem['TotPrice'];
				} else if(isset($CartItem['Is_Free_Sample']) && $CartItem['Is_Free_Sample'] == 'Yes'){
					$ItemPrice = $CartItem['TotPrice'];
				} else{ 
					$ItemPrice = $CartItem['ItemPrice'];
				}
				$unit_amountInfo = array();
				$unit_amountInfo["currency_code"] = "USD";
				$unit_amountInfo["value"] =  $ItemPrice;
				
				
				$ItemsDetails[] = array(
							'name' 			=> $CartItem['ProductName'],
							'sku'			=> $CartItem['SKU'],
							'unit_amount'	=> $unit_amountInfo,
							'quantity' 		=> $CartItem['Qty']
							);
			}
			$ItemsArr['value'] = $ItemsDetails;
		
			$data['op'] = 'replace';
			$data['path'] = "/purchase_units/@reference_id=='default'/amount";
			$data['value']['currency_code'] = "USD";
			$data['value']['value'] = NumberFormat($this->GetNetTotal());
			
			$ShippingSignature = 0;
			if(!empty($this->GetAllCharges('GiftWrappingCharge')) && $this->GetAllCharges('GiftWrappingCharge') > 0)
			{
				$ShippingSignature = $this->GetAllCharges('GiftWrappingCharge');
			}
			
			if(!empty($this->GetAllCharges('ShippingSignature')) && $this->GetAllCharges('ShippingSignature') > 0)
			{
				$ShippingSignature = $ShippingSignature + $this->GetAllCharges('ShippingSignature');
			}
			
			
			
			$data['value']['breakdown']["item_total"]["currency_code"] = "USD";
			$data['value']['breakdown']["item_total"]["value"] =  NumberFormat($OrderSubTotal);
			if(!empty($this->GetAllCharges('Tax')) &&  $this->GetAllCharges('Tax')>0)
			{
				$data['value']['breakdown']['tax_total']['currency_code'] = "USD";
				$data['value']['breakdown']['tax_total']['value'] = $this->GetAllCharges('Tax');
			}	
			if(!empty($this->GetAllCharges('ShippingCharge')) &&  $this->GetAllCharges('ShippingCharge')>0)
			{
				$data['value']['breakdown']['shipping']['currency_code'] = "USD";
				$data['value']['breakdown']['shipping']['value'] = $this->GetAllCharges('ShippingCharge');
			}	
			if(!empty($this->GetAllCharges('ShippingInsurance')) &&  $this->GetAllCharges('ShippingInsurance')>0)
			{
				$data['value']['breakdown']['insurance']['currency_code'] = "USD";
				$data['value']['breakdown']['insurance']['value'] = $this->GetAllCharges('ShippingInsurance');
			}
			if(!empty($ShippingSignature) &&  $ShippingSignature > 0)
			{
				$data['value']['breakdown']['handling']['currency_code'] = "USD";
				$data['value']['breakdown']['handling']['value'] = NumberFormat($ShippingSignature);
			}
			if(!empty($TotalDiscount) &&  $TotalDiscount>0)
			{
				$data['value']['breakdown']['discount']['currency_code'] = "USD";
				$data['value']['breakdown']['discount']['value'] = NumberFormat($TotalDiscount);
			}
			//$data1[] = $shipping;
			$ShippingOption['op'] = 'add';
			$ShippingOption['path'] ="/purchase_units/@reference_id=='default'/shipping/options";
			$ShippingOption['value']= $ShippingInfo;
			$data1[] = $data;
			$data1[] = $ItemsArr;
			$data1[] = $ShippingOption;
			
			
			
			$payment_response = $this->provider->updateOrder($PaypalOrderId,$data1);
		    
		  
		   $response = $this->provider->showOrderDetails($PaypalOrderId);

		   $myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." After Checkout PayPal Update :".json_encode($request->rtnm)."\n";	
				$stringData .= date("m/d/Y H:i:s")." After Checkout PayPal Update :".json_encode($response)."\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
		   
		    
		    if(isset($payment_response["error"])  && $payment_response["error"]!='')
		    {
				
				$err_msg = $payment_response["error"];
				Session::flash('PlaceOrderError',$err_msg);	
				$Res['status'] = 'Error';
				return json_encode($Res);
			}
		    else
		    {
				
				if(isset($response["purchase_units"][0]["payee"]["email_address"]))
				{
					//Session::put('PaypalEmail', $response["purchase_units"][0]["payee"]["email_address"]);
				}
				if(isset($request["payer_email"]) && $request["payer_email"]!='')
				{
					Session::put('PaypalEmail', $request["payer_email"]);
				}
				Session::put('Paypalcity', $request["city"]);
				Session::put('Paypalcountry', $request["country"]);
				Session::put('Paypalstate', $request["state"]);
				Session::put('Paypalzipcode', $request["zip"]);
				$Res['status'] = 'Success';
				return json_encode($Res);
			}	
	}

	public function DoPaymentPaypalPDP(Request $request)
	{
		$step = "";
		$detailsRes = json_decode($request->OrderDetails,true);
		$rtnm = $request->routenmnew;
		$abort = "";
		if(isset($request->isAbort) && $request->isAbort=='isAbort')
		{
			$abort = $request->isAbort;
			$detailsRes = $this->provider->showOrderDetails($detailsRes["id"]); 
		}
		$detailsRes = $this->provider->capturePaymentOrder($detailsRes["id"]);
		$myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
		
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." PDP Before PayPal Order Approval :".json_encode($rtnm)."\n";	
			$stringData .= date("m/d/Y H:i:s")." PDP Before PayPal Order Approval Insert :".json_encode($detailsRes)."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		if(isset($detailsRes["status"]) && ($detailsRes["status"]=="COMPLETED" || $detailsRes["status"]=="APPROVED"))
		{
			//Log::info('paypalcont : 2127 Status - '.$detailsRes["status"]);
			if(isset($detailsRes["status"]) && $detailsRes["status"]=="COMPLETED" && isset($request->isAbort) && $request->isAbort!='' && $request->isAbort=='isAbort')
			{
				//return "OrderReceiptSuceess";
				//Log::info('paypalcont : 2131 Status - '.$detailsRes["status"]);
				$ret['status'] = 'OrderReceiptSuceess';
				$ret['oid'] = base64_encode($request->OrderID);
				return json_encode($ret);
			}
			$ret['status'] = 'OrderReceiptSuceess';
			$ret['detailsRes'] = $detailsRes;
			$ret['oid'] = base64_encode($request->OrderID);
			return json_encode($ret);
		}
		else
		{		
			//Log::info('paypalcont : 2139 Status - '.$detailsRes["status"]);	
			$payment_gateway_response = json_encode($detailsRes);
			$transaction_info = "This transaction has been Declined.";	
			$updAray = array (
								'status'									=> 'Declined',
								'transaction_info' 					=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response.$step
			);

			if(isset($request->OrderID)  && $request->OrderID > 0)
			{
				$order_id = $request->OrderID;
				$updOrder = Order::Where("orders_id","=",$order_id)->update($updAray);	
			}
			
								
			// $ErrorLongMsg = 'Please check details, try again';
			// Session::flash('CartError',$ErrorLongMsg);	
			$myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." PDP After PayPal Order Approval :".json_encode($rtnm)."\n";	
				$stringData .= date("m/d/Y H:i:s")." PDP After PayPal Order Approval Insert :".json_encode($detailsRes)."\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}

		}		

	}

	public function transliterate($string)
	{
		// ICU rule: Any-Latin → Latin ASCII
		$latin = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
		$latin = preg_replace('/[^\x20-\x7E]/', '', $latin);
		 $map = [
			"ʿ" => "a",   // ʿAyn → A
			"`" => "",    // Remove backticks if any
		];

    	return strtr($latin, $map);
	}

	public function DoPaymentPaypal(Request $request)
	{
		$step = "";
		$detailsRes = json_decode($request->OrderDetails,true);
		$rtnm = $request->routenmnew;
		if($rtnm == 'billing-payment'){
			$step = "---last_step";
		}
		
		$abort = "";
		if(isset($request->isAbort) && $request->isAbort=='isAbort')
		{
			$abort = "---".$request->isAbort;
			$detailsRes = $this->provider->showOrderDetails($detailsRes["id"]); 
		}
		$detailsRes = $this->provider->capturePaymentOrder($detailsRes["id"]);
		$myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." Before PayPal Order Approval :".json_encode($rtnm)."\n";	
			$stringData .= date("m/d/Y H:i:s")." After PayPal Order Approval Insert :".json_encode($detailsRes)."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		$shoppingController = new ShoppingcartController();

		$ShippingSignatureFlag = 'No';
		if(Session::has('ShoppingCart.ShippingSignature') && Session::get('ShoppingCart.ShippingSignature') != ''){
			$ShippingSignatureFlag = 'Yes';
		}
	
		$address2		= ''; 	
		$firstname  	= '';
		if(isset($detailsRes["payer"]["name"]["given_name"]) && $detailsRes["payer"]["name"]["given_name"]!='')
		{
			$firstname 		=  $detailsRes["payer"]["name"]["given_name"];
		}
		$surname 		= '';
		if(isset($detailsRes["payer"]["name"]["surname"]) && $detailsRes["payer"]["name"]["surname"]!='')
		{
			$surname 		=$detailsRes["payer"]["name"]["surname"];
		}
		$shipping_name = "";
		$sfirst_name = "";
		$slast_name = "";
		if(isset($detailsRes["purchase_units"][0]["shipping"]["name"]["full_name"]) && $detailsRes["purchase_units"][0]["shipping"]["name"]["full_name"]!=''){
			$shipping_name = $detailsRes["purchase_units"][0]["shipping"]["name"]["full_name"];
			$shipping_nm_arr = explode(" ",$shipping_name);
			if(isset($shipping_nm_arr[0]) && $shipping_nm_arr[0] != ''){
				$sfirst_name = $shipping_nm_arr[0];
			}
			if(isset($shipping_nm_arr[1]) && $shipping_nm_arr[1] != ''){
				$slast_name = $shipping_nm_arr[1];
			}
		}

		if($sfirst_name != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $sfirst_name))
		{
			$sfirst_name = $this->transliterate($sfirst_name);
		}
		if($slast_name != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $slast_name))
		{
			$slast_name = $this->transliterate($slast_name);
		}


		$address1 = '';
		if(isset($detailsRes["purchase_units"][0]["shipping"]["address"]["address_line_1"]))
		{
			$address1 		= $detailsRes["purchase_units"][0]["shipping"]["address"]["address_line_1"];
		}
		$address2 = '';
		if(isset($detailsRes["purchase_units"][0]["shipping"]["address"]["address_line_2"]))
		{
			$address2 		= $detailsRes["purchase_units"][0]["shipping"]["address"]["address_line_2"];
		}
		$city = '';
		if(isset($detailsRes["purchase_units"][0]["shipping"]["address"]["admin_area_2"]))
		{
		$city 		= $detailsRes["purchase_units"][0]["shipping"]["address"]["admin_area_2"];
		}
		$state = '';
		if(isset($detailsRes["purchase_units"][0]["shipping"]["address"]["admin_area_1"]))
		{ 
			$state 		= $detailsRes["purchase_units"][0]["shipping"]["address"]["admin_area_1"];
		}
		$postal_code 		= '';
		if(isset($detailsRes["purchase_units"][0]["shipping"]["address"]["postal_code"]))
		{
			$postal_code 		= $detailsRes["purchase_units"][0]["shipping"]["address"]["postal_code"];
		}
		$country_code = '';
		if(isset($detailsRes["purchase_units"][0]["shipping"]["address"]["country_code"]))
		{
			$country_code 		= $detailsRes["purchase_units"][0]["shipping"]["address"]["country_code"];
		}
		$customerEmail  = '';
		if (isset($detailsRes["payer"]["email_address"]))
		{
			$customerEmail 		=  $detailsRes["payer"]["email_address"] ;
		}

		if($address1 != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $address1))
		{
			$address1 = $this->transliterate($address1);
		}
		if($address2 != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $address2))
		{
			$address2 = $this->transliterate($address2);
		}
		if($city != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $city))
		{
			$city = $this->transliterate($city);
		}

		if($firstname != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $firstname))
		{
			$firstname = $this->transliterate($firstname);
		}
		if($surname != '' && !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $surname))
		{
			$surname = $this->transliterate($firstname);
		}

			$newrequest = [
				'bill_country' 			=> $country_code,
				'bill_fname' 			=> $firstname,
				'bill_lname'				=> $surname,
				'bill_address1' 		=> $address1,
				'bill_address2' 		=> $address2,
				'bill_city' 				=> $city,
				'bill_state' 				=> $state,
				'bill_zip' 				=> $postal_code,
				'bill_phone' 			=> '',
				'bill_email' 				=> $customerEmail,
				'bill_cemail' 			=> $customerEmail,
				'sameasbill' 			=> 'No',
				'bill_other_state'		=> $country_code != 'US' ? $state : ''
			];
			
			//$this->SetBillingAddress($newrequest);
			
			
			$newrequestShip = [
				'ship_country' 			=> $country_code,
				'ship_fname' 				=> $sfirst_name,
				'ship_lname'				=> $slast_name,
				'ship_company'			=> '',
				'ship_address1' 			=> $address1,
				'ship_address2' 			=> $address2,
				'ship_city' 					=>$city,
				'ship_state' 				=> $state,
				'ship_zip' 					=>$postal_code,
				'ship_phone' 				=> '',
				'ship_email' 				=> $customerEmail,
				'sameasbill' 				=> 'No',
				'ship_other_state'			=> $country_code != 'US' ? $state : ''
			];
			
			// $this->SetShippingAddress($newrequestShip);
			
			// if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			// {
			// 	$shoppingController->SetGuestCustomer($newrequest,"Yes");
			// } else {
			// 	$shoppingController->CustomerInfoUpdate($newrequest);
			// }
			
			//if(isset($detailsRes["status"]) && $detailsRes["status"]=="COMPLETED")
			if(isset($detailsRes["status"]) && ($detailsRes["status"]=="COMPLETED" || $detailsRes["status"]=="APPROVED"))
			{
				
			if(isset($rtnm) && $rtnm == 'billing-payment')
			{
			  $BillingVal  = Session::get('ShoppingCart.BillingAddress');
			  $newrequest["bill_phone"] =  isset($BillingVal['phone']) ? $BillingVal['phone'] : '';
			  $ShippingVal = Session::get('ShoppingCart.ShippingAddress');
			  $newrequestShip["ship_phone"] =  isset($ShippingVal['phone']) ? $ShippingVal['phone'] : '';	
			}	
			$this->SetBillingAddress($newrequest);		
			$this->SetShippingAddress($newrequestShip);
		
			if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			{
				$shoppingController->SetGuestCustomer($newrequest,"Yes");
			} else {
				$shoppingController->CustomerInfoUpdate($newrequest);
			}	
				
			$payment_gateway_response = json_encode($detailsRes);
			$transaction_info = "This transaction has been approved.";	
			
			$Billing  = Session::get('ShoppingCart.BillingAddress');
			$Shipping = Session::get('ShoppingCart.ShippingAddress');

			$pay_status = 'Unpaid';
			if($detailsRes["status"]=="COMPLETED"){
				$pay_status = 'Paid';
			}

			$updAray = array (
								'status'									=> 'Pending',
								'pay_status' 	   						=> $pay_status,//'Paid',
								'transaction_info' 					=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response.$step.$abort,
								'ship_first_name' 					=> isset($Shipping['first_name']) ? $Shipping['first_name'] : '', 
								'ship_last_name' 						=> isset($Shipping['last_name']) ? $Shipping['last_name'] : '',
								'ship_company' 						=> isset($Shipping['company']) ? $Shipping['company'] : '',
								'ship_email' 							=> isset($Shipping['email']) ? $Shipping['email'] : '',
								'ship_address1' 						=> isset($Shipping['address1']) ? $Shipping['address1'] : '',
								'ship_address2' 						=> isset($Shipping['address2']) ? $Shipping['address2'] : '',
								'ship_city' 								=> isset($Shipping['city']) ? $Shipping['city'] : '',
								'ship_zip' 								=> isset($Shipping['zip']) ? $Shipping['zip'] : '',
								'ship_state' 							=> isset($Shipping['state']) ? $Shipping['state'] : '',
								'ship_country' 						=> isset($Shipping['country']) ? $Shipping['country'] : '',
								'ship_phone' 							=> isset($Shipping['phone']) ? $Shipping['phone'] : '',
								'bill_first_name' 						=> isset($Billing['first_name']) ? $Billing['first_name'] : '',
								'bill_last_name' 						=> isset($Billing['last_name']) ? $Billing['last_name'] : '',
								'bill_company' 						=> isset($Billing['company']) ? $Billing['company'] : '',
								'bill_email' 								=> isset($Billing['email']) ? $Billing['email'] : '',
								'bill_address1' 						=> isset($Billing['address1']) ? $Billing['address1'] : '',
								'bill_address2' 						=> isset($Billing['address2']) ? $Billing['address2'] : '',
								'bill_city' 								=> isset($Billing['city']) ? $Billing['city'] : '',
								'bill_zip' 								=> isset($Billing['zip']) ? $Billing['zip'] : '',
								'bill_state' 								=> isset($Billing['state']) ? $Billing['state'] : '',
								'bill_country' 							=> isset($Billing['country']) ? $Billing['country'] : '',
								'bill_phone' 							=> isset($Billing['phone']) ? $Billing['phone'] : '',
								'is_shipping_signature' 				=> $ShippingSignatureFlag
							  );
			
		
			if(isset($request->OrderID)  && $request->OrderID > 0)
			{
				$order_id = $request->OrderID;
			}
			else if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID') > 0)
			{
				$order_id = Session::get('ShoppingCart.OrderID');
			} else {
				$myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');
					$stringData = date("m/d/Y H:i:s")." Order id not found ".$Billing['email'];
					fwrite($fh, $stringData);
					fclose($fh);
				}
			}
			
			$updOrder = Order::Where("orders_id","=",$order_id)
								->update($updAray);	
								
			 //if(isset($detailsRes["status"]) && $detailsRes["status"]=="COMPLETED" && isset($request->isAbort) && $request->isAbort!='' && $request->isAbort=='isAbort')
			 if (isset($detailsRes['status']) && $detailsRes['status'] === 'COMPLETED')
			 {
				  return "OrderReceiptSuceess";
			 }						
			}
			else
			{
				
			$payment_gateway_response = json_encode($detailsRes);
			$transaction_info = "This transaction has been Declined.";	
			
			$Billing  = Session::get('ShoppingCart.BillingAddress');
			$Shipping = Session::get('ShoppingCart.ShippingAddress');
			$CustomerEmail = "";
			$Paypalcountry = "";
			$Paypalcity  = "";
			$Paypalstate  = "";
			$Paypalzipcode  = "";
				if(Session::has('PaypalEmail') && Session::get('PaypalEmail')!='')
				{
					$Billing['email']  =  Session::get('PaypalEmail');
					$Shipping['email']  =  Session::get('PaypalEmail');
				}	
				if(Session::has('Paypalcountry') && Session::get('Paypalcountry')!='')
				{
					$Billing['country']   =  Session::get('Paypalcountry');
					$Shipping['country']  =  Session::get('Paypalcountry');
				}	
				if(Session::has('Paypalcity') && Session::get('Paypalcity')!='')
				{
					$Billing['city']   =  Session::get('Paypalcity');
					$Shipping['city']   =  Session::get('Paypalcity');
				}	
				if(Session::has('Paypalstate') && Session::get('Paypalstate')!='')
				{
					$Billing['state']   =  Session::get('Paypalstate');
					$Shipping['state']   =  Session::get('Paypalstate');
				}	
				if(Session::has('Paypalzipcode') && Session::get('Paypalzipcode')!='')
				{
					$Billing['zip']   =  Session::get('Paypalzipcode');
					$Shipping['zip']   =  Session::get('Paypalzipcode');
				}	

						
			
			$updAray = array (
								'status'									=> 'Declined',
								'transaction_info' 					=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response.$step,
								'ship_first_name' 					=> isset($Shipping['first_name']) ? $Shipping['first_name'] : '', 
								'ship_last_name' 						=> isset($Shipping['last_name']) ? $Shipping['last_name'] : '',
								'ship_company' 						=> isset($Shipping['company']) ? $Shipping['company'] : '',
								'ship_email' 							=> isset($Shipping['email']) ? $Shipping['email'] : '',
								'ship_address1' 						=> isset($Shipping['address1']) ? $Shipping['address1'] : '',
								'ship_address2' 						=> isset($Shipping['address2']) ? $Shipping['address2'] : '',
								'ship_city' 								=> isset($Shipping['city']) ? $Shipping['city'] : '',
								'ship_zip' 								=> isset($Shipping['zip']) ? $Shipping['zip'] : '',
								'ship_state' 							=> isset($Shipping['state']) ? $Shipping['state'] : '',
								'ship_country' 						=> isset($Shipping['country']) ? $Shipping['country'] : '',
								'ship_phone' 							=> isset($Shipping['phone']) ? $Shipping['phone'] : '',
								'bill_first_name' 						=> isset($Billing['first_name']) ? $Billing['first_name'] : '',
								'bill_last_name' 						=> isset($Billing['last_name']) ? $Billing['last_name'] : '',
								'bill_company' 						=> isset($Billing['company']) ? $Billing['company'] : '',
								'bill_email' 								=> isset($Billing['email']) ? $Billing['email'] : '',
								'bill_address1' 						=> isset($Billing['address1']) ? $Billing['address1'] : '',
								'bill_address2' 						=> isset($Billing['address2']) ? $Billing['address2'] : '',
								'bill_city' 								=> isset($Billing['city']) ? $Billing['city'] : '',
								'bill_zip' 								=> isset($Billing['zip']) ? $Billing['zip'] : '',
								'bill_state' 								=> isset($Billing['state']) ? $Billing['state'] : '',
								'bill_country' 							=> isset($Billing['country']) ? $Billing['country'] : '',
								'bill_phone' 							=> isset($Billing['phone']) ? $Billing['phone'] : '',
								'is_shipping_signature' 				=> $ShippingSignatureFlag
							  );
			
		
				if(isset($request->OrderID)  && $request->OrderID > 0)
				{
					$order_id = $request->OrderID;
				}
				else if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID') > 0)
				{
					$order_id = Session::get('ShoppingCart.OrderID');
				}
				
				$updOrder = Order::Where("orders_id","=",$order_id)
									->update($updAray);	
									
				$ErrorLongMsg = 'Please check details, try again';
				Session::flash('CartError',$ErrorLongMsg);

				$myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');
					$stringData = date("m/d/Y H:i:s")." After PayPal Order Approval :".json_encode($rtnm)."\n";
					$stringData .= date("m/d/Y H:i:s")."After PayPal Order Approval Insert :".json_encode($detailsRes)."\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}	
			}
			
			$myFile = '/home/maxaroma/public_html/Logs/ApplePayOtherLog.txt';
			if(fopen($myFile, 'a+')) 
			{
				$fh = fopen($myFile, 'a+');
				$stringData = date("m/d/Y H:i:s")." After PayPal Order Approval :".json_encode($rtnm)."\n";
				$stringData .= date("m/d/Y H:i:s")."After PayPal Order Approval Insert after :".json_encode($detailsRes)."\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			if (isset($detailsRes['status']) && $detailsRes['status'] === 'COMPLETED') {
				return "OrderReceiptSuceess";
			} else {
				return "Declined";
			}

								
		
	}

	public function Capture_Phoneorder(Request $request){
		$OrderID = $request->order_id;
		$orderResponse = $request->orderDetails;

		$MainId = (isset($orderResponse['id'])) ? $orderResponse['id']: '';
		$Status = (isset($orderResponse["status"]))  ? $orderResponse["status"]: '';
		$payment_source = (isset($orderResponse["payment_source"]))  ? $orderResponse["payment_source"]: ''; 
		$purchase_units = (isset($orderResponse["purchase_units"]))  ? $orderResponse["purchase_units"]: ''; 
		$payer = (isset($orderResponse["payer"]))  ? $orderResponse["payer"]: '';
				
		if(isset($Status) && strtoupper($Status)=="COMPLETED")
		{
		
			$payment_gateway_response = "ID".$MainId."--Status".$Status."--".json_encode($payment_source)."---".json_encode($purchase_units)."---".json_encode($payer);
			$transaction_info = "This transaction has been completed.";
			$updAray = array (
				//'PayPalResponse' 			=> $orderResponse,
				'status' 	   				=> 'Pending',
				'pay_status' 	   			=> 'Paid',
				'transaction_info' 			=> $transaction_info,
				'payment_gateway_response' 	=> $orderResponse,//$payment_gateway_response,
				'phoneorder_paymentdate' 	=> date("Y-m-d H:i:s"),
				'payment_type' 	 			=> 'PAYMENT_PAYPALEC',
				'payment_method' 	 		=> 'Paypal Express Checkout',
				'pay_status' 				=> 'Paid'
			);
			$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);

			$OrderRs = DB::table('pu_orders as o')
									->join('pu_order_detail as ord','ord.orders_id','=','o.orders_id')
									->select('o.orders_id', 'o.orders_no','o.customer_id', 'o.order_total', 'o.bill_email','o.bill_first_name','o.bill_last_name','o.bill_company','o.bill_email','o.bill_address1','o.bill_address2','o.bill_city','o.bill_zip','o.bill_state','o.bill_country','o.bill_phone','o.ship_first_name','o.ship_last_name','o.ship_company','o.ship_email','o.ship_address1','o.ship_address2','o.ship_city','o.ship_zip','o.ship_state','o.ship_country','o.ship_phone','o.sub_total','o.auto_discount','o.quantity_discount','o.reward_discount','o.coupon_amount','o.gc_amount','o.refer_amount','o.apply_credit','ord.price','ord.quantity','ord.sku','ord.product_name','ord.total','o.shipping_amt','o.tax','o.shipping_signature','o.route_shipping_insurance_charge','o.gift_charge')
									->where('o.orders_id', '=', $OrderID)
									->get();

			$customerEmail = '';
				if(isset($OrderRs[0]->bill_email) && $OrderRs[0]->bill_email!='')
				{
					$customerEmail	= $OrderRs[0]->bill_email;
				}
				
				$orderNo		= $OrderRs[0]->orders_no;
				$customerName 	= '';
				if((isset($OrderRs[0]->ship_first_name) && $OrderRs[0]->ship_first_name!=''))
				{
					$customerName	= $OrderRs[0]->ship_first_name." ";
				}
				
				if(isset($OrderRs[0]->ship_last_name) && $OrderRs[0]->ship_last_name)
				{
					$customerName = $customerName.$OrderRs[0]->ship_last_name;
				}
				
				// $Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
				// OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
								
				$ret['status'] = 'success';
				$ret['oid'] = base64_encode($OrderID);
				return json_encode($ret);
		} else if(isset($Status) && strtoupper($Status)=="APPROVED"){
			$payment_gateway_response = "ID".$MainId."--Status".$Status."--".json_encode($payment_source)."---".json_encode($purchase_units)."---".json_encode($payer);
			$transaction_info = "This transaction has been approved.";
			$updAray = array (
				'pay_status' 	   			=> 'Unpaid',
				'transaction_info' 			=> $transaction_info,
				'payment_gateway_response' 	=> $orderResponse,//$payment_gateway_response,
				'phoneorder_paymentdate' 	=> date("Y-m-d H:i:s"),
				'payment_type' 	 			=> 'PAYMENT_PAYPALEC',
				'payment_method' 	 		=> 'Paypal Express Checkout',				
			);
			$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
			$ret['status'] = 'approved';
			$ret['oid'] = base64_encode($OrderID);
			return json_encode($ret);
		}  else {
			$payment_gateway_response = "ID".$MainId."--Status".$Status."--".json_encode($payment_source)."---".json_encode($purchase_units)."---".json_encode($payer);
			$transaction_info = "This transaction has been declined.";
			$updAray = array (
				//'PayPalResponse' 			=> $orderResponse,
				'status' 	   				=> 'Declined',
				'pay_status' 	   			=> 'Unpaid',
				'transaction_info' 			=> $transaction_info,
				'payment_gateway_response' 	=> $orderResponse,//$payment_gateway_response,
				'phoneorder_paymentdate' 	=> date("Y-m-d H:i:s"),
				'payment_type' 	 			=> 'PAYMENT_PAYPALEC',
				'payment_method' 	 		=> 'Paypal Express Checkout',				
			);
			$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
			$ret['status'] = 'declined';
			$ret['oid'] = base64_encode($OrderID);
			return json_encode($ret);
		}

		/*$OrderInsert = array (
			'PayPalResponse' => $order_details,
			'payment_type' 	 => 'order_id',
			'payment_method' 	 => 'Paypal Express Checkout',
			'pay_status' 		=> 'Paid'
		);
		$CurrOrder = Order::find($order_ref_id);
		$udpRefer = $CurrOrder->update($OrderInsert);	*/
	}

	public function updatePaypalOrderShippingOption(Request $request)
	{
		try {
			$tokenArr = $this->provider->getAccessToken();
			$accessToken = $tokenArr['access_token'] ?? '';

			$orderId = $request->orderID;

			if (empty($orderId)) {
				return response()->json([
					'status' => 'error',
					'message' => 'PayPal Order ID is missing.'
				], 400);
			}

			$prod_price = (float)($request->prod_price ?? 0);
			$prod_qty   = (float)($request->prod_qty ?? 1);

			$total_product_price = number_format($prod_price * $prod_qty, 2, '.', '');

			$selectedShippingOption = [];

			if ($request->has('ship_info') && !empty($request->ship_info)) {
				$selectedShippingOption = json_decode(json_encode($request->ship_info), true);
			} elseif ($request->has('selectedShippingOption') && !empty($request->selectedShippingOption)) {
				$selectedShippingOption = json_decode(json_encode($request->selectedShippingOption), true);
			}

			if (empty($selectedShippingOption)) {
				return response()->json([
					'status' => 'error',
					'message' => 'Selected shipping option is missing.'
				], 400);
			}

			$shipping_charges = "0.00";

			if (isset($selectedShippingOption['amount']['value'])) {
				$shipping_charges = number_format((float)$selectedShippingOption['amount']['value'], 2, '.', '');
			}			

			$paypalSelectedShippingOption = [
				'id' => (string)($selectedShippingOption['id'] ?? 'shipping_option'),
				'label' => (string)($selectedShippingOption['label'] ?? 'Shipping'),
				'type' => 'SHIPPING',
				'selected' => true,
				'amount' => [
					'currency_code' => 'USD',
					'value' => $shipping_charges
				]
			];
			$tax_price = "0.00";

			Log::warning('LocationTax 1143: LocationTax', [
				'LocationTax' => json_encode($request->all())
			]);

			$shoppingController = new ShoppingcartController();		
			$get_tax_price = $shoppingController->TaxCalculation($request->shipping_countrycode ?? '',$request->shipping_state ?? '',$request->shipping_postalcode ?? '','',(float)$total_product_price,$request->shipping_city ?? '',$shipping_charges);

			$tax_price = number_format((float)$get_tax_price, 2, '.', '');		

			$total_price = number_format(
				(float)$total_product_price + (float)$shipping_charges + (float)$tax_price,
				2,
				'.',
				''
			);
			
			$patchData = [
				[
					'op' => 'replace',
					'path' => "/purchase_units/@reference_id=='default'/shipping/options",
					'value' => [
						$paypalSelectedShippingOption
					]
				],
				[
					'op' => 'replace',
					'path' => "/purchase_units/@reference_id=='default'/amount",
					'value' => [
						'currency_code' => 'USD',
						'value' => $total_price,
						'breakdown' => [
							'item_total' => [
								'currency_code' => 'USD',
								'value' => $total_product_price
							],
							'shipping' => [
								'currency_code' => 'USD',
								'value' => $shipping_charges
							],
							'tax_total' => [
								'currency_code' => 'USD',
								'value' => $tax_price
							]
						]
					]
				]
			];

			$curl = curl_init();

			curl_setopt_array($curl, [
				CURLOPT_URL => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . $orderId,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST => 'PATCH',
				CURLOPT_POSTFIELDS => json_encode($patchData),
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'Authorization: Bearer ' . $accessToken
				],
			]);

			$response = curl_exec($curl);
			$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$curlError = curl_error($curl);

			curl_close($curl);

			if ($curlError) {
				return response()->json([
					'status' => 'error',
					'message' => $curlError
				], 500);
			}

			if ($httpCode == 204) {
				return response()->json([
					'status' => 'success',
					'total_product_price' => $total_product_price,
					'shipping_charges' => $shipping_charges,
					'tax_price' => $tax_price,
					'total_price' => $total_price
				]);
			}

			return response()->json([
				'status' => 'error',
				'message' => 'PayPal order update failed.',
				'http_code' => $httpCode,
				'paypal_response' => json_decode($response, true),
				'raw_response' => $response,
				'patchData' => $patchData
			], 400);

		} catch (\Exception $e) {
			return response()->json([
				'status' => 'error',
				'message' => $e->getMessage()
			], 500);
		}
	}

	public function updatePaypalOrder(Request $request){		
		//$accessToken = $this->provider->getAccessToken(); ///$this->getPayPalAccessToken();
		$tokenArr = $this->provider->getAccessToken();
		$accessToken = $tokenArr['access_token'];		
		
		$orderId = $request->orderID;
		$ref_id = $request->ref_id;
		$shipping_address = $request->shippingAddress;

		$shipping_city = $request->shipping_city;
		$shipping_countrycode = $request->shipping_countrycode;
		$shipping_postalcode = $request->shipping_postalcode;
		$shipping_state = $request->shipping_state;

		$shipping_info = $request->shipping_info;
		$shipping_charges = "0";
		if($shipping_info != ''){
			$shipping_options_arr = json_decode(json_encode($shipping_info),true); 
			foreach($shipping_options_arr as $s){
				if($s['selected'] == true){
					$shipping_charges = $s['amount']['value']; 
				}
			}
		}
		$prod_price = $request->prod_price;
        $prod_qty = $request->prod_qty;
        $prod_id = $request->prod_id;

		// if($shipping_state == 'NY'){
		// 	$tax_price = "20";
		// } else {
		// 	$tax_price = "0";
		// }	
		$shoppingController = new ShoppingcartController();
		$total_product_price = (float)$prod_price * (float)$prod_qty;
		
		
		
		$get_tax_price = $shoppingController->TaxCalculation($shipping_countrycode, $shipping_state, $shipping_postalcode,'', $total_product_price,$shipping_city,$shipping_charges);
		
		if($get_tax_price > 0){
			$tax_price = (string)number_format($get_tax_price,2);//$get_tax_price;
			//$tax_price = "20";
		} else {
			$tax_price = "0";
		}
		//$tax_price = "15";
		
		$total_price = (float)$total_product_price + (float)$tax_price + (float)$shipping_charges;

		//https://staging.maxaroma.com/fragrance/niche-fragrances/stephanie-de-bruijn-le-sully/pid/28367/2
		$orderDetails = $this->provider->showOrderDetails($accessToken); //$this->getOrderDetails($orderId,$accessToken);
		if(!empty($orderDetails)){
			$curl = curl_init();
			/*
			"amount": {
                "currency_code": "USD",
                "value": "190.00",
                "breakdown": {
                    "item_total": {
                        "currency_code": "USD",
                        "value": "180.00"
                    },
                    "tax_total": {
                        "currency_code": "USD",
                        "value": "10.00"
                    }
                }
            },*/

			// $orderDetails['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code'] = 'USD';
			// $orderDetails['purchase_units'][0]['amount']['breakdown']['shipping']['value'] = "20.00";
			
			// $orderDetails['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code'] = 'USD';
			// $orderDetails['purchase_units'][0]['amount']['breakdown']['shipping']['value'] = "20.00";

			// $orderDetails['purchase_units'][0]['amount']['currency_code'] = 'USD';		
			// $orderDetails['purchase_units'][0]['amount']['value'] = "190.00";

			// $orderDetails['purchase_units'][0]['amount']['breakdown']['tax_total']['currency_code'] = 'USD';
			// $orderDetails['purchase_units'][0]['amount']['breakdown']['tax_total']['value'] = "10.00";

			// $orderDetails['purchase_units'][0]['amount']['breakdown']['item_total']['currency_code'] = 'USD';
			// $orderDetails['purchase_units'][0]['amount']['breakdown']['item_total']['value'] = "180.00";

			

			// $orderDetails['purchase_units'][0]['currency_code'] = 'USD';	

			$oDetails['amount']['currency_code'] = 'USD';
			$oDetails['amount']['value'] = $total_price; //'200.00';
			
			
			$oDetails['amount']['breakdown']['tax_total']['currency_code'] = 'USD';
			$oDetails['amount']['breakdown']['tax_total']['value'] = $tax_price; //"20.00";

			$oDetails['amount']['breakdown']['shipping']['currency_code'] = 'USD';
			$oDetails['amount']['breakdown']['shipping']['value'] = $shipping_charges;
				
			$oDetails['amount']['breakdown']['item_total']['currency_code'] = 'USD';
			$oDetails['amount']['breakdown']['item_total']['value'] = (float)$total_product_price; //$prod_price; //"180.00";

			$oDetails['shipping']['options'] = $shipping_info;
			
			
			curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://api-m.paypal.com/v2/checkout/orders/'.$orderId, 
			//CURLOPT_URL => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/'.$orderId,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'PATCH',
			CURLOPT_POSTFIELDS => json_encode([[
				'op' => 'replace',
				'path' => "/purchase_units/@reference_id=='default'",
				//'path' => "/purchase_units/@reference_id=='default'/amount",
				//'path' => "/purchase_units/@reference_id=='".$ref_id."'/amount",
				//'path' => "/purchase_units",
				'value' => $oDetails, //$orderDetails,
			]]),
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json',
				'Authorization: Bearer '.$accessToken
			),
			));

			$response = curl_exec($curl);

			curl_close($curl);
			return $response;
		}

		// if(!empty($orderDetails)){
		// 	// $orderDetails->purchase_units[0]->amount->breakdown->shipping->value = "20.00";
		// 	// $totalAmount = "200.00";
		// 	// $orderDetails->purchase_units[0]->amount->value = $totalAmount;
		// 	$naccessToken = $this->getPayPalAccessToken();
		// 	$orderDetails['purchase_units'][0]['amount']['breakdown']['shipping']['value'] = "20.00";
		// 	$orderDetails['purchase_units'][0]['amount']['value'] = "200.00";
		// 	$ch = curl_init();

		// 	$data = ['orderID' => $orderId];
		// 	curl_setopt($ch, CURLOPT_URL, "https://api-m.paypal.com/v2/checkout/orders/" . $orderId);
		// 	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
		// 	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		// 		'Content-Type: application/json',
		// 		'Authorization: Bearer ' . $naccessToken,
		// 	]);
		// 	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([[
		// 		'op' => 'replace',
		// 		'path' => "/purchase_units/@reference_id=='default'/amount",
		// 		'value' => $orderDetails,
		// 	]]));
		// 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// 	$response = curl_exec($ch);
		// 	curl_close($ch);

		// 	$responseData = json_decode($response, true);
		// 	return $responseData;
		// }
		
	}

	public function Success(Request $request)
	{
		$token = "";

		if (!empty($request->token))	
		{	
			Session::forget('PayPalToken');			
			Session::put('PayPalToken',$request->token);
			Session::save();
		}
		## token check end here 

		## if token not set then redired on shoppin cart
		if(!Session::has("PayPalToken") || empty(Session::get("PayPalToken")))
		{
			$ErrorLongMsg = "Error in Processing Request, Please try again.";
			Session::flash('CartError',$ErrorLongMsg);
			return redirect('shoppingcart/view');
		}
	

		$response = $this->provider->showOrderDetails($request->token);
		//echo "<pre>"; print_r($response); exit;

		if (isset($response['status']) && $response['status']=='APPROVED') {
			
			Session::put('PAYPAL_PAYER_ID',$response['payer']["payer_id"]);
			Session::save();
			
			$first_name = "";
			$last_name = '';
			if(isset($response["purchase_units"][0]['shipping']['name']['full_name']))
			{
				$name = explode(" ",trim($response["purchase_units"][0]['shipping']['name']['full_name']));
				$first_name = $name[0];
				if(isset($name[1]) && $name[1] != '') {
					$last_name = $name[1];
				} else {
					$last_name = '';
				}
			}

			## Billing Address Start Here
			$Billing = [];
			$Billing['bill_fname']	  		= $first_name;
			$Billing['bill_lname']	  		= $last_name;
			$Billing['bill_company']				= '';
			$Billing['bill_address1']	  		= $response["purchase_units"][0]['shipping']['address']['address_line_1'];

			if(isset($response["purchase_units"][0]['shipping']['address']['address_line_2'])) {
				$Billing['bill_address2'] 	= $response["purchase_units"][0]['shipping']['address']['address_line_2'];
			} else {
				$Billing['bill_address2']	= '';
			}

			$Billing['bill_city']	  		= (isset($response["purchase_units"][0]['shipping']['address']['admin_area_2'])?$response["purchase_units"][0]['shipping']['address']['admin_area_2']:'');
			$Billing['bill_country']		= $response["purchase_units"][0]['shipping']['address']['country_code'];
			if($response["purchase_units"][0]['shipping']['address']['country_code'] != 'US') {
				$Billing['bill_other_state'] 	= (isset($response["purchase_units"][0]['shipping']['address']['admin_area_1'])?$response["purchase_units"][0]['shipping']['address']['admin_area_1']:'');
			} else {
				$Billing['bill_state'] 	  		= (isset($response["purchase_units"][0]['shipping']['address']['admin_area_1'])?$response["purchase_units"][0]['shipping']['address']['admin_area_1']:'');;
			}
			$Billing['bill_zip']			= (isset($response["purchase_units"][0]['shipping']['address']['postal_code'])?$response["purchase_units"][0]['shipping']['address']['postal_code']:'');
			$Billing['bill_phone']	  		= '';
			$Billing['bill_email'] 	  		= $response['payer']['email_address'];
			$Billing['bill_cemail'] 	  	= $response['payer']['email_address'];
			$Billing['sameasbill'] 	  		= 'Yes';
			
			if(isset($request->dropsipflag) && $request->dropsipflag == 'dropship')
			{
				Session::put('tempShippingAdd1Val',$Billing);
				Session::put('tempBillingAdd1Val',$Billing);
				return redirect('paypal/dopayment/dropship');
			} else {
				$this->SetBillingAddress($Billing);			
				$this->SetShippingAddress($Billing);			
				return redirect('checkout/paypal');
			}
		}
		else	
		{
			$ErrorMsg = $response["error"]["message"];
			
			Session::flash('CartError',$ErrorMsg);	
			
			if(isset($request->dropsipflag) && $request->dropsipflag == 'dropship')
			{
				return redirect('/imported-order-list.html');
			} else {
				return redirect('shoppingcart/view');
			}
		}		
	}
	public function DoPayment(Request $request)
	{
		$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';
		$order_id = Session::get('ShoppingCart.OrderID');	
		
		if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID') > 0)
		{
			$order_id = Session::get('ShoppingCart.OrderID');
		}
		else if(isset($request->dropsipflag) && $request->dropsipflag != 'dropship' && $request->dropsipflag > 0)
		{
			$order_id = $request->dropsipflag;
		}
		
		$sessionDetails	= Session::get('ShoppingCart.BillingAddress');
		
		$customerEmail = '';
		if(isset($sessionDetails['email']) && $sessionDetails['email']!='')
		{
			$customerEmail	= $sessionDetails['email'];
		}
		
		$orderNo		= 'OR'.$order_id;
		$customerName 	= '';
		if((isset($sessionDetails['first_name']) && $sessionDetails['first_name']!=''))
		{
			$customerName	= $sessionDetails['first_name']." ";
		}
		
		if(isset($sessionDetails['last_name']) && $sessionDetails['last_name'])
		{
			$customerName = $customerName.$sessionDetails['last_name'];
		}
		
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData = date("m/d/Y H:i:s")." And ".$order_id." : Paypal DoPayment Function With Paypal Token2 In Paypal Controller.\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}			
		
		if(!Session::has("PayPalToken") && empty(Session::get("PayPalToken")))
		{
			$ErrorLongMsg = "Error in Processing Request, Please try again.";
			Session::flash('CartError',$ErrorLongMsg);
			if(isset($request->dropsipflag) && $request->dropsipflag == 'dropship')
			{
				
				$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';
			
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." : Paypal DoPayment Function With Paypal Token1 In Paypal Controller.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}			
				
				return redirect('imported-order-list.html');
			}else{
				$OrderFoundRes = Order::select('status','pay_status', 'payment_gateway_response')->where('orders_id','=',$order_id)->get();
				
				if($OrderFoundRes->count() > 0)
				{
					if(isset($OrderFoundRes[0]["status"]) && ($OrderFoundRes[0]["status"]=="Pending" || $OrderFoundRes[0]["status"]=="Completed") && isset($OrderFoundRes[0]["pay_status"]) && $OrderFoundRes[0]["pay_status"]=="Paid" && isset($OrderFoundRes[0]["payment_gateway_response"]) && $OrderFoundRes[0]["payment_gateway_response"]!='')
					{
						$ErrorLongMsg = "Invalid Transaction, please retry the payment process";
						Session::flash('CartError',$ErrorLongMsg);	
						return redirect('shoppingcart/view'); 
					}
				}
				$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';
			
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." : Paypal DoPayment Function With Paypal Token In Paypal Controller.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}								
				
				if(isset($order_id) && $order_id > 0)
				{
					$transaction_info = "This transaction has been Declined.";

					$updAray = array (
										'status' 	   				=> 'Declined',
										'transaction_info' 			=> $transaction_info
									  );
												
					$updOrder = Order::Where("orders_id","=",$order_id)
										->update($updAray);
										
					$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
					
					/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
					
					$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
				}
				return redirect('shoppingcart/view');
			}
		}
		
		if(isset($request->dropsipflag) && $request->dropsipflag == 'dropship')
		{
			
			$payerId = Session::get("PAYPAL_PAYER_ID");
			$token = Session::get("PayPalToken");
			
			$orderResponse = $this->provider->capturePaymentOrder($token);
			$payerID 	   = $payerId;
			
			
			$MainId = (isset($orderResponse['id'])) ? $orderResponse['id']: '';
			$Status = (isset($orderResponse["status"]))  ? $orderResponse["status"]: '';
			$payment_source = (isset($orderResponse["payment_source"]))  ? $orderResponse["payment_source"]: ''; 
			$purchase_units = (isset($orderResponse["purchase_units"]))  ? $orderResponse["purchase_units"]: ''; 
			$payer = (isset($orderResponse["payer"]))  ? $orderResponse["payer"]: '';
			
			$payment_response = $this->provider->capturePaymentOrder($token);
			
			if(isset($Status) && strtoupper($Status)=="COMPLETED"){
				
				$payment_gateway_response = "ID".$MainId."--Status".$Status."--".json_encode($payment_source)."---".json_encode($purchase_units)."---".json_encode($payer);
				
				$currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');
				$transaction_info = "This transaction has been approved.";

				$UpdateOrderNoArr =  Session::get("UpdateOrderNoArr");
				
				if(count($UpdateOrderNoArr) > 0)
				{
					for($i=0; $i<count($UpdateOrderNoArr);$i++)
					{
						$dropshipper_order_res = DropshipperOrder::where('orders_id','=',$UpdateOrderNoArr[$i])->get();
						$TotalOrder = $dropshipper_order_res->count();
						if($TotalOrder > 0)
						{
							$ShippingMethodRS = ShippingMode::where('shipping_mode_id','=',$dropshipper_order_res[0]["shippingModeId"])->where('status','=','1')->get();

							$fullShippingname = '';
							$shippingDays = $dropshipper_order_res[0]["shipping_days"];
							//$estimateShipDate = getShipmentEstimateDate($shippingDays);
							$estimateShipDate = "";
							if($dropshipper_order_res[0]["shipping_amt"] > 0)
							{
								$fullShippingname =  $ShippingMethodRS[0]['type']. " <b>(".Session::get('currency_symbol').$dropshipper_order_res[0]["shipping_amt"].")</b> ".$estimateShipDate;
							}
							else
							{
								$fullShippingname =  $ShippingMethodRS[0]['type']. " <b>(Free)</b> ".$estimateShipDate;
							}

							$OrderInsert = array (
								'customer_id'				=> Session::get('sess_icustomerid'),
								'dropshipper_order_no'	 	=> $dropshipper_order_res[0]["orders_no"],
								'sub_total' 				=> $dropshipper_order_res[0]["sub_total"],
								'shipping_amt' 				=> $dropshipper_order_res[0]["shipping_amt"],
								'tax' 						=> $dropshipper_order_res[0]["tax"],
								'gift_charge' 				=> $dropshipper_order_res[0]['gift_charge'],
								'gift_message' 				=> '',
								'is_gift_order'				=> 'No',
								'handling_charge' 			=> '0.00',
								'wire_discount' 			=> '0.00',
								'auto_discount' 			=> 0,
								'quantity_discount'			=> 0,
								'reward_discount'			=> 0,
								'coupon_amount' 			=> 0,
								'coupon_id' 				=> $dropshipper_order_res[0]["coupon_id"],
								'Second_coupon_id'			=> $dropshipper_order_res[0]["Second_coupon_id"],
								'coupon_code' 				=> $dropshipper_order_res[0]["coupon_code"],
								'gc_amount' 				=> $dropshipper_order_res[0]["gc_amount"],
								'gc_code' 					=> $dropshipper_order_res[0]["gc_code"],
								'refer_id'					=> $dropshipper_order_res[0]["refer_id"],
								'refer_amount' 				=> $dropshipper_order_res[0]["refer_amount"],
								'order_total' 				=> $dropshipper_order_res[0]["order_total"],
								'shipinfo' 					=> $ShippingMethodRS[0]['type'],
								'payment_type' 				=> "PAYMENT_PAYPALEC",
								'payment_method' 			=> "Paypal Express Checkout",
								'pay_status' 				=> 'Paid',
								'ccinfo' 					=> '',
								'customer_comment' 			=> $dropshipper_order_res[0]['customer_comment'],
								'status'					=> 'Pending',
								'currency_info'				=> $currency_info,
								'checkout_type' 			=> Session::get('etype'),
								'user_type' 				=> Session::get('eusertype'),
								'ilevelid' 					=> '0',
								'level_price' 				=> $dropshipper_order_res[0]['level_price'],
								'ship_first_name' 			=> $dropshipper_order_res[0]['ship_first_name'],
								'ship_last_name' 			=> $dropshipper_order_res[0]['ship_last_name'],
								'ship_company' 				=> $dropshipper_order_res[0]['ship_company'],
								'ship_email' 				=> $dropshipper_order_res[0]['ship_email'],
								'ship_address1' 			=> $dropshipper_order_res[0]['ship_address1'],
								'ship_address2' 			=> $dropshipper_order_res[0]['ship_address2'],
								'ship_city' 				=> $dropshipper_order_res[0]['ship_city'],
								'ship_zip' 					=> $dropshipper_order_res[0]['ship_zip'],
								'ship_state' 				=> $dropshipper_order_res[0]['ship_state'],
								'ship_country' 				=> $dropshipper_order_res[0]['ship_country'],
								'ship_phone' 				=> $dropshipper_order_res[0]['ship_phone'],
								'bill_first_name' 			=> $dropshipper_order_res[0]['bill_first_name'],
								'bill_last_name' 			=> $dropshipper_order_res[0]['bill_last_name'],
								'bill_company' 				=> $dropshipper_order_res[0]['bill_company'],
								'bill_email' 				=> $dropshipper_order_res[0]['bill_email'],
								'bill_address1' 			=> $dropshipper_order_res[0]['bill_address1'],
								'bill_address2' 			=> $dropshipper_order_res[0]['bill_address2'],
								'bill_city' 				=> $dropshipper_order_res[0]['bill_city'],
								'bill_zip' 					=> $dropshipper_order_res[0]['bill_zip'],
								'bill_state' 				=> $dropshipper_order_res[0]['bill_state'],
								'bill_country' 				=> $dropshipper_order_res[0]['bill_country'],
								'bill_phone' 				=> $dropshipper_order_res[0]['bill_phone'],
								'customer_ip' 				=> $_SERVER['REMOTE_ADDR'],
								'customer_browser' 			=> $_SERVER['HTTP_USER_AGENT'],
								'is_only_gc'				=> $dropshipper_order_res[0]['is_only_gc'],
								'free_gift'					=> $dropshipper_order_res[0]['free_gift'],
								'gift_from'					=> $dropshipper_order_res[0]['gift_from'],
								'gift_to'					=> $dropshipper_order_res[0]['gift_to'],
								'gift_message_customer'		=> $dropshipper_order_res[0]['gift_message_customer'],
								'cust_current_credit_limit' => $dropshipper_order_res[0]['cust_current_credit_limit'],
								'apply_credit'          	=> $dropshipper_order_res[0]['apply_credit'],
								'remaining_credit'      	=> $dropshipper_order_res[0]['remaining_credit'],
								'use_credit_limit'      	=> $dropshipper_order_res[0]['use_credit_limit'],
								'is_dropship_order'     	=> $dropshipper_order_res[0]['is_dropship_order'],
								'shipping_signature'	 	=> $dropshipper_order_res[0]['shipping_signature'],
								'Is_GiftCertificatPurchase' => $dropshipper_order_res[0]['Is_GiftCertificatPurchase'],
								'order_come_from'			=> "Dropshipper",
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' => $payment_gateway_response,
								'paypal_payer_id' 	   		=> $payerID,
								'paypal_transaction_id' 	=> $MainId,
								'paypal_transaction_status' => $Status,
								'paypal_transaction_date' 	=> '',
								'fullshipping_info'			=> $fullShippingname
							);
							$NewOrder = Order::create($OrderInsert) ;
							$OrderID = $NewOrder->orders_id;

							if($OrderID > 0)
							{
								$updateOrder = array ('orders_no' => "OR".$OrderID );
								$udpRefer = Order::where('orders_id','=',$OrderID)->update($updateOrder);
								$dropshipperOrderDetails = DropshipperOrderDetail::where('orders_id','=',$dropshipper_order_res[0]['orders_id'])->get();
								$totalOrdersDetails = $dropshipperOrderDetails->count();

								if($totalOrdersDetails > 0)
								{
									for($j=0;$j<$totalOrdersDetails;$j++)
									{
										$OrderDetailInsert = array (
											'orders_id'					=> $OrderID,
											'orders_no'					=> "OR".$OrderID,
											'products_id'				=> $dropshipperOrderDetails[$j]["products_id"],
											'sku' 						=> $dropshipperOrderDetails[$j]['sku'],
											'product_name'				=> $dropshipperOrderDetails[$j]['product_name'],
											'quantity' 					=> $dropshipperOrderDetails[$j]['quantity'],
											'price' 					=> $dropshipperOrderDetails[$j]['price'],
											'total' 					=> $dropshipperOrderDetails[$j]['total'],
											'status' 					=> '1',
											'item_price' 				=> $dropshipperOrderDetails[$j]['item_price'],
											'excluded_flag'  			=> $dropshipperOrderDetails[$j]['excluded_flag'],
											'is_gift_wrap'				=> $dropshipperOrderDetails[$j]['is_gift_wrap'],
											'is_free_gift_products' 	=> $dropshipperOrderDetails[$j]['is_free_gift_products'],
											'VendorSKU'					=> $dropshipperOrderDetails[$j]['VendorSKU'],
											'IsCosmo'					=> $dropshipperOrderDetails[$j]['IsCosmo'],
											'IsNandansons'  			=> $dropshipperOrderDetails[$j]['IsNandansons'],
											'IsPerfumePW'				=> $dropshipperOrderDetails[$j]['IsPerfumePW'],
											'coupon_itemwise_discount' 	=> $dropshipperOrderDetails[$j]['coupon_itemwise_discount']
										);
										$OrderDetailID = OrderDetail::create($OrderDetailInsert) ;

										$ProductSt = Products::select('current_stock','cosmo_current_stock','cosmo_sku','nandansons_sku','nandansons_current_stock','perfumeworldwide_sku','perfumeworldwide_currentstock')
														->where('status','=','1')->where('sku','=',$dropshipperOrderDetails[$j]['sku'])->get();
										
										$TotalProductCnt = $ProductSt->count();
										$new_stock = 0;

										if($TotalProductCnt > 0)
										{
											if($ProductSt[0]["current_stock"]>$dropshipperOrderDetails[$j]['quantity'])
											{
												$new_stock = $ProductSt[0]["current_stock"]-$dropshipperOrderDetails[$j]['quantity'];
											}
											else if($dropshipperOrderDetails[$j]['quantity']>$ProductSt[0]["current_stock"])
											{
												$new_stock = $dropshipperOrderDetails[$j]['quantity']-$ProductSt[0]["current_stock"];
											}
											if($new_stock<=0)
											{
												$new_stock=0;
											}

											$UpdateStock   = array ('current_stock' => $new_stock);
											Products::where('sku','=',$dropshipperOrderDetails[$j]['sku'])->update($UpdateStock);
										}
									}
								}
								DropshipperOrderDetail::where('orders_id','=',$dropshipper_order_res[0]['orders_id'])->delete();
								DropshipperOrder::where('orders_id','=',$dropshipper_order_res[0]['orders_id'])->where('customer_id','=',Session::get('sess_icustomerid'))->delete();
							}
						}
					}
				}
				
				$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';
			
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." : Paypal DoPayment Function With Order History In Paypal Controller.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}			
				
				return redirect('/order-history.html');
			}
			else
			{
				$ErrorLongMsg = 'Please check details, try again';
				Session::flash('CartError',$ErrorLongMsg);
				return redirect('/imported-order-list.html');
			}
		} 
		else {
			$data = [];
			
			## Gather the information to make the final call to finalize the PayPal payment.  		
			$tempBillingAdd  = Session::get('ShoppingCart.BillingAddress');
			$tempShippingAdd = Session::get('ShoppingCart.ShippingAddress');
			$OrderSubTotal 	 =  Session::get('ShoppingCart.SubTotal');
			$GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
			$GiftValue = 0;
			if($GiftCouponInfo && count($GiftCouponInfo) > 0)
			{
				$GiftValue = $GiftCouponInfo['Value'];
			}
			## For the All Type Of Discounts Start Here
			$NetDiscount 	 = $this->GetAllDiscounts();
			
			//$TotalDiscount 	 = NumberFormat($NetDiscount['TotalDiscount'] + $GiftValue);
			$TotalDiscount 	 = NumberFormat($NetDiscount['TotalDiscount']);
			## For the all types of discounts end here
			$OrderSubTotal 	 = NumberFormat($OrderSubTotal);

			$total_charges = $this->GetAllCharges();
			
			$shipping=array();
			
			$shipping['op'] 				= 'replace';
			$shipping['path'] 				= "/purchase_units/@reference_id=='default'/shipping/address";
			$shipping['value']['full_name'] 			= $tempShippingAdd['first_name']." ".$tempShippingAdd['last_name'];
			$shipping['value']['address_line_1'] 	= $tempShippingAdd["address1"];
			
			if(isset($tempShippingAdd["address2"]) && $tempShippingAdd["address2"]!='')
			{
				$shipping['value']['address_line_2'] 	= $tempShippingAdd["address2"];
			}
			
			$shipping['value']['admin_area_2'] 		= $tempShippingAdd["city"];
			$shipping['value']['country_code'] 		= $tempShippingAdd["country"];
			$shipping['value']['admin_area_1'] 		= $tempShippingAdd["state"];
			$shipping['value']['postal_code'] 		= $tempShippingAdd["zip"];
			
			
			$ShopCart = Session::get('ShoppingCart.Cart');
			$ItemsArr = array();
			$ItemsArr['op'] = 'replace';
			$ItemsArr['path'] = "/purchase_units/@reference_id=='default'/items";
			foreach($ShopCart as $key => $CartItem)
			{
				if(isset($CartItem['IS_Free_Gift']) && $CartItem['IS_Free_Gift'] == 'Yes')
				{	
					$ItemPrice = $CartItem['TotPrice'];
				} else if(isset($CartItem['Is_Free_Sample']) && $CartItem['Is_Free_Sample'] == 'Yes'){
					$ItemPrice = $CartItem['TotPrice'];
				} else{ 
					$ItemPrice = $CartItem['ItemPrice'];
				}
				$unit_amountInfo = array();
				$unit_amountInfo["currency_code"] = "USD";
				$unit_amountInfo["value"] =  $ItemPrice;
				
				
				$ItemsDetails[] = array(
							'name' 			=> $CartItem['ProductName'],
							'sku'			=> $CartItem['SKU'],
							'unit_amount'	=> $unit_amountInfo,
							'quantity' 		=> $CartItem['Qty']
							);
			}
			$ItemsArr['value'] = $ItemsDetails;
		
			$data['op'] = 'replace';
			$data['path'] = "/purchase_units/@reference_id=='default'/amount";
			$data['value']['currency_code'] = "USD";
			$data['value']['value'] = NumberFormat($this->GetNetTotal());
			
			$ShippingSignature = 0;
			if(!empty($this->GetAllCharges('GiftWrappingCharge')) && $this->GetAllCharges('GiftWrappingCharge') > 0)
			{
				$ShippingSignature = $this->GetAllCharges('GiftWrappingCharge');
			}
			
			if(!empty($this->GetAllCharges('ShippingSignature')) && $this->GetAllCharges('ShippingSignature') > 0)
			{
				$ShippingSignature = $ShippingSignature + $this->GetAllCharges('ShippingSignature');
			}
			
			
			
			$data['value']['breakdown']["item_total"]["currency_code"] = "USD";
			$data['value']['breakdown']["item_total"]["value"] =  NumberFormat($OrderSubTotal);
			if(!empty($this->GetAllCharges('Tax')) &&  $this->GetAllCharges('Tax')>0)
			{
				$data['value']['breakdown']['tax_total']['currency_code'] = "USD";
				$data['value']['breakdown']['tax_total']['value'] = $this->GetAllCharges('Tax');
			}	
			if(!empty($this->GetAllCharges('ShippingCharge')) &&  $this->GetAllCharges('ShippingCharge')>0)
			{
				$data['value']['breakdown']['shipping']['currency_code'] = "USD";
				$data['value']['breakdown']['shipping']['value'] = $this->GetAllCharges('ShippingCharge');
			}	
			if(!empty($this->GetAllCharges('ShippingInsurance')) &&  $this->GetAllCharges('ShippingInsurance')>0)
			{
				$data['value']['breakdown']['insurance']['currency_code'] = "USD";
				$data['value']['breakdown']['insurance']['value'] = $this->GetAllCharges('ShippingInsurance');
			}
			if(!empty($ShippingSignature) &&  $ShippingSignature > 0)
			{
				$data['value']['breakdown']['handling']['currency_code'] = "USD";
				$data['value']['breakdown']['handling']['value'] = NumberFormat($ShippingSignature);
			}
			if(!empty($TotalDiscount) &&  $TotalDiscount>0)
			{
				$data['value']['breakdown']['discount']['currency_code'] = "USD";
				$data['value']['breakdown']['discount']['value'] = NumberFormat($TotalDiscount);
			}
			$data1[] = $shipping;
			$data1[] = $data;
			$data1[] = $ItemsArr;
			
			$payerId = Session::get("PAYPAL_PAYER_ID");
			$token = Session::get("PayPalToken");
			
			
			
			
			$payment_response = $this->provider->updateOrder($token,$data1);
			
		
			
			if(isset($payment_response['error']) && $payment_response['error']!='')
			{
				
				
				$transaction_info = "This transaction has been Declined.";


				$updAray = array (
									'status' 	   				=> 'Declined',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_response['error']
								  );
											
									
											
				$updOrder = Order::Where("orders_id","=",$order_id)
									->update($updAray);
				
				$payment_responseMSg = json_decode($payment_response['error'],true);
				
		
				$ErrorLongMsg = (isset($payment_responseMSg['details'][0]['description'])) ? $payment_responseMSg['details'][0]['description'] : 'Please check details and try again';
				Session::flash('CartError',$ErrorLongMsg);
				
				$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);	
			
				$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';
			
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." : Paypal DoPayment Function With Shoppingcart In Paypal Controller.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}			
											
				return redirect('shoppingcart/view');	
			}
			else
			{
				$orderResponse = $this->provider->capturePaymentOrder($token);
				$payerID 	   		= $payerId;
				
				
				$transaction_info = "This transaction has been approved.";
				
				$MainId = (isset($orderResponse['id'])) ? $orderResponse['id']: '';
				$Status = (isset($orderResponse["status"]))  ? $orderResponse["status"]: '';
				$payment_source = (isset($orderResponse["payment_source"]))  ? $orderResponse["payment_source"]: ''; 
				$purchase_units = (isset($orderResponse["purchase_units"]))  ? $orderResponse["purchase_units"]: ''; 
				$payer = (isset($orderResponse["payer"]))  ? $orderResponse["payer"]: '';
				
				if(isset($Status) && strtoupper($Status)=="COMPLETED")
				{
				
					$payment_gateway_response = "ID".$MainId."--Status".$Status."--".json_encode($payment_source)."---".json_encode($purchase_units)."---".json_encode($payer);
					
					$updAray = array (
										'status'					=> 'Pending',
										'pay_status' 	   			=> 'Paid',
										'transaction_info' 			=> $transaction_info,
										'payment_gateway_response' 	=> $payment_gateway_response
									  );

					$updOrder = Order::Where("orders_id","=",$order_id)
										->update($updAray);	
										
										
					$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';
				
					if(fopen($myFile, 'a+'))
					{
						$fh = fopen($myFile, 'a+');

						$stringData = date("m/d/Y H:i:s")." And ".$order_id." And ".serialize(Session::get('ShoppingCart')). ": Paypal DoPayment Function In Paypal Controller.\n";
						fwrite($fh, $stringData);
						fclose($fh);
					}								
					return redirect('order-receipt');
				}
				else
				{
						
				$payerID = $payerId;
				
				
				$transaction_info = "This transaction has been Declined.";
				
			
				$payment_gateway_response = json_encode($orderResponse);

				$updAray = array (
									'status' 	   				=> 'Declined',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_gateway_response
								  );
											
				//$order_id = Session::get('ShoppingCart.OrderID');
				$updOrder = Order::Where("orders_id","=",$order_id)
									->update($updAray);
				$ErrorLongMsg = 'Please check details, try again';
				Session::flash('CartError',$ErrorLongMsg);
				
				$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);	
				
				/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
				
				$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
				
				$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/PaypalLog.txt';
			
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." : Paypal DoPayment Function With Shoppingcart In Paypal Controller.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}			
											
				return redirect('shoppingcart/view');
					
				}
			}
			
			
	}		
			
	}
	
	public function PhoneOrder(Request $request)
	{	
		if(!Session::has('phoneorder_detail.order_id'))
		{
			return redirect(config('global.SITE_URL'));
		}
		$OrderID = Session::get('phoneorder_detail.order_id');
		
		$OrderRs = DB::table('pu_orders as o')
								->join('pu_order_detail as ord','ord.orders_id','=','o.orders_id')
								->select('o.orders_id', 'o.orders_no','o.customer_id', 'o.order_total', 'o.bill_email','o.bill_first_name','o.bill_last_name','o.bill_company','o.bill_email','o.bill_address1','o.bill_address2','o.bill_city','o.bill_zip','o.bill_state','o.bill_country','o.bill_phone','o.ship_first_name','o.ship_last_name','o.ship_company','o.ship_email','o.ship_address1','o.ship_address2','o.ship_city','o.ship_zip','o.ship_state','o.ship_country','o.ship_phone','o.sub_total','o.auto_discount','o.quantity_discount','o.reward_discount','o.coupon_amount','o.gc_amount','o.refer_amount','o.apply_credit','ord.price','ord.quantity','ord.sku','ord.product_name','ord.total','o.shipping_amt','o.tax','o.shipping_signature','o.route_shipping_insurance_charge','o.gift_charge')
								->where('o.orders_id', '=', $OrderID)
								->get();
		
		$order_total = $OrderRs[0]->order_total;
		$OrderSubTotal 	 =  NumberFormat($OrderRs[0]->sub_total);
		
		$OtherCharges = NumberFormat($OrderRs[0]->shipping_amt + $OrderRs[0]->tax+$OrderRs[0]->shipping_signature+$OrderRs[0]->route_shipping_insurance_charge+$OrderRs[0]->gift_charge);
		
		$TotalDiscount 	 = NumberFormat($OrderRs[0]->auto_discount + $OrderRs[0]->quantity_discount+$OrderRs[0]->reward_discount+$OrderRs[0]->coupon_amount+$OrderRs[0]->gc_amount+$OrderRs[0]->refer_amount+$OrderRs[0]->apply_credit);
		// $OrderSubTotal 	 = NumberFormat($OrderSubTotal - $TotalDiscount);
		$order_total 	 = NumberFormat($order_total);
		
		
		$data = [];
		
		foreach($OrderRs as $key => $CartItem)
		{
			
			$unit_amountInfo = array();
			$unit_amountInfo["currency_code"] = "USD";
			$unit_amountInfo["value"] = $CartItem->price;
			
			$ItemsArr[] = array(
						'name' 			=> $CartItem->product_name,
						'sku'			=> $CartItem->sku,
						'unit_amount'	=> $unit_amountInfo,
						'quantity' 		=> $CartItem->quantity
						);
		}
		
		$data['intent'] = 'CAPTURE';
		$data["application_context"]['return_url'] = url('/paypal/success_phoneorder');
		$data["application_context"]['cancel_url'] = url('/paypal/cancel_phoneorder');
	
		
		$AmountArr = array();
		if($OrderSubTotal > 0)
		{
			$AmountArr["currency_code"] = "USD";
			$AmountArr["value"] = $order_total;
			$AmountArr["breakdown"]["item_total"]["currency_code"] = "USD";
			$AmountArr["breakdown"]["item_total"]["value"] = $OrderSubTotal;
			$ShippingSignature = 0;
			if(!empty($OrderRs[0]->gift_charge) && $OrderRs[0]->gift_charge > 0)
			{
				$ShippingSignature = $OrderRs[0]->gift_charge;
			}
			
			if(!empty($OrderRs[0]->shipping_signature) && $OrderRs[0]->shipping_signature > 0)
			{
				$ShippingSignature = $ShippingSignature + $OrderRs[0]->shipping_signature;
			}
			
			
			
			if(!empty($OrderRs[0]->shipping_amt) && $OrderRs[0]->shipping_amt>0)
			{
				$AmountArr['breakdown']['shipping']['currency_code'] = "USD";
				$AmountArr['breakdown']['shipping']['value'] = NumberFormat($OrderRs[0]->shipping_amt);
			}
			if(!empty($OrderRs[0]->route_shipping_insurance_charge) &&  $OrderRs[0]->route_shipping_insurance_charge>0)
			{
				$AmountArr['breakdown']['insurance']['currency_code'] = "USD";
				$AmountArr['breakdown']['insurance']['value'] = NumberFormat($OrderRs[0]->route_shipping_insurance_charge);
			}
			if(!empty($ShippingSignature) && $ShippingSignature>0)
			{
				$AmountArr['breakdown']['handling']['currency_code'] = "USD";
				$AmountArr['breakdown']['handling']['value'] = NumberFormat($ShippingSignature);
			}
			if(!empty($OrderRs[0]->tax) &&  $OrderRs[0]->tax>0)
			{
				$AmountArr['breakdown']['tax_total']['currency_code'] = "USD";
				$AmountArr['breakdown']['tax_total']['value'] =  NumberFormat($OrderRs[0]->tax);
			}
			
			if(!empty($TotalDiscount) &&  $TotalDiscount>0)
			{
				$AmountArr["breakdown"]['discount']['currency_code'] = "USD";
				$AmountArr["breakdown"]['discount']['value'] = NumberFormat($TotalDiscount);
			}	
			
		}
		
		
		$data["purchase_units"][0]["amount"] = $AmountArr;
		$data["purchase_units"][0]["items"] = $ItemsArr;
			
		$response = $this->provider->createOrder($data);
		
		//echo "<pre>"; print_r($data); exit;
		
		$UpdateOrderInformation = [
					'status'			=> 'Pending - PhoneOrder',
					'payment_type' 		=> 'PAYMENT_PAYPALEC',
					'payment_method' 	=> 'Paypal Express Checkout'
				]; 
		$Order = Order::where('orders_id','=',$OrderID)->update($UpdateOrderInformation);
		
		
		if(isset($response["id"]) && $response["id"]!='')
		    {
				foreach($response["links"] as $link)
				{
					if($link['rel']=="approve")
					{
						return redirect()->away($link['href']);
					}	
				}
			}
			else
			{
				$ErrorMsg = $response["error"]["message"];
			
				Session::flash('error',$ErrorMsg);								
				return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
			}
		
		
			
	}

	public function Success_Phoneorder(Request $request)
	{
		$OrderID = Session::get('phoneorder_detail.order_id');
		$token = "";

		if (!empty($request->token))	
		{	
			Session::forget('phoneorder_detail.Paypal.PayPalToken');			
			Session::put('phoneorder_detail.Paypal.PayPalToken',$request->token);
			Session::save();
		}
		## token check end here 

		## if token not set then redired on shoppin cart
		if(!Session::has("phoneorder_detail.Paypal.PayPalToken") || empty(Session::get("phoneorder_detail.Paypal.PayPalToken")))
		{
			$ErrorLongMsg = "Error in Processing Request, Please try again.";
			Session::flash('error',$ErrorLongMsg);
			return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
		}
		
		$OrderRs = DB::table('pu_orders as o')
									->join('pu_order_detail as ord','ord.orders_id','=','o.orders_id')
									->select('o.orders_id', 'o.orders_no','o.customer_id', 'o.order_total', 'o.bill_email','o.bill_first_name','o.bill_last_name','o.bill_company','o.bill_email','o.bill_address1','o.bill_address2','o.bill_city','o.bill_zip','o.bill_state','o.bill_country','o.bill_phone','o.ship_first_name','o.ship_last_name','o.ship_company','o.ship_email','o.ship_address1','o.ship_address2','o.ship_city','o.ship_zip','o.ship_state','o.ship_country','o.ship_phone','o.sub_total','o.auto_discount','o.quantity_discount','o.reward_discount','o.coupon_amount','o.gc_amount','o.refer_amount','o.apply_credit','ord.price','ord.quantity','ord.sku','ord.product_name','ord.total','o.shipping_amt','o.tax','o.shipping_signature','o.route_shipping_insurance_charge','o.gift_charge')
									->where('o.orders_id', '=', $OrderID)
									->get();
			
			$order_total = $OrderRs[0]->order_total;
			$OrderSubTotal 	 =  $OrderRs[0]->sub_total;
			
			$OtherCharges = NumberFormat($OrderRs[0]->shipping_amt + $OrderRs[0]->tax+$OrderRs[0]->shipping_signature+$OrderRs[0]->route_shipping_insurance_charge+$OrderRs[0]->gift_charge);
			
			$TotalDiscount 	 = NumberFormat($OrderRs[0]->auto_discount + $OrderRs[0]->quantity_discount+$OrderRs[0]->reward_discount+$OrderRs[0]->coupon_amount+$OrderRs[0]->gc_amount+$OrderRs[0]->refer_amount+$OrderRs[0]->apply_credit);
			// $OrderSubTotal 	 = NumberFormat($OrderSubTotal - $TotalDiscount);
			$order_total 	 = NumberFormat($order_total);
		
			$shipping=array();
			$data=array();
			
			$shipping['op'] 				= 'replace';
			$shipping['path'] 				= "/purchase_units/@reference_id=='default'/shipping/address";
			$shipping['value']['full_name'] 			= $OrderRs[0]->ship_first_name." ".$OrderRs[0]->ship_last_name;
			$shipping['value']['address_line_1'] 	= $OrderRs[0]->ship_address1;
			
			if(isset($OrderRs[0]->ship_address2) && $OrderRs[0]->ship_address2!='')
			{
				$shipping['value']['address_line_2'] 	= $OrderRs[0]->ship_address2;
			}
			
			$shipping['value']['admin_area_2'] 		= $OrderRs[0]->ship_city;
			$shipping['value']['country_code'] 		= $OrderRs[0]->ship_country;
			$shipping['value']['admin_area_1'] 		= $OrderRs[0]->ship_state;
			$shipping['value']['postal_code'] 		= $OrderRs[0]->ship_zip;
			
			$ItemsArr = array();
			$ItemsArr['op'] = 'replace';
			$ItemsArr['path'] = "/purchase_units/@reference_id=='default'/items";
			foreach($OrderRs as $key => $CartItem)
			{
				$unit_amountInfo = array();
				$unit_amountInfo["currency_code"] = "USD";
				$unit_amountInfo["value"] =  $CartItem->price;
				
				
				$ItemsDetails[] = array(
							'name' 			=> $CartItem->product_name,
							'sku'			=> $CartItem->sku,
							'unit_amount'	=> $unit_amountInfo,
							'quantity' 		=> $CartItem->quantity
							);
			}
			$ItemsArr['value'] = $ItemsDetails;
		
			$data['op'] = 'replace';
			$data['path'] = "/purchase_units/@reference_id=='default'/amount";
			$data['value']['currency_code'] = "USD";
			$data['value']['value'] = NumberFormat($order_total);
			
			
			$data['value']['breakdown']["item_total"]["currency_code"] = "USD";
			$data['value']['breakdown']["item_total"]["value"] =  NumberFormat($OrderSubTotal);
			
			
			$ShippingSignature = 0;
			if(!empty($OrderRs[0]->gift_charge) && $OrderRs[0]->gift_charge > 0)
			{
				$ShippingSignature = $OrderRs[0]->gift_charge;
			}
			
			if(!empty($OrderRs[0]->shipping_signature) && $OrderRs[0]->shipping_signature > 0)
			{
				$ShippingSignature = $ShippingSignature + $OrderRs[0]->shipping_signature;
			}
			
			
			if(!empty($OrderRs[0]->tax) &&  $OrderRs[0]->tax > 0)
			{
				$data['value']['breakdown']['tax_total']['currency_code'] = "USD";
				$data['value']['breakdown']['tax_total']['value'] =  NumberFormat($OrderRs[0]->tax);
			}	
			if(!empty($OrderRs[0]->shipping_amt) &&  $OrderRs[0]->shipping_amt>0)
			{
				$data['value']['breakdown']['shipping']['currency_code'] = "USD";
				$data['value']['breakdown']['shipping']['value'] = NumberFormat($OrderRs[0]->shipping_amt);
			}	
			if(!empty($OrderRs[0]->route_shipping_insurance_charge) &&  $OrderRs[0]->route_shipping_insurance_charge>0)
			{
				$data['value']['breakdown']['insurance']['currency_code'] = "USD";
				$data['value']['breakdown']['insurance']['value'] = NumberFormat($OrderRs[0]->route_shipping_insurance_charge);
			}
			if(!empty($ShippingSignature) &&  $ShippingSignature > 0)
			{
				$data['value']['breakdown']['handling']['currency_code'] = "USD";
				$data['value']['breakdown']['handling']['value'] = NumberFormat($ShippingSignature);
			}
			if(!empty($TotalDiscount) &&  $TotalDiscount>0)
			{
				$data['value']['breakdown']['discount']['currency_code'] = "USD";
				$data['value']['breakdown']['discount']['value'] = NumberFormat($TotalDiscount);
			}
			$data1[] = $shipping;
			$data1[] = $data;
			$data1[] = $ItemsArr;
			
			
			
			
			
		$payment_response = $this->provider->updateOrder($request->token,$data1);
		
		
		// dd($response);
		
		$MainId = (isset($orderResponse['id'])) ? $orderResponse['id']: '';
		$Status = (isset($orderResponse["status"]))  ? $orderResponse["status"]: '';
		$payment_source = (isset($orderResponse["payment_source"]))  ? $orderResponse["payment_source"]: ''; 
		$purchase_units = (isset($orderResponse["purchase_units"]))  ? $orderResponse["purchase_units"]: ''; 
		$payer = (isset($orderResponse["payer"]))  ? $orderResponse["payer"]: '';
				
	
			if(isset($payment_response['error']) && $payment_response['error']!='')
			{
				
				$transaction_info = "This transaction has been Declined.";


				$updAray = array (
									'status' 	   				=> 'Declined',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_response['error']
								  );
											
									
											
				$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
				
				$payment_responseMSg = json_decode($payment_response['error'],true);
				
		
				$ErrorLongMsg = (isset($payment_responseMSg['details'][0]['description'])) ? $payment_responseMSg['details'][0]['description'] : 'Please check details and try again';
				
				Session::flash('error',$ErrorLongMsg);
				
				$customerEmail = '';
				if(isset($OrderRs[0]->bill_email) && $OrderRs[0]->bill_email!='')
				{
					$customerEmail	= $OrderRs[0]->bill_email;
				}
				
				$orderNo		= $OrderRs[0]->orders_no;
				$customerName 	= '';
				if((isset($OrderRs[0]->ship_first_name) && $OrderRs[0]->ship_first_name!=''))
				{
					$customerName	= $OrderRs[0]->ship_first_name." ";
				}
				
				if(isset($OrderRs[0]->ship_last_name) && $OrderRs[0]->ship_last_name)
				{
					$customerName = $customerName.$OrderRs[0]->ship_last_name;
				}
				
				$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
								
				return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
			}
			
			else
			{
				$orderResponse = $this->provider->capturePaymentOrder($request->token);
				
				$transaction_info = "This transaction has been approved.";
				
				$MainId = (isset($orderResponse['id'])) ? $orderResponse['id']: '';
				$Status = (isset($orderResponse["status"]))  ? $orderResponse["status"]: '';
				$payment_source = (isset($orderResponse["payment_source"]))  ? $orderResponse["payment_source"]: ''; 
				$purchase_units = (isset($orderResponse["purchase_units"]))  ? $orderResponse["purchase_units"]: ''; 
				$payer = (isset($orderResponse["payer"]))  ? $orderResponse["payer"]: '';
				
				if(isset($Status) && strtoupper($Status)=="COMPLETED")
				{
				
					$payment_gateway_response = "ID".$MainId."--Status".$Status."--".json_encode($payment_source)."---".json_encode($purchase_units)."---".json_encode($payer);
					
					$updAray = array (
						'status' 	   				=> 'Pending',
						'pay_status' 	   			=> 'Paid',
						'transaction_info' 			=> $transaction_info,
						'payment_gateway_response' 	=> $payment_gateway_response,
						'phoneorder_paymentdate' => date("Y-m-d H:i:s")
				);

				$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);				
				
				############################# Complete Other Related processes Start #########################################
				$response_arr = $this->PhoneorderPaymentSuccess('Paypal');
				if($response_arr['success'] == "1"){
					Session::flash('success',$response_arr['err_msg']);
				}else{
					Session::flash('error',$response_arr['err_msg']);
				}	
				
				############################# Complete Other Related processes End #########################################
				return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
				}
				else
				{
				
				$payment_gateway_response = json_encode($orderResponse);	
				$transaction_info = "This transaction has been Declined.";

				$updAray = array (
									'status' 	   				=> 'Declined',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_gateway_response
								  );
											
				$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
				$ErrorLongMsg = 'Please check details, try again';
				Session::flash('error',$ErrorLongMsg);
				
				$customerEmail = '';
				if(isset($OrderRs[0]->bill_email) && $OrderRs[0]->bill_email!='')
				{
					$customerEmail	= $OrderRs[0]->bill_email;
				}
				
				$orderNo		= $OrderRs[0]->orders_no;
				$customerName 	= '';
				if((isset($OrderRs[0]->ship_first_name) && $OrderRs[0]->ship_first_name!=''))
				{
					$customerName	= $OrderRs[0]->ship_first_name." ";
				}
				
				if(isset($OrderRs[0]->ship_last_name) && $OrderRs[0]->ship_last_name)
				{
					$customerName = $customerName.$OrderRs[0]->ship_last_name;
				}
				
				$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
								
				return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
					
				}
			}
		
		
		
	}

	public function Cancel_Phoneorder(Request $request)
	{
		$OrderID = Session::get('phoneorder_detail.order_id');
		
		## 'This method is not in use.'
		// dd($request->all());
		Session::flash('error','Error in Processing Request, Please try again.');									
		return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
	}

}
