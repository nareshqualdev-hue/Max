<h3 class="cart-hd d-none d-md-block" id="shipping-method-heading">
    <span>Shipping Method</span> 
    <a href="javascript:void(0);" class="float-right edit-btn btn btn-primary shipback" data-index="3" role="button" aria-label="Edit Shipping Method">Edit</a>
</h3>
<h3 class="cart-hd d-md-none" id="shipping-method-heading-mobile">
    <span>2. Shipping Method</span> 
    <a href="javascript:void(0);" class="float-right edit-btn btn btn-primary shipback" data-index="3" role="button" aria-label="Edit Shipping Method">Edit</a>
</h3>
<div class="pt-3" role="region" aria-labelledby="shipping-method-heading shipping-method-heading-mobile" aria-label="Selected Shipping Method">
    @if(Session::has('ShoppingCart.Shipping') && count(Session::get('ShoppingCart.Shipping')) > 0)
        <p class="mb-0">
            <strong>{{strip_tags(Session::get('ShoppingCart.Shipping.ShippingMethodName'))}}</strong>
            @if(Session::has('ShoppingCart.Shipping.ShippingDays') && Session::get('ShoppingCart.Shipping.ShippingDays') != '')
                <br> 
                EST : {!!Session::get('ShoppingCart.Shipping.ShippingDays')!!}
            @endif
        </p>
    @endif
</div>