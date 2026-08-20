<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\UpdateAddressRequest;
use App\Services\Checkout\AddressService;
use Illuminate\Http\JsonResponse;

class CheckoutAddressController extends Controller
{
    public function __construct(
        private AddressService $addressService
    ) {
    }

    /**
     * Update checkout billing/shipping address.
     */
    public function __invoke(
        UpdateAddressRequest $request
    ): JsonResponse {
        $result = $this->addressService
            ->updateCheckoutAddress(
                $request->validated()
            );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}