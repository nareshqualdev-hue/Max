@extends('layouts.app')
@section('content')
<div class="showDwnMessage" style="display:none" role="status" aria-live="polite">Please wait while your transaction is being processed.... Please do not refresh the page.</div>
<style>.clsgreen {color: green;font-weight: 600;}</style>
@include('checkout.checkout-header')
<div class="col-12">
    <div class="checkout-cont" style="max-width:460px;" role="region" aria-label="Payment Methods Section">
        @if(Session::has('PlaceOrderError'))
            <x-message :attr="[ 'classname' => 'alert alert-danger', 'message' => Session::get('PlaceOrderError'), 'mid' => 'error_order']"/>
        @endif
        <ul class="checkout-steps" role="list" aria-label="Checkout Steps">
            <li role="listitem" aria-label="Shipping & Billing Information">
                <div class="row">
                    <div class="col-md-12">
                        <h3 class="cart-hd-sub text-center pt-md-0" id="shipping-billing-heading">
                            <span>Shipping & Billing Information</span>
                        </h3>
                    </div>
                    <div class="col-8 pb-md-5">
                       @if(isset($onlyGCPurchasedVal) && $onlyGCPurchasedVal==1)
                       <p class="mb-0 lheight-18" aria-labelledby="shipping-billing-heading">
                            {{$Billing['first_name']}} {{$Billing['last_name']}}<br>
                            {{$Billing['address1']}},
                            @if($Billing['address2'] != '')
                                {{$Billing['address2']}},
                            @endif
                            <br>
                            {{$Billing['city']}}, {{$Billing['state']}} - {{$Billing['zip']}}, {{$Billing['country']}}<br>
                            {{$Billing['email']}}
                        </p>
                        @else
                        <p class="mb-0 lheight-18" aria-labelledby="shipping-billing-heading">
                            {{$Shipping['first_name']}} {{$Shipping['last_name']}}<br>
                            {{$Shipping['address1']}},
                            @if($Shipping['address2'] != '')
                                {{$Shipping['address2']}},
                            @endif
                            <br>
                            {{$Shipping['city']}}, {{$Shipping['state']}} - {{$Shipping['zip']}}, {{$Shipping['country']}}<br>
                            {{$Shipping['email']}}
                        </p>
                        @endif
                    </div>
                     @if(Session::has('ShoppingCart.AfterPay.Checkout_Token'))
                    <div class="col-4" align="right">
                        <a href="javascript:void(0);" class="float-right edit-btn btn btn-secondary" data-index="2" id="EditAfter" role="button" aria-label="Edit Shipping & Billing Information">Edit</a>
                    </div>
                     @else
                    <div class="col-4 pb-md-5" align="right">
                        <a href="{{config('global.SITE_URL')}}checkout{{$payment_method_url}}" class="edit-btn btn btn-secondary" data-index="2" role="button" aria-label="Edit Shipping & Billing Information">Edit</a>
                    </div>
                     @endif
                </div>
            </li>
            @if(isset($onlyGCPurchasedVal) && $onlyGCPurchasedVal==1)
            <li></li>
            @else
            <li class="border-md-top" role="listitem" aria-label="Shipping Method">
                <div class="row">
                    <div class="col-12 d-md-none">
                        <div class="mobile-shadow mb-0" aria-hidden="true">&nbsp;</div>
                    </div>
                    <div class="col-md-12">
                        <h3 class="cart-hd-sub text-center" id="shipping-method-heading">
                            <span>Shipping Method</span>
                        </h3>
                        <div class="pb-2">Place your order in the next <span class="clsgreen">{{$datediff}}</span> for delivery by the listed dates.</div>
                    </div>
                    <div class="col-8 pb-md-5">
                        @if(Session::has('ShoppingCart.Shipping') && count(Session::get('ShoppingCart.Shipping')) > 0)
                            <p class="mb-0 lheight-18" aria-labelledby="shipping-method-heading">
                                {{strip_tags(Session::get('ShoppingCart.Shipping.ShippingMethodName'))}}
                                @if(Session::has('ShoppingCart.Shipping.ShippingDays') && Session::get('ShoppingCart.Shipping.ShippingDays') != '')
                                    <br>
                                    Estimated Delivery: On or before {!!Session::get('ShoppingCart.Shipping.ShippingDays')!!}
                                @endif
                            </p>
                            <div class="clsgreen" id="divontime">98% on-time delivery rate</div>
                        @endif
                    </div>
                    <div class="col-4 pb-md-5" align="right">
                        <a href="{{config('global.SITE_URL')}}checkout{{$payment_method_url}}" class="edit-btn btn btn-secondary" data-index="3" role="button" aria-label="Edit Shipping Method">Edit</a>
                    </div>
                </div>
            </li>
            @endif
            <li class="billtab shipping-method border-md-top" role="listitem" aria-label="Payment Methods">
                @include('checkout.payment-methods',[$CartDetails,$CartAttr])
            </li>
        </ul>
    </div>
</div>
@include('checkout.checkout-footer')
@endsection

<script type="text/javascript">
var routenm='<?php echo Route::currentRouteName();?>';
</script>
