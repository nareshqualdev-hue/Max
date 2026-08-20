<main role="main" id="main-content" tabindex="-1" aria-label="Customer Checkout Main Content">
    <section class="checkout-page pb-md-5" role="region" aria-label="Customer Checkout Section">
        <div class="container">
            <div class="chekout-top">
                <div class="mainhd text-left pt-4 pb-4 pt-lg-5 pb-lg-5">
                    <h2 id="checkout-heading">Checkout</h2>
                </div>
                <nav class="progress" aria-label="Checkout Progress">
                    <ul role="list" aria-label="Checkout Steps">
                        <li class="active" id="step1" role="listitem">
                            <span class="prog-circal">
                                <svg class="sv-chcek vam" aria-hidden="true" role="img" width="13" height="10">
                                    <use href="#sv-chcek" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-chcek"></use>
                                </svg>
                            </span> <span>Billing & Shipping Address</span>
                        </li>
                        <li id="step2" role="listitem">
                            <span class="prog-circal">
                                <svg class="sv-chcek vam" aria-hidden="true" role="img" width="13" height="10">
                                    <use href="#sv-chcek" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-chcek"></use>
                                </svg>
                            </span> <span>Shipping Option</span>
                        </li>
                        <li id="step3" role="listitem">
                            <span class="prog-circal">
                                <svg class="sv-chcek vam" aria-hidden="true" role="img" width="13" height="10">
                                    <use href="#sv-chcek" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-chcek"></use>
                                </svg>
                            </span> <span>Payment Info</span>
                        </li>
                    </ul>
                </nav>
            </div>
            <div class="row">
                <div class="col-lg-8">
                    <div class="row pr-lg-4 pr-0">
                        <div class="col-12">
                            <ul class="checkout-steps" role="list" aria-label="Checkout Steps Details">
                                <li id="billshipinfo" style="display:none" role="listitem"></li>
                                <li class="billtab bill-info active" role="listitem" aria-label="Billing and Shipping Information">
                                    @include('checkout.billing-shipping',[$CartDetails])
                                </li>
                                @if($CartAttr['onlyGCPurchased'] == 0)
                                <li id="shipinfo" style="display:none" role="listitem">
                                    @include('checkout.shippinginfo',[$CartDetails])
                                </li>
                                <li class="billtab shipping-method" role="listitem" aria-label="Shipping Option">
                                    <h3 class="cart-hd d-none d-md-block" id="shipping-option-heading">Shipping Option</h3>
                                    <h3 class="cart-hd d-md-none">2. Shipping Method</h3>
                                </li>
                                @endif
                                <li class="billtab payment-method" role="listitem" aria-label="Payment Information">
                                    <h3 class="cart-hd d-none d-md-block" id="payment-info-heading">Payment Info</h3>
                                    <h3 class="cart-hd d-md-none">3. Payment</h3>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mt-4 mt-lg-0">
                    <aside class="order-summry p-3 p-md-4" role="complementary" aria-label="Order Summary">
                        <div class="row">
                            <div class="col-12" id="cartsubtotal">
                                @include('checkout.subtotalbox',[$CartDetails])
                            </div>
                        </div>
                    </aside>

                    <div class="row">
                        <div class="col-12 mt-3">
                            <div class="max_coupon_box" role="region" aria-label="Promotional or Coupon Code Section">
                                <div class="coupan_boxhd">
                                    <div class="float-left w-50 text-left">
                                        <h6 id="promo-heading">Promotional/Coupon Code</h6>
                                    </div>
                                    <div class="float-right w-50 text-right">
                                        <a href="javascript:void(0);" class="more-link" aria-label="View promo codes" role="button">
                                            View promo codes 
                                            <svg class="sv-back vam" aria-hidden="true" role="img" width="14" height="14">
                                                <use href="#sv-back" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-back"></use>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="pb-3 pb-md-4">
                                    <div class="cart-discount">
                                        <label for="couponCode" class="sr-only">Enter Coupon Code</label>
                                        <input type="text" id="couponCode" placeholder="Enter Coupon Code" class="form-control" aria-label="Enter Coupon Code">
                                        <a href="#" class="btn btn-primary" aria-label="Apply Coupon Code" role="button">Apply</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chekout-main-btn mt-0 mt-md-3" role="region" aria-label="Checkout Main Button">
                        <div class="row">
                            <div class="col-4 text-left d-md-none">
                                <span aria-label="Total Items in Cart">{{$CartDetails['TotalItemInCart']}} items</span>
                            </div>
                            <div class="col-8 text-right d-md-none">
                                <strong aria-label="Estimated Total">Estimated Total : {{Price($NetTotal)}}</strong>
                            </div>
                            <div class="col-12 mt-3 mt-md-0">
                                <a class="btn btn-secondary d-block checkout-btn" href="#" role="button" aria-label="Ship To This Address">Ship To This Address</a>
                            </div>
                        </div>
                    </div>

                    <div class="your-bag mt-3 mb-4" role="region" aria-label="Your Bag Section">
                        @include('checkout.cart_table',[$CartDetails])
                    </div>

                    <div class="max_need_help mt-4 d-none d-md-block" role="region" aria-label="Need Help Section">
                        <h4 id="need-help-heading">Need Help? <a href="contact-us.html" class="">Contact Us</a></h4>
                        <ul role="list" aria-labelledby="need-help-heading">
                            <li role="listitem">
                                <img src="{{config('global.SITE_IMAGES')}}phon.jpg" alt="Phone Icon" class="left">
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
                                <img src="{{config('global.SITE_IMAGES')}}email.jpg" alt="Email Icon" class="left">
                                <small>
                                    <a href="mailto:{{config('Settings.ADMIN_MAIL')}}" class="grey_text" aria-label="Email Us">{{config('Settings.ADMIN_MAIL')}}</a>
                                </small>
                            </li>
                        </ul>
                    </div> 
                </div> 
            </div>
        </div>
        <input type="hidden" name="onlyGCPurchased" id="onlyGCPurchased" value="{{$CartAttr['onlyGCPurchased']}}"/>
        <input type="hidden" name="IsVenderItem" id="IsVenderItem" value="{{$CartAttr['IsVenderItem']}}"/>
        <input type="hidden" name="IsCosmo" id="IsCosmo" value="{{$CartAttr['IsCosmo']}}"/>
        <input type="hidden" name="IsNandansons" id="IsNandansons" value="{{$CartAttr['IsNandansons']}}"/>
        <input type="hidden" name="IsPerfumePW" id="IsPerfumePW" value="{{$CartAttr['IsPerfumePW']}}"/>
        <input type="hidden" name="IsPCA" id="IsPCA" value="{{$CartAttr['IsPCA']}}"/>
        <input type="hidden" name="IsND" id="IsND" value="{{$CartAttr['IsND']}}"/>
        <input type="hidden" name="IsMaxaromaTwoDelivery" id="IsMaxaromaTwoDelivery" value="{{$CartAttr['IsMaxaromaTwoDelivery']}}"/>
    </section>
</main>
