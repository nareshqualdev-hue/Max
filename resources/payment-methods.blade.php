<style>
    .clsgreen{color:green;font-weight: 600;}
</style>
@if($Is_Afterpay_Checkout == "Yes")
    <script>
        // ensure this function is defined before loading afterpay.js
        function initAfterpayCheckout() {
            setTimeout(function() {
                AfterPay.initializeForPopup({
                    countryCode: 'US',
                    onCommenceCheckout: function(actions) {
                        /* retrieve afterpay token from your server */
                        /* then call `actions.resolve(token)` */
                        // actions.resolve('$Afterpay_Token');

                        $.ajax({
                            type: "POST",
                            url: site_url + "afterpay/placeorder_express",
                            headers: {
                                'X-CSRF-TOKEN': token
                            },
                            data: "get_token=1",
                            dataType: "JSON",
                            success: function(response) {
                                if (response.success == 1 && response.token != "") {
                                    actions.resolve(response.token);
                                } else {
                                    alert(response.message);
                                    // location.reload();
                                    return false;
                                }
                            }
                        })
                    },
                    onShippingAddressChange: function(data, actions) {
                        /* address in `data` */
                        /* calc options, then call `actions.resolve(options)` */
                        // console.log(data);

                        actions.resolve({!!$Shipping_Arr_AP!!})
                    },
                    onComplete: function(event) {
                        $("#page-spinner").show()
                        // console.log(event);
                        /* handle success/failure of checkout */
                        if (event.data.status == "SUCCESS") {
                            // The consumer has confirmed the payment schedule.
                            // Call your server here to retrieve the order details
                            var order_token = event.data.orderToken;
                            var merchant_reference = event.data.merchantReference;

                            $.ajax({
                                type: "GET",
                                url: site_url + "afterpay/billing_checkout_express/2/" + order_token,
                                headers: {
                                    'X-CSRF-TOKEN': token
                                },
                                dataType: "JSON",
                                success: function(res) {
                                    if (res == 1) {
                                        $("input[name='PaymentMethod']").after('<input type="hidden" name="is_btm_ap_chkout" value="Yes">')
                                        $("#btnPlaceOrder").trigger("click");
                                    } else {
                                        $("#pschecksum").val('');
                                        $("#ap_psChecksum").val('');
                                        document.location = site_url + "checkout/view";
                                        return false;
                                    }
                                }
                            })
                            return false;
                        } else {
                            var order_token = "undefined";

                            // The consumer cancelled the payment or closed the popup window.
                            location.href = site_url + "afterpay/billing_checkout_express/0/" + order_token;
                            return false;
                        }
                    },
                    target: '#afterpay-button',
                    shippingOptionRequired: true,
                    buyNow: true,
                })
            }, 500);
        }
    </script>
    <!-- <script type="text/javascript" src="https://static-us.afterpay.com/javascript/present-afterpay.js"></script> -->
    <script src="{!! $token_js_url !!}" async onload="initAfterpayCheckout()"></script>
@endif

<form method="post" action="{{route('placeorder')}}" name="order_process" id="order_process" aria-labelledby="paymentHeading" role="form">
    <input type="hidden" name="shipping_signature" id="shipping_signature" value="{{$shipping_signature}}" />
    <div class="row">
        <div class="col-12 d-md-none" aria-hidden="true">
            <div class="mobile-shadow mb-0">&nbsp;</div>
        </div>
        <div class="col-12">
            <h4 class="cart-hd-sub text-center" id="paymentHeading">Payment</h4>
        </div>
        <div class="col-12">
            @if($show=="Yes" || $MethodNoShow=="Yes")
                <div class="pt-3" role="alert" aria-live="assertive">
                    <div class="alert alert-danger">
                        To complete the order, Please use 'Checkout with Paypal' OR 'Pay with Amazon' payment option on shopping cart page. <a href="{{url('/shoppingcart/view')}}" aria-label="Go to shopping cart">click here </a> to go shopping cart.
                    </div>
                </div>
            @else
                <div>
                    @if(count($PaymentMethodList) > 0)
                        @if($OnlyWT == 1 && Session::get('eusertype') == "Retailer")
                            <div class="alert alert-danger" role="alert" aria-live="assertive">
                                Checkout is temporarily unavailable at this time, Please contact administrator.
                            </div>
                        @else
                            @if($onlyAmazonPaypal != 1)
                                <input type="hidden" name="shipsignatureflag" id="shipsignatureflag" value="" />
                                <input type="hidden" name="ap_psChecksum" id="ap_psChecksum" value="">
                                {{ csrf_field() }}
                                @if($Is_Afterpay_Checkout == 'No')
                                    <div style="display:none;">
                                        <div id="shoptotal" style="display:none;">{{$NetTotal}}</div>
                                    </div>
                                @endif
                                <div class="pb-2 mb-1" aria-live="polite">All transactions are secure and encrypted.</div>
                                <fieldset>
                                    <legend class="sr-only">Select Payment Method</legend>
                                    @foreach($PaymentMethodList as $pkey => $PMethod)
                                        @if((Session::get('eusertype') == "" || Session::get('eusertype') == "Retailer") && $PMethod['pm_name'] != "Wire Transfer" && $PMethod['pm_group_name'] !='PAYMENT_GIFT_CERTIFICATE')
                                            <div class="payment-stripe">
                                                <label @if($NetTotal <=0 ) style="display:none !important;" @endif class="comcheck radio checkbox-label pay-radio card-pay credit-methods w-100" for="paytypeID{{$pkey}}" role="radio" aria-checked="@if($NetTotal> 0 && ((isset($arrPaymentDetail) && $arrPaymentDetail['Payment_Type'] == $PMethod['pm_group_name']) || $SelMethod == $PMethod['pm_group_name'] || $pkey==0 || $ptype == 'AP'))true @else false @endif">
                                                    <div class="chebox">
                                                        <input type="radio" name="PaymentMethod" id="paytypeID{{$pkey}}" value="{{$PMethod['pm_group_name']}}" @if($NetTotal> 0 && ((isset($arrPaymentDetail) && $arrPaymentDetail['Payment_Type'] == $PMethod['pm_group_name']) || $SelMethod == $PMethod['pm_group_name'] || $pkey==0 || $ptype == 'AP')) checked @endif aria-labelledby="label-paytypeID{{$pkey}}">
                                                        <span class="checkmark"></span>
                                                        <span class="fw500" id="label-paytypeID{{$pkey}}">{{$PMethod['pm_name']}}
                                                            @if($PMethod['pm_group_name'] == 'PAYMENT_STRIPE')
                                                            <img src="{{config('global.SITE_IMAGES')}}/all-card.png" alt="All major cards accepted" class="img-fluid all-card">
                                                            @endif
                                                        </span>
                                                    </div>
                                                    @if($PMethod['pm_group_name'] == 'PAYMENT_PAYWITHAFTERPAY')
                                                    <div id="shoptotal" style="display:none;">{{$NetTotal}}</div>
                                                    @endif
                                                </label>
                                                @if($PMethod['pm_group_name'] == 'PAYMENT_STRIPE')
                                                    <div class="strip-logodiv">
                                                        <img src="{{config('global.SITE_IMAGES')}}powered-by-strip-logo.png" alt="Guaranteed safe & secure checkout powered by stripe" class="">
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                        @if(Session::get('eusertype') == "Wholesaler")
                                            <label @if($NetTotal <=0 ) style="display:none !important;" @endif class="comcheck radio checkbox-label pay-radio card-pay credit-methods" for="paytypeID{{$pkey}}" role="radio" aria-checked="@if($NetTotal> 0 && ((isset($arrPaymentDetail) && $arrPaymentDetail['Payment_Type'] == $PMethod['pm_group_name']) || $SelMethod == $PMethod['pm_group_name'] || $pkey==0 || $ptype == 'AP'))true @else false @endif">
                                                <div class="chebox">
                                                    <input type="radio" checked name="PaymentMethod" id="paytypeID{{$pkey}}" value="{{$PMethod['pm_group_name']}}" @if($NetTotal> 0 && ((isset($arrPaymentDetail) && $arrPaymentDetail['Payment_Type'] == $PMethod['pm_group_name']) || $SelMethod == $PMethod['pm_group_name'] || $pkey==0 || $ptype == 'AP')) checked @endif aria-labelledby="label-paytypeID{{$pkey}}">
                                                    <span class="checkmark"></span>
                                                    <span class="fw500" id="label-paytypeID{{$pkey}}">{{$PMethod['pm_name']}}
                                                        @if($PMethod['pm_group_name'] == 'PAYMENT_STRIPE')
                                                        <img src="{{config('global.SITE_IMAGES')}}/all-card.png" alt="All major cards accepted" class="img-fluid all-card">
                                                        @endif
                                                    </span>
                                                </div>
                                                @if($PMethod['pm_group_name'] == 'PAYMENT_PAYWITHAFTERPAY')
                                                <div id="shoptotal" style="display:none;">{{$NetTotal}}</div>
                                                @endif
                                            </label>
                                        @endif
                                    @endforeach
                                </fieldset>
                                <label id="PaymentDetailsStoreView" @if($NetTotal !=0) style="display:none !important;" @endif class="comcheck radio d-inline-block w-100 checkbox-label pay-radio card-pay" for="paytypeID0" role="radio" aria-checked="false">
                                    <div class="chebox">
                                        @if($OnlyGiftCert==1)
                                        <input type="radio" name="PaymentMethod" id="paytypeID0" value="PAYMENT_GIFT_CERTIFICATE" aria-labelledby="label-paytypeID0">
                                        @else
                                        <input type="radio" name="PaymentMethod" id="paytypeID0" value="PAYMENT_CL" aria-labelledby="label-paytypeID0">
                                        @endif

                                        <span class="checkmark"></span>
                                        <span class="float-left w-100" id="label-paytypeID0">
                                            @if($OnlyGiftCert==1)
                                            Gift Certificate
                                            @else
                                            Credit Limit
                                            @endif
                                        </span>
                                    </div>
                                </label>
                                <!-- express checkout bottom buttons-->
                                @if($is_afterpay != "yes" && $NetTotal > 0)
                                    <div class="express-ck express-ck-inner text-center mt-md-3" role="region" aria-labelledby="expressCheckoutHeading">
                                        <div class="row">
                                            <div class="col-12">
                                                <h3 class="cart-hd p-0" id="expressCheckoutHeading">Express Checkout</h3>
                                            </div>
                                            <style>.express-ck ul li a{padding:4px;width:150px;height:45px;}</style>
                                            <div class="col-12">
                                                <ul class="pt-md-2" role="list" aria-label="Express Checkout Options">
                                                    <li style="width:100%;" role="listitem">
                                                        @if($Is_Afterpay_Checkout == "Yes" && $SelPayMethod != 'paypal')
                                                        <a id="afterpay-button" data-afterpay-entry-point="cart" class="btn btn-primary" href="javascript:void(0)" aria-label="Pay with Afterpay">
                                                            <img src="{{config('global.SITE_URL')}}images/afterpay.svg" height="32" style="width:120px;padding:3px;" alt="Afterpay">
                                                        </a>
                                                        @endif
                                                    </li>
                                                    <!-- <li>
                                                        @if($PaypalPayButton == 'Yes')
                                                        <a class="paypal-btn btn btn-primary inner-paybtn d-none" href="{{url('paypal/placeorder')}}">Pay with Paypal</a>
                                                        <div id="paypal-button-container"></div>
                                                        @endif
                                                    </li> -->
                                                    <li role="listitem">
                                                        @if($AmazonPayButton == 'Yes' && $CartAttr['onlyGCPurchased'] != '1')
                                                        <a class="amazon-btn btn btn-primary inner-paybtn d-none" href="javascript:void(0);" aria-label="Pay with Amazon">Pay with Amazon</a>
                                                        <div id="AmazonPayButtonAll"></div>
                                                        <script type="text/javascript">
                                                            window.onAmazonLoginReady = function() {
                                                                amazon.Login.setClientId("{{config('CLIENT_ID')}}");
                                                                amazon.Login.setUseCookie(true);
                                                            };
                                                            var call_url = "{{config('CALLBACK_CHECKOUT_URL')}}";
                                                        </script>
                                                        <script type="text/javascript" src="{{config('JS_SERVER_URL')}}"></script>
                                                        <script>
                                                            var authRequest;
                                                            OffAmazonPayments.Button("AmazonPayButtonAll", "{{config('MERCHANT_ID')}}", {
                                                                type: "pay",
                                                                size: "large",
                                                                authorization: function() {
                                                                    loginOptions = {
                                                                        scope: "profile postal_code payments:widget payments:shipping_address",
                                                                        popup: true
                                                                    };
                                                                    authRequest = amazon.Login.authorize(loginOptions, call_url);
                                                                },
                                                                onError: function(error) {
                                                                    // something bad happened
                                                                }
                                                            });
                                                        </script>
                                                        @endif
                                                    </li>
                                                    <li role="listitem">
                                                        <div class="text-center inner-wallet-payment" id="payment-request-button"></div>
                                                        <input type="hidden" name="is_stripe_wallet" id="is_stripe_wallet" value="" />
                                                        <input type="hidden" name="is_stripe_applepay" id="is_stripe_applepay" value="" />
                                                        <input type="hidden" name="is_paypal" id="is_paypal" value="{{$is_paypal}}" />
                                                        <input type="hidden" name="is_step_gpay" id="is_step_gpay" value="LastStep" />
                                                    </li>
                                                    <li style="width:73%;" role="listitem">
                                                        @if($PaypalPayButton == 'Yes')
                                                        <div id="paypal-button-container-checkout" style="position: relative;z-index:1;"></div>
                                                        <div data-pp-message data-pp-style-layout="text" data-pp-style-logo-type="inline" data-pp-style-text-color="black" data-pp-style-text-size="12" data-pp-amount="{{$NetTotal}}" data-pp-placement=product></div>
                                                        @endif
                                                    </li>
                                                </ul>
                                                @if(count($PaymentMethodList) > 0 && $onlyAmazonPaypal != 1 && $CartAttr['CreditLimitFlag'] != 0)
                                                <label class="checkbox-label che-box mt-3" for="chkcreditlimit">
                                                    <div class="chebox">
                                                        @if($CreditDiscount > 0 && $CartAttr['CreditLimitFlag'] == 2)
                                                        <input type="checkbox" checked name="chkcreditlimit" id="chkcreditlimit" value="{{$CartAttr['RemainCreditLimit']}}" @if($CartAttr['onlyGCPurchased']=='1' ) disabled @endif aria-checked="true" aria-label="Use your account balance" />
                                                        <span class="checkmark"></span>
                                                        <span class="float-left w-100" id="credamt">
                                                            @if($CartAttr['RemainCreditLimit'] > 0)
                                                            Use your account balance, Your account balance is : {{Price($CartAttr['RemainCreditLimit'])}}
                                                            @else
                                                            Your account balance has been applied.
                                                            @endif
                                                        </span>
                                                        @else
                                                        <input type="checkbox" name="chkcreditlimit" id="chkcreditlimit" value="{{$CartAttr['CreditLimit']}}" aria-checked="false" aria-label="Use your account balance" />
                                                        <span class="checkmark"></span>
                                                        <span class="float-left w-100" id="credamt">
                                                            Use your account balance, Your account balance is : {{Price($CartAttr['CreditLimit'])}}
                                                        </span>
                                                        @endif
                                                    </div>
                                                </label>
                                                @endif
                                            </div>
                                            <div class="col-12" style="display:none;" aria-hidden="true">
                                                @if($Is_Afterpay_Checkout == "Yes" && $SelPayMethod != 'paypal')
                                                    <a id="afterpay-button" data-afterpay-entry-point="cart" class="btn btn-primary inner-paybtn" href="javascript:void(0)" aria-label="Pay with Afterpay">Pay with Afterpay</a>
                                                @endif
                                                @if($PaypalPayButton == 'Yes')
                                                    <a class="paypal-btn btn btn-primary inner-paybtn" href="{{url('paypal/placeorder')}}" aria-label="Pay with Paypal">Pay with Paypal</a>
                                                @endif
                                            </div>
                                            @if(config('global.StripeButton') == 'Show')
                                                <div class="col-12 mt-3 text-center" id="payment-request-button"></div>
                                            @endif
                                            <input type="hidden" name="is_stripe_wallet" id="is_stripe_wallet" value="" />
                                            <input type="hidden" name="is_stripe_applepay" id="is_stripe_applepay" value="" />
                                            <input type="hidden" name="is_paypal" id="is_paypal" value="{{$is_paypal}}" />
                                            <input type="hidden" name="is_step_gpay" id="is_step_gpay" value="LastStep" />
                                        </div>
                                    </div>
                                @endif
                                <!-- express checkout bottom buttons-->
                            @endif
                        @endif
                    @else
                        <div class="alert alert-danger" role="alert" aria-live="assertive">
                            @if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') =='Wholesaler')
                                You do not have sufficient balance in your dropship account. To complete your order, please <a href="javascript:void(0)" class="add-fund" aria-label="Add Fund">Add Fund</a> to your account.
                            @else
                                Checkout is temporarily unavailable at this time, Please contact administrator.
                            @endif
                        </div>
                    @endif
                    @if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') =='Wholesaler' && isset($DropshipperAccountDetails))
                        <div class="row" role="region" aria-labelledby="dropshipperInfoHeading">
                            <div class="col-lg-12 mt-0 mt-md-4">
                                <div class="max_coupon_box">
                                    <div class="coupan_boxhd">
                                        <div class="col-12 text-center">
                                            <div id="dropshipperInfoHeading">Dropshipper Information</div>
                                            @if(isset($DropshipperAccountDetails['fund_msg']) && $DropshipperAccountDetails['fund_msg'] != "")
                                                <div class="alert alert-danger" role="alert" aria-live="assertive">
                                                    {{$DropshipperAccountDetails['fund_msg']}}
                                                </div>
                                            @endif
                                            <table width="100%" border="0" aria-label="Dropshipper Account Details">
                                                <tr>
                                                    <th scope="col">Total Fund</th>
                                                    <th scope="col">Required Amount</th>
                                                    <th scope="col">
                                                        @if(isset($DropshipperAccountDetails['fund_available']) && $DropshipperAccountDetails['fund_available'] == 'Yes')
                                                            Remaining Fund
                                                        @else
                                                            Required Fund
                                                        @endif
                                                    </th>
                                                </tr>
                                                <tr>
                                                    <td align="center">{{Price($DropshipperAccountDetails['total_fund'])}}</td>
                                                    <td align="center">{{Price($DropshipperAccountDetails['total_payment'])}}</td>
                                                    <td align="center">
                                                        @if(isset($DropshipperAccountDetails['fund_available']) && $DropshipperAccountDetails['fund_available'] == 'Yes')
                                                            {{Price($DropshipperAccountDetails['remaining_fund'])}}
                                                        @else
                                                            {{Price($DropshipperAccountDetails['required_fund'])}} | <a href="javascript:void(0);" class="add-fund" aria-label="Add Fund">Add Fund</a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if(count($PaymentMethodList) > 0 && $onlyAmazonPaypal != 1 && $CartAttr['CreditLimitFlag'] != 0)
                        <!-- Your account checkbox div was here before new branded button design -->
                    @endif
                </div>
                @if(count($PaymentMethodList) > 0 && $onlyAmazonPaypal != 1)
                    @if($giftflag == 1)
                        <div class="gift-card" role="region" aria-labelledby="giftCardHeading">
                            <div class="actmenuhd" id="giftCardHeading">
                                <svg class="svg-newgift vam" aria-hidden="true" role="img" width="24" height="24">
                                    <use href="#svg-newgift" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-newgift"></use>
                                </svg>
                                <span>Add gift card</span>
                                <svg id="giftcard-down-arraw" class="sv-down-arrow vam float-right" aria-hidden="true" role="img" width="20" height="20">
                                    <use href="#sv-down-arrow" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-down-arrow"></use>
                                </svg>
                            </div>
                            <div class="actmenu-inner mt-4" id="divgiftcard">
                                <form name="frmgiftcard" id="frmgiftcard" aria-labelledby="giftCardHeading">
                                    <div class="row pt-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="col-form-label" for="gift_from">From<span class="errmsg">*</span></label>
                                                <input id="gift_from" name="gift_from" type="text" class="form-control" value="{{Session::get('ShoppingCart.GiftFrom')}}" aria-required="true" aria-label="Gift From">
                                                <x-message :attr="['classname' => 'frmerror', 'message' => '', 'mid' => 'error_gift_from']" />
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="gift_to">To<span class="errmsg">*</span></label>
                                                <input id="gift_to" name="gift_to" type="text" class="form-control" value="{{Session::get('ShoppingCart.GiftTo')}}" aria-required="true" aria-label="Gift To">
                                                <x-message :attr="['classname' => 'frmerror', 'message' => '', 'mid' => 'error_gift_to']" />
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="gift_message_customer" class="col-form-label">Gift Message</label>
                                                <textarea id="gift_message_customer" name="gift_message_customer" rows="4" cols="50" type="text" class="form-control" aria-label="Gift Message">{{Session::get('ShoppingCart.GiftMessageCustomer')}}</textarea>
                                                <x-message :attr="['classname' => 'frmerror', 'message' => '', 'mid' => 'error_gift_message_customer']" />
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="freegiftvalue">Select Gift<span class="errmsg">*</span></label>
                                                {!! $freegiftcombo !!}
                                                <x-message :attr="['classname' => 'frmerror', 'message' => '', 'mid' => 'error_freegiftvalue']" />
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-actions clearfix pt-0 pb-0">
                                                <div class="float-left">
                                                    <a href="javascript:void(0);" id="btnappygiftcard" class="btn btn-primary" aria-label="Apply Gift Card">Apply</a>
                                                    <a href="#" class="ulink cancel-btn" aria-label="Cancel Gift Card">Cancel</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                    @if($OnlyWT == 1 && Session::get('eusertype') == "Retailer")
                    @else
                        <div class="row">
                            @if($dropshipFundSection == 'No')
                                <div class="col-12">
                                    <div class="form-group" style="margin-bottom:9px !important;">
                                        <h4 class="cart-hd-sub text-center pt-4 mt-md-3 mb-md-2 pb-4 fw500" id="specialRequestHeading">Special Request</h4>
                                        <textarea id="customer_comment" class="form-control" name="customer_comment" placeholder="Special Request" aria-labelledby="specialRequestHeading"></textarea>
                                    </div>
                                </div>
                            @endif
                            <div class="col-12 pb-md-3">
								<label>This request and notes are optional. We’ll do our best to accommodate your request, but it’s not guaranteed.</label>
                                <label class="checkbox-label che-box" for="chkagree">
                                    <div class="chebox">
                                        <input type="checkbox" checked id="chkagree" aria-checked="true" aria-label="Agree to terms and conditions"><span class="checkmark"></span>
                                        <span>By placing and order on MaxAroma.com you agree with the <a href="{{url('/terms-and-conditions.html')}}" target="_blank" aria-label="Terms and Conditions">terms and conditions</a>.</span>
                                    </div>
                                </label>
                            </div>
                            <div class="col-12 d-md-none" aria-hidden="true">
                                <div class="mobile-shadow">&nbsp;</div>
                            </div>
                            <div class="col-12 pt-md-3 pb-md-5">
                               <div class="chekout-main-btn">
                                     @if(Session::has('ShowStockLeftMessage') && Session::get('ShowStockLeftMessage') == 'Yes')
                                    <div class="clsgreen pb-2 text-center">Your items are almost sold out - secure them now.</div>
                                    @endif
                                    <a href="javascript:void(0);" class="btn btn-primary d-block checkout-btn" id="btnPlaceOrder" role="button" aria-label="Place Order">Place Order</a>
                                </div>
                            </div>
                            <div class="col-12 f12 text-center">
                                <a href="{{config('global.SITE_URL')}}shipping" class="ulink" aria-label="Return to Shipping"><strong>Return to Shipping</strong></a>
                            </div>
                        </div>
                    @endif
                @endif
            @endif
            <input type="hidden" name="amazon_callback" id="amazon_callback" value="{{config('CALLBACK_CHECKOUT_URL')}}" aria-hidden="true" />
            <input type="hidden" id="amazon_fund_callback" value="{{config('CALLBACK_URL')}}" aria-hidden="true" />
        </div>
    </div>
</form>

<!-- @if($PaypalPayButton == 'Yes')
<script src="https://www.paypal.com/sdk/js?client-id=ATtuGj0FvDJqZDzPKYnfx13ovzfCod0CF_L-V87mX8Lnm0SV32vycXfwJHriS41I6YyCd5tBfxQ1dA8j&components=buttons"></script>
<script type="text/javascript">
    var CREATE_PAYMENT_URL  = '{{url('paypal/placeorder')}}';
    paypal.Buttons({
        onInit(data, actions) {
            actions.disable();
        },
        onClick: function() {
            window.location.href = CREATE_PAYMENT_URL;
        },
        style: {
            layout: 'horizontal',
            color:  'gold',
            shape:  'rect',
            label:  'paypal',
            height: 45
        }
    }).render('#paypal-button-container');
</script>
@endif -->
