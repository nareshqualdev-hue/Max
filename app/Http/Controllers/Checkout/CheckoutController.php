<?php

namespace App\Http\Controllers\Checkout;
use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * One Page Checkout new test1
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

        $result['CSSFILES'] = ['components.css','checkout-new.css'];

        return view('newcheckout.checkout-page')->with(
            $result['data']
        );
    }
}
