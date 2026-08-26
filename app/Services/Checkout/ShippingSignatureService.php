<?php

namespace App\Services\Checkout;

use Illuminate\Support\Facades\Session;

class ShippingSignatureService
{
    public function __construct(
        protected CheckoutTotalsService $checkoutTotalsService
    ) {
    }

	public function calculate(string $action = 'add'): float
{
    $action = strtolower(trim($action));

    if (!in_array($action, ['add', 'remove'], true)) {
        throw new \InvalidArgumentException(
            'Invalid shipping signature action.'
        );
    }

    if ($action === 'remove') {
        $this->remove();

        return 0.0;
    }

    if (!$this->isEligibleCustomer()) {
        $this->remove();

        return 0.0;
    }

    $shippingMethodId = (int) Session::get(
        'ShoppingCart.Shipping.ShippingMethodID',
        0
    );

    /*
     * Existing store-pickup behavior.
     */
    if ($shippingMethodId === 46) {
        $this->remove();

        return 0.0;
    }

    /*
     * Use the existing configured Signature charge.
     *
     * Normal customer:
     *     Settings.SHIPPING_SIGNATURE
     *
     * Dropshipper:
     *     Settings.DROPSHIPPER_SHIPPING_SIGNATURE
     */
    $charge =
        $this->getConfiguredCharge();

    if ($charge <= 0) {
        $this->remove();

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * LEGACY TRUTH MODE
     * ---------------------------------------------------------
     *
     * Old checkout behavior:
     *
     * InsureAmount =
     *     NetTotal
     *     - Shipping Insurance
     *     - Shipping Signature
     *
     * If InsureAmount <= $200:
     *     Signature uses the configured charge.
     *
     * If InsureAmount > $200:
     *     Signature is removed.
     */
    $eligibleAmount =
        $this->getSignatureEligibleAmount();

    if ($eligibleAmount <= 200) {

        Session::put(
            'ShoppingCart.ShippingSignature',
            $charge
        );

        addLog('SetShippingSignature', [
            'charge' =>
                NumberFormat($charge),

            'eligible_amount' =>
                NumberFormat($eligibleAmount),

            'reason' =>
                'signature_charge_under_or_equal_200',
        ]);

        return (float) $charge;
    }

    /*
     * Above $200 the old checkout removes Signature.
     */
    $this->remove();

    addLog('RemoveShippingSignature', [
        'reason' =>
            'eligible_amount_over_200',

        'eligible_amount' =>
            NumberFormat($eligibleAmount),
    ]);

    return 0.0;
}
    public function remove(): void
    {
        Session::forget(
            'ShoppingCart.ShippingSignature'
        );

        addLog('RemoveShippingSignature', [
            'charge' => 0,
        ]);
    }

    /**
     * Only clears an already-applied signature if it is no longer valid.
     * It never turns signature on by itself.
     */
    public function sync(): float
    {
        if (!Session::has('ShoppingCart.ShippingSignature')) {
            return 0.0;
        }

        if (!$this->isEligibleCustomer()) {
            $this->remove();
            return 0.0;
        }

        if (
            (int) Session::get(
                'ShoppingCart.Shipping.ShippingMethodID',
                0
            ) === 46
        ) {
            $this->remove();
            return 0.0;
        }

        /*
         * Legacy Truth Mode:
         * An already-applied signature is valid only when the
         * signature eligible amount is <= $200.
         */
        $eligibleAmount =
            $this->getSignatureEligibleAmount();

        if ($eligibleAmount <= 200) {
            Session::put(
                'ShoppingCart.ShippingSignature',
                0
            );

            return 0.0;
        }

        $this->remove();

        addLog('RemoveShippingSignature', [
            'reason' => 'eligible_amount_over_200',
            'eligible_amount' =>
                NumberFormat($eligibleAmount),
        ]);

        return 0.0;
    }

    public function getConfiguredCharge(): float
    {
        if (Session::get('is_dropshipper') === 'Yes') {
            return (float) config(
                'Settings.DROPSHIPPER_SHIPPING_SIGNATURE',
                0
            );
        }

        return (float) config(
            'Settings.SHIPPING_SIGNATURE',
            0
        );
    }

    public function isEligibleCustomer(): bool
    {
        $address = Session::get(
            'ShoppingCart.ShippingAddress',
            []
        );

        $country = strtoupper(trim(
            (string) (
                $address['country']
                ?? Session::get(
                    'ShoppingCart.Shipping.country',
                    ''
                )
            )
        ));

        if ($country !== 'US') {
            return false;
        }

        if (Session::get('is_dropshipper') === 'Yes') {
            return (
                (float) config(
                    'Settings.DROPSHIPPER_SHIPPING_SIGNATURE',
                    0
                ) > 0
                &&
                strtolower((string) Session::get(
                    'eusertype',
                    ''
                )) === 'wholesaler'
                &&
                Session::get('etype', '') === 'M'
            );
        }

        return (float) config(
            'Settings.SHIPPING_SIGNATURE',
            0
        ) > 0;
    }

    /**
     * Matches the legacy InsureAmount calculation:
     * NetTotal - shipping insurance - current signature.
     */
    protected function getSignatureEligibleAmount(): float
    {
        return $this->checkoutTotalsService
            ->getSignatureEligibleAmount();
    }
}
