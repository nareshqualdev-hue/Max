<style>
    .clsgreen{color:green;font-weight: 600;}
    .clsfree{color:green;font-weight: 600;}
    .estDate{clear:both;}
</style>
<input type="hidden" name="max_hidsubtotal" id="max_hidsubtotal" value="{{Session::get('ShoppingCart.SubTotal')}}" />
<div class="row" role="region" aria-label="Shipping Methods Selection Section">
    <div class="col-12 d-md-none">
        <div class="mobile-shadow mb-0" aria-hidden="true">&nbsp;</div>
    </div>
    <div class="col-12">
        <h4 class="cart-hd-sub text-center pb-4" id="shipping-methods-heading">Shipping Method</h4>
    </div>

    <div class="col-12">
        <div class="pb-4" id="shipping-delivery-message">Place your order in the next <span class="clsgreen">{{$datediff}}</span> for delivery by the listed dates.</div>

        @if(isset($ShippingMethods) && count($ShippingMethods) > 0)
        @if(count($ShippingMessage) > 0)
        @foreach($ShippingMessage as $SMSG)
        <div class=@if(isset($MsgSucess) && $MsgSucess!=1) "alert alert-danger" @else "alert alert-success" @endif role="alert">
            {!! $SMSG !!}
        </div>
        @endforeach
        @endif
        @if(isset($ShipMethodError) && $ShipMethodError != '')
        <div class="alert alert-danger" role="alert">
            {{$ShipMethodError}}
        </div>
        @endif

        <form id="frmpayment" class="mb-0" name="frmpayment" method="post"  action="{{ config('global.SITE_URL') }}{{(Auth::guard('store')->check()) ? 'store-payment' : 'payment'}}" aria-labelledby="shipping-methods-heading" role="form">
			
		 
			
            {{ csrf_field() }}
            <input type="hidden" name="action" id="action" value="paymentinfo" />
            <input type="hidden" name="OnlyHead" id="OnlyHead" value="0" />
            <input type="hidden" name="SelPayMethod" id="SelPayMethod" value="{{$SelPayMethod}}" />
            <div class="row row5 pb-2" id="divinsurance" role="region" aria-label="Shipping Protection Option">
                <div class="col-9">
                    <div>Protect My Order</div>
                    <div>From Damage, Loss, Theft
                        @if(Session::has('shipping_insurance_charge'))
                        <span>{{Price(Session::get('shipping_insurance_charge'))}}</span>
                        @endif
                    </div>
                </div>
                <div class="col-3" align="right">
                    <label class="switch active mb-0" id="insurance" aria-label="Toggle Shipping Insurance">
                        <input type="checkbox" id="shipinsurance" checked aria-checked="true" aria-label="Shipping Insurance">
                        <span class="slider round"></span>
                    </label>
                    <span class="cart-tooltip max_qttable" id="insure_info">
                        <a href="javascript:void(0);" role="button" class="infoBtn" aria-label="click to more Shipping Insurance infomartion"><u>i</u></a>
                        <span class="tables infoTables">Please note by turning Shipping Insurance OFF, you are agreeing to taking full responsibility for any loss, damages or theft. Maxaroma cannot take claim if the insurance is off, however you can submit a claim to the carrier directly. </span>
                    </span>
                </div>
            </div>

            <div class="row row5 pb-2" id="divshipcerty" data-amt="{{$InsureAmount}}" role="region" aria-label="Signature Requirement Option">
                <div class="col-9">
                    @if($InsureAmount <= 199)
                        <div>Request Signature ($2.5)</div>
                    @else
                        <div>Request Signature (It's Free)</div>
                    @endif
                </div>
                <div class="col-3" align="right">
                    <label class="mb-0 switch @if($InsureAmount >= 200 || Session::has('ShoppingCart.ShippingSignature')) active @endif" id="insurance" aria-label="Toggle Signature Requirement">
                        <input type="checkbox" value="Yes" data-value="@if($InsureAmount >= 200)0 @else 2.5 @endif" @if($InsureAmount>= 200 || Session::has('ShoppingCart.ShippingSignature')) checked @endif name="shipping_signature" id="shipping_signature" aria-checked="@if($InsureAmount>= 200 || Session::has('ShoppingCart.ShippingSignature'))true @else false @endif" aria-label="Signature Requirement">
                        <span class="slider round"></span>
                    </label>
                    <span class="cart-tooltip max_qttable" id="shipcerty_info">
                        <a href="javascript:void(0);"  role="button" class="infoBtn" aria-label="click to more Signature Requirement infomartion"><u>i</u></a>
                        @if($InsureAmount < 200)
                            <span class="tables infoTables">For this order, there is an option to add signature requirements at $2.5. Opting out of signature requests will void any additional reassurances should your package show as delivered according to tracking..</span>
                        @else
                            <span class="tables infoTables">For this order, we automatically add signature requirements free of charge. Opting out of signature requests will void any additional reassurances should your package show as delivered according to tracking.</span>
                        @endif
                    </span>
                </div>
            </div>
            <div class="pt-3" role="radiogroup" aria-labelledby="shipping-methods-heading" aria-label="Available Shipping Methods">
                @foreach($ShippingMethods as $key => $ShippingMethod)
                <label id="method-{{$key}}" class="comcheck radio d-inline-block w-100 checkbox-label clsship @if(Session::has('ShoppingCart.Shipping.ShippingMethodID') == $ShippingMethod['shipping_mode_id']) active @endif" aria-label="Select {{$ShippingMethod['method_name']}}">
                    <div class="chebox">
                        <input type="radio" class="shipmethod" name="shippingModeId" data-key="{{$key}}" id="shippingModeId{{$key}}" value="{{$ShippingMethod['shipping_mode_id']}}" data-estdate="{{$ShippingMethod['estdate']}}" @if(Session::get('ShoppingCart.Shipping.ShippingMethodID')==$ShippingMethod['shipping_mode_id']) checked @endif aria-checked="@if(Session::get('ShoppingCart.Shipping.ShippingMethodID')==$ShippingMethod['shipping_mode_id'])true @else false @endif" aria-labelledby="method-{{$key}}-label">
                        <span class="checkmark"></span>
                        <span class="float-left w-100" id="method-{{$key}}-label">
                            <div>
                                {!!$ShippingMethod['display_date']!!} - {!! $ShippingMethod['charge_str'] !!}
                                <span class="cart-tooltip max_qttable">
                                    <a href="javascript:void(0);" role="button" class="infoBtn" aria-label="click to more Shipping Method infomartion"><u>i</u></a>
                                    @if($ShippingMethod['shipping_mode_id'] == '33' || $ShippingMethod['shipping_mode_id'] == '34')
                                    <span class="tables infoTables">Guaranteed</span>
                                    @else
                                    <span class="tables infoTables">This is an estimated delivery date, and it is not guaranteed.</span>
                                    @endif
                                </span>
                            </div>
                            <div>{!!$ShippingMethod['method_name']!!}</div>
                        </span>
                    </div>
                </label>
                @endforeach

                <input type="hidden" id="vendor-popup" value="{{$VendorPopup}}" />

            </div>
        </form>

        @else
        <div class="alert alert-danger" role="alert">There is no shipping method available to your destination. Please fill a different shipping address.</div>
        @endif
    </div>
    @if(isset($ShippingMethods) && count($ShippingMethods) > 0 && $PageFrom == '' )
    <div class="col-12 pb-md-5 pt-md-4 mt-md-2">
        <div class="mobile-shadow d-md-none" aria-hidden="true">&nbsp;</div>
        <div class="chekout-main-btn" role="region" aria-label="Continue to Payment Button Section">
            <a class="max_continuesp_btn btn btn-primary d-block" id="btnbill_step2" href="javascript:void(0);" role="button" aria-label="Continue to Payment">Continue to Payment</a>
        </div>
    </div>
    <div class="col-12 f12 text-center">
        <a href="{{config('global.SITE_URL')}}checkout{{$payment_method_url}}" class="ulink" aria-label="Return to Information"><strong>Return to Information</strong></a>
    </div>
    @endif
</div>
