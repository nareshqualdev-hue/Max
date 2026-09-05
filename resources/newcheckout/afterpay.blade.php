@if($Is_Afterpay_Checkout == "Yes")
    @php $JSSAafterpayValVer = filemtime(config('global.SITE_JS_CORE_PATH').'afterpay-new.js'); @endphp
    <script type="text/javascript" src="{{config('global.SITE_JS_CORE')}}afterpay-new.js?ver={{$JSSAafterpayValVer}}"></script>
    <script src="{{$token_js_url}}" async onload="initAfterpayCheckout()"></script>
@endif