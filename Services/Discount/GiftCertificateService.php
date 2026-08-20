<?php

namespace App\Services\Discount;

use App\Models\GiftCertificate;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class GiftCertificateService
{
    /**
     * Apply Gift Certificate.
     *
     * Migration of CartTrait::ApplyGiftCoupons()
     *
     * Return values:
     *
     * 1   = Gift Certificate applied
     * 2   = Gift Certificate invalid
     * Yes = Gift Certificate product is already in cart
     */
    public function apply(string $coupon): int|string
    {
        $log = [
            'coupon' => $coupon,
        ];

        addLog(
            'ApplyGiftCouponsStart',
            $log
        );

        /*
         * ---------------------------------------------------------
         * Current net total
         * ---------------------------------------------------------
         */
        $totvalue =
            $this->getNetTotal();

        if ($totvalue <= 0) {
            $totvalue = 0;
        }

        /*
         * ---------------------------------------------------------
         * Cart
         * ---------------------------------------------------------
         */
        $CartInfo =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        $Gifcard = 'No';
        $TotalAmount = 0;

        /*
         * ---------------------------------------------------------
         * Check Gift Certificate product in cart
         * ---------------------------------------------------------
         */
        if (
            count($CartInfo) > 0
        ) {
            for (
                $i = 0;
                $i < count($CartInfo);
                $i++
            ) {
                /*
                 * If actual Gift Certificate product
                 * exists in cart, remove applied GC coupon.
                 */
                if (
                    isset(
                        $CartInfo[$i]['IsGiftCertificateItem']
                    )
                    &&
                    $CartInfo[$i]['IsGiftCertificateItem']
                        == 'Yes'
                ) {
                    $Gifcard = 'Yes';

                    break;
                }

                $FreeGift = '';

                if (
                    isset(
                        $CartInfo[$i]['IS_Free_Gift']
                    )
                ) {
                    $FreeGift =
                        $CartInfo[$i]['IS_Free_Gift'];
                }

                $FreeSample = '';

                if (
                    isset(
                        $CartInfo[$i]['Is_Free_Sample']
                    )
                ) {
                    $FreeSample =
                        $CartInfo[$i]['Is_Free_Sample'];
                }

                /*
                 * Only normal products participate
                 * in Gift Certificate item-wise allocation.
                 */
                if (
                    isset(
                        $CartInfo[$i]['IsDealProducts']
                    )
                    &&
                    $CartInfo[$i]['IsDealProducts']
                        != 'Yes'
                    &&
                    $FreeGift != 'Yes'
                    &&
                    $FreeSample != 'Yes'
                ) {
                    $TotalAmount +=
                        (float)
                        (
                            $CartInfo[$i]['TotPrice']
                            ?? 0
                        );
                }
            }
        }

        /*
         * ---------------------------------------------------------
         * Gift Certificate product found in cart
         * ---------------------------------------------------------
         */
        if (
            $Gifcard == 'Yes'
        ) {
            $this->resetGiftCoupon();

            addLog(
                'ResetGiftCoupon'
            );

            return $Gifcard;
        }

        /*
         * ---------------------------------------------------------
         * Coupon code
         * ---------------------------------------------------------
         */
        $coupon =
            trim($coupon);

        if (
            $coupon === ''
        ) {
            $this->resetGiftCoupon();

            addLog(
                'ResetGiftCoupon_4'
            );

            return 2;
        }

        /*
         * ---------------------------------------------------------
         * Gift Certificate lookup
         *
         * Existing conditions:
         * - remaining_value > 0
         * - status = 1
         * - gc_code matches
         * - expiry_date >= today
         * ---------------------------------------------------------
         */
        $CouponRS =
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
                $coupon
            )
            ->where(
                'expiry_date',
                '>=',
                DB::raw('curdate()')
            )
            ->get();

        $SubTotal =
            Session::get(
                'ShoppingCart.SubTotal',
                0
            );

        $log['giftcouponrecordset'] =
            json_encode(
                $CouponRS
            );

        addLog(
            'GiftCouponRecordset',
            $log
        );

        /*
         * ---------------------------------------------------------
         * No valid Gift Certificate
         * ---------------------------------------------------------
         */
        if (
            !$CouponRS
            ||
            $CouponRS->count() <= 0
        ) {
            $this->resetGiftCoupon();

            addLog(
                'ResetGiftCoupon_4'
            );

            return 2;
        }

        $giftCertificate =
            $CouponRS[0];

        /*
         * ---------------------------------------------------------
         * Minimum purchase
         * ---------------------------------------------------------
         */
        if (
            (float) $SubTotal
            <
            (float)
            $giftCertificate->minimum_purchase_value
        ) {
            $this->resetGiftCoupon();

            addLog(
                'ResetGiftCoupon_4'
            );

            return 2;
        }

        /*
         * ---------------------------------------------------------
         * GC ID validation
         * ---------------------------------------------------------
         */
        if (
            !isset(
                $giftCertificate->gc_id
            )
            ||
            $giftCertificate->gc_id <= 0
        ) {
            $this->resetGiftCoupon();

            addLog(
                'ResetGiftCoupon_4'
            );

            return 2;
        }

        /*
         * =========================================================
         * NORMAL CUSTOMER-GENERATED GIFT CERTIFICATE
         * is_added_by_admin = No
         * =========================================================
         */
        if (
            isset(
                $giftCertificate->is_added_by_admin
            )
            &&
            $giftCertificate->is_added_by_admin
                == 'No'
        ) {
            return $this->applyCustomerGiftCertificate(
                $giftCertificate,
                $CartInfo,
                $TotalAmount,
                $totvalue,
                $log
            );
        }

        /*
         * =========================================================
         * ADMIN-ADDED GIFT CERTIFICATE
         * is_added_by_admin = Yes
         * =========================================================
         */
        if (
            isset(
                $giftCertificate->is_added_by_admin
            )
            &&
            $giftCertificate->is_added_by_admin
                == 'Yes'
        ) {
            return $this->applyAdminGiftCertificate(
                $giftCertificate,
                $CartInfo,
                $TotalAmount,
                $totvalue,
                $log
            );
        }

        /*
         * Unknown source.
         */
        $this->resetGiftCoupon();

        addLog(
            'ResetGiftCoupon_4'
        );

        return 2;
    }

    /**
     * Apply normal customer-generated Gift Certificate.
     *
     * Existing logic:
     *
     * GiftCertificate
     *      ↓
     * OrderDetail
     *      ↓
     * Order
     *
     * Order status must be Pending or Completed.
     */
    protected function applyCustomerGiftCertificate(
        $giftCertificate,
        array $CartInfo,
        float $TotalAmount,
        float $totvalue,
        array $log
    ): int {
        /*
         * ---------------------------------------------------------
         * Order Detail
         * ---------------------------------------------------------
         */
        $OrderRESData =
            OrderDetail::where(
                'orders_detail_id',
                '=',
                $giftCertificate->orders_detail_id
            )
            ->get();

        if (
            $OrderRESData->count() <= 0
            ||
            !isset(
                $OrderRESData[0]['orders_detail_id']
            )
            ||
            $OrderRESData[0]['orders_detail_id']
                <= 0
        ) {
            $this->resetGiftCoupon();

            addLog(
                'ResetGiftCoupon_3'
            );

            return 2;
        }

        /*
         * ---------------------------------------------------------
         * Parent Order
         *
         * Existing statuses:
         * Pending / Completed
         * ---------------------------------------------------------
         */
        $OrderResVal =
            Order::where(
                'orders_id',
                '=',
                $OrderRESData[0]['orders_id']
            )
            ->whereIn(
                'status',
                [
                    'Pending',
                    'Completed',
                ]
            )
            ->get();

        if (
            $OrderResVal->count() <= 0
            ||
            !isset(
                $OrderResVal[0]['orders_id']
            )
            ||
            $OrderResVal[0]['orders_id']
                <= 0
        ) {
            $this->resetGiftCoupon();

            addLog(
                'ResetGiftCoupon_2'
            );

            return 2;
        }

        /*
         * ---------------------------------------------------------
         * Calculate applicable Gift Certificate value.
         *
         * Existing behavior:
         * If remaining value >= current net total,
         * use current net total.
         * ---------------------------------------------------------
         */
        $remainingValue =
            (float)
            $giftCertificate->remaining_value;

        $applicableValue =
            $remainingValue;

        if (
            $remainingValue >= $totvalue
        ) {
            $applicableValue =
                NumberFormat(
                    $totvalue
                );
        }

        /*
         * ---------------------------------------------------------
         * Save GiftCoupon session
         * ---------------------------------------------------------
         */
        $this->setGiftCoupon(
            $giftCertificate->gc_code,
            $applicableValue
        );

        /*
         * Existing remaining value calculation:
         *
         * original remaining
         * -
         * applicable value
         */
        $newValue =
            $remainingValue
            -
            Session::get(
                'ShoppingCart.GiftCoupon.Applicable_Value'
            );

        /*
         * ---------------------------------------------------------
         * Item-wise discount
         * ---------------------------------------------------------
         */
        if (
            $TotalAmount > 0
        ) {
            $this->setItemWiseDiscounts(
                $CartInfo,
                $applicableValue,
                $TotalAmount
            );
        }

        /*
         * ---------------------------------------------------------
         * Remaining Value
         * ---------------------------------------------------------
         */
        Session::put(
            'ShoppingCart.GiftCoupon.Remaining_Value',
            $newValue
        );

        $log['giftcouponRemaining_Value'] =
            json_encode(
                $newValue
            );

        addLog(
            'SetGiftCouponRemainingValue',
            $log
        );

        return 1;
    }

    /**
     * Apply admin-added Gift Certificate.
     */
    protected function applyAdminGiftCertificate(
        $giftCertificate,
        array $CartInfo,
        float $TotalAmount,
        float $totvalue,
        array $log
    ): int {
        /*
         * Existing admin branch does NOT require
         * OrderDetail / Order validation.
         */
        $remainingValue =
            (float)
            $giftCertificate->remaining_value;

        $applicableValue =
            $remainingValue;

        if (
            $remainingValue >= $totvalue
        ) {
            $applicableValue =
                NumberFormat(
                    $totvalue
                );
        }

        /*
         * ---------------------------------------------------------
         * Save GiftCoupon session
         * ---------------------------------------------------------
         */
        $this->setGiftCoupon(
            $giftCertificate->gc_code,
            $applicableValue
        );

        /*
         * ---------------------------------------------------------
         * Remaining value
         * ---------------------------------------------------------
         */
        $newValue =
            $remainingValue
            -
            Session::get(
                'ShoppingCart.GiftCoupon.Applicable_Value'
            );

        Session::put(
            'ShoppingCart.GiftCoupon.Remaining_Value',
            $newValue
        );

        $log['giftcouponRemaining_Value'] =
            json_encode(
                $newValue
            );

        addLog(
            'SetGiftCouponRemainingValue',
            $log
        );

        /*
         * ---------------------------------------------------------
         * Item-wise discount
         * ---------------------------------------------------------
         */
        if (
            $TotalAmount > 0
        ) {
            $this->setItemWiseDiscounts(
                $CartInfo,
                $applicableValue,
                $TotalAmount
            );
        }

        return 1;
    }

    /**
     * Get current net total.
     *
     * IMPORTANT:
     * This reads the already-calculated checkout/cart net total.
     *
     * The final CheckoutTotalsService will own the calculation
     * of this value.
     */
    protected function getNetTotal(): float
    {
        /*
         * Existing checkout flow should populate NetTotal.
         *
         * If your current session uses a different key,
         * connect this method to CheckoutTotalsService instead
         * of duplicating the entire pricing calculation here.
         */
        $netTotal =
            Session::get(
                'ShoppingCart.NetTotal',
                null
            );

        if (
            $netTotal !== null
        ) {
            return NumberFormat(
                $netTotal
            );
        }

        /*
         * Fallback to existing cart subtotal
         * when NetTotal has not yet been stored.
         */
        $subTotal =
            (float)
            Session::get(
                'ShoppingCart.SubTotal',
                0
            );

        return NumberFormat(
            max(
                0,
                $subTotal
            )
        );
    }

    /**
     * Set GiftCoupon session values.
     */
    protected function setGiftCoupon(
        string $code,
        $value
    ): void {
        Session::put(
            'ShoppingCart.GiftCoupon.Code',
            $code
        );

        Session::put(
            'ShoppingCart.GiftCoupon.Value',
            $value
        );

        Session::put(
            'ShoppingCart.GiftCoupon.Applicable_Value',
            $value
        );
    }

    /**
     * Existing item-wise Gift Certificate discount.
     *
     * Do NOT apply GC discount to:
     * - Deal products
     * - Free Gift products
     * - Free Sample products
     */
    protected function setItemWiseDiscounts(
        array $CartInfo,
        $giftCouponValue,
        $TotalAmount
    ): void {
        if (
            (float) $TotalAmount <= 0
        ) {
            return;
        }

        for (
            $i = 0;
            $i < count($CartInfo);
            $i++
        ) {
            $FreeGift = '';

            if (
                isset(
                    $CartInfo[$i]['IS_Free_Gift']
                )
            ) {
                $FreeGift =
                    $CartInfo[$i]['IS_Free_Gift'];
            }

            $FreeSample = '';

            if (
                isset(
                    $CartInfo[$i]['Is_Free_Sample']
                )
            ) {
                $FreeSample =
                    $CartInfo[$i]['Is_Free_Sample'];
            }

            if (
                isset(
                    $CartInfo[$i]['IsDealProducts']
                )
                &&
                $CartInfo[$i]['IsDealProducts']
                    != 'Yes'
                &&
                $FreeGift != 'Yes'
                &&
                $FreeSample != 'Yes'
            ) {
                /*
                 * Existing formula:
                 *
                 * GC value / eligible total amount
                 * → item-wise percentage
                 */
                $GiftCertificateDiscountItemWise =
                    (
                        $giftCouponValue * 100
                    )
                    /
                    $TotalAmount;

                $GiftCertificateDiscountCal =
                    (
                        $CartInfo[$i]['TotPrice']
                        *
                        $GiftCertificateDiscountItemWise
                    )
                    /
                    100;

                Session::put(
                    'ShoppingCart.Cart.'
                    . $i
                    . '.GiftCertificateItemWiseDiscout',
                    $GiftCertificateDiscountCal
                );
            }
        }
    }

    /**
     * Reset GiftCoupon session.
     */
    protected function resetGiftCoupon(): void
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
    }
}