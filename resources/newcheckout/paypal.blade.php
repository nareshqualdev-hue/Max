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
                            <h1 id="gpay_applepay_paypal">PayPal</h1>
                        </div>
                        <div class="modal-space">
                            <div class="checkout-addons">
                                <div class="addon-list">

                                    <!-- Protect My Order -->
                                    <label class="addon-row{{ $checkoutInsurance > 0 ? ' active' : '' }}" aria-label="Add Protect My Order">
                                    <span class="addon-icon" aria-hidden="true">
                                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                                        <path d="M11 2L3 5.5V10.5C3 15.1 6.6 19.4 11 20.5C15.4 19.4 19 15.1 19 10.5V5.5L11 2Z" stroke="#000" stroke-width="1.5"></path>
                                        <path d="M8 11L10 13L14 9" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </span>
                                    <span class="addon-body">
                                        <span class="addon-title">Protect My Order</span>
                                        <span class="addon-desc">Covers damage, loss &amp; theft</span>
                                    </span>
                                    <span class="addon-price" id="protection-addon-price">{{ $checkoutInsurance > 0 ? '+' . $money($checkoutInsurance) : 'Free' }}</span>
                                    <span class="toggle-switch">
                                        <input type="checkbox" id="protection" name="protection" {{ $checkoutInsurance > 0 ? 'checked' : '' }} >
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                    </span>
                                    </label>

                                    <!-- Request Signature -->
                                    <label class="addon-row{{ $checkoutSignature > 0 ? ' active' : '' }}" aria-label="Add Request Signature">
                                    <span class="addon-icon" aria-hidden="true">
                                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                                        <path d="M3 16C5 13 7 12 8.5 13.5C9.5 14.5 8 16 6.5 15C5 14 6.5 9 8.5 7C10 5.5 11 6.5 10.5 8.5C10 11 8 14.5 10 16C11.5 17 13.5 15.5 15 13" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M16 17H19" stroke="#000" stroke-width="1.5" stroke-linecap="round"></path>
                                        </svg>
                                    </span>
                                    <span class="addon-body">
                                        <span class="addon-title">Request Signature</span>
                                        <span class="addon-desc">Signature required on delivery</span>
                                    </span>
                                    <span class="addon-price" id="signature-addon-price">{{ $checkoutSignature > 0 ? '+' . $money($checkoutSignature) : 'Free' }}</span>
                                    <span class="toggle-switch">
                                        <input type="checkbox" id="request-signature" name="request-signature" {{ $checkoutSignature > 0 ? 'checked' : '' }}>
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                    </span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <div class="row d-flex justify-content-center">
                                    <div class="col-sm-12 col-md-12 col-lg-12">
                                        <div class="text-center pt-2">
                                            <div class="col-12 mt-3 text-center" id="payment-request-button" role="region" aria-label="GPay/ApplePay Button"></div>
                                            <div class="col-12 mt-3 text-center" id="paypal-button-container-checkout"  role="region" aria-label="Paypal Button"></div>
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
    <!-- <script type="text/javascript" src="{{config('global.SITE_JS_CORE')}}paypal.js?ver={{$JSSPaypalValVer}}"></script> -->
@endif