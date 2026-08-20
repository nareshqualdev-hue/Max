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
						console.log(data);

					},
					onComplete: function(event) {
						$("#page-spinner").show()
						console.log(event);
						/* handle success/failure of checkout */
						if (event.data.status == "SUCCESS") {
							// The consumer has confirmed the payment schedule.
							// Call your server here to retrieve the order details
							var order_token = event.data.orderToken;
							var merchant_reference = event.data.merchantReference;

							location.href = site_url + "afterpay/billing_checkout_express/1/" + order_token;
							return false;
						} else {
							var order_token = "undefined";

							// The consumer cancelled the payment or closed the popup window.
							location.href = site_url + "afterpay/billing_checkout_express/0/" + order_token;
							return false;
						}
					},
					target: '#afterpay-button',
					shippingOptionRequired: false,
				})
			}, 500);
		}
	</script>
	<!-- <script type="text/javascript" src="https://static-us.afterpay.com/javascript/present-afterpay.js"></script>-->
	<script src="{!! $token_js_url !!}" async onload="initAfterpayCheckout()"></script>
@endif
@if($NetTotal > 0)
    <style>.express-ck ul li a{padding:4px;width:150px;height:45px;}</style>
    <ul class="pt-md-2" role="list" aria-label="Express Checkout Options">
        <li role="listitem">
            @if($Is_Afterpay_Checkout == "Yes")
                <a id="afterpay-button" data-afterpay-entry-point="cart" class="btn btn-primary d-block" href="javascript:void(0)" role="button" aria-label="Pay with Afterpay">
                    <img src="{{config('global.SITE_URL')}}images/afterpay.svg" height="32" style="width:120px;padding:3px;" alt="Afterpay">
                </a>
            @endif
        </li>
        <li style="display:none;" role="listitem">
            @if($CartAttr['IsPaypalExpressCheckout'] == 'Yes')
                <a class="paypal-btn btn btn-primary d-block" href="{{url('paypal/placeorder')}}" role="button" aria-label="Pay with Paypal">Pay with Paypal</a>
            @endif
        </li>
        <li role="listitem">
            @if($CartAttr['Amazon_pay_Checkout'] == 'Yes' && $CartAttr['onlyGCPurchased'] != '1')
                <div id="AmazonPayButtonAll" role="region" aria-label="Amazon Pay Button"></div>
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
                            alert(error);
                        }
                    });
                </script>
            @endif
        </li>
        <li role="listitem">
            <!--<div class="col-12 mt-3 text-center" id="payment-request-button"></div>
            <form id="frmstripe" method="post" action="{{url('placeorder')}}">
                <input type="hidden" name="is_stripe_wallet" id="is_stripe_wallet" value=""/>
                <input type="hidden" name="is_stripe_applepay" id="is_stripe_applepay" value=""/>
                <input type="hidden" name="is_step_gpay" id="is_step_gpay" value="FirstStep"/>
                {{ csrf_field() }}
            </form>-->
            <div id="shoptotal" style="display:none;" aria-hidden="true">
                @if($NetTotal > 0 && isset($NetTotal))
                    {{$NetTotal}}
                @endif
            </div>
            <div class="gpayapplepaybtn" id="payment-request-button-checkout" role="region" aria-label="GPay and ApplePay Button">
                <div class="cover-spin" aria-hidden="true"></div>
            </div>
            <a href="javascript:void(0);" class="edit-btn btn btn-primary insuranceSignature" id="GpayBtnn" style="display:none;" role="button" aria-label="Pay with Gpay or ApplePay">Gpay/ApplePay</a>
        </li>
        <li role="listitem">
            <!-- @if($CartAttr['IsPaypalExpressCheckout'] == 'Yes')
                <div id="paypal-button-container"></div>
            @endif -->
            <div id="paypal-button-container-checkout-pg" style="position: relative;z-index:1;" role="region" aria-label="Paypal Button"></div>
            <div data-pp-message data-pp-style-layout="text" data-pp-style-logo-type="inline" data-pp-style-text-color="black" data-pp-style-text-size="12" data-pp-amount="{{$NetTotal}}" data-pp-placement=product aria-hidden="true"></div>
        </li>
    </ul>
    <ul class="pt-md-2" style="display:none;" role="list" aria-label="Additional Payment Options">
        <li role="listitem">
            @if(config('global.StripeButton') == 'Show')
                <!--<div class="col-12 mt-3 text-center" id="payment-request-button"></div>
                <form id="frmstripe" method="post" action="{{url('placeorder')}}">
                    <input type="hidden" name="is_stripe_wallet" id="is_stripe_wallet" value=""/>
                    <input type="hidden" name="is_stripe_applepay" id="is_stripe_applepay" value=""/>
                    <input type="hidden" name="is_step_gpay" id="is_step_gpay" value="FirstStep"/>
                    {{ csrf_field() }}
                </form>-->
                <div id="shoptotal" style="display:none;" aria-hidden="true">
                    @if($NetTotal > 0 && isset($NetTotal))
                        {{$NetTotal}}
                    @endif
                </div>
                <a href="javascript:void(0);" class="edit-btn btn btn-primary insuranceSignature" id="GpayBtn1" style="display:none;" role="button" aria-label="Pay with Gpay or ApplePay">Gpay/ApplePay</a>
            @endif
        </li>
    </ul>
@endif
<div id="ShippingSignInsu" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="small_popup1" aria-modal="true" aria-hidden="true">
    <div class="vertical-alignment-helper text-left">
        <div class="modal-dialog modal-sm vertical-align-center" role="document">
            <div class="modal-content">
                <a class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <svg class="sv-close vam" aria-hidden="true" role="img" width="16" height="16">
                        <use href="#sv-close" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-close"></use>
                    </svg>
                </a>
                <div class="modal-body">
                    <div class="modal-hd">
                        <h1 id="gpay_applepay_paypal">Gpay/ApplePay</h1>
                    </div>
                    <div class="modal-space">
                        <div class="pt-3 pb-3">
                            <div class="row d-flex justify-content-center">
                                <div class="col-sm-9">
                                    <div class="row row5 pb-2" id="divinsurance" role="region" aria-labelledby="insuranceLabel">
                                        <div class="col-9">
                                            <div id="insuranceLabel">Shipping Protection</div>
                                            <div>From Damage, Loss, Theft
                                                @if(Session::has('shipping_insurance_charge'))
                                                    <span>{{Price(Session::get('shipping_insurance_charge'))}}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-3" align="right">
                                            <label class="switch active mb-0" id="insurance">
                                                <input type="checkbox" id="shipinsurance" checked aria-checked="true" aria-label="Enable Shipping Insurance">
                                                <span class="slider round"></span>
                                            </label>
                                            <span class="cart-tooltip max_qttable" id="insure_info">
                                                <a href="javascript:void(0);" aria-label="Shipping Insurance Info"><u>i</u></a>
                                                <span class="tables" role="tooltip">Please note by turning Shipping Insurance OFF, you are agreeing to taking full responsibility for any loss, damages or theft. Maxaroma cannot take claim if the insurance is off, however you can submit a claim to the carrier directly. </span>
                                            </span>
                                            <input type="hidden" name="tempValInsuSignature" id="tempValInsuSignature" value="Yes" />
                                        </div>
                                    </div>
                                    <div class="row row5 pb-2" id="divshipcerty" data-amt="{{$InsureAmount}}" role="region" aria-labelledby="signatureLabel">
                                        <div class="col-9">
                                            @if($InsureAmount <= 199)
                                                <div id="signatureLabel">Request Signature ($2.5)</div>
                                            @else
                                                <div id="signatureLabel">Request Signature (It's Free)</div>
                                            @endif
                                        </div>
                                        <div class="col-3" align="right">
                                            <label class="mb-0 switch @if($InsureAmount >= 200 || Session::has('ShoppingCart.ShippingSignature')) active @endif" id="insurance">
                                                <input type="checkbox" value="Yes" data-value="@if($InsureAmount >= 200)0 @else 2.5 @endif" @if($InsureAmount>= 200 || Session::has('ShoppingCart.ShippingSignature')) checked @endif name="shipping_signature" id="shipping_signature" aria-checked="@if($InsureAmount>= 200 || Session::has('ShoppingCart.ShippingSignature'))true @else false @endif" aria-label="Request Signature">
                                                <span class="slider round"></span>
                                            </label>
                                            <span class="cart-tooltip max_qttable" id="shipcerty_info" @if($InsureAmount < 200) style="visibility:hidden;" @endif>
                                                <a href="javascript:void(0);" aria-label="Signature Info"><u>i</u></a>
                                                <span class="tables" role="tooltip">For this order, we automatically add signature requirements free of charge. Opting out of signature requests will void any additional reassurances should your package show as delivered according to tracking.</span>
                                            </span>
                                            <input type="hidden" name="tempValInsu" id="tempValInsu" value="Yes" />
                                        </div>
                                    </div>
                                    <div class="text-center pt-2">
                                        <div class="col-12 mt-3 text-center" id="payment-request-button" role="region" aria-label="GPay/ApplePay Button"></div>
                                        <div class="col-12 mt-3 text-center" id="paypal-button-container-checkout" style="display:none;" role="region" aria-label="Paypal Button"></div>
                                        <!-- <a href="#" class="d-inline-block btn btn-secondary m-1">btn1</a>
                                        <a href="#" class="d-inline-block btn btn-secondary m-1">btn1</a> -->
                                    </div>
                                    <div id="payment-request-button pt-3"></div>
                                    <form id="frmstripe" method="post" action="{{url('placeorder')}}" role="form" aria-label="Stripe Payment Form">
                                        <input type="hidden" name="is_stripe_wallet" id="is_stripe_wallet" value="" />
                                        <input type="hidden" name="is_stripe_applepay" id="is_stripe_applepay" value="" />
                                        <input type="hidden" name="is_step_gpay" id="is_step_gpay" value="FirstStep" />
                                        <input type="hidden" name="shipsignatureflag" id="shipsignatureflag" value="" />
                                        {{ csrf_field() }}
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@if($CartAttr['IsPaypalExpressCheckout'] == 'Yes')
    <!-- <script src="https://www.paypal.com/sdk/js?client-id=ATtuGj0FvDJqZDzPKYnfx13ovzfCod0CF_L-V87mX8Lnm0SV32vycXfwJHriS41I6YyCd5tBfxQ1dA8j&components=buttons"></script>
    <script type="text/javascript">
        var CREATE_PAYMENT_URL = '{{url('paypal/placeorder')}}';
        paypal.Buttons({
            onInit(data, actions) {
                actions.disable();
            },
            onClick: function () {
                window.location.href = CREATE_PAYMENT_URL;
            },
            style: {
                layout: 'horizontal',
                color: 'gold',
                shape: 'rect',
                label: 'paypal',
                height: 45
            }
        }).render('#paypal-button-container');
    </script> -->
@endif