@if($CurrentRoute == 'proddetails')
<!-- sb <script src="https://www.paypal.com/sdk/js?client-id=AQymJLkSRgzhHf0AjiYOGL_OHQZ60bCggeySkd8F31n_2ery6HK7HXYQGeeBCfszGgAin8XfJbvZuByn&components=messages,buttons,funding-eligibility&commit=false&disable-funding=card"></script> -->
<script src="https://www.paypal.com/sdk/js?client-id=ATtuGj0FvDJqZDzPKYnfx13ovzfCod0CF_L-V87mX8Lnm0SV32vycXfwJHriS41I6YyCd5tBfxQ1dA8j&components=messages,buttons,funding-eligibility&commit=false&disable-funding=card"></script>
<script>
function calculatePrice(quantity) {
    if($('#sp_price_range').length > 0){
        const priceRanges = JSON.parse($('#sp_price_range').val());
        for (const range of priceRanges) {
            const min = parseInt(range.min, 10);
            const max = parseInt(range.max, 10);            
            if ((quantity >= min) && (max === 0 || quantity <= max)) {
                return parseFloat(range.price);
            }
        }
    }    
    return $("#prod_pro_price").val().trim().replace("$",""); // Default price if no match found
}

var shippingmodes = [];
function getShippingInfo(state='',zip='',country=''){
    var productID = $(".addtocart").attr('data-product');
    var prodqty = $("#prodqty").val();   
    var prod_price = calculatePrice(prodqty); //$("#show_sort_priceDiv strong.prodprice").text().trim().replace("$",""); //$("#js_prod_price").val();
    var total_price = parseFloat(prodqty) * parseFloat(prod_price);
    // alert(state);
    // alert(zip);
    // alert(country);
    $.ajax({
        type:'POST',
        url:site_url+'shippingpaypal',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        async:false,
        data:{
            'OnlyHead':'0',
            'action':'shippinginfo',
            'subaction' : 'paypalproductpage', //'stripecart',
            'state' : state,
            'zip' : zip,
            'country' : country,
            'Gpay'	  : 'Yes',
            'FirstStepGpay' : 'FirstStep',
            'paypal_productid' : productID,
            'paypal_prodqty' : prodqty,
            'paypal_prod_price' : prod_price,
            'paypal_total_price' : total_price

        },
        datatype: 'JSON',
        success:function(data) {
            // alert('shippingOptions');
            // console.log('shippingOptions');
            // console.log(data);
            shippingmodes = data;            
        }
    });
    return shippingmodes;
}

async function create_paypal_order(payer_email, payer_address1, payer_state, payer_city, payer_country, payer_postcode, order_details){
    var create_paypal_buynoworder = '{{url('paypalbuynoworder')}}';
    //return 0;
    var prod_qty = $("#prodqty").val();
    var prod_price = calculatePrice(prod_qty); //$("#prod_pro_price").val().trim().replace("$","");
    var total_price = parseFloat(prod_qty) * parseFloat(prod_price);
    var productID = $(".addtocart").attr('data-product');  
    var product_sku = $("#detail_prod_sku").text();

    return new Promise((resolve, reject) => {
        $.ajax({
            type: 'POST',
            url: create_paypal_buynoworder,
            datatype: 'JSON',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                prod_qty : prod_qty,
                product_id : productID,
                prod_price : prod_price,
                product_sku : product_sku,
                total_price: total_price,
                payer_email:payer_email,
                payer_address1 : payer_address1,
                payer_state : payer_state,
                payer_city : payer_city,
                payer_country : payer_country,
                payer_postcode : payer_postcode,
                order_details : order_details
            },
            success: function (data) {
                resolve(data); // Resolve the promise with the data
            },
            error: function (xhr, status, error) {
                reject(error); // Reject the promise on error
            }
        });
    });
}


async function setPayPal(){
    var CREATE_CAPTUREPAYMENT_URL  = '{{url('placepaypalorder')}}';
    var updatePayPalOrder = '{{url('update_paypal_order')}}';
    var chkProductStock_url = '{{url('check_product_stock')}}';
    var updateShippingOption_url = '{{url('update_paypal_order_shipping_option')}}';

    var ref_id = 0; //"order_"+Date.now();
    var paypalShippingAddress = {};
    paypal.Buttons({
            style: {
            layout: 'vertical',
            color:  'gold',
            shape:  'rect',
            label:  'paypal',
            tagline :false
            },
            onInit(data, actions) {                                       
            },
            onClick: function() {
                //AddToCart($(".addtocart").attr('data-product'), $("#prodqty").val());
                //getShippingInfo('NY','10018','US');                            
            },
            createOrder: async function(data, actions) {
                //console.log("product price is "+$("#show_sort_priceDiv strong.prodprice").text().trim().replace("$",""));
                var productID = $(".addtocart").attr('data-product');
                var prodqty = $("#prodqty").val();   
                //var prod_price = $("#show_sort_priceDiv strong.prodprice").text().trim().replace("$",""); //$("#js_prod_price").val();
                var prod_price = calculatePrice(prodqty);  //$("#prod_pro_price").val().trim().replace("$","");
                
                var total_price = parseFloat(prodqty) * parseFloat(prod_price);
                var tax_amount = parseFloat("0.00");// parseFloat("10.00");
                var full_amount = total_price + tax_amount;
                
                //ref_id = await create_paypal_order();

                return actions.order.create({
                    purchase_units: [{
                        invoice_id: 'INV-' + Date.now() + '-' + Math.floor(Math.random() * 100000), 
                        amount: {
                            value: full_amount.toFixed(2),
                            breakdown: {
                                item_total: {
                                    currency_code: 'USD',
                                    value: total_price.toFixed(2)
                                },
                                tax_total: {
                                    currency_code: 'USD',
                                    value: tax_amount
                                }
                            }
                        },
                        currency:"USD",
                        name:{
                            full_name : ""
                        },
                        items: [{
                            name: $(".bottomLine").attr("data-name"),//'Paco Rabanne',
                            quantity: prodqty, //'1',
                            url: $(".bottomLine").attr("data-url"),
                            unit_amount: {
                                currency_code: 'USD',
                                value: prod_price
                            },
                            sku: $("#detail_prod_sku").text(),//'UP3349668597963',                        
                            category: 'PHYSICAL_GOODS',
                            tax : {
                                currency_code: 'USD',
                                value: tax_amount
                            },
                        }],
                        shipping:{
                            options : shippingmodes.length > 0 ? shippingmodes : null                        
                        }
                    }]
                });
            },
            onShippingOptionsChange(data, actions) {

                    var selectedShippingOption = data.selectedShippingOption || {};
                    var shippingOptions = data.shippingOptions || [];

                    return fetch(updateShippingOption_url, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify({
                            orderID: data.orderID,
                            
                            ship_info: selectedShippingOption,
                            selectedShippingOption: selectedShippingOption,

                            shippingAddress: paypalShippingAddress,

                            shipping_city: paypalShippingAddress.city || '',
                            shipping_countrycode: paypalShippingAddress.countryCode || '',
                            shipping_postalcode: paypalShippingAddress.postalCode || '',
                            shipping_state: paypalShippingAddress.state || '',
                            
                            shipping_options: shippingOptions,
                            
                            prod_price: calculatePrice($("#prodqty").val()),
                            prod_qty: $("#prodqty").val(),
                            prod_id: $(".addtocart").attr('data-product'),
                            product_sku: $("#detail_prod_sku").text()
                        })
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(res) {

                        console.log("Shipping option update response:", res);

                        if (res.status === "success") {
                            return Promise.resolve();
                        }

                        console.error("Shipping option update failed:", res);
                        return Promise.reject();
                    })
                    .catch(function(error) {
                        console.error("PayPal shipping option update error:", error);
                        return Promise.reject();
                    });
                },                        
                       
            onShippingAddressChange(data, actions) {             
                // if (data.shippingAddress.countryCode !== "US") {
                //     return actions.reject(data.errors.COUNTRY_ERROR);
                // }else {
                    paypalShippingAddress = data.shippingAddress || {};
                    return fetch(updatePayPalOrder,{
                        method:"PATCH",
                        headers: {
                            "Content-Type": "application/json",
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify({
                            orderID: data.orderID,
                            shippingAddress: data.shippingAddress,
                            shipping_city : data.shippingAddress.city,
                            shipping_countrycode : data.shippingAddress.countryCode,
                            shipping_postalcode : data.shippingAddress.postalCode,
                            shipping_state : data.shippingAddress.state,
                            shipping_info : getShippingInfo(data.shippingAddress.state,data.shippingAddress.postalCode,data.shippingAddress.countryCode),
                            ref_id : ref_id,
                            prod_price : calculatePrice($("#prodqty").val()),  //$("#prod_pro_price").val().trim().replace("$",""), //$("#show_sort_priceDiv strong.prodprice").text().trim().replace("$",""), //$("#js_prod_price").val(),
                            prod_qty : $("#prodqty").val(),
                            prod_id : $(".addtocart").attr('data-product')
                        })
                    })
                //}
            },
            onCancel(data) {
                // Show a cancel page, or return to cart
                //window.location.assign("/your-cancel-page");
                ////AddToCart($(".addtocart").attr('data-product'), $("#prodqty").val());
                //window.location.assign(site_url+'shoppingcart');            
            },
            onApprove: async function(data, actions) {
                var routenmnew = '<?php echo Route::currentRouteName();?>';
                addPayPalLog("onApprove",data,actions,routenmnew);
                var payer_email = "";
                var payer_address1 = "";
                var payer_state = "";
                var payer_city = "";
                var payer_country = "";
                var payer_postcode = "";
                //return actions.order.get().then(function (details) {
                    const details = await actions.order.get();
                    payer_address1 = details.purchase_units[0].shipping.address.address_line_1;
                    payer_state = details.purchase_units[0].shipping.address.admin_area_1;
                    payer_city = details.purchase_units[0].shipping.address.admin_area_2;
                    payer_country = details.purchase_units[0].shipping.address.country_code;
                    payer_postcode = details.purchase_units[0].shipping.address.postal_code;

                    payer_email = details.payer.email_address;

                    ref_id = await create_paypal_order(payer_email,payer_address1, payer_state, payer_city, payer_country, payer_postcode, JSON.stringify(details));
                    if(ref_id == 'Blocked' || ref_id == 'WholeSaleMinOrder'){
                        window.location.reload();                        
                    }

                    const alive = await checkServerPayPal();
                    if (!alive) {
                        alert("Server unavailable. Please try again later.");
                        window.location.reload();                        
                        return false;
                    }

                    var capture_details = await UpdatePaypalOrderResponsePDP(JSON.stringify(details), JSON.stringify(ref_id), routenmnew);
                    var obj = JSON.parse(capture_details);
                    
                    var order_data = obj.detailsRes;

                    const controller = new AbortController();
					const signal = controller.signal;
                    fetch(CREATE_CAPTUREPAYMENT_URL, {
                        method: 'POST',
                        credentials: 'include', 
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify({
                            product_sku : $("#detail_prod_sku").text(),
                            product_id : $(".addtocart").attr('data-product'),
                            prod_price : calculatePrice($("#prodqty").val()),
                            prod_qty : $("#prodqty").val(),
                            ref_id : ref_id,
                            orderData: order_data
                        }),
                        signal: signal 
                    })
                    .then(response => response.json())
                    .then(data => {     
                        if(data.status == 'success'){
                            window.location.href = site_url + 'order-receipt?order='+data.oid;
                        } else {
                            window.location.reload();
                        }                       
                    })
                    .catch((error) => {   
                        if (error.name === 'AbortError') {
                            alert('Payment request aborted. Server might be down.');
                        } else {
                            alert('Error:', error);
                        }                        
                        if(resval.status == 'OrderReceiptSuceess'){
                            window.location.href = site_url + 'order-receipt?order='+resval.oid;
                            return;
                        } else {
                            window.location.reload();
                            return;
                        }
                        window.location.reload();
                    });
                
                    // return actions.order.capture().then(async function(order_data) {                
                        
                    //     const controller = new AbortController();
					// 	const signal = controller.signal;

                    //     await UpdatePaypalOrderResponsePDP(JSON.stringify(order_data), JSON.stringify(ref_id), routenmnew);
                    //     fetch(CREATE_CAPTUREPAYMENT_URL, {
                    //         method: 'POST',
                    //         credentials: 'include', 
                    //         headers: {
                    //             'Content-Type': 'application/json',
                    //             'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    //         },
                    //         body: JSON.stringify({
                    //             product_sku : $("#detail_prod_sku").text(),
                    //             product_id : $(".addtocart").attr('data-product'),
                    //             prod_price : calculatePrice($("#prodqty").val()), //$("#prod_pro_price").val().trim().replace("$",""), //$("#show_sort_priceDiv strong.prodprice").text().trim().replace("$",""), //$("#js_prod_price").val(),
                    //             prod_qty : $("#prodqty").val(),
                    //             ref_id : ref_id,
                    //             orderData: order_data                        
                    //         }),
                    //         signal: signal 
                    //     })
                    //     .then(response => response.json())
                    //     .then(data => {     
                    //         // alert(site_url);
                    //         // alert(data.status); 
                    //         // alert(data);
                    //         if(data.status == 'success'){
                    //             window.location.href = site_url + 'order-receipt?order='+data.oid;
                    //         } else {
                    //             window.location.reload();
                    //         }                         
                    //     })
                    //     .catch((error) => { 
					// 		if (error.name === 'AbortError') {
					// 			alert('Payment request aborted. Server might be down.');
					// 		} else {
					// 			alert('Error:', error);
					// 		}                       
                    //         console.error('Error:', error);
                    //         addPayPalLog("OrderCaptureCatchPDP", "Error--" + JSON.stringify(error) + "--Details--" + JSON.stringify(order_data), actions, routenmnew);
                    //         resval =UpdatePaypalOrderResponsePDP(JSON.stringify(order_data), JSON.stringify(ref_id), routenmnew,"isAbort");
                    //         if(resval.status == 'OrderReceiptSuceess'){
					// 			window.location.href = site_url + 'order-receipt?order='+resval.oid;
                    //             return;
					// 		} else {
					// 			window.location.reload();
                    //             return;
					// 		}
					// 		window.location.reload();
                    //     });
                    // });

                //});    
                
                //ref_id = await create_paypal_order();
                
                // return actions.order.capture().then(function(order_data) {                
                //     fetch(CREATE_CAPTUREPAYMENT_URL, {
                //         method: 'POST',
                //         credentials: 'include', 
                //         headers: {
                //             'Content-Type': 'application/json',
                //             'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                //         },
                //         body: JSON.stringify({
                //             product_sku : $("#detail_prod_sku").text(),
                //             product_id : $(".addtocart").attr('data-product'),
                //             prod_price : $("#prod_pro_price").val().trim().replace("$",""), //$("#show_sort_priceDiv strong.prodprice").text().trim().replace("$",""), //$("#js_prod_price").val(),
                //             prod_qty : $("#prodqty").val(),
                //             ref_id : ref_id,
                //             orderData: order_data                        
                //         })
                //     })
                //     .then(response => response.json())
                //     .then(data => {                                       
                //         if(data.status == 'success'){
                //             window.location.href = site_url + 'order-receipt?order='+data.oid;
                //         }                        
                //     })
                //     .catch((error) => {                        
                //         console.error('Error:', error);
                //     });
                // });

                // console.log('approve');
                // console.log(data);
                // console.log(actions);                
            }
    }).render('#paypal-button-container');
}
setPayPal();
</script>
@else
<script type="text/javascript">
var routenmnew = '<?php echo Route::currentRouteName();?>';
</script>
@if(isset($CartAttr['IsPaypalExpressCheckout']) && $CartAttr['IsPaypalExpressCheckout'] == 'Yes' && ($CurrentRoute=='billing' || $CurrentRoute=='billing-payment'))			
<!-- <script src="https://www.paypal.com/sdk/js?client-id={{$CartAttr['PaypalClientID']}}&components=messages,buttons,funding-eligibility&commit=false&enable-funding=paylater&disable-funding=card"></script> -->
<script src = "https://www.paypal.com/sdk/js?client-id={{$CartAttr['PaypalClientID']}}&currency=USD&components=messages,buttons,funding-eligibility&enable-funding=paylater&disable-funding=card"></script>

<script type="text/javascript">
let paypalApprovalHandled = false; 	
var Site_URL = "{{config('global.SITE_URL')}}";	
var CartFullDetails = '';
var CREATE_PAYMENT_URL  = '{{url('paypal/placeorder')}}';
let  state= '';
let  zip= '';
let  country= '';
let  city= '';
if($("#paypal-button-container").length > 0) {  
    paypal.Buttons({
        onInit(data, actions) {            
            actions.disable();			
        },
        onClick: function() {
            window.location.href = CREATE_PAYMENT_URL;
        },
        style: {
            layout: 'horizontal',
            color:  'gold',
            shape:  'rect',
            label:  'paypal',
            height: 45
        }
    }).render('#paypal-button-container');
}
function UpdatePaypalOrderResponse(OrderDetails,OrderID,routenmnew,isAbort="")
{
    var res ='';
    $.ajax({
        type:'POST',
        url:site_url+'dopaymentpaypal',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        async:false,
        datatype: 'JSON',
        data:{
                OrderDetails : OrderDetails,
                OrderID : OrderID,
                routenmnew : routenmnew,
                isAbort : isAbort
        },
        success:function(data) {
            res= data
        },
        
    });
    return res
}

async function GetPaypalCart()
{			
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			url:site_url+'getpaypalcartItems',
			datatype: 'JSON',
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			data:{
				rtnm : routenmnew
			},				
			success: function (data) {
				resolve(data);
			},
			error: function (xhr, status, error) {
				reject(error); 
			}
		});
	});			
}

function SetPaypalShippingMethod(ShipMethodID,state='',zip='',country='',city='')
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
			action : 'paypalcart',	
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
if($("#paypal-button-container-checkout-pg").length > 0){
    paypal.Buttons({
        // style: {
        // 	layout: 'vertical',
        // 	color:  'gold',
        // 	shape:  'rect',
        // 	label:  'paypal',
        // 	height: 45
        // },
        style: {
            layout: 'vertical', 
            color: 'gold',
            shape: 'rect', 
            label:  'paypal',       
            tagline: false,
            height: 45
        },
        onInit(data, actions) {            
            actions.disable();			
        },
        //fundingSource: paypal.FUNDING.PAYLATER,
        onClick:  function() {
            //purchasUnits = await GetPaypalCart();
            $("#payment-request-button").hide();
            $("#gpay_applepay_paypal").text("PayPal");
            $("#paypal-button-container-checkout").show();
            $('#ShippingSignInsu').modal('show');
            return false;		
        },
        onError: function (err) {
            console.error('PayPal Checkout Error:', err);
        }
    }).render('#paypal-button-container-checkout-pg');
}


// Initialize PayPal buttons
paypal.Buttons({
	style: {
        layout: 'horizontal',
        color:  'gold',
        shape:  'rect',
        label:  'paypal',
        tagline :false
		// layout: 'horizontal',
		// color:  'gold',
		// shape:  'rect',
		// label:  'paypal',
		// height: 45
	},
	onClick:  function() {
		//purchasUnits = await GetPaypalCart(); 
        $('#ShippingSignInsu').modal('hide');       		
	},    
    createOrder:async function (data, actions) {
		
        return actions.order.create(
		await GetPaypalCart()
        );
    },
  onShippingAddressChange(data, actions) {
        if(routenmnew == 'billing-payment'){
            return true;
        } else {
			
            state=  data.shippingAddress.state;
            zip =   data.shippingAddress.postalCode;
            country=  data.shippingAddress.countryCode;	 
            city=  data.shippingAddress.city;	 	 
	        return fetch(Site_URL+"paypalupdatedetails",{
                method:'POST',
				headers: {
					 "Content-Type": "application/json",
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				body: JSON.stringify({
				'OnlyHead':'0',
				'action':'shippinginfo',
				'subaction' : 'paypalcart',
				orderID:data.orderID,
				state : data.shippingAddress.state,
				zip: data.shippingAddress.postalCode,
				country : data.shippingAddress.countryCode,
				city : data.shippingAddress.city
			  })
		
            }) .then(response => response.json())
                    .then(data => { 
                        if(data.status== 'Error'){                            
							throw new Error()                            
                        }                        
                    })
                    .catch((error) => { 
                        window.location.href = site_url + 'shoppingcart/view';
                    });		  
        }
	},
	
  onShippingOptionsChange: function(data, actions) {
        if(routenmnew == 'billing-payment'){
            return true;
        } else {
		
            SetPaypalShippingMethod(data.selectedShippingOption.id,state,zip,country,city)
            return fetch(Site_URL+"paypalupdatedetails",{
                method:'POST',
                headers: {
                        "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({
                    'OnlyHead':'0',
                    'action':'shippinginfo',
                    'subaction' : 'paypalcart',
                    orderID:data.orderID,
                    state : state,
                    zip: zip,
                    country : country,
                    city : city
                })    
            }) .then(response => response.json())
                    .then(data => { 
                        if(data.status== 'Error'){
                            throw new Error()                            
                        }                        
                    })
                    .catch((error) => {
                        window.location.href = site_url + 'shoppingcart/view';
                    });  
            }            
    }, 
    onApprove: function (data, actions) {
        addPayPalLog("onApprove",data,actions,routenmnew);
        var payer_email = "";
        var payer_address1 = "";
        var payer_city = "";
        var payer_state = "";
        var payer_country = "";
        var payer_postcode = "";
        
        if (paypalApprovalHandled) {
				 window.location.href = site_url + 'shoppingcart/view'; 
                 return false;
		}
		paypalApprovalHandled = true; 
			
        return actions.order.get().then(async function (details) {
            payer_address1 = details.purchase_units[0].shipping.address.address_line_1;
            payer_state = details.purchase_units[0].shipping.address.admin_area_1; //state
            payer_city = details.purchase_units[0].shipping.address.admin_area_2; //city
            payer_country = details.purchase_units[0].shipping.address.country_code; //country
            payer_postcode = details.purchase_units[0].shipping.address.postal_code; //postal code
            
            payer_email = details.payer.email_address;
            if(routenmnew == 'billing'){ 
                UpdateDetails(data.orderID,state,zip,country,payer_email,city);        
            }
            //Ans=InsertOrders(payer_email);            
            Ans = InsertOrders(payer_email, payer_address1, payer_state, payer_city, payer_country, payer_postcode, JSON.stringify(details),"");
            
            if(Ans=='OutOfStock' || Ans=='Close' || Ans=='Guest' || Ans=='SHMethod' || Ans=='Zero' || Ans=='Blocked')
            {      
                if(Ans != "Blocked"){
                    Ans = InsertOrders(payer_email, payer_address1, payer_state, payer_city, payer_country, payer_postcode, JSON.stringify(details)+"---"+Ans+"---"+routenmnew,"order_invalid"); 
                }      
                window.location.href = site_url + 'shoppingcart/view'; 
                return false;
            }
            
            
            if(routenmnew == 'billing'){ 
               // UpdateDetails(data.orderID,state,zip,country,payer_email);        
            }

            if(Ans != "" && Ans != null && Ans > 0 && $.isNumeric(Ans)){
                var res_capture = UpdatePaypalOrderResponse(JSON.stringify(details),JSON.stringify(Ans),routenmnew);
                    
                if(res_capture=="OrderReceiptSuceess")
                {                    
                    window.location.href = site_url + 'order-receipt';
                } 
                else
                {                        
                    window.location.href = site_url + 'shoppingcart/view';
                }
            } else {
                ///alert(12312312312);
                addPayPalLog("NoOrderId",details,actions,routenmnew);                
                window.location.href = site_url + 'shoppingcart/view';
                return false;
            }  
             
            
            // if(Ans != "" && Ans != null && Ans > 0 && $.isNumeric(Ans)){
            //     await UpdatePaypalOrderResponse(JSON.stringify(details),JSON.stringify(Ans),routenmnew);

            //     try {
            //             const alive = await checkServerPayPal();
            //             if (!alive) {
            //                 alert("Server unavailable. Please try again later.");
            //                 window.location = site_url + 'shoppingcart/view';
            //                 return false;
			// 				}
            //     const controller = new AbortController();
			// 	const signal = controller.signal;

            //    return actions.order.capture({ signal }).then(async function (Orderdetails) {
            //         console.log(Orderdetails);
            //         await UpdatePaypalOrderResponse(JSON.stringify(Orderdetails),JSON.stringify(Ans),routenmnew)
            //         if(Orderdetails.status=="COMPLETED"  || Orderdetails.status=="APPROVED")
            //         {	
            //             addPayPalLog("OrderDetailStatus--"+Orderdetails.status,JSON.stringify(Orderdetails),actions,routenmnew);
            //             window.location.href = site_url + 'order-receipt';
            //         }
            //         else
            //         {
            //             addPayPalLog("OrderDetailStatusError",JSON.stringify(Orderdetails),actions,routenmnew);
            //             window.location.href = site_url + 'shoppingcart/view';
            //         }		
            //     }).catch(function(err) {
					
			// 		if (err.name === 'AbortError') {
			// 				alert('Payment stopped. Please try again.');
			// 			} else {
			// 				console.error('Payment capture failed:', err);
			// 			}
			// 			addPayPalLog("OrderCaptureCatch", "Error--" + JSON.stringify(err) + "--Details--" + JSON.stringify(details), actions, routenmnew);
			// 			resval =UpdatePaypalOrderResponse(JSON.stringify(details), JSON.stringify(Ans), routenmnew,"isAbort");
			// 			if(resval=="OrderReceiptSuceess")
			// 			{
			// 				window.location.href = site_url + 'order-receipt';
			// 			} 
			// 			else
			// 			{
			// 				window.location.href = site_url + 'shoppingcart/view';
			// 			}
					
            //     });
            // }catch (error) {
            //         console.error('Error:', error);
            //         addPayPalLog("OrderCaptureError", JSON.stringify(error), actions, routenmnew);
                  
            //          resval =UpdatePaypalOrderResponse(JSON.stringify(details), JSON.stringify(Ans), routenmnew,"isAbort");
			// 			if(resval=="OrderReceiptSuceess")
			// 			{
			// 				window.location.href = site_url + 'order-receipt';
			// 			} 
			// 			else
			// 			{
			// 				window.location.href = site_url + 'shoppingcart/view';
			// 			}
                  
			// 		}    
            // } else {
            //     ///alert(12312312312);
            //     addPayPalLog("NoOrderId",details,actions,routenmnew);
            //     window.location.href = site_url + 'shoppingcart/view';
            //     return false;
            // }     
        })


        // if(routenmnew == 'billing'){        
		//     //UpdateDetails(data.orderID,state,zip,country,city);  
        //     UpdateDetails(data.orderID,state,zip,country); 
        // } 
		// Ans=InsertOrders(payer_email);
		// console.log(Ans);


        // return;
		// if(Ans=='OutOfStock' || Ans=='Close' || Ans=='Guest' || Ans=='SHMethod' || Ans=='Zero')
		// {            
		// 	window.location.href = site_url + 'shoppingcart/view';
		// }		
        // return actions.order.capture().then(function (Orderdetails) {
        //     console.log(Orderdetails);
		// 	UpdatePaypalOrderResponse(JSON.stringify(Orderdetails),JSON.stringify(Ans))
		// 	if(Orderdetails.status=="COMPLETED")
		// 	{	
		// 		//window.location.href = site_url + 'order-receipt';
		// 	}
		// 	else
		// 	{
		// 		window.location.href = site_url + 'shoppingcart/view';
		// 	}		
        // }).catch(function(err) {
	      
		// 	UpdatePaypalOrderResponse(JSON.stringify(err),JSON.stringify(Ans))
		// 	window.location.href = site_url + 'shoppingcart/view';
		// });
    },
    onError: function (err) {
        console.error('PayPal Checkout Error:', err);
       
    }
}).render('#paypal-button-container-checkout');

</script>
@endif
@endif
<script>
function addPayPalLog(logaction,data,actions,routenmnew)	{
    var res = "";
    $.ajax({
        type:'POST',
        url:site_url+'paypallogupdate',
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

function UpdateDetails(orderID,state,zip,country,payer_email,city)
{
    var res ='';
    $.ajax({
        type:'POST',
        url:site_url+'paypalupdatedetails',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data:{
			orderID: orderID,
			state:state,
			zip:zip,
			country:country,
			payer_email:payer_email,
			city:city,
			'OnlyHead':'0',
			'action':'shippinginfo',
			'subaction' : 'paypalcart',
            rtnm : routenmnew
		},
        async:false,
        datatype: 'JSON',
        success:function(data) {
            res= data
        },
        
    });
    return res
}

	
function InsertOrders(payer_email, payer_address1, payer_state, payer_city, payer_country, payer_postcode, order_details, is_invalid)
{
    var res ='';
    $.ajax({
        type:'POST',
        url:site_url+'paypalordercollect',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data:{
            payer_email : payer_email,
            payer_address1 : payer_address1, 
            payer_state : payer_state, 
            payer_city : payer_city, 
            payer_country : payer_country, 
            payer_postcode : payer_postcode,
            order_details : order_details,
            order_invalid : is_invalid,
            rtnm : routenmnew
        },
        async:false,
        datatype: 'JSON',
        success:function(data) {
            res= data
        },
        
    });
    return res
}
async function checkServerPayPal() {	
	try {
		const controller = new AbortController();
		const signal = controller.signal;

		const res = await fetch(site_url + 'servercheck', {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Content-Type': 'application/json'
			},
			signal: signal
		});

		if (!res.ok) return false;
		const data = await res.json();
		return data && data.status === 'ok';
	} catch (e) {
		if (e.name === 'AbortError') {
			alert('Server check aborted (server down or timeout).');
		}
		return false;
	}
}
function UpdatePaypalOrderResponsePDP(OrderDetails,OrderID,routenmnew,isAbort="")
{    
    var res ='';
    $.ajax({
        type:'POST',
        url:site_url+'dopaymentpaypalpdp',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        async:false,
        datatype: 'JSON',
        data:{
                OrderDetails : OrderDetails,
                OrderID : OrderID,
                routenmnew : routenmnew,
                isAbort : isAbort
            },
        success:function(data) {
            res= data
        },
        
    });
    return res
}
</script>
