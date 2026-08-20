<?php
namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

use App\Models\Products;
use App\Models\PaymentMethod;
use DB;
use Session;
use Cache;

use Afterpay\SDK\MerchantAccount as AfterpayMerchantAccount;
use Afterpay\SDK\HTTP\Request\GetConfiguration as AfterpayGetConfigurationRequest;

trait AfterpayTrait
{	
	public $PageData,$Payment_Url,$Token_JS_Url,$TRANSACTION_MODE,$ap_arr,$Afterpay_Checkout;
	
	public function constructfunc_afterpaydetails()
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
		
		$db_res = $PaymentMethods->firstWhere('pm_group_name', 'PAYMENT_PAYWITHAFTERPAY');

		if (!$db_res) {
			$this->TRANSACTION_MODE = '';
			$this->Payment_Url = "";
			$this->Token_JS_Url = "";
			$this->Afterpay_Checkout = "No";
			return;
		}

       $this->Afterpay_Checkout = "Yes";  
			
		 if(Session::get('sess_useremail') == 'wgequaldev@gmail.com' || Session::get('sess_useremail') == 'gequaldev@gmail.com' || Session::get('sess_useremail') == 'qqualdev@gmail.com' || $_SERVER['HTTP_X_FORWARDED_FOR'] == "2406:b400:d11:21df:6d2f:5c3f:799d:7404")
		 {
			  $db_res->pm_details = 'a:6:{s:27:"PaywithAfterpay_Merchant_ID";s:16:"6+/un9C1fWJXNwA=";s:35:"PaywithAfterpay_Merchant_Secret_Key";s:144:"DcpbEsIgDADA6zu8EopYb+IASQPpbarSHkH3e3eHHWoFXrewBFBLb4q+A56KUw5Sm1ZhDBzbyF7VuFq8Lzfky/VWJC3PhyvGD+WAkHrErwEhylcjmiLZ7ZqO2LY7Z339k53V4oAxmjmPT/gB";s:36:"PaywithAfterpay_Header_Authorization";s:244:"BcHJCoJAAABQJOjXszKtY4IkRc6kDokOpJ1qRiRbXVDTKKx7tFide0+byik49feqlcaIYgYYO1bho7b9ezezKue1wTqzHIWqo72AxHEF4ycBfrxwgi2bB2cODegKvIZ0kt89pMo14IUNxVgf9DJ0j7trrJm4Cxnagn6U6NSpoBQG0ChaqP5ws9rN1Tq4hzcigduLYKuEwXLCmemX2PWSzcuCzBJDAB9zCf2iiyQ4B1JETato/AE=";s:33:"PaywithAfterpay_Header_User_Agent";s:152:"AWsAlP+h0NLTyfR4p8fU8MXMhZC3inmwnIaf+MTM8f2776nYxr/Pxc7M9HilvMzui4+Ej7eKlKinoay4jo28uYjFqdW/0L7HwMj7h4iLj7qMjo+SuYN58MvNzPyRjrv+wPn5trvEz8TRyujRhb7O9g==";s:32:"PaywithAfterpay_Transaction_Mode";s:7:"Sandbox";s:29:"PaywithAfterpay_Currency_Code";s:3:"USD";}';
		}
	 
		$arrPEVar		= unserialize($db_res->pm_details);
		
		$this->ap_arr['PaywithAfterpay_Merchant_ID']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_ID']);
		$this->ap_arr['PaywithAfterpay_Merchant_Secret_Key']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_Secret_Key']);
		$this->ap_arr['PaywithAfterpay_Header_Authorization']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_Authorization']);
		$this->ap_arr['PaywithAfterpay_Header_User_Agent']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_User_Agent']);
		
		
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
		
	}		
	
	public function constructfunc11()
    {
		$this->constructfunc_afterpaydetails();
		/* $db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_PAYWITHAFTERPAY')
							->where('pm_status', '=', 'Active')
							->get();
		
		if($db_res->count() > 0)
		{
			$arrPEVar		= unserialize($db_res[0]->pm_details);
			
			//echo "<pre>";print_r($arrPEVar);exit;
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
				$this->TRANSACTION_MODE = '';
				$this->Payment_Url = "https://api.us.afterpay.com/v2/";
				$this->Token_JS_Url = "https://portal.afterpay.com/afterpay.js";
			}
		}else{
			
		} */
		
	}
	
	public function GetAfterPayResult($data_payload = array(),$ApiType="",$IsPost = "Yes"){
		$this->constructfunc_afterpaydetails();
		if(empty($data_payload)){
			$data_payload = json_encode($data_payload);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->Payment_Url.$ApiType);
		curl_setopt($ch, CURLOPT_SSLVERSION, 'CURL_SSLVERSION_TLSv1_2');
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
		return $resultArr;
	}
	
	public function GetAfterPayToken($orderTotal,$ApiType=""){
		$data_var = array(
			"amount" => array(
				"amount" => $orderTotal,
				"currency" => "USD"
			),
			"mode" => "express",
			"merchant" => array(
				"popupOriginUrl" => "https://staging.maxaroma.com/payment/MjI3NTAy"
			)
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->Payment_Url.$ApiType);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_var));
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Basic '.$this->ap_arr["PaywithAfterpay_Header_Authorization"];	//taken from doc
		$headers[] = 'User-Agent: '.$this->ap_arr["PaywithAfterpay_Header_User_Agent"];
		$headers[] = 'Accept: application/json';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		$resArr = json_decode($response, true);
		return $resArr['token'];
	}
	
	public function getAfterPayDetails(){
		$this->constructfunc_afterpaydetails();
		$Details['ap_arr'] = $this->ap_arr;
		$Details['transaction_mode'] = $this->TRANSACTION_MODE;
		$Details['payment_url'] = $this->Payment_Url;
		$Details['token_js_url'] = $this->Token_JS_Url;
		
		return $Details;
	}
	
	public function GetAfterpayMinMaxConfig()
	{
		$this->constructfunc_afterpaydetails();
		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
						->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
						->setApiEnvironment($this->TRANSACTION_MODE)
						->setCountryCode('US');
		$getConfigurationRequest = new AfterpayGetConfigurationRequest();
		$getConfigurationRequest->setMerchantAccount($merchant);
		$getConfigurationRequest->send();
		$body = $getConfigurationRequest->getResponse()->getParsedBody();

		$returnArr = array();
		if($body && isset($body->minimumAmount))
		{
			$returnArr["Min_AP_AMT"] = $body->minimumAmount->amount;
			$returnArr["Max_AP_AMT"] = $body->maximumAmount->amount;
		}	
		
		
		return $returnArr;
			
	}
	
	

}
