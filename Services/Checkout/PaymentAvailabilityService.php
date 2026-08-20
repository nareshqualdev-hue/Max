<?php

namespace App\Services\Checkout;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Session;

class PaymentAvailabilityService
{
    public function __construct(
        protected CheckoutTotalsService $checkoutTotalsService
    ) {
    }

    /**
     * Build payment availability for checkout.
     *
     * Existing payment business rules are preserved:
     *
     * - Active payment methods only
     * - PayPal Express
     * - Pay With Amazon
     * - Afterpay
     * - Existing PayPal token flow
     */
    public function getAvailability(
        float $orderTotal = 0,
        string $selectedMethod = ''
    ): array {
        /*
         * CheckoutTotalsService is the common source of truth.
         *
         * When the caller does not provide an order total, use the
         * already calculated checkout NetTotal instead of rebuilding
         * totals here.
         */
        if ($orderTotal <= 0) {
            $orderTotal =
                $this->checkoutTotalsService
                    ->getNetTotal();
        }
        $result = [
            'IsPaypalExpressCheckout' => 'No',
            'Amazon_pay_Checkout'      => 'No',
            'Afterpay_Checkout'        => 'No',

            'AmazonPayButton'          => 'No',
            'PaypalPayButton'          => 'No',

            'SelMethod'                => '',
            'is_paypal'                => 'no',
            'is_afterpay'              => 'no',

            'PaymentMethods'           => [],
        ];

        /*
         * ---------------------------------------------------------
         * Active payment methods
         * ---------------------------------------------------------
         */
        $paymentMethods =
            PaymentMethod::where(
                'pm_status',
                '=',
                'Active'
            )->get();

        $result['PaymentMethods'] =
            $paymentMethods;

        /*
         * ---------------------------------------------------------
         * PayPal Token flow
         *
         * Existing behavior:
         *
         * If PayPalToken exists, PayPal Express becomes
         * the selected payment method.
         * ---------------------------------------------------------
         */
        $hasPayPalToken =
            Session::has(
                'PayPalToken'
            )
            &&
            Session::get(
                'PayPalToken'
            ) !== '';

        if (
            $selectedMethod === 'paypal'
            ||
            $hasPayPalToken
        ) {
            $result['SelMethod'] =
                'PAYMENT_PAYPALEC';

            $result['is_paypal'] =
                'yes';

            /*
             * Existing flow replaces the allowed payment
             * option with PayPal Express.
             */
            $result['allowpaymentoption'] = [
                'PAYMENT_PAYPALEC',
            ];
        } else {
            /*
             * Existing default payment options from checkout.
             */
            $result['allowpaymentoption'] = [
                'PAYMENT_STRIPE',
                'PAYMENT_MOC',
            ];
        }

        /*
         * ---------------------------------------------------------
         * Do not inspect the normal payment methods when a
         * PayPal token is already present.
         *
         * This preserves:
         *
         * !Session::has('PayPalToken')
         * &&
         * Session::get('PayPalToken') == ''
         * ---------------------------------------------------------
         */
        if (
            $paymentMethods->isNotEmpty()
            &&
            !$hasPayPalToken
        ) {
            foreach (
                $paymentMethods as $paymentMethod
            ) {
                /*
                 * -------------------------------------------------
                 * Pay With Amazon
                 * -------------------------------------------------
                 */
                if (
                    $paymentMethod->pm_group_name
                    === 'PAYMENT_PAYWITHAMAZON'
                ) {
                    $this->configureAmazon(
                        $paymentMethod,
                        $result
                    );
                }

                /*
                 * -------------------------------------------------
                 * PayPal Express
                 * -------------------------------------------------
                 */
                if (
                    $paymentMethod->pm_group_name
                    === 'PAYMENT_PAYPALEC'
                ) {
                    /*
                     * Existing logic:
                     *
                     * if Is_WholeSaler_Allow() == false
                     *     No
                     * else
                     *     Yes
                     *
                     * The actual helper is intentionally kept
                     * behind this method.
                     */
                    if (
                        $this->isWholeSalerAllowed()
                    ) {
                        $result[
                            'IsPaypalExpressCheckout'
                        ] = 'Yes';
                    } else {
                        $result[
                            'IsPaypalExpressCheckout'
                        ] = 'No';
                    }
                }
            }
        }

        /*
         * ---------------------------------------------------------
         * Afterpay
         * ---------------------------------------------------------
         *
         * Existing SetCheckoutCommonDetails():
         *
         * Afterpay enabled
         * AND
         * order total >= min
         * AND
         * order total <= max
         *
         * => Afterpay checkout available.
         *
         * We read already prepared session min/max values here.
         */
        $result['Afterpay_Checkout'] =
            $this->resolveAfterpay(
                $orderTotal
            );

        /*
         * Existing selected Afterpay flow.
         */
        if (
            $result['Afterpay_Checkout']
                === 'Yes'
            &&
            $selectedMethod === 'AP'
            &&
            Session::has(
                'ShoppingCart.AfterPay.Checkout_Token'
            )
            &&
            Session::get(
                'ShoppingCart.AfterPay.Checkout_Token'
            ) !== ''
        ) {
            $result['allowpaymentoption'] = [
                'PAYMENT_PAYWITHAFTERPAY',
            ];

            $result['SelMethod'] =
                'PAYMENT_PAYWITHAFTERPAY';

            $result['is_afterpay'] =
                'yes';
        }

        /*
         * Buttons used by existing checkout.
         */
        $result['AmazonPayButton'] =
            $result['Amazon_pay_Checkout'];

        $result['PaypalPayButton'] =
            $result['IsPaypalExpressCheckout'];

        /*
         * Existing Afterpay session details.
         */
        $result['afterpay_checkout_token'] =
            Session::get(
                'ShoppingCart.AfterPay.Checkout_Token',
                ''
            );

        $result['show_afterpay_widget_box'] =
            $result['afterpay_checkout_token'] !== ''
                ? 'Yes'
                : 'No';

        return $result;
    }

    /**
     * Configure Amazon payment availability.
     *
     * Existing logic checks:
     *
     * - Access Key
     * - Secret Key
     * - Merchant ID
     */
    protected function configureAmazon(
        PaymentMethod $paymentMethod,
        array &$result
    ): void {
        $pmDetails =
            unserialize(
                $paymentMethod->pm_details
            );

        if (
            !is_array(
                $pmDetails
            )
        ) {
            return;
        }

        /*
         * Existing payment settings array.
         */
        $settings = [];

        foreach (
            $pmDetails as $name => $value
        ) {
            $settings[$name] =
                $value;
        }

        $accessKey =
            $settings[
                'paywithamazon_Access_Key_Id'
            ] ?? '';

        $secretKey =
            $settings[
                'paywithamazon_Secret_Key_ID'
            ] ?? '';

        $merchantId =
            $settings[
                'paywithamazon_Merchant_Id'
            ] ?? '';

        if (
            $accessKey !== ''
            &&
            $secretKey !== ''
            &&
            $merchantId !== ''
        ) {
            $result[
                'Amazon_pay_Checkout'
            ] = 'Yes';
        }

        /*
         * IMPORTANT:
         *
         * The original controller also decrypts these values
         * and sets runtime config values:
         *
         * CLIENT_ID
         * MERCHANT_ID
         * CALLBACK_URL
         * CALLBACK_CHECKOUT_URL
         * JS_SERVER_URL
         *
         * We do NOT silently duplicate decrypt() here because
         * that helper belongs to the existing EncryptTrait.
         *
         * Runtime Amazon configuration should be handled by
         * AmazonPaymentService / AmazonConfigService.
         */
    }

    /**
     * Resolve Afterpay availability from order total.
     *
     * Existing source:
     *
     * Afterpay_Checkout == Yes
     * AND
     * OrderTotal >= Min_AP_AMT
     * AND
     * OrderTotal <= Max_AP_AMT
     */
    protected function resolveAfterpay(
        float $orderTotal
    ): string {
        /*
         * Afterpay enabled flag.
         *
         * This value is prepared by the existing
         * constructfunc_afterpaydetails() flow.
         */
        $afterpayEnabled =
            Session::get(
                'Afterpay_Checkout',
                'No'
            );

        /*
         * If session does not contain the flag,
         * do not assume Afterpay is available.
         */
        if (
            $afterpayEnabled !== 'Yes'
        ) {
            return 'No';
        }

        $min =
            Session::get(
                'Afterpay.Min_AP_AMT'
            );

        $max =
            Session::get(
                'Afterpay.Max_AP_AMT'
            );

        if (
            $min === null
            ||
            $max === null
            ||
            $min === ''
            ||
            $max === ''
        ) {
            return 'No';
        }

        if (
            $orderTotal >=
                (float) $min
            &&
            $orderTotal <=
                (float) $max
        ) {
            return 'Yes';
        }

        return 'No';
    }

    /**
     * Existing Is_WholeSaler_Allow() behavior.
     *
     * We cannot safely recreate that helper without changing
     * business rules, so this is intentionally isolated.
     *
     * Replace the implementation with the existing helper
     * adapter when the old trait is removed.
     */
    protected function isWholeSalerAllowed(): bool
    {
        /*
         * The source confirms that PayPal availability depends
         * on Is_WholeSaler_Allow().
         *
         * Until that helper is migrated, defaulting to true here
         * would be a business-rule assumption.
         *
         * Therefore the safer value is false.
         */
        return false;
    }
}