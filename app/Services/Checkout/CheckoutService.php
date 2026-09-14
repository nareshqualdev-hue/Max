<?php

namespace App\Services\Checkout;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Services\Cart\CartCalculatorService;
use Illuminate\Support\Facades\Log;

use App\Models\Customer;
class CheckoutService
{
    public function __construct(
        protected CartAttributeService $cartAttributeService,
		protected TaxService $taxService,
		protected ShippingService $shippingService,
		protected ShippingInsuranceService $shippingInsuranceService,
		protected GiftWrappingService $giftWrappingService,
		protected ShippingSignatureService $shippingSignatureService,
		protected PaymentAvailabilityService $paymentAvailabilityService,
		protected CheckoutTotalsService $checkoutTotalsService,
		protected CartCalculatorService $cartCalculatorService,
		protected \App\Services\Discount\FreeGiftService $freeGiftService,
		protected \App\Services\Discount\AutoDiscountService $autoDiscountService,
		protected \App\Services\Discount\QuantityDiscountService $quantityDiscountService,
		protected \App\Services\Checkout\GiftCertificateService $giftCertificateService,
		protected \App\Services\Discount\BogoDiscountService $bogoDiscountService,
		protected \App\Services\Discount\CouponService $couponService,
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
		/*
		 * ---------------------------------------------------------
		 * Wholesaler minimum order validation.
		 *
		 * Preserve old Is_WholeSaler_Allow() behavior:
		 * - Only Wholesaler customers are restricted.
		 * - Dropshippers are excluded from this restriction.
		 * - Minimum amount comes from existing setting.
		 * - Below minimum => redirect to Shopping Cart.
		 * ---------------------------------------------------------
		 */
		if($normalUser && ($normalUser->is_dropshipper ?? 'No') != 'Yes' && strtolower(trim($normalUser->eusertype ?? '')) === 'wholesaler')
        {
			$orderSubTotal =
				(float) Session::get(
					'ShoppingCart.SubTotal',
					0
				);

			$wholesalerMinimumOrder =
				NumberFormat(
					config(
						'Settings.WHOLESALER_MIN_ORDER_AMOUNT'
					)
				);

			if (
				$orderSubTotal
				<
				$wholesalerMinimumOrder
			) {
				return [
					'redirect' =>
						redirect('/shoppingcart'),
				];
			}
		}
        if($normalUser)
        {

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
                'paypal.js'
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

    public function setShippingBillingAddress(
        array $shipaddress = [],
        array $billaddress = [],
        string $billasship = 'Y'
    ): array {

        $shippingAddress =
            $this->resolveAddress(
                $shipaddress,
                'Shipping'
            );

        Session::put('ShoppingCart.ShippingAddress',$shipaddress);

        $billingAddress =
            $this->resolveAddress(
                $billaddress,
                'Billing'
            );

        if($billasship == 'Y')
        {
            Session::put('ShoppingCart.BillingAddress',$shippingAddress);
        } else {
            Session::put('ShoppingCart.BillingAddress',$billingAddress);
        }
       return ['status' => 'success'];
    }

    protected function resolveAddress(
        array $address,
        string $type
    ): array {
        return [
            'country' =>
                trim(
                    $address['country']
                    ??
                    Session::get(
                        'ShoppingCart.'.$type.'Address.country',
                        ''
                    )
                ),

            'state' =>
                trim(
                    $address['state']
                    ??
                    Session::get(
                        'ShoppingCart.'.$type.'Address.state',
                        ''
                    )
                ),

            'zip' =>
                trim(
                    $address['zip']
                    ??
                    Session::get(
                        'ShoppingCart.'.$type.'Address.zip',
                        ''
                    )
                ),

            'city' =>
                trim(
                    $address['city']
                    ??
                    Session::get(
                        'ShoppingCart.'.$type.'Address.city',
                        ''
                    )
                ),

            'address1' =>
                trim(
                    $address['address1']
                    ??
                    Session::get(
                        'ShoppingCart.'.$type.'Address.address1',
                        ''
                    )
                ),

            'address2' =>
                trim(
                    $address['address2']
                    ??
                    Session::get(
                        'ShoppingCart.'.$type.'Address.address2',
                        ''
                    )
                ),
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

         $isGiftCertificateRestricted =
			strtolower(
				trim(
					Session::get(
						'eusertype',
						''
					)
				)
			) === 'wholesaler'
			||
			trim(
				Session::get(
					'is_dropshipper',
					''
				)
			) === 'Yes';

		if ($isGiftCertificateRestricted) {
			$this->giftCertificateService
			->remove();
		}
		/*
		 * ---------------------------------------------------------
		 * Recalculate active coupon after cart/subtotal changes.
		 *
		 * Important:
		 * CouponService::apply() is the existing coupon business
		 * logic. Reuse it so current DB coupon rules and current
		 * cart state are always used.
		 *
		 * This fixes stale FirstCouponDiscount when:
		 * - coupon rule changes in admin
		 * - cart quantity changes
		 * - cart item is added/removed
		 * - subtotal changes
		 * ---------------------------------------------------------
		 */
		Session::put(
					'ShoppingCart.AutoDiscount',
					0
				);

        $this->cartCalculatorService
            ->calculateSubTotal();

			if (
				config('Settings.AUTODISCOUNTFLAG') === 'Yes'
			) {
				$this->autoDiscountService
					->apply();
			}

			if (
				config('Settings.QUANTITYDISCOUNTFLAG') === 'Yes'
			) {
				$this->quantityDiscountService
					->apply();
			}
			if (
				config('Settings.BOGODISCOUNTFLAG') === 'Yes'
			) {
				$this->bogoDiscountService
					->apply();
			}

			if (
			config('global.BOGO_QTY__AUTO_COMBINED') == '1'
			) {
			$bogoDiscount =
				(float) Session::get(
					'ShoppingCart.DogoDiscount',
					0
				);

			if ($bogoDiscount > 0) {
				Session::put(
					'ShoppingCart.AutoDiscount',
					0
				);

				Session::put(
					'ShoppingCart.QuantityDiscount',
					0
				);
			}
		}

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

        /*
 * ---------------------------------------------------------
 * 3. Shipping Signature
 * ---------------------------------------------------------
 *
 * IMPORTANT:
 *
 * Do NOT call calculate('add') here.
 *
 * calculate('add') means "explicitly enable Signature".
 * A checkout refresh must not turn an OFF customer
 * preference back ON.
 *
 * sync() preserves the existing customer selection:
 *
 * - Signature OFF -> remains OFF
 * - Signature ON  -> recalculates current charge
 * - No longer eligible -> removes Signature
 */
if (
    ($cartAttributes['onlyGCPurchased'] ?? 0) != 1
) {
    $this->shippingSignatureService
        ->sync();
}

/*
 * ---------------------------------------------------------
 * 4. Shipping Insurance
 * ---------------------------------------------------------
 *
 * Recalculate Insurance using the current cart.
 *
 * persistPreference = false is IMPORTANT.
 *
 * Refresh/recalculation must not turn an explicitly
 * disabled Insurance back ON.
 */

	if (
		($cartAttributes['onlyGCPurchased'] ?? 0) != 1
	) {
		$this->recalculateCouponAndTax(
			$cartAttributes
		);
	}
	else
	{
		$activeCouponCode = trim(
			(string) Session::get(
				'ShoppingCart.PromoCoupon.CouponCode',
				''
			)
		);

		if ($activeCouponCode !== '') {
			$this->couponService->apply(
				$activeCouponCode,
				(int) Session::get(
					'sess_icustomerid',
					0
				)
			);
		}
	}
if (
    ($cartAttributes['onlyGCPurchased'] ?? 0) != 1
) {
    $this->shippingInsuranceService
        ->calculate(
            'add',
            0,
            'No',
            false
        );
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

        //$dropshipperDetails = $this->GetDropshipperDetails();
        /*
         * ---------------------------------------------------------
         * 7. Final response
         * ---------------------------------------------------------
         */
        $result = [
            'status' => 'success',
            'cartAttributes' => $cartAttributes,
            'paymentAvailability' => $paymentAvailability,
            'totals' => $totals,
            //'dropshipperDetails' => $dropshipperDetails,
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

            /*
             * Preserve legacy GIFTCERTIFICATEFLAG behavior.
             *
             * Old Checkout only called ApplyGiftCoupons()
             * when GIFTCERTIFICATEFLAG was enabled.
             */
            if (
                config('Settings.GIFTCERTIFICATEFLAG') !== 'Yes'
            ) {
                $this->giftCertificateService
                    ->remove();

                $result = [
                    'status' => 'success',
                    'applied' => 'No',
                    'giftCertificate' => [
                        'code' => '',
                        'value' => 0.0,
                    ],
                ];
            } elseif ($action === 'remove') {
                $result =
                    $this->giftCertificateService
                        ->remove();
            } else {
                $result =
                    $this->giftCertificateService
                        ->apply($code);
            }

            /*
             * Recalculate Tax and Protect My Order after
             * Gift Certificate state changes.
             *
             * IMPORTANT:
             * Existing TaxService and ShippingInsuranceService
             * remain the source of truth. We only re-run their
             * existing calculations here; no business rules are
             * duplicated or changed.
             */
            $cartAttributes =
                $this->cartAttributeService
                    ->getAttributes();

            if (
                ($cartAttributes['onlyGCPurchased'] ?? 0) != 1
            ) {
                /*
                 * Gift Certificate changes the checkout amount.
                 *
                 * Re-run Shipping Signature FIRST so the current
                 * Signature charge is available before Insurance
                 * calculates its amount.
                 *
                 * This matches the normal checkout refresh flow,
                 * where the currently applied Signature is part of
                 * the amount used by Shipping Insurance.
                 *
                 * ShippingSignatureService remains the single
                 * source of truth. No Signature business rules are
                 * duplicated here.
                 */
                $this->shippingSignatureService
                    ->calculate('add');

                /*
                 * Recalculate Protect My Order after Signature.
                 *
                 * Existing ShippingInsuranceService remains the
                 * single source of truth for Insurance calculation.
                 */
                $this->shippingInsuranceService
                    ->calculate('add');

                /*
                 * Now calculate Tax using the fully refreshed
                 * Signature + Insurance state.
                 *
                 * Existing TaxService/business rules are unchanged.
                 */

                 $this->recalculateCouponAndTax(
					$cartAttributes
				);

            }

            /*
             * Recalculate final totals after Protect My Order,
             * Shipping Signature and Tax have been refreshed.
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
         * ---------------------------------------------------------
         * Recalculate Tax
         * ---------------------------------------------------------
         *
         * Signature ON/OFF changes the taxable checkout amount.
         * Recalculate Tax after Signature and Insurance are updated.
         *
         * Existing Tax calculation logic remains unchanged.
         */
		if (
			($attributes['onlyGCPurchased'] ?? 0) != 1
		) {
			$this->recalculateCouponAndTax(
				$attributes
			);
		}

        /*
         * ---------------------------------------------------------
         * Recalculate final totals.
         * ---------------------------------------------------------
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
}  /**
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
         * ---------------------------------------------------------
         * Recalculate Tax
         * ---------------------------------------------------------
         *
         * Insurance ON/OFF changes the taxable checkout amount.
         *
         * Calculate Tax after the Insurance value has been
         * updated so TaxService uses the current Insurance state.
         */
		if (
			($cartAttributes['onlyGCPurchased'] ?? 0) != 1
		) {
			$this->recalculateCouponAndTax(
				$cartAttributes
			);
		}

        /*
         * ---------------------------------------------------------
         * Recalculate final checkout totals.
         * ---------------------------------------------------------
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

    Log::info(
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
     * 4. Shipping method successfully changed.
     *
     * Reset previously applied Shipping Signature.
     * Customer can manually enable it again.
     *
     * Existing Shipping Signature business logic remains
     * unchanged.
     * ---------------------------------------------------------
     */
    Session::forget(
        'ShoppingCart.ShippingSignature'
    );
    /*
     * ---------------------------------------------------------
     * 5. Gift Certificate only cart.
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
         * 6. Request Signature validation.
         *
         * Existing logic retained.
         * -----------------------------------------------------
         */

        $this->shippingSignatureService
            ->sync();

        /*
         * -----------------------------------------------------
         * 7. Shipping Insurance.
         *
         * EXISTING LOGIC - DO NOT CHANGE.
         * -----------------------------------------------------
         */

        /*
         * -----------------------------------------------------
         * 8. Tax.
         *
         * EXISTING LOGIC - DO NOT CHANGE.
         * -----------------------------------------------------
         */
         $cartAttributes =
				$this->cartAttributeService
					->getAttributes();
        if (
				($cartAttributes['onlyGCPurchased'] ?? 0) != 1
			) {

				$this->recalculateCouponAndTax(
    $cartAttributes,$address
);
			}
			$taxResult = Session::get(
			'ShoppingCart.Tax',
			0);
    }

$insurance =
            $this->shippingInsuranceService
                ->calculate(
                    'add'
                );
    /*
     * ---------------------------------------------------------
     * 9. Final totals.
     *
     * Totals service is the final source of truth.
     * ---------------------------------------------------------
     */
    $totals =
        $this->checkoutTotalsService
            ->calculate();

    /*
     * ---------------------------------------------------------
     * 10. Payment availability.
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
     * 11. Response.
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
        array $cartAttributes,
        array $taxContext = []
    ) {
        $shippingAddress =
            Session::get(
                'ShoppingCart.ShippingAddress',
                []
            );
		$insurance =
            $this->shippingInsuranceService
                ->calculate(
                    'add'
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
		/*$taxContext['country'] = "US";
		$taxContext['state'] = "CA";
		$taxContext['zip'] = "95131";
		$taxContext['city'] = "San Jose";
        */
        $country =
            $taxContext['country']
            ?? '';

        $state =
            $taxContext['state']
            ?? '';

        $zip =
            $taxContext['zip']
            ?? '';

        $city =
            $taxContext['city']
            ?? '';

		Log::info(
            'calculateTax',
            [
                'Shipping Infor for Tax' =>
                    Session::get(
                        'eusertype'
                    )
                    . '---'
                    . $country
                    . '--'
                    . $state
                    . '--'
                    . $zip
                    . '--'
                    . $city
            ]
        );

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
        * Cart attributes are the source of truth for
        * cart-dependent shipping flags.
        */
        $cartAttributes =
            $this->cartAttributeService
                ->getAttributes();

        /*
        * IMPORTANT:
        *
        * Do NOT allow request/frontend values to override
        * cart-dependent shipping flags.
        *
        * This is required for Max2Day logic.
        *
        * Example:
        *
        * CartAttributeService:
        *     IsMaxaromaTwoDelivery = Yes
        *     ISMaxTwoItem          = Yes
        *     ISMax2dayVal          = No
        *
        * Frontend may still send:
        *     IsMaxaromaTwoDelivery = No
        *
        * But the frontend value must NOT overwrite the
        * actual cart attribute.
        */
        $flags = [

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

            /*
            * -----------------------------------------------------
            * Max2Day
            * -----------------------------------------------------
            *
            * These MUST come from CartAttributeService.
            *
            * Do NOT merge request/frontend values here.
            */
            'IsMaxaromaTwoDelivery' =>
                $cartAttributes[
                    'IsMaxaromaTwoDelivery'
                ] ?? 'No',

            'ISMaxTwoItem' =>
                $cartAttributes[
                    'ISMaxTwoItem'
                ] ?? 'No',

            'ISMax2dayVal' =>
                $cartAttributes[
                    'ISMax2dayVal'
                ] ?? 'No',

            /*
            * Cart-level value.
            */
            'onlyGCPurchased' =>
                $cartAttributes[
                    'onlyGCPurchased'
                ] ?? 0,
        ];

        /*
        * Preserve any non-cart-dependent request flags.
        *
        * Currently the controller sends only:
        *
        *     onlyGCPurchased
        *     selectedShippingMethodId
        *
        * Do NOT merge the complete request $flags because that
        * would overwrite the cart-derived Max2Day/vendor values.
        */
        if (
            isset(
                $flags['selectedShippingMethodId']
            )
        ) {
            $flags['selectedShippingMethodId'] =
                (int) $flags[
                    'selectedShippingMethodId'
                ];
        }

        /*
        * Keep request onlyGCPurchased out of the
        * cart-derived source of truth.
        *
        * CartAttributeService already provides the actual value.
        */

        /*
        * ShippingService owns all shipping-method
        * business logic.
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

/**
 * Update the current checkout ShippingAddress.
 *
 * Existing ShippingAddress values are preserved when only
 * partial address data is supplied.
 */
public function updateShippingAddress(
    array $address
): array {
    $currentAddress = Session::get(
        'ShoppingCart.ShippingAddress',
        []
    );

    if (!is_array($currentAddress)) {
        $currentAddress = [];
    }

    $shippingAddress = array_merge(
        $currentAddress,
        $address
    );

    Session::put(
        'ShoppingCart.ShippingAddress',
        $shippingAddress
    );

    return $shippingAddress;
}
protected function recalculateCouponAndTax(
    array $cartAttributes,
     array $taxContext = []
): void {

    $isPayPalSubTotal =
        $taxContext['isPayPalSubTotal'] ?? 0;

    $shippingChargePayPalProductPage =
        $taxContext['shippingChargePayPalProductPage'] ?? 0;
        $couponCode = trim(
            (string) Session::get(
                'ShoppingCart.PromoCoupon.CouponCode',
                ''
            )
        );

        $ShippingAddress = Session::get('ShoppingCart.ShippingAddress');
        //echo "<pre>"; print_r($ShippingAddress); exit;

            if (empty($taxContext['country'])) {
            $taxContext['country'] = $ShippingAddress['country'] ?? '';
            }

            if (empty($taxContext['state'])) {
                $taxContext['state'] = $ShippingAddress['state'] ?? '';
            }

            if (empty($taxContext['zip'])) {
                $taxContext['zip'] = $ShippingAddress['zip'] ?? '';
            }

            if (empty($taxContext['city'])) {
                $taxContext['city'] = $ShippingAddress['city'] ?? '';
            }

        /*
        * No active coupon.
        */
        if ($couponCode === '') {
            $this->calculateTax($cartAttributes,$taxContext);

            return;
        }

        /*
        * First coupon calculation.
        *
        * This also gives us the coupon configuration,
        * including count_ship_tax.
        */
        $couponResult = $this->couponService->apply(
            $couponCode,
            (int) Session::get(
                'sess_icustomerid',
                0
            )
        );

        /*
        * Invalid / unavailable coupon.
        */
        if (
            ($couponResult['error'] ?? 1) !== 0
        ) {
            $this->calculateTax($cartAttributes,$taxContext);

            return;
        }

        /*
        * ---------------------------------------------------------
        * count_ship_tax != 1
        * ---------------------------------------------------------
        *
        * No Coupon <-> Tax circular dependency.
        *
        * Keep the normal existing flow.
        */
        if (
            (string) (
                $couponResult['count_ship_tax'] ?? ''
            ) !== '1'
        ) {
            $this->calculateTax(
                $cartAttributes,$taxContext
            );

            return;
        }

        /*
        * ---------------------------------------------------------
        * count_ship_tax = 1
        * ---------------------------------------------------------
        *
        * Coupon uses Tax and Tax uses Coupon Discount.
        *
        * Resolve the dependency by iterating until both values
        * become stable.
        */

        $previousCouponDiscount =
            (float) (
                $couponResult['discount']
                ??
                Session::get(
                    'ShoppingCart.PromoCoupon.FirstCouponDiscount',
                    Session::get(
                        'ShoppingCart.PromoCoupon.CouponDiscount',
                        0
                    )
                )
            );

        $previousTax =
            (float) Session::get(
                'ShoppingCart.Tax',
                0
            );

        /*
        * Maximum 5 iterations.
        */
        for (
            $iteration = 1;
            $iteration <= 5;
            $iteration++
        ) {

            /*
            * -----------------------------------------------------
            * 1. Recalculate Coupon using current Tax
            * -----------------------------------------------------
            *
            * Existing CouponService logic remains untouched.
            *
            * This preserves:
            * - Order Amount
            * - SKU
            * - Category
            * - Brand
            * - Excluded SKU
            * - Pocket Perfume
            * - Deal of Week
            * - Gift Certificate
            * - Free Shipping
            * - Free Gift
            */
            $couponResult =
                $this->couponService->apply(
                    $couponCode,
                    (int) Session::get(
                        'sess_icustomerid',
                        0
                    )
                );

            /*
            * Read fresh coupon discount.
            */
            $currentCouponDiscount =
                (float) (
                    $couponResult['discount']
                    ??
                    Session::get(
                        'ShoppingCart.PromoCoupon.FirstCouponDiscount',
                        Session::get(
                            'ShoppingCart.PromoCoupon.CouponDiscount',
                            0
                        )
                    )
                );

            /*
            * -----------------------------------------------------
            * 2. Recalculate Tax using fresh Coupon Discount
            * -----------------------------------------------------
            */
            $this->calculateTax(
                $cartAttributes,$taxContext
            );

            $currentTax =
                (float) Session::get(
                    'ShoppingCart.Tax',
                    0
                );

            /*
            * -----------------------------------------------------
            * 3. Check whether Coupon + Tax are stable
            * -----------------------------------------------------
            */
            if (
                abs(
                    $currentCouponDiscount
                    -
                    $previousCouponDiscount
                ) < 0.01
                &&
                abs(
                    $currentTax
                    -
                    $previousTax
                ) < 0.01
            ) {
                break;
            }

            $previousCouponDiscount =
                $currentCouponDiscount;

            $previousTax =
                $currentTax;
        }

        /*
        * Final synchronized calculation.
        *
        * Apply coupon one final time using the latest Tax,
        * then calculate final Tax using the latest Coupon.
        */
        $couponResult =
            $this->couponService->apply(
                $couponCode,
                (int) Session::get(
                    'sess_icustomerid',
                    0
                )
            );

        $this->calculateTax(
            $cartAttributes,$taxContext
        );
    }

    public function GetDropshipperDetails()
    {
        $DropshipperAccountDetails = array();
        $DropshipperAccountDetails['status'] = false;
        if(Auth::user() && Auth::user()->is_dropshipper == 'Yes' && Auth::user()->eusertype == 'Wholesaler')
        {
            $customer = Customer::where('customer_id', '=', Auth::user()->customer_id)->first();
            if ($customer && $customer->count() > 0) {
                $available_funds = $customer->available_funds;
                $DropshipperAccountDetails['status'] = true;
                $NetTotal = $this->checkoutTotalsService->getNetTotal();
                if ($available_funds >= $NetTotal) {
                    $DropshipperAccountDetails['fund_available'] = 'Yes';
                    $DropshipperAccountDetails['fund_msg'] = "";
                    $DropshipperAccountDetails['total_fund'] = $available_funds;
                    $DropshipperAccountDetails['total_fund_formated'] = Price($available_funds);
                    $DropshipperAccountDetails['total_payment'] = $NetTotal;
                    $DropshipperAccountDetails['total_payment_formated'] = Price($NetTotal);
                    $DropshipperAccountDetails['remaining_fund'] = $available_funds - $NetTotal;
                    $DropshipperAccountDetails['remaining_fund_formated'] = Price($available_funds - $NetTotal);
                    $DropshipperAccountDetails['required_fund'] = "";
                } else {
                    $DropshipperAccountDetails['fund_available'] = 'No';
                    $DropshipperAccountDetails['fund_msg'] = "Your dropshipper account does not have sufficient balance";
                    $DropshipperAccountDetails['total_fund'] = $available_funds;
                    $DropshipperAccountDetails['total_fund_formated'] = Price($available_funds);
                    $DropshipperAccountDetails['total_payment'] = $NetTotal;
                    $DropshipperAccountDetails['total_payment_formated'] = Price($NetTotal);
                    $DropshipperAccountDetails['remaining_fund'] = "";
                    $DropshipperAccountDetails['required_fund'] = $NetTotal - $available_funds;
                    $DropshipperAccountDetails['required_fund_formated'] = Price($NetTotal - $available_funds);
                }
            }
        }
        return $DropshipperAccountDetails;
    }
}
