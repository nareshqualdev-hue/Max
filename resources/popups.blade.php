<div id="sales-offers-popup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="sales-offers-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="coupon-modal-popup_ajax" data-status="{{ Session::get('showPopUp')}}"></div>
<div id="myModalPopUpLogin" class="modal fade login_popup" tabindex="-1" role="dialog" aria-labelledby="login-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="NicheFragrancesPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="niche-fragrances-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="ProductAlertMePopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="product-alertme-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="EmailFriendPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="email-friend-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="ProductRatingsReviewPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="product-ratings-review-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="ProductQuickViewPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="product-quickview-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="FreeGiftViewPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="free-gift-view-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="AddFundPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="add-fund-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="ShippingCalculatePopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="shipping-calculate-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="FreeShippingPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="free-shipping-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="ShippingServicePopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="shipping-service-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="WholesalerShippingPolicyPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="wholesaler-shipping-policy-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="signInSignUp" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="sign-in-sign-up-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="WholesalerTerms" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="wholesaler-terms-popup-title" aria-modal="true" aria-hidden="true"></div>
<div id="FreeSampleProductsViewPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="middle_popup1" aria-hidden="true"></div>
<div id="SecondBarPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="second-bar-popup-title" aria-modal="true" aria-hidden="true">
    <? $popup = getTopPopupText(); ?>
    <div class="vertical-alignment-helper">
        <div class="modal-dialog modal-sm vertical-align-center">
            <div class="modal-content">
                <a class="close" type="button" data-dismiss="modal" aria-label="Close popup">
                    <svg class="sv-close vam" aria-hidden="true" role="img" width="16" height="16">
                        <use href="#sv-close" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-close"></use>
                    </svg>
                </a>
                <div class="modal-body">
                    <div class="sales-offers-modal">
                        <div class="modal-hd">
                            <h2 class="h1" id="second-bar-popup-title">Holiday Delivery Cut Off Time</h2>
                        </div>
                        <div class="modal-space">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="holidaypopup-content">
                                        <? if(!empty($popup)){
                                        for($p = 0; $p < count($popup); $p++){ ?>
                                            <div class="popuptext-row">
                                                <div class="popuptext-hd">
                                                    <strong><?=$popup[$p]['link_title'];?></strong>								        
                                                </div>
                                                <?=$popup[$p]['link'];?>
                                            </div>
                                        <? } } ?>

                                        <? /* ?>
                                            <div class="popuptext-row" role="region" aria-labelledby="ups-shipping-title">
												<div class="popuptext-hd">
													<strong id="ups-shipping-title">UPS</strong>
													<p>Here are the last days to ship with UPS in 2024 for holiday delivery:</p>
												</div>
												<ul role="list" aria-label="UPS holiday shipping deadlines">
													<li role="listitem"><strong>UPS Ground:</strong> December 15</li>
													<li role="listitem"><strong>UPS 3-Day Select:</strong> December 19</li>
													<li role="listitem"><strong>UPS 2nd Day Air:</strong> December 20</li>
													<li role="listitem"><strong>UPS Next Day Air:</strong> December 23</li>
												</ul>
												<p>UPS does not offer pickup or delivery services on Christmas Day (December 25) or New Year's Day (January 1). UPS Express Critical service is available 24/7/365</p>
											</div>
											<div class="popuptext-row" role="region" aria-labelledby="usps-shipping-title">
												<div class="popuptext-hd">
													<strong id="usps-shipping-title">USPS</strong>
													<p>The United States Postal Service (USPS) holiday shipping deadlines for 2024 are:</p>
												</div>
												<ul role="list" aria-label="USPS holiday shipping deadlines">
													<li role="listitem"><strong>USPS Ground Advantage Service:</strong> December 18</li>
													<li role="listitem"><strong>Priority Mail Service:</strong> December 19</li>
													<li role="listitem"><strong>Priority Mail Express Service:</strong> December 21</li>
												</ul>
												<p>The actual delivery date may vary depending on the origin, destination, Post Office acceptance time, and other conditions.</p>
											</div>
											<div class="popuptext-row" role="region" aria-labelledby="fedex-shipping-title">
												<div class="popuptext-hd">
													<strong id="fedex-shipping-title">FEDEX</strong>
												</div>
												<ul role="list" aria-label="FedEx holiday shipping deadlines">
													<li role="listitem"><strong>FedEx Ground:</strong> Last day to ship is December 13, 2024 for delivery by December 24th</li>
													<li role="listitem"><strong>FedEx Express Saver:</strong> Last day to ship is December 19, 2024</li>
													<li role="listitem"><strong>FedEx 2Day:</strong> Last day to ship is December 21, 2024 for delivery by December 24th</li>
													<li role="listitem"><strong>FedEx Overnight Services:</strong> Last day to ship is December 22, 2024</li>
													<li role="listitem"><strong>FedEx SameDay:</strong> Last day to ship is December 23, 2024</li>
												</ul>
											</div>
                                        <? */ ?>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="VendorItemPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="vendor-item-popup-title" aria-modal="true" aria-hidden="true">
    <div class="vertical-alignment-helper">
        <div class="modal-dialog modal-md vertical-align-center">
            <div class="modal-content">
                <a class="close" type="button" data-dismiss="modal" aria-label="Close popup">
                    <svg class="sv-close vam" aria-hidden="true" role="img" width="16" height="16">
                        <use href="#sv-close" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-close"></use>
                    </svg>
                </a>
                <div class="modal-body">
                    <div class="modal-space">
                        <div class="sales-offers-modal" id="VendorText"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="CheckoutConfirmPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="checkout-confirm-popup-title" aria-modal="true" aria-hidden="true">
    <div class="vertical-alignment-helper">
        <div class="modal-dialog modal-sm vertical-align-center">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="modal-space">
                        <div class="pt-3 pb-3 text-center">
                            <div class="sales-offers-modal lheight-18" id="ChekoutConfirm"></div>
                            <a href="javascript:void(0);" class="btn btn-primary mt-4" type="button" data-dismiss="modal" aria-label="Close popup">OK</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="CheckoutAfterPayPopup" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="checkout-afterpay-popup-title" aria-modal="true" aria-hidden="true">
    <div class="vertical-alignment-helper">
        <div class="modal-dialog modal-sm vertical-align-center">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="modal-space">
                        <div class="pt-3 pb-3 text-center">
                            <div class="sales-offers-modal lheight-18" id="CheckoutAfterPayPop"></div>
                            <a href="javascript:void(0);" class="btn btn-primary mt-4" type="button" data-dismiss="modal" aria-label="Cancel popup">Cancel</a>
                            <a href="{{config('global.SITE_URL')}}checkout/pa" class="btn btn-primary mt-4" aria-label="Refresh cart">Refresh Cart</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="ShippingPickupMethod" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="shipping-pickup-method-popup-title" aria-modal="true" aria-hidden="true">
    <div class="vertical-alignment-helper">
        <div class="modal-dialog modal-md vertical-align-center">
            <div class="modal-content">
                <a class="close" type="button" data-dismiss="modal" aria-label="Close popup">
                    <svg class="sv-close vam" aria-hidden="true" role="img" width="16" height="16">
                        <use href="#sv-close" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-close"></use>
                    </svg>
                </a>
                <div class="modal-body">
                    <div class="modal-space">
                        <div class="text-center">
                            <div class="sales-offers-modal" id="PickupTextValue"></div>
                            <a href="javascript:void(0);" class="btn btn-primary mt-4" type="button" data-dismiss="modal" aria-label="Close popup">Ok</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>