<div class="col-12"
    <div
        id="swell-customer-identification"
        data-authenticated="true"
        data-email="{{ $email }}"
        data-id="{{ $customer_id }}"
        data-token="{{ $token }}"
        style="display:none;">
    </div>

    <div id="yotpo-loyalty-cart-data"
         data-free-product-points="0"
         data-applied-coupon-points="0"
         data-cart-id="{{ $cart_id }}"
         data-has-paid-product="true"
         data-has-free-product="false"
         style="display:none;">
    </div>

    <div id="yotpo-loyalty-checkout-data"
         cart-subtotal-cents="{{ $cart_subtotal_cents }}"
         style="display:none;">
    </div>

    <div class="yotpo-widget-instance"
         data-yotpo-instance-id="90560"
         data-yotpo-widget-type="checkout_redemptions">
    </div>

</div>