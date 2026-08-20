<?php

namespace App\Services\Discount;

use App\Models\Customer;
use App\Models\RewardRule;
use Illuminate\Support\Facades\Session;

class RewardDiscountService
{
    /**
     * Apply customer's available reward points
     * as an automatic reward discount.
     *
     * Migration of:
     * CartTrait::ApplyAutoRewardDiscount()
     */
    public function apply(): ?float
    {
        /*
         * Existing behavior:
         * If YOTPO program is disabled, do nothing.
         */
        if (
            config('global.YOTPO_PROG') == false
        ) {
            return null;
        }

        /*
         * Existing behavior:
         * Always clear previous reward calculation
         * before recalculating.
         */
        Session::forget(
            'ShoppingCart.Reward_array'
        );

        /*
         * Keep the same calculation dependency:
         *
         * NetTotal - all existing discounts
         */
        $netTotal =
            $this->getNetTotal();

        $allDiscount =
            $this->getAllDiscounts();

        $discount =
            $allDiscount['TotalDiscount']
            ?? 0;

        $subtotal =
            NumberFormat(
                $netTotal - $discount
            );

        $rewardDiscount = 0;

        /*
         * Existing eligibility:
         *
         * etype = M
         * user type = retailer
         */
        if (
            Session::get('etype') !== 'M'
            ||
            strtolower(
                Session::get('eusertype') ?? ''
            ) !== 'retailer'
        ) {
            return null;
        }

        $customerId =
            (int)
            Session::get(
                'sess_icustomerid'
            );

        if (
            $customerId <= 0
        ) {
            return null;
        }

        /*
         * Customer reward points.
         */
        $customerReward =
            Customer::where(
                'customer_id',
                $customerId
            )
            ->where(
                'status',
                '1'
            )
            ->first();

        /*
         * Redeem rule.
         */
        $redeemReward =
            RewardRule::where(
                'erewardrule',
                'redeem'
            )
            ->first();

        /*
         * Maximum reward rule.
         */
        $maxReward =
            RewardRule::where(
                'erewardrule',
                'max'
            )
            ->first();

        /*
         * Existing code requires both
         * customer and max rule.
         */
        if (
            !$customerReward
            ||
            !$maxReward
            ||
            !$redeemReward
        ) {
            return null;
        }

        /*
         * Customer must have at least
         * the configured maximum threshold.
         */
        $rewardPoints =
            (float)
            $customerReward->iRewardpoint;

        if (
            $rewardPoints <
            (float)
            $maxReward->fcharge
        ) {
            return null;
        }

        /*
         * Existing calculation:
         *
         * refer_amount =
         * customer points / redeem points
         *
         * reward discount =
         * number of redeem blocks * order amount
         */
        $referAmount =
            $rewardPoints
            /
            (float)
            $redeemReward->fcharge;

        $rewardDiscount =
            (int)
            $referAmount
            *
            (float)
            $redeemReward->forderamount;

        /*
         * Points consumed for this discount.
         */
        $remainCount =
            (float)
            $redeemReward->fcharge
            *
            (int)
            $referAmount;

        /*
         * Points remaining after applying reward.
         */
        $rewardRemaining =
            $rewardPoints
            -
            $remainCount;

        $totalRewardPoint =
            $rewardPoints;

        /*
         * Existing behavior:
         *
         * Reward discount must be LESS than subtotal.
         *
         * If equal or greater, Reward_array is not created.
         */
        if (
            NumberFormat(
                $rewardDiscount
            ) < $subtotal
        ) {
            $rewardData = [
                'RemainRewardPoint' =>
                    NumberFormat(
                        $rewardRemaining
                    ),

                'TotalRewardPoint' =>
                    NumberFormat(
                        $totalRewardPoint
                    ),

                'RewardDiscount' =>
                    NumberFormat(
                        $rewardDiscount
                    ),

                'AppliedRewardPoint' =>
                    NumberFormat(
                        $remainCount
                    ),
            ];

            Session::put(
                'ShoppingCart.Reward_array',
                $rewardData
            );

            return NumberFormat(
                $rewardDiscount
            );
        }

        return null;
    }

    /**
     * Get current NetTotal.
     *
     * This is intentionally kept as a service dependency
     * instead of copying CartTrait calculation here.
     *
     * Checkout/Cart service will provide this calculation.
     */
    protected function getNetTotal(): float
    {
        return (float)
            Session::get(
                'ShoppingCart.NetTotal',
                0
            );
    }

    /**
     * Get current discount summary.
     *
     * The final CheckoutTotals/DiscountService will provide
     * the consolidated discount amount.
     */
    protected function getAllDiscounts(): array
    {
        return [
            'TotalDiscount' =>
                (float)
                Session::get(
                    'ShoppingCart.TotalDiscount',
                    0
                ),
        ];
    }
}