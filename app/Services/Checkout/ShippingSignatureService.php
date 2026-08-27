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
     * REMOVE
     * ---------------------------------------------------------
     *
     * Explicit user action:
     * remove Signature.
     */
    if ($action === 'remove') {
        $this->remove();

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * CUSTOMER ELIGIBILITY
     * ---------------------------------------------------------
     */
    if (!$this->isEligibleCustomer()) {
        $this->remove();

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * SHIPPING METHOD
     * ---------------------------------------------------------
     *
     * IMPORTANT:
     *
     * Shipping Method 46 = Store Pickup.
     *
     * Old checkout behavior:
     *
     *     46 -> Insurance is hidden/removed
     *     46 -> Signature is NOT removed
     *
     * Therefore DO NOT remove Signature here based on
     * ShippingMethodID == 46.
     *
     * Insurance has its own independent logic.
     * ---------------------------------------------------------
     */

    $charge = $this->getConfiguredCharge();

    if ($charge <= 0) {
        $this->remove();

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * SIGNATURE ELIGIBILITY
     * ---------------------------------------------------------
     *
     * Preserve the existing > $200 rule.
     *
     * When the eligible amount is >= $200,
     * Signature is FREE.
     *
     * Do NOT remove Signature.
     */
    $eligibleAmount =
        $this->getSignatureEligibleAmount();

    if ($eligibleAmount >= 200) {

        Session::put(
            'ShoppingCart.ShippingSignature',
            '0.00'
        );

        addLog(
            'SetShippingSignature',
            [
                'charge' => '0.00',
                'eligible_amount' =>
                    NumberFormat(
                        $eligibleAmount
                    ),
                'reason' =>
                    'eligible_amount_greater_than_or_equal_200',
            ]
        );

        return 0.0;
    }

    /*
     * ---------------------------------------------------------
     * NORMAL PAID SIGNATURE
     * ---------------------------------------------------------
     */
    Session::put(
        'ShoppingCart.ShippingSignature',
        NumberFormat($charge)
    );

    addLog(
        'SetShippingSignature',
        [
            'charge' =>
                NumberFormat($charge),

            'eligible_amount' =>
                NumberFormat(
                    $this->getSignatureEligibleAmount()
                ),
        ]
    );

    return (float) NumberFormat($charge);
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
