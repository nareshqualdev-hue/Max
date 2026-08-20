<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutTotalsController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService
    ) {
    }

    /**
     * POST /checkout/totals
     *
     * Recalculate checkout totals via AJAX.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /*
         * Keep this controller thin.
         *
         * Validation/business logic belongs in:
         * - Form Request
         * - CheckoutService
         * - CartPricingEngine
         */

        $options = [
            /*
             * Selected shipping method.
             */
            'shipping_mode_id' =>
                $request->input('shipping_mode_id'),

            /*
             * Shipping signature.
             */
            'signature' =>
                $request->boolean('signature'),

            'signature_amount' =>
                (float) $request->input(
                    'signature_amount',
                    0
                ),

            /*
             * Shipping insurance.
             */
            'insurance' =>
                $request->boolean('insurance'),

            'insurance_amount' =>
                (float) $request->input(
                    'insurance_amount',
                    0
                ),

            /*
             * Address can later be replaced by
             * AddressService / address DTO.
             */
            'address' =>
                $request->input('address'),

            /*
             * Discount context.
             */
            'discount_context' =>
                $request->input(
                    'discount_context',
                    []
                ),
        ];

        $pricing =
            $this->checkoutService->recalculate(
                $options
            );

        return response()->json([
            'success' => true,

            'data' => [
                'subtotal' =>
                    $pricing->subtotal,

                'discount_total' =>
                    $pricing->discountTotal,

                'discounts' =>
                    $pricing->discounts,

                'shipping_amount' =>
                    $pricing->shippingAmount,

                'insurance_amount' =>
                    $pricing->insuranceAmount,

                'signature_amount' =>
                    $pricing->signatureAmount,

                'tax_amount' =>
                    $pricing->taxAmount,

                'grand_total' =>
                    $pricing->grandTotal,

                'free_gift' =>
                    $pricing->freeGift,

                'free_sample' =>
                    $pricing->freeSample,
            ],
        ]);
    }
}