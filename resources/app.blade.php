<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<!-- Responsive Metatags -->
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<!-- <meta name="viewport" content="width=device-width, initial-scale=1"> -->
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<meta name="robots" content="noindex, nofollow" />
	@if(config('global.SITE_MODE') == 'Live')
	<meta name="p:domain_verify" content="798c033e248145d3c22b4ea5d09f56a9" />
	<meta name="google-site-verification" content="htgE48PlMcKi9JNyU_bSnIw3-ZeWHYBOPDX_NgiAIhc" />
	<meta name="p:domain_verify" content="3432dcf2cb5d491bb31fd877350e4996" />
	@endif
	<!-- Responsive Metatags -->
	<meta name="csrf-token" id="csrf-token" content="{{ csrf_token() }}">
	<title>@if(isset($meta_title) && $meta_title!=''){!! $meta_title !!}@else{{config('global.META_TITLE')}}@endif
	</title>
	<meta name="Keywords" data-type="text"
		content="@if(isset($meta_keywords) && $meta_keywords!=''){{stripslashes($meta_keywords)}}@else{{config('global.META_KEYWORDS')}}@endif">
	<meta name="Description" data-type="text"
		content="@if(isset($meta_description) && $meta_description!=''){{stripslashes($meta_description)}}@else{{config('global.META_DESCRIPTION')}}@endif">
	<!--<script type="text/javascript" src="//script.crazyegg.com/pages/scripts/0103/4351.js" async="async" ></script>-->

	@if(CanonicalURL() !='')
	<link rel="canonical" href="{{CanonicalURL()}}" />
	@elseif(isset($CanonicalURL) && $CanonicalURL!='')
	<link rel="canonical" href="{{$CanonicalURL}}" />
	@endif
	<!-- Favicon Icon -->
	<meta name="facebook-domain-verification" content="4psthqxc0xek0mjwrcyzw7apcaqrkw" />
	<meta name="facebook-domain-verification" content="o6zz49kna7k8m1e6dtp4b9a7rvv8s3" />

	<link rel="icon" href="{{ config('global.SITE_IMAGES').'favicon.ico' }}" type="image/x-icon" />
	<link rel="icon" href="{{ config('global.SITE_IMAGES').'favicon.ico' }}" type="ico" />
	<link rel="SHORTCUT ICON" href="{{ config('global.SITE_IMAGES').'favicon.ico' }}" />
	<!-- Favicon Icon -->

	<!-- Stylesheet File Start -->
	@php
	$FilePath = config('global.SITE_URL').'public';
	$ConfigMsg = json_encode(config('message'));
	$CategorySize = config('Settings.CATEGORY_TITLE_SIZE');
	$fileVerVal = time();
	$jqueryUIVer = filemtime(config('global.SITE_STYLE_CORE_PATH').'jquery-ui1.12.1.css');
	$customVer = filemtime(config('global.SITE_STYLE_CORE_PATH').'custom.css');
	$bundleVer = filemtime(config('global.SITE_STYLE_CORE_PATH').'all.bundle.css');
	$commonVer = filemtime(config('global.SITE_STYLE_CORE_PATH').'common-min.css');
	$HeaderVer = filemtime(config('global.SITE_STYLE_CORE_PATH').'header-footer-min.css');
	$bootstrapIconsVer = filemtime(config('global.SITE_STYLE_CORE_PATH').'bootstrap-icons-1_13_1.css');
	$posstyleVer = filemtime(config('global.SITE_STYLE_CORE_PATH').'pos-style.css');
	$slickVer = filemtime(config('global.SITE_STYLE_CORE_PATH').'slick-min.css');

	$jqueryJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'jquery-3.4.1.min.js');
	$jqueryuiJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'jquery-ui1.12.1.js');
	$jqueryvalidateJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'jquery.validate.min.js');
	$jqueryJSselectboxVer = filemtime(config('global.SITE_JS_CORE_PATH').'jquery.selectbox-0.2.min.js');
	$ModalJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'modal.js');
	$CommonJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'common.js');
	$PopupJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'popup.js');
	$SmoothScrollJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'smooth-scroll.js');
	$MenunewJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'menu-new.js');
	$SlimscrollJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'jquery.slimscroll.min.js');
	$HeadBottomJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'head-bottom.js');
	$ShoppingcartJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'shoppingcart.js');
	$NewsletterJSVer = filemtime(config('global.SITE_JS_CORE_PATH').'newsletter.js');
	$ShoppingcartJSNewVer = filemtime(config('global.SITE_JS_CORE_PATH').'shoppingcart_new.js');
	$POSCheckoutJSNewVer = filemtime(config('global.SITE_JS_CORE_PATH').'poscheckout.js');
	$CommonJSNewVer = filemtime(config('global.SITE_JS_CORE_PATH').'common_new.js');
	$JSSlickValVer = filemtime(config('global.SITE_JS_CORE_PATH').'slick.min.js');
	@endphp

	@if($CurrentRoute != 'secure-checkout111' && $CurrentRoute != 'checkout-new111sd')
	<link rel="stylesheet" href="{{ config('global.SITE_STYLE_CORE')}}common-min.css?ver={{$commonVer}}">
	<link rel="preload" as="style" href="{{ config('global.SITE_STYLE_CORE')}}header-footer-min.css?ver={{$HeaderVer}}" onload="this.rel='stylesheet'">
	<link rel="preload" as="style" href="{{ config('global.SITE_STYLE_CORE')}}custom.css?ver={{$customVer}}" onload="this.rel='stylesheet'">
	@endif
	<!-- <link rel="stylesheet" type="text/css" media="all" href="{{ config('global.SITE_STYLE_CORE')}}all.bundle.css?ver={{$bundleVer}}"> -->
	<link rel="preload" as="style" href="{{ config('global.SITE_STYLE_CORE')}}slick-min.css?ver={{$slickVer}}" onload="this.rel='stylesheet'">
	<link rel="preload" as="style" href="{{ config('global.SITE_STYLE_CORE')}}jquery-ui1.12.1.css?ver={{$jqueryUIVer}}" onload="this.rel='stylesheet'">

	@if(request()->is('store/*') || (Session::has('sess_storeuserid') && Session::get('sess_storeuserid')!=''))
	<!-- This css add only for POS -->
	<link rel="stylesheet" type="text/css" media="all" href="{{ config('global.SITE_STYLE_CORE')}}bootstrap-icons-1_13_1.css?ver={{$bootstrapIconsVer}}">
	<link rel="preload" as="style" href="{{ config('global.SITE_STYLE_CORE')}}pos-style.css?ver={{$posstyleVer}}" onload="this.rel='stylesheet'">
	<!-- This css add End only for POS -->
	@endif

	@if(isset($CSSFILES))
		@foreach($CSSFILES as $CSSFILESVAL)
			@php
				if($CSSFILESVAL == 'slick.css')
				{
					continue;
				}
				$CSSValVer = filemtime(config('global.SITE_STYLE_CORE_PATH').$CSSFILESVAL);
			@endphp
			<link rel="preload" as="style" href="{{ config('global.SITE_STYLE_CORE')}}{{$CSSFILESVAL}}?ver={{$CSSValVer}}" onload="this.rel='stylesheet'">
			{{-- <link rel="stylesheet" type="text/css" media="all" href="{{ config('global.SITE_STYLE_CORE')}}{{$CSSFILESVAL}}?ver={{$CSSValVer}}">--}}
		@endforeach
	@endif

	<script type="text/javascript" src="{{config('global.SITE_JS_CORE')}}jquery-3.4.1.min.js?ver={{$jqueryJSVer}}"></script>
	<!--@include('css.indexcss',(isset($CSSFILES)?['CSSFILES' => $CSSFILES,'NewHomeDesign' => (isset($NewHomeDesign)?$NewHomeDesign:'')]:['CSSFILES' =>[]]))-->

</head>

<body class="{{ $body_class ?? ''}}" data-test="{{Session::get('new')}}">
	<a href="#main-content" class="skip-link visually-hidden">Skip to Content</a>
	<a href="#getHelpChatNew" class="skip-link visually-hidden">Skip to customer support chat</a>
	@if($CurrentRoute == 'referral-program')
		<div id="page-spinner" style="display: block;"></div>
	@else
		<div id="page-spinner"></div>
	@endif

	@if($CurrentRoute != 'secure-checkout' && $CurrentRoute != 'checkout-new')
		@if($CurrentRoute == 'billing' || $CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment' ||
		$CurrentRoute == 'order-receipt' || $CurrentRoute == 'phoneorder_payment_receipt' || $CurrentRoute ==
		'AmazonBilling' || $CurrentRoute == 'AmazonBillingFund' || $CurrentRoute == 'AmazonPhoneOrderCheckout' ||
		$CurrentRoute == 'billing' || $CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment' ||
		$CurrentRoute == 'order-receipts' || $CurrentRoute == 'store-payment')
			@include('layouts.blank')
		@else
			@include('layouts.header')
		@endif
	@endif

		@yield('content')

	@if($CurrentRoute != 'secure-checkout' && $CurrentRoute != 'checkout-new')
		@if($CurrentRoute != 'billing' && $CurrentRoute != 'billing-shipping' && $CurrentRoute != 'billing-payment' &&
		$CurrentRoute != 'order-receipt' && $CurrentRoute != 'phoneorder_payment_receipt' && $CurrentRoute !=
		'AmazonBilling' && $CurrentRoute != 'AmazonBillingFund' && $CurrentRoute != 'AmazonPhoneOrderCheckout' &&
		$CurrentRoute !='billing' && $CurrentRoute != 'billing-shipping' && $CurrentRoute != 'billing-payment' &&
		$CurrentRoute != 'temp_proddetails_code' && $CurrentRoute != 'order-receipts' && $CurrentRoute != 'store-payment')
			@include('layouts.footer')
		@endif
	@endif
		<input type="hidden" id="site_currency" value="{{Session::get('currency_code')}}" />
		<input type="hidden" id="apminamt" value="{{Session::get('Afterpay.Min_AP_AMT')}}" />
		<input type="hidden" id="apmaxamt" value="{{Session::get('Afterpay.Max_AP_AMT')}}" />

		@include('layouts.svg')

		<!-- NEW ARRANGEMENT -->
		<!-- <style>
			@font-face{font-family:'Montserrat';src:url('/public/fonts/Montserrat-Regular.woff') format('woff');font-display:swap}
		</style> -->
		<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" onload="this.rel='stylesheet'">
		<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre&display=swap" onload="this.rel='stylesheet'">
		<!-- <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet"> -->
		<script>
			var locationValue = window.location.hash;
			var CurrLocation = window.location.href;
			if(locationValue.indexOf("#") >= 0)
			{
				locationValue = locationValue.replace("#","");
				locationValues = locationValue.split("&");
				locationValues = locationValues.filter((v, i, a) => a.indexOf(v) === i);
				locationValues = locationValues.join("/");
				var PageName = '';

				if(CurrLocation.indexOf("dealofweek.html") >= 0)
				{
					PageName = site_url+'dealofweek.html/'+locationValues+"/view";
				}
				else if(CurrLocation.indexOf("maxtwoday.html") >= 0)
				{
					PageName = site_url+'maxtwoday.html/'+locationValues+"/view";
				} else {
					if(document.getElementById("catName") && document.getElementById("catName").value != ''){
						var Page = document.getElementById("catName").value;
						Page = Page.trim();
					} else {
						var Page = document.getElementById("pageTitle").value;
						Page = Page.trim();
					}
					if(locationValues.substring(0, 1) != '/')
						locationValues = '/'+locationValues;
						//PageName = site_url+Page+"/p4u"+locationValues+"/view";
					if(Page != ''){
						PageName = site_url+Page+"/p4u"+locationValues+"/view";
					} else {
						PageName = site_url+Page+"p4u"+locationValues+"/view";
					}
					//alert(locationValues);
				}
				window.location.href = PageName;
			}
		</script>
		<script type="text/javascript">
			var newroutenm = '<?php echo Route::currentRouteName(); ?>';
			var modal_js = "{{config('global.SITE_JS_CORE')}}modal.js?ver={{$ModalJSVer}}";
			var site_url = '{{config("global.SITE_URL")}}';

			function GetMessage(Module, Message) {
				var config = <?php echo $ConfigMsg ?>;
				return config[Module][Message];
			}
		</script>
		@if($CurrentRoute != 'billing' && $CurrentRoute != 'billing-shipping' && $CurrentRoute != 'billing-payment' && $CurrentRoute != 'order-receipt' && $CurrentRoute != 'phoneorder_payment_receipt' && $CurrentRoute != 'AmazonBilling' && $CurrentRoute != 'AmazonBillingFund' && $CurrentRoute != 'AmazonPhoneOrderCheckout' && $CurrentRoute != 'billing' && $CurrentRoute != 'billing-shipping' && $CurrentRoute != 'billing-payment' && $CurrentRoute != 'order-receipts' && $CurrentRoute != 'store-payment')
			<script type="text/javascript"> var yotpo_app_key = "MQY5nd09CBJk1IVKoMXrZmiUjvJj7s9krlkG1eL8";</script>
		@endif

		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}jquery.slimscroll.min.js?ver={{$SlimscrollJSVer}}"></script>
		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}jquery-ui1.12.1.js?ver={{$jqueryuiJSVer}}"></script>
		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}jquery.validate.min.js?ver={{$jqueryvalidateJSVer}}"></script>
		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}jquery.selectbox-0.2.min.js?ver={{$jqueryJSselectboxVer}}"></script>
		<script defer type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>
		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}slick.min.js?ver={{$JSSlickValVer}}"></script>
		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}modal.js?ver={{$ModalJSVer}}"></script>

		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}popup.js?ver={{$PopupJSVer}}"></script>
		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}smooth-scroll.js?ver={{$SmoothScrollJSVer}}"></script>
		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}menu-new.js?ver={{$MenunewJSVer}}"></script>
		<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}head-bottom.js?ver={{$HeadBottomJSVer}}"></script>
		<script defer fertype="text/javascript" src="{{config('global.SITE_JS_CORE')}}newsletter.js?ver={{$NewsletterJSVer}}"></script>

		@if($CurrentRoute == 'home')
			<script defer src="https://cdn-widgetsrepository.yotpo.com/v1/loader/uIl5V6C_LVeCr5BT4bhDLQ" async></script>
		@endif

		@if($CurrentRoute == 'referral-program')
			<script>
				document.addEventListener("DOMContentLoaded", function() {
					var checkData = setInterval(function() {
						if ($('.yotpo-widget-referral-widget').length) {
							$("#page-spinner").hide();
							clearInterval(checkData);
						}
					}, 1000);
				});
			</script>
		@endif

		@if(($CurrentRoute == 'shoppingcart' || $CurrentRoute == 'billing' || $CurrentRoute == 'proddetails' || $CurrentRoute == 'proddetails_size' || $CurrentRoute == 'proddetails_code' ))
			<script async src="https://js.afterpay.com/afterpay-1.x.js"></script>
			{{--
			@if(config('NEW') == 1)
				<script async src="https://js.afterpay.com/afterpay-1.x.js"></script>
			@else
				<script type="text/javascript" src="https://static-us.afterpay.com/javascript/present-afterpay.js"></script>
			@endif
			--}}
		@endif

		@if($CurrentRoute == 'proddetails')
			{{--
			@if(config('global.PaypalButton') == 'Show')
				@include('layouts.paypal-button')
			@endif
			--}}
			<script  src="https://cdn-widgetsrepository.yotpo.com/v1/loader/MQY5nd09CBJk1IVKoMXrZmiUjvJj7s9krlkG1eL8" async></script>
			<link rel="preload" as="image" href="{{ config('global.SPEED_SIZE_URL')}}{{ $productDetails->mainImage }}" fetchpriority="high">
			@if($productDetails->story_image_one != '' && file_exists(config('global.PRODUCT_STORY_IMAGE_PATH').$productDetails->story_image_one))
				<link rel="preload" as="image" href="{{ config('global.SPEED_SIZE_URL')}}{{config('global.PRODUCT_STORY_IMAGE_URL').$productDetails->story_image_one}}?ver={{ filemtime(config('global.PRODUCT_STORY_IMAGE_PATH').$productDetails->story_image_one);  }}" fetchpriority="high">
			@endif
			@if($productDetails->story_image_two != '' && file_exists(config('global.PRODUCT_STORY_IMAGE_PATH').$productDetails->story_image_two))
				<link rel="preload" as="image" href="{{ config('global.SPEED_SIZE_URL')}}{{config('global.PRODUCT_STORY_IMAGE_URL').$productDetails->story_image_two}}?ver={{ filemtime(config('global.PRODUCT_STORY_IMAGE_PATH').$productDetails->story_image_two);  }}" fetchpriority="high">
			@endif
			@if($productDetails->story_image_three != '' && file_exists(config('global.PRODUCT_STORY_IMAGE_PATH').$productDetails->story_image_three))
				<link rel="preload" as="image" href="{{ config('global.SPEED_SIZE_URL')}}{{config('global.PRODUCT_STORY_IMAGE_URL').$productDetails->story_image_three}}?ver={{ filemtime(config('global.PRODUCT_STORY_IMAGE_PATH').$productDetails->story_image_three);  }}" fetchpriority="high">
			@endif
			@if($productDetails->story_image_four != '' && file_exists(config('global.PRODUCT_STORY_IMAGE_PATH').$productDetails->story_image_four))
				<link rel="preload" as="image" href="{{ config('global.SPEED_SIZE_URL')}}{{config('global.PRODUCT_STORY_IMAGE_URL').$productDetails->story_image_four}}?ver={{ filemtime(config('global.PRODUCT_STORY_IMAGE_PATH').$productDetails->story_image_four);  }}" fetchpriority="high">
			@endif

			<script>
				let encoded = "{{ base64_encode($availableStock) }}";
				let available_stock = parseInt(atob(encoded));
			</script>
		@endif

		@if($CurrentRoute == 'phoneorder_payment_receipt')
		<script  src="https://www.paypalobjects.com/api/checkout.js"></script>
		@endif

		@if(config('global.StripeButton') == 'Show' && isset($NetTotal) && $NetTotal >0 && !Auth::guard('store')->check())
			@include('layouts.stripe-button',['NetTotal' => $NetTotal])
		@endif

		@if(config('global.PaypalButton') == 'Show' && isset($NetTotal) && $NetTotal >0 && !Auth::guard('store')->check())
			@include('layouts.paypal-button')
		@endif

		@if($CurrentRoute == 'billing')
			<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCcdWTGp2vy5_cEjzW6VdBadPer4CUKM3Q&libraries=places&callback=initAutocomplete&loading=async" async></script>
			<script>
			const addressForm = {
				street_number: 'short_name',
				route: 'long_name',
				subpremise: 'short_name',
				locality: 'long_name',
				postal_town: 'long_name',
				administrative_area_level_1: 'short_name',
				administrative_area_level_2: 'long_name',
				country: 'short_name',
				postal_code: 'short_name'
			};

			let autocompletes = {};

			function initAutocomplete() {
				['bill_address1', 'ship_address1'].forEach(function(id) {
					const input = document.getElementById(id);
					if (!input) return;

					const country = (id === 'bill_address1')
						? $('#bill_country').val() || 'US'
						: $('#ship_country').val() || 'US';

					const autocomplete = new google.maps.places.Autocomplete(input, {
						types: ['address'],
						fields: ['address_components'],
						componentRestrictions: { country: country.toLowerCase() }
					});

					autocompletes[id] = autocomplete;

					autocomplete.addListener('place_changed', () => {
						const place = autocomplete.getPlace();
						fillAddressFields(place, id);
						$(input).data('place-selected', true);
					});

					$(input).on('blur', function () {
						const hasValue = $(this).val().trim() !== '';
						const placeSelected = $(this).data('place-selected');
						if (hasValue && !placeSelected) {
							const service = new google.maps.places.AutocompleteService();
							service.getPlacePredictions({
								input: $(this).val(),
								types: ['address'],
								componentRestrictions: { country: country.toLowerCase() }
							}, (predictions, status) => {
								if (status === google.maps.places.PlacesServiceStatus.OK && predictions.length > 0) {
									const placeId = predictions[0].place_id;
									const placesService = new google.maps.places.PlacesService(document.createElement('div'));
									placesService.getDetails({
										placeId: placeId,
										fields: ['address_components']
									}, (place, detailStatus) => {
										if (detailStatus === google.maps.places.PlacesServiceStatus.OK) {
											fillAddressFields(place, id);
										}
									});
								}
							});
						}
						$(this).data('place-selected', false);
					});
				});
			}

			$(document).on('change', '#bill_country, #ship_country', function () {
				const id = this.id === 'bill_country' ? 'bill_address1' : 'ship_address1';
				const country = $(this).val() || 'US';
				if (autocompletes[id]) {
					autocompletes[id].setComponentRestrictions({ country: country.toLowerCase() });
				}
			});

			function updateInputDivClass(el) {
				const $el = $(el);
				const formGroup = $el.closest('.form-group');

				if ($el.val().trim() !== '') {
					formGroup.addClass('input-fcs');
				} else {
					formGroup.removeClass('input-fcs');
				}
			}

			const addressFields = [
				'#bill_email','#bill_address1', '#bill_address2', '#bill_city', '#bill_zip', '#bill_state', '#bill_other_state',
				'#ship_address1', '#ship_address2', '#ship_city', '#ship_zip', '#ship_state', '#ship_other_state'
			];

			function applyInputClassToAll() {
				addressFields.forEach(selector => {
					updateInputDivClass($(selector));
				});
			}

			function fillAddressFields(place,srcId){
				if (!place || !place.address_components) return;
				console.log('load formatted address');
				const places_data = {};
				place.address_components.forEach(component => {
					const addressType = component.types[0];
					if (addressForm[addressType]) {
						places_data[addressType] = component[addressForm[addressType]];
					}
				});

				const address_line1 = [places_data.street_number, places_data.route].filter(Boolean).join(' ').trim();
				const address_line2 = places_data.subpremise || '';
				const address_city = places_data.locality || places_data.postal_town || places_data.administrative_area_level_2 || '';
				const address_state = places_data.administrative_area_level_1 || '';
				const address_zip = places_data.postal_code || '';
				const address_country = places_data.country || '';

				if(srcId == "bill_address1"){
					$("#bill_address1").val(address_line1);
					$("#bill_address2").val(address_line2);
					$("#bill_city").val(address_city);
					$("#bill_country").val(address_country);

					$("#bill_zip").val(address_zip);

					if(address_country != "US"){
						$("#divstate").hide();
						$("#divotherstate").show();
						$("#bill_other_state").val(address_state);
					} else {
						$("#bill_state").val(address_state);
						$("#bill_other_state").val("");
						$("#divotherstate").hide();
						$("#divstate").show();
					}
				} else {
					$("#ship_address1").val(address_line1);
					$("#ship_address2").val(address_line2);
					$("#ship_city").val(address_city);
					$("#ship_country").val(address_country);
					$("#ship_zip").val(address_zip);

					if(address_country != "US"){
						$("#divshipstate").hide();
						$("#divshipotherstate").show();
						$("#ship_other_state").val(address_state);
					} else {
						$("#ship_state").val(address_state);
						$("#ship_other_state").val("");
						$("#divshipotherstate").hide();
						$("#divshipstate").show();
					}
				}
				applyInputClassToAll();
			}
		</script>
		@endif

		@if(($CurrentRoute == 'shoppingcart' || $CurrentRoute == 'billing' ||  $CurrentRoute == 'AmazonBilling' || $CurrentRoute == 'dropshipper-fund-summary' || $CurrentRoute == 'AmazonBillingFund' || $CurrentRoute == 'phoneorder_payment_receipt' || $CurrentRoute == 'AmazonPhoneOrderCheckout' || $CurrentRoute == 'billing-shipping' || $CurrentRoute == 'billing-payment' || $CurrentRoute == 'store-payment') && !Auth::guard('store')->check())
			<script type="text/javascript">
				var address_veriffication = "{{config('global.address_verification')}}";
				window.onAmazonLoginReady = function() {
					amazon.Login.setClientId("{{config('CLIENT_ID')}}");
					amazon.Login.setUseCookie(true);
				};
				var call_url= "{{config('CALLBACK_CHECKOUT_URL')}}";
			</script>
			@if(config('NEW') == 1 && !Auth::guard('store')->check())
				<script async src="https://js.afterpay.com/afterpay-1.x.js"></script>
			@elseif(!Auth::guard('store')->check())
				<script type="text/javascript" src="https://static-us.afterpay.com/javascript/present-afterpay.js"></script>
			@endif

			@if($CurrentRoute == 'phoneorder_payment_receipt')
				<script type="text/javascript">
					var authRequest;
						OffAmazonPayments.Button("AmazonPayButton", "{{config('MERCHANT_ID')}}", {
							type: "PwA",
							size: "large",
							authorization: function () {
								loginOptions = { scope: "profile postal_code payments:widget payments:shipping_address", popup: true };
								authRequest = amazon.Login.authorize(loginOptions, call_url);
						},
						onError: function (error) {
							// something bad happened
						}
					});
				</script>
			@endif

			@if($CurrentRoute == 'AmazonBilling' || $CurrentRoute == 'AmazonBillingFund')
				<script type="text/javascript">
					$("#page-spinner").show();
					new OffAmazonPayments.Widgets.AddressBook({
						sellerId: "{{config('MERCHANT_ID')}}",
						onOrderReferenceCreate: function (orderReference) {
							orderReferenceId = orderReference.getAmazonOrderReferenceId();
						},
						onAddressSelect: function () {
							GetAmazonDetails(orderReferenceId);
						},
						design: {
							designMode: 'responsive'
						},
						onError: function (error) {
							$("#btnAmazonOrder").hide();
							// your error handling code
						}
					}).bind("addressBookWidgetDiv");

					new OffAmazonPayments.Widgets.Wallet({
						sellerId: "{{config('MERCHANT_ID')}}",
						onPaymentSelect: function () {
							$("#btnAmazonOrder").show();
							//console.log(orderReference);
						},
						design: {
							designMode: 'responsive'
						},
						onError: function (error) {
							$("#btnAmazonOrder").hide();
							// your error handling code
						}
					}).bind("walletWidgetDiv");
				</script>
			@endif

			@if($CurrentRoute == 'phoneorder_payment_receipt' || $CurrentRoute == 'AmazonPhoneOrderCheckout')
				<script type="text/javascript">
					$("#page-spinner").show();
					new OffAmazonPayments.Widgets.Wallet({
						sellerId: "{{config('MERCHANT_ID')}}",
						onOrderReferenceCreate: function (orderReference) {
							orderReferenceId = orderReference.getAmazonOrderReferenceId();
						},
						onPaymentSelect: function () {
							GetAmazonDetails(orderReferenceId);
							$("#btnAmazonOrder").show();
							//console.log(orderReference);
						},
						design: {
							designMode: 'responsive'
						},
						onError: function (error) {
							$("#btnAmazonOrder").hide();
							// your error handling code
						}
					}).bind("walletWidgetDiv");
				</script>
			@endif
		@endif

		@if($CurrentRoute != 'secure-checkout' && $CurrentRoute != 'checkout-new')
			@if($CurrentRoute=="shoppingcart_new" || $CurrentRoute=="setcart_new" || $CurrentRoute=="getcarthtml_new" || $CurrentRoute=="getcartpartial_new" || $CurrentRoute=="billing_new" || $CurrentRoute=="billing_new-shipping" || $CurrentRoute=="billing_new-payment")
				<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}common_new.js?ver={{$CommonJSNewVer}}"></script>
			@else
				<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}common.js?ver={{$CommonJSVer}}"></script>
			@endif
		@endif

		@if($CurrentRoute=="create-order")
			<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}poscheckout.js?ver={{$POSCheckoutJSNewVer}}"></script>
		@endif

		@if($CurrentRoute=="shoppingcart_new" || $CurrentRoute=="setcart_new" || $CurrentRoute=="getcarthtml_new" ||
		$CurrentRoute=="getcartpartial_new" || $CurrentRoute=="billing_new" || $CurrentRoute=="billing_new-shipping" ||
		$CurrentRoute=="billing_new-payment")
			<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}shoppingcart_new.js?ver={{$ShoppingcartJSNewVer}}"></script>
		@else
			<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}shoppingcart.js?ver={{$ShoppingcartJSVer}}"></script>
		@endif

		@if($CurrentRoute == 'proddetails')
			@if(config('global.PaypalButton') == 'Show')
				@include('layouts.paypal-button')
			@endif
		@endif

		@if(isset($JSFILES))
			@php
			/*if (!Arr::has($JSFILES, 'slick.js'))
			{
				array_unshift($JSFILES, 'slick.js');
			}*/
			if(!isMobile() && !isTablet())
			{
				if (in_array('jquery.mobile-1.0rc2.min.js', $JSFILES))
				{
					$JSFILES = array_values(array_diff($JSFILES, ['jquery.mobile-1.0rc2.min.js']));
				}
			}
		@endphp

		@foreach($JSFILES as $JSFILESVAL)
			@php
				if($JSFILESVAL == 'slick.js')
				{
					continue;
				}
				$JSValVer = filemtime(config('global.SITE_JS_CORE_PATH').$JSFILESVAL);
			@endphp
				<script defer type="text/javascript" src="{{config('global.SITE_JS_CORE')}}{{$JSFILESVAL}}?ver={{$JSValVer}}"></script>
			@endforeach
		@endif

		@if(false && ($CurrentRoute == 'product-list5' || $CurrentRoute == 'myaccount' || $CurrentRoute =='billing' || $CurrentRoute =='wholesaler-registration' || $CurrentRoute =='billing-shipping'))
			<script async src="https://www.googletagmanager.com/gtag/js?id=GTM-NXK4PT"></script>
			<script>
				window.dataLayer = window.dataLayer || [];
				function gtag(){dataLayer.push(arguments);}
				gtag('js', new Date());
				gtag('config', 'GTM-NXK4PT');
			</script>
		@endif

		@if(!isset($_GET['tp']))
			@include('layouts.thirdparty',['GTMDATA' => isset($GTMDATA)?$GTMDATA:[],'CurrentRoute' => $CurrentRoute])
		@endif

		<div class="black-grbg fade in"></div>
		{!! $SitePageSchema??PageSchema() !!}

		@if($CurrentRoute == 'home')
			<script defer src="https://cdn-widgetsrepository.yotpo.com/v1/loader/uIl5V6C_LVeCr5BT4bhDLQ" async></script>
		@endif

		<script>
			const chatElements = document.getElementsByClassName("getHelpChatNew");
			for (const chatElement of chatElements) {
				chatElement.addEventListener("click", function() {
					loadSupportChat();
				});
			}
			//document.addEventListener('DOMContentLoaded', function () {
				function getMiniCartSlider() {
					$('.mini-products-slider').slick({
						lazyLoad: 'ondemand',
						dots: true,
						infinite: false,
						speed: 300,
						arrows: true,
						centerMode: true,
						centerPadding: '0',
						slidesToShow: 1,
						slidesToScroll: 2,
						accessibility:false
						//nextArrow: '<button class="slick-next round-btn-sl" aria-label="Next recommended product"><svg class="svg svg-slick-right" aria-hidden="true" width="24" height="24" role="img"><use href="#sv-right-arrow" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-right-arrow"></use></svg></button>',
						//prevArrow: '<button class="slick-prev round-btn-sl" aria-label="Previous recommended product"><svg class="svg svg-slick-left" width="24" height="24" aria-hidden="true" role="img"><use href="#sv-left-arrow" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-left-arrow"></use></svg></button>',
					});
				}

				function updateActivity() {
					localStorage.setItem('lastActivity', Date.now());
				}
				let login_timeout = 30;
				var isStoreUser = "{{ Auth::guard('store')->check() }}";

				$(document).ready(function () {
					if ($('.sectionMiniCart').length > 0) {
						$('.mini-products-slider').on('init', function (event, slick) {
							$('.cover-spin').hide();
							//$('.sectionMiniCart').find(".cover-spin").hide();
						});
						getMiniCartSlider();
					}

					if (isStoreUser) {
						// Covers most devices (modern browsers, including iOS Safari)
						const events = [
							'pointerdown', // taps, clicks, stylus
							'pointermove',
							'touchstart', // fallback for older iOS
							'touchmove',
							'click',
							'scroll',
							'keydown'
						];

						events.forEach(event => {
							window.addEventListener(event, updateActivity, {
								passive: true
							});
						});

						// When user switches back to tab/app
						document.addEventListener('visibilitychange', () => {
							if (!document.hidden) updateActivity();
						});

						const logoutUrl = "{{ config('global.SITE_URL').'store/logout' }}";

						setInterval(() => {
							let last = localStorage.getItem('lastActivity');
							//$("#pos-dashboard-title").text("POS Dashboard ("+last+")")
							if (last && (Date.now() - last) / 1000 > login_timeout) {
								if (typeof window.manager1 !== 'undefined' && window.manager1 instanceof StarWebPrintExtManager) {
									//window.manager1.disconnect();
								}
								window.top.location.replace(logoutUrl);
							}
						}, 1000);

						updateActivity();
					}
				});
			//});
		</script>

		@if($CurrentRoute == 'order-receipt' || $CurrentRoute == 'order-receipts')
			<script>
				if(isStoreUser == ""){
					$(".showDwnMessage").show();
					$("#page-spinner").show();

					$(window).on("load", function () {
						$(".showDwnMessage").hide();
						$("#page-spinner").hide();
					});
				}

				$(document).on('click', '.sv-down-arrow', function () {
					const $arrow   = $(this);
					const $yourBag = $arrow.closest('.your-bag');
					$arrow.toggleClass('cart-toggle');
					$yourBag.find('#cart_items').slideToggle();
					$yourBag.next('.order-summry').slideToggle();
					$yourBag.nextAll('.small-address:first').slideToggle();
				});
			</script>
		@endif

	@yield('scripts')
</body>

</html>
