<?php

namespace App\Services\Cart;

use App\Services\Discount\DiscountService;
use Illuminate\Support\Facades\Session;

class CartCalculatorService
{
    public function __construct(
        protected DiscountService $discountService
    ) {
    }

    /**
     * Recalculate cart subtotal.
     *
     * Existing CalculateSubTotal() logic preserved exactly.
     */
    public function calculateSubTotal(): void
    {
        addLog('CalculateSubTotalStart');

        if (!Session::has('ShoppingCart.Cart')) {
            return;
        }

        $shoppingCart = Session::get(
            'ShoppingCart.Cart',
            []
        );

        $subTotal = 0;
        $totalItemInCart = 0;

        foreach ($shoppingCart as $cartItem) {
            $subTotal += isset($cartItem['TotPrice'])
                ? $cartItem['TotPrice']
                : 0;

            $totalItemInCart += isset($cartItem['Qty'])
                ? $cartItem['Qty']
                : 0;
        }

        Session::put(
            'ShoppingCart.SubTotal',
            NumberFormat($subTotal)
        );

        Session::put(
            'ShoppingCart.TotalItemInCart',
            $totalItemInCart
        );

        addLog(
            'CalculateSubTotal',
            [
                'SubTotal' => NumberFormat($subTotal),
                'TotalItemInCart' => $totalItemInCart,
            ]
        );
    }

    /**
     * Existing GetAllCharges() logic.
     *
     * Supported:
     *
     * - ShippingCharge
     * - Tax
     * - GiftWrappingCharge
     * - ShippingSignature
     * - ShippingInsurance
     */
    public function getAllCharges(
        string $chargeName = ''
    ) {
        /*
         * Existing behavior:
         *
         * GetAllCharges('TaxValue')
         * actually reads the Tax charge.
         */
        if ($chargeName === 'TaxValue') {
            $chargeName = 'Tax';
        }

        $log = [
            'ChargeName' => $chargeName,
        ];

        addLog(
            'GetAllChargesStart',
            $log
        );

        $charges = [];

        /*
         * ---------------------------------------------------------
         * Shipping
         * ---------------------------------------------------------
         */
        $shippingCharge = Session::get(
            'ShoppingCart.Shipping.ShippingCharge'
        );

        if (
            Session::has(
                'ShoppingCart.Shipping.ShippingCharge'
            ) &&
            $shippingCharge > 0
        ) {
            $charges['ShippingCharge'] = [
                'label' => 'Shipping Charge',
                'charge' => $shippingCharge,
            ];
        }

        /*
         * ---------------------------------------------------------
         * Tax
         * ---------------------------------------------------------
         */
        $tax = Session::get(
            'ShoppingCart.Tax'
        );

        if (
            Session::has('ShoppingCart.Tax') &&
            $tax > 0
        ) {
            $charges['Tax'] = [
                'label' => 'Sales Tax',
                'charge' => $tax,
            ];
        }

        /*
         * ---------------------------------------------------------
         * Gift Wrapping
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.GiftWrapping'
            )
        ) {
            $giftWrap = Session::get(
                'ShoppingCart.GiftWrapping'
            );

            if (
                isset($giftWrap['Charge']) &&
                $giftWrap['Charge'] > 0
            ) {
                $charges['GiftWrappingCharge'] = [
                    'label' =>
                        'Gift Wrapping Charge',

                    'charge' =>
                        $giftWrap['Charge'],
                ];
            }
        }

        /*
         * ---------------------------------------------------------
         * Shipping Signature
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.ShippingSignature'
            )
        ) {
            $charges['ShippingSignature'] = [
                'label' =>
                    'Shipping Signature',

                'charge' =>
                    Session::get(
                        'ShoppingCart.ShippingSignature'
                    ),
            ];
        }

        /*
         * ---------------------------------------------------------
         * Shipping Insurance
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'shipping_insurance_charge'
            )
        ) {
            $charges['ShippingInsurance'] = [
                'label' =>
                    'Shipping Insurance Charge',

                'charge' =>
                    Session::get(
                        'shipping_insurance_charge'
                    ),
            ];
        }

        /*
         * ---------------------------------------------------------
         * Single charge requested
         * ---------------------------------------------------------
         */
        if ($chargeName !== '') {

            $charge = isset(
                $charges[$chargeName]
            )
                ? NumberFormat(
                    $charges[$chargeName]['charge']
                )
                : 0;

            $log['Charge'] = $charge;

            addLog(
                'GetAllCharges',
                $log
            );

            return $charge;
        }

        /*
         * ---------------------------------------------------------
         * All charges
         * ---------------------------------------------------------
         */
        $totalCharges = array_sum(
            array_column(
                $charges,
                'charge'
            )
        );

        $chargesInfo = [
            'Charges' => $charges,

            'TotalCharges' =>
                NumberFormat($totalCharges),
        ];

        $log['ChargesInfo'] = $chargesInfo;

        addLog(
            'GetAllCharges',
            $log
        );

        return $chargesInfo;
    }

    /**
     * Get one charge.
     */
    public function getCharge(
        string $chargeName
    ): float {
        return (float) $this->getAllCharges(
            $chargeName
        );
    }

    /**
     * Existing GetNetTotal() calculation.
     *
     * Formula:
     *
     * SubTotal
     * + TotalCharges
     * - TotalDiscount
     * = NetTotal
     *
     * Minimum = 0
     */
    public function getNetTotal(
        $totalDiscount = null
    ): float {
        /*
         * Existing GetNetTotal() reads GiftCoupon,
         * GiftWrapping and ShippingSignature before
         * calculating charges.
         *
         * Keep these reads because those session values
         * participate in the underlying charge/discount
         * methods.
         */
        $giftCouponInfo = $this->getGiftCoupon();

        $giftWrapCharge = 0;

        if (
            Session::has(
                'ShoppingCart.GiftWrapping'
            )
        ) {
            $giftWrap = Session::get(
                'ShoppingCart.GiftWrapping'
            );

            $giftWrapCharge = isset(
                $giftWrap['Charge']
            )
                ? (float) $giftWrap['Charge']
                : 0;
        }

        $shippingSignature = 0;

        if (
            Session::has(
                'ShoppingCart.ShippingSignature'
            )
        ) {
            $shippingSignature =
                (float) Session::get(
                    'ShoppingCart.ShippingSignature'
                );
        }

        /*
         * Charges are calculated from the same session
         * structure as the existing method.
         */
        $allCharges = $this->getAllCharges();

        $subTotal = (float) Session::get(
            'ShoppingCart.SubTotal',
            0
        );

        $totalAmount =
            $subTotal
            + $allCharges['TotalCharges'];

        /*
         * IMPORTANT:
         *
         * GetAllDiscounts() belongs to DiscountService.
         *
         * We do NOT duplicate discount logic here.
         */
        if ($totalDiscount === null) {
            $totalDiscount =
                $this->getTotalDiscountFromSession();
        }

        $netTotal =
            $totalAmount
            - $totalDiscount;

        if ($netTotal <= 0) {
            $netTotal = 0;
        }

        $netTotal = NumberFormat(
            $netTotal
        );

        addLog(
            'GetNetTotal',
            [
                'SubTotal' =>
                    $subTotal,

                'TotalCharges' =>
                    $allCharges['TotalCharges'],

                'TotalDiscount' =>
                    $totalDiscount,

                'NetTotal' =>
                    $netTotal,
            ]
        );

        return $netTotal;
    }

    /**
     * Read currently available discount total.
     *
     * IMPORTANT:
     * This is a temporary bridge until DiscountService
     * owns GetAllDiscounts().
     */
    protected function getTotalDiscountFromSession(): float
    {
        /*
         * DiscountService is the single source of truth for the
         * currently active checkout/cart discounts.
         *
         * Keep this method as a compatibility wrapper because
         * existing callers still use getNetTotal(), but do not
         * maintain a second discount calculation here.
         */
        return (float) $this->discountService
            ->getTotal();
    }

    /**
     * Existing GetAllCoupons('GiftCoupon') dependency.
     */
    protected function getGiftCoupon(): array
    {
        $giftCoupon = '';

        if (
            Session::has(
                'ShoppingCart.GiftCoupon'
            ) &&
            Session::get(
                'ShoppingCart.GiftCoupon'
            ) !== ''
        ) {
            $giftCoupon =
                Session::get(
                    'ShoppingCart.GiftCoupon'
                );
        }

        return $giftCoupon;
    }

    /**
     * Return complete calculation snapshot.
     */
    public function getCalculationSnapshot(): array
    {
        $allCharges =
            $this->getAllCharges();

        $subTotal =
            (float) Session::get(
                'ShoppingCart.SubTotal',
                0
            );

        $totalDiscount =
            $this->getTotalDiscountFromSession();

        $netTotal =
            $this->getNetTotal(
                $totalDiscount
            );

        return [
            'SubTotal' =>
                NumberFormat($subTotal),

            'Charges' =>
                $allCharges['Charges'],

            'TotalCharges' =>
                NumberFormat(
                    $allCharges['TotalCharges']
                ),

            'TotalDiscount' =>
                NumberFormat(
                    $totalDiscount
                ),

            'NetTotal' =>
                NumberFormat($netTotal),

            'TotalItemInCart' =>
                (int) Session::get(
                    'ShoppingCart.TotalItemInCart',
                    0
                ),
        ];
    }
}