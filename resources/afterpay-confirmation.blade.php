<!DOCTYPE html>
<html lang="en">
<head>
    <title>Deferred Payment Auth Request Sample</title>
</head>
<body>
    <a href="#main-content" class="skip-link visually-hidden">Skip to Content</a>
    <main role="main" id="main-content" tabindex="-1" aria-label="Afterpay Confirmation Main Content">
    @if($type == 'error')
        <h3 id="error-heading">Error</h3>
        <strong aria-labelledby="error-heading">{{ $error->message }}</strong>
        <p><a href="{{ url('afterpay/placeorder') }}" role="button" aria-label="Try Afterpay order again">Try again</a></p>
    @elseif($type == 'order')
        <h3 id="order-heading">Order Record Created</h3>
        <ul aria-labelledby="order-heading">
            <li>Order ID: {{ $order->id }}</li>
            <li>Status: {{ $order->status }}</li>
            <li>Is Approved? {!! $isApproved !!}</li>
        </ul>
        @if($isApproved == 'YES')
            <p>
                <a href="{{ url('afterpay/dopayment/'.$order->id) }}" role="button" aria-label="Capture Payment for this order">Capture Payment for this order</a>
            </p>
            <p>
                <a href="{{ url('afterpay/void/'.$order->id) }}" role="button" aria-label="Void Payment for this order">Void Payment for this order</a>
            </p>
        @else
            <p>
                <a href="{{ url('afterpay/placeorder') }}" role="button" aria-label="Start Afterpay order again">Start again</a>
            </p>
        @endif
    @else
        <h3 id="deferred-heading">Deferred Payment Auth</h3>
        <form method="POST" aria-labelledby="deferred-heading">
            <div>
                <label for="redirectReturnUrl">Return here after checkout:</label>
                <input type="text" name="redirectReturnUrl" id="redirectReturnUrl" value="<?php echo 'http' . ((! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 's' : '') . '://' . htmlspecialchars($_SERVER['HTTP_HOST']) . (strstr($_SERVER['REQUEST_URI'], '?') ? substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')) : $_SERVER['REQUEST_URI']); ?>">
            </div>
            <div>
                <button type="submit" aria-label="Proceed to Afterpay">Proceed to Afterpay</button>
            </div>
        </form>
    @endif
    </main>
</body>
</html>