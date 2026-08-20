@extends('layouts.app')
@section('content')
<main role="main" id="main-content" tabindex="-1" aria-label="Amazon Order Receipt Main Content">
    <section class="checkout-page order-confirm pt-md-5 pb-md-5" role="region" aria-label="Order Confirmation Section">
        <div class="container">
            <div class="mainhd text-left pt-4 pb-4 d-md-none">
                <h2 id="order-receipt-heading-mobile">Order Receipt</h2>
            </div>
            <div class="row">
                <div class="col-lg-8">
                    <div class="row pr-lg-4 pr-0">
                        <div class="col-12">
                            <div class="confm-top d-none d-md-inline-block w-100" role="region" aria-label="Order Confirmation Message">
                                <h3 class="cart-hd mb-2">Hooray, your order is placed! Order Receipt!</h3>
                                <p>Your order number is {{$MainOrder->AmazonOrderID}}</p>
                                @if($Payment_Method_Message != '')
                                    {!!$Payment_Method_Message!!}
                                    @if($wholesale_terms!="")
                                        {{$wholesale_terms}}
                                    @endif
                                @else
                                    <p>A confirmation email is headed your<br>
                                        way at {{$MainOrder->bill_email}}.
                                        @if($wholesale_terms!="")
                                            {{$wholesale_terms}}
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <div class="confm-top d-md-none text-center" role="region" aria-label="Order Confirmation Message Mobile">
                                @if($Payment_Method_Message != '')
                                    {!!$Payment_Method_Message!!}
                                    @if($wholesale_terms!="")
                                        {{$wholesale_terms}}
                                    @endif
                                @else
                                    <h3 class="cart-hd mb-2 color-green">Thank you for placing your order.</h3>
                                    <p class="p-3">We hope you enjoyed shopping with us. Your order will be processed as
                                        soon as possible. We will contact you with updates. Please allow 24hrs to process
                                        the payment. An E-mail Confirmation will be sent upon payment received.
                                        @if($wholesale_terms!="")
                                            {{$wholesale_terms}}
                                        @endif
                                    </p>
                                @endif
                                <h6><strong>Your Order Number : <span class="color-red">{{$MainOrder->AmazonOrderID}}</span></strong></h6>
                            </div>
                            <div class="confm-middle d-md-none" role="region" aria-label="Order Receipt Items Mobile">
                                <h3 class="cart-hd mb-3">
                                    <span class="order-date float-left">Date: {{date('m/d/Y h:i:s',strtotime($MainOrder->OrderDate))}}</span>
                                    <a href="#" class="print-btn float-right" role="button" aria-label="Print Order Receipt">
                                        <svg class="sv-print vam" aria-hidden="true" role="img" width="14" height="14">
                                            <use href="#sv-print" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-print"></use>
                                        </svg>
                                    </a>
                                </h3>
                                <div class="your-bag" role="region" aria-label="Order Receipt Items List Mobile">
                                    <div class="your-bag-inner">
                                        <ul>
                                            @foreach($OrderDetails as $key => $Order)
                                            <li>
                                                <div class="row">
                                                    <div class="col-4 col-sm-3">
                                                        <img alt="Product Image" src="https://media.maxaroma.com/9cd29e92-4b8f-436f-ad1f-8e368b30dc1d/{{$Order->Image}}" class="product-img img-fluid">
                                                    </div>
                                                    <div class="col-8 col-sm-6">
                                                        <div class="cart-product pb-1">
                                                            <a href="{{$Order->ProdLink}}" aria-label="View product: {!! strip_tags($Order->product_name) !!}">{!!$Order->product_name!!}
                                                            </a>
                                                        </div>
                                                        <div class="pb-1 SKU">
                                                            <span>Item SKU: {{$Order->Item_SKU}}</span>
                                                        </div>
                                                        <div class="pb-1 qty">
                                                            <span>Quantity: {{$Order->Item_Quantity}}</span>
                                                        </div>
                                                        <div class="cart-price d-block d-sm-none">
                                                            <span>{{Price($Order->Item_Principal)}}</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3 text-right d-none d-sm-block">
                                                        <div class="cart-price">
                                                            <span>{{Price($Order->Item_Principal)}}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="confm-bottom d-none d-md-inline-block w-100 text-center" role="region" aria-label="Continue Shopping Section">
                                <a href="#" class="mb-3" aria-label="Order Receipt Banner">
                                    <img src="{{config('global.SITE_IMAGES')}}cofim-banner.png" alt="Order Receipt Banner" class="img-fluid">
                                </a>
                                <a class="max_continuesp_btn btn btn-primary mt-3" href="{{url('/')}}" role="button" aria-label="Continue Shopping">continues shopping</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-4 mt-md-0">
                    <h3 class="cart-hd mb-3 d-none d-md-inline-block w-100" id="order-receipt-heading">
                        <span>Order Receipt</span>
                        <div class="float-right">
                            <span class="order-date">Date: {{date('m/d/Y h:i:s',strtotime($MainOrder->OrderDate))}}</span>
                            <a href="#" class="print-btn" role="button" aria-label="Print Order Receipt">
                                <svg class="sv-print vam" aria-hidden="true" role="img" width="14" height="14">
                                    <use href="#sv-print" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-print"></use>
                                </svg> Print
                            </a>
                        </div>
                    </h3>
                    <div class="your-bag d-none d-md-inline-block w-100" role="region" aria-labelledby="order-receipt-heading" aria-label="Order Receipt Items List">
                        <div class="your-bag-inner">
                            <ul>
                                @foreach($OrderDetails as $key => $Order)
                                <li>
                                    <div class="row">
                                        <div class="col-4 col-sm-3">
                                            <img alt="Product Image" src="{{$Order->Image}}" class="product-img img-fluid">
                                        </div>
                                        <div class="col-8 col-sm-6">
                                            <div class="cart-product pb-1">
                                                <a href="{{$Order->ProdLink}}" aria-label="View product: {!! strip_tags($Order->product_name) !!}">{!!$Order->product_name!!}</a>
                                            </div>
                                            <div class="pb-1 SKU">
                                                <span>Item SKU: {{$Order->Item_SKU}}</span>
                                            </div>
                                            <div class="pb-1 qty">
                                                <span>Quantity: {{$Order->Item_Quantity}}</span>
                                            </div>
                                            <div class="cart-price d-block d-sm-none">
                                                <span>{{Price($Order->Item_Principal)}}</span>
                                            </div>
                                        </div>
                                        <div class="col-sm-3 text-right d-none d-sm-block">
                                            <div class="cart-price">
                                                <span>{{Price($Order->Item_Principal)}}</span>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="order-summry" role="region" aria-label="Order Summary Table">
                        <div class="row">
                            <div class="col-12">
                                <table class="table text-left table-border-none table-borderless" role="table" aria-label="Order Summary">
                                    <tbody>
                                        <tr>
                                            <td>Subtotal:</td>
                                            <td class="text-right">
                                                <strong>{{Price($MainOrder->OrderSubTotal)}}</strong>
                                            </td>
                                        </tr>
                                        @foreach($AllCharges as $ckey => $Charge)
                                        @if($MainOrder->{$Charge['field']} > 0 )
                                            <tr>
                                                <td>{{$Charge['label']}}:</td>
                                                <td class="text-right">
                                                    <strong>{{Price($MainOrder->{$Charge['field']})}}</strong>
                                                </td>
                                            </tr>
                                        @endif
                                        @endforeach
                                        @foreach($AllDiscounts as $dkey => $Discount)
                                            @if($MainOrder->{$Discount['field']} > 0 )
                                                <tr>
                                                    <td>{{$Discount['label']}}:</td>
                                                    <td class="text-right">
                                                        <strong>-{{Price($MainOrder->{$Discount['field']})}}</strong>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        <tr class="cart-total">
                                            <td class="border-top" id="order-total-label">Order Total</td>
                                            <td class="text-right border-top">
                                                <strong aria-labelledby="order-total-label">{{Price($MainOrder->OrderTotal)}}</strong>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="small-address d-none d-md-inline-block w-100" role="region" aria-label="Order Addresses and Payment">
                        <ul>
                            @if($MainOrder->is_only_gc == 0)
                            <li>
                                <h4 class="mb-2" id="shipping-address-heading">Shipping to:</h4>
                                <p class="mb-0" aria-labelledby="shipping-address-heading">
                                    {{$MainOrder->Ship_Name}},<br>
                                    {{$MainOrder->Ship_AddressFieldOne}},
                                    @if(trim($MainOrder->Ship_AddressFieldTwo) != '')
                                    <br>{{$MainOrder->Ship_AddressFieldTwo}},
                                    @endif
                                    @if(trim($MainOrder->Ship_AddressFieldThree) != '')
                                    <br>{{$MainOrder->Ship_AddressFieldThree}},
                                    @endif
                                    <br>{{$MainOrder->Ship_City}},
                                    {{$MainOrder->Ship_State}} - {{$MainOrder->Ship_PostalCode}},
                                    {{$MainOrder->Ship_CountryCode}}, {{$MainOrder->ship_phone}}<br>
                                    {{$MainOrder->BuyerEmailAddress}}
                                </p>
                            </li>
                            @endif
                            <li>
                                <h4 class="mb-2" id="payment-method-heading">Payment Method:</h4>
                                <p class="mb-0" aria-labelledby="payment-method-heading">{{$MainOrder->payment_method}}</p>
                            </li>
                            @if($MainOrder->is_only_gc == 0)
                            <li>
                                <h4 class="mb-2" id="shipping-method-heading">Shipping Method:</h4>
                                <p class="mb-0" aria-labelledby="shipping-method-heading">{!!$MainOrder->fullshipping_info!!}</p>
                            </li>
                            @endif
                            @if($MainOrder->gift_from!="" || $MainOrder->gift_to!="" ||
                                $MainOrder->gift_message_customer!="" || $MainOrder->free_gift!="")
                                @if($MainOrder->gift_from!="")
                                    <p class="mb-0"><strong>From:</strong> {{$MainOrder->gift_from}}</p>
                                @endif
                                @if($MainOrder->gift_to!="")
                                    <p class="mb-0"><strong>To:</strong> {{$MainOrder->gift_to}}</p>
                                @endif
                                @if($MainOrder->gift_message_customer!="")
                                    <p class="mb-0"><strong>Customer Message:</strong> {{$MainOrder->gift_message_customer}}</p>
                                @endif
                                @if($MainOrder->free_gift!="")
                                    <p class="mb-0"><strong>{{$MainOrder->free_gift}}</strong></p>
                                @endif
                            @endif
                        </ul>
                    </div>
                    <div class="max_need_help mt-4" role="region" aria-label="Need Help Section">
                        <h4 class="">Need Help? <a href="contact-us.html" class="">Contact Us</a></h4>
                        <ul>
                            <li>
                                <img src="{{config('global.SITE_IMAGES')}}phon.jpg" alt="Phone Icon" class="left">
                                <small class="">
                                    <a href="tel:{{config('Settings.TOLL_FREE_NO')}}" aria-label="Call Toll Free Number">{{config('Settings.TOLL_FREE_NO')}}</a> (Toll Free),
                                    <a href="tel:{{config('Settings.INTERNATIONAL_PHONE_NO')}}" aria-label="Call International Number">{{config('Settings.INTERNATIONAL_PHONE_NO')}}</a> (Outside of USA)<br>
                                </small>
                            </li>
                            <li>
                                <svg class="svg-whatsapp vam left" aria-hidden="true" role="img" width="20" height="20">
                                    <use href="#svg-whatsapp" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-whatsapp"></use>
                                </svg>
                                <small class="">
                                    <a href="tel:{{config('Settings.TOLL_FREE_NO')}}" aria-label="Text Toll Free Number">{{config('Settings.TOLL_FREE_NO')}}</a> (Text Number)
                                </small>
                            </li>
                            <li>
                                <img src="{{config('global.SITE_IMAGES')}}email.jpg" alt="Email Icon" class="left">
                                <small>
                                    <a href="mailto:{{config('Settings.ADMIN_MAIL')}}" class="grey_text" aria-label="Email Us">{{config('Settings.ADMIN_MAIL')}}</a>
                                </small>
                            </li>
                        </ul>
                    </div>
                    <div class="chekout-main-btn" role="region" aria-label="Continue Button Section">
                        <div class="row">
                            <div class="col-12 mt-md-0">
                                <a class="btn btn-secondary d-block checkout-btn" href="{{url('/')}}" role="button" aria-label="Continue">Continue</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection