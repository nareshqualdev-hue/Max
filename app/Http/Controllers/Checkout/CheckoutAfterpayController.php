<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Services\Payment\AfterpayService;
use App\Services\Checkout\CheckoutService;

class CheckoutAfterpayController extends Controller
{
    protected AfterpayService $afterpay;

    protected CheckoutService $checkoutService;
    public function __construct(AfterpayService $afterpay, CheckoutService $checkoutService)
    {
        $this->afterpay = $afterpay;
        $this->checkoutService = $checkoutService;
    }

    public function SetAfterpay_Express(Request $request)
    {
        $CheckoutResult = $this->checkoutService->prepareCheckout($request);
        if (isset($CheckoutResult['redirect'])) {
            return $CheckoutResult['redirect'];
        }
        /* * Preserve old behaviour. */
        Session::forget('ShoppingCart.OrderID'); /* * Cart validation */
        if (Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0) {
            Session::forget('ShoppingCart');
            return response()->json(['success' => '0', 'token' => '', 'message' => 'Error in Processing Request, Please try again.']);
        }
        try {
            $result = $this->afterpay->createExpressCheckout($CheckoutResult['data']);
            return response()->json(['success' => $result['success'], 'token' => $result['token'], 'redirect' => $result['redirect'] ?? '', 'message' => $result['message'] ?? '',]);
        } catch (\Throwable $e) {
            Log::error('Afterpay Express Checkout Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString(),]);
            return response()->json(['success' => '0', 'token' => '', 'message' => 'Error in Processing Request, Please try again.']);
        }
    }

    public function Billing_Checkout_Express(Request $request)
	{
        $expires = Session::get('ShoppingCart.AfterPay.Checkout_Expires');

        if ($this->afterpay->isCheckoutTokenExpired($expires)) {
            // Token expired — create a new checkout
            return redirect('secure-checkout1');
        }
		if(isset($request->status) && $request->status == "2")
		{
			if (isset($request->orderToken) && $request->orderToken != '') {
				// billing_checkout_express
				if(Session::has('ShoppingCart.AfterPay.Checkout_Token') && Session::get('ShoppingCart.AfterPay.Checkout_Token') != ""){
					if($request->orderToken != Session::get('ShoppingCart.AfterPay.Checkout_Token')){
						Session::flash('PlaceOrderError','Token Mismatch, Error in Processing Request. Please try again.');
						Session::forget('ShoppingCart.AfterPay.Checkout_Token');
						return redirect('secure-checkout1');
					}
				}
                $SetToken = $this->afterpay->setToken($request->orderToken);
				if($SetToken['success'] === true)
				{
					$response = $SetToken['response'];

					if(checkBlockedUser($response->consumer->email,0,'AfterpayGuest')==true)
					{
						Session::flash('PlaceOrderError',config('message.Register.Blocked'));
						return redirect('/shoppingcart/view');
					}

					 //echo "<pre>";print_r($getCheckoutResponse->isSuccessful());exit;
				//	 return 1;
					if ($SetToken['isSuccessful'] && isset($response->token) && $response->token != "")
					{
						$tempShippingAdd = Session::get('ShoppingCart.ShippingAddress');
						if($response->shipping->postcode!=$tempShippingAdd['zip'] || $response->shipping->countryCode!=$tempShippingAdd['country'] || $response->shipping->region!=$tempShippingAdd['state'])
						{
							Session::flash('PlaceOrderError','Address mismatch error, Please try again.');
							Session::forget('ShoppingCart.AfterPay.Checkout_Token');
							$request->session()->forget('ShoppingCart.AfterPay.Checkout_Token');

							Session::forget('Afterpay.Min_AP_AMT');
							Session::forget('Afterpay.Max_AP_AMT');
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

				$SetToken = $this->afterpay->setToken($request->orderToken);

				if ($SetToken['success'] === true) {
					$response = $SetToken['response'];

					if(checkBlockedUser($response->consumer->email,0,'AfterpayGuest')==true)
					{
						Session::flash('PlaceOrderError',config('message.Register.Blocked'));
						return redirect('/shoppingcart/view');
					}

					// echo "<pre>";print_r($response);exit;
					if ($SetToken['isSuccessful'] && isset($response->token) && $response->token != "") {
						$cst_details = [];
						$ShippingAddress = [];
						if(!empty($response->shipping) && isset($response->shipping->name) && $response->shipping->name != ""){

							$name_arr = explode(" ",$response->shipping->name);
							if(isset($name_arr[0]) && $name_arr[0]!='')
							{
								$cst_details['ship_fname'] = $this->afterpay->transliterate($name_arr[0]);
								$ShippingAddress['first_name'] = $this->afterpay->transliterate($name_arr[0]);
							}
							if(isset($name_arr[1]) && $name_arr[1]!='')
							{
								$cst_details['ship_lname'] = $this->afterpay->transliterate($name_arr[1]);
								$ShippingAddress['last_name'] = $this->afterpay->transliterate($name_arr[1]);
							}
							if(isset($response->shipping->line1) && $response->shipping->line1!='')
							{
								$cst_details['ship_address1'] = $this->afterpay->transliterate($response->shipping->line1);
								$ShippingAddress['address1'] = $this->afterpay->transliterate($response->shipping->line1);
							}
							$ShippingAddress['address2'] = "";
							if(isset($response->shipping->line2) && $response->shipping->line2!='')
							{
								$cst_details['ship_address2'] = $response->shipping->line2;
								$ShippingAddress['address2'] = $this->afterpay->transliterate($response->shipping->line2);
							}
							if(isset($response->shipping->area1) && $response->shipping->area1!='')
							{
								$cst_details['ship_city'] = $response->shipping->area1;
								$ShippingAddress['city'] = $response->shipping->area1;
							}
							if(isset($response->shipping->region) && $response->shipping->region!='')
							{
								$cst_details['ship_state'] = $response->shipping->region;
								$ShippingAddress['state'] = $response->shipping->region;
							}
							if(isset($response->shipping->postcode) && $response->shipping->postcode!='')
							{
								$cst_details['ship_zip'] = $response->shipping->postcode;
								$ShippingAddress['zip'] = $response->shipping->postcode;
							}
							if(isset($response->shipping->phoneNumber) && $response->shipping->phoneNumber!='')
							{
								$cst_details['ship_phone'] = $response->shipping->phoneNumber;
								$ShippingAddress['phone'] = $response->shipping->phoneNumber;
							}
							if(isset($response->shipping->countryCode) && $response->shipping->countryCode!='')
							{
								$cst_details['ship_country'] = $response->shipping->countryCode;
								$ShippingAddress['country'] = $response->shipping->countryCode;
							}

						}

						if(!empty($response->consumer) && isset($response->consumer->email) && $response->consumer->email != ""){
							$cst_details['email'] = $response->consumer->email;
							$ShippingAddress['email'] = $response->consumer->email;
							$cst_details['fName'] = $response->consumer->givenNames;
							$cst_details['lName'] = $response->consumer->surname;
						}
						if(!empty($cst_details)){
							Session::put('ShoppingCart.AfterPay.Customer_Details',$cst_details);
							Session::put('ShoppingCart.ShippingAddress',$ShippingAddress);
							Session::put('ShoppingCart.BillingAddress',$ShippingAddress);
						}
						return redirect('secure-checkout1/afterpay');
					}else{
						Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');
						return redirect('secure-checkout1');
					}

				}
			}else{
				Session::forget('ShoppingCart.AfterPay.Checkout_Token');
				Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');
				return redirect('secure-checkout1');
			}
		}else{
			Session::forget('ShoppingCart.AfterPay.Checkout_Token');
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');
			return redirect('secure-checkout1');
		}
	}

    public function SetAfterpay(Request $request)
    {
        if (Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0) {
            Session::forget('ShoppingCart');
            return redirect('/shoppingcart');
        }
        $orderId = Session::get('ShoppingCart.OrderID');
        if (!$orderId && $request->filled('ordernoid')) {
            $orderId = (int) $request->ordernoid;
        }
        if (!$orderId) {
            Session::flash('PlaceOrderError', 'Invalid order.');
            return redirect('checkout');
        }
        $result = $this->afterpay->createCheckout(
            (int) $orderId,
            [
                'mode' => 'express',
                'redirectConfirmUrl' => url('afterpay/success'),
                'redirectCancelUrl' => url('afterpay/cancel')
            ]
        );

        if (!$result['success']) {
            Order::where('orders_id', $orderId)->update(['status' => 'Declined', 'transaction_info' => $result['message'], 'payment_gateway_response' => json_encode($result),]);
            Session::flash('PlaceOrderError', $result['message']);
            return redirect('checkout');
        }
        Order::where('orders_id', $orderId)->update(['status' => 'Sent To AfterPay', 'payment_type' => 'PAYMENT_PAYWITHAFTERPAY', 'payment_method' => 'Pay With Afterpay',]);
        return redirect($result['redirect_url']);
    }

    public function Success(Request $request)
    {
        if ($request->status !== 'SUCCESS' || !$request->filled('orderToken')) {
            return $this->afterpay->afterpayDeclined('This transaction has been Declined by User.');
        }
        $result = $this->afterpay->authorize($request->orderToken);
        if (!$result['success'] || !$result['approved']) {
            return $this->afterpay->afterpayDeclined('This transaction has been Declined.', $result);
        }
        $response = $result['response'];
        $orderId = Session::get('ShoppingCart.OrderID');
        Order::where('orders_id', $orderId)->update(['payment_gateway_response' => 'Auth Response::' . json_encode($response), 'afterpay_transaction_id' => $response->id ?? null,]);
        return redirect(url('afterpay/dopayment/' . $response->id));
    }

    public function DoPayment(Request $request)
    {
        $orderId = (int) Session::get('ShoppingCart.OrderID');
        if (!$orderId) {
            return redirect('checkout');
        }
        $amount = NumberFormat($this->afterpay->GetNetTotal());
        $result = $this->afterpay->capture($request->order_id, $amount, $orderId);
        $gatewayResponse = $result['response'] ?? null;
        if ($result['success'] && $result['captured']) {
            $oldResponse = Order::where('orders_id', $orderId)->value('payment_gateway_response');
            $paymentResponse = $oldResponse . "\n\n==============\n\n" . "Capture Response::" . json_encode($gatewayResponse);
            Order::where('orders_id', $orderId)->update(['pay_status' => 'Paid', 'status' => 'Pending', 'transaction_info' => 'This transaction has been approved.', 'payment_gateway_response' => $paymentResponse, 'afterpay_transaction_id' => $gatewayResponse->id ?? null,]);
            return redirect('order-receipt');
        }
        Order::where('orders_id', $orderId)->update(['status' => 'Declined', 'transaction_info' => 'This transaction has been Declined.', 'payment_gateway_response' => json_encode($gatewayResponse),]);
        Session::flash('CartError', $gatewayResponse->message ?? 'Error in Processing Request, Please try again.');
        return redirect('shoppingcart');
    }
}
