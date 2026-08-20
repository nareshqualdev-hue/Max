@if($CurrentRoute == 'billing' )
	<script src="https://js.stripe.com/v3/"></script>	
	<script>
		var AppleGPay = '';
		function GetShippingOptions(state='',zip='',country='',city=''){
			//alert(state);
			//alert(zip);
			//alert(country);
			var shippingmodes = [];
			$.ajax({
				type:'POST',
				url:site_url+'shipping',
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				async:false,
				data:{
					'OnlyHead':'0',
					'action':'shippinginfo',
					'subaction' : 'stripecart',
					'state' : state,
					'zip' : zip,
					'country' : country,
					'city'	: city,	
					'Gpay'	  : 'Yes',	
					'FirstStepGpay' : 'FirstStep'
				},
				datatype: 'JSON',
				success:function(data) {
					//alert(data);
					shippingmodes = data;
					SetStripeShippingMethod(data[0].id,state,zip,country,city);
				}
			});
		
			return shippingmodes;
		}
		
		function GetStripeCart()
		{			
			$("#page-spinner").show();
			var items = [];
			var NetTotal = 0;
			$("#ShippingSignInsu").modal('hide');
			$.ajax({
				type:'POST',
				url:site_url+'getstripecart',
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				async:false,
				data:{
					'action' : 'getcart',
				},
				datatype: 'JSON',
				success:function(data) {
					
					if(data=="OutOfStock")
					{
						window.location= site_url+'shoppingcart/view';
						return false;
					}
					
					if(data=="Zero")
					{
						window.location= site_url+'shoppingcart/view';
						return false;
					}
					
					
					items = data.items;
					NetTotal = data.NetTotal;
					$("#page-spinner").hide();
				}
			});
			var CartData = {'items':items,'NetTotal' : NetTotal};
			return CartData;
		}
		
		function SetStripeShippingMethod(ShipMethodID,state='',zip='',country='',city='')
		{
			$.ajax({
				type:'POST',
				url:site_url+'setshipmethod',
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				async:false,
				datatype: 'JSON',
				data:{
					action : 'stripecart',	
					ShipMethodID: ShipMethodID,
					state:state,
					zip:zip,
					country:country,
					city:city
				},
				success:function(data) {	
				},
			});
		}
		function GetClientSecret(AppleGPay,allDetails)
		{
			var clientSecret = "";
			$.ajax({
				type:'POST',
				url:site_url+'getclientsecret',
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				async:false,
				datatype: 'JSON',
				data:{
					action : 'clientsecret',
					AppleGPay : AppleGPay,
					allDetailsVal : allDetails,
					stepfrom : 'firststep',
				},
				success:function(data) {	
					if(data=="OutOfStock")
					{
						//alert("Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment");
						return false;
					}	
					else if(data=="Close")
					{
						//alert("Sorry please try again");
						return false;	
					}
					else if(data=="Guest")
					{
						//alert("Error while processing your order, Please try again");
						return false;
					}	
					else if(data=="SHMethod")
					{
						//alert("PlaceOrderError: Shipping Method Not Selected");
						return false;
					}
					else if(data=="Zero")
					{
						//alert("Please change payment type");
						return false;
					}
					else
					{
						clientSecret = data.clientSecret;
					}
				},
			});
			return clientSecret;
		}
		//pk_test_xWTUgWDaaFSIfHkKClwzQrhS00eb9PQSLX

		$(document).ready(function(){
			var stripe = Stripe('{{env("STRIPE_KEY")}}', {			
				apiVersion: "2019-05-16",				
			});
			var paymentRequest = stripe.paymentRequest({
				country: 'US',
				currency: 'usd',
				total: {
					label: 'Order Total',
					amount: {{round($NetTotal*100)}},
				},
				requestShipping: true,
				requestPayerName: true,
				requestPayerEmail: true,
				requestPayerPhone: true,
				/*shippingOptions: [
					// The first shipping option in this list appears as the default
					// option in the browser payment interface.
					{
					  id: 'free-shipping',
					  label: 'Free shipping',
					  detail: 'Arrives in 5 to 7 days',
					  amount: 0,
					},
				  ],*/
			});
			var elements = stripe.elements();
			
			var prButton = elements.create('paymentRequestButton', {
				paymentRequest: paymentRequest,
				 style: {
					paymentRequestButton: {
					  type: 'default',	// One of 'default', 'book', 'buy', or 'donate'
					  theme: 'dark',	// One of 'dark', 'light', or 'light-outline'
					  height: '45px',	// Defaults to '40px'. The width is always '100%'.
					  //width: '155px'
					},
				  },
			});

			// prButton.on('click',function(ev){
			// 	var CartData = GetStripeCart();
                  
			// 	paymentRequest.update({
			// 		total: {
			// 			label: 'Order Total',
			// 			amount: CartData.NetTotal,
			// 		},
			// 		displayItems : CartData.items,
			// 		// shippingOptions: GetShippingOptions(),
			// 	});
			// })

			// Check the availability of the Payment Request API first.
			paymentRequest.canMakePayment().then(function(result) {
              if (result) {
					// $("#GpayBtn").show();	
					if(result.applePay)
					{
						//$("#GpayBtn").show();
						$("#GpayBtn").html("Apple Pay");
						//AppleGPay = 'A';
					}
					if(result.googlePay)
					{
						//$("#GpayBtn").show();
						$("#GpayBtn").html("Google Pay");
						//AppleGPay = 'G';
					}					
					prButton.mount('#payment-request-button-checkout');
					
					prButton.addEventListener('click', (event) => {
						event.preventDefault();
						$("#GpayBtnn").click();
					});
				} else { 
					$('#payment-request-button-checkout').closest('li').hide();                 
					document.getElementById('payment-request-button-checkout').style.display = 'none';
				}
			});

		});

		$(document).ready(function(){
			var stripe = Stripe('{{env("STRIPE_KEY")}}', {
			//var stripe = Stripe('pk_test_Ht4UumNApNKNPlO0eZVA4rhM00j4ohrYZO', {
				apiVersion: "2019-05-16",	 			
			});			
			
			var paymentRequest = stripe.paymentRequest({
				country: 'US',
				currency: 'usd',
				total: {
					label: 'Order Total',
					amount: {{round($NetTotal*100)}},
				},
				requestShipping: true,
				requestPayerName: true,
				requestPayerEmail: true,
				requestPayerPhone: true,
				/*shippingOptions: [
					// The first shipping option in this list appears as the default
					// option in the browser payment interface.
					{
					  id: 'free-shipping',
					  label: 'Free shipping',
					  detail: 'Arrives in 5 to 7 days',
					  amount: 0,
					},
				  ],*/
			});
			
			
			var elements = stripe.elements();
			
			var prButton = elements.create('paymentRequestButton', {
				paymentRequest: paymentRequest,
				 style: {
					paymentRequestButton: {
					  type: 'default',	// One of 'default', 'book', 'buy', or 'donate'
					  theme: 'dark',	// One of 'dark', 'light', or 'light-outline'
					  height: '45px'	// Defaults to '40px'. The width is always '100%'.
					},
				  },
			});

			prButton.on('click',function(ev){
				var CartData = GetStripeCart();
                  
				paymentRequest.update({
					total: {
						label: 'Order Total',
						amount: CartData.NetTotal,
					},
					displayItems : CartData.items,
					// shippingOptions: GetShippingOptions(),
				});
			})

			// Check the availability of the Payment Request API first.
			paymentRequest.canMakePayment().then(function(result) {
              if (result) {
					// $("#GpayBtn").show();	
					if(result.applePay)
					{
						$("#GpayBtn").show();
						$("#GpayBtn").html("Apple Pay");
						AppleGPay = 'A';
					}
					if(result.googlePay)
					{
						$("#GpayBtn").show();
						$("#GpayBtn").html("Google Pay");
						AppleGPay = 'G';
					}					
					prButton.mount('#payment-request-button');
				} else {                  
					document.getElementById('payment-request-button').style.display = 'none';
				}
			});
			
			paymentRequest.on('shippingoptionchange', function(event) {
				console.log(event);
				var updateWith = event.updateWith;
				var ShipMethodID = event.shippingOption.id;
				//var state = event.shippingAddress.region;
			//	var zip = event.shippingAddress.postalCode;
				//var country = event.shippingAddress.country;
				SetStripeShippingMethod(ShipMethodID);
				var CartData = GetStripeCart();
				updateWith({
					status: 'success',
					total: {
						label: 'Order Total',
						amount: CartData.NetTotal,
					},
					displayItems : CartData.items,
				});	
			});

			paymentRequest.on('shippingaddresschange', async (ev) => {
			  if (ev.shippingAddress.country !== 'US') {
				ev.updateWith({status: 'invalid_shipping_address'});
			  } else {
				var state = ev.shippingAddress.region;
				var zip = ev.shippingAddress.postalCode;
				var country = ev.shippingAddress.country;  
				var city = ev.shippingAddress.city;
				var datas = GetShippingOptions(state,zip,country,city);
				//alert(datas);
				ev.updateWith({
				  status: 'success',
				  shippingOptions: datas,
				});
			  }
			});
			
	let paymentController; 
	paymentRequest.on('paymentmethod', async function (ev) 
	{

    paymentController = new AbortController();
    const signal = paymentController.signal;

    var clientSecret = GetClientSecret(AppleGPay, JSON.stringify(ev));
    if (!clientSecret) {
        window.location = site_url + 'shoppingcart/view';
        return false;
    }

    const alive = await checkServer();
    if (!alive) {
        alert("Server unavailable. Please try again later.");
        try { ev.complete('fail'); } catch (e) { }
        window.location = site_url + 'shoppingcart/view';
        return false;
    }

    try {
        stripe.confirmCardPayment(
            clientSecret,
            { payment_method: ev.paymentMethod.id },
            { handleActions: false }
        ).then(function (confirmResult) {
            $("#dd123").val(confirmResult + "\n\n\n" + ev);
            addApplePayLog("confirm_result_payment_method", confirmResult, "", '<?=$CurrentRoute?>');
            if (confirmResult.error) {
                alert('Your payment has failed. Please try again or use different payment option.');
                console.log('Could not confirm result: ', confirmResult.error);
                ev.complete('fail');
                window.location = site_url + 'shoppingcart/view';
                return false;
            } else {
                addApplePayLog("confirm_result_payment_method_2", confirmResult, "", '<?=$CurrentRoute?>');
                console.log("confirmResult==", confirmResult);
                ev.complete('success');

                if (confirmResult.paymentIntent.status === "requires_action") {
                    addApplePayLog("confirm_result_payment_require_action", confirmResult, "", '<?=$CurrentRoute?>');
                    stripe.confirmCardPayment(clientSecret).then(function (result) {
                        if (result.error) {
                            addApplePayLog("confirm_result_payment_require_action_error", result, "", '<?=$CurrentRoute?>');
                            alert('Your payment has failed. Please try again or use different payment option.');
                            ev.complete('fail');
                            window.location = site_url + 'shoppingcart/view';
                            return false;
                        } else {
                            $.ajax({
                                type: 'POST',
                                url: site_url + 'stripebtnres',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                datatype: 'JSON',
                                data: {
                                    'methodName': ev.paymentMethod.payment_method_types,
                                    'payerEmail': ev.payerEmail,
                                    'payerName': ev.payerName,
                                    'paymentMethod': ev.paymentMethod.id,
                                    'shippingOption': ev.shippingOption,
                                    'shippingAddress': ev.shippingAddress,
                                    'stepfrom': 'firststep',
                                    'payerPhone': ev.payerPhone
                                },
                                signal: signal, 
                                success: function (data) {
                                    if (data.status == 'success') {
                                        if ($("#shipping_signature").prop('checked'))
                                            $("#shipsignatureflag").val('Yes');
                                        else
                                            $("#shipsignatureflag").val('No');

                                        $("#is_stripe_wallet").val("google_pay");
                                        $("#is_stripe_applepay").val("apple_pay");
                                        window.location.href = site_url + 'order-receipt';
                                    } else {
                                        addApplePayLog("confirm_result_payment_failed", data, "", '<?=$CurrentRoute?>');
                                        alert('Your payment has failed. Please try again or use different payment option.');
                                        ev.complete('fail');
                                        window.location = site_url + 'shoppingcart/view';
                                        return false;
                                    }
                                },
                                error: function (xhr, status, error) {
                                    console.error("AJAX error:", error);
                                    ev.complete('fail');
                                    window.location = site_url + 'shoppingcart/view';
                                }
                            });
                        }
                    });
                } else {
                    $.ajax({
                        type: 'POST',
                        url: site_url + 'stripebtnres',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        datatype: 'JSON',
                        data: {
                            'methodName': ev.paymentMethod.payment_method_types,
                            'payerEmail': ev.payerEmail,
                            'payerName': ev.payerName,
                            'paymentMethod': ev.paymentMethod.id,
                            'shippingOption': ev.shippingOption,
                            'shippingAddress': ev.shippingAddress,
                            'stepfrom': 'firststep',
                            'payerPhone': ev.payerPhone
                        },
                        signal: signal, 
                        success: function (data) {
                            if (data.status == 'success') {
                                if ($("#shipping_signature").prop('checked'))
                                    $("#shipsignatureflag").val('Yes');
                                else
                                    $("#shipsignatureflag").val('No');

                                $("#is_stripe_wallet").val("google_pay");
                                $("#is_stripe_applepay").val("apple_pay");
                                window.location.href = site_url + 'order-receipt';
                            } else {
                                addApplePayLog("confirm_result_payment_failed_2", data, "", '<?=$CurrentRoute?>');
                                alert('Your payment has failed. Please try again or use different payment option.');
                                ev.complete('fail');
                                window.location = site_url + 'shoppingcart/view';
                                return false;
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error("AJAX error:", error);
                            ev.complete('fail');
                            window.location = site_url + 'shoppingcart/view';
                        }
                    });
                }
            }
        });
    } catch (err) {
        $.ajax({
		type: 'POST',
		url: site_url + 'stripebtnres',
		headers: {
			'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		},
		datatype: 'JSON',
		data: {
			'methodName': ev.paymentMethod.payment_method_types,
			'payerEmail': ev.payerEmail,
			'payerName': ev.payerName,
			'paymentMethod': ev.paymentMethod.id,
			'shippingOption': ev.shippingOption,
			'shippingAddress': ev.shippingAddress,
			'stepfrom': 'firststep',
			'payerPhone': ev.payerPhone,
			'isAbort' : 'Yes'
		},
		success: function (data) {
			if (data.status == 'success') {
				if ($("#shipping_signature").prop('checked'))
					$("#shipsignatureflag").val('Yes');
				else
					$("#shipsignatureflag").val('No');

				$("#is_stripe_wallet").val("google_pay");
				$("#is_stripe_applepay").val("apple_pay");
				window.location.href = site_url + 'order-receipt';
			} else {
				addApplePayLog("confirm_result_payment_failed", data, "", '<?=$CurrentRoute?>');
				alert('Your payment has failed. Please try again or use different payment option.');
				ev.complete('fail');
				window.location = site_url + 'shoppingcart/view';
				return false;
			}
		},
		error: function (xhr, status, error) {
			console.error("AJAX error:", error);
			ev.complete('fail');
			window.location = site_url + 'shoppingcart/view';
		}
		});
    }
});

});
				
	</script>	
@endif
@if($CurrentRoute == 'billing-payment')
	<script src="https://js.stripe.com/v3/"></script>	
	<script>
		var AppleGPay = 'G';
		function SetStripeShippingMethod(ShipMethodID,state='',zip='',country='')
		{
			$.ajax({
				type:'POST',
				url:site_url+'setshipmethod',
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				async:false,
				datatype: 'JSON',
				data:{
					action : 'stripecart',	
					ShipMethodID: ShipMethodID,
					state:state,
					isLastNew : 'Yes',
					zip:zip,
					country:country
				},
				success:function(data) {	
				},
			});
		}
		
		function GetClientSecret(AppleGPay,allDetailsVal)
		{
			var clientSecret = "";
			$.ajax({
				type:'POST',
				url:site_url+'getclientsecret',
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				async:false,
				datatype: 'JSON',
				data:{
					action : 'clientsecret',
					AppleGPay : AppleGPay,
					isLastNew : 'Yes',
					allDetailsVal : allDetailsVal
				},
				success:function(data) {
					if(data=="OutOfStock")
					{
						//alert("Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment");
						return false;
					}	
					else if(data=="Close")
					{
						//alert("Sorry please try again");
						return false;	
					}
					else if(data=="Guest")
					{
						//alert("Error while processing your order, Please try again");
						return false;
					}	
					else if(data=="SHMethod")
					{
						//alert("PlaceOrderError: Shipping Method Not Selected");
						return false;
					}
					else if(data=="Zero")
					{
						//alert("Please change payment type");
						return false;
					}
					else
					{
						clientSecret = data.clientSecret;
					}
				},
			});
			return clientSecret;
		}
		
		function GetStripeCart()
		{
			$("#page-spinner").show();
			var items = [];
			var NetTotal = 0;
			
			$.ajax({
				type:'POST',
				url:site_url+'getstripecart',
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				async:false,
				data:{
					'action' : 'getcart',
					'isLast' : 'Yes',
				},
				datatype: 'JSON',
				success:function(data) {
					if(data=="OutOfStock")
					{
						window.location= site_url+'shoppingcart/view';
						return false;
					}
					if(data=="Zero")
					{
						window.location= site_url+'shoppingcart/view';
						return false;
					}
					items = data.items;
					NetTotal = data.NetTotal;
					$("#page-spinner").hide();
				}
			});
			var CartData = {'items':items,'NetTotal' : NetTotal};
			return CartData;
		}
		
		$(document).ready(function(){
			var stripe = Stripe('{{env("STRIPE_KEY")}}', {
			//var stripe = Stripe('pk_test_Ht4UumNApNKNPlO0eZVA4rhM00j4ohrYZO', {
				apiVersion: "2019-05-16",
			});
			
			
			var paymentRequest = stripe.paymentRequest({
				country: 'US',
				currency: 'usd',
				total: {
					label: 'Order Total',
					amount: {{round($NetTotal*100)}},
				},
				//requestShipping: true,
				requestPayerName: true,
				requestPayerEmail: true,
				requestPayerPhone: true,
			});
			
			
			var elements = stripe.elements();
			
			var prButton = elements.create('paymentRequestButton', {
				paymentRequest: paymentRequest,
				 style: {
					paymentRequestButton: {
					  type: 'default',	// One of 'default', 'book', 'buy', or 'donate'
					  theme: 'dark',	// One of 'dark', 'light', or 'light-outline'
					  height: '45px'	// Defaults to '40px'. The width is always '100%'.
					},
				  },
			});

			prButton.on('click',function(ev){
				var CartData = GetStripeCart();
                  
				paymentRequest.update({
					total: {
						label: 'Order Total',
						amount: CartData.NetTotal,
					},
					displayItems : CartData.items,
					// shippingOptions: GetShippingOptions(),
				});
			})

			// Check the availability of the Payment Request API first.
			paymentRequest.canMakePayment().then(function(result) {
              if (result) {
					if(result.applePay)
					{						
						AppleGPay = 'A';
					}
					if(result.googlePay)
					{						
						AppleGPay = 'G';
					}
					prButton.mount('#payment-request-button');
				} else {
                  
					document.getElementById('payment-request-button').style.display = 'none';
				}
			});
			
			paymentRequest.on('shippingoptionchange', function(event) {
				console.log(event);
				var updateWith = event.updateWith;
				var ShipMethodID = event.shippingOption.id;
				SetStripeShippingMethod(ShipMethodID);
				var CartData = GetStripeCart();
				updateWith({
					status: 'success',
					total: {
						label: 'Order Total',
						amount: CartData.NetTotal,
					},
					displayItems : CartData.items,
				});	
			});
			paymentRequest.on('shippingaddresschange', function(ev) {
				var updateWith = ev.updateWith;
				
				if (ev.shippingAddress.country !== 'US') {
					ev.updateWith({status: 'invalid_shipping_address'});
				} else {
					var state = ev.shippingAddress.region;
					var zip = ev.shippingAddress.postalCode;
					var country = ev.shippingAddress.country;
					updateWith({
						status: 'success',
						//shippingOptions: GetShippingOptions(state,zip,country),
					});
				}
			});

		let paymentController; 

paymentRequest.on('paymentmethod', async function (ev) 
{

    paymentController = new AbortController();
    const signal = paymentController.signal;

    var clientSecret = GetClientSecret(AppleGPay, JSON.stringify(ev));

    if (!clientSecret) {
        window.location = site_url + 'shoppingcart/view';
        return false;
    }

    const alive = await checkServer();
    if (!alive) {
        alert("Server unavailable. Please try again later.");
        try { ev.complete('fail'); } catch (e) { }
        window.location = site_url + 'shoppingcart/view';
        return false;
    }

    try {
        // Confirm the PaymentIntent without handling potential next actions (yet).
        stripe.confirmCardPayment(
            clientSecret,
            { payment_method: ev.paymentMethod.id },
            { handleActions: false }
        ).then(function (confirmResult) {
            $("#dd123").val(confirmResult + "\n\n\n" + ev);
            addApplePayLog("confirm_result_payment_method_last_step", confirmResult, "", '<?=$CurrentRoute?>');
            if (confirmResult.error) {
                alert('Your payment has failed. Please try again or use different payment option.');
                console.log('Could not confirm result: ', confirmResult.error);
                ev.complete('fail');
                window.location = site_url + 'shoppingcart/view';
                return false;
            } else {
                addApplePayLog("confirm_result_payment_method_last_step_2", confirmResult, "", '<?=$CurrentRoute?>');
                console.log("confirmResult==", confirmResult);
                ev.complete('success');

                if (confirmResult.paymentIntent.status === "requires_action") {
                    addApplePayLog("confirm_result_payment_method_last_step_require_action", confirmResult, "", '<?=$CurrentRoute?>');
                    // Let Stripe.js handle the rest of the payment flow.
                    stripe.confirmCardPayment(clientSecret).then(function (result) {
                        if (result.error) {
                            addApplePayLog("confirm_result_payment_method_last_step_require_action_error", result, "", '<?=$CurrentRoute?>');
                            alert('Your payment has failed. Please try again or use different payment option.');
                            ev.complete('fail');
                            window.location = site_url + 'shoppingcart/view';
                            return false;
                        } else {
                            $.ajax({
                                type: 'POST',
                                url: site_url + 'stripebtnres',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                datatype: 'JSON',
                                data: {
                                    'methodName': ev.paymentMethod.payment_method_types,
                                    'payerEmail': ev.payerEmail,
                                    'payerName': ev.payerName,
                                    'paymentMethod': ev.paymentMethod.id,
                                    'shippingOption': ev.shippingOption,
                                    'shippingAddress': ev.shippingAddress,
                                    'stepfrom': 'laststep',
                                    'payerPhone': ev.payerPhone
                                },
                                signal: signal,
                                success: function (data) {

                                    if (data.status == 'success') {
                                        $("#is_stripe_wallet").val("google_pay");
                                        $("#is_stripe_applepay").val("apple_pay");
                                        window.location.href = site_url + 'order-receipt';
                                    } else {
                                        addApplePayLog("confirm_result_payment_method_last_step_failed", data, "", '<?=$CurrentRoute?>');
                                        alert('Your payment has failed. Please try again or use different payment option.');
                                        window.location = site_url + 'shoppingcart/view';
                                        return false;
                                    }
                                },
                                error: function (xhr, status, error) {
                                    console.error("AJAX error:", error);
                                    ev.complete('fail');
                                    window.location = site_url + 'shoppingcart/view';
                                }
                            });
                        }
                    });
                } else {
                    $.ajax({
                        type: 'POST',
                        url: site_url + 'stripebtnres',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        datatype: 'JSON',
                        data: {
                            'methodName': ev.paymentMethod.payment_method_types,
                            'payerEmail': ev.payerEmail,
                            'payerName': ev.payerName,
                            'paymentMethod': ev.paymentMethod.id,
                            'shippingOption': ev.shippingOption,
                            'shippingAddress': ev.shippingAddress,
                            'stepfrom': 'laststep',
                            'payerPhone': ev.payerPhone,
                        },
                        signal: signal, 
                        success: function (data) {

                            if (data.status == 'success') {
                                $("#is_stripe_wallet").val("google_pay");
                                $("#is_stripe_applepay").val("apple_pay");
                                window.location.href = site_url + 'order-receipt';
                            } else {
                                addApplePayLog("confirm_result_payment_method_last_step_failed_2", data, "", '<?=$CurrentRoute?>');
                                alert('Your payment has failed. Please try again or use different payment option.');
                                window.location = site_url + 'shoppingcart/view';
                                return false;
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error("AJAX error:", error);
                            ev.complete('fail');
                            window.location = site_url + 'shoppingcart/view';
                        }
                    });
                }
            }
        });
    } catch (err) {
         $.ajax({
			type: 'POST',
			url: site_url + 'stripebtnres',
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			datatype: 'JSON',
			data: {
				'methodName': ev.paymentMethod.payment_method_types,
				'payerEmail': ev.payerEmail,
				'payerName': ev.payerName,
				'paymentMethod': ev.paymentMethod.id,
				'shippingOption': ev.shippingOption,
				'shippingAddress': ev.shippingAddress,
				'stepfrom': 'laststep',
				'payerPhone': ev.payerPhone,
				'isAbort' : 'Yes'
			},
			success: function (data) {

				if (data.status == 'success') {
					$("#is_stripe_wallet").val("google_pay");
					$("#is_stripe_applepay").val("apple_pay");
					window.location.href = site_url + 'order-receipt';
				} else {
					addApplePayLog("confirm_result_payment_method_last_step_failed", data, "", '<?=$CurrentRoute?>');
					alert('Your payment has failed. Please try again or use different payment option.');
					window.location = site_url + 'shoppingcart/view';
					return false;
				}
			},
			error: function (xhr, status, error) {
				console.error("AJAX error:", error);
				ev.complete('fail');
				window.location = site_url + 'shoppingcart/view';
			}
		});
    }
});

});
				
	</script>	
@endif
<script>
	function addApplePayLog(logaction,data,actions,routenmnew)	{
    var res = "";
    $.ajax({
        type:'POST',
        url:site_url+'applepaylogupdate',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data:{
			logaction : logaction,
            log_data : JSON.stringify(data),
            log_actions : JSON.stringify(actions),
            rtnm : routenmnew
		},
        async:false,
        datatype: 'JSON',
        success:function(data) {
            res= data
        },
        
    });
}
async function checkServer() {
        try {
            const res = await $.ajax({
                type: 'POST',
                url: site_url + 'servercheck',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                dataType: 'json',
                timeout: 4000
            });
            return res && res.status === 'ok';
        } catch (e) {
            return false;
        }
    }

</script>
