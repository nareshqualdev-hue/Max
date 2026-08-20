<?php 
namespace App\Http\Controllers;

ini_set('memory_limit', '2024M');

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use URL;

use Hash;
use Session;
use App\Models\MetaInfo;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PaymentMethod;

use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\EncryptTrait;
use App\Http\Controllers\Traits\CartTrait;
use App\Http\Controllers\Traits\AfterpayTrait;
	
use DB;
use Mail;

use Illuminate\Support\Facades\Log;


use Afterpay\SDK\HTTP\Request\Ping as AfterpayPingRequest;

use Afterpay\SDK\Exception\NetworkException as AfterpayNetworkException;
use Afterpay\SDK\Exception\ParsingException as AfterpayParsingException;


use Afterpay\SDK\Config as AfterpayConfig;
use Afterpay\SDK\MerchantAccount as AfterpayMerchantAccount;
use Afterpay\SDK\PersistentStorage as AfterpayPersistentStorage;
use Afterpay\SDK\HTTP\Request\GetConfiguration as AfterpayGetConfigurationRequest;


use Afterpay\SDK\Exception\InvalidModelException as AfterpayInvalidModelException;
use Afterpay\SDK\HTTP\Request\CreateCheckout as AfterpayCreateCheckoutRequest;
use Afterpay\SDK\HTTP\Request\GetCheckout as AfterpayGetCheckoutRequest;
use Afterpay\SDK\Model\Consumer as AfterpayConsumer;
use Afterpay\SDK\Model\Money as AfterpayMoney;


use Afterpay\SDK\HTTP\Request\DeferredPaymentAuth as AfterpayDeferredPaymentAuthRequest;


use Afterpay\SDK\Helper\StringHelper as AfterpayStringHelper;
use Afterpay\SDK\Model\Payment as AfterpayPayment;
use Afterpay\SDK\HTTP\Request\DeferredPaymentCapture as AfterpayDeferredPaymentCaptureRequest;
use Afterpay\SDK\HTTP\Request\ImmediatePaymentCapture as AfterpayImmediatePaymentCapture;



class AfterpayController extends Controller
{
	use CommonTrait;
	use EncryptTrait;
	use CartTrait;
	use AfterpayTrait;
		
	public $PageData,$Payment_Url,$Token_JS_Url,$TRANSACTION_MODE;
	public $ap_arr;

	public $merchant = null;
	public $error = null;
	public $order = null;
	public $paymentEvent = null;

	public function __construct()
    {
		$this->constructfunc_afterpaydetails();
		// echo "<pre>";print_r($this);exit;
		
		/* $db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_PAYWITHAFTERPAY')
							->where('pm_status', '=', 'Active')
							->get();
		
		if($db_res->count() > 0)
		{
			// if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.19.248" || Session::get('sess_useremail') == 'gequaldev@gmail.com'){
				 $db_res[0]->pm_details = 'a:6:{s:27:"PaywithAfterpay_Merchant_ID";s:16:"6+/un9C1fWJXNwA=";s:35:"PaywithAfterpay_Merchant_Secret_Key";s:144:"DcpbEsIgDADA6zu8EopYb+IASQPpbarSHkH3e3eHHWoFXrewBFBLb4q+A56KUw5Sm1ZhDBzbyF7VuFq8Lzfky/VWJC3PhyvGD+WAkHrErwEhylcjmiLZ7ZqO2LY7Z339k53V4oAxmjmPT/gB";s:36:"PaywithAfterpay_Header_Authorization";s:244:"BcHJCoJAAABQJOjXszKtY4IkRc6kDokOpJ1qRiRbXVDTKKx7tFide0+byik49feqlcaIYgYYO1bho7b9ezezKue1wTqzHIWqo72AxHEF4ycBfrxwgi2bB2cODegKvIZ0kt89pMo14IUNxVgf9DJ0j7trrJm4Cxnagn6U6NSpoBQG0ChaqP5ws9rN1Tq4hzcigduLYKuEwXLCmemX2PWSzcuCzBJDAB9zCf2iiyQ4B1JETato/AE=";s:33:"PaywithAfterpay_Header_User_Agent";s:152:"AWsAlP+h0NLTyfR4p8fU8MXMhZC3inmwnIaf+MTM8f2776nYxr/Pxc7M9HilvMzui4+Ej7eKlKinoay4jo28uYjFqdW/0L7HwMj7h4iLj7qMjo+SuYN58MvNzPyRjrv+wPn5trvEz8TRyujRhb7O9g==";s:32:"PaywithAfterpay_Transaction_Mode";s:7:"Sandbox";s:29:"PaywithAfterpay_Currency_Code";s:3:"USD";}';
			// }
			
			$arrPEVar		= unserialize($db_res[0]->pm_details);
			
			//echo "<pre>";print_r($arrPEVar);//exit;
			#############################
			$this->ap_arr['PaywithAfterpay_Merchant_ID']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_ID']);
			$this->ap_arr['PaywithAfterpay_Merchant_Secret_Key']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_Secret_Key']);
			$this->ap_arr['PaywithAfterpay_Header_Authorization']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_Authorization']);
			$this->ap_arr['PaywithAfterpay_Header_User_Agent']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_User_Agent']);
			
			#############################
			//echo "<pre>";print_r($arrPEVar);exit;
			
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
		}else{
			
		} */
		
	}
	
	public function GetAfterPayResult($data_payload = array(),$ApiType="",$IsPost = "Yes"){
		if(empty($data_payload)){
			$data_payload = json_encode($data_payload);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->Payment_Url.$ApiType);
		curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if($IsPost == "Yes"){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_payload);
		}

		if(!empty($data_payload)){

		}

		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Basic '.$this->ap_arr["PaywithAfterpay_Header_Authorization"];	//taken from doc
		$headers[] = 'User-Agent: '.$this->ap_arr["PaywithAfterpay_Header_User_Agent"];
		$headers[] = 'Accept: application/json';

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);

		curl_close($ch);

		$resultArr = json_decode($response,true);
		//echo "<pre>sss";print_r($resultArr);exit;
		return $resultArr;
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
	

	public function SetAfterpay(Request $request)
	{
		/*
		if($this->Is_WholeSaler_Allow() == false)
		{
			return redirect('/shoppingcart');
		}
		*/
		
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
		{
			Session::forget('ShoppingCart');
			return redirect('/shoppingcart');	
		}

		/*$pingRequest = new AfterpayPingRequest();
		$this->tryPing($pingRequest);*/
		
		if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID') > 0)
		{
			$order_id = Session::get('ShoppingCart.OrderID');
		}
		else if(isset($request->ordernoid) && $request->ordernoid > 0)
		{
			$order_id = $request->ordernoid;
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

		// dd($body);

		$Payment_Amount  = NumberFormat($this->GetNetTotal());
		$Payment_Currency  = 'USD';
		// $payload['merchantReference'] = "OR".Session::get('ShoppingCart.OrderID');
		$setConsumer = [];
		if( (Session::has('sess_icustomerid')) && Session::get('sess_icustomerid') > 0) {
			$customer = Customer::where('customer_id', '=', Session::get('sess_icustomerid'))->get();
			if($customer && count($customer) > 0) {
				$setConsumer['givenNames'] = $this->transliterate($customer[0]['first_name']);		//optional
				$setConsumer['surname'] = $this->transliterate($customer[0]['last_name']);		//optional
				$setConsumer['email'] = $customer[0]['email'];	//required
			} else {
				Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
				return redirect('checkout');
			}
		} else {
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('checkout');
		}

		//$setMerchant['redirectConfirmUrl'] = url('afterpay/success');
		$setMerchant['popupOriginUrl'] = url('afterpay/success');
		$setMerchant['redirectCancelUrl'] = url('afterpay/cancel');
		
		$setMode['mode'] = "express";
		
		$tempBillingAdd  = Session::get('ShoppingCart.BillingAddress');
		$tempShippingAdd = Session::get('ShoppingCart.ShippingAddress');

		$setBilling = [];
		$setBilling['name'] = $this->transliterate($tempBillingAdd['first_name'])." ".$this->transliterate($tempBillingAdd['last_name']);
		$setBilling['line1'] = $this->transliterate($tempBillingAdd['address1']);
		$setBilling['area1'] = $tempBillingAdd['city'];
		$setBilling['region'] = $tempBillingAdd['state'];
		$setBilling['postcode'] = $tempBillingAdd['zip'];
		$setBilling['countryCode'] = $tempBillingAdd['country'];
		$setBilling['phoneNumber'] = $tempBillingAdd['phone'];

		$setShipping = [];
		$setShipping['name'] = $this->transliterate($tempShippingAdd['first_name'])." ".$this->transliterate($tempShippingAdd['last_name']);
		$setShipping['line1'] = $this->transliterate($tempShippingAdd['address1']);
		$setShipping['area1'] = $tempShippingAdd['city'];
		$setShipping['region'] = $tempShippingAdd['state'];
		$setShipping['postcode'] = $tempShippingAdd['zip'];
		$setShipping['countryCode'] = $tempShippingAdd['country'];
		$setShipping['phoneNumber'] = $tempShippingAdd['phone'];


		//Items details

		$setItems = [];
		$ShopCart = Session::get('ShoppingCart.Cart');
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
			$setItems[] = [
		        'name' => $CartItem['ProductName'],
		        'sku' => $CartItem['SKU'],
		        'quantity' => $CartItem['Qty'],
		        'pageUrl' => $CartItem['Prod_URL'],
		        'price' => [$ItemPrice, 'USD']
		    ];
		}

		/**
		 * Method B:
		 *
		 * Instantiating an empty Request class, then setting the values of each field using the individual
		 * setter methods. If Automatic Validation is disabled, you can load in all of the data and then iterate over
		 * the list of errors, rather than only catching the first.
		 */
		// dd($setConsumer, $setBilling, $setShipping, $setItems, $setMerchant);
		\Afterpay\SDK\Model::setAutomaticValidationEnabled(false);

		$createCheckoutRequest = new AfterpayCreateCheckoutRequest();

		$createCheckoutRequest
		    ->setAmount($Payment_Amount, 'USD')
		    ->setConsumer($setConsumer)
		    ->setBilling($setBilling)
		    ->setShipping($setShipping)
		    ->setMode($setMode)
		    /*->setCourier([
		        'shippedAt' => '2019-01-01T00:00:00+10:00',
		        'name' => 'FedEx',
		        'tracking' => 'AA0000000000000',
		        'priority' => 'STANDARD'
		    ])*/
		    ->setItems($setItems)
		    /*->setDiscounts([
		        [
		            'displayName' => '20% off SALE',
		            'amount' => [ '24.00', 'USD' ]
		        ]
		    ])*/
		    ->setMerchant($setMerchant)
		    /*->setTaxAmount('0.00', 'USD')
		    ->setShippingAmount('0.00', 'USD')*/
		;
		if ($createCheckoutRequest->isValid()) {
			$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
			    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
					->setApiEnvironment($this->TRANSACTION_MODE)
			    	->setCountryCode('US');

			$createCheckoutRequest->setMerchantAccount($merchant);

		    $createCheckoutRequest->send();

		    // $response = $createCheckoutRequest->getRawLog();

		    $createCheckoutResponse = $createCheckoutRequest->getResponse();
    		$response = $createCheckoutResponse->getParsedBody();
				// dd($response);
			if ($createCheckoutResponse->isSuccessful()) {
				// dd($response->redirectCheckoutUrl);
				if(isset($response->token) && $response->token != ""){
					$redirect = $response->redirectCheckoutUrl;
					$token = $response->token;
					$expires = $response->expires;
					
					$updAray = array ('status' => 'Sent To AfterPay');

					//$order_id = Session::get('ShoppingCart.OrderID');
					$uporderres = Order::Where("orders_id","=",$order_id)
										->update($updAray);

			    	return redirect($response->redirectCheckoutUrl);
				}else{
					$transaction_info = "This transaction has been Declined.";
					$Payment_response = json_encode($response);

					$updAray = array (
										'status' 	   			=> 'Declined',
										'transaction_info' 			=> $transaction_info,
										'payment_gateway_response' 	=> $Payment_response,
										'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
									  );

					//$order_id = Session::get('ShoppingCart.OrderID');
					
					$updOrder = Order::Where("orders_id","=",$order_id)
										->update($updAray);		
					
					addLog("AfterPayOrderDeclined - 351 - ".$order_id,$updAray);
					Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
					return redirect('checkout');
				}

			} else {
			    $this->error = $response;
			}
		} else {
		    $response = $createCheckoutRequest->getValidationErrorsAsHtml();
		}

	}

	public function Success(Request $request)
	{
		// dd($request->all());
		if($request->has('status') && $request->status == "SUCCESS") {
			if ($request->has('orderToken') && $request->orderToken != '') {

			    	$deferredPaymentAuthRequest = new AfterpayDeferredPaymentAuthRequest([
				        'token' => urlencode($request->orderToken)
				    ]);

					$merchant = new AfterpayMerchantAccount();
					$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
					    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
							->setApiEnvironment($this->TRANSACTION_MODE)
					    	->setCountryCode('US');

				    if (!is_null($merchant)) {
				        $deferredPaymentAuthRequest->setMerchantAccount($merchant);
				    }

				    $deferredPaymentAuthRequest->send();

				    $deferredPaymentAuthResponse = $deferredPaymentAuthRequest->getResponse();
				    $repsonse = $deferredPaymentAuthResponse->getParsedBody();

					/////////// Log Start ////////////
					$cur_date = date("Y-m-d");
					$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
					if(@fopen($myFile, 'a+'))
					{
						$fh = fopen($myFile, 'a+');

						$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->orderToken) . chr(13) . chr(13) ;
						$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

						fwrite($fh, $stringData);
						fclose($fh);
					}
					/////////// Log End ////////////
					// dd($repsonse);
				    /*if ($deferredPaymentAuthResponse->isSuccessful()) {
				        $this->PageData['order'] = $repsonse;
				        $this->PageData['type'] = 'order';
				    } else {
				        $this->PageData['error'] = $repsonse;
				        $this->PageData['type'] = 'error';
				    }*/

				    if($deferredPaymentAuthRequest->getResponse()->isApproved() && $repsonse->paymentState == "AUTH_APPROVED" && $request->orderToken != "") {

						if( (Session::has('sess_icustomerid')) && Session::get('sess_icustomerid') > 0) {
							$payment_gateway_response = "Auth Response::".json_encode($repsonse);
							$updAray = array (
												'payment_gateway_response' 	=> $payment_gateway_response,
												'afterpay_transaction_id' 	=> $repsonse->id
											  );

							$order_id = Session::get('ShoppingCart.OrderID');
							$uporderres = Order::Where("orders_id","=",$order_id)
												->update($updAray);	
							$capturePayment = url('afterpay/dopayment/'.$repsonse->id);
							return redirect($capturePayment);
						} else {

							//order order not confirmed by customer
							//status >> CANCELLED
							$transaction_info = "This transaction has been Declined.";
							$updAray = array (
												'status' 	   				=> 'Declined',
												'transaction_info' 			=> $transaction_info,
												'payment_gateway_response' 	=> $transaction_info,
												'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
											  );
											  
							$order_id = Session::get('ShoppingCart.OrderID');
							$uporderres = Order::Where("orders_id","=",$order_id)
												->update($updAray);	
							
							addLog("AfterPayOrderDeclined - 442 - ".$order_id,$updAray);					
							Session::flash('CartError','Error in Processing Request, Please try again.');								
							return redirect('shoppingcart');

						}
				    } else {
						$transaction_info = "This transaction has been Declined.";
						$Payment_response = json_encode($repsonse);
						
						$updAray = array (
											'status' 	   				=> 'Declined',
											'transaction_info' 			=> $transaction_info,
											'payment_gateway_response' 	=> $Payment_response,
											'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
										  );

						$order_id = Session::get('ShoppingCart.OrderID');
						$updOrder = Order::Where("orders_id","=",$order_id)
											->update($updAray);	

						addLog("AfterPayOrderDeclined - 461 - ".$order_id,$updAray);
						Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
						return redirect('checkout');
				    }
			}
		} else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> "This transaction has been Declined by User.",
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
							  
			$order_id = Session::get('ShoppingCart.OrderID');
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);
			
			addLog("AfterPayOrderDeclined - 479 - ".$order_id,$updAray);					
			Session::flash('CartError','Error in Processing Request, Please try again.');								
			return redirect('shoppingcart');
		}

		// dd('Success', $request->all(), $this->order, $this->error);
	}

	public function Cancel(Request $request)
	{
		$transaction_info = "This transaction has been Declined.";
		$updAray = array (
							'status' 	   				=> 'Declined',
							'transaction_info' 			=> $transaction_info,
							'payment_gateway_response' 	=> "This transaction has been Declined by User.",
							'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
						  );
						  
		$order_id = Session::get('ShoppingCart.OrderID');
		$uporderres = Order::Where("orders_id","=",$order_id)
							->update($updAray);
		
		addLog("AfterPayOrderDeclined - 500 - ".$order_id,$updAray); 
		
		return redirect('shoppingcart');
		//dd('Cancel', $request->all());
	}

	public function DoPayment(Request $request)
	{
		$Payment_Amount  = NumberFormat($this->GetNetTotal());
		$Payment_Currency  = 'USD';
		$requestId = AfterpayStringHelper::generateUuid();
		// dd($requestId);
	    $capturePaymentRequest = new AfterpayDeferredPaymentCaptureRequest([
	        'requestId' => $requestId,
	        'amount' => [$Payment_Amount, 'USD']
	    ]);
	    if(!is_numeric($request[ 'order_id' ])) {
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('checkout');
	    }
	    $capturePaymentRequest->setOrderId($request->order_id);

		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');

        $capturePaymentRequest->setMerchantAccount($merchant);

		$merchantReference = "OR".Session::get('ShoppingCart.OrderID');
        $capturePaymentRequest->setMerchantReference($merchantReference);

		/////////// Log Start ////////////
		/*$cur_date = date("Y-m-d");
		$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
		if(@fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->order_id) . chr(13) . chr(13) ;
			$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

			fwrite($fh, $stringData);
			fclose($fh);
		}*/
		/////////// Log End ////////////

	    $capturePaymentRequest->send();

	    $capturePaymentResponse = $capturePaymentRequest->getResponse();
	    $repsonse = $capturePaymentResponse->getParsedBody();
	    // dd($capturePaymentResponse);

		$payment_gateway_response = json_encode($repsonse);

		$order_id = Session::get('ShoppingCart.OrderID');
	    if((isset($repsonse->status) && $repsonse->status == "APPROVED") && ($repsonse->paymentState == "CAPTURED" || $repsonse->paymentState == "PARTIALLY_CAPTURED") ) {
	    	$transaction_info = "This transaction has been approved.";

			$order_result = Order::select('payment_gateway_response')->where("orders_id","=",$order_id)->get();
			if($order_result && count($order_result) > 0) {
				$payment_gateway_response = $order_result[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
			}

			$updAray = array (
								'pay_status' 	   			=> 'Paid',
								'status' 	   				=> 'Pending',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'afterpay_transaction_id' 	=> $repsonse->id,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );

			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	

			return redirect('order-receipt');
	    } else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	

			addLog("AfterPayOrderDeclined - 589 - ".$order_id,$updAray);					
			$Message = 'Error in Processing Request, Please try again.';				
			if(isset($repsonse->errorId)){
				$Message = $repsonse->message;
			}
			Session::flash('CartError', $Message);								
			return redirect('shoppingcart');

	    }
	    /*if ($capturePaymentRequest->send()) {
	        $order = new AfterpayPayment($capturePaymentRequest->getResponse()->getParsedBody());
	        $this->pageData['paymentEvent'] = json_encode($capturePaymentRequest->getResponse()->getPaymentEvent());
	    } else {
	        $this->pageData['error'] = $capturePaymentRequest->getResponse()->getParsedBody();
	    }
	    dd($this->pageData);*/
	}



	function tryPing($pingRequest)
	{
	    try {
	        if ($pingRequest->send()) {
	            # Success

	            echo "Afterpay/HTTP is UP\n";
	        } else {
	            # A 3xx, 4xx, or 5xx series HTTP Response.
	            # Please log the response code,
	            # errorCode, errorId and message from the body (if available),
	            # or the CF-Ray ID otherwise.

	            $pingResponse = $pingRequest->getResponse();
	            $responseCode = $pingResponse->getHttpStatusCode();
	            $contentType = $pingResponse->getContentTypeSimplified();

	            if (is_object($body = $pingResponse->getParsedBody())) {
	                $errorCode = $body->errorCode;
	                $errorId = $body->errorId;
	                $message = $body->message;

	                echo "ERROR: Received unexpected HTTP {$responseCode} {$contentType} response from Afterpay with errorCode: {$errorCode}; errorId: {$errorId}; message: {$message}\n";
	            } else {
	                $cfRayId = $pingResponse->getParsedHeaders()[ 'cf-ray' ];

	                echo "ERROR: Received unexpected HTTP {$responseCode} {$contentType} response from Afterpay with CF-Ray ID: {$cfRayId}\n";
	            }
	        }
	    } catch (AfterpayNetworkException $e) {
	        # This generally indicates a transient network error, such as a connection reset
	        # or client timeout.

	        $curl_error_number = $e->getCode();
	        $curl_error_message = $e->getMessage();

	        echo "ERROR: Cannot connect to Afterpay via HTTP; caught Afterpay\SDK\Exception\NetworkException #{$curl_error_number}: '{$curl_error_message}'\n";
	    } catch (AfterpayParsingException $e) {
	        # This means that the SDK could not process the response
	        # according to the Content-Type that the API declared.

	        $contentType = $pingRequest->getResponse()->getContentTypeSimplified();
	        $json_parsing_error_number = $e->getCode();
	        $json_parsing_error_message = $e->getMessage();

	        echo "ERROR: Received unparsable {$contentType} response from Afterpay; caught Afterpay\SDK\Exception\ParsingException #{$json_parsing_error_number}: '{$json_parsing_error_message}'\n";
	    }
	}


	public function PhoneOrder(Request $request)
	{
		if( $OrderID <= 0) 
		{
			Session::flash('error','Error in Processing Request, Please try again.');		
			return redirect(config('global.SITE_URL'));
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

		// dd($body);
		$OrderRs = DB::table('pu_orders as o')
								->join('pu_customer as c','o.customer_id','=','c.customer_id')
								->select('o.orders_id', 'o.orders_no','o.customer_id', 'o.order_total', 'o.bill_email','o.bill_first_name','o.bill_last_name','o.bill_company','o.bill_email','o.bill_address1','o.bill_address2','o.bill_city','o.bill_zip','o.bill_state','o.bill_country','o.bill_phone','o.ship_first_name','o.ship_last_name','o.ship_company','o.ship_email','o.ship_address1','o.ship_address2','o.ship_city','o.ship_zip','o.ship_state','o.ship_country','o.ship_phone','c.first_name','c.last_name','c.email')
								->where('orders_id', '=', $OrderID)
								->get();
		
		if($OrderRs->count() <= 0 || (isset($OrderRs[0]->order_total) && $OrderRs[0]->order_total <= 0)){
			Session::flash('error','Error in Processing Request, Please try again.');		
			return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
		}

		$Payment_Amount  = NumberFormat($OrderRs[0]->order_total);
		$Payment_Currency  = 'USD';
		// $payload['merchantReference'] = "OR".Session::get('ShoppingCart.OrderID');
		$setConsumer = [];
		if($OrderRs[0]->customer_id > 0) {
			$setConsumer['givenNames'] = $OrderRs[0]->first_name;		//optional
			$setConsumer['surname'] = $OrderRs[0]->last_name;		//optional
			$setConsumer['email'] = $OrderRs[0]->email;	//required
		} else {
			Session::flash('error','Error in Processing Request, Please try again.');								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
		}

		$setMerchant['redirectConfirmUrl'] = url('afterpay/success_phoneorder');
		$setMerchant['redirectCancelUrl'] = url('afterpay/cancel_phoneorder');
		
		$setBilling = [];
		$setBilling['name'] = $OrderRs[0]->bill_first_name." ".$OrderRs[0]->bill_last_name;
		$setBilling['line1'] = $OrderRs[0]->bill_address1;
		$setBilling['area1'] = $OrderRs[0]->bill_city;
		$setBilling['region'] = $OrderRs[0]->bill_state;
		$setBilling['postcode'] = $OrderRs[0]->bill_zip;
		$setBilling['countryCode'] = $OrderRs[0]->bill_country;
		$setBilling['phoneNumber'] = $OrderRs[0]->bill_phone;

		$setShipping = [];
		$setShipping['name'] = $OrderRs[0]->ship_first_name." ".$OrderRs[0]->ship_last_name;
		$setShipping['line1'] = $OrderRs[0]->ship_address1;
		$setShipping['area1'] = $OrderRs[0]->ship_city;
		$setShipping['region'] = $OrderRs[0]->ship_state;
		$setShipping['postcode'] = $OrderRs[0]->ship_zip;
		$setShipping['countryCode'] = $OrderRs[0]->ship_country;
		$setShipping['phoneNumber'] = $OrderRs[0]->ship_phone;


		//Items details
		$Data_order_detail = OrderDetail::where('orders_id', '=', $OrderID)
								->get();
		$setItems = [];
			
		for($i=0;$i < count($Data_order_detail); $i++){
			$prd_res = DB::table('pu_products as p')
								->join('pu_products_category as pc','p.products_id','=','pc.products_id')
								->select('p.products_id', 'p.product_name' ,'p.sku', 'p.sale_price', 'p.current_stock','p.short_description','p.status','pc.category_id')
								->where('sku', '=', $Data_order_detail[$i]['sku'])
								->where('status', '=', '1')
								->get();
								
		    $prd_sku = $prd_res[0]->sku;	
		    $iprod_id = $prd_res[0]->products_id;
		    $product_name = $prd_res[0]->product_name;
		    $short_description = $prd_res[0]->short_description;	
		    $category_id = $prd_res[0]->category_id;	
		   
			// $p_link = $generalobj->getProductRewriteURL($prd_res[0]['products_id'], $prd_res[0]['product_name']);
			$p_link = SetProductURL($iprod_id, $product_name, $category_id);
			
			$setItems[] = [
		        'name' => $Data_order_detail[$i]['product_name'],
		        'sku' => $Data_order_detail[$i]['sku'],
		        'quantity' => $Data_order_detail[$i]['quantity'],
		        'pageUrl' => $p_link,
		        'price' => [$Data_order_detail[$i]['total'], 'USD']
		    ];
			 
			//$payload['items'][$i]['imageUrl'] = $ItemArr[$i]['ProductName'];
		}

		/**
		 * Method B:
		 *
		 * Instantiating an empty Request class, then setting the values of each field using the individual
		 * setter methods. If Automatic Validation is disabled, you can load in all of the data and then iterate over
		 * the list of errors, rather than only catching the first.
		 */
		// dd($setConsumer, $setBilling, $setShipping, $setItems, $setMerchant);
		\Afterpay\SDK\Model::setAutomaticValidationEnabled(false);

		$createCheckoutRequest = new AfterpayCreateCheckoutRequest();

		$createCheckoutRequest
		    ->setAmount($Payment_Amount, 'USD')
		    ->setConsumer($setConsumer)
		    ->setBilling($setBilling)
		    ->setShipping($setShipping)
		    /*->setCourier([
		        'shippedAt' => '2019-01-01T00:00:00+10:00',
		        'name' => 'FedEx',
		        'tracking' => 'AA0000000000000',
		        'priority' => 'STANDARD'
		    ])*/
		    ->setItems($setItems)
		    /*->setDiscounts([
		        [
		            'displayName' => '20% off SALE',
		            'amount' => [ '24.00', 'USD' ]
		        ]
		    ])*/
		    ->setMerchant($setMerchant)
		    /*->setTaxAmount('0.00', 'USD')
		    ->setShippingAmount('0.00', 'USD')*/
		;
		if ($createCheckoutRequest->isValid()) {
			$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
			    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
					->setApiEnvironment($this->TRANSACTION_MODE)
			    	->setCountryCode('US');

			$createCheckoutRequest->setMerchantAccount($merchant);

		    $createCheckoutRequest->send();

		    // $response = $createCheckoutRequest->getRawLog();

		    $createCheckoutResponse = $createCheckoutRequest->getResponse();
    		$response = $createCheckoutResponse->getParsedBody();
				// dd($response);echo $OrderID;exit;
			if ($createCheckoutResponse->isSuccessful()) {
				// dd($response->redirectCheckoutUrl);
				if(isset($response->token) && $response->token != ""){
					$redirect = $response->redirectCheckoutUrl;
					$token = $response->token;
					$expires = $response->expires;
					
					$updAray = array (
										'status' => 'Sent To AfterPay',
										'payment_type' 			=> 'PAYMENT_PAYWITHAFTERPAY',
										'payment_method' 		=> 'Pay With Afterpay'
									);

					$uporderres = Order::Where("orders_id","=",$OrderID)->update($updAray);

			    	return redirect($response->redirectCheckoutUrl);
				}else{
					$transaction_info = "This transaction has been Declined.";
					$Payment_response = json_encode($response);

					$updAray = array (
										'status' 	   			=> 'Declined',
										'transaction_info' 		=> $transaction_info,
										'payment_gateway_response' => $Payment_response,
										'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
									  );

					$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);		
					addLog("AfterPayOrderDeclined - 839 - ".$OrderID,$updAray);
					Session::flash('error','Error in Processing Request, Please try again.');
					
					$sessionDetails	= Session::get('ShoppingCart.BillingAddress');
					
					$customerEmail = '';
					if(isset($sessionDetails['email']) && $sessionDetails['email']!='')
					{
						$customerEmail	= $sessionDetails['email'];
					}
					
					$orderNo		= 'OR'.$OrderID;
					$customerName 	= '';
					if((isset($sessionDetails['first_name']) && $sessionDetails['first_name']!=''))
					{
						$customerName	= $this->transliterate($sessionDetails['first_name'])." ";
					}
					
					if(isset($sessionDetails['last_name']) && $sessionDetails['last_name']!='')
					{
						$customerName = $customerName.$this->transliterate($sessionDetails['last_name']);
					}
					
					$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
					
					/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
					
					$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
					
					return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
				}

			} else {
			    $this->error = $response;
			}
		} else {
		    $response = $createCheckoutRequest->getValidationErrorsAsHtml();
		}

	}
	
	public function Success_Phoneorder(Request $request)
	{
		if($request->has('status') && $request->status == "SUCCESS") {
			if ($request->has('orderToken') && $request->orderToken != '') {

			    	$deferredPaymentAuthRequest = new AfterpayDeferredPaymentAuthRequest([
				        'token' => urlencode($request->orderToken)
				    ]);

					$merchant = new AfterpayMerchantAccount();
					$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
					    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
							->setApiEnvironment($this->TRANSACTION_MODE)
					    	->setCountryCode('US');

				    if (!is_null($merchant)) {
				        $deferredPaymentAuthRequest->setMerchantAccount($merchant);
				    }

				    $deferredPaymentAuthRequest->send();

				    $deferredPaymentAuthResponse = $deferredPaymentAuthRequest->getResponse();
				    $repsonse = $deferredPaymentAuthResponse->getParsedBody();

					/////////// Log Start ////////////
					// $cur_date = date("Y-m-d");
					// $myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
					// if(@fopen($myFile, 'a+'))
					// {
						// $fh = fopen($myFile, 'a+');

						// $stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->orderToken) . chr(13) . chr(13) ;
						// $stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

						// fwrite($fh, $stringData);
						// fclose($fh);
					// }
					/////////// Log End ////////////
			
					Session::put('phoneorder_detail.Afterpay.AP_Auth_Token',$repsonse->token);
					Session::put('phoneorder_detail.Afterpay.AP_Auth_ID',$repsonse->id);
					Session::put('phoneorder_detail.Afterpay.AP_Auth_Status',$repsonse->status);
					
					$sessionDetails	= Session::get('ShoppingCart.BillingAddress');
					
					$customerEmail = '';
					if(isset($sessionDetails['email']) && $sessionDetails['email']!='')
					{
						$customerEmail	= $sessionDetails['email'];
					}
					
					$orderNo		= 'OR'.Session::get('phoneorder_detail.order_id');
					$customerName 	= '';
					if((isset($sessionDetails['first_name']) && $sessionDetails['first_name']!=''))
					{
						$customerName	= $this->transliterate($sessionDetails['first_name'])." ";
					}
					
					if(isset($sessionDetails['last_name']) && $sessionDetails['last_name']!='')
					{
						$customerName = $customerName.$this->transliterate($sessionDetails['last_name']);
					}
		
				    if($deferredPaymentAuthRequest->getResponse()->isApproved() && $repsonse->paymentState == "AUTH_APPROVED" && $repsonse->token != "") {
						Session::put('phoneorder_detail.Afterpay.AP_Auth_Amt',$repsonse->originalAmount->amount);
						Session::put('phoneorder_detail.Afterpay.AP_Auth_Currency',$repsonse->originalAmount->currency);
						
						$customer_id = Session::get('phoneorder_detail.customer_id');
						$order_id = Session::get('phoneorder_detail.order_id');
						
						if($customer_id > 0) {
							$payment_gateway_response = "Auth Response::".json_encode($repsonse);
							$updAray = array (
												'payment_gateway_response' 	=> $payment_gateway_response,
												'afterpay_transaction_id' 	=> $repsonse->id
											  );

							$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
							
							$capturePayment = url('afterpay/dopayment_phoneorder/'.$repsonse->id);
							return redirect($capturePayment);
						} else {

							//order order not confirmed by customer
							//status >> CANCELLED
							$transaction_info = "This transaction has been Declined.";
							$updAray = array (
												'status' 	   				=> 'Declined',
												'transaction_info' 			=> $transaction_info,
												'payment_gateway_response' 	=> $transaction_info,
												'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
											  );
											  
							$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
							addLog("AfterPayOrderDeclined - 976 - ".$order_id,$updAray);
							$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
							OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
							
							/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
							OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
							
							$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
							OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

							Session::flash('error','Error in Processing Request, Please try again.');								
							return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));

						}
				    } else {
						$transaction_info = "This transaction has been Declined.";
						$Payment_response = json_encode($repsonse);
						
						$updAray = array (
											'status' 	   				=> 'Declined',
											'transaction_info' 			=> $transaction_info,
											'payment_gateway_response' 	=> $Payment_response,
											'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
										  );

						$order_id = Session::get('phoneorder_detail.order_id');
						$updOrder = Order::Where("orders_id","=",$order_id)->update($updAray);
						addLog("AfterPayOrderDeclined - 1001 - ".$order_id,$updAray);
						$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
						OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
						
						/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
						OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
						
						$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
						OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

						Session::flash('error','Error in Processing Request, Please try again.');								
						return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
				    }
			}
		} else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> "This transaction has been Declined by User.",
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
							  
			$order_id = Session::get('phoneorder_detail.order_id');
			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
			addLog("AfterPayOrderDeclined - 1027 - ".$order_id,$updAray);
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
				$customerName	= $this->transliterate($sessionDetails['first_name'])." ";
			}
			
			if(isset($sessionDetails['last_name']) && $sessionDetails['last_name']!='')
			{
				$customerName = $customerName.$this->transliterate($sessionDetails['last_name']);
			}
			
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
				
			Session::flash('error','Error in Processing Request, Please try again.');								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
		}

		// dd('Success', $request->all(), $this->order, $this->error);
	}

	public function DoPayment_Phoneorder(Request $request)
	{
		$customer_id = Session::get('phoneorder_detail.customer_id');
		$order_id = Session::get('phoneorder_detail.order_id');
		$Payment_Amount = NumberFormat(Session::get('phoneorder_detail.order_amt'));
		$Payment_Currency  = 'USD';
		
		$requestId = AfterpayStringHelper::generateUuid();
		// dd($requestId);
	    $capturePaymentRequest = new AfterpayDeferredPaymentCaptureRequest([
	        'requestId' => $requestId,
	        'amount' => [$Payment_Amount, 'USD']
	    ]);
	    if(!is_numeric($request[ 'order_id' ])) {
			Session::flash('error','Error in Processing Request, Please try again.');								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
	    }
	    $capturePaymentRequest->setOrderId($request->order_id);

		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');

        $capturePaymentRequest->setMerchantAccount($merchant);

		$merchantReference = "OR".$order_id;
        $capturePaymentRequest->setMerchantReference($merchantReference);

		/////////// Log Start ////////////
		/*$cur_date = date("Y-m-d");
		$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
		if(@fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->order_id) . chr(13) . chr(13) ;
			$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

			fwrite($fh, $stringData);
			fclose($fh);
		}*/
		/////////// Log End ////////////

	    $capturePaymentRequest->send();

	    $capturePaymentResponse = $capturePaymentRequest->getResponse();
	    $repsonse = $capturePaymentResponse->getParsedBody();
	    // dd($capturePaymentResponse);

		$payment_gateway_response = json_encode($repsonse);

	    if((isset($repsonse->status) && $repsonse->status == "APPROVED") && ($repsonse->paymentState == "CAPTURED" || $repsonse->paymentState == "PARTIALLY_CAPTURED") ) {
	    	$transaction_info = "This transaction has been approved.";

			$order_result = Order::select('payment_gateway_response')->where("orders_id","=",$order_id)->get();
			if($order_result && $order_result->count() > 0) {
				$payment_gateway_response = $order_result[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
			}

			$updAray = array (
								'pay_status' 	   			=> 'Paid',
								'status' 	   				=> 'Pending',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'afterpay_transaction_id' 	=> $repsonse->id,
								'phoneorder_paymentdate' => date("Y-m-d H:i:s"),
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );

			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);	

			//stock,other related changes start
			$response_arr = $this->PhoneorderPaymentSuccess('Afterpay');
			if($response_arr['success'] == "1"){
				Session::flash('success',$response_arr['err_msg']);
			}else{
				Session::flash('error',$response_arr['err_msg']);
			}	
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
	    } else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
			addLog("AfterPayOrderDeclined - 1154 - ".$order_id,$updAray);
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
				$customerName	= $this->transliterate($sessionDetails['first_name'])." ";
			}
			
			if(isset($sessionDetails['last_name']) && $sessionDetails['last_name']!='')
			{
				$customerName = $customerName.$this->transliterate($sessionDetails['last_name']);
			}
			
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

			$Message = 'Error in Processing Request, Please try again.';				
			if(isset($repsonse->errorId)){
				$Message = $repsonse->message;
			}
			Session::flash('error', $Message);								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));

	    }
	    /*if ($capturePaymentRequest->send()) {
	        $order = new AfterpayPayment($capturePaymentRequest->getResponse()->getParsedBody());
	        $this->pageData['paymentEvent'] = json_encode($capturePaymentRequest->getResponse()->getPaymentEvent());
	    } else {
	        $this->pageData['error'] = $capturePaymentRequest->getResponse()->getParsedBody();
	    }
	    dd($this->pageData);*/
	}

	public function Cancel_Phoneorder(Request $request)
	{
		$order_id = Session::get('phoneorder_detail.order_id');
		$transaction_info = "This transaction has been Declined.";
		$updAray = array (
							'status' 	   				=> 'Declined',
							'transaction_info' 			=> $transaction_info,
							'payment_gateway_response' 	=> "This transaction has been Declined by User.",
							'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
						  );
		
		$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
		addLog("AfterPayOrderDeclined - 1212 - ".$order_id,$updAray);
		return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
	}
	
	public function PhoneOrder_Express(Request $request)
	{
		$OrderID = Session::get('phoneorder_detail.order_id');
		if( $OrderID <= 0) 
		{
			$message = 'Error in Processing Request, Please try again.';
			return response()->json(array('success' => "0", 'token'=>"",'message'=>$message));
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

		// dd($body);exit;
		$OrderRs = DB::table('pu_orders as o')
								->join('pu_customer as c','o.customer_id','=','c.customer_id')
								->select('o.orders_id', 'o.orders_no','o.customer_id', 'o.order_total', 'o.bill_email','o.bill_first_name','o.bill_last_name','o.bill_company','o.bill_email','o.bill_address1','o.bill_address2','o.bill_city','o.bill_zip','o.bill_state','o.bill_country','o.bill_phone','o.ship_first_name','o.ship_last_name','o.ship_company','o.ship_email','o.ship_address1','o.ship_address2','o.ship_city','o.ship_zip','o.ship_state','o.ship_country','o.ship_phone','c.first_name','c.last_name','c.email')
								->where('orders_id', '=', $OrderID)
								->get();
		
		if($OrderRs->count() <= 0 || (isset($OrderRs[0]->order_total) && $OrderRs[0]->order_total <= 0)){
			$message = 'Error in Processing Request, Please try again.';
			return response()->json(array('success' => "0", 'token'=>"",'message'=>$message));
		}

		$Payment_Amount  = NumberFormat($OrderRs[0]->order_total);
		$Payment_Currency  = 'USD';
		// $payload['merchantReference'] = "OR".Session::get('ShoppingCart.OrderID');
		$setConsumer = [];
		if($OrderRs[0]->customer_id > 0) {
			$setConsumer['givenNames'] = $OrderRs[0]->first_name;		//optional
			$setConsumer['surname'] = $OrderRs[0]->last_name;		//optional
			$setConsumer['email'] = $OrderRs[0]->email;	//required
		} else {
			$message = 'Error in Processing Request, Please try again.';
			return response()->json(array('success' => "0", 'token'=>"",'message'=>$message));
		}
		
		// $setMode['mode'] = "express";
		$setMerchant['popupOriginUrl'] = request()->headers->get('referer');
		// $setMerchant['redirectConfirmUrl'] = url('afterpay/success_phoneorder');
		// $setMerchant['redirectCancelUrl'] = url('afterpay/cancel_phoneorder');
		
		$setBilling = [];
		$setBilling['name'] = $OrderRs[0]->bill_first_name." ".$OrderRs[0]->bill_last_name;
		$setBilling['line1'] = $OrderRs[0]->bill_address1;
		$setBilling['area1'] = $OrderRs[0]->bill_city;
		$setBilling['region'] = $OrderRs[0]->bill_state;
		$setBilling['postcode'] = $OrderRs[0]->bill_zip;
		$setBilling['countryCode'] = ($OrderRs[0]->bill_country === 'UK') ? 'GB' : $OrderRs[0]->bill_country;
		$setBilling['phoneNumber'] = $OrderRs[0]->bill_phone;

		$setShipping = [];
		$setShipping['name'] = $OrderRs[0]->ship_first_name." ".$OrderRs[0]->ship_last_name;
		$setShipping['line1'] = $OrderRs[0]->ship_address1;
		$setShipping['area1'] = $OrderRs[0]->ship_city;
		$setShipping['region'] = $OrderRs[0]->ship_state;
		$setShipping['postcode'] = $OrderRs[0]->ship_zip;		
		$setShipping['countryCode'] = ($OrderRs[0]->ship_country === 'UK') ? 'GB' : $OrderRs[0]->ship_country;
		$setShipping['phoneNumber'] = $OrderRs[0]->ship_phone;


		//Items details
		$Data_order_detail = OrderDetail::where('orders_id', '=', $OrderID)
								->get();
		$setItems = [];
			
		for($i=0;$i < count($Data_order_detail); $i++){
			$prd_res = DB::table('pu_products as p')
								->join('pu_products_category as pc','p.products_id','=','pc.products_id')
								->select('p.products_id', 'p.product_name' ,'p.sku', 'p.sale_price', 'p.current_stock','p.short_description','p.status','pc.category_id')
								->where('sku', '=', $Data_order_detail[$i]['sku'])
								->where('status', '=', '1')
								->get();
								
		    $prd_sku = $prd_res[0]->sku;	
		    $iprod_id = $prd_res[0]->products_id;
		    $product_name = $prd_res[0]->product_name;
		    $short_description = $prd_res[0]->short_description;	
		    $category_id = $prd_res[0]->category_id;	
		   
			// $p_link = $generalobj->getProductRewriteURL($prd_res[0]['products_id'], $prd_res[0]['product_name']);
			$p_link = SetProductURL($iprod_id, $product_name, $category_id);
			
			//remove tags and add space
			$item_name = $Data_order_detail[$i]['product_name'];
			$item_name = str_replace( '<', ' <',$item_name);
			$item_name = strip_tags($item_name);
			$item_name = str_replace( '  ', ' ',$item_name);
			
			$setItems[] = [
		        'name' => $item_name,
		        'sku' => $Data_order_detail[$i]['sku'],
		        'quantity' => $Data_order_detail[$i]['quantity'],
		        'pageUrl' => $p_link,
		        'price' => [$Data_order_detail[$i]['price'], 'USD']
		    ];
			 
			//$payload['items'][$i]['imageUrl'] = $ItemArr[$i]['ProductName'];
		}
		
		/**
		 * Method B:
		 *
		 * Instantiating an empty Request class, then setting the values of each field using the individual
		 * setter methods. If Automatic Validation is disabled, you can load in all of the data and then iterate over
		 * the list of errors, rather than only catching the first.
		 */
		// dd($setConsumer, $setBilling, $setShipping, $setItems, $setMerchant);
		\Afterpay\SDK\Model::setAutomaticValidationEnabled(false);

		$createCheckoutRequest = new AfterpayCreateCheckoutRequest();

		$createCheckoutRequest
		    ->setAmount($Payment_Amount, 'USD')
		    ->setConsumer($setConsumer)
		    ->setBilling($setBilling)
		    ->setShipping($setShipping)
			->setMode("EXPRESS")
		    /*->setCourier([
		        'shippedAt' => '2019-01-01T00:00:00+10:00',
		        'name' => 'FedEx',
		        'tracking' => 'AA0000000000000',
		        'priority' => 'STANDARD'
		    ])*/
		    ->setItems($setItems)
		    /*->setDiscounts([
		        [
		            'displayName' => '20% off SALE',
		            'amount' => [ '24.00', 'USD' ]
		        ]
		    ])*/
		    ->setMerchant($setMerchant)
		    /*->setTaxAmount('0.00', 'USD')
		    ->setShippingAmount('0.00', 'USD')*/
		;
		
		$token = "";
		if ($createCheckoutRequest->isValid()) {
			$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
			    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
					->setApiEnvironment($this->TRANSACTION_MODE)
			    	->setCountryCode('US');

			$createCheckoutRequest->setMerchantAccount($merchant);
		    $createCheckoutRequest->send();

		    // $response = $createCheckoutRequest->getRawLog();

		    $createCheckoutResponse = $createCheckoutRequest->getResponse();
    		$response = $createCheckoutResponse->getParsedBody();
			// echo "<pre>";print_r($response);echo $OrderID;exit;
			if ($createCheckoutResponse->isSuccessful()) {
				// dd($response->redirectCheckoutUrl);
				if(isset($response->token) && $response->token != ""){
					$redirect = $response->redirectCheckoutUrl;
					$token = $response->token;
					$expires = $response->expires;
					$success = "1";
					
					$updAray = array (
										'status' => 'Sent To AfterPay',
										'payment_type' 			=> 'PAYMENT_PAYWITHAFTERPAY',
										'payment_method' 		=> 'Pay With Afterpay'
									);

					$uporderres = Order::Where("orders_id","=",$OrderID)->update($updAray);

				}else{
					$transaction_info = "This transaction has been Declined.";
					$Payment_response = json_encode($response);

					$updAray = array (
										'status' 	   			=> 'Declined',
										'transaction_info' 		=> $transaction_info,
										'payment_gateway_response' => $Payment_response,
										'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
									  );

					$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);
					addLog("AfterPayOrderDeclined - 1407 - ".$OrderID,$updAray);
					$customerEmail = '';
					if(isset($OrderRs[0]->bill_email) && $OrderRs[0]->bill_email!='')
					{
						$customerEmail	= $OrderRs[0]->bill_email;
					}
					
					$orderNo		= 'OR'.$OrderID;
					$customerName 	= '';
					if((isset($OrderRs[0]->bill_first_name) && $OrderRs[0]->bill_first_name!=''))
					{
						$customerName	= $OrderRs[0]->bill_first_name." ";
					}
					
					if(isset($OrderRs[0]->bill_last_name) && $OrderRs[0]->bill_last_name!='')
					{
						$customerName = $customerName.$OrderRs[0]->bill_last_name;
					}
					
					$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
					
					/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
					
					$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
					OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
					
					$success = "0";
				}

			} else {
			    // $this->error = $response;
				$success = "0";
			}
		} else {
		    // $response = $createCheckoutRequest->getValidationErrorsAsHtml();
			$success = "0";
		}
		
		$message = "";
		if($success == "0"){
			$message = 'Error in Processing Request, Please try again.';
		}
		
		return response()->json(array('success' => $success, 'token'=>$token,'message'=>$message));
	}

	/* public function Success_Phoneorder_Express(Request $request)
	{
		// dd($request->all());
		if($request->has('status') && $request->status == "1") {
			if ($request->has('orderToken') && $request->orderToken != '') {

			    	$deferredPaymentAuthRequest = new AfterpayDeferredPaymentAuthRequest([
				        'token' => urlencode($request->orderToken)
				    ]);

					$merchant = new AfterpayMerchantAccount();
					$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
					    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
							->setApiEnvironment($this->TRANSACTION_MODE)
					    	->setCountryCode('US');

				    if (!is_null($merchant)) {
				        $deferredPaymentAuthRequest->setMerchantAccount($merchant);
				    }

				    $deferredPaymentAuthRequest->send();

				    $deferredPaymentAuthResponse = $deferredPaymentAuthRequest->getResponse();
				    $repsonse = $deferredPaymentAuthResponse->getParsedBody();

					/////////// Log Start ////////////
					// $cur_date = date("Y-m-d");
					// $myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
					// if(@fopen($myFile, 'a+'))
					// {
						// $fh = fopen($myFile, 'a+');

						// $stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->orderToken) . chr(13) . chr(13) ;
						// $stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

						// fwrite($fh, $stringData);
						// fclose($fh);
					// }
					/////////// Log End ////////////
			
					Session::put('phoneorder_detail.Afterpay.AP_Auth_Token',$repsonse->token);
					Session::put('phoneorder_detail.Afterpay.AP_Auth_ID',$repsonse->id);
					Session::put('phoneorder_detail.Afterpay.AP_Auth_Status',$repsonse->status);
		
				    if($deferredPaymentAuthRequest->getResponse()->isApproved() && $repsonse->paymentState == "AUTH_APPROVED" && $repsonse->token != "") {
						Session::put('phoneorder_detail.Afterpay.AP_Auth_Amt',$repsonse->originalAmount->amount);
						Session::put('phoneorder_detail.Afterpay.AP_Auth_Currency',$repsonse->originalAmount->currency);
						
						$customer_id = Session::get('phoneorder_detail.customer_id');
						$order_id = Session::get('phoneorder_detail.order_id');
						
						if($customer_id > 0) {
							$payment_gateway_response = "Auth Response::".json_encode($repsonse);
							$updAray = array (
												'payment_gateway_response' 	=> $payment_gateway_response,
												'afterpay_transaction_id' 	=> $repsonse->id
											  );

							$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
							
							$this->DoPayment_Phoneorder_Express();
							$capturePayment = url('afterpay/dopayment_phoneorder/'.$repsonse->id);
							return redirect($capturePayment);
							
							$message = 'Error in Processing Request, Please try again.';
							return response()->json(array('success' => "1",'message'=>$message));
						} else {

							//order order not confirmed by customer
							//status >> CANCELLED
							$transaction_info = "This transaction has been Declined.";
							$updAray = array (
												'status' 	   				=> 'Declined',
												'transaction_info' 			=> $transaction_info,
												'payment_gateway_response' 	=> $transaction_info
											  );
											  
							$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);	
							
							$message = 'Error in Processing Request, Please try again.';
							return response()->json(array('success' => "0",'message'=>$message));

						}
				    } else {
						$transaction_info = "This transaction has been Declined.";
						$Payment_response = json_encode($repsonse);
						
						$updAray = array (
											'status' 	   				=> 'Declined',
											'transaction_info' 			=> $transaction_info,
											'payment_gateway_response' 	=> $Payment_response
										  );

						$order_id = Session::get('phoneorder_detail.order_id');
						$updOrder = Order::Where("orders_id","=",$order_id)->update($updAray);	

						$message = 'Error in Processing Request, Please try again.';
						return response()->json(array('success' => "0",'message'=>$message));
				    }
			}
		} else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> "This transaction has been Declined by User."
							  );
							  
			$order_id = Session::get('phoneorder_detail.order_id');
			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
				
			$message = 'Error in Processing Request, Please try again.';
			return response()->json(array('success' => "0",'message'=>$message));
		}

		// dd('Success', $request->all(), $this->order, $this->error);
	} */
	
	public function Success_Phoneorder_Express(Request $request)
	{
		if(isset($request->status) && $request->status == "1") {
			if (isset($request->orderToken) && $request->orderToken != '') {
					$customer_id = Session::get('phoneorder_detail.customer_id');
					$order_id = Session::get('phoneorder_detail.order_id');
					
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
						$customerName	= $this->transliterate($sessionDetails['first_name'])." ";
					}
					
					if(isset($sessionDetails['last_name']) && $sessionDetails['last_name'] != '')
					{
						$customerName = $customerName.$this->transliterate($sessionDetails['last_name']);
					}
					
					if( (Session::has('phoneorder_detail.order_amt')) && Session::get('phoneorder_detail.order_amt') > 0) {
						$Payment_Amount = Session::get('phoneorder_detail.order_amt');
					}else{
						Session::flash('error','Error in Processing Request, Please try again.');								
						return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
					}

			    	$deferredPaymentAuthRequest = new AfterpayDeferredPaymentAuthRequest([
				        'token' => urlencode($request->orderToken)
				    ]);

					$merchant = new AfterpayMerchantAccount();
					$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
					    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
							->setApiEnvironment($this->TRANSACTION_MODE)
					    	->setCountryCode('US');

				    if (!is_null($merchant)) {
				        $deferredPaymentAuthRequest->setMerchantAccount($merchant);
				    }
					
					$deferredPaymentAuthRequest->setAmount($Payment_Amount, 'USD');
				    $deferredPaymentAuthRequest->send();

				    $deferredPaymentAuthResponse = $deferredPaymentAuthRequest->getResponse();
				    $repsonse = $deferredPaymentAuthResponse->getParsedBody();
					
					// echo "<pre>1";print_r($repsonse);exit;

					/////////// Log Start ////////////
					// $cur_date = date("Y-m-d");
					// $myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
					// if(@fopen($myFile, 'a+'))
					// {
						// $fh = fopen($myFile, 'a+');

						// $stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->orderToken) . chr(13) . chr(13) ;
						// $stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

						// fwrite($fh, $stringData);
						// fclose($fh);
					// }
					/////////// Log End ////////////
					
					if(!isset($repsonse->token) || $repsonse->token == ""){
						Session::flash('error','Error in Processing Request, Please try again.');								
						return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
					}
			
					Session::put('phoneorder_detail.Afterpay.AP_Auth_Token',$repsonse->token);
					Session::put('phoneorder_detail.Afterpay.AP_Auth_ID',$repsonse->id);
					Session::put('phoneorder_detail.Afterpay.AP_Auth_Status',$repsonse->status);
		
				    if($deferredPaymentAuthRequest->getResponse()->isApproved() && $repsonse->paymentState == "AUTH_APPROVED" && $repsonse->token != "") {
						Session::put('phoneorder_detail.Afterpay.AP_Auth_Amt',$repsonse->originalAmount->amount);
						Session::put('phoneorder_detail.Afterpay.AP_Auth_Currency',$repsonse->originalAmount->currency);
						
						if($customer_id > 0) {
							$payment_gateway_response = "Auth Response::".json_encode($repsonse);
							$updAray = array (
												'payment_gateway_response' 	=> $payment_gateway_response,
												'afterpay_transaction_id' 	=> $repsonse->id
											  );

							$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
							
							$capturePayment = url('afterpay/dopayment_phoneorder_express/'.$repsonse->id);
							return redirect($capturePayment);
						} else {

							//order order not confirmed by customer
							//status >> CANCELLED
							$transaction_info = "This transaction has been Declined.";
							$updAray = array (
												'status' 	   				=> 'Declined',
												'transaction_info' 			=> $transaction_info,
												'payment_gateway_response' 	=> $transaction_info,
												'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
											  );
											  
							$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
							addLog("AfterPayOrderDeclined - 1681 - ".$order_id,$updAray);
							$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
							OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
							
							/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
							OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
							
							$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
							OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

							Session::flash('error','Error in Processing Request, Please try again.');								
							return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));

						}
				    } else {
						$transaction_info = "This transaction has been Declined.";
						$Payment_response = json_encode($repsonse);
						
						$updAray = array (
											'status' 	   				=> 'Declined',
											'transaction_info' 			=> $transaction_info,
											'payment_gateway_response' 	=> $Payment_response,
											'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
										  );

						// $order_id = Session::get('phoneorder_detail.order_id');
						$updOrder = Order::Where("orders_id","=",$order_id)->update($updAray);
						addLog("AfterPayOrderDeclined - 1707 - ".$order_id,$updAray);
						$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
						OmanisendRequest('63c113f03d897d0020e8b31e',$Data);	
						
						/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
						OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
						
						$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
						OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

						$err_msg = 'Error in Processing Request, Please try again.';
						if($repsonse->paymentState == "AUTH_DECLINED"){	//card declined
							$err_msg = 'Error, your Afterpay purchase was Declined, Please try again or select another payment method.';
						}
						Session::flash('error',$err_msg);								
						return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
				    }
			}
		} else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> "This transaction has been Declined by User.",
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
							  
			$order_id = Session::get('phoneorder_detail.order_id');
			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
			addLog("AfterPayOrderDeclined - 1736 - ".$order_id,$updAray);
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
				$customerName	= $this->transliterate($sessionDetails['first_name'])." ";
			}
			
			if(isset($sessionDetails['last_name']) && $sessionDetails['last_name']!='')
			{
				$customerName = $customerName.$this->transliterate($sessionDetails['last_name']);
			}
			
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
				
			Session::flash('error','Error in Processing Request, Please try again.');								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
		}

		// dd('Success', $request->all(), $this->order, $this->error);
	}

	public function DoPayment_Phoneorder_Express(Request $request)
	{
		$customer_id = Session::get('phoneorder_detail.customer_id');
		$order_id = Session::get('phoneorder_detail.order_id');
		$Payment_Amount = NumberFormat(Session::get('phoneorder_detail.order_amt'));
		$Payment_Currency  = 'USD';
		
		$requestId = AfterpayStringHelper::generateUuid();
		// dd($requestId);
	    $capturePaymentRequest = new AfterpayDeferredPaymentCaptureRequest([
	        'requestId' => $requestId,
	        'amount' => [$Payment_Amount, 'USD']
	    ]);
	    if(!is_numeric($request[ 'order_id' ])) {
			Session::flash('error','Error in Processing Request, Please try again.');								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
	    }
	    $capturePaymentRequest->setOrderId($request->order_id);

		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');

        $capturePaymentRequest->setMerchantAccount($merchant);

		$merchantReference = "OR".$order_id;
        $capturePaymentRequest->setMerchantReference($merchantReference);

		/////////// Log Start ////////////
		/*$cur_date = date("Y-m-d");
		$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
		if(@fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->order_id) . chr(13) . chr(13) ;
			$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

			fwrite($fh, $stringData);
			fclose($fh);
		}*/
		/////////// Log End ////////////

	    $capturePaymentRequest->send();

	    $capturePaymentResponse = $capturePaymentRequest->getResponse();
	    $repsonse = $capturePaymentResponse->getParsedBody();
	    // dd($capturePaymentResponse);

		$payment_gateway_response = json_encode($repsonse);
		
		// echo "<pre>";print_r($repsonse);
	    if((isset($repsonse->status) && $repsonse->status == "APPROVED") && ($repsonse->paymentState == "CAPTURED" || $repsonse->paymentState == "PARTIALLY_CAPTURED") ) {
	    	$transaction_info = "This transaction has been approved.";

			$order_result = Order::select('payment_gateway_response')->where("orders_id","=",$order_id)->get();
			if($order_result && $order_result->count() > 0) {
				$payment_gateway_response = $order_result[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
			}

			$updAray = array (
								'pay_status' 	   			=> 'Paid',
								'status' 	   				=> 'Pending',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'afterpay_transaction_id' 	=> $repsonse->id,
								'phoneorder_paymentdate' => date("Y-m-d H:i:s"),
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );

			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);	

			//stock,other related changes start
			$response_arr = $this->PhoneorderPaymentSuccess('Afterpay');
			if($response_arr['success'] == "1"){
				Session::flash('success',$response_arr['err_msg']);
			}else{
				Session::flash('error',$response_arr['err_msg']);
			}	
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
	    } else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);	
			addLog("AfterPayOrderDeclined - 1864 - ".$order_id,$updAray);				  
			$Message = 'Error in Processing Request, Please try again.';				
			if(isset($repsonse->errorId)){
				$Message = $repsonse->message;
			}
			Session::flash('error', $Message);								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));

	    }
	}

	public function SetAfterpay_Express(Request $request)
	{
		/*
		if($this->Is_WholeSaler_Allow() == false)
		{
			return redirect('/shoppingcart');
		}
		*/
		Session::forget('ShoppingCart.OrderID');
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
		{
			Session::forget('ShoppingCart');
			$message = 'Error in Processing Request, Please try again.';
			return response()->json(array('success' => "0", 'token'=>"",'message'=>$message));
		}

		/*$pingRequest = new AfterpayPingRequest();
		$this->tryPing($pingRequest);*/

		$merchant = new AfterpayMerchantAccount();

		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');


	    $getConfigurationRequest = new AfterpayGetConfigurationRequest();

		$getConfigurationRequest->setMerchantAccount($merchant);

		$getConfigurationRequest->send();

		$body = $getConfigurationRequest->getResponse()->getParsedBody();

		// dd($body);exit;
		/* if($body && isset($body->minimumAmount))
		{
			Session::put('ShoppingCart.Afterpay.Min_AP_AMT',($body->minimumAmount->amount));
			Session::put('ShoppingCart.Afterpay.Max_AP_AMT',($body->maximumAmount->amount));
		} */		
		
		$Payment_Amount  = NumberFormat($this->GetNetTotal());
		$Payment_Currency  = 'USD';
		// $payload['merchantReference'] = "OR".Session::get('ShoppingCart.OrderID');
		$setConsumer = [];
		if( (Session::has('sess_icustomerid')) && Session::get('sess_icustomerid') > 0) {
			$customer = Customer::where('customer_id', '=', Session::get('sess_icustomerid'))->get();
			if($customer && count($customer) > 0) {
				$setConsumer['givenNames'] = $customer[0]['first_name'];		//optional
				$setConsumer['surname'] = $customer[0]['last_name'];		//optional
				$setConsumer['email'] = $customer[0]['email'];	//required
			} else {
				// $message = 'Error in Processing Request, Please try again.';
				// return response()->json(array('success' => "0", 'token'=>"",'message'=>$message));
			}
		} else {
			// $message = 'Error in Processing Request, Please try again.';
			// return response()->json(array('success' => "0", 'token'=>"",'message'=>$message));
		}

		// $setMode['mode'] = "express";
		$setMerchant['popupOriginUrl'] = request()->headers->get('referer');
		
		// echo "<pre>";print_r(Auth::user());exit;
		$setBilling = [];
		$setShipping = [];
		if(Auth::user()){
			$setBilling['name'] = Auth::user()->first_name." ".Auth::user()->last_name;
			$setBilling['line1'] = Auth::user()->address1;
			$setBilling['area1'] = Auth::user()->city;
			$setBilling['region'] = Auth::user()->state;
			$setBilling['postcode'] = Auth::user()->zip;
			$setBilling['countryCode'] = Auth::user()->country;
			$setBilling['phoneNumber'] = Auth::user()->phone;
			
			$setShipping['name'] = Auth::user()->first_name." ".Auth::user()->last_name;
			$setShipping['line1'] = Auth::user()->address1;
			$setShipping['area1'] = Auth::user()->city;
			$setShipping['region'] = Auth::user()->state;
			$setShipping['postcode'] = Auth::user()->zip;
			$setShipping['countryCode'] = Auth::user()->country;
			$setShipping['phoneNumber'] = Auth::user()->phone;
		}
		
		// $tempBillingAdd  = Session::get('ShoppingCart.BillingAddress');
		// $tempShippingAdd = Session::get('ShoppingCart.ShippingAddress');

		// $setBilling = [];
		// $setBilling['name'] = $tempBillingAdd['first_name']." ".$tempBillingAdd['last_name'];
		// $setBilling['line1'] = $tempBillingAdd['address1'];
		// $setBilling['area1'] = $tempBillingAdd['city'];
		// $setBilling['region'] = $tempBillingAdd['state'];
		// $setBilling['postcode'] = $tempBillingAdd['zip'];
		// $setBilling['countryCode'] = $tempBillingAdd['country'];
		// $setBilling['phoneNumber'] = $tempBillingAdd['phone'];

		// $setShipping = [];
		// $setShipping['name'] = $tempShippingAdd['first_name']." ".$tempShippingAdd['last_name'];
		// $setShipping['line1'] = $tempShippingAdd['address1'];
		// $setShipping['area1'] = $tempShippingAdd['city'];
		// $setShipping['region'] = $tempShippingAdd['state'];
		// $setShipping['postcode'] = $tempShippingAdd['zip'];
		// $setShipping['countryCode'] = $tempShippingAdd['country'];
		// $setShipping['phoneNumber'] = $tempShippingAdd['phone'];


		//Items details

		$setItems = [];
		$ShopCart = Session::get('ShoppingCart.Cart');
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
			$setItems[] = [
		        'name' => $CartItem['ProductName'],
		        'sku' => $CartItem['SKU'],
		        'quantity' => $CartItem['Qty'],
		        'pageUrl' => $CartItem['Prod_URL'],
		        'price' => [$ItemPrice, 'USD']
		    ];
		}

		/**
		 * Method B:
		 *
		 * Instantiating an empty Request class, then setting the values of each field using the individual
		 * setter methods. If Automatic Validation is disabled, you can load in all of the data and then iterate over
		 * the list of errors, rather than only catching the first.
		 */
		// dd($setConsumer, $setBilling, $setShipping, $setItems, $setMerchant);
		\Afterpay\SDK\Model::setAutomaticValidationEnabled(false);

		$createCheckoutRequest = new AfterpayCreateCheckoutRequest();
			
		if(!empty($setConsumer)){
			$createCheckoutRequest->setConsumer($setConsumer);
		}
		
		if(!empty($setBilling) && !empty($setShipping)){
			$createCheckoutRequest->setBilling($setBilling)->setShipping($setShipping);
		}
		
		$createCheckoutRequest
		    ->setAmount($Payment_Amount, 'USD')
		    ->setMode("EXPRESS")
		    /*->setCourier([
		        'shippedAt' => '2019-01-01T00:00:00+10:00',
		        'name' => 'FedEx',
		        'tracking' => 'AA0000000000000',
		        'priority' => 'STANDARD'
		    ])*/
		    ->setItems($setItems)
		    /*->setDiscounts([
		        [
		            'displayName' => '20% off SALE',
		            'amount' => [ '24.00', 'USD' ]
		        ]
		    ])*/
		    ->setMerchant($setMerchant)
		    /*->setTaxAmount('0.00', 'USD')
		    ->setShippingAmount('0.00', 'USD')*/
		;
	
		$token = "";
		if ($createCheckoutRequest->isValid()) {
			$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
			    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
					->setApiEnvironment($this->TRANSACTION_MODE)
			    	->setCountryCode('US');

			$createCheckoutRequest->setMerchantAccount($merchant);

		    $createCheckoutRequest->send();

		    // $response = $createCheckoutRequest->getRawLog();

		    $createCheckoutResponse = $createCheckoutRequest->getResponse();
    		$response = $createCheckoutResponse->getParsedBody();
				// dd($response);
			if ($createCheckoutResponse->isSuccessful()) {
				// dd($response->redirectCheckoutUrl);
				if(isset($response->token) && $response->token != ""){
					$redirect = $response->redirectCheckoutUrl;
					$token = $response->token;
					$expires = $response->expires;
					$success = "1";
					
					/*$updAray = array ('status' => 'Sent To AfterPay');

					$order_id = Session::get('ShoppingCart.OrderID');
					$uporderres = Order::Where("orders_id","=",$order_id)
										->update($updAray);
					*/
					Session::put('ShoppingCart.AfterPay.Checkout_Token',$token);

				}else{
					$transaction_info = "This transaction has been Declined.";
					$Payment_response = json_encode($response);

					/*$updAray = array (
										'status' 	   			=> 'Declined',
										'transaction_info' 			=> $transaction_info,
										'payment_gateway_response' 	=> $Payment_response
									  );

					$order_id = Session::get('ShoppingCart.OrderID');
					$updOrder = Order::Where("orders_id","=",$order_id)
										->update($updAray);		
					*/
					$success = "0";
				}

			} else {
			    // $this->error = $response;
				$success = "0";
			}
		} else {
		    // $response = $createCheckoutRequest->getValidationErrorsAsHtml();
			$success = "0";
		}
		
		$message = "";
		if($success == "0"){
			$message = 'Error in Processing Request, Please try again.';
		}
		
		return response()->json(array('success' => $success, 'token'=>$token,'message'=>$message));

	}

	public function Billing_Checkout_Express(Request $request)
	{
		//get token details and set shipping in session
		// echo $request->status."-=--".$request->orderToken;exit;
		
		if(isset($request->status) && $request->status == "2") 
		{
			if (isset($request->orderToken) && $request->orderToken != '') {
				// billing_checkout_express
				if(Session::has('ShoppingCart.AfterPay.Checkout_Token') && Session::get('ShoppingCart.AfterPay.Checkout_Token') != ""){
					if($request->orderToken != Session::get('ShoppingCart.AfterPay.Checkout_Token')){
						Session::flash('PlaceOrderError','Token Mismatch, Error in Processing Request. Please try again.');								
						Session::forget('ShoppingCart.AfterPay.Checkout_Token');
						return redirect('checkout');
					}
				}
				
				$merchant = new AfterpayMerchantAccount();

				$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
						->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
						->setApiEnvironment($this->TRANSACTION_MODE)
						->setCountryCode('US');

			
				$getCheckoutRequest = new AfterpayGetCheckoutRequest();
				$getCheckoutRequest->setCheckoutToken($request->orderToken);
				
				if ($getCheckoutRequest->isValid()) 
				{
					$getCheckoutRequest->setMerchantAccount($merchant);
					
					$getCheckoutRequest->send();
					
					$getCheckoutResponse = $getCheckoutRequest->getResponse();
					$response = $getCheckoutResponse->getParsedBody();

					if(checkBlockedUser($response->consumer->email,0,'AfterpayGuest')==true)
					{
						Session::flash('PlaceOrderError',config('message.Register.Blocked'));
						return redirect('/shoppingcart/view');						
					}
			
					 //echo "<pre>";print_r($getCheckoutResponse->isSuccessful());exit;
				//	 return 1;
					if ($getCheckoutResponse->isSuccessful() && isset($response->token) && $response->token != "") 
					{
						//$tempBillingAdd  = Session::get('ShoppingCart.BillingAddress');
						$tempShippingAdd = Session::get('ShoppingCart.ShippingAddress');
					//	echo "<pre>"; print_r($tempShippingAdd); exit;
						if($response->shipping->postcode!=$tempShippingAdd['zip'] || $response->shipping->countryCode!=$tempShippingAdd['country'] || $response->shipping->region!=$tempShippingAdd['state'])
						{
							Session::flash('PlaceOrderError','Address mismatch error, Please try again.');	
							Session::forget('ShoppingCart.AfterPay.Checkout_Token');	
							$request->session()->forget('ShoppingCart.AfterPay.Checkout_Token');
							
							Session::forget('Afterpay.Min_AP_AMT');
							Session::forget('Afterpay.Max_AP_AMT');	
							//echo "test"; exit;					
							return 0;
							exit;
							
						}
						else
						{
						    return 1;
						    exit;
						}
					}
					else
					{
						Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');	
						Session::forget('ShoppingCart.AfterPay.Checkout_Token');
						$request->session()->forget('ShoppingCart.AfterPay.Checkout_Token');								
						return 0;
						exit;
					}
					
				}		
			}
			else
			{
				Session::forget('ShoppingCart.AfterPay.Checkout_Token');
				Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');	
				$request->session()->forget('ShoppingCart.AfterPay.Checkout_Token');								
				return redirect('checkout');
			}
		}	
		elseif(isset($request->status) && $request->status == "1") {
			if (isset($request->orderToken) && $request->orderToken != '') {
				// billing_checkout_express
				if(Session::has('ShoppingCart.AfterPay.Checkout_Token') && Session::get('ShoppingCart.AfterPay.Checkout_Token') != ""){
					if($request->orderToken != Session::get('ShoppingCart.AfterPay.Checkout_Token')){
						Session::flash('PlaceOrderError','Token Mismatch, Error in Processing Request. Please try again.');								
						return redirect('checkout');
					}
				}
				
				$merchant = new AfterpayMerchantAccount();

				$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
						->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
						->setApiEnvironment($this->TRANSACTION_MODE)
						->setCountryCode('US');

				// $getConfigurationRequest = new AfterpayGetConfigurationRequest();
				// $getConfigurationRequest->setMerchantAccount($merchant);
				// $getConfigurationRequest->send();
				// $body = $getConfigurationRequest->getResponse()->getParsedBody();
				
				$getCheckoutRequest = new AfterpayGetCheckoutRequest();
				$getCheckoutRequest->setCheckoutToken($request->orderToken);
				
				if ($getCheckoutRequest->isValid()) {
					$getCheckoutRequest->setMerchantAccount($merchant);
					
					$getCheckoutRequest->send();
					
					$getCheckoutResponse = $getCheckoutRequest->getResponse();
					$response = $getCheckoutResponse->getParsedBody();

					if(checkBlockedUser($response->consumer->email,0,'AfterpayGuest')==true)
					{
						Session::flash('PlaceOrderError',config('message.Register.Blocked'));
						return redirect('/shoppingcart/view');						
					}
			
					// echo "<pre>";print_r($response);exit;
					if ($getCheckoutResponse->isSuccessful() && isset($response->token) && $response->token != "") {
						$cst_details = [];
						if(!empty($response->shipping) && isset($response->shipping->name) && $response->shipping->name != ""){
							
							$name_arr = explode(" ",$response->shipping->name);
							if(isset($name_arr[0]) && $name_arr[0]!='')
							{
								$cst_details['ship_fname'] = $this->transliterate($name_arr[0]);
							}
							if(isset($name_arr[1]) && $name_arr[1]!='')
							{
								$cst_details['ship_lname'] = $this->transliterate($name_arr[1]);
							}
							if(isset($response->shipping->line1) && $response->shipping->line1!='')
							{
								$cst_details['ship_address1'] = $this->transliterate($response->shipping->line1);
							}
							if(isset($response->shipping->line2) && $response->shipping->line2!='')
							{
								$cst_details['ship_address2'] = $response->shipping->line2;
							}
							if(isset($response->shipping->area1) && $response->shipping->area1!='')
							{
								$cst_details['ship_city'] = $response->shipping->area1;
							}
							if(isset($response->shipping->region) && $response->shipping->region!='')
							{
							$cst_details['ship_state'] = $response->shipping->region;
							}
							if(isset($response->shipping->postcode) && $response->shipping->postcode!='')
							{
							$cst_details['ship_zip'] = $response->shipping->postcode;
							}
							if(isset($response->shipping->phoneNumber) && $response->shipping->phoneNumber!='')
							{
							$cst_details['ship_phone'] = $response->shipping->phoneNumber;				
							}
							if(isset($response->shipping->countryCode) && $response->shipping->countryCode!='')
							{
							$cst_details['ship_country'] = $response->shipping->countryCode;				
							}
							
						}
						
						if(!empty($response->consumer) && isset($response->consumer->email) && $response->consumer->email != ""){
							$cst_details['email'] = $response->consumer->email;
							$cst_details['fName'] = $response->consumer->givenNames;
							$cst_details['lName'] = $response->consumer->surname;
						}
						if(!empty($cst_details)){
							Session::put('ShoppingCart.AfterPay.Customer_Details',$cst_details);	
						}
						return redirect('checkout/AP');
					}else{
						Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
						return redirect('checkout');
					}
					
				}		
			}else{
				Session::forget('ShoppingCart.AfterPay.Checkout_Token');
				Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
				return redirect('checkout');
			}
		}else{
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('checkout');
		}
		
	}
	
	public function Success_Express(Request $request)
	{
		$order_id = 0;
		$customer_id = 0;
		if(isset($request->ordernoid) && $request->ordernoid!='')
		{
			$OreDStr = explode('~',$request->ordernoid); 
			$order_id = $OreDStr[0];
			$customer_id = $OreDStr[1];
		}
		
		//$myFile = '/home/maxaroma/public_html/Logs/Walmart/AfterPayLog.txt';
		$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/AfterPayLog.txt';
		
			
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  Sucess Express In Afterpay Controller.\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}
		
		if(empty($customer_id) && $customer_id<=0)
		{
		$Message = 'Error in Processing Request, Please try again.';				
		Session::flash('PlaceOrderError', $Message);								
		return redirect('checkout');
		}
		/*if((!Session::has('sess_icustomerid')) && Session::get('sess_icustomerid') <= 0) 
		{
		$Message = 'Error in Processing Request, Please try again.';				
		Session::flash('PlaceOrderError', $Message);								
		return redirect('checkout');
		}*/
		
		$Payment_Amount  = NumberFormat($this->GetNetTotal());
		$Payment_Currency  = 'USD';
		
		
		if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID') > 0)
		{
			$order_id = Session::get('ShoppingCart.OrderID');
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
			$customerName	= $this->transliterate($sessionDetails['first_name'])." ";
		}
		
		if(isset($sessionDetails['last_name']) && $sessionDetails['last_name']!='')
		{
			$customerName = $customerName.$this->transliterate($sessionDetails['last_name']);
		}
		
		
		if(!isset($request->ap_psChecksum) || $request->ap_psChecksum == ""){
			
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  Sucess Express In Afterpay Controller6.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			Session::flash('PlaceOrderError','Error in Processing Request. Please try again.');	
			
			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);
			addLog("AfterPayOrderDeclined - 2400 - ".$order_id,$updAray);					
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
								
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');															
			return redirect('checkout');
		}
		// dd($request->all());
		if(!Session::has('ShoppingCart.AfterPay.Checkout_Token') || Session::get('ShoppingCart.AfterPay.Checkout_Token') == ""){
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  Sucess Express In Afterpay Controller7.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			
			$transaction_info = "This transaction has been Declined.";
			
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	
			addLog("AfterPayOrderDeclined - 2433 - ".$order_id,$updAray);					
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
								
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');
			Session::flash('PlaceOrderError','Token Mismatch, Error in Processing Request. Please try again.');								
			return redirect('checkout');
		}
		
		if($Payment_Amount <= 0){
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  Sucess Express In Afterpay Controller8.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	
			addLog("AfterPayOrderDeclined - 2464 - ".$order_id,$updAray);				
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
							
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('checkout');
		}
		
		$orderToken = Session::get('ShoppingCart.AfterPay.Checkout_Token');
		
		$deferredPaymentAuthRequest = new AfterpayImmediatePaymentCapture([
			'token' => urlencode($orderToken),
			'isCheckoutAdjusted' => true,
			'paymentScheduleChecksum' => $request->ap_psChecksum,
		]);

		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
				->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
				->setCountryCode('US');

		if (!is_null($merchant)) {
			$deferredPaymentAuthRequest->setMerchantAccount($merchant);
		}
		
		$merchantReference = "OR".$order_id;
        $deferredPaymentAuthRequest->setMerchantReference($merchantReference);
		
		$setItems = [];
		$ShopCart = Session::get('ShoppingCart.Cart');
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
			$setItems[] = [
		        'name' => $CartItem['ProductName'],
		        'sku' => $CartItem['SKU'],
		        'quantity' => $CartItem['Qty'],
		        'pageUrl' => $CartItem['Prod_URL'],
		        'price' => [$ItemPrice, 'USD']
		    ];
		} 
		$deferredPaymentAuthRequest->setItems($setItems);
		//$order_id = Session::get('ShoppingCart.OrderID');
		
		
		
		
		
		if ($deferredPaymentAuthRequest->isValid()) 
		{
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  Sucess Express In Afterpay Controller4.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$deferredPaymentAuthRequest->setAmount($Payment_Amount, 'USD');
			$deferredPaymentAuthRequest->send();

			$deferredPaymentAuthResponse = $deferredPaymentAuthRequest->getResponse();
			$repsonse = $deferredPaymentAuthResponse->getParsedBody();
			
			/////////// Log Start ////////////
			$cur_date = date("Y-m-d");
			/*$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
			if(@fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData .= chr(13) . chr(13) . 'Capture REQUEST == ' . serialize($orderToken) . chr(13) . chr(13) ;
				$stringData .= chr(13) . chr(13) . 'Capture RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

				fwrite($fh, $stringData);
				fclose($fh);
			}*/
			$payment_gateway_response = json_encode($repsonse);
			if((isset($repsonse->status) && $repsonse->status == "APPROVED") && ($repsonse->paymentState == "CAPTURED" || $repsonse->paymentState == "PARTIALLY_CAPTURED") ) 
			{

				$transaction_info = "This transaction has been approved.";

				$order_result = Order::select('payment_gateway_response')->where("orders_id","=",$order_id)->get();
				if($order_result && count($order_result) > 0) {
					$payment_gateway_response = $order_result[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
				}
				
				
				
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  Sucess Express In Afterpay Controller1.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}
				
				$updAray = array (
									'pay_status' 	   			=> 'Paid',
									'status' 	   				=> 'Pending',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_gateway_response,
									'afterpay_transaction_id' 	=> $repsonse->id,
									'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
								  );

				$uporderres = Order::Where("orders_id","=",$order_id)
									->update($updAray);	
				return redirect('order-receipt');
			} else {
				
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  Sucess Express In Afterpay Controller2.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}
				$transaction_info = "This transaction has been Declined.";
				$updAray = array (
									'status' 	   				=> 'Declined',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_gateway_response,
									'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
								  );
					
				$uporderres = Order::Where("orders_id","=",$order_id)
									->update($updAray);	
				addLog("AfterPayOrderDeclined - 2608 - ".$order_id,$updAray);				  
				$Message = 'Error in Processing Request, Please try again.';				
				if(isset($repsonse->errorId)){
					$Message = $repsonse->message;
				}
				
				$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
				
				/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
				
				$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
				
				Session::forget('ShoppingCart.AfterPay.Checkout_Token');
				Session::flash('PlaceOrderError', $Message);								
				return redirect('checkout');

			}
		}
		else{
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  Sucess Express In Afterpay Controller3.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	
			addLog("AfterPayOrderDeclined - 2647 - ".$order_id,$updAray);
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('	63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

			$Message = 'Error in Processing Request, Please try again.';				
			Session::flash('PlaceOrderError', $Message);
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');								
			return redirect('checkout');
			}

		// dd('Success', $request->all(), $this->order, $this->error);
	}
	
	public function DoPayment_Express(Request $request)
	{
		$Payment_Amount  = NumberFormat($this->GetNetTotal());
		$Payment_Currency  = 'USD';
		$requestId = AfterpayStringHelper::generateUuid();
		
		if($Payment_Amount <= 0){
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('checkout');
		}
		
		// dd($requestId);
	    $capturePaymentRequest = new AfterpayImmediatePaymentCapture([
	        'requestId' => $requestId,
	        'amount' => [$Payment_Amount, 'USD']
	    ]);
	    if(!is_numeric($request['order_id'])) {
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('checkout');
	    }
	    $capturePaymentRequest->setOrderId($request->order_id);

		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');

        $capturePaymentRequest->setMerchantAccount($merchant);

		$merchantReference = "OR".Session::get('ShoppingCart.OrderID');
        $capturePaymentRequest->setMerchantReference($merchantReference);

		/////////// Log Start ////////////
		/*$cur_date = date("Y-m-d");
		$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
		if(@fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->order_id) . chr(13) . chr(13) ;
			$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

			fwrite($fh, $stringData);
			fclose($fh);
		}*/
		/////////// Log End ////////////

		$order_id = Session::get('ShoppingCart.OrderID');
		if ($capturePaymentRequest->isValid()) {
			$capturePaymentRequest->send();

			$capturePaymentResponse = $capturePaymentRequest->getResponse();
			$repsonse = $capturePaymentResponse->getParsedBody();
			// dd($repsonse);exit;

			$payment_gateway_response = json_encode($repsonse);

			if((isset($repsonse->status) && $repsonse->status == "APPROVED") && ($repsonse->paymentState == "CAPTURED" || $repsonse->paymentState == "PARTIALLY_CAPTURED") ) {
				$transaction_info = "This transaction has been approved.";

				$order_result = Order::select('payment_gateway_response')->where("orders_id","=",$order_id)->get();
				if($order_result && count($order_result) > 0) {
					$payment_gateway_response = $order_result[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
				}

				$updAray = array (
									'pay_status' 	   			=> 'Paid',
									'status' 	   				=> 'Pending',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_gateway_response,
									'afterpay_transaction_id' 	=> $repsonse->id,
									'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
								  );

				$uporderres = Order::Where("orders_id","=",$order_id)
									->update($updAray);	

				return redirect('order-receipt');
			} else {

				$transaction_info = "This transaction has been Declined.";
				$updAray = array (
									'status' 	   				=> 'Declined',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_gateway_response,
									'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
								  );
					
				$uporderres = Order::Where("orders_id","=",$order_id)
									->update($updAray);	
				addLog("AfterPayOrderDeclined - 2755 - ".$order_id,$updAray);				  
				$Message = 'Error in Processing Request, Please try again.';				
				if(isset($repsonse->errorId)){
					$Message = $repsonse->message;
				}
				Session::flash('PlaceOrderError', $Message);								
				return redirect('checkout');

			}
		}else{
			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	
			addLog("AfterPayOrderDeclined - 2774 - ".$order_id,$updAray);
			$Message = 'Error in Processing Request, Please try again.';				
			Session::flash('PlaceOrderError', $Message);								
			return redirect('checkout');
		}
	}
	
	
	public function DoPayment_Express_BTM(Request $request)
	{	//from the bottom express checkout button
		
		
		$order_id = 0;
		$customer_id = 0;
		if(isset($request->ordernoid) && $request->ordernoid!='')
		{
			$OreDStr = explode('~',$request->ordernoid); 
			$order_id = $OreDStr[0];
			$customer_id = $OreDStr[1];
		}
		////$myFile = '/home/maxaroma/public_html/Logs/Walmart/AfterPayLog.txt';
		$myFile = config('global.PHYSICAL_PATH').'Logs/Walmart/AfterPayLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller1.\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}
		if(empty($customer_id) && $customer_id<=0)
		{
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller2.\n";
				fwrite($fh, $stringData);
				fclose($fh);
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
			
		$transaction_info = "This transaction has been Declined.";
		$updAray = array (
							'status' 	   				=> 'Declined',
							'transaction_info' 			=> $transaction_info,
							'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
						  );
			
		$uporderres = Order::Where("orders_id","=",$order_id)
							->update($updAray);	
		addLog("AfterPayOrderDeclined - 2843 - ".$order_id,$updAray);							
		Session::forget('ShoppingCart.AfterPay.Checkout_Token');	
		$Message = 'Error in Processing Request, Please try again.';
		
		$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
		OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
		
		/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
		OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
		
		$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
		OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
		
		Session::flash('PlaceOrderError', $Message);								
		return redirect('checkout');
		}
		
	
		$Payment_Amount  = NumberFormat($this->GetNetTotal());
		$Payment_Currency  = 'USD';
		
		/*if(!isset($request->ap_psChecksum) || $request->ap_psChecksum == ""){
			Session::flash('PlaceOrderError','Error in Processing Request. Please try again.');								
			return redirect('checkout');
		}*/
		// dd($request->all());
		if(!Session::has('ShoppingCart.AfterPay.Checkout_Token') || Session::get('ShoppingCart.AfterPay.Checkout_Token') == ""){
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller3.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);
			addLog("AfterPayOrderDeclined - 2886 - ".$order_id,$updAray);					
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
								
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');	
			Session::flash('PlaceOrderError','Token Mismatch, Error in Processing Request. Please try again.');								
			return redirect('checkout');
		}
		
		if($Payment_Amount <= 0){
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller4.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}
			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	
								
			addLog("AfterPayOrderDeclined - 2919 - ".$order_id,$updAray);
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
			
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');						
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('checkout');
		}
		
		if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID') > 0)
		{
			$order_id = Session::get('ShoppingCart.OrderID');
		}
		
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller5.\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}	
		
		$orderToken = Session::get('ShoppingCart.AfterPay.Checkout_Token');
		
		$deferredPaymentAuthRequest = new AfterpayImmediatePaymentCapture([
			'token' => urlencode($orderToken),
			'isCheckoutAdjusted' => true,
		]);

		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
				->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
				->setCountryCode('US');

		if (!is_null($merchant)) {
			$deferredPaymentAuthRequest->setMerchantAccount($merchant);
		}
		
		$merchantReference = "OR".$order_id;
        $deferredPaymentAuthRequest->setMerchantReference($merchantReference);
		
		$setItems = [];
		$ShopCart = Session::get('ShoppingCart.Cart');
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
			$setItems[] = [
		        'name' => $CartItem['ProductName'],
		        'sku' => $CartItem['SKU'],
		        'quantity' => $CartItem['Qty'],
		        'pageUrl' => $CartItem['Prod_URL'],
		        'price' => [$ItemPrice, 'USD']
		    ];
		} 
		$deferredPaymentAuthRequest->setItems($setItems);
		//$order_id = Session::get('ShoppingCart.OrderID');
		
		
		
		if ($deferredPaymentAuthRequest->isValid()) 
		{
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller6.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}	
			$deferredPaymentAuthRequest->setAmount($Payment_Amount, 'USD');
			$deferredPaymentAuthRequest->send();

			$deferredPaymentAuthResponse = $deferredPaymentAuthRequest->getResponse();
			$repsonse = $deferredPaymentAuthResponse->getParsedBody();
			
			/////////// Log Start ////////////
			$cur_date = date("Y-m-d");
			/*$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
			if(@fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData .= chr(13) . chr(13) . 'Capture REQUEST == ' . serialize($orderToken) . chr(13) . chr(13) ;
				$stringData .= chr(13) . chr(13) . 'Capture RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

				fwrite($fh, $stringData);
				fclose($fh);
			}*/
			$payment_gateway_response = json_encode($repsonse);
			if((isset($repsonse->status) && $repsonse->status == "APPROVED") && ($repsonse->paymentState == "CAPTURED" || $repsonse->paymentState == "PARTIALLY_CAPTURED") ) 
			{
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller7.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}	
				$nameval = explode(" ",$repsonse->orderDetails->shipping->name);
				$fname = '';
				if(isset($nameval[0]) && $nameval[0]!='')
				{
					$fname = $this->transliterate($nameval[0]);
				}
				$lname = '';
				if(isset($nameval[1]) && $nameval[1]!='')
				{
					$lname = $this->transliterate($nameval[1]);
				}
				$ship_address2= '';
				if(isset($repsonse->orderDetails->shipping->line2) && $repsonse->orderDetails->shipping->line2!='')
				{
					$ship_address2 = $repsonse->orderDetails->shipping->line2;
				}
				$bill_address2= '';
				if(isset($repsonse->orderDetails->shipping->line2) && $repsonse->orderDetails->shipping->line2!='')
				{
					$bill_address2 = $repsonse->orderDetails->shipping->line2;
				}
				
				$OrderUpdate = array (
				'ship_first_name' 	=> $fname,
				'ship_last_name' 	=> $lname,
				'ship_address1' 	=> $repsonse->orderDetails->shipping->line1,
				'ship_address2' 	=> $ship_address2,
				'ship_city' 		=> $repsonse->orderDetails->shipping->area1,
				'ship_zip' 			=> $repsonse->orderDetails->shipping->postcode,
				'ship_state' 		=> $repsonse->orderDetails->shipping->region,
				'ship_country' 		=> $repsonse->orderDetails->shipping->countryCode,
				'ship_phone' 		=> $repsonse->orderDetails->shipping->phoneNumber,
				'bill_first_name' 	=> $fname,
				'bill_last_name' 	=> $lname,
				'bill_address1' 	=> $repsonse->orderDetails->shipping->line1,
				'bill_address2' 	=> $bill_address2,
				'bill_city' 		=> $repsonse->orderDetails->shipping->area1,
				'bill_zip' 			=> $repsonse->orderDetails->shipping->postcode,
				'bill_state' 		=> $repsonse->orderDetails->shipping->region,
				'bill_country' 		=> $repsonse->orderDetails->shipping->countryCode,
				'bill_phone' 		=> $repsonse->orderDetails->shipping->phoneNumber
				);
				
				$uporderres = Order::Where("orders_id","=",$order_id)
									->update($OrderUpdate);	

				$transaction_info = "This transaction has been approved.";

				$order_result = Order::select('payment_gateway_response')->where("orders_id","=",$order_id)->get();
				if($order_result && count($order_result) > 0) {
					$payment_gateway_response = $order_result[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
				}

				$updAray = array (
									'pay_status' 	   			=> 'Paid',
									'status' 	   				=> 'Pending',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_gateway_response,
									'afterpay_transaction_id' 	=> $repsonse->id,
									'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
								  );

				$uporderres = Order::Where("orders_id","=",$order_id)
									->update($updAray);	
				
				
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller8.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}	
									
				return redirect('order-receipt');
			} else {
				
				if(fopen($myFile, 'a+'))
				{
					$fh = fopen($myFile, 'a+');

					$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller8.\n";
					fwrite($fh, $stringData);
					fclose($fh);
				}	
				$transaction_info = "This transaction has been Declined.";
				$updAray = array (
									'status' 	   				=> 'Declined',
									'transaction_info' 			=> $transaction_info,
									'payment_gateway_response' 	=> $payment_gateway_response,
									'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
								  );
					
				$uporderres = Order::Where("orders_id","=",$order_id)
									->update($updAray);	
				addLog("AfterPayOrderDeclined - 3127 - ".$order_id,$updAray);				  
				$Message = 'Error in Processing Request, Please try again.';				
				if(isset($repsonse->errorId)){
					$Message = $repsonse->message;
				}
				
				$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
				
				/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
				
				$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
				
				Session::flash('PlaceOrderError', $Message);								
				return redirect('checkout');

			}
		}
		else{
			
			if(fopen($myFile, 'a+'))
			{
				$fh = fopen($myFile, 'a+');

				$stringData = date("m/d/Y H:i:s")." And ".$order_id." :  DoPayment Express BTM In Afterpay Controller9.\n";
				fwrite($fh, $stringData);
				fclose($fh);
			}	
			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'BraintreeResponse'        => Session::get('ShoppingCart.AfterPay.Checkout_Token')?Session::get('ShoppingCart.AfterPay.Checkout_Token'):''
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	
			addLog("AfterPayOrderDeclined - 3166 - ".$order_id,$updAray);					
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/

			$Message = 'Error in Processing Request, Please try again.';				
			Session::flash('PlaceOrderError', $Message);								
			return redirect('checkout');
			}

	}
	

}
?>
