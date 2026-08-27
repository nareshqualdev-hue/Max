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

    /*
     * ---------------------------------------------------------
     * Explicit user REMOVE
     * ---------------------------------------------------------
     */
    if ($action === 'remove') {

        $this->remove();

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * Customer eligibility
     * ---------------------------------------------------------
     *
     * Keep existing customer eligibility rules.
     */
    if (!$this->isEligibleCustomer()) {

        $this->remove();

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * Store pickup
     * ---------------------------------------------------------
     *
     * Existing pickup behavior remains unchanged.
     */
    $shippingMethodId = (int) Session::get(
        'ShoppingCart.Shipping.ShippingMethodID',
        0
    );

    if ($shippingMethodId === 46) {

        $this->remove();

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * Current configured Shipping Signature charge
     * ---------------------------------------------------------
     */
    $charge =
        $this->getConfiguredCharge();

    if ($charge <= 0) {

        $this->remove();

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * Apply configured Signature charge
     * ---------------------------------------------------------
     *
     * IMPORTANT:
     *
     * Shipping Signature is a selectable checkout add-on.
     *
     * When customer explicitly adds it, keep the configured
     * charge in session.
     *
     * Do not recalculate/remove it from the order amount here.
     */
    Session::put(
        'ShoppingCart.ShippingSignature',
        $charge
    );

    addLog(
        'SetShippingSignature',
        [
            'charge' =>
                NumberFormat($charge),

            'shipping_method_id' =>
                $shippingMethodId,

            'reason' =>
                'configured_shipping_signature_charge',
        ]
    );

    return (float) $charge;
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
    /*
     * Signature is currently OFF.
     *
     * Do not automatically enable it.
     */
    if (
        !Session::has(
            'ShoppingCart.ShippingSignature'
        )
    ) {
        return 0.0;
    }

    /*
     * Existing customer eligibility.
     */
    if (!$this->isEligibleCustomer()) {

        $this->remove();

        return 0.0;
    }

    /*
     * Existing store pickup behavior.
     *
     * Keep this rule untouched.
     */
    $shippingMethodId = (int) Session::get(
        'ShoppingCart.Shipping.ShippingMethodID',
        0
    );

    if ($shippingMethodId === 46) {

        $this->remove();

        return 0.0;
    }

    /*
     * Current configured Signature charge.
     */
    $charge =
        $this->getConfiguredCharge();

    if ($charge <= 0) {

        $this->remove();

        return 0.0;
    }

    /*
     * Signature was already selected by the customer.
     *
     * Shipping method refresh must preserve it.
     *
     * Do NOT re-run the $200 eligibility rule here.
     */
    Session::put(
        'ShoppingCart.ShippingSignature',
        $charge
    );

    addLog(
        'SyncShippingSignature',
        [
            'charge' =>
                NumberFormat($charge),

            'shipping_method_id' =>
                $shippingMethodId,

            'reason' =>
                'existing_signature_preserved',
        ]
    );

    return (float) $charge;
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
