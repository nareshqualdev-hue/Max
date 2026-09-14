<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
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
        protected CheckoutTotalsService $checkoutTotalsService
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
         * Free Gift popup selection uses the SAME new checkout
         * /cart/add route.
         *
         * The old Free Gift popup/template remains unchanged.
         * The selected product is inserted through FreeGiftService
         * instead of being treated as a normal paid cart product.
         */
        if (
            ($validated['free_gift'] ?? false) === true
        ) {
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

        $result = $this->cartService->addByProductId(
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

protected function resolveFreeGiftAfterCartChange(): array
{
    /*
     * =========================================================
     * CURRENT CART
     * =========================================================
     */
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
     * IMPORTANT:
     *
     * Do NOT depend only on FreeGiftAutoAdded.
     *
     * Old checkout Free Gifts can have:
     *
     * IS_Free_Gift = Yes
     *
     * without:
     *
     * FreeGiftAutoAdded = Yes
     *
     * Therefore detect the actual Free Gift line first.
     *
     * Free Samples are excluded.
     */
    $existingGiftCount = 0;

    $existingFreeGiftRuleIds = [];

    $existingFreeGiftIndexes = [];

    foreach (
        $cart as $index => $item
    ) {

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

        /*
         * Count the actual Free Gift quantity.
         */
        $existingGiftCount +=
            max(
                1,
                (int) (
                    $item['Qty'] ?? 1
                )
            );

        /*
         * Keep the exact cart index.
         *
         * This is used only if the existing Free Gift
         * needs to be removed because the rule changed.
         */
        $existingFreeGiftIndexes[] =
            $index;

        /*
         * Existing rule ID.
         *
         * Support both current and legacy key names.
         */
        $ruleId =
            (int) (
                $item['freeproductsid']
                ??
                $item['FreeGiftRuleId']
                ??
                0
            );

        if (
            $ruleId > 0
        ) {

            $existingFreeGiftRuleIds[
                $ruleId
            ] = true;
        }
    }

    /*
     * =========================================================
     * FREE GIFT RULE MUST USE SUBTOTAL
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
     *
     * Existing gift count is passed only for determining
     * how many gifts are already selected/available.
     */
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

    /*
     * =========================================================
     * RULE CHANGE DETECTION
     * =========================================================
     *
     * Example:
     *
     * Qty 8
     * $400
     * Rule = 280-400
     *
     * Qty 9
     * $450
     * Rule = 401-500
     *
     * Existing Free Gift exists.
     *
     * New Rule ID is not the existing Rule ID.
     *
     * Therefore old Free Gift must be removed.
     */
    $ruleChanged =
        $newRuleId > 0
        &&
        $existingGiftCount > 0
        &&
        (
            empty(
                $existingFreeGiftRuleIds
            )
            ||
            !isset(
                $existingFreeGiftRuleIds[
                    $newRuleId
                ]
            )
        );

    /*
     * =========================================================
     * REMOVE OLD FREE GIFT WHEN RULE CHANGES
     * =========================================================
     */
    $removedFreeGiftCount = 0;

    if (
        $ruleChanged
    ) {

        /*
         * First use the existing FreeGiftService removal
         * logic.
         *
         * This preserves the existing new-checkout behavior.
         */
        $removedFreeGiftCount =
            $this->freeGiftService
                ->removeAutoAddedFreeGifts();

        /*
         * -----------------------------------------------------
         * LEGACY FREE GIFT FALLBACK
         * -----------------------------------------------------
         *
         * If the service did not remove anything, the old
         * Free Gift may be a legacy cart item that does not
         * have FreeGiftAutoAdded = Yes.
         *
         * Old CartTrait removes the existing IS_Free_Gift
         * item when the selected rule changes.
         *
         * Therefore remove ONLY the old rule Free Gift
         * indexes captured above.
         */
        if (
            $removedFreeGiftCount === 0
            &&
            !empty(
                $existingFreeGiftIndexes
            )
        ) {

            $currentCart =
                $this->cartService
                    ->getCart();

            if (
                is_array($currentCart)
                &&
                isset(
                    $currentCart['Cart']
                )
                &&
                is_array(
                    $currentCart['Cart']
                )
            ) {
                $currentCart =
                    $currentCart['Cart'];
            }

            $filteredCart = [];

            foreach (
                $currentCart as $item
            ) {

                $isFreeGift =
                    (
                        ($item['IS_Free_Gift'] ?? 'No')
                        === 'Yes'
                    );

                $isFreeSample =
                    (
                        ($item['Is_Free_Sample'] ?? 'No')
                        === 'Yes'
                    );

                if (
                    !$isFreeGift
                    ||
                    $isFreeSample
                ) {

                    $filteredCart[] =
                        $item;

                    continue;
                }

                /*
                 * Identify the legacy Free Gift by its
                 * previous rule ID.
                 */
                $itemRuleId =
                    (int) (
                        $item['freeproductsid']
                        ??
                        $item['FreeGiftRuleId']
                        ??
                        0
                    );

                /*
                 * Only remove when:
                 *
                 * - it belongs to one of the old rule IDs
                 * - OR there was exactly one existing Free Gift
                 *   and its rule ID was not available
                 *
                 * The second condition supports the legacy cart
                 * structure where the rule ID is stored outside
                 * the cart line.
                 */
                $removeLegacyGift = false;

                if (
                    $itemRuleId > 0
                    &&
                    isset(
                        $existingFreeGiftRuleIds[
                            $itemRuleId
                        ]
                    )
                ) {

                    $removeLegacyGift = true;
                }

                if (
                    $itemRuleId === 0
                    &&
                    count(
                        $existingFreeGiftIndexes
                    ) === 1
                    &&
                    empty(
                        $existingFreeGiftRuleIds
                    )
                ) {

                    $removeLegacyGift = true;
                }

                if (
                    $removeLegacyGift
                ) {

                    $removedFreeGiftCount++;

                    Log::info(
                        'Legacy Free Gift Removed On Rule Change',
                        [
                            'productId' =>
                                $item['ProductID']
                                ?? null,

                            'sku' =>
                                $item['SKU']
                                ?? null,

                            'freeproductsid' =>
                                $item[
                                    'freeproductsid'
                                ]
                                ?? null,

                            'FreeGiftRuleId' =>
                                $item[
                                    'FreeGiftRuleId'
                                ]
                                ?? null,

                            'newRuleId' =>
                                $newRuleId,
                        ]
                    );

                    continue;
                }

                $filteredCart[] =
                    $item;
            }

            /*
             * Save the legacy-cleaned cart.
             */
            Session::put(
                'ShoppingCart.Cart',
                array_values(
                    $filteredCart
                )
            );
        }

        /*
         * -----------------------------------------------------
         * OLD GIFT REMOVED
         * -----------------------------------------------------
         */
        if (
            $removedFreeGiftCount > 0
        ) {

            /*
             * Refresh totals after the old Free Gift
             * has actually been removed from Session.
             */
            $this->checkoutService
                ->refresh('cart');

            /*
             * Read FINAL cart again.
             */
            $shoppingCart =
                $this->cartService
                    ->getCart();

            $cart =
                is_array($shoppingCart)
                && isset(
                    $shoppingCart['Cart']
                )
                && is_array(
                    $shoppingCart['Cart']
                )
                    ? $shoppingCart['Cart']
                    : (
                        is_array($shoppingCart)
                            ? $shoppingCart
                            : []
                    );

            /*
             * Read SUBTOTAL again.
             *
             * Free Gift rules always use SUBTOTAL.
             */
            $totalValue =
                (float) Session::get(
                    'ShoppingCart.SubTotal',
                    0
                );

            Log::info(
                'Free Gift Rule Re-Resolve After Old Gift Removal',
                [
                    'subtotal' =>
                        $totalValue,

                    'removedFreeGiftCount' =>
                        $removedFreeGiftCount,

                    'newRuleIdBeforeReResolve' =>
                        $newRuleId,

                    'cartCount' =>
                        count($cart),
                ]
            );

            /*
             * -------------------------------------------------
             * RE-RESOLVE NEW RULE
             * -------------------------------------------------
             *
             * Old gift is already gone.
             *
             * Therefore:
             *
             * existingGiftCount = 0
             */
            $decision =
                $this->freeGiftService
                    ->resolveEligibleGifts(
                        $cart,
                        $totalValue,
                        0,
                        0
                    );

            $decision[
                'ruleChanged'
            ] = true;

            $decision[
                'removedFreeGiftCount'
            ] =
                $removedFreeGiftCount;
        }
    }

    /*
     * =========================================================
     * QUALIFICATION LOST
     * =========================================================
     */
    if (
        ($decision['status'] ?? '')
            === 'no_rule'
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
                ->removeAutoAddedFreeGifts();

        if (
            $removed > 0
        ) {

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

            $decision[
                'status'
            ] =
                'qualification_lost';

            $decision[
                'shouldAutoAdd'
            ] = false;

            $decision[
                'shouldPopup'
            ] = false;

            $decision[
                'removedFreeGiftCount'
            ] =
                $removed;

            $decision[
                'cart'
            ] =
                is_array($finalCart)
                    ? $finalCart
                    : [];
        }
    }

    /*
     * =========================================================
     * POPUP HTML
     * =========================================================
     */
    if (
        ($decision['status'] ?? '')
            === 'popup'
        &&
        !empty(
            $decision['eligibleGifts']
        )
    ) {

        $popupGifts =
            is_array(
                $decision[
                    'eligibleGifts'
                ]
            )
                ? $decision[
                    'eligibleGifts'
                ]
                : [];

        $popupGifts =
            array_map(
                function ($gift) use (
                    $decision
                ) {

                    $gift =
                        is_array($gift)
                            ? $gift
                            : [];

                    $productId =
                        (int) (
                            $gift[
                                'products_id'
                            ]
                            ??
                            $gift[
                                'product_id'
                            ]
                            ??
                            $gift[
                                'ProductID'
                            ]
                            ??
                            0
                        );

                    $gift[
                        'products_id'
                    ] =
                        $productId;

                    $gift[
                        'product_name'
                    ] =
                        $gift[
                            'product_name'
                        ]
                        ??
                        $gift[
                            'ProductName'
                        ]
                        ??
                        $gift[
                            'name'
                        ]
                        ??
                        '';

                    $gift['sku'] =
                        $gift['sku']
                        ??
                        $gift['SKU']
                        ??
                        '';

                    $gift[
                        'short_description'
                    ] =
                        $gift[
                            'short_description'
                        ]
                        ??
                        $gift[
                            'ProductName_description'
                        ]
                        ??
                        '';

                    $gift['FoundSku'] =
                        $gift['FoundSku']
                        ??
                        'No';

                    $gift[
                        'free_gift_products_id'
                    ] =
                        (int) (
                            $gift[
                                'free_gift_products_id'
                            ]
                            ??
                            $gift[
                                'freeproductsid'
                            ]
                            ??
                            $gift[
                                'FreeGiftRuleId'
                            ]
                            ??
                            $decision[
                                'rule'
                            ]['id']
                            ??
                            0
                        );

                    $gift[
                        'freegift_add_count'
                    ] =
                        (int) (
                            $gift[
                                'freegift_add_count'
                            ]
                            ??
                            $decision[
                                'rule'
                            ][
                                'freegift_add_count'
                            ]
                            ??
                            $decision[
                                'remainingCount'
                            ]
                            ??
                            1
                        );

                    /*
                     * Legacy popup expects thumb_image.
                     */
                    $thumbImage =
                        $gift[
                            'thumb_image'
                        ]
                        ??
                        null;

                    if (
                        empty($thumbImage)
                        &&
                        !empty(
                            $gift['image']
                        )
                    ) {

                        $thumbImage =
                            rtrim(
                                (string) config(
                                    'global.PRD_THUMB_IMG_URL'
                                ),
                                '/'
                            )
                            . '/'
                            . ltrim(
                                (string) $gift['image'],
                                '/'
                            );
                    }

                    if (
                        empty($thumbImage)
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

                            if (
                                !empty(
                                    $productImage
                                )
                            ) {

                                $thumbImage =
                                    rtrim(
                                        (string) config(
                                            'global.PRD_THUMB_IMG_URL'
                                        ),
                                        '/'
                                    )
                                    . '/'
                                    . ltrim(
                                        (string) $productImage,
                                        '/'
                                    );
                            }

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
                        }
                    }

                    if (
                        empty(
                            $thumbImage
                        )
                    ) {

                        $thumbImage =
                            config(
                                'global.NO_IMAGE_THUMB'
                            );
                    }

                    $gift[
                        'thumb_image'
                    ] =
                        $thumbImage;

                    return $gift;

                },
                $popupGifts
            );

        $totalListItems =
            (int) (
                $popupGifts[0][
                    'freegift_add_count'
                ]
                ??
                $decision[
                    'rule'
                ][
                    'freegift_add_count'
                ]
                ??
                $decision[
                    'remainingCount'
                ]
                ??
                1
            );

        try {

            $decision[
                'popupHtml'
            ] =
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
                        $decision[
                            'rule'
                        ]
                        ?? null,

                    'eligibleGifts' =>
                        $popupGifts,
                ]
            );

            $decision[
                'popupHtml'
            ] =
                '';
        }
    }

    /*
     * =========================================================
     * AUTO ADD SINGLE GIFT
     * =========================================================
     */
    if (
        ($decision['status'] ?? '')
            === 'auto_add'
        &&
        !empty(
            $decision[
                'eligibleGifts'
            ]
        )
    ) {

        $gift =
            $decision[
                'eligibleGifts'
            ][0];

        $message =
            $this->freeGiftService
                ->addGift(
                    (int) (
                        $gift[
                            'products_id'
                        ]
                        ?? 0
                    ),
                    (int) (
                        $decision[
                            'rule'
                        ]['id']
                        ?? 0
                    ),
                    'No'
                );

        if (
            $message === ''
        ) {

            $decision[
                'status'
            ] =
                'auto_added';

            $decision[
                'shouldAutoAdd'
            ] = false;

            $decision[
                'shouldPopup'
            ] = false;

            $decision[
                'autoAddedProductId'
            ] =
                (int) (
                    $gift[
                        'products_id'
                    ]
                    ?? 0
                );

            $decision[
                'message'
            ] = '';

            $this->checkoutService
                ->refresh('cart');

            $decision[
                'cart'
            ] =
                $this->cartService
                    ->getCart();

            if (
                is_array(
                    $decision['cart']
                )
                &&
                isset(
                    $decision[
                        'cart'
                    ]['Cart']
                )
                &&
                is_array(
                    $decision[
                        'cart'
                    ]['Cart']
                )
            ) {

                $decision[
                    'cart'
                ] =
                    $decision[
                        'cart'
                    ]['Cart'];
            }

        } else {

            $decision[
                'status'
            ] =
                'auto_add_failed';

            $decision[
                'shouldAutoAdd'
            ] = false;

            $decision[
                'shouldPopup'
            ] = false;

            $decision[
                'autoAddError'
            ] =
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
        !isset(
            $decision['cart']
        )
        ||
        !is_array(
            $decision['cart']
        )
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

        $decision[
            'cart'
        ] =
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
                $decision[
                    'status'
                ] ?? null,

            'ruleChanged' =>
                $decision[
                    'ruleChanged'
                ] ?? false,

            'removedFreeGiftCount' =>
                $decision[
                    'removedFreeGiftCount'
                ] ?? 0,

            'shouldAutoAdd' =>
                $decision[
                    'shouldAutoAdd'
                ] ?? null,

            'shouldPopup' =>
                $decision[
                    'shouldPopup'
                ] ?? null,

            'rule' =>
                $decision[
                    'rule'
                ] ?? null,

            'eligibleGifts' =>
                $decision[
                    'eligibleGifts'
                ] ?? [],

            'remainingCount' =>
                $decision[
                    'remainingCount'
                ] ?? null,

            'popupHtmlExists' =>
                !empty(
                    $decision[
                        'popupHtml'
                    ]
                ),

            'popupHtmlLength' =>
                strlen(
                    $decision[
                        'popupHtml'
                    ] ?? ''
                ),
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
    $totalValue =
        max(
            0,
            $subTotal
            - $actualDiscount
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
        ) == 'Yes'
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
