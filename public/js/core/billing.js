$("#page-spinner").show();

$(document).ready(function(){

	$('html, body').animate({
			scrollTop: $("body").offset().top
		}, 500);
	$("#page-spinner").hide();

	if($("#ship_country").val() != 'US')
	{
		$("#divshipstate").hide();
		$("#divshipotherstate").show();
	} else {
		$("#divshipotherstate").hide();
		$("#divshipstate").show();
	}

	if($("#bill_country").val() != 'US') {
		$("#divstate").hide();
		$("#divotherstate").show();
	} else {
		$("#divotherstate").hide();
		$("#divstate").show();
	}
	$(".amazon-btn").on('click',function(){
		$(".amazonpay-button-inner-image").trigger('click');
	});
	if($("#guest_email").val() !== "" && $("#guest_email").val() === sess_storeuseremail){
		$("#SkipEmailValidation").prop("checked", true);
	}
	if($("#SkipEmailValidation").is(":checked") && $("#firstname").val() == "" && $("#guest_email").val()!="" && isValidEmail($("#guest_email").val())){
		setAjaxGuestData($("#guest_email").val());
	}

	// var chkYotpoDrop = setInterval(function() {
	// 	if ($('#vs1__listbox li').length) {
	// 		console.log($('#vs1__listbox').html());
	// 		$("#vs1__listbox li").each(function() {
	// 			$(this).addClass("vs__dropdown_option_new");
	// 			console.log(111);
	// 			$(this).css("padding","10px!important");
	// 			//$(this).css({"padding":"10px!important","line-height":"18px!important"});
	// 			//$(this).find("a").css({"padding":"10px!important","line-height":"18px!important"});
	// 			console.log(222);
	// 			console.log($('#vs1__listbox').html());
	// 		});
	// 	   clearInterval(chkYotpoDrop);
	// 	}
	//   }, 1000);

	var checkData = setInterval(function() {
		if ($('.yotpo-button-style-active').length) {
		   $(this).on('click',function(){
				$("#page-spinner").show();
				setTimeout(checkCoupontoRedeem, 2000);
				//checkCoupontoRedeem();
				//$("#page-spinner").show();
		   });
		   clearInterval(checkData);
		}
	}, 1000);

	var checkYotpoWidgetError = setInterval(function() {
		if ($('.yotpo-point-balance-error-message').length) {
			$('.yotpo-widget-checkout-redemptions-widget').hide();
		   clearInterval(checkYotpoWidgetError);
		}
	  }, 1000);

	// if($("#OrderType").val() === 'Store' || $("#OrderType").val() === 'Both')  {
	// 	customer_auto_complete();
	// }
	if($("#issStore").length > 0 && $("#issStore").val() === "Y"){
		customer_auto_complete();
	}
})

$(document).ready(function () {
    const nonEnglishMessage = "Please enter the state name using english only.";
	const nonEnglishMessageCity = "Please enter the city name using english only.";

    $("#bill_other_state").on("input", function () {
        const enteredValue = $(this).val();
        
        const cleanedValue = enteredValue.replace(/[^A-Za-z\s'-]/g, "");

        if (enteredValue !== cleanedValue) {
            $(this).val(cleanedValue);

            $("#error_bill_other_state").text(nonEnglishMessage).show();

            $(this).addClass("input-error");
        } else {            
            if ($("#error_bill_other_state").text().trim() === nonEnglishMessage) {
                $("#error_bill_other_state").text("").hide();
                $(this).removeClass("input-error");
            }
        }
    });
	$("#bill_city").on("input", function () {
        const enteredValue = $(this).val();
        
        const cleanedValue = enteredValue.replace(/[^A-Za-z\s'-]/g, "");

        if (enteredValue !== cleanedValue) {
            $(this).val(cleanedValue);

            $("#error_bill_city").text(nonEnglishMessageCity).show();
            $(this).addClass("input-error");
        } else {            
            if ($("#error_bill_city").text().trim() === nonEnglishMessageCity) {
                $("#error_bill_city").text("").hide();
                $(this).removeClass("input-error");
            }
        }
    });
});

let useEnteredAddress = false;
let useRecommendAddress = false;
$(document).on('click','#btnbill_step1',function(){
	if(FrmValidate())
	//if(FrmValidate() && checkGoogleAddress())
	{
		SetBilling();
		$(".checkout-page").attr('data-section',0);
	} else {
		$('html, body').animate({
			scrollTop: $(".checkout-steps").offset().top - 300
		}, 2000);
	}
});

function reEnterAddress(){
	//useEnteredAddress = true;
	$("#googleAddresValidatePopup").modal('hide');
	$("#EditBilling").click();
}

function useYourAddress(){
	useEnteredAddress = true;
	$("#googleAddresValidatePopup").modal('hide');
	$("#btnbill_step1").click();
}

function useRecommendedAddress(){
	$("#page-spinner").show();
	var shippingSameAsBill = 'N';
	var fname = $("#ship_fname").val();
	var lname = $("#ship_lname").val();
	var email = $("#ship_email").val();
	var company = $("#ship_company").val();
	var phone = $("#ship_phone").val();
	if($("#chksamebill").is(":checked")){
		shippingSameAsBill = 'Y';
		fname = $("#bill_fname").val();
		lname = $("#bill_lname").val();
		email = $("#bill_email").val();
		company = $("#bill_company").val();
		phone = $("#bill_phone").val();
	}
	$.ajax({
		type:'POST',
		url:site_url+'checkout',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			method : 'userecommendedaddress',
			shippingSameAsBill : shippingSameAsBill,
			fname : fname,
			lname : lname,
			email : email,
			company : company,
			phone : phone
		},
		success:function(data) {
			//console.log(data);
			var suggestedData = JSON.parse(data);
			//console.log(suggestedData);
			if(typeof suggestedData.Billing !== 'undefined'){
				if(shippingSameAsBill == 'Y'){
					if(typeof suggestedData.Billing.address1 !== 'undefined' && suggestedData.Billing.address1 != '') {
						$("#bill_address1").val(suggestedData.Billing.address1);
					}
					if(typeof suggestedData.Billing.address2 !== 'undefined' && suggestedData.Billing.address2 != '') {
						$("#bill_address2").val(suggestedData.Billing.address2);
					}
					if(typeof suggestedData.Billing.city !== 'undefined' && suggestedData.Billing.city != '') {
						$("#bill_city").val(suggestedData.Billing.city);
					}
					if(typeof suggestedData.Billing.state !== 'undefined' && suggestedData.Billing.state != '') {
						$("#bill_state").val(suggestedData.Billing.state);
					}
					if(typeof suggestedData.Billing.country !== 'undefined' && suggestedData.Billing.country != '') {
						$("#bill_country").val(suggestedData.Billing.country);
					}
					if(typeof suggestedData.Billing.zip !== 'undefined' && suggestedData.Billing.zip != '') {
						$("#bill_zip").val(suggestedData.Billing.zip);
					}
					//console.log(suggestedData.Billing);
				}
			}
			if(typeof suggestedData.Shipping !== 'undefined'){
				if(shippingSameAsBill == 'N'){
					if(typeof suggestedData.Shipping.address1 !== 'undefined' && suggestedData.Shipping.address1 != '') {
						$("#ship_address1").val(suggestedData.Shipping.address1);
					}
					if(typeof suggestedData.Shipping.address2 !== 'undefined' && suggestedData.Shipping.address2 != '') {
						$("#ship_address2").val(suggestedData.Shipping.address2);
					}
					if(typeof suggestedData.Shipping.city !== 'undefined' && suggestedData.Shipping.city != '') {
						$("#ship_city").val(suggestedData.Shipping.city);
					}
					if(typeof suggestedData.Shipping.state !== 'undefined' && suggestedData.Shipping.state != '') {
						$("#ship_state").val(suggestedData.Shipping.state);
					}
					if(typeof suggestedData.Shipping.country !== 'undefined' && suggestedData.Shipping.country != '') {
						$("#ship_state").val(suggestedData.Shipping.country);
					}
					if(typeof suggestedData.Shipping.zip !== 'undefined' && suggestedData.Shipping.zip != '') {
						$("#ship_country").val(suggestedData.Shipping.zip);
					}
					//console.log(suggestedData.Shipping);
				}
			}
			useRecommendAddress = true;
			$("#googleAddresValidatePopup").modal('hide');
			$("#btnbill_step1").click();
		}
	});
}

function checkGoogleAddress(){
	if(address_veriffication != "1"){
		return true;
	}
	if(useEnteredAddress == true || useRecommendAddress == true){
		return true;
	}
	let ret;
	var chk_address1 = $("#ship_address1").val();
	var chk_address2 = $("#ship_address2").val();
	var chk_city = $("#ship_city").val();
	var chk_country = $("#ship_country").val();
	var chk_state = $("#ship_state").val();
	var chk_zip = $("#ship_zip").val();
	if(chk_country != 'US'){
		chk_state = $("#ship_other_state").val();
	}
	if($("#chksamebill").is(":checked")){
		chk_address1 = $("#bill_address1").val();
		chk_address2 = $("#bill_address2").val();
		chk_city = $("#bill_city").val();
		chk_country = $("#bill_country").val();
		chk_state = $("#bill_state").val();
		chk_zip = $("#bill_zip").val();
		if(chk_country != 'US'){
			chk_state = $("#bill_other_state").val();
		}
	}

	//alert(chk_address1+"==="+chk_address2+"==="+chk_city+"==="+chk_country+"==="+chk_state+"==="+chk_zip);
	$("#page-spinner").show();
	//var ret = false;
	$.ajax({
		type:'POST',
		url:site_url+'checkout',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		async: false,
		data:{
			method : 'verifygoogleaddress',
			address1 : chk_address1,
			address2 : chk_address2,
			city : chk_city,
			state : chk_state,
			zip : chk_zip,
			country : chk_country
		},
		success:function(data) {
			//console.log(data);
			var validateData = JSON.parse(data);
			//console.log(validateData);
			if(validateData.validate === false){
				//console.log(validateData);
				var suggested_address = validateData.suggestedAddress;
				var entered_address = validateData.enteredAddress;
				var popupData = validateData.validaePopup;
				var cntUnConfirmedComponentTypes = validateData.cntUnConfirmedComponentTypes;
				var missingComponentsAddress = validateData.missingComponentsAddress;
				var unconfirmedComponentAddress = validateData.unConfirmedComponentsAddress;
				//console.log(missingComponentsAddress);
				//$("#page-spinner").hide();
				//$("#googleAddresValidatePopup").html(popupData).modal('show');
				GoogleAddressSuggestionPopup(suggested_address,entered_address,cntUnConfirmedComponentTypes,missingComponentsAddress,unconfirmedComponentAddress,chk_country);
				ret = false;
				//return false;
			} else {
				$("#page-spinner").hide();
				ret = true;
				//return true;
			}
			//$("#googleAddresValidatePopup").html(data).modal('show');
			//return false;

		}
	});
	return ret;
}

function GoogleAddressSuggestionPopup(suggested_address, entered_address, cntUnConfirmedComponentTypes,missingComponentsAddress,unconfirmedComponentAddress,chk_country){
	//$("#googleAddresValidatePopup").html(data).modal('show');
	//console.log(suggested_address);
	//console.log(entered_address);
	$.ajax({
		type:'GET',
		url:site_url+'validategoogle',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			suggested_address : suggested_address,
			entered_address : entered_address,
			cntUnConfirmedComponentTypes : cntUnConfirmedComponentTypes,
			missingComponentsAddress : missingComponentsAddress,
			unconfirmedComponentAddress : unconfirmedComponentAddress,
			ship_country : chk_country
		},
		success:function(data) {
			$("#page-spinner").hide();
			$("#googleAddresValidatePopup").html(data).modal('show');
			return false;
		}
	});
}

function checkCoupontoRedeem(){
	$.ajax({
		type:'POST',
		url:site_url+'checkcoupontoredeem',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			//action : 'setcreditlimit',
			check : 'coupon'
		},
		success:function(data) {
			if(data)
			{
				if(data.coupon_number!= ''){
					$("#coupon_number").val(data.coupon_number);
					ApplyCouponCode();
				} else {
					$("#page-spinner").hide();
				}

			}
		}
	});
}
$("#down-arrow").click(function(){
	$("#cart_items").toggle();
	if($("#down-arrow").hasClass('cart-toggle'))
		$("#down-arrow").removeClass('cart-toggle');
	else
		$("#down-arrow").addClass('cart-toggle');
});

$("#OrderSummaryTogg").click(function(){
	$("#cart_items").toggle();
	if($("#down-arrow").hasClass('cart-toggle'))
		$("#down-arrow").removeClass('cart-toggle');
	else
		$("#down-arrow").addClass('cart-toggle');
});
$("#EditBilling").click(function(){
	$("#EditInfo").hide();
	$("#frmbilling").show();
	$("#DeskView").removeClass("d-md-block");
	//$("#MobileView").removeClass("intro");

});

$(document).on('click',"#giftcard-down-arraw",function(){
	$("#divgiftcard").toggle();
	if($("#giftcard-down-arraw").hasClass('cart-toggle'))
		$("#giftcard-down-arraw").removeClass('cart-toggle');
	else
		$("#giftcard-down-arraw").addClass('cart-toggle');
})
$(document).on('click','.shipmethod',function(){
	//SetShippingMethod();
});
$(document).on('change','.shipmethod',function(){
	SetShippingMethod();
});
$(document).on('click','#btnappygiftcard',function(){
	ApplyFreeGift();
})
$(document).on('click','input[name=PaymentMethod]',function(){
	if($(this).val() == 'PAYMENT_STRIPE')
	{
		$("#btnPlaceOrder").text('Proceed to Payment');
	} else {
		$("#btnPlaceOrder").text('Place Order');
	}
});
$(document).on('click','#btnPlaceOrder',function(){

	if($('input[name=PaymentMethod]:checked').length == 0)
	{
		alert('Please select payment method.');
		return false;
	}
	if(!$("#chkagree").prop('checked'))
	{
		alert('Please indicate that you have read and agree to the Terms and Conditions');
		return false;
	} else {
		if($("#shipping_signature").prop('checked'))
			$("#shipsignatureflag").val('Yes');
		else
			$("#shipsignatureflag").val('No');
		$("#page-spinner").show();
		$(".showDwnMessage").show();
		// $("#ap_psChecksum").val($("#pschecksum").val());
		$("#order_process").submit();
	}
});

$("#order_process").submit(function(){
	$("#page-spinner").show();
	$(".showDwnMessage").show();
});

$(document).on('click',"#btnAmazonOrder",function(){
	var page_from = "";
	if($("#page_from").length > 0 && $("#page_from").val() == 'fund')
		page_from = "fund";

	var onlyGCPurchased = $("#onlyGCPurchased").val();

	if(onlyGCPurchased == '0' && $('.shipmethod:checked').length == 0 && page_from == '')
	{
		alert('Please select shipping method.');
		return false;
	}
	$("#page-spinner").show();
	$(".showDwnMessage").show();
	$('#frmamazon').submit();
});
$("#frmamazon").submit(function(){
	$("#page-spinner").show();
	$(".showDwnMessage").show();
});

$(document).on('change',"#bill_country",function(){
	if($(this).val() != 'US') {
		$("#divstate").hide();
		$("#divotherstate").show();
	} else {
		$("#divotherstate").hide();
		$("#divstate").show();
	}
});
$(document).on('change',"#ship_country",function(){
	if($(this).val() != 'US') {
		$("#divshipstate").hide();
		$("#divshipotherstate").show();
	} else {
		$("#divshipotherstate").hide();
		$("#divshipstate").show();
	}
});
$(document).on('click',"#chksamebill",function(){
	$("#EditShipToStore").prop('checked', false);
	if($(this).prop('checked'))
	{
		$("#frmshipping").hide();
	}else{
		$("#frmshipping").show();
	}
});
$(document).on('click', "#EditShipToStore", function () {
	$("#chksamebill").prop('checked', false);
    isCheckedVal  = $(this).prop('checked');

    $("#btnbill_step1").html("Continue to Shipping");
	$("#ShipToStoreVal").val("No");
	if(isCheckedVal)
	{
		$("#ShipToStoreVal").val("Yes");
		$("#btnbill_step1").html("Continue to Payment");
	}

    $("#frmshipping").toggle(isCheckedVal);

    $("#chksamebill").prop('checked', !isCheckedVal);

    ShipToStoreShipping(isCheckedVal);
});

$(document).on('click','#btnbill_step2',function(){
	$("#frmpayment").submit();

    if($("#vendor-popup").val() != '')
	{
		$("#VendorText").html($("#vendor-popup").val());
		$('#VendorItemPopup').modal('show');
	}
	/*
	$(".progress li").removeClass('active');
	$("#step3").addClass('active');
	$("#shipinfo").show();
	$(".shipping-method").hide();
	$(".billtab").removeClass('active');
	$("#shipinfo").removeClass('active');
	$(".payment-method").addClass('active');
	GetPaymentMethods();
	$(".checkout-page").attr('data-section',0);
    */
})
$(document).on('click','.billback',function(){
	var CurrentSection = parseInt($(".checkout-page").attr('data-section'));
	if (CurrentSection == 0 || CurrentSection > 1)
	{
		$(".progress li").removeClass('active');
		$("#step1").addClass('active');
		$(".bill-info").show();
		$("#billshipinfo").hide();
		$(".billtab").removeClass('active');
		$(".bill-info").addClass('active');
		$("#shipping-method").hide();
		$("#payment-method").hide();
		GetShippingMethods(1);
		GetPaymentMethods(1);
		$(".checkout-page").attr('data-section',2);
	}
});
$(document).on('click','.shipback',function(){
	var CurrentSection = parseInt($(".checkout-page").attr('data-section'));
	if (CurrentSection == 0 || CurrentSection >= 2)
	{
		$(".progress li").removeClass('active');
		$("#step2").addClass('active');
		$(".shipping-method").show();
		$("#shipinfo").hide();
		$(".billtab").removeClass('active');
		$(".shipping-method").addClass('active');
		GetShippingMethods();
		GetPaymentMethods(1);
	}
});

$(document).on('click',".guestback",function(){
	$("#guest2").hide();
	$("#guest1").show();
	$(".billtab").removeClass('active');
	$("#billshipinfo").show();
	$(".bill-info").hide();
	$("#shipping-method").hide();
	$("#payment-method").hide()
	GetShippingMethods(1);
	GetPaymentMethods(1);
	$(".checkout-page").attr('data-section',1);
});
$(document).on('click','#shipping_signature',function(){
	//route_shipping_insurance();
	var insuramt = $("#divshipcerty").attr('data-amt');
		insuramt = parseFloat(insuramt);
	if($("#shipping_signature").prop("checked")== true)
	{

		var insuramt = $("#divshipcerty").attr('data-amt');
		insuramt = parseFloat(insuramt);
		if(insuramt>199)
		{
			$("#shipcerty_info").css("visibility", "visible");
			//$("#shipcerty_info").show();
		}
	}
	else
	{

		$("#shipcerty_info").css("visibility", "hidden");
		if(insuramt<200 && $("#tempValInsu").val()!="Yes")
		{
		//$("#shipcerty_info").show();
		$("#ChekoutConfirm").html("For this order, we automatically add signature requirements free of charge. Opting out of signature requests will void any additional reassurances should your package show as delivered according to tracking.");
		$('#CheckoutConfirmPopup').modal('show');
		}
	}
	SetShippingInsuranceCharge();
});
$(document).on('change',"#shipinsurance", function(){
	if($("#shipinsurance").prop("checked")== true)
	{
		//$("#insure_info").show();
		$("#insure_info").css("visibility", "visible");
	}
	else
	{
		if($("#tempValInsuSignature").val()!="Yes")
		{
		$("#ChekoutConfirm").html("Please note by turning Shipping Insurance OFF, you are agreeing to taking full responsibility for any loss, damages or theft. Maxaroma cannot take claim if the insurance is off, however you can submit a claim to the carrier directly.");
		$('#CheckoutConfirmPopup').modal('show');
		}
		//$("#insure_info").hide();
		$("#insure_info").css("visibility", "hidden");
	}
	SetShippingInsuranceCharge();
})

$(document).on('click','#EditAfter',function(){
	$("#CheckoutAfterPayPop").html("To have the shipping address changed, please go to Afterpay to edit your address and refresh the cart.");
	$('#CheckoutAfterPayPopup').modal('show');
});

$(document).on('click',"#EditAfterSameAs",function(){
	$( "#EditAfterSameAs" ).prop( "checked", true );
	if($(this).prop('checked'))
	{
		$("#CheckoutAfterPayPop").html("To change the address you need to change it on AfterPay and cart needs to be refresh.");
		$('#CheckoutAfterPayPopup').modal('show');
	}
});

$(document).on('click','#btnguest',function(){
	if (window.storeAuth.check) {

		var isValid = $('#formguest').valid();
		if (!isValid) {
			return false;
		}

		AutoLogin();
		$(".bill-info").show();
		$("#poscustsection").hide();
		$("#guestcheckout").hide();
        $("#newacc").hide();
	}
	else
	{
		CheckMember();
	}
	$(".checkout-page").attr('data-section',0);
});

function AutoLogin()
{
    $("#page-spinner").show();

    var frmdata = $("#formguest").serialize();

    $.ajax({
        type: 'POST',
        url: site_url + 'skipaddress',
        dataType: 'json',
        data: frmdata,

        success: function (res) {

            console.log(res);

            if (res.success === '1') {

                $.each(res.billing, function(key, value){
                    let el = $("#" + key);
                    if(el.length){
                        el.val(value);
                    }
                });

            } else {

                alert(res.message || 'Something went wrong');

            }
        },

        error: function () {
            alert("Server error");
        }

    }).always(function(){
        $("#page-spinner").hide();
    });
}

$(document).on('click','#chkcreditlimit',function(){
	ApplyCreditLimit();

});

$(document).ready(function () {

    $('#formguest').validate({
        rules: {
            guest_email: {
                required: {
                    depends: function () {

                        if ($('#SkipEmailValidation').length > 0) {
                            return !$('#SkipEmailValidation').is(':checked');
                        }

                        return true;
                    }
                },
                email: {
                    depends: function () {

                        if ($('#SkipEmailValidation').length > 0) {
                            return !$('#SkipEmailValidation').is(':checked');
                        }

                        return true;
                    }
                }
            }
        },
        messages: {
            guest_email: {
                required: GetMessage('Validate', 'ValidEmail'),
                email: GetMessage('Validate', 'ValidEmail')
            }
        },
        onsubmit: false,
        invalidHandler: function (form, validator) {

            var errors = validator.numberOfInvalids();

            if (errors) {
                for (var i = 0; i < errors; i++) {
                    var message = validator.errorList[i].message;
                    var id = $(validator.errorList[i].element).attr('name');

                    $("#formguest #error_" + id)
                        .html(message)
                        .show();
                }
            } else {
                $("#formguest .frmerror").html('');
            }
        },
        errorPlacement: function (error, element) {
            // keep your override
        }
    });

});

$(document).on('click', "#btnasguest", function () {

    if ($('#formguest').valid()) {

        var email = $("#guest_email").val();

        $("#gemail").html(email);
        $("#bill_email")
            .val(email)
            .closest('.form-group')
            .addClass('input-fcs');

        $("#guestcheckout").hide();
        $("#newacc").hide();

        $(".bill-info").show();
        $(".checkout-page").attr('data-section', 2);
    }

});

$(document).on('change', '#SkipEmailValidation', function () {
	 $('#guest_email').val('');
	 $('#firstname').val('');
	 $('#lastname').val('');
	 $('#phonenumber').val('');
	 $('#chlabelval').hide();
    if ($(this).is(':checked')) {
        //$('#guest_email').val('');
        $('#error_guest_email').html('').hide();
        $('#guest_email').removeClass('error');
		$("#guest_email")
            .val(sess_storeuseremail)
            .closest('.form-group')
            .addClass('input-fcs');
		if(isValidEmail(sess_storeuseremail)){
			setAjaxGuestData(sess_storeuseremail);		
		}
		
		//$('#guest_email').val(sess_storeuseremail);
		$('#chlabelval').show();
		$('#chlabelval').html('This order will be linked to the store email address.');

    }

});

$(document).on('click','.chkhead',function(){
	$(".chkhead").removeClass('chkactive');
	if($(this).attr('id') == 'returning')
	{
		$(this).addClass('chkactive');
		$("#divguest").hide();
		$("#divreturning").show();
	} else {
		$(this).addClass('chkactive');
		$("#divreturning").hide();
		$("#divguest").show();
	}
})

function ApplyCreditLimit()
{
	var token = $('meta[name="csrf-token"]').attr('content');
	var check = 0;
	var onlyGCPurchased = $("#onlyGCPurchased").val();

	var ShipInsCharge = 'no';
	if($("#shipinsurance").prop('checked'))
		ShipInsCharge = 'yes';

	$("#page-spinner").show();
	if($("#chkcreditlimit").prop('checked'))
		check = 1;
	$.ajax({
		type:'POST',
		url:site_url+'setcreditlimit',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			//action : 'setcreditlimit',
			check : check,
			onlyGCPurchased : onlyGCPurchased,
			ShipInsCharge : ShipInsCharge,
		},
		success:function(data) {
			if(data)
			{
				$("#cartsubtotal").html(data.CheckoutBoxHTML);
				UpdateAfterPayWidget(data.CheckoutBoxHTML);

				$("#credit-limit").html(data.CreditLimitBoxHTML);
				if(data.UnformatedRemainCreditLimit > 0 )
					$("#credamt").html('Use your account balance, Your account balance is : ' + data.RemainCreditLimit);
				else
					$("#credamt").html('Your account balance has been applied.');
				if(data.NetTotal == 0)
				{
					$("#PaymentDetailsStoreView").attr('style','display:inline-block !important');
					$(".credit-methods").attr('style','display:none !important');
					$("input[name=PaymentMethod]").prop('checked',false);
					$("#paytypeIDCL1").prop('checked',true);
				} else {
					$("#PaymentDetailsStoreView").attr('style','display:none !important');
					$(".credit-methods").attr('style','display:inline-block !important');
					$("#paytypeIDCL1").prop('checked',false);
				}

				$("#page-spinner").hide();
			}
		}
	});
}
function CheckMember()
{
	$('#formguest').validate({
		rules: {
			guest_email: { required: true,email: true  },
		},
		messages: {
			guest_email: { required: GetMessage('Validate','ValidEmail'), email: GetMessage('Validate','ValidEmail') }
		},
		onsubmit: false,
		invalidHandler: function(form, validator)
		{
			var errors = validator.numberOfInvalids();
			if (errors)
			{
				for(var i=0;i<errors;i++)
				{
					var message = validator.errorList[i].message;
					var id = $(validator.errorList[i].element).attr('name');
					$("#formguest #error_"+id).html(message);
					$("#formguest #error_"+id).show();
				}
			} else {
				   $("#formguest .frmerror").html('');
			}
		},
		errorPlacement: function(error, element)
		{
			// Override error placement to not show error messages beside elements //
		}
	});

	if($('#formguest').valid())
	{
		var token = $('meta[name="csrf-token"]').attr('content');
		var email = $("#guest_email").val();
		$("#chknews").prop("checked", false);
		if($("#chknews1").prop('checked'))
		{
			$("#chknews").prop("checked", true);
		}
        $("#page-spinner").show();
		$.ajax({
			type:'POST',
			url:site_url+'checkmember',
			headers: {
				'X-CSRF-TOKEN': token
			},
			datatype: 'JSON',
			data:{
				//action : 'chkmember',
				action : 'chkmember_billing',
				email : email
			},
			success:function(data) {
				if(data != ''){
					$chkArr = data.split("~");
					if($chkArr[0]=="0"){
						$("#error_guest_email").html('You have an account, <a href="javascript:void(0);" data-action="sign_in" class="ulink signinsignup" >Sign in Here</a>.');
						$("#error_guest_email").show();
						$("#btnguest").hide();
						$("#btnasguest").show();
						$("#duplicate_ip").html($chkArr[1]).show();
					} else if($chkArr[0]=="1"){
						$("#error_guest_email").html('You have an account, <a href="javascript:void(0);" data-action="sign_in" class="ulink signinsignup" >Sign in Here</a>.');
						$("#error_guest_email").show();
						$("#btnguest").hide();
						$("#btnasguest").show();
					} else if($chkArr[0] == "2"){
						$("#duplicate_ip").html($chkArr[1]).show();
						//$(".cart-hd-sub").html('Have an account? <a href="javascript:void(0);" data-action="sign_in" class="ulink signinsignup">Sign In</a><br>'+$chkArr[1]);
					} else if($chkArr[0] == "3"){
						$("#duplicate_ip").hide();
						$("#btnasguest").click();
					} else if($chkArr[0] == "4"){
						$("#error_guest_email").html($chkArr[1]).show();
					}
				}

				// if(data)
				// {
				// 	//$("#error_guest_email").html('You already have an account with us, please <a href="'+site_url+'login.html">login</a> or continue as guest.');
				// 	$("#error_guest_email").html('You have an account, <a href="javascript:void(0);" data-action="sign_in" class="ulink signinsignup" >Sign in Here</a>.');
				// 	//$("#error_guest_email").html('You have an account, <a href="'+site_url+'login/checkout.html" class="ulink" >Sign in Here</a>.');
				// 	$("#error_guest_email").show();
				// 	$("#btnguest").hide();
				// 	$("#btnasguest").show();
				// } else {
				// 	$("#btnasguest").click();
				// }
                $("#page-spinner").hide();
			}
		});
	}
}
function route_shipping_insurance(){
	//Live-2b3983e3-6b7d-45e4-bc21-a4ee1db24609
	routeapp.get_quote('2b3983e3-6b7d-45e4-bc21-a4ee1db24609', $("#max_hidsubtotal").val(), 'USD', function(data){
		update_route_shipping_action(data);
	});
	routeapp.on_insured_change(function (data){
		update_route_shipping_action(data);
	})
}
function update_route_shipping_action(data){
	if(data.insurance_selected == true){
		$(".route-widget .rw-contents .rw-right .rw-checkbox-span.rw-checked").css("background-color","#000 !important");
		$("#shipping_insurance").prop("checked","checked");
		$("#shipping_insurance_charge").val(data.insurance_price);
		$("#shipping_signature").removeAttr("checked");
		$("#shipping_signature_div").hide();
		SetShippingInsuranceCharge();
	} else {
		$(".route-widget .rw-contents .rw-right .rw-checkbox-span.rw-unchecked").css("background-color","#f3f3f3 !important");
		$("#shipping_insurance").removeAttr("checked");
		$("#shipping_insurance_charge").val("");
		$("#shipping_signature_div").show();
		SetShippingInsuranceCharge();
	}
}

function GetShippingMethods(OnlyHead=0,PageFrom='')
{
	//alert("fff"); return false;
	var IsVenderItem = $("#IsVenderItem").val();
	var IsCosmo = $("#IsCosmo").val();
	var IsNandansons = $("#IsNandansons").val();
	var IsPerfumePW = $("#IsPerfumePW").val();
	var IsPCA = $("#IsPCA").val();
	var IsND = $("#IsND").val();
	var IsMaxaromaTwoDelivery = $("#IsMaxaromaTwoDelivery").val();
	var onlyGCPurchased = $("#onlyGCPurchased").val();
	var ISMaxTwoItem = $("#ISMaxTwoItem").val();
	var ISMax2dayVal = $("#ISMax2dayVal").val();
	var token = $('meta[name="csrf-token"]').attr('content');
	$("#page-spinner").show();
	$.ajax({
		type:'POST',
		url:site_url+'shipping',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			action : 'shippinginfo',
			OnlyHead : OnlyHead,
			PageFrom : PageFrom,
			IsVenderItem: IsVenderItem,
			IsCosmo: IsCosmo,
			IsNandansons: IsNandansons,
			IsPerfumePW: IsPerfumePW,
			IsPCA: IsPCA,
			IsND: IsND,
			IsMaxaromaTwoDelivery: IsMaxaromaTwoDelivery,
			ISMaxTwoItem : ISMaxTwoItem,
			onlyGCPurchased: onlyGCPurchased,
			ISMax2dayVal: ISMax2dayVal,
		},
		success:function(data) {

			if(PageFrom == 'amazon_billing'){
				$(".shipping-method").html(data.ShipMethodsHtml);
				SetShippingMethod();
				$("#page-spinner").hide();
			} else {

				$(".bill-info").html(data.ShipMethodsHtml);

				$("#cartsubtotal").html(data.CheckoutBoxHTML);
				UpdateAfterPayWidget(data.CheckoutBoxHTML);
				//$("#shipinfo").hide();
				//$(".shipping-method").show();
				//SetShippingMethod();
				$("#page-spinner").hide();
				$('html, body').animate({
					//scrollTop: $(".shipping-method").offset().top - 100
					scrollTop: $(".bill-info").offset().top - 100
				}, 2000);
				/*setTimeout(function(){
					route_shipping_insurance();
				},1000);*/
			}
		}
	});
}

function GetAmazonDetails(AmazonOrderID)
{
	var onlyGCPurchased = $("#onlyGCPurchased").val();
	$.ajax({
		type:'POST',
		url:site_url+'amazon/order-details',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			amazon_order_id: AmazonOrderID
		},
		success:function(data) {
			if(data){
				if(onlyGCPurchased == '0')
				{
					GetShippingMethods(0,'amazon_billing');
				}
			}
		}
	});
}

if(routenm=="billing-shipping")
{
	SetShippingMethod();
}

function SetShippingMethod()
{
	var token = $('meta[name="csrf-token"]').attr('content');
	var ShipMethodID = $('.shipmethod:checked').val();
	var IsVenderItem = $("#IsVenderItem").val();
	var IsCosmo = $("#IsCosmo").val();
	var IsNandansons = $("#IsNandansons").val();
	var IsPerfumePW = $("#IsPerfumePW").val();
	var IsPCA = $("#IsPCA").val();
	var IsND = $("#IsND").val();
	var onlyGCPurchased = $("#onlyGCPurchased").val();
	$("#page-spinner").show();
	$(".clsship").removeClass('active');
	var SelShip = $('.shipmethod:checked').attr('data-key');
	var EstDate = $('.shipmethod:checked').attr('data-estdate');
	$("#method-"+SelShip).addClass('active');
	$("#divinsurance").show();
	if(ShipMethodID==46)
	{
		$('#ShippingPickupMethod').modal('show');
		$("#PickupTextValue").html("You have Selected The Order to be Picked up From Address: 31-17 38th Ave, Long Island City NY 11101.");

		$("#insurance").removeClass('active');
		$("#shipinsurance").prop("checked",false);

		$("#divinsurance").hide();
	}
	$("#divontime").remove();
	$.ajax({
		type:'POST',
		url:site_url+'setshipmethod',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			ShipMethodID: ShipMethodID,
			IsVenderItem: IsVenderItem,
			IsCosmo: IsCosmo,
			IsNandansons: IsNandansons,
			IsPerfumePW: IsPerfumePW,
			IsPCA: IsPCA,
			IsND: IsND,
			onlyGCPurchased: onlyGCPurchased,
			EstDate:EstDate,
		},
		success:function(data) {
			$("#cartsubtotal").html(data.SubTotalBox);
			UpdateAfterPayWidget(data.SubTotalBox);

			$(".shipinsprice").html(data.ShipInsCharge);
			$("#vendor-popup").val(data.VendorPopup);

			$('.shipmethod:checked').parent().append('<div class="clsgreen" id="divontime">98% on-time delivery rate</div>');

			$("#page-spinner").hide();
		},
	});
}

function SetShippingInsuranceCharge()
{
	/*
	var token = $('meta[name="csrf-token"]').attr('content');

	if($("#shipping_signature").is(":checked")==true){
		$("#slider_round").text("On").removeClass("text_off").addClass("text_on");
	} else {
		$("#slider_round").text("Off").removeClass("text_on").addClass("text_off");
	}

	var shipping_insurance = "N";
	var shipping_insurance_charge = "";
	if($("#shipping_insurance").is(":checked")==true){
		shipping_insurance = "Y";
		shipping_insurance_charge = $("#shipping_insurance_charge").val();
	}

	$(".rdate").removeClass("active");
	var  shipping_mode_id = $('input[name=shippingModeId]:checked').val();

	$("#rdateSel"+shipping_mode_id).addClass("active");

	var est_date = $("#rdateSel"+shipping_mode_id).next("input[name='shippingModeId']").attr("data-estidate");
	$("#EstimatedDeliveryDate").val(est_date);

	var   shipping_signature = "";
	if($("#shipping_signature").prop("checked")== true)
	{
		shipping_signature = $('input[name=shipping_signature]:checked').val();
	}*/
	$("#page-spinner").show();
	var insuramt = $("#divshipcerty").attr('data-amt');
	insuramt = parseFloat(insuramt);

	/*
	if($("#shipping_signature").is(":checked")==true){
		$("#slider_round").text("On").removeClass("text_off").addClass("text_on");
	} else {
		$("#slider_round").text("Off").removeClass("text_on").addClass("text_off");
	}
	*/
	var subaction = 'add';
	if($("#shipinsurance").prop('checked')){
		$("#shipinsurance").parent().addClass('active');
		subaction = 'add';
		if(insuramt < 200){
			//$("#divshipcerty").hide();
			//$("#shipping_signature").prop("checked",false);
		}
		//$("#shipping_signature").removeAttr("checked");
		//$("#shipping_signature_div").hide();
	}else{
		$("#shipinsurance").parent().removeClass('active');
		subaction = 'remove';
		if(insuramt < 200)
		{
			//$("#divshipcerty").hide();
		}
		//$("#shipping_insurance_charge").val("");
		//$("#shipping_signature_div").show();
	}
	var   shipping_signature = "";
	if($("#shipping_signature").prop("checked")== true)
	{

		/*if(insuramt > 200)
			$("#shipcerty_info").show();
		else
			$("#shipcerty_info").hide();
		*/
		if(insuramt > 199)
		{
			//$("#shipcerty_info").show();
			$("#shipcerty_info").css("visibility", "visible");

		}
		//shipping_signature = $('input[name=shipping_signature]:checked').val();
		shipping_signature = $('#shipping_signature').attr('data-value');
		shipping_signature = parseFloat(shipping_signature.trim());
		$("#shipping_signature").parent().addClass('active');
	} else{
		$("#shipcerty_info").show();
		$("#shipping_signature").parent().removeClass('active');
	}

	$.ajax({
		type:'POST',
		url:site_url+'setshippinginsurance',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			action : 'setshippinginsurance',
			subaction : subaction,
			shipping_signature: shipping_signature,
			/*ShippingInsuranceMethod : shipping_insurance,
			ShippingInsuranceCharge : shipping_insurance_charge,*/
		},
		success:function(data) {
			$("#cartsubtotal").html(data);
			UpdateAfterPayWidget(data);

			$("#page-spinner").hide();
		}
	});
}
function GetPaymentMethods(OnlyHead=0)
{
	var token = $('meta[name="csrf-token"]').attr('content');
	var SelPayMethod = $("#SelPayMethod").val();

	var ShipInsCharge = 'no';
	if($("#shipinsurance").prop('checked'))
		ShipInsCharge = 'yes';

	$("#page-spinner").show();
	$.ajax({
		type:'POST',
		url:site_url+'paymentinfo',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			action : 'paymentinfo',
			OnlyHead : OnlyHead,
			SelPayMethod : SelPayMethod,
			ShipInsCharge : ShipInsCharge,
		},
		success:function(data) {
			if(data.ShipInfo)
				$("#shipinfo").html(data.ShipInfo);
			$(".payment-method").html(data.PayMethods);
			AfterpaySetup();
			$("#page-spinner").hide();
			$('html, body').animate({
				scrollTop: $(".payment-method").offset().top - 100
			}, 2000);
			if($('input[name=PaymentMethod]:checked').val() == 'PAYMENT_STRIPE')
			{
				$("#btnPlaceOrder").text('Proceed to Payment');
			} else {
				$("#btnPlaceOrder").text('Place Order');
			}
		}
	});
}

function FrmValidate()
{
	var onlyGCPurchased = $("#onlyGCPurchased").val();
	if(onlyGCPurchased == 0 && !$('#chksamebill').prop('checked'))
	{
		var Bill = ValidBilling();
		var Ship = ValidShipping();
		if(Bill && Ship)
			return true;
		else
			return false;
	} else {
		return ValidBilling();
	}
}
function ValidBilling()
{

	$("#frmbilling .frmerror").hide();

	 $.validator.addMethod("BILLFIRST",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid billing first name.");
     $.validator.addMethod("BILLLAST",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid billing last name.");
     $.validator.addMethod("BILLADDRESS1",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid billing address1.");
     $.validator.addMethod("BILLADDRESS2",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid billing address2.");
     $.validator.addMethod("BILLCITY",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid billing city.");

	var Rules = {
			bill_fname: { required: true  },
			bill_fname: "required BILLFIRST",
			bill_lname: { required: true  },
			bill_lname: "required BILLLAST",
			bill_address1: { required: true  },
			bill_address1: "required BILLADDRESS1",
			bill_address2: "BILLADDRESS2",
			bill_city: "required BILLCITY" ,
			bill_zip: { required: true  },
			bill_phone: { required: true  },
			bill_email: { required: true,email: true },
			//bill_cemail: { required: true, equalTo: '#bill_email'},
			bill_state: { required: function() { return $("#bill_country").val() == "US"}},
			bill_other_state: { required:function() { return $("#bill_country").val() != "US" }}
		};
	var Messages = {
			bill_fname: 	{ required: GetMessage('Validate','FirstName') },
			bill_lname: 	{ required: GetMessage('Validate','LastName') },
			bill_address1: 	{ required: GetMessage('Validate','Address') },
			bill_address2: 	{ required: GetMessage('Validate','Address') },
			bill_city: 		{ required: GetMessage('Validate','City') },
			bill_zip: 		{ required: GetMessage('Validate','ZipCode') },
			bill_phone: 	{ required: GetMessage('Validate','Phone') },
			bill_email: 	{ required: GetMessage('Validate','ValidEmail'), email: GetMessage('Validate','ValidEmail') },
			//bill_cemail: 	{ required: GetMessage('Validate','ValidConfirmPassword'), equalTo: GetMessage('Validate','ValidConfirmPassword') },
			bill_state: 	{ required: GetMessage('Validate','State') },
			bill_other_state: 	{ required: GetMessage('Validate','OtherState') },
			guest_password	: { required: GetMessage('Register','Password') },
			guest_confirm_password : { required: GetMessage('Validate','ValidConfirmPassword'), equalTo: GetMessage('Validate','ValidConfirmPassword')},
		};

	$('#frmbilling').validate({
		rules: Rules,
		messages: Messages,
		onsubmit: false,
		ignore : '',
		invalidHandler: function(form, validator)
		{
			var errors = validator.numberOfInvalids();
			if (errors)
			{

				for(var i=0;i<errors;i++)
				{
					var message = validator.errorList[i].message;
					var id = $(validator.errorList[i].element).attr('name');
					$("#frmbilling #error_"+id).html(message);
					$("#frmbilling #error_"+id).show();
				}
			} else {
				   $("#frmbilling .frmerror").html('');
		   }
		},
		errorPlacement: function(error, element)
		{
			$("#EditInfo").hide();
			$("#frmbilling").show();
			$("#DeskView").removeClass("d-md-block");
			// Override error placement to not show error messages beside elements //
		}
	});
	$('#frmbilling').validate();
	if($("#chkflag").length > 0 && $("#chkflag").val() == 'guest')
	{
		var guest_password = $("#guest_password").val();
		guest_password = guest_password.trim();
		var guest_confirm_password = $("#guest_confirm_password").val();
		guest_confirm_password = guest_confirm_password.trim();
		if(guest_password != '' || guest_confirm_password != '')
		{
			$("#guest_password").rules('add',{ required: true  });
			//$("#guest_password").messages('add',{ required: GetMessage('Register','Password') });

			$("#guest_confirm_password").rules('add',{ required: true, equalTo: '#guest_password' });
			//$("#guest_confirm_password").messages('add',{ required: GetMessage('Validate','ValidConfirmPassword'), equalTo: GetMessage('Validate','ValidConfirmPassword')});
			/*Rules.guest_password = { required: true  };
			Rules.guest_confirm_password = { required: true, equalTo: '#guest_password' };
			Messages.guest_password = { required: GetMessage('Register','Password') };
			Messages.guest_confirm_password = { required: GetMessage('Validate','ValidConfirmPassword'), equalTo: GetMessage('Validate','ValidConfirmPassword')};
			*/
		} else {
			$("#guest_password").rules('remove', 'required');
			$("#guest_confirm_password").rules('remove', 'required equalTo' );

			/*delete Rules['guest_password'];
			delete Rules['guest_confirm_password'];
			delete Messages['guest_password'];
			delete Messages['guest_confirm_password'];
			*/
		}
	}
	return $('#frmbilling').valid();
}

function ValidShipping()
{
	 $.validator.addMethod("SHIPFIRST",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid shipping first name.");
     $.validator.addMethod("SHIPLAST",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid shipping last name.");
     $.validator.addMethod("SHIPADDRESS1",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid shipping address1.");
     $.validator.addMethod("SHIPADDRESS2",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid shipping address2.");
     $.validator.addMethod("SHIPCITY",function(value,element){
                return this.optional(element) || /[^? ]/i.test(value);;
            },"Please enter valid shipping city.");

	$('#frmshipping').validate({
		rules: {
			ship_fname: { required: true  },
			ship_fname: "required SHIPFIRST",
			ship_lname: { required: true  },
			ship_lname: "required SHIPLAST",
			ship_address1: { required: true  },
			ship_address1: "required SHIPADDRESS1",
			ship_address2: "SHIPADDRESS2",
			ship_city: { required: true  },
			ship_city: "required SHIPCITY",
			ship_zip: { required: true  },
			ship_phone: { required: true  },
			ship_email: { required: true,email: true },
			ship_state: { required: function() { return $("#ship_country").val() == "US"}},
			ship_other_state: { required:function() { return $("#ship_country").val() != "US" }}
		},
		messages:{
			ship_fname: 	{ required: GetMessage('Validate','FirstName') },
			ship_lname: 	{ required: GetMessage('Validate','LastName') },
			ship_address1: 	{ required: GetMessage('Validate','Address') },
			ship_city: 		{ required: GetMessage('Validate','City') },
			ship_zip: 		{ required: GetMessage('Validate','ZipCode') },
			ship_phone: 	{ required: GetMessage('Validate','Phone') },
			ship_email: 	{ required: GetMessage('Validate','ValidEmail'), email: GetMessage('Validate','ValidEmail') },
			ship_state: 	{ required: GetMessage('Validate','State') },
			ship_other_state: 	{ required: GetMessage('Validate','OtherState') }
		},
		onsubmit: false,
		invalidHandler: function(form, validator)
		{
			var errors = validator.numberOfInvalids();
			if (errors)
			{
				for(var i=0;i<errors;i++)
				{
					var message = validator.errorList[i].message;
					var id = $(validator.errorList[i].element).attr('name');
					$("#frmshipping #error_"+id).html(message);
					$("#frmshipping #error_"+id).show();
				}
			} else {
				   $("#frmshipping .frmerror").html('');
		   }
		},
		errorPlacement: function(error, element)
		{
			// Override error placement to not show error messages beside elements //
		}
	});
	return $('#frmshipping').valid();
}
function SetBilling()
{
	$("#page-spinner").show();
	var token = $('meta[name="csrf-token"]').attr('content');
	var frmdata = Array();
	var onlyGCPurchased = $("#onlyGCPurchased").val();
	var onlyGCPurchasedVal = $("#onlyGCPurchasedVal").val();
	var orderType = $("#OrderType").val();
	var EditShipToStore = $("#EditShipToStore").prop('checked');

	if($('#chksamebill').prop('checked')){
		frmdata = $("#frmbilling").serializeArray();
		frmdata[frmdata.length] = {name: 'sameasbill', value: 'Yes'};
	}else{
		frmdata = $("#frmbilling, #frmshipping").serializeArray();
		frmdata[frmdata.length] = {name: 'sameasbill', value: 'No'};
	}
	for(var i=0; i<frmdata.length; i++)
	{
		if(frmdata[i].name == 'bill_address1' || frmdata[i].name == 'bill_address2' || frmdata[i].name == 'ship_address1' || frmdata[i].name == 'ship_address2')
		{
			var Address = frmdata[i].value.toLowerCase();
			if(Address.search('union') != -1)
			{
				frmdata[i].value = frmdata[i].value.split(" ").join("__");
			}
		}
	}
	if($("#chknews").prop('checked')){
		frmdata[frmdata.length] = {name: 'newsletter', value: 'Yes'};
	}
	frmdata[frmdata.length] = {name: 'action', value: 'setbilling'};

	$.ajax({
		type:'POST',
		url:site_url+'setbilling',
		headers: {
			'X-CSRF-TOKEN': token
		},
		contentType: 'application/x-www-form-urlencoded',
		dataType: 'text',
		data:frmdata,
		success:function(data) {
            if(EditShipToStore && orderType=="Both")
            {
				$("#frmbilling").attr('action', 'store-payment');
				$("#frmbilling").attr('method', 'post');
				$("#takeaction").val("TakeAction");
				$("#OnlyHeadval").val(0);
				$("#frmbilling").submit();

			}
            else if(onlyGCPurchasedVal==1 || orderType=="Store")
			{
				$("#takeaction").val("TakeAction");
				$("#OnlyHeadval").val(0);
				$("#frmbilling").submit();
			}
			else
			{
				$("#frmshipping").submit();
			}
			/*
            $(".progress li").removeClass('active');
			$("#billshipinfo").show();
			$(".bill-info").hide();
			$(".billtab").removeClass('active');
			$("#billshipinfo").html(data);

            if(onlyGCPurchased == 0)
			{
				$("#step2").addClass('active');
				$(".shipping-method").addClass('active');
				GetShippingMethods();
			} else {
				$("#step3").addClass('active');
				$(".payment-method").addClass('active');
				GetPaymentMethods();
			}
            */
		}

	});
}

function ApplyFreeGift()
{
	var token = $('meta[name="csrf-token"]').attr('content');
	$('#frmgiftcard').validate({
		rules: {
			gift_from: {required: true},
			gift_to: {required: true},
			gift_message_customer: {required: true},
			freegiftvalue: { required: true },
		},
		messages: {
			gift_from: "Please enter from value.",
			gift_to: "Please enter to value.",
			gift_message_customer: "Please enter gift value.",
			freegiftvalue: "Please select free gift.",
		},
		onsubmit: false,
		invalidHandler: function(form, validator)
		{
			var errors = validator.numberOfInvalids();
			if (errors)
			{
				for(var i=0;i<errors;i++)
				{
					var message = validator.errorList[i].message;
					var id = $(validator.errorList[i].element).attr('name');
					$("#frmgiftcard #error_"+id).html(message);
					$("#frmgiftcard #error_"+id).show();
				}
			} else {
				   $("#frmgiftcard .frmerror").html('');
		   }
		},
		errorPlacement: function(error, element)
		{
			// Override error placement to not show error messages beside elements //
		}

	 });
	if($('#frmgiftcard').valid()) {
		var GiftFrom = $("#gift_from").val();
		var GiftTo = $("#gift_to").val();
		var GiftMessage = $("#gift_message_customer").val();
		var GiftValue = $("#freegiftvalue").val();

		$.ajax({
			type: 'POST',
			url: site_url + 'cart',
			headers: {
				'X-CSRF-TOKEN': token
			},
			datatype: 'JSON',
			data: {
				action: 'apply_free_gift',
				GiftFrom: GiftFrom,
				GiftTo: GiftTo,
				GiftMessage: GiftMessage,
				GiftValue: GiftValue,
			},
			success: function (data) {
				if(data.success == '1')
					alert("Free Gift is applied successfully");
				else
					alert("Free Gift is not applied");
			}
		});
	}
}

function UpdateAfterPayWidget(response){
	 var order_amount= $($.parseHTML(response)).find("#net_total_amt").data("amt");
	 $("#net_total_amt1").html("$"+order_amount);

	 // var is_ap = $("#is_ap").val();

	 if(typeof afterpayWidget != "undefined"){
		 afterpayWidget.update({
			amount: { amount: String(order_amount), currency: "USD" },
		 })
	 }
}
$(document).on('change', '#BillingSkip', function () {

    const isChecked = $(this).is(':checked');

    // KEEPING YOUR ORIGINAL LOGIC
    $('#fname, #lname, #cphone').toggle(isChecked);

    // ✅ Update hidden field properly
    $('#BillingSkipVariable').val(isChecked ? 'Yes' : '');
    $('#BillingSkipVariableFromBill').val(isChecked ? 'Yes' : '');

    updateContinueButton();
});

// Skip Email Change
$(document).on('change', '#SkipEmailValidation', function () {

    const isChecked = $(this).is(':checked');

    // ✅ FIX: Update hidden field (this was missing)
    $('#BillingSkipEmail').val(isChecked ? 'Yes' : '');
    $('#BillingSkipEmailFromBill').val(isChecked ? 'Yes' : '');

    updateContinueButton();
});

// On Page Load
$(document).ready(function () {

    // Initialize hidden fields correctly
    $('#BillingSkipVariable').val(
        $('#BillingSkip').is(':checked') ? 'Yes' : ''
    );

    $('#BillingSkipEmail').val(
        $('#SkipEmailValidation').is(':checked') ? 'Yes' : ''
    );

    updateContinueButton();
});

// Button Update Logic
function updateContinueButton() {

    const billingChecked = $('#BillingSkip').is(':checked');
    const emailChecked = $('#SkipEmailValidation').is(':checked');

    if (billingChecked || emailChecked) {

        if ($('#btnguest').length) {
            $('#btnguest')
                .attr('id', 'btnpmnt')
                .text('Continue Payment');
        }

        $('#btnasguest').hide();

    } else {

        if ($('#btnpmnt').length) {
            $('#btnpmnt')
                .attr('id', 'btnguest')
                .text('Continue Address');
        }

    }
}

var hasDataLoaded = false;
var isArrowClick = false;
function customer_auto_complete()
{
    $("#guest_email").autocomplete({
        minLength: 2,
		close: function (event, ui) {
			if (isArrowClick) {
				event.preventDefault();
				return false;
			}
		},
        source: function(request, response){
			$(".input-loader").show();
            $.ajax({
                url: site_url + "store/search-customer",
                dataType: "json",
                data: {
                    term: request.term
                },
                success: function(data){
					$(".input-loader").hide();
					hasDataLoaded = true;
                    if(data.length === 0)
                    {
                        response([{
                            label: "Customer not found",
                            value: "",
                            notfound: true
                        }]);
                    }
                    else
                    {
                        response(data);
                    }
                }
            });
        },
        select: function(event, ui){
            if(ui.item.notfound)
                return false;

            $("#guest_email").val(ui.item.email);
			$("#firstname").val(ui.item.first_name);
			$("#lastname").val(ui.item.last_name);
			$("#phonenumber").val(ui.item.phone);
			if(isValidEmail(ui.item.email)) {
				setAjaxGuestData(ui.item.email,ui.item.first_name,ui.item.last_name,ui.item.phone);
			}	
			/*if($("#chlabelval").length > 0){
				$("#chlabelval").hide();
			}*/
			if(ui.item.email !== sess_storeuseremail && $("#chlabelval").length > 0){
				$("#chlabelval").hide();
			}
			if(ui.item.email === sess_storeuseremail && $("#SkipEmailValidation").is(":checked") && $("#chlabelval").length > 0){
				$("#chlabelval").show();
			}

			$("#fname").addClass("input-fcs");
			$("#lname").addClass("input-fcs");
			$("#cphone").addClass("input-fcs");
			//fixSuggestions();
            return false;
        }

    })
    .autocomplete("instance")._renderItem = function(ul, item){
        if(item.notfound)
        {
            return $("<li>")
                .append(`
                    <div style="
                        padding:8px;
                        color:red;
                        text-align:center;
                        font-weight:bold;
                        font-style:italic;
                    ">
                        Customer not found
                    </div>
                `)
                .appendTo(ul);
        }

		var term = $("#guest_email").val();

		function highlight(text){
			if(!text) return "";
			var regex = new RegExp("(" + term + ")", "gi");
			return text.replace(regex,'<span class="highlight-match">$1</span>');
		}

        var html = `
        <div class="customer-autocomplete-row"
             style="
                display:flex;
                flex-direction:column;
                padding:8px;
                cursor:pointer;
            ">

            <div style="
                font-weight:bold;
                font-size:14px;
                color:#000;
                padding-bottom:2px;
            ">
                ${highlight(item.name)}
            </div>

            <div style="
                font-size:12px;
                color:#333;
                padding-bottom:3px;
            ">
                ${highlight(item.email)}
            </div>

            <div style="
                font-size:13px;
                color:#33;
            ">
                ${highlight(item.phone ?? '')}
            </div>
        </div>`;

        return $("<li>")
            .append(html)
            .appendTo(ul);
    };
}

$(document).on("click",".clear-input",function(){
    $("#guest_email").val("").focus();
    $("#ui-id-1").hide();
    $(".dropdown-arrow").addClass("d-none");
    $(this).hide();
	$("#firstname").val("");
	$("#lastname").val("");
	$("#phonenumber").val("");
	$('#checkoutYotpoWidget').empty().html("");

});
$("#guest_email").on("input blur", function(){
    if($(this).val().length > 0){
        $(".clear-input").removeClass("d-none").show();
		$(".dropdown-arrow").removeClass("d-none").show();
    }else{
        $("#ui-id-1").hide();
        $(".clear-input").addClass("d-none");
        $(".dropdown-arrow").addClass("d-none");
    }

	if(event.type === "blur") {
		var email = $(this).val().trim();
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if(emailRegex.test(email) && isValidEmail(email))  {		
            setAjaxGuestData(email,$("#firstname").val(),$("#lastname").val(),$("#phonenumber").val());
        }
    }
});

$("#formguest #firstname, #formguest #lastname, #formguest #phonenumber").on("blur", function () {
	if($("#guest_email").val() != "" && isValidEmail($("#guest_email").val())) {
    	setAjaxGuestData($("#guest_email").val(), $("#firstname").val(), $("#lastname").val(), $("#phonenumber").val());
	}	
});

$("#firstname").on("focus", function(){
	if($(this).val() == "" && $("#guest_email").val()!="" && isValidEmail($("#guest_email").val())){
		setAjaxGuestData($("#guest_email").val());
	}
});

function fixSuggestions()
{
	var checkData = setInterval(function() {
		var input_offset = $("#guest_email").offset();
		var input_height = $("#guest_email").outerHeight();
		var new_top = input_offset.top + input_height;
		var new_left = input_offset.left;
		$("#ui-id-1").css({
			top: new_top + "px",
			left: new_left + "px",
			display:"inline-block"
		});
		clearInterval(checkData);
	}, 1000);
}

function isValidEmail(email) {
    email = $.trim(email);
    var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

let guestTypingTimer = null;
function setAjaxGuestData(email,first_name = "",last_name = "",phone = ""){	
	clearTimeout(guestTypingTimer);
    guestTypingTimer = setTimeout(function () {
        setAjaxGuestDataSchedule(email, first_name, last_name, phone);
    }, 500);
}

function setAjaxGuestDataSchedule(email,first_name = "",last_name = "",phone = "")
{
	$.ajax({
		type:'POST',
		url:site_url+'setguestcustomerautocomplete',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data:{
			guest_email : email,
			firstname : first_name,
			lastname : last_name,
			phonenumber : phone,
			SkipEmailValidation : $("#SkipEmailValidation").is(":checked") ? 'Yes' : 'No'
		},
		success:function(data) {
			$("#bill_fname").val("");
			$("#bill_fname").val("");
			$("#bill_company").val("");
			$("#bill_address1").val("");
			$("#bill_address2").val("");
			$("#bill_city").val("");
			//$("#bill_country").val("");
			//$("#bill_state").val("");
			$("#bill_zip").val("");
			$("#bill_phone").val("");
			$("#bill_email").val("");

			var addInName = "N";
			if(first_name == "" && last_name == "" && phone == ""){
				addInName = "Y";
			}

			if (data && data.Billing && data.Billing.first_name) {
				$("#bill_fname").val(data.Billing.first_name);
				$("#bill_fname").parent().addClass('input-fcs');
				if(addInName == "Y"){
					$("#firstname").val(data.Billing.first_name);
					$("#firstname").parent().addClass('input-fcs');
				}
			}
			if (data && data.Billing && data.Billing.last_name) {
				$("#bill_lname").val(data.Billing.last_name);
				$("#bill_lname").parent().addClass('input-fcs');
				if(addInName == "Y"){
					$("#lastname").val(data.Billing.last_name);
					$("#lastname").parent().addClass('input-fcs');
				}
			}
			if (data && data.Billing && data.Billing.company) {
				$("#bill_company").val(data.Billing.company);
				$("#bill_company").parent().addClass('input-fcs');
			}
			if (data && data.Billing && data.Billing.address1) {
				$("#bill_address1").val(data.Billing.address1);
				$("#bill_address1").parent().addClass('input-fcs');
			}
			if (data && data.Billing && data.Billing.address2) {
				$("#bill_address2").val(data.Billing.address2);
				$("#bill_address2").parent().addClass('input-fcs');
			}
			if (data && data.Billing && data.Billing.city) {
				$("#bill_city").val(data.Billing.city);
				$("#bill_city").parent().addClass('input-fcs');
			}
			if (data && data.Billing && data.Billing.country) {
				$("#bill_country").val(data.Billing.country);
				$("#bill_country").parent().addClass('input-fcs');
			}
			if (data && data.Billing && data.Billing.country && data.Billing.country != 'US' && data.Billing.state!='') {
				$("#bill_other_state").val(data.Billing.state);
				$("#bill_other_state").parent().addClass('input-fcs');
				$("#divotherstate").show();
			} else {
				$("#bill_other_state").val('');
				$("#divotherstate").hide();
				$("#divstate").show();
			}
			if (data && data.Billing && data.Billing.country && data.Billing.country == 'US') {
				$("#bill_state").val(data.Billing.state);
				$("#bill_state").parent().addClass('input-fcs');
				$("#divstate").show();
			} else {
				//$("#bill_state").val('');
				//$("#divstate").hide();
			}
			if (data && data.Billing && data.Billing.zip) {
				$("#bill_zip").val(data.Billing.zip);
				$("#bill_zip").parent().addClass('input-fcs');
			}
			if (data && data.Billing && data.Billing.phone) {
				$("#bill_phone").val(data.Billing.phone);
				$("#bill_phone").parent().addClass('input-fcs');
				if(addInName == "Y"){										
					$("#phonenumber").val((data.Billing.phone || '').replace(/\D/g, ''));
					$("#phonenumber").parent().addClass('input-fcs');
				}
			}

			if (data && data.Billing && data.Billing.email) {
				$("#bill_email").val(data.Billing.email);
				$("#bill_email").parent().addClass('input-fcs');
			}

			if(data && data.yotpo_widget && data.yotpo_widget.length > 0 && $("#checkoutYotpoWidget").length > 0){
				 $('#checkoutYotpoWidget').empty().html(data.yotpo_widget);
				 initYotpoWidget();
			}

			//initiateYotpoWidgets(data.newcustid, data.Billing.email,data.Billing.first_name,data.Billing.last_name);
			// setTimeout(function () {
			// 	if (typeof yotpoWidgetsContainer !== 'undefined') {
			// 		console.log('one');
			// 		yotpoWidgetsContainer.initWidgets();
			// 	} else if (typeof Yotpo !== 'undefined') {
			// 		console.log('two');
			// 		var api = new Yotpo.API();
			// 		api.refreshWidgets();
			// 	}
			// }, 3000);

		}
	});
}

function initYotpoWidget() {
    let tries = 0;
    const maxTries = 30;

    function waitForYotpo() {
        tries++;

        if (
            window.yotpoWidgetsContainer &&
            document.getElementById('swell-customer-identification') &&
            document.getElementById('yotpo-loyalty-cart-data') &&
            document.getElementById('yotpo-loyalty-checkout-data')
        ) {
            //console.log('Initializing Yotpo widgets...');
            window.yotpoWidgetsContainer.initWidgets();
            return;
        }

        if (tries < maxTries) {
            setTimeout(waitForYotpo, 300);
        } else {
            console.error('Yotpo failed to initialize');
        }
    }

    waitForYotpo();
}

function initiateYotpoWidgets(customer_id, customer_email, first_name, last_name) {

	window.yotpoConfig = {
		appKey: "uIl5V6C_LVeCr5BT4bhDLQ",
		customerEmail: customer_email,
		customerId: customer_id, //"1",
		customerName: first_name + " " + last_name,//"Test User"
	};

    let tries = 0;

    let interval = setInterval(function () {
        tries++;
        
        if (window.yotpoWidgetsContainer && typeof window.yotpoWidgetsContainer.initWidgets === 'function') {
            window.yotpoWidgetsContainer.initWidgets();
            clearInterval(interval);
            console.log('Yotpo WidgetsContainer initialized');			
        }
        
        else if (window.yotpo && typeof window.yotpo.initWidgets === 'function') {
            window.yotpo.initWidgets();
            clearInterval(interval);
            console.log('Yotpo initWidgets fallback');
        }

        if (tries > 20) {
            clearInterval(interval);
            console.log('Yotpo not loaded');
        }

    }, 500);
}

$(document).ready(function () {
	if($("#guest_email").val() != ''){
		$(".clear-input").removeClass("d-none");
	}
    if (window.visualViewport) {
        var initialHeight = window.visualViewport.height;
        var keyboardOpen = false;
        var isSkuFocused = false;

        $("#guest_email").on("focus", function () {
            isSkuFocused = true;
        });

        $("#guest_email").on("blur", function () {
            isSkuFocused = false;
        });

        window.visualViewport.addEventListener("resize", function () {

            var currentHeight = window.visualViewport.height;

            if (currentHeight < initialHeight) {

                if (!keyboardOpen && isSkuFocused) {
                    keyboardOpen = true;

                    setTimeout(function () {
                        document.getElementById("guest_email").scrollIntoView({
                            behavior: "smooth",
                            block: "center"
                        });
                    }, 150);
                }

            } else {
                keyboardOpen = false;
            }

        });

    }

});

var isDropdownOpen = false;

$("#guest_email").on("input", function () {
    isDropdownOpen = false;
    hasDataLoaded = false;
});
$("#guest_email").on("focus", function () {
    isDropdownOpen = false;
});

$(".dropdown-arrow").on("mousedown", function (e) {

    e.preventDefault();

    var $input = $("#guest_email");
    var guestEmail = $input.val().trim();
    var $menu = $input.autocomplete("widget");

    if (guestEmail === "") {
        $(this).hide();
        $input.autocomplete("close");
        return;
    }

    $input.autocomplete("search", guestEmail);

});

function ShipToStoreShipping(ShipToStore)
{
    $("#page-spinner").show();

    $.ajax({
        type: 'POST',
        url: site_url + 'shiptostore',
        dataType: 'json',
        data: { ShipTostore: ShipToStore },

        success: function (res) {

            console.log(res);

            if (res.success === '1') {

                $.each(res.shipping, function(key, value){
                    const el = $("#" + key);
                    if (el.length) {
                        el.val(value);
                        el.parent().addClass('input-fcs');
                    }
                });

            } else {
                alert(res.message || 'Something went wrong');
            }
        },

        error: function () {
            alert("Server error");
        }

    }).always(function(){
        $("#page-spinner").hide();
    });
}

