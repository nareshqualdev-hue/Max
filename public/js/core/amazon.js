window.onAmazonLoginReady = function() {
    amazon.Login.setClientId(window.amazon.client_id);
    amazon.Login.setUseCookie(true);
};

var call_url = window.amazon.callback_url;
window.onAmazonPaymentsReady = function() {
    var authRequest;
    OffAmazonPayments.Button(
        "AmazonPayButtonAll",
        window.amazon.merchant_id,
        {
            type: "pay",
            size: "large",

            authorization: function() {
                var loginOptions = {
                    scope: "profile postal_code payments:widget payments:shipping_address",
                    popup: true
                };
                authRequest = amazon.Login.authorize(
                    loginOptions,
                    call_url
                );
            },
            onError: function(error) {

                console.error(
                    'Amazon Pay Error:',
                    error
                );
                //alert(error);
            }
        }
    );
};