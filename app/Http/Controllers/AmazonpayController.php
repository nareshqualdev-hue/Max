<?php
namespace App\Http\Controllers;

use Srmklive\PayPal\Services\ExpressCheckout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Traits\CartTrait;
use App\Http\Controllers\Traits\EncryptTrait;
use AmazonPay\Client as AmazonClient;
use Client;
use App\Models\Order;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\AmazonFundLog;
use Session;
use URL;

class AmazonpayController extends Controller
{
	use CartTrait;
	use EncryptTrait;
	
	public function __construct()
	{
		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_PAYWITHAMAZON')
							->where('pm_status', '=', 'Active')
							->get(); 
		if($db_res->count() > 0)
		{
			$pm_details = unserialize($db_res[0]["pm_details"]);
			foreach ( $pm_details as $pm_var_name => $pm_var_value )
			{
				$amazon_payment_info[$pm_var_name] = $pm_var_value;
			}
			$this->MERCHANT_ID 		= $this->decrypt($amazon_payment_info['paywithamazon_Merchant_Id']);
			$this->MARKETPLACE_ID 	= $this->decrypt($amazon_payment_info['paywithamazon_Marketplace_Id']);
			$this->AWS_ACCESS_KEY 	= $this->decrypt($amazon_payment_info['paywithamazon_Access_Key_Id']);
			$this->AWS_SECRET_KEY	= $this->decrypt($amazon_payment_info['paywithamazon_Secret_Key_ID']);
			$this->CLIENT_ID		= $this->decrypt($amazon_payment_info['paywithamazon_Client_ID']);
			$this->TRANSACTION_MODE	= $amazon_payment_info['paywithamazon_Transaction_Mode'];
			$this->CURRENCY_CODE	= $amazon_payment_info['paywithamazon_Currency_Code'];
		}
			
		
		if(strtoupper(trim($this->TRANSACTION_MODE)) == 'SANDBOX')	{
			$this->JS_SERVER_URL		= 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js?sellerId='.$this->MERCHANT_ID;
			$this->API_URL			= 'https://api.sandbox.amazon.com/auth/o2/tokeninfo?access_token=';
			$this->API_PROFILE_URL	= 'https://api.sandbox.amazon.com/user/profile';
			$this->SANDBOX_VAL		= true;
		}else	{
			
			$this->JS_SERVER_URL		= 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js?sellerId='.$this->MERCHANT_ID;
			$this->API_URL			= 'https://api.amazon.com/auth/o2/tokeninfo?access_token=';
			$this->API_PROFILE_URL	= 'https://api.amazon.com/user/profile';
			$this->SANDBOX_VAL		= false;
		}	
		$config = array('merchant_id' => $this->MERCHANT_ID,
                'access_key'  => $this->AWS_ACCESS_KEY,
                'secret_key'  => $this->AWS_SECRET_KEY,
                'client_id'   => $this->CLIENT_ID,
                'currency_code' => $this->CURRENCY_CODE,
                'region'      => 'us',
                'sandbox'     => $this->SANDBOX_VAL);
		$this->client = new AmazonClient($config);
		
		//$this->client->setSandbox($this->SANDBOX_VAL);
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
	
	public function SetupAmazon(Request $request)
	{
		
		if(isset($request->access_token) && $request->access_token != '')
		{
			Session::put('AMAZON_ACCESS_TOKEN',$request->access_token);
			if(isset($request->page_from))
			{
				Session::put('page_from',$request->page_from);
				if($request->page_from == "phoneorder_payment_receipt"){
					return redirect('/phoneorder-amazon');
				}else{
					return redirect('/billing-amazon');
				}
			} else {
				$reqtoken = isset($request->access_token) ? $request->access_token : false;

				if(!empty($reqtoken))
				{
					$Profile = $this->client->getUserInfo($reqtoken);
					if($Profile && isset($Profile['email'])){
						if(isset($Profile['email']) && $Profile['email'] !=''){
							if(checkBlockedUser($Profile['email'],0,'AmazonGuest')==true)
							{
								Session::flash('PlaceOrderError',config('message.Register.Blocked'));								
								return redirect('/shoppingcart/view');  						
							}
						}
					}
					if($this->CheckUser($reqtoken))
					{
						
						return redirect('/billing-amazon-checkout');
					} else {
						Session::forget('AMAZON_ACCESS_TOKEN');
						return redirect('/shoppingcart');
					}
				}
				else {
						Session::forget('AMAZON_ACCESS_TOKEN');
						return redirect('/shoppingcart');
					}	
			}						
		}	
	}
	
	public function CheckUser($AccessToken)
	{	
		$Profile = $this->client->getUserInfo($AccessToken);
		if($Profile && isset($Profile['email']))
		{
			
			
			$username 		= (isset($Profile['name']))?$this->transliterate($Profile['name']):'';
			$email 			= $Profile['email'];
			$vfirst_name	= (isset($Profile['name']))?$this->transliterate($Profile['name']):'';
			$social_id 		= $Profile['user_id'];
			$postal_code    = '';
			if(!empty($Profile['postal_code']))
			{
			$postal_code	= $Profile['postal_code'];
			}
			
			Session::put('amazon_id', $social_id);
			
			//dd(Session::all()); 
			if(!Session::has('sess_icustomerid') || empty(Session::get('sess_icustomerid')))
			{
				$result = Customer::select('customer_id','status','first_name','email','eusertype','is_dropshipper')
							->where('email', '=' , $email)->where('registration_type','=','M')->get();
				
				$registration_type = "Member";
				if(!$result || $result->count() <= 0)
				{
					//check for guest
					$result = Customer::select('customer_id','status','first_name','email','eusertype','is_dropshipper')
							->where('email', '=' , $email)->where('registration_type','=','G')
							->where('is_deleted','=','No')->get();
					$registration_type = "Guest";
				}
				
				if($result && isset($result[0]["email"]) && $result[0]["email"]!="" && !empty($result[0]["customer_id"]) && $result[0]["customer_id"]>0)
				{	
					//convert guest to member
					if(isset($registration_type) && $registration_type == "Guest"){
						if(isset($email) && $email != '')			
						{
							//$aData['email'] 			= $email;				
							$aData['first_name'] 		= $vfirst_name;
							$aData['is_amazon'] 		= "Yes";
							$aData['amazon_id'] 		= $social_id;
							$aData['zip'] 				= $postal_code;
							$aData['registration_type'] = 'M'; // member or guest customer
							$aData['upd_datetime'] 		= date('Y-m-d H:i:s');
							$aData['status'] 			= 1;
							$aData['customer_ip'] 		= $_SERVER['REMOTE_ADDR'];
							$aData['customer_browser'] 	= $_SERVER['HTTP_USER_AGENT'];
							$aData['is_dropshipper'] 	= "No";
							$aData['eusertype']		    = 'Retailer';
							$aData['merge_log'] = "Auto updated guest to member from amazon login";
							
							$iCustomerId = $result[0]["customer_id"];
							Customer::where('customer_id','=',$iCustomerId)->update($aData);
						}	
					}
					//convert guest to member
					
					if($result[0]['status'] == "0"){	//mostly member
						$CustomerArr = array (
							'upd_datetime' 		=> date('Y-m-d H:i:s'),
							'merge_log' 		=> "Auto updated to Active from amazon page",
							'status' 			=> '1'
						);
						$iCustomerId = $result[0]["customer_id"];	
						Customer::where('customer_id','=',$iCustomerId)->update($CustomerArr);
					}
					
					Session::put('sess_useremail',$result[0]["email"]);
					Session::put('sess_icustomerid',$result[0]["customer_id"]);
					Session::put('eusertype',$result[0]["eusertype"]);
					Session::put('is_dropshipper',$result[0]["is_dropshipper"]);
					Session::put('etype',"M");
					Session::put('amazon_id',$social_id);
					
					//merge guest accounts will called only when login
					$user_email = Session::get('sess_useremail');
					$this->Merge_Guest_Register($user_email,Session::get('sess_icustomerid'));
					//merge guest accounts
				} else {
					if($email != '')			
					{					
						$aData['email'] 			= $email;				
						$aData['first_name'] 		= $vfirst_name;
						$aData['is_amazon'] 		= "Yes";
						$aData['amazon_id'] 		= $social_id;
						$aData['zip'] 				= $postal_code;
						$aData['registration_type'] = 'M'; // member or guest customer
						$aData['reg_datetime'] 		= date('Y-m-d H:i:s');
						$aData['status'] 			= 1;
						$aData['customer_ip'] 		= $_SERVER['REMOTE_ADDR'];
						$aData['customer_browser'] 	= $_SERVER['HTTP_USER_AGENT'];
						$aData['is_dropshipper'] 	= "No";
						$aData['eusertype']		    = 'Retailer';
						$CustAdd = Customer::create($aData);
						$iCustomerId = $CustAdd->customer_id;			
					}	
					
					if (isset($iCustomerId) && $iCustomerId>0)
					{
						
						Session::put('sess_useremail' ,$email);
						Session::put('sess_icustomerid',$iCustomerId);
						Session::put('eusertype',$aData['eusertype']);
						Session::put('is_dropshipper',$aData['is_dropshipper']);
						Session::put('etype',"M");
						Session::put('amazon_id',$social_id);
						
						//merge guest accounts will called only when login
						$user_email = Session::get('sess_useremail');
						$this->Merge_Guest_Register($user_email,Session::get('sess_icustomerid'));
						//merge guest accounts
					}else{
						//echo "else";
					}
				}
			}
			
			
			return true;
		} else {
			return false;
		}			
	}
	
	public function GetOrderInfo(Request $request)
	{
		if(isset($request->amazon_order_id) && $request->amazon_order_id != '')
		{
			Session::put('AMAZON_ORDER_REFERENCE_ID',$request->amazon_order_id);
			$requestParameters = [];
			$requestParameters['amazon_order_reference_id'] = $request->amazon_order_id;
			$requestParameters['access_token']  = Session::get('AMAZON_ACCESS_TOKEN');
			$requestParameters['mws_auth_token']         = 'MWS_AUTH_TOKEN';
			$response = $this->client->getOrderReferenceDetails($requestParameters);
			$response = $response->toArray();
			if($response['ResponseStatus'] == '200')
			{
				$destination = $response['GetOrderReferenceDetailsResult']['OrderReferenceDetails']['Destination']['PhysicalDestination'];
				$buyer = array();
				if(isset($response['GetOrderReferenceDetailsResult']['OrderReferenceDetails']['Buyer'])){
					$buyer = $response['GetOrderReferenceDetailsResult']['OrderReferenceDetails']['Buyer'];
				}
				if(strtoupper($destination['CountryCode']) == "GB") {
					$destination['CountryCode'] = "UK";
				}
				if(isset($destination['StateOrRegion']) && $destination['StateOrRegion']!='')
				{
					Session::put('AmazonShipState', $destination['StateOrRegion']);
				}
				else
				{
					Session::put('AmazonShipState','');
				}
				if(isset($destination['PostalCode']) && $destination['PostalCode']!='')
				{
					Session::put('AmazonShipZip', $destination['PostalCode']);
				}
				else
				{
					Session::put('AmazonShipZip', '');
				}
				if(isset($destination['CountryCode']) && $destination['CountryCode']!='')
				{
					Session::put('AmazonShipCountry', $destination['CountryCode']);
				}
				else
				{
					Session::put('AmazonShipCountry', '');	
				}
				if(isset($destination['City']) && $destination['City']!='')
				{
					Session::put('AmazonShipCity', $destination['City']);
				}
				else
				{
					Session::put('AmazonShipCity', '');	
				}
				$temp = array();
				$temp['first_name'] 	= (isset($destination['Name']))?$this->transliterate($destination['Name']):'';//$destination['Name'];
				$temp['last_name']  	= '';
				$temp['company']    	= '';
				$temp['address1'] 		= (isset($destination['AddressLine1']))?$this->transliterate($destination['AddressLine1']):'';//$destination['AddressLine1'];
				$temp['address2'] 		= (isset($destination['AddressLine2']))?$destination['AddressLine2']:'';
				$temp['city'] 			= (isset($destination['City']))?$destination['City']:'';//$destination['City'];
				$temp['country'] 		= (isset($destination['CountryCode']))?$destination['CountryCode']:'';//$destination['CountryCode'];
				$temp['state'] 			= (isset($destination['StateOrRegion']))?$destination['StateOrRegion']:'';
				$temp['zip'] = '';
				$temp['email'] 			= "";
				$temp['confirm_email'] 	= "";
				if(isset($destination['PostalCode']) && $destination['PostalCode']!='')
				{
					$temp['zip'] 			= $destination['PostalCode'];
				}
				$temp['phone']          = "";
				
				if(isset($destination['Phone']) && $destination['Phone']!='')
				{
					$temp['phone'] 			= $destination['Phone'];
				}
				else if(isset($buyer['Phone']) && $buyer['Phone']!='')
				{
					$temp['phone'] 			= $buyer['Phone'];
				}
				if(isset($buyer['Email']) && $buyer['Email'] !=''){
					$temp['email'] 			= $buyer['Email'];
					$temp['confirm_email'] 	= $buyer['Email'];
				}

				Session::put('ShoppingCart.BillingAddress',$temp);
				Session::put('ShoppingCart.ShippingAddress',$temp);
				
				return true;
			} else {
				return false;
			}				
				
		} else {
			return false;
		}
	}
	
	public function AmazonPlaceOrder(Request $request)
	{
		$requestParameters = array();
		$OrderID = Session::get('ShoppingCart.OrderID');
		$OrderID = 'MAX'.(int)$OrderID;
		$OrdID = Session::get('ShoppingCart.OrderID');
		$OrderAmount = $this->GetNetTotal();
		$requestParameters['merchant_id'] 				= $this->MERCHANT_ID;
		$requestParameters['amazon_order_reference_id'] = Session::get('AMAZON_ORDER_REFERENCE_ID');
		$requestParameters['charge_amount']             = $OrderAmount;
		$requestParameters['currency_code']     		= 'USD';
		$requestParameters['authorization_reference_id']= 'MAXAuthorize'.$OrdID;
		$requestParameters['charge_order_id']   		= $OrderID;
		$requestParameters['store_name']        		= config('Settings.SITE_TITLE');
		$requestParameters['capture_now']        		= false;
		$paywithamazon_response = $this->client->charge($requestParameters);
		$response = $paywithamazon_response->toArray();
		if(isset($response['ResponseStatus']) && $response['ResponseStatus'] == '200')
		{
			$AmazonAuthorizationId = '';
			$amazon_response = 'Status: '.$response['ResponseStatus'].', ';
			if(isset($response['AuthorizeResult']['AuthorizationDetails']))
			{
				$AuthorizeResult = $response['AuthorizeResult']['AuthorizationDetails'];	
				$AmazonAuthorizationId = $AuthorizeResult['AmazonAuthorizationId'];
				$amazon_response .= 'AuthorizationAmount: '.$AuthorizeResult['AuthorizationAmount']['Amount'].' '.$AuthorizeResult['AuthorizationAmount']['CurrencyCode'].', ';
				$amazon_response .= 'CapturedAmount: '.$AuthorizeResult['CapturedAmount']['Amount'].' '.$AuthorizeResult['CapturedAmount']['CurrencyCode'].', ';
				$amazon_response .= 'Timestamp: '.$AuthorizeResult['CreationTimestamp'].', ';
			}
			$updAray = array (
								'status'					=> 'Pending By Amazon',
								'pay_status' 	   			=> 'Unpaid',
								'AmazonAuthorizationId'		=> $AmazonAuthorizationId,
								'AmazonRequestId'			=> $response['ResponseMetadata']['RequestId'],
								'transaction_info' 			=> $amazon_response,
								'payment_gateway_response' 	=> serialize($response)
							 );
			$uporderres = Order::where("orders_id", '=' , Session::get('ShoppingCart.OrderID'))->update($updAray);

			$cRequestParameters['merchant_id'] 				 = $this->MERCHANT_ID;
			$cRequestParameters['amazon_order_reference_id'] = Session::get('AMAZON_ORDER_REFERENCE_ID');
			$closeOrder = $this->client->closeOrderReference($cRequestParameters);	
			return redirect(url('/order-receipt'));
		} else {
			$amazon_response = 'Status: '.$response['ResponseStatus'].', ';
			
			if(isset($response['Error']['message']) && $response['Error']['message']!='')
			{
				$amazon_response .= 'Error: '.$response['Error']['message'].', ';
			}
			$AmazonAuthorizationId = '';
			if(isset($response['AuthorizeResult']['AuthorizationDetails']))
			{
				$AuthorizeResult = $response['AuthorizeResult']['AuthorizationDetails'];	
				$AmazonAuthorizationId = $AuthorizeResult['AmazonAuthorizationId'];
			}
			$updAray = array (
								'status' 	  	   			=> 'Declined',
								'AmazonRequestId'			=> $AmazonRequestId,
								'transaction_info' 			=> $amazon_response,
								'payment_gateway_response' 	=> serialize($response)
							 );
			$uporderres = Order::where("orders_id", '=' , Session::get('ShoppingCart.OrderID'))->update($updAray);
			$msg = "Sorry, your payment has been DECLINED, please make sure to verify the accuracy of your payment details and try again.";
			
			
			$sessionDetails	= Session::get('ShoppingCart.BillingAddress');
			
			$customerEmail = '';
			if(isset($sessionDetails['email']) && $sessionDetails['email']!='')
			{
				$customerEmail	= $sessionDetails['email'];
			}
			
			$orderNo		= 'OR'.Session::get('ShoppingCart.OrderID');
			$customerName = '';
			if((isset($sessionDetails['first_name']) && $sessionDetails['first_name']!=''))
			{
				$customerName	= $sessionDetails['first_name']." ";
			}
			
			if(isset($sessionDetails['last_name']) && $sessionDetails['last_name'])
			{
				$customerName = $customerName.$sessionDetails['last_name'];
			}
			
			$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
			
			$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
			OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
			
			Session::flash('PlaceOrderError',$msg);
			return redirect('/billing-amazon-checkout');
		}
	}
	
	public function AmazonFundProcess(Request $request)
	{
		if(isset($request->PaymentMethod) && $request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
		{
			$insAray = array(
				"status" => 'Pending',
				"customer_id" => (int)Session::get('sess_icustomerid')
			);
			$fund_order = AmazonFundLog::create($insAray);
			$fund_order_id = $fund_order->amazon_log_id;
			$OrderID = 'PBA'.(int)$fund_order_id;
			$OrderAmount = Session::get('CurrFundAmount');

			$requestParameters['merchant_id'] 				= $this->MERCHANT_ID;
			$requestParameters['amazon_order_reference_id'] = Session::get('AMAZON_ORDER_REFERENCE_ID');
			$requestParameters['charge_amount']             = $OrderAmount;
			$requestParameters['currency_code']     		= 'USD';
			$requestParameters['authorization_reference_id']= 'PBAAuthorize'.(int)$fund_order_id;
			$requestParameters['charge_order_id']   		= $OrderID;
			$requestParameters['store_name']        		= config('global.SITE_TITLE');
			$requestParameters['capture_now']        		= false;
			$paywithamazon_response = $this->client->charge($requestParameters);
			$response = $paywithamazon_response->toArray();
			
			if(isset($response['ResponseStatus']) && $response['ResponseStatus'] == '200')
			{
				$AmazonAuthorizationId = '';
				$amazon_response = 'Status: '.$response['ResponseStatus'].', ';
				if(isset($response['AuthorizeResult']['AuthorizationDetails']))
				{
					$AuthorizeResult = $response['AuthorizeResult']['AuthorizationDetails'];	
					$AmazonAuthorizationId = $AuthorizeResult['AmazonAuthorizationId'];
					$amazon_response .= 'AuthorizationAmount: '.$AuthorizeResult['AuthorizationAmount']['Amount'].' '.$AuthorizeResult['AuthorizationAmount']['CurrencyCode'].', ';
					$amazon_response .= 'CapturedAmount: '.$AuthorizeResult['CapturedAmount']['Amount'].' '.$AuthorizeResult['CapturedAmount']['CurrencyCode'].', ';
					$amazon_response .= 'Timestamp: '.$AuthorizeResult['CreationTimestamp'].', ';
				}
				$updAray = array (
									'status'					=> 'Pending By Amazon',
									'pay_status' 	   			=> 'Unpaid',
									'inv_order_id'              => $OrderID,
									'fund_amount'               => $OrderAmount,
									'AmazonAuthorizationId'		=> $AmazonAuthorizationId,
									'AmazonRequestId'			=> $response['ResponseMetadata']['RequestId'],
									'transaction_info' 			=> $amazon_response,
									'payment_gateway_response' 	=> serialize($response)
								 );
				$uporderres = AmazonFundLog::where("amazon_log_id","=",$fund_order_id)->update($updAray);

				$cRequestParameters['merchant_id'] 				 = $this->MERCHANT_ID;
				$cRequestParameters['amazon_order_reference_id'] = Session::get('AMAZON_ORDER_REFERENCE_ID');
				$closeOrder = $this->client->closeOrderReference($cRequestParameters);	
				$msg = "Your Fund Request Submitted Successfully. Your Account Will Be Credited Within 1 Hour.";
				Session::flash('fund_success',$msg);
				if(Session::get('page_from') == 'fund')
					return redirect(url('/dropshipper-fund-summary.html'));
				elseif(Session::get('page_from') == 'billing')
					return redirect(url('/billing'));
				else
					return redirect(url('/shoppingcart'));	
			} else {
				$amazon_response = 'Status: '.$response['ResponseStatus'].', ';
				$amazon_response .= 'Error: '.$response['Error']['message'].', ';
				$AmazonAuthorizationId = '';
				if(isset($response['AuthorizeResult']['AuthorizationDetails']))
				{
					$AuthorizeResult = $response['AuthorizeResult']['AuthorizationDetails'];	
					$AmazonAuthorizationId = $AuthorizeResult['AmazonAuthorizationId'];
				}
				$updAray = array (
									'status' 	  	   			=> 'Declined',
									'inv_order_id'              => $OrderID,
									'fund_amount'               => $OrderAmount,
									'AmazonRequestId'			=> $AmazonRequestId,
									'transaction_info' 			=> $amazon_response,
									'payment_gateway_response' 	=> serialize($response)
								 );
				$uporderres = AmazonFundLog::where('amazon_log_id','=',$fund_order_id)->update($updAray);				 
				$msg = "Sorry, your payment has been DECLINED, please make sure to verify the accuracy of your payment details and try again.";
				Session::flash('fund_error',$msg);
				if(Session::get('page_from') == 'fund')
					return redirect(url('/dropshipper-fund-summary.html'));
				elseif(Session::get('page_from') == 'billing')
					return redirect(url('/billing'));
				else
					return redirect(url('/shoppingcart'));
			}			
		}
	}
	
	public function AmazonPhoneOrderProcess(Request $request)
	{
		if(isset($request->PaymentMethod) && $request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
		{
			$order_id = Session::get('phoneorder_detail.order_id');
			$OrderRs = Order::select('orders_id', 'orders_no','customer_id', 'sub_total', 'order_total')
							->where('orders_id', '=', $order_id)
							->get();
							
			$OrderID = 'MAX'.(int)$order_id;
							
			$requestParameters['merchant_id'] 				= $this->MERCHANT_ID;
			$requestParameters['amazon_order_reference_id'] = Session::get('AMAZON_ORDER_REFERENCE_ID');
			$requestParameters['charge_amount']             = $OrderRs[0]->order_total;
			$requestParameters['currency_code']     		= 'USD';
			$requestParameters['authorization_reference_id']= 'MAXAuthorize'.(int)$order_id;
			$requestParameters['charge_order_id']   		= $OrderID;
			$requestParameters['store_name']        		= config('global.SITE_TITLE');
			$requestParameters['capture_now']        		= false;
			$paywithamazon_response = $this->client->charge($requestParameters);
			$response = $paywithamazon_response->toArray();
			
			if(isset($response['ResponseStatus']) && $response['ResponseStatus'] == '200')
			{
				$AmazonAuthorizationId = '';
				$amazon_response = 'Status: '.$response['ResponseStatus'].', ';
				if(isset($response['AuthorizeResult']['AuthorizationDetails']))
				{
					$AuthorizeResult = $response['AuthorizeResult']['AuthorizationDetails'];	
					$AmazonAuthorizationId = $AuthorizeResult['AmazonAuthorizationId'];
					$amazon_response .= 'AuthorizationAmount: '.$AuthorizeResult['AuthorizationAmount']['Amount'].' '.$AuthorizeResult['AuthorizationAmount']['CurrencyCode'].', ';
					$amazon_response .= 'CapturedAmount: '.$AuthorizeResult['CapturedAmount']['Amount'].' '.$AuthorizeResult['CapturedAmount']['CurrencyCode'].', ';
					$amazon_response .= 'Timestamp: '.$AuthorizeResult['CreationTimestamp'].', ';
				}
				
				$updAray = array (
								'status'					=> 'Pending By Amazon',
								'pay_status' 	   			=> 'Unpaid',
								'AmazonAuthorizationId'		=> $AmazonAuthorizationId,
								'AmazonRequestId'			=> $response['ResponseMetadata']['RequestId'],
								'transaction_info' 			=> $amazon_response,
								'payment_gateway_response' 	=> serialize($response),
								'phoneorder_paymentdate' => date("Y-m-d H:i:s")
							 );
				$uporderres = Order::where("orders_id", '=' , $order_id)->update($updAray);


				$cRequestParameters['merchant_id'] 				 = $this->MERCHANT_ID;
				$cRequestParameters['amazon_order_reference_id'] = Session::get('AMAZON_ORDER_REFERENCE_ID');
				$closeOrder = $this->client->closeOrderReference($cRequestParameters);	
				
				//stock,other related changes start
				$response_arr = $this->PhoneorderPaymentSuccess('Amazon');
				if($response_arr['success'] == "1"){
					Session::flash('success',$response_arr['err_msg']);
				}else{
					Session::flash('error',$response_arr['err_msg']);
				}	
				return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
				
			} else {
				$amazon_response = 'Status: '.$response['ResponseStatus'].', ';
				$amazon_response .= 'Error: '.$response['Error']['message'].', ';
				$AmazonAuthorizationId = '';
				if(isset($response['AuthorizeResult']['AuthorizationDetails']))
				{
					$AuthorizeResult = $response['AuthorizeResult']['AuthorizationDetails'];	
					$AmazonAuthorizationId = $AuthorizeResult['AmazonAuthorizationId'];
				}
				$updAray = array (
								'status' 	  	   			=> 'Declined',
								'AmazonRequestId'			=> $response['ResponseMetadata']['RequestId'],
								'transaction_info' 			=> $amazon_response,
								'payment_gateway_response' 	=> serialize($response)
							 );
				$uporderres = Order::where("orders_id", '=' , $order_id)->update($updAray);
				
				$sessionDetails	= Session::get('ShoppingCart.BillingAddress');
				
				$customerEmail = '';
				if(isset($sessionDetails['email']) && $sessionDetails['email']!='')
				{
					$customerEmail	= $sessionDetails['email'];
				}
				
				$orderNo		= $OrderRs[0]->orders_no;
				$customerName 	= '';
				if((isset($sessionDetails['first_name']) && $sessionDetails['first_name']!=''))
				{
					$customerName	= $sessionDetails['first_name']." ";
				}
				
				if(isset($sessionDetails['last_name']) && $sessionDetails['last_name'])
				{
					$customerName = $customerName.$sessionDetails['last_name'];
				}
				
				$Data = ['toMail' => $customerEmail, 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
				
				/*$Data = ['toMail' => 'naresh.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);
				
				$Data = ['toMail' => 'ravi.qualdev@gmail.com', 'orderno' => $orderNo, 'customer_name' => $customerName];
				OmanisendRequest('63c113f03d897d0020e8b31e',$Data);*/
				
				Session::flash('error',"Something went wrong, payment failed.");
				return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
			}			
		}
	}
	
	public function GetOrderInfo_Phoneorder(Request $request)
	{
		if(isset($request->amazon_order_id) && $request->amazon_order_id != '')
		{
			Session::put('AMAZON_ORDER_REFERENCE_ID',$request->amazon_order_id);
			$requestParameters = [];
			$requestParameters['amazon_order_reference_id'] = $request->amazon_order_id;
			$requestParameters['address_consent_token']  = Session::get('AMAZON_ACCESS_TOKEN');
			$requestParameters['mws_auth_token']         = 'MWS_AUTH_TOKEN';
			$response = $this->client->getOrderReferenceDetails($requestParameters);
			$response = $response->toArray();
			if($response['ResponseStatus'] == '200')
			{
				$destination = $response['GetOrderReferenceDetailsResult']['OrderReferenceDetails']['Destination']['PhysicalDestination'];
				$buyer = $response['GetOrderReferenceDetailsResult']['OrderReferenceDetails']['Buyer'];

				if(strtoupper($destination['CountryCode']) == "GB") {
					$destination['CountryCode'] = "UK";
				}
				// Session::put('AmazonShipState', $destination['StateOrRegion']);
				// Session::put('AmazonShipZip', $destination['PostalCode']);
				// Session::put('AmazonShipCountry', $destination['CountryCode']);
				
				return true;
			} else {
				return false;
			}				
				
		} else {
			return false;
		}
	}
}
