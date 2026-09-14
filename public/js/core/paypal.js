//const { act } = require("react");
function renderPayPalButton(selector) {
    var btnAction = "FirstStep";
    if(selector == '#paypal-button-container-checkout-pg')
    {
        btnAction = "LastStep";
    }
    paypal.Buttons({
        style: {
            layout: 'vertical',
            color: 'gold',
            shape: 'rect',
            label: 'paypal',
            tagline: false,
            height: 45
        }/*,
        onInit(data, actions) {
            if(btnAction == 'LastStep')
            {
                actions.disable();
            }
        }*/,
        createOrder:async function (data, actions) {
            return actions.order.create(
                await GetPaypalCart(btnAction)
            );
        },
        onShippingAddressChange(data, actions) {
            if(btnAction == 'FirstStep')
            {
                return shippingAddressChange(data, actions, btnAction);
            }
            return true;
        },
        onShippingOptionsChange: function(data, actions) {
            if(btnAction == 'FirstStep')
            {
                return shippingOptionsChange(data, actions, btnAction);
            }
            return true;
        },
        onApprove: function (data, actions) {
            return approve(data,actions);
        },
        onError: function (err) {
            console.error('PayPal Checkout Error:', err);
        }

    }).render(selector);
}

//New Functions For Paypal Events
async function shippingAddressChange(data, actions, btnAction) {

    console.log('PayPal shipping address:', data);

    state   = data.shippingAddress.state;
    zip     = data.shippingAddress.postalCode;
    country = data.shippingAddress.countryCode;
    city    = data.shippingAddress.city;

    try {

        const response = await fetch(Site_URL + "paypal-update", {
            method: 'POST',

            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
            },

            body: JSON.stringify({
                OnlyHead: '0',
                action: 'shippinginfo',
                subaction: 'paypalcart',
                orderID: data.orderID,
                state: data.shippingAddress.state,
                zip: data.shippingAddress.postalCode,
                country: data.shippingAddress.countryCode,
                city: data.shippingAddress.city,
                btnAction: btnAction
            })
        });

        /*
         * Don't immediately call response.json().
         * First inspect what the server actually returned.
         */
        const contentType = response.headers.get('content-type') || '';

        console.log('PayPal update status:', response.status);
        console.log('PayPal update content-type:', contentType);

        if (!response.ok) {

            const text = await response.text();

            console.error(
                'PayPal update HTTP error:',
                response.status,
                text
            );

            throw new Error(
                'Unable to update shipping address.'
            );
        }

        if (!contentType.includes('application/json')) {

            const text = await response.text();

            console.error(
                'Expected JSON but received:',
                text
            );

            throw new Error(
                'Invalid response from checkout server.'
            );
        }

        const result = await response.json();

        console.log(
            'PayPal shipping update response:',
            result
        );

        /*
         * Only reload shipping methods after
         * the backend successfully processes the address.
         */
        if (result.status === 'Error') {
            throw new Error(
                result.message || 'Shipping address is not available.'
            );
        }

        //window.MaxaromaOnePageCheckout.loadShippingMethods();

        return result;

    } catch (error) {

        console.error(
            'PayPal shippingAddressChange error:',
            error
        );

        throw error;
    }
}

async function shippingOptionsChange(data, actions, btnAction) {

    console.log('PayPal Shipping Option Change:', data);
    const shippingOptionId = data?.selectedShippingOption?.id;

    if (!shippingOptionId) {
        console.error('PayPal shipping option ID is missing.');
        throw new Error('Shipping option is required.');
    }

    try {

        const response = await fetch(Site_URL + "paypal-update", {
            method: 'POST',
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify({
                OnlyHead: '0',
                action: 'setShippingMethod',
                subaction: 'paypalcart',
                orderID: data.orderID,
                state: state,
                zip: zip,
                country: country,
                city: city,
                shippingMethod: shippingOptionId,
                shippingCharge: data?.selectedShippingOption?.amount?.value,
                btnAction: btnAction
            })
        });

        // Check HTTP status first
        if (!response.ok) {
            const text = await response.text();
            console.error(
                'PayPal shipping option update failed:',
                response.status,
                text
            );

            throw new Error(
                `Shipping update failed (${response.status})`
            );
        }

        // Check that Laravel actually returned JSON
        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            const text = await response.text();

            console.error(
                'Expected JSON but received:',
                text
            );

            throw new Error('Invalid server response.');
        }

        const result = await response.json();

        console.log('PayPal Shipping Option Update Response:', result);

        if (result.status === 'Error') {
            throw new Error(
                result.message || 'Unable to update shipping method.'
            );
        }

        // Successfully updated checkout/shipping state
        return result;

    } catch (error) {

        console.error(
            'PayPal Shipping Option Change Error:',
            error
        );

        // IMPORTANT:
        // Do not silently swallow the error.
        // PayPal needs to know that the callback failed.
        throw error;
    }
}

async function approve(data, actions) {
    addPayPalLog("onApprove",data,actions,routenmnew);
    var payer_email = "";
    var payer_address1 = "";
    var payer_city = "";
    var payer_state = "";
    var payer_country = "";
    var payer_postcode = "";

    console.log(data,actions);
    console.log(paypalApprovalHandled);

    if (paypalApprovalHandled) {
        window.location.href = site_url + 'shoppingcart/view';
        return false;
    }
    paypalApprovalHandled = true;

    return actions.order.get().then(async function (details)
    {
        console.log(details);
        payer_address1 = details.purchase_units[0].shipping.address.address_line_1;
        payer_state = details.purchase_units[0].shipping.address.admin_area_1; //state
        payer_city = details.purchase_units[0].shipping.address.admin_area_2; //city
        payer_country = details.purchase_units[0].shipping.address.country_code; //country
        payer_postcode = details.purchase_units[0].shipping.address.postal_code; //postal code

        payer_email = details.payer.email_address;
        //if(routenmnew == 'billing'){
        //UpdateDetails(data.orderID,state,zip,country,payer_email,city);
        //}

        res = InsertOrders(payer_email, payer_address1, payer_state, payer_city, payer_country, payer_postcode, JSON.stringify(details),"");
        if(res.status === true)
        {
            var OrderID = res.order_id;
            var res_capture = UpdatePaypalOrderResponse(JSON.stringify(details),OrderID,routenmnew);
            if(res_capture.status === true)
            {
                window.location.href = site_url + 'order-receipt';
            }
            else
            {
                window.location.href = site_url + 'shoppingcart/view';
            }
        }
        /*
        if(Ans=='OutOfStock' || Ans=='Close' || Ans=='Guest' || Ans=='SHMethod' || Ans=='Zero' || Ans=='Blocked')
        {
            if(Ans != "Blocked"){
                Ans = InsertOrders(payer_email, payer_address1, payer_state, payer_city, payer_country, payer_postcode, JSON.stringify(details)+"---"+Ans+"---"+routenmnew,"order_invalid");
            }
            window.location.href = site_url + 'shoppingcart/view';
            return false;
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
            addPayPalLog("NoOrderId",details,actions,routenmnew);
            window.location.href = site_url + 'shoppingcart/view';
            return false;
        }
        */
    })
}
//New Functions For Paypal Events

function UpdatePaypalOrderResponse(OrderDetails,OrderID,routenmnew,isAbort="")
{
    var res ='';
    $.ajax({
        type:'POST',
        url:site_url+'checkout/order/update',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        async:false,
        datatype: 'JSON',
        data:{
            OrderDetails : OrderDetails,
            OrderID : OrderID,
            routenmnew : routenmnew,
            isAbort : isAbort,
            isPaypalOrder : 'Yes'
        },
        success:function(data) {
            res= data
        },
    });
    return res
}

async function GetPaypalCart(btnAction)
{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			url:site_url+'prepare-paypal-cart',
			datatype: 'JSON',
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			data:{
				btnAction : btnAction
			},
			success: function (data) {
				resolve(data);
                console.log(data);
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
    renderPayPalButton("#paypal-button-container-checkout-pg");
}
if($("#paypal-button-container-checkout").length > 0)
{
    renderPayPalButton("#paypal-button-container-checkout");
}

function addPayPalLog(logaction,data,actions,routenmnew)
{
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
        //url:site_url+'paypalordercollect',
        url:site_url+'checkout/order/create',
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
            rtnm : routenmnew,
            isPaypalOrder : 'Yes'
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