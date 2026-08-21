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

class CheckoutCartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService,
        protected FreeGiftService $freeGiftService
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
                    ($validated['free_gift_one'] ?? true)
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
          if (isset($result['freeGift']['cart'])
					&&
					is_array($result['freeGift']['cart'])
				) {
					$result['cart'] =
						$result['freeGift']['cart'];
				}      
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
        $shoppingCart =
    $this->cartService->getCart();

$cart =
    $shoppingCart['Cart'] ?? [];

        if (empty($cart)) {
            return [
                'status' => 'no_rule',
                'shouldPopup' => false,
                'shouldAutoAdd' => false,
                'eligibleGifts' => [],
                'remainingCount' => 0,
            ];
        }

        $existingGiftCount = 0;

        foreach ($cart as $item) {
            if (
                ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
            ) {
                $existingGiftCount +=
                    (int) ($item['Qty'] ?? 1);
            }
        }

        /*
         * Checkout refresh has already recalculated the cart subtotal.
         * Use that current subtotal as the input to the migrated
         * Free Gift rule engine.
         */
        $totalValue =
            (float) Session::get(
                'ShoppingCart.SubTotal',
                0
            );

        $decision =
            $this->freeGiftService
                ->resolveEligibleGifts(
                    $cart,
                    $totalValue,
                    $existingGiftCount,
                    0
                );

        /*
         * =========================================================
         * FREE GIFT QUALIFICATION LOST
         * =========================================================
         *
         * Example:
         * Rule = $280 - $400
         * Cart total falls to $249
         *
         * The rule engine returns no_rule. In that case an existing
         * automatic Free Gift must not remain in the cart.
         *
         * Only the automatic rule-generated gift is removed by the
         * service. Free Samples and coupon-generated Free Gifts are
         * not removed here.
         */
        if (
            ($decision['status'] ?? '') === 'no_rule'
            &&
            $existingGiftCount > 0
        ) {
			
			Log::info(
    'Free Gift Removal Attempt',
    [
        'decisionStatus' =>
            $decision['status'] ?? null,

        'existingGiftCount' =>
            $existingGiftCount,

        'cartBeforeRemoval' =>
            $cart,
    ]
);

            $removedFreeGifts =
                $this->freeGiftService
                    ->removeAutoAddedFreeGifts();

            if ($removedFreeGifts > 0) {
                /*
                 * The previous checkout refresh happened before the
                 * Free Gift was removed. Refresh once more so the
                 * response contains the final cart/totals.
                 */
                $decision['checkout'] =
                    $this->checkoutService
                        ->refresh('cart');

                $decision['cart'] =
                    $this->cartService
                        ->getCart();

                $decision['status'] =
                    'qualification_lost';

                $decision['shouldAutoAdd'] =
                    false;

                $decision['shouldPopup'] =
                    false;

                $decision['removedFreeGiftCount'] =
                    $removedFreeGifts;
            }
        }
		
		Log::info('Free Gift Checkout Debug', [
    'totalValue' =>
        $totalValue,

    'existingGiftCount' =>
        $existingGiftCount,

    'decisionStatus' =>
        $decision['status'] ?? null,

    'shouldAutoAdd' =>
        $decision['shouldAutoAdd'] ?? null,

    'shouldPopup' =>
        $decision['shouldPopup'] ?? null,

    'rule' =>
        $decision['rule'] ?? null,

    'eligibleGifts' =>
        $decision['eligibleGifts'] ?? [],

    'remainingCount' =>
        $decision['remainingCount'] ?? null,
]);
		
			
        /*
         * Single eligible gift:
         * preserve the requested automatic-add behavior.
         *
         * Do not auto-add if the decision is not explicitly auto_add.
         */
        if (
            ($decision['status'] ?? '')
                === 'auto_add'
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
                            $gift['products_id'] ?? 0
                        ),
                        (int) (
                            $decision['rule']['id']
                            ?? $gift['free_gift_products_id']
                            ?? 0
                        ),
                        'No'
                    );

            /*
             * Refresh only after the gift was actually attempted.
             * This keeps totals/session state synchronized.
             */
            if ($message === '') {
                /*
                 * The gift was actually inserted.
                 *
                 * IMPORTANT:
                 * Return the refreshed cart as well as checkout totals.
                 * The frontend must use this refreshed state as the
                 * backend source of truth.
                 */
                $decision['status'] =
                    'auto_added';

                $decision['shouldAutoAdd'] =
                    false;

                $decision['shouldPopup'] =
                    false;

                $decision['autoAddedProductId'] =
                    (int) (
                        $gift['products_id'] ?? 0
                    );

                $decision['message'] =
                    '';

                $decision['checkout'] =
                    $this->checkoutService
                        ->refresh('cart');

                $decision['cart'] =
                    $this->cartService
                        ->getCart();

            } else {
                /*
                 * addGift() did not confirm a successful insert.
                 * Do not report the gift as added.
                 */
                $decision['status'] =
                    'auto_add_failed';

                $decision['shouldAutoAdd'] =
                    false;

                $decision['shouldPopup'] =
                    false;

                $decision['autoAddError'] =
                    $message
                    ?? 'Unable to add free gift.';
            }
        }

        /*
         * Keep the decision object self-contained.
         * The checkout.js layer can use:
         *   no_rule
         *   popup
         *   auto_added
         *   auto_add_failed
         *
         * No extra AJAX call is required just to discover the result.
         */
        return $decision;
    }

}
