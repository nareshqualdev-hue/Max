@extends('layouts.app')
@section('content')
@include('checkout.checkout-header')
<div class="col-12">
    <div class="checkout-cont" style="max-width:460px;" role="region" aria-label="Shipping Methods Section">
        @if(Session::has('PlaceOrderError'))
            <x-message :attr="[ 'classname' => 'alert alert-danger', 'message' => Session::get('PlaceOrderError'), 'mid' => 'error_order']"/>
        @endif	
        
        <ul class="checkout-steps" role="list" aria-label="Checkout Steps">
            <li role="listitem" aria-label="Shipping Information">
                <div class="row">
                    <div class="col-md-12">
                        <h3 class="cart-hd-sub text-center pt-md-0" id="shipping-info-heading">
                            <span>Shipping Information</span> 
                        </h3>
                    </div>
                    <div class="col-8 pb-md-5">
                        <p class="mb-0 lheight-18" aria-labelledby="shipping-info-heading">
                            @if(isset($Shipping))
                            {{$Shipping['first_name']}} {{$Shipping['last_name']}}<br>
                            {{$Shipping['address1']}},
                            @if($Shipping['address2'] != '')
                                {{$Shipping['address2']}},
                            @endif
                            <br> 
                            {{$Shipping['city']}}, {{$Shipping['state']}} - {{$Shipping['zip']}}, {{$Shipping['country']}}<br> 
                            {{$Shipping['email']}}
                            @endif
                        </p>
                    </div>
                    @if(Session::has('ShoppingCart.AfterPay.Checkout_Token'))
                    <div class="col-4" align="right">
                        <a href="javascript:void(0);" class="float-right edit-btn btn btn-secondary" data-index="2" id="EditAfter" role="button" aria-label="Edit Shipping Information">Edit</a>
                    </div>
                     @else
                    <div class="col-4" align="right">
                        <a href="{{config('global.SITE_URL')}}checkout{{$payment_method_url}}" class="float-right edit-btn btn btn-secondary" data-index="2" role="button" aria-label="Edit Shipping Information">Edit</a>
                    </div>
                    @endif   
                </div>    
            </li>
            <li class="billtab shipping-method border-md-top" role="listitem" aria-label="Shipping Methods">
                @include('checkout.shipping-methods',[$CartDetails,$CartAttr])
            </li>
        </ul>
    </div>
</div>
@include('checkout.checkout-footer')
@endsection
<script type="text/javascript">
var routenm='<?php echo Route::currentRouteName();?>'; 
</script>