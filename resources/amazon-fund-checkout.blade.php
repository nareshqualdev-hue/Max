@extends('layouts.app')
@section('content')
    <main role="main" id="main-content" tabindex="-1" aria-label="Amazon Fund Checkout Main Content">
        <section class="checkout-page pb-md-5" role="region" aria-label="Amazon Fund Checkout Section">
            <div class="container">
                <div class="chekout-top">
                    <div class="mainhd text-left pt-4 pb-4 pt-lg-5 pb-lg-5">
                        <h2 id="checkout-heading">Checkout</h2>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="row pr-lg-4 pr-0">
                            <div class="col-12">
                                @if(Session::has('PlaceOrderError'))
                                    <x-message :attr="[ 'classname' => 'alert alert-danger', 'message' => Session::get('PlaceOrderError'), 'mid' => 'error_order']"/>
                                @endif
                                <form id="frmamazon" method="post" action="{{url('/amazon-fund-process')}}" aria-labelledby="checkout-heading">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="PaymentMethod" value="PAYMENT_PAYWITHAMAZON"/>
                                    <input type="hidden" name="page_from" id="page_from" value="fund"/>
                                    <ul class="checkout-steps" role="list" aria-label="Checkout Steps">
                                        <li class="bill-info active" role="listitem">
                                            <div class="mb-0 mb-md-4">
                                                <h3 class="cart-hd d-none d-md-block" id="billing-shipping-heading">Billing Shipping Information</h3>
                                                <div id="addressBookWidgetDiv" class="mt-3" style="height:300px;" aria-label="Amazon Address Book"></div>
                                            </div>
                                        </li>
                                        <li class="payment-method active" role="listitem">
                                            <h3 class="cart-hd d-none d-md-block" id="payment-info-heading">Payment Info</h3>
                                            <div id="walletWidgetDiv" class="mt-3 mb-3" style="height:300px;" aria-label="Amazon Wallet"></div>
                                            <a href="javascript:void(0);" class="btn btn-primary" id="btnAmazonOrder" role="button" aria-label="Place Amazon Order">Place Order</a>
                                        </li>
                                    </ul>
                                </form>	
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mt-4 mt-lg-0">
                        <div class="max_need_help mt-4 d-none d-md-block" role="region" aria-label="Need Help Section">
                            <h4 id="need-help-heading">Need Help? <a href="contact-us.html" class="">Contact Us</a></h4>
                            <ul role="list" aria-labelledby="need-help-heading">
                                <li role="listitem">
                                    <img src="{{config('global.SITE_IMAGES')}}phon.jpg" width="23" height="23" alt="Phone Icon" class="left">
                                    <small class="">
                                        <a href="tel:{{config('Settings.TOLL_FREE_NO')}}" aria-label="Call Toll Free Number">{{config('Settings.TOLL_FREE_NO')}}</a> (Toll Free), 
                                        <a href="tel:{{config('Settings.INTERNATIONAL_PHONE_NO')}}" aria-label="Call International Number">{{config('Settings.INTERNATIONAL_PHONE_NO')}}</a> (Outside of USA)<br>
                                    </small>
                                </li>
                                <li role="listitem">
                                    <svg class="svg-whatsapp vam left" aria-hidden="true" role="img" width="20" height="20">
                                        <use href="#svg-whatsapp" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-whatsapp"></use>
                                    </svg>
                                    <small class="">
                                        <a href="tel:{{config('Settings.TOLL_FREE_NO')}}" aria-label="Text Toll Free Number">{{config('Settings.TOLL_FREE_NO')}}</a> (Text Number)
                                    </small>
                                </li>
                                <li role="listitem">
                                    <img src="{{config('global.SITE_IMAGES')}}email.jpg" width="23" height="23" alt="Email Icon" class="left">
                                    <small>
                                        <a href="mailto:{{config('Settings.ADMIN_MAIL')}}" class="grey_text" aria-label="Email Us">{{config('Settings.ADMIN_MAIL')}}</a>
                                    </small>
                                </li>
                            </ul>
                        </div> 
                    </div> 
                </div>
            </div>
        </section>
    </main>	
@endsection