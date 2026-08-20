<?php

namespace App\Services\Checkout;

use App\Models\GiftCertificate;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class GiftCertificateService
{
    /**
     * Apply a Gift Certificate to the current checkout cart.
     *
     * IMPORTANT:
     * - This only validates/calculates the certificate.
     * - It does NOT reduce GiftCertificate.remaining_value.
     * - Redemption/balance deduction remains part of the successful
     *   order/payment flow.
     */
    public function apply(string $code): array
    {
        $code = trim($code);

        if ($code === '') {
            $this->clear();

            return [
                'status' => 'error',
                'message' => 'Gift Certificate code is required.',
            ];
        }

        addLog('CheckoutGiftCertificateStart', [
            'code' => $code,
        ]);

        try {
            $cartInfo =
                Session::get(
                    'ShoppingCart.Cart',
                    []
                );

            if (!is_array($cartInfo)) {
                $cartInfo = [];
            }

            /*
             * Existing rule:
             * If the cart contains a Gift Certificate product,
             * another Gift Certificate cannot be applied.
             */
            if ($this->hasGiftCertificateProduct($cartInfo)) {
                $this->clear();

                return [
                    'status' => 'success',
                    'applied' => 'No',
                    'reason' =>
                        'gift_certificate_product_in_cart',
                    'giftCertificate' => [
                        'code' => '',
                        'value' => 0.0,
                        'applicableValue' => 0.0,
                        'remainingValue' => 0.0,
                    ],
                ];
            }

            /*
             * Keep the legacy total used by ApplyGiftCoupons().
             * The legacy method starts with GetNetTotal(), so the
             * current checkout total is read before applying the
             * certificate.
             */
            $netTotal =
                $this->getCurrentNetTotal();

            if ($netTotal <= 0) {
                $netTotal = 0.0;
            }

            $totalEligibleAmount =
                $this->getEligibleCartAmount(
                    $cartInfo
                );

            $certificate =
                GiftCertificate::where(
                    'remaining_value',
                    '>',
                    0
                )
                ->where(
                    'status',
                    '=',
                    '1'
                )
                ->where(
                    'gc_code',
                    '=',
                    $code
                )
                ->where(
                    'expiry_date',
                    '>=',
                    DB::raw('curdate()')
                )
                ->first();

            if (!$certificate) {
                $this->clear();

                return [
                    'status' => 'error',
                    'message' =>
                        'Invalid or expired Gift Certificate.',
                ];
            }

            $subTotal =
                (float)
                Session::get(
                    'ShoppingCart.SubTotal',
                    0
                );

            /*
             * Legacy condition:
             * subtotal must meet minimum_purchase_value.
             */
            if (
                $subTotal
                <
                (float)
                $certificate->minimum_purchase_value
            ) {
                $this->clear();

                return [
                    'status' => 'error',
                    'message' =>
                        'Minimum purchase requirement not met.',
                ];
            }

            if (
                !isset($certificate->gc_id)
                ||
                (int) $certificate->gc_id <= 0
            ) {
                $this->clear();

                return [
                    'status' => 'error',
                    'message' =>
                        'Invalid Gift Certificate.',
                ];
            }

            /*
             * Legacy normal/customer certificate branch:
             * validate the original order detail and order status.
             *
             * Admin-added certificates intentionally skip this
             * order-detail/order-status validation, exactly as the
             * old ApplyGiftCoupons() branch does.
             */
            if (
                (string)
                $certificate->is_added_by_admin
                === 'No'
            ) {
                $orderDetail =
                    OrderDetail::where(
                        'orders_detail_id',
                        '=',
                        $certificate->orders_detail_id
                    )->first();

                if (!$orderDetail) {
                    return $this->invalidCertificate();
                }

                $order =
                    Order::where(
                        'orders_id',
                        '=',
                        $orderDetail->orders_id
                    )
                    ->whereIn(
                        'status',
                        [
                            'Pending',
                            'Completed',
                        ]
                    )
                    ->first();

                if (!$order) {
                    return $this->invalidCertificate();
                }
            } elseif (
                (string)
                $certificate->is_added_by_admin
                !== 'Yes'
            ) {
                return $this->invalidCertificate();
            }

            /*
             * Legacy behavior:
             * never apply more than the current net total.
             */
            $remainingValue =
                (float)
                $certificate->remaining_value;

            $applicableValue =
                $remainingValue;

            if ($applicableValue >= $netTotal) {
                $applicableValue =
                    NumberFormat($netTotal);
            }

            $newRemainingValue =
                $remainingValue
                -
                (float) $applicableValue;

            Session::put(
                'ShoppingCart.GiftCoupon.Code',
                $certificate->gc_code
            );

            Session::put(
                'ShoppingCart.GiftCoupon.Value',
                $applicableValue
            );

            Session::put(
                'ShoppingCart.GiftCoupon.Applicable_Value',
                $applicableValue
            );

            Session::put(
                'ShoppingCart.GiftCoupon.Remaining_Value',
                $newRemainingValue
            );

            /*
             * Preserve legacy item-wise allocation.
             */
            $this->applyItemWiseAllocation(
                $cartInfo,
                $totalEligibleAmount,
                $applicableValue
            );

            addLog('CheckoutGiftCertificateApplied', [
                'code' =>
                    $certificate->gc_code,
                'value' =>
                    $applicableValue,
                'remainingValue' =>
                    $newRemainingValue,
            ]);

            return [
                'status' => 'success',
                'applied' => 'Yes',
                'giftCertificate' => [
                    'code' =>
                        $certificate->gc_code,
                    'value' =>
                        (float) $applicableValue,
                    'applicableValue' =>
                        (float) $applicableValue,
                    'remainingValue' =>
                        (float) $newRemainingValue,
                ],
            ];
        } catch (\Throwable $e) {
            addLog('CheckoutGiftCertificateError', [
                'code' => $code,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->clear();

            return [
                'status' => 'error',
                'message' =>
                    'Unable to apply Gift Certificate.',
            ];
        }
    }

    /**
     * Remove the currently applied Gift Certificate.
     */
    public function remove(): array
    {
        $this->clear();

        return [
            'status' => 'success',
            'applied' => 'No',
            'giftCertificate' => [
                'code' => '',
                'value' => 0.0,
                'applicableValue' => 0.0,
                'remainingValue' => 0.0,
            ],
        ];
    }

    /**
     * Re-apply the currently stored certificate after cart/total
     * changes, matching SetupCart() behavior.
     */
    public function sync(): array
    {
        $code =
            trim(
                (string)
                Session::get(
                    'ShoppingCart.GiftCoupon.Code',
                    ''
                )
            );

        if ($code === '') {
            return [
                'status' => 'success',
                'applied' => 'No',
            ];
        }

        /*
         * Legacy SetupCart() resets these before calling
         * ApplyGiftCoupons().
         */
        Session::put(
            'ShoppingCart.GiftCoupon.Value',
            0.0
        );

        Session::put(
            'ShoppingCart.GiftCoupon.Applicable_Value',
            0.0
        );

        return $this->apply($code);
    }

    protected function clear(): void
    {
        Session::put(
            'ShoppingCart.GiftCoupon.Code',
            ''
        );

        Session::put(
            'ShoppingCart.GiftCoupon.Value',
            0.0
        );

        Session::put(
            'ShoppingCart.GiftCoupon.Applicable_Value',
            0.0
        );

        Session::put(
            'ShoppingCart.GiftCoupon.Remaining_Value',
            0.0
        );

        $cartInfo =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        if (is_array($cartInfo)) {
            foreach (
                array_keys($cartInfo) as $index
            ) {
                Session::forget(
                    'ShoppingCart.Cart.'
                    . $index
                    . '.GiftCertificateItemWiseDiscout'
                );
            }
        }

        addLog('ResetGiftCoupon');
    }

    protected function invalidCertificate(): array
    {
        $this->clear();

        return [
            'status' => 'error',
            'message' =>
                'Gift Certificate is not valid.',
        ];
    }

    protected function hasGiftCertificateProduct(
        array $cartInfo
    ): bool {
        foreach ($cartInfo as $item) {
            if (
                isset(
                    $item['IsGiftCertificateItem']
                )
                &&
                $item['IsGiftCertificateItem']
                === 'Yes'
            ) {
                return true;
            }

            $sku =
                (string)
                ($item['SKU'] ?? '');

            if (
                $sku !== ''
                &&
                (
                    $sku === config(
                        'global.GIFT_CERTIFICATE_SKU'
                    )
                    ||
                    $sku === config(
                        'global.GIFT_CERTIFICATE_SKU1'
                    )
                    ||
                    $sku === config(
                        'global.GIFT_CERTIFICATE_SKU2'
                    )
                    ||
                    $sku === config(
                        'global.GIFT_CERTIFICATE_SKU3'
                    )
                )
            ) {
                return true;
            }
        }

        return false;
    }

    protected function getEligibleCartAmount(
        array $cartInfo
    ): float {
        $total = 0.0;

        foreach ($cartInfo as $item) {
            $isFreeGift =
                ($item['IS_Free_Gift'] ?? '')
                === 'Yes';

            $isFreeSample =
                ($item['Is_Free_Sample'] ?? '')
                === 'Yes';

            $isDealProduct =
                ($item['IsDealProducts'] ?? '')
                === 'Yes';

            if (
                !$isDealProduct
                &&
                !$isFreeGift
                &&
                !$isFreeSample
            ) {
                $total +=
                    (float)
                    ($item['TotPrice'] ?? 0);
            }
        }

        return $total;
    }

    protected function applyItemWiseAllocation(
        array $cartInfo,
        float $totalEligibleAmount,
        float $applicableValue
    ): void {
        if ($totalEligibleAmount <= 0) {
            return;
        }

        foreach (
            $cartInfo as $index => $item
        ) {
            $isFreeGift =
                ($item['IS_Free_Gift'] ?? '')
                === 'Yes';

            $isFreeSample =
                ($item['Is_Free_Sample'] ?? '')
                === 'Yes';

            $isDealProduct =
                ($item['IsDealProducts'] ?? '')
                === 'Yes';

            if (
                $isDealProduct
                ||
                $isFreeGift
                ||
                $isFreeSample
            ) {
                continue;
            }

            /*
             * Same proportional calculation as the legacy method:
             *
             * (GiftCertificateValue * 100 / TotalAmount)
             * * ItemTotal / 100
             */
            $itemWiseDiscount =
                (
                    (
                        $applicableValue
                        * 100
                    )
                    /
                    $totalEligibleAmount
                )
                *
                (
                    (float)
                    ($item['TotPrice'] ?? 0)
                )
                /
                100;

            Session::put(
                'ShoppingCart.Cart.'
                . $index
                . '.GiftCertificateItemWiseDiscout',
                $itemWiseDiscount
            );
        }
    }

    /**
     * The current checkout net total must come from the existing
     * session-calculated total. The CheckoutTotalsService remains
     * the final source of truth after applying/removing the
     * certificate.
     */
    protected function getCurrentNetTotal(): float
    {
        $netTotal =
            Session::get(
                'ShoppingCart.NetTotal'
            );

        if (
            $netTotal !== null
            &&
            $netTotal !== ''
        ) {
            return (float) $netTotal;
        }

        /*
         * Fallback using the same session components already used
         * by the legacy cart total calculation. This avoids calling
         * GetNetTotal() from the new service and avoids coupling the
         * new checkout service back to CartTrait.
         */
        $subTotal =
            (float)
            Session::get(
                'ShoppingCart.SubTotal',
                0
            );

        $charges = 0.0;

        foreach ([
            'ShoppingCart.Shipping.ShippingCharge',
            'ShoppingCart.Tax',
            'ShoppingCart.GiftWrapping.Charge',
            'ShoppingCart.ShippingSignature',
            'shipping_insurance_charge',
        ] as $key) {
            $charges +=
                (float)
                Session::get(
                    $key,
                    0
                );
        }

        $discounts = 0.0;

        foreach ([
            'ShoppingCart.AutoDiscount',
            'ShoppingCart.YotpoRewardDiscount',
            'ShoppingCart.QuantityDiscount',
            'ShoppingCart.PromoCoupon.CouponDiscount',
            'ShoppingCart.AutoReferDiscount',
            'ShoppingCart.Reward_array.RewardDiscount',
            'ShoppingCart.credit_limit_discount',
            'ShoppingCart.DogoDiscount',
        ] as $key) {
            $discounts +=
                (float)
                Session::get(
                    $key,
                    0
                );
        }

        return max(
            0,
            $subTotal
            + $charges
            - $discounts
        );
    }
}
