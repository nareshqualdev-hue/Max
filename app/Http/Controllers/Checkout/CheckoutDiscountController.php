<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutService;
use App\Services\Discount\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CheckoutDiscountController extends Controller
{
    public function __construct(
        protected CouponService $couponService,
        protected CheckoutService $checkoutService
    ) {
    }

    /**
     * Apply a normal Coupon or a Yotpo Reward.
     *
     * pu_coupon is shared by both.
     * CouponService determines the type from source:
     *
     * source = Yotpo -> Reward
     * source != Yotpo -> Coupon
     */
    public function apply(
        Request $request
    ): JsonResponse {
        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        $code = trim(
            (string) $request->input(
                'code',
                $request->input(
                    'coupon_code',
                    $request->input(
                        'coupon_number',
                        ''
                    )
                )
            )
        );

        if ($code === '') {
            return response()->json([
                'status' => 'error',
                'error' => 1,
                'message' => 'Invalid Coupon Code.',
            ], 422);
        }

        $normalUser = Auth::user();

        if (Auth::guard('store')->check()) {
            $normalUser =
                Auth::guard('web')->user();
        }

        $customerId =
            $normalUser
                ? Session::get(
                    'sess_icustomerid'
                )
                : null;

        addLog(
            'CheckoutDiscountApplyStart',
            [
                'coupon_code' => $code,
                'customer_id' => $customerId,
            ]
        );

        try {
            $result =
                $this->couponService->apply(
                    $code,
                    $customerId
                );

            if (
                isset($result['error'])
                &&
                (int) $result['error'] === 1
            ) {
                return response()->json(
                    [
                        'status' => 'error',
                        'error' => 1,
                        'message' =>
                            $result['message']
                            ?? 'Unable to apply coupon.',
                    ],
                    422
                );
            }

            /*
             * Coupon/Reward changes can affect shipping,
             * tax and the final checkout total.
             *
             * CheckoutService remains the central orchestrator.
             */
            $checkout =
                $this->checkoutService
                    ->refresh(
                        'discount'
                    );

            return response()->json([
                'status' => 'success',
                'error' => 0,
                'message' =>
                    $result['message']
                    ?? 'Coupon applied successfully.',
                'discount' => $result,
                'checkout' => $checkout,
                'totals' =>
                    $checkout['totals']
                    ?? [],
            ]);
        } catch (\Throwable $e) {
            addLog(
                'CheckoutDiscountApplyError',
                [
                    'coupon_code' => $code,
                    'customer_id' => $customerId,
                    'message' =>
                        $e->getMessage(),
                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status' => 'error',
                'error' => 1,
                'message' =>
                    'Unable to apply coupon. Please try again.',
            ], 500);
        }
    }

    /**
     * Remove the currently applied normal Coupon.
     *
     * Preserve the existing Apple Pay / Google Pay
     * payment-intent restriction.
     */
    public function removeCoupon(
        Request $request
    ): JsonResponse {
        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        $paymentIntentId = '';

        if (
            Session::has('StripePaymentType')
            &&
            (
                Session::get('StripePaymentType')
                === 'Google Pay'
                ||
                Session::get('StripePaymentType')
                === 'Apple Pay'
            )
        ) {
            $paymentIntentId =
                Session::get(
                    'ShoppingCart.apple_google_paymentintentid',
                    ''
                );
        }

        if ($paymentIntentId !== '') {
            return response()->json([
                'status' => 'error',
                'error' => 1,
                'message' =>
                    'Coupon Code cannot be removed for now.',
            ], 422);
        }

        try {
            $this->couponService
                ->removeCoupon();

            $checkout =
                $this->checkoutService
                    ->refresh(
                        'discount'
                    );

            $message =
                'Applied coupon code removed successfully.';

            addLog(
                'CheckoutRemoveCoupon',
                [
                    'message' => $message,
                ]
            );

            return response()->json([
                'status' => 'success',
                'error' => 0,
                'message' => $message,
                'checkout' => $checkout,
                'totals' =>
                    $checkout['totals']
                    ?? [],
            ]);
        } catch (\Throwable $e) {
            addLog(
                'CheckoutRemoveCouponError',
                [
                    'message' =>
                        $e->getMessage(),
                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status' => 'error',
                'error' => 1,
                'message' =>
                    'Unable to remove coupon. Please try again.',
            ], 500);
        }
    }

    /**
     * Remove the currently applied Yotpo Reward.
     */
    public function removeYotpoReward(
        Request $request
    ): JsonResponse {
        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        try {
            $this->couponService
                ->removeYotpoReward();

            $checkout =
                $this->checkoutService
                    ->refresh(
                        'discount'
                    );

            $message =
                'Applied yotpo reward discount removed successfully.';

            addLog(
                'CheckoutRemoveYotpoReward',
                [
                    'message' => $message,
                ]
            );

            return response()->json([
                'status' => 'success',
                'error' => 0,
                'message' => $message,
                'checkout' => $checkout,
                'totals' =>
                    $checkout['totals']
                    ?? [],
            ]);
        } catch (\Throwable $e) {
            addLog(
                'CheckoutRemoveYotpoRewardError',
                [
                    'message' =>
                        $e->getMessage(),
                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status' => 'error',
                'error' => 1,
                'message' =>
                    'Unable to remove Yotpo reward. Please try again.',
            ], 500);
        }
    }
}
