<?php

namespace App\Services\Payment;

use App\Services\Checkout\CheckoutTotalsService;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripePaymentService
{
    protected CheckoutTotalsService $checkoutTotals;
    public function __construct(CheckoutTotalsService $checkoutTotals) {
        $this->checkoutTotals = $checkoutTotals;
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Get frontend-safe Stripe configuration.
     */
    public function config(): array
    {
        return ['publishable_key' => config('services.stripe.striptkey')];
    }

    /**
     * Create a PaymentIntent and confirm it using
     * a Stripe PaymentMethod created by Stripe.js.
     *
     * Laravel receives ONLY the PaymentMethod ID.
     */
    public function pay(
        string $paymentMethodId
    ): array {

        if (
            trim($paymentMethodId) === ''
        ) {
            throw new RuntimeException(
                'Payment method is required.'
            );
        }

        /*
         * IMPORTANT:
         *
         * Always calculate the amount on the server.
         */
        $totals =
            $this->checkoutTotals->calculate();

        $total =
            (float) (
                $totals['NetTotal'] ?? 0
            );

        if ($total <= 0) {
            throw new RuntimeException(
                'Invalid checkout total.'
            );
        }

        $amount =
            $this->toStripeAmount($total);

        $currency =
            $this->currency();

        /*
         * Create and confirm PaymentIntent.
         */
        $intent =
            PaymentIntent::create([
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $paymentMethodId,
                /*
                 * This tells Stripe to confirm the
                 * payment immediately.
                 */
                'confirm' => true,
                /*
                 * Required for cards that need
                 * 3DS/SCA authentication.
                 *
                 * Stripe may return requires_action.
                 */
                'return_url' => route('checkout.payment.stripe.return'),
                /*
                 * Keep this enabled because the same
                 * PaymentIntent architecture will later
                 * support Apple Pay / Google Pay.
                 */
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],

                'metadata' => [
                    'source' => 'maxaroma_checkout',
                    'customer_id' => (string) Session::get('sess_icustomerid',''),
                ],
            ]);

        /*
         * Keep PaymentIntent ID in checkout session.
         */
        Session::put(
            'ShoppingCart.Stripe.PaymentIntentID',
            $intent->id
        );

        return $this->result(
            $intent
        );
    }

    /**
     * Retrieve a PaymentIntent.
     */
    public function retrieve(
        string $paymentIntentId
    ): PaymentIntent {

        $sessionIntentId =
            (string) Session::get(
                'ShoppingCart.Stripe.PaymentIntentID',
                ''
            );

        /*
         * Don't allow a customer to inspect/verify
         * an unrelated PaymentIntent.
         */
        if (
            $sessionIntentId === ''
            ||
            !hash_equals(
                $sessionIntentId,
                $paymentIntentId
            )
        ) {
            throw new RuntimeException(
                'Invalid payment session.'
            );
        }

        return PaymentIntent::retrieve(
            $paymentIntentId
        );
    }

    /**
     * Verify a payment after Stripe.js
     * completes authentication.
     */
    public function verify(
        string $paymentIntentId
    ): array {

        $intent =
            $this->retrieve(
                $paymentIntentId
            );

        /*
         * Verify amount again.
         */
        $totals =
            $this->checkoutTotals->calculate();

        $expectedAmount =
            $this->toStripeAmount(
                (float) (
                    $totals['NetTotal'] ?? 0
                )
            );

        if (
            (int) $intent->amount
            !== $expectedAmount
        ) {
            throw new RuntimeException(
                'Payment amount does not match checkout.'
            );
        }

        /*
         * Verify currency.
         */
        if (
            strtolower(
                (string) $intent->currency
            )
            !== strtolower(
                $this->currency()
            )
        ) {
            throw new RuntimeException(
                'Payment currency does not match checkout.'
            );
        }

        return $this->result(
            $intent
        );
    }

    /**
     * Convert decimal amount to Stripe amount.
     *
     * Example:
     *
     * 100.50 => 10050
     */
    protected function toStripeAmount(
        float $amount
    ): int {

        return (int) round(
            $amount * 100
        );
    }

    /**
     * Current checkout currency.
     *
     * Replace the session key with your existing
     * MaxAroma currency implementation if required.
     */
    protected function currency(): string
    {
        return strtolower(
            (string) Session::get(
                'currency_code',
                'usd'
            )
        );
    }

    /**
     * Normalize PaymentIntent response.
     */
    protected function result(
        PaymentIntent $intent
    ): array {

        $result = [
            'success' => false,

            'status' =>
                $intent->status,

            'payment_intent_id' =>
                $intent->id,
        ];

        /*
         * Payment completed.
         */
        if (
            $intent->status === 'succeeded'
        ) {
            $result['success'] = true;

            return $result;
        }

        /*
         * Stripe requires customer authentication.
         *
         * Stripe.js will handle this.
         */
        if (
            $intent->status
            === 'requires_action'
        ) {
            $result[
                'requires_action'
            ] = true;

            /*
             * client_secret is safe to send to
             * the browser.
             *
             * NEVER send the Stripe secret key.
             */
            $result[
                'client_secret'
            ] =
                $intent->client_secret;

            return $result;
        }

        /*
         * Payment failed or requires another
         * payment method.
         */
        $result['message'] =
            $intent
                ->last_payment_error
                ->message
                ??
                'Payment could not be completed.';

        return $result;
    }
}