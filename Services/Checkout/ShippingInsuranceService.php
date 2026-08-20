<?php

namespace App\Services\Checkout;

use Illuminate\Support\Facades\Session;

class ShippingInsuranceService
{
    public function __construct(
        protected CheckoutTotalsService $checkoutTotalsService
    ) {
    }

    /**
     * Calculate / set shipping insurance charge.
     *
     * Migration of:
     * CartTrait::SetShippingInsuranceCharge()
     *
     * Existing business logic is intentionally preserved.
     *
     * @param string $action
     * @param float $netTotal
     * @param string $phoneOrder
     * @return float|void
     */
    public function calculate(
        string $action = 'add',
        float $netTotal = 0,
        string $phoneOrder = 'No',
        bool $persistPreference = true
    ) {
        addLog(
            'SetShippingInsuranceChargeStart',
            [
                'action' =>
                    $action,

                'NetTotal' =>
                    $netTotal,

                'phoneOrder' =>
                    $phoneOrder,
            ]
        );

        /*
         * ---------------------------------------------------------
         * Persist the customer's explicit Insurance ON/OFF choice.
         *
         * This is the source of truth for checkout refreshes.
         *
         * add    => customer explicitly enabled Insurance
         * remove => customer explicitly disabled Insurance
         *
         * A missing preference keeps the existing default behavior:
         * Insurance is enabled.
         * ---------------------------------------------------------
         */
        if ($action === 'remove') {
            Session::put(
                'ShoppingCart.ShippingInsuranceEnabled',
                false
            );

            /*
             * Removing Insurance must immediately remove the
             * previously calculated charge.
             */
            if ($phoneOrder === 'No') {
                Session::forget(
                    'shipping_insurance_charge'
                );
            }

            return 0;
        }

        if (
            $action === 'add'
            &&
            $persistPreference
        ) {
            /*
             * Only an explicit customer "add" request changes
             * the persisted preference. Refresh/recalculation
             * calls pass false so an OFF choice stays OFF.
             */
            Session::put(
                'ShoppingCart.ShippingInsuranceEnabled',
                true
            );
        }

        /*
         * ---------------------------------------------------------
         * Refresh protection.
         *
         * CheckoutService / shipping refresh may call calculate('add')
         * automatically. If the customer previously selected OFF,
         * do NOT turn Insurance back on.
         * ---------------------------------------------------------
         */
        $insuranceEnabled =
            Session::get(
                'ShoppingCart.ShippingInsuranceEnabled',
                true
            );

        if (
            $action === 'add' &&
            $insuranceEnabled === false
        ) {
            if ($phoneOrder === 'No') {
                Session::forget(
                    'shipping_insurance_charge'
                );
            }

            return 0;
        }

        /*
         * ---------------------------------------------------------
         * Existing behavior:
         *
         * Normal checkout clears the old insurance before
         * recalculating it.
         * ---------------------------------------------------------
         */
        if (
            $phoneOrder === 'No'
        ) {
            Session::forget(
                'shipping_insurance_charge'
            );
        }

        /*
         * ---------------------------------------------------------
         * Store order:
         * No shipping insurance.
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.OrderType'
            )
            &&
            Session::get(
                'ShoppingCart.OrderType'
            ) === 'Store'
        ) {
            return 0;
        }

        /*
         * ---------------------------------------------------------
         * Pickup / shipping method 46:
         * No shipping insurance.
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.Shipping.ShippingMethodID'
            )
            &&
            Session::get(
                'ShoppingCart.Shipping.ShippingMethodID'
            ) == 46
        ) {
            return 0;
        }

        /*
         * Only the supported "add" action reaches the calculation.
         * Unknown actions keep the existing no-op behavior.
         */
        if ($action !== 'add') {
            return null;
        }

        /*
         * ---------------------------------------------------------
         * Normal checkout:
         *
         * Existing code gets NetTotal and then removes Tax
         * because insurance is calculated on the amount before tax.
         * ---------------------------------------------------------
         */
        if (
            $phoneOrder === 'No'
        ) {
            $taxAmount = 0;

            if (
                Session::has(
                    'ShoppingCart.Tax'
                )
                &&
                Session::get(
                    'ShoppingCart.Tax'
                ) > 0
            ) {
                $taxAmount =
                    (float)
                    Session::get(
                        'ShoppingCart.Tax'
                    );
            }

            /*
             * IMPORTANT:
             *
             * This should use the existing CheckoutTotalsService
             * once the complete checkout flow is connected.
             *
             * For now we read the existing session value if it
             * exists, otherwise calculate from current checkout
             * components.
             */
            $netTotal =
                $this->getNetTotal();

            if (
                $taxAmount > 0
            ) {
                $netTotal -=
                    $taxAmount;
            }
        }

        /*
         * ---------------------------------------------------------
         * Shipping insurance formula
         *
         * <= $99:
         *     $1.55
         *
         * > $99:
         *     NetTotal * 2% + $1.55
         * ---------------------------------------------------------
         */
        $shippingInsurance = 0;

        if (
            $netTotal > 0
        ) {
            if (
                $netTotal <= 99
            ) {
                $shippingInsurance = 1.55;
            } else {
                $shippingInsurance =
                    NumberFormat(
                        (
                            $netTotal
                            * 0.02
                        )
                        +
                        1.55
                    );
            }
        }

        /*
         * ---------------------------------------------------------
         * Normal checkout:
         * Store insurance in session.
         * ---------------------------------------------------------
         */
        if (
            $phoneOrder === 'No'
        ) {
            Session::put(
                'shipping_insurance_charge',
                NumberFormat(
                    $shippingInsurance
                )
            );

            return NumberFormat(
                $shippingInsurance
            );
        }

        /*
         * ---------------------------------------------------------
         * Phone order:
         * Return insurance amount instead of storing it.
         * ---------------------------------------------------------
         */
        if (
            $phoneOrder === 'Yes'
        ) {
            return NumberFormat(
                $shippingInsurance
            );
        }

        return null;
    }

    /**
     * Get the current NetTotal used for insurance calculation.
     *
     * Existing SetShippingInsuranceCharge() calls:
     *
     *     $this->GetNetTotal();
     *
     * We intentionally avoid injecting CheckoutTotalsService here
     * until the final checkout dependency graph is connected.
     */
    protected function getNetTotal(): float
    {
        /*
         * Shipping insurance is calculated from the checkout amount
         * before Sales Tax and Shipping Insurance are included.
         *
         * CheckoutTotalsService owns the common subtotal/charges/
         * discount calculation, so do not duplicate that logic here.
         */
        return $this->checkoutTotalsService
            ->getNetTotalExcludingCharges([
                'Tax',
                'ShippingInsurance',
            ]);
    }

}
