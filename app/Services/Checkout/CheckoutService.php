<?php

namespace App\Services\Checkout;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Services\Cart\CartCalculatorService;

class CheckoutService
{
    public function __construct(
        protected CartAttributeService $cartAttributeService,
		protected TaxService $taxService,
		protected ShippingService $shippingService,
		protected ShippingInsuranceService $shippingInsuranceService,
		protected GiftWrappingService $giftWrappingService,
		protected ShippingSignatureService $shippingSignatureService,
		protected GiftCertificateService $giftCertificateService,
		protected PaymentAvailabilityService $paymentAvailabilityService,
		protected CheckoutTotalsService $checkoutTotalsService,
		protected CartCalculatorService $cartCalculatorService,
		protected \App\Services\Discount\FreeGiftService $freeGiftService
    ) {
    }

    /**
     * Prepare the initial One Page Checkout page.
     *
     * This replaces the page-initialization portion of the legacy
     * ShoppingcartController::CheckoutPage().
     *
     * Business calculations remain owned by the existing checkout
     * services. The Blade reads the current checkout state from
     * Session, so only required checkout initialization is done here.
     */
    public function prepareCheckout(
        \Illuminate\Http\Request $request
    ): array {
        addLog('CheckoutPrepareStart');

        /*
         * Preserve existing Afterpay / Store checkout session rules.
         */
        if (
            $request->input('method') === 'AP'
        ) {
            config([
                'global.address_verification' => false,
            ]);
        }

        if (
            Auth::guard('store')->check()
            &&
            Session::get('ShoppingCart.OrderType') === 'Store'
        ) {
            Session::forget('shipping_insurance_charge');
            Session::forget('ShoppingCart.ShippingSignature');
        }

        if (Auth::guard('store')->check()) {
            foreach ([
                'ShoppingCart.OrderID',
                'ShoppingCart.StoreOrderID',
                'ShoppingCart.StoreCashSplitPaymentId',
                'ShoppingCart.WebsiteCashSplitPaymentId',
                'ShoppingCart.StoreCashPaymentId',
                'ShoppingCart.WebsiteOrderID',
            ] as $key) {
                Session::forget($key);
            }
        }

        /*
         * Afterpay entry flow.
         */
        if ($request->input('method') === 'pa') {
            Session::forget(
                'ShoppingCart.AfterPay.Checkout_Token'
            );
        }

        /*
         * Checkout cannot be opened without a cart.
         */
        $cart = Session::get('ShoppingCart.Cart', []);

        if (
            !is_array($cart)
            ||
            count($cart) === 0
        ) {
            return [
                'redirect' => redirect('/shoppingcart'),
            ];
        }

        /*
         * ---------------------------------------------------------
         * Calculate current cart subtotal.
         *
         * CartCalculatorService remains the existing source of
         * truth for ShoppingCart.SubTotal. This must run before
         * checkout totals are prepared.
         * ---------------------------------------------------------
         */
        $this->cartCalculatorService
            ->calculateSubTotal();

        /*
         * Preserve the existing logged-in customer address
         * initialization from CheckoutPage().
         */
        $normalUser = Auth::user();

        if (Auth::guard('store')->check()) {
            $normalUser = Auth::guard('web')->user();
        }

        if ($normalUser) {
            $billingAddress = Session::get(
                'ShoppingCart.BillingAddress',
                []
            );

            if (empty($billingAddress)) {
                $billingAddress = [
                    'first_name' =>
                        $normalUser->first_name ?? '',
                    'last_name' =>
                        $normalUser->last_name ?? '',
                    'company' =>
                        $normalUser->company_name ?? '',
                    'address1' =>
                        $normalUser->address1 ?? '',
                    'address2' =>
                        $normalUser->address2 ?? '',
                    'city' =>
                        $normalUser->city ?? '',
                    'zip' =>
                        $normalUser->zip ?? '',
                    'state' =>
                        $normalUser->state ?? '',
                    'country' =>
                        $normalUser->country ?? 'US',
                    'phone' =>
                        $normalUser->phone ?? '',
                    'email' =>
                        $normalUser->email ?? '',
                    'confirm_email' => '',
                ];

                Session::put(
                    'ShoppingCart.BillingAddress',
                    $billingAddress
                );
            }
        }

        /*
         * Prepare ShippingAddress in one place. Existing session
         * address is preserved; otherwise logged-in customer data
         * is used, and guests get a blank US address.
         */
        $shippingAddress = $this->getShippingAddress($normalUser);

        /*
         * Preserve BillingAsShipping when it already exists.
         * Default to Yes for the new checkout Blade.
         */
        if (
            !Session::has(
                'ShoppingCart.BillingAsShipping'
            )
        ) {
            Session::put(
                'ShoppingCart.BillingAsShipping',
                'Yes'
            );
        }

        /*
         * Refresh the checkout state through the new service
         * architecture. This performs tax/insurance/totals/payment
         * calculations using the current session state.
         */
        $checkout = $this->refresh('page');

        /*
         * The new Blade reads its display values directly from
         * the checkout session. Keep the controller/service
         * response available for future AJAX/page initialization.
         */
        /*
         * Country/State lists are loaded exactly once here.
         * Blade only consumes the returned data.
         */
        $countries = GetCountries();
        $states = GetStates();

        $data = [
            'checkout' => $checkout,
            'CSSFILES' => [
                'components.css',
                'checkout-new.css',
            ],

            'JSFILES' => [
                'checkout.js',
                //'checkout-new.js'
            ],

            'Countries' => $countries,
            'States' => $states,
            'ShippingAddress' => $shippingAddress,
            'SelectedShippingCountry' =>
                $shippingAddress['country'] ?? 'US',
            'SelectedShippingState' =>
                $shippingAddress['state'] ?? '',
        ];

        addLog('CheckoutPrepareEnd', [
            'status' => 'success',
            'only_gc_purchased' =>
                $checkout['onlyGCPurchased'] ?? 0,
        ]);

        return [
            'data' => $data,
        ];
    }

    /**
     * Prepare the ShippingAddress used by the checkout Blade.
     * Existing session data always wins.
     */
    protected function getShippingAddress($normalUser = null): array
    {
        $shippingAddress = Session::get(
            'ShoppingCart.ShippingAddress'
        );

        if (is_array($shippingAddress)) {
            return $shippingAddress;
        }

        $normalUser = $normalUser ?: Auth::user();

        $shippingAddress = $normalUser
            ? [
                'first_name' => $normalUser->first_name ?? '',
                'last_name' => $normalUser->last_name ?? '',
                'company' => $normalUser->company_name ?? '',
                'address1' => $normalUser->address1 ?? '',
                'address2' => $normalUser->address2 ?? '',
                'city' => $normalUser->city ?? '',
                'zip' => $normalUser->zip ?? '',
                'state' => $normalUser->state ?? '',
                'country' => $normalUser->country ?: 'US',
                'phone' => $normalUser->phone ?? '',
                'email' => $normalUser->email ?? '',
                'confirm_email' => '',
            ]
            : [
                'first_name' => '',
                'last_name' => '',
                'company' => '',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'zip' => '',
                'state' => '',
                'country' => 'US',
                'phone' => '',
                'email' => '',
                'confirm_email' => '',
            ];

        Session::put(
            'ShoppingCart.ShippingAddress',
            $shippingAddress
        );

        return $shippingAddress;
    }

    /**
     * Refresh checkout state.
     *
     * This is the central orchestrator for One Page Checkout.
     *
     * IMPORTANT:
     * This method does not contain discount/tax/shipping
     * business rules itself. It only coordinates services.
     */
    public function refresh(
        string $pageFrom = ''
    ): array {
        addLog(
            'CheckoutRefreshStart',
            [
                'page_from' => $pageFrom,
            ]
        );

        /*
         * ---------------------------------------------------------
         * 1. Cart attributes
         * ---------------------------------------------------------
         */
        $cartAttributes =
            $this->cartAttributeService
                ->getAttributes();

        /*
         * ---------------------------------------------------------
         * Calculate current cart subtotal.
         *
         * Keep ShoppingCart.SubTotal in sync before tax,
         * insurance and final checkout totals are calculated.
         * ---------------------------------------------------------
         */
        $this->cartCalculatorService
            ->calculateSubTotal();

        /*
         * ---------------------------------------------------------
         * 2. Gift Certificate only cart
         * ---------------------------------------------------------
         */
        if (
            ($cartAttributes['onlyGCPurchased'] ?? 0) == 1
        ) {
            $this->clearGiftCertificateOnlyCheckout();
        }

        /*
         * ---------------------------------------------------------
         * 3. Tax
         *
         * Only calculate tax when the cart is not a pure
         * Gift Certificate cart.
         *
         * Address is taken from the current checkout session.
         * ---------------------------------------------------------
         */
        if (
            ($cartAttributes['onlyGCPurchased'] ?? 0) != 1
        ) {
            $this->calculateTax(
                $cartAttributes
            );
        }

        /*
         * ---------------------------------------------------------
         * 4. Shipping insurance
         * ---------------------------------------------------------
         */
        if (
    ($cartAttributes['onlyGCPurchased'] ?? 0) != 1
) {
    $this->shippingInsuranceService
        ->calculate('add');

    /*
     * ---------------------------------------------------------
     * Shipping Signature
     * ---------------------------------------------------------
     *
     * New checkout requirement:
     *
     * Page refresh => Signature ON again.
     *
     * Do not only change the frontend checkbox.
     * Re-apply the real configured Signature charge in
     * session so CheckoutTotalsService returns the same
     * charge to Blade/frontend.
     *
     * ShippingSignatureService remains the source of truth
     * for customer eligibility, pickup method and configured
     * charge.
     */
    $this->shippingSignatureService
        ->calculate('add');
}

        /*
         * ---------------------------------------------------------
         * 5. Final totals
         * ---------------------------------------------------------
         *
         * Coupon/Reward changes are already present in session.
         * CheckoutTotalsService is the single source of truth.
         */
        $totals =
            $this->checkoutTotalsService
                ->calculate();

        /*
         * ---------------------------------------------------------
         * 6. Payment availability
         * ---------------------------------------------------------
         *
         * Always use the freshly calculated checkout total.
         */
        $paymentAvailability =
            $this->paymentAvailabilityService
                ->getAvailability(
                    $this->resolveOrderTotal(
                        $totals
                    )
                );

        /*
         * ---------------------------------------------------------
         * 7. Final response
         * ---------------------------------------------------------
         */
        $result = [
            'status' => 'success',

            'cartAttributes' =>
                $cartAttributes,

            'paymentAvailability' =>
                $paymentAvailability,

            'totals' =>
                $totals,

            /*
             * Preserve the currently applied Gift Certificate
             * in the checkout AJAX response so the frontend can
             * restore its label after page refresh.
             */
            'giftCertificate' =>
                $this->getCurrentGiftCertificate(),

            'onlyGCPurchased' =>
                $cartAttributes['onlyGCPurchased']
                ?? 0,
        ];

        addLog(
            'CheckoutRefreshEnd',
            [
                'only_gc_purchased' =>
                    $cartAttributes[
                        'onlyGCPurchased'
                    ]
                    ?? 0,
            ]
        );

        return $result;
    }

    /**
     * Resolve the Free Gift UI state for One Page Checkout.
     *
     * The eligible gift list MUST come from the migrated legacy
     * Free Gift rule engine. This method only orchestrates the
     * service and does not duplicate legacy rule queries.
     */
    public function getFreeGiftDecision(
        array $eligibleGifts,
        int $existingGiftCount = 0,
        int $freeGiftCount = 0
    ): array {
        $decision =
            $this->freeGiftService
                ->getPopupDecision(
                    $eligibleGifts,
                    $existingGiftCount,
                    $freeGiftCount
                );

        return [
            'status' => 'success',
            'freeGift' => $decision,
        ];
    }

    /**
     * Add a customer-selected or automatically-selected Free Gift.
     */
    public function addFreeGift(
        $productsId,
        $freeProductsId = 0,
        $oneGift = 'No'
    ): array {
        try {
            $message =
                $this->freeGiftService
                    ->addGift(
                        $productsId,
                        $freeProductsId,
                        $oneGift
                    );

            $this->cartCalculatorService
                ->calculateSubTotal();

            $totals =
                $this->checkoutTotalsService
                    ->calculate();

            return [
                'status' => 'success',
                'message' => $message ?? '',
                'giftAdded' => $message === '',
                'totals' => $totals,
            ];
        } catch (\Throwable $e) {
            addLog(
                'CheckoutFreeGiftAddError',
                [
                    'products_id' => $productsId,
                    'freeproductsid' => $freeProductsId,
                    'message' => $e->getMessage(),
                ]
            );

            return [
                'status' => 'error',
                'message' =>
                    'Unable to add free gift.',
            ];
        }
    }

    /**
     * Apply or remove a Gift Certificate for One Page Checkout.
     */
    public function setGiftCertificate(
        string $action = 'apply',
        string $code = ''
    ): array {
        $action =
            strtolower(
                trim($action)
            );

        if (
            !in_array(
                $action,
                ['apply', 'remove'],
                true
            )
        ) {
            return [
                'status' => 'error',
                'message' =>
                    'Invalid Gift Certificate action.',
            ];
        }

        addLog(
            'CheckoutGiftCertificateActionStart',
            [
                'action' => $action,
            ]
        );

        try {
            $cartAttributes =
                $this->cartAttributeService
                    ->getAttributes();

            /*
             * Gift Certificate-only carts cannot apply another
             * Gift Certificate.
             */
            if (
                ($cartAttributes['onlyGCPurchased'] ?? 0)
                == 1
            ) {
                $this->giftCertificateService
                    ->remove();

                $totals =
                    $this->checkoutTotalsService
                        ->calculate();

                return [
                    'status' => 'success',
                    'applied' => 'No',
                    'giftCertificate' => [
                        'code' => '',
                        'value' => 0.0,
                    ],
                    'totals' => $totals,
                    'paymentAvailability' =>
                        $this->paymentAvailabilityService
                            ->getAvailability(
                                $this->resolveOrderTotal(
                                    $totals
                                )
                            ),
                    'onlyGCPurchased' => 1,
                ];
            }

            if ($action === 'remove') {
                $result =
                    $this->giftCertificateService
                        ->remove();
            } else {
                $result =
                    $this->giftCertificateService
                        ->apply($code);
            }

            /*
             * Recalculate totals after Gift Certificate state
             * changes. The totals service remains the final source
             * of truth.
             */
            $totals =
                $this->checkoutTotalsService
                    ->calculate();

            $paymentAvailability =
                $this->paymentAvailabilityService
                    ->getAvailability(
                        $this->resolveOrderTotal(
                            $totals
                        )
                    );

            $result['totals'] = $totals;
            $result['paymentAvailability'] =
                $paymentAvailability;
            $result['onlyGCPurchased'] =
                $cartAttributes[
                    'onlyGCPurchased'
                ] ?? 0;

            return $result;
        } catch (\Throwable $e) {
            addLog(
                'CheckoutGiftCertificateActionError',
                [
                    'action' => $action,
                    'code' => $code,
                    'message' =>
                        $e->getMessage(),
                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return [
                'status' => 'error',
                'message' =>
                    'Unable to update Gift Certificate.',
            ];
        }
    }

    /**
     * Set or remove Request Signature for One Page Checkout.
     */
    /**
 * Set or remove Request Signature for One Page Checkout.
 *
 * Existing legacy Shipping Signature business rules remain
 * inside ShippingSignatureService.
 */

public function setShippingSignature(
    string $action = 'add'
): array {
    $action = strtolower(trim($action));

    if (!in_array($action, ['add', 'remove'], true)) {
        return [
            'status' => 'error',
            'message' =>
                'Invalid shipping signature action.',
        ];
    }

    addLog(
        'CheckoutShippingSignatureStart',
        [
            'action' => $action,
        ]
    );

    try {

        $attributes =
            $this->cartAttributeService
                ->getAttributes();

        /*
         * Gift Certificate only cart:
         * no shipping signature.
         */
        if (
            ($attributes['onlyGCPurchased'] ?? 0) == 1
        ) {

            $this->shippingSignatureService
                ->remove();

            $charge = 0.0;

        } else {

            /*
             * ShippingSignatureService contains the
             * complete legacy eligibility/business rules.
             *
             * add:
             *   - US only
             *   - configured charge
             *   - shipping method 46 protection
             *   - > $200 eligibility
             *
             * remove:
             *   - clears session value
             */
            $charge =
                $this->shippingSignatureService
                    ->calculate($action);
        }

        /*
         * ---------------------------------------------------------
         * Recalculate Shipping Insurance
         * ---------------------------------------------------------
         *
         * Shipping Insurance depends on the current checkout
         * amount, and the current Shipping Signature charge is
         * part of that calculation.
         *
         * IMPORTANT:
         *
         * persistPreference = false
         *
         * This means:
         * - Signature ON/OFF does NOT change Insurance ON/OFF.
         * - If Insurance is ON, its amount is recalculated.
         * - If Insurance is OFF, it remains OFF.
         *
         * This is the same recalculation behavior used when
         * Shipping Method changes, without creating another
         * frontend AJAX request.
         */
        if (
            ($attributes['onlyGCPurchased'] ?? 0) != 1
        ) {

            $insurance =
                $this->shippingInsuranceService
                    ->calculate(
                        'add',
                        0,
                        'No',
                        false
                    );

        } else {

            $insurance = 0.0;
        }

        /*
         * Recalculate final totals.
         */
        $totals =
            $this->checkoutTotalsService
                ->calculate();

        $paymentAvailability =
            $this->paymentAvailabilityService
                ->getAvailability(
                    $this->resolveOrderTotal(
                        $totals
                    )
                );

        $applied =
            Session::has(
                'ShoppingCart.ShippingSignature'
            )
                ? 'Yes'
                : 'No';

        $finalCharge =
            (float) Session::get(
                'ShoppingCart.ShippingSignature',
                0
            );

        $finalInsurance =
            (float) Session::get(
                'shipping_insurance_charge',
                0
            );

        addLog(
            'CheckoutShippingSignatureEnd',
            [
                'action' =>
                    $action,

                'charge' =>
                    $finalCharge,

                'applied' =>
                    $applied,

                'shipping_insurance' =>
                    $finalInsurance,
            ]
        );

        return [
            'status' => 'success',

            'shippingSignature' => [
                'charge' =>
                    $finalCharge,

                'applied' =>
                    $applied,
            ],

            'shippingInsurance' => [
                'charge' =>
                    $finalInsurance,
            ],

            'totals' =>
                $totals,

            'paymentAvailability' =>
                $paymentAvailability,

            'onlyGCPurchased' =>
                $attributes[
                    'onlyGCPurchased'
                ] ?? 0,
        ];

    } catch (\Throwable $e) {

        addLog(
            'CheckoutShippingSignatureError',
            [
                'action' =>
                    $action,

                'message' =>
                    $e->getMessage(),

                'trace' =>
                    $e->getTraceAsString(),
            ]
        );

        return [
            'status' => 'error',
            'message' =>
                'Unable to update shipping signature.',
        ];
    }
}
  /**
     * Set or remove gift wrapping for One Page Checkout.
     *
     * The configured gift wrapping charge and existing cart
     * selection rules are owned by GiftWrappingService.
     *
     * @param string $action add|remove
     * @param string $productId optional product id
     * @return array
     */
    public function setGiftWrapping(
        string $action = 'add',
        string $productId = ''
    ): array {
        $action =
            strtolower(
                trim($action)
            );

        if (
            !in_array(
                $action,
                ['add', 'remove'],
                true
            )
        ) {
            return [
                'status' => 'error',
                'message' =>
                    'Invalid gift wrapping action.',
            ];
        }

        addLog(
            'CheckoutGiftWrappingStart',
            [
                'action' => $action,
                'product_id' => $productId,
            ]
        );

        try {
            $cartAttributes =
                $this->cartAttributeService
                    ->getAttributes();

            /*
             * Gift Certificate-only carts do not have
             * shipping/gift wrapping charges.
             */
            if (
                ($cartAttributes['onlyGCPurchased'] ?? 0)
                == 1
            ) {
                Session::forget(
                    'ShoppingCart.GiftWrapping'
                );

                $giftWrappingCharge = 0.0;
            } else {
                $giftWrappingCharge =
                    $this->giftWrappingService
                        ->calculate(
                            $action,
                            $productId
                        );
            }

            /*
             * Recalculate totals after the gift wrapping
             * state changes.
             */
            $totals =
                $this->checkoutTotalsService
                    ->calculate();

            $paymentAvailability =
                $this->paymentAvailabilityService
                    ->getAvailability(
                        $this->resolveOrderTotal(
                            $totals
                        )
                    );

            $applied =
                $giftWrappingCharge > 0
                    ? 'Yes'
                    : (
                        $action === 'remove'
                            ? 'No'
                            : (
                                Session::get(
                                    'ShoppingCart.GiftWrapping.Applied'
                                )
                                === 'Yes'
                                    ? 'Yes'
                                    : 'No'
                            )
                    );

            addLog(
                'CheckoutGiftWrappingEnd',
                [
                    'action' => $action,
                    'charge' =>
                        $giftWrappingCharge,
                    'applied' => $applied,
                ]
            );

            return [
                'status' => 'success',

                'giftWrapping' => [
                    'charge' =>
                        (float)
                        $giftWrappingCharge,

                    'applied' =>
                        $applied,
                ],

                'totals' =>
                    $totals,

                'paymentAvailability' =>
                    $paymentAvailability,

                'onlyGCPurchased' =>
                    $cartAttributes[
                        'onlyGCPurchased'
                    ] ?? 0,
            ];
        } catch (\Throwable $e) {
            addLog(
                'CheckoutGiftWrappingError',
                [
                    'action' => $action,
                    'product_id' => $productId,
                    'message' =>
                        $e->getMessage(),
                    'trace' =>
                        $e->getTraceAsString(),
                ]
            );

            return [
                'status' => 'error',
                'message' =>
                    'Unable to update gift wrapping.',
            ];
        }
    }

    /**
     * Set or remove shipping insurance for One Page Checkout.
     *
     * The ShippingInsuranceService remains the source of truth
     * for the existing insurance business rules.
     *
     * @param string $action add|remove
     * @return array
     */
   /**
 * Set or remove shipping insurance for One Page Checkout.
 *
 * Existing ShippingInsuranceService remains the source
 * of truth for the legacy insurance calculation.
 */
public function setShippingInsurance(
    string $action = 'add'
): array {
    $action = strtolower(trim($action));

    if (!in_array($action, ['add', 'remove'], true)) {
        return [
            'status' => 'error',
            'message' =>
                'Invalid shipping insurance action.',
        ];
    }

    addLog(
        'CheckoutShippingInsuranceStart',
        [
            'action' => $action,
        ]
    );

    try {

        $cartAttributes =
            $this->cartAttributeService
                ->getAttributes();

        /*
         * Gift Certificate only cart:
         * no shipping insurance.
         */
        if (
            ($cartAttributes['onlyGCPurchased'] ?? 0) == 1
        ) {

            Session::forget(
                'shipping_insurance_charge'
            );

        } else {

            /*
             * DO NOT calculate insurance here.
             *
             * ShippingInsuranceService contains
             * the existing legacy calculation.
             */
            $this->shippingInsuranceService
                ->calculate($action);
        }

        /*
         * Always read the final value from session.
         */
        $insurance =
            (float) Session::get(
                'shipping_insurance_charge',
                0
            );

        /*
         * Recalculate final checkout totals.
         */
        $totals =
            $this->checkoutTotalsService
                ->calculate();

        $paymentAvailability =
            $this->paymentAvailabilityService
                ->getAvailability(
                    $this->resolveOrderTotal(
                        $totals
                    )
                );

        addLog(
            'CheckoutShippingInsuranceEnd',
            [
                'action' => $action,
                'insurance' => $insurance,
            ]
        );

        return [
            'status' => 'success',

            'insurance' =>
                $insurance,

            'shipping_insurance_charge' =>
                $insurance,

            'applied' =>
                $insurance > 0
                    ? 'Yes'
                    : 'No',

            'totals' =>
                $totals,

            'paymentAvailability' =>
                $paymentAvailability,

            'onlyGCPurchased' =>
                $cartAttributes[
                    'onlyGCPurchased'
                ] ?? 0,
        ];

    } catch (\Throwable $e) {

        addLog(
            'CheckoutShippingInsuranceError',
            [
                'action' => $action,
                'message' =>
                    $e->getMessage(),
                'trace' =>
                    $e->getTraceAsString(),
            ]
        );

        return [
            'status' => 'error',
            'message' =>
                'Unable to update shipping insurance.',
        ];
    }
}
    /**
     * Refresh checkout after shipping method selection.
     *
     * This is the One Page Checkout flow:
     *
     * Shipping
     *   ↓
     * Tax
     *   ↓
     * Insurance
     *   ↓
     * Totals
     */

public function refreshAfterShippingMethod(
    array $context,
    array $address,
    array $paymentContext = []
): array {

    addLog(
        'CheckoutRefreshAfterShippingStart',
        [
            'shipping_method_id' =>
                $context['shippingMethodId'] ?? 0,

            'country' =>
                $address['country'] ?? '',

            'state' =>
                $address['state'] ?? '',

            'zip' =>
                $address['zip'] ?? '',

            'action' =>
                $paymentContext['action'] ?? '',
        ]
    );

    $shippingMethodId =
        (int) (
            $context['shippingMethodId']
            ?? 0
        );

    $shipCountry =
        trim(
            $address['country']
            ?? ''
        );

    $shipState =
        trim(
            $address['state']
            ?? ''
        );

    $shipZip =
        trim(
            $address['zip']
            ?? ''
        );

    $shipCity =
        trim(
            $address['city']
            ?? ''
        );

    $onlyGCPurchased =
        (int) (
            $context['onlyGCPurchased']
            ?? 0
        );

    $isPayPalSubTotal =
        $context['isPayPalSubTotal']
        ?? 0;

    $shippingChargePayPalProductPage =
        $context[
            'shippingChargePayPalProductPage'
        ]
        ?? 0;

    /*
     * ---------------------------------------------------------
     * 1. Save selected shipping method.
     * ---------------------------------------------------------
     */
    Session::put(
        'ShoppingCart.Shipping.ShippingMethodID',
        $shippingMethodId
    );

    /*
     * ---------------------------------------------------------
     * 2. Save estimated delivery date.
     * ---------------------------------------------------------
     */
    $estimatedDeliveryDate =
        $context[
            'estimatedDeliveryDate'
        ]
        ?? '';

    if (
        $estimatedDeliveryDate !== ''
    ) {
        Session::put(
            'ShoppingCart.EstimatedDeliveryDate',
            $estimatedDeliveryDate
        );

        Session::put(
            'ShoppingCart.Shipping.ShippingDays',
            $estimatedDeliveryDate
        );
    }

    /*
     * ---------------------------------------------------------
     * 3. Shipping method / shipping charge.
     *
     * IMPORTANT:
     * ShippingService owns shipping business rules.
     * ---------------------------------------------------------
     */

    $shippingFlags = [
        'IsCosmo' =>
            $context['IsCosmo'] ?? 'No',

        'IsNandansons' =>
            $context['IsNandansons'] ?? 'No',

        'IsPerfumePW' =>
            $context['IsPerfumePW'] ?? 'No',

        'IsPCA' =>
            $context['IsPCA'] ?? 'No',

        'IsND' =>
            $context['IsND'] ?? 'No',

        'IsVenderItem' =>
            $context['IsVenderItem'] ?? 'No',

        'action' =>
            $paymentContext['action'] ?? '',

        'isPayPalSubTotal' =>
            $isPayPalSubTotal,

        'shippingChargePayPalProductPage' =>
            $shippingChargePayPalProductPage,
    ];

    $shippingResult =
        $this->shippingService
            ->setShippingMethod(
                $shippingMethodId,
                $address,
                $shippingFlags
            );

    /*
     * Shipping failure.
     */
    if (
        is_array($shippingResult)
        &&
        isset(
            $shippingResult['status']
        )
        &&
        $shippingResult['status'] !== 'success'
    ) {
        return [
            'status' =>
                $shippingResult['status'],

            'shipping' =>
                $shippingResult,
        ];
    }

    /*
     * ---------------------------------------------------------
     * 4. Gift Certificate only cart.
     *
     * No shipping/tax/insurance.
     * ---------------------------------------------------------
     */
    if (
        $onlyGCPurchased === 1
    ) {
        Session::forget(
            'ShoppingCart.Shipping'
        );

        Session::forget(
            'ShoppingCart.Tax'
        );

        Session::forget(
            'ShoppingCart.GiftWrapping'
        );

        Session::forget(
            'ShoppingCart.ShippingSignature'
        );

        Session::forget(
            'shipping_insurance_charge'
        );

        $taxResult = null;
        $insurance = 0;
    } else {

        /*
         * -----------------------------------------------------
         * 5. Tax
         * -----------------------------------------------------
         */
        $taxResult =
            $this->taxService
                ->calculate(
                    $shipCountry,
                    $shipState,
                    $shipZip,
                    $onlyGCPurchased,
                    $isPayPalSubTotal,
                    $shipCity,
                    $shippingChargePayPalProductPage
                );

        /*
         * -----------------------------------------------------
         * 6. Shipping Insurance
         * -----------------------------------------------------
         */
        $insurance =
            $this->shippingInsuranceService
                ->calculate(
                    'add'
                );
    }

    /*
     * ---------------------------------------------------------
     * 7. Request Signature validation.
     *
     * If an already-applied signature is no longer eligible
     * after shipping/address/total changes, clear it.
     * ---------------------------------------------------------
     */
    $this->shippingSignatureService->sync();

    /*
     * ---------------------------------------------------------
     * 8. Final totals.
     *
     * Totals service is the final source of truth.
     * ---------------------------------------------------------
     */
    $totals =
        $this->checkoutTotalsService
            ->calculate();

    /*
     * ---------------------------------------------------------
     * 9. Payment availability.
     * ---------------------------------------------------------
     */
    $paymentAvailability =
        $this->paymentAvailabilityService
            ->getAvailability(
                $this->resolveOrderTotal(
                    $totals
                )
            );

    /*
     * ---------------------------------------------------------
     * 10. Response.
     * ---------------------------------------------------------
     */
    $result = [
        'status' =>
            'success',

        'shipping' =>
            $shippingResult,

        'tax' =>
            $taxResult,

        'insurance' =>
            $insurance,
        
        'shippingSignature' => [
			'charge' =>
				(float) Session::get(
					'ShoppingCart.ShippingSignature',
					0
				),

			'applied' =>
				Session::has(
					'ShoppingCart.ShippingSignature'
				)
					? 'Yes'
					: 'No',
		],
            

        'totals' =>
            $totals,

        'paymentAvailability' =>
            $paymentAvailability,

        /*
         * Preserve the currently applied Gift Certificate
         * when shipping/totals are refreshed as well.
         */
        'giftCertificate' =>
            $this->getCurrentGiftCertificate(),

        'onlyGCPurchased' =>
            $onlyGCPurchased,

        'estimatedDeliveryDate' =>
            $estimatedDeliveryDate,
    ];

    addLog(
        'CheckoutRefreshAfterShippingEnd',
        [
            'shipping_method_id' =>
                $shippingMethodId,

            'status' =>
                'success',
        ]
    );

    return $result;
}

    /**
     * Return the currently applied Gift Certificate state.
     *
     * GiftCertificateService stores the applied code/value in
     * the existing ShoppingCart.GiftCoupon session values.
     *
     * This is response-only state for the One Page Checkout UI;
     * it does not change Gift Certificate calculation logic.
     */
    protected function getCurrentGiftCertificate(): array
    {
        return [
            'code' =>
                (string) Session::get(
                    'ShoppingCart.GiftCoupon.Code',
                    ''
                ),

            'value' =>
                (float) Session::get(
                    'ShoppingCart.GiftCoupon.Value',
                    0
                ),
        ];
    }

protected function resolveOrderTotal(
    $totals
): float {
    /*
     * Prefer CheckoutTotalsService result.
     */
    if (
        is_array($totals)
    ) {
        foreach (
            [
                'NetTotal',
                'netTotal',
                'Total',
                'total',
                'GrandTotal',
                'grandTotal',
            ] as $key
        ) {
            if (
                isset(
                    $totals[$key]
                )
            ) {
                return (float)
                    $totals[$key];
            }
        }
    }

    /*
     * Fallback to existing session total.
     */
    return (float)
        Session::get(
            'ShoppingCart.NetTotal',
            0
        );
}

    /**
     * Calculate tax from current checkout address.
     */
    protected function calculateTax(
        array $cartAttributes
    ) {
        $shippingAddress =
            Session::get(
                'ShoppingCart.ShippingAddress',
                []
            );

        /*
         * If BillingAsShipping is used, preserve the existing
         * checkout behavior by resolving the address accordingly.
         */
        $billingAsShipping =
            Session::get(
                'ShoppingCart.BillingAsShipping'
            );

        if (
            $billingAsShipping === 'Yes'
        ) {
            $billingAddress =
                Session::get(
                    'ShoppingCart.BillingAddress',
                    []
                );

            if (
                !empty(
                    $billingAddress
                )
            ) {
                $shippingAddress =
                    $billingAddress;
            }
        }

        $country =
            $shippingAddress['country']
            ?? '';

        $state =
            $shippingAddress['state']
            ?? '';

        $zip =
            $shippingAddress['zip']
            ?? '';

        $city =
            $shippingAddress['city']
            ?? '';

        /*
         * No address = no tax calculation yet.
         *
         * One Page Checkout may call refresh before the
         * customer has completed the address.
         */
        if (
            $country === ''
            ||
            $state === ''
            ||
            $zip === ''
        ) {
            return null;
        }

        return $this->taxService
            ->calculate(
                $country,
                $state,
                $zip,
                (int) (
                    $cartAttributes[
                        'onlyGCPurchased'
                    ]
                    ?? 0
                ),
                0,
                $city,
                0
            );
    }

    /**
     * Remove checkout-only values for a pure Gift Certificate cart.
     *
     * This is the existing SetupCart() behavior.
     */
    protected function clearGiftCertificateOnlyCheckout(): void
    {
        Session::forget(
            'ShoppingCart.Shipping'
        );

        Session::forget(
            'ShoppingCart.Tax'
        );

        Session::forget(
            'ShoppingCart.GiftWrapping'
        );

        Session::forget(
            'ShoppingCart.ShippingSignature'
        );

        Session::forget(
            'shipping_insurance_charge'
        );
    }

    /**
     * Read the current order total.
     *
     * This is only used for payment availability.
     *
     * CheckoutTotalsService remains the source of truth for
     * the final checkout total.
     */
    protected function getCurrentOrderTotal(): float
    {
        /*
         * Compatibility wrapper for any legacy callers.
         *
         * CheckoutTotalsService owns the checkout total calculation.
         */
        return $this->checkoutTotalsService
            ->getNetTotal();
    }
public function getAvailableShippingMethods(
    array $address,
    array $flags = []
): array {
    $country = trim(
        $address['country'] ?? ''
    );

    $state = trim(
        $address['state'] ?? ''
    );

    $zip = trim(
        $address['zip'] ?? ''
    );

    /*
     * Keep current shipping address in session.
     */
    $currentAddress = Session::get(
        'ShoppingCart.ShippingAddress',
        []
    );

    Session::put(
        'ShoppingCart.ShippingAddress',
        array_merge(
            $currentAddress,
            $address
        )
    );

    /*
     * Cart attributes are the default shipping flags.
     * Request flags override them.
     */
    $cartAttributes =
        $this->cartAttributeService
            ->getAttributes();

    $flags = array_merge(
        [
            'IsCosmo' =>
                $cartAttributes['IsCosmo'] ?? 'No',

            'IsNandansons' =>
                $cartAttributes['IsNandansons'] ?? 'No',

            'IsPerfumePW' =>
                $cartAttributes['IsPerfumePW'] ?? 'No',

            'IsPCA' =>
                $cartAttributes['IsPCA'] ?? 'No',

            'IsND' =>
                $cartAttributes['IsND'] ?? 'No',

            'IsVenderItem' =>
                $cartAttributes['IsVenderItem'] ?? 'No',

            'IsMaxaromaTwoDelivery' =>
                $cartAttributes['IsMaxaromaTwoDelivery'] ?? 'No',

            'ISMaxTwoItem' =>
                $cartAttributes['ISMaxTwoItem'] ?? 'No',

            'ISMax2dayVal' =>
                $cartAttributes['ISMax2dayVal'] ?? 'No',

            'onlyGCPurchased' =>
                $cartAttributes['onlyGCPurchased'] ?? 0,
        ],
        $flags
    );

    /*
     * ShippingService owns all shipping-method business logic.
     */
    $shippingMethods =
        $this->shippingService
            ->getAvailableMethods(
                $address,
                $flags
            );

    return [
        'status' =>
            'success',

        'shippingMethods' =>
            $shippingMethods,

        'selectedShippingMethodId' =>
            (int) Session::get(
                'ShoppingCart.Shipping.ShippingMethodID',
                0
            ),

        'address' =>
            Session::get(
                'ShoppingCart.ShippingAddress',
                []
            ),
    ];
}
}
