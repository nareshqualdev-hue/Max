<?php
namespace App\Http\Controllers\Checkout;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CartTrait;
use Illuminate\Support\Facades\Auth;
use App\Services\Checkout\CheckoutService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Traits\CommonTrait;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Customer;
use App\Models\NewsLetter;
use App\Models\RewardPoint;
use App\Services\Checkout\ShippingService;
use App\Services\Checkout\CheckoutTotalsService;

class CheckoutStripeController extends Controller
{
    use CommonTrait;
	use CartTrait;
    protected CheckoutService $checkoutService;
    protected StripePaymentService $StripePaymentService;
	protected ShippingService $ShippingService;
	protected CheckoutTotalsService $CheckoutTotalsService;

    public function __construct(
        CheckoutService $checkoutService,
        StripePaymentService $StripePaymentService,
		ShippingService $ShippingService,
		CheckoutTotalsService $CheckoutTotalsService
    )
    {
        $this->checkoutService = $checkoutService;
        $this->StripePaymentService = $StripePaymentService;
		$this->ShippingService = $ShippingService;
		$this->CheckoutTotalsService = $CheckoutTotalsService;
    }

    public function CreatePaymentIntent()
    {
        return $this->StripePaymentService->createPaymentIntent();
    }

    public function GetCartForStripe(Request $request)
	{
        $result = $this->checkoutService->prepareCheckout($request);

        if (isset($result['redirect'])) {
            return $result['redirect'];
        }
		$NetTotal = (float)$result['data']['checkout']['totals']['NetTotal']??0;
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
				$AllCharges = $result['data']['checkout']['totals']['Charges'] ?? [];
				foreach($AllCharges as $Charge)
				{
					$CartItems[$i]['amount'] = round($Charge['charge']*100);
					$CartItems[$i]['label'] = $Charge['label'];
					$i++;
				}

				$AllDiscount = $result['data']['checkout']['totals']['Discounts'] ?? [];
				foreach($AllDiscount as $discount)
				{
					$CartItems[$i]['amount'] = round($discount['discount']*100);
					$CartItems[$i]['label'] = $discount['label'];
					$i++;
				}
			}
		}
		return response()->json(['items' => $CartItems, 'NetTotal' => round($NetTotal * 100)]);
	}
	public function StripeShippingMethods(Request $request)
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
        $ShippingInsurance = 0;
        if(isset($AllCharges['ShippingInsurance']['charge']))
        {
            $ShippingInsurance= (float)$AllCharges['ShippingInsurance']['charge']??0;
        }
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
		$shippingMethods = [];
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
				$shippingMethods[] = [
					'id' => (string)$ShipMethod['shipping_mode_id'],
                    'label' => strip_tags($ShipMethod['method_name']),
                    'detail' => $ShipMethod['display_date'],
                    'amount' => ((float)$amount_arr['value'] * 100),
				];
            }
        }
		if(count($shippingMethods) > 0 )
		{
			$this->ShippingService->setShippingMethod($shippingMethods[0]['id'],$currentAddress);
		}
		//Log::channel('newcheckout')->info($shippingMethods);
        return $shippingMethods;
    }
	public function SetStripeShippingMethod(Request $request)
	{
		$ShippingStatus = 'failed';
		//Log::channel('newcheckout')->info($request->all());
		if(!empty($request->ShipMethodID))
		{
			$currentAddress = Session::get('ShoppingCart.ShippingAddress');
			$ShippingStatus = $this->ShippingService->setShippingMethod($request->ShipMethodID,$currentAddress);
			if($ShippingStatus['status'] == 'success')
			{
				$this->checkoutService->refresh();
			}
		}
		return $ShippingStatus;
	}
	public function GetStripeClientSecret(Request $request)
	{
		Log::channel('newcheckout')->info("GPay Request:".json_encode($request->all()));
		$log['ajax_request'] = json_encode($request->all());
		addLog('GetClientSecretStart', $log);
		//if(isset($request->emailAddress) && $request->emailAddress!='')
		if (isset($request->allDetailsVal) && $request->allDetailsVal != '') {
			$payerEmail = "";
			$payerPhone = "";
			$allDetailsVal = $request->allDetailsVal;
			if (is_string($allDetailsVal)) {
				$allDetailsVal = json_decode($allDetailsVal, true);
				$payerEmail = $allDetailsVal['payerEmail'] ?? '';
				$payerPhone = $allDetailsVal['payerPhone'] ?? '';
			}

			if ($payerPhone == '') {
				Session::flash('PlaceOrderError', "Please enter phone no.");
				return false;
			}

			if ($payerEmail != '') {
				//if(checkBlockedUser($request->emailAddress,0,'ClientSecret')==true)
				if (checkBlockedUser($payerEmail, 0, 'ClientSecret') == true) {
					Session::flash('PlaceOrderError', config('message.Register.Blocked'));
					return false;
				}
			}
		}
		Log::channel('newcheckout')->info("stepfrom:".$request->stepfrom);
		if (isset($request->stepfrom) && $request->stepfrom == "firststep")
		{
			if (isset($request->allDetailsVal) && $request->allDetailsVal != '')
			{
				$Billing = json_decode($request->allDetailsVal, true);
				Log::channel('newcheckout')->info("Billing:".$request->allDetailsVal);
				$log['billing_details'] = json_encode($Billing);
				addLog('GetClientSecretFirstStepGetBilling', $log);

				$frsname = '';
				$lrsname = '';
				if (isset($Billing["payerName"]) && $Billing["payerName"] != '') {
					$fnameArr = explode(" ", $Billing["payerName"]);

					if (isset($fnameArr[0]) && $fnameArr[0] != '')
						$frsname = $fnameArr[0];

					if (isset($fnameArr[1]) && $fnameArr[1] != '')
						$lrsname = $fnameArr[1];
				}
				$AddressLine1 = '';
				if (isset($Billing['shippingAddress']['addressLine'][0]) && $Billing['shippingAddress']['addressLine'][0] != '') {
					$AddressLine1 = $Billing['shippingAddress']['addressLine'][0];
				}
				$AddressLine2 = '';
				if (isset($Billing['shippingAddress']['addressLine'][1]) && $Billing['shippingAddress']['addressLine'][1] != '') {
					$AddressLine2 = $Billing['shippingAddress']['addressLine'][1];
				}

				$CustomerEmail = '';

				if (isset($Billing['payerEmail']) && $Billing['payerEmail'] != '') {
					$CustomerEmail =  $Billing['payerEmail'];
				} else if (Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0) {
					$CustomerEmail =  Session::get('sess_useremail');
				}

				$newrequest = [
					'bill_country' 		=> (isset($Billing['paymentMethod']['billing_details']['address']['country']) ? $Billing['paymentMethod']['billing_details']['address']['country'] : ''),
					'bill_fname' 		=> $frsname,
					'bill_lname'		=> $lrsname,
					'bill_address1' 	=> (isset($Billing['paymentMethod']['billing_details']['address']['line1']) ? $Billing['paymentMethod']['billing_details']['address']['line1'] : ''),
					'bill_address2' 	=> (isset($Billing['paymentMethod']['billing_details']['address']['line2']) ? $Billing['paymentMethod']['billing_details']['address']['line2'] : ''),
					'bill_city' 		=> (isset($Billing['paymentMethod']['billing_details']['address']['city']) ? $Billing['paymentMethod']['billing_details']['address']['city'] : ''),
					'bill_state' 		=> (isset($Billing['paymentMethod']['billing_details']['address']['state']) ? $Billing['paymentMethod']['billing_details']['address']['state'] : ''),
					'bill_zip' 			=> (isset($Billing['paymentMethod']['billing_details']['address']['postal_code']) ? $Billing['paymentMethod']['billing_details']['address']['postal_code'] : ''),
					'bill_phone' 		=> (isset($Billing['paymentMethod']['billing_details']['address']['phone']) ? $Billing['paymentMethod']['billing_details']['address']['phone'] : ''),
					'bill_email' 		=> $CustomerEmail,
					'bill_cemail' 		=> $CustomerEmail,
					'sameasbill' 		=> 'No'
				];
				$log['newrequest'] = json_encode($newrequest);
				addLog('GetClientSecretFirstStepSetBilling', $log);
				Session::put('ShoppingCart.BillingAddress',$newrequest);

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
				if (isset($Billing['paymentMethod']['card']['wallet']['type']) && $Billing['paymentMethod']['card']['wallet']['type'] != '') {
					$Wallet = $Billing['paymentMethod']['card']['wallet']['type'];

					if ($Wallet == 'google_pay') {
						$Wallet = 'Google Pay';
					}
					if ($Wallet == 'apple_pay') {
						$Wallet = 'Apple Pay';
					}
				}

				Session::put('StripePaymentType', $Wallet);
				Session::put('PayMethodRes', serialize(json_encode($Billing)));

				$log['newrequestShip'] = json_encode($newrequestShip);
				addLog('GetClientSecretFirstStepSetShipping', $log);
				Session::put('ShoppingCart.ShippingAddress',$newrequestShip);
			}
		}

		$clientSecret = "";
		$payment_intent_id = "";
		$NetTotal = $this->CheckoutTotalsService->getNetTotal();
		Log::channel('newcheckout')->info("NetTotal:".$NetTotal);
		if ($NetTotal > 0)
		{
			Stripe::setApiKey(env('STRIPE_SECRET'));
			$intent = \Stripe\PaymentIntent::create([
				'amount' => round($NetTotal * 100),
				'currency' => 'usd',
				'metadata' => [
					'order_number' => 'OR' . Session::get('ShoppingCart.OrderID'),
				],
			]);

			if ($intent && isset($intent->client_secret))
				$clientSecret = $intent->client_secret;

			if ($intent && isset($intent->id))
				$payment_intent_id = $intent->id;
			//}
			$log['clientsecretintent'] = json_encode($intent);
			addLog('GetClientSecretIntent', $log);
		}

		$myFile = env('LOG_BASE_PATH') . 'Logs/ApplePayStripOrderPlaceLog.txt';
		if (fopen($myFile, 'a+')) {
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s") . " OrderPlace " . json_encode(Session::get('ShoppingCart')) . " \n";
			fwrite($fh, $stringData);
			fclose($fh);
		}

		if (isset($_REQUEST['AppleGPay']) && ($_REQUEST['AppleGPay'] == 'G' || $_REQUEST['AppleGPay'] == 'A'))
		{
			$OrderVal = $this->ApplePayPlaceOrder($request);
			if ($OrderVal != 'OK') {
				Session::forget("StripePaymentType");
				Session::forget("PayMethodRes");
			}
			$log['clientsecret_status'] = json_encode($OrderVal);
			addLog('GetClientSecretStatus', $log);
		}

		if (isset($OrderVal) && $OrderVal == 'OK')
		{
			$NetTotal = $this->GetNetTotal();
			\Stripe\PaymentIntent::update(
				$payment_intent_id,
				[
					'amount' => round($NetTotal * 100),
					'currency' => 'usd',
					'metadata' => [
						'order_number' => 'OR' . Session::get('ShoppingCart.OrderID'),
					]
				]
			);
			$log['clientsecretpayment_intent_id'] = json_encode($payment_intent_id);
			addLog('GetClientSecretIntent', $log);
			Session::put('ShoppingCart.apple_google_paymentintentid', $payment_intent_id);
			return response()->json(['clientSecret' => $clientSecret]);
		} else if ($OrderVal == 'OutOfStock') {
			return "OutOfStock";
		} else if ($OrderVal == 'Close') {
			return "Close";
		} else if ($OrderVal == 'Guest') {
			return "Guest";
		} else if ($OrderVal == 'SHMethod') {
			return "SHMethod";
		} else if ($OrderVal == 'Zero') {
			return "Zero";
		}
	}
}
?>