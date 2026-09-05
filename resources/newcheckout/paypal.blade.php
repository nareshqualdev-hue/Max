@if($CartAttr["IsPaypalExpressCheckout"] == 'Yes')
    @php $JSSPaypalValVer = filemtime(config('global.SITE_JS_CORE_PATH').'paypal.js'); @endphp
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
    <script src="https://www.paypal.com/sdk/js?client-id=AQymJLkSRgzhHf0AjiYOGL_OHQZ60bCggeySkd8F31n_2ery6HK7HXYQGeeBCfszGgAin8XfJbvZuByn&components=messages,buttons,funding-eligibility&commit=false&disable-funding=card"></script>
    <script type="text/javascript">
    let paypalApprovalHandled = false;
    var Site_URL = "{{config('global.SITE_URL')}}";
    var CartFullDetails = '';
    var CREATE_PAYMENT_URL  = '{{url('paypal/placeorder')}}';
    let  state= '';
    let  zip= '';
    let  country= '';
    let  city= '';
    </script>
    <script type="text/javascript" src="{{config('global.SITE_JS_CORE')}}paypal.js?ver={{$JSSPaypalValVer}}"></script>
@endif