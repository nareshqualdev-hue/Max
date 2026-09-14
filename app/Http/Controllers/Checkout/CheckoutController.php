<?php

namespace App\Http\Controllers\Checkout;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\Checkout\CheckoutService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Traits\AfterpayTrait;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Customer;
use App\Models\NewsLetter;
use App\Services\Checkout\ShippingService;
use App\Services\Payment\PaypalService;

class CheckoutController extends Controller
{
    use AfterpayTrait;
    protected CheckoutService $checkoutService;
    protected StripePaymentService $StripePaymentService;
    protected ShippingService $ShippingService;
    protected PaypalService $PaypalService;

    public function __construct(
        CheckoutService $checkoutService,
        StripePaymentService $StripePaymentService,
        ShippingService $ShippingService,
        PaypalService $PaypalService
    )
    {
        $this->checkoutService = $checkoutService;
        $this->StripePaymentService = $StripePaymentService;
        $this->ShippingService = $ShippingService;
        $this->PaypalService = $PaypalService;
    }

    /**
     * One Page Checkout
     */
    public function index(Request $request)
    {

        $result = $this->checkoutService->prepareCheckout($request);

        //echo "<pre>"; print_r(Session::get('ShoppingCart.ShippingAddress')); exit;
        /*
         * Existing CheckoutPage() returns:
         * checkout.index
         *
         * Service prepares PageData only.
         */
        if (isset($result['redirect'])) {
            return $result['redirect'];
        }

        $result['data']['token_js_url'] = "https://portal.sandbox.afterpay.com/afterpay.js";
        return view('newcheckout.checkout-page')->with($result['data']);
    }

    /**
     * Return Stripe public configuration.
     */
    public function stripeConfig()
    {
        return response()->json(
            $this->StripePaymentService->config()
        );
    }

     /**
     * Laravel receives only:
     *
     * payment_method_id
     *
     * No card number.
     * No CVC.
     * No expiry.
     */
    public function stripePay(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string|max:255',
        ]);

        try {
            $result =
                $this->StripePaymentService->pay(
                    $request->payment_method_id
                );
            return response()->json(
                $result
            );
        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify PaymentIntent after
     * Stripe.js authentication.
     */
    public function stripeVerify(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string|max:255',
        ]);

        try {

            $result =
                $this->StripePaymentService->verify(
                    $request->payment_intent_id
                );
            return response()->json(
                $result
            );

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,

                'message' =>
                    $e->getMessage(),

            ], 422);
        }
    }

    /**
     * Return URL for Stripe authentication.
     *
     * Usually Stripe.js handles this directly,
     * but keeping this route gives us a safe fallback.
     */
    public function stripeReturn(Request $request)
    {
        $paymentIntentId =
            $request->get(
                'payment_intent'
            );

        if (!$paymentIntentId) {
            return redirect()
                ->route('checkout');
        }

        try {

            $result =
                $this->StripePaymentService->verify(
                    $paymentIntentId
                );

            if (
                $result['success']
                ?? false
            ) {

                /*
                 * Replace this with your actual
                 * checkout success/order flow.
                 */
                return redirect()
                    ->route(
                        'checkout'
                    )
                    ->with(
                        'payment_success',
                        true
                    );
            }

        } catch (Throwable $e) {

            report($e);
        }

        return redirect()
            ->route('checkout')
            ->with(
                'payment_error',
                'Payment could not be completed.'
            );
    }

    public function PreparePaypalCart(Request $request)
    {
        $result = $this->checkoutService->prepareCheckout($request);

        $CheckoutData = $result['data']['checkout']['totals'];
        $TotalDiscount = $CheckoutData['TotalDiscount'];
        $NetTotal = $CheckoutData['NetTotal'];

        $OrderSubTotal 	 =  Session::get('ShoppingCart.SubTotal');
		$GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
		$GiftValue = 0;
		if ($GiftCouponInfo && count($GiftCouponInfo) > 0) {
			$GiftValue = $GiftCouponInfo['Value'];
		}

        $OrderSubTotal = (float)$CheckoutData['SubTotal'];
        $AllCharges = $CheckoutData['Charges'];
        $Tax = 0;
        if(isset($AllCharges['Tax']['charge']) && $AllCharges['Tax']['charge'] > 0)
        {
            $Tax = $AllCharges['Tax']['charge'];
        }
        $ShippingCharge = ($AllCharges['ShippingCharge']['charge']??0);
        $ShippingInsurance= ((float)$AllCharges['ShippingInsurance']['charge']??0);

		$ShopCart = Session::get('ShoppingCart.Cart');
		$ItemsArr = array();
		$data = array();
		if(is_array($ShopCart) && $ShopCart)
        {
			foreach ($ShopCart as $key => $CartItem)
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
			if ($OrderSubTotal > 0)
            {
				$AmountArr["currency_code"] = "USD";
				$AmountArr["value"] = NumberFormat($NetTotal);
				$AmountArr["breakdown"]["item_total"]["currency_code"] = "USD";
				$AmountArr["breakdown"]["item_total"]["value"] = $OrderSubTotal;

				$ShippingSignature = 0;
                if(isset($AllCharges['GiftWrappingCharge']))
                {
                    $ShippingSignature = (float)$AllCharges['GiftWrappingCharge']['charge']??0;
                }
                if(isset($AllCharges['ShippingSignature']))
                {
                    $ShippingSignature+= (float)$AllCharges['ShippingSignature']['charge']??0;
                }

                $AmountArr['breakdown']['shipping']['currency_code'] = "USD";
                $AmountArr['breakdown']['shipping']['value'] = $ShippingCharge;

				if ($ShippingInsurance > 0) {
					$AmountArr['breakdown']['insurance']['currency_code'] = "USD";
					$AmountArr['breakdown']['insurance']['value'] = $ShippingInsurance;
				}
				if ($ShippingSignature > 0) {
					$AmountArr['breakdown']['handling']['currency_code'] = "USD";
					$AmountArr['breakdown']['handling']['value'] = NumberFormat($ShippingSignature);
				}
				if ($Tax > 0) {
					$AmountArr['breakdown']['tax_total']['currency_code'] = "USD";
					$AmountArr['breakdown']['tax_total']['value'] = NumberFormat($Tax);
				}

				if (!empty($TotalDiscount) &&  $TotalDiscount > 0) {
					$AmountArr["breakdown"]['discount']['currency_code'] = "USD";
					$AmountArr["breakdown"]['discount']['value'] = NumberFormat($TotalDiscount);
				}
			}

			$data["purchase_units"][0]["invoice_id"] = "INV" . time() . '-' . mt_rand(1000, 9999);
			$data["purchase_units"][0]["amount"] = $AmountArr;
			$data["purchase_units"][0]["items"] = $ItemsArr;

            if($request->btnAction == 'LastStep')
            {
                $data["application_context"]["shipping_preference"] = "SET_PROVIDED_ADDRESS";
                //$data["application_context"] = "";
                $shippingAddressArr['name']['full_name'] = '';
                $shippingAddressArr['address']['address_line_1'] = '';
                $shippingAddressArr['address']['address_line_2'] = '';
                $shippingAddressArr['address']['admin_area_2'] = ''; //city
                $shippingAddressArr['address']['admin_area_1'] = ''; //state
                $shippingAddressArr['address']['postal_code'] = '';
                $shippingAddressArr['address']['country_code'] = '';
                $shipping_nm = '';

                if (Session::has('ShoppingCart.BillingAddress.city') && Session::get('ShoppingCart.BillingAddress.city') != '') {
                    $shippingAddressArr['address']['admin_area_2'] = Session::get('ShoppingCart.BillingAddress.city');
                }
                if (Session::has('ShoppingCart.BillingAddress.state') && Session::get('ShoppingCart.BillingAddress.state') != '') {
                    $shippingAddressArr['address']['admin_area_1'] = Session::get('ShoppingCart.BillingAddress.state');
                }
                if (Session::has('ShoppingCart.BillingAddress.zip') && Session::get('ShoppingCart.BillingAddress.zip') != '') {
                    $shippingAddressArr['address']['postal_code'] = Session::get('ShoppingCart.BillingAddress.zip');
                }
                if (Session::has('ShoppingCart.BillingAddress.country') && Session::get('ShoppingCart.BillingAddress.country') != '') {
                    $shippingAddressArr['address']['country_code'] = Session::get('ShoppingCart.BillingAddress.country');
                }
                if (Session::has('ShoppingCart.BillingAddress.address1') && Session::get('ShoppingCart.BillingAddress.address1') != '') {
                    $shippingAddressArr['address']['address_line_1'] = Session::get('ShoppingCart.BillingAddress.address1');
                }
                if (Session::has('ShoppingCart.BillingAddress.address2') && Session::get('ShoppingCart.BillingAddress.address2') != '') {
                    $shippingAddressArr['address']['address_line_2'] = Session::get('ShoppingCart.BillingAddress.address2');
                }
                if (Session::has('ShoppingCart.BillingAddress.first_name') && Session::get('ShoppingCart.BillingAddress.first_name') != '') {
                    $shipping_nm = Session::get('ShoppingCart.BillingAddress.first_name');
                }
                if (Session::has('ShoppingCart.BillingAddress.last_name') && Session::get('ShoppingCart.BillingAddress.last_name') != '') {
                    if ($shipping_nm != '') {
                        $shipping_nm .= " ";
                    }
                    $shipping_nm .= Session::get('ShoppingCart.BillingAddress.last_name');
                }
                $shippingAddressArr['name']['full_name'] = $shipping_nm;
                $data["purchase_units"][0]["shipping"] = $shippingAddressArr;
            }
		}
		return response()->json($data);
    }
    public function CheckoutPaypalUpdate(Request $request)
    {
        $currentAddress = Session::get(
            'ShoppingCart.ShippingAddress',
            []
        );

        if (!is_array($currentAddress)) {
            $currentAddress = [];
        }

        $currentAddress['city'] = $request->city;
        $currentAddress['state'] = $request->state;
        $currentAddress['zip'] = $request->zip;
        $currentAddress['country'] = $request->country;

        Session::put('ShoppingCart.ShippingAddress',$currentAddress);

        if($request->action == 'setShippingMethod')
        {
            //Session::put('ShoppingCart.Shipping.ShippingMethodID',$request->shippingMethod);
            $this->ShippingService->setShippingMethod($request->shippingMethod,$currentAddress);
        }
        $result = $this->checkoutService->prepareCheckout($request);

        $CheckoutData = $result['data']['checkout']['totals'];
        $Discounts = $CheckoutData['TotalDiscount'];
        $NetTotal = $CheckoutData['NetTotal'];

        $ShopCart = Session::get('ShoppingCart.Cart');
        $ItemsArr = array();
        $ItemsArr['op'] = 'replace';
        $ItemsArr['path'] = "/purchase_units/@reference_id=='default'/items";
        $ItemsDetails = [];
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
        $data['value']['value'] = NumberFormat($NetTotal);

        $SubTotal = (float)$CheckoutData['SubTotal'];
        $AllCharges = $CheckoutData['Charges'];
        $Tax=0;
        if(isset($AllCharges['Tax']['charge']) && $AllCharges['Tax']['charge'] > 0)
        {
            $Tax = (float)NumberFormat($AllCharges['Tax']['charge'])??0;
        }

        $ShippingCharge = $AllCharges['ShippingCharge']['charge']??0;
        $ShippingInsurance= (float)$AllCharges['ShippingInsurance']['charge']??0;
        $ShippingSignature = 0;
        if(isset($AllCharges['GiftWrappingCharge']))
        {
            $ShippingSignature = (float)$AllCharges['GiftWrappingCharge']['charge']??0;
        }
        if(isset($AllCharges['ShippingSignature']))
        {
            $ShippingSignature+= (float)$AllCharges['ShippingSignature']['charge']??0;
        }
        $address = $result['data']['ShippingAddress'];
        $ShippingInfo = $this->ShippingService->getAvailableMethods($address);

        $shipping_mode_tmp_arr = [];
        if(isset($ShippingInfo['shipping_methods']) && count($ShippingInfo['shipping_methods'])>0)
        {
            foreach($ShippingInfo['shipping_methods'] as $ShipMethod)
            {
                $MtVal = false;
                if(Session::get('ShoppingCart.Shipping.ShippingMethodID') == $ShipMethod['shipping_mode_id'])
                {
                    $MtVal = true;
                }
                $amount_arr['value'] = (string)$ShipMethod['chargewithoutformat']; //round($SMethod['chargewithoutformat']*100);
                $amount_arr['currency_code'] = 'USD';
                $shipping_mode_tmp_arr[] = array(
                    'id' => (string)$ShipMethod['shipping_mode_id'],
                    'label' => strip_tags($ShipMethod['method_name']) . "-" . $ShipMethod['display_date'],
                    'selected' => $MtVal,
                    'type' => 'SHIPPING',
                    'amount' => $amount_arr,
                );
            }
        }

        $data['value']['breakdown']["item_total"]["currency_code"] = "USD";
        $data['value']['breakdown']["item_total"]["value"] =  NumberFormat($SubTotal);
        if($Tax>0)
        {
            $data['value']['breakdown']['tax_total']['currency_code'] = "USD";
            $data['value']['breakdown']['tax_total']['value'] = $Tax;
        }
        //dd($ShippingCharge);
        //if($ShippingCharge>0){
            $data['value']['breakdown']['shipping']['currency_code'] = "USD";
            $data['value']['breakdown']['shipping']['value'] = $ShippingCharge;
        //}
        if($ShippingInsurance>0)
        {
            $data['value']['breakdown']['insurance']['currency_code'] = "USD";
            $data['value']['breakdown']['insurance']['value'] = $ShippingInsurance;
        }
        if(!empty($ShippingSignature) &&  $ShippingSignature > 0)
        {
            $data['value']['breakdown']['handling']['currency_code'] = "USD";
            $data['value']['breakdown']['handling']['value'] = NumberFormat($ShippingSignature);
        }
        if(!empty($Discounts) &&  $Discounts>0)
        {
            $data['value']['breakdown']['discount']['currency_code'] = "USD";
            $data['value']['breakdown']['discount']['value'] = NumberFormat($Discounts);
        }
        //$data1[] = $shipping;
        $ShippingOption['op'] = 'add';
        $ShippingOption['path'] ="/purchase_units/@reference_id=='default'/shipping/options";
        $ShippingOption['value']= $shipping_mode_tmp_arr;
        $data1[] = $data;
        $data1[] = $ItemsArr;
        $data1[] = $ShippingOption;

        $PaypalOrderId = $request->orderID;
        $ShipData = [
            'PaypalOrderId' => $request->orderID,
            'data1'         => $data1,
            'payer_email'   => $request->payer_email??'',
            'city'          => $request->city,
            'state'         => $request->state,
            'country'       => $request->country,
            'zip'           => $request->zip
        ];
        $response = $this->PaypalService->UpdateDetails($ShipData);
        return $response;

        /*
        $myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." Before Checkout PayPal Update :".json_encode($request->rtnm)."\n";
			$stringData .= date("m/d/Y H:i:s")." Before Checkout PayPal Update :".json_encode($request)."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

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
        */

    }

    public function PreparePaypalOrder(Request $request)
    {
        $myFile = env('LOG_BASE_PATH') . 'Logs/ApplePayOtherLog.txt';
		if (fopen($myFile, 'a+')) {
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s") . " Before PayPal Order Insert :" . json_encode($request->rtnm) . "\n";
			$stringData .= date("m/d/Y H:i:s") . " Before PayPal Order Insert :" . json_encode($request->all()) . "\n";
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

		if (isset($request->payer_address1) && $request->payer_address1 != '') {
			$payer_address1 = $request->payer_address1;
		}

		if (isset($request->payer_state) && $request->payer_state != '') {
			$payer_state = $request->payer_state;
		}
		if (isset($request->payer_city) && $request->payer_city != '') {
			$payer_city = $request->payer_city;
		}
		if (isset($request->payer_country) && $request->payer_country != '') {
			$payer_country = $request->payer_country;
		}
		if (isset($request->payer_postcode) && $request->payer_postcode != '') {
			$payer_postcode = $request->payer_postcode;
		}
		if (isset($request->order_details) && $request->order_details != '') {
			$order_details = $request->order_details;
		}
		if (isset($request->order_invalid) && $request->order_invalid != "") {
			$order_invalid = $request->order_invalid;
		}

		$fname = "";
		$lname = "";
		$ship_fname = "";
		$ship_lname = "";
		if ($order_details != '') {
			if (strpos($order_details, "---") !== "") {
				$ord_data = explode("---", $order_details);
				$ord = json_decode($ord_data[0], true);
				if (isset($ord['payer']['name']['given_name']) && $ord['payer']['name']['given_name'] != '') {
					$fname = $ord['payer']['name']['given_name'];
				}
				if (isset($ord['payer']['name']['surname']) && $ord['payer']['name']['surname'] != '') {
					$lname = $ord['payer']['name']['surname'];
				}
			} else {
				$ord = json_decode($order_details, true);
				if (isset($ord['payer']['name']['given_name']) && $ord['payer']['name']['given_name'] != '') {
					$fname = $ord['payer']['name']['given_name'];
				}
				if (isset($ord['payer']['name']['surname']) && $ord['payer']['name']['surname'] != '') {
					$lname = $ord['payer']['name']['surname'];
				}
				if (isset($ord['purchase_units'][0]['shipping']['name']) && $ord['purchase_units'][0]['shipping']['name'] != '') {
					$ship_name = $ord['purchase_units'][0]['shipping']['name'];
					$ship_name_arr = explode(" ", $ship_name);
					if (isset($ship_name_arr[0]) && $ship_name_arr[0] != '') {
						$ship_fname =  $ship_name_arr[0];
					}
					if (isset($ship_name_arr[1]) && $ship_name_arr[1] != '') {
						$ship_lname = $ship_name_arr[1];
					}
				}
			}
		}

		if (Session::has('Paypalcountry') && Session::get('Paypalcountry') != '') {
			$payer_country = Session::get('Paypalcountry');
		}

		if (Session::has('Paypalcity') && Session::get('Paypalcity') != '') {
			$payer_city = Session::get('Paypalcity');
		}

		if (Session::has('Paypalstate') && Session::get('Paypalstate') != '') {
			$payer_state = Session::get('Paypalstate');
		}

		if ((Session::has('Paypalzipcode')) && Session::get('Paypalzipcode') != '') {
			$payer_postcode = Session::get('Paypalzipcode');
		}

		$chk_paypal_email_data = '';
        if (Session::has('PaypalEmail') && Session::get('PaypalEmail') != '') {
            $CustomerEmail  =  Session::get('PaypalEmail');
            $chk_paypal_email_data .= date("m/d/Y H:i:s") . " PayPal Session Email :" . $CustomerEmail . "\n";
        } else {
            $CustomerEmail  =  isset($request->payer_email) ? $request->payer_email : '';
            $chk_paypal_email_data .= date("m/d/Y H:i:s") . " PayPal Payer Email :" . $CustomerEmail . "\n";
        }

        $myFile = env('LOG_BASE_PATH') . 'Logs/ApplePayOtherLog.txt';
        if (fopen($myFile, 'a+')) {
            $fh = fopen($myFile, 'a+');
            $stringData = $chk_paypal_email_data;
            fwrite($fh, $stringData);
            fclose($fh);
        }

        if (isset($CustomerEmail) && $CustomerEmail != '') {
            if (checkBlockedUser($CustomerEmail, 0, 'PayPalGuest') == true) {
                Session::flash('PlaceOrderError', config('message.Register.Blocked'));
                return "Blocked";
            }
        }

        if (isset($CustomerEmail) && $CustomerEmail != '') {
            $payer_email = $CustomerEmail;
        }

        $newrequest = [
            'country' 			=> $payer_country,
            'first_name' 			=> $fname,
            'last_name'			=> $lname,
            'address1' 		=> $payer_address1,
            'address2' 		=> '',
            'city' 			=> $payer_city,
            'state' 			=> $payer_state,
            'zip' 				=> $payer_postcode,
            'phone' 			=> '',
            'email' 			=> $payer_email,
            'cemail' 			=> $payer_email,
            'sameasbill' 			=> 'No',
            'other_state'		=> $payer_country != 'US' ? $payer_state : ''
        ];

        Session::put('ShoppingCart.BillingAddress',$newrequest);

        $newrequestShip = [
            'country' 			=> $payer_country, //((Session::has('Paypalcountry')) ? Session::get('Paypalcountry') : ''),
            'first_name' 			=> $ship_fname, //'',
            'last_name'			=> $ship_lname, //'',
            'company'			=> '',
            'address1' 		=> $payer_address1, //'',
            'address2' 		=> '',
            'city' 			=> $payer_city,  //((Session::has('Paypalcity')) ? Session::get('Paypalcity') : ''),
            'state' 			=> $payer_state, //((Session::has('Paypalstate')) ? Session::get('Paypalstate') : ''),
            'zip' 				=> $payer_postcode, //((Session::has('Paypalzipcode')) ? Session::get('Paypalzipcode') : ''),
            'phone' 			=> '',
            'email' 			=> $payer_email, //$CustomerEmail,
            'sameasbill' 			=> 'No',
            'other_state'			=> $payer_country != 'US' ? $payer_state : ''
        ];

        Session::put('ShoppingCart.ShippingAddress',$newrequestShip);

        addLog("setPaymentDetailStart");
        $temp = [];
        $temp['Payment_Type']     	=  "PAYMENT_PAYPALEC";
        $temp['Payment_Method']   	=  "PAYMENT_PAYPALEC";
        $log['payment_details'] = $temp;
        addLog("setPaymentDetail",$log);
        Session::put('ShoppingCart.Payment_Detail',$temp);
        //$OrderVal = $this->ApplePayPlaceOrder($request);
    }
    public function MaxOrder(Request $request)
    {
        $PaymentType = $request->payment_type??'';
        $PaymentMethod = $request->payment_method??'';
        $PaypalOrder = 'No';

        if($request->has('isPaypalOrder') && $request->isPaypalOrder == 'Yes')
        {
            $PaypalOrder = 'Yes';
            $this->PreparePaypalOrder($request);
            if (Session::has('ShoppingCart.Payment_Detail'))
            {
                $PaymentDetail = Session::get('ShoppingCart.Payment_Detail');
                $PaymentType = $PaymentDetail['Payment_Type'];
                $PaymentMethod = "Paypal Express Checkout";
            }
        }
        $result = $this->checkoutService->prepareCheckout($request);

        if (isset($result['redirect']))
        {
            return $result['redirect'];
        }

        if(!Auth::user())
        {
            $this->SetGuestCustomer($request,$PaypalOrder);
        }else{
            $this->CustomerInfoUpdate();
        }

        $this->checkoutService->refresh('checkout');

        $CheckoutData = $result['data']['checkout']['totals'];
        $customer_id = (int)Session::get('sess_icustomerid');
        $SubTotal = (float)$CheckoutData['SubTotal'];
        $AllCharges = $CheckoutData['Charges'];
        $Tax = 0;
        if(isset($AllCharges['Tax']['charge']))
            $Tax = (float)$AllCharges['Tax']['charge']??0;

        $GiftWrappingCharge = 0;
        if(isset($AllCharges['GiftWrappingCharge']))
        {
            $GiftWrappingCharge = (float)$AllCharges['GiftWrappingCharge']['charge']??0;
        }
        $GCCode = $result['data']['checkout']['giftCertificate']['code']??'';
        $GCAmount = $result['data']['checkout']['giftCertificate']['value']??0;

        $Discounts = $CheckoutData['Discounts'];
        $NetTotal = $CheckoutData['NetTotal'];

        $CartAttributes = $result['data']['checkout']['cartAttributes'];

        $currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');
        if(Session::has('etype') && Session::get('etype') == 'M')
			$checkout_type = 'M';
		else
			$checkout_type = 'G';

        $w_user_type = Session::get('eusertype')??'Retailer';
		$w_ilevelid  = (Session::has('ilevelid'))?Session::get('ilevelid'):0;

        $Shipping = $result['data']['ShippingAddress'];
        $Billing = Session::get('ShoppingCart.BillingAddress');

        $onlyGCPurchased = $result['data']['checkout']['onlyGCPurchased']??0;
        $free_gift = Session::get('ShoppingCart.FreeGift')??'';
        $gift_from = Session::get('ShoppingCart.GiftFrom')??'';
        $gift_to   = Session::get('ShoppingCart.GiftTo')??'';
        $gift_message_customer = Session::get('ShoppingCart.GiftMessageCustomer')??'';
        $is_dropship_order = 'No';
        $ShippingSignatureFlag = 'No';
        $EstimatedDeliveryDate = Session::get("ShoppingCart.EstimatedDeliveryDate")??'';
        $ShippingInfo = Session::get('ShoppingCart.Shipping');
        $ShipMethodCharge = 0;
        $ShippingSignature = 0;
        $ShippingInsurance = 0;
        $fullShippingname = "";
        $is_maxtwoday = "No";
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
			$ShipMethodCharge = (float)($AllCharges['ShippingCharge']['charge']??0);
			$ShippingSignature = (float)($AllCharges['ShippingSignature']['charge']??0);
            $ShippingInsurance = (float)($AllCharges['ShippingInsurance']['charge']??0);
		}

        $merge_note = (Session::has('ShoppingCart.merge_note')) ? Session::get('ShoppingCart.merge_note') : "";

        $CouponDetails = Session::get('ShoppingCart.Procoupon')??[];

        $OrderInsert = array (
            'customer_id'		=> $customer_id,
            'sub_total' 		=> $SubTotal,
            'shipping_amt' 		=> $ShipMethodCharge,
            'tax' 				=> $Tax,
            'gift_charge' 		=> $GiftWrappingCharge,
            'gift_message' 		=> '',
            'is_gift_order'		=> 'No',
            'handling_charge' 	=> '0.00',
            'wire_discount' 	=> '0.00',
            'auto_discount' 	=> (float)($Discounts['AutoDiscount'] ?? 0),
            'quantity_discount'	=> (float)($Discounts['QuantityDiscount'] ?? 0),
            'reward_discount'	=> (float)($Discounts['YotpoRewardDiscount'] ?? 0),
            'coupon_amount' 	=> (float)($Discounts['CouponDiscount'] ?? 0),
            'coupon_id' 		=> ($CouponDetails['CouponID']??''),
            'Second_coupon_id'	=> $yotporewardcode??'',
            'coupon_code' 		=> ($CouponDetails['CouponCode']??''),
            'gc_amount' 		=> $GCAmount,
            'gc_code' 			=> $GCCode,
            'refer_id'			=> $referDiscountId??0,
            'refer_amount' 		=> $AutoReferDiscount??0,
            'order_total' 		=> (float)$NetTotal,
            'shipinfo' 			=> (Session::get('ShoppingCart.Shipping.ShippingMethodName')??''),
            'payment_type' 		=> $PaymentType,
            'payment_method' 	=> $PaymentMethod,
            'pay_status' 		=> 'Unpaid',
            'ccinfo' 			=> "",
            'customer_comment' 	=> "",
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
            'gift_from'				=> $gift_from,
            'gift_to'				=> $gift_to,
            'gift_message_customer'	=> $gift_message_customer,
            'cust_current_credit_limit' => $cust_current_credit_limit??0,
            'apply_credit'          => (float)($Discounts['CreditDiscount']??0),
            'remaining_credit'      => $remaining_credit??0,
            'use_credit_limit'      => $use_credit_limit??0,
            'is_dropship_order'     => $is_dropship_order,
            'shipping_signature'	 => $ShippingSignature,
            'is_shipping_signature' => $ShippingSignatureFlag,
            'Is_GiftCertificatPurchase' => $GCAmount,
            'EstimatedDeliveryDate' 	=> $EstimatedDeliveryDate,
            'fullshipping_info'		=> 	$fullShippingname,
            'merge_note'		=> 	$merge_note,
            'bogo_discount'	=> (float)($Discounts['DogoDiscount']??0),
            'is_maxtwoday'	=> $is_maxtwoday,
            'route_shipping_insurance_charge' => $ShippingInsurance,
            'vLang_flag' => Session::get('ShoppingCart.YotpoFreeGiftCoupon'),
            'paymentintentid' => "",
            'payment_gateway_response' => Session::get("PayMethodRes")??''
		);
        $PlaceOrder = Order::create($OrderInsert);
		$OrderID = $PlaceOrder->orders_id;
		Session::put('ShoppingCart.OrderID',$OrderID);

        if($OrderID != "")
		{
			$CurrOrder = Order::find($OrderID);
			$updateOrder = array ('orders_no'	 => "OR".$OrderID );
			$CurrOrder->update($updateOrder);
		}

        $tempCart = Session::get('ShoppingCart.Cart');
		$cnt_row  = count($tempCart);

		$IsVender = "No";
		$IsAmazOR = 'No';
		// if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAMAZON')
		// {
		// 	$IsAmazOR = 'Yes';
		// }

		$IsPerfumePWVendor = "No";

		$is_order_update = 'N';
        $TaxValueNew = $Tax;
        if($is_order_update == 'N')
        {
            $couponCode = ($CouponDetails['CouponCode']??'');
            $CouponDiscount = (float)($Discounts['CouponDiscount'] ?? 0);
            $AutoDiscount = (float)($Discounts['AutoDiscount'] ?? 0);
            $QuanityDiscount = (float)($Discounts['QuanityDiscount'] ?? 0);
            $YotpoRewardDiscount = (float)($Discounts['YotpoRewardDiscount'] ?? 0);
            $DogoDiscount = (float)($Discounts['DogoDiscount'] ?? 0);
            $apply_credit = (float)($Discounts['CreditDiscount']??0);

            $TotalTxShipping = 0;
            $couponPercentage = 0;
            if (isset($couponCode) && $couponCode != '' && $CouponDiscount > 0 && Session::has('ShoppingCart.CountShipTax') && Session::get('ShoppingCart.CountShipTax')=='1' && Session::has('ShoppingCart.CouponPercentage'))
            {
                $couponPercentage = session::get('ShoppingCart.CouponPercentage');
            }
            for($i=0; $i<$cnt_row; $i++)
            {
                $ItemWiseTaxVal = 0;
                if($TaxValueNew > 0)
                {
                    $ItemWiseTaxVal = (($tempCart[$i]['TotPrice'] * $TaxValueNew) / $SubTotal);
                }
                $allocatedDiscount  = 0;
                if (isset($couponCode) && $couponCode != '' && $CouponDiscount > 0 && Session::has('ShoppingCart.CountShipTax') &&  Session::get('ShoppingCart.CountShipTax')=='1' && Session::has('ShoppingCart.CouponPercentage'))
                {

                $ShippingChargeItemWise = ($ShipMethodCharge > 0  && $SubTotal > 0)
                    ? NumberFormat(($tempCart[$i]["TotPrice"] * $ShipMethodCharge) / $SubTotal)
                    : 0;
                $ItemWiseTaxShipping = NumberFormat($ItemWiseTaxVal + $ShippingChargeItemWise);
                $allocatedDiscount      = $ItemWiseTaxShipping * ($couponPercentage / 100);

                $allocatedDiscount      = NumberFormat($allocatedDiscount);
                }
                $tempCart[$i]['TaxShippingItemWiseDiscount'] = $allocatedDiscount;

                if($CouponDiscount <= 0)
                {
                    $tempCart[$i]['CouponDisItemWiseDiscout'] = 0;
                }
                if($AutoDiscount <= 0)
                {
                    $tempCart[$i]['AutoItemWiseDiscout'] = 0;
                }
                if($QuanityDiscount <= 0)
                {
                    $tempCart[$i]['QuantityItemWiseDiscout'] = 0;
                }
                if($YotpoRewardDiscount <= 0)
                {
                    $tempCart[$i]['RewardItemWiseDiscout'] = 0;
                }
                if($DogoDiscount <= 0)
                {
                    $tempCart[$i]['BogoItemWiseDiscout'] = 0;
                }

                $tempCart[$i]['GiftCertificateItemWiseDiscout'] = 0;
                // if(empty($this->GetAllDiscounts('GiftCoupon')) || $this->GetAllDiscounts('GiftCoupon') <= 0)
                // {
                //     $tempCart[$i]['GiftCertificateItemWiseDiscout'] = 0;
                // }
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
                    'coupon_itemwise_discount' => ($tempCart[$i]['ItemWiseCouponDiscount']??0),
                    'handling_time_str'		=> 	(isset($tempCart[$i]['HandlingTimeStr']))?$tempCart[$i]['HandlingTimeStr']:'',
                    'attribute_info'        => (isset($tempCart[$i]['IsYotpoFreeProduct']))?$tempCart[$i]['IsYotpoFreeProduct']:'No',
                    'actual_price'			=> $ActualPrice,
                    'item_tax_amount'		=> $ItemWiseTaxVal,
                    'sf_orderitemid'		=> $tempCart[$i]['TaxShippingItemWiseDiscount'] ?? 0
                );

                Log::info('OrderDetailInsert -- '.json_encode($OrderDetailInsert));
                $log['OrderDetailInsert'] = json_encode($OrderDetailInsert);
                addLog("PlaceOrder",$log);
                $OrdDetail = OrderDetail::create($OrderDetailInsert);
                Log::info('OrdDetail -- '.json_encode($OrdDetail));
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
                /*
                $IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$tempCart[$i]);
                if($IsGiftCertificateItem == 'Yes')
                {
                    //$AddGC = $this->InsertGiftCertificateDB($tempCart[$i], $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
                    $AddGC = $this->checkGiftCertificateItem('InsertGiftCertificateInDB', $tempCart[$i], 'Yes', $OrdDetail->orders_detail_id, $customer_id,$IsAmazOR);
                }
                */
            }
        }

        return response()->json([
            'status' => true,
            'order_id' => $OrderID,
        ]);
    }
    public function UpdateOrder(Request $request)
    {
        if($request->has('isPaypalOrder') && $request->isPaypalOrder == 'Yes')
        {
            $res = $this->PaypalService->DoPaymentPaypal($request);
            return response()->json([
                'status' => $res['status'],
                'message' => $res['message'],
            ]);
        } elseif($request->has('PayMethod') && $request->PayMethod == 'PAYMENT_STRIPE') {
            $payment_intent_id = $request->payment_intent_id??'';
            $order_id = $request->order_id??'';

            /*
            * Verify Stripe payment.
            */
            $payment =
                $this->StripePaymentService->verify(
                    $payment_intent_id
                );

            if (!$payment['success']) {

                return response()->json([
                    'status' => false,
                    'message' =>
                        'Payment has not been completed.',
                ], 422);
            }

            $Order = Order::find($order_id);
            $Order->pay_status = 'Paid';
            $Order->paymentintentid = $payment_intent_id;
            $Order->save();
        }elseif($request->has('PayMethod') && $request->PayMethod == 'PAYMENT_DS'){
            $order_id = $request->order_id??'';
            $Order = Order::find($order_id);
            $Order->pay_status = 'Paid';
            $Order->save();

            $Customer = Customer::where('customer_id',$Order->customer_id)->first();
            $DropshipperDetails = $this->checkoutService->GetDropshipperDetails();
            if($Customer->available_funds > 0 && $DropshipperDetails['fund_available'] == 'Yes')
            {
                $remaining_fund = $DropshipperDetails['remaining_fund'];
                $Customer->available_funds = $remaining_fund;
                $Customer->save();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment has been completed.',
        ]);
    }

    public function SetBillingShippingAddress($Data)
	{
		$temp = [];
		$prefix = 'ship';
        $prefix = 'bill';
        $Billing = Session::get('ShoppingCart.BillingAddress');
        Session::put('ShoppingCart.ShippingAddress',$Billing);

		return null;
	}
    public function CustomerInfoUpdate()
	{
		$allow_update_details = "Yes";
		$normaluser = Auth::user();

		if ($normaluser && $allow_update_details == "Yes")
        {
            $Billing = Session::get('ShoppingCart.BillingAddress');
			if ($Billing['country'] != 'US') {
				$state = isset($Billing['other_state']) ? $Billing['other_state'] : "";
			} else {
				$state = $Billing['state'];
			}
			$CustomerAddNew = array(
				'first_name'		=> stripslashes($Billing['first_name']),
				'last_name' 		=> stripslashes($Billing['last_name']),
				'address1' 			=> stripslashes($Billing['address1']),
				'city' 				=> stripslashes($Billing['city']),
				'state' 			=> $state,
				'country' 			=> $Billing['country'],
				'zip' 				=> $Billing['zip'],
				'phone' 			=> $Billing['phone']
			);
			if (isset($Billing['company']) && $Billing['company'] != "") {
				$CustomerAddNew['company_name'] = stripslashes($Billing['company']);
			}
			if (isset($Billing['address2']) && $Billing['address2'] != "") {
				$CustomerAddNew['address2'] = stripslashes($Billing['address2']);
			}
			$cust_upd = Customer::where('customer_id', '=', $normaluser->customer_id)->update($CustomerAddNew);

            Session::put('ShoppingCart.BillingAddress.email',$normaluser->email);
            Session::put('ShoppingCart.BillingAddress.cemail',$normaluser->email);

            Session::put('ShoppingCart.ShippingAddress.email',$normaluser->email);

            Session::put('sess_useremail',$normaluser->email);
            //merge guest accounts
			$user_email = $normaluser->email;

			$this->Merge_Guest_Register($user_email, $normaluser);
			//merge guest accounts
		}
        /*
		if(isset($request['newsletter']) && $request['newsletter'] == 'Yes' && trim($request['bill_email']) != '')
        {
			$check_news = NewsLetter::where('email', '=', trim($request['bill_email']))->get();
			if ($check_news && $check_news->count() <= 0) {
				$arrInsert = array(
					'first_name' => trim($request['bill_fname']),
					'last_name'  => trim($request['bill_lname']),
					'email' 	 => trim($request['bill_email']),
					'phone_no' => trim($request['bill_phone']),
					'status'	 => '1'
				);
				$News = NewsLetter::create($arrInsert);
				$NewsId = $News->news_letter_id;
				if ($NewsId) {
					$data["phone"] = trim($request['bill_phone']); //"+12679018713";
					$data["email"] = trim($request['bill_email']); //"test@gmail.com";
					$data["first_name"] = trim($request['bill_fname']);
					$data["visitorId"] = $NewsId; //"762bb2a97d604f958e3071fef83dfd5a";
					if (trim($data["phone"]) != "" && config('global.SITE_MODE') == 'Live') {
						AddAttentiveSubscriber($data);
					}
				}
			}
		}
        */
	}
    public function SetGuestCustomer($request, $isPaypal = 'No')
    {
        /*
        * ---------------------------------------------------------
        * 1. Check authenticated user
        * ---------------------------------------------------------
        */
        $normalUser = Auth::user();

        // Existing user or guest checkout disabled.
        if ($normalUser || config('global.IS_GUEST_CHECKOUT') !== 'Yes') {
            return null;
        }

        /*
        * ---------------------------------------------------------
        * 2. Prepare commonly used values
        * ---------------------------------------------------------
        */
        $customerId = (int) Session::get('sess_icustomerid');
        $Billing = Session::get("ShoppingCart.BillingAddress");

        $email      = trim($Billing['email'] ?? '');
        $ip         = request()->ip();
        $userAgent  = request()->userAgent();

        /*
        * Existing code clears the old guest session before
        * looking up the customer.
        */
        if ($customerId > 0) {
            Session::forget([
                'sess_icustomerid',
                'etype',
                'eusertype',
                'sess_useremail',
            ]);

            $customerId = 0;
        }

        /*
        * ---------------------------------------------------------
        * 3. Find existing customer
        *
        * Prefer member first, then guest.
        * ---------------------------------------------------------
        */
        $customer = null;
        $registrationType = 'Member';
        $allowUpdateDetails = 'Yes';

        if ($email !== '') {
            $customer = Customer::select([
                    'customer_id',
                    'email',
                    'status',
                    'eusertype',
                    'first_name',
                    'last_name',
                    'is_dropshipper',
                    'DownloadSpecialPricelist',
                    'payment_amount',
                    'omnisend_accountid',
                ])
                ->where('email', $email)
                ->where('registration_type', 'M')
                ->first();

            if (!$customer) {
                $customer = Customer::select([
                        'customer_id',
                        'email',
                        'status',
                        'eusertype',
                        'first_name',
                        'last_name',
                        'is_dropshipper',
                        'DownloadSpecialPricelist',
                        'payment_amount',
                        'omnisend_accountid',
                    ])
                    ->where('email', $email)
                    ->where('registration_type', 'G')
                    ->where('is_deleted', 'No')
                    ->first();

                $registrationType = 'Guest';
            }
        }

        /*
        * ---------------------------------------------------------
        * 4. Existing customer
        * ---------------------------------------------------------
        */
        if ($customer) {

            $customerId = (int) $customer->customer_id;

            Session::put([
                'sess_icustomerid' => $customerId,
                'etype'            => 'G',
                'eusertype'        => $customer->eusertype,
                'sess_useremail'   => $customer->email,
            ]);

            $allowUpdateDetails = 'No';

            /*
            * Activate inactive guest/member.
            */
            if ((string) $customer->status === '0') {
                Customer::where('customer_id', $customerId)->update([
                    'upd_datetime' => now(),
                    'merge_log'    => 'Auto updated to Active from billing page',
                    'status'       => '1',
                ]);

                $customer->status = '1';
            }

            /*
            * PayPal guest checkout:
            * update customer billing information.
            */
            if ($registrationType === 'Guest' && $isPaypal === 'Yes') {

                $customerData = $this->getGuestCustomerBillingData($request);

                Customer::where('customer_id', $customerId)
                    ->update($customerData);
            }

            Session::put(
                'ShoppingCart.merge_note',
                "Merge with {$customer->eusertype} ({$registrationType}) customer id: {$customerId}"
            );

            Session::put(
                'ShoppingCart.is_registered_guest',
                'Yes'
            );
        }

        /*
        * ---------------------------------------------------------
        * 5. Create/update guest customer
        * ---------------------------------------------------------
        */
        else {

            $customerData = $this->getGuestCustomerBillingData($request);

            $customerData['email']            = $email;
            $customerData['registration_type'] = 'G';
            $customerData['status']            = '1';
            $customerData['eusertype']         = 'Retailer';
            $customerData['customer_ip']      = $ip;
            $customerData['customer_browser'] = $userAgent;

            /*
            * Normally customerId will be zero because the old
            * session was cleared above.
            */
            if ($customerId <= 0) {
                $customer = Customer::create($customerData);

                $customerId = (int) $customer->customer_id;

                Session::put([
                    'sess_icustomerid' => $customerId,
                    'etype'            => 'G',
                    'eusertype'        => 'Retailer',
                    'sess_useremail'   => $email,
                ]);
            }
            else {

                Customer::where('customer_id', $customerId)
                    ->update($customerData);
            }
        }

        /*
        * ---------------------------------------------------------
        * 6. Merge guest if required
        * ---------------------------------------------------------
        */
        if ($allowUpdateDetails === 'No') {
            $this->Merge_Guest_Register($email, $customer);
        }

        /*
        * ---------------------------------------------------------
        * 7. Billing address
        * ---------------------------------------------------------
        */
        $this->SetBillingShippingAddress($request);

        /*
        * ---------------------------------------------------------
        * 8. Newsletter
        * ---------------------------------------------------------
        */
        $this->handleGuestNewsletter($request);

        /*
        * ---------------------------------------------------------
        * 9. Guest -> registered customer
        * ---------------------------------------------------------
        */
        $customerPassword = trim($request['guest_password'] ?? '');

        if (
            $customerPassword !== '' &&
            $registrationType === 'Guest'
        ) {
            return $this->registerGuestCustomer(
                $request,
                $customerId,
                $customerPassword
            );
        }

        return true;
    }

    private function getGuestCustomerBillingData($request): array
    {
        $fields = [
            'bill_fname',
            'bill_lname',
            'bill_address1',
            'bill_address2',
            'bill_city',
        ];

        $data = [];
        /*
        foreach ($fields as $field) {

            $value = trim($request[$field] ?? '');

            if (
                $value !== '' &&
                !preg_match('/^[\p{Latin}\p{Common}\s]+$/u', $value)
            ) {
                $value = $this->transliterate($value);
            }

            $data[$field] = stripslashes($value);
        }

        $country = $request['bill_country'] ?? '';

        $state = (
            $country !== 'US' &&
            !empty($request['bill_other_state'])
        )
            ? $request['bill_other_state']
            : ($request['bill_state'] ?? '');
        */
        $data = Session::get('ShoppingCart.BillingAddress');
        return [
            'first_name' => $data['first_name'] ?? '',
            'last_name'  => $data['last_name'] ?? '',
            'address1'   => $data['address1'] ?? '',
            'address2'   => $data['address2'] ?? '',
            'city'       => $data['city'] ?? '',
            'state'      => $data['state'] ?? '',
            'country'    => $data['country'] ?? '',
            'zip'        => $request['zip'] ?? '',
            'phone'      => $request['phone'] ?? '',
        ];
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

    private function handleGuestNewsletter($request): void
    {
        if (
            ($request['newsletter'] ?? 'No') !== 'Yes'
        ) {
            return;
        }

        $email = trim($request['email'] ?? '');

        if ($email === '') {
            return;
        }

        /*
        * exists() is considerably cheaper than get()
        * because we only need to know whether it exists.
        */
        if (NewsLetter::where('email', $email)->exists()) {
            return;
        }

        $newsletter = NewsLetter::create([
            'first_name' => trim($request['bill_fname'] ?? ''),
            'last_name'  => trim($request['bill_lname'] ?? ''),
            'email'      => $email,
            'phone_no'   => trim($request['bill_phone'] ?? ''),
            'status'     => '1',
        ]);

        if($newsletter->news_letter_id &&trim($request['bill_phone'] ?? '') !== '' && config('global.SITE_MODE') === 'Live')
        {
            AddAttentiveSubscriber([
                'phone'     => trim($request['bill_phone']),
                'email'     => $email,
                'first_name' => trim($request['bill_fname'] ?? ''),
                'visitorId' => $newsletter->news_letter_id,
            ]);
        }
    }

    private function registerGuestCustomer(
        $request,
        int $customerId,
        string $customerPassword
    ) {
        $email = trim($request['bill_email'] ?? '');
        $ip    = request()->ip();

        /*
        * Check whether the email is already used by another member.
        */
        $emailAlreadyUsed = Customer::where('customer_id', $customerId)
            ->where('email', $email)
            ->where('registration_type', 'M')
            ->exists();

        if ($emailAlreadyUsed) {
            return response()->json([
                'error'   => 1,
                'Message' => 'To become a registered customer, please change your email address, as its already in use.',
            ]);
        }

        /*
        * Maximum 5 registered users per IP.
        */
        $registeredUsersFromIp = Customer::where('customer_ip', $ip)
            ->where('registration_type', 'M')
            ->where('customer_id', '!=', $customerId)
            ->count();

        if ($registeredUsersFromIp >= 5) {
            return response()->json([
                'error'   => 1,
                'Message' => 'Oops .. Your IP has reached the maximum count of user registered with maxaroma.There are 5 different users already registered from this IP.',
            ]);
        }

        $customerData = $this->getGuestCustomerBillingData($request);

        $customerData += [
            'email'            => $email,
            'status'           => '1',
            'eusertype'        => 'Retailer',
            'customer_ip'      => $ip,
            'customer_browser' => request()->userAgent(),
            'password'         => $customerPassword,
            'registration_type'=> 'M',
            'upd_datetime'     => now(),
        ];

        if (config('global.YOTPO_PROG') === false) {
            $customerData['iRewardpoint'] = 150;
        }

        $updated = Customer::where('customer_id', $customerId)
            ->update($customerData);

        if (!$updated) {
            return response()->json([
                'error'   => 1,
                'Message' => 'Error###You have not been registered, please try again to become a registered customer.',
            ]);
        }

        /*
        * Reward point.
        */
        if (config('global.YOTPO_PROG') === false) {
            RewardPoint::create([
                'customer_id'  => $customerId,
                'note'         => 'Reward Point Added By Checkout Register',
                'iRewardpoint' => 150,
            ]);
        }

        /*
        * Fetch the customer once.
        */
        $customer = Customer::where('customer_id', $customerId)
            ->where('status', '1')
            ->where('registration_type', 'M')
            ->first();

        if (!$customer) {
            return response()->json([
                'error'   => 1,
                'Message' => 'Error###Customer registration failed.',
            ]);
        }

        /*
        * ---------------------------------------------------------
        * Update session in one operation.
        * ---------------------------------------------------------
        */
        Session::put([
            'sess_useremail'       => $customer->email,
            'sess_username'        => $customer->first_name,
            'sess_icustomerid'     => $customer->customer_id,
            'eusertype'            => $customer->eusertype,
            'is_dropshipper'       => $customer->is_dropshipper,
            'SpecialCustomerFlag'  => $customer->DownloadSpecialPricelist,
            'etype'                => 'M',
            'payment_amount'       => $customer->payment_amount,
            'sess_custname'       => ($customer->first_name ?? '') . '|' . ($customer->last_name ?? ''),
            'sess_useraddress'    => implode('|', [
                $customer->city ?? '',
                $customer->state ?? '',
                $customer->country ?? '',
                $customer->zip ?? '',
                $customer->phone ?? '',
            ]),
            'GARegsiter'          => 'Yes',
        ]);

        Auth::login($customer, false);

        /*
        * External integrations should ideally be queued.
        */
        YotpoRequest('create_customer', $customer);

        /*
        * Merge guest accounts.
        */
        $this->Merge_Guest_Register(
            $email,
            $customer
        );

        /*
        * Billing address after registration.
        */
        $this->SetBillingShippingAddress($request);
        return true;
    }

    public function Merge_Guest_Register($email, $memberCustomer)
    {
        $guestCustomer = Customer::select('customer_id')
            ->where('status', '1')
            ->where('email', trim($email))
            ->where('registration_type', 'G')
            ->where('is_deleted', 'No')
            ->first();

        if (!$guestCustomer) {
            return false;
        }

        $memberCustomerId = (int) $memberCustomer->customer_id;
        $guestCustomerId  = (int) $guestCustomer->customer_id;

        if ($memberCustomerId === $guestCustomerId) {
            return false;
        }

        $mergeLog = sprintf(
            'Merge with %s%s customer id: %d',
            $memberCustomer->eusertype,
            $memberCustomer->registration_type,
            $memberCustomerId
        );

        Customer::where('customer_id', $guestCustomerId)
            ->update([
                'is_deleted' => 'Yes',
                'merge_log'  => $mergeLog,
            ]);

        Order::where('customer_id', $guestCustomerId)
            ->update([
                'customer_id'    => $memberCustomerId,
                'merge_note'     => $mergeLog . '<br>Previous customer id: ' . $guestCustomerId,
                'old_customerid' => $guestCustomerId,
            ]);

        return true;
    }

    public function DropshipperDetails(Request $request)
    {
        $data = $this->checkoutService->GetDropshipperDetails();
        return response()->json($data);
    }
}