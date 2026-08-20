<?php

namespace App\Services\Discount;

use Illuminate\Support\Facades\Session;

class DiscountAllocationService
{
    /**
     * Item-wise discount fields used by the legacy checkout flow.
     *
     * Keep the exact legacy field names because order creation,
     * tax allocation and checkout code still consume them.
     */
    protected array $discountFields = [
        'CouponDisItemWiseDiscout',
        'AutoItemWiseDiscout',
        'QuantityItemWiseDiscout',
        'RewardItemWiseDiscout',
        'BogoItemWiseDiscout',
        'GiftCertificateItemWiseDiscout',
        'CreditLimitItemWiseDiscout',
    ];

    /**
     * Reset item-wise discount values whose corresponding total
     * discount is no longer active.
     *
     * This is the common part of the old repeated reset blocks.
     */
    public function resetInactiveDiscounts(
        array &$item,
        array $activeDiscounts = []
    ): void {
        $activeDiscounts = $this->resolveActiveDiscounts(
            $activeDiscounts
        );

        $map = [
            'CouponDisItemWiseDiscout' =>
                'CouponDiscount',

            'AutoItemWiseDiscout' =>
                'AutoDiscount',

            'QuantityItemWiseDiscout' =>
                'QuantityDiscount',

            'RewardItemWiseDiscout' =>
                'YotpoRewardDiscount',

            'BogoItemWiseDiscout' =>
                'DogoDiscount',

            'GiftCertificateItemWiseDiscout' =>
                'GiftCoupon',

            'CreditLimitItemWiseDiscout' =>
                'CreditLimitDiscount',
        ];

        foreach ($map as $itemField => $discountKey) {
            if (
                ($activeDiscounts[$discountKey] ?? 0) <= 0
            ) {
                $item[$itemField] = 0;
            }
        }
    }

    /**
     * Return the item-wise discount total for an item.
     *
     * This is shared by order creation, tax calculation and
     * any checkout code that needs the discounted item price.
     */
    public function getItemDiscountTotal(
        array $item
    ): float {
        $total = 0.0;

        foreach ($this->discountFields as $field) {
            $total += (float) (
                $item[$field] ?? 0
            );
        }

        return (float) NumberFormat(
            max(0, $total)
        );
    }

    /**
     * Get the item's price after item-wise discounts.
     *
     * The legacy behavior is:
     *
     * TotPrice - all item-wise discounts.
     *
     * A negative item price is never allowed.
     */
    public function getItemNetPrice(
        array $item
    ): float {
        $totalPrice =
            (float) (
                $item['TotPrice'] ?? 0
            );

        $discount =
            $this->getItemDiscountTotal(
                $item
            );

        return (float) NumberFormat(
            max(
                0,
                $totalPrice - $discount
            )
        );
    }

    /**
     * Calculate the tax + shipping amount attributable to one
     * cart item for the legacy percentage-coupon rule.
     *
     * Tax is supplied by the caller because ItemWiseTax() belongs
     * to the tax domain and must not be duplicated here.
     */
    public function getTaxShippingBase(
        float $itemTax,
        float $itemTotal,
        float $shippingCharge,
        float $subTotal
    ): float {
        $itemShipping = 0.0;

        if (
            $shippingCharge > 0
            &&
            $subTotal > 0
        ) {
            $itemShipping = NumberFormat(
                (
                    $itemTotal
                    * $shippingCharge
                )
                /
                $subTotal
            );
        }

        return (float) NumberFormat(
            $itemTax + $itemShipping
        );
    }

    /**
     * Preserve the legacy CouponPercentage + CountShipTax rule.
     *
     * This method only allocates the tax/shipping portion.
     * Coupon item-wise discount itself remains untouched.
     */
    public function getCouponTaxShippingAllocation(
        array $item,
        float $itemTax,
        float $shippingCharge,
        float $subTotal,
        float $couponPercentage
    ): float {
        $couponDiscount =
            $this->getSessionDiscount(
                'CouponDiscount'
            );

        $couponCode =
            (string) Session::get(
                'ShoppingCart.PromoCoupon.CouponCode',
                ''
            );

        $countShipTax =
            (string) Session::get(
                'ShoppingCart.CountShipTax',
                ''
            );

        if (
            $couponCode === ''
            ||
            $couponDiscount <= 0
            ||
            $countShipTax !== '1'
            ||
            $couponPercentage <= 0
        ) {
            return 0.0;
        }

        $base =
            $this->getTaxShippingBase(
                $itemTax,
                (float) (
                    $item['TotPrice'] ?? 0
                ),
                $shippingCharge,
                $subTotal
            );

        return (float) NumberFormat(
            $base
            *
            (
                $couponPercentage
                / 100
            )
        );
    }

    /**
     * Build the legacy TaxShippingItemWiseDiscount value.
     *
     * When the coupon percentage is 100%, the old flow appends
     * the CouponDisItemWiseDiscout after "###". Preserve that
     * representation exactly for compatibility.
     */
    public function buildTaxShippingItemWiseDiscount(
        array $item,
        float $itemTax,
        float $shippingCharge,
        float $subTotal,
        float $couponPercentage
    ): string|float {
        $allocated =
            $this->getCouponTaxShippingAllocation(
                $item,
                $itemTax,
                $shippingCharge,
                $subTotal,
                $couponPercentage
            );

        $couponItemWise =
            (float) (
                $item[
                    'CouponDisItemWiseDiscout'
                ] ?? 0
            );

        if (
            $couponItemWise > 0
            &&
            NumberFormat(
                $couponPercentage
            ) == 100
        ) {
            return
                NumberFormat($allocated)
                . '###'
                . NumberFormat($couponItemWise);
        }

        return NumberFormat($allocated);
    }

    /**
     * Apply the common allocation/reset logic to a cart.
     *
     * The caller supplies item tax values because tax calculation
     * remains owned by the tax service.
     */
    public function prepareCart(
        array &$cart,
        array $itemTaxes = [],
        ?float $shippingCharge = null,
        ?float $subTotal = null,
        ?float $couponPercentage = null
    ): void {
        $activeDiscounts =
            $this->resolveActiveDiscounts();

        $shippingCharge =
            $shippingCharge
            ??
            (float) Session::get(
                'ShoppingCart.Shipping.ShippingCharge',
                0
            );

        $subTotal =
            $subTotal
            ??
            (float) Session::get(
                'ShoppingCart.SubTotal',
                0
            );

        $couponPercentage =
            $couponPercentage
            ??
            (float) Session::get(
                'ShoppingCart.CouponPercentage',
                0
            );

        foreach ($cart as $index => &$item) {
            /*
             * Free gifts/samples keep their existing zero-price
             * behavior and should not receive normal product
             * discount allocation.
             */
            if (
                ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
                ||
                ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
            ) {
                $this->resetInactiveDiscounts(
                    $item,
                    $activeDiscounts
                );

                $item[
                    'TaxShippingItemWiseDiscount'
                ] = 0;

                continue;
            }

            $this->resetInactiveDiscounts(
                $item,
                $activeDiscounts
            );

            $itemTax =
                (float) (
                    $itemTaxes[$index] ?? 0
                );

            $item[
                'TaxShippingItemWiseDiscount'
            ] =
                $this->buildTaxShippingItemWiseDiscount(
                    $item,
                    $itemTax,
                    $shippingCharge,
                    $subTotal,
                    $couponPercentage
                );
        }

        unset($item);
    }

    /**
     * Resolve active discount totals from the common
     * DiscountService when available.
     */
    protected function resolveActiveDiscounts(
        array $discounts = []
    ): array {
        if (!empty($discounts)) {
            return $discounts;
        }

        $service =
            app(DiscountService::class);

        $result =
            $service->getAll();

        $resolved = [];

        foreach (
            ($result['Discounts'] ?? []) as $key => $detail
        ) {
            $resolved[$key] =
                (float) (
                    $detail['discount'] ?? 0
                );
        }

        return $resolved;
    }

    protected function getSessionDiscount(
        string $discountName
    ): float {
        $service =
            app(DiscountService::class);

        return $service->get(
            $discountName
        );
    }
}
