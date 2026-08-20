<style>
    .your-bag .cart-tooltip a {
		position: relative;
		background: #fff;
		border: solid 1px #4c4c4c;
		color: #4c4c4c;
		font-size: 9px;
		width: 11px;
		height: 11px;
		line-height: 10px;
		top: 0px;
		display: inline-block;
		border-radius: 50%;
		text-align: center;
	}
	.your-bag .switch {
		width: 40px;
		height: 16px;
		vertical-align: top;
	}
	.stock_left{font-weight: 600;display: block;padding-bottom: 5px;color: #ec040f;font-size: 11px;line-height: 18px;}
</style>
<div class="your-bag" role="region" aria-labelledby="OrderSummaryTogg">
    @if(Session::has('CartError'))
        <x-message :attr="[ 'classname' => 'alert alert-danger', 'message' => Session::get('CartError'), 'mid' => 'error_cart', 'role' => 'alert', 'aria-live' => 'assertive']"/>
    @endif
    @if(Session::has('CartSuccess'))
        <x-message :attr="[ 'classname' => 'alert alert-success', 'message' => Session::get('CartSuccess'), 'mid' => 'success_cart', 'role' => 'status', 'aria-live' => 'polite']"/>
    @endif
    <div class="actmenuhd" role="heading" aria-level="2">
        <h4 class=""><span id="OrderSummaryTogg">Order Summary / Enter Coupon</span>
            <svg id="down-arrow" class="sv-down-arrow vam ml-2" aria-hidden="true" role="img" width="14" height="12" focusable="false">
                <use href="#sv-down-arrow" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-down-arrow"></use>
            </svg>
        </h4>
        <span class="float-right">
            @if(!Auth::guard('store')->check())
            <a href="{{config('global.SITE_URL').'shoppingcart/view'}}" class="ulink f12 mr-2 checkout-cartlink" aria-label="View Cart">
                <svg class="sv-cartnw vam" aria-hidden="true" role="img" width="22" height="22">
					<use href="#sv-cartnw" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-cartnw"></use>
				</svg><strong>Cart</strong>
            </a>|
            @else
            <a href="javascript:void(0);" class="ulink f12 mr-2 checkout-cartlink poscart" aria-label="View Cart">
                <svg class="sv-cartnw vam" aria-hidden="true" role="img" width="22" height="22">
					<use href="#sv-cartnw" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-cartnw"></use>
				</svg><strong>Cart</strong>
            </a>|
            @endif
            <strong id="net_total_amt1" data-amt="{{$NetTotal}}" aria-label="Net total amount">{{Price($NetTotal)}}</strong>
        </span>
    </div>
    @if(isset($CartDetails['Cart']) && count($CartDetails['Cart']) > 0 )
        <div class="actmenu-inner your-bag-inner mt-3" id="cart_items" aria-label="Cart Items">
            <ul role="list">
                @foreach($CartDetails['Cart'] as $key => $CartItem)
                <li role="listitem">
                    <div class="summary-row" aria-labelledby="cart-item-{{$key}}">
                        @if((isset($CartItem['IS_Free_Gift']) && $CartItem['IS_Free_Gift'] == 'Yes')  || (isset($CartItem['Is_Free_Sample']) && $CartItem['Is_Free_Sample'] == 'Yes') )
                            <a href="javascript:void(0);" class="summary-img" aria-label="Free Gift Image">{!!$CartItem['Image']!!}</a>
                        @else
                            <a href="{{$CartItem['Prod_URL']}}" class="summary-img" aria-label="Product Image: {{$CartItem['ProductName']}}">{!!$CartItem['Image']!!}</a>
                        @endif
                        <div class="cart-product pb-1" id="cart-item-{{$key}}">
                            @if((isset($CartItem['IS_Free_Gift']) && $CartItem['IS_Free_Gift'] == 'Yes')  || (isset($CartItem['Is_Free_Sample']) && $CartItem['Is_Free_Sample'] == 'Yes') )
                                <span aria-label="Product Name">{{$CartItem['ProductName']}}</span>
                            @else
                                <a href="{{$CartItem['Prod_URL']}}" aria-label="Product: {{$CartItem['ProductName']}}">
                                    {{$CartItem['ProductName']}}
                                    @if(isset($CartItem['short_description']) && $CartItem['short_description'] != '')
                                        <br>
                                        <span class="d-inline-block" aria-label="Short Description">{{$CartItem['short_description']}}</span>
                                    @endif
                                </a>
                            @endif
                            <div class="SKU"> <span aria-label="SKU">SKU: {{$CartItem['SKU']}}</span> </div>
                            <div class="SKU"> <span aria-label="Quantity">Qty: {{$CartItem['Qty']}}</span> </div>
                            @if(isset($CartItem['FinalSale']) && $CartItem['FinalSale']!='' )
                                <div class="SKU"> <span aria-label="Final Sale">{{$CartItem['FinalSale']}}</span></div>
                            @endif

                            @if(Auth::guard('store')->check())
								@if(isset($CartItem['OrderType']) && $CartItem['OrderType'] == 'Store')
									<div class="itemType">Store Item</div>
								@else
									<div class="itemType">Online Item</div>
								@endif
							@endif

                            @if(isset($CartItem['stock_left']) && $CartItem['stock_left'] < 6)
								<span class="stock_left">Only {{ $CartItem['stock_left'] }} left in stock</span>
							@endif
                            @if(isset($CartItem['BogoDiscountMessage']) && !empty($CartItem['BogoDiscountMessage']))
								{!! $CartItem['BogoDiscountMessage'] !!}
							@endif

                                @if($CurrentRoute != 'billing-payment')
								<div class="removeqty-options">
									<a href="javascript:void(0);" class="shopcartItemDel" data-index="{{$key}}" role="button" aria-label="Remove item from cart">
										<svg class="sv-trash vam" aria-hidden="true" role="img" width="18" height="18">
											<use href="#sv-trash" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-trash"></use>
										</svg>
									</a>
                                    @if((!isset($CartItem['IS_Free_Gift']) || $CartItem['IS_Free_Gift'] == 'No') && (!isset($CartItem['Is_Free_Sample']) || $CartItem['Is_Free_Sample'] == 'No'))
									<div class="qtybox" role="group" aria-label="Quantity Selector">
										<button type="button" class="left-qty btn-number" data-usertype="{{Session::get('eusertype')}}" data-type="minus" data-field="prodqty_{{$CartItem['ProductID']}}" data-product="{{$CartItem['ProductID']}}" aria-label="Decrease quantity">-</button>
										<input type="text" name="prodqty_{{$CartItem['ProductID']}}" id="prodqty_{{$CartItem['ProductID']}}" class="form-control input-number" value="{{$CartItem['Qty']}}" data-qty="{{$CartItem['Qty']}}" min="1" style="width:25% !important;" aria-label="Quantity">
										<button type="button" class="right-qty btn-number" data-usertype="{{Session::get('eusertype')}}" data-type="plus" data-field="prodqty_{{$CartItem['ProductID']}}" data-product="{{$CartItem['ProductID']}}" aria-label="Increase quantity">+</button>
									</div>
                                    @endif
								</div>

							@endif
                        </div>
                        <div class="cart-price text-right">
                            <span aria-label="Item Price">{{Price($CartItem['TotPrice'])}}</span>
                        </div>
                    </div>
                </li>
                @endforeach
            </ul>
            <div class="order-summry mt-4" role="region" aria-label="Order Summary">
                <div class="row">
                    <div class="col-12" id="cartsubtotal">
                        @include('checkout.subtotalbox',['CartDetails' => $CartDetails, 'CurentRoute' => $CurrentRoute])
                    </div>
                </div>
            </div>

            @if($show_afterpay_widget_box == "Yes")
                <div class="order-summry mt-4" id="alert_ap" style="display:none" role="alert" aria-live="assertive">
                    <div class="row">
                        <div class="col-12"><div class="alert alert-danger" role="alert" id="alert_order"></div></div>
                    </div>
                </div>
                <div class="order-summry mt-4" id="checkout_widget_ap" role="region" aria-label="AfterPay Payment Widget">
                    <div class="row">
                        <div class="col-12" id="ap_checkoutwidget">
                            <div id="afterpay-widget-container"></div>
                            <input type="hidden" name="pschecksum" id="pschecksum" value="" aria-hidden="true">
                            <script>
                                // Ensure this function is defined before loading afterpay.js
                                function createAfterpayWidget () {
                                    window.afterpayWidget = new AfterPay.Widgets.PaymentSchedule({
                                        token: '{{$afterpay_checkout_token}}',
                                        target: '#afterpay-widget-container',
                                        locale: 'en-US',
                                        onReady: function (event) {
                                        var paymentScheduleChecksum;
                                        paymentScheduleChecksum = event.data.paymentScheduleChecksum;
                                            afterpayWidget.update({
                                                amount: { amount: "{{$NetTotal}}", currency: "USD" },
                                            })
                                            $("#pschecksum").val(paymentScheduleChecksum);
                                            if($("#ap_psChecksum").length > 0){
                                                $("#ap_psChecksum").val(paymentScheduleChecksum);
                                            }
                                        },
                                        onChange: function (event) {
                                            // Fires after each update and on any other state changes.
                                            // See "Getting the widget's state" for more details.
                                            // console.log(event);
                                            var paymentScheduleChecksum;
                                            if(event.data.isValid == true){
                                                $("#alert_order").html("");
                                                $("#alert_ap").hide();
                                                $("#checkout_widget_ap").show();

                                                paymentScheduleChecksum = event.data.paymentScheduleChecksum;
                                                $("#pschecksum").val(paymentScheduleChecksum);
                                                if($("#ap_psChecksum").length > 0){
                                                    $("#ap_psChecksum").val(paymentScheduleChecksum);
                                                }
                                            }else{
                                                //issue
                                                // console.log(event);
                                                $("#alert_order").html("AfterPay is disabled because of amount range limit reached.");
                                                $("#alert_ap").show();
                                                $("#checkout_widget_ap").hide();
                                            }
                                        },
                                        onError: function (event) {
                                        // See "Handling widget errors" for more details.
                                            // console.log(event);
                                            $("#alert_order").html("AfterPay is disabled because of amount range limit reached.");
                                            $("#alert_ap").show();
                                            $("#checkout_widget_ap").hide();

                                            // if($("#net_total_amt").data("amt") >= $("#net_total_amt").data("minap") && $("#net_total_amt").data("amt") < $("#net_total_amt").data("maxap")){
                                                //code here
                                            // }
                                            // $("#ap_checkoutwidget").hide();
                                        },
                                    })
                                }
                            </script>
                            <script src="{{$token_js_url}}" async onload="createAfterpayWidget()"> </script>
                        </div>
                    </div>
                </div>
            @endif

            @if($CurrentRoute == 'billing-payment' && !auth()->guard('store')->check())
			    {{-- @if(Session::has('ShoppingCart.OrderType') && Session::get('ShoppingCart.OrderType')!='Store')--}}
                @if(!auth()->guard('store')->check())
                <div class="row row5 pb-2" id="divinsurance" role="region" aria-label="Shipping Protection Option">
                    <div class="col-9">
                        <div>Protect my order</div>
                        <div>From Damage, Loss, Theft
                            @if(Session::has('shipping_insurance_charge'))
                            <span>{{Price(Session::get('shipping_insurance_charge'))}}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-3" align="right">
                        <label class="switch active mb-0" id="insurance" aria-label="Toggle Shipping Insurance">
                            <input type="checkbox" id="shipinsurance"
                                @if(Session::has('shipping_insurance_charge')) checked @endif
                                aria-checked="true" aria-label="Shipping Insurance">
                            <span class="slider round"></span>
                        </label>
                        <span class="cart-tooltip max_qttable" id="insure_info">
                            <a href="javascript:void(0);" aria-label="Shipping Insurance Info"><u>i</u></a>
                            <span class="tables" role="tooltip">Please note by turning Shipping Insurance OFF, you are agreeing to taking full responsibility for any loss, damages or theft. Maxaroma cannot take claim if the insurance is off, however you can submit a claim to the carrier directly. </span>
                        </span>
                    </div>
                </div>
                @endif
		    @endif

            @if($CurrentRoute == 'billing')
                <div class="row no-gutters">
                    {{-- @if(isset($show) && $CartAttr['isCouponsAvailable'] == 1 && $CartAttr['coupon_number'] == '') --}}
                    @if($CartAttr['isCouponsAvailable'] == 1 &&  !Session::has('PayPalToken'))
				<div class="col-12 remdis checkout">
					<div class="max_coupon_box active">
						<div class="coupan_boxhd plus-minus d-none">
							<h6 class="f12">Promotional / Coupon Code</h6>
						</div>
						<div class="cart-discount">
							<div class="form-group mb-0">
								<input type="text" name="coupon_number" id="coupon_number" class="form-control mb-0" aria-label="Enter Coupon Code">
								<label for="guest_confirm_password" class="col-form-label" aria-hidden="true">Enter Coupon Code</label>
								<a href="javascript:void(0);" id="btncouponapply" class="btn btn-secondary checkout" aria-label="Apply Coupon Code">Apply</a>
							</div>
						</div>
					</div>
				</div>
			@endif

			@if($CartAttr['allow_gift'] == 1 && !Session::has('PayPalToken') && $CartAttr['onlyGCPurchased']==0 )
				<div class="col-12 remdis checkout">
					<div class="max_coupon_box active">
						<div class="coupan_boxhd plus-minus d-none">
							<h6 class="f12">E-Gift Card Code</h6>
						</div>
						<div class="cart-discount">
							<div class="form-group mb-0">
								<input type="text" id="txtgiftcard" value="{{Session::get('ShoppingCart.GiftCoupon.Code')}}" class="form-control mb-0" aria-label="Enter E-Gift Card Code">
								<label for="guest_confirm_password" class="col-form-label" aria-hidden="true">Enter E-Gift Card Code</label>
								<a href="javascript:void(0);" id="btngiftcard" class="btn btn-secondary checkout" aria-label="Apply E-Gift Card">Apply</a>
							</div>
						</div>
					</div>
				</div>
			@endif
                </div>
            @endif
        </div>
        @if($CurrentRoute == 'billing' && (!Session::has('ShoppingCart.YotpoRewardCode') || (Session::has('ShoppingCart.YotpoRewardCode') && trim(Session::get('ShoppingCart.YotpoRewardCode'))  == '')))
            <div class="col-12" id="checkoutYotpoWidget">
                @if(Session::has("sess_icustomerid"))
                    <div class="yotpo-widget-instance" data-yotpo-instance-id="90560" role="region" aria-label="Yotpo Rewards"></div>
                @endif
            </div>
        @endif
    @endif
</div>
@if($CartAttr['FundFlag'] == 1 && $CurrentRoute == 'billing-payment')
    <div class="row">
        <div class="col-lg-12 mt-3">
            <div class="max_coupon_box" role="region" aria-label="Available Fund">
                <div class="coupan_boxhd">
                    <div class="float-left">
                        <h6 class="" aria-label="Available Fund">Available Fund : {{Price($CartAttr['available_funds'])}}</h6><br>
                        <x-button btype="button" btntext="Add New Fund" classname="btn btn-primary" bid="btnAdddFundBilling" aria-label="Add New Fund"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@if($CartAttr['CreditLimitFlag'] != 0 && $CurrentRoute == 'billing' && Auth::guard('store')->check())
    <div class="row">
        <div class="col-lg-12 mt-3">
            <div class="max_coupon_box">
                <div class="coupan_boxhd">
                    <div class="float-left">
									 <span id="credit-balance-label">Your available credit Balance is :</span>
					@if($CreditDiscount > 0 && $CartAttr['CreditLimitFlag'] == 2)
						<span aria-label="Remaining Credit Limit">{{Session::get('currency_symbol').$CartAttr['RemainCreditLimit']}}</span>
					@else
						<span aria-label="Credit Limit">{{Session::get('currency_symbol').$CartAttr['CreditLimit']}}</span>
					@endif
					@if($CreditDiscount > 0)
						<a href="javascript:void(0);" class="ulink d-inline-block ml-2" id="btnremovecredBill" role="button" aria-label="Remove Credit"><strong>Remove</strong></a>
					@else
						<a href="javascript:void(0);" id="btncreditBill" class="ulink d-inline-block ml-2 checkout" role="button" aria-label="Apply Credit"><strong>Apply</strong></a>
					@endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@if(!auth()->guard('store')->check())
<div class="max_need_help mt-4 d-none d-md-block" role="region" aria-label="Need Help Information">
    <ul>
        <li>
            <span>
                <svg class="svg-truck" aria-hidden="true" role="img" width="22" height="22" focusable="false">
                    <use href="#svg-truck" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-truck"></use>
                </svg>
            </span>
            Free Standard Shipping
        </li>
        <li>
            <span>
                <svg class="svg-return" aria-hidden="true" role="img" width="18" height="18" focusable="false">
                    <use href="#svg-return" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-return"></use>
                </svg>
            </span>
            Free Returns & Exchanges (excludes final sale items)
        </li>
        <li>
            @if($CartAttr['onlyGCPurchased'] != 1)
                <a href="javascript:void(0);" title="Shipping Rate Calculator" class="shippingCalculate" role="button" aria-label="Shipping Rate Calculator">
                    <span>
                        <svg class="svg-calculator" aria-hidden="true" role="img" width="16" height="16" focusable="false">
                            <use href="#svg-calculator" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-calculator"></use>
                        </svg>
                    </span>
                    Shipping Rate Calculator
                </a>
            @endif
        </li>
        <li>
        <span>
            <svg class="svg-doler" aria-hidden="true" role="img" width="16" height="16" focusable="false">
                <use href="#svg-doler" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-doler"></use>
            </svg>
        </span>
        Items are not reserved until checkout is complete. Prices are subject to change based on the price in effect the day you checkout.</li>

        @php
			$freeGiftEnabled = config('Settings.FREEGIFTFLAG') === 'Yes';
			$hasFreeGiftAvailable = isset($TotalFreeavailItems) && $TotalFreeavailItems > 0;
			$TotalFreeGiftItems = $TotalFreeGiftItems ?? 0;
			$hasTakenFreeGift = $TotalFreeGiftItems > 0;
			$sampleEligible = isset($totalAllowSampleProducts) && $totalAllowSampleProducts > 0;
		@endphp

        {{-- @if(isset($TotalFreeavailItems) && $TotalFreeavailItems > 0 && $TotalFreeGiftItems <= 0 && $freeSampleInCart == 'No') --}}

        @if(isset($TotalFreeavailItems) && $TotalFreeavailItems > 0 && isset($allFreeGiftsInCart) &&  $allFreeGiftsInCart == 'No' && $allFreeGiftsInCart != 'one')
        <li>
			<span>
			<svg class="svg-free-gift" aria-hidden="true" role="img" width="16" height="16" focusable="false">
                <use href="#svg-free-gift" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-free-gift"></use>
            </svg></span>

                    You qualify for <b>FREE GIFT</b> items.  <a href="javascript:void(0);" class="ulink" onclick="return DisplayPopupFreeGift();">Click Here</a> to view and add to your cart.
        </li>

		@else

           @if(
					config('Settings.FREESAMPLE_VALUE') == "Yes" &&
					isset($totalAllowSampleProducts) && $totalAllowSampleProducts > 0 &&
					isset($totalAllowSampleCustomerChoice) &&
					isset($TotalFreeSampleItems) && $TotalFreeSampleItems < $totalAllowSampleCustomerChoice &&
					(
						(isset($TotalFreeavailItems) && $TotalFreeavailItems > 0) ||
						(empty($TotalFreeGiftItems) && $TotalFreeGiftItems <= 0)
					)
				)

			<li>
				<span>
				<svg class="svg-free-gift" aria-hidden="true" role="img" width="16" height="16" focusable="false">
					<use href="#svg-free-gift" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-free-gift"></use>
				</svg></span>
					You qualify for <b>FREE SAMPLE</b> items.  <a href="javascript:void(0);" class="ulink" onclick="return DisplayPopupSampleProducts();">Click Here</a> to view and add to your cart.
			</li>
			@endif
		@endif
        <li>
			<span class="max_need_help-icon">
				<svg class="svg-aroma-club" aria-hidden="true" role="img" width="16" height="16">
					<use href="#svg-aroma-club" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-aroma-club"></use>
				</svg>
			</span>
			<a href="{{ url('reward-point-program.html') }}" target="_blank">JOIN Aroma CLUB (Reward Program)</a>
		</li>
        <span id="fsic" style="display:none">{{ $freeSampleInCart }}--{{ $freeGiftInCart }}</span>

    </ul>
</div>
@endif
<div class="secure-payment-icons d-none d-lg-block" role="region" aria-label="Secure Payment and Payment Method Icons">
	<div class="secure-iconmain">
		<ul class="secureicon-list" role="list" aria-label="Secure Payment Icons">
			<li class="secure-box" role="listitem">
				<svg class="svg-paypalsecure" aria-hidden="true" focusable="false" role="img" width="422" height="159">
					<use href="#svg-paypalsecure" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-paypalsecure"></use>
				</svg>
			</li>
			<li class="secure-box" role="listitem">
				<svg class="svg-mastercardsecure" aria-hidden="true" focusable="false" role="img" width="235" height="84">
					<use href="#svg-mastercardsecure" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-mastercardsecure"></use>
				</svg>
			</li>
			<li class="secure-box" role="listitem">
				<svg class="svg-visasecure" aria-hidden="true" focusable="false" role="img" width="198" height="198">
					<use href="#svg-visasecure" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-visasecure"></use>
				</svg>
			</li>
		</ul>
	</div>
	<div class="checkout-paylogo" role="region" aria-label="Payment Methods">
		<ul class="paylogo-list" role="list" aria-label="Payment Methods">
			<li class="cardicon-box" role="listitem">
				<svg class="card-amex" aria-hidden="true" focusable="false" role="img" width="48" height="30">
					<use href="#card-amex" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#card-amex"></use>
				</svg>
			</li>
			<li class="cardicon-box" role="listitem">
				<svg class="card-visa" aria-hidden="true" focusable="false" role="img" width="48" height="48">
					<use href="#card-visa" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#card-visa"></use>
				</svg>
			</li>
			<li class="cardicon-box" role="listitem">
				<svg class="card-master" aria-hidden="true" focusable="false" role="img" width="48" height="30">
					<use href="#card-master" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#card-master"></use>
				</svg>
			</li>
			<li class="cardicon-box" role="listitem">
				<svg class="card-discover" aria-hidden="true" focusable="false" role="img" width="48" height="48">
					<use href="#card-discover" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#card-discover"></use>
				</svg>
			</li>
			<li class="cardicon-box" role="listitem">
				<svg class="card-paypal" aria-hidden="true" focusable="false" role="img" width="48" height="48">
					<use href="#card-paypal" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#card-paypal"></use>
				</svg>
			</li>
			<li class="cardicon-box" role="listitem">
				<svg class="card-amazon" aria-hidden="true" focusable="false" role="img" width="48" height="48">
					<use href="#card-amazon" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#card-amazon"></use>
				</svg>
			</li>
		</ul>
	</div>
</div>
