<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
use App\Services\Discount\CouponService;
use App\Services\Discount\FreeGiftService;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\Checkout\CheckoutTotalsService;
use App\Services\Discount\FreeSampleService;

class CheckoutCartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService,
        protected FreeGiftService $freeGiftService,
        protected FreeSampleService $freeSampleService,
        protected CheckoutTotalsService $checkoutTotalsService,
        protected CouponService $couponService
    ) {
    }

    /**
     * Add product to checkout cart.
     *
     * CartService owns stock/product/cart business logic.
     * Controller only validates HTTP input and returns JSON.
     */
  public function add(Request $request): JsonResponse
{
    /*
     * jQuery form POST can send boolean values as strings.
     * Normalize them before Laravel boolean validation.
     */
    if ($request->has('free_gift')) {
        $request->merge([
            'free_gift' => filter_var(
                $request->input('free_gift'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
        ]);
    }

    if ($request->has('free_gift_one')) {
        $request->merge([
            'free_gift_one' => filter_var(
                $request->input('free_gift_one'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
        ]);
    }

    $validated = $request->validate([
        'product_id' => ['required', 'integer', 'min:1'],
        'qty' => ['nullable', 'integer', 'min:1'],
        'order_type' => ['nullable', 'string'],
        'cookie' => ['nullable', 'string'],
        'gift_wrap' => ['nullable', 'string'],
        'free_gift' => ['nullable', 'boolean'],
        'free_product_id' => ['nullable', 'integer', 'min:0'],
        'free_gift_one' => ['nullable', 'boolean'],
    ]);

    /*
     * =========================================================
     * FREE GIFT
     * =========================================================
     */
    if (($validated['free_gift'] ?? false) === true) {

        $message =
            $this->freeGiftService->addGift(
                (int) $validated['product_id'],
                (int) ($validated['free_product_id'] ?? 0),
                ($validated['free_gift_one'] ?? false)
                    ? 'Yes'
                    : 'No'
            );

        $result = [
            'success' => $message === '',
            'status' => $message === ''
                ? 'success'
                : 'error',
            'message' => $message,
        ];

        if ($message === '') {

            $result['checkout'] =
                $this->checkoutService
                    ->refresh('cart');

            $result['cart'] =
                $this->cartService
                    ->getCart();

            $result['freeGift'] = [
                'status' => 'selected',
                'shouldPopup' => false,
                'shouldAutoAdd' => false,
                'eligibleGifts' => [],
                'remainingCount' => 0,
                'cart' => $result['cart'],
            ];
        }

        return response()->json($result);
    }

    /*
     * =========================================================
     * NORMAL PRODUCT
     * =========================================================
     */
    $result =
        $this->cartService->addByProductId(
            (int) $validated['product_id'],
            (int) ($validated['qty'] ?? 1),
            $validated['order_type'] ?? 'Website',
            $validated['cookie'] ?? 'No',
            $validated['gift_wrap'] ?? 'No'
        );

    if (($result['success'] ?? false) === true) {

        $result['checkout'] =
            $this->checkoutService
                ->refresh('cart');

        $result['freeGift'] =
            $this->resolveFreeGiftAfterCartChange();

        $result['freeGift'] =
            $this->attachFinalFreeGiftCart(
                $result['freeGift']
            );
    }

    return response()->json($result);
}
    /**
     * Update existing cart item quantity.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'qty' => ['required', 'integer', 'min:1'],
            'gift_wrap' => ['nullable', 'string'],
        ]);

        $result =
            $this->cartService->update(
                (int) $validated['product_id'],
                (int) $validated['qty'],
                $validated['gift_wrap'] ?? 'No'
            );

        if (($result['success'] ?? false) === true) {
            $result['checkout'] =
                $this->checkoutService
                    ->refresh('cart');

            $result['freeGift'] =
                $this->resolveFreeGiftAfterCartChange();

            /*
             * Truth Mode:
             * Keep response.cart unchanged.
             *
             * The existing checkout.js quantity flow expects
             * response.cart for the product that was updated, while
             * the final Free Gift cart is exposed inside
             * response.freeGift.cart.
             *
             * Do NOT promote freeGift.cart to response.cart.
             * That previously changed the response contract and
             * caused the existing Qty 7 behaviour to stop working.
             */
            $result['freeGift'] =
                $this->attachFinalFreeGiftCart(
                    $result['freeGift']
                );
        }

        return response()->json($result);
    }

    /**
     * Remove by ShoppingCart.Cart array index.
     */
    public function remove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cart_id' => ['required', 'integer', 'min:0'],
        ]);

        $result =
            $this->cartService->remove(
                (int) $validated['cart_id']
            );

       if (($result['success'] ?? false) === true) {

    /*
     * ---------------------------------------------------------
     * Check final cart after removing the requested item.
     * ---------------------------------------------------------
     */
    $cart =
        Session::get(
            'ShoppingCart.Cart',
            []
        );

    $hasRegularProduct = false;

    foreach ($cart as $item) {

        $isFreeGift =
            ($item['IS_Free_Gift'] ?? 'No') === 'Yes';

        $isFreeSample =
            ($item['Is_Free_Sample'] ?? 'No') === 'Yes';

        /*
         * Free Gift and Free Sample do not count
         * as regular products.
         */
        if (
            !$isFreeGift
            &&
            !$isFreeSample
        ) {
            $hasRegularProduct = true;

            break;
        }
    }


    /*
     * ---------------------------------------------------------
     * No regular product remains.
     *
     * If the cart only contains the coupon Free Gift,
     * remove the coupon and its Free Gift, then redirect
     * to Shopping Cart.
     * ---------------------------------------------------------
     */
    if (!$hasRegularProduct) {

        $this->couponService
            ->removeCoupon();

        /*
         * Get the FINAL cart after removeCoupon().
         *
         * removeCoupon() also removes items marked:
         * FreeGiftCoupon = Yes
         */
        $result['cart'] =
            Session::get(
                'ShoppingCart.Cart',
                []
            );

        return response()->json([
            'success' => true,
            'status' => 'redirect',
            'redirect' => url('/shoppingcart'),
            'cart' => $result['cart'],
        ]);
    }


    /*
     * ---------------------------------------------------------
     * Normal cart flow.
     * ---------------------------------------------------------
     */
    $result['checkout'] =
        $this->checkoutService
            ->refresh('cart');

    $result['freeGift'] =
        $this->resolveFreeGiftAfterCartChange();

    /*
     * Keep existing response contract.
     */
    $result['freeGift'] =
        $this->attachFinalFreeGiftCart(
            $result['freeGift']
        );
}

        return response()->json($result);
    }

    /**
     * Return the current checkout cart state.
     *
     * This is the API replacement for the cart-data portion of the
     * legacy GetCart/GetCartPartial flow. HTML rendering remains in
     * the Blade layer and is intentionally not generated here.
     */
    public function summary(): JsonResponse
    {
        $cart = $this->cartService->getCart();

        $subtotal = (float) \Session::get(
            'ShoppingCart.SubTotal',
            0
        );

        $itemCount = 0;

        foreach ($cart as $item) {
            if (
                isset($item['Qty'])
                && !isset($item['IS_Free_Gift'])
                && !isset($item['Is_Free_Sample'])
            ) {
                $itemCount += (int) $item['Qty'];
            }
        }

        return response()->json([
            'success' => true,
            'cart' => $cart,
            'subtotal' => $subtotal,
            'item_count' => $itemCount,
            'cart_count' => count($cart),
        ]);
    }

    /**
     * Empty the checkout cart.
     */
    public function clear(): JsonResponse
    {
        $result =
            $this->cartService->clear();

        if (($result['success'] ?? false) === true) {
            $result['checkout'] =
                $this->checkoutService
                    ->refresh('cart');

            $result['freeGift'] =
                $this->resolveFreeGiftAfterCartChange();

            /*
             * Truth Mode:
             * Keep response.cart unchanged.
             *
             * The existing checkout.js quantity flow expects
             * response.cart for the product that was updated, while
             * the final Free Gift cart is exposed inside
             * response.freeGift.cart.
             *
             * Do NOT promote freeGift.cart to response.cart.
             * That previously changed the response contract and
             * caused the existing Qty 7 behaviour to stop working.
             */
            $result['freeGift'] =
                $this->attachFinalFreeGiftCart(
                    $result['freeGift']
                );
        }

        return response()->json($result);
    }
    /**
     * Resolve Free Gift state after a successful cart mutation.
     *
     * Truth Mode:
     * - Existing cart/price/discount logic remains unchanged.
     * - Free Gift rules are resolved only after the cart mutation and
     *   checkout refresh.
     * - One eligible gift may be auto-added.
     * - Multiple eligible gifts are returned for the existing popup UI.
     */
    protected function attachFinalFreeGiftCart(
        array $freeGift
    ): array {
        /*
         * The resolver already returns the final cart after an
         * automatic Free Gift add/remove.
         *
         * Keep that cart nested under freeGift so the frontend can
         * render it without changing the normal response.cart shape.
         */
        if (
            !isset($freeGift['cart'])
            || !is_array($freeGift['cart'])
        ) {
            $freeGift['cart'] =
                $this->cartService->getCart();
        }

        return $freeGift;
    }

public function resolveFreeGiftAfterCartChange(): array
{
    /*
     * =========================================================
     * CURRENT CART
     * =========================================================
     */
    $isFreeGiftCoupon =
    Session::get(
        'ShoppingCart.PromoCoupon.HasFreeGift',
        'No'
    ) === 'Yes';

	if ($isFreeGiftCoupon) {
		return [
			'status' => 'coupon_active',
			'shouldPopup' => false,
			'shouldAutoAdd' => false,
			'eligibleGifts' => [],
			'remainingCount' => 0,
			'cart' => $this->cartService->getCart(),
		];
	}

    $shoppingCart =
        $this->cartService->getCart();

    $cart =
        is_array($shoppingCart)
        && isset($shoppingCart['Cart'])
        && is_array($shoppingCart['Cart'])
            ? $shoppingCart['Cart']
            : (
                is_array($shoppingCart)
                    ? $shoppingCart
                    : []
            );

    /*
     * =========================================================
     * EMPTY CART
     * =========================================================
     */
    if (empty($cart)) {
        return [
            'status' => 'no_rule',
            'shouldPopup' => false,
            'shouldAutoAdd' => false,
            'eligibleGifts' => [],
            'remainingCount' => 0,
            'removedFreeGiftCount' => 0,
            'ruleChanged' => false,
            'cart' => [],
        ];
    }

    /*
     * =========================================================
     * EXISTING FREE GIFTS
     * =========================================================
     *
     * Free Sample is NOT a Free Gift.
     */
    $existingGiftCount = 0;
    $existingFreeGiftRuleIds = [];
    $existingFreeGiftIndexes = [];

    foreach ($cart as $index => $item) {

        if (
            ($item['IS_Free_Gift'] ?? 'No') !== 'Yes'
        ) {
            continue;
        }

        if (
            ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
        ) {
            continue;
        }

        $existingGiftCount += max(
            1,
            (int) ($item['Qty'] ?? 1)
        );

        $existingFreeGiftIndexes[] =
            $index;

        $ruleId = (int) (
            $item['freeproductsid']
            ??
            $item['FreeGiftRuleId']
            ??
            0
        );

        if ($ruleId > 0) {
            $existingFreeGiftRuleIds[$ruleId] = true;
        }
    }

    /*
     * =========================================================
     * CURRENT PURCHASE VALUE
     * =========================================================
     */
    $totalValue =
        (float) Session::get(
            'ShoppingCart.SubTotal',
            0
        );

    Log::info(
        'Free Gift Rule Before Resolve',
        [
            'subtotal' =>
                $totalValue,

            'existingGiftCount' =>
                $existingGiftCount,

            'existingRuleIds' =>
                array_keys(
                    $existingFreeGiftRuleIds
                ),

            'existingFreeGiftIndexes' =>
                $existingFreeGiftIndexes,
        ]
    );

    /*
     * =========================================================
     * RESOLVE CURRENT RULE
     * =========================================================
     */
    Log::info(
        'FREE GIFT RESOLVE INPUT',
        [
            'subtotal' =>
                $totalValue,

            'cart_count' =>
                count($cart),

            'existingGiftCount' =>
                $existingGiftCount,

            'cart' =>
                $cart,
        ]
    );

    $decision =
        $this->freeGiftService
            ->resolveEligibleGifts(
                $cart,
                $totalValue,
                $existingGiftCount,
                0
            );

    $newRuleId =
        (int) (
            $decision['rule']['id']
            ?? 0
        );

    Log::info(
        'FREE GIFT RESOLVE OUTPUT',
        [
            'status' =>
                $decision['status']
                ?? null,

            'rule' =>
                $decision['rule']
                ?? null,

            'eligibleGifts' =>
                $decision['eligibleGifts']
                ?? [],

            'remainingCount' =>
                $decision['remainingCount']
                ?? null,

            'shouldPopup' =>
                $decision['shouldPopup']
                ?? null,

            'shouldAutoAdd' =>
                $decision['shouldAutoAdd']
                ?? null,

            'popupHtmlExists' =>
                !empty(
                    $decision['popupHtml']
                    ?? ''
                ),
        ]
    );

    /*
     * =========================================================
     * RULE CHANGE DETECTION
     * =========================================================
     */
    $ruleChanged =
        $newRuleId > 0
        &&
        $existingGiftCount > 0
        &&
        (
            empty($existingFreeGiftRuleIds)
            ||
            !isset(
                $existingFreeGiftRuleIds[$newRuleId]
            )
        );

    $removedFreeGiftCount = 0;

    /*
     * =========================================================
     * RULE CHANGED
     * =========================================================
     */
    if ($ruleChanged) {

        /*
         * -----------------------------------------------------
         * REMOVE OLD AUTO-ADDED FREE GIFT
         * -----------------------------------------------------
         */
        $removedFreeGiftCount =
            $this->freeGiftService
                ->removeAutoAddedFreeGifts();

        /*
         * -----------------------------------------------------
         * FALLBACK FOR LEGACY FREE GIFT
         * -----------------------------------------------------
         */
        if (
            $removedFreeGiftCount === 0
            &&
            !empty($existingFreeGiftIndexes)
        ) {

            $currentCart =
                $this->cartService
                    ->getCart();

            if (
                is_array($currentCart)
                &&
                isset($currentCart['Cart'])
                &&
                is_array($currentCart['Cart'])
            ) {
                $currentCart =
                    $currentCart['Cart'];
            }

            if (!is_array($currentCart)) {
                $currentCart = [];
            }

            $filteredCart = [];

            foreach ($currentCart as $item) {

                $isFreeGift =
                    ($item['IS_Free_Gift'] ?? 'No')
                    === 'Yes';

                $isFreeSample =
                    ($item['Is_Free_Sample'] ?? 'No')
                    === 'Yes';

                if (
                    $isFreeGift
                    &&
                    !$isFreeSample
                ) {
                    continue;
                }

                $filteredCart[] = $item;
            }

            Session::put(
                'ShoppingCart.Cart',
                array_values($filteredCart)
            );

            $removedFreeGiftCount = 1;
        }

        /*
         * -----------------------------------------------------
         * OLD GIFT REMOVED
         * -----------------------------------------------------
         */
        if ($removedFreeGiftCount > 0) {

            $this->checkoutService
                ->refresh('cart');

            /*
             * Get latest cart after removal.
             */
            $shoppingCart =
                $this->cartService
                    ->getCart();

            $cart =
                is_array($shoppingCart)
                &&
                isset($shoppingCart['Cart'])
                &&
                is_array($shoppingCart['Cart'])
                    ? $shoppingCart['Cart']
                    : (
                        is_array($shoppingCart)
                            ? $shoppingCart
                            : []
                    );

            /*
             * Re-read subtotal.
             */
            $totalValue =
                (float) Session::get(
                    'ShoppingCart.SubTotal',
                    0
                );

            /*
             * -------------------------------------------------
             * RE-RESOLVE NEW RULE
             * -------------------------------------------------
             */
            $decision =
                $this->freeGiftService
                    ->resolveEligibleGifts(
                        $cart,
                        $totalValue,
                        0,
                        0
                    );

            $decision['ruleChanged'] =
                true;

            $decision['removedFreeGiftCount'] =
                $removedFreeGiftCount;

            Log::info(
                'Free Gift Rule AFTER Old Gift Removal',
                [
                    'subtotal' =>
                        $totalValue,

                    'removedFreeGiftCount' =>
                        $removedFreeGiftCount,

                    'status' =>
                        $decision['status']
                        ?? null,

                    'rule' =>
                        $decision['rule']
                        ?? null,

                    'eligibleGifts' =>
                        $decision['eligibleGifts']
                        ?? [],

                    'remainingCount' =>
                        $decision['remainingCount']
                        ?? null,

                    'shouldAutoAdd' =>
                        $decision['shouldAutoAdd']
                        ?? null,

                    'shouldPopup' =>
                        $decision['shouldPopup']
                        ?? null,
                ]
            );

            /*
             * =================================================
             * NEW RULE - AUTO ADD
             * =================================================
             */
            if (
                ($decision['status'] ?? '') === 'auto_add'
                &&
                !empty(
                    $decision['eligibleGifts'][0]
                )
            ) {

                $gift =
                    $decision['eligibleGifts'][0];

                $giftProductId =
                    (int) (
                        $gift['products_id']
                        ?? 0
                    );

                $giftRuleId =
                    (int) (
                        $decision['rule']['id']
                        ?? $gift['free_gift_products_id']
                        ?? 0
                    );

                Log::info(
                    'FREE GIFT NEW RULE AUTO ADD START',
                    [
                        'products_id' =>
                            $giftProductId,

                        'freeproductsid' =>
                            $giftRuleId,

                        'rule' =>
                            $decision['rule']
                            ?? null,

                        'gift' =>
                            $gift,
                    ]
                );

                if ($giftProductId > 0) {

                    $message =
                        $this->freeGiftService
                            ->addGift(
                                $giftProductId,
                                $giftRuleId,
                                'No'
                            );

                    if ($message === '') {

                        $decision['status'] =
                            'auto_added';

                        $decision['shouldAutoAdd'] =
                            false;

                        $decision['shouldPopup'] =
                            false;

                        $decision['autoAddedProductId'] =
                            $giftProductId;

                        $decision['message'] =
                            '';

                        $this->checkoutService
                            ->refresh('cart');

                        $finalCart =
                            $this->cartService
                                ->getCart();

                        if (
                            is_array($finalCart)
                            &&
                            isset(
                                $finalCart['Cart']
                            )
                            &&
                            is_array(
                                $finalCart['Cart']
                            )
                        ) {

                            $finalCart =
                                $finalCart['Cart'];
                        }

                        $decision['cart'] =
                            is_array($finalCart)
                                ? $finalCart
                                : [];

                    } else {

                        $decision['status'] =
                            'auto_add_failed';

                        $decision['shouldAutoAdd'] =
                            false;

                        $decision['shouldPopup'] =
                            false;

                        $decision['autoAddError'] =
                            $message
                            ??
                            'Unable to add free gift.';
                    }

                } else {

                    $decision['status'] =
                        'auto_add_failed';

                    $decision['shouldAutoAdd'] =
                        false;

                    $decision['shouldPopup'] =
                        false;

                    $decision['autoAddError'] =
                        'Invalid free gift product.';
                }
            }
        }
    }

    /*
     * =========================================================
     * NO RULE / QUALIFICATION LOST
     * =========================================================
     */
    if (
        ($decision['status'] ?? '') === 'no_rule'
        &&
        $existingGiftCount > 0
        &&
        !$ruleChanged
    ) {

        Log::info(
            'Free Gift Removal Attempt',
            [
                'decisionStatus' =>
                    $decision['status']
                    ?? null,

                'existingGiftCount' =>
                    $existingGiftCount,

                'cartBeforeRemoval' =>
                    $cart,
            ]
        );

        $removed =
    $this->freeGiftService
        ->removeAllFreeGifts();

        if ($removed > 0) {

            $this->checkoutService
                ->refresh('cart');

            $finalCart =
                $this->cartService
                    ->getCart();

            if (
                is_array($finalCart)
                &&
                isset($finalCart['Cart'])
                &&
                is_array($finalCart['Cart'])
            ) {

                $finalCart =
                    $finalCart['Cart'];
            }

            $decision['status'] =
                'qualification_lost';

            $decision['shouldAutoAdd'] =
                false;

            $decision['shouldPopup'] =
                false;

            $decision['removedFreeGiftCount'] =
                $removed;

            $decision['cart'] =
                is_array($finalCart)
                    ? $finalCart
                    : [];
        }
    }

    /*
     * =========================================================
     * POPUP
     * =========================================================
     */
    if (
        ($decision['status'] ?? '') === 'popup'
        &&
        !empty(
            $decision['eligibleGifts']
        )
    ) {

        /*
         * IMPORTANT:
         *
         * Always get the LATEST cart here.
         *
         * This is critical after a Free Gift has been removed
         * because the old $cart variable may contain the previous
         * Free Gift.
         */
        $latestShoppingCart =
            $this->cartService
                ->getCart();

        $latestCart =
            is_array($latestShoppingCart)
            &&
            isset($latestShoppingCart['Cart'])
            &&
            is_array($latestShoppingCart['Cart'])
                ? $latestShoppingCart['Cart']
                : (
                    is_array($latestShoppingCart)
                        ? $latestShoppingCart
                        : []
                );

        /*
         * -----------------------------------------------------
         * EXISTING FREE GIFT PRODUCT IDS
         * -----------------------------------------------------
         */
        $existingFreeGiftProductIds = [];

        /*
         * -----------------------------------------------------
         * EXISTING FREE GIFT SKUS
         * -----------------------------------------------------
         */
        $existingFreeGiftSkus = [];

        foreach (
            $latestCart
            as $cartItem
        ) {

            if (
                !is_array($cartItem)
            ) {
                continue;
            }

            /*
             * ONLY actual Free Gifts.
             */
            if (
                ($cartItem['IS_Free_Gift'] ?? 'No')
                !== 'Yes'
            ) {
                continue;
            }

            /*
             * Free Sample is NOT a Free Gift.
             */
            if (
                ($cartItem['Is_Free_Sample'] ?? 'No')
                === 'Yes'
            ) {
                continue;
            }

            /*
             * Product ID.
             */
            $cartProductId =
                (int) (
                    $cartItem['ProductID']
                    ??
                    $cartItem['products_id']
                    ??
                    $cartItem['product_id']
                    ??
                    0
                );

            if (
                $cartProductId > 0
            ) {

                $existingFreeGiftProductIds[
                    $cartProductId
                ] = true;
            }

            /*
             * ORGSKU.
             */
            $cartOrgSku =
                strtoupper(
                    trim(
                        (string) (
                            $cartItem['ORGSKU']
                            ??
                            $cartItem['orgsku']
                            ??
                            ''
                        )
                    )
                );

            if (
                $cartOrgSku !== ''
            ) {

                $existingFreeGiftSkus[
                    $cartOrgSku
                ] = true;
            }

            /*
             * SKU.
             *
             * Example:
             * GIFT-UP8411061251706
             *
             * becomes:
             * UP8411061251706
             */
            $cartSku =
                strtoupper(
                    trim(
                        (string) (
                            $cartItem['SKU']
                            ??
                            $cartItem['sku']
                            ??
                            ''
                        )
                    )
                );

            if (
                str_starts_with(
                    $cartSku,
                    'GIFT-'
                )
            ) {

                $cartSku =
                    substr(
                        $cartSku,
                        5
                    );
            }

            if (
                $cartSku !== ''
            ) {

                $existingFreeGiftSkus[
                    $cartSku
                ] = true;
            }
        }

        /*
         * -----------------------------------------------------
         * PREPARE POPUP GIFTS
         * -----------------------------------------------------
         */
        $popupGifts =
            is_array(
                $decision['eligibleGifts']
            )
                ? $decision['eligibleGifts']
                : [];

        $popupGifts =
            array_map(
                function ($gift) use (
                    $decision,
                    $existingFreeGiftProductIds,
                    $existingFreeGiftSkus
                ) {

                    $gift =
                        is_array($gift)
                            ? $gift
                            : [];

                    /*
                     * =================================================
                     * PRODUCT ID
                     * =================================================
                     */
                    $productId =
                        (int) (
                            $gift['products_id']
                            ??
                            $gift['product_id']
                            ??
                            $gift['ProductID']
                            ??
                            0
                        );

                    $gift['products_id'] =
                        $productId;

                    /*
                     * =================================================
                     * SKU
                     * =================================================
                     */
                    $giftSku =
                        strtoupper(
                            trim(
                                (string) (
                                    $gift['sku']
                                    ??
                                    $gift['SKU']
                                    ??
                                    ''
                                )
                            )
                        );

                    /*
                     * Normalize GIFT- prefix.
                     */
                    if (
                        str_starts_with(
                            $giftSku,
                            'GIFT-'
                        )
                    ) {

                        $giftSku =
                            substr(
                                $giftSku,
                                5
                            );
                    }

                    /*
                     * =================================================
                     * FOUND SKU
                     * =================================================
                     *
                     * IMPORTANT:
                     *
                     * Do NOT trust FoundSku from the FreeGiftService.
                     *
                     * Calculate it ONLY from the CURRENT cart.
                     *
                     * Existing Free Gift:
                     *     Yes
                     *
                     * Not in cart:
                     *     No
                     */
                    $foundInCart =
                        (
                            $productId > 0
                            &&
                            isset(
                                $existingFreeGiftProductIds[
                                    $productId
                                ]
                            )
                        )
                        ||
                        (
                            $giftSku !== ''
                            &&
                            isset(
                                $existingFreeGiftSkus[
                                    $giftSku
                                ]
                            )
                        );

                    $gift['FoundSku'] =
                        $foundInCart
                            ? 'Yes'
                            : 'No';

                    /*
                     * =================================================
                     * PRODUCT NAME
                     * =================================================
                     */
                    $gift['product_name'] =
                        $gift['product_name']
                        ??
                        $gift['ProductName']
                        ??
                        $gift['name']
                        ??
                        '';

                    /*
                     * =================================================
                     * DESCRIPTION
                     * =================================================
                     */
                    $gift['short_description'] =
                        $gift['short_description']
                        ??
                        $gift['ProductName_description']
                        ??
                        '';

                    /*
                     * =================================================
                     * FREE GIFT RULE ID
                     * =================================================
                     */
                    $gift['free_gift_products_id'] =
                        (int) (
                            $gift['free_gift_products_id']
                            ??
                            $gift['freeproductsid']
                            ??
                            $gift['FreeGiftRuleId']
                            ??
                            $decision['rule']['id']
                            ??
                            0
                        );

                    /*
                     * =================================================
                     * FREE GIFT COUNT
                     * =================================================
                     */
                    $gift['freegift_add_count'] =
                        (int) (
                            $gift['freegift_add_count']
                            ??
                            $decision['rule']['freegift_add_count']
                            ??
                            $decision['remainingCount']
                            ??
                            1
                        );

                    /*
                     * =================================================
                     * IMAGE
                     * =================================================
                     */
                    $thumbImage =
                        trim(
                            (string) (
                                $gift['thumb_image']
                                ??
                                ''
                            )
                        );

                    if (
                        $thumbImage !== ''
                        &&
                        str_contains(
                            $thumbImage,
                            '<img'
                        )
                    ) {

                        if (
                            preg_match(
                                '/<img[^>]+src=["\']([^"\']*)["\']/i',
                                $thumbImage,
                                $matches
                            )
                        ) {

                            $thumbImage =
                                trim(
                                    (string) (
                                        $matches[1]
                                        ??
                                        ''
                                    )
                                );

                        } else {

                            $thumbImage =
                                '';
                        }
                    }

                    if (
                        $thumbImage === ''
                        &&
                        !empty(
                            $gift['image']
                        )
                    ) {

                        $thumbImage =
                            trim(
                                (string) $gift['image']
                            );
                    }

                    if (
                        $thumbImage === ''
                        &&
                        $productId > 0
                    ) {

                        try {

                            $productImage =
                                \App\Models\Products::where(
                                    'products_id',
                                    $productId
                                )
                                ->value(
                                    'image'
                                );

                            $thumbImage =
                                trim(
                                    (string) (
                                        $productImage
                                        ??
                                        ''
                                    )
                                );

                        } catch (
                            \Throwable $e
                        ) {

                            Log::warning(
                                'Free Gift popup product image lookup failed',
                                [
                                    'products_id' =>
                                        $productId,

                                    'message' =>
                                        $e->getMessage(),
                                ]
                            );

                            $thumbImage =
                                '';
                        }
                    }

                    $thumbImage =
                        trim(
                            stripslashes(
                                $thumbImage
                            )
                        );

                    if (
                        $thumbImage !== ''
                        &&
                        !empty(
                            config(
                                'global.PRD_THUMB_IMG_PATH'
                            )
                        )
                    ) {

                        $thumbnailPath =
                            rtrim(
                                (string) config(
                                    'global.PRD_THUMB_IMG_PATH'
                                ),
                                '/'
                            )
                            . '/'
                            . ltrim(
                                $thumbImage,
                                '/'
                            );

                        if (
                            !file_exists(
                                $thumbnailPath
                            )
                        ) {

                            $thumbImage =
                                '';
                        }
                    }

                    if (
                        $thumbImage === ''
                    ) {

                        $thumbImage =
                            config(
                                'global.NO_IMAGE_THUMB'
                            );
                    }

                    $gift['thumb_image'] =
                        $thumbImage;

                    return $gift;

                },
                $popupGifts
            );

        /*
         * =========================================================
         * POPUP HTML
         * =========================================================
         */
        $totalListItems =
            (int) (
                $popupGifts[0]['freegift_add_count']
                ??
                $decision['rule']['freegift_add_count']
                ??
                $decision['remainingCount']
                ??
                1
            );

        try {

            $decision['popupHtml'] =
                view(
                    'popup.freegift-popup'
                )
                ->with(
                    [
                        'TotalListItems' =>
                            $totalListItems,

                        'Free_Gift_Res' =>
                            $popupGifts,
                    ]
                )
                ->render();

        } catch (
            \Throwable $e
        ) {

            Log::error(
                'Free Gift popup render failed',
                [
                    'message' =>
                        $e->getMessage(),

                    'rule' =>
                        $decision['rule']
                        ?? null,

                    'eligibleGifts' =>
                        $popupGifts,
                ]
            );

            $decision['popupHtml'] =
                '';
        }
    }

    /*
     * =========================================================
     * AUTO ADD SINGLE GIFT
     * =========================================================
     */
    if (
        ($decision['status'] ?? '') === 'auto_add'
        &&
        !empty(
            $decision['eligibleGifts']
        )
    ) {

        $gift =
            $decision['eligibleGifts'][0];

        $message =
            $this->freeGiftService
                ->addGift(
                    (int) (
                        $gift['products_id']
                        ?? 0
                    ),
                    (int) (
                        $decision['rule']['id']
                        ?? 0
                    ),
                    'No'
                );

        if ($message === '') {

            $decision['status'] =
                'auto_added';

            $decision['shouldAutoAdd'] =
                false;

            $decision['shouldPopup'] =
                false;

            $decision['autoAddedProductId'] =
                (int) (
                    $gift['products_id']
                    ?? 0
                );

            $decision['message'] =
                '';

            $this->checkoutService
                ->refresh('cart');

            $decision['cart'] =
                $this->cartService
                    ->getCart();

            if (
                is_array($decision['cart'])
                &&
                isset(
                    $decision['cart']['Cart']
                )
                &&
                is_array(
                    $decision['cart']['Cart']
                )
            ) {

                $decision['cart'] =
                    $decision['cart']['Cart'];
            }

        } else {

            $decision['status'] =
                'auto_add_failed';

            $decision['shouldAutoAdd'] =
                false;

            $decision['shouldPopup'] =
                false;

            $decision['autoAddError'] =
                $message
                ??
                'Unable to add free gift.';
        }
    }

    /*
     * =========================================================
     * FINAL CART
     * =========================================================
     */
    if (
        !isset($decision['cart'])
        ||
        !is_array($decision['cart'])
    ) {

        $finalCart =
            $this->cartService
                ->getCart();

        if (
            is_array($finalCart)
            &&
            isset(
                $finalCart['Cart']
            )
            &&
            is_array(
                $finalCart['Cart']
            )
        ) {

            $finalCart =
                $finalCart['Cart'];
        }

        $decision['cart'] =
            is_array($finalCart)
                ? $finalCart
                : [];
    }

    /*
     * =========================================================
     * FINAL DEBUG
     * =========================================================
     */
    Log::info(
        'Free Gift Checkout Debug',
        [
            'subtotal' =>
                $totalValue,

            'existingGiftCount' =>
                $existingGiftCount,

            'existingRuleIds' =>
                array_keys(
                    $existingFreeGiftRuleIds
                ),

            'decisionStatus' =>
                $decision['status']
                ?? null,

            'ruleChanged' =>
                $decision['ruleChanged']
                ?? false,

            'removedFreeGiftCount' =>
                $decision['removedFreeGiftCount']
                ?? 0,

            'shouldAutoAdd' =>
                $decision['shouldAutoAdd']
                ?? null,

            'shouldPopup' =>
                $decision['shouldPopup']
                ?? null,

            'rule' =>
                $decision['rule']
                ?? null,

            'eligibleGifts' =>
                $decision['eligibleGifts']
                ?? [],

            'remainingCount' =>
                $decision['remainingCount']
                ?? null,

            'autoAddedProductId' =>
                $decision['autoAddedProductId']
                ?? null,

            'popupHtmlExists' =>
                !empty(
                    $decision['popupHtml']
                    ?? ''
                ),

            'popupHtmlLength' =>
                strlen(
                    $decision['popupHtml']
                    ?? ''
                ),

            'finalCart' =>
                $decision['cart']
                ?? [],
        ]
    );

    return $decision;
}
public function freeSamplePopup(Request $request)
{
    /*
     * =========================================================
     * Free Sample setting disabled
     * =========================================================
     */
    if (
        config('Settings.FREESAMPLE_VALUE') != 'Yes'
    ) {

        Log::info(
            'FreeSamplePopupBlocked',
            [
                'reason' =>
                    'FREESAMPLE_VALUE_NOT_YES',

                'value' =>
                    config(
                        'Settings.FREESAMPLE_VALUE'
                    ),
            ]
        );

        $this->freeSampleService
            ->removeSamples();

        Session::forget(
            'ShoppingCart.FreeSamplePendingRule'
        );

        return response()->json([
            'status' => 'success',
            'html' => '',
        ]);
    }


    /*
     * =========================================================
     * Store users cannot get Free Samples
     * =========================================================
     */
    if (
        Auth::guard('store')->check()
    ) {
        return response()->json([
            'status' => 'success',
            'html' => '',
        ]);
    }


    /*
     * =========================================================
     * Get current cart
     * =========================================================
     */
    $cart = Session::get(
        'ShoppingCart.Cart',
        []
    );


    /*
     * =========================================================
     * Free Gift has priority over Free Sample
     *
     * If a normal Free Gift rule is currently eligible,
     * Free Sample popup must NOT be shown.
     *
     * Free Gift price-range eligibility uses
     * ShoppingCart.SubTotal.
     * =========================================================
     */
    if (
        config('Settings.FREEGIFTFLAG') == 'Yes'
        &&
        !Auth::guard('store')->check()
        &&
        strtolower(
            trim(
                Session::get(
                    'eusertype',
                    ''
                )
            )
        ) != 'wholesaler'
        &&
        trim(
            Session::get(
                'is_dropshipper',
                ''
            )
        ) != 'Yes'
    ) {

        $freeGiftTotalValue =
            (float) Session::get(
                'ShoppingCart.SubTotal',
                0
            );


        $freeGiftDecision =
            $this->freeGiftService
                ->resolveEligibleGifts(
                    $cart,
                    $freeGiftTotalValue,
                    0,
                    0
                );


        $eligibleFreeGifts =
            $freeGiftDecision['eligibleGifts']
            ?? [];


        if (
            !empty($eligibleFreeGifts)
        ) {

            Log::info(
                'FreeSamplePopupBlockedByFreeGift',
                [
                    'reason' =>
                        'FREE_GIFT_HAS_PRIORITY',

                    'subtotal' =>
                        $freeGiftTotalValue,

                    'eligibleFreeGiftCount' =>
                        count(
                            $eligibleFreeGifts
                        ),

                    'freeGiftStatus' =>
                        $freeGiftDecision['status']
                        ?? null,

                    'freeGiftRule' =>
                        $freeGiftDecision['rule']
                        ?? null,
                ]
            );

            return response()->json([
                'status' => 'success',
                'html' => '',
            ]);
        }
    }


    /*
     * =========================================================
     * Free Gift conflict
     *
     * Preserve existing behavior:
     * If Free Gift already exists in cart,
     * do not show Free Sample popup.
     * =========================================================
     */
    foreach (
        $cart as $cartItem
    ) {

        if (
            isset(
                $cartItem['IS_Free_Gift']
            )
            &&
            $cartItem['IS_Free_Gift'] == 'Yes'
        ) {

            return response()->json([
                'status' => 'success',
                'html' => '',
            ]);
        }
    }


    /*
     * =========================================================
     * Calculate Free Sample eligibility value
     * =========================================================
     */
    $subTotal =
        NumberFormat(
            Session::get(
                'ShoppingCart.SubTotal',
                0
            )
        );


    $totalDiscount =
        (float) $this->checkoutTotalsService
            ->getTotal('discount');


    $giftCertiTotal =
        NumberFormat(
            Session::get(
                'ShoppingCart.GiftCertiTotal',
                0
            )
        );


    /*
     * DiscountService total contains GiftCoupon.
     *
     * Remove Gift Certificate from the discount amount
     * first so that we know the actual non-Gift-Certificate
     * discount.
     */
    $actualDiscount =
        max(
            0,
            $totalDiscount
            - $giftCertiTotal
        );


    /*
     * Free Sample eligibility amount.
     *
     * Do NOT subtract GiftCertiTotal again here.
     */
    $subTotal = (float) Session::get(
    'ShoppingCart.SubTotal',
    0
	);

	$totalValue = max(
		0,
		$subTotal
	);


    Log::info(
        'FREE_SAMPLE_DEBUG',
        [
            'subTotal' =>
                $subTotal,

            'totalDiscount' =>
                $totalDiscount,

            'giftCertiTotal' =>
                $giftCertiTotal,

            'totalValue' =>
                $totalValue,
        ]
    );


    /*
     * Detailed eligibility log.
     */
    Log::info(
        'FreeSamplePopupEligibility',
        [
            'subTotal' =>
                $subTotal,

            'totalDiscountFromCheckout' =>
                $totalDiscount,

            'giftCertiTotal' =>
                $giftCertiTotal,

            'actualDiscount' =>
                $actualDiscount,

            'totalValue' =>
                $totalValue,
        ]
    );


    /*
     * =========================================================
     * Wholesaler / Dropshipper exclusion
     * =========================================================
     */
    if (
        strtolower(
            trim(
                Session::get(
                    'eusertype',
                    ''
                )
            )
        ) == 'wholesaler'
        ||
        trim(
            Session::get(
                'is_dropshipper',
                ''
            )
        )
        == 'Yes'
    ) {

        return response()->json([
            'status' => 'success',
            'html' => '',
        ]);
    }


    /*
     * =========================================================
     * Shipping-cart checkout must be enabled
     * =========================================================
     */
    if (
        config(
            'Settings.CHECKOUT_SHOIPPINGCART'
        ) != 'Yes'
        ||
        $totalValue <= 0
    ) {

        $this->freeSampleService
            ->removeSamples();

        Session::forget(
            'ShoppingCart.FreeSamplePendingRule'
        );

        Log::info(
            'FreeSamplePopupBlocked',
            [
                'reason' =>
                    'CHECKOUT_DISABLED_OR_ZERO_TOTAL',

                'totalValue' =>
                    $totalValue,
            ]
        );

        return response()->json([
            'status' => 'success',
            'html' => '',
        ]);
    }


    /*
     * =========================================================
     * Current Free Sample count
     * =========================================================
     */
    $totalFreeSampleItems = 0;

    foreach (
        $cart as $cartItem
    ) {

        if (
            isset(
                $cartItem['Is_Free_Sample']
            )
            &&
            $cartItem['Is_Free_Sample'] == 'Yes'
        ) {

            $totalFreeSampleItems +=
                (int) (
                    $cartItem['Qty']
                    ?? 1
                );
        }
    }


    /*
     * =========================================================
     * Get products for the applicable Free Sample rule
     * =========================================================
     */
    $sampleProducts =
        $this->freeSampleService
            ->getSampleProductsPopup(
                $totalValue,
                $totalFreeSampleItems
            );


    /*
     * =========================================================
     * No matching Free Sample rule/products
     * =========================================================
     */
    if (
        empty($sampleProducts)
    ) {

        Log::info(
            'FreeSamplePopupBlocked',
            [
                'reason' =>
                    'NO_SAMPLE_PRODUCTS',

                'totalValue' =>
                    $totalValue,

                'totalFreeSampleItems' =>
                    $totalFreeSampleItems,
            ]
        );

        if (
            $totalFreeSampleItems > 0
        ) {

            $this->freeSampleService
                ->removeSamples();
        }

        Session::forget(
            'ShoppingCart.FreeSamplePendingRule'
        );

        return response()->json([
            'status' => 'success',
            'html' => '',
        ]);
    }


    /*
     * =========================================================
     * Current applicable Free Sample rule
     * =========================================================
     */
    $currentRuleStart =
        (float) (
            $sampleProducts[0][
                'free_sample_rule_start'
            ]
            ?? 0
        );


    $currentRuleEnd =
        (float) (
            $sampleProducts[0][
                'free_sample_rule_end'
            ]
            ?? 0
        );


    /*
     * =========================================================
     * Detect existing Free Sample rule
     *
     * Example:
     *
     * Existing:
     *     201 - 300
     *
     * New eligibility:
     *     186
     *
     * Current rule:
     *     100 - 200
     *
     * Therefore:
     *
     *     201 - 300 != 100 - 200
     *
     * Old samples must be removed.
     * =========================================================
     */
    $existingFreeSample = null;

    foreach (
        $cart as $cartItem
    ) {

        if (
            isset(
                $cartItem['Is_Free_Sample']
            )
            &&
            $cartItem['Is_Free_Sample'] == 'Yes'
        ) {

            $existingFreeSample =
                $cartItem;

            break;
        }
    }


    $oldRuleStart =
        $existingFreeSample[
            'FreeSampleRuleStart'
        ]
        ?? null;


    $oldRuleEnd =
        $existingFreeSample[
            'FreeSampleRuleEnd'
        ]
        ?? null;


    $ruleChanged =
        $existingFreeSample !== null
        &&
        $oldRuleStart !== null
        &&
        $oldRuleEnd !== null
        &&
        (
            (float) $oldRuleStart !==
            $currentRuleStart

            ||

            (float) $oldRuleEnd !==
            $currentRuleEnd
        );


    Log::info(
        'FREE_SAMPLE_RULE_CHANGE_CHECK',
        [
            'totalValue' =>
                $totalValue,

            'oldRuleStart' =>
                $oldRuleStart,

            'oldRuleEnd' =>
                $oldRuleEnd,

            'currentRuleStart' =>
                $currentRuleStart,

            'currentRuleEnd' =>
                $currentRuleEnd,

            'ruleChanged' =>
                $ruleChanged,
        ]
    );


    /*
     * =========================================================
     * Rule changed
     *
     * Remove ONLY old Free Samples.
     * Normal cart products remain untouched.
     * =========================================================
     */
    if (
        $ruleChanged
    ) {

        Log::info(
            'FREE_SAMPLE_RULE_CHANGED',
            [
                'totalValue' =>
                    $totalValue,

                'oldRuleStart' =>
                    $oldRuleStart,

                'oldRuleEnd' =>
                    $oldRuleEnd,

                'newRuleStart' =>
                    $currentRuleStart,

                'newRuleEnd' =>
                    $currentRuleEnd,
            ]
        );


        /*
         * Remove old Free Samples.
         */
        $this->freeSampleService
            ->removeSamples();


        /*
         * Store the new rule.
         *
         * addSample() will read this rule and store it
         * against the newly selected Free Samples.
         */
        Session::put(
            'ShoppingCart.FreeSamplePendingRule',
            [
                'start' =>
                    $currentRuleStart,

                'end' =>
                    $currentRuleEnd,
            ]
        );


        /*
         * Old samples are now removed.
         */
        $totalFreeSampleItems = 0;
    }


    /*
     * =========================================================
     * Customer choice
     * =========================================================
     */
    $customerChoice =
        (int) (
            $sampleProducts[0][
                'customer_choice'
            ]
            ?? 0
        );


    Log::info(
        'FreeSamplePopupCustomerChoice',
        [
            'customerChoice' =>
                $customerChoice,

            'totalFreeSampleItems' =>
                $totalFreeSampleItems,

            'totalValue' =>
                $totalValue,

            'currentRuleStart' =>
                $currentRuleStart,

            'currentRuleEnd' =>
                $currentRuleEnd,

            'ruleChanged' =>
                $ruleChanged,
        ]
    );


    /*
     * =========================================================
     * Show Free Sample popup
     *
     * If rule changed, totalFreeSampleItems was reset to 0,
     * therefore the popup will be shown for the new rule.
     *
     * Existing behavior is preserved when rule has not changed.
     * =========================================================
     */
    if (
        $customerChoice > 0
        &&
        $totalFreeSampleItems
            != $customerChoice
    ) {

        /*
         * Store the rule for addSample().
         *
         * This also covers the normal case where there are
         * no existing Free Samples and the popup is shown.
         */
        Session::put(
            'ShoppingCart.FreeSamplePendingRule',
            [
                'start' =>
                    $currentRuleStart,

                'end' =>
                    $currentRuleEnd,
            ]
        );


        $data = [
            'TotalListItems' =>
                $customerChoice,

            'Free_Sample_Products' =>
                $sampleProducts,
        ];


        $html =
            view(
                'popup.freesample-popup',
                $data
            )->render();


        return response()->json([
            'status' => 'success',
            'html' => $html,
        ]);
    }


    /*
     * =========================================================
     * No popup required
     * =========================================================
     */
    return response()->json([
        'status' => 'success',
        'html' => '',
    ]);
}
public function freeSampleAdd(Request $request)
{
    $productsId = $request->input('products_id');

    $message = $this->freeSampleService->addSample(
        $productsId
    );

    $this->checkoutService->refresh();

    return response()->json([
        'success' => true,
        'message' => $message,
    ]);
}


}
