<?php

namespace App\Services\Payment;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use App\Services\Checkout\CheckoutTotalsService;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Traits\EncryptTrait;
use Illuminate\Http\Request;

use App\Models\PaymentMethod;
use App\Models\Order;
class PaypalService
{
    use EncryptTrait;
    private $provider;
    private $tokenDetail;
    protected CheckoutTotalsService $checkoutTotals;

    public function __construct(CheckoutTotalsService $checkoutTotals, PayPalClient $provider) {
        $this->checkoutTotals = $checkoutTotals;
        $this->provider = $provider;

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

            $PaypalConfig = [
                'mode'    => 'sandbox',
                'sandbox' => [
                    'client_id'    	=> 'AQymJLkSRgzhHf0AjiYOGL_OHQZ60bCggeySkd8F31n_2ery6HK7HXYQGeeBCfszGgAin8XfJbvZuByn',
                    'client_secret' => 'ECHswxs-ygvtI9JSZ_3uOTWxyKyrQCK2w7mGq-MXs6a1r8zZSHZt0TuHiJbKAkkjx0_Y06Ma8iE1TGJh',
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

            // $PaypalConfig = [
            // 	'mode'    => 'live',
            // 	'live' => [
            // 		'client_id'    	=> $client_id,
            // 		'client_secret' => $client_secret,
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

			$this->provider->setApiCredentials($PaypalConfig);
		}
    }

    public function UpdateDetails($ShipData = [])
    {
        $this->tokenDetail = $this->provider->getAccessToken();
        if(empty($this->tokenDetail))
		{
			Session::forget('PayPalToken');
			$err_msg = 'Please try agian token expired';
			Session::flash('PlaceOrderError',$err_msg);
			$Res['status'] = 'Error';
            $Res['error_msg'] = $err_msg;
			return response()->json($Res);
		}

		if(empty($ShipData['PaypalOrderId']))
		{
			Session::forget('PayPalToken');
			$err_msg = 'Please try agian token expired';
			Session::flash('PlaceOrderError',$err_msg);
			$Res['status'] = 'Error';
            $Res['error_msg'] = $err_msg;
			return response()->json($Res);
		}
        $PaypalOrderId = $ShipData['PaypalOrderId'];
        $data1 = $ShipData['data1'];

        $payment_response = $this->provider->updateOrder($PaypalOrderId,$data1);
        if(isset($payment_response["error"])  && $payment_response["error"]!='')
        {
            $err_msg = $payment_response["error"];
            Session::flash('PlaceOrderError',$err_msg);
            $Res['status'] = 'Error';
            $Res['error_msg'] = json_decode($payment_response["error"],true);
            return response()->json($Res);
        }
        else
        {
            $response = $this->provider->showOrderDetails($PaypalOrderId);
            $myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
            if(fopen($myFile, 'a+'))
            {
                $fh = fopen($myFile, 'a+');
                $stringData = date("m/d/Y H:i:s")." After Checkout PayPal Update : Checkout New\n";
                $stringData .= date("m/d/Y H:i:s")." After Checkout PayPal Update :".json_encode($response)."\n";
                fwrite($fh, $stringData);
                fclose($fh);
            }

            if(!empty($ShipData['payer_email']))
            {
                Session::put('PaypalEmail', $ShipData['payer_email']);
            }
            Session::put('Paypalcity', $ShipData['city']);
            Session::put('Paypalcountry', $ShipData['country']);
            Session::put('Paypalstate', $ShipData['state']);
            Session::put('Paypalzipcode', $ShipData['zip']);
            $Res['status'] = 'Success';
            return response()->json($Res);
        }
    }

    public function DoPaymentPaypal(Request $request)
	{
        $this->tokenDetail = $this->provider->getAccessToken();
		$step = "";
		$detailsRes = json_decode($request->OrderDetails,true);
		$rtnm = $request->routenmnew;
		$step = "---last_step";

		$abort = "";
		if(isset($request->isAbort) && $request->isAbort=='isAbort')
		{
			$abort = "---".$request->isAbort;
			$detailsRes = $this->provider->showOrderDetails($detailsRes["id"]);
		}
		$detailsRes = $this->provider->capturePaymentOrder($detailsRes["id"]);

        $myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');
			$stringData = date("m/d/Y H:i:s")." Before PayPal Order Approval :".json_encode($rtnm)."\n";
			$stringData .= date("m/d/Y H:i:s")." After PayPal Order Approval Insert :".json_encode($detailsRes)."\n";
			fwrite($fh, $stringData);
			fclose($fh);
		}
        $payment_gateway_response = json_encode($detailsRes);
        $OrderStatus = "";
        $pay_status = "";
        $Billing  = Session::get('ShoppingCart.BillingAddress');

        if(isset($detailsRes["status"]) && ($detailsRes["status"]=="COMPLETED" || $detailsRes["status"]=="APPROVED"))
        {
			$transaction_info = "This transaction has been approved.";
			$pay_status = 'Unpaid';
			if($detailsRes["status"]=="COMPLETED"){
				$pay_status = 'Paid';
			}
            $OrderStatus = "Pending";
        } else {
			$transaction_info = "This transaction has been Declined.";
            $OrderStatus = "Declined";
            $ErrorLongMsg = 'Please check details, try again';
            Session::flash('CartError',$ErrorLongMsg);
        }

		$updAray = array (
            'status'                    => $OrderStatus,
            'pay_status' 	   			=> $pay_status,
            'transaction_info' 			=> $transaction_info,
            'payment_gateway_response' 	=> $payment_gateway_response.$step.$abort,
        );

        if(isset($request->OrderID)  && $request->OrderID > 0)
        {
            $order_id = $request->OrderID;
        }
        else if(Session::has('ShoppingCart.OrderID') && Session::get('ShoppingCart.OrderID') > 0)
        {
            $order_id = Session::get('ShoppingCart.OrderID');
        } else {
            $myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
            if(fopen($myFile, 'a+'))
            {
                $fh = fopen($myFile, 'a+');
                $stringData = date("m/d/Y H:i:s")." Order id not found ".$Billing['email'];
                fwrite($fh, $stringData);
                fclose($fh);
            }
        }
        $updOrder = Order::Where("orders_id","=",$order_id)->update($updAray);

        $myFile = env('LOG_BASE_PATH').'Logs/ApplePayOtherLog.txt';
        if(fopen($myFile, 'a+'))
        {
            $fh = fopen($myFile, 'a+');
            $stringData = date("m/d/Y H:i:s")." After PayPal Order Approval :".json_encode($rtnm)."\n";
            $stringData .= date("m/d/Y H:i:s")."After PayPal Order Approval Insert :".json_encode($detailsRes)."\n";
            fwrite($fh, $stringData);
            fclose($fh);
        }

        if (isset($detailsRes['status']) && $detailsRes['status'] === 'COMPLETED') {
            return ['status' => true, 'message' => 'Payment has been completed.'];
        } else {
            return ['status' => false, 'message' => "Please check details, try again"];
        }
	}
}

