var token = $('meta[name="csrf-token"]').attr('content');

// function edit_checkout_step(type){
// 	$("#"+type+"-form-section").toggleClass("d-none");
// 	// if($("#"+type+"-form-section").hasClass("d-none")){
// 	// 	$("#"+type+"-form-section").removeClass("d-none");
// 	// } else{
// 	// 	$("#"+type+"-form-section").toggleClass("d-none");
// 	// }
// }
function edit_checkout_step(current_step)
{
	$(".twofa_sec").addClass("d-none");
	$('#email_twofa_factor').val("off");

	if(current_step == 'agent')
	{
		$("#agent-form-section").toggleClass("d-none");
		$('#agent-login-link').removeClass('d-none');
		$('#agent-edit-link').addClass('d-none');
		//$('#agent-form-section').removeClass('d-none');
		$('#agent-info-section').addClass('d-none');

		$('#contact-login-link').addClass('d-none');
		$('#contact-edit-link').removeClass('d-none');
		$('#contact-form-section').addClass('d-none');
		$('#contact-info-section').removeClass('d-none');

		$('#shipping-edit-link').addClass('d-none');
		$('#customer-add-address-link').addClass('d-none');
		$('#shipping-form-section').addClass('d-none');
		$('#shipping-info-section').addClass('d-none');

		$('#billing-edit-link').addClass('d-none');
		$('#billing-form-section').addClass('d-none');
		$('#billing-info-section').addClass('d-none');

		if($('#gbcash-form-section')) {
			$('#gbcash-form-section').addClass('d-none');
			$("#c-step-4").css("paddingBottom", "40px");
			if($('#loyaltyPayment')) {
				$('#loyaltyPayment').addClass('d-none');
			}
		}
		if($('#giftcard-form-section')) {
			$('#giftcard-form-section').addClass('d-none');
			$("#c-step-5").css("paddingBottom", "40px");
			//if($('#loyaltyPayment')) {
			//	$('#loyaltyPayment').addClass('d-none');
			//}
		}
		$('#payment-form-section').addClass('d-none');

	}

	if(current_step == 'contact')
	{
		if($('#agent-edit-link')) {
			$('#agent-login-link').addClass('d-none');
			$('#agent-edit-link').removeClass('d-none');
			$('#agent-form-section').addClass('d-none');
			$('#agent-info-section').removeClass('d-none');
		}

		$('#contact-login-link').removeClass('d-none');
		$('#contact-edit-link').addClass('d-none');
		$('#contact-form-section').removeClass('d-none');
		$('#contact-info-section').addClass('d-none');

		$('#shipping-edit-link').addClass('d-none');
		$('#customer-add-address-link').addClass('d-none');
		$('#shipping-form-section').addClass('d-none');
		$('#shipping-info-section').addClass('d-none');

		$('#billing-edit-link').addClass('d-none');
		$('#billing-form-section').addClass('d-none');
		$('#billing-info-section').addClass('d-none');

		if($('#gbcash-form-section')) {
			$('#gbcash-form-section').addClass('d-none');
			$("#c-step-4").css("paddingBottom", "40px");
			if($('#loyaltyPayment')) {
				$('#loyaltyPayment').addClass('d-none');
			}
		}
		if($('#giftcard-form-section')) {
			$('#giftcard-form-section').addClass('d-none');
			$("#c-step-5").css("paddingBottom", "40px");
			//if($('#loyaltyPayment')) {
			//	$('#loyaltyPayment').addClass('d-none');
			//}
		}
		$('#payment-form-section').addClass('d-none');

	}

	if(current_step == 'shipping')
	{

		if($('#agent-edit-link')) {
			$('#agent-login-link').addClass('d-none');
			$('#agent-edit-link').removeClass('d-none');
			$('#agent-form-section').addClass('d-none');
			$('#agent-info-section').removeClass('d-none');
		}

		$('#contact-login-link').addClass('d-none');
		$('#contact-edit-link').removeClass('d-none');
		$('#contact-form-section').addClass('d-none');
		$('#contact-info-section').removeClass('d-none');

		$('#shipping-edit-link').addClass('d-none');
		$('#customer-add-address-link').removeClass('d-none');
		$('#shipping-form-section').removeClass('d-none');
		$('#shipping-info-section').addClass('d-none');

		$('#billing-edit-link').addClass('d-none');
		$('#billing-form-section').addClass('d-none');
		$('#billing-info-section').addClass('d-none');

		if($('#gbcash-form-section')) {
			$('#gbcash-form-section').addClass('d-none');
			$("#c-step-4").css("paddingBottom", "40px");
			if($('#loyaltyPayment')) {
				$('#loyaltyPayment').addClass('d-none');
			}
		}
		if($('#giftcard-form-section')) {
			$('#giftcard-form-section').addClass('d-none');
			$("#c-step-5").css("paddingBottom", "40px");
			//if($('#loyaltyPayment')) {
			//	$('#loyaltyPayment').addClass('d-none');
			//}
		}
		$('#payment-form-section').addClass('d-none');

		if($("#same_asbill").is(':checked') == true)
		{
			$("#same_asbill").prop('checked', false);
			show_billing_address();
		}
	}

	if(current_step == 'billing')
	{
		if($('#agent-edit-link')) {
			$('#agent-login-link').addClass('d-none');
			$('#agent-edit-link').removeClass('d-none');
			$('#agent-form-section').addClass('d-none');
			$('#agent-info-section').removeClass('d-none');
		}

		$('#contact-login-link').addClass('d-none');
		$('#contact-edit-link').removeClass('d-none');
		$('#contact-form-section').addClass('d-none');
		$('#contact-info-section').removeClass('d-none');

		$('#shipping-edit-link').removeClass('d-none');
		$('#customer-add-address-link').addClass('d-none');
		$('#shipping-form-section').addClass('d-none');
		$('#shipping-info-section').removeClass('d-none');

		$('#billing-edit-link').addClass('d-none');
		$('#billing-form-section').removeClass('d-none');
		$('#billing-info-section').addClass('d-none');

		if($('#gbcash-form-section')) {
			$('#gbcash-form-section').addClass('d-none');
			$("#c-step-4").css("paddingBottom", "40px");
			if($('#loyaltyPayment')) {
				$('#loyaltyPayment').addClass('d-none');
			}
		}
		if($('#giftcard-form-section')) {
			$('#giftcard-form-section').addClass('d-none');
			$("#c-step-5").css("paddingBottom", "40px");
			//if($('#loyaltyPayment')) {
			//	$('#loyaltyPayment').addClass('d-none');
			//}
		}
		$('#payment-form-section').addClass('d-none');

		if($("#same_asbill").is(':checked') == true)
		{
			$("#same_asbill").prop('checked', false);
			show_billing_address();
		}

	}

}

$(function(){
  var $form      = $('#frmstoreAddToCart');
  var $errorSku  = $('#error_skuval');

  $form.validate({
    rules: {
      skuval: { required: true }
    },
    messages: {
      skuval: { required: GetMessage('POS','ProductSKUUPC') }
    },
    errorPlacement: function(error, element) {
      $errorSku.text(error.text()).show();
    },
  });

  $('#AddToCartStore').on('click', function(e){
    e.preventDefault();
    $errorSku.hide().empty();
	console.log('IN')
	var SKU = $(".tab-pane.active #skuval").val();
	if($.trim(SKU) == '')
	{
		$(".tab-pane.active #error_skuval").html(GetMessage('POS','ProductSKUUPC'));
		$(".tab-pane.active #error_skuval").show();
		return false;
	}
    if ( $form.valid() ) {
      AddToCartStore( $('#skuval').val(), 1 );
    }
  });
});

function save_storeuser_detail_bk() {

	var cntAgents = $('#cntagents').val();
	for(i=1;i<=cntAgents;i++) {
		if($('#salesperson'+i+'_per'+i).val()=='') {
			$("#agentDiv"+i).remove();
			//$("#error_salesperson"+i).html('');
			$("#error_salesperson"+i).html('').hide();
			var cntAgents = parseInt($('#cntagents').val())-1;
			$('#cntagents').val(parseInt(cntAgents));

			if(cntAgents==5) { $('#addAgentDiv').hide(); } else if(cntAgents<5) { $('#addAgentDiv').show(); }
		}
	}

	var totAgents = $('#totagents').val();
	$('#agent-info-section').html('');
	var agentInfo = '';
	var saveAgentInfo = '&totAgents='+totAgents;
	var agentCommission = 0;
	for(i=1;i<=totAgents;i++) {
		var agentDtl = $('#salesperson'+i).val()+" ("+$('#salesperson'+i+'_per'+i).val()+"%)";
		var addClassVal = '';
		//if(i==1) { addClassVal = ' pt-md-4 pt-3 '; }
		agentInfo = agentInfo+'<div class="left-infodtl-inner '+addClassVal+' mt-md-1 mt-1" id="agent-filled-section">'+agentDtl+'</div>';
		saveAgentInfo = saveAgentInfo+'&sales_person_id'+i+'='+$('#salespersonuser'+i).val()+'&sales_person_per'+i+'='+$('#salesperson'+i+'_per'+i).val();
		agentCommission+= parseFloat($('#salesperson'+i+'_per'+i).val());
	}

	if(agentCommission!=100) { $("#error_commission").html('The total commission should be 100% among the selected salespersons.').show(); return false; }
	$("#error_commission").html('');

	//var STR_POST_VAR = STR_POST_VAR + '&_token='+$('meta[name="csrf-token"]').attr('content');
	var STR_POST_VAR = '&_token='+token;
		STR_POST_VAR = STR_POST_VAR + saveAgentInfo;

		$.ajax({
			type: 'POST',
			url: site_url+'store/add_salesperson',
			dataType: "json",
			data: STR_POST_VAR,
			beforeSend: function()
			{
			},
			success: (function(data, status)
			{
				$('#agent-info-section').append(agentInfo);
				show_next_checkout_step('agent');
				}),
			complete :(function()
			{
			})
	});
}

function save_storeuser_detail() {

	var cntAgents = parseInt($('#cntagents').val());
	var isValid = true;

	for (var i = 1; i <= cntAgents; i++) {
		var email = $('#salesperson' + i).val();
		var percent = $('#salesperson' + i + '_per' + i).val();
		var errorField = $('#error_salesperson' + i);

		var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

		if (!emailPattern.test(email) || percent === '') {
			errorField.html('Please enter a valid email and commission %').show();
			isValid = false;
		} else {
			errorField.html('').hide();
		}
	}

	if (!isValid) {
		return false;
	}

	var cntAgents = $('#cntagents').val();
	for(i=1;i<=cntAgents;i++) {
		if($('#salesperson'+i+'_per'+i).val()=='') {
			$("#agentDiv"+i).remove();
			//$("#error_salesperson"+i).html('');
			$("#error_salesperson"+i).html('').hide();
			var cntAgents = parseInt($('#cntagents').val())-1;
			$('#cntagents').val(parseInt(cntAgents));

			if(cntAgents==5) { $('#addAgentDiv').hide(); } else if(cntAgents<5) { $('#addAgentDiv').show(); }
		}
	}

	var totAgents = $('#totagents').val();
	$('#agent-info-section').html('');
	var agentInfo = '';
	var saveAgentInfo = '&totAgents='+totAgents;
	var agentCommission = 0;
	for(i=1;i<=totAgents;i++) {
		var agentDtl = $('#salesperson'+i).val()+" ("+$('#salesperson'+i+'_per'+i).val()+" %)";
		var addClassVal = '';
		//if(i==1) { addClassVal = ' pt-md-4 pt-3 '; }
		agentInfo = agentInfo+'<div class="left-infodtl-inner '+addClassVal+' mt-md-1 mt-1" id="agent-filled-section">'+agentDtl+'</div>';
		saveAgentInfo = saveAgentInfo+'&sales_person_id'+i+'='+$('#salespersonuser'+i).val()+'&sales_person_per'+i+'='+$('#salesperson'+i+'_per'+i).val();
		agentCommission+= parseFloat($('#salesperson'+i+'_per'+i).val());
	}

	if(agentCommission!=100) { $("#error_commission").html('The total commission should be 100% among the selected salespersons.').show(); return false; }
	$("#error_commission").html('');
	var STR_POST_VAR = STR_POST_VAR + '&_token='+$('meta[name="csrf-token"]').attr('content');
		STR_POST_VAR = STR_POST_VAR + saveAgentInfo;
		$.ajax({
					type: 'POST',
					url: site_url+'store/add_salesperson',
					dataType: "json",
					data: STR_POST_VAR,
					beforeSend: function()
					{
					},
					success: (function(data, status)
					{
						$('#agent-info-section').append(agentInfo);
						show_next_checkout_step('agent');
						}),
					complete :(function()
					{
					})
			});
	$("#agent-edit-link").addClass("d-none");
}

$(document).on('input', '.commission-input', function () {
    let val = $(this).val();
    val = val.replace(/[^0-9.]/g, '');
    val = val.replace(/(\..*)\./g, '$1');
    $(this).val(val);
});

function show_next_checkout_step(current_step)
{
	if(current_step == 'agent')
	{
		$('#agent-login-link').addClass('d-none');
		//$('#agent-edit-link').removeClass('d-none');
		$('#agent-form-section').addClass('d-none');
		$('#agent-info-section').removeClass('d-none');

		$('#contact-login-link').removeClass('d-none');
		$('#contact-edit-link').addClass('d-none');
		$('#contact-form-section').removeClass('d-none');
		$('#contact-info-section').addClass('d-none');

		$('#shipping-edit-link').addClass('d-none');
		$('#customer-add-address-link').removeClass('d-none');
		$('#shipping-form-section').removeClass('d-none');
		$('#shipping-info-section').addClass('d-none');

	}

	if(current_step == 'contact')
	{
		if($('#agent-edit-link')) {
			$('#agent-login-link').addClass('d-none');
			$('#agent-edit-link').removeClass('d-none');
			$('#agent-form-section').addClass('d-none');
			$('#agent-info-section').removeClass('d-none');
		}

		$('#contact-login-link').addClass('d-none');
		$('#contact-edit-link').removeClass('d-none');
		$('#contact-form-section').addClass('d-none');
		$('#contact-info-section').removeClass('d-none');

		$('#shipping-edit-link').addClass('d-none');
		$('#customer-add-address-link').removeClass('d-none');
		$('#shipping-form-section').removeClass('d-none');
		$('#shipping-info-section').addClass('d-none');

	}

	if(current_step == 'shipping')
	{
		if($('#agent-edit-link')) {
			$('#agent-login-link').addClass('d-none');
			$('#agent-edit-link').removeClass('d-none');
			$('#agent-form-section').addClass('d-none');
			$('#agent-info-section').removeClass('d-none');
		}

		$('#contact-login-link').addClass('d-none');
		$('#contact-edit-link').removeClass('d-none');
		$('#contact-form-section').addClass('d-none');
		$('#contact-info-section').removeClass('d-none');

		$('#shipping-edit-link').removeClass('d-none');
		$('#customer-add-address-link').addClass('d-none');
		$('#shipping-form-section').addClass('d-none');
		$('#shipping-info-section').removeClass('d-none');

		$('#billing-edit-link').addClass('d-none');
		$('#billing-form-section').removeClass('d-none');
		$('#billing-info-section').addClass('d-none');

	}

	if(current_step == 'billing')
	{
		if($('#agent-edit-link')) {
			$('#agent-login-link').addClass('d-none');
			$('#agent-edit-link').removeClass('d-none');
			$('#agent-form-section').addClass('d-none');
			$('#agent-info-section').removeClass('d-none');
		}

		$('#contact-login-link').addClass('d-none');
		$('#contact-edit-link').removeClass('d-none');
		$('#contact-form-section').addClass('d-none');
		$('#contact-info-section').removeClass('d-none');

		$('#shipping-edit-link').removeClass('d-none');
		$('#customer-add-address-link').addClass('d-none');
		$('#shipping-form-section').addClass('d-none');
		$('#shipping-info-section').removeClass('d-none');

		$('#billing-edit-link').removeClass('d-none');
		$('#billing-form-section').addClass('d-none');
		$('#billing-info-section').removeClass('d-none');

		if($('#gbcash-form-section')) {
			$("#c-step-4").css("paddingBottom", "0px");
			$('#gbcash-form-section').removeClass('d-none');
			if($('#loyaltyPayment')) {
				$('#loyaltyPayment').removeClass('d-none');
			}
		}
		if($('#giftcard-form-section')) {
			$("#c-step-5").css("paddingBottom", "0px");
			$('#giftcard-form-section').removeClass('d-none');
			//if($('#loyaltyPayment')) {
			//	$('#loyaltyPayment').removeClass('d-none');
			//}
		}
		$('#payment-form-section').removeClass('d-none');
		//Ajax_GetOrder_Summery();

	}

}

function checkChangedCommissionValues(id) {
	if($("#salesperson"+id).val() == ""){
		$("#salesperson"+id+"_per"+id).val("");
		$("#error_salesperson"+id).html('Please enter salesperson!').show();
		return false;
	}
	var totAgents = $('#totagents').val();
	//alert(totAgents);
	var perCal = 0;
	if($('#salesperson'+id+'_per'+id).val()!=$('#editCurrentPts').val()) { $('#salesperson'+id+'_fix'+id).val(1);
		for(ms=1;ms<=totAgents;ms++) {
			if($('#salesperson'+ms+'_fix'+ms).val()==1 && $("#salesperson"+ms).val()!='') {
				var perval = $('#salesperson'+ms+'_per'+ms).val();
				perCal+= parseFloat($('#salesperson'+ms+'_per'+ms).val());
			}
		}
		if(perCal>100) { var adjustPrice = parseFloat(perCal)-100; $('#salesperson'+id+'_per'+id).val(parseFloat($('#salesperson'+id+'_per'+id).val()-adjustPrice).toFixed(2)); }
		//$('#salesperson'+id+'_per'+id).prop('readonly', true);
		resetCommissionValues();
	}
	//$('#editCurrentPts').val('');
}

function resetCommissionValues_bk() {
	var totAgents = $('#totagents').val();
	var calCom = 0;
	var calAllCom = 0;

	var fixedComAgents = 0;
	var addedAgents = 0;
	var perCal = 0;

	for(ms=1;ms<=totAgents;ms++) {
		if($('#salesperson'+ms+'_fix'+ms).val()==1 && $("#salesperson"+ms).val()!='') {
			// if($('#salesperson'+ms+'_per'+ms).length){
			// 	console.log("exists");
			// } else {
			// 	console.log("not exists");
			// }
			// console.log($('#salesperson'+ms+'_per'+ms).val());

			var perval = $('#salesperson'+ms+'_per'+ms).val();
			fixedComAgents++;
			addedAgents++;
			perCal+= parseFloat($('#salesperson'+ms+'_per'+ms).val());
			$('#salesperson'+ms+'_per'+ms).val(parseFloat(perval).toFixed(2));
		}
		else if($("#salesperson"+ms).val()!='') {
			addedAgents++;
		}
	}

	fixedComAgents = addedAgents-fixedComAgents;
	perCal = 100 - perCal;

	for(i=1;i<=totAgents;i++) {
		if($('#salesperson'+i+'_fix'+i).val()==0 && $("#salesperson"+i).val()!='') { calCom = parseFloat(perCal/fixedComAgents).toFixed(2); $('#salesperson'+i+'_per'+i).val(calCom); }
		calAllCom+= parseFloat($('#salesperson'+i+'_per'+i).val());
		//alert((100-parseFloat(calAllCom)).toFixed(2));
		if(((100-parseFloat(calAllCom)).toFixed(2))==0.01 && $("#salesperson"+i).val()!='') { $('#salesperson'+i+'_per'+i).val((parseFloat(calCom)+0.01).toFixed(2));  }
		//alert(parseFloat(calAllCom).toFixed(2));
		if(((parseFloat(calAllCom)-100).toFixed(2))==0.01 && $("#salesperson"+i).val()!='') { $('#salesperson'+i+'_per'+i).val((parseFloat(calCom)-0.01).toFixed(2));  }
	}

	if(totAgents==5) { $('#addAgentDiv').hide(); } else if(totAgents<5) { $('#addAgentDiv').show(); }
}

function valid_storeuser_detail(id) {
	//$("#frmCheckOut div.error-cls").html('');

	$("#frmCheckOut div.frmerror").html('');
	$('#frmCheckOut').data('validator', null);
	$("#frmCheckOut").unbind('validate');

	$('#frmCheckOut').validate({
		ignore: ":hidden:not(#keycode)",
		rules: {
			['salesperson' + id]: { required: true, email: true }
		},
		messages:
		{
			['salesperson' + id]: { required: "Please Enter valid Salesperson Email Address", email: "Please Enter valid Salesperson Email Address"}
		},
		onsubmit: false,
		invalidHandler: function(form, validator)
		{
			var errors = validator.numberOfInvalids();
			//console.log(errors);
			if (errors)
			{
				for(var i=0;i<errors;i++)
				{
					var message = validator.errorList[i].message;
					var id = $(validator.errorList[i].element).attr('name');
					$("#frmCheckOut div#error").html(message);
				}
				validator.errorList[0].element.focus();
			}
		   else
		   {
			   //$("#frmCheckOut div.error-cls").html('');
			   $("#frmCheckOut div.frmerror").html('');
		   }
		},
		errorPlacement: function(error, element)
		{
			// Override error placement to not show error messages beside elements //
		}
	});

	if(!$("#frmCheckOut").valid())
	{
		return false;
	}
	else {
		return true;
	}
}

function check_salesuser(email, id) {
	// alert(email);
	// alert(token);
	// alert(id);
	var STR_POST_VAR = "";
	// $('#search_result'+id+' li:first').trigger('click');
	// email = $firstLi.data('email');

	// var $firstLi = $('#search_result'+id+' li').first();
	// var email = $firstLi.data('email');
	// $firstLi.trigger('click');

	if(valid_storeuser_detail(id)) {
		$('#search_result'+id).html(''); $("#search_result"+id).removeClass('cust-search');
		//var STR_POST_VAR = STR_POST_VAR + '&_token='+$('meta[name="csrf-token"]').attr('content');
		var STR_POST_VAR = STR_POST_VAR + '&_token='+token;
		STR_POST_VAR = STR_POST_VAR + '&email='+email;

		$.ajax({
					type: 'POST',
					url: site_url+'store/validate_salesperson',
					dataType: "json",
					data: STR_POST_VAR,
					beforeSend: function()
					{
					},
					success: (function(data, status)
					{
						if(data==0) {
							//$("#error_salesperson"+id).html('No salesperson found with this email id!')
							$("#salesperson"+id+"_per"+id).val("").prop("readonly",true);
							$("#error_salesperson"+id).html('No salesperson found with this email id!').show();
						}
					}),
					complete :(function()
					{
					})
			});

	}
}

function addAgent() {

	var cntAgents = parseInt($('#cntagents').val());
	var isValid = true;

	for (var i = 1; i <= cntAgents; i++) {
		var email = $('#salesperson' + i).val();
		var percent = $('#salesperson' + i + '_per' + i).val();
		var errorField = $('#error_salesperson' + i);

		var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

		if (!emailPattern.test(email) || percent === '') {
			errorField.html('Please enter a valid email and commission %').show();
			isValid = false;
		} else {
			errorField.html('').hide();
		}
	}

	if (!isValid) {
		return false;
	}

	var cntAgents = $('#cntagents').val();

	for(i=1;i<=cntAgents;i++) {
		if($('#salesperson'+i+'_per'+i).val()=='') {
			$("#agentDiv"+i).remove();
			//$("#error_salesperson"+i).html('');
			$("#error_salesperson"+i).html('').hide();
			var cntAgents = parseInt($('#cntagents').val())-1;
			$('#cntagents').val(parseInt(cntAgents)-1);
		}
	}
	var idVal = parseInt(cntAgents)+1;
	$('#cntagents').val(idVal);

	$("#addAgent").append('<div class="form-group agent-info-group d-flex" id="agentDiv' + idVal + '"> \
    <div class="aig-left"> \
		<input type="hidden" name="salespersonuser' + idVal + '" id="salespersonuser' + idVal + '" class="form-control" value="" /> \
        <input type="text" name="salesperson' + idVal + '" id="salesperson' + idVal + '" class="form-control"  onblur="javascript:check_salesuser(this.value, ' + idVal + ');" onkeyup="javascript:load_salesperson_data(this.value, ' + idVal + ');" autocomplete="off" /> \
        <ul id="search_result' + idVal + '"></ul> \
        </div> \
    <div class="aig-right d-flex"> \
        <input type="text" name="salesperson' + idVal + '_per" id="salesperson' + idVal + '_per' + idVal + '" class="form-control commission-input" onblur="javascript: checkChangedCommissionValues(' + idVal + ');" /> \
        <input type="hidden" name="salesperson' + idVal + '_fix' + idVal + '" id="salesperson' + idVal + '_fix' + idVal + '" value="0" /> \
        <div class="aig-icon d-flex pl-2  align-items-center"> \
            <a href="javascript:void(0);" onclick="javascript:deleteSalesCommission(' + idVal + ');"> \
                <svg class="sv-trash vam" aria-hidden="true" role="img" width="18" height="18"> \
                    <use href="#sv-trash" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-trash"></use> \
                </svg> \
            </a> \
        </div> \
    </div> \
	 \
</div><span class="frmerror" id="error_salesperson' + idVal + '"></span>');
	$("#salespersonuser"+idVal).focus();
	if($('#cntagents').val()==5) { $('#addAgentDiv').hide(); }
}

function addAgent_bk() {

	var cntAgents = $('#cntagents').val();

	for(i=1;i<=cntAgents;i++) {
		if($('#salesperson'+i+'_per'+i).val()=='') {
			$("#agentDiv"+i).remove();
			//$("#error_salesperson"+i).html('');
			$("#error_salesperson"+i).html('').hide();
			var cntAgents = parseInt($('#cntagents').val())-1;
			$('#cntagents').val(parseInt(cntAgents)-1);
		}
	}
	var idVal = parseInt(cntAgents)+1;
	$('#cntagents').val(idVal);

	$("#addAgent").append('<div class="form-group agent-info-group d-flex" id="agentDiv' + idVal + '"> \
    <div class="aig-left"> \
		<input type="hidden" name="salespersonuser' + idVal + '" id="salespersonuser' + idVal + '" class="form-control" value="" /> \
        <input type="text" name="salesperson' + idVal + '" id="salesperson' + idVal + '" class="form-control"  onblur="javascript:check_salesuser(this.value, ' + idVal + ');" onkeyup="javascript:load_salesperson_data(this.value, ' + idVal + ');" autocomplete="off" /> \
        <ul id="search_result' + idVal + '"></ul> \
        </div> \
    <div class="aig-right d-flex"> \
        <input type="text" name="salesperson' + idVal + '_per" id="salesperson' + idVal + '_per' + idVal + '" class="form-control commission-input" onblur="javascript: checkChangedCommissionValues(' + idVal + ');" /> \
        <input type="hidden" name="salesperson' + idVal + '_fix' + idVal + '" id="salesperson' + idVal + '_fix' + idVal + '" value="0" /> \
        <div class="aig-icon d-flex pl-2  align-items-center"> \
            <a href="javascript:void(0);" onclick="javascript:deleteSalesCommission(' + idVal + ');"> \
                <svg class="sv-trash vam" aria-hidden="true" role="img" width="18" height="18"> \
                    <use href="#sv-trash" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-trash"></use> \
                </svg> \
            </a> \
        </div> \
    </div> \
	<span class="frmerror" id="error_salesperson' + idVal + '"></span> \
</div>');
	$("#salespersonuser"+idVal).focus();
	if($('#cntagents').val()==5) { $('#addAgentDiv').hide(); }
}

function deleteSalesCommission_11(id) {
    const isExisting = $("#salesperson" + id).val() !== '' && $('#salesperson' + id + '_per' + id).val() !== '';

    if (isExisting) {
        // Clear values
        $("#salesperson" + id).val('');
        $('#salesperson' + id + '_per' + id).val('');
        $('#salesperson' + id + '_fix' + id).val(0);

        $("#agentDiv" + id).remove();

        let totAgents = parseInt($('#totagents').val());
        let cntAgents = parseInt($('#cntagents').val());
        $('#totagents').val(totAgents - 1);
        $('#cntagents').val(cntAgents - 1);

        resetCommissionValues();
    }
}

function resetCommissionValues_11() {
    var calCom = 0, calAllCom = 0, fixedComAgents = 0, addedAgents = 0, perCal = 0;

    let allAgents = $('[id^=salespersonuser]').filter(function () {
        const id = $(this).attr('id').replace('salespersonuser', '');
        return $("#salesperson" + id).val() !== '';
    });

    var totAgents = allAgents.length;
    $('#totagents').val(totAgents);

    allAgents.each(function () {
        const id = $(this).attr('id').replace('salespersonuser', '');
        if ($("#salesperson" + id + "_fix" + id).val() == 1) {
            let perval = $('#salesperson' + id + '_per' + id).val();
            fixedComAgents++;
            addedAgents++;
            perCal += parseFloat(perval || 0);
            $('#salesperson' + id + '_per' + id).val(parseFloat(perval).toFixed(2));
        } else {
            addedAgents++;
        }
    });

    fixedComAgents = addedAgents - fixedComAgents;
    perCal = 100 - perCal;

    allAgents.each(function () {
        const id = $(this).attr('id').replace('salespersonuser', '');
        if ($("#salesperson" + id + "_fix" + id).val() == 0) {
            calCom = parseFloat(perCal / fixedComAgents).toFixed(2);
            $('#salesperson' + id + '_per' + id).val(calCom);
        }

        calAllCom += parseFloat($('#salesperson' + id + '_per' + id).val() || 0);

        if (((100 - parseFloat(calAllCom)).toFixed(2)) == 0.01) {
            $('#salesperson' + id + '_per' + id).val((parseFloat(calCom) + 0.01).toFixed(2));
        }
        if (((parseFloat(calAllCom) - 100).toFixed(2)) == 0.01) {
            $('#salesperson' + id + '_per' + id).val((parseFloat(calCom) - 0.01).toFixed(2));
        }
    });

    if (totAgents >= 5) {
        $('#addAgentDiv').hide();
    } else {
        $('#addAgentDiv').show();
    }
}

function deleteSalesCommission(id) {
	if($("#salesperson"+id).val()!='' && $('#salesperson'+id+'_per'+id).val()!='') {
		$("#salesperson"+id).val('');
		$('#salesperson'+id+'_per'+id).val('');
		$('#salesperson'+id+'_fix'+id).val(0);
		//$("#salesperson"+id).parent().parent().remove();

		var nextSalesId = parseInt(id)+1;
		for(i=nextSalesId;i<6;i++) {
			var resetValueId = parseInt(i)-1;
			$("#salesperson"+resetValueId).val($("#salesperson"+i).val());
			$('#salesperson'+resetValueId+'_per'+resetValueId).val($('#salesperson'+i+'_per'+i).val());
			$('#salesperson'+resetValueId+'_fix'+resetValueId).val($('#salesperson'+i+'_fix'+i).val());

			if(i==5) {
				$("#salesperson"+i).val('');
				$('#salesperson'+i+'_per'+i).val('');
				$('#salesperson'+i+'_fix'+i).val(0);
			}
		}
		//$("#salesperson"+i).parent().parent().remove();
		var totAgents = parseInt($('#totagents').val())-1;
		$('#totagents').val(totAgents);
		resetCommissionValues();

		//console.log($("#salespersonuser"+id).parent().parent().html());
		//$("#salespersonuser"+id).parent().parent().remove();

		var cntAgents = $('#cntagents').val();
		$("#agentDiv"+cntAgents).remove();
		$("#salesperson"+cntAgents).parent().parent().remove();
		$('#cntagents').val(parseInt(cntAgents)-1);
	} else {
		$("#agentDiv"+id).remove();
		var cntAgents = $('#cntagents').val();
		$('#cntagents').val(parseInt(cntAgents)-1);
	}
}

function resetCommissionValues() {
	var totAgents = $('#totagents').val();
	var calCom = 0;
	var calAllCom = 0;

	var fixedComAgents = 0;
	var addedAgents = 0;
	var perCal = 0;

	for(ms=1;ms<=totAgents;ms++) {
		if($('#salesperson'+ms+'_fix'+ms).val()==1 && $("#salesperson"+ms).val()!='') {
			var perval = $('#salesperson'+ms+'_per'+ms).val();
			fixedComAgents++;
			addedAgents++;
			perCal+= parseFloat($('#salesperson'+ms+'_per'+ms).val());
			$('#salesperson'+ms+'_per'+ms).val(parseFloat(perval).toFixed(2));
		}
		else if($("#salesperson"+ms).val()!='') {
			addedAgents++;
		}
	}

	fixedComAgents = addedAgents-fixedComAgents;
	perCal = 100 - perCal;

	for(i=1;i<=totAgents;i++) {
		if($('#salesperson'+i+'_fix'+i).val()==0 && $("#salesperson"+i).val()!='') { calCom = parseFloat(perCal/fixedComAgents).toFixed(2); $('#salesperson'+i+'_per'+i).val(calCom); }
		calAllCom+= parseFloat($('#salesperson'+i+'_per'+i).val());
		//alert((100-parseFloat(calAllCom)).toFixed(2));
		if(((100-parseFloat(calAllCom)).toFixed(2))==0.01 && $("#salesperson"+i).val()!='') { $('#salesperson'+i+'_per'+i).val((parseFloat(calCom)+0.01).toFixed(2));  }
		//alert(parseFloat(calAllCom).toFixed(2));
		if(((parseFloat(calAllCom)-100).toFixed(2))==0.01 && $("#salesperson"+i).val()!='') { $('#salesperson'+i+'_per'+i).val((parseFloat(calCom)-0.01).toFixed(2));  }
	}

	if(totAgents==5) { $('#addAgentDiv').hide(); } else if(totAgents<5) { $('#addAgentDiv').show(); }

	if (shouldResetToDynamic()) {
		redistributeExceptLastFixed();
		//resetAllAgentsToDynamic();
	}
}

function shouldResetToDynamic() {
	var totAgents = $('#totagents').val();
	for (var i = 1; i <= totAgents; i++) {
		if ($("#salesperson"+i).val() != '' && $('#salesperson'+i+'_fix'+i).val() != '1') {
			return false;
		}
	}
	return true;
}

function redistributeExceptLastFixed() {
	var totAgents = $('#totagents').val();
	var agentData = [];

	// Collect fixed agents with values
	for (var i = 1; i <= totAgents; i++) {
		var name = $("#salesperson" + i).val();
		var percent = parseFloat($('#salesperson' + i + '_per' + i).val());
		var fixed = $('#salesperson' + i + '_fix' + i).val();

		if (name != '' && percent > 0 && fixed == '1') {
			agentData.push({ id: i, percent: percent });
		}
	}

	if (agentData.length < 2) return; // Not enough to redistribute

	// Get the last fixed agent (keep as-is)
	var lastFixed = agentData[agentData.length - 1];
	var remainingPercent = 100 - lastFixed.percent;

	// All others: reset fix to 0 and redistribute
	var agentsToUpdate = agentData.slice(0, -1);
	var perShare = parseFloat(remainingPercent / agentsToUpdate.length).toFixed(2);

	var distributedTotal = 0;
	for (var j = 0; j < agentsToUpdate.length; j++) {
		var id = agentsToUpdate[j].id;
		$('#salesperson'+id+'_fix'+id).val(0);
		$('#salesperson'+id+'_per'+id).val(perShare);
		distributedTotal += parseFloat(perShare);
	}

	// Round off any diff
	var roundingDiff = (remainingPercent - distributedTotal).toFixed(2);
	if (roundingDiff != 0 && agentsToUpdate.length > 0) {
		var adjustId = agentsToUpdate[0].id;
		var current = parseFloat($('#salesperson'+adjustId+'_per'+adjustId).val());
		$('#salesperson'+adjustId+'_per'+adjustId).val((current + parseFloat(roundingDiff)).toFixed(2));
	}
}

function redistributeExceptLastFixed_11() {
	var totAgents = $('#totagents').val();
	var agentData = [];

	for (var i = 1; i <= totAgents; i++) {
		var name = $("#salesperson" + i).val();
		var percent = parseFloat($('#salesperson' + i + '_per' + i).val());
		var fixed = $('#salesperson' + i + '_fix' + i).val();

		if (name != '') {
			agentData.push({
				id: i,
				name: name,
				percent: percent,
				fixed: fixed
			});
		}
	}

	if (agentData.length < 2) return;

	// Get the last fixed agent
	var last = agentData[agentData.length - 1];
	if (last.fixed != '1') return;

	var remainingPercent = 100 - last.percent;
	var distributableAgents = agentData.slice(0, -1);
	var perAgent = parseFloat(remainingPercent / distributableAgents.length).toFixed(2);

	var total = 0;
	for (var j = 0; j < distributableAgents.length; j++) {
		var id = distributableAgents[j].id;
		$('#salesperson' + id + '_fix' + id).val(0);
		$('#salesperson' + id + '_per' + id).val(perAgent);
		total += parseFloat(perAgent);
	}

	var diff = (remainingPercent - total).toFixed(2);
	if (diff != 0) {
		var id = distributableAgents[0].id;
		let current = parseFloat($('#salesperson' + id + '_per' + id).val());
		$('#salesperson' + id + '_per' + id).val((current + parseFloat(diff)).toFixed(2));
	}
}

function shouldResetToDynamic_11() {
	var totAgents = $('#totagents').val();
	var allFixed = true;
	var addedAgents = 0;

	for (var i = 1; i <= totAgents; i++) {
		if ($("#salesperson" + i).val() != '') {
			addedAgents++;
			if ($('#salesperson' + i + '_fix' + i).val() != '1') {
				allFixed = false;
				break;
			}
		}
	}
	return allFixed && addedAgents > 0;
}

function resetAllAgentsToDynamic() {
	var totAgents = $('#totagents').val();
	var dynamicAgents = 0;

	// First: Reset all to fix = 0
	for (var i = 1; i <= totAgents; i++) {
		if ($("#salesperson" + i).val() != '') {
			$('#salesperson' + i + '_fix' + i).val(0);
			dynamicAgents++;
		}
	}

	// Distribute 100% among all dynamic agents
	if (dynamicAgents === 0) return;

	var perAgent = parseFloat(100 / dynamicAgents).toFixed(2);
	var total = 0;

	for (var i = 1; i <= totAgents; i++) {
		if ($("#salesperson" + i).val() != '') {
			$('#salesperson' + i + '_per' + i).val(perAgent);
			total += parseFloat(perAgent);
		}
	}

	// Adjustment for rounding issue (±0.01)
	var diff = (100 - total).toFixed(2);
	if (diff != 0) {
		for (var i = 1; i <= totAgents; i++) {
			if ($("#salesperson" + i).val() != '') {
				let current = parseFloat($('#salesperson' + i + '_per' + i).val());
				$('#salesperson' + i + '_per' + i).val((current + parseFloat(diff)).toFixed(2));
				break;
			}
		}
	}
}

function deleteSalesCommission_bk(id) {
	if($("#salesperson"+id).val()!='' && $('#salesperson'+id+'_per'+id).val()!='') {
		$("#salesperson"+id).val('');
		$('#salesperson'+id+'_per'+id).val('');
		$('#salesperson'+id+'_fix'+id).val(0);

		var nextSalesId = parseInt(id)+1;
		for(i=nextSalesId;i<6;i++) {
			var resetValueId = parseInt(i)-1;
			$("#salesperson"+resetValueId).val($("#salesperson"+i).val());
			$('#salesperson'+resetValueId+'_per'+resetValueId).val($('#salesperson'+i+'_per'+i).val());
			$('#salesperson'+resetValueId+'_fix'+resetValueId).val($('#salesperson'+i+'_fix'+i).val());

			if(i==5) {
				$("#salesperson"+i).val('');
				$('#salesperson'+i+'_per'+i).val('');
				$('#salesperson'+i+'_fix'+i).val(0);
			}
		}

		var totAgents = parseInt($('#totagents').val())-1;
		$('#totagents').val(totAgents);
		resetCommissionValues();

		var cntAgents = $('#cntagents').val();
		$("#agentDiv"+cntAgents).remove();
		$("#agentDiv"+id).remove();
		$('#cntagents').val(parseInt(cntAgents)-1);
	} else {
		$("#agentDiv"+id).remove();
		var cntAgents = $('#cntagents').val();
		$('#cntagents').val(parseInt(cntAgents)-1);
	}
}

function checkStoreUserAdded(val,id) {
	var totAgents = $('#totagents').val();
	var isAdded = 0;
	for(ms=1;ms<=totAgents;ms++) {
		//if($("#salesperson"+ms).val()==val) {
		if(ms!=id && $("#salesperson"+ms).val()==val) {
			isAdded = 1;
		}
	}
	return isAdded;
}
function get_salesuser(val, id, store_user_id)
{
	//if(checkStoreUserAdded(val)==0) {
	if(checkStoreUserAdded(val,id)==0) {
		$('#salesperson'+id).removeClass('error'); $('#salesperson'+id).addClass('valid');
		$('#salesperson'+id).val(val); $('#salespersonuser'+id).val(store_user_id); $('#search_result'+id).html('');$("#search_result"+id).removeClass('cust-search');
		if($('#salesperson'+id+'_per'+id).val()=='') {
			var totAgents = parseInt($('#totagents').val(), 10)+1;
			$('#totagents').val(totAgents);
		}
		resetCommissionValues();
	} else {
		//$('#salesperson'+id).val(''); $('#search_result'+id).html(''); $("#search_result"+id).removeClass('cust-search'); $("#error_salesperson"+id).html('Salesperson Already Added!');
		$('#salesperson'+id).val(''); $('#search_result'+id).html(''); $("#search_result"+id).removeClass('cust-search'); $("#error_salesperson"+id).html('Salesperson Already Added!').show();
	}
}

function load_salesperson_data(query, id)
{
	//$("#error_salesperson"+id).html('');
	$("#error_salesperson"+id).html('').hide();
	var totagents = $("#totagents").val();
	var no_email = "";
	for(var i = 1; i <= parseInt(totagents); i++){
		if($("#salesperson"+i).length > 0 && $("#salesperson"+i+"_per"+i).val()!=''){
			no_email = no_email +","+$("#salesperson"+i).val();
		}
	}
	if (no_email != '') {
		no_email = no_email.trim().replace(/^,/, '');
	}
	//console.log(no_email);
	if(query.length > 1)
	{
		var STR_POST_VAR = STR_POST_VAR + '&_token='+token;
		STR_POST_VAR = STR_POST_VAR + '&val='+query;
		STR_POST_VAR = STR_POST_VAR + '&no_email='+no_email;
		$.ajax({
			type: 'POST',
			url: site_url+'store/search_salesperson', //site_url+'checkout/salesperson/searchajax',
			dataType: "json",
			data: STR_POST_VAR,
			beforeSend: function()
			{
			},
			success: (function(data, status)
			{

				$('#search_result'+id).empty();

					// If customers are found, display them in the list
					if (data!='') {
					$("#search_result"+id).addClass('cust-search');
					data.storeusers.forEach(function(storeusers) {
						$('#search_result'+id).append('<li data-email="'+storeusers.email+'" data-id="'+storeusers.sales_person_id+'" onclick="get_salesuser(\''+storeusers.email+'\',\''+id+'\',\''+storeusers.sales_person_id+'\')">' + storeusers.name + ' - ' + storeusers.email + '</li>');
					});
					} else {
						$("#search_result"+id).removeClass('cust-search');
					}
				}),
			complete :(function()
			{
			})
		});
	}
	else
	{
		//$("#salesperson"+id+"_per"+id).val("");
		$('#search_result'+id).html('');
	}
}

function AddToCartStore(productID, prodqty, device='')
{
    $("#page-spinner").show();
    var Page = $("main").attr('id');
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			products_id: productID,
			prodqty: prodqty,
			action: 'insert',
			btlform : "store",
			device : device
		},
        success:function(data){

             //$("#skuval").val('');
            if(data.No == 'No')
            {
				$("#show_success_msg").html('');
				$("#show_success_msg").hide();
				$("#show_error_msg").html('<div class="alert alert-danger p-2">Product Not Found.</div>');
				$("#show_error_msg").show();

				$('html, body').animate({
					scrollTop: $('#show_error_msg').offset().top - 100
				}, 500);

				$(".tab-pane.active .frmerror").show();
				$(".tab-pane.active #error_skuval").html("Product Not Found. Invalid Product SKU / UPC Number.");

				$("#page-spinner").hide();
				$(".frmerror").hide();

				return false;
			}
			var ProdExistInCart = 0;
			if(data.exist != undefined)
			{
				ProdExistInCart = data.exist;
			}
			if(data.Added == '0' && ProdExistInCart == 0)
            {
				$("#show_success_msg").html('');
				$("#show_success_msg").hide();
				$("#show_error_msg").html('');

				$(".tab-pane.active .frmerror").show();
				if(data.CartErrors != undefined && data.CartErrors.length > 0)
				{
					$(".tab-pane.active #error_skuval").html(data.CartErrors[0]);
					$("#show_error_msg").append('<div class="alert alert-danger p-2">'+data.CartErrors[0]+'</div>');
				}
				$("#show_error_msg").show();
				$('html, body').animate({
					scrollTop: $('#show_error_msg').offset().top - 100
				}, 500);

				$("#page-spinner").hide();
				$(".frmerror").hide();

				return false;
			} else if(data.Added == '0' && data.CartErrors != undefined && data.CartErrors.length > 0)
			{
				$("#show_success_msg").html('');
				$("#show_success_msg").hide();
				$("#show_error_msg").html('')

				$(".tab-pane.active .frmerror").show();
				if(data.CartErrors != undefined && data.CartErrors.length > 0)
				{
					$(".tab-pane.active #error_skuval").html(data.CartErrors[0]);
					$("#show_error_msg").append('<div class="alert alert-danger p-2">'+data.CartErrors[0]+'</div>');
				}
				$("#show_error_msg").show();
				$('html, body').animate({
					scrollTop: $('#show_error_msg').offset().top - 100
				}, 500);

				$("#page-spinner").hide();
				$(".frmerror").hide();

				return false;
			}
				GetCart();
				/*$('#cart-open').animate({
					right: '0px'
				});
				$('body').toggleClass('slide-open');*/

				var getPage = '';

				if ($('.cart-table').length > 0) {
				$("#page-spinner").show();
					getPage = "Shopcart";
				}

				if (getPage == "Shopcart") {
					GetCartPartial('','','add_to_cart');
					//window.location.reload();
				}
				$("#show_error_msg").html('');
				$("#show_error_msg").hide();

				if(data.Added == '1' || data.exist == '1'){
					$("#show_success_msg").html('<div class="alert alert-success p-2">Product '+productID+' added to cart.</div>');
					$("#show_success_msg").show();
					$('html, body').animate({
						scrollTop: $('#show_success_msg').offset().top - 100
					}, 500);
				}

				$("#page-spinner").hide();
				$(".frmerror").hide();
        }
	});
}

function showDailyOpenPendingPopup()
{
	if($('#daily-open-pending-popup').length == 0)
	{
		$.ajax({
		  type: 'GET',
		  url: site_url + 'store/get-daily-open-pending-popup',
		  cache: true,
		  async: false,
		  dataType: "json",
		  success: (function(data, status) {
			  $('#daily-open-pending-popup').remove();
			  $("body").append(data.html);
			  $('#daily-open-pending-popup').modal('show');
		  }),
		  error: (function(resXhr){
		  })
		});
	}
	else
	{
		$('#daily-open-pending-popup').modal('show');
	}
}

function ShowPaymentMethod() {
    const selectedInput = document.querySelector('input[name="PaymentMethod"]:checked');
    const extraField = document.getElementById('extraField');

    if (!selectedInput) {
        // No radio is selected yet, hide everything safely
        extraField.style.display = "none";
        $('#btnPlaceOrderVal').hide();
        return;
    }

    const selected = selectedInput.value;

    if (selected === "PAYMENT_SPLIT") {
        extraField.style.display = "block";
    } else {
        extraField.style.display = "none";
    }
	$("#card-element").hide();
	$('#btnPlaceOrderVal').hide();
	if(selected === 'PAYMENT_STRIPE_NORMAL')
	{
		$('#btnPlaceOrderVal').show();
		$("#card-element").show();
	}

	 /*if (selected === "PAYMENT_CASH" || selected === "PAYMENT_SPLIT") {
		 showDeviceStatus();
	 }*/

	if(selected === 'PAYMENT_FREEITEM')
	{
		$('#btnPlaceOrderVal').show();
	}

    if (selected === "PAYMENT_CASH" || selected === "PAYMENT_STRIPE") {
		if(selected === "PAYMENT_CASH"){
			if(isshow_dailyopen_pending == '1'){
				showDailyOpenPendingPopup();
				$('#btnPlaceOrderVal').hide();
			}else{
				$('#btnPlaceOrderVal').show();
			}
		}else{
			$('#btnPlaceOrderVal').show();
		}
    }

    if(selected === "PAYMENT_SPLIT"){
		if(isshow_dailyopen_pending == '1'){
			showDailyOpenPendingPopup();
			$('#btnPlaceOrderVal').hide();
		}else{
			$('#btnPlaceOrderVal').show();
		}
	}

    const total = calculateTotal();

    if (total >= maxTotal && selected === "PAYMENT_SPLIT") {
		/*if(isshow_dailyopen_pending == '1'){
			showDailyOpenPendingPopup();
			$('#btnPlaceOrderVal').hide();
		}else{
			$('#btnPlaceOrderVal').show();
		}*/
	}
}

function calculateTotal() {
  let total = 0;

  // Get value from cash field
  const cash = parseFloat($('#CashPayment').val());
  if (!isNaN(cash)) {
    total += cash;
  }

  // Get all credit card textboxes
  $('.dynamic-textbox').each(function () {
    const val = parseFloat($(this).val());
    if (!isNaN(val)) {
      total += val;
    }
  });

  return Number(total.toFixed(2));
}

function updateRemaining() {
  const total = calculateTotal();

  let remaining = maxTotal - total;
  remaining = Number(remaining.toFixed(2));
  $('#remaining-amount').text(remaining);
  if (remaining < 0) {
    $('#error-msg').text('❌ Payment exceeds order total.');
    $('#btnPlaceOrderVal').hide();
    return;
  } else {
    $('#error-msg').text('');
  }

  if (remaining === 0) {
    const $lastInput = $('#textbox-container .textbox-wrapper:last .dynamic-textbox');

    const totalCredit = $('.dynamic-textbox').filter(function () {
      return $(this).prop('readonly');
    }).toArray().reduce((sum, input) => sum + parseFloat(input.value || 0), 0);

	const totalCashCredit = $('#CashPayment').filter(function () {
      return $(this).prop('readonly');
    }).toArray().reduce((sum, input) => sum + parseFloat(input.value || 0), 0);

	 const confirmedTotal = totalCredit + totalCashCredit;
	 const confirmedRemaining = maxTotal - confirmedTotal;

	const isCreditReadonly = $('.dynamic-textbox').filter(function () {
	  return $(this).prop('readonly');
	}).length > 0;

	//const isCreditReadonly = $lastInput.length && $lastInput.prop('readonly');
    const isCashReadonly = $('#CashPayment').length && $('#CashPayment').prop('readonly');

	if (confirmedRemaining === 0 && isCreditReadonly && isCashReadonly) {
      $('#btnPlaceOrderVal').show();
    } else {
      $('#btnPlaceOrderVal').hide();
    }

	if (totalCashCredit >= maxTotal) {
		$('#btnPlaceOrderVal').show();
	}

    if (totalCredit >= maxTotal) {
      $('#btnPlaceOrderVal').show();
      $('.split-cash').hide();
    } else {
      $('.split-cash').show();
    }

    $('.dynamic-textbox').each(function () {
      const $input = $(this);
      const $wrapper = $input.closest('.textbox-wrapper');
      const $confirmBtn = $wrapper.next('.confirm-link');

      if (!$input.prop('readonly') && !$input.val()) {
        $wrapper.hide();
        $confirmBtn.hide();
      }
    });

  } else {
    $('#btnPlaceOrderVal').hide();

    // Show all hidden inputs and their confirm buttons again
    $('.dynamic-textbox').each(function () {
      const $input = $(this);
      const $wrapper = $input.closest('.textbox-wrapper');
      const $confirmBtn = $wrapper.next('.confirm-link');

      $wrapper.show();
      $confirmBtn.show();
    });
  }
}

$('#textbox-container').on('click', '.confirm-link', function (e) {
  e.preventDefault();

  const $input = $('#textbox-container .textbox-wrapper:last .dynamic-textbox');
  const $msgSpan = $(this).siblings('.confirm-msg');
  const val = $input.val()?.trim() || '';
  const numVal = parseFloat(val);
  const $btn = $(this);

  if (val === '' || isNaN(numVal)) {
    $msgSpan.text('❌ Invalid amount').css('color', 'red');
    return;
  }

  // Calculate total including this new input value
  const currentTotal = calculateTotal();

  if (currentTotal > maxTotal) {
    $msgSpan.text(`❌ Total exceeds $${maxTotal}`).css('color', 'red');
    return;
  }
  // Confirm this input
  $msgSpan.text('✅ Confirmed').css('color', 'green');
  $input.prop('readonly', true);

  // Remove current Confirm and Remove buttons

	PaymentProduct(numVal).then(function(res) {
  try {

    if (res === "success") {

      $btn.remove();

      // Add a new textbox if limit not reached
      if (currentTotal < maxTotal) {
        remaining = Number((maxTotal - calculateTotal()).toFixed(2));
        const newTextbox = $(`
          <div>
            <div class="textbox-wrapper form-group mb-0 input-fcs">
              <label for="txtcreditcard" class="col-form-label">CreditCard</label>
              <input id="txtcreditcard" name="txtcreditcard[]" type="text" class="form-control dynamic-textbox" value="${remaining}">
            </div>
            <a href="javascript:void(0)" class="confirm-link btn btn-secondary">Confirm</a>
            <span class="confirm-msg"></span>
          </div>
        `);
        $('#textbox-container').append(newTextbox);
        updateRemaining();
      }

        $msgSpan.text('✅ Payment of $' + numVal + ' has been received').css('color', 'green');
        $input.prop('readonly', true);

		remaining = maxTotal - currentTotal;
		remaining = Number(remaining.toFixed(2));

		if(remaining === 0)
		{
			$('#btnPlaceOrderVal').show();
		}

    }
    else if(res === "cancelSuccess")
     {
		$msgSpan.text('❌ Payment of $' + numVal + ' has been canceled').css('color', 'red');
        $input.prop('readonly', false);
         $('#btnPlaceOrderVal').hide();
	 }
    else if(res === "InvalidError")
      {
		$msgSpan.text('❌ You can not cancel this payment,this payment not processed').css('color', 'red');
        $input.prop('readonly', false);
         $('#btnPlaceOrderVal').hide();
	  }
    else {
      $input.prop('readonly', false);
      $msgSpan.text('❌ Payment failed or declined').css('color', 'red');
       $('#btnPlaceOrderVal').hide();
    }
  }
  catch (err) {
    console.error("Error in PaymentProduct then():", err);
    $input.prop('readonly', false);
    $msgSpan.text('❌ Payment failed or declined').css('color', 'red');
	 $('#btnPlaceOrderVal').hide();
  }
});

		/* $.ajax({
		url: site_url+'process-payment',
		type: 'POST',
		data: { amount: numVal,PaymentType : "CreditCard"  },
		success: function (response) {
		  if (response.status === 'success') {
			$msgSpan.text('✅ ' + response.message).css('color', 'green');

			$input.prop('readonly', true);

		  } else {
			$msgSpan.text('❌ ' + response.message).css('color', 'red');
			alert(response.message);
			window.location=site_url+"checkout/view";
			return false;
		  }
		},
		error: function () {

		  $msgSpan.text('❌ Server error').css('color', 'red');
		  alert("Server error,please try again ");
		  window.location=site_url+"checkout/view";
		  return false;
		}
	});*/

  updateRemaining();
});

$.ajaxSetup({
  headers: {
    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
  }
});
$(document).on('input', '#CashPayment, .dynamic-textbox', function () {

  // Allow only digits and a single decimal point
  let cleaned = this.value.replace(/[^0-9.]/g, '');
  // Ensure only one decimal point
  const parts = cleaned.split('.');
  if (parts.length > 2) {
    cleaned = parts[0] + '.' + parts[1]; // Keep only the first decimal
  }

  // Update the field with cleaned value
  this.value = cleaned;

  // Call the update function
  updateRemaining();
});
function autoFillCreditOnce(remaining) {

    const $credit = $('.dynamic-textbox').filter(function () {
        return !$(this).prop('readonly');
    }).last();

    if ($credit.length) {
        $credit.val(remaining.toFixed(2)).focus();
    }
}

 $(document).ready(function () {
    $('.split-cash').on('click', '#ConfirmLink', function () {

        const $container = $(this).closest('.split-cash');
        const $input = $container.find('.cash-input'); // ✅ class selector
        const $msg = $container.find('.confirm-msg');
        const $confirmBtn = $container.find('#ConfirmLink');
        const $editBtn = $container.find('.edit-link');

        const rawVal = $input.val();
        if (typeof rawVal !== 'string') {
            $msg.text('❌ Cannot read value').css('color', 'red');
            return;
        }

        const cleanedVal = rawVal.trim().replace(/,/g, '');
        const numVal = parseFloat(cleanedVal);

        if (cleanedVal === '' || isNaN(numVal)) {
            $msg.text('❌ Invalid amount').css('color', 'red');
            return;
        }
		 const currentTotal = calculateTotal();

			if (currentTotal > maxTotal) {

			return;
		}

		CheckCurrentToal = calculateReadOnlyTotal();

		remaining = Number((maxTotal - calculateTotal()).toFixed(2));

		autoFillCreditOnce(remaining);

        $input.prop('readonly', true);
        $msg.text('✅ Confirmed').css('color', 'green');

        $confirmBtn.hide();
        $editBtn.show();
        updateRemaining();

        if (CheckCurrentToal < maxTotal) {
    const unconfirmedExists = $('.dynamic-textbox').filter(function () {
        return !$(this).prop('readonly');
    }).length > 0;

    if (!unconfirmedExists && currentTotal < maxTotal) {
        const newTextbox = $(`
          <div>
            <div class="textbox-wrapper form-group mb-0">
              <label for="txtcreditcard" class="col-form-label">CreditCard</label>
              <input id="txtcreditcard" name="txtcreditcard[]" type="text" class="form-control dynamic-textbox">
            </div>
            <a href="javascript:void(0)" class="confirm-link btn btn-secondary">Confirm</a>
            <span class="confirm-msg"></span>
          </div>
        `);

        $('#textbox-container').append(newTextbox);
    }

   // msg = PaymentProduct(rawVal);

    $.ajax({
		url: site_url+'process-payment',
		type: 'POST',
		data: { amount: numVal,PaymentType : "Cash" },
		success: function (response) {
		  if (response.status === 'success') {
			$msg.text('✅ ' + response.message).css('color', 'green');

			$input.prop('readonly', true);

		  } else {
			$msg.text('❌ ' + response.message).css('color', 'red');
			alert(response.message);
			window.location=site_url+"checkout/view";
			return false;

		  }
		},
		error: function () {
		  $msg.text('❌ Server error').css('color', 'red');
		  alert("Server error,please try again ");
		  window.location=site_url+"checkout/view";
		  return false;
		}
	});

}

    });

    $('.split-cash').on('click', '.edit-link', function () {
        const $container = $(this).closest('.split-cash');
        const $input = $container.find('.cash-input');
        const $msg = $container.find('.confirm-msg');
        const $confirmBtn = $container.find('#ConfirmLink');
        const $editBtn = $container.find('.edit-link');
		$input.val('');
        $input.prop('readonly', false);
        $msg.text('');
	    $editableCredit = $('.dynamic-textbox').filter(function () {
			return !$(this).prop('readonly');
		}).last();

		if ($editableCredit.length) {
			$editableCredit.val('');
        }

        $editBtn.hide();
        $confirmBtn.show();
        updateRemaining();

    });
});

let paymentController = null;
let currentPaymentIntentId = null;
let cancelResolver = null;

async function PaymentProduct(TotalAmount = 0) {
  const amount = TotalAmount;
  if (amount <= 0) return false;

  const loader = document.getElementById("loader");
  const cancelBtn = document.getElementById("cancelPaymentBtn");
  loader.style.display = "block";

  paymentController = new AbortController();
  const signal = paymentController.signal;

  const cancelPromise = new Promise((resolve) => (cancelResolver = resolve));

  cancelBtn.onclick = async function () {
    if (paymentController) paymentController.abort(); // Stop payment immediately

    if (currentPaymentIntentId) {
      try {
        const cancelRes = await fetch(site_url + "terminal/cancel-intent", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": token,
          },
          body: JSON.stringify({
            payment_intent_id: currentPaymentIntentId,
            amount: amount,
            PaymentType: "CreditCard",
          }),
        });

        const cancelResponse = await cancelRes.json();
        cancelResolver(cancelResponse.status);
        return;
      } catch {
        cancelResolver(cancelResponse.status);
        return;
      }
    } else {
      cancelResolver("InvalidError");
    }
  };

  try {
    const intentRes = await fetch(site_url + "terminal/pos-create-intent", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": token,
      },
      body: JSON.stringify({ amount, PaymentType: "CreditCard" }),
      signal,
    });

    const intent = await intentRes.json();

    if (intent.status === "success") {
      currentPaymentIntentId = intent.id;

      const readerRes = await fetch(site_url + "terminal/pos-process-intent", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": token,
        },
        body: JSON.stringify({
          payment_intent_id: intent.id,
          amount,
          PaymentType: "CreditCard",
        }),
        signal,
      });

      const reader = await readerRes.json();
      return reader.status === "success" ? "success" : "Error";
    }

    return "Error";

  } catch (error) {
    if (error.name === "AbortError") {
      const cancelStatus = await cancelPromise;
      return cancelStatus; //
    } else {
      console.error("Payment error:", error);
      return "Error";
    }
  } finally {
    loader.style.display = "none";
    paymentController = null;
    currentPaymentIntentId = null;
  }
}
function calculateReadOnlyTotal() {
  let total = 0;

  $cashInput = $('#CashPayment');
  if ($cashInput.length && $cashInput.prop('readonly')) {
    const cashVal = parseFloat($cashInput.val());
    if (!isNaN(cashVal)) {
      total += cashVal;
    }
  }

  $('.dynamic-textbox').each(function () {
    const $input = $(this);
    if ($input.prop('readonly')) {
      const val = parseFloat($input.val());
      if (!isNaN(val)) {
        total += val;
      }
    }
  });

  return total;
}

$('#btnPlaceOrderVal').click(async function(event) {

    const selectedInput = document.querySelector('input[name="PaymentMethod"]:checked');
    const selected = selectedInput ? selectedInput.value : '';

    if (selected === "PAYMENT_STRIPE_NORMAL") {
		event.preventDefault();
        event.stopImmediatePropagation();

        $("#page-spinner").show();
        $(".showDwnMessage").show();

        try {
			if (!isCardComplete) {
			alert('Please enter proper card details.');
			$("#page-spinner").hide();
			$(".showDwnMessage").hide();
			return false;
			}
            // 🔹 Create PaymentIntent
            const res = await fetch(site_url + 'terminal/pos-create-intent-normal', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
				amount: maxTotal,
				})
            });

            const data = await res.json();

            const result = await stripe.confirmCardPayment(
                data.client_secret,
                {
                    payment_method: { card: card }

                }

            );

            if (result.error) {

                event.preventDefault();
				event.stopImmediatePropagation();
				$("#error_orderval").show();
				$("#page-spinner").hide();
				$(".showDwnMessage").hide();
                return false;
            }
            if (result.paymentIntent.status === 'succeeded' && data.client_secret!='') {

               $('<input>')
				  .attr({
					  type: 'hidden',
					  name: 'stripe_payment_id',
					  value: result.paymentIntent.id
				  })
				  .appendTo('#order_process');

				$('<input>')
				  .attr({
					  type: 'hidden',
					  name: 'fullresponse',
					  value: JSON.stringify(result)
				  })
				  .appendTo('#order_process');
                $('#order_process').submit();
            }

        } catch (err) {
			//alert('❌ Stripe error: ' + err.message);
            event.preventDefault();
			event.stopImmediatePropagation();
			$("#error_orderval").show();
		//	window.location.href = site_url + "store/store-dashboard.html?posmsg=1";
			$("#page-spinner").hide();
			$(".showDwnMessage").hide();

            return false;
        }
    }

    // 🔹 YOUR EXISTING CODE – UNTOUCHED
    else if (selected === "PAYMENT_STRIPE") {

        event.preventDefault();
        event.stopImmediatePropagation();

        $("#page-spinner").hide();
        $(".showDwnMessage").hide();

        PaymentProduct(maxTotal).then(function(res) {
            try {
                if (res === "success") {
                    $("#order_process").submit();
                    return true;
                }
                else if (res === "cancelSuccess") {
					$("#page-spinner").hide();
					$("#error_cancel").show();
                   /*setTimeout(function() {
						window.location.href = site_url + "store-payment";
					}, 2000);*/
				}
                else if (res === "InvalidError") {

					$("#page-spinner").hide();
					$("#error_orderval").show();
				   /*setTimeout(function() {
						window.location.href = site_url + "store/store-dashboard.html?posmsg=2";
					}, 2000);*/
                }
                else {
					$("#error_orderval").show();
                    $("#page-spinner").hide();
				   // window.location.href = site_url + "store/store-dashboard.html?posmsg=1";
                }
            } catch (err) {

				$("#error_orderval").show();
                $("#page-spinner").hide();
				//window.location.href = site_url + "store/store-dashboard.html?posmsg=1";
            }
        });
    }

    return false;
});

$("#btnCancelChangeCardReader").click(function(){
	$('#change_cardreader_div').hide();
	var oldSelReader = $("#selcardreader").val();
	$("#sel_cardreader_id").val(oldSelReader);
});

$("#resetReader").click(function(){
	window.location.reload();
});

$("#btnChangeCardReader").click(function(e){
	e.preventDefault();
	var sel_cardreader_id = $('#sel_cardreader_id').find(":selected").val();
	var sel_cardreader_name = $('#sel_cardreader_id').find(":selected").text();
	$(".input-loader").css("display", "block");
	$('#change_cardreader_div').hide();
	$.ajax({
		type: 'POST',
		url: site_url+'store/change-connect-reader',
		headers: {
			'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		},
		datatype: 'JSON',
		data: {
			sel_cardreader_id: sel_cardreader_id,
			sel_cardreader_name: sel_cardreader_name
		},
		success: function(response) {
			//window.location.reload(true);
			$("#divreader").text(sel_cardreader_name);
			$("#status_online").hide();
			$("#status_offline").hide();
			if(response.status == 'ONLINE')
			{
				$("#status_online").text('('+response.status+')');
				$("#status_online").show();
			} else {
				$("#status_offline").text('('+response.status+')');
				$("#status_offline").show();
			}
			$(".input-loader").css("display", "none");
		},
		error: function(xhr) {
			console.error(xhr.responseText);
		}
	});
});

$("#change_cardreader_link").click(function(){
	$('#change_cardreader_div').toggle();
});

$(document).on('click', "#btncreditBill", function () {
	ApplyCreditLimitBilling();
	$('html, body').animate({
		scrollTop: $("body").offset().top
	}, 500);
});
$(document).on('click', "#btnremovecredBill", function () {
	RemoveCreditLimitBilling();
	$('html, body').animate({
		scrollTop: $("body").offset().top
	}, 500);
});
function ApplyCreditLimitBilling() {
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'apply_credit_limit',
		},
		success: function (data) {
			if (data.CartErrors) {
				$("#page-spinner").hide();
			} else {
				window.location.reload();
			}
		}
	});
}
function RemoveCreditLimitBilling() {
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'remove_credit_limit',
		},
		success: function (data) {
			if (data.CartErrors) {
				$("#page-spinner").hide();
			} else {
				window.location.reload();
			}
		}
	});
}

$(document).on('click', '#btnpmnt', function (e) {
    e.preventDefault();
    var isValid = $('#formguest').valid();
    if (!isValid) {
        return false;
    }
    SetAddressPartSKip();
});

function SetAddressPartSKip()
{
	$("#page-spinner").show();
	var token = $('meta[name="csrf-token"]').attr('content');
	var frmdata = Array();
	frmdata = $("#formguest").serializeArray();

	$.ajax({
		type:'POST',
		url:site_url+'skipaddress',
		headers: {
			'X-CSRF-TOKEN': token
		},
		dataType: 'json',
		data:frmdata,
		  success: function (res) {
			console.log(res);
            $("#page-spinner").show();

            if (res.success === '1') {
				  $('#formguest')
					.attr('action', site_url + 'store-payment')
					.off('submit')
					.submit();

			} else {
				 $("#page-spinner").hide();
				alert(res.message || 'Something went wrong');
			}

        },

    });
}
$(document).on('click',".poscart",function(){
	$("#OrderSummaryTogg").trigger("click");
})
$(document).ready(function () {

    if ($("#paytypeID0").is(":checked") &&
        $("#paytypeID0").val() == "PAYMENT_FREEITEM") {

        ShowPaymentMethod();
    }

});
