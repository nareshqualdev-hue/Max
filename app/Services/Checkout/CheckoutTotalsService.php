<?php

namespace App\Services\Checkout;

use App\Services\Discount\DiscountService;
use Illuminate\Support\Facades\Session;


class CheckoutTotalsService
{
    public function __construct(
        protected DiscountService $discountService
    ) {
    }

    /**
     * Get complete checkout totals.
     *
     * Existing logic:
     *
     * SubTotal
     * + Shipping
     * + Tax
     * + Gift Wrapping
     * + Shipping Signature
     * + Shipping Insurance
     * - Total Discount
     * = Net Total
     */
    public function calculate(): array
    {
        $subTotal =
            $this->getSubTotal();

        $charges =
            $this->getAllCharges();

        $discounts =
            $this->discountService->getAll();

        $totalCharges =
            (float) (
                $charges['TotalCharges']
                ?? 0
            );

        $totalDiscount =
            (float) (
                $discounts['TotalDiscount']
                ?? 0
            );

        $totalAmount =
            $subTotal
            +
            $totalCharges;

        $netTotal =
            $totalAmount
            -
            $totalDiscount;

        /*
         * Existing behavior:
         * NetTotal can never be negative.
         */
        if (
            $netTotal <= 0
        ) {
            $netTotal = 0;
        }

        $result = [
            'SubTotal' =>
                NumberFormat(
                    $subTotal
                ),

            'Charges' =>
                $charges['Charges'] ?? [],

            'TotalCharges' =>
                NumberFormat(
                    $totalCharges
                ),

            'Discounts' =>
                $discounts['Discounts'] ?? [],

            'TotalDiscount' =>
                NumberFormat(
                    $totalDiscount
                ),

            'TotalAmount' =>
                NumberFormat(
                    $totalAmount
                ),

            'NetTotal' =>
                NumberFormat(
                    $netTotal
                ),
        ];

        /*
         * Keep NetTotal available for other checkout
         * services that currently read it from session.
         */
        Session::put(
            'ShoppingCart.NetTotal',
            $result['NetTotal']
        );

        addLog(
            'CheckoutTotalsService',
            [
                'SubTotal' =>
                    $result['SubTotal'],

                'TotalCharges' =>
                    $result['TotalCharges'],

                'TotalDiscount' =>
                    $result['TotalDiscount'],

                'TotalAmount' =>
                    $result['TotalAmount'],

                'NetTotal' =>
                    $result['NetTotal'],
            ]
        );

        return $result;
    }

    /**
     * Get subtotal from the existing cart session.
     */
    public function getSubTotal(): float
    {
        return (float)
            Session::get(
                'ShoppingCart.SubTotal',
                0
            );
    }

    /**
     * Get all checkout charges.
     *
     * Migration of CartTrait::GetAllCharges()
     */
    public function getAllCharges(
        string $chargeName = ''
    ): array|float {
        /*
         * Existing compatibility:
         *
         * TaxValue → Tax
         */
        if (
            $chargeName === 'TaxValue'
        ) {
            $chargeName = 'Tax';
        }

        $charges = [];

        /*
         * ---------------------------------------------------------
         * Shipping Charge
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.Shipping.ShippingCharge'
            )
            &&
            Session::get(
                'ShoppingCart.Shipping.ShippingCharge'
            ) > 0
        ) {
            $charges[
                'ShippingCharge'
            ] = [
                'label' =>
                    'Shipping Charge',

                'charge' =>
                    Session::get(
                        'ShoppingCart.Shipping.ShippingCharge'
                    ),
            ];
        }

        /*
         * ---------------------------------------------------------
         * Sales Tax
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.Tax'
            )
            &&
            Session::get(
                'ShoppingCart.Tax'
            ) > 0
        ) {
            $charges[
                'Tax'
            ] = [
                'label' =>
                    'Sales Tax',

                'charge' =>
                    Session::get(
                        'ShoppingCart.Tax'
                    ),
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
            $giftWrapping =
                Session::get(
                    'ShoppingCart.GiftWrapping'
                );

            if (
                is_array(
                    $giftWrapping
                )
                &&
                (
                    $giftWrapping['Charge']
                    ?? 0
                ) > 0
            ) {
                $charges[
                    'GiftWrappingCharge'
                ] = [
                    'label' =>
                        'Gift Wrapping Charge',

                    'charge' =>
                        $giftWrapping['Charge'],
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
            $charges[
                'ShippingSignature'
            ] = [
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
            $charges[
                'ShippingInsurance'
            ] = [
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
        if (
            $chargeName !== ''
        ) {
            $charge =
                isset(
                    $charges[$chargeName]
                )
                    ? NumberFormat(
                        $charges[
                            $chargeName
                        ]['charge']
                    )
                    : 0;

            addLog(
                'GetAllCharges',
                [
                    'ChargeName' =>
                        $chargeName,

                    'Charge' =>
                        $charge,
                ]
            );

            return $charge;
        }

        /*
         * ---------------------------------------------------------
         * Total charges
         * ---------------------------------------------------------
         */
        $totalCharges =
            array_sum(
                array_column(
                    $charges,
                    'charge'
                )
            );

        $result = [
            'Charges' =>
                $charges,

            'TotalCharges' =>
                NumberFormat(
                    $totalCharges
                ),
        ];

        addLog(
            'GetAllCharges',
            [
                'ChargeName' =>
                    $chargeName,

                'ChargesInfo' =>
                    $result,
            ]
        );

        return $result;
    }

    /**
     * Get the merchandise subtotal after active discounts.
     *
     * This is intentionally NOT NetTotal:
     * shipping-rate rules use merchandise subtotal after
     * discounts, before tax/shipping/insurance/signature charges.
     */
    public function getDiscountedSubTotal(): float
    {
        $subTotal =
            $this->getSubTotal();

        $totalDiscount =
            $this->discountService
                ->getTotal();

        return (float) NumberFormat(
            max(
                0,
                $subTotal - $totalDiscount
            )
        );
    }

    /**
     * Get NetTotal excluding selected checkout charge components.
     *
     * This is used when a charge is calculated from the order amount
     * before that charge itself (or another excluded charge) is added.
     *
     * Example:
     * Shipping insurance is calculated from the order total before
     * Sales Tax and Shipping Insurance are included.
     */
    public function getNetTotalExcludingCharges(
        array $excludedCharges = []
    ): float {
        $subTotal =
            $this->getSubTotal();

        $charges =
            $this->getAllCharges();

        $discounts =
            $this->discountService->getAll();

        $excludedCharges =
            array_map(
                static fn ($name) =>
                    strtolower((string) $name),
                $excludedCharges
            );

        $totalCharges = 0.0;

        foreach (
            ($charges['Charges'] ?? []) as $key => $charge
        ) {
            if (
                in_array(
                    strtolower((string) $key),
                    $excludedCharges,
                    true
                )
            ) {
                continue;
            }

            $totalCharges +=
                (float) (
                    $charge['charge'] ?? 0
                );
        }

        $totalDiscount =
            (float) (
                $discounts['TotalDiscount'] ?? 0
            );

        $netTotal =
            $subTotal
            + $totalCharges
            - $totalDiscount;

        return (float) NumberFormat(
            max(0, $netTotal)
        );
    }

    /**
     * Get the amount used to determine Shipping Signature eligibility.
     *
     * This keeps the legacy rule:
     *
     * NetTotal - Shipping Insurance - current Shipping Signature
     *
     * The NetTotal itself is calculated by this service, so other
     * services do not need to duplicate subtotal/charge/discount logic.
     */
    public function getSignatureEligibleAmount(): float
    {
        $totals = $this->getTotals();

        $netTotal = (float) (
            $totals['NetTotal'] ?? 0
        );

        $insurance = (float) Session::get(
            'shipping_insurance_charge',
            0
        );

        $signature = (float) Session::get(
            'ShoppingCart.ShippingSignature',
            0
        );

        return max(
            0,
            $netTotal
            - $insurance
            - $signature
        );
    }

    /**
     * Get a single total component.
     */
    /**
     * Get the complete calculated checkout totals.
     *
     * This is the common totals accessor for services that need
     * SubTotal, TotalAmount or NetTotal.
     *
     * Do not calculate these values independently in other services.
     */
    public function getTotals(): array
    {
        return $this->calculate();
    }

    /**
     * Get the final NetTotal from the common totals result.
     */
    public function getNetTotal(): float
    {
        $totals = $this->getTotals();

        return (float) (
            $totals['NetTotal'] ?? 0
        );
    }

    /**
     * Get TotalAmount before discounts.
     */
    public function getTotalAmount(): float
    {
        $totals = $this->getTotals();

        return (float) (
            $totals['TotalAmount'] ?? 0
        );
    }

    /**
     * Get a single total component.
     *
     * All calculated totals come from the same common totals
     * result, so NetTotal/TotalAmount are not independently
     * recalculated multiple times inside this method.
     */
    public function getTotal(
        string $type
    ): float {
        return match ($type) {
            'subtotal' =>
                $this->getSubTotal(),

            'shipping' =>
                (float)
                $this->getAllCharges(
                    'ShippingCharge'
                ),

            'tax' =>
                (float)
                $this->getAllCharges(
                    'Tax'
                ),

            'gift_wrapping' =>
                (float)
                $this->getAllCharges(
                    'GiftWrappingCharge'
                ),

            'shipping_signature' =>
                (float)
                $this->getAllCharges(
                    'ShippingSignature'
                ),

            'shipping_insurance' =>
                (float)
                $this->getAllCharges(
                    'ShippingInsurance'
                ),

            'discount' =>
                $this->discountService
                    ->getTotal(),

            'total' =>
                $this->getTotalAmount(),

            'net_total' =>
                $this->getNetTotal(),

            default =>
                0.0,
        };
    }
}
