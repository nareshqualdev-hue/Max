<script>
let stripe = null;
let paymentRequest = null;
let prButton = null;

let stripeInitialized = false;
let AppleGPay = '';
let stripeCartRefreshInProgress = false;

const csrfToken = $('meta[name="csrf-token"]').attr('content');

const stripeConfig = {
    publishableKey: '{{ env("STRIPE_KEY") }}',
    country: 'US',
    currency: 'usd'
};

/**
 * ---------------------------------------------------------
 * Common AJAX helper
 * ---------------------------------------------------------
 */
async function checkoutPost(url, data = {}, options = {}) {

    const response = await $.ajax({
        type: 'POST',
        url: url,
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        dataType: 'json',
        data: data,
        ...options
    });

    return response;
}

/**
 * ---------------------------------------------------------
 * Convert amount to Stripe smallest currency unit
 * ---------------------------------------------------------
 */
function toStripeAmount(amount) {
    return Math.round(Number(amount || 0));
}

/**
 * ---------------------------------------------------------
 * Get shipping options
 * ---------------------------------------------------------
 */
async function GetShippingOptions(
    state = '',
    zip = '',
    country = '',
    city = ''
) {
    const data = await checkoutPost(
        //site_url + 'shipping',
        site_url + 'get-shipping-methods',
        {
            OnlyHead: '0',
            action: 'shippinginfo',
            subaction: 'stripecart',

            state: state,
            zip: zip,
            country: country,
            city: city,

            Gpay: 'Yes',
            FirstStepGpay: 'FirstStep'
        }
    );

    if (!Array.isArray(data) || !data.length) {
        return [];
    }

    // Set first shipping method as default.
    /*
    await SetStripeShippingMethod(
        data[0].id,
        state,
        zip,
        country,
        city
    );
    */
    return data;
}

async function RefreshStripePaymentRequest() {

    if (!paymentRequest || stripeCartRefreshInProgress) {
        return;
    }

    stripeCartRefreshInProgress = true;

    try {

        const cart = await GetStripeCart();

        if (!cart) {
            return;
        }

        paymentRequest.update({
            total: {
                label: 'Order Total',
                amount: cart.NetTotal
            },
            displayItems: cart.items || []
        });

    } catch (error) {

        console.error(
            'Stripe cart update failed:',
            error
        );

    } finally {

        stripeCartRefreshInProgress = false;
    }
}

/**
 * ---------------------------------------------------------
 * Get Stripe Cart
 * ---------------------------------------------------------
 */
async function GetStripeCart() {

    $('#page-spinner').show();
    $('#ShippingSignInsu').modal('hide');

    try {

        const data = await checkoutPost(
            site_url + 'get-stipe-cart',
            {
                action: 'getcart'
            }
        );

        if (data === 'OutOfStock') {
            window.location.href =
                site_url + 'shoppingcart/view';

            return null;
        }

        if (data === 'Zero') {
            window.location.href =
                site_url + 'shoppingcart/view';

            return null;
        }

        return {
            items: data.items || [],
            NetTotal: Number(data.NetTotal || 0)
        };

    } finally {
        $('#page-spinner').hide();
    }
}

/**
 * ---------------------------------------------------------
 * Set Stripe shipping method
 * ---------------------------------------------------------
 */
async function SetStripeShippingMethod(
    ShipMethodID,
    state = '',
    zip = '',
    country = '',
    city = ''
) {
    //alert(ShipMethodID);
    const setShipping =  checkoutPost(
        //site_url + 'setshipmethod',
        site_url + 'set-shipping-method',
        {
            action: 'stripecart',

            ShipMethodID: ShipMethodID,

            // state: state,
            // zip: zip,
            // country: country,
            // city: city
        }
    );

    //alert(setShipping);
    return setShipping;
}

/**
 * ---------------------------------------------------------
 * Get Stripe PaymentIntent client secret
 * ---------------------------------------------------------
 */
async function GetClientSecret(appleGPay, allDetails) {

    const data = await checkoutPost(
        site_url + 'getclientsecret',
        //site + 'get-client-secret',
        {
            action: 'clientsecret',

            AppleGPay: appleGPay,
            allDetailsVal: allDetails,

            stepfrom: 'firststep'
        }
    );

    if (
        data === 'OutOfStock' ||
        data === 'Close' ||
        data === 'Guest' ||
        data === 'SHMethod' ||
        data === 'Zero'
    ) {
        return null;
    }

    return data.clientSecret || null;
}

/**
 * ---------------------------------------------------------
 * Handle payment failure
 * ---------------------------------------------------------
 */
function StripePaymentFailed(event = null) {

    try {
        if (event) {
            event.complete('fail');
        }
    } catch (e) {
        console.error(
            'Unable to complete Stripe payment event:',
            e
        );
    }

    alert(
        'Your payment has failed. Please try again or use different payment option.'
    );

    window.location.href =
        site_url + 'shoppingcart/view';
}

/**
 * ---------------------------------------------------------
 * Save successful Stripe wallet payment
 * ---------------------------------------------------------
 */
async function SaveStripeWalletPayment(
    ev,
    paymentMethodId
) {

    const data = await checkoutPost(
        site_url + 'stripebtnres',
        {
            methodName:
                ev.paymentMethod.payment_method_types,

            payerEmail:
                ev.payerEmail || '',

            payerName:
                ev.payerName || '',

            paymentMethod:
                paymentMethodId,

            shippingOption:
                ev.shippingOption || null,

            shippingAddress:
                ev.shippingAddress || null,

            stepfrom:
                'firststep',

            payerPhone:
                ev.payerPhone || ''
        }
    );

    if (!data || data.status !== 'success') {

        addApplePayLog(
            'confirm_result_payment_failed',
            data,
            '',
            '<?= $CurrentRoute ?>'
        );

        StripePaymentFailed(ev);

        return false;
    }

    /**
     * Shipping signature
     */
    $('#shipsignatureflag').val(
        $('#shipping_signature').prop('checked')
            ? 'Yes'
            : 'No'
    );

    /**
     * Keep existing flags for backward compatibility.
     */
    $('#is_stripe_wallet').val('google_pay');
    $('#is_stripe_applepay').val('apple_pay');

    window.location.href =
        site_url + 'order-receipt';

    return true;
}

/**
 * ---------------------------------------------------------
 * Initialize Stripe
 * ---------------------------------------------------------
 */
async function InitializeStripePaymentRequest() {

    if (stripeInitialized) {
        return;
    }

    stripeInitialized = true;

    stripe = Stripe(
        stripeConfig.publishableKey
    );

    paymentRequest = stripe.paymentRequest({

        country:
            stripeConfig.country,

        currency:
            stripeConfig.currency,

        total: {
            label: 'Order Total',
            amount: toStripeAmount(
                {{ $NetTotal }}
            )
        },

        requestShipping: true,
        requestPayerName: true,
        requestPayerEmail: true,
        requestPayerPhone: true
    });

    /**
     * Stripe Elements
     */
    const elements = stripe.elements();

    prButton = elements.create(
        'paymentRequestButton',
        {
            paymentRequest: paymentRequest,

            style: {
                paymentRequestButton: {
                    type: 'default',
                    theme: 'dark',
                    height: '45px'
                }
            }
        }
    );

    /**
     * -----------------------------------------------------
     * Check Apple Pay / Google Pay availability
     * -----------------------------------------------------
     */
    const result =
        await paymentRequest.canMakePayment();

    if (!result) {

        $('#payment-request-button-checkout')
            .closest('li')
            .hide();

        $('#payment-request-button-checkout')
            .hide();

        $('#payment-request-button')
            .hide();

        return;
    }

    /**
     * Detect wallet
     */
    if (result.applePay) {
        AppleGPay = 'A';

        $('#GpayBtn')
            .show()
            .html('Apple Pay');
    }

    if (result.googlePay) {
        AppleGPay = 'G';

        $('#GpayBtn')
            .show()
            .html('Google Pay');
    }

    /**
     * Mount wallet button.
     *
     * Use whichever container is required
     * by your existing checkout.
     */
    if ($('#payment-request-button-checkout').length) {

        prButton.mount(
            '#payment-request-button-checkout'
        );
    }

    /**
     * -----------------------------------------------------
     * Button click
     * -----------------------------------------------------
     */
    /*
    prButton.on(
        'click',
        async function () {

            try {

                const cart =
                    await GetStripeCart();

                if (!cart) {
                    return;
                }

                paymentRequest.update({

                    total: {
                        label: 'Order Total',

                        amount:
                            toStripeAmount(
                                cart.NetTotal
                            )
                    },

                    displayItems:
                        cart.items
                });

            } catch (error) {

                console.error(
                    'Stripe cart update failed:',
                    error
                );

                window.location.href =
                    site_url +
                    'shoppingcart/view';
            }
        }
    );
    */
    /**
     * -----------------------------------------------------
     * Shipping address change
     * -----------------------------------------------------
     */
    paymentRequest.on(
        'shippingaddresschange',
        async function (ev) {

            try {

                if (
                    ev.shippingAddress.country !== 'US'
                ) {

                    ev.updateWith({
                        status:
                            'invalid_shipping_address'
                    });

                    return;
                }

                const address =
                    ev.shippingAddress;

                const shippingOptions =
                    await GetShippingOptions(
                        address.region || '',
                        address.postalCode || '',
                        address.country || '',
                        address.city || ''
                    );

                ev.updateWith({
                    status: 'success',
                    shippingOptions:shippingOptions
                });

            } catch (error) {

                console.error(
                    'Shipping address error:',
                    error
                );

                ev.updateWith({
                    status: 'fail'
                });
            }
        }
    );

    /**
     * -----------------------------------------------------
     * Shipping method change
     * -----------------------------------------------------
     */
    paymentRequest.on(
        'shippingoptionchange',
        async function (ev) {

            try {

                const shippingMethodId =
                    ev.shippingOption.id;

                await SetStripeShippingMethod(
                    shippingMethodId
                );

                const cart =
                    await GetStripeCart();

                if (!cart) {

                    ev.updateWith({
                        status: 'fail'
                    });

                    return;
                }

                ev.updateWith({

                    status: 'success',

                    total: {
                        label: 'Order Total',

                        amount:
                            toStripeAmount(
                                cart.NetTotal
                            )
                    },

                    displayItems:
                        cart.items
                });

            } catch (error) {

                console.error(
                    'Shipping method update failed:',
                    error
                );

                ev.updateWith({
                    status: 'fail'
                });
            }
        }
    );

    /**
     * -----------------------------------------------------
     * Payment Method
     * -----------------------------------------------------
     */
    paymentRequest.on(
        'paymentmethod',
        async function (ev) {

            let paymentCompleted = false;

            try {

                /**
                 * Get fresh PaymentIntent client secret.
                 */
                const clientSecret =
                    await GetClientSecret(
                        AppleGPay,
                        JSON.stringify(ev)
                    );
                alert(clientSecret);
                if (!clientSecret) {

                    StripePaymentFailed(ev);

                    return;
                }

                /**
                 * Confirm wallet payment.
                 *
                 * handleActions=false means we handle
                 * next_action separately.
                 */
                const result =
                    await stripe.confirmCardPayment(
                        clientSecret,
                        {
                            payment_method:
                                ev.paymentMethod.id
                        },
                        {
                            handleActions: false
                        }
                    );

                if (result.error) {

                    console.error(
                        'Stripe confirmation error:',
                        result.error
                    );

                    StripePaymentFailed(ev);

                    return;
                }

                const paymentIntent =
                    result.paymentIntent;

                /**
                 * Customer authentication required.
                 */
                if (
                    paymentIntent.status ===
                    'requires_action'
                ) {

                    const actionResult =
                        await stripe.confirmCardPayment(
                            clientSecret
                        );

                    if (actionResult.error) {

                        console.error(
                            'Stripe authentication failed:',
                            actionResult.error
                        );

                        StripePaymentFailed(ev);

                        return;
                    }
                }

                /**
                 * Tell Apple Pay / Google Pay that
                 * Stripe payment succeeded.
                 */
                ev.complete('success');

                paymentCompleted = true;

                /**
                 * Save payment / create final order.
                 */
                await SaveStripeWalletPayment(
                    ev,
                    ev.paymentMethod.id
                );

            } catch (error) {

                console.error(
                    'Stripe wallet payment error:',
                    error
                );

                if (!paymentCompleted) {
                    StripePaymentFailed(ev);
                }
            }
        }
    );
}

/**
 * ---------------------------------------------------------
 * Initialize once
 * ---------------------------------------------------------
 */
$(document).ready(function () {

    InitializeStripePaymentRequest()
        .then(function () {
            RefreshStripePaymentRequest();
        })
        .catch(function (error) {

            console.error(
                'Stripe initialization failed:',
                error
            );

            $('#payment-request-button-checkout')
                .hide();

            $('#payment-request-button')
                .hide();
        });

});
</script>
{{--
<script>
    var AppleGPay = '';
    function GetShippingOptions(state = '', zip = '', country = '', city = '') {
        //alert(state);
        //alert(zip);
        //alert(country);
        var shippingmodes = [];
        $.ajax({
            type: 'POST',
            url: site_url + 'shipping',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            async: false,
            data: {
                'OnlyHead': '0',
                'action': 'shippinginfo',
                'subaction': 'stripecart',
                'state': state,
                'zip': zip,
                'country': country,
                'city': city,
                'Gpay': 'Yes',
                'FirstStepGpay': 'FirstStep'
            },
            datatype: 'JSON',
            success: function(data) {
                //alert(data);
                shippingmodes = data;
                SetStripeShippingMethod(data[0].id, state, zip, country, city);
            }
        });

        return shippingmodes;
    }

    function GetStripeCart() {
        $("#page-spinner").show();
        var items = [];
        var NetTotal = 0;
        $("#ShippingSignInsu").modal('hide');
        $.ajax({
            type: 'POST',
            url: site_url + 'get-stipe-cart',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            async: false,
            data: {
                'action': 'getcart',
            },
            datatype: 'JSON',
            success: function(data) {

                if (data == "OutOfStock") {
                    window.location = site_url + 'shoppingcart/view';
                    return false;
                }

                if (data == "Zero") {
                    window.location = site_url + 'shoppingcart/view';
                    return false;
                }

                items = data.items;
                NetTotal = data.NetTotal;
                $("#page-spinner").hide();
            }
        });
        var CartData = {
            'items': items,
            'NetTotal': NetTotal
        };
        return CartData;
    }

    function SetStripeShippingMethod(ShipMethodID, state = '', zip = '', country = '', city = '') {
        $.ajax({
            type: 'POST',
            url: site_url + 'setshipmethod',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            async: false,
            datatype: 'JSON',
            data: {
                action: 'stripecart',
                ShipMethodID: ShipMethodID,
                state: state,
                zip: zip,
                country: country,
                city: city
            },
            success: function(data) {},
        });
    }

    function GetClientSecret(AppleGPay, allDetails) {
        var clientSecret = "";
        $.ajax({
            type: 'POST',
            url: site_url + 'getclientsecret',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            async: false,
            datatype: 'JSON',
            data: {
                action: 'clientsecret',
                AppleGPay: AppleGPay,
                allDetailsVal: allDetails,
                stepfrom: 'firststep',
            },
            success: function(data) {
                if (data == "OutOfStock") {
                    //alert("Sorry some of your cart items are out of stock. Please update your cart and then proceed for payment");
                    return false;
                } else if (data == "Close") {
                    //alert("Sorry please try again");
                    return false;
                } else if (data == "Guest") {
                    //alert("Error while processing your order, Please try again");
                    return false;
                } else if (data == "SHMethod") {
                    //alert("PlaceOrderError: Shipping Method Not Selected");
                    return false;
                } else if (data == "Zero") {
                    //alert("Please change payment type");
                    return false;
                } else {
                    clientSecret = data.clientSecret;
                }
            },
        });
        return clientSecret;
    }
    //pk_test_xWTUgWDaaFSIfHkKClwzQrhS00eb9PQSLX

    $(document).ready(function() {
        var stripe = Stripe('{{env("STRIPE_KEY")}}', {
            apiVersion: "2019-05-16",
        });
        var paymentRequest = stripe.paymentRequest({
            country: 'US',
            currency: 'usd',
            total: {
                label: 'Order Total',
                amount: {{ round($NetTotal * 100)}},
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
                    type: 'default', // One of 'default', 'book', 'buy', or 'donate'
                    theme: 'dark', // One of 'dark', 'light', or 'light-outline'
                    height: '45px', // Defaults to '40px'. The width is always '100%'.
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
                if (result.applePay) {
                    //$("#GpayBtn").show();
                    $("#GpayBtn").html("Apple Pay");
                    //AppleGPay = 'A';
                }
                if (result.googlePay) {
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

    $(document).ready(function() {
        var stripe = Stripe('{{env("STRIPE_KEY")}}', {
            //var stripe = Stripe('pk_test_Ht4UumNApNKNPlO0eZVA4rhM00j4ohrYZO', {
            apiVersion: "2019-05-16",
        });

        var paymentRequest = stripe.paymentRequest({
            country: 'US',
            currency: 'usd',
            total: {
                label: 'Order Total',
                amount: {{ round($NetTotal * 100) }},
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
                    type: 'default', // One of 'default', 'book', 'buy', or 'donate'
                    theme: 'dark', // One of 'dark', 'light', or 'light-outline'
                    height: '45px' // Defaults to '40px'. The width is always '100%'.
                },
            },
        });

        prButton.on('click', function(ev) {
            var CartData = GetStripeCart();

            paymentRequest.update({
                total: {
                    label: 'Order Total',
                    amount: CartData.NetTotal,
                },
                displayItems: CartData.items,
                // shippingOptions: GetShippingOptions(),
            });
        })

        // Check the availability of the Payment Request API first.
        paymentRequest.canMakePayment().then(function(result) {
            if (result) {
                // $("#GpayBtn").show();
                if (result.applePay) {
                    $("#GpayBtn").show();
                    $("#GpayBtn").html("Apple Pay");
                    AppleGPay = 'A';
                }
                if (result.googlePay) {
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
                displayItems: CartData.items,
            });
        });

        paymentRequest.on('shippingaddresschange', async (ev) => {
            if (ev.shippingAddress.country !== 'US') {
                ev.updateWith({
                    status: 'invalid_shipping_address'
                });
            } else {
                var state = ev.shippingAddress.region;
                var zip = ev.shippingAddress.postalCode;
                var country = ev.shippingAddress.country;
                var city = ev.shippingAddress.city;
                var datas = GetShippingOptions(state, zip, country, city);
                //alert(datas);
                ev.updateWith({
                    status: 'success',
                    shippingOptions: datas,
                });
            }
        });

        let paymentController;
        paymentRequest.on('paymentmethod', async function(ev) {

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
                try {
                    ev.complete('fail');
                } catch (e) {}
                window.location = site_url + 'shoppingcart/view';
                return false;
            }

            try {
                stripe.confirmCardPayment(
                    clientSecret, {
                        payment_method: ev.paymentMethod.id
                    }, {
                        handleActions: false
                    }
                ).then(function(confirmResult) {
                    $("#dd123").val(confirmResult + "\n\n\n" + ev);
                    addApplePayLog("confirm_result_payment_method", confirmResult, "", '<?= $CurrentRoute ?>');
                    if (confirmResult.error) {
                        alert('Your payment has failed. Please try again or use different payment option.');
                        console.log('Could not confirm result: ', confirmResult.error);
                        ev.complete('fail');
                        window.location = site_url + 'shoppingcart/view';
                        return false;
                    } else {
                        addApplePayLog("confirm_result_payment_method_2", confirmResult, "", '<?= $CurrentRoute ?>');
                        console.log("confirmResult==", confirmResult);
                        ev.complete('success');

                        if (confirmResult.paymentIntent.status === "requires_action") {
                            addApplePayLog("confirm_result_payment_require_action", confirmResult, "", '<?= $CurrentRoute ?>');
                            stripe.confirmCardPayment(clientSecret).then(function(result) {
                                if (result.error) {
                                    addApplePayLog("confirm_result_payment_require_action_error", result, "", '<?= $CurrentRoute ?>');
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
                                        success: function(data) {
                                            if (data.status == 'success') {
                                                if ($("#shipping_signature").prop('checked'))
                                                    $("#shipsignatureflag").val('Yes');
                                                else
                                                    $("#shipsignatureflag").val('No');

                                                $("#is_stripe_wallet").val("google_pay");
                                                $("#is_stripe_applepay").val("apple_pay");
                                                window.location.href = site_url + 'order-receipt';
                                            } else {
                                                addApplePayLog("confirm_result_payment_failed", data, "", '<?= $CurrentRoute ?>');
                                                alert('Your payment has failed. Please try again or use different payment option.');
                                                ev.complete('fail');
                                                window.location = site_url + 'shoppingcart/view';
                                                return false;
                                            }
                                        },
                                        error: function(xhr, status, error) {
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
                                success: function(data) {
                                    if (data.status == 'success') {
                                        if ($("#shipping_signature").prop('checked'))
                                            $("#shipsignatureflag").val('Yes');
                                        else
                                            $("#shipsignatureflag").val('No');

                                        $("#is_stripe_wallet").val("google_pay");
                                        $("#is_stripe_applepay").val("apple_pay");
                                        window.location.href = site_url + 'order-receipt';
                                    } else {
                                        addApplePayLog("confirm_result_payment_failed_2", data, "", '<?= $CurrentRoute ?>');
                                        alert('Your payment has failed. Please try again or use different payment option.');
                                        ev.complete('fail');
                                        window.location = site_url + 'shoppingcart/view';
                                        return false;
                                    }
                                },
                                error: function(xhr, status, error) {
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
                        'isAbort': 'Yes'
                    },
                    success: function(data) {
                        if (data.status == 'success') {
                            if ($("#shipping_signature").prop('checked'))
                                $("#shipsignatureflag").val('Yes');
                            else
                                $("#shipsignatureflag").val('No');

                            $("#is_stripe_wallet").val("google_pay");
                            $("#is_stripe_applepay").val("apple_pay");
                            window.location.href = site_url + 'order-receipt';
                        } else {
                            addApplePayLog("confirm_result_payment_failed", data, "", '<?= $CurrentRoute ?>');
                            alert('Your payment has failed. Please try again or use different payment option.');
                            ev.complete('fail');
                            window.location = site_url + 'shoppingcart/view';
                            return false;
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", error);
                        ev.complete('fail');
                        window.location = site_url + 'shoppingcart/view';
                    }
                });
            }
        });
    });
</script>
--}}