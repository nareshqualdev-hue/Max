function initAfterpayCheckout() {
    setTimeout(function() {
        AfterPay.initializeForPopup({
            countryCode: 'US',
            onCommenceCheckout: function(actions) {
                /* retrieve afterpay token from your server */
                /* then call `actions.resolve(token)` */
                // actions.resolve('$Afterpay_Token');

                $.ajax({
                    type: "POST",
                    url: site_url + "afterpay/placeorder_express",
                    headers: {
                        'X-CSRF-TOKEN': token
                    },
                    data: "get_token=1",
                    dataType: "JSON",
                    success: function(response) {
                        if (response.success == 1 && response.token != "") {
                            actions.resolve(response.token);
                        } else {
                            alert(response.message);
                            // location.reload();
                            return false;
                        }
                    }
                })
            },
            onShippingAddressChange: function(data, actions) {
                /* address in `data` */
                /* calc options, then call `actions.resolve(options)` */
                console.log(data);

            },
            onComplete: function(event) {
                $("#page-spinner").show()
                console.log(event);
                /* handle success/failure of checkout */
                if (event.data.status == "SUCCESS") {
                    // The consumer has confirmed the payment schedule.
                    // Call your server here to retrieve the order details
                    var order_token = event.data.orderToken;
                    var merchant_reference = event.data.merchantReference;

                    location.href = site_url + "afterpay/billing_checkout_express/1/" + order_token;
                    return false;
                } else {
                    var order_token = "undefined";

                    // The consumer cancelled the payment or closed the popup window.
                    location.href = site_url + "afterpay/billing_checkout_express/0/" + order_token;
                    return false;
                }
            },
            target: '#afterpay-button',
            shippingOptionRequired: false,
        })
    }, 500);
}