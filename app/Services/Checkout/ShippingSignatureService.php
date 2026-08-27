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
     * $200+ = FREE Shipping Signature
     * ---------------------------------------------------------
     *
     * IMPORTANT:
     *
     * Do not call getSignatureEligibleAmount() here because
     * that method calls getTotals(), which calls calculate().
     *
     * Therefore calculate the same legacy eligible amount
     * without including Shipping Signature / Insurance.
     */
    $eligibleAmount =
        $this->checkoutTotalsService
            ->getNetTotalExcludingCharges([
                'ShippingInsurance',
                'ShippingSignature',
            ]);

    if ($eligibleAmount >= 200) {

        /*
         * Signature remains applied, but charge is FREE.
         */
        Session::put(
            'ShoppingCart.ShippingSignature',
            0
        );

        addLog(
            'SetShippingSignature',
            [
                'charge' =>
                    0,

                'eligible_amount' =>
                    NumberFormat(
                        $eligibleAmount
                    ),

                'shipping_method_id' =>
                    $shippingMethodId,

                'reason' =>
                    'signature_free_200_plus',
            ]
        );

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * Below $200
     * ---------------------------------------------------------
     *
     * Apply the configured Signature charge.
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

            'eligible_amount' =>
                NumberFormat(
                    $eligibleAmount
                ),

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
     * ---------------------------------------------------------
     * Check $200+ eligibility.
     * ---------------------------------------------------------
     *
     * sync() can safely use getSignatureEligibleAmount()
     * because sync() itself is not called by getTotals().
     */
    $eligibleAmount =
        $this->getSignatureEligibleAmount();

    /*
     * ---------------------------------------------------------
     * $200 or more = FREE Signature
     * ---------------------------------------------------------
     *
     * Keep Signature applied, but make the charge $0.
     */
    if ($eligibleAmount >= 200) {

        Session::put(
            'ShoppingCart.ShippingSignature',
            0
        );

        addLog(
            'SyncShippingSignature',
            [
                'charge' =>
                    0,

                'eligible_amount' =>
                    NumberFormat(
                        $eligibleAmount
                    ),

                'shipping_method_id' =>
                    $shippingMethodId,

                'reason' =>
                    'signature_free_200_plus',
            ]
        );

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * Below $200
     * ---------------------------------------------------------
     *
     * Signature was already selected by customer,
     * therefore preserve the configured charge.
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

            'eligible_amount' =>
                NumberFormat(
                    $eligibleAmount
                ),

            'shipping_method_id' =>
                $shippingMethodId,

            'reason' =>
                'existing_signature_preserved_below_200',
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
