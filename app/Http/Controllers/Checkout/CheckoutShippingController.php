<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;


class CheckoutShippingController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {
    }

    /**
     * Set selected shipping method and refresh checkout totals.
     *
     * One Page Checkout endpoint.
     *
     * Old flow:
     *
     * SetShippingMethod()
     *     ↓
     * CheckShippingMethod()
     *     ↓
     * CalculateShippingCharge()
     *     ↓
     * TaxCalculation()
     *     ↓
     * Insurance
     *     ↓
     * SetupCart()
     *
     * New flow:
     *
     * Controller
     *     ↓
     * CheckoutService
     *     ↓
     * Shipping / Tax / Insurance / Totals
     */
    public function setShippingMethod(
        Request $request
    ): JsonResponse {
        /*
         * ---------------------------------------------------------
         * AJAX validation
         * ---------------------------------------------------------
         */

        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        /*
         * ---------------------------------------------------------
         * Shipping method is required.
         * ---------------------------------------------------------
         */
        if (
            !$request->filled(
                'ShipMethodID'
            )
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Shipping method is required.',
            ], 422);
        }

        /*
         * ---------------------------------------------------------
         * Keep the existing request log.
         * ---------------------------------------------------------
         */
        addLog(
            'SetShippingMethodStart',
            [
                'ajax_request' =>
                    json_encode(
                        $request->all()
                    ),
            ]
        );

        /*
         * ---------------------------------------------------------
         * Resolve address.
         *
         * One Page Checkout sends the current address.
         *
         * If a value is missing, use the existing session address.
         * This is important for:
         *
         * - normal checkout
         * - direct product checkout
         * - PayPal cart
         * - Stripe cart
         * ---------------------------------------------------------
         */
        $address =
            $this->resolveShippingAddress(
                $request
            );

        /*
         * ---------------------------------------------------------
         * Existing direct payment/cart flow.
         *
         * We DO NOT remove these flags.
         *
         * Product Detail → direct checkout can still use:
         *
         * action = paypalcart
         * action = stripecart
         * ---------------------------------------------------------
         */
        $paymentContext = [
            'action' =>
                $request->input(
                    'action',
                    ''
                ),

            'isPayPalCart' =>
                $request->input(
                    'action'
                ) === 'paypalcart',

            'isStripeCart' =>
                $request->input(
                    'action'
                ) === 'stripecart',
        ];

        /*
         * ---------------------------------------------------------
         * Checkout context.
         * ---------------------------------------------------------
         */
        $context = [
            'shippingMethodId' =>
                (int)
                $request->input(
                    'ShipMethodID'
                ),

            'estimatedDeliveryDate' =>
                $request->input(
                    'EstDate',
                    ''
                ),

            'onlyGCPurchased' =>
                (int)
                $request->input(
                    'onlyGCPurchased',
                    0
                ),

            /*
             * Existing vendor flags.
             */
            'IsCosmo' =>
                $request->input(
                    'IsCosmo',
                    'No'
                ),

            'IsNandansons' =>
                $request->input(
                    'IsNandansons',
                    'No'
                ),

            'IsPerfumePW' =>
                $request->input(
                    'IsPerfumePW',
                    'No'
                ),

            'IsPCA' =>
                $request->input(
                    'IsPCA',
                    'No'
                ),

            'IsND' =>
                $request->input(
                    'IsND',
                    'No'
                ),

            'IsVenderItem' =>
                $request->input(
                    'IsVenderItem',
                    'No'
                ),

            /*
             * PayPal Product Page / direct checkout.
             *
             * Do not remove these values.
             */
            'isPayPalSubTotal' =>
                $request->input(
                    'isPayPalSubTotal',
                    0
                ),

            'shippingChargePayPalProductPage' =>
                $request->input(
                    'shipping_charge_paypal_product_page',
                    0
                ),
        ];

        /*
         * ---------------------------------------------------------
         * Execute checkout shipping refresh.
         * ---------------------------------------------------------
         */
        try {
            $result =
                $this->checkoutService
                    ->refreshAfterShippingMethod(
                        $context,
                        $address,
                        $paymentContext
                    );


            /*
             * -----------------------------------------------------
             * Store selected shipping method/date.
             *
             * This preserves the existing session behavior.
             * -----------------------------------------------------
             */
            if (
                ($result['status'] ?? 'error')
                    === 'success'
            ) {
                Session::put(
                    'ShoppingCart.Shipping.ShippingMethodID',
                    $context[
                        'shippingMethodId'
                    ]
                );

                Session::put(
                    'ShoppingCart.EstimatedDeliveryDate',
                    $context[
                        'estimatedDeliveryDate'
                    ]
                );

                Session::put(
                    'ShoppingCart.Shipping.ShippingDays',
                    $context[
                        'estimatedDeliveryDate'
                    ]
                );
            }

            /*
             * -----------------------------------------------------
             * GA4
             *
             * Old SetShippingMethod() generated the shipping
             * analytics tag here.
             *
             * For One Page Checkout we return the event data
             * instead of rendering HTML.
             * -----------------------------------------------------
             */
            $result['shippingAnalytics'] =
                $this->buildShippingAnalytics();

            return response()->json(
                $result
            );
        } catch (
            \Throwable $e
        ) {
            addLog(
                'SetShippingMethodError',
                [
                    'message' =>
                        $e->getMessage(),

                    'request' =>
                        $request->all(),

                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status' => 'error',

                'message' =>
                    'Unable to update shipping method.',

                /*
                 * Do not expose exception details to customer.
                 */
            ], 500);
        }
    }

    /**
     * Apply or remove Gift Certificate for One Page Checkout.
     *
     * POST /checkout/gift-certificate
     *
     * Request:
     * - action=apply|remove
     * - code=certificate code (required for apply)
     */
    public function setGiftCertificate(
        Request $request
    ): JsonResponse {
        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        $action =
            strtolower(
                trim(
                    (string)
                    $request->input(
                        'action',
                        'apply'
                    )
                )
            );

        $code =
            trim(
                (string)
                $request->input(
                    'code',
                    ''
                )
            );

        if (
            !in_array(
                $action,
                ['apply', 'remove'],
                true
            )
        ) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Invalid Gift Certificate action.',
            ], 422);
        }

        if (
            $action === 'apply'
            &&
            $code === ''
        ) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Gift Certificate code is required.',
            ], 422);
        }

        addLog(
            'SetGiftCertificateStart',
            [
                'action' => $action,
            ]
        );

        try {
            $result =
                $this->checkoutService
                    ->setGiftCertificate(
                        $action,
                        $code
                    );

            $httpStatus =
                ($result['status'] ?? 'error')
                === 'success'
                    ? 200
                    : 422;

            return response()->json(
                $result,
                $httpStatus
            );
        } catch (\Throwable $e) {
            addLog(
                'SetGiftCertificateError',
                [
                    'action' => $action,
                    'message' =>
                        $e->getMessage(),
                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status' => 'error',
                'message' =>
                    'Unable to update Gift Certificate.',
            ], 500);
        }
    }

    /**
     * Set or remove Request Signature for One Page Checkout.
     *
     * POST /checkout/shipping-signature
     */
    public function setShippingSignature(
        Request $request
    ): JsonResponse {
        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        $action = strtolower(trim((string) $request->input(
            'action',
            'add'
        )));

        if (!in_array($action, ['add', 'remove'], true)) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Invalid shipping signature action.',
            ], 422);
        }

        addLog('SetShippingSignatureStart', [
            'action' => $action,
            'ajax_request' =>
                json_encode($request->all()),
        ]);

        try {
            $result =
                $this->checkoutService
                    ->setShippingSignature($action);

            if (($result['status'] ?? 'error') !== 'success') {
                return response()->json($result, 422);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            addLog('SetShippingSignatureError', [
                'action' => $action,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' =>
                    'Unable to update shipping signature.',
            ], 500);
        }
    }

    /**
     * Set or remove gift wrapping for One Page Checkout.
     *
     * POST /checkout/gift-wrapping
     *
     * Optional productId preserves the existing item-specific
     * gift wrapping behavior. When omitted, the existing cart
     * gift_wrap flags are used.
     */
    public function setGiftWrapping(
        Request $request
    ): JsonResponse {
        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        $action =
            strtolower(
                trim(
                    (string)
                    $request->input(
                        'action',
                        'add'
                    )
                )
            );

        if (
            !in_array(
                $action,
                ['add', 'remove'],
                true
            )
        ) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Invalid gift wrapping action.',
            ], 422);
        }

        $productId =
            trim(
                (string)
                $request->input(
                    'productId',
                    ''
                )
            );

        addLog(
            'SetGiftWrappingStart',
            [
                'action' => $action,
                'product_id' => $productId,
                'ajax_request' =>
                    json_encode(
                        $request->all()
                    ),
            ]
        );

        try {
            $result =
                $this->checkoutService
                    ->setGiftWrapping(
                        $action,
                        $productId
                    );

            if (
                ($result['status'] ?? 'error')
                !== 'success'
            ) {
                return response()->json(
                    $result,
                    422
                );
            }

            return response()->json(
                $result
            );
        } catch (\Throwable $e) {
            addLog(
                'SetGiftWrappingError',
                [
                    'action' => $action,
                    'product_id' => $productId,
                    'message' =>
                        $e->getMessage(),
                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status' => 'error',
                'message' =>
                    'Unable to update gift wrapping.',
            ], 500);
        }
    }

    /**
     * Set or remove shipping insurance for One Page Checkout.
     *
     * New checkout endpoint:
     * POST /checkout/shipping-insurance
     *
     * The business rules remain inside ShippingInsuranceService;
     * this controller only validates the request and returns JSON.
     */
    public function setShippingInsurance(
        Request $request
    ): JsonResponse {
        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        $action =
            strtolower(
                trim(
                    (string)
                    $request->input(
                        'action',
                        'add'
                    )
                )
            );

        if (!in_array($action, ['add', 'remove'], true)) {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Invalid shipping insurance action.',
            ], 422);
        }

        addLog(
            'SetShippingInsuranceStart',
            [
                'action' => $action,
                'ajax_request' =>
                    json_encode(
                        $request->all()
                    ),
            ]
        );

        try {
            $result =
                $this->checkoutService
                    ->setShippingInsurance(
                        $action
                    );

            $status =
                $result['status'] ?? 'error';

            if ($status !== 'success') {
                return response()->json(
                    $result,
                    422
                );
            }

            return response()->json(
                $result
            );
        } catch (\Throwable $e) {
            addLog(
                'SetShippingInsuranceError',
                [
                    'action' => $action,
                    'message' => $e->getMessage(),
                    'request' => $request->all(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status' => 'error',
                'message' =>
                    'Unable to update shipping insurance.',
            ], 500);
        }
    }

    /**
     * Resolve current shipping address.
     *
     * Request has priority because One Page Checkout updates
     * the address through AJAX.
     */
    protected function resolveShippingAddress(
        Request $request
    ): array {
        $sessionAddress =
            Session::get(
                'ShoppingCart.ShippingAddress',
                []
            );

        $country =
            $request->input(
                'country'
            );

        $state =
            $request->input(
                'state'
            );

        $zip =
            $request->input(
                'zip'
            );

        $city =
            $request->input(
                'city'
            );

        /*
         * Request values override session values.
         */
        $address = [
            'country' =>
                $country !== null
                    ? $country
                    : (
                        $sessionAddress[
                            'country'
                        ] ?? ''
                    ),

            'state' =>
                $state !== null
                    ? $state
                    : (
                        $sessionAddress[
                            'state'
                        ] ?? ''
                    ),

            'zip' =>
                $zip !== null
                    ? $zip
                    : (
                        $sessionAddress[
                            'zip'
                        ] ?? ''
                    ),

            'city' =>
                $city !== null
                    ? $city
                    : (
                        $sessionAddress[
                            'city'
                        ] ?? ''
                    ),

            'address1' =>
                $request->input(
                    'address1',
                    $sessionAddress[
                        'address1'
                    ] ?? ''
                ),

            'address2' =>
                $request->input(
                    'address2',
                    $sessionAddress[
                        'address2'
                    ] ?? ''
                ),
        ];

        /*
         * Keep the current address in session.
         */
        Session::put(
            'ShoppingCart.ShippingAddress',
            array_merge(
                $sessionAddress,
                $address
            )
        );

        return $address;
    }

    /**
     * Build shipping analytics payload.
     *
     * Old SetShippingMethod() used:
     *
     * googleAnalyticsGA4(
     *     "ShippingMethods",
     *     Cart,
     *     NetTotal,
     *     Coupons
     * )
     *
     * We don't render HTML here.
     */
    protected function buildShippingAnalytics(): array
    {
        $onlyGCPurchased =
            $this->getCartAttribute(
                'onlyGCPurchased'
            );

        if (
            $onlyGCPurchased == 1
        ) {
            return [
                'event' => '',
            ];
        }

        return [
            'event' =>
                'ShippingMethods',
        ];
    }

    /**
     * Read a cart attribute.
     *
     * Kept small intentionally.
     */
    protected function getCartAttribute(
        string $attribute
    ) {
        $cart =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        if (
            empty($cart)
        ) {
            return 0;
        }

        if (
            $attribute === 'onlyGCPurchased'
        ) {
            foreach (
                $cart as $item
            ) {
                /*
                 * If any non-Gift-Certificate item exists,
                 * the cart is not GC-only.
                 *
                 * The CartAttributeService is the actual source
                 * of truth; this is only a safe fallback for the
                 * analytics response.
                 */
                $sku =
                    $item['SKU']
                    ?? '';

                $gcSku =
                    config(
                        'global.GIFT_CERTIFICATE_SKU'
                    );

                $gcSku1 =
                    config(
                        'global.GIFT_CERTIFICATE_SKU1'
                    );

                if (
                    $sku !== $gcSku
                    &&
                    $sku !== $gcSku1
                ) {
                    return 0;
                }
            }

            return 1;
        }

        return 0;
    }

    /**
     * Get available shipping methods for the current checkout address.
     *
     * ShippingService owns the actual shipping-method business logic.
     * Controller only prepares address/flags and returns JSON.
     */


    public function getAvailableShippingMethods(
        Request $request
    ): JsonResponse
    {

        if (!$request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request.',
            ], 400);
        }

        $country = trim(
            (string) $request->input('country', '')
        );

        $state = trim(
            (string) $request->input('state', '')
        );

        $zip = trim(
            (string) $request->input('zip', '')
        );

        if ($country === '' || $zip === '') {
            return response()->json([
                'status' => 'error',
                'message' =>
                    'Shipping country and ZIP are required.',
                'shippingMethods' => [],
            ], 422);
        }

        addLog(
            'GetAvailableShippingMethodsStart',
            [
                'ajax_request' =>
                    json_encode(
                        $request->all()
                    ),
            ]
        );

        try {
            /*
             * Resolve request address and keep it in session.
             */
            $address =
                $this->resolveShippingAddress(
                    $request
                );

            /*
             * These flags are already supported by
             * ShippingService::getAvailableMethods().
             *
             * Do not move shipping business rules into
             * this controller.
             */
            $flags = [
                'IsCosmo' =>
                    $request->input(
                        'IsCosmo',
                        'No'
                    ),

                'IsNandansons' =>
                    $request->input(
                        'IsNandansons',
                        'No'
                    ),

                'IsPerfumePW' =>
                    $request->input(
                        'IsPerfumePW',
                        'No'
                    ),

                'IsPCA' =>
                    $request->input(
                        'IsPCA',
                        'No'
                    ),

                'IsND' =>
                    $request->input(
                        'IsND',
                        'No'
                    ),

                'IsVenderItem' =>
                    $request->input(
                        'IsVenderItem',
                        'No'
                    ),

                'IsMaxaromaTwoDelivery' =>
                    $request->input(
                        'IsMaxaromaTwoDelivery',
                        'No'
                    ),

                'ISMaxTwoItem' =>
                    $request->input(
                        'ISMaxTwoItem',
                        'No'
                    ),

                'ISMax2dayVal' =>
                    $request->input(
                        'ISMax2dayVal',
                        'No'
                    ),

                'onlyGCPurchased' =>
                    (int) $request->input(
                        'onlyGCPurchased',
                        0
                    ),

                'selectedShippingMethodId' =>
                    (int) Session::get(
                        'ShoppingCart.Shipping.ShippingMethodID',
                        0
                    ),
            ];

            $result =
                $this->checkoutService
                    ->getAvailableShippingMethods(
                        $address,
                        $flags
                    );

            addLog(
                'GetAvailableShippingMethodsEnd',
                [
                    'status' =>
                        $result['status']
                        ?? 'success',
                ]
            );

            return response()->json(
                $result
            );
        } catch (
            \Throwable $e
        ) {
            addLog(
                'GetAvailableShippingMethodsError',
                [
                    'message' =>
                        $e->getMessage(),

                    'request' =>
                        $request->all(),

                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status' => 'error',

                'message' =>
                    'Unable to load shipping methods.',

                'shippingMethods' => [],
            ], 500);
        }
    }

}
