<?php

namespace App\Http\Controllers\Checkout;
use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

use App\Models\Order;
use App\Models\OrderDetail;
class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;
    protected StripePaymentService $StripePaymentService;

    public function __construct(CheckoutService $checkoutService, StripePaymentService $StripePaymentService)
    {
        $this->checkoutService = $checkoutService;
        $this->StripePaymentService = $StripePaymentService;
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
        return view('newcheckout.checkout-page')->with(
            $result['data']
        );
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
    public function stripeVerify(
        Request $request
    ) {

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
    public function stripeReturn(
        Request $request
    ) {

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

    public function MaxOrder(Request $request)
    {
        $result = $this->checkoutService->prepareCheckout($request);

        if (isset($result['redirect']))
        {
            return $result['redirect'];
        }
        $CheckoutData = $result['data']['checkout']['totals'];
        $customer_id = (int)Session::get('sess_icustomerid');
        $SubTotal = (float)$CheckoutData['SubTotal'];
        $AllCharges = $CheckoutData['Charges'];

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

        $w_user_type = Session::get('eusertype');
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
            'payment_type' 		=> "PAYMENT_STRIPE",
            'payment_method' 	=> "Credit Card",
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
        $payment_intent_id = $request->payment_intent_id;
        $order_id = $request->order_id;

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

        return response()->json([
            'status' => true,
            'message' => 'Payment has been completed.',
        ]);
    }

}