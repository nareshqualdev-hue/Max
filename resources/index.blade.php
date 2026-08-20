@extends('layouts.app')
@section('content')
    <div class="showDwnMessage" style="display:none" role="status" aria-live="polite">Please wait while your transaction is being processed.... Please do not refresh the page.</div>
    @include('checkout.checkout-header')
    @if(!Session::has('PayPalToken') && $NetTotal > 0)
        @if(Session::has('sess_storeuserid'))
            <div class="col-12">
                <div class="express-ck text-center">
                    @include('checkout.pos.agent-info',[$CartAttr])
                </div>
            </div>
        @endif
        @if(!Auth::guard('store')->check())
        <div class="col-12 step1" style="{{$show_other_payments_checkout}}" role="region" aria-label="Express Checkout Section">
            <div class="express-ck text-center">
                <h3 class="cart-hd" id="express-checkout-heading">Express Checkout</h3>
                @include('checkout.checkout-express',[$CartAttr])
            </div>
        </div>
        @endif
        @if(Auth::guard('store')->check())
            <div class="col-12"><hr></div>
            <div id="poscustsection" class="col-12 pt-2 pb-2 step1 text-center" style="{{$show_other_payments_checkout}}" role="region" aria-label="Alternative Payment Section">
                <h3 class="cart-hd">Customer Information</h3>
            </div>
        @else
            <div class="col-12 pt-3 @if(Auth::guard('store')->check()) pb-3 @endif step1" style="{{$show_other_payments_checkout}}" role="region" aria-label="Alternative Payment Section">
                <div class="middle-border"><span> @if(Auth::guard('store')->check()) Customer Information @else Or pay by card below @endif</span></div>
            </div>
        @endif
    @endif

    @if(!Auth::guard('store')->check())
    <div class="col-12 text-center" id="newacc" style="{{$show_have_acc_div}}" role="region" aria-label="Account Sign In Section">
        <input type="hidden" name="checkloginPop" id="checkloginPop" value="1">
        <h4 class="cart-hd-sub text-center" id="signin-heading">
            Checkout as Guest or <a href="javascript:void(0);" data-action="sign_in" class="ulink signinsignup" aria-label="Sign In">Sign In</a>
            <div class="pt-2"><small>Enter your email to continue as a guest. You'll have the option to create an account later.</small></div>
            <span id="duplicate_ip" class="frmerror frmerror_shw" role="alert" style="padding-top:20px; font-weight:normal; display:none;"></span>
        </h4>
    </div>
    @endif
    <div class="col-12" role="region" aria-label="Checkout Steps Section">
        <div class="checkout-cont">
            @if(Session::has('PlaceOrderError'))
                <x-message :attr="[ 'classname' => 'alert alert-danger', 'message' => Session::get('PlaceOrderError'), 'mid' => 'error_order']"/>
            @endif

            <ul class="checkout-steps" role="list" aria-label="Checkout Steps">
                @if(!Session::has('PayPalToken'))
                <li id="guestcheckout" style="{{$show_guest_checkout_div}}" role="listitem" aria-label="Guest Checkout">
                    @include('checkout.guest')
                </li>
                @endif

                <li class="billtab bill-info" style="{{$show_billing_info_div}}" role="listitem" aria-label="Billing and Shipping Information">
                    @include('checkout.billing-shipping',[$CartDetails,$CartAttr,$SelPayMethod])
                </li>
            </ul>
        </div>
    </div>
    @include('checkout.checkout-footer')
@endsection
