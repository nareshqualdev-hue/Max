<?php

namespace App\Services\Discount;

use Illuminate\Support\Facades\Session;

class DiscountService
{
    /**
     * Get all currently applied discounts.
     *
     * Migration of:
     * CartTrait::GetAllDiscounts()
     *
     * @param string $discountName
     * @return array|float
     */
    public function getAll(string $discountName = ''): array|float
    {
        $log = [
            'DiscountName' => $discountName,
        ];

        addLog(
            'GetAllDiscountStart',
            $log
        );

        $discounts = [];

        /*
         * ---------------------------------------------------------
         * Auto Discount
         * ---------------------------------------------------------
         */
        $this->addDiscount(
            $discounts,
            'AutoDiscount',
            'Auto Discount',
            'ShoppingCart.AutoDiscount'
        );

        /*
         * ---------------------------------------------------------
         * Yotpo Reward Discount
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.YotpoRewardDiscount'
            )
            &&
            Session::get(
                'ShoppingCart.YotpoRewardDiscount'
            ) > 0
        ) {
            $discounts['YotpoRewardDiscount'] = [
                'label' =>
                    'Reward Discount',

                'discount' =>
                    Session::get(
                        'ShoppingCart.YotpoRewardDiscount'
                    ),

                'Ricon' =>
                    'Yes',

                'dataid' =>
                    'YotpoRewardDiscount',
            ];
        }

        /*
         * ---------------------------------------------------------
         * Quantity Discount
         * ---------------------------------------------------------
         */
        $this->addDiscount(
            $discounts,
            'QuantityDiscount',
            'Quantity Discount',
            'ShoppingCart.QuantityDiscount'
        );

        /*
         * ---------------------------------------------------------
         * Coupon Discount
         *
         * IMPORTANT:
         *
         * Existing legacy code adds:
         * FirstCouponDiscount
         * +
         * SecondCouponDiscount
         *
         * But SecondCoupon flow is intentionally NOT used
         * in the new checkout.
         *
         * Therefore only FirstCouponDiscount/current
         * CouponDiscount is used here.
         * ---------------------------------------------------------
         */
        $couponDiscount = 0.0;

        if (
            Session::has(
                'ShoppingCart.PromoCoupon.FirstCouponDiscount'
            )
            &&
            Session::get(
                'ShoppingCart.PromoCoupon.FirstCouponDiscount'
            ) > 0
        ) {
            $couponDiscount =
                NumberFormat(
                    Session::get(
                        'ShoppingCart.PromoCoupon.FirstCouponDiscount'
                    )
                );
        }

        /*
         * If CouponService already stored the final
         * CouponDiscount, use it when FirstCouponDiscount
         * is not available.
         */
        if (
            $couponDiscount <= 0
            &&
            Session::has(
                'ShoppingCart.PromoCoupon.CouponDiscount'
            )
            &&
            Session::get(
                'ShoppingCart.PromoCoupon.CouponDiscount'
            ) > 0
        ) {
            $couponDiscount =
                NumberFormat(
                    Session::get(
                        'ShoppingCart.PromoCoupon.CouponDiscount'
                    )
                );
        }

        if (
            $couponDiscount > 0
        ) {
            Session::put(
                'ShoppingCart.PromoCoupon.CouponDiscount',
                $couponDiscount
            );

            $discounts['CouponDiscount'] = [
                'label' =>
                    'Coupon Discount',

                'discount' =>
                    $couponDiscount,

                'Ricon' =>
                    'Yes',

                'dataid' =>
                    'CouponDiscount',
            ];
        }

        /*
         * ---------------------------------------------------------
         * Gift Certificate
         * ---------------------------------------------------------
         *
         * Existing session structure:
         *
         * ShoppingCart.GiftCoupon.Value
         *
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.GiftCoupon'
            )
        ) {
            $giftCoupon =
                Session::get(
                    'ShoppingCart.GiftCoupon'
                );

            if (
                is_array($giftCoupon)
                &&
                isset(
                    $giftCoupon['Value']
                )
                &&
                $giftCoupon['Value'] > 0
            ) {
                $discounts['GiftCoupon'] = [
                    'label' =>
                        'Gift Certificate Discount',

                    'discount' =>
                        $giftCoupon['Value'],

                    'Ricon' =>
                        'Yes',

                    'dataid' =>
                        'GiftCoupon',
                ];
            }
        }

        /*
         * ---------------------------------------------------------
         * Auto Refer Discount
         * ---------------------------------------------------------
         */
        $this->addDiscount(
            $discounts,
            'AutoReferDiscount',
            'Auto Refer Discount',
            'ShoppingCart.AutoReferDiscount'
        );

        /*
         * ---------------------------------------------------------
         * Automatic Reward Discount
         * ---------------------------------------------------------
         *
         * This is different from YotpoRewardDiscount.
         *
         * YotpoRewardDiscount:
         * ShoppingCart.YotpoRewardDiscount
         *
         * AutoRewardDiscount:
         * ShoppingCart.Reward_array.RewardDiscount
         * ---------------------------------------------------------
         */
        if (
            Session::has(
                'ShoppingCart.Reward_array.RewardDiscount'
            )
            &&
            Session::get(
                'ShoppingCart.Reward_array.RewardDiscount'
            ) > 0
        ) {
            $discounts['AutoRewardDiscount'] = [
                'label' =>
                    'Reward Discount',

                'discount' =>
                    Session::get(
                        'ShoppingCart.Reward_array.RewardDiscount'
                    ),
            ];
        }

        /*
         * ---------------------------------------------------------
         * Credit Limit Discount
         * ---------------------------------------------------------
         */
        $this->addDiscount(
            $discounts,
            'CreditLimitDiscount',
            'Credit Limit Discount',
            'ShoppingCart.credit_limit_discount'
        );

        /*
         * ---------------------------------------------------------
         * BOGO Discount
         * ---------------------------------------------------------
         */
        $this->addDiscount(
            $discounts,
            'DogoDiscount',
            'Bogo Discount',
            'ShoppingCart.DogoDiscount'
        );

        /*
         * ---------------------------------------------------------
         * Specific discount requested
         *
         * Existing behavior:
         *
         * GetAllDiscounts('CouponDiscount')
         * → only CouponDiscount value
         *
         * If discount doesn't exist:
         * → 0
         * ---------------------------------------------------------
         */
        if (
            $discountName !== ''
        ) {
            $log['Discounts'] =
                json_encode(
                    $discounts
                );

            addLog(
                'GetAllDiscount',
                $log
            );

            $discountDetail = 0.0;

            if (
                isset(
                    $discounts[$discountName]
                )
            ) {
                $discountDetail =
                    NumberFormat(
                        $discounts[
                            $discountName
                        ]['discount']
                    );
            }

            return $discountDetail;
        }

        /*
         * ---------------------------------------------------------
         * Total Discount
         * ---------------------------------------------------------
         */
        $totalDiscount =
            array_sum(
                array_map(
                    'floatval',
                    array_column(
                        $discounts,
                        'discount'
                    )
                )
            );

        $discountInfo = [
            'Discounts' =>
                $discounts,

            'TotalDiscount' =>
                NumberFormat(
                    $totalDiscount
                ),
        ];

        $log['DiscountInfo'] =
            json_encode(
                $discountInfo
            );

        addLog(
            'GetAllDiscount',
            $log
        );

        return $discountInfo;
    }

    /**
     * Get one discount only.
     */
    public function get(
        string $discountName
    ): float {
        return (float)
            $this->getAll(
                $discountName
            );
    }

    /**
     * Get total discount only.
     */
    public function getTotal(): float
    {
        $result =
            $this->getAll();

        return (float)
            (
                $result['TotalDiscount']
                ?? 0
            );
    }

    /**
     * Add a session-based discount when its value
     * exists and is greater than zero.
     */
    protected function addDiscount(
        array &$discounts,
        string $key,
        string $label,
        string $sessionKey
    ): void {
        if (
            !Session::has(
                $sessionKey
            )
        ) {
            return;
        }

        $discount =
            Session::get(
                $sessionKey
            );

        if (
            $discount <= 0
        ) {
            return;
        }

        $discounts[$key] = [
            'label' =>
                $label,

            'discount' =>
                $discount,
        ];
    }
}