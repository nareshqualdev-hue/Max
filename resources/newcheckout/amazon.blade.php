@if($CartAttr["Amazon_pay_Checkout"] == 'Yes' && $onlyGCPurchased != '1')
    @php $JSSAmazonValVer = filemtime(config('global.SITE_JS_CORE_PATH').'amazon.js'); @endphp
    <script type="text/javascript" src="{{config('global.SITE_JS_CORE')}}amazon.js?ver={{$JSSAmazonValVer}}"></script>
    <script type="text/javascript" src="{{ config('JS_SERVER_URL') }}" async></script>
@endif