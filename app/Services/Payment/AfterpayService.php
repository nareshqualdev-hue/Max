<?php

namespace App\Services\Payment;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Traits\EncryptTrait;
use App\Http\Controllers\Traits\CartTrait;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use Afterpay\SDK\HTTP\Request\Ping as AfterpayPingRequest;

use Afterpay\SDK\Exception\NetworkException as AfterpayNetworkException;
use Afterpay\SDK\Exception\ParsingException as AfterpayParsingException;

use Afterpay\SDK\Config as AfterpayConfig;
use Afterpay\SDK\MerchantAccount as AfterpayMerchantAccount;
use Afterpay\SDK\PersistentStorage as AfterpayPersistentStorage;
use Afterpay\SDK\HTTP\Request\GetConfiguration as AfterpayGetConfigurationRequest;

use Afterpay\SDK\Exception\InvalidModelException as AfterpayInvalidModelException;
use Afterpay\SDK\HTTP\Request\CreateCheckout as AfterpayCreateCheckoutRequest;
use Afterpay\SDK\HTTP\Request\GetCheckout as AfterpayGetCheckoutRequest;
use Afterpay\SDK\Model\Consumer as AfterpayConsumer;
use Afterpay\SDK\Model\Money as AfterpayMoney;

use Afterpay\SDK\HTTP\Request\DeferredPaymentAuth as AfterpayDeferredPaymentAuthRequest;

use Afterpay\SDK\Helper\StringHelper as AfterpayStringHelper;
use Afterpay\SDK\Model\Payment as AfterpayPayment;
use Afterpay\SDK\HTTP\Request\DeferredPaymentCapture as AfterpayDeferredPaymentCaptureRequest;
use Afterpay\SDK\HTTP\Request\ImmediatePaymentCapture as AfterpayImmediatePaymentCapture;

class AfterpayService
{
    use EncryptTrait;
    use CartTrait;
    protected array $config = [];
    protected string $transactionMode = 'sandbox';
    protected string $paymentUrl = '';
    protected string $tokenJsUrl = '';
    public function __construct()
    {
        $this->loadConfiguration();
    }
    /** * Load Afterpay merchant configuration. */
    protected function loadConfiguration(): void
    {
        $paymentMethod = PaymentMethod::query()->select('pm_group_name', 'pm_gateway_name', 'pm_details')
            ->where('pm_group_name', 'PAYMENT_PAYWITHAFTERPAY')->where('pm_status', 'Active')->first();
        if (!$paymentMethod) {
            throw new \RuntimeException('Afterpay payment method is not configured.');
        }
        //Sandbox Details
        $paymentMethod->pm_details = 'a:6:{s:27:"PaywithAfterpay_Merchant_ID";s:16:"6+/un9C1fWJXNwA=";s:35:"PaywithAfterpay_Merchant_Secret_Key";s:144:"DcpbEsIgDADA6zu8EopYb+IASQPpbarSHkH3e3eHHWoFXrewBFBLb4q+A56KUw5Sm1ZhDBzbyF7VuFq8Lzfky/VWJC3PhyvGD+WAkHrErwEhylcjmiLZ7ZqO2LY7Z339k53V4oAxmjmPT/gB";s:36:"PaywithAfterpay_Header_Authorization";s:244:"BcHJCoJAAABQJOjXszKtY4IkRc6kDokOpJ1qRiRbXVDTKKx7tFide0+byik49feqlcaIYgYYO1bho7b9ezezKue1wTqzHIWqo72AxHEF4ycBfrxwgi2bB2cODegKvIZ0kt89pMo14IUNxVgf9DJ0j7trrJm4Cxnagn6U6NSpoBQG0ChaqP5ws9rN1Tq4hzcigduLYKuEwXLCmemX2PWSzcuCzBJDAB9zCf2iiyQ4B1JETato/AE=";s:33:"PaywithAfterpay_Header_User_Agent";s:152:"AWsAlP+h0NLTyfR4p8fU8MXMhZC3inmwnIaf+MTM8f2776nYxr/Pxc7M9HilvMzui4+Ej7eKlKinoay4jo28uYjFqdW/0L7HwMj7h4iLj7qMjo+SuYN58MvNzPyRjrv+wPn5trvEz8TRyujRhb7O9g==";s:32:"PaywithAfterpay_Transaction_Mode";s:7:"Sandbox";s:29:"PaywithAfterpay_Currency_Code";s:3:"USD";}';
        //Sandbox Details
        $details = unserialize($paymentMethod->pm_details);
        $this->config = [
            'merchant_id' => $this->decrypt($details['PaywithAfterpay_Merchant_ID']),
            'secret_key' => $this->decrypt($details['PaywithAfterpay_Merchant_Secret_Key']),
            'authorization' => $this->decrypt($details['PaywithAfterpay_Header_Authorization']),
            'user_agent' => $this->decrypt($details['PaywithAfterpay_Header_User_Agent']),
            'currency' => $details['PaywithAfterpay_Currency_Code'] ?? 'USD'
        ];
        if (strtoupper(trim($details['PaywithAfterpay_Transaction_Mode'] ?? '')) === 'SANDBOX') {
            $this->transactionMode = 'sandbox';
            $this->paymentUrl = 'https://api.us-sandbox.afterpay.com/v2/';
            $this->tokenJsUrl = 'https://portal.sandbox.afterpay.com/afterpay.js';
        } else {
            $this->transactionMode = 'production';
            $this->paymentUrl = 'https://api.us.afterpay.com/v2/';
            $this->tokenJsUrl = 'https://portal.afterpay.com/afterpay.js';
        }
    }

    public function createExpressCheckout($CheckoutData=[]): array
    {
        $token = '';
        $message = '';
        /*
        * Get current checkout amount.
        *
        * IMPORTANT:
        * Use the same checkout/cart calculation method that
        * SetAfterpay() currently uses.
        */
        $totals = $CheckoutData['checkout']['totals'];
        $checkoutNetTotal = (float) ($totals['NetTotal'] ?? 0);
        $paymentAmount = NumberFormat($checkoutNetTotal);

        $paymentCurrency = 'USD';

        /*
        * Merchant
        */
        $merchant = new AfterpayMerchantAccount();

        $merchant->setMerchantId($this->config['merchant_id'])
            ->setSecretKey($this->config['secret_key'])
            ->setApiEnvironment($this->transactionMode)
            ->setCountryCode('US');

        $getConfigurationRequest = new AfterpayGetConfigurationRequest();

		$getConfigurationRequest->setMerchantAccount($merchant);

		$getConfigurationRequest->send();

		$body = $getConfigurationRequest->getResponse()->getParsedBody();

        /*
        * Consumer
        */
        $setConsumer = [];

        if (
            Session::has('sess_icustomerid') &&
            Session::get('sess_icustomerid') > 0
        ) {
            $customer = Customer::where('customer_id', Session::get('sess_icustomerid'))->first();

            if ($customer) {
                $setConsumer = [
                    'givenNames' => $customer->first_name,
                    'surname'    => $customer->last_name,
                    'email'      => $customer->email,
                ];
            }
        }

        /*
        * Build billing/shipping/items using the SAME
        * logic currently present in SetAfterpay_Express().
        *
        * Do not duplicate different cart-building logic here.
        */
        $setBilling  = $this->getAfterpayBilling();
        $setShipping = $this->getAfterpayShipping();
        $setItems    = $this->getAfterpayItems();
        $setMerchant = $this->getAfterpayMerchant();

        \Afterpay\SDK\Model::setAutomaticValidationEnabled(false);

        $createCheckoutRequest = new AfterpayCreateCheckoutRequest();

        /*
        * EXPRESS is the important difference from normal
        * SetAfterpay().
        */
        if (!empty($setConsumer)) {
            $createCheckoutRequest->setConsumer($setConsumer);
        }

        if (!empty($setBilling) && !empty($setShipping)) {
            $createCheckoutRequest
                ->setBilling($setBilling)
                ->setShipping($setShipping);
        }

        $createCheckoutRequest
            ->setAmount($paymentAmount, $paymentCurrency)
            ->setMode("EXPRESS")
            ->setItems($setItems)
            ->setMerchant($setMerchant);
        ///dd($paymentAmount, $paymentCurrency, $setItems, $setMerchant);
        /*
        * Validate
        */
        if (!$createCheckoutRequest->isValid()) {
            return [
                'success'  => '0',
                'token'    => '',
                'redirect' => '',
                'message'  => 'Error in Processing Request, Please try again.',
            ];
        }

        /*
        * Attach merchant account
        */
        $createCheckoutRequest->setMerchantAccount($merchant);

        /*
        * Send request to Afterpay
        */
        $createCheckoutRequest->send();

        $createCheckoutResponse = $createCheckoutRequest->getResponse();

        $response = $createCheckoutResponse->getParsedBody();

        /*
        * Successful response
        */
        if (
            $createCheckoutResponse->isSuccessful() &&
            isset($response->token) &&
            $response->token != ''
        ) {
            $token = $response->token;

            $redirect = $response->redirectCheckoutUrl ?? '';

            /*
            * Keep the old behaviour.
            */
            Session::put('ShoppingCart.AfterPay.Checkout_Token',$token);
            Session::put('ShoppingCart.AfterPay.Checkout_Expires',$response->expires ?? null);
            return [
                'success'  => '1',
                'token'    => $token,
                'redirect' => $redirect,
                'expires'  => $response->expires ?? null,
                'message'  => '',
                'response' => $response,
            ];
        }

        /*
        * Failed response
        */
        return [
            'success'  => '0',
            'token'    => '',
            'redirect' => '',
            'message'  => 'Error in Processing Request, Please try again.',
            'response' => $response,
        ];
    }

    public function setToken($token="")
    {
        $merchant = new AfterpayMerchantAccount();

        $merchant->setMerchantId($this->config['merchant_id'])
            ->setSecretKey($this->config['secret_key'])
            ->setApiEnvironment($this->transactionMode)
            ->setCountryCode('US');

        $getCheckoutRequest = new AfterpayGetCheckoutRequest();
        $getCheckoutRequest->setCheckoutToken($token);
        if($getCheckoutRequest->isValid())
        {
            $getCheckoutRequest->setMerchantAccount($merchant);

            $getCheckoutRequest->send();

            $getCheckoutResponse = $getCheckoutRequest->getResponse();
            $response = $getCheckoutResponse->getParsedBody();

            return [
                'success' => true,
                'response' => $response,
                'isSuccessful' => $getCheckoutResponse->isSuccessful()
            ];
        } else {
            return [
                'success' => false
            ];
        }
    }

    public function isCheckoutTokenExpired(?string $expires): bool
    {
        if (empty($expires)) {
            return true;
        }

        return Carbon::parse($expires)->isPast();
    }
    protected function getAfterpayMerchant(): array
    {
        return ['popupOriginUrl' => request()->headers->get('referer')];
        //return ['popupOriginUrl' => url('afterpay/success'), 'redirectCancelUrl' => url('afterpay/cancel'),];
    }

    protected function getAfterpayBilling(): array
    {
        $address = Session::get('ShoppingCart.BillingAddress');
        if (empty($address)) {
            return [];
        }
        return [
            'name' => $this->transliterate($address['first_name']) . ' ' . $this->transliterate($address['last_name']),
            'line1' => $this->transliterate($address['address1']),
            'area1' => $address['city'],
            'region' => $address['state'],
            'postcode' => $address['zip'],
            'countryCode' => $address['country'],
            'phoneNumber' => $address['phone']
        ];
    }

    protected function getAfterpayShipping(): array
    {
        $address = Session::get('ShoppingCart.ShippingAddress');
        if (empty($address)) {
            return [];
        }

        return [
            'name' => $this->transliterate($address['first_name']). ' '. $this->transliterate($address['last_name']),
            'line1' => $this->transliterate($address['address1']),
            'area1' => $address['city'],
            'region' => $address['state'],
            'postcode' => $address['zip'],
            'countryCode' => $address['country'],
            'phoneNumber' => $address['phone'],
        ];
    }

    protected function getAfterpayItems(): array
    {
        $items = [];

        $shopCart = Session::get('ShoppingCart.Cart', []);

        foreach ($shopCart as $cartItem)
        {
            if(isset($cartItem['IS_Free_Gift']) && $cartItem['IS_Free_Gift'] === 'Yes')
            {
                $itemPrice = $cartItem['TotPrice'];

            } elseif(isset($cartItem['Is_Free_Sample']) && $cartItem['Is_Free_Sample'] === 'Yes')
            {
                $itemPrice = $cartItem['TotPrice'];

            } else {
                $itemPrice = $cartItem['ItemPrice'];
            }

            $items[] = [
                'name'     => $cartItem['ProductName'],
                'sku'      => $cartItem['SKU'],
                'quantity' => $cartItem['Qty'],
                'pageUrl'  => $cartItem['Prod_URL'],
                'price'    => [$itemPrice, 'USD'],
            ];
        }

        return $items;
    }

    /** * Get Afterpay configuration. */
    public function getConfiguration()
    {
        $merchant = $this->merchantAccount();
        $request = new AfterpayGetConfigurationRequest();
        $request->setMerchantAccount($merchant);
        $request->send();
        return $request->getResponse();
    }
    /** * Create Afterpay merchant account. */
    protected function merchantAccount(): AfterpayMerchantAccount
    {
        $merchant = new AfterpayMerchantAccount();
        $merchant->setMerchantId($this->config['merchant_id'])
            ->setSecretKey($this->config['secret_key'])
            ->setApiEnvironment($this->transactionMode)
            ->setCountryCode('US');
        return $merchant;
    }
    /** * Create normal Afterpay checkout. * * Returns: * * [ * 'success' => true, * 'token' => '...', * 'redirect_url' => '...', * 'expires' => '...', * 'response' => object * ] */
    public function createCheckout(int $orderId, array $options = []): array
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return $this->failure('Order not found.');
        }
        $amount = NumberFormat($options['amount'] ?? $this->GetNetTotal());
        $currency = $options['currency'] ?? $this->config['currency'] ?? 'USD';
        $consumer = $this->buildConsumer($order);
        if (!$consumer) {
            return $this->failure('Customer information is not available.');
        }
        $billing = $this->buildBilling($order);
        $shipping = $this->buildShipping($order);
        $items = $this->buildItems($orderId);
        $merchantData = ['popupOriginUrl' => $options['popupOriginUrl'] ?? url('/'), 'redirectConfirmUrl' => $options['redirectConfirmUrl'] ?? url('afterpay/success'), 'redirectCancelUrl' => $options['redirectCancelUrl'] ?? url('afterpay/cancel'),];
        $mode = $options['mode'] ?? null;
        \Afterpay\SDK\Model::setAutomaticValidationEnabled(false);
        $request = new AfterpayCreateCheckoutRequest();
        $request->setAmount($amount, $currency)->setConsumer($consumer)->setBilling($billing)->setShipping($shipping)->setItems($items)->setMerchant($merchantData);
        if ($mode) {
            $request->setMode($mode);
        }
        if (!$request->isValid()) {
            return $this->failure('Invalid Afterpay checkout request.', ['validation_errors' => $request->getValidationErrorsAsHtml(),]);
        }
        $request->setMerchantAccount($this->merchantAccount());
        $request->send();
        $response = $request->getResponse();
        $body = $response->getParsedBody();
        if (!$response->isSuccessful()) {
            return $this->failure('Afterpay checkout request failed.', ['response' => $body,]);
        }
        if (!isset($body->token) || empty($body->token)) {
            return $this->failure('Afterpay did not return a checkout token.', ['response' => $body,]);
        }
        return ['success' => true, 'token' => $body->token, 'redirect_url' => $body->redirectCheckoutUrl ?? null, 'expires' => $body->expires ?? null, 'response' => $body,];
    }
    /** * Authorize an Afterpay checkout token. */
    public function authorize(string $orderToken): array
    {
        if (empty($orderToken)) {
            return $this->failure('Afterpay order token is required.');
        }
        $request = new AfterpayDeferredPaymentAuthRequest(['token' => urlencode($orderToken),]);
        $request->setMerchantAccount($this->merchantAccount());
        $request->send();
        $response = $request->getResponse();
        $body = $response->getParsedBody();
        $approved = $response->isApproved() && isset($body->paymentState) && $body->paymentState === 'AUTH_APPROVED';
        return ['success' => $approved, 'approved' => $approved, 'response' => $body, 'http_status' => $response->getHttpStatusCode(),];
    }
    /** * Capture an authorized Afterpay payment. */
    public function capture(string $afterpayOrderId, float|string $amount, int $orderId): array
    {
        if (empty($afterpayOrderId) || !is_numeric($orderId)) {
            return $this->failure('Invalid Afterpay capture information.');
        }
        $currency = $this->config['currency'] ?? 'USD';
        $requestId = AfterpayStringHelper::generateUuid();
        $request = new AfterpayDeferredPaymentCaptureRequest(['requestId' => $requestId, 'amount' => [NumberFormat($amount), $currency,],]);
        $request->setOrderId($afterpayOrderId);
        $request->setMerchantAccount($this->merchantAccount());
        $request->setMerchantReference('OR' . $orderId);
        $request->send();
        $response = $request->getResponse();
        $body = $response->getParsedBody();
        $captured = isset($body->status) && $body->status === 'APPROVED' && isset($body->paymentState) && in_array($body->paymentState, ['CAPTURED', 'PARTIALLY_CAPTURED',], true);
        return ['success' => $captured, 'captured' => $captured, 'response' => $body, 'http_status' => $response->getHttpStatusCode(),];
    }
    /** * Get order. */
    protected function getOrder(int $orderId)
    {
        return DB::table('pu_orders as o')->leftJoin('pu_customer as c', 'o.customer_id', '=', 'c.customer_id')->select('o.*', 'c.first_name as customer_first_name', 'c.last_name as customer_last_name', 'c.email as customer_email')->where('o.orders_id', $orderId)->first();
    }
    /** * Build Afterpay consumer. */
    protected function buildConsumer($order): array
    {
        $firstName = $order->customer_first_name ?? $order->bill_first_name ?? '';
        $lastName = $order->customer_last_name ?? $order->bill_last_name ?? '';
        $email = $order->customer_email ?? $order->bill_email ?? '';
        if (empty($email)) {
            return [];
        }
        return ['givenNames' => $this->transliterate($firstName), 'surname' => $this->transliterate($lastName), 'email' => $email,];
    }
    /** * Build billing address. */
    protected function buildBilling($order): array
    {
        return [
            'name' => $this->transliterate(trim($order->bill_first_name . ' ' . $order->bill_last_name)),
            'line1' => $this->transliterate($order->bill_address1 ?? ''),
            'area1' => $order->bill_city ?? '',
            'region' => $order->bill_state ?? '',
            'postcode' => $order->bill_zip ?? '',
            'countryCode' => $this->normalizeCountry($order->bill_country ?? ''),
            'phoneNumber' => $order->bill_phone ?? ''
        ];
    }
    /** * Build shipping address. */
    protected function buildShipping($order): array
    {
        return [
            'name' => $this->transliterate(trim($order->ship_first_name . ' ' . $order->ship_last_name)),
            'line1' => $this->transliterate($order->ship_address1 ?? ''),
            'area1' => $order->ship_city ?? '',
            'region' => $order->ship_state ?? '',
            'postcode' => $order->ship_zip ?? '',
            'countryCode' => $this->normalizeCountry($order->ship_country ?? ''),
            'phoneNumber' => $order->ship_phone ?? ''
        ];
    }
    /** * Build checkout items. */
    protected function buildItems(int $orderId): array
    {
        $details = OrderDetail::where('orders_id', $orderId)->get();
        $items = [];
        foreach ($details as $detail) {
            $product = DB::table('pu_products as p')->join('pu_products_category as pc', 'p.products_id', '=', 'pc.products_id')->select('p.products_id', 'p.product_name', 'p.sku', 'pc.category_id')->where('p.sku', $detail->sku)->where('p.status', '1')->first();
            if (!$product) {
                continue;
            }
            $productUrl = SetProductURL($product->products_id, $product->product_name, $product->category_id);
            $itemName = str_replace('<', ' <', $detail->product_name);
            $itemName = strip_tags($itemName);
            $itemName = preg_replace('/\s+/', ' ', $itemName);
            $items[] = ['name' => $itemName, 'sku' => $detail->sku, 'quantity' => (int) $detail->quantity, 'pageUrl' => $productUrl, 'price' => [NumberFormat($detail->price), $this->config['currency'] ?? 'USD',],];
        }
        return $items;
    }
    /** * Normalize country code. */
    protected function normalizeCountry(string $country): string
    {
        return strtoupper($country) === 'UK' ? 'GB' : strtoupper($country);
    }
    /** * Transliterate string. */
    public function transliterate(string $string): string
    {
        if ($string === '') {
            return '';
        }
        if (!function_exists('transliterator_transliterate')) {
            return $string;
        }
        $latin = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
        return strtr($latin, ['ʿ' => 'a', '`' => '',]);
    }
    /** * Common failure response. */
    protected function failure(string $message, array $data = []): array
    {
        return array_merge(['success' => false, 'message' => $message,], $data);
    }
    public function getTransactionMode(): string
    {
        return $this->transactionMode;
    }
    public function getPaymentUrl(): string
    {
        return $this->paymentUrl;
    }
    public function getTokenJsUrl(): string
    {
        return $this->tokenJsUrl;
    }
    public function getCurrency(): string
    {
        return $this->config['currency'] ?? 'USD';
    }
}
