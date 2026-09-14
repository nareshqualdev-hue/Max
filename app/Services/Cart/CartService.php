<?php

namespace App\Services\Cart;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Services\Discount\AutoDiscountService;
use App\Services\Discount\QuantityDiscountService;
use App\Services\Discount\BogoDiscountService;
use App\Services\Discount\CouponService;
use App\Services\Checkout\GiftCertificateService;
use App\Services\Discount\FreeSampleService;
use Illuminate\Support\Facades\Log;

class CartService
{
    public function __construct(
        protected CartCalculatorService $cartCalculatorService,
        protected CartSessionService $cartSessionService,
        protected CartStockService $stockService,
        protected CartProductService $productService,
        protected AutoDiscountService $autoDiscountService,
        protected QuantityDiscountService $quantityDiscountService,
        protected BogoDiscountService $bogoDiscountService,
        protected CouponService $couponService,
        protected GiftCertificateService $giftCertificateService,
        protected FreeSampleService $freeSampleService
    ) {
    }

    /**
     * Add product to cart.
     *
     * Product lookup, stock validation and SetProduct-equivalent
     * normalization are handled by CartStockService.
     */
    public function add(
        object $product,
        int $qty = 1,
        string $orderType = 'Website',
        string $cookie = 'No',
        string $giftWrap = 'No'
    ): array {
        $productId = (int) ($product->products_id ?? 0);
        $qty = $qty > 0 ? $qty : 1;

        if ($productId <= 0) {
            return [
                'success' => false,
                'StockInfo' => 1111,
                'message' => 'Product not available.',
            ];
        }

        $stock = $this->stockService->checkStock(
            $productId,
            $qty,
            'insert',
            $cookie,
            $orderType
        );

        if (($stock['StockInfo'] ?? 1111) !== 3333) {
            return [
                'success' => false,
                'StockInfo' => $stock['StockInfo'] ?? 1111,
                'availableStock' =>
                    $stock['availableStock'] ?? null,
                'message' =>
                    $this->stockMessage($stock),
            ];
        }

        $normalizedProduct = $stock['ProdInfo'] ?? null;

        if (!$normalizedProduct) {
            return [
                'success' => false,
                'StockInfo' => 1111,
                'message' => 'Product not available.',
            ];
        }

        $cart = $this->cartSessionService->getCart();

        foreach ($cart as $index => $item) {
            if (
                (int) ($item['ProductID'] ?? 0) === $productId
                && !isset($item['IS_Free_Gift'])
                && !isset($item['Is_Free_Sample'])
            ) {
                $existingQty = (int) ($item['Qty'] ?? 0);

                $newQty = $cookie === 'Yes'
                    ? (
                        $existingQty > $qty
                            ? $existingQty + $qty
                            : $existingQty
                    )
                    : $existingQty + $qty;

                /*
                 * Stock was checked against the final requested
                 * quantity by ProductCheckInStock-equivalent logic.
                 */
                $cart[$index]['Qty'] = $newQty;
                $cart[$index]['gift_wrap'] = $giftWrap;

                $cart[$index] =
                    $this->productService->applyPrice(
                        $cart[$index],
                        $normalizedProduct,
                        $newQty
                    );

                $this->cartSessionService->putCart($cart);
                $this->cartCalculatorService->calculateSubTotal();

                return [
                    'success' => true,
                    'updated' => true,
                    'cart' => $cart,
                ];
            }
        }

        $cartItem =
            $this->productService->buildCartItem(
                $normalizedProduct,
                $qty,
                $orderType
            );

        $cartItem =
            $this->productService->applyPrice(
                $cartItem,
                $normalizedProduct,
                $qty
            );

        $cartItem['gift_wrap'] = $giftWrap;

        $cart[] = $cartItem;

        $this->cartSessionService->putCart($cart);
        $this->cartCalculatorService->calculateSubTotal();
        $this->syncOmnisendCart();

        return [
            'success' => true,
            'updated' => false,
            'cart' => $cart,
        ];
    }

    /**
     * Add a product using only its product ID.
     *
     * The stock service performs the existing product lookup and
     * normalization. The normalized product is then passed to add().
     */
    public function addByProductId(
        int $productId,
        int $qty = 1,
        string $orderType = 'Website',
        string $cookie = 'No',
        string $giftWrap = 'No'
    ): array {
        $qty = $qty > 0 ? $qty : 1;

        $stock = $this->stockService->checkStock(
            $productId,
            $qty,
            'insert',
            $cookie,
            $orderType
        );

        if (($stock['StockInfo'] ?? 1111) !== 3333) {
            return [
                'success' => false,
                'StockInfo' => $stock['StockInfo'] ?? 1111,
                'availableStock' =>
                    $stock['availableStock'] ?? null,
                'message' =>
                    $this->stockMessage($stock),
            ];
        }

        $product = $stock['ProdInfo'] ?? null;

        if (!$product) {
            return [
                'success' => false,
                'StockInfo' => 1111,
                'message' => 'Product not available.',
            ];
        }

        return $this->add(
            $product,
            $qty,
            $orderType,
            $cookie,
            $giftWrap
        );
    }

    /**
     * Update quantity for an existing cart product.
     */
	/**
 * Update an existing product quantity.
 */
/**
 * Update an existing product quantity.
 */

public function update(
    int $productId,
    int $qty,
    string $giftWrap = 'No'
): array {

    $qty = $qty > 0 ? $qty : 1;

    /*
     * ---------------------------------------------------------
     * Products are stored in ShoppingCart.Cart.
     * ---------------------------------------------------------
     */
    $cart =
        $this->cartSessionService->getItems();

    $index = null;
    $orderType = 'Website';

    /*
     * ---------------------------------------------------------
     * Find normal cart product.
     *
     * Free Gift / Free Sample must never be updated through
     * normal quantity update.
     * ---------------------------------------------------------
     */
    foreach ($cart as $key => $item) {

        if (
            (int) ($item['ProductID'] ?? 0) === $productId
            && !isset($item['IS_Free_Gift'])
            && !isset($item['Is_Free_Sample'])
        ) {

            $index = $key;

            $orderType =
                $item['OrderType'] ?? 'Website';

            break;
        }
    }

    if ($index === null) {

        return [
            'success' => false,
            'Update' => 0,
            'message' =>
                'Product not found in cart.',
        ];
    }

    /*
     * ---------------------------------------------------------
     * Check stock for requested quantity.
     * ---------------------------------------------------------
     */
    $stock =
        $this->stockService->checkStock(
            $productId,
            $qty,
            'update',
            'No',
            $orderType
        );

    /*
     * ---------------------------------------------------------
     * Preserve retailer quantity limitation.
     * ---------------------------------------------------------
     */
    $userType = strtolower(
        trim(
            (string) Session::get(
                'eusertype',
                'retailer'
            )
        )
    );

    if (
        $userType === 'retailer'
        && $qty > 20
    ) {

        $message =
            'The maximum quantity you can add is 20 pieces.';

        Session::flash(
            'CartError',
            $message
        );

        Session::flash(
            'CartErrors',
            [$message]
        );

        return [
            'success' => false,
            'Update' => 0,
            'StockInfo' =>
                $stock['StockInfo'] ?? 3333,
            'availableStock' =>
                $stock['availableStock'] ?? null,
            'message' =>
                $message,
        ];
    }

    /*
     * ---------------------------------------------------------
     * Stock validation failed.
     * ---------------------------------------------------------
     */
    if (
        ($stock['StockInfo'] ?? 1111) !== 3333
    ) {

        if (
            $userType === 'retailer'
            && isset(
                $stock['availableStock']
            )
            && $stock['availableStock'] !== ''
            && (float) $stock['availableStock'] > 20
        ) {

            $message =
                'The maximum quantity you can add is 20 pieces.';

            Session::flash(
                'CartError',
                $message
            );

            Session::flash(
                'CartErrors',
                [$message]
            );

            return [
                'success' => false,
                'Update' => 0,
                'StockInfo' =>
                    $stock['StockInfo'] ?? 2222,
                'availableStock' =>
                    $stock['availableStock'],
                'message' =>
                    $message,
            ];
        }

        return [
            'success' => false,
            'Update' => 0,
            'StockInfo' =>
                $stock['StockInfo'] ?? 1111,
            'availableStock' =>
                $stock['availableStock'] ?? null,
            'message' =>
                $this->stockMessage($stock),
        ];
    }

    /*
     * ---------------------------------------------------------
     * IMPORTANT:
     *
     * Preserve the existing Free Sample BEFORE changing
     * quantity / recalculating discounts.
     *
     * This is the OLD Free Sample snapshot.
     *
     * We need its:
     *
     * - ProductID
     * - SKU
     * - ORGSAMPLESKU
     * - FreeSampleRuleStart
     * - FreeSampleRuleEnd
     * - all other existing Free Sample metadata
     *
     * for OLD RULE vs CURRENT RULE comparison.
     * ---------------------------------------------------------
     */
    $freeSampleItems = [];

    foreach ($cart as $cartKey => $cartItem) {

        if (
            ($cartItem['Is_Free_Sample'] ?? '') === 'Yes'
        ) {

            $freeSampleItems[$cartKey] =
                $cartItem;
        }
    }

    $hadFreeSample =
        !empty($freeSampleItems);

    Log::info(
        'CartService:update OLD FREE SAMPLE SNAPSHOT',
        [
            'had_free_sample' =>
                $hadFreeSample,

            'free_sample_count' =>
                count($freeSampleItems),

            'free_sample_items' =>
                $freeSampleItems,
        ]
    );

    /*
     * ---------------------------------------------------------
     * Normalized product returned by stock service.
     * ---------------------------------------------------------
     */
    $normalizedProduct =
        $stock['ProdInfo'] ?? null;

    /*
     * ---------------------------------------------------------
     * Update quantity.
     * ---------------------------------------------------------
     */
    $cart[$index]['Qty'] =
        $qty;

    $cart[$index]['gift_wrap'] =
        $giftWrap;

    /*
     * ---------------------------------------------------------
     * Recalculate product price / total.
     * ---------------------------------------------------------
     */
    if ($normalizedProduct) {

        $cart[$index] =
            $this->productService->applyPrice(
                $cart[$index],
                $normalizedProduct,
                $qty
            );

    } else {

        $cart[$index]['TotPrice'] =
            $this->numberFormat(
                $qty *
                (float) (
                    $cart[$index]['Price']
                    ?? 0
                )
            );
    }

    /*
     * ---------------------------------------------------------
     * Save cart.
     * ---------------------------------------------------------
     */
    Session::put(
        'ShoppingCart.Cart',
        $cart
    );

    /*
     * ---------------------------------------------------------
     * Recalculate subtotal FIRST.
     * ---------------------------------------------------------
     */
    $this->cartCalculatorService
        ->calculateSubTotal();

    /*
     * ---------------------------------------------------------
     * Recalculate all cart discounts.
     *
     * Free Sample eligibility must use the FINAL
     * discounted cart value.
     * ---------------------------------------------------------
     */
    $this->recalculateAfterCartMutation();

    /*
     * ---------------------------------------------------------
     * IMPORTANT:
     *
     * Some existing recalculation logic may remove/rebuild
     * Free Sample items.
     *
     * DO NOT use the current cart to determine OLD RULE.
     *
     * $freeSampleItems contains the exact Free Sample state
     * from BEFORE this quantity mutation.
     * ---------------------------------------------------------
     */

    $freeSampleRuleChanged = false;

    /*
     * ---------------------------------------------------------
     * ONLY run Free Sample sync when a Free Sample existed
     * BEFORE the normal product quantity update.
     *
     * We intentionally DO NOT check $hasFreeSample here.
     *
     * The current cart may not contain the sample anymore
     * because recalculation can remove/rebuild it.
     * ---------------------------------------------------------
     */
    if ($hadFreeSample) {

        $freeSampleRuleChanged =
            $this->freeSampleService
                ->syncFreeSamplesAfterCartMutation(
                    $freeSampleItems
                );
    }

    /*
     * ---------------------------------------------------------
     * Sync Omnisend after final cart state.
     * ---------------------------------------------------------
     */
    $this->syncOmnisendCart();

    /*
     * ---------------------------------------------------------
     * Final backend cart.
     * ---------------------------------------------------------
     */
    $finalCart =
        Session::get(
            'ShoppingCart.Cart',
            []
        );

    $hasFreeSampleAfterSync = false;

    foreach ($finalCart as $item) {

        if (
            ($item['Is_Free_Sample'] ?? '') === 'Yes'
        ) {

            $hasFreeSampleAfterSync = true;

            break;
        }
    }

    Log::info(
        'CartService:update FINAL CART',
        [
            'cart' =>
                $finalCart,

            'had_free_sample' =>
                $hadFreeSample,

            'has_free_sample_after_sync' =>
                $hasFreeSampleAfterSync,

            'free_sample_rule_changed' =>
                $freeSampleRuleChanged,
        ]
    );

    return [
        'success' => true,

        'Update' => 1,

        'free_sample_rule_changed' =>
            $freeSampleRuleChanged,

        'cart' =>
            $finalCart,
    ];
}


/* Recalculate discounts/certificates after a cart quantity change.
     *
     * This intentionally mirrors the proven legacy UpdateCart()
     * behavior without bringing ShoppingcartController/CartTrait
     * into the new checkout.
     */
    protected function recalculateAfterCartMutation(): void
    {
        /*
         * Gift Certificate must be synced first because the
         * applicable certificate amount depends on the new cart total.
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
		else
		{
			$this->giftCertificateService->sync();
		}

        /*
         * Legacy UpdateCart() explicitly re-applies the active
         * Yotpo Reward before SetupCart().
         */
        $rewardCode = trim(
            (string) Session::get(
                'ShoppingCart.YotpoRewardCode',
                ''
            )
        );

        if ($rewardCode !== '') {
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

            $this->couponService->apply(
                $rewardCode,
                $customerId
            );
        }

        /*
         * Same automatic-discount rules used by SetupCart().
         */
        if (
            config('Settings.AUTODISCOUNTFLAG') ===
            'Yes'
        ) {
            $this->autoDiscountService->apply();
        }

        if (
            config('Settings.QUANTITYDISCOUNTFLAG') ===
            'Yes'
        ) {
            $this->quantityDiscountService->apply();
        }

        /*
         * BOGO is also cart-dependent and must be recalculated
         * after quantity changes.
         */
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
			
    }

    /**
     * Remove cart item.
     *
     * The frontend sends ProductID as cart_id (for example 33032).
     * ShoppingCart.Cart contains the actual cart item array, while
     * CartSessionService::getCart() returns the complete ShoppingCart
     * wrapper. Therefore removal must operate on getItems() and save
     * only ShoppingCart.Cart.
     */
	public function remove(int $cartId): array
{
    $cart = $this->cartSessionService->getItems();

    /*
     * ---------------------------------------------------------
     * IMPORTANT:
     * Preserve OLD Free Samples BEFORE cart mutation.
     *
     * Recalculation can change/remove cart-dependent items.
     * We need the OLD Free Sample rule to compare against
     * the NEW eligibility after product removal.
     * ---------------------------------------------------------
     */
    $freeSampleItems = [];

    foreach ($cart as $item) {

        if (
            !is_array($item)
        ) {
            continue;
        }

        if (
            ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
        ) {
            $freeSampleItems[] = $item;
        }
    }

    $removeIndex = null;

    /*
     * Primary new-checkout contract:
     * cart_id is ProductID for a normal cart item.
     */
    foreach ($cart as $index => $item) {

        if (!is_array($item)) {
            continue;
        }

        if (
            (int) ($item['ProductID'] ?? 0)
            !== $cartId
        ) {
            continue;
        }

        /*
         * Free Gift / Free Sample must not be removed through
         * the normal product-remove fallback.
         */
        if (
            ($item['IS_Free_Gift'] ?? 'No') === 'Yes'
            ||
            ($item['Is_Free_Sample'] ?? 'No') === 'Yes'
        ) {
            //continue;
        }

        $removeIndex = $index;
        break;
    }

    /*
     * Backward compatibility:
     * if the caller sends the actual session-cart array index,
     * allow that as well, but never use it to remove a special
     * Free Gift / Free Sample line.
     */
    if (
        $removeIndex === null
        && isset($cart[$cartId])
        && is_array($cart[$cartId])
    ) {

        $candidate = $cart[$cartId];

        if (
            ($candidate['IS_Free_Gift'] ?? 'No') !== 'Yes'
            &&
            ($candidate['Is_Free_Sample'] ?? 'No') !== 'Yes'
        ) {
            $removeIndex = $cartId;
        }
    }

    if ($removeIndex === null) {
        return [
            'success' => false,
            'message' => 'Cart item not found.',
            'cart' => $cart,
        ];
    }

    $removed = $cart[$removeIndex];

    if (
        ($removed['IsYotpoFreeProduct'] ?? 'No') === 'Yes'
    ) {
        Session::forget(
            'ShoppingCart.YotpoFreeGiftCoupon'
        );
    }

    unset($cart[$removeIndex]);

    /*
     * Re-index the actual ShoppingCart.Cart array.
     */
    $cart = array_values($cart);

    /*
     * Preserve the existing legacy Yotpo-only-cart behavior.
     */
    if (
        count($cart) === 1
        &&
        ($cart[0]['IsYotpoFreeProduct'] ?? 'No') === 'Yes'
    ) {
        $cart = [];

        Session::forget(
            'ShoppingCart.YotpoFreeGiftCoupon'
        );
    }

    /*
     * IMPORTANT:
     * Do NOT call putCart($cart) here because putCart() stores
     * the complete ShoppingCart wrapper. We only changed the
     * ShoppingCart.Cart collection.
     */
    $this->cartSessionService->put(
        'Cart',
        $cart
    );

    /*
     * Always recalculate subtotal after a successful removal,
     * including when the cart becomes empty.
     */
    $this->cartCalculatorService->calculateSubTotal();

    $this->syncGiftCertificateTotals($cart);

    /*
     * Removing an item changes cart-dependent discounts and
     * Gift Certificate applicability.
     */
    $this->recalculateAfterCartMutation();

    /*
     * ---------------------------------------------------------
     * IMPORTANT:
     * Free Sample rule check MUST happen AFTER all discount
     * recalculation.
     *
     * The OLD Free Sample snapshot is passed because the
     * recalculation may have changed the current cart state.
     * ---------------------------------------------------------
     */
    $freeSampleRuleChanged = false;

    if (!empty($freeSampleItems)) {

        $freeSampleRuleChanged =
            $this->freeSampleService
                ->syncFreeSamplesAfterCartMutation(
                    $freeSampleItems
                );
    }

    /*
     * ---------------------------------------------------------
     * Omnisend sync remains after cart mutation.
     * ---------------------------------------------------------
     */
    $this->syncOmnisendCart();

    return [
        'success' => true,
        'message' => 'Item removed successfully.',

        'cart' =>
            $this->cartSessionService->getItems(),

        'removed' =>
            $removed,

        /*
         * Frontend uses this flag to decide whether the
         * Free Sample popup needs to be rendered again.
         */
        'free_sample_rule_changed' =>
            $freeSampleRuleChanged,
    ];
}
	
	
    /**
     * Empty the cart.
     */
    public function clear(): array
    {
        $this->cartSessionService->putCart([]);
        $this->syncGiftCertificateTotals([]);
        $this->cartCalculatorService->calculateSubTotal();
		$this->syncOmnisendCart();
        return [
            'success' => true,
            'cart' => [],
        ];
    }
	protected function syncGiftCertificateTotals(array $cart): void
	{
		$giftCertiTotal = null;
		$giftCertiCount = 0;

		foreach ($cart as $item) {
			if (
				($item['IsGiftCertificateItem'] ?? 'No') === 'Yes'
			) {
				$giftCertiTotal =
					($giftCertiTotal ?? 0)
					+ (float) ($item['TotPrice'] ?? 0);

				$giftCertiCount +=
					(int) ($item['Qty'] ?? 0);
			}
		}

		Session::put(
			'ShoppingCart.GiftCertiTotal',
			$giftCertiTotal
		);

		Session::put(
			'ShoppingCart.GiftCertiCount',
			$giftCertiCount
		);
	}
    public function getCart(): array
    {
        return $this->cartSessionService->getCart();
    }

    public function getItems(): array
    {
        return Session::get(
            'ShoppingCart.Cart',
            []
        );
    }

    public function hasItems(): bool
    {
        return count($this->getItems()) > 0;
    }

    protected function stockMessage(array $stock): string
    {
        if (($stock['StockInfo'] ?? 0) === 2222) {
            $available =
                $stock['availableStock'] ?? null;

            if ($available !== null) {
                return
                    'The maximum quantity you can add is '
                    . (int) $available
                    . ' pieces.';
            }

            return 'Requested quantity is not available.';
        }

        return 'Product is not available.';
    }

    protected function numberFormat(
        float|int|string $value
    ): float {
        if (function_exists('NumberFormat')) {
            return (float) NumberFormat($value);
        }

        return round((float) $value, 2);
    }
    protected function syncOmnisendCart(): void
	{
		if (!config('global.OMNISEND_CART')) {
			return;
		}

		OmanisendRequest(
			'setCart',
			[
				'CartData' => Session::get(
					'ShoppingCart',
					[]
				),
			]
		);
	}
}
