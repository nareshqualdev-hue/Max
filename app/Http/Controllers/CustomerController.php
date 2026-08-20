<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Traits\CartTrait;

use Hash;
use Session;
use DateTime;

use App\Models\MetaInfo;
use App\Models\Customer;
use App\Models\AddressBook;
use App\Models\RewardPoint;
use App\Models\EmailTemplates;
use App\Models\ReferFriend;
use App\Models\RewardRule;
use App\Models\Order;
use App\Models\ProductsCategory;
use App\Models\Category;
use App\Models\Products;
use App\Models\OrderDetail;
use App\Models\GiftCertificate;
use App\Models\FreeGiftProduct;
use App\Models\ReturnOrders;
use App\Models\AdminCreditLog;
use App\Models\ProductsOne;
use App\Models\AdminFundLog;
use App\Models\Admin;
use App\Models\PaypalIpnLog;
use App\Models\AuthorizeFundLog;
use App\Models\AmazonFundLog;
use App\Models\DropshipperOrder;
use App\Models\PaymentMethod;
use App\Models\ImportDropshiporder;
use App\Models\DropshipperOrderDetail;
use App\Models\MarkupPrices;
use App\Models\Dealofweek;
use App\Models\Dealofweektitle;
use App\Models\ShippingMode;
use App\Models\ShippingRule;
use App\Models\ShippingRate;
use App\Models\WishlistCategory;
use App\Models\Wishlist;
use App\Models\Manufacture;
use App\Models\ProductsReview;
use App\Models\ShippingHoliday;
use App\Models\NewsLetter;
use App\Models\StaticPages;
use App\Models\Claim;
use App\Models\SiteSettings;

use App\Imports\ImportDropshiporderImport;
use Maatwebsite\Excel\Facades\Excel;

use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\VendorTrait;
use DB;
use Mail;

use Carbon\Carbon;
// use File;
use Illuminate\Support\Facades\File;

use PDF;
use Cookie;

class CustomerController extends Controller
{
	use CommonTrait;
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

	public function Register(Request $request)
	{
		$this->PageData['Countries'] = GetCountries();
		$this->PageData['States'] = GetStates();
		$this->PageData['YoutubeAttrs'] = GetCustomerAttribute('Youtube');
		$this->PageData['InstagramAttrs'] = GetCustomerAttribute('Instagram');
		$this->PageData['SelCountry'] = 'US';
		if(isset($request['action']) && $request['action'] == 'signup' && isset($request['to']) && $request['to']=="")
		{
			$this->PageData['SelCountry'] = $request['country'];
	        $validatedData = $request->validate([
								'first_name' => 'required|max:10',
								'last_name'	=> 'required|max:10',
								'address1' => 'required',
								'city' => 'required|max:10',
								'zip' => 'required',
								'phone' => 'required',
					            'email' => 'required|email',
					            'password' => 'required|same:confirm_Password',
					            'confirm_Password' => 'required',
					            'instagram' => 'required_if:hear_about,Instagram',
					            'youtube' => 'required_if:hear_about,Youtube',
					            'other' => 'required_if:hear_about,Other',
					            'state' => 'required_if:country,US',
					            'other_state' => 'required_unless:country,US',
					        ], [
					            'first_name.required' => config('message.Validate.FirstName'),
					            'last_name.required' => config('message.Validate.LastName'),
					            'address1.required' => config('message.Validate.Address'),
					            'city.required' => config('message.Validate.City'),
					            'zip.required' => config('message.Validate.ZipCode'),
					            'phone.required' => config('message.Validate.Phone'),
					            'email.required' => config('message.Validate.ValidEmail'),
					            'email.email' => config('message.Validate.ValidEmail'),
					            'password.required' => config('message.Register.Password'),
					            'password.same' => config('message.Validate.ValidConfirmPassword'),
					            'confirm_Password.required' => config('message.Validate.ValidConfirmPassword'),
					            'instagram.required_if' => config('message.Register.Instagram'),
					            'youtube.required_if' => config('message.Register.Youtube'),
					            'other.required_if' => config('message.Register.OtherText'),
					            'state.required_if' => config('message.Validate.State'),
					            'other_state.required_unless' => config('message.Validate.OtherState'),
					        ]);

			$ChkEmail = Customer::where('email','=',$request['email'])->where('registration_type','=','M')->get();
			if($ChkEmail && $ChkEmail->count() > 0)
			{
				if($ChkEmail[0]->block_customer_flag == "Yes"){
					checkBlockedUser($ChkEmail[0]->email,$ChkEmail[0]->customer_id,'Register','Y');
					return redirect()->back()
						->withInput()
						->withErrors([
							'existing_email' => config('message.Register.Blocked'),
						]);
				}
				return redirect()->back()
				->withInput()
				->withErrors([
					'existing_email' => config('message.Register.ExistingEmail'),
				]);
			}
			$ChkIP = Customer::where('customer_ip','=',$_SERVER['REMOTE_ADDR'])->where('registration_type','=','M')->get();
			if($ChkIP && $ChkIP->count() >= 5)
			{
				$link_login = config('global.SITE_URL')."login.html";
				$link_forgot = config('global.SITE_URL')."forgot-password.html";

				$forgot_password_link = '<a class="btn_link" href="'.$link_forgot.'" style="color:red;">click here</a>';
				$login_link = '<a class="btn_link signinsignup" href="'.$link_login.'" style="color:red;">login</a>';
				$duplicate_ip_msg = str_replace("{click_here}",$forgot_password_link,config('message.Register.DuplicateIP'));
				$duplicate_ip_msg = str_replace("{login}",$login_link,$duplicate_ip_msg);
				Session::flash('duplication_ip_error', $duplicate_ip_msg);
				return redirect()->back();

				/*return redirect()->back()
				->withInput()
				->withErrors([
					'duplicate_ip' => config('message.Register.DuplicateIP'),
				]);*/
			}
			$State = $request['state'];
			if($request['country'] != 'US')
				$State = $request['other_state'];
			$UserData = array(
				'first_name' => $request['first_name'],
				'last_name' => $request['last_name'],
				'company_name' => ($request['company_name'] != '') ? $request['company_name'] : '',
				'address1' => $request['address1'],
				'address2' => ($request['address2'] != '') ? $request['address2'] : '',
				'phone' => $request['phone'],
				'email' => $request['email'],
				'password' => $request['password'],
				'city' => $request['city'],
				'state' => $State,
				'country' => $request['country'],
				'zip' => $request['zip'],
				'status' => '1',
				'eusertype' => 'Retailer',
				'customer_ip' => $_SERVER['REMOTE_ADDR'],
				'customer_browser' => $_SERVER['HTTP_USER_AGENT'],
				'is_dropshipper' => "No",
				//'iRewardpoint' => '150',
				'upd_datetime' => date('Y-m-d H:i:s'),
				'merge_log' => 'Auto updated from guest to member on registration page',
				'birthday' => ($request['birthday'] != null) ? $request['birthday'] : null,
			);

            if(config('global.YOTPO_PROG') == false)
            {
                $UserData['iRewardpoint'] = '150';
            }

			$User = Customer::create($UserData);
			if($User)
			{
				$iCustomerId = $User->customer_id;
				$AddressBook=array();
				$AddressBook['customer_id'] = $iCustomerId;
				$AddressBook['title'] = 'Self Address';
				$AddressBook['email'] = $User->email;
				$AddressBook['first_name'] = $User->first_name;
				$AddressBook['last_name'] = $User->last_name;
				$AddressBook['address1'] = $User->address1;
				$AddressBook['address2'] = $User->address2;
				$AddressBook['company_name'] = $User->company_name;
				$AddressBook['city'] = $User->city;
				$AddressBook['state'] = $User->state;
				$AddressBook['country'] = $User->country;
				$AddressBook['zip'] = $User->zip;
				$AddressBook['phone'] = $User->phone;
				$AddressBook['status'] = '1';
				$UserAddress = AddressBook::create($AddressBook);

                if(config('global.YOTPO_PROG') == false)
                {
                    $RewardPoints = array();
                    $RewardPoints["customer_id"] = $iCustomerId;
                    $RewardPoints["note"] = "Reward Point Added By Register";
                    $RewardPoints["iRewardpoint"] = 150;
                    $UserRewardPoints = RewardPoint::create($RewardPoints);
                }

				$request->session()->regenerate();
				Session::put('sess_useremail',$User->email);
				Session::put('sess_username',$User->first_name);
				Session::put('sess_icustomerid',$User->customer_id);
				Session::put('eusertype',$User->eusertype);
				Session::put('is_dropshipper',$User->is_dropshipper);
				Session::put('etype','M');
				Session::put('eusertype','Retailer');
				Session::put('payment_amount',$User->payment_amount);
				Session::put('resale_certificate',$User->upload_sales_tax);
				
				if($User->resale_certificate_status == ''){
					$User->resale_certificate_status = 'Pending';
				}else{
					$User->resale_certificate_status = $User->resale_certificate_status;
				}
				Session::put('resale_certificate_status',$User->resale_certificate_status);

				$custFname = '';
				if(isset($User->first_name) &&  $User->first_name!='' )
				{
					$custFname = $User->first_name;
				}
				$custlname = '';
				if(isset($User->last_name) && $User->last_name!='')
				{
					$custlname = $User->last_name;
				}
				Session::put('sess_custname',$custFname."|".$custlname);
				$CityVal = '';
				if(isset($User->city) &&  $User->city!='')
				{
					$CityVal = $User->city;
				}
				$StateVal = '';
				if(isset($User->state) &&  $User->state!='')
				{
					$StateVal = $User->state;
				}
				$CountryVal = '';
				if(isset($User->country) &&  $User->country!='')
				{
					$CountryVal = $User->country;
				}
				$ZipVal = '';
				if(isset($User->zip) &&  $User->zip!='')
				{
					$ZipVal = $User->zip;
				}
				$PhoneVal = '';
				if(isset($User->phone) &&  $User->phone!='')
				{
					$PhoneVal = $User->phone;
				}
				Session::put('sess_useraddress',$CityVal."|".$StateVal."|".$CountryVal."|".$ZipVal."|".$PhoneVal);

				Session::flash('success', config('message.Register.Success'));

				$Template = GetMailTemplate("CUSTOMER_REGISTER");
                $EmailBody = $Template[0]->mail_body;
				$EmailBody = str_replace('{$vFirstName}',$User->first_name,$EmailBody);
				$EmailBody = str_replace('{$vLastName}',$User->last_name,$EmailBody);
				$EmailBody = str_replace('{$vemail}',$User->email,$EmailBody);
				$EmailBody = str_replace('{$password}',$User->password,$EmailBody);
				$EmailBody = str_replace('{$COUPON_CODE_VALUE}',config('Settings.COUPON_CODE_VALUE'),$EmailBody);
				$EmailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$EmailBody);
				$FreeShipping = "";
				if(config('Settings.FREESHIPPING_VALUE')) {
					$FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
				}
				$EmailBody = str_replace('{$freeshippinginfo}',$FreeShipping,$EmailBody);
				$To = $User->email;
				// $To = "qqualdev@gmail";
				$Subject = $Template[0]->subject;
				//$EmailBody = $Template[0]->mail_body;
				$From = config('Settings.ADMIN_MAIL');
                $check_email = NewsLetter::whereRaw('LCASE(`email`) = "' . strtolower(trim($User->email)) . '"')->count();
                if($check_email == 0)
                {
				    //SendMail($Subject,$EmailBody,$To,$From);
                }
				$EventData = ['email' => $To, //'qualdev.devs@gmail.com',
					'fields' => [
						'first_name' => $User->first_name,
						'last_name' => $User->last_name,
						'password' => $User->password,
						'SITE_NAME' => config('Settings.SITE_TITLE'),
						'COUPON_CODE_VALUE' => config('Settings.COUPON_CODE_VALUE'),
						'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
						'Site_URL' => config('global.SITE_URL'),
						'freeshippinginfo' => $FreeShipping
					]
				];

                /** YOTPO **/
				YotpoRequest('create_customer',$User);
				/** YOTPO **/

				/*if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']=='27.56.182.123'){
					echo "<pre>";
					print_r($EventData);
					exit;
				}*/

				/*
                $YotpoInfo = YotpoRequest('customer_detail',['email' => $User->email]);
                if(isset($YotpoInfo->third_party_id))
                {
                    Session::put('UserYotpoID', $YotpoInfo->third_party_id);
                }
                */

				if(config('global.OMNISEND_PROG') == false)
				{
				   SendMail($Subject,$EmailBody,$To,$From);
				}
				else
				{
					OmanisendRequest('create_customer',$User);
					OmanisendRequest('61e55276af90600022058216',$EventData);
				}
				Auth::login($User);
                Cookie::make('omnisendContactID',$User->omnisend_accountid,time()+60*60*24*15);
				$this->GenerateShopCartFromCookieAfterLogin();
				$this->StoreShopCartInCookie();

				Session::put('GARegister',"Yes");
				return redirect('/myaccount.html');
			} else {
				Session::flash('failed', config('message.Register.Failed'));
				return redirect()->back();
			}
		}
		$GTMDATA = ['page' => 'register', 'pagetype' => 'other'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Registration';
		$this->PageData['JSFILES'] = ['register.js','moment.min.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css','register.css'];
		return view('customer.register')->with($this->PageData);
	}

	public function Login(Request $request)
	{
		if((isset($request->automated_login) && $request->automated_login == 'yes') || (isset($request->action) && $request->action == 'signin'))
		{
			if(!$request->automated_login)
			{
				$validatedData = $request->validate([
					            'email' => 'required|email',
					            'password' => 'required'
					        ], [
					            'email.required' => config('message.Login.ValidEmail'),
					            'email.email' => config('message.Login.ValidEmail'),
					            'password.required' => config('message.Login.Password')
					        ]);
			}
			if($request->automated_login == 'yes')
			{

				if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0)
				{
					Auth::logout();
				}
				$request->email = $request->vemail;

				$CustomerQry = Customer::where('email', $request->email)
						->where('status','1')
						->where('registration_type','M');
				if($request->vpassword != 'null' && $request->vpassword != null && $request->vpassword != '')
				{
					$decodedPassword = base64_decode($request->vpassword);

					$CustomerQry->whereRaw('BINARY password = ?', [$decodedPassword]);

				}
			} else {

				$CustomerQry = Customer::where('email', $request->email)
				->where('password',$request->password)
				->where('status','1')
				->where('registration_type','M');
			}

			$remember_me = $request->has('rememberMe') ? true : false;
			$Customer = $CustomerQry->first();
			//$remember_me = $request->has('rememberMe') ? true : false;
			$remember_me = false;
			if($Customer && $Customer->count() > 0)
			{
				if($Customer->block_customer_flag == "Yes"){
					checkBlockedUser($Customer->email,$Customer->customer_id,'Login','Y');
					return redirect()->back()
						->withInput()
						->withErrors([
							'Failed' => config('message.Login.Blocked'),
						]);
				}
				if($Customer->eusertype=="Wholesaler Pending")
				{
				  $Customer->eusertype = "Retailer";
				}
				Auth::login($Customer,$remember_me);
				//Session::forget('ShoppingCart');
				$request->session()->regenerate();
				Session::put('sess_useremail',$Customer->email);
				Session::put('sess_username',$Customer->first_name);
				Session::put('sess_icustomerid',$Customer->customer_id);
				Session::put('eusertype',$Customer->eusertype);
				Session::put('is_dropshipper',$Customer->is_dropshipper);
				Session::put('SpecialCustomerFlag',$Customer->DownloadSpecialPricelist);
				Session::put('etype','M');
				Session::put('payment_amount',$Customer->payment_amount);
				
				if($Customer->resale_certificate_status == ''){
					$Customer->resale_certificate_status = 'Pending';
				}else{
					$Customer->resale_certificate_status = $Customer->resale_certificate_status;
				}
				Session::put('resale_certificate',$Customer->upload_sales_tax);
				Session::put('resale_certificate_status',$Customer->resale_certificate_status);
				
				Session::put('sales_tax_exemption',$Customer->sales_tax_exemption);
				SetCustomerLoyaltyTier($Customer->email);

				/*Status - 'Pending','Completed','Canceled','Declined','Back Ordered','Closed','Refund','Sent To Stripe','Return Requested','Return Approved','Return Rejected','Item Received','Request Cancellation','Cancellation Approved','Pending By Amazon','Pending - PhoneOrder','Sent To AfterPay','Resolved','Claim Approved','Claim Rejected','Claim In Review','Claim Requested','Claim In Review - Police Report Required','Claim In Review - Pictures / Videos Required','Refund Initiated','Refund Declined','Refund Partially'
				Payment Status - 'Paid','Unpaid','Inprocess'*/

				//$InProgressStatusArray = ['Pending','Sent To Stripe','Pending By Amazon','Pending - PhoneOrder','Sent To AfterPay'];
				$InProgressStatusArray = ['Pending','Pending - PhoneOrder','Pending By Amazon'];
				$OrdInProgressResultQuery = Order::select('orders_id', 'status', DB::raw('DATE_FORMAT(order_datetime, "%m/%d/%Y") as datetime,DATEDIFF(now(), order_datetime) as days'))
									->where('customer_id', '=', $Customer->customer_id)
									->where('pay_status', '=', 'Paid')
									->whereIn('status', $InProgressStatusArray)
									->having('days', '<=', 60)
									->get();
									//->toSql();

				Session::put('InProgressOrders',count($OrdInProgressResultQuery));

				$rewrd_cust = Customer::select('iRewardpoint')
								->where('customer_id', '=', Auth::user()->customer_id)
								->get();
				Session::put('reward_point',$rewrd_cust[0]['iRewardpoint']);

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

                if($Customer->email == 'gequaldev@gmail.com')
                {
                    config(['global.YOTPO_PROG' => true ]);
                }
                /*
                $YotpoInfo = YotpoRequest('customer_detail',['email' => $Customer->email]);

                if(isset($YotpoInfo->third_party_id))
                {
                    Session::put('UserYotpoID', $YotpoInfo->third_party_id);
                }
                */
                //Cookie::queue(Cookie::make('omnisendContactID',$Customer->omnisend_accountid,time()+60*60*24*15));
                Cookie::make('omnisendContactID',$Customer->omnisend_accountid,time()+60*60*24*15);
                //setcookie('omnisendContactID',$Customer->omnisend_accountid,time()+60*60*24*15);
				Session::forget('ShoppingCart.BillingAddress');
				Session::forget('ShoppingCart.ShippingAddress');
				$this->GenerateShopCartFromCookieAfterLogin();
				$this->StoreShopCartInCookie();

				//$this->PageData['GA4'] = $GA4;

				//echo $this->PageData['GA4']; exit;
				if(isset($request->fromcheckout) && $request->fromcheckout=='checkout')
				{
					return redirect('/checkout');
				}
				else if(isset($request->page) && $request->page == 'billing')
				{
					return redirect('/billing');
				}
				else if(isset($request->page) && $request->page == 'paypal')
				{
					return redirect('/billing/paypal');
				}
				else
				{
					Session::put('MyAccountGA4',"Yes");
					return redirect('/myaccount.html');
				}
			} else {
				return redirect()->back()
				->withInput()
				->withErrors([
					'Failed' => config('message.Login.Failed'),
				]);
			}
		}
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Member Login';
		$this->PageData['JSFILES'] = ['login.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		$this->PageData['FromCheckout'] = "";
		if(isset($request->id) && $request->id=='checkout')
		{
			$this->PageData['FromCheckout'] = $request->id;
		}

		return view('customer.login')->with($this->PageData);
	}

	public function Logout(Request $request)
	{
		Auth::logout();
		$request->session()->forget('sess_useremail');
		$request->session()->forget('sess_username');
		$request->session()->forget('sess_icustomerid');
		$request->session()->forget('eusertype');
		$request->session()->forget('is_dropshipper');
		$request->session()->forget('etype');
		$request->session()->forget('payment_amount');
		$request->session()->forget('sess_useraddress');
		$request->session()->forget('sess_custname');
		$request->session()->forget('LoyaltyTier');
		$request->session()->forget('YotpoRewardPoints');
		$request->session()->forget('InProgressOrders');
		$request->session()->forget('reward_point');
		$request->session()->forget('resale_certificate');
		$request->session()->forget('resale_certificate_status');
		$request->session()->forget('sales_tax_exemption');

		$this->ReformatCartPrice();
		return redirect(config('global.SITE_URL'));
	}

	public function Myaccount(Request $request)
	{
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Account Overview';
		$GTMDATA = ['page' => 'myaccount', 'pagetype' => 'other'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		$GA4= "";
		if(Session::has('MyAccountGA4') && Session::get('MyAccountGA4')!='')
		{
		$GA4= googleAnalyticsGA4("LoginPage");
		Session::forget("MyAccountGA4");
		}

		$this->PageData['GA4'] = $GA4;
		$GARegister1 = "";

		if(Session::has('GARegister') && Session::get('GARegister')!='')
		{
		$GARegister1= googleAnalyticsGA4("RegisterPage");
		Session::forget("GARegister");
		}
		$this->PageData['GARegister1'] = $GARegister1;

		$account_image_path = config('global.SITE_URL').'images/';
		$account_image = generalsetting('ACCOUNT_IMAGE',1);
		$account_image_path_Val = config('global.PHYSICAL_PATH').'images/';
		$newimageVal = $account_image_path_Val.$account_image;

		$verP = ""; //filemtime($newimageVal);
		//$verP = "";
		$account_image = $account_image_path.$account_image.'?ver='.$verP;

		$this->PageData['account_image'] = $account_image;

		$rewrd_cust = Customer::select('iRewardpoint')
								->where('customer_id', '=', Auth::user()->customer_id)
								->get();
		$this->PageData['reward_point'] = $rewrd_cust[0]['iRewardpoint'];

		$OrdResultQuery = Order::select('orders_id', 'orders_no', 'sub_total', 'ship_status','order_total', 'refund_amount', 'pay_status', 	'status', 'payment_type', 'use_credit_limit', 'payment_method', 'phoneorder_payby', 'route_shipping_insurance_charge','bill_first_name', 'bill_last_name','bill_email', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i') AS datetime,DATEDIFF(now(), order_datetime) as days"))
							->where('customer_id', '=', Auth::user()->customer_id)
							->orderBy('orders_id', 'DESC')->limit(5)->get();

		$this->PageData['OrdResult'] = $OrdResultQuery;
		//dd(Session::get('LoyaltyTier'));

		$ExclusiveCustomers = Order::select('orders_id', 'orders_no', 'sub_total', 'ship_status','order_total', 'refund_amount', 'pay_status', 'status', 'payment_type', 'use_credit_limit', 'payment_method', 'phoneorder_payby', 'route_shipping_insurance_charge','bill_first_name', 'bill_last_name','bill_email', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i') AS datetime,DATEDIFF(now(), order_datetime) as days"))
							->where('customer_id', '=', Session::get('sess_icustomerid'))
							->where('status', '!=', 'Declined')
							->where('order_total', '>', 200)
							->having('days', '<=', 60)
							->get();

		if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0 && count($ExclusiveCustomers) > 0){
			$this->PageData['IsExclusiveCustomers'] = 'Yes';
		}else{
			$this->PageData['IsExclusiveCustomers'] = 'No';
		}

		//require_once app_path('Libraries/phpqrcode/qrlib.php');

		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		// $this->PageData['qr_image'] = "";
		// if(isset($normaluser->customer_id) && $normaluser->customer_id > 0){
		// 	$url = config('global.SITE_URL')."store/customer-login.html?k=".base64_encode($normaluser->customer_id);
		// 	ob_start();
		// 	\QRcode::png($url, null, QR_ECLEVEL_M, 6, 2);
		// 	$image = ob_get_clean();
		// 	$qrBase64 = 'data:image/png;base64,' . base64_encode($image);
		// 	$this->PageData['qr_image'] = $qrBase64;
		// }

		return view('myaccount.dashboard')->with($this->PageData);
	}

	public function ForgotPassword(Request $request)
	{
		if(isset($request->action) && $request->action == 'forgot_password')
		{
	        $validatedData = $request->validate([
					            'email' => 'required|email',
								'g-recaptcha-response' => 'required'
					        ], [
					            'email.required' => config('message.Forgot.ValidEmail'),
					            'email.email' => config('message.Forgot.ValidEmail'),
								'g-recaptcha-response.required' => config('message.Validate.Captcha')
					        ]);
			$ChkEmail = Customer::where('email','=',$request['email'])->where('status','=','1')->get();
			if($ChkEmail)
			{
				if(empty($ChkEmail[0]->password))
				{
					return redirect()->back()
					->withInput()
					->withErrors([
						'NotExistEmail' => config('message.Forgot.NotExistEmail'),
					]);
				} else {
					$FreeShipping = "";
					if(config('Settings.FREESHIPPING_VALUE'))
						$FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
					$Template = GetMailTemplate("FORGOT_PASSWORD");
					$EmailBody = $Template[0]->mail_body;
					$EmailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$EmailBody);
					$EmailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$EmailBody);
					$EmailBody = str_replace('{$freeshippinginfo}',$FreeShipping,$EmailBody);
					$EmailBody = str_replace('{$vemail}',$ChkEmail[0]->email,$EmailBody);
					$EmailBody = str_replace('{$password}',$ChkEmail[0]->password,$EmailBody);
					$EmailBody = str_replace('{$TOLL_FREE_NO}',config('Settings.TOLL_FREE_NO'),$EmailBody);
					$EmailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$EmailBody);

					$To = $ChkEmail[0]->email;
					$Subject = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$Template[0]->subject);
					$From = config('Settings.CONTACT_MAIL');

                    if(config('global.OMNISEND_PROG') == false)
                    {
                       SendMail($Subject,$EmailBody,$To,$From);
                    } else {
                        /** OMANISEND **/
                        OmanisendRequest('61e048930e8680001cd923aa',$ChkEmail[0]);
                        /** OMANISEND **/
                    }

					Session::flash('success', config('message.Forgot.Success'));
					return redirect()->back();
				}
			}
		}
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Forgot Password';
		$this->PageData['JSFILES'] = ['forgotpassword.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('customer.forgotpassword')->with($this->PageData);
	}

	public function SendMails(Request $request)
	{
		$Template = GetMailTemplate("FORGOT_PASSWORD");
		$To = 'qqualdev@gmail.com';
		$Subject = $Template[0]->subject;
		$EmailBody = $Template[0]->mail_body;
		$From = "qualdev.devs@gmail.com";
		SendMail($Subject,$EmailBody,$To,$From);
	}

	public function EditProfile(Request $request)
	{
		$this->PageData['Countries'] = GetCountries();
		$this->PageData['States'] = GetStates();
		$this->PageData['SelCountry'] = 'US';

		$Userdata = Customer::select('customer_id', 'email', 'first_name', 'last_name', 'address1', 'address2', 'city', 'country', 'state', 'zip', 'phone', 'company_name', 'birthday', 'eusertype', 'salestax_id', 'einnumber', 'upload_sales_tax')
								->where('customer_id', '=', Auth::user()->customer_id)
								->get();
		if($request['action'] == 'update')
		{
			$this->PageData['SelCountry'] = $request['country'];

	        $validatedData = $request->validate([
								'first_name' => 'required',
								'last_name'	=> 'required',
								'address1' => 'required',
								'city' => 'required',
								'zip' => 'required',
								'phone' => 'required',
								'country' => 'required',
					            'state' => 'required_if:country,US',
					            'other_state' => 'required_unless:country,US',
					            // 'einnumber' => 'required_if:state,NY',	// Sometime means if 'einnumber' exists in request array then it will be required
					        ], [
					            'first_name.required' => config('message.Validate.FirstName'),
					            'last_name.required' => config('message.Validate.LastName'),
					            'address1.required' => config('message.Validate.Address'),
					            'city.required' => config('message.Validate.City'),
					            'zip.required' => config('message.Validate.ZipCode'),
					            'phone.required' => config('message.Validate.Phone'),
					            'country.required' => config('message.Validate.Country'),
					            'state.required_if' => config('message.Validate.State'),
					            'other_state.required_unless' => config('message.Validate.OtherState'),
					            // 'einnumber.required_if' => config('message.Register.EINnumber'),
					        ]);

			if($request['country'] == 'US' && $request['state'] == 'NY' && Session::get("eusertype")=="Wholesaler" && $request['einnumber'] == '')
			{
				return redirect()->back()
				->withInput()
				->withErrors([
					'einnumber' => config('message.Register.EINnumber'),
				]);
			}
			$state = $request['state'];
			if($request['country'] != 'US')
				$state = $request['other_state'];
			$UserDataArray = array(
				'first_name' => $request['first_name'],
				'last_name' => $request['last_name'],
				'company_name' => ($request['company_name'] != '') ? $request['company_name'] : '',
				'address1' => $request['address1'],
				'address2' => ($request['address2'] != '') ? $request['address2'] : '',
				'birthday' => ($request['birthday'] != null) ? $request['birthday'] : null,
				'phone' => $request['phone'],
				'city' => $request['city'],
				'state' => $state,
				'country' => $request['country'],
				'zip' => $request['zip'],
				'upd_datetime' => date('Y-m-d H:i:s')
			);

			$UserDataArray['einnumber'] = "";
			if(Session::get("eusertype")=="Wholesaler" && $UserDataArray['state'] == 'NY' && $UserDataArray['country'] == 'US' )
		    {
			 	// $UserDataArray['salestax_id'] = $UserDataArray["salestax_id"];
			 	$UserDataArray['einnumber'] = $request['einnumber'];
			}

			$User = Customer::find(Auth::user()->customer_id);

			$User->update($UserDataArray);
			if($User)
			{
				if(Session::get("eusertype")=="Wholesaler" && $request->hasFile('upload_sales_tax')){
					$image = $request->file('upload_sales_tax');
					$image_name = $image->getClientOriginalName();
					$destinationPath = base_path('images/homeimg/');
					$upload = $image->move($destinationPath, $image_name);

					$uploadArr = array(
						'upload_sales_tax'				=> $image_name,
						'resale_certificate_status'	=> 'Pending',
					);

					$User->update($uploadArr);

					Session::put('resale_certificate',$User->upload_sales_tax);
					Session::put('resale_certificate_status',$User->resale_certificate_status);
				}

				Session::flash('success', config('message.EditProfile.Success'));
				return redirect()->back();
			}
		}
		if($Userdata[0]->birthday != null) {
			$this->PageData['CSSFILES'] = ['myaccount.css'];
		} else {
			$this->PageData['CSSFILES'] = ['myaccount.css', 'datepicker.css'];
		}
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Edit Profile';
		$this->PageData['JSFILES'] = ['moment.min.js', 'editprofile.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		$this->PageData['Userdata'] = $Userdata;
		return view('myaccount.editprofile')->with($this->PageData);
	}

	public function ChangePassword(Request $request)
	{
		if(isset($request['old_password']))
		{
	        $validatedData = $request->validate([
					            'old_password' => 'required',
					            'new_password' => 'required|min:6|regex:/^\S*$/u',
								're_type_new_password' 	=> 'required|same:new_password',
					        ], [
					            'old_password.required' => config('message.Validate.OldPassword'),
					            'new_password.required' => config('message.Validate.RequiredNewPassword'),
					            'new_password.min' => config('message.Validate.NewPassword'),
					            're_type_new_password.required' => config('message.Validate.ConfirmPassword'),
					            're_type_new_password.same' => config('message.Validate.DoesNotMatch'),
					            'new_password.regex' => config('message.Validate.NewPassword'),
					        ]);
	        /* At least one uppercase letter and one number */
	        if(!preg_match('/[a-z]/', $request->new_password) || !preg_match('/[A-Z]/', $request->new_password) || !preg_match('/[0-9]/', $request->new_password))
			{
				return redirect()->back()
				->withInput()
				->withErrors([
					'uppercase_number' => config('message.Validate.UpperCaseAndLetter')
				]);
			}
			$checkOldPassword = Customer::where('customer_id','=',Auth::user()->customer_id)->where('password','=',$request['old_password'])->get();

			if($checkOldPassword->count() <= 0)
			{
				return redirect()->back()
				->withInput()
				->withErrors([
					'wrong_password' => config('message.ChangePassword.WrongOldPassword')
				]);
			}
			else
			{
				$UserData = array(
					'password' => $request['new_password'],
				);
				$User = Customer::find(Auth::user()->customer_id);
				$User->update($UserData);
				if($User)
				{
					Session::flash('success', config('message.ChangePassword.Success'));
					return redirect()->back();
				}
			}
		}

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Change Password';
		$this->PageData['JSFILES'] = ['changepassword.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('myaccount.changepassword')->with($this->PageData);
	}

	public function ReferralCustomer(Request $request)
	{
		$referral_customer = ReferFriend::select('receiver', 'refer_comment', 'is_sender_notified', DB::raw('DATE_FORMAT(refer_datetime, "%m/%d/%Y %H:%i") as datetime'))
							->where('customer_id', '=', Auth::user()->customer_id)
							->paginate(10);

		$this->PageData['referral_customer'] = $referral_customer;

		$reward_point = 0;
		if(strtolower(Session::get('eusertype')) == 'retailer') {
			$rewrd_cust = Customer::select('iRewardpoint')
								->where('customer_id', '=', Auth::user()->customer_id)
								->get();
			$reward_point = $rewrd_cust[0]['iRewardpoint'];
		}
		$this->PageData['reward_point'] = $reward_point;

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Referral Customer List';
		$this->PageData['CSSFILES'] = ['myaccount.css', 'pagination.css'];
		return view('myaccount.referralcustomer')->with($this->PageData);
	}

	public function AddressBook(Request $request)
	{
		$this->PageData['address_book'] = AddressBook::select('address_book_id', 'first_name', 'last_name', 'company_name', 'address1', 'address2', 'phone', 'city', 'zip', 'state', 'country')
							->where('customer_id', '=', Auth::user()->customer_id)
							->where('status', '=', '1')
							->where('title', '!=', 'Self Address')
							->paginate(10);

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Address Book';
		$this->PageData['JSFILES'] = ['addressbook.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css', 'pagination.css'];
		return view('myaccount.addressbooklist')->with($this->PageData);
	}

	public function AddAddressbook(Request $request)
	{
		$this->PageData['Countries'] = GetCountries();
		$this->PageData['States'] = GetStates();
		if($request['action'] == 'submit') {

	        $validatedData = $request->validate([
								'first_name' => 'required',
								'last_name'	=> 'required',
								'address1' => 'required',
								'city' => 'required',
								'country' => 'required',
								'zip' => 'required',
								'phone' => 'required',
					            'email' => 'required|email',
					            'state' => 'required_if:country,US',
					            'other_state' => 'required_unless:country,US',
					        ], [
					            'first_name.required' => config('message.Validate.FirstName'),
					            'last_name.required' => config('message.Validate.LastName'),
					            'address1.required' => config('message.Validate.Address'),
					            'city.required' => config('message.Validate.City'),
					            'country.required' => config('message.Validate.Country'),
					            'zip.required' => config('message.Validate.ZipCode'),
					            'phone.required' => config('message.Validate.Phone'),
					            'email.required' => config('message.Validate.ValidEmail'),
					            'email.email' => config('message.Validate.ValidEmail'),
					            'state.required_if' => config('message.Validate.State'),
					            'other_state.required_unless' => config('message.Validate.OtherState'),
					        ]);

			$state = $request['state'];
			if($request['country'] != 'US')
				$state = $request['other_state'];
			$AddressDataArray = array(
				'customer_id' => Auth::user()->customer_id,
				// 'title' => $request['title'],
				'first_name' => $request['first_name'],
				'last_name' => $request['last_name'],
				'address1' => $request['address1'],
				'address2' => ($request['address2'] != '')?$request['address2']:'',
				'city' => $request['city'],
				'state' => $state,
				'zip' => $request['zip'],
				'country' => $request['country'],
				'email' => $request['email'],
				'phone' => $request['phone'],
				'status' => '1',
			);
			$Address = Addressbook::create($AddressDataArray);
			if($Address) {
				Session::flash('success', config('message.Addressbook.AddSuccess'));
				return redirect(config('global.SITE_URL').'addressbook.html');
			}

		}

		$this->PageData['SelCountry'] = 'US';
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Address Book';
		$this->PageData['JSFILES'] = ['addressbook.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('myaccount.addaddressbook')->with($this->PageData);
	}

	public function EditAddressbook(Request $request)
	{
		$this->PageData['Countries'] = GetCountries();
		$this->PageData['States'] = GetStates();
		$this->PageData['SelCountry'] = 'US';
		if($request['action'] == 'update') {

	        $validatedData = $request->validate([
								'first_name' => 'required',
								'last_name'	=> 'required',
								'address1' => 'required',
								'city' => 'required',
								'country' => 'required',
								'zip' => 'required',
								'phone' => 'required',
					            'email' => 'required|email',
					            'state' => 'required_if:country,US',
					            'other_state' => 'required_unless:country,US',
					        ], [
					            'first_name.required' => config('message.Validate.FirstName'),
					            'last_name.required' => config('message.Validate.LastName'),
					            'address1.required' => config('message.Validate.Address'),
					            'city.required' => config('message.Validate.City'),
					            'country.required' => config('message.Validate.Country'),
					            'zip.required' => config('message.Validate.ZipCode'),
					            'phone.required' => config('message.Validate.Phone'),
					            'email.required' => config('message.Validate.ValidEmail'),
					            'email.email' => config('message.Validate.ValidEmail'),
					            'state.required_if' => config('message.Validate.State'),
					            'other_state.required_unless' => config('message.Validate.OtherState'),
					        ]);

			$state = $request['state'];
			if($request['country'] != 'US')
				$state = $request['other_state'];
			$AddressDataArray = array(
				'customer_id' => Auth::user()->customer_id,
				// 'title' => $request['title'],
				'first_name' => $request['first_name'],
				'last_name' => $request['last_name'],
				'address1' => $request['address1'],
				'address2' => ($request['address2'] != '')?$request['address2']:'',
				'city' => $request['city'],
				'state' => $state,
				'zip' => $request['zip'],
				'country' => $request['country'],
				'phone' => $request['phone'],
				'status' => '1',
				'email' => $request['email']
			);
			$Address = Addressbook::find($request['id']);
			$Address->update($AddressDataArray);
			if($Address) {
				Session::flash('success', config('message.Addressbook.EditSuccess'));
				return redirect(config('global.SITE_URL').'addressbook.html');
			}
		}

		$this->PageData['addressdata'] = Addressbook::where('address_book_id','=',$request['id'])->where('status','=','1')->where('customer_id','=',Auth::user()->customer_id)->first();

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Address Book';
		$this->PageData['JSFILES'] = ['addressbook.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('myaccount.editaddressbook')->with($this->PageData);
	}

	public function RemoveAddressbook(Request $request)
	{
		if($request->ajax()){
			if(!Auth::user()) {
		        $response['status'] = 401;
		        $response['message'] = "Unauthorized request";
		        return response()->json($response, 400);
			} else {
				if(isset($request->id) && ($request->id) != '') {
					Addressbook::where('address_book_id',$request->id)->delete();
			        $response['status'] = 200;
			        $response['message'] = "Success";
			        return response()->json($response, 200);
				} else {
			        $response['status'] = 400;
			        $response['message'] = "Bad parameters";
			        return response()->json($response, 400);
				}
			}
		} else {
	        $response['status'] = 404;
	        $response['message'] = "Not found";
	        return response()->json($response, 400);
		}
	}

	public function MyRewardPoint(Request $request)
	{
		if(Auth::user() && Auth::user()->eusertype != 'Wholesaler Pending' &&  strtolower(Session::get('eusertype')) == 'retailer') {
			//echo Auth::user()->customer_id;
			$rewrd_cust = Customer::select('iRewardpoint')
								->where('customer_id', '=', Auth::user()->customer_id)
								->get();
			$this->PageData['reward_point'] = $rewrd_cust[0]['iRewardpoint'];
			if(Auth::user()->customer_id == 34){
				//$this->PageData['reward_point'] = "200";
			}
			$max_reward = RewardRule::select('fcharge')
								->where('erewardrule', '=', 'max')
								->get();
			$this->PageData['maximum_reward_point'] = (int)$max_reward[0]['fcharge'];
			$this->PageData['CSSFILES'] = ['myaccount.css'];

			$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: My Reward Points';
			return view('myaccount.myrewardpoint')->with($this->PageData);
		} else {
			return redirect(config('global.SITE_URL'));
		}
	}

	public function OrderCancel(Request $request)
	{
		$IsGiftCertificateItem = '';
		$GC_IMAGE_URL = '';

		/*$status_array = ['Pending','Canceled','Request Cancellation','Cancellation Approved','Sent To Stripe'];
		$prodCntRes = Order::select('orders_id', DB::raw('DATE_FORMAT(order_datetime, "%m/%d/%Y %H:%i") as datetime, DATEDIFF(now(), order_datetime) as days'))
							->where('customer_id', '=', Auth::user()->customer_id)
							->whereIn('status', $status_array)
							->orderBy('order_datetime', 'DESC')
							->orderBy('orders_id', 'DESC')
							->get();
		$this->PageData['prodCntRes'] = $prodCntRes;*/
		//dd($prodCntRes);

		$order_status_array = ['Canceled','Request Cancellation','Cancellation Approved']; //'Pending'
		$OrdResult = Order::select('orders_id', 'orders_no', 'sub_total', 'order_total', 'refund_amount', 'pay_status', 'status', 'payment_type', 'use_credit_limit', 'payment_method', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i') AS datetime,DATEDIFF(now(), order_datetime) as days"))
							->where('customer_id', '=', Auth::user()->customer_id)
							->whereIn('status', $order_status_array)
							->orderBy('order_datetime', 'DESC')
							->orderBy('orders_id', 'DESC')
							->paginate(50);
		if($OrdResult && $OrdResult->count() > 0) {
			foreach ($OrdResult as $order_result_key => $order_result_value) {
				// $order_detail_status_array = ['Pending','Completed','Return Requested','Return Approved','Return Rejected','Item Received'];
				$OrdDetailResult = OrderDetail::select('pu_order_detail.orders_detail_id', 'pu_order_detail.product_name', 'pu_order_detail.sku', 'pu_order_detail.price', 'pu_order_detail.ostatus', 'pu_order_detail.quantity', 'pu_order_detail.total')
									->where('pu_order_detail.orders_id', $order_result_value['orders_id'])
									// ->whereIn('pu_order_detail.ostatus', $order_detail_status_array)
									->whereRaw("(pu_order_detail.is_free_gift_products='No' || pu_order_detail.is_free_gift_products='')")
									->orderBy('pu_order_detail.orders_detail_id', 'DESC')
									->get();

				if(count($OrdDetailResult) > 0) {
					foreach ($OrdDetailResult as $order_details_key => $order_details_value) {

						$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$order_details_value,'No');

						/*if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL'));
						}
						else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU1'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL1'));
						}
						else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU2'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL2'));
						}*/

						if($IsGiftCertificateItem == 'Yes'){

							$GC_IMAGE_URL = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$order_details_value,'No');

							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], $GC_IMAGE_URL);
						}
						else
						{
							$prod_res = Products::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->select('image')->first();
							if($prod_res && $prod_res->count() > 0) {
								if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->image) && !empty($prod_res->image))
									$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->image;
								 else
									$thumb_image = config('global.NO_IMAGE_THUMB');
							} else {
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
							$OrdDetailResult[$order_details_key]['Image'] = $thumb_image;
						}

					}
				}

				$OrdResult[$order_result_key]['order_items'] = $OrdDetailResult;
			}
		}
		$this->PageData['OrdResult'] = $OrdResult;
		// dd($OrdResult);
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Order Cancel';
		$this->PageData['CSSFILES'] = ['myaccount.css', 'pagination.css'];

		return view('myaccount.ordercancel')->with($this->PageData);
	}

	public function OrderHistory(Request $request)
	{
		$IsGiftCertificateItem = '';
		$GC_IMAGE_URL = '';

		$isval		 = $request['isval'];
		$orders_no	 = isset($request['orders_no']) ? $request['orders_no'] : '';
		$this->PageData['orders_no'] = $orders_no;
		$FilterByOrderNo = isset($request['FilterByOrderNo']) ? $request['FilterByOrderNo'] : 'YourOrderNo';
		$this->PageData['FilterByOrderNo'] = $FilterByOrderNo;
		/*$prodCntResQuery = Order::select('orders_id')
								->where('customer_id', '=', Auth::user()->customer_id);
		$status_array = ['Pending','Canceled','Refund','Sent To Stripe'];
		if($isval == "yes")
		{
			$prodCntResQuery->whereIn('status', $status_array)
							->where('payment_type', '=', 'PAYMENT_DS');
		}
		if($orders_no != ""){
			$prodCntResQuery->where('orders_no', '=', $orders_no);
		}
		$prodCntRes = $prodCntResQuery->groupBy('orders_id')
									->orderBy('order_datetime', 'DESC')
									->orderBy('orders_id', 'DESC')
									->get();
		$this->PageData['prodCntRes'] = $prodCntRes;
		$total_count = count($prodCntRes);*/

		$OrdResultQuery = Order::select('orders_id', 'orders_no', 'sub_total', 'ship_status','order_total', 'refund_amount', 'pay_status', 'status', 'payment_type', 'order_type', 'use_credit_limit', 'payment_method', 'phoneorder_payby', 'route_shipping_insurance_charge','bill_first_name', 'bill_last_name','bill_email','tracking_no','ship_method', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i') AS datetime,DATE_FORMAT(EstimatedDeliveryDate, '%m/%d/%Y %H:%i:%s') AS EstimatedDeliveryDate,DATEDIFF(now(), order_datetime) as days"))
							->where('customer_id', '=', Auth::user()->customer_id)
							->where('status', '!=', 'Declined');
		$status_array = ['Pending','Completed','Refund'];
		if($isval == "yes")
		{
			$OrdResultQuery->whereIn('status', $status_array)
							->where('payment_type', '=', 'PAYMENT_DS');
		}

		/*if($orders_no != ""){
			if($request['FilterByOrderNo'] == 'MaxaromaOrderNo') {
				$OrdResultQuery->where('orders_no', '=', $orders_no);
			}
			if($request['FilterByOrderNo'] == 'YourOrderNo') {
				$OrdResultQuery->where('orders_id', '=', $orders_no);
			}
		}*/

		if($FilterByOrderNo!='') {
			if($orders_no != "") {
				//$OrdResultQuery->whereRaw("(orders_no = '".$orders_no."' OR dropshipper_order_no='".$orders_no."')");
				$OrdResultQuery->whereRaw("(orders_no = '".$orders_no."' OR dropshipper_order_no='".$orders_no."' OR orders_id = '".$orders_no."' )");
			}
		}
		$OrdResult = $OrdResultQuery->orderBy('order_datetime', 'DESC')
									->orderBy('orders_id', 'DESC')
									->paginate(50);
		if(count($OrdResult) > 0) {
			foreach ($OrdResult as $order_result_key => $order_result_value) {

				$todayDateTime = $EstimatedDeliveryDate = $show_ship_status = '';

				$todayDateTime = Carbon::now();
				$EstimatedDeliveryDate = $order_result_value->EstimatedDeliveryDate;
				$EstimatedDeliveryDate = Carbon::parse($EstimatedDeliveryDate);

				if($order_result_value->ship_status == 'Shipped' && $todayDateTime->isAfter($EstimatedDeliveryDate)){
					$order_result_value->show_ship_status = 'Delivered';
				}
				else{
					$order_result_value->show_ship_status = '';
				}

				$claimPoliceReport = Claim::select('police_report_document','pictures_videos')
								->where('orders_id', '=', $order_result_value['orders_id'])
								->first();

				if($claimPoliceReport && $claimPoliceReport->count() > 0){
					$reportDoc	= $claimPoliceReport->police_report_document;
					$reportPicturesVideos	= $claimPoliceReport->pictures_videos;
				} else {
					$reportDoc	= '';
					$reportPicturesVideos = '';
				}

				if(trim($reportDoc) != '' && file_exists(config('global.RETURN_PDF_PATH').'claims/'.$reportDoc)){
					$policeReportAttach	= config('global.RETURN_PDF_URL').'claims/'.$reportDoc;
				} else {
					$policeReportAttach	= '';
				}

				$order_detail_status_array = ['Pending','Completed','Return Requested','Return Approved','Return Rejected','Item Received'];
				$OrdDetailResult = OrderDetail::select('pu_order_detail.orders_detail_id', 'pu_order_detail.product_name', 'pu_order_detail.sku', 'pu_order_detail.price', 'pu_order_detail.ostatus', 'pu_order_detail.quantity', 'pu_order_detail.total','pu_order_detail.is_free_gift_products','pu_order_detail.excluded_flag')
									->where('pu_order_detail.orders_id', $order_result_value['orders_id'])
									->whereIn('pu_order_detail.ostatus', $order_detail_status_array)
									//->whereRaw("(pu_order_detail.is_free_gift_products='No' || pu_order_detail.is_free_gift_products='')")
									->orderBy('pu_order_detail.orders_detail_id', 'DESC')
									->get();

				if(count($OrdDetailResult) > 0) {
					foreach ($OrdDetailResult as $order_details_key => $order_details_value) {

						$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$order_details_value,'No');

						/*if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL'));
						}
						else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU1'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL1'));
						}
						else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU2'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL2'));
						}*/

						if($IsGiftCertificateItem == 'Yes'){
							$GC_IMAGE_URL = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$order_details_value,'No');

							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], $GC_IMAGE_URL);
						}
						else
						{
							$prod_res = Products::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->select('image')->first();
							if($prod_res && $prod_res->count() > 0) {
								if(file_exists(config('global.PRD_LARGE_IMG_PATH').$prod_res->image) && !empty($prod_res->image)){
									$imgver = filemtime(config('global.PRD_LARGE_IMG_PATH').stripslashes($prod_res->image));
									$thumb_image = config('global.PRD_LARGE_IMG_URL').$prod_res->image."?ver=".$imgver;
								} else {
									$thumb_image = config('global.NO_IMAGE_LARGE');
								}
							} else {
								$thumb_image = config('global.NO_IMAGE_LARGE');
							}
							$OrdDetailResult[$order_details_key]['Image'] = $thumb_image;
						}

                        $return_res = ReturnOrders::select('reason', 'quantity', 'orders_detail_id', 'return_shipping_label')
													->where('orders_id', '=', $order_result_value['orders_id'])
													->get();
						$tot_reqs = count($return_res);
						if($tot_reqs > 0)
						{
							for($d=0; $d < $tot_reqs; $d++){
								$reason_arr = explode("##",$return_res[$d]["reason"]);
								$qty_arr = explode("##",$return_res[$d]["quantity"]);
								$order_detail_arr = explode("##",$return_res[$d]["orders_detail_id"]);
								foreach($order_detail_arr as $key_ids => $detail_ids){
									$Return_data[$detail_ids]['reason'] = $reason_arr[$key_ids];
									$Return_data[$detail_ids]['qty'] = $qty_arr[$key_ids];

									$Return_data[$detail_ids]['base_orderdetailid'] = $order_detail_arr[0];
									$Return_data[$detail_ids]['returnlabel'] = $return_res[$d]["return_shipping_label"];
								}
							}
						}
						$OrdDetailResult[$order_details_key]["Reason"]  = '';
						$OrdDetailResult[$order_details_key]["Qty"]  = 0;

						$OrdDetailResult[$order_details_key]["return_shipping_label"]  = '';

						if(!empty($Return_data[$order_details_value["orders_detail_id"]])){
							$OrdDetailResult[$order_details_key]["Qty"] = $Return_data[$order_details_value["orders_detail_id"]]['qty'];
							$OrdDetailResult[$order_details_key]["Reason"] = $Return_data[$order_details_value["orders_detail_id"]]['reason'];

							$OrdDetailResult[$order_details_key]["COrderNo"] = "";
							$returnLabelFile = "Return-Authorization-Order-".$order_result_value["orders_no"]."-".$Return_data[$order_details_value["orders_detail_id"]]['base_orderdetailid'].".pdf";

							if(file_exists(config('global.RETURN_PDF_PATH').$returnLabelFile)){
								$OrdDetailResult[$order_details_key]["COrderNo"] = config('global.RETURN_PDF_URL').$returnLabelFile;
							}

							$ShippingLabel = '';

							$returnlabel = $Return_data[$order_details_value["orders_detail_id"]]['returnlabel'];
							if($returnlabel != "" && file_exists(config('global.RETURN_PDF_PATH').$returnlabel))
							{
								$ShippingLabel = '<a href="'.url('/returnord/'.$Return_data[$order_details_value["orders_detail_id"]]['returnlabel']).'" target="_blank" class="btn btn-small btn-primary">Shipping Label</a>';
								$OrdDetailResult[$order_details_key]["return_shipping_label"] =$ShippingLabel;
							}
							else
							{
								$OrdDetailResult[$order_details_key]["return_shipping_label"]  = '';
							}

						}

						if($order_result_value["ostatus"]!="Pending" && $order_result_value["ostatus"]!="Completed")
						{
							$OrdDetailResult[$order_details_key]["TotalPriceVal"] = $OrdDetailResult[$order_details_key]["Qty"] * $order_result_value["price"];
							$OrdDetailResult[$order_details_key]["TotalPriceVal"] = number_format($OrdDetailResult[$order_details_key]["TotalPriceVal"],2,'.','');
						}

						if($tot_reqs == 1 && !empty($Return_data[$order_result_value["orders_detail_id"]])){
							$Data_Return[$order_result_key]['COrderNo'] = $OrdDetailResult[$order_details_key]["COrderNo"];
							$Data_Return[$order_result_key]['return_shipping_label'] = $OrdDetailResult[$order_details_key]["return_shipping_label"];
						}

					}
				}
				$OrdResult[$order_result_key]['policeReportAttach'] = $policeReportAttach;
				$OrdResult[$order_result_key]['order_items'] = $OrdDetailResult;
			}
		}
		$this->PageData['OrdResult'] = $OrdResult;

		if(Session::has('SKULISTARR') && count(Session::get('SKULISTARR')) > 0) {
			$skuval = implode("','", Session::get('SKULISTARR'));
			$OutOfStockMsg = "Following products sku(s) are out of stock or inactive <br/>".$skuval;
			Session::forget('SKULISTARR');
		} else {
			$OutOfStockMsg = '';
		}
		// $OutOfStockMsg = 'Following products sku(s) are out of stock or inactive';
		$this->PageData['OutOfStockMsg'] = $OutOfStockMsg;
		if(Session::has('OrderNotProcess') && count(Session::get('OrderNotProcess')) > 0) {
			$skuval = implode("','", Session::get('OrderNotProcess'));
			$OrderProcessMsg = "Following order(s) are not processed,shipping method is not selected  <br/>".$skuval;
			Session::forget('OrderNotProcess');
		} else {
			$OrderProcessMsg = '';
		}
		// $OrderProcessMsg = 'Following order(s) are not processed,shipping method is not selected';
		$this->PageData['OrderProcessMsg'] = $OrderProcessMsg;

		$view_fileType = "";
		// if(Session::get('eusertype') == "Wholesaler" || (Session::get('eusertype')=="Wholesaler" && Session::get('is_dropshipper')=="Yes")) {
			// $view_fileType  = 'pdf';
		// } else {
			// $view_fileType = 'print';
		// }

		$view_fileType  = 'pdf';	//for retailer and wholesaler both
		$this->PageData['view_fileType'] = $view_fileType;

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Order History';
		$this->PageData['JSFILES'] = ['orderhistory.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css', 'pagination.css'];

		return view('myaccount.orderhistory')->with($this->PageData);
	}

    public function OrderReturnHistory(Request $request)
	{
		$IsGiftCertificateItem = '';

		$ostatus_array = ['Return Requested','Return Approved','Return Rejected','Item Received','Refund'];
		$odstatus_array = ['Return Requested','Return Approved','Return Rejected','Item Received','Refund'];

		//if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
		if(Session::has('sess_useremail') && Session::get('sess_useremail') == 'gequaldev@gmail.com')
		{
				//dd($ManufactureStr);
				config(['app.debug' => true]); // error Val

		}
		$OrderReturnsHistory = DB::table('pu_orders as o')
						->select('o.order_datetime','od.price as unitprice','od.ostatus as ostatus','od.orders_detail_id','od.quantity','od.total','od.product_name','od.sku','od.price','o.orders_id', 'o.orders_no', 'o.sub_total', 'o.order_total', 'o.refund_amount', 'o.pay_status', 'o.status', 'o.payment_type', 'o.use_credit_limit', 'o.payment_method', 'o.phoneorder_payby')
						->join('pu_order_detail as od', 'o.orders_id', '=', 'od.orders_id')
						->where('o.customer_id', '=', Auth::user()->customer_id)
						->whereIn('o.status', $ostatus_array)
						->whereIn('od.ostatus', $odstatus_array)
						->orderBy('order_datetime', 'DESC')
						->orderBy('orders_id', 'DESC')
						->paginate(10);

				if($OrderReturnsHistory->count() > 0) {
					foreach ($OrderReturnsHistory as $order_details_key => $order_details_value) {
						$return_res = ReturnOrders::select('reason', 'quantity', 'orders_detail_id', 'return_shipping_label')
													->where('orders_id', '=', $order_details_value->orders_id)
													->where('orders_detail_id', '=', $order_details_value->orders_detail_id)
													->get();

						$OrderReturnsHistory[$order_details_key]->Reason = '';
						$OrderReturnsHistory[$order_details_key]->Qty = 0;
						if(count($return_res) > 0)
						{
							$OrderReturnsHistory[$order_details_key]->Reason = $return_res[0]["reason"];
							$OrderReturnsHistory[$order_details_key]->Qty	= $return_res[0]["quantity"];
						}

						//$OrderReturnsHistory[$order_details_key]->COrderNo = "Return-Authorization-Order-".$OrderReturnsHistory[$order_details_key]->orders_no."-".$OrderReturnsHistory[$order_details_key]->orders_detail_id.".pdf";
						$OrderReturnsHistory[$order_details_key]->COrderNo = "";
						$returnLabelFile = "Return-Authorization-Order-".$OrderReturnsHistory[$order_details_key]->orders_no."-".$OrderReturnsHistory[$order_details_key]->orders_detail_id.".pdf";

						if(file_exists(config('global.RETURN_PDF_PATH').$returnLabelFile)){
							$OrderReturnsHistory[$order_details_key]->COrderNo = config('global.RETURN_PDF_URL').$returnLabelFile;
						}

						if($OrderReturnsHistory[$order_details_key]->ostatus!="Pending" && $OrderReturnsHistory[$order_details_key]->ostatus!="Completed")
						{
							$OrderReturnsHistory[$order_details_key]->TotalPriceVal =  $OrderReturnsHistory[$order_details_key]->Qty * $OrderReturnsHistory[$order_details_key]->price;
							$OrderReturnsHistory[$order_details_key]->TotalPriceVal = number_format($OrderReturnsHistory[$order_details_key]->TotalPriceVal,2,'.','');

						}

						$ShippingLabel = '';
						if(file_exists(config('global.RETURN_PDF_PATH').'Return-Shipping-Label-'.$OrderReturnsHistory[$order_details_key]->orders_no."-".$OrderReturnsHistory[$order_details_key]->orders_detail_id.".pdf"))
						{
							//$ShippingLabel = '<a href="'.config('global.SITE_URL').'/returnord/Return-Shipping-Label-'.$OrderReturnsHistory[$order_details_key]->orders_no.'-'.$OrderReturnsHistory[$order_details_key]->orders_detail_id.'.pdf" target="_blank" class="button btn-1 btn-small" style="padding-left:8px;">Print Shipping Label</a>';
							$ShippingLabel = '<a href="'.config('global.SITE_URL').'/returnord/Return-Shipping-Label-'.$OrderReturnsHistory[$order_details_key]->orders_no.'-'.$OrderReturnsHistory[$order_details_key]->orders_detail_id.'.pdf" target="_blank" class="btn btn-sm btn-primary">Shipping Label</a>';
							$OrderReturnsHistory[$order_details_key]->return_shipping_label =$ShippingLabel;
						}
						else
						{
							$OrderReturnsHistory[$order_details_key]->return_shipping_label  = '';
						}

						$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$order_details_value,'No');

						/*if($order_details_value->sku==config('global.GIFT_CERTIFICATE_SKU'))
						{
							$GCRs = GiftCertificate::where('orders_detail_id', '=', $order_details_value->orders_detail_id)
								->where('customer_id', '=', Auth::user()->customer_id)
								->first();
							if($GCRs && $GCRs->count() > 0) {
								$OrderReturnsHistory[$order_details_key]->RecipientName  	= $GCRs->recipient_name;
								$OrderReturnsHistory[$order_details_key]->RecipientEmail 	= $GCRs->recipient_email;
								$OrderReturnsHistory[$order_details_key]->SenderName  	= $GCRs->your_name;
								$OrderReturnsHistory[$order_details_key]->SenderEmail 	= $GCRs->your_email;
								$OrderReturnsHistory[$order_details_key]->Image			= config('global.GC_IMAGE_URL');
							}
						}
						else if($order_details_value->sku==config('global.GIFT_CERTIFICATE_SKU1'))
						{
							$GCRs = GiftCertificate::where('orders_detail_id', '=', $order_details_value->orders_detail_id)
								->where('customer_id', '=', Auth::user()->customer_id)
								->first();
							if($GCRs && $GCRs->count() > 0) {
								$OrderReturnsHistory[$order_details_key]->RecipientName  	= $GCRs->recipient_name;
								$OrderReturnsHistory[$order_details_key]->RecipientEmail 	= $GCRs->recipient_email;
								$OrderReturnsHistory[$order_details_key]->SenderName  	= $GCRs->your_name;
								$OrderReturnsHistory[$order_details_key]->SenderEmail 	= $GCRs->your_email;
								$OrderReturnsHistory[$order_details_key]->Image			= config('global.GC_IMAGE_URL1');
							}
						}
						else if($order_details_value->sku==config('global.GIFT_CERTIFICATE_SKU2'))
						{
							$GCRs = GiftCertificate::where('orders_detail_id', '=', $order_details_value->orders_detail_id)
								->where('customer_id', '=', Auth::user()->customer_id)
								->first();
							if($GCRs && $GCRs->count() > 0) {
								$OrderReturnsHistory[$order_details_key]->RecipientName  	= $GCRs->recipient_name;
								$OrderReturnsHistory[$order_details_key]->RecipientEmail 	= $GCRs->recipient_email;
								$OrderReturnsHistory[$order_details_key]->SenderName  	= $GCRs->your_name;
								$OrderReturnsHistory[$order_details_key]->SenderEmail 	= $GCRs->your_email;
								$OrderReturnsHistory[$order_details_key]->Image			= config('global.GC_IMAGE_URL2');
							}
						}*/

						if($IsGiftCertificateItem == 'Yes'){
							$GCRs = GiftCertificate::where('orders_detail_id', '=', $order_details_value->orders_detail_id)
								->where('customer_id', '=', Auth::user()->customer_id)
								->first();
							if($GCRs && $GCRs->count() > 0) {
								$OrderReturnsHistory[$order_details_key]->RecipientName  	= $GCRs->recipient_name;
								$OrderReturnsHistory[$order_details_key]->RecipientEmail 	= $GCRs->recipient_email;
								$OrderReturnsHistory[$order_details_key]->SenderName  	= $GCRs->your_name;
								$OrderReturnsHistory[$order_details_key]->SenderEmail 	= $GCRs->your_email;
								//$OrderReturnsHistory[$order_details_key]->Image			= config('global.GC_IMAGE_URL2');
								$OrderReturnsHistory[$order_details_key]->Image = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$order_details_value,'No');
							}
						}
						else
						{
							$prod_res = Products::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value->sku))."'")->select('image')->first();
							if($prod_res && $prod_res->count() > 0) {
								if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->image) && !empty($prod_res->image)){
									$imgver = filemtime(config('global.PRD_THUMB_IMG_PATH').stripslashes($prod_res->image));
									$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->image."?ver=".$imgver;
								} else {
									$thumb_image = config('global.NO_IMAGE_THUMB');
								}
							} else {
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
							$OrderReturnsHistory[$order_details_key]->Image = $thumb_image;
						}

					}
				}
		//echo "<pre>"; print_r($OrderReturnsHistory); exit;
		$this->PageData['OrdReturnHistory'] = $OrderReturnsHistory;

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Order History';
		$this->PageData['JSFILES'] = ['orderhistory.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css', 'pagination.css'];

		return view('myaccount.orderreturnhistory')->with($this->PageData);
	}

	public function OrderDetailPrint(Request $request)
	{
		$IsGiftCertificateItem = '';
		$GC_IMAGE_URL = '';

		/*if(Session::get('sess_icustomerid') == '' || Session::get('sess_icustomerid') <= 0){
			return redirect('/login.html');
		}*/

		$orders_id = (int)$request['id'];

		if(!Auth::user()){
			$OrderRs = Order::select('*', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i') AS datetime"))
							->where('orders_id', '=', $orders_id)
							->first();
		}else{
			$OrderRs = Order::select('*', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i') AS datetime"))
							->where('customer_id', '=', Auth::user()->customer_id)
							->where('orders_id', '=', $orders_id)
							->first();
		}
		$GC_Only = 0;
		if($OrderRs && $OrderRs->count() <= 0) {
			return redirect('/myaccount.html');
		} else {

			$todayDateTime = $EstimatedDeliveryDate = $show_ship_status = '';

			$todayDateTime = Carbon::now();
			$EstimatedDeliveryDate = $OrderRs->EstimatedDeliveryDate;
			$EstimatedDeliveryDate = Carbon::parse($EstimatedDeliveryDate);

			if($OrderRs->ship_status == 'Shipped' && $todayDateTime->isAfter($EstimatedDeliveryDate)){
				$OrderRs->show_ship_status = 'Delivered';
			}
			else{
				$OrderRs->show_ship_status = $OrderRs->ship_status;
			}

			$this->PageData['OrderRs'] = $OrderRs;
			$track_flag=1;
			$OrderDetailRs = OrderDetail::select('*')
								->where('orders_id', '=', $OrderRs->orders_id)
								->orderBy('orders_detail_id', 'DESC')
								->get();
			if(count($OrderDetailRs) > 0) {

				foreach ($OrderDetailRs as $order_details_key => $order_details_value) {

					$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$order_details_value,'No');
					$order_details_value["IsGiftCertificateItem"] = $IsGiftCertificateItem;
					/*if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL'));
					}
					else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU1'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL1'));
					}
					else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU2'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL2'));
					}*/

					if($IsGiftCertificateItem == 'Yes'){
						$GC_IMAGE_URL = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$order_details_value,'No');

						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], $GC_IMAGE_URL);
					}
					else
					{
						if($order_details_value["is_free_gift_products"]=="Yes")
						{
							$prod_res = FreeGiftProduct::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->first();
							if($prod_res && $prod_res->count() > 0) {
								if(file_exists(config('global.PRD_LARGE_IMG_PATH').$prod_res->product_image) && !empty($prod_res->product_image))
									$thumb_image = config('global.PRD_LARGE_IMG_URL').$prod_res->product_image;
								 else
									$thumb_image = config('global.NO_IMAGE_LARGE');
							} else {
								$thumb_image = config('global.NO_IMAGE_LARGE');
							}
						}
						else
						{
							$prod_res = Products::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->first();
							if($prod_res && $prod_res->count() > 0) {
								if(file_exists(config('global.PRD_LARGE_IMG_PATH').$prod_res->image) && !empty($prod_res->image)){
									$imgver = filemtime(config('global.PRD_LARGE_IMG_PATH').stripslashes($prod_res->image));
									$thumb_image = config('global.PRD_LARGE_IMG_URL').$prod_res->image."?ver=".$imgver;
								} else {
									$thumb_image = config('global.NO_IMAGE_LARGE');
								}
							} else {
								$thumb_image = config('global.NO_IMAGE_LARGE');
							}
						}
						$OrderDetailRs[$order_details_key]['Image'] = $thumb_image;
					}
				}

			}
			if($OrderRs->is_only_gc==1) {
				$GC_Only = 1;
			}
		}

		$is_skip_address = 'N';
		// if(isset($OrderRs->store_id) && $OrderRs->store_id > 0 && isset($OrderRs->is_skip_addresspart) && $OrderRs->is_skip_addresspart!=''){
		// 	$address_part = explode("###",$OrderRs->is_skip_addresspart);
		// 	if(isset($address_part[0]) && $address_part[0] == 'Yes' ){
		// 		$is_skip_address = 'Y';
		// 	}
		// }

		$this->PageData['is_skip_address'] = $is_skip_address;

		$this->PageData['GC_Only'] = $GC_Only;
		$this->PageData['OrderDetailRs'] = $OrderDetailRs;

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Order Detail';
		$this->PageData['JSFILES'] = ['printpage.js'];
		$this->PageData['CSSFILES'] = ['custom.css','common.css','header-footer.css','myaccount.css'];

		return view('myaccount.orderdetailprint')->with($this->PageData);
	}

	public function OrderDetail(Request $request)
	{
		$IsGiftCertificateItem = '';
		$GC_IMAGE_URL = '';

		$orders_id 			= (int)$request['id'];
		$orders_detail_id 	= $policeReportAttach	= $reportDoc = $reportPicturesVideos = $picturesVideosAttach = '';

		$is_returnable		= 'no';

		$OrderRs = Order::select('*', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i') AS datetime,DATEDIFF(now(), order_datetime) as days"))
							->where('customer_id', '=', Auth::user()->customer_id)
							->where('orders_id', '=', $orders_id)
							->first();
		$GC_Only = $quantity = 0;

		if($OrderRs == null || $OrderRs && $OrderRs->count() <= 0) {
			return redirect('/order-history.html');
		} else {

		$todayDateTime = $EstimatedDeliveryDate = $show_ship_status = '';

		$todayDateTime = Carbon::now();
		$EstimatedDeliveryDate = $OrderRs->EstimatedDeliveryDate;
		$EstimatedDeliveryDate = Carbon::parse($EstimatedDeliveryDate);

		if($OrderRs->ship_status == 'Shipped' && $todayDateTime->isAfter($EstimatedDeliveryDate)){
			$OrderRs->show_ship_status = 'Delivered';
		}
		else{
			$OrderRs->show_ship_status = $OrderRs->ship_status;
		}

			$claimPoliceReport = Claim::select('police_report_document','pictures_videos')
								->where('orders_id', '=', $orders_id)
								->first();

			if($claimPoliceReport && $claimPoliceReport->count() > 0){
				$reportDoc	= $claimPoliceReport->police_report_document;
				$reportPicturesVideos	= $claimPoliceReport->pictures_videos;
			}

			if(trim($reportDoc) != '' && file_exists(config('global.RETURN_PDF_PATH').'claims/'.$reportDoc)){
				$policeReportAttach	= config('global.RETURN_PDF_URL').'claims/'.$reportDoc;
			}
			$pic_arr = array();
			if(trim($reportPicturesVideos) != ''){
				$picturesvideosarr = explode(",",$reportPicturesVideos);
				for($p = 0; $p < count($picturesvideosarr); $p++){
					if(file_exists(config('global.RETURN_PDF_PATH').'claims/'.$picturesvideosarr[$p])){
						$picvideopath = config('global.RETURN_PDF_URL').'claims/'.$picturesvideosarr[$p];
						array_push($pic_arr,$picvideopath);
					}
				}
			}

			$this->PageData['OrderRs'] = $OrderRs;

			$OrderDetailRs = OrderDetail::select('*')
								->where('orders_id', '=', $OrderRs->orders_id)
								->orderBy('orders_detail_id', 'DESC')
								->get();
			if(count($OrderDetailRs) > 0) {
				$orders_detail_id = $OrderDetailRs[0]->orders_detail_id;

				foreach ($OrderDetailRs as $order_details_key => $order_details_value) {
					$return_res = ReturnOrders::select('return_shipping_label')
												->where('orders_id', '=', $order_details_value->orders_id)
												->where('orders_detail_id', '=', $order_details_value->orders_detail_id)
												->get();

					$tot_reqs = count($return_res);
					$returnShippingLabel = $returnLabel = '';

					$quantity = $quantity + $order_details_value->quantity;
					//echo $OrderRs->days;
					//if($order_details_value->is_free_gift_products !='Yes' && $OrderRs->days <= 60 && $OrderRs->ship_status =='Shipped' && in_array($OrderRs->status, ['Completed','Return Requested','Return Approved','Return Rejected','Item Received']) && in_array($order_details_value->ostatus,['Pending','Completed'])){
					if($order_details_value->is_free_gift_products !='Yes' && $OrderRs->days <= 30 && $OrderRs->ship_status =='Shipped' && in_array($OrderRs->status, ['Completed','Return Requested','Return Approved','Return Rejected','Item Received']) && in_array($order_details_value->ostatus,['Pending','Completed'])){
						$is_returnable	= 'yes';
						if(count($OrderDetailRs) == 1 && $order_details_value->excluded_flag=='Final Sale'){
							$is_returnable	= 'no';
						}
					}

					if($tot_reqs > 0){
						if(file_exists(config('global.RETURN_PDF_PATH').$return_res[0]->return_shipping_label) && $return_res[0]->return_shipping_label != ''){
							$returnShippingLabel = '<a class="btn btn-sm btn-primary mt-3" href="'.config('global.RETURN_PDF_URL').$return_res[0]->return_shipping_label.'" target="_blank">Shipping Label </a>';
						}

					}
					$returnLabel = "";
					$returnLabelFile = "Return-Authorization-Order-".$order_details_value['orders_no']."-".$order_details_value['orders_detail_id'].".pdf";

					if(file_exists(config('global.RETURN_PDF_PATH').$returnLabelFile)){
						$returnLabel = config('global.RETURN_PDF_URL').$returnLabelFile;
					}

					$OrderDetailRs[$order_details_key]['return_shipping_label'] = $returnShippingLabel;
					$OrderDetailRs[$order_details_key]['return_label'] = $returnLabel;

					$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$order_details_value,'No');

					$order_details_value["IsGiftCertificateItem"] = $IsGiftCertificateItem;

					/*if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL'));
					}
					else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU1'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL1'));
					}
					else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU2'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL2'));
					}*/

					if($IsGiftCertificateItem == 'Yes'){
						$GC_IMAGE_URL = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$order_details_value,'No');

						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], $GC_IMAGE_URL);
					}
					else
					{
						if($order_details_value["is_free_gift_products"]=="Yes")
						{
							$prod_res = FreeGiftProduct::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->first();
							if($prod_res && $prod_res->count() > 0) {
								if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->product_image) && !empty($prod_res->product_image))
									$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->product_image;
								 else
									$thumb_image = config('global.NO_IMAGE_THUMB');
							} else {
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
						}
						else
						{
							$prod_res = Products::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->first();
							if($prod_res && $prod_res->count() > 0) {
								if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->image) && !empty($prod_res->image)){
									$imgver = filemtime(config('global.PRD_THUMB_IMG_PATH').stripslashes($prod_res->image));
									$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->image."?ver=".$imgver;
								} else {
									$thumb_image = config('global.NO_IMAGE_THUMB');
								}
							} else {
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
						}
						$OrderDetailRs[$order_details_key]['Image'] = $thumb_image;
					}
				}

			}
			if($OrderRs->is_only_gc==1) {
				$GC_Only = 1;
			}
		}

		$this->PageData['GC_Only'] = $GC_Only;
		$this->PageData['quantity'] = $quantity;
		$this->PageData['orders_detail_id'] = $orders_detail_id;
		$this->PageData['policeReportAttach'] = $policeReportAttach;
		$this->PageData['picturesVideosAttach'] = $picturesVideosAttach;
		$this->PageData['pic_arr'] = $pic_arr;

		$this->PageData['is_returnable'] = $is_returnable;

		$this->PageData['OrderDetailRs'] = $OrderDetailRs;

		$view_fileType = "";
		if(Session::get('eusertype') == "Wholesaler" || (Session::get('eusertype')=="Wholesaler" && Session::get('is_dropshipper')=="Yes")) {
			$view_fileType  = 'pdf';
		} else {
			$view_fileType = 'print';
		}
		$is_skip_address = 'N';
		// if(isset($OrderRs->store_id) && $OrderRs->store_id > 0 && isset($OrderRs->is_skip_addresspart) && $OrderRs->is_skip_addresspart!=''){
		// 	$address_part = explode("###",$OrderRs->is_skip_addresspart);
		// 	if(isset($address_part[0]) && $address_part[0] == 'Yes' ){
		// 		$is_skip_address = 'Y';
		// 	}
		// }

		$this->PageData['view_fileType'] = $view_fileType;
		$this->PageData['is_skip_address'] = $is_skip_address;

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Order Detail';
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('myaccount.orderdetail')->with($this->PageData);
	}

	public function WishCategory(Request $request)
	{
		if($request['action'] == 'DeleteCat')
		{
			$checked = count($request['ch']);
			$ch = $request['ch'];
			if($checked > 0)
			{
				$result = WishlistCategory::whereIn('wishlist_category_id', $request['ch'])->delete();
				$result = Wishlist::whereIn('wishlist_category_id', $request['ch'])->delete();
				Session::flash('success', config('message.WishCategory.DeleteSuccess'));
			}
			else
			{
				Session::flash('error', config('message.WishCategory.CheckToDelete'));
			}
			return redirect('wish-category.html');
		}

		$WishCatRS = WishlistCategory::select('wishlist_category_id', 'name', 'description')
									->where('customer_id', '=', Auth::user()->customer_id)
									->paginate(10);

		$this->PageData['WishCatRS'] =  $WishCatRS;
		$this->PageData['CSSFILES'] = ['myaccount.css', 'pagination.css'];
		$this->PageData['JSFILES'] = ['wishcategory.js'];
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Wish List';
		return view('myaccount.wishcategory')->with($this->PageData);
	}

	public function WishCategoryEdit(Request $request)
	{
		$category_id = $request['category_id'];

		if($request['action'] == 'EditCat')
		{
	        $validatedData = $request->validate([
								'name' => 'required',
								'description'	=> 'required'
					        ], [
					            'name.required' => config('message.WishCategory.Name'),
					            'description.required' => config('message.WishCategory.Description')
					        ]);

			$name_exist = WishlistCategory::where('customer_id', '=', Auth::user()->customer_id)
										->where('wishlist_category_id', '!=', $category_id)
										->where('name', '=', trim($request['name']))
										->count();
			if($name_exist > 0) {
				Session::flash('error', config('message.WishListCategory.ExistCategory'));
				return redirect()->back();
			}

			$WishCatInsertAry =	array(
										'name'			=> $request['name'],
										'description'	=> $request['description']
									);

			$updateQry = WishlistCategory::where('wishlist_category_id', '=', $category_id)
										->where('customer_id', '=', Auth::user()->customer_id)
			              				->update($WishCatInsertAry);

			Session::flash('success', config('message.WishCategory.UpdateSuccess'));
			return redirect('wish-category.html');
		}

		$WishCat = WishlistCategory::select('wishlist_category_id', 'name', 'description')
									->where('customer_id', '=', Auth::user()->customer_id)
									->where('wishlist_category_id', '=', $category_id)
									->first();

		$this->PageData['WishCat'] =  $WishCat;

		$this->PageData['CSSFILES'] = ['myaccount.css'];
		$this->PageData['JSFILES'] = ['wishcategory.js'];
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Wish List';
		return view('myaccount.wishcategoryedit')->with($this->PageData);
	}

	public function WishProduct(Request $request)
	{
		$category_id = $request['category_id'];

		######### Set Wish category id ############
		if($request['category_id'] != '') {
			$wishlist_category_id = (int)$request['category_id'];
			Session::put('Wish_CategoryID', $wishlist_category_id);
		} else {
			$wishlist_category_id = (int)Session::get('Wish_CategoryID');
		}
		$this->PageData['wishlist_category_id'] =  $wishlist_category_id;

		######### Set Wish category id ############

		######### Delete Wish Product ############
		if($request['action'] == 'DeleteWishProd')
		{
			$checked = count($request['ch']);
			$ch = $request['ch'];
			if($checked > 0)
			{
				$result = Wishlist::whereIn('wishlist_id', $request['ch'])->where('customer_id', '=', Auth::user()->customer_id)->delete();
				Session::flash('success', config('message.WishProduct.DeleteSuccess'));
			}
			else
			{
				Session::flash('error', config('message.WishProduct.CheckToDelete'));
			}
			return redirect()->back();
		}

		######### Delete Wish Product ############

		######## Get Wish Category Start ##########
		$WishCatRS = WishlistCategory::select('name')
									->where('customer_id', '=', Auth::user()->customer_id)
									->where('wishlist_category_id', '=', $wishlist_category_id)
									->first();
		$this->PageData['WishCatRS'] =  $WishCatRS;
		######## Get Wish Category Start ##########

		######## Get Wish Product Start ##########
		$wish_res_prod = Wishlist::select('wishlist_id', 'products_id', 'sku', 'description')
								->where('wishlist_category_id', '=', $wishlist_category_id)
								->where('customer_id', '=', Auth::user()->customer_id)
								->orderBy('wishlist_id', 'ASC')
								->get();

		$arr_products = array();
		if(count($wish_res_prod) > 0) {
			$casewhenprice  = $this->GetSystemProductPrice("`pu_products`");
			foreach ($wish_res_prod as $wish_res_prod_key => $wish_res_prod_value) {
				$sku = $wish_res_prod_value['sku'];
				$products_id = $wish_res_prod_value['products_id'];
				$arr_product = Products::select('pu_products.sku', 'pu_products.products_id', 'pu_products.product_name', 'pu_products.short_description', 'pu_products.image', 'pu_products_category.category_id', 'pu_manufacture.vmanufacture', 'pu_products.minimum_stock', 'pu_products.current_stock', 'pu_products.our_price','pu_products.sale_price','pu_products.retail_price','pu_products.wholesale_price','pu_products.cosmo_current_stock', 'pu_products.cosmo_sku','pu_products.cosmo_our_price','pu_products.cosmo_price','pu_products.cosmo_wholesale_price','pu_products.cosmo_retail_price', 'pu_products.pca_current_stock', 'pu_products.pca_sku', 'pu_products.pca_retail_price','pu_products.pca_price','pu_products.pca_our_price','pu_products.pca_wholesale_price','pu_products.nandansons_current_stock', 'pu_products.nandansons_sku','pu_products.nandansons_retail_price','pu_products.nandansons_price','pu_products.nandansons_our_price','pu_products.nandansons_wholesale_price',
				'pu_products.perfumeworldwide_currentstock', 'pu_products.perfumeworldwide_sku','pu_products.perfumeworldwide_retail_price','pu_products.perfumeworldwide_price','pu_products.perfumeworldwide_our_price','pu_products.perfumeworldwide_wholesale_price',
				'pu_products.nd_sku','pu_products.nd_current_stock','pu_products.nd_wholesale_price','pu_products.nd_our_price','pu_products.nd_retail_price','pu_products.nd_price',
				'pu_products.nd_current_stock','pu_products.nd_sku','pu_products.perfumeworldwide_sku','pu_products.perfumeworldwide_currentstock','pu_products.perfumeworldwide_wholesale_price','pu_products.perfumeworldwide_our_price','pu_products.perfumeworldwide_retail_price','pu_products.perfumeworldwide_price')
										->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
										->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
										->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
										->where('pu_products.products_id', '=', $products_id)
										->where('pu_products.status', '=', '1')
										->where('pu_category.status', '=', '1')
										->groupBy('pu_products.products_id')
										->first();
				if($arr_product && $arr_product->count() > 0) {
					$file_path = config('global.PRD_LARGE_IMG_PATH').stripslashes($arr_product->image);
					if(File::exists($file_path) && $arr_product->image != '') {
						// File::delete($file_path);
						$thumb_image = config('global.PRD_LARGE_IMG_URL').rawurlencode(stripslashes($arr_product->image));
					} else {
						$thumb_image = config('global.NO_IMAGE_THUMB');
					}
					$arr_product->thumb_image = $thumb_image;

		 			$arr_product->product_url = $this->getProductRewriteURL($arr_product->products_id, $arr_product->product_name, $arr_product->category_id, $arr_product->vmanufacture);

					$products_sku 	  = $arr_product->sku;
					$product_name 	  = remove_html_entities($arr_product->product_name);
					$short_description  = remove_html_entities($arr_product->short_description);

					$our_price 		  = $arr_product->product_price;
					$description	= remove_html_entities($wish_res_prod_value['description']);

					$arr_products[] = array(
											"wishlist_id"	=> $wish_res_prod_value['wishlist_id'],
											"sku"			=> $products_sku,
											"product_name"	=> strip_tags($product_name),
											"short_description"	=> strip_tags($short_description),
											"description"	=> strip_tags($description),
											'sale_price'	=> $our_price,
											'thumb_image'	=> $arr_product->thumb_image,
											'p_link'		=> $arr_product->product_url
										);

				}

			}
		}

		$this->PageData['WishProdRS'] =  $arr_products;
		######## Get Wish Product End ##########

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Wish Products';
		$this->PageData['CSSFILES'] = ['myaccount.css', 'listing.css'];
		$this->PageData['JSFILES'] = ['wishproduct.js'];
		return view('myaccount.wishproduct')->with($this->PageData);
	}

	public function OrderItemReturn(Request $request)
	{
		$IsGiftCertificateItem = '';
		$GC_IMAGE_URL = '';

		if($request['action'] == 'return') {
			$ord_data = Order::select('customer_id')
								->where('orders_id', '=', $request['orderno'])
								->first();
			if(empty($ord_data) || $ord_data->customer_id != Auth::user()->customer_id) {
				return redirect()->back()
				->withInput()
				->withErrors([
					'something_went_wrong' => config('message.General.SomethingWentWrong')
				]);
			}

			$selected_arr = $request["chk"];
			$reason_return = "";
			$order_detail_id_return = "";
			$quantity_return = "";
			$return_details = "";
			$order_detail_ids = "";
			$order_detail_ids_array = [];
			if(!empty($selected_arr)) {
				foreach($selected_arr as $key_selected => $orders_detail_id) {
					$no = $orders_detail_id;
					if($request['customerReturnReason'.$no]!='Other') {
						$request['otherreason'.$no] = "";
					}
					if($request['otherreason'.$no]!='' && $request['customerReturnReason'.$no]=="Other") {
						$request['customerReturnReason'.$no] = $request['otherreason'.$no];
					}
					$quantity = $request['quantity'.$no];

					$reason_return .= trim($request['customerReturnReason'.$no])."##";
					$order_detail_id_return .= $no."##";
					$quantity_return .= $quantity."##";

					$return_details .= "<tr>
											<td>".$request['sku'.$no]."</td>
											<td>".trim($request['customerReturnReason'.$no])."</td>
										</tr>";
					$order_detail_ids .= "'".$no."',";
					$order_detail_ids_array[] = $no;
				}
				/*$insertArr = array(
										"customer_id"  		=> Auth::user()->customer_id,
										"orders_id"	   		=> $request["orderno"],
										"reason"	   		=> rtrim($reason_return,"##"),
										"is_rma_scan"  		=> "No",
										"status"	   		=> 'Return Requested',
										"orders_detail_id"	=> rtrim($order_detail_id_return,"##"),
										"quantity"			=> rtrim($quantity_return,"##")
								  );
				$returnOrderData = ReturnOrders::create($insertArr);*/
				$returnOrderData = new ReturnOrders;
				$returnOrderData->customer_id = Auth::user()->customer_id;
				$returnOrderData->orders_id = $request["orderno"];
				$returnOrderData->reason = rtrim($reason_return,"##");
				$returnOrderData->is_rma_scan = 'No';
				$returnOrderData->status = 'Return Requested';
				$returnOrderData->orders_detail_id = rtrim($order_detail_id_return,"##");
				$returnOrderData->quantity = rtrim($quantity_return,"##");
				$returnOrderData->save();

				$updateQry = Order::where('orders_id', (int)$request["orderno"])
				              		->update(['status' => 'Return Requested']);
          		//dd($updateQry);
				// if($updateQry)
				// {
					$return_info = "&nbsp;(<a href='".config('global.SITE_ADMIN_URL')."index.php?f=viewreturnorder&return_id=".$returnOrderData->return_id."' target='_blank'>Return Info</a>)";

					$order_detail_ids = substr($order_detail_ids,0,-1);
					$updateQry = OrderDetail::whereIn('orders_detail_id', $order_detail_ids_array)
				              			->update(['ostatus' => 'Return Requested']);
				// }

				Session::flash('success', config('message.Order.ReturnRequestSubmit'));

				$orderRes = Order::where('orders_id', (int)$request["orderno"])
				              		->select('orders_no', 'bill_email')
				              		->first();
          		if($orderRes  && $orderRes->count() > 0) {
					$mail = GetMailTemplate("ORDER_RETURN_NOTIFICATION");
					$EmailBody = str_replace('{$return_details}',$return_details,$mail[0]->mail_body);
					$EmailBody = str_replace('{$return_info}',$return_info,$EmailBody);
					$EmailBody = str_replace('{$order_no}',$orderRes->order_no,$EmailBody);
					$EmailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$EmailBody);
					$EmailBody = str_replace('{$Site_URL}',config('global.Site_URL'),$EmailBody);
					$EmailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$EmailBody);
					$freeshippinginfo = '';
					if(config('Settings.FREESHIPPING_VALUE')!="")
					{
						$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
					}
					$EmailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$EmailBody);
					$Subject = $mail[0]->subject;
					$To = config('Settings.ADMIN_MAIL');
					$From = config('Settings.CONTACT_MAIL');
					SendMail($Subject,$EmailBody,$To,$From);
          		}
				return redirect()->back();
			} else {
				return redirect()->back()
				->withInput()
				->withErrors([
					'order_not_returned' => config('message.Order.OrderNotReturned')
				]);
			}
		}
		/*$order_status_array = ['Pending','Completed','Return Requested','Return Approved','Return Rejected','Item Received'];
		$prodCntRes = Order::select('pu_orders.orders_id', 'pu_order_detail.orders_detail_id', 'pu_order_detail.product_name', 'pu_order_detail.sku', 'pu_order_detail.price', 'pu_order_detail.ostatus','pu_orders.status', DB::raw('DATE_FORMAT(pu_orders.order_datetime, "%m/%d/%Y %H:%i") as datetime,DATEDIFF(now(), pu_orders.order_datetime) as days'))
							->join('pu_order_detail', 'pu_order_detail.orders_id', '=', 'pu_orders.orders_id')
							->where('pu_orders.customer_id', '=', Auth::user()->customer_id)
							->whereIn('pu_orders.status', '=', $order_status_array)
							->where('pu_orders.ship_status', '=', 'Shipped')
							->whereRaw("(pu_order_detail.is_free_gift_products='No' || pu_order_detail.is_free_gift_products='')")
							->having('days', '<=', 30)
							->orderBy('pu_orders.order_datetime', 'DESC')
							->orderBy('pu_orders.orders_id', 'DESC')
							->get();
		$total_count = count($prodCntRes);*/

		$order_status_array = ['Completed','Return Requested','Return Approved','Return Rejected','Item Received'];
		$OrdResult = Order::select('pu_orders.orders_id', 'pu_orders.orders_no', 'pu_orders.payment_type', 'pu_orders.use_credit_limit', 'pu_orders.payment_method', 'pu_orders.sub_total', 'pu_orders.order_total', 'pu_orders.status', DB::raw('DATE_FORMAT(pu_orders.order_datetime, "%m/%d/%Y") as datetime,DATEDIFF(now(), pu_orders.order_datetime) as days'))
							->where('pu_orders.customer_id', '=', Auth::user()->customer_id)
							->whereIn('pu_orders.status', $order_status_array)
							->where('pu_orders.ship_status', '=', 'Shipped')
							->having('days', '<=', 60)
							->orderBy('pu_orders.order_datetime', 'DESC')
							->orderBy('pu_orders.orders_id', 'DESC')
							->paginate(50);
		// dd($OrdResult);
		$Data_Return = [];
		if(count($OrdResult) > 0) {
			foreach ($OrdResult as $order_result_key => $order_result_value) {
				$order_detail_status_array = ['Pending','Completed','Return Requested','Return Approved','Return Rejected','Item Received'];
				$OrdDetailResult = OrderDetail::select('pu_order_detail.orders_detail_id', 'pu_order_detail.product_name', 'pu_order_detail.sku', 'pu_order_detail.price', 'pu_order_detail.ostatus', 'pu_order_detail.quantity', 'pu_order_detail.total')
									->where('pu_order_detail.orders_id', $order_result_value['orders_id'])
									->whereIn('pu_order_detail.ostatus', $order_detail_status_array)
									->whereRaw("(pu_order_detail.is_free_gift_products='No' || pu_order_detail.is_free_gift_products='')")
									->orderBy('pu_order_detail.orders_detail_id', 'DESC')
									->get();

				if(count($OrdDetailResult) > 0) {
					foreach ($OrdDetailResult as $order_details_key => $order_details_value) {

						$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$order_details_value,'No');

						/*if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL'));
						}
						else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU1'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL1'));
						}
						else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU2'))
						{
							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL2'));
						}*/

						if($IsGiftCertificateItem == 'Yes'){
							$GC_IMAGE_URL = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$order_details_value,'No');

							$this->GetGiftCertificateData($OrdDetailResult, $order_details_key, $order_details_value['orders_detail_id'], $GC_IMAGE_URL);
						}
						else
						{
							$prod_res = Products::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->select('image')->first();
							if($prod_res  && $prod_res->count() > 0) {
								if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->image) && !empty($prod_res->image))
									$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->image;
								 else
									$thumb_image = config('global.NO_IMAGE_THUMB');
							} else {
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
							$OrdDetailResult[$order_details_key]['Image'] = $thumb_image;
						}

						$return_res = ReturnOrders::select('reason', 'quantity', 'orders_detail_id', 'return_shipping_label')
													->where('orders_id', '=', $order_result_value['orders_id'])
													->get();
						$tot_reqs = count($return_res);
						if($tot_reqs > 0)
						{
							for($d=0; $d < $tot_reqs; $d++){
								$reason_arr = explode("##",$return_res[$d]["reason"]);
								$qty_arr = explode("##",$return_res[$d]["quantity"]);
								$order_detail_arr = explode("##",$return_res[$d]["orders_detail_id"]);
								foreach($order_detail_arr as $key_ids => $detail_ids){
									$Return_data[$detail_ids]['reason'] = $reason_arr[$key_ids];
									$Return_data[$detail_ids]['qty'] = $qty_arr[$key_ids];

									$Return_data[$detail_ids]['base_orderdetailid'] = $order_detail_arr[0];
									$Return_data[$detail_ids]['returnlabel'] = $return_res[$d]["return_shipping_label"];
								}
							}
						}
						$OrdDetailResult[$order_details_key]["Reason"]  = '';
						$OrdDetailResult[$order_details_key]["Qty"]  = 0;

						$OrdDetailResult[$order_details_key]["return_shipping_label"]  = '';

						if(!empty($Return_data[$order_details_value["orders_detail_id"]])){
							$OrdDetailResult[$order_details_key]["Qty"] = $Return_data[$order_details_value["orders_detail_id"]]['qty'];
							$OrdDetailResult[$order_details_key]["Reason"] = $Return_data[$order_details_value["orders_detail_id"]]['reason'];

							$OrdDetailResult[$order_details_key]["COrderNo"] = "Return-Authorization-Order-".$order_result_value["orders_no"]."-".$Return_data[$order_details_value["orders_detail_id"]]['base_orderdetailid'].".pdf";

							$ShippingLabel = '';

							$returnlabel = $Return_data[$order_details_value["orders_detail_id"]]['returnlabel'];
							if($returnlabel != "" && file_exists(config('global.RETURN_PDF_PATH').$returnlabel))
							{
								$ShippingLabel = '<a href="'.url('/returnord/'.$Return_data[$order_details_value["orders_detail_id"]]['returnlabel']).'" target="_blank" class="button btn-1 btn-small" style="padding-left:8px;">Print Shipping Label</a>';
								$OrdDetailResult[$order_details_key]["return_shipping_label"] =$ShippingLabel;
							}
							else
							{
								$OrdDetailResult[$order_details_key]["return_shipping_label"]  = '';
							}

						}

						if($order_result_value["ostatus"]!="Pending" && $order_result_value["ostatus"]!="Completed")
						{
							$OrdDetailResult[$order_details_key]["TotalPriceVal"] = $OrdDetailResult[$order_details_key]["Qty"] * $order_result_value["price"];
							$OrdDetailResult[$order_details_key]["TotalPriceVal"] = number_format($OrdDetailResult[$order_details_key]["TotalPriceVal"],2,'.','');

						}

						if($tot_reqs == 1 && !empty($Return_data[$order_result_value["orders_detail_id"]])){
							$Data_Return[$order_result_key]['COrderNo'] = $OrdDetailResult[$order_details_key]["COrderNo"];
							$Data_Return[$order_result_key]['return_shipping_label'] = $OrdDetailResult[$order_details_key]["return_shipping_label"];
						}

					}
				}

				$OrdResult[$order_result_key]['order_items'] = $OrdDetailResult;
			}
		}
		// $this->PageData['OrdResult'] =  $Data_order;
		$this->PageData['OrdResult'] =  $OrdResult;
		$this->PageData['Data_Return'] =  $Data_Return;
		$this->PageData['JSFILES'] = ['orderitemreturn.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css', 'orderitemreturn.css', 'pagination.css'];
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Order Return';
		return view('myaccount.orderitemreturn')->with($this->PageData);
	}

	public function WholeSaleRegister(Request $request)
	{
		$this->PageData['Countries'] = GetCountries();
		$this->PageData['States'] = GetStates();
		$this->PageData['SelCountry'] = 'US';

		$uploadArr = array();

		if(isset($request['action']) && $request['action'] == 'signup')
		{
			$this->PageData['SelCountry'] = $request['country'];

	        $validatedData = $request->validate([
								'company_name' => 'required',
								'first_name' => 'required',
								'last_name'	=> 'required',
								'address1' => 'required',
								'city' => 'required',
								'country' => 'required',
								'zip' => 'required',
								'phone' => 'required',
					            'email' => 'required|email',
					            'password' => 'required|same:confirm_password|min:6',
					            'confirm_password' => 'required|min:6',
								'hear_about' => 'required',
					            'youtube' => 'required_if:hear_about,Youtube',
					            'other' => 'required_if:hear_about,Other',
					            'state' => 'required_if:country,US',
					            'other_state' => 'required_unless:country,US',
					            'einnumber' => 'required_if:state,NY',
					            'salestax_id' => 'required_if:state,NY',
								'g-recaptcha-response' => 'required'
					        ], [
					            'company_name.required' => config('message.Register.CompanyName'),
					            'first_name.required' => config('message.Validate.FirstName'),
					            'last_name.required' => config('message.Validate.LastName'),
					            'address1.required' => config('message.Validate.Address'),
					            'city.required' => config('message.Validate.City'),
					            'country.required' => config('message.Validate.Country'),
					            'zip.required' => config('message.Validate.ZipCode'),
					            'phone.required' => config('message.Validate.Phone'),
					            'email.required' => config('message.Validate.ValidEmail'),
					            'email.email' => config('message.Validate.ValidEmail'),
					            'password.required' => config('message.Validate.WholeSalerPassword'),
					            'password.same' => config('message.Validate.WholeSalerPassword'),
					            'password.min' => config('message.Validate.MinLengthPassword'),
					            'confirm_password.required' => config('message.Validate.WholeSalerValidConfirmPassword'),
					            'confirm_password.min' => config('message.Validate.MinLengthConfirmPassword'),
					            'hear_about.required' => config('message.Register.HearAbout'),
					            'youtube.required_if' => config('message.Register.Youtube'),
					            'other.required_if' => config('message.Register.OtherText'),
					            'state.required_if' => config('message.Validate.State'),
					            'other_state.required_unless' => config('message.Validate.OtherState'),
					            'einnumber.required_if' => config('message.Register.EINnumber'),
					            'salestax_id.required_if' => config('message.Register.SalesTaxID'),
								'g-recaptcha-response.required' => config('message.Validate.Captcha')
					        ]);

			$ChkEmail = Customer::where('email','=',$request['email'])->where('registration_type','=','M')->get();
			if($ChkEmail && $ChkEmail->count() > 0)
			{
				if($ChkEmail[0]->block_customer_flag == "Yes"){
					checkBlockedUser($ChkEmail[0]->email,$ChkEmail[0]->customer_id,'Register','Y');
					return redirect()->back()
						->withInput()
						->withErrors([
							'existing_email' => config('message.Register.Blocked'),
						]);
				}
				return redirect()->back()
				->withInput()
				->withErrors([
					'existing_email' => config('message.Register.ExistingEmail'),
				]);
			}
			$ChkIP = Customer::where('customer_ip','=',$_SERVER['REMOTE_ADDR'])->where('registration_type','=','M')->get();
			if($ChkIP && $ChkIP->count() >= 5)
			{
				return redirect()->back()
				->withInput()
				->withErrors([
					'duplicate_ip' => config('message.Register.DuplicateIP'),
				]);
			}
			$State = $request['state'];
			if($request['country'] != 'US')
				$State = $request['other_state'];
			$UserData = array(
				'company_name' => $request['company_name'],
				'first_name' => $request['first_name'],
				'last_name' => $request['last_name'],
				'address1' => $request['address1'],
				'address2' => ($request['address2'] != '') ? $request['address2'] : '',
				'phone' => $request['phone'],
				'email' => $request['email'],
				'password' => $request['password'],
				'city' => $request['city'],
				'state' => $State,
				'country' => $request['country'],
				'zip' => $request['zip'],
				'status' => '1',
				'eusertype' => 'Wholesaler Pending', // Whole sale or normal customer
				'registration_type' => 'M', // member or guest customer
				'customer_ip' => $_SERVER['REMOTE_ADDR'],
				'customer_browser' => $_SERVER['HTTP_USER_AGENT'],
				'hear_about' => $request['hear_about'],
				'is_dropshipper' => $request['is_dropshipper'],
				'iRewardpoint' => '0',
				'upd_datetime' => date('Y-m-d H:i:s'),
				'merge_log' => 'Auto updated from guest to member on registration page',

			);

			$UserData['hear_text']  = ($request["hear_about"] == 'Other') ? $request["other"] : '';
			$UserData['einnumber']  = ($request["state"] == 'NY') ? $request["einnumber"] : '';
			$UserData['youtube_text']  = ($request["hear_about"] == 'Youtube') ? $request["youtube"] : '';

			$UserData['company_website']  = ($request["company_website"] != '') ? $request["company_website"] : '';
			$UserData['stores']  = ($request["stores"] != '') ? $request["stores"] : '';
			$UserData['sell_online']  = $request["sell_online"];
			$UserData['website']  = $request["website"];
			$UserData['webiste_url']  = ($request["webiste_url"] != '') ? $request["webiste_url"] : '';
			$UserData['policy']  = ($request["policy"] != '') ? $request["policy"] : '';
			$UserData['knowledge']  = ($request["knowledge"] != '') ? $request["knowledge"] : '';
			$UserData['salestax_id']  = ($request["country"] == 'US' && $request["state"] == 'NY') ? $request["salestax_id"] : '';

			$User = Customer::create($UserData);
			if($User)
			{
				$iCustomerId = $User->customer_id;
				$AddressBook=array();
				$AddressBook['customer_id'] = $iCustomerId;
				$AddressBook['title'] = 'Self Address';
				$AddressBook['email'] = $User->email;
				$AddressBook['first_name'] = $User->first_name;
				$AddressBook['last_name'] = $User->last_name;
				$AddressBook['address1'] = $User->address1;
				$AddressBook['address2'] = $User->address2;
				$AddressBook['company_name'] = $User->company_name;
				$AddressBook['city'] = $User->city;
				$AddressBook['state'] = $User->state;
				$AddressBook['country'] = $User->country;
				$AddressBook['zip'] = $User->zip;
				$AddressBook['phone'] = $User->phone;
				$AddressBook['status'] = '1';
				$UserAddress = AddressBook::create($AddressBook);

				if($request->hasFile('upload_sales_tax')){
					// $path_large = config('global.HOME_IMAGE_PATH');
			        $image = $request->file('upload_sales_tax');
			        $image_name = $image->getClientOriginalName();
			        $destinationPath = base_path('images/homeimg/');
			        $upload = $image->move($destinationPath, $image_name);

					$uploadArr = array(
						'upload_sales_tax'				=> $image_name,
						'resale_certificate_status'	=> 'Pending',
					);

					$result = Customer::where('customer_id', $iCustomerId)->update($uploadArr);
				}
				// $request->session()->regenerate();
				Session::put('sess_useremail',$User->email);
				Session::put('sess_username',$User->first_name);
				Session::put('sess_icustomerid',$User->customer_id);
				Session::put('eusertype',$User->eusertype);
				Session::put('is_dropshipper',$User->is_dropshipper);
				Session::put('etype','M');
				Session::put('eusertype','Retailer');
				Session::put('resale_certificate',$User->upload_sales_tax);
				Session::put('resale_certificate_status',$User->resale_certificate_status);

				$custFname = '';
				if(isset($User->first_name) &&  $User->first_name!='' )
				{
					$custFname = $User->first_name;
				}
				$custlname = '';
				if(isset($User->last_name) && $User->last_name!='')
				{
					$custlname = $User->last_name;
				}
				Session::put('sess_custname',$custFname."|".$custlname);
				$CityVal = '';
				if(isset($User->city) &&  $User->city!='')
				{
					$CityVal = $User->city;
				}
				$StateVal = '';
				if(isset($User->state) &&  $User->state!='')
				{
					$StateVal = $User->state;
				}
				$CountryVal = '';
				if(isset($User->country) &&  $User->country!='')
				{
					$CountryVal = $User->country;
				}
				$ZipVal = '';
				if(isset($User->zip) &&  $User->zip!='')
				{
					$ZipVal = $User->zip;
				}
				$PhoneVal = '';
				if(isset($User->phone) &&  $User->phone!='')
				{
					$PhoneVal = $User->phone;
				}
				Session::put('sess_useraddress',$CityVal."|".$StateVal."|".$CountryVal."|".$ZipVal."|".$PhoneVal);

				Session::flash('success', config('message.Register.Success'));

				$mail = GetMailTemplate("WHOLESALER");
				$EmailBody = str_replace('{$customer_id}',$iCustomerId,$mail[0]->mail_body);
				$EmailBody = str_replace('{$vemail}',$request->email,$EmailBody);
				$EmailBody = str_replace('{$password}',$request->password,$EmailBody);
				$EmailBody = str_replace('{$vFirstName}',$request->first_name,$EmailBody);
				$EmailBody = str_replace('{$vLastName}',$request->last_name,$EmailBody);
				$EmailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$EmailBody);
				$EmailBody = str_replace('{$TOLL_FREE_NO}',config('Settings.CONTACT_PHONE_NO'),$EmailBody);
				$EmailBody = str_replace('{$Site_URL}',config('global.Site_URL'),$EmailBody);

				$freeshippinginfo = '';
				if(config('Settings.FREESHIPPING_VALUE')!="")
				{
					//$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
				}
				$EmailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$EmailBody);

				if(config('global.OMNISEND_PROG') == true){
					$EventData = ['email' => $request['email'], //'qualdev.devs@gmail.com', //
						'fields' => [
							'first_name' => $request->first_name,
							'last_name' => $request->last_name,
							'password' => $request->password,
							'SITE_NAME' => config('Settings.SITE_TITLE'),
							'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
							'Site_URL' => config('global.SITE_URL')
						]
					];
					OmanisendRequest('61e6a448c01934001be85479',$EventData);

					/*$EventData = ['email' => 'qualdev.devs@gmail.com', //
						'fields' => [
							'first_name' => $request->first_name,
							'last_name' => $request->last_name,
							'password' => $request->password,
							'SITE_NAME' => config('Settings.SITE_TITLE'),
							'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
							'Site_URL' => config('global.SITE_URL')
						]
					];
					OmanisendRequest('61e6a448c01934001be85479',$EventData);*/
				} else {
					/* First Mail */
					$To1 = $request->email;
					$Subject = $mail[0]->subject;
					$From1 = config('Settings.ADMIN_MAIL');
					//SendMail($Subject,$EmailBody,$To1,$From1);

					/* Second Mail */
					$To2 = config('Settings.ADMIN_MAIL');
					$From2 = config('Settings.CONTACT_MAIL');
					//SendMail($Subject,$EmailBody,$To2,$From2);
				}

				/* First Mail */
				$To1 = $request->email;
				$Subject = $mail[0]->subject;
				$From1 = config('Settings.ADMIN_MAIL');
				//SendMail($Subject,$EmailBody,$To1,$From1);

				/*$EventData = ['email' => 'qualdev.devs@gmail.com',
					'fields' => [
						'first_name' => $request->first_name,
						'last_name' => $request->last_name,
						'password' => $request->password,
						'SITE_NAME' => config('Settings.SITE_TITLE'),
						'CONTACT_MAIL' => config('Settings.CONTACT_MAIL'),
						'Site_URL' => config('global.SITE_URL')
					]
				]; */

				//OmanisendRequest('61e6a448c01934001be85479',$EventData);

				/* Second Mail */
				$To2 = config('Settings.ADMIN_MAIL');
				$From2 = config('Settings.CONTACT_MAIL');
				//SendMail($Subject,$EmailBody,$To2,$From2);

				if($request['is_dropshipper']=="Yes")
				{
					$dropshipmail = GetMailTemplate("DROPSHIPPER_CUSTOMER");
					$dropShipMailBody = str_replace('{$customer_id}',$iCustomerId,$dropshipmail[0]->mail_body);
					$dropShipMailBody = str_replace('{$vemail}',$request->email,$dropShipMailBody);
					$dropShipMailBody = str_replace('{$password}',$request->password,$dropShipMailBody);
					$dropShipMailBody = str_replace('{$vFirstName}',$request->first_name,$dropShipMailBody);
					$dropShipMailBody = str_replace('{$vLastName}',$request->last_name,$dropShipMailBody);
					$dropShipMailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$dropShipMailBody);
					$dropShipMailBody = str_replace('{$TOLL_FREE_NO}',config('Settings.CONTACT_PHONE_NO'),$dropShipMailBody);
					$dropShipMailBody = str_replace('{$Site_URL}',config('global.Site_URL'),$dropShipMailBody);

					$freeshippinginfo = '';
					if(config('Settings.FREESHIPPING_VALUE')!="")
					{
						$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
					}
					$dropShipMailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$dropShipMailBody);

					$Subject = (isset($dropshopmail[0]->subject) && $dropshopmail[0]->subject!='') ? $dropshopmail[0]->subject : '';

					$To3 = config('Settings.ADMIN_MAIL');
					$From3 = config('Settings.CONTACT_MAIL');
					//SendMail($Subject,$dropShipMailBody,$To3,$From3);
				}
				Session::put('GARegister',"Yes");
				return redirect()->back();
			} else {
				Session::flash('failed', config('message.Register.Failed'));
				return redirect()->back();
			}
		}
		$GTMDATA = ['page' => 'wholesaleregister', 'pagetype' => 'other'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Registration';
		$this->PageData['JSFILES'] = ['register.js', 'wholesaleregister.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css','register.css'];

		$GARegister1 = "";

		if(Session::has('GARegister') && Session::get('GARegister')!='')
		{
		$GARegister1= googleAnalyticsGA4("RegisterPage");
		Session::forget("GARegister");
		}
		$this->PageData['GARegister1'] = $GARegister1;

		return view('customer.wholesaleregister')->with($this->PageData);
	}

	public function CreditSummary(Request $request)
	{
		$result1 = Order::select(DB::raw("CONCAT('Used in Order# ',`orders_no`) as orders_no, apply_credit, remaining_credit, DATE_FORMAT(order_datetime,'%Y-%m-%d %h:%i:%s') AS date_time, '' as oldnew, '' as oldnew1, '--' as note"), 'cust_current_credit_limit as cust_current_credit_limit')
							->where('customer_id', '=', Auth::user()->customer_id)
							->where('apply_credit', '>', 0);

		$credit_customer = AdminCreditLog::select(DB::raw("CONCAT('Added by Maxaroma') as orders_no, '' as apply_credit, '' as remaining_credit, DATE_FORMAT(date,'%Y-%m-%d %h:%i:%s') AS date_time, old_amount as oldnew, new_amount as oldnew1, note"), 'credited_amount as cust_current_credit_limit' )
							->where('customer_id', '=', Auth::user()->customer_id)
							->orderBy('date_time', 'DESC')
							->union($result1)
							->paginate(10);

		$this->PageData['credit_customer'] = $credit_customer;

		$credit_limit = Customer::select('credit_limit')->where('customer_id', '=', Auth::user()->customer_id)->first();
		if($credit_limit['credit_limit'] == null) {
			$credit_limit['credit_limit'] = 0;
		}
		$this->PageData['credit_limit'] = $credit_limit['credit_limit'];

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: New Added Credit Limit Report';
		$this->PageData['CSSFILES'] = ['myaccount.css', 'pagination.css'];
		return view('myaccount.creditsummary')->with($this->PageData);
	}

	public function DropshipperFundSummary(Request $request)
	{
		if(Session::get('eusertype') != 'Wholesaler' || Session::get('is_dropshipper') != 'Yes' || config('Settings.DROPSHIPPER_SYSTEM_FLAG') != 'Yes')
			return redirect('/login.html');

		$result1 = Order::select(DB::raw("CONCAT('Used in Order# ',pu_orders.orders_id) as orders_no,DATE_FORMAT(pu_orders.order_datetime,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$-',pu_orders.order_total) as added_fund,'order' as oldnew,'--' as note"))
							->join('pu_customer', 'pu_customer.customer_id', '=', 'pu_orders.customer_id')
							->where('pu_orders.customer_id', '=', Auth::user()->customer_id)
							->where('pu_orders.payment_method', '=', 'Dropshipper Fund')
							->where('pu_customer.eusertype', '=', 'Wholesaler')
							->where('pu_customer.registration_type', '=', 'M');

		$result2 = AdminFundLog::select(DB::raw("'Added by Maxaroma' as orders_no, DATE_FORMAT(pu_admin_fund_log.date,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$',pu_admin_fund_log.funded_amount) as added_fund, CONCAT('$',pu_admin_fund_log.old_fund_value,' | $',pu_admin_fund_log.new_fund_value) as oldnew,pu_admin_fund_log.note"))
							->join('pu_admin', 'pu_admin.admin_id', '=', 'pu_admin_fund_log.admin_id')
							->where('pu_admin_fund_log.customer_id', '=', Auth::user()->customer_id);

		$result3 = PaypalIpnLog::select(DB::raw("'Added by You' as orders_no, DATE_FORMAT(pu_paypal_ipn_log.order_date,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$',pu_paypal_ipn_log.cust_requested_fund) as added_fund, '--' as oldnew,'--' as note"))
							->join('pu_customer', 'pu_customer.customer_id', '=', 'pu_paypal_ipn_log.customer_id')
							->where('pu_paypal_ipn_log.customer_id', '=', Auth::user()->customer_id);

		$result4 = AuthorizeFundLog::select(DB::raw("'Added by You' as orders_no, DATE_FORMAT(pu_authorize_fund_logs.order_date,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$',pu_authorize_fund_logs.fund_amount) as added_fund,'--' as oldnew,'--' as note"))
							->join('pu_customer', 'pu_customer.customer_id', '=', 'pu_authorize_fund_logs.customer_id')
							->where('pu_authorize_fund_logs.customer_id', '=', Auth::user()->customer_id)
							->where('pu_authorize_fund_logs.status', '!=', 'Sent To Stripe');

		$DropShipper_Customer = AmazonFundLog::select(DB::raw("'Added by You' as orders_no, DATE_FORMAT(pu_amazon_fund_logs.order_date,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$',pu_amazon_fund_logs.fund_amount) as added_fund,'--' as oldnew,'--' as note"))
							->join('pu_customer', 'pu_customer.customer_id', '=', 'pu_amazon_fund_logs.customer_id')
							->where('pu_amazon_fund_logs.customer_id', '=', Auth::user()->customer_id)
							->where('pu_amazon_fund_logs.status', '!=', 'Declined')
							->orderBy('date_time', 'DESC')
							->union($result1)
							->union($result2)
							->union($result3)
							->union($result4)
							->paginate(10);
		// dd($DropShipper_Customer);
		$total_records = count($DropShipper_Customer);
		if($total_records > 0) {
			for($i=0;$i<$total_records;$i++) {
				if($DropShipper_Customer[$i]['added_fund'] != '') {
					$oldamt = $newamt = $totamt = 0;

					if(isset($DropShipper_Customer[$i]['oldnew']) &&$DropShipper_Customer[$i]['oldnew']!='--' && $DropShipper_Customer[$i]['oldnew']!='order') {
						if($i==0)
						{
							$a = explode("|", $DropShipper_Customer[$i]['oldnew']);
						}
						else
						{
							$a = explode("|", $DropShipper_Customer[$i-1]['oldnew']);
						}
						$newamt = str_replace(" ","",$a[1]);
						$newamt = str_replace("$","",$a[1]);
						$newamt = abs($newamt);
					}
					if($newamt=='') {
						$newamt = 0;
						$newamt = abs($newamt);
					}
					$fund = str_replace("$","",$DropShipper_Customer[$i]['added_fund']);
					$fund = abs($fund);
					if($DropShipper_Customer[$i]['oldnew']=='--') {
						$totamt = abs($newamt + $fund);
						$valueToAssign = "$".str_replace(" ","",$newamt)." | $".$totamt;
						$valueToAssign1 = "$".$totamt;
						$DropShipper_Customer[$i]['oldnew'] = $valueToAssign;
						$DropShipper_Customer[$i]['oldnew1'] = $valueToAssign1;
					} elseif($DropShipper_Customer[$i]['oldnew']=='order') {
						$totamt = abs($newamt - $fund);
								if($totamt==$fund) {
									$valueToAssign = "$0 | $0";
									$valueToAssign1 = "$0";
								} else {
									$valueToAssign = "$".str_replace(" ","",$newamt)." | $".$totamt;
									$valueToAssign1 = "$".$totamt;
								}
						//$valueToAssign = "$".str_replace(" ","",$newamt)." | $".$totamt;
						$DropShipper_Customer[$i]['oldnew'] = $valueToAssign;
						$DropShipper_Customer[$i]['oldnew1'] = $valueToAssign1;
					} else {
						$a1 = explode("|", $DropShipper_Customer[$i]['oldnew']);
						$newamt11 = $a1[1];
						$valueToAssign1 = $newamt11;

						$DropShipper_Customer[$i]['oldnew1'] = $valueToAssign1;
					}
				}
			}
		}
		$this->PageData['DropShipper_Customer'] = $DropShipper_Customer;

		$fund = Customer::select('pu_customer.dropshipperfund_history', 'pu_customer.available_funds')
							// ->join('pu_orders', 'pu_orders.customer_id', '=', 'pu_customer.customer_id')
							->where('pu_customer.is_dropshipper', '=', 'Yes')
							->where('pu_customer.customer_id', '=', Auth::user()->customer_id)
							->where('pu_customer.eusertype', '=', 'Wholesaler')
							->where('pu_customer.registration_type', '=', 'M')
							->first();
		if(!is_null($fund)) {
			$history = preg_replace('#Updated By.*?On#si', 'updated by Maxaroma on', $fund->dropshipperfund_history);
		} else {
			$history = '';
		}
		$this->SetAmazonConfig('fund');
		$this->PageData['available_funds'] = (!is_null($fund)) ? $fund->available_funds : '';
		$this->PageData['history'] = $history;
		$this->PageData['eusertype'] = strtolower(Session::get('eusertype'));
		/*$this->PageData['isExport'] = $request->isExport;
		$export_file_name = "fund_history.csv";
		$export_file_url = config('global.DOWNLOAD_CSV_URL').$export_file_name."?ver".time();
		$this->PageData['export_file_url'] = $export_file_url;
		$this->PageData['export_file_path'] = $request->isExport;*/
		// dd($DropShipper_Customer);
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Dropshipper Fund Report';
		$this->PageData['CSSFILES'] = ['myaccount.css', 'pagination.css'];
		return view('myaccount.dropshipperfundsummary')->with($this->PageData);
	}

	public function HelpVideos(Request $request){
		if(Session::get('eusertype') != 'Wholesaler' || Session::get('is_dropshipper') != 'Yes' || config('Settings.DROPSHIPPER_SYSTEM_FLAG') != 'Yes')
			return redirect('/login.html');

		if(trim(generalsetting("DROPSHIPPER_VIDEO1")) == '' && trim(generalsetting("DROPSHIPPER_VIDEO2"))  == '' && trim(generalsetting("DROPSHIPPER_VIDEO3")) == '' && trim(generalsetting("DROPSHIPPER_VIDEO4")) == ''){
			return redirect('/myaccount.html');
		}
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Help Videos';
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('myaccount.helpvideos')->with($this->PageData);
	}

	public function ProductFtp(Request $request)
	{
		if(Session::get('eusertype') != 'Wholesaler' || Session::get('is_dropshipper') != 'Yes' || config('Settings.DROPSHIPPER_SYSTEM_FLAG') != 'Yes')
			return redirect('/login.html');

		$aCustomer = Customer::select('send_email_feed', 'is_newarrival_email', 'is_pricelist_email', 'ftp_flag', 'ftp_type', 'ftp_host', 'ftp_username', 'ftp_password', 'ftp_port', 'ftp_file_path', 'ftp_sendfeed')
					->where('customer_id', '=', Auth::user()->customer_id)
					->where('registration_type', '=', 'M')
					->where('eusertype', '=', Session::get('eusertype'))
					->where('is_dropshipper', '=', Session::get('is_dropshipper'))
					->where('status', '=', '1')
					->first();

		$this->PageData['aCustomer'] = $aCustomer;
		$this->PageData['eusertype'] = Session::get('eusertype');
		if($request['action'] == 'update') {
			if(trim($request['ftp_flag']) == 'Yes'){
				$validatedData = $request->validate([
					'ftp_host' => 'required',
					'ftp_username'	=> 'required',
					'ftp_password' => 'required',
					'ftp_port' => 'required',
					'ftp_file_path' => 'required'
				], [
					'ftp_host.required' => config('message.FTP.Host'),
					'ftp_username.required' => config('message.FTP.Username'),
					'ftp_password.required' => config('message.FTP.Password'),
					'ftp_port.required' => config('message.FTP.Port'),
					'ftp_file_path.required' => config('message.FTP.Path')
				]);
			}

			$custDataAry = array(
				'send_email_feed'  		=> $request['send_email_feed'],
				'is_newarrival_email'    		=> $request['is_newarrival_email'],
				'is_pricelist_email'     	=> $request['is_pricelist_email'],
				'ftp_flag'     	=> $request['ftp_flag'],
				'ftp_type' 			=> $request['ftp_type'],
				'ftp_host' 	=> $request['ftp_host'],
				'ftp_username' 	=> $request['ftp_username'],
				'ftp_password'=> $request['ftp_password'],
				'ftp_port' 		   	=> $request['ftp_port'],
				'ftp_file_path' 	=> $request['ftp_file_path'],
				'ftp_sendfeed' 	=> $request['ftp_sendfeed'],
				'is_quantity' => 'noqty'
				);

			$ProductFTP = Customer::find(Auth::user()->customer_id);
			$ProductFTP->update($custDataAry);
			if($ProductFTP) {
				Session::flash('success', config('message.FTP.Success'));
			} else {
				Session::flash('error', config('message.FTP.Failed'));
			}
				return redirect()->back();
		}

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Product FTP Details';
		$this->PageData['JSFILES'] = ['productftp.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('myaccount.productftp')->with($this->PageData);
	}

	public function EditFtp(Request $request)
	{
		if(Session::get('eusertype') != 'Wholesaler' || Session::get('is_dropshipper') != 'Yes' || config('Settings.DROPSHIPPER_SYSTEM_FLAG') != 'Yes')
			return redirect('/login.html');

		$aCustomer = Customer::select('dropshipper_ftp_type', 'dropshipper_ftp_host', 'dropshipper_ftp_username', 'dropshipper_ftp_password', 'dropshipper_ftp_port', 'dropshipper_ftp_file_path', 'dropshipper_ftp_file_name', 'dropshipper_ftp_picktime_order', 'dropshipper_ftp_flag', 'dropshipper_ftp_timestamp')
									->where('customer_id', '=', Auth::user()->customer_id)
									->where('registration_type', '=', 'M')
									->where('eusertype', '=', Session::get('eusertype'))
									->where('is_dropshipper', '=', Session::get('is_dropshipper'))
									->where('status', '=', '1')
									->first();

		$this->PageData['aCustomer'] = $aCustomer;
		$this->PageData['eusertype'] = Session::get('eusertype');

		if($request['action'] == 'update') {

	        $validatedData = $request->validate([
								'dropshipper_ftp_host' => 'required',
								'dropshipper_ftp_username'	=> 'required',
								'dropshipper_ftp_password' => 'required',
								'dropshipper_ftp_port' => 'required',
								'dropshipper_ftp_file_path' => 'required',
								'dropshipper_ftp_file_name' => 'required'
					        ], [
					            'dropshipper_ftp_host.required' => config('message.FTP.Host'),
					            'dropshipper_ftp_username.required' => config('message.FTP.Username'),
					            'dropshipper_ftp_password.required' => config('message.FTP.Password'),
					            'dropshipper_ftp_port.required' => config('message.FTP.Port'),
					            'dropshipper_ftp_file_path.required' => config('message.FTP.Path'),
					            'dropshipper_ftp_file_name.required' => config('message.FTP.NamePrefix')
					        ]);

			$custDataAry = array(
								'dropshipper_ftp_type'  		=> $request['dropshipper_ftp_type'],
								'dropshipper_ftp_host'    		=> $request['dropshipper_ftp_host'],
								'dropshipper_ftp_username'     	=> $request['dropshipper_ftp_username'],
								'dropshipper_ftp_password'     	=> $request['dropshipper_ftp_password'],
								'dropshipper_ftp_port' 			=> $request['dropshipper_ftp_port'],
								'dropshipper_ftp_file_path' 	=> $request['dropshipper_ftp_file_path'],
								'dropshipper_ftp_file_name' 	=> $request['dropshipper_ftp_file_name'],
								'dropshipper_ftp_picktime_order'=> $request['dropshipper_ftp_picktime_order'],
								'dropshipper_ftp_flag' 		   	=> $request['dropshipper_ftp_flag'],
								'dropshipper_ftp_timestamp' 	=> date('Y-m-d H:i:s')
								);

			$EditFTP = Customer::find(Auth::user()->customer_id);
			$EditFTP->update($custDataAry);
			if($EditFTP) {
				Session::flash('success', config('message.FTP.Success'));
			} else {
				Session::flash('error', config('message.FTP.Failed'));
			}
				return redirect()->back();
		}

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: FTP Details';
		$this->PageData['JSFILES'] = ['editftp.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('myaccount.editftp')->with($this->PageData);
	}

	public function testFunction(Request $request)
	{
		if(is_null($request->args)) {
			dd('You are in testFunction. There are no args available in the current URL.');
		} else {
		   	$args = explode('/', $request->args);
			dd($args);
		}
	}

	public function ImportOrder(Request $request)
	{
		$OutOfStockMsg = '';
		$HalfOutOfStockMsg = '';
		if(Session::get('eusertype')=='Wholesaler' && Session::get('is_dropshipper')=='Yes' && config('Settings.DROPSHIPPER_SYSTEM_FLAG')=='Yes') {
			if(Session::has('aOutOfStockItems') && count(Session::get('aOutOfStockItems')) > 0) {
				$skuval = implode("','", Session::get('aOutOfStockItems'));
				$OutOfStockMsg = "Following products sku(s) are out of stock<br/>".$skuval;
				Session::forget('aOutOfStockItems');
				Session::forget('aOutOfStockItemsfull');
			}
			if(Session::has('HalfaOutOfStockItems') && count(Session::get('HalfaOutOfStockItems')) > 0) {
				$skuval = implode("','", Session::get('HalfaOutOfStockItems'));
				$HalfOutOfStockMsg = "Following products sku(s) have some product stock<br/>".$skuval;
				Session::forget('HalfaOutOfStockItems');
			}

		}
		$this->PageData['OutOfStockMsg'] = $OutOfStockMsg;
		$this->PageData['HalfOutOfStockMsg'] = $HalfOutOfStockMsg;
		$this->PageData['under_maintanance'] = '0';
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Import Order';
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		$this->PageData['JSFILES'] = ['additional-methods.min.js', 'importorder.js'];
		return view('myaccount.importorder')->with($this->PageData);
	}

	public function ImportedOrderList(Request $request)
	{
		if(Session::has('fund_from')) {
		    Session::forget('fund_from');
		}
		if(Session::has('fund_flag')) {
		    Session::forget('fund_flag');
		}
		$this->PageData['PaymentCheck'] = 'production';

		$OutOfStockMsg = '';
		$OrderProcessMsg = '';
		if(Session::get('eusertype')=='Wholesaler' && Session::get('is_dropshipper')=='Yes' && config('Settings.DROPSHIPPER_SYSTEM_FLAG')=='Yes') {
			## Pay With Amazon ##
			$this->PageData['AMAZON_ORDER_REFERENCE_ID'] = Session::get('AMAZON_ORDER_REFERENCE_ID');
			$this->PageData['is_dropshipper'] = Session::get('is_dropshipper');

			$OrdResult = DropshipperOrder::select('orders_id', 'orders_no', 'customer_id', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y' ) as order_date,DATE_FORMAT(order_datetime, '%H:%i:%s' ) as order_time"), 'sub_total', 'shipping_amt', 'tax' , 'order_total', 'status', 'bill_first_name', 'bill_last_name', 'ship_phone', 'is_outofstock', 'list_outofstock', 'shippingModeId')
										->where('customer_id', '=', Auth::user()->customer_id)
										->where('is_order_deleted', '=', 'No')
										->orderBy('order_datetime', 'DESC')
										->get();
			$this->PageData['OrdResult'] = $OrdResult;

			if(Session::has('SKULISTARR') && count(Session::get('SKULISTARR')) > 0) {
				$skuval = implode("','", Session::get('SKULISTARR'));
				$OutOfStockMsg = "Following products sku(s) are out of stock or inactive <br/>".$skuval;
				Session::forget('SKULISTARR');
			}
			if(Session::has('OrderNotProcess') && count(Session::get('OrderNotProcess')) > 0) {
				$skuval = implode("','", Session::get('OrderNotProcess'));
				$OrderProcessMsg = "Following order(s) are not processed,shipping method is not selected  <br/>".$skuval;
				Session::forget('OrderNotProcess');
			}

			## Paypal Display Setting
			$paypalEC = PaymentMethod::select('pm_status')
										->where('pm_group_name', '=', 'PAYMENT_PAYPALEC')
										->first();
			if(!empty($paypalEC) && $paypalEC->pm_status == 'Active') {
				$IsPaypalExpressCheckout ='Yes';
			}

			if(Session::has('order_amount')) {
				Session::forget('order_amount');
			}
			if(Session::has('tempShippingAdd1Val')) {
				Session::forget('tempShippingAdd1Val');
			}
			if(Session::has('tempBillingAdd1Val')) {
				Session::forget('tempBillingAdd1Val');
			}
			if(Session::has('UpdateOrderNoArr')) {
				Session::forget('UpdateOrderNoArr');
			}
			if(Session::has('UpdateOrderNumber')) {
				Session::forget('UpdateOrderNumber');
			}
		}
		$this->PageData['under_maintanance'] = '0';
		$this->PageData['IsPaypalExpressCheckout'] = $this->GetCartAttribute('IsPaypalExpressCheckout');
		$this->PageData['Amazon_pay_Checkout'] = $this->GetCartAttribute('Amazon_pay_Checkout');
		$this->PageData['OutOfStockMsg'] = $OutOfStockMsg;
		$this->PageData['OrderProcessMsg'] = $OrderProcessMsg;
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Imported Dropship Order List';
		$this->PageData['JSFILES'] = ['importedorderlist.js'];
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		return view('myaccount.importedorderlist')->with($this->PageData);
	}

	public function DeleteImportedOrderList(Request $request)
	{
		if($request->ajax()){
			if(!Auth::user()) {
		        $response['status'] = 401;
		        $response['message'] = "Unauthorized request";
		        return response()->json($response, 400);
			} else {
				if(isset($request->orders_id) && ($request->orders_id) != '') {
					$orders_id = $request["orders_id"];
					$updateAdmin = array(
											'is_order_deleted' => "Yes"
										);

					$updateDropshipperOrder = DropshipperOrder::find($orders_id);
					$updateDropshipperOrder->update($updateAdmin);
			        $response['status'] = 200;
			        $response['message'] = "Success";
			        return response()->json($response, 200);
				} else {
			        $response['status'] = 400;
			        $response['message'] = "Bad parameters";
			        return response()->json($response, 400);
				}
			}
		} else {
	        $response['status'] = 404;
	        $response['message'] = "Not found";
	        return response()->json($response, 400);
		}
	}

	public function SetDropshiperOrder(Request $request)
	{
		$chkfield 				= trim($request["chkfield"]);
		$disabledfield 			= trim($request["disabledfield"]);
		$ordersnew_id 			= trim($request["ordersnew_id"]);
		$order_action 			= trim($request["order_action"]);
		$dataselected 			= trim($request["dataselected"]);
		$chk		  			= $request["chk"];
		$order_amount			= trim($request["order_amount"]);
		$totalorders			= trim($request["totalorders"]);

		if($chkfield!='')
		{
			$chkfieldArr = explode(",",$chkfield);
		}

		if($disabledfield!='')
		{
			$disabledfieldArr = explode(",",$disabledfield);
			if(count($disabledfieldArr) > 0)
			{
				for($j=1;$j<=count($disabledfieldArr)-1;$j++)
				{
					$request['disabledfield'.$j] = $disabledfieldArr[($j-1)];
				}
			}
		}
		if($ordersnew_id!='')
		{
			$ordersnew_idArr = explode(",",$ordersnew_id);
			if(count($ordersnew_idArr) > 0)
			{
				for($j=1;$j<=count($ordersnew_idArr)-1;$j++)
				{
					$request['ordersnew_id'.$j] = $ordersnew_idArr[($j-1)];
					for($i=1;$i<=count($chkfieldArr)-1;$i++)
					{
						if($chkfieldArr[($i-1)]==$ordersnew_idArr[($j-1)])
						{
							$request['ch'.$j] = $chkfieldArr[($i-1)];
						}
					}
				}
			}
		}

		if( $order_amount <= 0 )
		{
			$err_msg = 'Order amount is not selecetd';
			Session::flash('DropshipOrderError',$err_msg);
			return redirect()->back();
		}

		$customer_id = (int)Session::get('sess_icustomerid');
		if((!Session::has('sess_icustomerid') || empty($customer_id)) &&  Session::get('is_dropshipper')=="Yes")
		{
			$err_msg = "Error while processing your order, Please try again.";
			Session::flash('DropshipOrderError',$err_msg);
			return redirect()->back();
		}

		$Customer_Res = Customer::where('customer_id','=',$customer_id)->get();
		if(trim($Customer_Res[0]['first_name'])== '' || trim($Customer_Res[0]['last_name'])== '' || trim($Customer_Res[0]['address1'])== '' || trim($Customer_Res[0]['city'])== '' ||  trim($Customer_Res[0]['zip'])== '' || trim($Customer_Res[0]['state'])== '' || trim($Customer_Res[0]['country'])== '' || trim($Customer_Res[0]['phone'])== '' || trim($Customer_Res[0]['email'])== '')
		{

			$err_msg = "Please fill the required fields for billing information in my account profile. ";
			Session::flash('DropshipOrderError',$err_msg);
			return redirect()->back();
		}

		$UpdateOrderNoArr = [];
		$ShippingMethoArr = [];
		$SKULISTARR = [];
		$OrderNoArr = [];
		$OrderNotProcess = [];
		//$totalorders = count($chk);

		for($i=1;$i<=$totalorders;$i++)
		{
			$TotalOrder = 0;
			/*$dropshipper_order_res = DropshipperOrder::where('orders_id','=',$chk[$i])
											->where('is_order_deleted','=','No')->get();
			*/
			if($dataselected=='No' && $request["disabledfield".$i]=='No')
			{
				$dropshipper_order_res = DropshipperOrder::where('orders_id','=',$request["ordersnew_id".$i])
											->where('is_order_deleted','=','No')->get();
				$TotalOrder = $dropshipper_order_res->count();
			} else {
			   if($request["disabledfield".$i]=='No' && $request['ch'.$i] > 0)
			   {
				   $dropshipper_order_res = DropshipperOrder::where('orders_id','=',$request["ordersnew_id".$i])
											->where('is_order_deleted','=','No')->get();
				   $TotalOrder = $dropshipper_order_res->count();
			   } else {
				   continue;
			   }
			}

			if($TotalOrder > 0){
				$dropshipperOrderDetails = DropshipperOrderDetail::where('orders_id','=',$dropshipper_order_res[0]['orders_id'])->get();
				$totalOrdersDetails = $dropshipperOrderDetails->count();

				for($j=0;$j<$totalOrdersDetails;$j++)
				{
					$Prods = DB::table('pu_products as po')
						->join('pu_products_category as pc','po.products_id','=','pc.products_id')
						->join('pu_category as c','pc.category_id','=','c.category_id')
						->join('pu_brand as b','b.brand_id','=','po.brand_id')
						->join('pu_manufacture as m',function($join){
							$join->on('po.imanufactureid','=','m.imanufactureid');
							$join->on('b.imanufactureid','=','m.imanufactureid');
						})
						->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
									'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
									'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
									'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
									'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','m.is_popular','pc.category_id','c.parent_id')
						->where('po.status','=','1')
						->where('c.status','=','1')
						->where('po.current_stock','>','0')
						->where('po.sku','=',$dropshipperOrderDetails[$j]['sku'])->groupBy('po.products_id')->get();
					$CntProducts = 0;
					if($Prods && $Prods->count() > 0)
					{
						foreach($Prods as $Prod)
						{
							$Prod = $this->SetProduct($Prod);
							if($Prod->product_price > 0)
							{
								$CntProducts++;
							}
						}
						if($CntProducts < 0)
						{
							$SKULISTARR[] = $dropshipperOrderDetails[$j]['sku'];
							$OrderNoArr[] = $dropshipper_order_res[0]["orders_no"];
							$order_amount = $order_amount - $dropshipper_order_res[0]["order_total"];
							$order_amount = number_format($order_amount,2,'.','');
						}
					}
				}
			}

			if(!in_array($dropshipper_order_res[0]["orders_no"],$OrderNoArr))
			{

				$ShippingMethodRS = ShippingMode::where('shipping_mode_id','=',$dropshipper_order_res[0]["shippingModeId"])->where('status','=','1')->get();
				if($ShippingMethodRS && $ShippingMethodRS->count() <=0)
				{
					$OrderNotProcess[] = $dropshipper_order_res[0]["orders_no"];
					$order_amount = $order_amount - $dropshipper_order_res[0]["order_total"];
					$order_amount = number_format($order_amount,2,'.','');
					continue;
				}

				if(isset($request->order_action) && $request->order_action =='Fund')
				{
					$fullShippingname = '';
					$shippingDays = $dropshipper_order_res[0]["shipping_days"];
					$estimateShipDate = $this->getShipmentEstimateDate($shippingDays);
					if($dropshipper_order_res[0]["shipping_amt"] > 0)
					{
						$fullShippingname =  $ShippingMethodRS[0]['type']. " <b>(".Session::get('currency_symbol').$dropshipper_order_res[0]["shipping_amt"].")</b> ".$estimateShipDate;
					}
					else
					{
						$fullShippingname =  $ShippingMethodRS[0]['type']. " <b>(Free)</b> ".$estimateShipDate;
					}
					$currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');
					$checkout_type = '';
					if(Session::has('etype') && Session::get('etype') == 'M')
					{
						$checkout_type = 'M';
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
							'payment_type' 				=> "PAYMENT_DS",
							'payment_method' 			=> "Dropshipper Fund",
							'pay_status' 				=> 'Paid',
							'ccinfo' 					=> '',
							'customer_comment' 			=> $dropshipper_order_res[0]['customer_comment'],
							'status'					=> 'Pending',
							'currency_info'				=> $currency_info,
							'checkout_type' 			=> $checkout_type,
							'user_type' 				=> Session::get('eusertype'),
							'ilevelid' 					=> '0',
							'level_price' 				=> $dropshipper_order_res[0]['level_price'],
							'ship_first_name' 			=> $dropshipper_order_res[0]['ship_first_name'],
							'ship_last_name' 			=> $dropshipper_order_res[0]['ship_last_name'],
							'ship_company' 				=> $dropshipper_order_res[0]['ship_company'],
							'ship_email' 				=> $dropshipper_order_res[0]['ship_email'],
							'ship_address1' 			=> $dropshipper_order_res[0]['ship_address1'],
							'ship_address2' 			=> ($dropshipper_order_res[0]['ship_address2']!='')?$dropshipper_order_res[0]['ship_address2'] : "",
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
							'bill_address2' 			=> ($dropshipper_order_res[0]['bill_address2']!='')?$dropshipper_order_res[0]['bill_address2'] : "",
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
							'fullshipping_info'			=> 	$fullShippingname

						);

					$NewOrder= Order::create($OrderInsert) ;
					//$NewOrder =1;
					if($NewOrder)
					{
						$OrderID = $NewOrder->orders_id;
						$UpdateOrderNoArr[] = $OrderID;
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
				} else {
					$UpdateOrderNoArr[] = $dropshipper_order_res[0]['orders_id'];
					$UpdateOrderNumber[] = $dropshipper_order_res[0]['orders_no'];
				}
			}else{
			   continue;
			}
		}

		if(isset($request->order_action) && $request->order_action =='Fund')
		{
			if(count($UpdateOrderNoArr) > 0)
			{
				if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') == 'Wholesaler')
				{
					$ds_res = Customer::where("customer_id","=", Session::get('sess_icustomerid'))->get();

					$DropshipperAccountDetails = $this->GetDropshipperAccountDetails($order_amount);
					if($ds_res[0]['available_funds']>0 && $DropshipperAccountDetails['fund_available'] == 'Yes')
					{
						$remaining_fund = $DropshipperAccountDetails['remaining_fund'];
						$upgCustomer = array (
							'available_funds' => $remaining_fund
						);
						$udpDS = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);
					}
				}
				return redirect('/order-history.html');
				exit;
			}
			else
			{
				return redirect('/imported-order-list.html');
				exit;
			}
		} else {
			if(count($SKULISTARR)>0)
			{
				Session::put('SKULISTARR',$SKULISTARR);
			}
			if(count($OrderNotProcess)>0)
			{
				Session::put('OrderNotProcess',$OrderNotProcess);
			}

			if(count($UpdateOrderNoArr) > 0)
			{
				Session::put("UpdateOrderNoArr",$UpdateOrderNoArr);
				Session::put("UpdateOrderNumber",$UpdateOrderNumber);
			}
			Session::put('DropShipperOrderAmount',$order_amount);
			Session::put('DropShipperOrder','Yes');
			return redirect('/paypal/placeorder/dropship');
		}
	}

	public function GetDropshipperAccountDetails($OrderTotal)
	{
		$DropshipperAccountDetails = array();
		$ds_res = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
		if($ds_res && $ds_res->count() > 0)
		{
			$available_funds = $ds_res[0]['available_funds'];
			if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') == 'Wholesaler')
			{
				$NetTotal = $OrderTotal;
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

	public function ImportedOrderDetail(Request $request)
	{
		if(Session::get('sess_useremail') == 'wgequaldev@gmail.com')
		{
			config(['app.debug' => true]);
		}
		if($request->has('pageAction') && $request->pageAction == 'Update Shipping Address') {
	        $validatedData = $request->validate([
								'ship_first_name' => 'required',
								'ship_last_name'	=> 'required',
								'ship_address1' => 'required',
								'ship_city' => 'required',
								'ship_zip' => 'required',
								'ship_phone' => 'required',
								'ship_country' => 'required',
					            'ship_state' => 'required_if:ship_country,US',
					            'ship_other_state' => 'required_unless:ship_country,US'
					        ], [
					            'ship_first_name.required' => config('message.Validate.FirstName'),
					            'ship_last_name.required' => config('message.Validate.LastName'),
					            'ship_address1.required' => config('message.Validate.Address'),
					            'ship_city.required' => config('message.Validate.City'),
					            'ship_zip.required' => config('message.Validate.ZipCode'),
					            'ship_phone.required' => config('message.Validate.Phone'),
					            'ship_country.required' => config('message.Validate.Country'),
					            'ship_state.required_if' => config('message.Validate.State'),
					            'ship_other_state.required_unless' => config('message.Validate.OtherState')
					        ]);
			$state = $request['ship_state'];
			if($request['ship_country'] != 'US') {
				$state = $request['ship_other_state'];
			}
			$UserDataArray = array(
				'ship_first_name' => $request['ship_first_name'],
				'ship_last_name' => $request['ship_last_name'],
				'ship_company' => ($request['ship_company'] != '') ? $request['ship_company'] : '',
				'ship_address1' => $request['ship_address1'],
				'ship_address2' => ($request['ship_address2'] != '') ? $request['ship_address2'] : '',
				'ship_email' => ($request['ship_email'] != '') ? $request['ship_email'] : '',
				'ship_phone' => $request['ship_phone'],
				'ship_city' => $request['ship_city'],
				'ship_state' => $state,
				'ship_country' => $request['ship_country'],
				'ship_zip' => $request['ship_zip'],
				'upd_datetime' => date('Y-m-d H:i:s')
			);

			$updateShippingAddress = DropshipperOrder::findOrFail($request['orders_id']);
			$updateShippingAddress->update($UserDataArray);

			if($updateShippingAddress)
			{
				Session::flash('success', 'Shipping information updated successfully.');
				return redirect()->back();
			}
		}

		if($request->has('pageAction') && $request->pageAction == 'UpdateSKUOrder')
		{
			$totalItemsQty = 0;
			$skuarr = array();
			$QuantityChange = "No";
			$Subtotal	= 0;
			$TotalQuantity	= 0;
			$TotalUnitPrice = 0;
			$orderTotal = 0;

			if(Session::has('SKULISTARR') && count(Session::get('SKULISTARR')) > 0) {
				Session::forget('SKULISTARR');
			}

			$pageAction = trim($request['pageAction']);
			$orders_id = (int) $request['orders_id'];
			$total_items = (int) $request['total_items'];

			############ Update order detail start hare#################
			for ($i = 1; $i <= $total_items; $i++) {
				$orders_detail_id = (int) $request["orders_detail_id" . $i];
				$vtmpsku =  $request["vtmpsku" . $i];
				$quantity = $request["quantity".$i];
				$OriginalQuantity = 0;

				$prdRes = Products::select('pu_products.products_id', 'pu_products.sku', 'pu_products.is_atomizer', 'pu_products.short_description', 'pu_products.vtype', 'pu_products.product_type', 'pu_products.product_name', 'pu_products.gender', 'pu_manufacture.vmanufacture', 'pu_products.minimum_stock', 'pu_products.current_stock', 'pu_products.wholesale_price as product_price', 'pu_products.image', 'pu_products_category.category_id', 'pu_products.brand_id', 'pu_products.imanufactureid')
							->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
							->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
							->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
							->where('pu_products.status', '=', '1')
							->where('pu_category.status', '=', '1')
							->where('pu_products.sku', '=', $vtmpsku)
							->whereIn('pu_products.product_type', ['wholesaler','both'])
							->having('pu_products.wholesale_price', '>', 0)
							->groupBy('pu_products.products_id')
							->get();
				if($prdRes != null && $prdRes->count() <= 0) {
					 $skuarr[] = $vtmpsku.",";
					 continue;
				}
				$prdRes = $prdRes[0];
				if($prdRes && $prdRes->current_stock <= 0)
				{
					$skuarr[] = $vtmpsku.",";
					continue;
				}

				if($prdRes && $quantity > $prdRes->current_stock)
				{
					$quantity = $prdRes->current_stock;
				}
				$per = 0;
				$val = 0;
				if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
				{
					$specialpricedtl = $this->getSpecialPricePercentandValue($quantity);
					$perval = explode("#",$specialpricedtl);
					if($perval[0] != '') {
						$per = $perval[0];
					}
					if($perval[1] != '') {
						$val = $perval[1];
					}
				}

				$prdRes->product_price = number_format((float) $prdRes->product_price,2,'.','');
				$prdRes->sale_price = $prdRes->product_price;
				// dd($prdRes);
				########################### Code For Change Price of Weekly Deal Product Start ###########################//

				$dealofdayRS = Dealofweek::select('pu_dealofweek.product_sku', 'pu_dealofweek.deal_price', 'pu_dealofweek.description', 'pu_dealofweek.discount_coupon_flag')
										->join('pu_dealofweektitle', 'pu_dealofweektitle.did', '=', 'pu_dealofweek.did')
										->join('pu_products', 'pu_products.sku', '=', 'pu_dealofweek.product_sku')
										->where('pu_products.status', '=', '1')
										->where('pu_dealofweek.status', '=', '1')
										->where('pu_dealofweek.start_date', '<=', date('Y-m-d H:i'))
										->where('pu_dealofweek.end_date', '>=', date('Y-m-d H:i'))
										->where('pu_dealofweek.deal_type', '=', 'Weekly')
										->orderBy('pu_dealofweek.dealofweek_id', 'DESC')
										->first();
	            $prdRes->IsDealProducts = 'No';
				$prdRes->DealDiscountFlag = 'No';
				if($dealofdayRS && $dealofdayRS->count() > 0) {
					if(trim($dealofdayRS->product_sku)==trim($prdRes->sku)) {
						if($dealofdayRS->deal_price!='' && $dealofdayRS->deal_price < $prdRes->sale_price) {
							$dealprice = number_format($dealofdayRS->deal_price,2,'.','');
							$prdRes->product_price = $dealprice;
							if($dealofdayRS->description!='') {
								$prdRes->short_description =  $dealofdayRS->description;
							}
						}
					 $prdRes->DealDiscountFlag = $dealofdayRS->discount_coupon_flag;
					 $prdRes->IsDealProducts = 'Yes';
					}
				}

				########################### Code For Change Price of Weekly Deal Product End ###########################//

				########################### Code For Change Price of Daily Deal Product Start Here###########################//
				$date = date('Y-m-d');
				$dealofdayRS = Dealofweek::select('pu_dealofweek.product_sku', 'pu_dealofweek.deal_price', 'pu_dealofweek.description', 'pu_dealofweek.discount_coupon_flag')
										->join('pu_dealofweektitle', 'pu_dealofweektitle.did', '=', 'pu_dealofweek.did')
										->join('pu_products', 'pu_products.sku', '=', 'pu_dealofweek.product_sku')
										->where('pu_products.status', '=', '1')
										->where('pu_dealofweek.status', '=', '1')
										->where('pu_dealofweek.start_date', '<=', date('Y-m-d H:i'))
										->where('pu_dealofweek.end_date', '>=', date('Y-m-d H:i'))
										->where('pu_dealofweek.deal_type', '=', 'Daily')
										->orderBy('pu_dealofweek.dealofweek_id', 'DESC')
										->first();
				if($dealofdayRS && $dealofdayRS->count() > 0) {
					if(trim($dealofdayRS->product_sku)==trim($prdRes->sku)) {
						if($dealofdayRS->deal_price!='' && $dealofdayRS->deal_price < $prdRes->sale_price) {
							$dealprice = number_format($dealofdayRS->deal_price,2,'.','');
							$prdRes->product_price = $dealprice;
							if($dealofdayRS->description!='') {
								$prdRes->short_description =  $dealofdayRS->description;
							}
						}
					 $prdRes->DealDiscountFlag = $dealofdayRS->discount_coupon_flag;
					 $prdRes->IsDealProducts = 'Yes';
					}
				}

				########################### Code For Change Price of Daily Deal Product End Here###########################//
				$SpecialPriceDetails = '';
				$ItemPrice = $prdRes->product_price;

				if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
				{
					$prdRes->product_price = $prdRes->product_price - $prdRes->product_price * $per/100;
				}
				if(File::exists(config('global.PRD_THUMB_IMG_PATH').$prdRes->image) && !empty($prdRes->image)) {
					$vthumb_image = config('global.PRD_THUMB_IMG_URL').$prdRes->image;
				} else {
					$vthumb_image = config('global.NO_IMAGE_THUMB');
				}

				$TotalQuantity = $TotalQuantity + $quantity;
				$unit_price =  $prdRes->product_price;

				$TotalUnitPrice = $quantity * $unit_price;

				$TotalUnitPrice = number_format($TotalUnitPrice,2,'.','');

				$Subtotal = $Subtotal + $TotalUnitPrice;

				$UpdateOrderDetail  = array (
											    'sku' 					=> $prdRes->sku,
											    'quantity' 				=> $quantity,
											    'product_name' 			=> $prdRes->product_name,
											    'products_id' 			=> $prdRes->products_id,
												'quantity' 				=> $quantity,
												'price' 				=> $unit_price,
												'total' 				=> $TotalUnitPrice,
												'status' 				=> '1',
												'item_price' 			=> $unit_price
											);

				$UpdateOrder = DropshipperOrderDetail::findOrFail($orders_detail_id);
				$UpdateOrder->update($UpdateOrderDetail);
			}
			Session::put('SKULISTARR', $skuarr);
			$shipping_signature	  = $request["shipping_signature"];
			$TotalOrderItems = DropshipperOrderDetail::where('orders_id', '=', $orders_id)->count();

		    if($TotalOrderItems<=0)
		    {
		    	DropshipperOrder::where('orders_id', '=', $orders_id)->delete();
		        return redirect()->back();
			}
			else
			{
				$order_res = DropshipperOrder::where('orders_id', '=', $orders_id)->first();

				if($order_res && $order_res->count() > 0) {
					$shipping_mode_id     = $order_res->shippingModeId;
					$ship_first_name	  = trim($order_res->ship_first_name);
					$ship_last_name	  	  = trim($order_res->ship_last_name);
					$ship_company	 	  = trim($order_res->ship_company);
					$ship_address1	 	  = trim($order_res->ship_address1);
					$ship_address2	  	  = trim($order_res->ship_address2);
					$ship_city	  		  = trim($order_res->ship_city);
					$ship_country		  = trim($order_res->ship_country);
					$ship_state			  = trim($order_res->ship_state);
					$ship_email			  = trim($order_res->ship_email);
					$ship_zip			  = trim($order_res->ship_email);
					$ship_phone			  = trim($order_res->ship_phone);

					$Subtotal =  number_format($Subtotal,2,'.','');
					$shipping_mode_id = $this->CheckAvailableShippingMethod($shipping_mode_id, $ship_country,$ship_state,$ship_zip,$Subtotal,$TotalQuantity);
					$ShippingCharge = 0;
					$shipping_days  = '';
					if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
					{
						$ShippingCharge = $this->CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id,$Subtotal,$TotalQuantity);
						$tempChargeArr  = explode("###",$ShippingCharge);
						$ShippingCharge = $tempChargeArr[0];
						$shipping_days  = $tempChargeArr[1];
					}
					$orderTotal = $Subtotal + $ShippingCharge + $shipping_signature;
					$orderTotal = number_format($orderTotal,2,'.','');
					$updateStockInfo = array(
												'sub_total' 		=> $Subtotal,
												'order_total'		=> $orderTotal,
												'shippingModeId'	=> $shipping_mode_id,
												'shipping_amt'		=> $ShippingCharge,
												'shipping_signature'	=> $shipping_signature,
												'shipping_days'		=> $shipping_days
											);

					$UpdateStock = DropshipperOrder::findOrFail($orders_id);
					$UpdateStock->update($updateStockInfo);

					Session::flash('success', 'SKU(s) Updated Successfully.');
			        return redirect()->back();
				} else {
			        return redirect()->back();
				}

			}
		}

		if($request->has('pageAction') && ($request->pageAction) == 'UpdateOrder') {
			$totalItemsQty = 0;
			$skuarr = array();
			$QuantityChange = "No";
			$Subtotal	= 0;
			$TotalQuantity	= 0;

			if(Session::has('SKULISTARR') && count(Session::get('SKULISTARR')) > 0) {
				Session::forget('SKULISTARR');
			}

			$pageAction = trim($request['pageAction']);
			$orders_id = (int) $request['orders_id'];
			$total_items = (int) $request['total_items'];
			$loopvalue = (int) $request['loopvalue'];
			$start_record = $total_items + 1;

			############ Update order detail start hare#################
			for ($i = 1; $i <= $total_items; $i++) {
				$orders_detail_id = (int) $request["orders_detail_id" . $i];
				$vtmpsku =  $request["vtmpsku" . $i];
				$quantity = $request["quantity".$i];
		        $unit_price = $request["unit_price" . $i];
		        $total_price = $request["total_price" . $i];
		        $old_vtmpsku =  $request["old_vtmpsku" . $i];
				$OriginalQuantity = 0;

				$prdRes = Products::select('pu_products.products_id', 'pu_products.sku', 'pu_products.is_atomizer', 'pu_products.short_description', 'pu_products.vtype', 'pu_products.product_type', 'pu_products.product_name', 'pu_products.gender', 'pu_manufacture.vmanufacture', 'pu_products.minimum_stock', 'pu_products.current_stock', 'pu_products.wholesale_price as product_price', 'pu_products.image', 'pu_products_category.category_id', 'pu_products.brand_id', 'pu_products.imanufactureid')
							->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
							->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
							->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
							->where('pu_products.status', '=', '1')
							->where('pu_category.status', '=', '1')
							->where('pu_products.sku', '=', $old_vtmpsku)
							->whereIn('pu_products.product_type', ['wholesaler','both'])
							->having('pu_products.wholesale_price', '>', 0)
							->first();

				if($prdRes != null && $prdRes->count() <= 0) {
					DropshipperOrderDetail::where('orders_detail_id', '=', $orders_detail_id)->delete();
					$skuarr[] = $vtmpsku.",";
					continue;
				}
				if($prdRes && $prdRes->current_stock <= 0)
				{
					DropshipperOrderDetail::where('orders_detail_id', '=', $orders_detail_id)->delete();
					$skuarr[] = $vtmpsku.",";
					continue;
				}
				if($prdRes && $quantity > $prdRes->current_stock)
				{
					$quantity = $prdRes->current_stock;
					$QuantityChange = "Yes";
					$OriginalQuantity = $quantity;
					$TotalUnitPrice = $quantity * $unit_price;

					$TotalUnitPrice = number_format($TotalUnitPrice,2,'.','');

					$Subtotal = $Subtotal + $TotalUnitPrice;

					$TotalQuantity = $TotalQuantity + $quantity;

					$UpdateOrderDetail  = array (
												  'quantity' 				=> $quantity,
												  'total' 					=> $TotalUnitPrice,
												  'Original_product_stock'	=> $OriginalQuantity
												);
					$UpdateOrder = DropshipperOrderDetail::findOrFail($orders_detail_id);
					$UpdateOrder->update($UpdateOrderDetail);
				} else {
					$OriginalQuantity = 0;
					$TotalQuantity = $TotalQuantity + $quantity;

					$TotalUnitPrice = $quantity * $unit_price;

					$TotalUnitPrice = number_format($TotalUnitPrice,2,'.','');

					$Subtotal = $Subtotal + $TotalUnitPrice;

					$UpdateOrderDetail  = array (
												  'quantity' 				=> $quantity,
												  'Original_product_stock'	=> $OriginalQuantity,
												  'total' 					=> $TotalUnitPrice,
												);
					$UpdateOrder = DropshipperOrderDetail::findOrFail($orders_detail_id);
					$UpdateOrder->update($UpdateOrderDetail);
				}

			}

			for ($i = $start_record; $i <= $loopvalue; $i++) {
				$sku = $request["form_productid" . $i];
		        if ($sku != '') {
		            $quantity = $request["qty" . $i];
		            $unit_price = $request["price" . $i];
		            $total_price = $request["txtprdtotal" . $i];
					$prdRes = Products::select('pu_products.products_id', 'pu_products.sku', 'pu_products.is_atomizer', 'pu_products.short_description', 'pu_products.vtype', 'pu_products.product_type', 'pu_products.product_name', 'pu_products.gender', 'pu_manufacture.vmanufacture', 'pu_products.minimum_stock', 'pu_products.current_stock', 'pu_products.wholesale_price as product_price', 'pu_products.image', 'pu_products_category.category_id', 'pu_products.brand_id', 'pu_products.imanufactureid')
								->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
								->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
								->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
								->where('pu_products.status', '=', '1')
								->where('pu_category.status', '=', '1')
								->where('pu_products.sku', '=', $sku)
								->whereIn('pu_products.product_type', ['wholesaler','both'])
								->having('pu_products.wholesale_price', '>', 0)
								->first();

					if($prdRes->count() <= 0) {
						$skuarr[] = $vtmpsku.",";
						continue;
					}
					if($prdRes && $prdRes->current_stock <= 0)
					{
						$skuarr[] = $vtmpsku.",";
						continue;
					}
					if($quantity > $prdRes->current_stock)
					{
						$quantity = $prdRes->current_stock;
						$QuantityChange = "Yes";
						$OriginalQuantity = $quantity;
						$TotalUnitPrice = $quantity * $unit_price;

						$TotalUnitPrice = number_format($TotalUnitPrice,2,'.','');

						$Subtotal = $Subtotal + $TotalUnitPrice;

						$TotalQuantity = $TotalQuantity + $quantity;

						$InsertPrCategoryTable = array(
		                        'orders_id' 				=> $orders_id,
		                        'orders_no' 				=> "OR".$orders_id,
		                        'products_id' 				=> $prdRes->products_id,
		                        'sku' 						=> $sku,
		                        'product_name' 				=> $prdRes->product_name,
		                        'quantity' 					=> $quantity,
		                        'price' 					=> $unit_price,
		                        'total' 					=> $TotalUnitPrice,
		                        'status' 					=> '1',
		                        'item_price' 				=> $unit_price,
		                        'customer_id' 				=> Auth::user()->customer_id,
		                        'Original_product_stock'	=> $OriginalQuantity
		                    );

						$TotalQuantity = $TotalQuantity + $prdRes->current_stock;
						$InsertDropshipperOrderDetail = DropshipperOrderDetail::create($InsertPrCategoryTable);
					} else {
						$OriginalQuantity = 0;

						$TotalQuantity = $TotalQuantity + $quantity;

						$TotalUnitPrice = $quantity * $unit_price;

						$TotalUnitPrice = number_format($TotalUnitPrice,2,'.','');

						$Subtotal = $Subtotal + $TotalUnitPrice;

						$InsertPrCategoryTable = array(
		                        'orders_id' 				=> $orders_id,
		                        'orders_no' 				=> "OR".$orders_id,
		                        'products_id' 				=> $prdRes->products_id,
		                        'sku' 						=> $sku,
		                        'product_name' 				=> $prdRes->product_name,
		                        'quantity' 					=> $quantity,
		                        'price' 					=> $unit_price,
		                        'total' 					=> $TotalUnitPrice,
		                        'status' 					=> '1',
		                        'item_price' 				=> $unit_price,
		                        'customer_id' 				=> Auth::user()->customer_id,
		                        'Original_product_stock'	=> $OriginalQuantity
		                    );

						$TotalQuantity = $TotalQuantity + $prdRes->current_stock;
						$InsertDropshipperOrderDetail = DropshipperOrderDetail::create($InsertPrCategoryTable);
					}
		        } else {
		        	continue;
		        }
			}

			Session::put('SKULISTARR', $skuarr);
			$TotalOrderItems = DropshipperOrderDetail::where('orders_id', '=', $orders_id)->count();

		    if($TotalOrderItems<=0)
		    {
		    	DropshipperOrder::where('orders_id', '=', $orders_id)->delete();
		        return redirect()->back();
			}
			else
			{
				$order_res = DropshipperOrder::where('orders_id', '=', $orders_id)->first();
				if($order_res && $order_res->count() > 0) {
					$shipping_mode_id     = $request["shippingId"];
					$ship_first_name	  = trim($request["ship_first_name"]);
					$ship_last_name	  	  = trim($request["ship_last_name"]);
					$ship_company	 	  = trim($request["ship_company"]);
					$ship_address1	 	  = trim($request["ship_address1"]);
					$ship_address2	  	  = trim($request["ship_address2"]);
					$ship_city	  		  = trim($request["ship_city"]);
					$ship_country		  = trim($request["ship_country"]);
					$ship_state			  = trim($request["ship_state"]);
					$ship_email			  = trim($request["ship_email"]);
					$shipping_signature	  = $request["shipping_signature"];
					if($ship_country!='US')
					{
						$ship_state = trim($request["other_state"]);
					}
					$ship_zip			  = trim($request["ship_zip"]);
					$ship_phone			  = trim($request["ship_phone"]);
					$subTotal =  number_format($Subtotal,2,'.','');
					$shipping_mode_id = $this->CheckAvailableShippingMethod($shipping_mode_id, $ship_country,$ship_state,$ship_zip,$Subtotal,$TotalQuantity);

					$ShippingCharge = 0;
					$shipping_days  = '';
					if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
					{
						$ShippingChargeStr = $this->CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id,$Subtotal,$TotalQuantity);
						$tempChargeArr  = explode("###",$ShippingChargeStr);
						$ShippingCharge = $tempChargeArr[0];
						$shipping_days  = $tempChargeArr[1];
					}
					$orderTotal = $subTotal + $ShippingCharge + $shipping_signature;
					$orderTotal = number_format($orderTotal,2,'.','');
					$updateStockInfo = array(
												'sub_total' 		=> $subTotal,
												'order_total'		=> $orderTotal,
												'ship_first_name'	=> $ship_first_name,
												'ship_last_name'	=> $ship_last_name,
												'ship_company'		=> $ship_company,
												'ship_email'		=> $ship_email,
												'ship_address1'		=> $ship_address1,
												'ship_address2'		=> $ship_address2,
												'ship_city'			=> $ship_city,
												'ship_zip'			=> $ship_zip,
												'ship_state'		=> $ship_state,
												'ship_country'		=> $ship_country,
												'ship_phone'		=> $ship_phone,
												'shippingModeId'	=> $shipping_mode_id,
												'shipping_amt'		=> $ShippingCharge,
												'shipping_signature'=> $shipping_signature,
												'shipping_days'		=> $shipping_days
											);

					$UpdateStock = DropshipperOrder::findOrFail($orders_id);
					$UpdateStock->update($updateStockInfo);

					Session::flash('success', 'Order details updated successfully.');
			        return redirect()->back();
				} else {
			        return redirect()->back();
				}
			}
		}

		$orders_id = (int)$request['id'];
		$errmsg = '';
		$SKUUPDATESUCCESS = '';
		if(Session::has('SKUUPDATESUCCESS') && Session::get('SKUUPDATESUCCESS') != '') {
			$SKUUPDATESUCCESS = Session::get('SKUUPDATESUCCESS');
			Session::forget('SKUUPDATESUCCESS');
		}

		if(Session::get('eusertype')=="Wholesaler" && Session::get('is_dropshipper')=="Yes" && config('Settings.DROPSHIPPER_SYSTEM_FLAG') == 'Yes') {
			$OrderRs = DropshipperOrder::select('*', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i') AS datetime"))
								->where('customer_id', '=', Auth::user()->customer_id)
								->where('orders_id', '=', $orders_id)
								->first();

			if($OrderRs && $OrderRs->count() <= 0) {
				Session::flash('error', 'Wrong order selected, please choose a order from list to view detail.');
				return redirect()->back();
			} else {
				$OrderRs->list_outofstock	= str_replace(",",", #", substr($OrderRs->list_outofstock,0,-1));
				$currencyguide = explode("#",$OrderRs->currency_info);
				$currencysymbol = $currencyguide[1];
				$currencyrate = $currencyguide[2];
				$OrderRs->currency_info = $currencyguide[0];

				$OrderDetailRs = DropshipperOrderDetail::select('*')
									->where('orders_id', '=', $OrderRs->orders_id)
									->orderBy('orders_detail_id', 'ASC')
									->get();

				$TotalQuantity = 0;
				$Subtotal = 0;
				if(count($OrderDetailRs) > 0) {
					foreach ($OrderDetailRs as $order_details_key => $order_details_value) {
						$prod_res = Products::select('image', 'current_stock')
									->where('sku', '=', trim($order_details_value['sku']))
									->whereIn('product_type', ['wholesaler','both'])
									->where('wholesale_price', '>', '0')
									->first();

						$errmsgOutOfStock = '';
						if($prod_res && $prod_res->count() <= 0)
						{
							$errmsgOutOfStock = "Item is not found";
							$order_details_value["quantity"] = 0;
							$order_details_value["total"]	   = 0;
						}
						else if($prod_res->current_stock <= 0)
						{
							$errmsgOutOfStock = "Item is out of stock or item is inactive";
							$order_details_value["quantity"] = 0;
							$order_details_value["total"]	   = 0;

						}
						else if($order_details_value["Original_product_stock"] > $prod_res->current_stock && $order_details_value["Original_product_stock"] > 0)
						{
							$errmsgOutOfStock = "Your quantity is ".$order_details_value['Original_product_stock'].", the item stock is ".$prod_res->current_stock;
							$order_details_value["quantity"] = $prod_res->current_stock;
						}
						else if($order_details_value['quantity'] > $prod_res->current_stock)
						{
							$errmsgOutOfStock = "Your quantity is ".$order_details_value['quantity']." the item stock is ".$prod_res->current_stock;
							$order_details_value["quantity"] = $prod_res->current_stock;
						}
						if(Session::has('SKULISTARR') && count(Session::get('SKULISTARR')) > 0) {
							$Skustr = implode("','", Session::get('SKULISTARR'));
							$errmsg = "The sku(s) ".$Skustr. " are out of stock or inactive";
						}
						$TotalUnitPrice = $order_details_value["quantity"] * $order_details_value["item_price"];

						$TotalUnitPrice = number_format($TotalUnitPrice,2,'.','');

						$Subtotal = $Subtotal + $TotalUnitPrice;

						if(File::exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->image) && !empty($prod_res->product_image)) {
							$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->product_image;
						} else {
							$thumb_image = config('global.NO_IMAGE_THUMB');
						}

						$order_details_value['Image'] = $thumb_image;
						$TotalQuantity = $TotalQuantity + $order_details_value["quantity"];
						$order_details_value['errmsgOutOfStock'] = $errmsgOutOfStock;
						$UpdateOrderDetail  = array(
														  'quantity' 		=> $order_details_value["quantity"],
														  'total' 			=> $TotalUnitPrice
												   );

						$updateDropshipperOrderDetail = DropshipperOrderDetail::find($order_details_value["orders_detail_id"]);

						$updateDropshipperOrderDetail->update($UpdateOrderDetail);

					}

					$shippingModeId = $OrderRs->shippingModeId;
					$shippingCharge = 0;
					$shipping_mode_id = 0;
					$shipping_days = 0;

					if($shippingModeId > 0)
					{
						$shipping_mode_id = $this->CheckAvailableShippingMethod($shippingModeId , $OrderRs->ship_country,$OrderRs->ship_state,$OrderRs->ship_zip,$Subtotal,$TotalQuantity);
					}

					if(isset($shipping_mode_id) && $shipping_mode_id > 0 && $shipping_mode_id!='')
					{

						if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
						{

							$tempChargeStr  = $this->CalculateAvailableShippingCharge($OrderRs->ship_zip,$OrderRs->ship_state,$OrderRs->ship_country,$shipping_mode_id,$Subtotal,$TotalQuantity);

							$tempChargeArr  = explode("###",$tempChargeStr);
							$tempCharge 	= $tempChargeArr[0];
							$shipping_days  = $tempChargeArr[1];
							$shippingModeId = $shipping_mode_id;
							$shippingCharge = $tempCharge;
						}
					} else {

						$ShippingModeRS = ShippingMode::where('status', '=', '1')->where('eusertype', '=', 'Dropshipper')->orderBy('display_position', 'ASC')->get();

						if($ShippingModeRS && $ShippingModeRS->count() > 0) {

							foreach($ShippingModeRS as $key_shipping_mode => $value_shipping_mode) {
								$shipping_mode_id = $this->CheckAvailableShippingMethod($value_shipping_mode['shipping_mode_id'], $OrderRs->ship_country,$OrderRs->ship_state,$OrderRs->ship_zip,$Subtotal,$TotalQuantity);

								if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
								{

									$tempChargeStr  = $this->CalculateAvailableShippingCharge($OrderRs->ship_zip,$OrderRs->ship_state,$OrderRs->ship_country,$shipping_mode_id,$Subtotal,$TotalQuantity);

									$tempChargeArr  = explode("###",$tempChargeStr);
									$tempCharge 	= $tempChargeArr[0];

									if($tempCharge > 0)
									{
										$shippingModeId = 	$shipping_mode_id;
										$shippingCharge = 	$tempCharge;
										$shipping_days  =   $tempChargeArr[1];
										break;
									}

								}
								else
								{
									continue;
								}

							}
						}
					}

					$shipping_signature = 0;
					if($OrderRs->shipping_signature > 0)
					{
						if(config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') == "Yes" && Session::get('eusertype') == "Wholesaler" &&  Session::get('etype') == "M")
						{
							$shipping_signature = config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE');
						}
					}

				    $orderTotal = 0;
					$Subtotal =  number_format($Subtotal,2,'.','');

					$orderTotal = $Subtotal + $shippingCharge + $shipping_signature;
					$orderTotal = number_format($orderTotal,2,'.','');

					$updateStockInfo = array(
												'sub_total' 		 => $Subtotal,
												'order_total'		 => $orderTotal,
												'shipping_amt'		 => $shippingCharge,
												'shippingModeId'	 => $shippingModeId,
												'shipping_signature' => $shipping_signature,
												'shipping_days'		 => $shipping_days
											);
					$updateDropshipperOrder = DropshipperOrder::find($OrderRs->orders_id);
					$updateDropshipperOrder->update($updateStockInfo);
					$OrderRs->sub_total		= $Subtotal;
					$OrderRs->shipping_amt		= $shippingCharge;
					$OrderRs->order_total		= $orderTotal;
					$OrderRs->shippingModeId	= $shippingModeId;
					$OrderRs->shipping_signature	= $shipping_signature;
				}
			}

			$htmlstr = '';
			for($j=count($OrderDetailRs)+1;$j<=30;$j++) {
				$str = '*^';
	           	$htmlstr.='<tr id="carttrid'.$j.'" style="display:none;">';
	            $htmlstr.='<td data-title="full center" class="text-left">
	            		<div class="row row5">
		                    <div class="col-lg-4 col-md-5 col-sm-6 text-center text-sm-left" id="vprod_image'.$j.'">Coming Soon</div>
		                    <div class="col-lg-8 col-md-7 col-sm-6 text-center text-sm-left pt-2 pt-sm-0">
								Item SKU :
								<input name="form_productid'.$j.'" id="form_productid'.$j.'" value="" type="text" onkeyup=get_Sku_List(this.value+"'.$str.'"+'.$j.') class="input form-control" />
								<a href="javascript:void(0);" onclick="javascript:RemoveLineItem('.$j.');" title="Remove Item" class="pt-3">Remove Items</a>
		                    </div>
	                    </div>
	              </td>';
	            $htmlstr .= '<td data-title="Unit price"><input type="text" name="price'.$j.'" id="price'.$j.'" onchange="prdtotal('.$j.');" class="input form-control" readonly></td>
	              <td data-title="Quantity"><input type="text" name="qty'.$j.'" id="qty'.$j.'" value="1" onkeyup="prdtotal('.$j.');" class="input form-control" placeholder="Quantity" style="width:50%;text-align:center;" ></td>
	              <td data-title="Total Price" class="text-md-right text-left"><input type="text" name="txtprdtotal'.$j.'" id="txtprdtotal'.$j.'" class="input form-control" readonly></td>
	           </tr>';
			}
			$this->PageData['htmlstr'] = $htmlstr;
			$this->PageData['OrderRs'] = $OrderRs;
			$this->PageData['OrderDetailRs'] = $OrderDetailRs;
			$this->PageData['TotalQuantity'] = $TotalQuantity;
			$this->PageData['Countries'] = GetCountries();
			$this->PageData['States'] = GetStates();
			$this->PageData['SelCountry'] = 'US';
			$this->PageData['errmsg'] = $errmsg;
			$this->PageData['SKUUPDATESUCCESS'] = $SKUUPDATESUCCESS;
			$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Imported Dropshipper Order Detail';
			$this->PageData['CSSFILES'] = ['myaccount.css', 'importedorderdetail.css'];
			$this->PageData['JSFILES'] = ['importedorderdetail.js'];
			return view('myaccount.importedorderdetail')->with($this->PageData);
		} else {
			return redirect(config('global.SITE_URL'));
		}
	}

	public function DropshipShippingMethods(Request $request)
	{
		if($request->ajax()){
			if(!Auth::user()) {
		        $response['status'] = 401;
		        $response['message'] = "Unauthorized request";
		        return response()->json($response, 400);
			} else {
				$ship_zip 					= $request["ship_zip"];
				$ship_state 				= $request["ship_state"];
				$ship_country 				= $request["ship_country"];
				$subTotal	  				= $request["subTotal"];
				$TotalQuantity				= $request["TotalQuantity"];
				$shippingId					= $request["shippingId"];
				$shipping_sign				= $request["shipping_sign"];

				$ShippingModeRS = ShippingMode::where('status', '=', '1')->where('eusertype', '=', 'Dropshipper')->orderBy('display_position', 'ASC')->get();
				$ShippingMethodDropDown = '';
				$ShippingMethodDropDownFinal = '';
				$count = 0;
				$Checkcounter = 0;
				if(count($ShippingModeRS) > 0) {
					foreach($ShippingModeRS as $key => $val) {
						$shipping_mode_id = $this->CheckAvailableShippingMethod($val['shipping_mode_id'], $ship_country,$ship_state,$ship_zip,$subTotal,$TotalQuantity);
						if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
						{
							$tempChargeStr = $this->CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id,$subTotal,$TotalQuantity);
							$tempChargeArr = explode("###",$tempChargeStr);

							$tempCharge = $tempChargeArr[0];
							$days		= $tempChargeArr[1];

							$charge_str = '';

							if($tempCharge>0)
							{
								$charge_str = $this->Make_Price($tempCharge,true);
							}
							if(empty($shippingId))
							{
							 	 if($count==0)
									$r_sel = " checked ";
							   	else
									$r_sel = "";
							}
							else
							{
								if($shippingId==$val['shipping_mode_id'])
									$r_sel = " checked ";
								else
									$r_sel = "";
							}
							$val['days']	= $days;

							$estimateShipDate='';
							if($val['days']!='')
							{
								$estimateShipDate = $this->getShipmentEstimateDate($val['days']);
							}else
							{
								$estimateShipDate='';
							}
							$Checkcounter = 1;

							$TooltipStr = 'Please note delivery dates are only estimation arrival time and not guaranteed';

							if($charge_str!='')
							{
								$ShippingMethodDropDown .= '<label id="method-'.$count.'" class="comcheck radio d-inline-block w-100 checkbox-label clsship  active ">
								   <div class="chebox">
								      <input type="radio" class="shipmethod" name="shippingModeId" data-key="'.$count.'" id="shippingModeId'.$count.'"  value="'.$val['shipping_mode_id'].'" '.$r_sel.' onclick="Ajax_GetOrder_Summery();">
								      <span class="checkmark"></span>
								      <span class="float-left w-75">
								         <div>
								            <strong>'.$estimateShipDate.' - <strong>'.$charge_str.'</strong></strong>
								            <span class="cart-tooltip max_qttable">
								            <a href="javascript:void(0);">
								            <u>i</u>
								            </a>
								            <span class="tables">'.$TooltipStr.'</span>
								            </span>
								         </div>
								         <div>'.$val['type'].'</div>
								      </span>
								   </div>
								</label>';

							}
							else
							{
								$ShippingMethodDropDown .= '<label id="method-'.$count.'" class="comcheck radio d-inline-block w-100 checkbox-label clsship  active ">
								   <div class="chebox">
								      <input type="radio" class="shipmethod" name="shippingModeId" data-key="'.$count.'" id="shippingModeId'.$count.'"  value="'.$val['shipping_mode_id'].'" '.$r_sel.' onclick="Ajax_GetOrder_Summery();">
								      <span class="checkmark"></span>
								      <span class="float-left w-75">
								         <div>
								            <strong>'.$estimateShipDate.' - <strong>Free</strong></strong>
								            <span class="cart-tooltip max_qttable">
								            <a href="javascript:void(0);">
								            <u>i</u>
								            </a>
								            <span class="tables">'.$TooltipStr.'</span>
								            </span>
								         </div>
								         <div>'.$val['type'].'</div>
								      </span>
								   </div>
								</label>';
							}
							$count = $count +1;
						}
						else
						{
							continue;
						}
					}
				}

				if($Checkcounter==1) {
					if(config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') == "Yes" && Session::get('eusertype') == "Wholesaler" && Session::get('etype') == "M" && $ship_country=="US") {
						$checked = '';
						if($shipping_sign > 0)
						{
							$checked= "checked='checked'";
						}
						$ShippingMethodDropDownFinal = '<label id="method-shipping-signature" class="comcheck d-inline-block w-100 checkbox-label ">
						   <div class="chebox">
						      <input type="checkbox" class="shipmethod" name="shipping_signature" data-key="Yes" id="shipping_signature"  value="Yes" '.$r_sel.' onclick="Ajax_GetOrder_Summery();" '.$checked.'>
						      <span class="checkmark"></span>
						      <span class="float-left w-75">
						         <div>$'.config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE').' Request Signature</div>
						      </span>
						   </div>
						</label>'.$ShippingMethodDropDown;
					}
				}
				if($Checkcounter == 0)
				{
				  $ShippingMethodDropDownFinal = '
				                    <label class="frmerror frmerror_shw"><strong>There is no shipping method available to your destination. Please fill a different shipping address.</strong></label>';
				}

		        $response['status'] = 200;
		        $response['message'] = "Success";
		        $response['html'] = $ShippingMethodDropDownFinal;
		        return response()->json($response, 200);
			}
		} else {
	        $response['status'] = 404;
	        $response['message'] = "Not found";
	        return response()->json($response, 400);
		}
	}

	public function DropshipOrderSummary(Request $request)
	{
		if($request->ajax()){
			if(!Auth::user()) {
		        $response['status'] = 401;
		        $response['message'] = "Unauthorized request";
		        return response()->json($response, 400);
			} else {
				$ship_zip 					= $request["ship_zip"];
				$ship_state 				= $request["ship_state"];
				$ship_country 				= $request["ship_country"];
				$sub_total	  				= $request["sub_total"];
				$TotalQuantity				= $request["TotalQuantity"];
				$shipping_mode_id 			= $request['shipping_mode_id'];
				$ship_city					= $request["ship_city"];
				$ShippingCharge = 0;
				$ShippingSignature = 0;

				$shipping_mode_id = $this->CheckAvailableShippingMethod($shipping_mode_id, $ship_country,$ship_state,$ship_zip,$sub_total,$TotalQuantity);

				if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
				{
					$ShippingCharge = $this->CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id,$sub_total,$TotalQuantity);
					$tempChargeArr = explode("###",$ShippingCharge);

					$ShippingCharge = $tempChargeArr[0];
					$days		= $tempChargeArr[1];

				}

				$ShippingSignature = 0;
				if($request["shipping_signature"]=="Yes")
				{
					if(config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') == "Yes" && Session::get('eusertype') == "Wholesaler" && Session::get('etype') == "M") {
						$ShippingSignature = config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE');
					}
				}

				$totalNetAmount 	= $sub_total;
				// dd($sub_total, $ShippingCharge);
				if($ShippingCharge > 0)
				{
					 $totalNetAmount 	= $sub_total + $ShippingCharge;
				}

				if($ShippingSignature > 0)
				{
					$totalNetAmount = $totalNetAmount + $ShippingSignature;
				}

				$totalNetAmount	= number_format((float) $totalNetAmount, 2, '.','');

                $htmlString = '<table class="st_table">
                  <tr>
                     <td width="48%">Subtotal:</td>
                     <td class="text-left text-md-right"><strong>'.config('global.SITE_CURRENCY_SYMBOL').'<span id="sub_total_span">'.$sub_total.'</span><input name="fsub_total" id="fsub_total" value="'.$sub_total.'"  type="hidden" /></strong></td>
                  </tr>';
				  if($ShippingCharge > 0) {
                  $htmlString .= '<tr>
                     <td>Shipping:</td>
                     <td class="text-left text-md-right"><strong>'.config('global.SITE_CURRENCY_SYMBOL').$ShippingCharge.'</strong></td>
                  </tr>';
                  }
				  $htmlString .= '<input type="hidden" value="'.$ShippingCharge.'" name="shipping_amt" id="shipping_amt" class="admin-input" size="10">';
                  if($ShippingSignature > 0) {
                  $htmlString .= '<tr>
                     <td>Shipping Signature: </td>
                     <td class="text-left text-md-right"><strong>'.config('global.SITE_CURRENCY_SYMBOL').$ShippingSignature.'</strong></td>
                  </tr>';
                  }
				  $htmlString .= '<input type="hidden" value="'.$ShippingSignature.'" name="shipping_signature" id="shipping_signature" class="admin-input" size="10">';
                  $htmlString .= '<tr>
                     <th>Order Total</th>
                     <th class="text-left text-md-right"><strong>'.config('global.SITE_CURRENCY_SYMBOL').$totalNetAmount.'</strong></th>
				  	 <input type="hidden" value="'.$totalNetAmount.'" name="order_total" id="order_total" class="admin-input" size="10">
                  </tr>
               </table>';

		        $response['status'] = 200;
		        $response['message'] = "Success";
		        $response['html'] = $htmlString;
		        return response()->json($response, 200);
			}
		} else {
	        $response['status'] = 404;
	        $response['message'] = "Not found";
	        return response()->json($response, 400);
		}
	}

	public function DropshipOrderItemRemove(Request $request)
	{
		if($request->ajax()){
			if(!Auth::user()) {
		        $response['status'] = 401;
		        $response['message'] = "Unauthorized request";
		        return response()->json($response, 400);
			} else {
				if(isset($request->orders_id) && ($request->orders_id) != '' && isset($request->orders_detail_id) && ($request->orders_detail_id) != '') {
					DropshipperOrderDetail::where('orders_detail_id',$request->orders_detail_id)->where('orders_id', $request->orders_id)->delete();
					$TotalOrdersItems = DropshipperOrderDetail::select('orders_detail_id')->where('orders_id', $request->orders_id)->count();
					if($TotalOrdersItems <=0)
					{
						DropshipperOrder::where('orders_id',$request->orders_id)->delete();
						Session::flash('success', 'Order item removed successfully');
					}
			        $response['status'] = 200;
			        $response['message'] = "Success";
					Session::flash('success', 'Order details updated successfully');
			        return response()->json($response, 200);
				} else {
			        $response['status'] = 400;
			        $response['message'] = "Bad parameters";
			        return response()->json($response, 400);
				}
			}
		} else {
	        $response['status'] = 404;
	        $response['message'] = "Not found";
	        return response()->json($response, 400);
		}
	}

	public function AjaxListSkus(Request $request)
	{
		if($request->ajax()) {
			$sku = $request['skukwd'];
			$no = $request['no'];
			$quantity = $request['quantity'];
			$ProductRs = Products::select('pu_products.products_id', 'pu_products.sku', 'pu_products.is_atomizer', 'pu_products.short_description', 'pu_products.vtype', 'pu_products.product_type', 'pu_products.product_name', 'pu_products.gender', 'pu_manufacture.vmanufacture', 'pu_products.minimum_stock', 'pu_products.current_stock', 'pu_products.wholesale_price as product_price', 'pu_products.image', 'pu_products_category.category_id', 'pu_products.brand_id', 'pu_products.imanufactureid')
						->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
						->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
						->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
						->where('pu_products.status', '=', '1')
						->where('pu_category.status', '=', '1')
						->where('pu_products.sku', '=', $sku)
						->whereIn('pu_products.product_type', ['wholesaler','both'])
						->having('pu_products.wholesale_price', '>', 0)
						->first();
			if($ProductRs && $ProductRs->count() > 0) {
				if($ProductRs->minimum_stock > $ProductRs->current_stock || $ProductRs->current_stock <= 0) {
					$WebsiteStock = 'Out';
				} else {
					$WebsiteStock = 'In';
				}

				if($WebsiteStock == "In")
				{
					$per = 0;
					$val = 0;
					if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
					{
						$specialpricedtl = $this->getSpecialPricePercentandValue($quantity);
						$perval = explode("#",$specialpricedtl);
						if($perval[0] != '') {
							$per = $perval[0];
						}
						if($perval[1] != '') {
							$val = $perval[1];
						}
					}
					$ProductRs->product_price = number_format($ProductRs->product_price,2,'.','');
					$ProductRs->sale_price = $ProductRs->product_price;

					########################### Code For Change Price of Weekly Deal Product Start ###########################//

					$dealofdayRS = Dealofweek::select('pu_dealofweek.product_sku', 'pu_dealofweek.deal_price', 'pu_dealofweek.description', 'pu_dealofweek.discount_coupon_flag')
											->join('pu_dealofweektitle', 'pu_dealofweektitle.did', '=', 'pu_dealofweek.did')
											->join('pu_products', 'pu_products.sku', '=', 'pu_dealofweek.product_sku')
											->where('pu_products.status', '=', '1')
											->where('pu_dealofweek.status', '=', '1')
											->where('pu_dealofweek.start_date', '<=', date('Y-m-d H:i'))
											->where('pu_dealofweek.end_date', '>=', date('Y-m-d H:i'))
											->where('pu_dealofweek.deal_type', '=', 'Weekly')
											->orderBy('pu_dealofweek.dealofweek_id', 'DESC')
											->first();
		            $ProductRs->IsDealProducts = 'No';
					$ProductRs->DealDiscountFlag = 'No';
					if($dealofdayRS && $dealofdayRS->count() > 0) {
						if(trim($dealofdayRS->product_sku)==trim($ProductRs->sku)) {
							if($dealofdayRS->deal_price!='' && $dealofdayRS->deal_price < $ProductRs->sale_price) {
								$dealprice = number_format($dealofdayRS->deal_price,2,'.','');
								$ProductRs->product_price = $dealprice;
								if($dealofdayRS->description!='') {
									$ProductRs->short_description =  $dealofdayRS->description;
								}
							}
						 $ProductRs->DealDiscountFlag = $dealofdayRS->discount_coupon_flag;
						 $ProductRs->IsDealProducts = 'Yes';
						}
					}

					########################### Code For Change Price of Weekly Deal Product End ###########################//

					########################### Code For Change Price of Daily Deal Product Start Here###########################//
					$date = date('Y-m-d');
					$dealofdayRS = Dealofweek::select('pu_dealofweek.product_sku', 'pu_dealofweek.deal_price', 'pu_dealofweek.description', 'pu_dealofweek.discount_coupon_flag')
											->join('pu_dealofweektitle', 'pu_dealofweektitle.did', '=', 'pu_dealofweek.did')
											->join('pu_products', 'pu_products.sku', '=', 'pu_dealofweek.product_sku')
											->where('pu_products.status', '=', '1')
											->where('pu_dealofweek.status', '=', '1')
											->where('pu_dealofweek.start_date', '<=', date('Y-m-d H:i'))
											->where('pu_dealofweek.end_date', '>=', date('Y-m-d H:i'))
											->where('pu_dealofweek.deal_type', '=', 'Daily')
											->orderBy('pu_dealofweek.dealofweek_id', 'DESC')
											->first();
					if($dealofdayRS && $dealofdayRS->count() > 0) {
						if(trim($dealofdayRS->product_sku)==trim($ProductRs->sku)) {
							if($dealofdayRS->deal_price!='' && $dealofdayRS->deal_price < $ProductRs->sale_price) {
								$dealprice = number_format($dealofdayRS->deal_price,2,'.','');
								$ProductRs->product_price = $dealprice;
								if($dealofdayRS->description!='') {
									$ProductRs->short_description =  $dealofdayRS->description;
								}
							}
						 $ProductRs->DealDiscountFlag = $dealofdayRS->discount_coupon_flag;
						 $ProductRs->IsDealProducts = 'Yes';
						}
					}

					########################### Code For Change Price of Daily Deal Product End Here###########################//
					$SpecialPriceDetails = '';
					$ItemPrice = $ProductRs->product_price;

					if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
					{
						$ProductRs->product_price = $ProductRs->product_price - $ProductRs->product_price * $per/100;
					}
					if(File::exists(config('global.PRD_THUMB_IMG_PATH').$ProductRs->image) && !empty($ProductRs->image)) {
						$vthumb_image = config('global.PRD_THUMB_IMG_URL').$ProductRs->image;
					} else {
						$vthumb_image = config('global.NO_IMAGE_THUMB');
					}

					$html = $ProductRs->sku."###".number_format($ProductRs->product_price, 2, ".", "")."###".$no."###".$vthumb_image;

			        $response['status'] = 200;
			        $response['message'] = "Success";
			        $response['html'] = $html;
			        return response()->json($response, 200);
				} else {
			        $response['status'] = 200;
			        $response['message'] = "Not found";
			        $response['html'] = "No";
			        return response()->json($response, 200);
				}
			} else {
		        $response['status'] = 200;
		        $response['message'] = "Not found";
		        $response['html'] = "No";
		        return response()->json($response, 200);
			}
		} else {
	        $response['status'] = 404;
	        $response['message'] = "Not found";
	        return response()->json($response, 400);
		}
	}

	public function OrderDetailPdf(Request $request)
	{
		$IsGiftCertificateItem = '';
		$GC_IMAGE_URL = '';

		$orders_id = (int)$request['id'];
		if(!Auth::user()){
			$customer_id = (int)$request['cid'];
			$OrderRs = Order::select('*', DB::raw("DATE_FORMAT(order_datetime, '%b %d, %Y') AS datetime"))
							//->where('customer_id', '=', $customer_id)
							->where('orders_id', '=', $orders_id)
							->first();
		} else {
			$OrderRs = Order::select('*', DB::raw("DATE_FORMAT(order_datetime, '%b %d, %Y') AS datetime"))
							->where('customer_id', '=', Auth::user()->customer_id)
							->where('orders_id', '=', $orders_id)
							->first();
		}
		$GC_Only = 0;
		if($OrderRs && $OrderRs->count() <= 0) {
			// Session::flash('error', config('message.FTP.Failed'));
			Session::flash('error', 'Wrong order selected, please choose a order from list to view detail.');
			return redirect()->back();
		} else {

			/*if(!empty($OrderRs->total_refund_amount) && $OrderRs->total_refund_amount > 0)
			{
				$total_refund_amount = $OrderRs->order_total - $OrderRs->total_refund_amount;
				$OrderRs->total_refund_amount = $total_refund_amount;
			}*/

			$todayDateTime = $EstimatedDeliveryDate = $show_ship_status = '';

			$todayDateTime = Carbon::now();
			if($OrderRs->store_id == 0){
				$EstimatedDeliveryDate = $OrderRs->EstimatedDeliveryDate;
				$EstimatedDeliveryDate = Carbon::parse($EstimatedDeliveryDate);
			}

			//if($OrderRs->ship_status == 'Shipped' && $todayDateTime->isAfter($EstimatedDeliveryDate)){
			if(isset($OrderRs->ship_status) && $OrderRs->ship_status == 'Shipped' && $EstimatedDeliveryDate != '' && $todayDateTime->isAfter($EstimatedDeliveryDate)){
				$OrderRs->show_ship_status = 'Delivered';
			}
			else{
				$OrderRs->show_ship_status = $OrderRs->ship_status;
			}
			if($OrderRs->store_id == 0){
				$OrderRs->show_ship_status = 'Delivered';
			}

			$this->PageData['OrderRs'] = $OrderRs;

			$OrderDetailRs = OrderDetail::select('pu_order_detail.*', 'pu_products.UPC')
								->join('pu_products', 'pu_products.products_id', '=', 'pu_order_detail.products_id')
								->where('orders_id', '=', $OrderRs->orders_id)
								->orderBy('orders_detail_id', 'DESC')
								->get();
			if(count($OrderDetailRs) > 0) {
				foreach ($OrderDetailRs as $order_details_key => $order_details_value) {

					$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$order_details_value,'No');
					$OrderDetailRs[$order_details_key]['track_url']  = '';
					/*if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL'));
					}
					else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU1'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL1'));
					}
					else if($order_details_value["sku"]==config('global.GIFT_CERTIFICATE_SKU2'))
					{
						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], config('global.GC_IMAGE_URL2'));
					}*/
					$OrderDetailRs[$order_details_key]['IsGiftCertificateItem']  =$IsGiftCertificateItem;
					if($IsGiftCertificateItem == 'Yes'){
						$GC_IMAGE_URL = $this->checkGiftCertificateItem('SetGiftCertificateImageURL',$order_details_value,'No');

						$this->GetGiftCertificateData($OrderDetailRs, $order_details_key, $order_details_value['orders_detail_id'], $GC_IMAGE_URL);
					}
					else
					{
						if($order_details_value["is_free_gift_products"]=="Yes")
						{
							$prod_res = FreeGiftProduct::select('product_image')->whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->first();
							if(!empty($prod_res)) {
								if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->product_image) && !empty($prod_res->product_image))
									$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->product_image;
								 else
									$thumb_image = config('global.NO_IMAGE_THUMB');
							} else {
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
						}
						else
						{
							$prod_res = Products::select('image')->whereRaw("LOWER(TRIM(sku))='".strtolower(trim($order_details_value['sku']))."'")->first();
							if(!empty($prod_res)) {
								if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->image) && !empty($prod_res->image))
									$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->image;
								 else
									$thumb_image = config('global.NO_IMAGE_THUMB');
							} else {
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
						}
						$OrderDetailRs[$order_details_key]['Image'] = $thumb_image;

						if($OrderDetailRs[$order_details_key]['dtl_tracking_no'] && $OrderDetailRs[$order_details_key]['IsGiftCertificateItem'] == 'No')
					    {
							$track_url  = 'javascript:void(0);';
							if($OrderDetailRs[$order_details_key]['dtl_ship_method']=='USPS') {
								$track_url = 'https://tools.usps.com/go/TrackConfirmAction.action?tRef=fullpage&tLc=1&tLabels='.$OrderDetailRs[$order_details_key]['dtl_tracking_no'];
							} elseif($OrderDetailRs[$order_details_key]['dtl_ship_method']=='FedEx') {
								$track_url = 'https://www.fedex.com/fedextrack/?trknbr='.$OrderDetailRs[$order_details_key]['dtl_tracking_no'];
							}  elseif($OrderDetailRs[$order_details_key]['dtl_ship_method']=='UPS') {
								$track_url = config('global.SITE_URL').'tracking/'.$OrderDetailRs[$order_details_key]['dtl_tracking_no'];
							}

							$OrderDetailRs[$order_details_key]['track_url'] = $track_url;

						}

					}
				}

			}
			if($OrderRs->is_only_gc==1) {
				$GC_Only = 1;
			}
		}
		//echo "<pre>"; print_r($OrderDetailRs); exit;
		/* Logo coding starts here */
		$path = public_path('images/logo.png');
		if(File::exists($path)) {
			$type = pathinfo($path, PATHINFO_EXTENSION);
			$data = file_get_contents($path);
			$logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
		} else {
			$logo = '';
		}
		/* Logo coding ends here */

		##############Terms and condition start##############
		$order_date = date("F dS, Y",$OrderRs->order_datetime);
		$order_time = date("h:i A",$OrderRs->order_datetime);
		$str_ip = "";
		if($OrderRs->customer_ip != ""){
			$str_ip = " from IP address ".$OrderRs->customer_ip;
		}
		$InsuranceText11 = "";
		// $str_terms_condition = "By placing an order on Maxaroma you agreed to below Maxaroma Terms and Conditions on ".$order_date." at ".$order_time."".$str_ip;
		$insuranceText = "Shipping Insurance Protection OFF. Customer agreed to take full responsibility for any loss, damages or theft. You can submit a claim to the carrier directly.";
		 if(!empty($OrderRs->route_shipping_insurance_charge) && $OrderRs->route_shipping_insurance_charge > 0){
		 $insuranceText = "Your order is protected by shipping insurance* If you experience any issues with your order, please contact our customer service.";
		 $InsuranceText11 = "*Please note that this is a third party company that is not affiliated with Maxaroma LLC. All claims can be submitted through Maxaroma's website in order to facilitate the process.";
		 }
		 $signatureText = '';
		 $OrderRs->is_shipping_signatureOff = "ON";
		 if(!empty($OrderRs->is_shipping_signature) && $OrderRs->is_shipping_signature == "No"){
		 $signatureText = "Signature Opted Out: The customer agreed to take full responsibility for this package in case of loss or theft. MAXAROMA cannot take any claims if the status of the package is marked as delivered.";
		 $OrderRs->is_shipping_signatureOff = "OFF";
		 }

		$str_terms_condition = " *Agreed to Maxaroma Terms and Conditions on ".$order_date." at ".$order_time."".$str_ip;

		$terms_conditions_content = "";
		if(isset($OrderRs->customer_comment) && $OrderRs->customer_comment!='')
		{
			$terms_conditions_content .=  $OrderRs->customer_comment;
		}
		if(isset($OrderRs->order_comment) && $OrderRs->order_comment!='' &&	isset($OrderRs->phone_order_type) && ($OrderRs->phone_order_type === 'INS' || $OrderRs->phone_order_type === 'RE')){
			$terms_conditions_content .=  $OrderRs->order_comment;
		}
		$EstimatedDeliveryDate = '';

		if(isset($OrderRs->EstimatedDeliveryDate) && $OrderRs->EstimatedDeliveryDate!='0000-00-00 00:00:00')
		{
			$EstimatedDeliveryDate =  $OrderRs->EstimatedDeliveryDate;
		}

		// $PData = StaticPages::where('name','=','terms_and_conditions')->where('status','=','1')->get();
		// $terms_conditions_content = $PData[0]->content;

		// if (str_contains($terms_conditions_content, '{$Site_URL}')) {
			// $terms_conditions_content = str_replace('{$Site_URL}',config('global.SITE_URL'),$terms_conditions_content);
		// }
		// echo $terms_conditions_content;exit;
		##############Terms and condition end##############

		// return view('myaccount.orderdetailpdf',compact('OrderDetailRs', 'OrderRs', 'GC_Only', 'logo','str_terms_condition','terms_conditions_content'));
		// return PDF::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])->loadView('myaccount.orderdetailpdf', compact('OrderDetailRs', 'OrderRs', 'GC_Only', 'logo','str_terms_condition','terms_conditions_content'))->stream('Invoice-'.$OrderRs->orders_no.'.pdf', array("Attachment" => false));	//view pdf
		$is_skip_address = 'N';
		// if(isset($OrderRs->store_id) && $OrderRs->store_id > 0 && isset($OrderRs->is_skip_addresspart) && $OrderRs->is_skip_addresspart!=''){
		// 	$address_part = explode("###",$OrderRs->is_skip_addresspart);
		// 	if(isset($address_part[0]) && $address_part[0] == 'Yes' ){
		// 		$is_skip_address = 'Y';
		// 	}
		// }

		return PDF::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])->loadView('myaccount.orderdetailpdf', compact('OrderDetailRs', 'OrderRs', 'GC_Only', 'logo','str_terms_condition','terms_conditions_content','EstimatedDeliveryDate','insuranceText','signatureText','InsuranceText11','is_skip_address'))->download('Invoice-'.$OrderRs->orders_no.'.pdf');
	}

	public function ReOrderDetail(Request $request)
	{
		$orders_id = (int)$request['id'];

		$OrderRs = Order::select('*', DB::raw("DATE_FORMAT(order_datetime, '%m/%d/%Y %H:%i %p') AS datetime"))
							->where('customer_id', '=', Auth::user()->customer_id)
							->where('orders_id', '=', $orders_id)
							->first();
		$ProdRS = [];
		if(empty($OrderRs)) {
			Session::flash('error', 'Wrong order selected, please choose a order from list to reorder products.');
			return redirect()->back();
		} else {
			$this->PageData['OrderRs'] = $OrderRs;
			$OrderDetailRs = OrderDetail::select('sku')
								->where('orders_id', '=', $OrderRs->orders_id)
								->where('sku', '!=', 'GIFT-CERTIFICATE')
								->orderBy('orders_detail_id', 'DESC')
								->get();

			if(count($OrderDetailRs) > 0) {

				foreach ($OrderDetailRs as $order_details_key => $order_details_value) {
					$product_item_sku = $order_details_value["sku"];
					if(strtolower(Session::get('eusertype'))=='wholesaler') {
						$fetch = ' IF(pu_products.wholesale_price!=0 ,pu_products.wholesale_price, pu_products.our_price) AS our_price ';
					} else {
						$fetch = ' IF(pu_products.sale_price!=0 AND pu_products.sale_price < pu_products.our_price,pu_products.sale_price,pu_products.our_price) AS our_price ';
					}

					$prod_info = Products::select('pu_products.sku', 'pu_products.products_id', 'pu_products.product_name', 'pu_products.product_description', 'pu_products.short_description', 'pu_products.sale_price', 'pu_products.retail_price', 'pu_products_category.category_id', 'pu_products.image', 'pu_products.status', 'pu_products.product_type', 'pu_products.minimum_stock', 'pu_products.current_stock')->addSelect(DB::raw($fetch))
											->join('pu_brand', 'pu_brand.brand_id', '=', 'pu_products.brand_id')
											->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
											->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
											->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
											->where('pu_products.status', '=', '1')
											->where('pu_products.sku', '=', $product_item_sku)
											->having('our_price', '>', 0)
											->groupBy('pu_products.products_id')
											->first();
					if($prod_info && $prod_info->count() > 0) {

				   		if($prod_info->minimum_stock > $prod_info->current_stock || $prod_info->current_stock == 0) {
				   			$stock = 'Out';
				   		} else {
				   			$stock = 'In';
				   		}
						$dealofdayRS = Dealofweek::select('pu_dealofweek.product_sku', 'pu_dealofweek.deal_price', 'pu_dealofweek.description')
												->join('pu_dealofweektitle', 'pu_dealofweektitle.did', '=', 'pu_dealofweek.did')
												->join('pu_products', 'pu_products.sku', '=', 'pu_dealofweek.product_sku')
												->where('pu_products.status', '=', '1')
												->where('pu_dealofweek.status', '=', '1')
												->where('pu_dealofweek.start_date', '<=', date('Y-m-d H:i'))
												->where('pu_dealofweek.end_date', '>=', date('Y-m-d H:i'))
												->orderBy('pu_dealofweek.dealofweek_id', 'DESC')
												->first();
						if($dealofdayRS && $dealofdayRS->count() > 0) {
							if(trim($dealofdayRS->product_sku)==trim($prod_info->sku)) {
								if($dealofdayRS->deal_price!='' && $dealofdayRS->deal_price < $prod_info->our_price) {
									$dealprice = number_format($dealofdayRS->deal_price);
									$prod_info->our_price = $dealprice;
									if($dealofdayRS->description!='') {
										$prod_info->short_description =  $dealofdayRS->description;
									}
								}
							}
						}

						$description = '';
						if(trim($prod_info->product_description)!='')
						{
							$description = "<br><br>".$prod_info->product_description;
						}

						if(File::exists(config('global.PRD_THUMB_IMG_PATH').$prod_info->image) && $prod_info->image != '') {
							$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_info->image;
						} else {
							$thumb_image = config('global.NO_IMAGE_THUMB');
						}

			 			$p_link = $this->getProductRewriteURL($prod_info->products_id, $prod_info->product_name, $prod_info->category_id, $prod_info->sku);

						$ProdRS[] = array(
											"sku"				=> $prod_info->sku,
											"products_id"       => $prod_info->products_id,
											"product_name"	    => $prod_info->product_name,
											"description"	    => $description,
											"short_description" => $prod_info->short_description,
											"quantity"          => 1,
								   			"our_price"	    	=> $prod_info->our_price,
											"thumb_image"	    => $thumb_image,
											"p_link"		    => $p_link,
											"current_stock"     => $stock,
											"product_type"      => $prod_info->product_type,

									);

					}

				}

			}
		}
		// dd($ProdRS);
		$this->PageData['ProdRS'] = $ProdRS;

		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Reorder Products';
		$this->PageData['CSSFILES'] = ['myaccount.css'];
		$this->PageData['JSFILES'] = ['reorderdetail.js'];

		return view('myaccount.reorderdetail')->with($this->PageData);
	}

	public function ExportOrders(Request $request)
	{
		$OutOfStockMsg = '';
		$HalfOutOfStockMsg = '';
		if(Session::get('eusertype')=='Wholesaler' && Session::get('is_dropshipper')=='Yes') {
			$err_msg = $request["err_msg"];

			$d_start_date = $request["d_start_date"];
			$d_end_date	= $request["d_end_date"];

			if($d_start_date!='')
			{
				$d_start_date = date("Y-m-d",strtotime($d_start_date));
				$d_start_date_disp = date("Y-m-d",strtotime($d_start_date));
			}
			if($d_end_date!='')
			{
				$d_end_date	  = date("Y-m-d",strtotime($d_end_date));
				$d_end_date_disp = date("Y-m-d",strtotime($d_end_date));
			}

			if(!isset($d_start_date) && !isset($d_end_date))
			{
				$d_start_date = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-30,date('Y')));
				$d_end_date   = date('Y-m-d',mktime(0,0,0,date('m'),date('d'),date('Y')));

				$d_start_date_disp = date('Y-m-d',mktime(0,0,0,date('m'),date('d')-30,date('Y')));
				$d_end_date_disp = date('Y-m-d',mktime(0,0,0,date('m'),date('d'),date('Y')));
			}

			$this->PageData['d_start_date_disp'] = $d_start_date_disp;
			$this->PageData['d_end_date_disp'] = $d_end_date_disp;

			$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Download Tracking Numbers';
			$this->PageData['CSSFILES'] = ['myaccount.css'];
			$this->PageData['JSFILES'] = ['additional-methods.min.js', 'exportorder.js'];
			return view('myaccount.exportorder')->with($this->PageData);
		} else {
			return redirect('/myaccount.html');
		}
	}

	public function CustomerReviews(Request $request)
	{
		$this->PageData['Customer_Reviews'] = $this->Get_Customer_Reviews($request);
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Customer Reviews';
		$this->PageData['CSSFILES'] = ['pagination.css'];
		// dd($Customer_Reviews);
		return view('myaccount.customerreviews')->with($this->PageData);
	}

	/* Below functions are common functions which are used in above functions */

	public function GetGiftCertificateData($orderArray, $key, $order_detail_id, $image_url)
	{
		if(!Auth::user()){
			$GCRs = GiftCertificate::where('orders_detail_id', '=', $order_detail_id)
								->first();
		}else{
			$GCRs = GiftCertificate::where('orders_detail_id', '=', $order_detail_id)
								->where('customer_id', '=', Auth::user()->customer_id)
								->first();
		}
		if($GCRs && $GCRs->count() > 0) {
			$orderArray[$key]['RecipientName']  	= $GCRs->recipient_name;
			$orderArray[$key]['RecipientEmail'] 	= $GCRs->recipient_email;
			$orderArray[$key]['SenderName']  		= $GCRs->your_name;
			$orderArray[$key]['SenderEmail'] 		= $GCRs->your_email;
			$orderArray[$key]['Image']				= $image_url;
		}
		return $orderArray;
	}

	public function GetSystemProductPrice($aliasname="",$pricename="")
	{
		if($aliasname=="") {
			$aliasname="p";
		}

		if($pricename=="") {
			$pricename = "AS product_price";
		}

		if($pricename=="blank") {
			$pricename = "";
		}

		if(strtolower(Session::get('eusertype')) == 'wholesaler'  && !strpos($_SERVER['PHP_SELF'],config('global.CONTROL_PANEL_NAME'))) {
			$casewhenprice = " CASE
					 WHEN ".$aliasname.".current_stock > 0 THEN IF(".$aliasname.".wholesale_price > 0 ,".$aliasname.".wholesale_price, ".$aliasname.".our_price)
					 ELSE
						CASE
						WHEN ".$aliasname.".cosmo_sku!='' AND ".$aliasname.".cosmo_current_stock > 0 THEN IF(".$aliasname.".cosmo_wholesale_price > 0 ,".$aliasname.".cosmo_wholesale_price, ".$aliasname.".cosmo_our_price)
						ELSE
								CASE
								WHEN ".$aliasname.".pca_sku!='' AND ".$aliasname.".pca_current_stock > 0 THEN IF(".$aliasname.".pca_wholesale_price > 0 ,".$aliasname.".pca_wholesale_price, ".$aliasname.".pca_our_price)
									ELSE
									CASE
									WHEN ".$aliasname.".nandansons_sku!='' AND ".$aliasname.".nandansons_current_stock > 0 THEN IF(".$aliasname.".nandansons_wholesale_price > 0 ,".$aliasname.".nandansons_wholesale_price, ".$aliasname.".nandansons_our_price)
									ELSE
									IF(".$aliasname.".wholesale_price > 0 ,".$aliasname.".wholesale_price, ".$aliasname.".our_price)
								END
							 END
						   END
						END   ".$pricename."
					";
					if($pricename!='')
					$casewhenprice .= ", '0' AS sale_item";
        } else {
			$casewhenprice = " CASE
					 WHEN ".$aliasname.".current_stock > 0 THEN IF(".$aliasname.".sale_price > 0 AND ".$aliasname.".sale_price < ".$aliasname.".our_price,".$aliasname.".sale_price,".$aliasname.".our_price)
					 ELSE
						CASE
						WHEN ".$aliasname.".cosmo_sku!='' AND ".$aliasname.".cosmo_current_stock > 0 THEN ".$aliasname.".cosmo_our_price
						ELSE

							  CASE
							  WHEN ".$aliasname.".pca_sku!='' AND ".$aliasname.".pca_current_stock > 0 THEN ".$aliasname.".pca_our_price
							  ELSE
							  CASE
							  WHEN ".$aliasname.".nandansons_sku!='' AND ".$aliasname.".nandansons_current_stock > 0 THEN ".$aliasname.".nandansons_our_price
							  ELSE
							  IF(".$aliasname.".sale_price > 0 AND ".$aliasname.".sale_price < ".$aliasname.".our_price,".$aliasname.".sale_price,".$aliasname.".our_price)
							 End
						   End
					    END
					END   ".$pricename."
					";

					if($pricename!='')
					$casewhenprice .= ", IF(".$aliasname.".sale_price > 0 ,'1','0') AS sale_item";
        }
        return $casewhenprice;
	}

	public function Get_Customer_Reviews($request)
	{
		$Reviews = ProductsReview::select('products_id', 'star_rate', 'user_review', 'first_name', 'date')
								->where('approved', '=', 'Yes')
								->orderBy('date', 'DESC')
								->paginate(10);
		// dd($Reviews);
		//$rcount = count($Reviews);
		$rcount = $Reviews->count();
		$Reviews_res= array();

		if($rcount > 0) {

			for($i=0; $i<$rcount; $i++)
			{
				$Product_res = Products::select('pu_products.sku', 'pu_products.products_id', 'pu_products.product_name', 'pu_products_category.category_id', 'pu_manufacture.vmanufacture', )
										->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
										->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
										->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
										->where('pu_products.products_id', '=', $Reviews[$i]['products_id'])
										->first();

	 			//echo "<pre>"; print_r($Product_res); exit;
	 			if($Product_res && $Product_res->count()> 0)
	 			{
	 			$product_url = $this->getProductRewriteURL($Product_res->products_id, $Product_res->product_name, $Product_res->category_id, $Product_res->vmanufacture, $Product_res->sku);

				$Reviews[$i]['product_name'] = $Product_res->product_name;
				$Reviews[$i]['product_star'] = $Reviews[$i]['star_rate'];
				$Reviews[$i]['user_review'] = $Reviews[$i]['user_review'];
				$Reviews[$i]['product_url'] = $product_url;
				$Reviews[$i]['first_name'] = $Reviews[$i]['first_name'];
				$Reviews[$i]['date'] = date("m/d/Y", strtotime($Reviews[$i]['date']));
				}
			}
		}
		return $Reviews;
	}
	public function OrderTracking(Request $request)
	{
		$this->PageData['tracking_no'] = $request['id'];
		return view('myaccount.tracking')->with($this->PageData);
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

			// dd($rid);
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
						// dd($rid, $shipping_mode_id);
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}

			if ($rid && $rid->count() > 0 )
			{
             	return (int) $ShippingMethodRS[0]['shipping_mode_id'];
			}
			else
			{
				return false;
			}
		}else{
			return false;
		}
	}

	public function CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id,$Subtotal=0,$TotalQuantity=0)
	{

		$AllDiscount = $this->GetAllDiscounts();
		$totalitem = $TotalQuantity;
		$subTotal = Session::get('ShoppingCart.SubTotal') - $AllDiscount['TotalDiscount'];

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
				if(empty($totalitem) || $totalitem <= 0)
				{
					$totalitem = Session::get('ShoppingCart.TotalItemInCart') ;
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
			/*if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($shipping_mode_id,Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
			{
				$temp_ShippingCharge = 0;
			}
			if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $shipping_mode_id == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
			{
				$temp_ShippingCharge = 0;
			}*/
			########### END CODE FOR CALCULATE PROP SHIP CHARGE###########
			return $temp_ShippingCharge."###".$days;
		}
	}

	public function getShipmentEstimateDate($days)
	{
		if($days==0)
		{
			$estimateShipDate='';
		} else {
			$sdate = date('Y-m-d');
			$edate = date('Y-m-d', strtotime("+" . $days . "days"));
			$satsun_cnt = $this->countWeekendDays($sdate, $edate);
			$holiday_day_arr = ShippingHoliday::whereBetween('holiday_date',[$sdate,$edate])->where('holiday_status','=','1')->where('holiday_date','!=',date("Y-m-d"))->get();
			$holiday_day = $holiday_day_arr->count();
			$exact_shipday = $days + $satsun_cnt + $holiday_day;
			$approx_shipdate = date('Y-m-d', strtotime("+" . $exact_shipday . "days"));
			$extradays = '0';
			$daynew = $this->checkday($approx_shipdate);
			if ($daynew == 'saturday')
			{
				$extradays = '2';
			} else if ($daynew == 'sunday'){
				$extradays = '1';
			}
			$days = $exact_shipday + $extradays;
			$dt_date =  date('M d', strtotime("+".$days. "days"));
			return $estimateShipDate = 'Estimated Delivery on or before <b>'.$dt_date.'</b>';
		}
		return $estimateShipDate;
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
		$normalized_weekday = strtolower($weekday);
		if (($normalized_weekday == "saturday") || ($normalized_weekday == "sunday")) {
			return $normalized_weekday;
		}
	}

	public function getSpecialPricePercentandValue($qty)
	{
		$per = '';
		$val = '';
		$db_recs = MarkupPrices::all();
		for($i=0;$i<count($db_recs);$i++)
		{
			if($db_recs[$i]["markup_value"]!="" && $db_recs[$i]["markup_value"]!="0" && $db_recs[$i]["markup_percent"]!="" && $db_recs[$i]["markup_percent"]!="0")
			{
				$mvalu = explode("-",$db_recs[$i]["markup_value"]);
				$mvalcount = count($mvalu);
				if($mvalcount>1)
				{
					if($qty >= $mvalu[0] && $qty <= $mvalu[1])
					{
						$per = $db_recs[$i]["markup_percent"];
						$val = $db_recs[$i]["markup_value"];
					}
				}
				else
				{
					if($qty > $mvalu[0])
					{
						$per = $db_recs[$i]["markup_percent"];
						$val = $db_recs[$i]["markup_value"];
					}
				}
			}

			if($per != '')
			{
				break;
			}
		}
		return $per."#".$val;
	}

	public function searchCustomer(Request $request)
	{
		$term = $request->get('term');
		$store_id = auth('store')->user()->store_id;

		//$customers = Customer::where('email','=',$request['email'])->where('registration_type','=','M')->get();
		$customers = Customer::where('eusertype','Retailer')->where('is_deleted', 'No')
				->where(function ($query) use ($term) {
					$query->where('first_name', 'LIKE', "%{$term}%")
						->orWhere(DB::raw("CONCAT(first_name,' ',last_name)"), 'LIKE', "%{$term}%")
						->orWhere('last_name', 'LIKE', "%{$term}%")
						->orWhere('phone', 'LIKE', "%{$term}%")
						->orWhere('email', 'LIKE', "%{$term}%");
				})
				->limit(50)
				->get();

		$result = [];

		foreach ($customers as $customer) {

			$result[] = [
				'name' => $customer->first_name.' '.$customer->last_name,
				'first_name' => $customer->first_name,
				'last_name' => $customer->last_name,
				'email' => $customer->email,
				'phone' => $customer->phone,
				'city' => $customer->city,
				'state' => $customer->state,
				'zip' => $customer->zip
			];
		}
		return response()->json($result);
	}

}
?>
