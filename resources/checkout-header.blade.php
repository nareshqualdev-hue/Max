@if(Session::has('ShoppingCart.OrderType') && Session::get('ShoppingCart.OrderType')!='Store')
<style>
	.progress-line {
		position: absolute;
		top: 27px;
		left: 15%;
		right: 60%;
		height: 2px;
		background: #ddd;
		z-index: 0;
	}
	.step2{
		right: 40%;
	}
</style>
@else
<style>
	.progress-line {
		position: absolute;
		top: 27px;
		left: 20%;
		right: 50%;
		height: 2px;
		background: #ddd;
		z-index: 0;
	}
	.step2{
		right: 20%;
	}
</style>
@endif
<style>
.progress-container {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: #fff;
  padding: 10px 15px;
  /* border-bottom: 1px solid #ddd; */
}
.progress-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  text-align: center;
  position: relative;
  cursor: pointer;
  padding: 0 5px;
}

.step3{
	right: 15%;
}
.step-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #ddd;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 14px;
  font-weight: bold;
  color: #000;
  transition: background 0.3s;
}
.active svg{
	fill:#fff;
}
.step-label {
  margin-top: 4px;
  font-size: 12px;
  font-weight: bold;
  color: #333;
}
.step-desc {
  margin-top: 2px;
  font-size: 11px;
  color: #777;
  line-height: 1.3;
  max-width: 100px;
}

.active .step-circle {
  background: #000;
}
.completed .step-circle {
  background: #2196F3;
}
.active .step-circle{
	color : #fff !important;
}
.progress-step::after {
  /* content: ""; */
  position: absolute;
  top: 14px;
  right: -50%;
  width: 100%;
  height: 2px;
  background: #ddd;
  z-index: -1;
  transition: background 0.3s;
}
.progress-step:last-child::after {
  display: none;
}
.completed::after {
  background: #2196F3;
}
.active::after {
  background: #000;
}
.cart-circle {
  font-size: 16px;
}
.progress-step.active .step-label::after{
  text-decoration: underline;
  font-weight: 600; /* optional, makes it stand out */
}
/* Sticky only on mobile */
@media (max-width: 768px) {
  .progress-container {
    position: sticky;
    top: 120px;
    background: #fff; /* keep it readable */
    padding: 10px 20px;
    z-index: 999;
  }
}
</style>
<main id="checkout-page" class="business_page" data-total="{{$CartAttr['TotalValue']}}" role="main" tabindex="-1" aria-label="Checkout Main Content">
    <section class="checkout-page" data-section="0" role="region" aria-label="Checkout Progress Section">
        <div class="container">
            @if(!Auth::guard('store')->check())
			<div class="checkout-uspsection">
				<div class="progressbar-textarea">
					<div class="progressbar-sticky">
						<div class="progressbar-main">
							<div id="shippingProgressBarContainer_610398" class="progressbar-top" role="progressbar" aria-label="Checkout Progress">
								<div id="shippingProgressBar_610398" class="progressbar-complete"
									@if(Session::has('ShoppingCart.OrderType') && Session::get('ShoppingCart.OrderType')!='Store')
										@if($CurrentRoute == 'billing')
											style="width:0%;"
										@elseif($CurrentRoute == 'billing-shipping')
											style="width:50%;"
										@elseif($CurrentRoute == 'billing-payment')
											style="width:100%;"
										@endif
									@else
										@if($CurrentRoute == 'billing')
											style="width:0%;"
										@elseif($CurrentRoute == 'billing-payment')
											style="width:100%;"
										@endif
									@endif
									>
								</div>
							</div>
							<div class="progressbar-bottom">
								<span class="progressbar-col @if($CurrentRoute == 'billing' || $CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment') is-complete @endif ">
									<span class="progressbar-icon">
										@if($CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment')
											@if(isset($SelPayMethod) && $SelPayMethod == 'paypal')
												<a href="{{config('global.SITE_URL')}}checkout/paypal" aria-label="PayPal Information">
													<svg class="svg-timelineinfo" aria-hidden="true" role="img" width="18" height="22">
														<use href="#svg-timelineinfo" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineinfo"></use>
													</svg>
												</a>
											@elseif(isset($SelPayMethod) && $SelPayMethod == 'AP')
												<a href="{{config('global.SITE_URL')}}checkout/AP" aria-label="AfterPay Information">
													<svg class="svg-timelineinfo" aria-hidden="true" role="img" width="18" height="22">
														<use href="#svg-timelineinfo" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineinfo"></use>
													</svg>
												</a>
											@else
												<a href="{{config('global.SITE_URL')}}checkout" aria-label="Checkout Information">
													<svg class="svg-timelineinfo" aria-hidden="true" role="img" width="18" height="22">
														<use href="#svg-timelineinfo" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineinfo"></use>
													</svg>
												</a>
											@endif
										@else
											<svg class="svg-timelineinfo" aria-hidden="true" role="img" width="18" height="22">
												<use href="#svg-timelineinfo" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineinfo"></use>
											</svg>
										@endif
									</span>
									<span class="progressbar-label">
										@if($CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment')
											@if(isset($SelPayMethod) && $SelPayMethod == 'paypal')
												<a href="{{config('global.SITE_URL')}}checkout/paypal" aria-label="PayPal Information">Information</a>
											@elseif(isset($SelPayMethod) && $SelPayMethod == 'AP')
												<a href="{{config('global.SITE_URL')}}checkout/AP" aria-label="AfterPay Information">Information</a>
											@else
												<a href="{{config('global.SITE_URL')}}checkout" aria-label="Checkout Information">Information</a>
											@endif
										@else
												Information
										@endif
									</span>
								</span>
								@if(Session::has('ShoppingCart.OrderType') && Session::get('ShoppingCart.OrderType')!='Store')
								<span class="progressbar-col @if($CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment') is-complete @endif ">
									<span class="progressbar-icon">
										@if($CurrentRoute == 'billing-payment')
											<a href="{{config('global.SITE_URL')}}shipping" aria-label="Shipping Information">
												<svg class="svg-timelineshipping" aria-hidden="true" role="img" width="24" height="20">
													<use href="#svg-timelineshipping" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineshipping"></use>
												</svg>
											</a>
										@else
											<svg class="svg-timelineshipping" aria-hidden="true" role="img" width="24" height="20">
												<use href="#svg-timelineshipping" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineshipping"></use>
											</svg>
										@endif
									</span>
									<span class="progressbar-label">
										@if($CurrentRoute == 'billing-payment')
											<a href="{{config('global.SITE_URL')}}shipping" aria-label="Shipping Information">Shipping</a>
										@else
											Shipping
										@endif
									</span>
								</span>
								@endif

								<span class="progressbar-col @if($CurrentRoute == 'billing-payment') is-complete @endif ">
									<span class="progressbar-icon">
										<svg class="svg-timelinepayment" aria-hidden="true" role="img" width="14" height="24">
											<use href="#svg-timelinepayment" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelinepayment"></use>
										</svg>
									</span>
									<span class="progressbar-label">Payment</span>
								</span>
							</div>
						</div>
					</div>
					<div class="text-center completing-txt" role="status" aria-live="polite">You're almost there! Completing your order takes less than 2 minutes.</div>
				</div>
                <div class="uspouter">
					<ul class="checkout-usplist" role="list" aria-label="Checkout Benefits">
						<li class="checkout-uspbox" role="listitem">
							<a href="{{ url('shipping-policy.html') }}" class="usplink" aria-label="Free Shipping - View shipping policy">
								<svg class="svg-newship" aria-hidden="true" role="img" width="22" height="22">
									<use href="#svg-newship" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-newship"></use>
								</svg>
								<span>Free Shipping</span>
							</a>
						</li>
						<li class="checkout-uspbox" role="listitem">
							<a href="{{ url('site-page/returns-policy.html') }}" class="usplink" aria-label="Free Returns - View returns policy">
								<svg class="svg-return" aria-hidden="true" role="img" width="18" height="18">
									<use href="#svg-return" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-return"></use>
								</svg>
								<span>Free Returns</span>
							</a>
						</li>
						<li class="checkout-uspbox" role="listitem">
							<a href="#" class="usplink" aria-label="Secure Checkout - Learn about our security measures">
								<svg class="svg-securelocklite" aria-hidden="true" role="img" width="18" height="18">
									<use href="#svg-securelocklite" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-securelocklite"></use>
								</svg>
								<span>Secure Checkout</span>
							</a>
						</li>
					</ul>
				</div>
			</div>
			@endif
            <div class="checkout-middle-sec">
                <div class="checkout-left-part">
                    <div class="checkout-cont-lg">
						@if(Auth::guard('store')->check())
						<div class="checkout-uspsection">
							<div class="progressbar-textarea">
								<div class="progressbar-sticky">
									<div class="progressbar-main">
										<div id="shippingProgressBarContainer_610398" class="progressbar-top" role="progressbar" aria-label="Checkout Progress">
											<div id="shippingProgressBar_610398" class="progressbar-complete"
												@if(Session::has('ShoppingCart.OrderType') && Session::get('ShoppingCart.OrderType')!='Store')
													@if($CurrentRoute == 'billing')
														style="width:0%;"
													@elseif($CurrentRoute == 'billing-shipping')
														style="width:50%;"
													@elseif($CurrentRoute == 'billing-payment')
														style="width:100%;"
													@endif
												@else
													@if($CurrentRoute == 'billing')
														style="width:0%;"
													@elseif($CurrentRoute == 'billing-payment')
														style="width:100%;"
													@endif
												@endif
												>
											</div>
										</div>
										<div class="progressbar-bottom">
											<span class="progressbar-col @if($CurrentRoute == 'billing' || $CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment') is-complete @endif ">
												<span class="progressbar-icon">
													@if($CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment')
														@if(isset($SelPayMethod) && $SelPayMethod == 'paypal')
															<a href="{{config('global.SITE_URL')}}checkout/paypal" aria-label="PayPal Information">
																<svg class="svg-timelineinfo" aria-hidden="true" role="img" width="18" height="22">
																	<use href="#svg-timelineinfo" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineinfo"></use>
																</svg>
															</a>
														@elseif(isset($SelPayMethod) && $SelPayMethod == 'AP')
															<a href="{{config('global.SITE_URL')}}checkout/AP" aria-label="AfterPay Information">
																<svg class="svg-timelineinfo" aria-hidden="true" role="img" width="18" height="22">
																	<use href="#svg-timelineinfo" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineinfo"></use>
																</svg>
															</a>
														@else
															<a href="{{config('global.SITE_URL')}}checkout" aria-label="Checkout Information">
																<svg class="svg-timelineinfo" aria-hidden="true" role="img" width="18" height="22">
																	<use href="#svg-timelineinfo" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineinfo"></use>
																</svg>
															</a>
														@endif
													@else
														<svg class="svg-timelineinfo" aria-hidden="true" role="img" width="18" height="22">
															<use href="#svg-timelineinfo" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineinfo"></use>
														</svg>
													@endif
												</span>
												<span class="progressbar-label">
													@if($CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment')
														@if(isset($SelPayMethod) && $SelPayMethod == 'paypal')
															<a href="{{config('global.SITE_URL')}}checkout/paypal" aria-label="PayPal Information">Information</a>
														@elseif(isset($SelPayMethod) && $SelPayMethod == 'AP')
															<a href="{{config('global.SITE_URL')}}checkout/AP" aria-label="AfterPay Information">Information</a>
														@else
															<a href="{{config('global.SITE_URL')}}checkout" aria-label="Checkout Information">Information</a>
														@endif
													@else
															Information
													@endif
												</span>
											</span>
											@if(Session::has('ShoppingCart.OrderType') && Session::get('ShoppingCart.OrderType')!='Store')
												@if(!isset($ShipToStoreVal) || (isset($ShipToStoreVal) && $ShipToStoreVal == 'No'))
												<span class="progressbar-col @if($CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment') is-complete @endif ">
													<span class="progressbar-icon">
														@if($CurrentRoute == 'billing-payment')
															<a href="{{config('global.SITE_URL')}}shipping" aria-label="Shipping Information">
																<svg class="svg-timelineshipping" aria-hidden="true" role="img" width="24" height="20">
																	<use href="#svg-timelineshipping" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineshipping"></use>
																</svg>
															</a>
														@else
															<svg class="svg-timelineshipping" aria-hidden="true" role="img" width="24" height="20">
																<use href="#svg-timelineshipping" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelineshipping"></use>
															</svg>
														@endif
													</span>
													<span class="progressbar-label">
														@if($CurrentRoute == 'billing-payment')
															<a href="{{config('global.SITE_URL')}}shipping" aria-label="Shipping Information">Shipping</a>
														@else
															Shipping
														@endif
													</span>
												</span>
												@endif
											@endif

											<span class="progressbar-col @if($CurrentRoute == 'billing-payment') is-complete @endif ">
												<span class="progressbar-icon">
													<svg class="svg-timelinepayment" aria-hidden="true" role="img" width="14" height="24">
														<use href="#svg-timelinepayment" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-timelinepayment"></use>
													</svg>
												</span>
												<span class="progressbar-label">Payment</span>
											</span>
										</div>
									</div>
								</div>
								<div class="text-center completing-txt" role="status" aria-live="polite">You're almost there! Completing your order takes less than 2 minutes.</div>
							</div>
						</div>
						@endif
                        <div class="row">
                            <div class="col-12 text-center d-none d-md-block">
                                <?php /*
                                <div class="cartnw-step" role="navigation" aria-label="Checkout Steps Navigation">
                                    <ul role="list" aria-label="Checkout Steps">
                                        <li role="listitem">
											<a href="{{config('global.SITE_URL')}}shoppingcart/view" aria-label="View Cart">Cart</a>
										</li>
                                        <li class="@if($CurrentRoute == 'billing') active @endif" role="listitem">
											@if($CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment')
												@if(isset($SelPayMethod) && $SelPayMethod == 'paypal')
												<a href="{{config('global.SITE_URL')}}checkout/paypal" aria-label="Information">Information</a>
												@elseif(isset($SelPayMethod) && $SelPayMethod == 'AP')
												<a href="{{config('global.SITE_URL')}}checkout/AP" aria-label="Information">Information</a>
												@else
												<a href="{{config('global.SITE_URL')}}checkout" aria-label="Information">Information</a>
												@endif
											@else
                                         		Information
											@endif
										</li>
                                         <li class="@if($CurrentRoute == 'billing-shipping') active @endif" role="listitem">
                                             @if($CurrentRoute == 'billing-payment')
                                             	<a href="{{config('global.SITE_URL')}}shipping" aria-label="Shipping">Shipping</a>
                                             @else
                                             	Shipping
                                             @endif
                                         </li>
                                         <li class="@if($CurrentRoute == 'billing-payment') active @endif" role="listitem">Payment</li>
                                    </ul>
                                </div>
                                */?>
                            </div>