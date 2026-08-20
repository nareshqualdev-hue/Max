<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Services\Checkout\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckoutPaymentController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $paymentGatewayManager
    ) {
    }

    /**
     * Place checkout order / process payment.
     */
    public function __invoke(
        PlaceOrderRequest $request
    ): JsonResponse {
        $data = $request->validated();

        try {
            $result = DB::transaction(function () use ($data) {
                return $this->paymentGatewayManager
                    ->process($data);
            });

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to process your order.',
            ], 422);
        }
    }
}